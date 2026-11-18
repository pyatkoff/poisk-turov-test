"""Finite reviewed queued attempts only. No names, age, cancellation or launch."""
import base64,hashlib,json,re,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parent
ALLOW_SHA='7c233b8336fbc2d8349f90179f9f0e65a01ebac6d29878e0093afedfad71e49e'
STATUSES=('in_progress','queued','waiting','pending','requested')
REPOS=('pyatkoff/max-search-bot','pyatkoff/poisk-turov-test')
KEYS=('id','head_sha','event','path','run_attempt','status','workflow_id','head_branch','referenced_workflows')
def need(v,m):
 if not v:raise RuntimeError(m)
def sha(b):return hashlib.sha256(b).hexdigest()
def strict(a,b):return type(a) is type(b) and (set(a)==set(b) and all(strict(a[k],b[k]) for k in a) if isinstance(a,dict) else len(a)==len(b) and all(strict(x,y) for x,y in zip(a,b)) if isinstance(a,list) else a==b)
def frozen(base=ROOT):
 raw=(base/'allowlist.json').read_bytes();need(sha(raw)==ALLOW_SHA,'queued_allowlist_changed');v=json.loads(raw)
 for p in v['classification_proofs']:
  f=base/p['path'];need(not f.is_symlink() and sha(f.read_bytes())==p['sha256'],'queued_source_proof_missing_or_changed')
 need(len(v['runs'])==26 and len({(r['repository'],r['id']) for r in v['runs']})==26,'allowlist_scope_changed')
 return v

def artifact_status(repo,artifact_id):
 # Explicit authenticated GET. Never convert a generic exception into absence.
 p=subprocess.run(['gh','api','--method','GET','--include','repos/'+repo+'/actions/artifacts/'+str(artifact_id)],capture_output=True,timeout=40)
 raw=p.stdout.decode('utf-8','strict');m=re.match(r'HTTP/[^ \r\n]+ ([0-9]{3})[^\r\n]*\r?\n',raw)
 need(m is not None,'artifact_http_status_unknown');status=int(m[1])
 need((status in (404,410) and p.returncode==1) or (status==200 and p.returncode==0),'artifact_http_status_unaccepted')
 return status

def verify(get, own_repo, own_id, own_head, expected_mains, base=ROOT, status_get=artifact_status):
 proof=frozen(base);need(set(expected_mains)==set(REPOS) and all(isinstance(x,str) and re.fullmatch('[a-f0-9]{40}',x) for x in expected_mains.values()),'queue_current_main_unbound')
 calls=0
 def read(repo,path):
  nonlocal calls
  calls+=1;need(calls<200,'queue_read_budget_exceeded');return get(repo,path)
 for repo in REPOS:need(read(repo,'git/ref/heads/main')['object']['sha']==expected_mains[repo],'queue_current_main_moved')
 allowed={(r['repository'],r['id']):r for r in proof['runs']};seen=set();accepted=[];initial_pages={}
 for repo in REPOS:
  for status in STATUSES:
   page=read(repo,'actions/runs?status='+status+'&per_page=100');runs=page.get('workflow_runs')
   need(type(page.get('total_count')) is int and isinstance(runs,list) and page['total_count']==len(runs)<=100,'runs_truncated')
   initial_pages[(repo,status)]={r.get('id'): {k:r[k] for k in KEYS if k in r} for r in runs}
   for r in runs:
    need(isinstance(r,dict) and type(r.get('id')) is int,'run_shape')
    key=(repo,r['id']);need(key not in seen,'duplicate_run');seen.add(key)
    if repo==own_repo and r['id']==own_id:
     need(status=='in_progress' and r.get('status')=='in_progress' and r.get('head_sha')==own_head and type(r.get('run_attempt')) is int and r['run_attempt']==1,'own_run_changed');continue
    need(status=='queued' and key in allowed,'other_GHA_active_or_unknown');p=allowed[key]
    need(all(k in r and strict(r[k],p[k]) for k in KEYS),'queued_identity_changed')
    direct=read(repo,'actions/runs/'+str(r['id']))
    need(all(k in direct and strict(direct[k],p[k]) for k in KEYS),'queued_direct_identity_changed')
    jobs=read(repo,'actions/runs/'+str(r['id'])+'/jobs?per_page=100')
    need(type(jobs.get('total_count')) is int and jobs['total_count']==0 and jobs.get('jobs')==[],'queued_jobs_not_empty')
    if p['classification']=='immutable_main_guard_before_native_requires_current_main_not_old_head':
     need(expected_mains[repo]!=p['head_sha'],'queued_main_guard_not_blocked')
    if p['classification']=='exact_required_artifact_unavailable':
     calls+=1;need(calls<200,'queue_read_budget_exceeded')
     status=status_get(repo,p['artifact_id']);need(type(status) is int and status in (404,410),'required_artifact_not_proven_unavailable')
    accepted.append(p['id'])
 # Every reviewed transitive workflow/helper source is checked at immutable ref.
 for s in proof['source_proofs']:
  v=read(s['repository'],'contents/'+s['path']+'?ref='+s['ref'])
  need(v.get('encoding')=='base64' and v.get('type')=='file' and v.get('sha')==s['blob_sha'],'queued_source_identity_changed')
  raw=base64.b64decode(''.join(v['content'].split()),validate=True)
  need(len(raw)<=1000000 and sha(raw)==s['sha256'] and hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()==s['blob_sha'],'queued_source_changed')
 # Source reads can take time. No added/removed/activated or changed attempt may hide in that window.
 for repo in REPOS:
  for status in STATUSES:
   page=read(repo,'actions/runs?status='+status+'&per_page=100');runs=page.get('workflow_runs')
   need(type(page.get('total_count')) is int and isinstance(runs,list) and page['total_count']==len(runs)<=100,'final_runs_truncated')
   need(all(isinstance(r,dict) and type(r.get('id')) is int for r in runs),'final_run_shape')
   current={r['id']:{k:r[k] for k in KEYS if k in r} for r in runs}
   need(len(current)==len(runs) and strict(current,initial_pages[(repo,status)]),'final_queue_snapshot_changed')
 # Main must remain the exact externally bound current source, not only differ from old head.
 for repo in REPOS:need(read(repo,'git/ref/heads/main')['object']['sha']==expected_mains[repo],'queue_current_main_moved')
 return {'ignored_exact_queued_ids':sorted(accepted),'allowlist_sha256':ALLOW_SHA,'read_calls':calls,'age_based_exclusions':False}
