import importlib.util,json,os,subprocess,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def load(path,name):
 s=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
class Core:
 PREFIX='/run-int-server-v1 '
 SHA_RE=__import__('re').compile(r'^[a-f0-9]{40}$')
 def __init__(self):
  self.REMOTE="def run_match942(stage, mode, offset, limit):\n    pass\n    if mode=='match-tv942-write':\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if not isinstance(files,dict) or len(files)<20: fail('manifest')\n"
  self.bundle_source=lambda p:(b'',{})
  self.parse_command=lambda b:(_ for _ in ()).throw(ValueError('old'))
class ApplyContract(unittest.TestCase):
 def setUp(self): self.m=load('scripts/deploy/int_server_executor_local_profile_apply.py','lpa')
 def test_exact_parser_and_replay_names(self):
  c=Core();self.m.register_parser(c);sha='a'*40
  cmd=c.parse_command(f'{c.PREFIX}{sha} {self.m.MODE} {self.m.OPERATION} {self.m.BATCH}')
  self.assertEqual(cmd['maximum_profile_writes'],36);self.assertEqual(cmd['provider_http_calls'],0)
  for bad in (self.m.OPERATION.replace('-v1','-v2'),'int-andromeda-local-profile-plan-4191-20261001-v2'):
   with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{sha} {self.m.MODE} {bad} {self.m.BATCH}')
 def test_remote_contract_has_no_supplier_path(self):
  c=Core();self.m.register_parser(c);self.m.activate(c,{'source_sha':'a'*40,'mode':self.m.MODE,'operation_id':self.m.OPERATION,'batch':self.m.BATCH,'maximum_profile_writes':36,'provider_http_calls':0})
  self.assertIn("local-profile-apply-4191",c.REMOTE);self.assertIn("database_writes']=72",c.REMOTE)
  self.assertNotIn("v2_data_tv_get",self.m.REMOTE_HANDLER)
 def test_php_scope_fixture_and_source_guards(self):
  src=(ROOT/'scripts/diagnostics/local_profile_apply_4191.php').read_text()
  self.assertIn("LPA_PLAN_SHA='a2306ab97e596b5df3d3eb54e85948c0e69a29237a9e96d697017db1cd902988'",src)
  self.assertIn("$owner->apply(LPA_OPERATION,36",src);self.assertNotIn("curl_",src);self.assertNotIn("v2_data_tv_get",src)
  rows=[]
  for i in range(366):
   state='RETAINED_DELTA_PREPARED' if i<36 else ('SOURCE_MISSING' if i<356 else 'D1_OVERLAP_HELD')
   r={'anytourHotelId':i+1,'state':state}
   if state=='RETAINED_DELTA_PREPARED':r.update(localHotelId=1000+i,expectedProfileSha256='a'*64,expectedRevision=1,expectedAliasSha256='b'*64)
   rows.append(r)
  plan={'schema_version':1,'operation_id':'int-andromeda-local-profile-plan-4191-20261001-v2','safe_to_apply':False,'demand_through':'2026-10-01 09:00:00','rows':rows}
  code="<?php require "+json.dumps(str(ROOT/'scripts/diagnostics/local_profile_apply_4191.php'))+";$x=lpa_scope("+__import__('base64').b64encode(json.dumps(plan,separators=(',',':')).encode()).decode().__repr__()+");"
  # Build JSON through base64 at PHP side to avoid interpolation.
  encoded=__import__('base64').b64encode(json.dumps(plan,separators=(',',':')).encode()).decode()
  code="<?php require "+json.dumps(str(ROOT/'scripts/diagnostics/local_profile_apply_4191.php'))+";$p=json_decode(base64_decode('"+encoded+"'),true);$x=lpa_scope($p);echo count($x['scope']).'|'.$x['through'];"
  r=subprocess.run(['php'],input=code,text=True,capture_output=True)
  self.assertEqual(r.returncode,0,r.stderr);self.assertEqual(r.stdout,'36|2026-10-01 09:00:00')
if __name__=='__main__':unittest.main()
