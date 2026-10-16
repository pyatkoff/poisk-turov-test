#!/usr/bin/env python3
import importlib.util,io,json,os,pathlib,py_compile,tempfile,unittest
from unittest import mock
R=pathlib.Path(__file__).resolve().parents[1]
class T(unittest.TestCase):
 def test_narrow(self):
  w=(R/'.github/workflows/search3-root-gateway-install.yml').read_text();p=(R/'scripts/deploy/search3_root_gateway_install.py').read_text();i=(R/'scripts/deploy/search3_root_gateway_inspect.py').read_text();d=R/'scripts/deploy/search3_root_gateway_incident.py';g=(R/'v2/api-v2.php').read_text()
  py_compile.compile(str(R/'scripts/deploy/search3_root_gateway_install.py'),doraise=True);py_compile.compile(str(R/'scripts/deploy/search3_root_gateway_inspect.py'),doraise=True);py_compile.compile(str(d),doraise=True)
  self.assertIn("issue.number == 3419",w);self.assertIn("/install-search3-root-gateway ",w);self.assertIn("/inspect-search3-root-gateway",w);self.assertIn("READY_DIR:",w);self.assertNotIn('\\n          READY_DIR:',w);self.assertIn("python3 -m py_compile",w);self.assertIn("contents/v2/api-v2.php?ref=",p);self.assertIn("predecessor_drift",p);self.assertIn("installed_hash",p);self.assertIn("api-v2.php?action=health",p);self.assertIn("inspected_read_only",i);self.assertIn("server_writes':0",i);self.assertNotIn("lead-adapter-v2.php",p);self.assertIn("b'strict_int($value, 1, 5000'",p)
  self.assertIn("github.event.issue.number == 4217",w);self.assertIn("/diagnose-tourvisor-results-500 13905446138 2026-10-14T15:00:58Z",w);self.assertIn("scripts/deploy/search3_root_gateway_incident.py",w);self.assertIn("github.run_attempt == 1",w);self.assertIn('GH_TOKEN: "${{ github.token }}"',w)
 def test_incident_reader_is_fixed_bounded_and_redacts(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
  self.assertEqual(m.ISSUE,4217);self.assertEqual(m.SEARCH_ID,'13905446138');self.assertEqual(m.INCIDENT_UTC,'2026-10-14T15:00:58Z');self.assertEqual(m.MAX_MATCHES,120);self.assertEqual(m.MAX_SOURCES,20);self.assertEqual(m.MAX_BYTES_PER_SOURCE,2*1024*1024)
  samples=['Authorization: Basic dXNlcjpwYXNz','Cookie: PHPSESSID=topsecret; csrftoken=secret2','Set-Cookie: session=topsecret; HttpOnly','DB_PASSWORD=topsecret','{"password":"topsecret","api_key":"topsecret2"}','/api?access_token=topsecret&searchId=13905446138','mysql://user:topsecret@localhost/db']
  for line in samples:
   clean=m.sanitize_line(line);self.assertNotIn('topsecret',clean);self.assertNotIn('dXNlcjpwYXNz',clean)
  source=path.read_text();self.assertIn("'server_writes': 0",source);self.assertIn("'supplier_calls': 0",source);self.assertIn("['ssh', *opts, remote, 'python3 -']",source);self.assertIn("search_id in line or (any(t in line for t in times)",source);self.assertIn("paths=sorted(set(paths))[:20]",source);self.assertIn("'diagnostic_error'",source);self.assertNotIn('curl',source);self.assertNotIn('scp',source)
 def test_incident_command_is_exact_source_pinned_and_single_attempt(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_event',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);sha='a'*40
  event={'issue':{'number':4217},'comment':{'id':987,'body':f'{m.COMMAND_PREFIX} {sha} {m.COMMAND_NONCE}','user':{'id':226193297},'author_association':'OWNER'}}
  env={'GITHUB_REPOSITORY':'pyatkoff/poisk-turov-test','GITHUB_RUN_ATTEMPT':'1','GITHUB_SHA':sha}
  self.assertEqual(m.validate_event(event,env),(event['comment']['body'],987))
  for changed in ({**env,'GITHUB_RUN_ATTEMPT':'2'},{**env,'GITHUB_SHA':'b'*40}):
   with self.assertRaises(RuntimeError):m.validate_event(event,changed)
  replay={**event,'comment':{**event['comment'],'body':event['comment']['body']+'x'}}
  with self.assertRaises(RuntimeError):m.validate_event(replay,env)
 def test_incident_command_comment_cannot_be_replayed(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_unique',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);command='one-use-command'
  def response(values):return io.BytesIO(json.dumps(values).encode())
  with mock.patch.object(m.urllib.request,'urlopen',return_value=response([{'id':987,'body':command}])):m.unique_command_comment(command,987,'test-token')
  with mock.patch.object(m.urllib.request,'urlopen',return_value=response([{'id':987,'body':command},{'id':988,'body':command}])):
   with self.assertRaises(RuntimeError):m.unique_command_comment(command,988,'test-token')
 def test_incident_failure_retains_explicit_error_evidence(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_error',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
  with tempfile.TemporaryDirectory() as td,mock.patch.dict(os.environ,{'RUNNER_TEMP':td}),mock.patch.object(m,'event'),mock.patch.object(m,'ssh_options',side_effect=RuntimeError('sensitive detail')):
   with self.assertRaisesRegex(RuntimeError,'diagnostic_failed'):m.main()
   evidence=json.loads((pathlib.Path(td)/'search3-root-gateway-incident/evidence.json').read_text())
  self.assertEqual(evidence['status'],'diagnostic_error');self.assertEqual(evidence['error_type'],'RuntimeError');self.assertNotIn('sensitive detail',json.dumps(evidence))
if __name__=='__main__':unittest.main()
