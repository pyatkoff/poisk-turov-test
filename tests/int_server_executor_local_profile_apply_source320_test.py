import importlib.util,json,base64,subprocess,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def load(p,n):s=importlib.util.spec_from_file_location(n,ROOT/p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
class Core:
 PREFIX='/run-int-server-v1 ';SHA_RE=__import__('re').compile(r'^[a-f0-9]{40}$')
 def __init__(self):self.REMOTE="def run_match942(stage, mode, offset, limit):\n pass\n    if mode=='match-tv942-write':\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if not isinstance(files,dict) or len(files)<20: fail('manifest')\n";self.bundle_source=lambda p:(b'',{});self.parse_command=lambda b:(_ for _ in ()).throw(ValueError('old'))
class T(unittest.TestCase):
 def setUp(self):self.m=load('scripts/deploy/int_server_executor_local_profile_apply_source320.py','m')
 def test_parser(self):
  c=Core();self.m.register_parser(c);sha='a'*40;d=c.parse_command(f'{c.PREFIX}{sha} {self.m.MODE} {self.m.OPERATION} {self.m.BATCH}');self.assertEqual(d['maximum_profile_writes'],320);self.assertEqual(d['provider_http_calls'],0)
 def test_php_scope(self):
  src=(ROOT/'scripts/diagnostics/local_profile_apply_source320_4191.php').read_text();self.assertNotIn('v2_data_tv_get',src);self.assertIn("array_chunk($expected,AnyTourProfileEnrichmentV1::MAX_BATCH,true)",src);self.assertIn("salvaged_verified",src)
  rows=[]
  for i in range(366):
   st='RETAINED_DELTA_PREPARED' if i<36 else ('SOURCE_MISSING' if i<356 else 'D1_OVERLAP_HELD');r={'anytourHotelId':i+1,'state':st}
   if st=='SOURCE_MISSING':r.update(localHotelId=1000+i,expectedProfileSha256='a'*64,expectedRevision=1,expectedAliasSha256='b'*64)
   rows.append(r)
  p={'schema_version':1,'operation_id':'int-andromeda-local-profile-plan-4191-20261001-v2','rows':rows};enc=base64.b64encode(json.dumps(p,separators=(',',':')).encode()).decode();code="<?php require "+json.dumps(str(ROOT/'scripts/diagnostics/local_profile_apply_source320_4191.php'))+";$p=json_decode(base64_decode('"+enc+"'),true);echo count(lps_scope($p));";r=subprocess.run(['php'],input=code,text=True,capture_output=True);self.assertEqual(r.returncode,0,r.stderr);self.assertEqual(r.stdout,'320')
if __name__=='__main__':unittest.main()
