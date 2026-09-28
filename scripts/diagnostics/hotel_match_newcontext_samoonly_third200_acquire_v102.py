#!/usr/bin/env python3
import importlib.util,os,pathlib,sys
base=pathlib.Path(__file__).with_name('hotel_match_live30_dedicated_account_acquire_v3.py');spec=importlib.util.spec_from_file_location('m102base',base);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.OP='hotel-match-newcontext-samoonly-third200-acquire-1971-20260928-v102'
def scope(plan,plan_sha,offset,limit):
 if plan_sha!=os.environ.get('MATCH_PLAN_SHA256','') or plan.get('operation')!='hotel-match-newcontext-samoonly-third200-plan-1971-20260928-v101' or plan.get('state')!='completed_read_only_third200_plan':raise RuntimeError('plan')
 rows=plan.get('rows'); 
 if plan.get('scope_count')!=200 or plan.get('prior_scope_excluded')!=400 or not isinstance(rows,list) or len(rows)!=200 or offset!=0 or limit!=200:raise RuntimeError('scope')
 if plan.get('tourvisor_account')!='TOURVISOR_ANEX_JWT' or plan.get('operator_filter_sent') is not False or plan.get('continue_calls')!=0 or plan.get('dates_calls')!=0:raise RuntimeError('contract')
 seen=set()
 for x in rows:
  h=int(x['tv_hotel_id']);z=x.get('missing_operator_ids')
  if h in seen or x.get('gap_bucket')!='samo_only' or int(x.get('departure_id',0))!=1 or x.get('departure_date')!='2026-10-15' or int(x.get('nights',0))!=7 or int(x.get('adults',0))!=2 or x.get('child_ages_signature','')!='' or not isinstance(z,list) or 13 not in z:raise RuntimeError('row')
  seen.add(h)
 return 200,rows
m.continuation_scope=scope
if '--self-test' in sys.argv:print('M102_ACQUIRE_OK');raise SystemExit(0)
if '--execute' not in sys.argv:raise SystemExit('disabled')
os.environ['MATCH_OFFSET']='0';os.environ['MATCH_LIMIT']='200';os.environ.setdefault('MATCH_CALL_CAP','900')
raise SystemExit(m.execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
