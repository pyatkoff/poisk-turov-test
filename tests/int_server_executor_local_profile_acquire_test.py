import importlib.util,json,base64,subprocess,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def load(p,n):s=importlib.util.spec_from_file_location(n,ROOT/p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
class Core:
 PREFIX='/run-int-server-v1 ';SHA_RE=__import__('re').compile(r'^[a-f0-9]{40}$')
 def __init__(self):
  self.REMOTE="def run_match942(stage, mode, offset, limit):\n    pass\n    if mode=='match-tv942-write':\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if not isinstance(files,dict) or len(files)<20: fail('manifest')\n";self.bundle_source=lambda p:(b'',{});self.parse_command=lambda b:(_ for _ in ()).throw(ValueError('old'))
class AcquireContract(unittest.TestCase):
 def setUp(self):self.m=load('scripts/deploy/int_server_executor_local_profile_acquire.py','lac')
 def test_parser_is_exact320(self):
  c=Core();self.m.register_parser(c);sha='a'*40;cmd=c.parse_command(f'{c.PREFIX}{sha} {self.m.MODE} {self.m.OPERATION} {self.m.BATCH}')
  self.assertEqual(cmd['maximum_hotel_http_calls'],320);self.assertEqual(cmd['maximum_profile_writes'],0)
  with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{sha} {self.m.MODE} {self.m.OPERATION.replace("-v1","-v2")} {self.m.BATCH}')
  rb=c.parse_command(f'{c.PREFIX}{sha} {self.m.READBACK_MODE} {self.m.READBACK_OPERATION} {self.m.READBACK_BATCH}')
  self.assertEqual(rb['maximum_hotel_http_calls'],0);self.assertEqual(rb['maximum_profile_writes'],0)
  self.m.activate(c,rb);self.assertIn('local-profile-acquire-readback-4191',c.REMOTE);self.assertEqual(c.bundle_source,self.m.bundle_source)
 def test_remote_contract(self):
  c=Core();self.m.register_parser(c);cmd={'source_sha':'a'*40,'mode':self.m.MODE,'operation_id':self.m.OPERATION,'batch':self.m.BATCH,'maximum_hotel_http_calls':320,'maximum_profile_writes':0};self.m.activate(c,cmd)
  self.assertIn("local-profile-acquire-4191",c.REMOTE);self.assertIn("rate_interval_ms",self.m.REMOTE_HANDLER)
 def test_php_scope_and_supplier_guards(self):
  src=(ROOT/'scripts/diagnostics/local_profile_acquire_4191.php').read_text()
  self.assertIn("LAC_INTERVAL_MS=550",src);self.assertIn("TOURVISOR_HTTP_MAX_ATTEMPTS=1",src);self.assertIn("v2_data_tv_get('/hotels/'.$id)",src);self.assertNotIn("/tours/",src)
  rows=[]
  for i in range(366):
   state='RETAINED_DELTA_PREPARED' if i<36 else ('SOURCE_MISSING' if i<356 else 'D1_OVERLAP_HELD');r={'anytourHotelId':i+1,'state':state}
   if state=='SOURCE_MISSING':r.update(localHotelId=1000+i,expectedProfileSha256='a'*64,expectedRevision=1,expectedAliasSha256='b'*64)
   rows.append(r)
  plan={'schema_version':1,'operation_id':'int-andromeda-local-profile-plan-4191-20261001-v2','rows':rows};enc=base64.b64encode(json.dumps(plan,separators=(',',':')).encode()).decode()
  code="<?php require "+json.dumps(str(ROOT/'scripts/diagnostics/local_profile_acquire_4191.php'))+";$p=json_decode(base64_decode('"+enc+"'),true);$x=lac_scope($p);echo count($x).'|'.array_key_first($x).'|'.array_key_last($x);"
  r=subprocess.run(['php'],input=code,text=True,capture_output=True);self.assertEqual(r.returncode,0,r.stderr);self.assertEqual(r.stdout,'320|37|356')
if __name__=='__main__':unittest.main()
