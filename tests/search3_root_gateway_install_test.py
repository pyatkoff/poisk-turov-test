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

class Preflight(unittest.TestCase):
 def module(self):
  path=R/'scripts/deploy/search3_root_gateway_inspect.py'
  spec=importlib.util.spec_from_file_location('preflight',path)
  m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
 def event(self,m):
  sha='a'*40
  event={'repository':{'full_name':m.REPO},'action':'created','issue':{'number':4217},
         'comment':{'id':987,'body':f'{m.COMMAND_PREFIX} {sha} {m.OPERATION}',
                    'user':{'id':226193297},'author_association':'OWNER'}}
  env={'GITHUB_REPOSITORY':m.REPO,'GITHUB_REF':'refs/heads/main','GITHUB_SHA':sha,
       'GITHUB_WORKFLOW_REF':f'{m.REPO}/{m.WORKFLOW}@refs/heads/main',
       'GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff',
       'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ATTEMPT':'1'}
  return event,env
 def result(self,m):
  files=[{'path':p,'sha256':h,'size':123,'mode':'0644'} for p,h in m.EXPECTED.items()]
  return {'status':'preflight_verified_read_only','syntax':'ok','before':files,'after':files}
 def test_event_requires_exact_owner_main_workflow_operation_and_first_attempt(self):
  m=self.module();event,env=self.event(m)
  self.assertEqual(m.validate_event(event,env),('a'*40,987))
  for key,value in {'GITHUB_REPOSITORY':'other/repo','GITHUB_REF':'refs/heads/other',
                    'GITHUB_SHA':'b'*40,'GITHUB_WORKFLOW_REF':m.WORKFLOW,
                    'GITHUB_ACTOR':'other','GITHUB_TRIGGERING_ACTOR':'other',
                    'GITHUB_EVENT_NAME':'workflow_dispatch','GITHUB_RUN_ATTEMPT':'2'}.items():
   with self.subTest(key=key),self.assertRaises(RuntimeError):m.validate_event(event,{**env,key:value})
  import copy
  variants=[]
  for key,value in [('action','edited'),('repository',{'full_name':'other/repo'}),
                    ('issue',{'number':3419}),('issue',{'number':4217,'pull_request':{'url':'x'}})]:
   v=copy.deepcopy(event);v[key]=value;variants.append(v)
  for key,value in [('id',True),('user',{'id':1}),('author_association','MEMBER'),
                    ('body',event['comment']['body']+' '),('body',event['comment']['body'].replace(m.OPERATION,'another-operation'))]:
   v=copy.deepcopy(event);v['comment'][key]=value;variants.append(v)
  for v in variants:
   with self.subTest(event=v),self.assertRaises(RuntimeError):m.validate_event(v,env)
 def test_admission_checks_current_refs_blob_and_reservation_without_remote(self):
  m=self.module();event,env=self.event(m);command=event['comment']['body']
  def api(path):
   if path=='/git/ref/heads/main':return {'object':{'sha':env['GITHUB_SHA']}}
   if path=='/git/ref/heads/release/search3-production-ready-v1':return {'object':{'sha':m.RELEASE}}
   if path.startswith('/contents/'):return {'sha':m.BLOB}
   return [{'id':987,'body':command}]
  with mock.patch.object(m,'api',side_effect=api) as calls:m.admission(env['GITHUB_SHA'],987)
  self.assertEqual(calls.call_count,5)
  for wrong in ('main','release','blob','duplicate','changed_operation','incomplete'):
   def bad(path):
    if wrong=='main' and path=='/git/ref/heads/main':return {'object':{'sha':'b'*40}}
    if wrong=='release' and 'heads/release/' in path:return {'object':{'sha':'b'*40}}
    if wrong=='blob' and path.startswith('/contents/'):return {'sha':'b'*40}
    if path.startswith('/issues/'):
     if wrong=='duplicate':return [{'id':986,'body':command},{'id':987,'body':command}]
     if wrong=='changed_operation':return [{'id':986,'body':f'{m.COMMAND_PREFIX} '+('b'*40)+' other'},{'id':987,'body':command}]
     if wrong=='incomplete':return [{'id':i,'body':'ordinary comment'} for i in range(100)]
    return api(path)
   with self.subTest(wrong=wrong),mock.patch.object(m,'api',side_effect=bad) as calls,self.assertRaises(RuntimeError):
    m.admission(env['GITHUB_SHA'],987)
   self.assertLessEqual(calls.call_count,4+m.MAX_COMMENT_PAGES)
  with mock.patch.object(m,'api',side_effect=[{'object':{'sha':'a'*40}},
       {'object':{'sha':m.RELEASE}},{'sha':m.BLOB},{'sha':m.BLOB},
       [{'id':i,'body':'ordinary'} for i in range(100)],[{'id':987,'body':command}]]):
   m.admission('a'*40,987)
 def test_github_response_read_is_bounded(self):
  m=self.module();response=mock.MagicMock()
  response.__enter__.return_value.read.return_value=b'x'*(m.MAX_API_BYTES+1)
  with mock.patch.dict(os.environ,{'GH_TOKEN':'synthetic-token'}),mock.patch.object(m.urllib.request,'urlopen',return_value=response),self.assertRaisesRegex(RuntimeError,'github_response_limit'):
   m.api('/git/ref/heads/main')
  response.__enter__.return_value.read.assert_called_once_with(m.MAX_API_BYTES+1)
 def remote(self,m,home,expected,run=None):
  scope={'EXPECTED':expected,'MAX_FILE_BYTES':m.MAX_FILE_BYTES}
  with mock.patch.object(pathlib.Path,'home',return_value=home),mock.patch.object(m.subprocess,'run',side_effect=run or (lambda *a,**k:mock.Mock(returncode=0))) as process,contextlib.redirect_stdout(io.StringIO()) as out:
   exec(m.REMOTE_BODY,scope)
  value=json.loads(out.getvalue());self.assertLess(len(out.getvalue()),m.MAX_EVIDENCE_BYTES)
  return value,process
 def files(self,m,home):
  import hashlib
  root=home/'www/anytoour.ru';expected={}
  for path in m.EXPECTED:
   f=root/path;f.parent.mkdir(parents=True,exist_ok=True)
   # A PHP body with a side effect must only be linted, never executed.
   f.write_bytes(b'<?php file_put_contents("MUST_NOT_EXIST", "private"); ?>' if path=='api-v2.php' else b'private fixture '+path.encode())
   expected[path]=hashlib.sha256(f.read_bytes()).hexdigest()
  return root,expected
 def test_remote_hashes_fixed_files_before_and_after_without_execution_or_writes(self):
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);root,expected=self.files(m,home)
   before={str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()}
   value,process=self.remote(m,home,expected)
   after={str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()}
   self.assertEqual(before,after);self.assertEqual(value['status'],'preflight_verified_read_only')
   self.assertEqual(value['before'],value['after']);self.assertEqual(len(value['before']),8)
   self.assertNotIn('private',json.dumps(value));self.assertEqual(process.call_count,1)
   self.assertEqual(process.call_args.args[0],['php','-n','-l',str(root/'api-v2.php')])
   self.assertEqual(process.call_args.kwargs['env'],{'PATH':'/usr/bin:/bin','HOME':str(home)})
   self.assertIs(process.call_args.kwargs['stdout'],m.subprocess.DEVNULL)
   self.assertIs(process.call_args.kwargs['stderr'],m.subprocess.DEVNULL)
 def test_remote_drift_and_unsafe_files_stop_before_php(self):
  m=self.module()
  for variant in ('api_drift','dependency_drift','missing','symlink','parent_symlink','hardlink','fifo','oversized'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    home=pathlib.Path(td);root,expected=self.files(m,home);api=root/'api-v2.php'
    if variant=='api_drift':api.write_text('changed private body')
    if variant=='dependency_drift':(root/'data/db-v1.php').write_text('changed private body')
    if variant=='missing':api.unlink()
    if variant=='symlink':api.unlink();api.symlink_to(root/'data/db-v1.php')
    if variant=='parent_symlink':(root/'data').rename(home/'real-data');(root/'data').symlink_to(home/'real-data',target_is_directory=True)
    if variant=='hardlink':os.link(api,home/'another-link')
    if variant=='fifo':api.unlink();os.mkfifo(api)
    if variant=='oversized':
     with api.open('wb') as f:f.truncate(m.MAX_FILE_BYTES+1)
    value,process=self.remote(m,home,expected)
    self.assertEqual(value['status'],'preflight_error');self.assertEqual(value['syntax'],'not_run')
    self.assertEqual(value['after'],[]);process.assert_not_called();self.assertNotIn('private',json.dumps(value))
 def test_syntax_failure_timeout_and_during_lint_change_cannot_pass(self):
  m=self.module()
  for variant in ('invalid','timeout','change'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    home=pathlib.Path(td);root,expected=self.files(m,home)
    def run(*args,**kwargs):
     if variant=='timeout':raise m.subprocess.TimeoutExpired('private command',15)
     if variant=='change':(root/'api-v2.php').write_text('changed private body')
     return mock.Mock(returncode=1 if variant=='invalid' else 0)
    value,process=self.remote(m,home,expected,run)
    self.assertEqual(value['status'],'preflight_error');self.assertNotIn('private',json.dumps(value))
 def test_parent_rejects_unknown_fields_paths_hashes_and_forged_success(self):
  import copy
  m=self.module();good=self.result(m);self.assertEqual(m.validate_result(good),good)
  variants=[{**good,'private_config':'SECRET'},{**good,'syntax':'not_run'},
            {**good,'error_type':'RuntimeError'},{**good,'before':[]}]
  for field,value in [('path','/private/config.php'),('sha256','0'*64),('size',m.MAX_FILE_BYTES+1),('size',True),('mode','07777')]:
   v=copy.deepcopy(good);v['before'][0][field]=value;variants.append(v)
  for v in variants:
   with self.subTest(value=v),self.assertRaises((RuntimeError,TypeError)):m.validate_result(v)
 def test_admission_failure_retains_only_safe_error_and_never_connects(self):
  m=self.module();event,env=self.event(m)
  with tempfile.TemporaryDirectory() as td:
   path=pathlib.Path(td)/'event.json';path.write_text(json.dumps(event))
   with mock.patch.dict(os.environ,{**env,'RUNNER_TEMP':td,'GITHUB_EVENT_PATH':str(path)}),mock.patch.object(m,'admission',side_effect=RuntimeError('PRIVATE_DETAIL')),mock.patch.object(m,'read_remote') as remote,contextlib.redirect_stdout(io.StringIO()) as output,self.assertRaisesRegex(RuntimeError,'preflight_failed'):
    m.preflight()
   value=json.loads((pathlib.Path(td)/'search3-root-gateway-preflight/evidence.json').read_text())
   self.assertEqual(value['status'],'preflight_error');self.assertNotIn('PRIVATE_DETAIL',output.getvalue())
   self.assertEqual([value[k] for k in ('server_writes','supplier_calls','db_writes','real_leads')],[0,0,0,0]);remote.assert_not_called()
 def test_remote_connection_keys_are_cleaned_on_success_and_failure(self):
  m=self.module()
  for failed in (False,True):
   with self.subTest(failed=failed),tempfile.TemporaryDirectory() as td:
    work=pathlib.Path(td)
    def process(args,**kwargs):
     if args[0]=='ssh-keyscan':return mock.Mock(stdout=b'synthetic known host')
     self.assertEqual(args[-1],'python3 -');self.assertEqual(kwargs['input'],m.REMOTE_PREFLIGHT.encode())
     self.assertIn('StrictHostKeyChecking=yes',args)
     if failed:raise m.subprocess.CalledProcessError(255,args,stderr=b'PRIVATE_DETAIL')
     return mock.Mock(stdout=json.dumps(self.result(m)).encode())
    with mock.patch.dict(os.environ,{'PREVIEW_KEY':'c3ludGhldGlj','PREVIEW_HOST':'synthetic.example','PREVIEW_USER':'synthetic'}),mock.patch.object(m.subprocess,'run',side_effect=process):
     if failed:
      with self.assertRaises(m.subprocess.CalledProcessError):m.read_remote(work)
     else:self.assertEqual(m.read_remote(work),self.result(m))
    self.assertFalse((work/'key').exists());self.assertFalse((work/'known').exists())
 def test_only_new_job_invokes_preflight_with_existing_production_lock(self):
  m=self.module();workflow=(R/m.WORKFLOW).read_text()
  job=workflow.split('  preflight:\n',1)[1].split('  incident:\n',1)[0]
  for guard in ("group: deploy-anytoour-production","cancel-in-progress: false","github.run_attempt == 1",
                "github.event.issue.number == 4217","github.actor == 'pyatkoff'","github.triggering_actor == 'pyatkoff'",
                "github.event.comment.user.id == 226193297","github.sha","permissions: {contents: read, issues: read}",
                "scripts/deploy/search3_root_gateway_inspect.py preflight"):
   self.assertIn(guard,job)
  self.assertNotIn('workflow_dispatch',workflow)
  self.assertNotIn('install.py',job);self.assertNotIn('incident.py',job)
  self.assertEqual(workflow.count('scripts/deploy/search3_root_gateway_inspect.py preflight'),1)


class CheckedInstall(unittest.TestCase):
 def module(self):
  path=R/'scripts/deploy/search3_root_gateway_install.py'
  spec=importlib.util.spec_from_file_location('checked_install',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
 def event(self,m):
  env={'GITHUB_REPOSITORY':m.REPO,'GITHUB_REF':'refs/heads/main','GITHUB_SHA':'b'*40,
       'GITHUB_WORKFLOW_REF':f'{m.REPO}/{m.CHECKED_WORKFLOW}@refs/heads/main',
       'GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff',
       'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ATTEMPT':'1','GITHUB_RUN_ID':'777'}
  event={'repository':{'full_name':m.REPO},'action':'created','issue':{'number':4217},
         'comment':{'id':987,'body':f'{m.CHECKED_PREFIX} '+('b'*40)+' '+m.CHECKED_OPERATION,
                    'user':{'id':226193297},'author_association':'OWNER'}}
  return event,env
 def fixture(self,m,home):
  import base64
  project=home/'www/anytoour.ru';expected={}
  for path in m.CHECKED_EXPECTED:
   p=project/path;p.parent.mkdir(parents=True,exist_ok=True)
   p.write_bytes(b'<?php file_put_contents(__DIR__."/MUST_NOT_EXECUTE","old"); ?>' if path=='api-v2.php' else b'fixture '+path.encode());p.chmod(0o644)
   expected[path]=m.sha(p.read_bytes())
  (project/'index.php').write_bytes(b'public home');(project/'index.php').chmod(0o644)
  candidate=b'<?php file_put_contents(__DIR__."/MUST_NOT_EXECUTE","new"); ?>'
  m.CHECKED_EXPECTED=expected;m.CHECKED_HASH=m.sha(candidate);m.CHECKED_BLOB=m.blob(candidate);m.CHECKED_BYTES=len(candidate)
  event,env=self.event(m);identity=m.checked_event(event,env)
  payload={**identity,'candidate_b64':base64.b64encode(candidate).decode()}
  return project,candidate,identity,payload,m.checked_remote()
 def execute(self,m,home,payload,program,callback=None,native=False):
  import types
  actual=m.subprocess.run;calls=[]
  def lint(args,**kw):
   calls.append(args)
   self.assertEqual(args[:3],['php','-n','-l']);self.assertEqual(len(args),4)
   self.assertIs(kw['stdout'],m.subprocess.DEVNULL);self.assertIs(kw['stderr'],m.subprocess.DEVNULL)
   if callback:
    value=callback(args,len(calls))
    if value is not None:return value
   return actual(args,**kw) if native else mock.Mock(returncode=0)
  stdin=types.SimpleNamespace(buffer=io.BytesIO(json.dumps(payload).encode()))
  with mock.patch.object(pathlib.Path,'home',return_value=home),mock.patch('sys.stdin',stdin),mock.patch.object(m.subprocess,'run',side_effect=lint),contextlib.redirect_stdout(io.StringIO()) as out:
   exec(program,{})
  return json.loads(out.getvalue()),calls
 def test_current_event_is_exact_and_legacy_operations_stay_frozen(self):
  m=self.module();event,env=self.event(m);identity=m.checked_event(event,env)
  self.assertEqual(identity,{'control_source_sha':'b'*40,'run_id':'777','command_comment_id':987})
  self.assertEqual(m.CHECKED_HASH,'ef60da2d948c42b7741771ef1e1a091280c14cd79eafea10d737cb7067e55463')
  self.assertEqual(m.CHECKED_BLOB,'6fb963e68a99945118d04f8e46845ecd6bcb6bc9')
  self.assertEqual(m.CHECKED_BYTES,14557);self.assertEqual(m.ISSUE,3419)
  for key,value in {'GITHUB_REF':'refs/heads/other','GITHUB_SHA':'c'*40,'GITHUB_RUN_ATTEMPT':'2','GITHUB_ACTOR':'other','GITHUB_TRIGGERING_ACTOR':'other','GITHUB_EVENT_NAME':'workflow_dispatch','GITHUB_RUN_ID':'0','GITHUB_WORKFLOW_REF':'other'}.items():
   with self.subTest(key=key),self.assertRaises(RuntimeError):m.checked_event(event,{**env,key:value})
  import copy
  for key,value in [('body',event['comment']['body']+' '),('body',event['comment']['body'].replace(m.CHECKED_OPERATION,'retry-v2')),('id',True),('user',{'id':1})]:
   bad=copy.deepcopy(event);bad['comment'][key]=value
   with self.subTest(key=key),self.assertRaises(RuntimeError):m.checked_event(bad,env)
  for issue in ({'number':3419},{'number':4217,'pull_request':{}}):
   bad=copy.deepcopy(event);bad['issue']=issue
   if 'pull_request' in issue:bad['issue']['pull_request']={'url':'synthetic'}
   with self.assertRaises(RuntimeError):m.checked_event(bad,env)
  p=R/'scripts/deploy/search3_root_gateway_inspect.py';spec=importlib.util.spec_from_file_location('frozen_preflight',p);old=importlib.util.module_from_spec(spec);spec.loader.exec_module(old)
  self.assertEqual(old.OPERATION,'preflight-20261018-fcfe503e-v1');self.assertEqual(old.EXPECTED,m.CHECKED_EXPECTED)
 def test_admission_checks_exact_candidate_ci_and_complete_single_reservation(self):
  import base64
  m=self.module();event,env=self.event(m);identity=m.checked_event(event,env)
  # Candidate bytes come from the mocked source/release API, not this main checkout.
  body=b'<?php /* reviewed synthetic candidate */ ?>'
  m.CHECKED_BLOB=m.blob(body);m.CHECKED_HASH=m.sha(body);m.CHECKED_BYTES=len(body)
  def api(path):
   if path=='/git/ref/heads/main':return {'object':{'sha':'b'*40}}
   if path.startswith('/git/ref/heads/release/'):return {'object':{'sha':m.CHECKED_RELEASE}}
   if path.startswith('/contents/'):return {'sha':m.CHECKED_BLOB,'content':base64.b64encode(body).decode()}
   if '/check-runs?' in path:return {'total_count':5,'check_runs':[{'id':i,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}} for i in m.CHECKED_CHECK_IDS]}
   return [{'id':987,'body':event['comment']['body']}]
  with mock.patch.object(m,'checked_api',side_effect=api):self.assertEqual(m.checked_admission(identity),body)
  for variant in ('main','release','blob','source_bytes','ci','duplicate','changed_control','incomplete'):
   def wrong(path):
    value=api(path)
    if variant=='main' and path=='/git/ref/heads/main':value['object']['sha']='c'*40
    if variant=='release' and '/heads/release/' in path:value['object']['sha']='c'*40
    if variant=='blob' and path.startswith('/contents/'):value['sha']='c'*40
    if variant=='source_bytes' and path.startswith('/contents/'):value['content']=base64.b64encode(b'wrong').decode()
    if variant=='ci' and '/check-runs?' in path:value['check_runs'][0]['conclusion']='failure'
    if path.startswith('/issues/'):
     if variant=='duplicate':value.append({'id':988,'body':event['comment']['body']})
     if variant=='changed_control':value.append({'id':988,'body':event['comment']['body'].replace('b'*40,'c'*40)})
     if variant=='incomplete':value=[{'id':i,'body':'ordinary'} for i in range(100)]
    return value
   with self.subTest(variant=variant),mock.patch.object(m,'checked_api',side_effect=wrong),self.assertRaises(RuntimeError):m.checked_admission(identity)
 def test_actual_remote_replaces_one_api_retains_backup_and_never_executes_php(self):
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home)
   before={p:(project/p).read_bytes() for p in m.CHECKED_EXPECTED};old=before['api-v2.php']
   old_umask=os.umask(0o077)
   try:result,calls=self.execute(m,home,payload,program)
   finally:os.umask(old_umask)
   self.assertTrue(m.checked_completed(identity,result),result);self.assertEqual(len(calls),3)
   self.assertEqual((project/'api-v2.php').read_bytes(),candidate)
   for p in before:
    if p!='api-v2.php':self.assertEqual((project/p).read_bytes(),before[p])
   op=home/'.anytoour-search3-root-gateway'/m.CHECKED_OPERATION
   self.assertEqual((op/'previous.php').read_bytes(),old);self.assertEqual((op/'previous.php').stat().st_mode&0o777,0o600)
   self.assertEqual((project/'api-v2.php').stat().st_mode&0o777,0o644)
   self.assertEqual(json.loads((op/'result.json').read_text()),result)
   self.assertFalse(list(home.rglob('MUST_NOT_EXECUTE')))
   self.assertNotIn('api-v2.php?action=health',program);self.assertNotIn('urllib',program);self.assertNotIn('repair_replace',program)
 def test_actual_remote_replay_preserves_first_private_reservation_and_receipt(self):
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home)
   first,_=self.execute(m,home,payload,program);self.assertTrue(m.checked_completed(identity,first))
   before={str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()}
   second,calls=self.execute(m,home,payload,program)
   after={str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()}
   self.assertEqual(second['reason'],'operation_exists_no_replay');self.assertEqual(calls,[]);self.assertEqual(before,after)
 def test_drift_unsafe_files_and_bad_payload_stop_before_lint_or_root_write(self):
  import base64
  m=self.module()
  for variant in ('api','helper','missing','symlink','hardlink','fifo','parent_symlink','candidate'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home);p=project/'api-v2.php'
    if variant=='api':p.write_bytes(b'foreign version')
    if variant=='helper':(project/'data/db-v1.php').write_bytes(b'foreign helper')
    if variant=='missing':p.unlink()
    if variant=='symlink':p.unlink();p.symlink_to(project/'data/db-v1.php')
    if variant=='hardlink':os.link(p,home/'another-link')
    if variant=='fifo':p.unlink();os.mkfifo(p)
    if variant=='parent_symlink':(project/'data').rename(home/'real-data');(project/'data').symlink_to(home/'real-data',target_is_directory=True)
    if variant=='candidate':payload['candidate_b64']=base64.b64encode(b'wrong candidate').decode()
    result,calls=self.execute(m,home,payload,program)
    self.assertEqual(result['status'],'blocked_no_replay');self.assertEqual(result['root_write_attempts'],0);self.assertEqual(calls,[])
 def test_lint_failure_and_changes_during_lint_abort_before_replacement(self):
  for variant in ('lint_fail','api_change','helper_change','candidate_change','public_change'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home);old=(project/'api-v2.php').read_bytes()
    def callback(args,count):
     if count==2:
      if variant=='lint_fail':return mock.Mock(returncode=1)
      if variant=='api_change':(project/'api-v2.php').write_bytes(b'foreign api during lint')
      if variant=='helper_change':(project/'data/db-v1.php').write_bytes(b'foreign helper during lint')
      if variant=='candidate_change':pathlib.Path(args[-1]).write_bytes(b'foreign staged candidate')
      if variant=='public_change':(project/'index.php').write_bytes(b'foreign public home')
    result,calls=self.execute(m,home,payload,program,callback)
    self.assertEqual(result['status'],'blocked_no_replay');self.assertEqual(result['root_write_attempts'],0)
    if variant!='api_change':self.assertEqual((project/'api-v2.php').read_bytes(),old)
 def test_post_install_failure_rolls_back_own_bytes_but_preserves_foreign_api(self):
  for foreign in (False,True):
   with self.subTest(foreign=foreign),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home);old=(project/'api-v2.php').read_bytes()
    def callback(args,count):
     if count==3:
      if foreign:(project/'api-v2.php').write_bytes(b'foreign owner after replacement')
      return mock.Mock(returncode=1)
    result,calls=self.execute(m,home,payload,program,callback)
    self.assertFalse(m.checked_completed(identity,result))
    self.assertEqual(result['status'],'unknown_no_replay' if foreign else 'rolled_back_no_replay')
    self.assertEqual(result['rollback_attempts'],0 if foreign else 1)
    self.assertEqual((project/'api-v2.php').read_bytes(),b'foreign owner after replacement' if foreign else old)
 def test_receipt_cannot_launder_wrong_identity_partial_inventory_or_mutation(self):
  import copy
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home);good,_=self.execute(m,home,payload,program)
  mutations=[lambda r:r.__setitem__('source_sha','a'*40),lambda r:r.__setitem__('file_writes',True),lambda r:r.__setitem__('database_writes',1),lambda r:r.__setitem__('supplier_calls',1),lambda r:r.__setitem__('backup_retained',False),lambda r:r['after_files'].pop('data/db-v1.php'),lambda r:r['after_files']['api-v2.php'].__setitem__('sha256','a'*64),lambda r:r['production_after'].__setitem__('index.php','c'*64),lambda r:r.__setitem__('syntax_candidate','not_run')]
  for mutation in mutations:
   changed=copy.deepcopy(good);mutation(changed);self.assertFalse(m.checked_completed(identity,changed))
 def test_runner_keeps_only_sanitized_failure_and_cleans_keys(self):
  m=self.module();event,env=self.event(m)
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);path=home/'event.json';path.write_text(json.dumps(event))
   with mock.patch.dict(os.environ,{**env,'RUNNER_TEMP':td,'GITHUB_EVENT_PATH':str(path)}),mock.patch.object(m,'checked_admission',side_effect=RuntimeError('PRIVATE_DETAIL')),mock.patch.object(m,'run') as network,contextlib.redirect_stdout(io.StringIO()) as out,self.assertRaisesRegex(RuntimeError,'install_not_verified'):
    m.checked_install()
   evidence=json.loads((home/'search3-root-gateway-checked/evidence.json').read_text());self.assertNotIn('PRIVATE_DETAIL',out.getvalue());self.assertEqual(evidence['status'],'blocked_no_replay');network.assert_not_called()
   self.assertFalse((home/'search3-root-gateway-checked/key').exists());self.assertFalse((home/'search3-root-gateway-checked/known').exists())
 def test_workflow_uses_same_carrier_lock_exact_owner_and_explicit_safe_artifact(self):
  m=self.module();workflow=(R/m.CHECKED_WORKFLOW).read_text();job=workflow.split('  checked-install:\n',1)[1].split('  preflight:\n',1)[0]
  for guard in ('group: deploy-anytoour-production','cancel-in-progress: false','github.run_attempt == 1',"github.event.issue.number == 4217","github.event.action == 'created'","github.actor == 'pyatkoff'","github.triggering_actor == 'pyatkoff'","github.event.comment.user.id == 226193297",m.CHECKED_OPERATION,'scripts/deploy/search3_root_gateway_install.py checked_install','checks: read',"SEARCH3_CHECKED_INSTALL_NATIVE_CI: '1'"):
   self.assertIn(guard,job)
  artifact=job.split('          path: |\n',1)[1].split('          if-no-files-found:',1)[0]
  self.assertEqual(len(artifact.strip().splitlines()),3);self.assertIn('/evidence.json',artifact);self.assertIn('/request.json',artifact);self.assertIn('/api-v2.php',artifact);self.assertNotIn('/key',artifact);self.assertNotIn('/known',artifact)
  self.assertNotIn('workflow_dispatch',workflow);self.assertNotIn('action=health',job);self.assertNotIn('/run-int-server-v1',job)
 def test_runner_rejects_private_extra_reply_and_cleans_keys_after_ssh(self):
  import copy
  for variant in ('success','private_reply','transport_error'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home)
    result,_=self.execute(m,home,payload,program);reply=copy.deepcopy(result)
    if variant=='private_reply':reply['private_config']='PRIVATE_DETAIL'
    event,env=self.event(m);path=home/'event.json';path.write_text(json.dumps(event))
    def run(args,**kwargs):
     if args[0]=='ssh-keyscan':return b'synthetic known host'
     self.assertEqual(args[0],'ssh');self.assertIn('StrictHostKeyChecking=yes',args)
     self.assertTrue(args[-1].startswith('python3 -c '));self.assertEqual(json.loads(kwargs['input'])['candidate_b64'],payload['candidate_b64'])
     if variant=='transport_error':raise m.subprocess.CalledProcessError(255,args,stderr=b'PRIVATE_DETAIL')
     return json.dumps(reply).encode()
    with mock.patch.dict(os.environ,{**env,'RUNNER_TEMP':td,'GITHUB_EVENT_PATH':str(path),'PREVIEW_HOST':'synthetic.example','PREVIEW_USER':'synthetic','PREVIEW_KEY':'c3ludGhldGlj'}),mock.patch.object(m,'checked_admission',return_value=candidate),mock.patch.object(m,'run',side_effect=run),contextlib.redirect_stdout(io.StringIO()) as out:
     if variant=='success':m.checked_install()
     else:
      with self.assertRaisesRegex(RuntimeError,'install_not_verified'):m.checked_install()
    work=home/'search3-root-gateway-checked';evidence=json.loads((work/'evidence.json').read_text())
    self.assertFalse((work/'key').exists());self.assertFalse((work/'known').exists())
    self.assertNotIn('PRIVATE_DETAIL',json.dumps(evidence));self.assertNotIn('PRIVATE_DETAIL',out.getvalue())
    self.assertEqual(evidence['status'],'installed_verified' if variant=='success' else 'unknown_no_replay')
 @unittest.skipUnless(os.environ.get('SEARCH3_CHECKED_INSTALL_NATIVE_CI')=='1','native PHP CI required')
 def test_native_php_lint_transaction_never_executes_side_effect_body(self):
  import shutil
  self.assertIsNotNone(shutil.which('php'),'native PHP is required in exact-head CI')
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home)
   result,calls=self.execute(m,home,payload,program,native=True)
   self.assertTrue(m.checked_completed(identity,result),result);self.assertEqual(len(calls),3)
   self.assertFalse(list(home.rglob('MUST_NOT_EXECUTE')))

class DependencyInstall(unittest.TestCase):
 def module(self):return CheckedInstall.module(self)
 def event(self,m):
  event,env=CheckedInstall.event(self,m)
  event['comment']['body']=f'{m.DEPENDENCY_PREFIX} '+env['GITHUB_SHA']+' '+m.DEPENDENCY_OPERATION
  return event,env
 def fixture(self,m,home,compatible=False):
  import base64
  project=home/'www/anytoour.ru';expected={}
  for path in m.DEPENDENCY_EXPECTED:
   p=project/path;p.parent.mkdir(parents=True,exist_ok=True)
   p.write_bytes(b'<?php file_put_contents(__DIR__."/MUST_NOT_EXECUTE","previous"); ?>' if path in m.DEPENDENCY_CANDIDATES else b'fixture '+path.encode());p.chmod(0o644)
   expected[path]=m.sha(p.read_bytes())
  candidates={path:b'<?php file_put_contents(__DIR__."/MUST_NOT_EXECUTE","candidate '+path.encode()+b'"); ?>' for path in m.DEPENDENCY_CANDIDATES}
  m.DEPENDENCY_EXPECTED=expected
  m.DEPENDENCY_CANDIDATES={path:{**m.DEPENDENCY_CANDIDATES[path],'bytes':len(body),'sha256':m.sha(body),'blob':m.blob(body)} for path,body in candidates.items()}
  if compatible:(project/'data/local-tv-catalog-v1.php').write_bytes(candidates['data/local-tv-catalog-v1.php'])
  for path in ('index.php','config.php',m.DEPENDENCY_APP):
   p=project/path;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b'PRIVATE_CONFIG_MUST_NOT_RETAIN' if path=='config.php' else b'protected fixture');p.chmod(0o644)
  m.DEPENDENCY_APP_HASH=m.sha((project/m.DEPENDENCY_APP).read_bytes())
  event,env=self.event(m);identity=m.dependency_event(event,env)
  payload={**identity,'candidates_b64':{p:base64.b64encode(b).decode() for p,b in candidates.items()}}
  return project,candidates,identity,payload,m.dependency_remote()
 def execute(self,m,home,payload,program,callback=None,native=False):
  import types,shutil
  actual=m.subprocess.run;calls=[]
  def lint(args,**kw):
   calls.append(args)
   self.assertEqual(args[:3],['php','-n','-l']);self.assertEqual(len(args),4)
   self.assertEqual(kw['env'],{'PATH':'/usr/bin:/bin','HOME':str(home)})
   self.assertIs(kw['stdout'],m.subprocess.DEVNULL);self.assertIs(kw['stderr'],m.subprocess.DEVNULL)
   if callback:
    value=callback(args,len(calls))
    if value is not None:return value
   return actual([shutil.which('php'),*args[1:]],**kw) if native else mock.Mock(returncode=0)
  stdin=types.SimpleNamespace(buffer=io.BytesIO(json.dumps(payload).encode()))
  with mock.patch.object(pathlib.Path,'home',return_value=home),mock.patch('sys.stdin',stdin),mock.patch.object(m.subprocess,'run',side_effect=lint),contextlib.redirect_stdout(io.StringIO()) as out:exec(program,{})
  value=json.loads(out.getvalue());self.assertNotIn('PRIVATE_CONFIG_MUST_NOT_RETAIN',out.getvalue());self.assertLess(len(out.getvalue()),24576)
  return value,calls
 def test_exact_new_owner_command_keeps_consumed_legacy_profiles_frozen(self):
  import copy
  m=self.module();event,env=self.event(m)
  self.assertEqual(m.dependency_event(event,env),{'control_source_sha':'b'*40,'run_id':'777','command_comment_id':987})
  self.assertEqual(m.CHECKED_OPERATION,'install-20261018-fcfe503e-v1');self.assertEqual(m.CHECKED_HASH,'ef60da2d948c42b7741771ef1e1a091280c14cd79eafea10d737cb7067e55463')
  for name,value in {'GITHUB_REF':'refs/heads/other','GITHUB_SHA':'c'*40,'GITHUB_RUN_ATTEMPT':'2','GITHUB_ACTOR':'other','GITHUB_TRIGGERING_ACTOR':'other','GITHUB_EVENT_NAME':'workflow_dispatch','GITHUB_WORKFLOW_REF':'other','GITHUB_RUN_ID':'0'}.items():
   with self.subTest(name=name),self.assertRaises(RuntimeError):m.dependency_event(event,{**env,name:value})
  for body in (event['comment']['body']+' ',event['comment']['body'].replace(m.DEPENDENCY_OPERATION,'retry-v2'),f'{m.CHECKED_PREFIX} '+env['GITHUB_SHA']+' '+m.CHECKED_OPERATION):
   changed=copy.deepcopy(event);changed['comment']['body']=body
   with self.assertRaises(RuntimeError):m.dependency_event(changed,env)
  for field,value in [('id',True),('user',{'id':1}),('author_association','MEMBER')]:
   changed=copy.deepcopy(event);changed['comment'][field]=value
   with self.assertRaises(RuntimeError):m.dependency_event(changed,env)
 def admission_api(self,m,event,identity):
  import base64
  bodies={path:b'<?php /* candidate '+path.encode()+b' */ ?>' for path in m.DEPENDENCY_CANDIDATES}
  m.DEPENDENCY_CANDIDATES={path:{**m.DEPENDENCY_CANDIDATES[path],'blob':m.blob(body),'sha256':m.sha(body),'bytes':len(body)} for path,body in bodies.items()}
  approval='immutable owner source claim';m.DEPENDENCY_APPROVAL_HASH=m.sha(approval.encode())
  collector_body=('existing reviewed collector '+m.DEPENDENCY_LOCAL_SOURCE).encode()
  m.DEPENDENCY_COLLECTORS={p:m.blob(collector_body) for p in m.DEPENDENCY_COLLECTORS}
  def api(path):
   if path=='/git/ref/heads/main':return {'object':{'sha':identity['control_source_sha']}}
   if path.startswith('/git/ref/heads/release/'):return {'object':{'sha':m.DEPENDENCY_RELEASE}}
   if path=='/issues/comments/'+str(m.DEPENDENCY_APPROVAL):return {'id':m.DEPENDENCY_APPROVAL,'user':{'id':226193297},'author_association':'OWNER','body':approval}
   if path=='/issues/comments/987':return event['comment']
   if path=='/issues/comments/'+str(m.DEPENDENCY_BLOCKED_COMMAND):return {'id':m.DEPENDENCY_BLOCKED_COMMAND,'user':{'id':226193297},'author_association':'OWNER','body':f'{m.DEPENDENCY_PREFIX} {m.DEPENDENCY_BLOCKED_CONTROL} {m.DEPENDENCY_OPERATION}'}
   if path=='/actions/runs/'+str(m.DEPENDENCY_BLOCKED_RUN):return {'id':m.DEPENDENCY_BLOCKED_RUN,'run_attempt':1,'event':'issue_comment','path':m.CHECKED_WORKFLOW,'head_sha':m.DEPENDENCY_BLOCKED_CONTROL,'status':'completed','conclusion':'failure'}
   if path=='/actions/jobs/'+str(m.DEPENDENCY_BLOCKED_JOB):return {'id':m.DEPENDENCY_BLOCKED_JOB,'run_id':m.DEPENDENCY_BLOCKED_RUN,'run_attempt':1,'name':'dependency-install','head_sha':m.DEPENDENCY_BLOCKED_CONTROL,'status':'completed','conclusion':'failure'}
   if path=='/actions/artifacts/'+str(m.DEPENDENCY_BLOCKED_ARTIFACT):return {'id':m.DEPENDENCY_BLOCKED_ARTIFACT,'name':'search3-root-gateway-dependencies-39227575241-1','size_in_bytes':416,'digest':m.DEPENDENCY_BLOCKED_DIGEST,'expired':False,'workflow_run':{'id':m.DEPENDENCY_BLOCKED_RUN,'head_sha':m.DEPENDENCY_BLOCKED_CONTROL}}
   if path.startswith('/contents/'):
    for target,record in m.DEPENDENCY_CANDIDATES.items():
     if path.startswith('/contents/'+record['path']+'?'):return {'sha':record['blob'],'content':base64.b64encode(bodies[target]).decode()}
    return {'sha':m.blob(collector_body),'content':base64.b64encode(collector_body).decode()}
   if path=='/git/commits/'+identity['control_source_sha']:return {'sha':identity['control_source_sha'],'tree':{'sha':'e'*40},'parents':[{'sha':'a'*40},{'sha':'d'*40}]}
   if path=='/git/commits/'+('d'*40):return {'sha':'d'*40,'tree':{'sha':'e'*40},'parents':[{'sha':'a'*40}]}
   if '/check-runs?check_name=' in path:
    name=path.split('check_name=',1)[1].split('&',1)[0];head=path.split('/')[2]
    return {'total_count':1,'check_runs':[{'id':100,'name':name,'head_sha':head,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}}]}
   if '/check-runs?' in path:return {'total_count':8,'check_runs':[{'id':i,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}} for i in m.DEPENDENCY_CHECK_IDS]+[{'id':117963841770,'status':'completed','conclusion':'skipped'}]}
   if path.startswith('/git/commits/'):return {'tree':{'sha':m.DEPENDENCY_LOCAL_TREE},'parents':[{'sha':m.DEPENDENCY_LOCAL_PARENT}]}
   if path.startswith('/compare/'):return {'status':'ahead','ahead_by':1,'behind_by':0,'total_commits':1,'files':[{'filename':'v2/data/local-tv-catalog-v1.php','status':'modified','sha':m.DEPENDENCY_CANDIDATES['data/local-tv-catalog-v1.php']['blob']}]}
   if path.startswith('/issues/4217/comments?'):return [{'id':m.DEPENDENCY_BLOCKED_COMMAND,'body':f'{m.DEPENDENCY_PREFIX} {m.DEPENDENCY_BLOCKED_CONTROL} {m.DEPENDENCY_OPERATION}'},{'id':987,'body':event['comment']['body']}]
   raise AssertionError('unrecognized GitHub path '+path)
  return api,bodies
 def test_admission_binds_two_sources_seven_ci_immutable_approval_delivery_scope_and_collectors(self):
  import copy,base64
  m=self.module();event,env=self.event(m);identity=m.dependency_event(event,env);api,bodies=self.admission_api(m,event,identity)
  with mock.patch.object(m,'checked_api',side_effect=api):self.assertEqual(m.dependency_admission(identity),bodies)
  for variant in ('main','release','approval_body','approval_owner','control_merge','control_tree','source_ci','main_ci','bytes','blob','ci','delivery_tree','delivery_parent','delivery_scope','collector','duplicate','replay_different_control','incomplete','fresh_command'):
   def bad(path):
    value=copy.deepcopy(api(path))
    if variant=='main' and path=='/git/ref/heads/main':value['object']['sha']='c'*40
    if variant=='release' and path.startswith('/git/ref/heads/release/'):value['object']['sha']='c'*40
    if path.startswith('/issues/comments/'):
     if variant=='approval_body':value['body']+=' changed'
     if variant=='approval_owner':value['user']['id']=1
     if variant=='fresh_command' and path=='/issues/comments/987':value['body']+=' changed'
    if path.startswith('/contents/'):
     if variant=='bytes':value['content']=base64.b64encode(b'foreign').decode()
     if variant=='blob':value['sha']='c'*40
     if variant=='collector' and any(path.startswith('/contents/'+p+'?') for p in m.DEPENDENCY_COLLECTORS):value['content']=base64.b64encode(b'old source').decode();value['sha']=m.blob(b'old source')
    if variant=='ci' and path.startswith('/commits/'+m.DEPENDENCY_SOURCE+'/'):value['check_runs'][0]['conclusion']='failure'
    if variant=='control_merge' and path=='/git/commits/'+identity['control_source_sha']:value['parents']=[{'sha':'a'*40}]
    if variant=='control_tree' and path=='/git/commits/'+('d'*40):value['tree']['sha']='c'*40
    if variant=='source_ci' and path.startswith('/commits/'+('d'*40)+'/'):value['check_runs'][0]['conclusion']='failure'
    if variant=='main_ci' and path.startswith('/commits/'+identity['control_source_sha']+'/'):value['check_runs'][0]['conclusion']='failure'
    if path=='/git/commits/'+m.DEPENDENCY_LOCAL_SOURCE:
     if variant=='delivery_tree':value['tree']['sha']='c'*40
     if variant=='delivery_parent':value['parents']=[{'sha':'c'*40}]
    if variant=='delivery_scope' and path.startswith('/compare/'):value['files'].append({'filename':'foreign-path'})
    if path.startswith('/issues/4217/comments?'):
     if variant=='duplicate':value.append({'id':988,'body':event['comment']['body']})
     if variant=='replay_different_control':value.append({'id':988,'body':event['comment']['body'].replace('b'*40,'c'*40)})
     if variant=='incomplete':value=[{'id':i,'body':'ordinary comment'} for i in range(100)]
    return value
   with self.subTest(variant=variant),mock.patch.object(m,'checked_api',side_effect=bad),self.assertRaises(RuntimeError):m.dependency_admission(identity)
 def test_two_file_transaction_preserves_unrelated_bodies_and_private_backups(self):
  for compatible in (False,True):
   with self.subTest(compatible=compatible),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home,compatible)
    before={p:(project/p).read_bytes() for p in m.DEPENDENCY_EXPECTED};result,calls=self.execute(m,home,payload,program)
    self.assertTrue(m.dependency_completed(identity,result),result);self.assertEqual(len(calls),5 if compatible else 6)
    self.assertEqual(result['compatible_catalogue'],compatible);self.assertEqual(result['root_write_attempts'],1 if compatible else 2)
    self.assertEqual(result['written_files'],(['api-v2.php'] if compatible else ['data/local-tv-catalog-v1.php','api-v2.php']))
    for p in before:self.assertEqual((project/p).read_bytes(),candidates.get(p,before[p]))
    op=home/'.anytoour-search3-root-gateway'/m.DEPENDENCY_OPERATION
    self.assertEqual(json.loads((op/'result.json').read_text()),result)
    for p in op.glob('previous-*.php'):self.assertEqual(p.stat().st_mode&0o777,0o600)
    self.assertEqual((project/'api-v2.php').stat().st_mode&0o777,0o644);self.assertFalse(list(home.rglob('MUST_NOT_EXECUTE')))
    for forbidden in ('urllib','action=health','repair_replace','mysql','PDO'):self.assertNotIn(forbidden,program)
 def test_exact_name_filter_finds_guard_behind_383_unrelated_jobs_and_rejects_truncation(self):
  import copy
  m=self.module();head='b'*40;name='guard'
  good={'total_count':1,'check_runs':[{'id':100,'name':name,'head_sha':head,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}}]}
  query='/commits/'+head+'/check-runs?check_name=guard&per_page=100'
  def api(path):
   if path==query:return copy.deepcopy(good)
   return {'total_count':383,'check_runs':[{'id':i,'name':'unrelated'} for i in range(100)]}
  with mock.patch.object(m,'checked_api',side_effect=api) as calls:m.dependency_green_check(head,name,'main_ci')
  calls.assert_called_once_with(query)
  for variant in ('truncated','wrong_count','boolean_count','duplicate','wrong_name','wrong_head','wrong_app','failed','pending'):
   value=copy.deepcopy(good)
   if variant=='truncated':value['total_count']=383
   if variant=='wrong_count':value['total_count']=2
   if variant=='boolean_count':value['total_count']=True
   if variant=='duplicate':value['total_count']=2;value['check_runs']*=2
   if variant=='wrong_name':value['check_runs'][0]['name']='other'
   if variant=='wrong_head':value['check_runs'][0]['head_sha']='c'*40
   if variant=='wrong_app':value['check_runs'][0]['app']['slug']='other'
   if variant=='failed':value['check_runs'][0]['conclusion']='failure'
   if variant=='pending':value['check_runs'][0]['status']='queued'
   with self.subTest(variant=variant),mock.patch.object(m,'checked_api',return_value=value),self.assertRaises(RuntimeError):m.dependency_green_check(head,name,'main_ci')
 def test_only_exact_retained_pre_ssh_blocked_operation_can_continue(self):
  import copy
  m=self.module();event,env=self.event(m);identity=m.dependency_event(event,env);api,bodies=self.admission_api(m,event,identity)
  with mock.patch.object(m,'checked_api',side_effect=api):m.dependency_pre_ssh_blocked_proof()
  variants=[('comment_owner','comment'),('comment_body','comment'),('run_head','run'),('run_attempt','run'),('run_pending','run'),('run_workflow','run'),('job_name','job'),('job_head','job'),('job_attempt','job'),('job_success','job'),('artifact_digest','artifact'),('artifact_size','artifact'),('artifact_expired','artifact'),('artifact_head','artifact'),('artifact_run','artifact')]
  for variant,kind in variants:
   def bad(path):
    value=copy.deepcopy(api(path))
    target={'comment':'/issues/comments/'+str(m.DEPENDENCY_BLOCKED_COMMAND),'run':'/actions/runs/'+str(m.DEPENDENCY_BLOCKED_RUN),'job':'/actions/jobs/'+str(m.DEPENDENCY_BLOCKED_JOB),'artifact':'/actions/artifacts/'+str(m.DEPENDENCY_BLOCKED_ARTIFACT)}[kind]
    if path!=target:return value
    if variant=='comment_owner':value['user']['id']=1
    if variant=='comment_body':value['body']+=' changed'
    if variant in ('run_head','job_head'):value['head_sha']='c'*40
    if variant in ('run_attempt','job_attempt'):value['run_attempt']=2
    if variant=='run_pending':value['status']='in_progress';value['conclusion']=None
    if variant=='run_workflow':value['path']='another/workflow.yml'
    if variant=='job_name':value['name']='another-job'
    if variant=='job_success':value['conclusion']='success'
    if variant=='artifact_digest':value['digest']='sha256:'+('c'*64)
    if variant=='artifact_size':value['size_in_bytes']=417
    if variant=='artifact_expired':value['expired']=True
    if variant=='artifact_head':value['workflow_run']['head_sha']='c'*40
    if variant=='artifact_run':value['workflow_run']['id']+=1
    return value
   with self.subTest(variant=variant),mock.patch.object(m,'checked_api',side_effect=bad),self.assertRaises(RuntimeError):m.dependency_admission(identity)
  for kind in ('missing_old','unknown_extra','duplicate_old'):
   def history(path):
    rows=copy.deepcopy(api(path))
    if path.startswith('/issues/4217/comments?'):
     if kind=='missing_old':rows=rows[1:]
     if kind=='unknown_extra':rows.insert(1,{'id':986,'body':event['comment']['body'].replace('b'*40,'c'*40)})
     if kind=='duplicate_old':rows.insert(0,rows[0])
    return rows
   with self.subTest(kind=kind),mock.patch.object(m,'checked_api',side_effect=history),self.assertRaises(RuntimeError):m.dependency_admission(identity)
 def test_pre_ssh_admission_failure_keeps_safe_reason_and_evidence_only(self):
  m=self.module();event,env=self.event(m)
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);path=home/'event.json';path.write_text(json.dumps(event))
   with mock.patch.dict(os.environ,{**env,'RUNNER_TEMP':td,'GITHUB_EVENT_PATH':str(path)}),mock.patch.object(m,'dependency_admission',side_effect=RuntimeError('main_ci')),mock.patch.object(m,'run') as network,contextlib.redirect_stdout(io.StringIO()),self.assertRaisesRegex(RuntimeError,'install_not_verified'):m.dependency_install()
   work=home/'search3-root-gateway-dependencies';value=json.loads((work/'evidence.json').read_text())
   self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['reason'],'main_ci');self.assertEqual({p.name for p in work.iterdir()},{'evidence.json'});network.assert_not_called()
 def test_no_replay_preserves_consumed_operation_receipt_and_all_bytes(self):
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home)
   first,_=self.execute(m,home,payload,program);self.assertTrue(m.dependency_completed(identity,first))
   before={str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()}
   replay,calls=self.execute(m,home,payload,program)
   self.assertEqual(replay['reason'],'operation_exists_no_replay');self.assertEqual(calls,[])
   self.assertEqual(before,{str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()})
 def test_wrong_dependency_payload_symlink_hardlink_and_predecessor_abort_without_php(self):
  import base64
  for variant in ('api','catalogue','helper','app','config_symlink','catalogue_symlink','parent_symlink','hardlink','candidate','extra_payload','private_parent'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home)
    if variant in ('api','catalogue','helper','app'):(project/{'api':'api-v2.php','catalogue':'data/local-tv-catalog-v1.php','helper':'data/db-v1.php','app':m.DEPENDENCY_APP}[variant]).write_bytes(b'foreign')
    if variant in ('config_symlink','catalogue_symlink'):
     p=project/('config.php' if variant=='config_symlink' else 'data/local-tv-catalog-v1.php');p.unlink();p.symlink_to(project/'data/db-v1.php')
    if variant=='parent_symlink':(project/'data').rename(home/'real-data');(project/'data').symlink_to(home/'real-data',target_is_directory=True)
    if variant=='hardlink':os.link(project/'api-v2.php',home/'foreign-link')
    if variant=='candidate':payload['candidates_b64']['api-v2.php']=base64.b64encode(b'wrong').decode()
    if variant=='extra_payload':payload['private_config']='PRIVATE_DETAIL'
    if variant=='private_parent':(home/'.anytoour-search3-root-gateway').mkdir(mode=0o755)
    result,calls=self.execute(m,home,payload,program)
    self.assertEqual(result['status'],'blocked_no_replay');self.assertEqual(result['root_write_attempts'],0);self.assertEqual(calls,[])
 def test_lint_and_stage_or_protected_races_abort_or_rollback_owned_partial_write(self):
  for variant in ('lint','helper','public','config','stage'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home);old={p:(project/p).read_bytes() for p in candidates}
    def callback(args,count):
     if count==4:
      if variant=='lint':return mock.Mock(returncode=1)
      if variant=='helper':(project/'data/db-v1.php').write_bytes(b'foreign helper')
      if variant=='public':(project/'index.php').write_bytes(b'foreign home')
      if variant=='config':(project/'config.php').write_bytes(b'foreign config')
      if variant=='stage':pathlib.Path(args[-1]).write_bytes(b'foreign stage')
    result,_=self.execute(m,home,payload,program,callback)
    self.assertFalse(m.dependency_completed(identity,result))
    self.assertEqual(result['root_write_attempts'],1 if variant=='stage' else 0)
    self.assertEqual(result['status'],'rolled_back_no_replay' if variant=='stage' else 'blocked_no_replay')
    for p,b in old.items():self.assertEqual((project/p).read_bytes(),b)
 def test_partial_second_replace_failure_rolls_back_only_first_owned_target(self):
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home);old={p:(project/p).read_bytes() for p in candidates};actual=os.replace
   def replace(src,dst):
    if pathlib.Path(src).name=='candidate-1.php':raise OSError('PRIVATE_DETAIL')
    return actual(src,dst)
   with mock.patch('os.replace',side_effect=replace):result,_=self.execute(m,home,payload,program)
   self.assertEqual(result['status'],'rolled_back_no_replay');self.assertEqual(result['root_write_attempts'],2);self.assertEqual(result['rollback_attempts'],1)
   for p,b in old.items():self.assertEqual((project/p).read_bytes(),b)
   self.assertNotIn('PRIVATE_DETAIL',json.dumps(result))
 def test_installed_lint_failure_restores_own_bytes_but_never_foreign_inode_or_same_body(self):
  for foreign in ('none','changed','same_body_foreign_inode'):
   with self.subTest(foreign=foreign),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home);old={p:(project/p).read_bytes() for p in candidates}
    def callback(args,count):
     if count==5:
      p=project/'data/local-tv-catalog-v1.php'
      if foreign=='changed':p.write_bytes(b'foreign owner')
      if foreign=='same_body_foreign_inode':
       temp=home/'foreign.php';temp.write_bytes(candidates['data/local-tv-catalog-v1.php']);temp.chmod(0o644);os.replace(temp,p)
      return mock.Mock(returncode=1)
    result,_=self.execute(m,home,payload,program,callback)
    self.assertEqual(result['status'],'rolled_back_no_replay' if foreign=='none' else 'unknown_no_replay')
    self.assertEqual((project/'api-v2.php').read_bytes(),old['api-v2.php'])
    self.assertEqual((project/'data/local-tv-catalog-v1.php').read_bytes(),old['data/local-tv-catalog-v1.php'] if foreign=='none' else b'foreign owner' if foreign=='changed' else candidates['data/local-tv-catalog-v1.php'])
 def test_safe_receipt_rejects_private_data_wrong_sources_partial_inventory_and_false_success(self):
  import copy
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home);good,_=self.execute(m,home,payload,program)
  for mutate in (lambda r:r.__setitem__('private_config','PRIVATE_DETAIL'),lambda r:r.__setitem__('source_sha','c'*40),lambda r:r.__setitem__('local_source_sha','c'*40),lambda r:r.__setitem__('file_writes',True),lambda r:r.__setitem__('database_writes',1),lambda r:r.__setitem__('supplier_calls',1),lambda r:r.__setitem__('root_write_attempts',3),lambda r:r.__setitem__('compatible_catalogue',True),lambda r:r['after_files'].pop('data/db-v1.php'),lambda r:r['after_files']['api-v2.php'].__setitem__('sha256','c'*64),lambda r:r['production_after'].__setitem__('config.php','c'*64),lambda r:r.__setitem__('syntax_installed','not_run')):
   changed=copy.deepcopy(good);mutate(changed);self.assertFalse(m.dependency_completed(identity,changed))
 def test_runner_sanitizes_admission_and_transport_failures_and_never_retains_keys(self):
  for variant in ('admission','transport','private_reply','success'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home);reply,_=self.execute(m,home,payload,program)
    if variant=='private_reply':reply['private_config']='PRIVATE_DETAIL'
    event,env=self.event(m);path=home/'event.json';path.write_text(json.dumps(event))
    def run(args,**kwargs):
     if args[0]=='ssh-keyscan':return b'synthetic host'
     self.assertEqual(args[0],'ssh');self.assertIn('StrictHostKeyChecking=yes',args);self.assertEqual(json.loads(kwargs['input'])['candidates_b64'],payload['candidates_b64'])
     if variant=='transport':raise m.subprocess.CalledProcessError(255,args,stderr=b'PRIVATE_DETAIL')
     return json.dumps(reply).encode()
    with mock.patch.dict(os.environ,{**env,'RUNNER_TEMP':td,'GITHUB_EVENT_PATH':str(path),'PREVIEW_HOST':'synthetic.example','PREVIEW_USER':'synthetic','PREVIEW_KEY':'c3ludGhldGlj'}),mock.patch.object(m,'dependency_admission',side_effect=RuntimeError('PRIVATE_DETAIL') if variant=='admission' else None,return_value=candidates),mock.patch.object(m,'run',side_effect=run) as network,contextlib.redirect_stdout(io.StringIO()) as out:
     if variant=='success':m.dependency_install()
     else:
      with self.assertRaisesRegex(RuntimeError,'install_not_verified'):m.dependency_install()
    work=home/'search3-root-gateway-dependencies';evidence=json.loads((work/'evidence.json').read_text())
    self.assertNotIn('PRIVATE_DETAIL',json.dumps(evidence));self.assertNotIn('PRIVATE_DETAIL',out.getvalue());self.assertFalse((work/'key').exists());self.assertFalse((work/'known').exists())
    if variant=='admission':network.assert_not_called()
 @unittest.skipUnless(os.environ.get('SEARCH3_DEPENDENCY_INSTALL_NATIVE_CI')=='1','native PHP CI required')
 def test_native_two_file_transaction_only_lints_side_effect_bodies(self):
  import shutil
  self.assertIsNotNone(shutil.which('php'),'native PHP required in exact-head CI')
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidates,identity,payload,program=self.fixture(m,home);result,calls=self.execute(m,home,payload,program,native=True)
   self.assertTrue(m.dependency_completed(identity,result),result);self.assertEqual(len(calls),6);self.assertFalse(list(home.rglob('MUST_NOT_EXECUTE')))
 @unittest.skipUnless(os.environ.get('SEARCH3_DEPENDENCY_INSTALL_NATIVE_CI')=='1','native reviewed-artifact PHP CI required')
 def test_native_actual_product_api_and_delivery_class_pass_nine_source_layout_cases(self):
  import base64,copy,shutil
  m=self.module();php=shutil.which('php');self.assertIsNotNone(php,'native PHP required in exact-head CI')
  product=pathlib.Path(os.environ['RECOVERY_PRODUCT_SOURCE_ROOT']);local=pathlib.Path(os.environ['RECOVERY_LOCAL_SOURCE_ROOT'])
  files={'v2/api-v2.php':(product/'v2/api-v2.php',m.DEPENDENCY_CANDIDATES['api-v2.php']['sha256']),
         'v2/data/local-tv-catalog-v1.php':(local/'v2/data/local-tv-catalog-v1.php',m.DEPENDENCY_CANDIDATES['data/local-tv-catalog-v1.php']['sha256']),
         'tests/search3-tourvisor-results-limit.php':(product/'tests/search3-tourvisor-results-limit.php','dc366469fd0a9794742da1e12842f4af71a2057c4399f24a7f61a9889bf707f5')}
  for path in ('data/db-v1.php','data/hotel-details-v1.php'):files['v2/'+path]=(local/'v2'/path,m.DEPENDENCY_EXPECTED[path])
  with tempfile.TemporaryDirectory() as td:
   fixture=pathlib.Path(td)
   for path,(source,digest) in files.items():
    body=source.read_bytes();self.assertEqual(m.sha(body),digest,path);target=fixture/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(body)
   before={path:m.sha((fixture/path).read_bytes()) for path in files}
   result=m.subprocess.run([php,str(fixture/'tests/search3-tourvisor-results-limit.php')],capture_output=True,text=True,check=False,timeout=60,env={'PATH':os.environ['PATH'],'HOME':str(fixture)})
   self.assertEqual(result.returncode,0,result.stdout+result.stderr)
   self.assertIn('SEARCH3_TOURVISOR_DEPENDENCY_REUSE_OK native_subprocesses=9 legacy_distinct_copy_fatal=1 supplier_http=0 pdo_constructed=0 sql=0',result.stdout)
   self.assertEqual(before,{path:m.sha((fixture/path).read_bytes()) for path in files})
   print('RECOVERY_REVIEWED_ARTIFACT_DEPENDENCY_REUSE_OK native_subprocesses=9 supplier_http=0 pdo_constructed=0 sql=0')
  # The real two-file envelope exceeds the historical 64 KiB one-file cap;
  # exercise the new fixed 128 KiB mode with actual reviewed candidate bytes.
  records=copy.deepcopy(m.DEPENDENCY_CANDIDATES)
  actual={path:files['v2/'+path][0].read_bytes() for path in records}
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,_,identity,payload,_=self.fixture(m,home)
   m.DEPENDENCY_CANDIDATES=records;payload['candidates_b64']={path:base64.b64encode(body).decode() for path,body in actual.items()}
   size=len(json.dumps(payload).encode());self.assertGreater(size,65536);self.assertLessEqual(size,131072)
   result,calls=self.execute(m,home,payload,m.dependency_remote(),native=True)
   self.assertTrue(m.dependency_completed(identity,result),result);self.assertEqual(len(calls),6)
   for path,body in actual.items():self.assertEqual((project/path).read_bytes(),body)

class TvDiagnosticInstall(unittest.TestCase):
 module=CheckedInstall.module
 execute=CheckedInstall.execute
 def event(self,m):
  event,env=CheckedInstall.event(self,m);event['issue']={'number':4709}
  event['comment']['body']=f'{m.TV_DIAGNOSTIC_PREFIX} '+env['GITHUB_SHA']+' '+m.TV_DIAGNOSTIC_OPERATION
  return event,env
 def fixture(self,m,home):
  import base64
  project=home/'www/anytoour.ru';expected={}
  for path in m.TV_DIAGNOSTIC_EXPECTED:
   p=project/path;p.parent.mkdir(parents=True,exist_ok=True)
   p.write_bytes(b'<?php file_put_contents(__DIR__."/MUST_NOT_EXECUTE","old"); ?>' if path=='api-v2.php' else b'fixture '+path.encode());p.chmod(0o644)
   expected[path]=m.sha(p.read_bytes())
  for path in m.TV_DIAGNOSTIC_PROTECTED:
   p=project/path;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b'public fixture '+path.encode());p.chmod(0o644)
  candidate=b'<?php file_put_contents(__DIR__."/MUST_NOT_EXECUTE","new"); ?>'
  m.TV_DIAGNOSTIC_EXPECTED=expected;m.TV_DIAGNOSTIC_HASH=m.sha(candidate);m.TV_DIAGNOSTIC_BLOB=m.blob(candidate);m.TV_DIAGNOSTIC_BYTES=len(candidate)
  event,env=self.event(m);identity=m.tv_diagnostic_event(event,env)
  return project,candidate,identity,{**identity,'candidate_b64':base64.b64encode(candidate).decode()},m.tv_diagnostic_remote()
 def test_exact_new_event_does_not_reopen_historical_operations(self):
  import copy
  m=self.module();event,env=self.event(m);self.assertEqual(m.tv_diagnostic_event(event,env)['command_comment_id'],987)
  self.assertEqual(m.CHECKED_OPERATION,'install-20261018-fcfe503e-v1');self.assertEqual(m.DEPENDENCY_OPERATION,'install-20261019-dependency-reuse-v1')
  self.assertEqual(m.CHECKED_HASH,'ef60da2d948c42b7741771ef1e1a091280c14cd79eafea10d737cb7067e55463')
  for number in (3419,4217,2530,996):
   bad=copy.deepcopy(event);bad['issue']['number']=number
   with self.assertRaises(RuntimeError):m.tv_diagnostic_event(bad,env)
  for key,value in {'GITHUB_REF':'refs/heads/other','GITHUB_SHA':'c'*40,'GITHUB_RUN_ATTEMPT':'2','GITHUB_ACTOR':'other','GITHUB_TRIGGERING_ACTOR':'other','GITHUB_EVENT_NAME':'workflow_dispatch','GITHUB_WORKFLOW_REF':'other'}.items():
   with self.subTest(key=key),self.assertRaises(RuntimeError):m.tv_diagnostic_event(event,{**env,key:value})
  for value in (event['comment']['body']+' ',event['comment']['body'].replace(m.TV_DIAGNOSTIC_OPERATION,m.CHECKED_OPERATION),event['comment']['body'].replace(m.TV_DIAGNOSTIC_PREFIX,m.CHECKED_PREFIX)):
   bad=copy.deepcopy(event);bad['comment']['body']=value
   with self.assertRaises(RuntimeError):m.tv_diagnostic_event(bad,env)
  bad=copy.deepcopy(event);bad['issue']['pull_request']={}
  with self.assertRaises(RuntimeError):m.tv_diagnostic_event(bad,env)
 def admission_api(self,m,event,identity,body):
  import base64
  def api(path):
   if path=='/git/ref/heads/main':return {'object':{'sha':identity['control_source_sha']}}
   if path.startswith('/git/ref/heads/release/'):return {'object':{'sha':m.TV_DIAGNOSTIC_RELEASE}}
   if path=='/git/commits/'+identity['control_source_sha']:return {'sha':identity['control_source_sha'],'parents':[{'sha':'a'*40},{'sha':'d'*40}],'tree':{'sha':'e'*40}}
   if path=='/git/commits/'+('d'*40):return {'sha':'d'*40,'tree':{'sha':'e'*40}}
   if path=='/git/commits/'+('a'*40):return {'sha':'a'*40,'tree':{'sha':'e'*40},'parents':[]}
   if '/check-runs?check_name=' in path:
    name=path.split('check_name=',1)[1].split('&',1)[0];head=path.split('/')[2]
    return {'total_count':1,'check_runs':[{'id':1,'name':name,'head_sha':head,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}}]}
   if '/check-runs?' in path:return {'total_count':len(m.TV_DIAGNOSTIC_CHECK_IDS),'check_runs':[{'id':i,'head_sha':m.TV_DIAGNOSTIC_SOURCE,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}} for i in m.TV_DIAGNOSTIC_CHECK_IDS]}
   if path.startswith('/contents/v2/api-v2.php?'):return {'sha':m.TV_DIAGNOSTIC_BLOB,'content':base64.b64encode(body).decode()}
   if path.startswith('/contents/'):return {'type':'file','sha':'9'*40}
   if path.startswith('/issues/comments/'):return event['comment']
   return [{'id':987,'body':event['comment']['body']}]
  return api
 def test_admission_requires_reviewed_source_release_checks_and_fresh_single_command(self):
  import base64
  m=self.module();event,env=self.event(m);identity=m.tv_diagnostic_event(event,env);body=b'<?php /* checked diagnostic fixture */ ?>'
  m.TV_DIAGNOSTIC_RELEASE='f'*40;m.TV_DIAGNOSTIC_BLOB=m.blob(body);m.TV_DIAGNOSTIC_HASH=m.sha(body);m.TV_DIAGNOSTIC_BYTES=len(body)
  api=self.admission_api(m,event,identity,body)
  with mock.patch.object(m,'checked_api',side_effect=api):self.assertEqual(m.tv_diagnostic_admission(identity),body)
  for variant in ('main','release','tree','source_bytes','source_check','main_check','duplicate','edited_command','incomplete'):
   def bad(path):
    value=api(path)
    if variant=='main' and path=='/git/ref/heads/main':value['object']['sha']='c'*40
    if variant=='release' and '/heads/release/' in path:value['object']['sha']='c'*40
    if variant=='tree' and path=='/git/commits/'+('d'*40):value['tree']['sha']='c'*40
    if variant=='source_bytes' and path.startswith('/contents/'):value['content']=base64.b64encode(b'wrong').decode()
    if variant=='source_check' and '/check-runs?' in path and 'check_name=' not in path:value['check_runs'][0]['conclusion']='failure'
    if variant=='main_check' and 'check_name=' in path:value['check_runs'][0]['conclusion']='skipped'
    if variant=='edited_command' and path.startswith('/issues/comments/'):value={**value,'body':'changed'}
    if path.startswith('/issues/4709/'):
     if variant=='duplicate':value.append({'id':988,'body':event['comment']['body'].replace('b'*40,'c'*40)})
     if variant=='incomplete':value=[{'id':i,'body':'ordinary'} for i in range(100)]
    return value
   with self.subTest(variant=variant),mock.patch.object(m,'checked_api',side_effect=bad),self.assertRaises(RuntimeError):m.tv_diagnostic_admission(identity)
  m.TV_DIAGNOSTIC_RELEASE='PENDING_REVIEWED_RELEASE'
  with mock.patch.object(m,'checked_api') as network,self.assertRaisesRegex(RuntimeError,'diagnostic_pins_incomplete'):m.tv_diagnostic_admission(identity)
  network.assert_not_called()
 def test_reviewed_control_survives_unrelated_merges_only_with_identical_owned_blobs(self):
  m=self.module();current,accepted,unrelated,old,reviewed=('b'*40,'a'*40,'c'*40,'d'*40,'e'*40)
  owned={path:str(index+1)*40 for index,path in enumerate(m.TV_DIAGNOSTIC_CONTROL_PATHS)}
  commits={current:{'sha':current,'tree':{'sha':'9'*40},'parents':[{'sha':accepted},{'sha':unrelated}]},
           unrelated:{'sha':unrelated,'tree':{'sha':'8'*40}},
           accepted:{'sha':accepted,'tree':{'sha':'7'*40},'parents':[{'sha':old},{'sha':reviewed}]},
           reviewed:{'sha':reviewed,'tree':{'sha':'7'*40}},old:{'sha':old,'tree':{'sha':'6'*40},'parents':[]}}
  def api(path):
   import copy
   if path.startswith('/git/commits/'):return copy.deepcopy(commits[path.rsplit('/',1)[1]])
   if path.startswith('/contents/'):
    name=path[len('/contents/'):].split('?ref=',1)[0];return {'type':'file','sha':owned[name]}
   name=path.split('check_name=',1)[1].split('&',1)[0];head=path.split('/')[2]
   if head==unrelated and name=='contracts':return {'total_count':0,'check_runs':[]}
   return {'total_count':1,'check_runs':[{'id':1,'name':name,'head_sha':head,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}}]}
  for same_tree in (False,True):
   if same_tree:commits[unrelated]['tree']['sha']='9'*40
   with self.subTest(same_tree=same_tree),mock.patch.object(m,'checked_api',side_effect=api):
    proof=m.tv_diagnostic_reviewed_control(current)
   self.assertEqual(proof,{'merge_sha':accepted,'source_sha':reviewed,'owned_blobs':owned})
  for variant in ('changed_owned','incomplete_ancestry','truncated_checks','foreign_check_head'):
   def bad(path):
    value=api(path)
    if variant=='changed_owned' and path.startswith('/contents/') and path.endswith('?ref='+reviewed):value['sha']='f'*40
    if variant=='incomplete_ancestry' and path=='/git/commits/'+accepted:value.pop('parents')
    if variant=='truncated_checks' and path.startswith('/commits/'+reviewed+'/'):value['total_count']=2
    if variant=='foreign_check_head' and path.startswith('/commits/'+reviewed+'/'):value['check_runs'][0]['head_sha']=unrelated
    return value
   with self.subTest(variant=variant),mock.patch.object(m,'checked_api',side_effect=bad),self.assertRaises(RuntimeError):m.tv_diagnostic_reviewed_control(current)
 def test_control_ancestry_has_a_hard_depth_bound(self):
  m=self.module();heads=[f'{index+1:040x}' for index in range(17)];visited=[]
  def api(path):
   if path.startswith('/contents/'):return {'type':'file','sha':'f'*40}
   head=path.rsplit('/',1)[1];visited.append(head);index=heads.index(head)
   return {'sha':head,'tree':{'sha':'e'*40},'parents':[{'sha':heads[index+1]}]}
  with mock.patch.object(m,'checked_api',side_effect=api),self.assertRaisesRegex(RuntimeError,'reviewed_control_missing'):m.tv_diagnostic_reviewed_control(heads[0])
  self.assertEqual(visited,heads[:16])
 def test_shared_remote_updates_only_api_and_replay_preserves_first_receipt(self):
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home)
   self.assertTrue(program.endswith(m.REMOTE_CHECKED_INSTALL));before={p:(project/p).read_bytes() for p in (*m.TV_DIAGNOSTIC_EXPECTED,*m.TV_DIAGNOSTIC_PROTECTED)}
   first,calls=self.execute(m,home,payload,program);self.assertTrue(m.tv_diagnostic_safe_receipt(identity,first,complete=True),first)
   self.assertEqual(len(calls),3);self.assertEqual((project/'api-v2.php').read_bytes(),candidate)
   for path,body in before.items():
    if path!='api-v2.php':self.assertEqual((project/path).read_bytes(),body)
   self.assertFalse(list(home.rglob('MUST_NOT_EXECUTE')));self.assertNotIn('action=health',program)
   saved={str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()}
   second,calls=self.execute(m,home,payload,program);self.assertEqual(second['reason'],'operation_exists_no_replay');self.assertEqual(calls,[])
   self.assertEqual(saved,{str(p.relative_to(home)):(p.read_bytes(),p.stat().st_mtime_ns) for p in home.rglob('*') if p.is_file()})
 def test_drift_races_and_rollback_do_not_overwrite_foreign_bytes(self):
  for variant in ('helper','pre_lint_race','post_lint_failure','foreign_after_replace'):
   with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
    m=self.module();home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home);old=(project/'api-v2.php').read_bytes()
    if variant=='helper':(project/'data/db-v1.php').write_bytes(b'foreign helper')
    def callback(args,count):
     if variant=='pre_lint_race' and count==2:(project/'config.php').write_bytes(b'foreign public config')
     if variant in ('post_lint_failure','foreign_after_replace') and count==3:
      if variant=='foreign_after_replace':(project/'api-v2.php').write_bytes(b'foreign owner')
      return mock.Mock(returncode=1)
    result,calls=self.execute(m,home,payload,program,callback);self.assertFalse(m.tv_diagnostic_safe_receipt(identity,result,complete=True))
    self.assertEqual((project/'api-v2.php').read_bytes(),b'foreign owner' if variant=='foreign_after_replace' else old)
    self.assertEqual(result['root_write_attempts'],0 if variant in ('helper','pre_lint_race') else 1)
    self.assertEqual(result['rollback_attempts'],1 if variant=='post_lint_failure' else 0)
 def test_receipt_rejects_private_fields_wrong_identity_and_false_success(self):
  import copy
  m=self.module()
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home);good,_=self.execute(m,home,payload,program)
  for mutate in (lambda r:r.__setitem__('private','secret'),lambda r:r.__setitem__('source_sha','a'*40),lambda r:r.__setitem__('file_writes',True),lambda r:r.__setitem__('supplier_calls',1),lambda r:r['after_files'].pop('data/db-v1.php'),lambda r:r['production_after'].__setitem__('config.php','c'*64)):
   bad=copy.deepcopy(good);mutate(bad);self.assertFalse(m.tv_diagnostic_safe_receipt(identity,bad,complete=True))
 def test_runner_does_not_connect_on_admission_failure(self):
  m=self.module();event,env=self.event(m)
  with tempfile.TemporaryDirectory() as td:
   root=pathlib.Path(td);path=root/'event.json';path.write_text(json.dumps(event))
   with mock.patch.dict(os.environ,{**env,'RUNNER_TEMP':td,'GITHUB_EVENT_PATH':str(path)}),mock.patch.object(m,'tv_diagnostic_admission',side_effect=RuntimeError('PRIVATE_DETAIL')),mock.patch.object(m,'run') as network,contextlib.redirect_stdout(io.StringIO()) as out,self.assertRaisesRegex(RuntimeError,'install_not_verified'):m.tv_diagnostic_install()
   network.assert_not_called();self.assertNotIn('PRIVATE_DETAIL',out.getvalue());self.assertFalse(list(root.rglob('key')))
   evidence=json.loads((root/'search3-root-tv-diagnostics/evidence.json').read_text())
   self.assertEqual(evidence['status'],'blocked_no_replay');self.assertEqual(evidence['failure_phase'],'admission');self.assertIs(evidence['ssh_started'],False)
 def test_workflow_is_exact_and_artifact_excludes_keys(self):
  m=self.module();workflow=(R/m.CHECKED_WORKFLOW).read_text();job=workflow.split('  tv-diagnostic-install:\n',1)[1].split('  dependency-install:\n',1)[0]
  for guard in ('group: deploy-anytoour-production','cancel-in-progress: false','github.run_attempt == 1','github.event.issue.number == 4709',"github.event.action == 'created'","github.actor == 'pyatkoff'","github.triggering_actor == 'pyatkoff'","github.event.comment.user.id == 226193297",m.TV_DIAGNOSTIC_OPERATION,'scripts/deploy/search3_root_gateway_install.py tv_diagnostic_install',"TV_DIAGNOSTIC_NATIVE_CI: '1'"):
   self.assertIn(guard,job)
  artifact=job.split('          path: |\n',1)[1].split('          if-no-files-found:',1)[0]
  self.assertEqual(len(artifact.strip().splitlines()),3);self.assertNotIn('/key',artifact);self.assertNotIn('/known',artifact);self.assertNotIn('health',job)
 @unittest.skipUnless(os.environ.get('TV_DIAGNOSTIC_NATIVE_CI')=='1','native reviewed diagnostic PHP CI required')
 def test_native_actual_candidate_only_lints_and_changes_api(self):
  m=self.module();body=(pathlib.Path(os.environ['TV_DIAGNOSTIC_PRODUCT_SOURCE_ROOT'])/'v2/api-v2.php').read_bytes()
  self.assertEqual((m.blob(body),m.sha(body),len(body)),(m.TV_DIAGNOSTIC_BLOB,m.TV_DIAGNOSTIC_HASH,m.TV_DIAGNOSTIC_BYTES))
  with tempfile.TemporaryDirectory() as td:
   home=pathlib.Path(td);project,candidate,identity,payload,program=self.fixture(m,home)
   import base64
   m.TV_DIAGNOSTIC_BLOB=m.blob(body);m.TV_DIAGNOSTIC_HASH=m.sha(body);m.TV_DIAGNOSTIC_BYTES=len(body);payload['candidate_b64']=base64.b64encode(body).decode()
   result,calls=self.execute(m,home,payload,m.tv_diagnostic_remote(),native=True)
   self.assertTrue(m.tv_diagnostic_safe_receipt(identity,result,complete=True),result);self.assertEqual(len(calls),3);self.assertEqual((project/'api-v2.php').read_bytes(),body)

if __name__=='__main__':unittest.main()


