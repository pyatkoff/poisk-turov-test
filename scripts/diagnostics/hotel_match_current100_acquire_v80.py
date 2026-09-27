#!/usr/bin/env python3
import importlib.util,json,hashlib,os,pathlib,sys
base=pathlib.Path(__file__).with_name('hotel_match_live30_common4_acquire_v2.py')
spec=importlib.util.spec_from_file_location('m',base);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.OP='hotel-match-current100-common4-acquire-1971-20260927-v81'
IDS=[1691,1693,1704,1709,2103,2119,2123,2158,2172,2178,2181,2182,2191,2200,2206,2210,2213,2214,2216,2223,2225,2231,2240,2252,2266,2270,2271,3407,3418,3422,3438,3445,3465,3469,5523,5526,5530,9242,9243,9251,9273,9283,9287,9312,9318,9320,9323,9325,9334,9352,9358,9427,9442,9443,9448,9450,9451,9454,15870,15877,15890,15896,15897,15902,15913,15916,16776,16790,16934,16938,16944,16945,17206,17282,17323,17338,17345,17371,17377,17383,17390,17442,17443,17489,17507,17562,17564,17603,17664,17671,17675,21641,21644,21669,21678,21679,21683,21684,21699,21701]
TARGET='873593ed012864ea2c97eee67994ff3a6102aa9dea3410416251fb0fcf5225b4'
m.PLAN_TARGET_SHA=TARGET
def scope(plan,plan_sha,offset,limit):
 if not isinstance(plan,dict) or plan.get('state')!='match_current100_ready':raise RuntimeError('plan_state')
 rows=plan.get('rows'); 
 if plan.get('scope_count')!=100 or not isinstance(rows,list) or len(rows)!=100:raise RuntimeError('plan_count')
 got=[int(x['tv_hotel_id']) for x in rows]
 if got!=IDS:raise RuntimeError('plan_ids')
 for x in rows:
  z=x.get('missing_operator_ids')
  if not isinstance(z,list) or not z or any(type(v) is not int or v not in m.OPS for v in z):raise RuntimeError('missing_lanes')
 if offset!=0 or limit!=100:raise RuntimeError('scope')
 return 100,rows
m.continuation_scope=scope
if '--self-test' in sys.argv:
 assert len(IDS)==100 and len(set(IDS))==100;print('M80_ACQUIRE_OK');raise SystemExit(0)
if '--execute' not in sys.argv:raise SystemExit('disabled')
os.environ['MATCH_OFFSET']='0';os.environ['MATCH_LIMIT']='100'
raise SystemExit(m.execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
