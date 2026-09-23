#!/usr/bin/env python3
import hashlib,json,os,pathlib,re,subprocess,sys,time

PLAN_OP='hotel-match-live30-common4-continuation-plan-1971-20260923-v9'
PLAN_COUNT=1349
PLAN_ID_SHA='ce464a7b71dc72cf425197c73c1b8a4770adaf586ee05d169f4c67f8fc43ccca'
OPS=[13,18,25,43]
STALE_SECONDS=120
CHILD_RE=re.compile(r'^hotel-match-live30-common4-continuation-(?:acquire-1971-20260923-c[0-9]+-n[0-9]+|resume-1971-20260924-r([0-9]+)-n[0-9]+)-v1$')

def need(v,why):
    if not v: raise RuntimeError(why)

def readj(path):
    p=pathlib.Path(path)
    need(p.is_file() and not p.is_symlink(),'json_missing')
    v=json.loads(p.read_text())
    need(isinstance(v,dict),'json_shape')
    return v

def enc(v):
    return json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()

def save(path,v):
    p=pathlib.Path(path);p.parent.mkdir(parents=True,exist_ok=True)
    raw=enc(v)+b'\n'
    with open(p,'xb') as f:
        need(f.write(raw)==len(raw),'short_write');f.flush();os.fsync(f.fileno())
    return hashlib.sha256(raw).hexdigest()

def id_digest(ids):
    return hashlib.sha256(json.dumps(sorted(int(x) for x in ids),separators=(',',':')).encode()).hexdigest()

def load_plan(ops_root):
    root=pathlib.Path(ops_root);d=root/PLAN_OP
    rp=d/'result.json';qp=d/'receipt.json'
    raw=rp.read_bytes();sha=hashlib.sha256(raw).hexdigest()
    r=json.loads(raw);q=readj(qp)
    need(q.get('result_sha256')==sha,'plan_receipt_hash')
    rows=r.get('rows')
    need(r.get('state')=='live30_common4_continuation_ready','plan_state')
    need(r.get('acquisition_target_count')==PLAN_COUNT,'plan_count')
    need(r.get('acquisition_target_id_sha256')==PLAN_ID_SHA,'plan_id_hash')
    need(isinstance(rows,list) and len(rows)==PLAN_COUNT,'plan_rows')
    need(r.get('provider_http_calls')==0 and r.get('database_writes')==0 and r.get('mapping_writes')==0,'plan_authority')
    ids=[]
    for row in rows:
        need(isinstance(row,dict),'plan_row')
        tv=int(row.get('tv_hotel_id',0));need(tv>0,'plan_tv_id');ids.append(tv)
    need(len(set(ids))==PLAN_COUNT and id_digest(ids)==PLAN_ID_SHA,'plan_identity')
    return r,sha

def search_start_count(directory):
    starts=0;latest=0.0
    for path in sorted(pathlib.Path(directory).glob('tv-request-*.json')):
        if not path.is_file() or path.is_symlink(): continue
        latest=max(latest,path.stat().st_mtime)
        value=readj(path)
        if value.get('action')=='search_start': starts+=1
    return starts,latest

def collect_attempted(ops_root,plan,now=None):
    now=time.time() if now is None else float(now)
    root=pathlib.Path(ops_root);attempted={};children=[];max_r=0
    plan_ids={int(x['tv_hotel_id']) for x in plan['rows']}
    for directory in sorted(root.iterdir(),key=lambda p:p.name):
        if not directory.is_dir() or directory.is_symlink(): continue
        m=CHILD_RE.fullmatch(directory.name)
        if not m: continue
        if m.group(1): max_r=max(max_r,int(m.group(1)))
        starts,latest=search_start_count(directory)
        terminal=(directory/'result.json').is_file() and (directory/'receipt.json').is_file()
        if starts:
            tvp=readj(directory/'tv-plan.json');groups=tvp.get('groups')
            need(isinstance(groups,list) and starts<=len(groups),'group_count')
            for group in groups[:starts]:
                ids=group.get('hotel_ids') if isinstance(group,dict) else None
                need(isinstance(ids,list) and ids,'group_shape')
                for raw in ids:
                    tv=int(raw);need(tv>0 and tv in plan_ids,'group_tv_id')
                    owner=attempted.get(tv)
                    need(owner is None or owner==directory.name,'attempted_overlap')
                    attempted[tv]=directory.name
        if not terminal and starts:
            for pattern in ('tv-response-*.json','tv-batch-*-result.json'):
                for path in directory.glob(pattern):
                    if path.is_file() and not path.is_symlink(): latest=max(latest,path.stat().st_mtime)
            need(latest>0 and now-latest>=STALE_SECONDS,'recent_nonterminal_child')
        children.append({'operation':directory.name,'search_start':starts,'terminal':terminal,
                         'last_activity_age_seconds':None if latest<=0 else int(now-latest)})
    return attempted,children,max_r

def select_remaining(plan,attempted,limit):
    need(1<=int(limit)<=300,'limit')
    rows=[x for x in plan['rows'] if int(x['tv_hotel_id']) not in attempted]
    selected=rows[:min(int(limit),len(rows))]
    return rows,selected

def execute(root,ops_root,parent_operation,source_sha,limit):
    root=pathlib.Path(root);ops_root=pathlib.Path(ops_root)
    need(root.is_dir() and ops_root.is_dir(),'runtime_root')
    need(re.fullmatch(r'[0-9a-f]{40}',source_sha or '') is not None,'source_sha')
    plan,plan_sha=load_plan(ops_root)
    attempted,children,max_r=collect_attempted(ops_root,plan)
    remaining,selected=select_remaining(plan,attempted,limit)
    if not selected:
        out={'state':'nothing_remaining','attempted_hotel_count':len(attempted),'remaining_before':0,
             'provider_calls':0,'database_writes':0,'mapping_writes':0,'children':children}
        print(json.dumps(out,sort_keys=True));return 0
    selected_ids=[int(x['tv_hotel_id']) for x in selected];selected_sha=id_digest(selected_ids)
    reduced=dict(plan)
    reduced.update(operation='hotel-match-live30-common4-continuation-remainder-plan-1971-20260924-v1',
                   source_sha=source_sha,acquisition_target_count=len(selected),
                   acquisition_target_id_sha256=selected_sha,rows=selected,
                   remainder_attempted_hotel_count=len(attempted),
                   remainder_remaining_before=len(remaining),
                   remainder_attempted_hotel_id_sha256=id_digest(attempted.keys()))
    r=max_r+1
    child=f'hotel-match-live30-common4-continuation-resume-1971-20260924-r{r}-n{len(selected)}-v1'
    child_dir=ops_root/child;need(not child_dir.exists(),'child_exists_no_replay');child_dir.mkdir(mode=0o700)
    plan_path=child_dir/'plan.json';plan_sha_reduced=save(plan_path,reduced)
    reservation={'operation':child,'state':'reserved_before_provider','source_sha':source_sha,
                 'parent_operation':parent_operation,'original_plan_sha256':plan_sha,
                 'reduced_plan_sha256':plan_sha_reduced,'attempted_hotel_count':len(attempted),
                 'remaining_before':len(remaining),'selected_count':len(selected),
                 'selected_target_id_sha256':selected_sha,'call_cap':5000,
                 'database_writes':0,'mapping_writes':0,'reserved_at':int(time.time())}
    save(child_dir/'reservation.json',reservation)
    runner=pathlib.Path(__file__).with_name('hotel_match_live30_common4_continuation_acquire_v10.py')
    need(runner.is_file(),'runner_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(root),'MATCH_OPERATION_DIR':str(child_dir),
         'MATCH_PLAN_PATH':str(plan_path),'MATCH_CHILD_OPERATION':child,'MATCH_OFFSET':'0',
         'MATCH_LIMIT':str(len(selected)),'MATCH_CALL_CAP':'5000','MATCH_SOURCE_SHA':source_sha}
    call=subprocess.run([sys.executable,str(runner),'--execute'],cwd=root,env=env,capture_output=True,text=True,timeout=1200)
    rp=child_dir/'result.json';qp=child_dir/'receipt.json'
    need(rp.is_file() and qp.is_file(),'terminal_missing')
    raw=rp.read_bytes();digest=hashlib.sha256(raw).hexdigest();res=json.loads(raw);receipt=readj(qp)
    need(receipt.get('result_sha256')==digest,'terminal_hash')
    need(res.get('continuation_plan_sha256')==plan_sha_reduced,'terminal_plan_hash')
    need(res.get('frontier_count')==len(selected) and res.get('frontier_id_sha256')==selected_sha,'terminal_frontier')
    need(res.get('scope_offset')==0 and res.get('scope_count')==len(selected),'terminal_scope')
    need(res.get('tourvisor_account')=='TOURVISOR_ANEX_JWT','terminal_account')
    need(res.get('operator_ids')==OPS and res.get('continue_calls')==0 and res.get('dates_calls')==0,'terminal_contract')
    need(res.get('database_writes')==0 and res.get('mapping_writes')==0,'terminal_writes')
    need(call.returncode==0 and res.get('state') in ('completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay'),'terminal_state')
    summary={k:v for k,v in res.items() if k not in ('edges','batches')}
    out={'state':res['state'],'child_operation':child,'result_sha256':digest,
         'attempted_hotel_count_before':len(attempted),'remaining_before':len(remaining),
         'selected_count':len(selected),'selected_target_id_sha256':selected_sha,
         'remaining_after_selected':len(remaining)-len(selected),'summary':summary}
    print(json.dumps(out,ensure_ascii=False,sort_keys=True));return 0

if __name__=='__main__':
    if '--self-test' in sys.argv:
        print('MATCH_LIVE30_COMMON4_REMAINDER_V1_SELFTEST_OK');sys.exit(0)
    need('--execute' in sys.argv,'disabled')
    sys.exit(execute(os.environ['ANYTOUR_ROOT'],os.environ['MATCH_OPERATIONS_ROOT'],
                     os.environ['MATCH_PARENT_OPERATION'],os.environ['MATCH_SOURCE_SHA'],
                     int(os.environ.get('MATCH_LIMIT','300'))))
