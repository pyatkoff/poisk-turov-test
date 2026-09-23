#!/usr/bin/env python3
import collections,hashlib,json,os,pathlib,re,sys,time

CHILD_RE=re.compile(r'^hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n([0-9]+)-v1$')
ALLOWED_OPS=[13,18,25,43]
ALLOWED_NS={13:'anex',18:'bgoperator',25:'operator_315',43:'operator_342'}

def need(v,why):
    if not v: raise RuntimeError(why)
def readj(p):
    p=pathlib.Path(p);need(p.is_file() and not p.is_symlink(),'json_missing')
    v=json.loads(p.read_text());need(isinstance(v,dict),'json_shape');return v
def enc(v): return (json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':'))+'\n').encode()
def save(p,v):
    p=pathlib.Path(p);raw=enc(v)
    with open(p,'xb') as f:
        need(f.write(raw)==len(raw),'short_write');f.flush();os.fsync(f.fileno())
    return hashlib.sha256(raw).hexdigest()
def id_digest(ids):
    return hashlib.sha256(json.dumps(sorted(int(x) for x in ids),separators=(',',':')).encode()).hexdigest()

def process_running(child):
    marker=('MATCH_CHILD_OPERATION='+child).encode();count=0
    for proc in pathlib.Path('/proc').iterdir():
        if not proc.name.isdigit() or int(proc.name)==os.getpid(): continue
        try: env=(proc/'environ').read_bytes()
        except Exception: continue
        if marker in env: count+=1
    return count

def execute(ops_root,source_sha):
    root=pathlib.Path(ops_root);need(root.is_dir(),'ops_root')
    matches=[p for p in root.iterdir() if p.is_dir() and not p.is_symlink() and CHILD_RE.fullmatch(p.name)]
    need(len(matches)==1,'child_count');d=matches[0];child=d.name
    need(process_running(child)==0,'child_still_running')
    result_path=d/'result.json';receipt_path=d/'receipt.json'
    need(not result_path.exists() and not receipt_path.exists(),'terminal_already_present')
    reservation=readj(d/'reservation.json');plan=readj(d/'plan.json');tvplan=readj(d/'tv-plan.json')
    need(reservation.get('operation')==child,'reservation_operation')
    expected=int(reservation.get('remaining_count',0) or 0);need(expected>0,'reservation_count')
    rows=plan.get('rows');need(isinstance(rows,list) and len(rows)==expected,'plan_rows')
    plan_ids=[int(x.get('tv_hotel_id',0)) for x in rows];need(all(x>0 for x in plan_ids) and len(set(plan_ids))==len(plan_ids),'plan_ids')
    groups=tvplan.get('groups');need(isinstance(groups,list) and groups,'tvplan_groups')
    need(tvplan.get('operator_ids')==ALLOWED_OPS,'tvplan_ops')

    requests=[];actions=collections.Counter();starts=[];started=set();latest=0.0
    for p in sorted(d.glob('tv-request-*.json')):
        q=readj(p);requests.append(q);latest=max(latest,p.stat().st_mtime)
        a=str(q.get('action',''));actions[a]+=1
        if a=='search_start':
            params=q.get('params');ids=params.get('hotelIds') if isinstance(params,dict) else None
            need(isinstance(ids,list) and ids,'start_ids')
            one=[int(x) for x in ids];need(all(x in plan_ids for x in one),'start_membership')
            need(not started.intersection(one),'start_overlap');started.update(one);starts.append(one)
    need(starts and len(starts)<=len(groups),'start_count')
    need(time.time()-latest>=120,'activity_not_stale')

    # Every persisted start must match the same ordered group prefix that the runner used.
    for i,one in enumerate(starts):
        g=groups[i];gids=[int(x) for x in g.get('hotel_ids',[])] if isinstance(g,dict) else []
        need(one==gids,'start_group_mismatch')

    edges=[]
    for p in sorted(d.glob('tv-edge-*.json')):
        e=readj(p);latest=max(latest,p.stat().st_mtime)
        need(e.get('state')=='detail_identity_verified','edge_state')
        op=int(e.get('operator_id',0));ns=str(e.get('namespace',''));tv=int(e.get('tv_hotel_id',0))
        need(op in ALLOWED_OPS and ALLOWED_NS[op]==ns and tv in started,'edge_identity')
        for k in ('search_id_sha256','tour_id_sha256'):
            need(re.fullmatch(r'[0-9a-f]{64}',str(e.get(k,''))) is not None,'edge_hash')
        if 'operator_link_sha256' in e:
            need(re.fullmatch(r'[0-9a-f]{64}',str(e.get('operator_link_sha256',''))) is not None,'edge_link_hash')
        edges.append(e)
    need(time.time()-latest>=120,'edge_activity_not_stale')

    batches=[]
    for p in sorted(d.glob('tv-batch-*-result.json')):
        b=readj(p);batches.append(b);latest=max(latest,p.stat().st_mtime)
    need(len(batches)<=len(starts),'batch_count')

    state_counts=collections.Counter(str(x.get('state','unknown')) for x in edges)
    link_counts=collections.Counter(str(x.get('namespace','none'))+'|'+str(x.get('link_state','none')) for x in edges)
    native={};single=collections.Counter()
    for e in edges:
        ids=e.get('positive_native_candidates') or []
        if e.get('link_state')=='captured_single_native' and isinstance(ids,list) and len(ids)==1:
            native.setdefault((e.get('namespace'),str(ids[0])),set()).add(int(e['tv_hotel_id']))
            single[str(e.get('operator_id'))]+=1
    unique=sum(1 for v in native.values() if len(v)==1)

    plan_raw=(d/'plan.json').read_bytes();plan_sha=hashlib.sha256(plan_raw).hexdigest()
    out={
      'operation':child,'state':'terminal_wrapper_timeout_salvaged_no_replay','reason':'github_ssh_wrapper_timeout_after_remote_provider_access',
      'source_sha':source_sha,'continuation_plan_sha256':plan_sha,
      'frontier_count':expected,'frontier_id_sha256':id_digest(plan_ids),'scope_offset':0,'scope_count':expected,
      'scope_target_id_sha256':id_digest(plan_ids),'planned_groups':len(groups),'completed_batches':len(batches),
      'searched_hotels':len(started),'incomplete_status_batches':sum(1 for b in batches if not b.get('search_complete',False)),
      'returned_targets':sum(int(b.get('returned_targets',0)) for b in batches),
      'returned_missing_operator_pairs':sum(int(b.get('returned_missing_operator_pairs',0)) for b in batches),
      'returned_operator_pairs':len(edges),'provider_calls':len(requests),'physical_http_attempts':len(requests),
      'operation_tariff_units':actions.get('search_start',0)+actions.get('search_continue',0)+actions.get('flights_actualization',0),
      'daily_tariff_units_after_local_ledger':None,'tourvisor_account':'TOURVISOR_ANEX_JWT','provider_day':'2026-09-24',
      'call_counts':dict(actions),'edge_state_counts':dict(state_counts),'link_state_counts':dict(link_counts),
      'single_native_chunk_unique_count':unique,'single_native_by_operator':dict(single),'edges':edges,'batches':batches,
      'operator_ids':ALLOWED_OPS,'continue_calls':0,'dates_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,
      'salvage':{'search_start_count':len(starts),'started_hotel_count':len(started),'started_hotel_ids_sha256':id_digest(started),
                 'unstarted_hotel_count':expected-len(started),'unstarted_hotel_ids_sha256':id_digest(set(plan_ids)-started),
                 'completed_batch_results':len(batches),'stale_seconds_minimum':120}
    }
    digest=save(result_path,out)
    save(receipt_path,{'operation':child,'state':out['state'],'result_sha256':digest,'continuation_plan_sha256':plan_sha,
                       'provider_calls':len(requests),'searched_hotels':len(started),'no_replay':True,
                       'database_writes':0,'mapping_writes':0,'salvaged_after_wrapper_timeout':True})
    print(json.dumps({'state':out['state'],'child_operation':child,'result_sha256':digest,
                      'searched_hotels':len(started),'provider_calls':len(requests),
                      'single_native_chunk_unique_count':unique,'single_native_by_operator':dict(single),
                      'unstarted_hotel_count':expected-len(started)},sort_keys=True))
    return 0

if __name__=='__main__':
    if '--self-test' in sys.argv:
        assert id_digest([2,1])==id_digest([1,2])
        print('MATCH_COMMON4_RESUME_SALVAGE_V1_SELFTEST_OK');sys.exit(0)
    need('--execute' in sys.argv,'disabled')
    sys.exit(execute(os.environ['MATCH_OPERATIONS_ROOT'],os.environ['MATCH_SOURCE_SHA']))
