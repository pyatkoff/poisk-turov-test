#!/usr/bin/env python3
import importlib.util,json,hashlib,os,pathlib,sys
base=pathlib.Path(__file__).with_name('hotel_match_live30_common4_acquire_v2.py')
spec=importlib.util.spec_from_file_location('m',base);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.OP='hotel-match-current100-common4-acquire-1971-20260927-v80'
IDS=[950,960,963,967,974,980,984,986,987,988,994,1006,1013,1015,1037,1041,1049,1059,1063,1070,1071,1078,1090,1097,1104,1107,1123,1124,1136,1150,1151,1157,1164,1175,1181,1223,1229,1244,1247,1259,1264,1266,1267,1268,1278,1285,1286,1341,1343,1348,1361,1363,1386,1395,1404,1416,1417,1418,1422,1428,1435,1451,1454,1461,1463,1470,1477,1483,1491,1500,1507,1514,1520,1523,1527,1528,1533,1534,1540,1546,1552,1554,1557,1562,1572,1573,1576,1580,1584,1587,1589,1590,1597,1600,1606,1643,1648,1652,1670,1677]
TARGET=hashlib.sha256(json.dumps(sorted(IDS),separators=(',',':')).encode()).hexdigest()
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
