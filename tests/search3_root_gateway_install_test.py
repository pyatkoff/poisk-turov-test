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
  m=self.module();event,env=self.event(m);identity=m.checked_event(event,env);body=(R/'v2/api-v2.php').read_bytes()
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

if __name__=='__main__':unittest.main()


