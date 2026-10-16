#!/usr/bin/env python3
import contextlib,importlib.util,io,json,os,pathlib,py_compile,tempfile,unittest
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
  samples=['Authorization: Basic dXNlcjpwYXNz','Cookie: PHPSESSID=topsecret; csrftoken=secret2','Set-Cookie: session=topsecret; HttpOnly','DB_PASSWORD=topsecret','{"password":"topsecret","api_key":"topsecret2"}','/api?access_token=topsecret&searchId=13905446138','mysql://user:topsecret@localhost/db', '{"password":"topsecret SECRET_TAIL with spaces and \\"escaped\\" text"}', '{"nested":{"Authorization":"Basic topsecret SECRET_TAIL"}}', 'X-Api-Key: topsecret', '/api?api-key=topsecret&searchId=13905446138', "ANYTOUR_CLIENT_SECRET='topsecret SECRET_TAIL with spaces'", 'Authorization: Bearer short', '{"pass\x00word":"topsecret SECRET_TAIL"}']
  for line in samples:
   clean=m.sanitize_line(line);self.assertNotIn('topsecret',clean);self.assertNotIn('SECRET_TAIL',clean);self.assertNotIn('dXNlcjpwYXNz',clean)
  source=path.read_text();self.assertIn("'server_writes': 0",source);self.assertIn("'supplier_calls': 0",source);self.assertIn("['ssh', *opts, remote, 'python3 -']",source);self.assertIn("search_id in line or (any(t in line for t in times)",source);self.assertIn("if len(paths)==20: break",source);self.assertIn("'diagnostic_error'",source);self.assertNotIn('curl',source);self.assertNotIn('scp',source)
  self.assertNotIn('short',m.sanitize_line('Authorization: Bearer short'))
  self.assertEqual(m.sanitize_line('2026/10/14 15:00:58 PHP Fatal api-v2.php searchId=13905446138'),'2026/10/14 15:00:58 PHP Fatal api-v2.php searchId=13905446138')
  for line in [('PHP Fatal {"password":"first,SECOND_SECRET_TAIL; '+('x'*2500))[:2400], ('PHP Fatal {"password":"first;SECOND_SECRET_TAIL, '+('x'*2500))[:2400], 'PHP Fatal DB_PASSWORD=first,SECOND_SECRET_TAIL', 'PHP Fatal DB_PASSWORD=first;SECOND_SECRET_TAIL', ('PHP Fatal mysql://user:first,SECOND_SECRET_TAIL'+('x'*2500))[:2400]]:
   clean=m.sanitize_line(line);self.assertNotIn('SECOND_SECRET_TAIL',clean);self.assertNotIn('first',clean);self.assertTrue(clean.startswith('PHP Fatal '));self.assertLess(len(clean),1200)
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
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_unique',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);command=f'{m.COMMAND_PREFIX} '+('a'*40)+f' {m.COMMAND_NONCE}'
  def response(values):return io.BytesIO(json.dumps(values).encode())
  with mock.patch.object(m.urllib.request,'urlopen',return_value=response([{'id':987,'body':command}])):m.unique_command_comment(command,987,'test-token')
  with mock.patch.object(m.urllib.request,'urlopen',return_value=response([{'id':987,'body':command},{'id':988,'body':command}])):
   with self.assertRaises(RuntimeError):m.unique_command_comment(command,988,'test-token')
  changed_source=f'{m.COMMAND_PREFIX} '+('b'*40)+' changed-nonce'
  with mock.patch.object(m.urllib.request,'urlopen',return_value=response([{'id':987,'body':command},{'id':988,'body':changed_source}])):
   with self.assertRaises(RuntimeError):m.unique_command_comment(changed_source,988,'test-token')
 def test_incident_failure_retains_explicit_error_evidence(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_error',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
  with tempfile.TemporaryDirectory() as td,mock.patch.dict(os.environ,{'RUNNER_TEMP':td}),mock.patch.object(m,'event'),mock.patch.object(m,'ssh_options',side_effect=RuntimeError('sensitive detail')):
   with self.assertRaisesRegex(RuntimeError,'diagnostic_failed'):m.main()
   evidence=json.loads((pathlib.Path(td)/'search3-root-gateway-incident/evidence.json').read_text())
  self.assertEqual(evidence['status'],'diagnostic_error');self.assertEqual(evidence['error_type'],'RuntimeError');self.assertNotIn('sensitive detail',json.dumps(evidence))
 def test_remote_journal_has_hard_byte_cap_and_kills_at_limit(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_remote',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
  process=mock.Mock();process.stdout.fileno.return_value=42;process.wait.return_value=-9;process.poll.return_value=0
  selector=mock.MagicMock();selector.__enter__.return_value=selector;selector.select.return_value=[object()]
  sizes=[]
  def read(fd,size):sizes.append(size);return b'x'*size
  with mock.patch('pathlib.Path.is_file',return_value=False),mock.patch('glob.iglob',return_value=iter([])),mock.patch('subprocess.Popen',return_value=process) as start,mock.patch('selectors.DefaultSelector',return_value=selector),mock.patch('os.read',side_effect=read),contextlib.redirect_stdout(io.StringIO()) as output:
   scope={};exec(m.REMOTE_READER,scope)
  payload=json.loads(output.getvalue());self.assertTrue(payload['journal_truncated']);self.assertEqual(sum(sizes),2*1024*1024);self.assertLessEqual(max(sizes),65536);process.kill.assert_called_once();self.assertEqual(payload['collection_errors'],[]);self.assertIs(start.call_args.kwargs['stderr'],m.subprocess.DEVNULL)
 def test_remote_collection_errors_are_not_no_matching_logs(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_remote_error',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
  with mock.patch('pathlib.Path.is_file',side_effect=PermissionError('synthetic private path')),mock.patch('glob.iglob',return_value=iter([])),mock.patch('subprocess.Popen',side_effect=FileNotFoundError('synthetic private detail')),contextlib.redirect_stdout(io.StringIO()) as output:
   exec(m.REMOTE_READER,{})
  payload=json.loads(output.getvalue());self.assertEqual(payload['sources_read'],[]);self.assertEqual(len(payload['collection_errors']),5);self.assertNotIn('synthetic private',output.getvalue());self.assertEqual(payload['collection_errors'][-1]['error_type'],'FileNotFoundError')
  with tempfile.TemporaryDirectory() as td,mock.patch.dict(os.environ,{'RUNNER_TEMP':td}),mock.patch.object(m,'event'),mock.patch.object(m,'ssh_options',return_value=(pathlib.Path(td)/'key',pathlib.Path(td)/'known',[],'synthetic')),mock.patch.object(m.subprocess,'run',return_value=mock.Mock(stdout=json.dumps(payload))):
   with self.assertRaisesRegex(RuntimeError,'diagnostic_failed'):m.main()
   evidence=json.loads((pathlib.Path(td)/'search3-root-gateway-incident/evidence.json').read_text())
  self.assertEqual(evidence['status'],'diagnostic_error');self.assertEqual(len(evidence['collection_errors']),5)
 def test_remote_source_discovery_and_file_reads_stay_bounded(self):
  path=R/'scripts/deploy/search3_root_gateway_incident.py';spec=importlib.util.spec_from_file_location('incident_files',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
  discovered=[]
  def candidates(pattern):
   for i in range(100):
    discovered.append(i)
    if len(discovered)>16:raise AssertionError('discovery exceeded remaining source slots')
    yield '/synthetic/log'+str(i)+'.log'
  class Tail(io.BytesIO):
   def read(self,size=-1):
    self.size_requested=size;return super().read(size)
  partial=b'SECRET_PARTIAL 13905446138\n';complete=b'PHP Fatal api-v2.php 13905446138\n'
  tail=Tail(b'x'*(1024*1024)+partial+complete+b'x'*(2*1024*1024-len(partial)-len(complete)))
  with mock.patch('pathlib.Path.is_file',lambda p:str(p)=='/var/log/nginx/error.log'),mock.patch('pathlib.Path.is_symlink',return_value=False),mock.patch('pathlib.Path.open',return_value=tail),mock.patch('glob.iglob',side_effect=candidates),mock.patch('subprocess.Popen',side_effect=FileNotFoundError('not retained')),contextlib.redirect_stdout(io.StringIO()) as output:
   scope={};exec(m.REMOTE_READER,scope)
  payload=json.loads(output.getvalue());self.assertEqual(len(scope['paths']),20);self.assertEqual(len(discovered),16);self.assertEqual(tail.size_requested,2*1024*1024);self.assertEqual(payload['sources_read'],['/var/log/nginx/error.log']);self.assertNotIn('not retained',output.getvalue());self.assertNotIn('SECRET_PARTIAL',output.getvalue());self.assertEqual(payload['matches'][0]['line'],complete.decode().strip())
if __name__=='__main__':unittest.main()

