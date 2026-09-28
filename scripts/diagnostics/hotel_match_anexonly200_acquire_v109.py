#!/usr/bin/env python3
import importlib.util,json,os,pathlib,sys
base=pathlib.Path(__file__).with_name('hotel_match_live30_dedicated_account_acquire_v3.py')
spec=importlib.util.spec_from_file_location('m109base',base);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.OP='hotel-match-anexonly200-acquire-1971-20260928-v109'
def scope(plan,plan_sha,offset,limit):
 exp=os.environ.get('MATCH_PLAN_SHA256','')
 if not exp or plan_sha!=exp: raise RuntimeError('plan_hash')
 if not isinstance(plan,dict) or plan.get('operation')!='hotel-match-anexonly200-plan-1971-20260928-v108' or plan.get('state')!='completed_read_only_anexonly200_plan': raise RuntimeError('plan_state')
 rows=plan.get('rows'); n=int(plan.get('scope_count',0))
 if n<1 or n>200 or not isinstance(rows,list) or len(rows)!=n: raise RuntimeError('plan_count')
 if plan.get('tourvisor_account')!='TOURVISOR_ANEX_JWT' or plan.get('operator_filter_sent') is not False or plan.get('continue_calls')!=0 or plan.get('dates_calls')!=0: raise RuntimeError('plan_contract')
 if offset!=0 or limit!=n: raise RuntimeError('scope')
 seen=set()
 for x in rows:
  hid=int(x['tv_hotel_id']);missing=x.get('missing_operator_ids')
  if hid in seen or x.get('gap_bucket')!='anex_only' or x.get('departure_date')!='2026-10-22' or int(x.get('nights',0))!=7 or int(x.get('adults',0))!=2 or x.get('child_ages_signature','')!='': raise RuntimeError('row_contract')
  if not isinstance(missing,list) or not missing or any(type(z) is not int or z not in (18,25,43) for z in missing): raise RuntimeError('missing_contract')
  seen.add(hid)
 return n,rows
m.continuation_scope=scope
if '--self-test' in sys.argv:
 print('M109_ACQUIRE_OK');raise SystemExit(0)
if '--execute' not in sys.argv: raise SystemExit('disabled')
plan=json.loads(pathlib.Path(os.environ['MATCH_PLAN_PATH']).read_text());os.environ['MATCH_OFFSET']='0';os.environ['MATCH_LIMIT']=str(int(plan['scope_count']));os.environ.setdefault('MATCH_CALL_CAP','900')
raise SystemExit(m.execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
