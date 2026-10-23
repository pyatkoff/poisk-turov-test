#!/usr/bin/env python3
"""Actual stock REMOTE, real PHP lint, synthetic HOME only; no SSH/provider/DB."""
import copy, hashlib, importlib.util, io, json, os, shutil, subprocess, tarfile, tempfile, unittest
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('capture_install_core',ROOT/'scripts/deploy/int_server_executor.py')
core=importlib.util.module_from_spec(spec);spec.loader.exec_module(core)
p=core.passive_fuel_readiness
SOURCE='04c0647ac30e1fd195aaf78d3d538178dcd59ebb'
COMMAND=core.parse_command(core.PREFIX+SOURCE+' '+p.CAPTURE_INSTALL_MODE+' '+p.CAPTURE_INSTALL_OPERATION)
READ_COMMAND=core.parse_command(core.PREFIX+SOURCE+' '+p.CAPTURE_RECONCILE_MODE+' '+p.CAPTURE_RECONCILE_OPERATION)
ORIGINAL_RESULT={'booking_calls':0,'database_writes':'unknown','lead_calls':0,
    'mode':'install-tv-fuel-capture','operation_id':p.CAPTURE_INSTALL_OPERATION,
    'production_before':{'index.php':'80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
        'v2/api-v2.php':None,'v2/index.php':None,'v2/lead-adapter-v2.php':None},
    'reason':'capture_install_protected_fingerprint','schema_version':1,'source_sha':SOURCE,
    'status':'unknown_no_replay','supplier_calls':'unknown'}

class CaptureInstall(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source=Path(os.environ.get('PASSIVE_FUEL_INSTALL_SOURCE_ROOT',str(ROOT))).resolve()
        cls.new=(cls.source/p.CAPTURE_INSTALL_PATH).read_bytes()
        cls.dependencies={n:(cls.source/n).read_bytes() for n in p.CAPTURE_INSTALL_DEPENDENCIES}
        old=ROOT/'predecessor-passive.php'
        if old.is_file(): cls.old=old.read_bytes()
        else:
            run=subprocess.run(['git','-C',str(cls.source),'show','b1a69c5721697a8f8c1867a445e7f2292dbe1b93:'+p.CAPTURE_INSTALL_PATH],capture_output=True,check=True)
            cls.old=run.stdout
        assert hashlib.sha256(cls.old).hexdigest()==p.CAPTURE_INSTALL_PREDECESSOR
        assert hashlib.sha256(cls.new).hexdigest()==p.CAPTURE_INSTALL_SHA256
        for n,b in cls.dependencies.items(): assert hashlib.sha256(b).hexdigest()==p.CAPTURE_INSTALL_DEPENDENCIES[n]
        cls.php=shutil.which('php')
        if not cls.php: raise RuntimeError('real_php_required')

    def fixture(self,root,drift=None,fail_lint=False,payload_change=None,public_drift=False):
        source=root/'source'; source.mkdir()
        f=source/p.CAPTURE_INSTALL_PATH;f.parent.mkdir(parents=True);f.write_bytes(self.new)
        blob,pins=p.bundle_source(source,COMMAND)
        archive=root/'source.tar.gz';archive.write_bytes(blob)
        home=root/'home';project=home/'www/anytoour.ru';runtime=project/'_preview/search3-anex-candidate'
        helper=runtime/p.CAPTURE_INSTALL_PATH;helper.parent.mkdir(parents=True)
        helper.write_bytes(self.old if drift!='target' else b'<?php /* foreign */')
        for n,b in self.dependencies.items(): (runtime/n).write_bytes(b if drift!=n else b'<?php /* drift */')
        for n in ['index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php']:
            target=project/n;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(('sentinel '+n).encode())
        unrelated=runtime/'app/integrations/unrelated.php';unrelated.write_bytes(b'UNRELATED')
        payload=dict(COMMAND,archive=str(archive),manifest_sha256=hashlib.sha256(json.dumps(pins,sort_keys=True,separators=(',',':')).encode()).hexdigest())
        payload.update(payload_change or {})
        remote=root/'remote.py';remote.write_text(p.remote_program(core.REMOTE,COMMAND))
        env=dict(os.environ,HOME=str(home))
        if fail_lint or public_drift:
            bindir=root/'bin';bindir.mkdir()
            shim=bindir/'php'
            action='sys.exit(1)' if fail_lint else 'open('+repr(str(project/'index.php'))+',"wb").write(b"changed public file")'
            shim.write_text('#!/usr/bin/env python3\nimport os,sys\nif sys.argv[-1]=='+repr(str(helper))+':'+action+'\nos.execv('+repr(self.php)+',['+repr(self.php)+']+sys.argv[1:])\n')
            shim.chmod(0o755);env['PATH']=str(bindir)+os.pathsep+env['PATH']
        def execute():
            run=subprocess.run(['python3',str(remote)],input=json.dumps(payload),capture_output=True,text=True,env=env,timeout=30)
            self.assertEqual(run.returncode,0,run.stderr);self.assertEqual(run.stderr,'')
            return json.loads(run.stdout)
        return execute,helper,runtime,home,unrelated

    def reconciliation_fixture(self,root,payload_change=None):
        _,helper,runtime,home,other=self.fixture(root)
        original=home/'.anytoour-int-executor'/p.CAPTURE_INSTALL_OPERATION
        original.mkdir(parents=True,mode=0o700)
        reservation={'operation_id':p.CAPTURE_INSTALL_OPERATION,'source_sha':SOURCE,
                     'mode':p.CAPTURE_INSTALL_MODE,'reserved_at':1792794859}
        (original/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
        raw=json.dumps(ORIGINAL_RESULT,sort_keys=True,separators=(',',':')).encode()
        self.assertEqual(hashlib.sha256(raw).hexdigest(),p.CAPTURE_RECONCILE_TARGET_RESULT)
        (original/'result.json').write_bytes(raw)
        before={f.name:f.read_bytes() for f in original.iterdir()}
        blob,pins=p.bundle_source(root/'source',READ_COMMAND)
        self.assertEqual(pins,{})
        archive=root/'source.tar.gz';archive.write_bytes(blob)
        payload=dict(READ_COMMAND,archive=str(archive),manifest_sha256=hashlib.sha256(b'{}').hexdigest())
        payload.update(payload_change or {})
        remote=root/'remote.py'
        program=p.remote_program(core.REMOTE,READ_COMMAND)
        # A successful actual stock-REMOTE read must never reach these write/DB lanes.
        program=program.replace('def install_runtime(stage,files,op):\n',"def install_runtime(stage,files,op):\n    raise AssertionError('install_forbidden')\n",1)
        program=program.replace('def db_summary(provider):\n',"def db_summary(provider):\n    raise AssertionError('db_forbidden')\n",1)
        remote.write_text(program)
        def execute():
            run=subprocess.run(['python3',str(remote)],input=json.dumps(payload),capture_output=True,text=True,
                               env=dict(os.environ,HOME=str(home)),timeout=30)
            self.assertEqual(run.returncode,0,run.stderr);self.assertEqual(run.stderr,'')
            return json.loads(run.stdout)
        return execute,helper,runtime,original,before,remote

    def test_reconcile_exact_original_without_install_or_db_and_no_replay(self):
        for absent in (False,True):
            with self.subTest(absent=absent),tempfile.TemporaryDirectory() as td:
                execute,helper,runtime,original,before,_=self.reconciliation_fixture(Path(td))
                if absent:shutil.rmtree(runtime.parents[1]/'v2')
                result=execute();self.assertTrue(p.completed(READ_COMMAND,result),result)
                self.assertEqual(helper.read_bytes(),self.old)
                self.assertEqual({f.name:f.read_bytes() for f in original.iterdir()},before)
                record=result['capture_install_reconciliation']
                self.assertEqual(record['target_status'],'unknown_no_replay')
                self.assertFalse(record['installation_authorized']);self.assertFalse(record['installation_attempted'])
                self.assertFalse(record['historical_path_types_established'])
                if absent:
                    for name in p.CAPTURE_PUBLIC_PATHS[1:]:
                        self.assertEqual(record['public_state']['paths'][name],{'state':'absent'})
                own=original.parent/p.CAPTURE_RECONCILE_OPERATION/'result.json';saved=own.read_bytes()
                self.assertEqual(execute()['reason'],'operation_exists_no_replay')
                self.assertEqual(own.read_bytes(),saved);self.assertEqual((original/'result.json').read_bytes(),before['result.json'])
                for mutate in [lambda r:r.update(supplier_calls=True),lambda r:r.update(production_unchanged=False),
                    lambda r:r['capture_install_reconciliation'].update(installation_authorized=True),
                    lambda r:r['capture_install_reconciliation'].update(target_supplier_calls=0),
                    lambda r:r['capture_install_reconciliation']['original'].update(result_sha256='f'*64),
                    lambda r:r['capture_install_reconciliation'].update(runtime_files={}),
                    lambda r:r['capture_install_reconciliation'].update(public_state={'status':'measured','paths':{}})]:
                    forged=copy.deepcopy(result);mutate(forged);self.assertFalse(p.completed(READ_COMMAND,forged))

    def test_reconcile_rejects_original_drift_and_install_evidence(self):
        for kind in ('result','missing_result','result_symlink','reservation_source','reservation_mode','reservation_extra','plan','backup','dangling_backup'):
            with self.subTest(kind=kind),tempfile.TemporaryDirectory() as td:
                execute,helper,_,original,_,_=self.reconciliation_fixture(Path(td))
                if kind=='result':(original/'result.json').write_text('{}')
                elif kind in ('missing_result','result_symlink'):
                    (original/'result.json').unlink()
                    if kind=='result_symlink':(original/'result.json').symlink_to(original/'reservation.json')
                elif kind.startswith('reservation'):
                    path=original/'reservation.json';row=json.loads(path.read_text())
                    row.update({'source_sha':'f'*40} if kind.endswith('source') else {'mode':'reconcile'} if kind.endswith('mode') else {'extra':True})
                    path.write_text(json.dumps(row))
                elif kind=='plan':(original/'install-plan.json').write_text('{}')
                elif kind=='backup':(original/'backup').mkdir()
                else:(original/'backup').symlink_to(original/'missing')
                result=execute();self.assertFalse(p.completed(READ_COMMAND,result),result)
                self.assertEqual(helper.read_bytes(),self.old);self.assertNotIn('install',result)

    def test_reconcile_public_unsafe_is_current_unavailable_not_absent_or_unchanged(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,runtime,original,before,_=self.reconciliation_fixture(Path(td))
            shutil.rmtree(runtime.parents[1]/'v2');(runtime.parents[1]/'v2').symlink_to(runtime.parents[1]/'missing')
            result=execute();self.assertTrue(p.completed(READ_COMMAND,result),result)
            self.assertEqual(result['capture_install_reconciliation']['public_state'],
                {'status':'unavailable','code':'capture_install_public_unsafe','path':'v2/index.php'})
            self.assertIs(result['production_unchanged'],False);self.assertEqual(helper.read_bytes(),self.old)
            self.assertEqual({f.name:f.read_bytes() for f in original.iterdir()},before)

    def test_reconcile_fixed_scope_and_current_runtime_hash_are_not_install_authority(self):
        for changed in ({'mode':'install-tv-fuel-capture'},{'source_sha':'f'*40},{'operation_id':p.CAPTURE_RECONCILE_OPERATION+'x'}):
            with self.subTest(changed=changed),tempfile.TemporaryDirectory() as td:
                execute,helper,*_=self.reconciliation_fixture(Path(td),changed)
                result=execute();self.assertEqual(result['reason'],'capture_reconcile_fixed_scope');self.assertEqual(helper.read_bytes(),self.old)
        with tempfile.TemporaryDirectory() as td:
            execute,helper,*_=self.reconciliation_fixture(Path(td));helper.write_bytes(b'<?php /* foreign current helper */')
            result=execute();self.assertTrue(p.completed(READ_COMMAND,result),result)
            state=result['capture_install_reconciliation']['runtime_files'][p.CAPTURE_INSTALL_PATH]
            self.assertEqual(state,{'status':'measured','sha256':hashlib.sha256(helper.read_bytes()).hexdigest()})
            self.assertFalse(result['capture_install_reconciliation']['installation_authorized'])

    def test_reconcile_changed_original_during_read_is_not_repaired(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,_,original,_,remote=self.reconciliation_fixture(Path(td))
            injection="        runtime_before=capture_reconcile_runtime()\n"
            remote.write_text(remote.read_text().replace(injection,injection+"        (private/CAPTURE_TARGET_OPERATION/'result.json').write_text('{}')\n",1))
            result=execute();self.assertEqual(result['reason'],'capture_reconcile_original_result')
            self.assertEqual((original/'result.json').read_text(),'{}');self.assertEqual(helper.read_bytes(),self.old)

    def test_reconcile_parse_bundle_and_owner_event_keep_mode_no_replay(self):
        for tail in (p.CAPTURE_RECONCILE_OPERATION+' extra',p.CAPTURE_RECONCILE_OPERATION+'x'):
            with self.assertRaises(ValueError):core.parse_command(core.PREFIX+SOURCE+' '+p.CAPTURE_RECONCILE_MODE+' '+tail)
        self.assertNotIn(p.CAPTURE_RECONCILE_MODE,p.RELEASE_MODES)
        with tempfile.TemporaryDirectory() as td:
            blob,pins=p.bundle_source(Path(td),READ_COMMAND)
            self.assertEqual(pins,{})
            with tarfile.open(fileobj=io.BytesIO(blob)) as archive:self.assertEqual(archive.getnames(),['manifest.json'])
        event={'action':'created','repository':{'full_name':core.REPO},'comment':{'id':99}}
        env={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_REPOSITORY':core.REPO,'GITHUB_REF':'refs/heads/main',
             'GITHUB_WORKFLOW_REF':core.REPO+'/.github/workflows/int-server-executor.yml@refs/heads/main',
             'GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_RUN_ATTEMPT':'1'}
        checks=[{'id':i,'head_sha':p.CAPTURE_INSTALL_CHECK_COMMIT,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}} for i in p.CAPTURE_INSTALL_CHECK_IDS]
        history=[{'id':98,'body':core.PREFIX+SOURCE+' '+p.CAPTURE_INSTALL_MODE+' '+p.CAPTURE_INSTALL_OPERATION},
                 {'id':99,'body':core.PREFIX+SOURCE+' '+p.CAPTURE_RECONCILE_MODE+' '+p.CAPTURE_RECONCILE_OPERATION}]
        def api(path,token):return {'total_count':len(checks),'check_runs':checks} if '/check-runs' in path else history
        p.admission(api,event,'fake',env,core.REPO,4709,core.PREFIX,READ_COMMAND)
        for changed in ({'GITHUB_RUN_ATTEMPT':'2'},{'GITHUB_ACTOR':'other'},{'GITHUB_EVENT_NAME':'workflow_dispatch'}):
            with self.assertRaises(ValueError):p.admission(api,event,'fake',dict(env,**changed),core.REPO,4709,core.PREFIX,READ_COMMAND)
        history.append(dict(history[-1],id=100))
        with self.assertRaises(ValueError):p.admission(api,event,'fake',env,core.REPO,4709,core.PREFIX,READ_COMMAND)

    def test_exact_native_install_backup_dependencies_and_completion(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,runtime,home,other=self.fixture(Path(td))
            result=execute()
            self.assertTrue(p.completed(COMMAND,result),result)
            self.assertEqual(helper.read_bytes(),self.new);self.assertEqual(other.read_bytes(),b'UNRELATED')
            op=home/'.anytoour-int-executor'/p.CAPTURE_INSTALL_OPERATION
            self.assertEqual((op/'backup'/p.CAPTURE_INSTALL_PATH).read_bytes(),self.old)
            for n,b in self.dependencies.items():self.assertEqual((runtime/n).read_bytes(),b)
            plan=json.loads((op/'install-plan.json').read_bytes())
            self.assertEqual(plan['files'],[p.CAPTURE_INSTALL_PATH])
            self.assertEqual(plan['changed_files'],[p.CAPTURE_INSTALL_PATH])
            saved=(op/'result.json').read_bytes()
            repeated=execute()
            self.assertEqual(repeated['reason'],'operation_exists_no_replay')
            self.assertEqual((op/'result.json').read_bytes(),saved)
            self.assertEqual(helper.read_bytes(),self.new)
            for mutate in [
                lambda r:r['install'].update(files=True),lambda r:r.update(supplier_calls=True),
                lambda r:r.update(passive_capture_install={}),lambda r:r.update(production_before={}),
                lambda r:r.update(production_before=dict.fromkeys(r['production_before']),production_after=dict.fromkeys(r['production_after'])),
                lambda r:r.update(production_before=dict.fromkeys(r['production_before'],'not-a-hash'),production_after=dict.fromkeys(r['production_after'],'not-a-hash')),
                lambda r:r.pop('passive_capture_public_before'),
                lambda r:r.update(passive_capture_public_before=dict.fromkeys(p.CAPTURE_PUBLIC_PATHS),passive_capture_public_after=dict.fromkeys(p.CAPTURE_PUBLIC_PATHS)),
                lambda r:r.update(passive_capture_public_before=dict.fromkeys(p.CAPTURE_PUBLIC_PATHS,{'state':'absent'}),passive_capture_public_after=dict.fromkeys(p.CAPTURE_PUBLIC_PATHS,{'state':'absent'})),
                lambda r:r['passive_capture_public_after']['index.php'].update(sha256='f'*64),
                lambda r:r['passive_capture_public_before']['index.php'].update(extra=True),
                lambda r:r['install'].update(manifest_sha256='0'*64)]:
                forged=copy.deepcopy(result);mutate(forged);self.assertFalse(p.completed(COMMAND,forged))

    def test_target_drift_denies_without_write(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,*_=self.fixture(Path(td),drift='target');before=helper.read_bytes()
            result=execute();self.assertEqual(result['reason'],'capture_install_predecessor_or_dependency_drift')
            self.assertEqual(helper.read_bytes(),before);self.assertFalse(p.completed(COMMAND,result))

    def test_missing_root_index_denies_before_write(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,runtime,*_=self.fixture(Path(td))
            (runtime.parents[1]/'index.php').unlink()
            result=execute();self.assertEqual(result['reason'],'capture_install_public_index_required')
            self.assertEqual(helper.read_bytes(),self.old);self.assertFalse(p.completed(COMMAND,result))

    def test_proven_absent_v2_paths_are_typed_and_preserved(self):
        for missing in ('one','files','directory'):
            with self.subTest(missing=missing),tempfile.TemporaryDirectory() as td:
                execute,helper,runtime,*_=self.fixture(Path(td));project=runtime.parents[1]
                if missing=='one':(project/'v2/lead-adapter-v2.php').unlink()
                elif missing=='files':
                    for path in (project/'v2').iterdir():path.unlink()
                else:shutil.rmtree(project/'v2')
                result=execute();self.assertTrue(p.completed(COMMAND,result),result)
                expected=['v2/lead-adapter-v2.php'] if missing=='one' else list(p.CAPTURE_PUBLIC_PATHS[1:])
                for relative in expected:
                    self.assertEqual(result['passive_capture_public_before'][relative],{'state':'absent'})
                    self.assertFalse((project/relative).exists())
                self.assertEqual(result['passive_capture_public_before'],result['passive_capture_public_after'])
                self.assertEqual(helper.read_bytes(),self.new)

    def test_unsafe_public_paths_deny_before_write(self):
        for kind in ('symlink','dangling','ancestor_symlink','directory','fifo','hardlink','oversize'):
            with self.subTest(kind=kind),tempfile.TemporaryDirectory() as td:
                execute,helper,runtime,*_=self.fixture(Path(td));project=runtime.parents[1]
                leaf=project/'v2/api-v2.php';leaf.unlink()
                if kind in ('symlink','dangling'):leaf.symlink_to(project/('index.php' if kind=='symlink' else 'missing.php'))
                elif kind=='ancestor_symlink':
                    shutil.rmtree(project/'v2');(project/'v2').symlink_to(project/'missing-directory')
                elif kind=='directory':leaf.mkdir()
                elif kind=='fifo':os.mkfifo(leaf)
                elif kind=='hardlink':os.link(project/'index.php',leaf)
                else:
                    with leaf.open('wb') as stream:stream.truncate(4*1024*1024+1)
                result=execute();self.assertEqual(result['reason'],'capture_install_public_unsafe',result)
                self.assertEqual(helper.read_bytes(),self.old);self.assertFalse(p.completed(COMMAND,result))

    def test_public_read_error_is_unavailable_not_absent(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,runtime,*_=self.fixture(Path(td))
            def fail(reason):raise RuntimeError(reason)
            scope={'project':runtime.parents[1],'os':os,'hashlib':hashlib,'fail':fail}
            exec(p.CAPTURE_PUBLIC_REMOTE,scope)
            with patch.object(os,'stat',side_effect=PermissionError()):
                with self.assertRaisesRegex(RuntimeError,'^capture_install_public_unavailable$'):
                    scope['capture_install_public_state']()
            self.assertEqual(helper.read_bytes(),self.old)

    def test_public_drift_after_install_uses_stock_rollback(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,*_=self.fixture(Path(td),public_drift=True)
            result=execute();self.assertEqual(result['install_failure_class'],'production_drift',result)
            self.assertEqual(result['status'],'rolled_back');self.assertEqual(helper.read_bytes(),self.old)
            self.assertFalse(p.completed(COMMAND,result))

    def test_dependency_drift_denies_without_write(self):
        for dependency in self.dependencies:
            with self.subTest(dependency=dependency),tempfile.TemporaryDirectory() as td:
                execute,helper,*_=self.fixture(Path(td),drift=dependency)
                result=execute();self.assertEqual(result['reason'],'capture_install_predecessor_or_dependency_drift')
                self.assertEqual(helper.read_bytes(),self.old)

    def test_postwrite_failure_uses_original_stock_rollback(self):
        with tempfile.TemporaryDirectory() as td:
            execute,helper,*_=self.fixture(Path(td),fail_lint=True)
            result=execute();self.assertEqual(result['status'],'rolled_back',result)
            self.assertEqual(result['rollback'],{'status':'complete','restored_files':1})
            self.assertEqual(helper.read_bytes(),self.old);self.assertFalse(p.completed(COMMAND,result))

    def test_fixed_mode_source_and_operation_fence(self):
        for changed in [{'mode':'install-runtime'},{'source_sha':'f'*40},{'operation_id':p.CAPTURE_INSTALL_OPERATION+'x'}]:
            with self.subTest(changed=changed),tempfile.TemporaryDirectory() as td:
                execute,helper,*_=self.fixture(Path(td),payload_change=changed)
                result=execute();self.assertEqual(result['reason'],'capture_install_fixed_scope')
                self.assertEqual(helper.read_bytes(),self.old)

    def test_parse_and_bundle_cannot_supply_paths_or_other_operation(self):
        for tail in [p.CAPTURE_INSTALL_OPERATION+'x',p.CAPTURE_INSTALL_OPERATION+' extra']:
            with self.assertRaises(ValueError):core.parse_command(core.PREFIX+SOURCE+' '+p.CAPTURE_INSTALL_MODE+' '+tail)
        self.assertNotIn(p.CAPTURE_INSTALL_MODE,p.RELEASE_MODES)
        with tempfile.TemporaryDirectory() as td:
            root=Path(td);target=root/p.CAPTURE_INSTALL_PATH;target.parent.mkdir(parents=True);target.write_bytes(self.new)
            (target.parent/'unrelated.php').write_text('<?php')
            blob,pins=p.bundle_source(root,COMMAND)
            self.assertEqual(pins,{p.CAPTURE_INSTALL_PATH:p.CAPTURE_INSTALL_SHA256})
            with tarfile.open(fileobj=io.BytesIO(blob)) as archive:
                self.assertEqual(archive.getnames(),[p.CAPTURE_INSTALL_PATH,'manifest.json'])
            target.write_bytes(b'<?php /* foreign */')
            with self.assertRaises(ValueError):p.bundle_source(root,COMMAND)

    def test_created_owner_event_exact_green_ci_and_no_second_command(self):
        event={'action':'created','repository':{'full_name':core.REPO},'comment':{'id':99}}
        env={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_REPOSITORY':core.REPO,'GITHUB_REF':'refs/heads/main',
             'GITHUB_WORKFLOW_REF':core.REPO+'/.github/workflows/int-server-executor.yml@refs/heads/main',
             'GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_RUN_ATTEMPT':'1'}
        rows=[{'id':i,'head_sha':p.CAPTURE_INSTALL_CHECK_COMMIT,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}} for i in p.CAPTURE_INSTALL_CHECK_IDS]
        history=[{'id':99,'body':core.PREFIX+SOURCE+' '+p.CAPTURE_INSTALL_MODE+' '+p.CAPTURE_INSTALL_OPERATION}]
        def api(path,token):
            return {'total_count':len(rows),'check_runs':rows} if '/check-runs' in path else history
        p.admission(api,event,'fake',env,core.REPO,4709,core.PREFIX,COMMAND)
        for change in [{'GITHUB_RUN_ATTEMPT':'2'},{'GITHUB_ACTOR':'other'},{'GITHUB_EVENT_NAME':'workflow_dispatch'}]:
            with self.assertRaises(ValueError):p.admission(api,event,'fake',dict(env,**change),core.REPO,4709,core.PREFIX,COMMAND)
        rows[0]['head_sha']='f'*40
        with self.assertRaises(ValueError):p.admission(api,event,'fake',env,core.REPO,4709,core.PREFIX,COMMAND)
        rows[0]['head_sha']=p.CAPTURE_INSTALL_CHECK_COMMIT
        history.append(dict(history[0],id=100))
        with self.assertRaises(ValueError):p.admission(api,event,'fake',env,core.REPO,4709,core.PREFIX,COMMAND)

if __name__=='__main__':unittest.main()
