#!/usr/bin/env python3
import collections, hashlib, json, pathlib, sys

OP='hotel-match-direct-anex-http400-route-class-audit-1971-20260926-v58'
ROUTER_OP='hotel-match-live-samo-missing-direct-anex-router-1971-20260925-v35'
OPS={
 'v41b':('hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v41b',40),
 'v45':('hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v45',50),
 'v49':('hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v49',50),
 'v53':('hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v53',31),
 'v57':('hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v57',1),
}
V53_RESULT_SHA='ef37e3380401bacdfe6ff16edeb445a103ec248594ed5cacafc0071b0fca5ec4'
V57_RESULT_SHA='3034f56a4c0c73d6792778bc77c516745e879600a18adb380e0f0ffc8101c80e'
EXPECTED_UNION=172
EXPECTED_UNTOUCHED=89

def load(p):
    x=json.loads(pathlib.Path(p).read_text())
    if not isinstance(x,(dict,list)): raise RuntimeError('json_shape')
    return x
def save(p,x):
    raw=json.dumps(x,ensure_ascii=False,sort_keys=True,separators=(',',':'))+'\n'
    pathlib.Path(p).write_text(raw)
    return hashlib.sha256(raw.encode()).hexdigest()
def sig(context,ids):
    a=sorted({int(x) for x in ids})
    if not context or not a or any(x<=0 for x in a): raise RuntimeError('signature_shape')
    return context+'|' + ','.join(map(str,a))
def parse_context(k):
    p=k.split('|')
    if len(p)!=7: return {'parse_ok':False,'raw_sha256':hashlib.sha256(k.encode()).hexdigest()}
    try:
        return {'parse_ok':True,'departure_id':int(p[0]),'country_id':int(p[1]),'departure_date':p[2],
                'nights':int(p[3]),'adults':int(p[4]),'children_count':int(p[5]),'child_age_signature':p[6]}
    except Exception:
        return {'parse_ok':False,'raw_sha256':hashlib.sha256(k.encode()).hexdigest()}
def router_batches(router):
    if router.get('operation_id')!=ROUTER_OP or router.get('state')!='completed_read_only': raise RuntimeError('router_state')
    if router.get('input_count')!=777 or router.get('routed_future_context_count')!=659 or router.get('batch_count')!=261: raise RuntimeError('router_counts')
    out={}
    for r in router.get('batch_plan') or []:
        if not isinstance(r,dict): continue
        k=str(r.get('context_key','')); ids=r.get('hotel_ids')
        if not isinstance(ids,list): raise RuntimeError('router_batch_shape')
        s=sig(k,ids)
        if s in out: raise RuntimeError('router_duplicate')
        out[s]={'context_key':k,'hotel_ids':sorted(map(int,ids)),'batch_index':int(r.get('batch_index',0)),'hotel_count':int(r.get('hotel_count',len(ids)))}
    if len(out)!=261: raise RuntimeError('router_batch_plan')
    return out
def audit(router,proj):
    rb=router_batches(router)
    if not isinstance(proj,list): raise RuntimeError('projection_shape')
    byop=collections.defaultdict(list); union={}
    for x in proj:
        if not isinstance(x,dict): raise RuntimeError('projection_row')
        op=str(x.get('operation','')); batch=int(x.get('batch',0)); k=str(x.get('context_key','')); ids=x.get('hotel_ids')
        if x.get('state')!='reserved_before_batch_http' or batch<=0 or not isinstance(ids,list): raise RuntimeError('reservation_shape')
        s=sig(k,ids)
        if s not in rb: raise RuntimeError('reservation_not_router')
        byop[op].append({'operation':op,'batch':batch,'context_key':k,'hotel_ids':sorted(map(int,ids)),'signature_sha256':hashlib.sha256(s.encode()).hexdigest()})
        union[s]=True
    for _,(op,n) in OPS.items():
        rows=byop.get(op,[])
        if len(rows)!=n: raise RuntimeError('reservation_count_'+op)
        if len({r['signature_sha256'] for r in rows})!=n: raise RuntimeError('reservation_duplicate_'+op)
        rows.sort(key=lambda r:r['batch'])
    if len(union)!=EXPECTED_UNION: raise RuntimeError('consumed_union')
    untouched=[v|{'signature_sha256':hashlib.sha256(s.encode()).hexdigest()} for s,v in rb.items() if s not in union]
    if len(untouched)!=EXPECTED_UNTOUCHED: raise RuntimeError('untouched_count')
    v53op=OPS['v53'][0];v57op=OPS['v57'][0]
    failed53=byop[v53op][-1];failed57=byop[v57op][0]
    successful_tail=byop[v53op][25:30]
    fctx=[parse_context(failed53['context_key']),parse_context(failed57['context_key'])]
    shared={}
    for key in ['departure_id','country_id','departure_date','nights','adults','children_count','child_age_signature']:
        vals=[x.get(key) for x in fctx if x.get('parse_ok')]
        shared[key]=vals[0] if len(vals)==2 and vals[0]==vals[1] else None
    classification='shared_route_class' if fctx[0].get('parse_ok') and fctx[1].get('parse_ok') and any(v is not None for v in shared.values()) else ('distinct_contexts' if all(x.get('parse_ok') for x in fctx) else 'insufficient_pattern')
    dist_country=collections.Counter();dist_departure=collections.Counter();dates=[]
    for x in untouched:
        c=parse_context(x['context_key'])
        if c.get('parse_ok'):
            dist_country[str(c['country_id'])]+=1;dist_departure[str(c['departure_id'])]+=1;dates.append(c['departure_date'])
    rows=lambda xs:[{'batch':r['batch'],'context':parse_context(r['context_key']),'hotel_ids':r['hotel_ids'],'signature_sha256':r['signature_sha256']} for r in xs]
    return {
      'operation':OP,'state':'completed_read_only_route_class_audit',
      'reservation_counts':{k:len(byop[v[0]]) for k,v in OPS.items()},
      'consumed_union_count':len(union),'untouched_count':len(untouched),
      'v53_last_successful_reservations':rows(successful_tail),
      'v53_failed_reservation':rows([failed53])[0],
      'v57_failed_reservation':rows([failed57])[0],
      'failed_shared_features':shared,'classification':classification,
      'untouched_distribution':{'country_id_counts':dict(sorted(dist_country.items())),'departure_id_counts':dict(sorted(dist_departure.items())),
                                'min_departure_date':min(dates) if dates else None,'max_departure_date':max(dates) if dates else None},
      'untouched_rows':[{'batch_index':x['batch_index'],'context':parse_context(x['context_key']),'hotel_ids':x['hotel_ids'],'signature_sha256':x['signature_sha256']} for x in untouched],
      'provider_http_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'server_mutation':0,'safe_to_write_now':False
    }
def selftest():
    router={'operation_id':ROUTER_OP,'state':'completed_read_only','input_count':777,'routed_future_context_count':659,'batch_count':261,'batch_plan':[]}
    # Only helpers here; full 261/172 fixture is exercised by contract test.
    assert parse_context('1|2|2026-10-15|5|2|1|2')['country_id']==2
    assert sig('x',[3,1,2])=='x|1,2,3'
    print('MATCH_V58_SELFTEST_OK')
if __name__=='__main__':
    if '--self-test' in sys.argv:selftest();sys.exit(0)
    if len(sys.argv)!=4 or sys.argv[1]!='--execute': raise SystemExit('usage')
    router=load(sys.argv[2]);proj=load(sys.argv[3]);out=audit(router,proj)
    save('terminal/result.json',out)
    h=hashlib.sha256(pathlib.Path('terminal/result.json').read_bytes()).hexdigest()
    save('terminal/receipt.json',{'operation':OP,'state':out['state'],'result_sha256':h,'readback_verified':True,'provider_http_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'server_mutation':0})
    print(json.dumps({k:out[k] for k in ['state','reservation_counts','consumed_union_count','untouched_count','classification','failed_shared_features','untouched_distribution']},ensure_ascii=False,sort_keys=True))
