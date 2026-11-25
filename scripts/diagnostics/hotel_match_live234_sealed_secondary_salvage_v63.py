#!/usr/bin/env python3
import hashlib,json,os,pathlib,re,sys

OP='hotel-match-live234-sealed-secondary-salvage-1971-20260926-v63'
CHILDREN=[
 ('hotel-match-live234-tv-secondary-1971-20260923-o0-n78-v1',0,78),
 ('hotel-match-live234-tv-secondary-1971-20260923-o78-n78-v1',78,78),
 ('hotel-match-live234-tv-secondary-1971-20260923-o156-n78-v1',156,78),
]
OPS={18:'bgoperator',25:'operator_315',43:'operator_342'}

def need(v,m):
    if not v: raise RuntimeError(m)
def load(path,cap=32*1024*1024):
    p=pathlib.Path(path);need(p.is_file() and not p.is_symlink(),'file')
    need(p.stat().st_size<=cap,'cap')
    x=json.loads(p.read_text())
    need(isinstance(x,dict),'json_shape')
    return x
def sha(path): return hashlib.sha256(pathlib.Path(path).read_bytes()).hexdigest()
def pos(v):
    s=str(v).strip()
    return s if re.fullmatch(r'[1-9][0-9]{0,21}',s) else None
def edge_projection(e,child,rsha,current):
    if not isinstance(e,dict): return None
    tv=int(e.get('tv_hotel_id') or 0);op=int(e.get('operator_id') or 0)
    if tv not in current or op not in OPS:return None
    ns=str(e.get('namespace') or OPS[op])
    ids=[]
    for v in e.get('positive_native_candidates') or []:
        p=pos(v)
        if p is not None:ids.append(p)
    ids=sorted(set(ids),key=lambda x:int(x))
    out={'tv_hotel_id':tv,'operator_id':op,'namespace':ns,'state':str(e.get('state') or ''),
         'link_state':str(e.get('link_state') or ''),'positive_native_candidates':ids,
         'source_child':child,'source_result_sha256':rsha}
    for k in ('search_id_sha256','tour_id_sha256','operator_link_sha256'):
        v=e.get(k)
        if isinstance(v,str) and re.fullmatch(r'[0-9a-f]{64}',v):out[k]=v
    return out

def salvage(root,current):
    need(len(current)==234,'current_membership_count')
    root=pathlib.Path(root);need(root.is_dir() and not root.is_symlink(),'root')
    children=[];edges=[];semantic=set()
    for child,offset,limit in CHILDREN:
        d=root/child
        info={'child':child,'offset':offset,'limit':limit,'present':False}
        if not d.is_dir() or d.is_symlink():
            children.append(info);continue
        info['present']=True
        rp=d/'result.json';qp=d/'receipt.json'
        info['result_present']=rp.is_file() and not rp.is_symlink()
        info['receipt_present']=qp.is_file() and not qp.is_symlink()
        if not info['result_present'] or not info['receipt_present']:
            children.append(info);continue
        rsha=sha(rp);qsha=sha(qp);r=load(rp);q=load(qp,1024*1024)
        info.update(result_sha256=rsha,receipt_sha256=qsha,result_receipt_hash_match=(q.get('result_sha256')==rsha),
                    state=r.get('state'),receipt_state=q.get('state'),receipt_no_replay=q.get('no_replay'),
                    provider_calls=r.get('provider_calls'),searched_hotels=r.get('searched_hotels'),
                    scope_offset=r.get('scope_offset'),scope_count=r.get('scope_count'),operator_ids=r.get('operator_ids'))
        valid=(q.get('result_sha256')==rsha and r.get('frontier_count')==234 and r.get('scope_offset')==offset
               and r.get('scope_count')==limit and r.get('operator_ids')==[18,25,43]
               and r.get('database_writes')==0 and r.get('mapping_writes')==0)
        info['hash_valid_scope']=bool(valid)
        if valid:
            for e in r.get('edges') or []:
                p=edge_projection(e,child,rsha,current)
                if p is None:continue
                key=(p['tv_hotel_id'],p['operator_id'],p['namespace'],tuple(p['positive_native_candidates']),p['state'],p['link_state'])
                if key in semantic:continue
                semantic.add(key);edges.append(p)
        children.append(info)
    edges.sort(key=lambda x:(x['tv_hotel_id'],x['operator_id'],x['namespace'],x['positive_native_candidates']))
    single=[e for e in edges if e['state']=='detail_identity_verified' and e['link_state']=='captured_single_native' and len(e['positive_native_candidates'])==1]
    amb=[e for e in edges if e['state']=='detail_identity_verified' and e['link_state']=='captured_ambiguous_native']
    by={}
    for e in single:by[e['namespace']]=by.get(e['namespace'],0)+1
    return {'operation':OP,'state':'completed_server_read_only_sealed_secondary_salvage',
            'current_membership_count':len(current),'children':children,'current_membership_edges':len(edges),
            'detail_verified_single_native_count':len(single),'detail_verified_ambiguous_count':len(amb),
            'single_native_namespace_counts':dict(sorted(by.items())),'single_native_edges':single,'edges':edges,
            'provider_http_calls':0,'tourvisor_calls':0,'samo_calls':0,'anex_calls':0,'andromeda_calls':0,
            'database_reads':0,'database_writes':0,'mapping_writes':0,'server_mutation':0,'safe_to_write_now':False}

def selftest():
    e={'tv_hotel_id':10,'operator_id':25,'namespace':'operator_315','state':'detail_identity_verified',
       'link_state':'captured_single_native','positive_native_candidates':[123],
       'search_id_sha256':'a'*64,'tour_id_sha256':'b'*64,'operator_link_sha256':'c'*64}
    p=edge_projection(e,'child','d'*64,{10})
    need(p['positive_native_candidates']==['123'] and p['namespace']=='operator_315','edge')
    need(edge_projection(e,'child','d'*64,{11}) is None,'membership')
    print('MATCH_LIVE234_SEALED_SECONDARY_SALVAGE_V63_SELFTEST_OK')

if __name__=='__main__':
    if '--self-test' in sys.argv:selftest();sys.exit(0)
    need(len(sys.argv)==3 and sys.argv[1]=='--execute','usage')
    current={int(x) for x in json.loads(os.environ['MATCH_CURRENT_IDS_JSON'])}
    out=salvage(sys.argv[2],current)
    print(json.dumps(out,ensure_ascii=False,sort_keys=True,separators=(',',':')))
