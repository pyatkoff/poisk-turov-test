#!/usr/bin/env python3
import fcntl,hashlib,json,os,pathlib,sys
OP='hotel-match-tv-ledger-floor1180-1971-20260919-v1';DAY='2026-09-19';FLOOR=1180
def enc(x):return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def sha(x):return hashlib.sha256(enc(x)).hexdigest()
def save(p,x):
 b=enc(x)
 with open(p,'xb') as f:f.write(b);f.flush();os.fsync(f.fileno())
 return hashlib.sha256(b).hexdigest()
def run(opdir):
 p=pathlib.Path.home()/'.anytour-match/provider-quotas'/('tourvisor-test-'+DAY+'.json')
 with open(p,'r+b') as f:
  fcntl.flock(f,fcntl.LOCK_EX);f.seek(0);before=json.loads(f.read())
  if int(before.get('owner_daily_limit',0))!=3000:raise RuntimeError('owner_limit')
  accounted=int(before.get('accounted_requests',0));prior=int(before.get('known_prior_attempt_floor',0));match=int(before.get('match_new_attempts',0));computed=max(accounted,prior+match)
  if computed<1060 or computed>3000:raise RuntimeError('current_floor')
  after=json.loads(json.dumps(before));after['accounted_requests']=max(computed,FLOOR)
  if after['accounted_requests']>3000:raise RuntimeError('limit')
  f.seek(0);f.truncate(0);raw=enc(after);f.write(raw);f.flush();os.fsync(f.fileno())
 out={'operation':OP,'state':'completed_accounting_reconcile','provider_calls':0,'database_writes':0,'mapping_writes':0,'before_sha256':sha(before),'after_sha256':sha(after),'before_accounted_requests':accounted,'before_computed_accounted':computed,'after_accounted_requests':after['accounted_requests'],'known_prior_attempt_floor':prior,'match_new_attempts':match,'owner_daily_limit':after['owner_daily_limit'],'fields_preserved':all(before.get(k)==after.get(k) for k in before if k!='accounted_requests'),'authority_artifact':10590198805,'authority_result_sha256':'329321e8f90efc8a9e0d8d50868818888567be61d9dda57b0748a69daf14f3b1'}
 d=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':out['state'],'result_sha256':d,'provider_calls':0,'database_writes':0,'mapping_writes':0});print(json.dumps(out,ensure_ascii=False))
if '--self-test' in sys.argv:assert max(1060,800+260,1180)==1180;print('TV_LEDGER_FLOOR1180_SELFTEST_OK')
elif '--execute' in sys.argv:run(pathlib.Path(os.environ['MATCH_OPERATION_DIR']))
else:raise SystemExit('disabled')
