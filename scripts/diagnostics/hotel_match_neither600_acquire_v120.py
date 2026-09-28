#!/usr/bin/env python3
import importlib.util,json,os,pathlib,sys
base=pathlib.Path(__file__).with_name('hotel_match_live30_dedicated_account_acquire_v3.py')
spec=importlib.util.spec_from_file_location('m120base',base);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.OP='hotel-match-neither600-acquire-1971-20260928-v120'
def scope(plan,plan_sha,offset,limit):
 exp=os.environ.get('MATCH_PLAN_SHA256','')
 if not exp or plan_sha!=exp or plan.get('operation')!='hotel-match-neither600-plan-1971-20260928-v119' or plan.get('state')!='completed_read_only_neither600_plan':raise RuntimeError('plan')
 rows=plan.get('rows');n=int(plan.get('scope_count',0))
 if n<500 or n>600 or not isinstance(rows,list) or len(rows)!=n or offset!=0 or limit!=n:raise RuntimeError('scope')
 seen=set()
 for x in rows:
  hid=int(x['tv_hotel_id']);z=x.get('missing_operator_ids')
  if hid in seen or x.get('gap_bucket')!='neither' or x.get('departure_date')!='2026-10-29' or not isinstance(z,list) or not z or any(type(v)is not int or v not in m.OPS for v in z):raise RuntimeError('row')
  seen.add(hid)
 return n,rows
m.continuation_scope=scope
if '--self-test' in sys.argv:print('M120_OK');raise SystemExit(0)
if '--execute' not in sys.argv:raise SystemExit('disabled')
p=json.loads(pathlib.Path(os.environ['MATCH_PLAN_PATH']).read_text());os.environ['MATCH_OFFSET']='0';os.environ['MATCH_LIMIT']=str(int(p['scope_count']));os.environ.setdefault('MATCH_CALL_CAP','1200')
raise SystemExit(m.execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
