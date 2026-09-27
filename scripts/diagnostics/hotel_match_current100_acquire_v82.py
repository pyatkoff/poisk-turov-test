#!/usr/bin/env python3
import importlib.util,json,os,pathlib,sys
base=pathlib.Path(__file__).with_name('hotel_match_live30_dedicated_account_acquire_v3.py')
spec=importlib.util.spec_from_file_location('m82base',base);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.OP='hotel-match-current100-dedicated-account-acquire-1971-20260927-v82'
def scope(plan,plan_sha,offset,limit):
 if not isinstance(plan,dict) or plan.get('state')!='match_current100_dedicated_ready':raise RuntimeError('plan_state')
 rows=plan.get('rows')
 if plan.get('scope_count')!=100 or not isinstance(rows,list) or len(rows)!=100:raise RuntimeError('plan_count')
 if plan.get('tourvisor_account')!='TOURVISOR_ANEX_JWT' or plan.get('operator_filter_sent') is not False:raise RuntimeError('account_contract')
 ids=[];seen=set()
 for x in rows:
  hid=int(x['tv_hotel_id']);z=x.get('missing_operator_ids')
  if hid in seen or not isinstance(z,list) or not z or any(type(v) is not int or v not in m.OPS for v in z):raise RuntimeError('row_contract')
  seen.add(hid);ids.append(hid)
 if offset!=0 or limit!=100:raise RuntimeError('scope')
 return 100,rows
m.continuation_scope=scope
if '--self-test' in sys.argv:
 assert m.OP.endswith('v82');print('M82_ACQUIRE_OK');raise SystemExit(0)
if '--execute' not in sys.argv:raise SystemExit('disabled')
os.environ['MATCH_OFFSET']='0';os.environ['MATCH_LIMIT']='100'
raise SystemExit(m.execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
