#!/usr/bin/env python3
import argparse, hashlib, io, json, re, tarfile, zipfile
from pathlib import Path

OP='hotel-match-refresh55-partial-reconcile-1971-20260919-v1'
SOURCE_OP='hotel-match-refresh55-tv-context-1971-20260919-v1'
FAILED_ZIP_SHA='a06ad2c06bfc212c3047b5ed88e6d425717969ce628fb67281b8884c9447b35c'
ROUTER_ZIP_SHA='5aeeb43241fdaeb22ca072f19580b70d450c63b9ad5c7810490c541c1e4d14bd'
ROUTER_RESULT_SHA='d69b80d4c47dc1406b50184ff811bb8665bafda6a6e64bc407c1c130e874bb1e'
MIN_DATE='2026-09-20'
MAX_CALLS=120
TOUCHED_INCOMPLETE_IDS=[80333,69325]
TOUCHED_COUNTRY_ID=56
TOUCHED_START_HASH='88ee87c07c98b65af1df6a76af92b3c018ec15fa532ae9253f649bcee40d5aad'
UNISSUED_IDS=[113978,130752]

def sha(b): return hashlib.sha256(b).hexdigest()
def need(x,msg):
    if not x: raise RuntimeError(msg)
def jload(b): return json.loads(b.decode('utf-8-sig'))
def compact_json(v): return json.dumps(v,ensure_ascii=False,separators=(',',':'))

def safe_zip_bytes(path, expected_sha):
    b=Path(path).read_bytes(); need(sha(b)==expected_sha,'zip_sha')
    z=zipfile.ZipFile(io.BytesIO(b))
    names=set()
    for i in z.infolist():
        need(not i.is_dir(),'zip_dir')
        need(i.filename not in names,'zip_duplicate')
        names.add(i.filename)
        need(not i.filename.startswith('/') and '..' not in Path(i.filename).parts,'zip_path')
        need(i.file_size < 128_000_000,'zip_size')
    return z

def safe_tar_map(blob):
    out={}; tf=tarfile.open(fileobj=io.BytesIO(blob),mode='r:gz')
    for m in tf.getmembers():
        need(m.isfile(),'tar_nonfile'); need(m.name not in out,'tar_duplicate')
        need(not m.name.startswith('/') and '..' not in Path(m.name).parts,'tar_path')
        need(m.size<128_000_000,'tar_size')
        out[m.name]=tf.extractfile(m).read()
    return out

def num(v):
    try:
        i=int(v)
        return i if i>0 else None
    except: return None

def date(v):
    if not isinstance(v,str): return None
    v=v.strip()
    return v if re.fullmatch(r'20\d\d-\d\d-\d\d',v) else None

def operator(t):
    o=t.get('operator') if isinstance(t,dict) else None
    if isinstance(o,dict): return num(o.get('id')), str(o.get('name') or '')[:120]
    return num(t.get('operatorId')) if isinstance(t,dict) else None, ''

def choose_context(h):
    cand=[]
    for t in h.get('tours') or []:
        if not isinstance(t,dict): continue
        d=date(t.get('date') or t.get('checkin')); n=num(t.get('nights'))
        tid=str(t.get('id') or t.get('tourId') or '').strip()
        if not d or d<MIN_DATE or not n or not tid: continue
        oid,oname=operator(t)
        cand.append((d,abs(n-7),tid,n,oid,oname))
    if not cand: return None
    cand.sort(key=lambda x:(x[0],x[1],x[2]))
    d,_,tid,n,oid,oname=cand[0]
    return {'departure_date':d,'nights':n,'tour_id':tid,'operator_id':oid,'operator_name':oname,'departure_id':1,'adults':2,'children_count':0}

def start_hash(country,ids):
    p={'departureId':1,'countryId':country,'dateFrom':'2026-09-20','dateTo':'2026-10-10','nightsFrom':5,'nightsTo':14,'adults':2,'currency':'RUB','onlyCharter':False,'onlyDirect':False,'hotelIds':ids}
    return sha(compact_json(p).encode())

def reconcile(failed_zip,router_zip):
    rz=safe_zip_bytes(router_zip,ROUTER_ZIP_SHA)
    rb=rz.read('result.json'); need(sha(rb)==ROUTER_RESULT_SHA,'router_result_sha')
    router=jload(rb); rq=router.get('refresh_queue') or []; need(len(rq)==55,'refresh55')
    rmap={int(x['tv_hotel_id']):x for x in rq}; need(len(rmap)==55,'refresh_unique')

    fz=safe_zip_bytes(failed_zip,FAILED_ZIP_SHA)
    need('server.tgz' in fz.namelist(),'server_tgz')
    tm=safe_tar_map(fz.read('server.tgz'))
    reservation=jload(tm['reservation.json'])
    need(reservation.get('operation')==SOURCE_OP and reservation.get('state')=='reserved_before_provider_access','reservation')
    need(int(reservation.get('input_refresh55',0))==55 and int(reservation.get('tv_call_cap',0))==120,'reservation_scope')
    for absent in ('result.json','receipt.json','raw-manifest.json'):
        need(absent not in tm,'unexpected_terminal_file')
    err=tm.get('bootstrap-errors.log',b'').decode('utf-8','replace')
    need('RuntimeException: tv_cap' in err,'tv_cap_missing')
    calls=[json.loads(x) for x in tm['tv-calls.jsonl'].decode().splitlines() if x.strip()]
    need(len(calls)==MAX_CALLS and [x['seq'] for x in calls]==list(range(1,MAX_CALLS+1)),'call_ledger')
    allowed=re.compile(r'^/tours/search(?:/\d+(?:/status|/continue)?)?$')
    need(all(allowed.fullmatch(x['path']) for x in calls),'call_path')
    starts=[x for x in calls if x['path']=='/tours/search']; need(len(starts)==9,'start_count')
    need(starts[-1]['params_sha256']==TOUCHED_START_HASH,'ninth_start_hash')
    need(start_hash(TOUCHED_COUNTRY_ID,TOUCHED_INCOMPLETE_IDS)==TOUCHED_START_HASH,'ninth_binding')
    need('/tours/search/13663425536' in {x['path'] for x in calls},'ninth_fetch')
    need('/tours/search/13663425536/continue' in {x['path'] for x in calls},'ninth_continue_seen')

    batch_names=sorted([n for n in tm if re.fullmatch(r'raw-tv-batch-\d+\.json',n)], key=lambda n:int(re.search(r'\d+',n).group()))
    need(batch_names==[f'raw-tv-batch-{i}.json' for i in range(1,9)],'batch_set')
    completed=[]; misses=[]; requested=[]; row_ids=[]; operator_counts={}
    for bi,nm in enumerate(batch_names,1):
        d=jload(tm[nm]); want=[int(x) for x in d['requested_hotel_ids']]
        need(len(want)==len(set(want)) and all(x in rmap for x in want),'batch_want')
        rows=d.get('rows') or []; by={int(h['id']):h for h in rows}; need(len(by)==len(rows),'batch_rows_unique')
        need(set(by).issubset(want),'batch_whitelist')
        requested.extend(want); row_ids.extend(by)
        for hid in want:
            meta=rmap[hid]
            if hid not in by:
                misses.append({'tv_hotel_id':hid,'hotel_name':meta['hotel_name'],'country_name':meta['country_name'],'batch':bi,'country_id':int(d['country_id']),'state':'completed_not_returned','safe_to_query_same_context':False,'safe_to_write_now':False})
                continue
            h=by[hid]; ctx=choose_context(h); need(ctx is not None,'context_missing')
            key=f"{ctx['operator_id']}:{ctx['operator_name']}"; operator_counts[key]=operator_counts.get(key,0)+1
            completed.append({'tv_hotel_id':hid,'hotel_name':meta['hotel_name'],'country_name':meta['country_name'],'batch':bi,'country_id':int(d['country_id']),'search_id':int(d['search_id']),'observation_count':int(meta.get('observation_count',0)),'context':ctx,'raw_batch_sha256':sha(tm[nm]),'state':'completed_context','safe_to_query_anex_samo_context':True,'safe_to_write_now':False})
    need(len(requested)==51 and len(set(requested))==51,'completed_requested51')
    need(len(completed)==46 and len(misses)==5 and len(row_ids)==46,'completed_counts')
    need(not(set(requested)&set(TOUCHED_INCOMPLETE_IDS)) and not(set(requested)&set(UNISSUED_IDS)),'tail_overlap')
    need(set(requested)|set(TOUCHED_INCOMPLETE_IDS)|set(UNISSUED_IDS)==set(rmap),'partition55')
    touched=[{'tv_hotel_id':i,'hotel_name':rmap[i]['hotel_name'],'country_name':rmap[i]['country_name'],'country_id':56,'state':'touched_incomplete','search_id':13663425536,'same_context_no_replay':True,'safe_to_query_same_context':False,'safe_to_write_now':False} for i in TOUCHED_INCOMPLETE_IDS]
    unissued=[{'tv_hotel_id':i,'hotel_name':rmap[i]['hotel_name'],'country_name':rmap[i]['country_name'],'state':'unissued','requires_real_context_plan':True,'safe_to_write_now':False} for i in UNISSUED_IDS]
    return {'operation':OP,'state':'completed_offline_reconciliation','source_operation':SOURCE_OP,'source_failed_run':35457543624,'source_failed_artifact':10588765891,'source_failed_zip_sha256':FAILED_ZIP_SHA,'router_artifact':10588343448,'router_result_sha256':ROUTER_RESULT_SHA,'tourvisor_calls_accounted':120,'source_terminal_reason':'tv_cap','completed_batch_count':8,'completed_requested_count':51,'completed_context_count':46,'completed_not_returned_count':5,'touched_incomplete_count':2,'unissued_count':2,'operator_context_counts':operator_counts,'completed_contexts':completed,'completed_not_returned':misses,'touched_incomplete':touched,'unissued':unissued,'provider_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'no_replay_source_operation':True}

def main():
    ap=argparse.ArgumentParser(); ap.add_argument('--failed-zip');ap.add_argument('--router-zip');ap.add_argument('--output');ap.add_argument('--receipt');ap.add_argument('--self-test',action='store_true'); a=ap.parse_args()
    if a.self_test:
        need(start_hash(4,[60804,65807,9334,111076,9450,1693])=='2eadd416b795d57ebae30916d3deea14f32227c20f8ddcef07e3ccd3f437acb3','hash_fixture')
        need(start_hash(56,[80333,69325])==TOUCHED_START_HASH,'hash_uz')
        print('REFRESH55_PARTIAL_RECONCILE_SELFTEST_OK');return
    r=reconcile(a.failed_zip,a.router_zip); raw=(json.dumps(r,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode(); Path(a.output).write_bytes(raw); digest=sha(raw)
    if a.receipt:
        rec={'operation':OP,'state':r['state'],'result_sha256':digest,'provider_calls':0,'database_writes':0,'mapping_writes':0,'source_tourvisor_calls_accounted':r['tourvisor_calls_accounted'],'no_replay_source_operation':True}
        Path(a.receipt).write_text(json.dumps(rec,sort_keys=True)+'\n')
    print(json.dumps({k:r[k] for k in ['completed_context_count','completed_not_returned_count','touched_incomplete_count','unissued_count','tourvisor_calls_accounted']},ensure_ascii=False))
if __name__=='__main__': main()
