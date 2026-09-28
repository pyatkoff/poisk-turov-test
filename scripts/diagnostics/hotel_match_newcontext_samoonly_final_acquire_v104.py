#!/usr/bin/env python3
import importlib.util,os,pathlib,sys
base=pathlib.Path(__file__).with_name('hotel_match_live30_dedicated_account_acquire_v3.py');spec=importlib.util.spec_from_file_location('m104base',base);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.OP='hotel-match-newcontext-samoonly-final-acquire-1971-20260928-v104'
def scope(plan,plan_sha,offset,limit):
 if plan_sha!=os.environ.get('MATCH_PLAN_SHA256','') or plan.get('operation')!='hotel-match-newcontext-samoonly-final-plan-1971-20260928-v103' or plan.get('state')!='completed_read_only_final_samoonly_plan':raise RuntimeError('plan')
 rows=plan.get('rows');n=int(plan.get('scope_count',0))
 if not isinstance(rows,list) or len(rows)!=n or n<1 or n>200 or plan.get('prior_scope_excluded')!=600 or offset!=0 or limit!=n:raise RuntimeError('scope')
 if plan.get('tourvisor_account')!='TOURVISOR_ANEX_JWT' or plan.get('operator_filter_sent') is not False or plan.get('continue_calls')!=0 or plan.get('dates_calls')!=0:raise RuntimeError('contract')
 seen=set()
 for x in rows:
  h=int(x['tv_hotel_id']);z=x.get('missing_operator_ids')
  if h in seen or x.get('gap_bucket')!='samo_only' or int(x.get('departure_id',0))!=1 or x.get('departure_date')!='2026-10-15' or int(x.get('nights',0))!=7 or int(x.get('adults',0))!=2 or x.get('child_ages_signature','')!='' or not isinstance(z,list) or 13 not in z:raise RuntimeError('row')
  seen.add(h)
 return n,rows
m.continuation_scope=scope
if '--self-test' in sys.argv:print('M104_ACQUIRE_OK');raise SystemExit(0)
if '--execute' not in sys.argv:raise SystemExit('disabled')
import json
plan=json.loads(pathlib.Path(os.environ['MATCH_PLAN_PATH']).read_text());n=int(plan['scope_count']);os.environ['MATCH_OFFSET']='0';os.environ['MATCH_LIMIT']=str(n);os.environ.setdefault('MATCH_CALL_CAP','900')
raise SystemExit(m.execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
