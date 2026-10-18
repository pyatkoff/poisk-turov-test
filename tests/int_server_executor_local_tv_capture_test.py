from __future__ import annotations
import ast
import contextlib
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import tarfile
import unittest
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
SOURCE=Path(os.environ.get('LOCAL_TV_CAPTURE_SOURCE_ROOT',str(ROOT/'local-tv-capture-source')))
spec=importlib.util.spec_from_file_location('capture_control',ROOT/'scripts/deploy/int_server_executor_local_tv_capture.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class CaptureControl(unittest.TestCase):
    def wrapper(self):
        s=importlib.util.spec_from_file_location('capture_wrapper',ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py')
        w=importlib.util.module_from_spec(s);s.loader.exec_module(w);return w
    def test_exact_one_use_operations_zero_http_and_old_parsers(self):
        c=self.wrapper().core
        for operation,action in m.OPERATIONS.items():
            cmd=c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {operation}')
            self.assertEqual(cmd['action'],action);self.assertEqual(cmd['provider_http_calls'],0)
            with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {operation} --replay')
            with self.assertRaises(ValueError):m.activate(c,dict(cmd,provider_http_calls=False))
        self.assertEqual(c.parse_command(f'{c.PREFIX}{"a"*40} local-tv-content-v1 int-andromeda-local-tv-content-fill-20261010-v1')['provider_http_calls'],200)
    def test_bundle_exact_backend_hashes_no_flag_or_legacy_writer(self):
        data,files=m.bundle_source(SOURCE);self.assertTrue(data);self.assertEqual(set(files),set(m.BUNDLE_FILES))
        self.assertFalse(any('migrate-' in p or 'enabled.json' in p for p in files))
        with tempfile.TemporaryDirectory() as directory:
            copy=Path(directory)
            for p in m.SOURCE_HASHES:
                f=copy/p;f.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(SOURCE/p,f)
            (copy/'v2/api-v2.php').write_text('foreign API')
            with self.assertRaisesRegex(ValueError,'reviewed_source_changed'):m.bundle_source(copy)
    def test_stock_reservation_unknown_no_replay_and_read_only_boundary(self):
        c=self.wrapper().core;cmd=c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {next(iter(m.OPERATIONS))}');m.activate(c,cmd);ast.parse(c.REMOTE)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",c.REMOTE)
        self.assertIn('capture_inspection_drift',c.REMOTE);self.assertIn('capture_data_collector_active',c.REMOTE)
        self.assertIn("data['database_writes']=None",c.REMOTE);self.assertIn("data['state']='unknown_no_replay'",c.REMOTE)
        self.assertIn("'--candidate-scope=local','--http-budget=0'",c.REMOTE)
        self.assertIn('curl_exec,curl_multi_exec',c.REMOTE)
    def test_fresh_main_release_and_actual_owner_still_required(self):
        c=self.wrapper().core;sha='a'*40;control='b'*40;body=f'{c.PREFIX}{sha} {m.MODE} {next(iter(m.OPERATIONS))}'
        event={'issue':{'number':4217},'comment':{'id':98,'user':{'id':226193297},'author_association':'OWNER','body':body}}
        def api(path,token):
            if path=='/issues/comments/98':return {'body':body,'user':{'id':226193297}}
            if path=='/git/ref/heads/main':return {'object':{'sha':control}}
            if path=='/git/ref/heads/release/search3-production-ready-v1':return {'object':{'sha':sha}}
            raise AssertionError(path)
        with mock.patch.object(c,'api_get',side_effect=api):self.assertEqual(c.checked_event('fake',event,control)['mode'],m.MODE)
        event['comment']['user']['id']=1
        with self.assertRaisesRegex(ValueError,'owner'):c.checked_event('fake',event,control)

class CaptureFiles(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();root=Path(self.temp.name)
        self.project=root/'project';self.project.mkdir();(self.project/'data').mkdir()
        self.stage=root/'source';self.stage.mkdir();self.op=root/'operation';self.op.mkdir()
        self.targets={'data/helper.php':'v2/data/helper.php','api-v2.php':'v2/api-v2.php'}
        for relative,src in self.targets.items():
            (self.project/relative).write_text('original '+relative)
            f=self.stage/src;f.parent.mkdir(parents=True,exist_ok=True);f.write_text('new '+src)
        def fail(reason):raise RuntimeError(reason)
        self.ns={'os':os,'pathlib':__import__('pathlib'),'hashlib':hashlib,'json':json,'subprocess':subprocess,'re':re,
            'project':self.project,'op':self.op,'operation':'int-andromeda-local-tv-capture-install-fixture-v1','fail':fail,
            'safe_file':lambda p,n:p.is_file() and not p.is_symlink() and p.stat().st_size<=n}
        exec(m.REMOTE_HANDLER,self.ns)
        self.paths=[*self.targets,'data/local-tv-catalog-enabled.json'];self.before=self.ns['capture_files'](self.paths)
        self.flag=b'{"registry":true,"dailyHttpBudget":0}\n'
    def tearDown(self):self.temp.cleanup()
    def install(self):return self.ns['capture_install_files'](self.stage,self.targets,self.before,self.flag)
    def test_real_atomic_copies_private_backup_and_enable_last(self):
        original=self.ns['capture_write'];writes=[]
        def write(path,data,mode=0o600):
            if path==self.project/'data/local-tv-catalog-enabled.json':
                for relative,src in self.targets.items():self.assertEqual((self.project/relative).read_bytes(),(self.stage/src).read_bytes())
            original(path,data,mode);writes.append(path)
        self.ns['capture_write']=write;changed=self.install()
        self.assertEqual(changed,[*self.targets,'data/local-tv-catalog-enabled.json'])
        self.assertEqual(writes[-1],self.project/'data/local-tv-catalog-enabled.json')
        self.assertEqual((self.op/'backup/api-v2.php').stat().st_mode&0o777,0o600)
        self.assertEqual((self.project/'data/local-tv-catalog-enabled.json').read_bytes(),self.flag)
    def test_known_mid_copy_failure_restores_exact_original_bytes_and_absent_flag(self):
        original=self.ns['capture_write'];failed=False
        def write(path,data,mode=0o600):
            nonlocal failed
            if path==self.project/'api-v2.php' and not failed:failed=True;raise OSError('fixture copy failure')
            original(path,data,mode)
        self.ns['capture_write']=write
        with self.assertRaisesRegex(RuntimeError,'rolled_back_no_replay'):self.install()
        self.assertEqual(self.ns['capture_files'](self.paths),self.before)
    def test_failed_rollback_is_unknown_and_is_not_retried(self):
        original=self.ns['capture_write'];calls=[]
        def write(path,data,mode=0o600):
            if path==self.project/'api-v2.php':calls.append(path);raise OSError('persistent fixture I/O error')
            original(path,data,mode)
        self.ns['capture_write']=write
        with self.assertRaisesRegex(RuntimeError,'unknown_no_replay'):self.install()
        self.assertEqual(len(calls),2) # One copy, one bounded restoration; no next installation.
    def test_target_drift_and_symlinks_stop_before_backups_or_copies(self):
        (self.project/'api-v2.php').write_text('concurrent edit')
        with self.assertRaisesRegex(RuntimeError,'before_files_drift'):self.install()
        self.assertFalse((self.op/'backup').exists())
        (self.project/'api-v2.php').unlink();(self.project/'api-v2.php').symlink_to(self.stage/'v2/api-v2.php')
        with self.assertRaisesRegex(RuntimeError,'target_path'):self.install()
    def test_actual_installed_flag_requires_exact_regular_file(self):
        for name in ('local-tv-catalog-v1.php','hotel-details-v1.php'):shutil.copyfile(SOURCE/'v2/data'/name,self.project/'data'/name)
        flag=self.project/'data/local-tv-catalog-enabled.json';env=dict(os.environ);env.pop('ANYTOUR_LOCAL_TV_CATALOG_ENABLED',None)
        def enabled():
            r=subprocess.run(['php','-r','require $argv[1]; echo LocalTvCatalogV1::enabled()?"1":"0";',str(self.project/'data/local-tv-catalog-v1.php')],env=env,capture_output=True,text=True)
            self.assertEqual(r.returncode,0,r.stderr);return r.stdout
        self.assertEqual(enabled(),'0');flag.write_bytes(self.flag);self.assertEqual(enabled(),'1')
        flag.write_text('{"registry":true}');self.assertEqual(enabled(),'0')
        flag.unlink();actual=self.op/'real-flag.json';actual.write_bytes(self.flag);flag.symlink_to(actual);self.assertEqual(enabled(),'0')

    def test_current_same_backend_source_carries_original_install_without_reinstallation(self):
        private=Path(self.temp.name)/'private';private.mkdir()
        targets={p[3:]:p for p in m.SOURCE_HASHES}
        for relative,src in targets.items():
            f=self.project/relative;f.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(SOURCE/src,f)
        (self.project/'data/local-tv-catalog-enabled.json').write_bytes(self.flag)
        expected={relative:m.SOURCE_HASHES[src] for relative,src in targets.items()}
        expected['data/local-tv-catalog-enabled.json']=hashlib.sha256(self.flag).hexdigest()
        full={'database_sha256':'1'*64,'config_sha256':'2'*64,'protected_snapshots':{},'readback':{'links_sha256':'3'*64},'catalog_rows':{}}
        summary={k:v for k,v in full.items() if k!='catalog_rows'}
        prior={'state':'inspected_read_only','source_sha':'a'*40,'control_source_sha':'b'*40,'before_files':{},'before_data':summary}
        installed=dict(prior,state='installed_capture',after_files=expected,after_data=summary)
        def receipt(name,data):
            d=private/name;d.mkdir(exist_ok=True);(d/'result.json').write_text(json.dumps({'mode':m.MODE,'status':'complete','local_tv_capture':data}))
        receipt('int-andromeda-local-tv-capture-inspect-20261010-v1',prior)
        receipt('int-andromeda-local-tv-capture-install-20261010-v1',installed)
        self.ns.update(private=private,source='c'*40,files=m.SOURCE_HASHES,time=__import__('time'),
            safe_json=lambda p,n:json.loads(p.read_text()),payload={'action':'retained','provider_http_calls':0,'old_profile_writes':0,
                'mapping_writes':0,'schema_writes':0,'local_tv_capture_control_sha':'d'*40},
            operation='int-andromeda-local-tv-capture-retained-20261010-v1',result={})
        calls=[]
        def run(args,**kwargs):
            calls.append(args)
            if args[-1] in ('capture-before.json','capture-after.json'):
                (self.op/args[-1]).write_text(json.dumps(full));out=json.dumps(summary)
            elif '-r' in args:out='enabled'
            else:out='ANYTOUR_LOCAL_TV_DAILY '+json.dumps({'supplierHttpAttempts':0,'httpRequests':0,'legacyMigration':{'transferred':0,'issues':[]}})
            return subprocess.CompletedProcess(args,0,out,'')
        with mock.patch.object(subprocess,'run',side_effect=run),mock.patch.dict(self.ns,{'capture_install_files':mock.Mock(side_effect=AssertionError('unexpected reinstall'))}):
            retained=self.ns['run_local_tv_capture'](self.stage)
            self.assertEqual(retained['state'],'retained_complete');self.assertEqual(retained['installed_source_sha'],'a'*40)
            self.assertEqual(retained['source_sha'],'c'*40);self.assertEqual(retained['database_writes'],0)
            self.assertEqual(len([c for c in calls if '--http-budget=0' in c]),1)
            receipt('int-andromeda-local-tv-capture-retained-20261010-v1',retained)
            self.ns['operation']='int-andromeda-local-tv-capture-readback-20261010-v1';self.ns['payload']['action']='readback'
            self.assertEqual(self.ns['run_local_tv_capture'](self.stage)['state'],'verified_read_only')
            (self.project/'data/local-tv-catalog-v1.php').write_text('unreviewed backend')
            with self.assertRaisesRegex(RuntimeError,'installed_files_drift'):self.ns['run_local_tv_capture'](self.stage)
            self.ns['operation']='int-andromeda-local-tv-capture-install-20261010-v1';self.ns['payload']['action']='install'
            with self.assertRaisesRegex(RuntimeError,'install_source_changed'):self.ns['run_local_tv_capture'](self.stage)

class RepairControl(unittest.TestCase):
    def core(self):return CaptureControl().wrapper().core
    def test_repair_command_is_exact_source_first_attempt_and_zero_caps(self):
        c=self.core();body=f'{c.PREFIX}{m.REPAIR_SOURCE} {m.MODE} {m.REPAIR_OPERATION}'
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'1'}):
            command=c.parse_command(body)
            self.assertEqual(command['action'],'repair');self.assertEqual(command['operation_id'],m.REPAIR_OPERATION)
            for key in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes'):
                self.assertIs(type(command[key]),int);self.assertEqual(command[key],0)
            for invalid in (body+' --replay',body+' extra',body.replace(m.REPAIR_SOURCE,'a'*40),body.replace(m.REPAIR_OPERATION,m.REPAIR_OPERATION+'x')):
                with self.subTest(body=invalid),self.assertRaises(ValueError):c.parse_command(invalid)
            with self.assertRaises(ValueError):m.activate(c,dict(command,provider_http_calls=False))
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}):
            with self.assertRaises(ValueError):c.parse_command(body)
    def test_repair_bundle_contains_only_two_pinned_files_and_manifest(self):
        archive,hashes=m.bundle_repair_source(SOURCE)
        self.assertEqual(set(hashes),set(m.REPAIR_TARGETS.values()))
        with tarfile.open(fileobj=io.BytesIO(archive),mode='r:gz') as tar:
            self.assertEqual(set(tar.getnames()),{*m.REPAIR_TARGETS.values(),'manifest.json'})
            for member in tar.getmembers():
                self.assertTrue(member.isfile());self.assertEqual(member.mode,0o600)
            manifest=json.load(tar.extractfile('manifest.json'));self.assertEqual(manifest['files'],hashes)
            for relative,source in m.REPAIR_TARGETS.items():
                self.assertEqual(hashlib.sha256(tar.extractfile(source).read()).hexdigest(),m.REPAIR_GOOD_HASHES[relative])
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory)
            for source in m.REPAIR_TARGETS.values():
                p=root/source;p.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(SOURCE/source,p)
            (root/'v2/data/db-v1.php').write_bytes(b'unreviewed source')
            with self.assertRaises(ValueError):m.bundle_repair_source(root)
    def test_activation_uses_repair_bundle_without_changing_legacy_operations(self):
        c=self.core();operations=dict(m.OPERATIONS);pins=dict(m.SOURCE_HASHES)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'1'}):
            command=c.parse_command(f'{c.PREFIX}{m.REPAIR_SOURCE} {m.MODE} {m.REPAIR_OPERATION}');m.activate(c,command)
        self.assertIs(c.bundle_source,m.bundle_repair_source);ast.parse(c.REMOTE)
        self.assertIn('run_local_tv_repair(stage)',c.REMOTE)
        self.assertIn("operation_exists_no_replay",c.REMOTE)
        self.assertEqual(operations,m.OPERATIONS);self.assertEqual(pins,m.SOURCE_HASHES)
        self.assertEqual(len(m.OPERATIONS),4);self.assertNotIn(m.REPAIR_OPERATION,m.OPERATIONS)

class RepairFiles(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();root=Path(self.temp.name)
        self.project=root/'project';self.project.mkdir();(self.project/'data').mkdir()
        self.stage=root/'source';self.stage.mkdir();self.op=root/'operation';self.op.mkdir()
        self.good={}
        for relative,sha in m.REPAIR_GOOD_HASHES.items():
            data=(b'{"registry":true,"dailyHttpBudget":0}\n' if relative.endswith('.json') else (SOURCE/('v2/'+relative)).read_bytes())
            self.assertEqual(hashlib.sha256(data).hexdigest(),sha)
            self.good[relative]=data;p=self.project/relative;p.write_bytes(data);p.chmod(0o644)
        self.bad={}
        for relative,source in m.REPAIR_TARGETS.items():
            good=self.good[relative]
            if relative=='data/db-v1.php':
                start=good.index(b'    if (is_file($privateConfig)) {');end=good.index(b'\n\n    $env =',start)
                data=good[:start]+b'    if (is_file($privateConfig)) require_once $privateConfig;'+good[end:]
            else:
                data=good.replace(b"require_once __DIR__ . '/local-tv-catalog-v1.php';\n",b'',1)
                start=data.index(b'    // Registration is independent of tour-price validity')
                end=data.index(b'    $stmt = $pdo->prepare(',start);data=data[:start]+data[end:]
            self.assertEqual((hashlib.sha256(data).hexdigest(),len(data)),tuple(m.REPAIR_BAD[relative]))
            self.bad[relative]=data;(self.project/relative).write_bytes(data)
            p=self.stage/source;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(good)
        def fail(reason):raise RuntimeError(reason)
        self.control='b'*40
        self.ns={'os':os,'pathlib':__import__('pathlib'),'hashlib':hashlib,'json':json,'subprocess':subprocess,'re':re,
            'time':__import__('time'),'project':self.project,'op':self.op,'operation':m.REPAIR_OPERATION,'source':m.REPAIR_SOURCE,
            'files':{source:m.REPAIR_GOOD_HASHES[relative] for relative,source in m.REPAIR_TARGETS.items()},'fail':fail,
            'safe_file':lambda p,n:p.is_file() and not p.is_symlink() and p.stat().st_size<=n,
            'payload':{'action':'repair','provider_http_calls':0,'old_profile_writes':0,'mapping_writes':0,'schema_writes':0,
                       'local_tv_capture_control_sha':self.control},'result':{}}
        exec(m.repair_remote()+m.REMOTE_HANDLER,self.ns)
        self.before=self.inventory();self.original_write=self.ns['capture_write'];self.root_writes=[]
        def track(path,data,mode=0o600,before_replace=None):
            if path.is_relative_to(self.project):self.root_writes.append((str(path.relative_to(self.project)),data))
            self.original_write(path,data,mode,before_replace=before_replace)
        self.ns['capture_write']=track
        self.process=mock.patch.object(subprocess,'run',side_effect=AssertionError('repair must not execute PHP, HTTP, SQL or subprocess'))
        self.process.start()
    def tearDown(self):self.process.stop();self.temp.cleanup()
    def inventory(self):return self.ns['repair_inventory']()
    def repair(self):return self.ns['run_local_tv_repair'](self.stage)
    def assert_no_root_writes(self):
        self.assertEqual(self.root_writes,[]);self.assertFalse((self.op/'repair-backup').exists())
    def test_known_predecessors_repaired_preserving_six_files_and_private_backups(self):
        data=self.repair();self.assertEqual(data['state'],'repaired_files_only')
        self.assertEqual(data['before_files'],self.before);self.assertEqual(data['after_files'],self.inventory())
        self.assertEqual(data['changed_files'],list(m.REPAIR_TARGETS));self.assertEqual(data['file_writes'],2)
        self.assertEqual(data['root_write_attempts'],2);self.assertEqual(data['rollback_attempts'],0)
        for relative in m.REPAIR_GOOD_HASHES:self.assertEqual((self.project/relative).read_bytes(),self.good[relative])
        self.assertEqual([relative for relative,_ in self.root_writes],list(m.REPAIR_TARGETS))
        backups=[p for p in (self.op/'repair-backup').rglob('*') if p.is_file()]
        self.assertEqual({p.read_bytes() for p in backups},set(self.bad.values()))
        self.assertEqual((self.op/'repair-backup').stat().st_mode&0o777,0o700)
        self.assertTrue(all(p.stat().st_mode&0o777==0o600 for p in backups))
        for key in ('supplier_calls','database_writes','old_profile_writes','mapping_writes','schema_writes'):
            self.assertIs(type(data[key]),int);self.assertEqual(data[key],0)
    def test_already_correct_is_true_no_op_without_backup_or_write(self):
        for relative in m.REPAIR_TARGETS:(self.project/relative).write_bytes(self.good[relative])
        before=self.inventory();data=self.repair()
        self.assertEqual(data['state'],'already_correct');self.assertEqual(data['changed_files'],[])
        for key in ('file_writes','root_write_attempts','rollback_attempts'):self.assertEqual(data[key],0)
        self.assertEqual(data['before_files'],before);self.assertEqual(data['after_files'],before);self.assert_no_root_writes()
    def test_restrictive_process_umask_still_installs_exact_0644_and_restores_umask(self):
        previous=os.umask(0o077)
        try:
            data=self.repair();current=os.umask(0o077)
            self.assertEqual(current,0o077)
            self.assertEqual(data['state'],'repaired_files_only')
            for relative in m.REPAIR_TARGETS:
                self.assertEqual((self.project/relative).stat().st_mode&0o777,0o644)
                self.assertEqual((self.project/relative).read_bytes(),self.good[relative])
        finally:os.umask(previous)
    def test_unknown_target_or_protected_drift_stops_before_backup(self):
        for relative in ('data/db-v1.php','api-v2.php'):
            with self.subTest(relative=relative):
                original=(self.project/relative).read_bytes();(self.project/relative).write_bytes(b'foreign writer bytes')
                with self.assertRaises(RuntimeError):self.repair()
                self.assertEqual((self.project/relative).read_bytes(),b'foreign writer bytes');self.assert_no_root_writes()
                (self.project/relative).write_bytes(original)
    def test_symlink_hardlink_and_unsafe_mode_stop_before_backup(self):
        relative='data/db-v1.php';p=self.project/relative;outside=Path(self.temp.name)/'outside';outside.write_bytes(self.bad[relative])
        for kind in ('symlink','hardlink','mode','setuid'):
            with self.subTest(kind=kind):
                p.unlink()
                if kind=='symlink':p.symlink_to(outside)
                elif kind=='hardlink':os.link(outside,p)
                else:p.write_bytes(self.bad[relative]);p.chmod(0o4644 if kind=='setuid' else 0o666)
                with self.assertRaises(RuntimeError):self.repair()
                self.assert_no_root_writes();self.assertEqual(outside.read_bytes(),self.bad[relative])
    def test_active_collector_stops_before_backup_or_copy(self):
        def busy():raise RuntimeError('repair_data_collector_active')
        self.ns['repair_assert_idle']=busy
        with self.assertRaisesRegex(RuntimeError,'active'):self.repair()
        self.assert_no_root_writes();self.assertEqual(self.inventory(),self.before)
    def test_second_copy_failure_rolls_back_only_first_successful_copy(self):
        second=list(m.REPAIR_TARGETS)[1];calls=[]
        def write(path,data,mode=0o600,before_replace=None):
            if path.is_relative_to(self.project):
                relative=str(path.relative_to(self.project));calls.append(relative)
                if relative==second:raise OSError('synthetic second-copy failure')
            self.original_write(path,data,mode,before_replace=before_replace)
        self.ns['capture_write']=write
        with self.assertRaises(RuntimeError):self.repair()
        data=self.ns['result']['local_tv_capture'];self.assertEqual(data['state'],'rolled_back_no_replay')
        self.assertEqual(self.inventory(),self.before);self.assertEqual(data['file_writes'],0)
        self.assertEqual(data['root_write_attempts'],2);self.assertEqual(data['rollback_attempts'],1)
        first=list(m.REPAIR_TARGETS)[0];self.assertEqual(calls,[first,second,first])
    def test_failed_rollback_is_unknown_with_one_bounded_attempt(self):
        first,second=list(m.REPAIR_TARGETS);calls=[]
        def write(path,data,mode=0o600,before_replace=None):
            if path.is_relative_to(self.project):
                relative=str(path.relative_to(self.project));calls.append(relative)
                if relative==second or (relative==first and data==self.bad[first]):raise OSError('synthetic persistent I/O error')
            self.original_write(path,data,mode,before_replace=before_replace)
        self.ns['capture_write']=write
        with self.assertRaises(RuntimeError):self.repair()
        data=self.ns['result']['local_tv_capture'];self.assertEqual(data['state'],'unknown_no_replay')
        self.assertIsNone(data['file_writes']);self.assertEqual(data['root_write_attempts'],2);self.assertEqual(data['rollback_attempts'],1)
        self.assertEqual(calls,[first,second,first]);self.assertEqual((self.project/first).read_bytes(),self.good[first])
    def test_concurrent_foreign_edit_is_preserved_and_is_never_rolled_back(self):
        first,second=list(m.REPAIR_TARGETS);calls=[];foreign=b'foreign concurrent replacement'
        def write(path,data,mode=0o600,before_replace=None):
            if path.is_relative_to(self.project):calls.append(str(path.relative_to(self.project)))
            self.original_write(path,data,mode,before_replace=before_replace)
            if path==self.project/first:(self.project/first).write_bytes(foreign)
        self.ns['capture_write']=write
        with self.assertRaises(RuntimeError):self.repair()
        data=self.ns['result']['local_tv_capture'];self.assertEqual(data['state'],'unknown_no_replay')
        self.assertEqual((self.project/first).read_bytes(),foreign);self.assertEqual((self.project/second).read_bytes(),self.bad[second])
        self.assertEqual(calls,[first]);self.assertEqual(data['rollback_attempts'],0)
    def test_foreign_edit_during_forward_temp_fsync_is_preserved_before_rename(self):
        first,second=list(m.REPAIR_TARGETS);foreign=b'foreign edit after temp fsync';injected=[]
        original_fsync=os.fsync
        temporary=(self.project/first).with_name(Path(first).name+'.'+m.REPAIR_OPERATION+'.tmp')
        def fsync(fd):
            original_fsync(fd)
            if os.readlink('/proc/self/fd/'+str(fd))==str(temporary) and not injected:
                (self.project/first).write_bytes(foreign);injected.append(True)
        with mock.patch.object(os,'fsync',side_effect=fsync):
            with self.assertRaisesRegex(RuntimeError,'unknown_no_replay'):self.repair()
        self.assertEqual(injected,[True]);self.assertEqual((self.project/first).read_bytes(),foreign)
        self.assertEqual((self.project/second).read_bytes(),self.bad[second]);self.assertFalse(temporary.exists())
        data=self.ns['result']['local_tv_capture'];self.assertEqual(data['state'],'unknown_no_replay')
        self.assertEqual(data['root_write_attempts'],1);self.assertEqual(data['rollback_attempts'],0)
    def test_foreign_edit_during_rollback_temp_fsync_is_preserved_before_rename(self):
        first,second=list(m.REPAIR_TARGETS);foreign=b'foreign edit while rollback temp fsyncs';injected=[]
        original_fsync=os.fsync;original_write=self.ns['capture_write']
        temporary=(self.project/first).with_name(Path(first).name+'.'+m.REPAIR_OPERATION+'.tmp')
        def write(path,data,mode=0o600,before_replace=None):
            if path==self.project/second:raise OSError('synthetic failure before second copy')
            original_write(path,data,mode,before_replace=before_replace)
        def fsync(fd):
            original_fsync(fd)
            if (os.readlink('/proc/self/fd/'+str(fd))==str(temporary)
                    and temporary.read_bytes()==self.bad[first] and not injected):
                (self.project/first).write_bytes(foreign);injected.append(True)
        self.ns['capture_write']=write
        with mock.patch.object(os,'fsync',side_effect=fsync):
            with self.assertRaisesRegex(RuntimeError,'unknown_no_replay'):self.repair()
        self.assertEqual(injected,[True]);self.assertEqual((self.project/first).read_bytes(),foreign)
        self.assertEqual((self.project/second).read_bytes(),self.bad[second]);self.assertFalse(temporary.exists())
        data=self.ns['result']['local_tv_capture'];self.assertEqual(data['state'],'unknown_no_replay')
        self.assertEqual(data['root_write_attempts'],2);self.assertEqual(data['rollback_attempts'],1)
    def test_concurrent_edit_during_backup_stops_before_first_root_copy(self):
        first,second=list(m.REPAIR_TARGETS);foreign=b'foreign change while backup completes';backups=[]
        def write(path,data,mode=0o600,before_replace=None):
            if path.is_relative_to(self.project):self.root_writes.append((str(path.relative_to(self.project)),data))
            self.original_write(path,data,mode,before_replace=before_replace)
            if path.is_relative_to(self.op/'repair-backup'):
                backups.append(path)
                if len(backups)==len(m.REPAIR_TARGETS):(self.project/second).write_bytes(foreign)
        self.ns['capture_write']=write
        with self.assertRaises(RuntimeError):self.repair()
        self.assertEqual(self.root_writes,[]);self.assertEqual((self.project/first).read_bytes(),self.bad[first])
        self.assertEqual((self.project/second).read_bytes(),foreign)
        self.assertEqual(self.ns['result']['local_tv_capture']['rollback_attempts'],0)
    def test_protected_file_drift_after_copy_rolls_back_own_copy_only(self):
        first,second=list(m.REPAIR_TARGETS);foreign=b'unrelated concurrent edit';calls=[]
        def write(path,data,mode=0o600,before_replace=None):
            if path.is_relative_to(self.project):calls.append(str(path.relative_to(self.project)))
            self.original_write(path,data,mode,before_replace=before_replace)
            if path==self.project/first and data==self.good[first]:(self.project/'api-v2.php').write_bytes(foreign)
        self.ns['capture_write']=write
        with self.assertRaises(RuntimeError):self.repair()
        data=self.ns['result']['local_tv_capture'];self.assertEqual(data['state'],'unknown_no_replay')
        self.assertEqual((self.project/'api-v2.php').read_bytes(),foreign)
        self.assertEqual((self.project/first).read_bytes(),self.bad[first]);self.assertEqual((self.project/second).read_bytes(),self.bad[second])
        self.assertEqual(calls,[first,first]);self.assertEqual(data['rollback_attempts'],1)
    def test_receipt_validator_rejects_false_success_identity_drift_and_bool_counts(self):
        data=self.repair()
        command={'source_sha':m.REPAIR_SOURCE,'mode':m.MODE,'operation_id':m.REPAIR_OPERATION,'action':'repair',
                 'provider_http_calls':0,'old_profile_writes':0,'mapping_writes':0,'schema_writes':0}
        protected={p:'f'*64 for p in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        result={'schema_version':1,'status':'complete','mode':m.MODE,'source_sha':m.REPAIR_SOURCE,'operation_id':m.REPAIR_OPERATION,
                'supplier_calls':0,'database_writes':0,'booking_calls':0,'lead_calls':0,
                'profile_writes':0,'mapping_writes':0,'schema_writes':0,
                'production_before':protected,'production_after':dict(protected),
                'production_unchanged':True,'local_tv_capture':data}
        self.assertTrue(m.repair_completed_result(command,result,self.control))
        changes=[('status','installed'),('operation_id',m.REPAIR_OPERATION+'x'),('source_sha','a'*40),('supplier_calls',False),
                 ('database_writes',1),('profile_writes',False),('mapping_writes',1),('schema_writes',False),
                 ('production_unchanged',False),('production_after',{'file':'foreign'}),('production_before',{})]
        for key,value in changes:
            with self.subTest(field=key):
                altered=json.loads(json.dumps(result));altered[key]=value
                self.assertFalse(m.repair_completed_result(command,altered,self.control))
        changes=[('control_source_sha','c'*40),('state','rolled_back_no_replay'),('changed_files',[]),('file_writes',False),
                 ('root_write_attempts',1),('rollback_attempts',1),('supplier_calls',False),('after_files',self.before)]
        for key,value in changes:
            with self.subTest(data_field=key):
                altered=json.loads(json.dumps(result));altered['local_tv_capture'][key]=value
                self.assertFalse(m.repair_completed_result(command,altered,self.control))
        for malformed in (None,[],0,'complete'):
            with self.subTest(malformed_data=malformed):
                altered=json.loads(json.dumps(result));altered['local_tv_capture']=malformed
                self.assertFalse(m.repair_completed_result(command,altered,self.control))
        for malformed in (None,[],0,'complete'):
            with self.subTest(malformed_result=malformed):
                self.assertFalse(m.repair_completed_result(command,malformed,self.control))
        for control in ('z'*40,'a'*39,None):
            with self.subTest(control=control):
                altered=json.loads(json.dumps(result));altered['local_tv_capture']['control_source_sha']=control
                self.assertFalse(m.repair_completed_result(command,altered,control))
        for key in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes'):
            with self.subTest(command_cap=key):
                self.assertFalse(m.repair_completed_result(dict(command,**{key:False}),result,self.control))
        altered=json.loads(json.dumps(result));altered['local_tv_capture']['after_files']['api-v2.php']['bytes']=False
        self.assertFalse(m.repair_completed_result(command,altered,self.control))
    def test_stock_remote_local_tar_reservation_full_receipt_and_identical_replay(self):
        c=CaptureControl().wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'1','GITHUB_SHA':self.control}):
            command=c.parse_command(f'{c.PREFIX}{m.REPAIR_SOURCE} {m.MODE} {m.REPAIR_OPERATION}')
            m.activate(c,command)
        home=Path(self.temp.name)/'home';project=home/'www/anytoour.ru'
        (project/'data').mkdir(parents=True)
        (project/'_preview/search3-anex-candidate').mkdir(parents=True)
        for relative in m.REPAIR_GOOD_HASHES:
            target=project/relative;target.write_bytes((self.project/relative).read_bytes());target.chmod(0o644)
        protected=('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')
        for relative in protected:
            target=project/relative;target.parent.mkdir(parents=True,exist_ok=True)
            target.write_bytes(('public entrypoint fixture '+relative).encode());target.chmod(0o644)
        archive,files=m.bundle_repair_source(SOURCE);bundle=Path(self.temp.name)/'bundle.tar.gz';bundle.write_bytes(archive)
        payload=dict(command,archive=str(bundle),local_tv_capture_control_sha=self.control,
            manifest_sha256=hashlib.sha256(json.dumps(files,sort_keys=True,separators=(',',':')).encode()).hexdigest())
        def execute():
            output=io.StringIO();ns={}
            with mock.patch.object(Path,'home',return_value=home),mock.patch.object(sys,'stdin',io.StringIO(json.dumps(payload))),contextlib.redirect_stdout(output):
                exec(c.REMOTE,ns)
            return json.loads(output.getvalue()),ns
        result,remote=execute()
        self.assertTrue(m.repair_completed_result(command,result,self.control),result)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':self.control}):self.assertTrue(c.completed_result(command,result))
        op=home/'.anytoour-int-executor'/m.REPAIR_OPERATION
        reservation=(op/'reservation.json').read_bytes();installed=(op/'installed-source.json').read_bytes()
        successful_receipt=(op/'result.json').read_bytes()
        self.assertEqual(json.loads(reservation)['operation_id'],m.REPAIR_OPERATION)
        self.assertEqual(json.loads(reservation)['source_sha'],m.REPAIR_SOURCE)
        self.assertEqual(json.loads(installed)['files'],files)
        self.assertEqual((op/'reservation.json').stat().st_mode&0o777,0o600)
        self.assertEqual((op/'result.json').stat().st_mode&0o777,0o600)
        self.assertEqual(op.stat().st_mode&0o777,0o700)
        self.assertEqual(json.loads((op/'result.json').read_text()),result)
        before={relative:(project/relative).read_bytes() for relative in [*m.REPAIR_GOOD_HASHES,*protected]}
        replay,_=execute()
        self.assertEqual(replay['status'],'blocked');self.assertEqual(replay['reason'],'operation_exists_no_replay')
        self.assertFalse(m.repair_completed_result(command,replay,self.control))
        self.assertEqual({relative:(project/relative).read_bytes() for relative in before},before)
        self.assertEqual((op/'reservation.json').read_bytes(),reservation)
        self.assertEqual((op/'installed-source.json').read_bytes(),installed)
        self.assertEqual((op/'result.json').read_bytes(),successful_receipt)

@unittest.skipUnless(os.environ.get('LOCAL_TV_SCHEMA_NATIVE_CI')=='1','isolated native MySQL CI required')
class CaptureNative(unittest.TestCase):
    def test_actual_snapshot_read_only_manual_and_full_gallery(self):
        code=r'''
declare(strict_types=1);
define('LOCAL_TV_SCHEMA_LIBRARY_ONLY',true);define('LOCAL_TV_SEED_LIBRARY_ONLY',true);define('LOCAL_TV_CAPTURE_LIBRARY_ONLY',true);
require $argv[1].'/scripts/diagnostics/local_tv_schema_v1.php';require $argv[1].'/scripts/diagnostics/local_tv_seed_v1.php';require $argv[1].'/scripts/diagnostics/local_tv_capture_v1.php';require $argv[2].'/v2/data/local-tv-catalog-v1.php';
$dsn=(string)getenv('LOCAL_TV_SCHEMA_TEST_DSN');if($dsn!=='mysql:host=127.0.0.1;port=3306;charset=utf8mb4' || getenv('LOCAL_TV_SCHEMA_TEST_PASSWORD')!=='local_tv_schema_test_only')throw new RuntimeException('fixture_only');
$server=new PDO($dsn,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$name='local_tv_capture_ci_'.bin2hex(random_bytes(6));
class CaptureReadonlyPDO extends PDO{public bool $rejected=false;public bool $probe=false;public function commit():bool{if($this->probe)try{$this->exec("UPDATE local_tv_hotels SET last_error='forbidden'");}catch(PDOException $e){$this->rejected=($e->errorInfo[1]??0)===1792;}return parent::commit();}}
try{
    $server->exec('CREATE DATABASE `'.$name.'`');$db=new CaptureReadonlyPDO($dsn.';dbname='.$name,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec(file_get_contents($argv[2].'/v2/data/migrations/20261010-local-tv-catalog.sql'));
    $db->exec('CREATE TABLE tour_price_observations(hotel_id INT,search_id INT,source VARCHAR(32),observed_at DATETIME)');$db->exec('CREATE TABLE hot_tours_current(hotel_id INT,fetched_at DATETIME)');
    $db->exec('CREATE TABLE catalog_hotel_details(hotel_id INT PRIMARY KEY,raw_json LONGTEXT,source_hash VARCHAR(64),fetched_at DATETIME,status VARCHAR(24))');
    $db->exec('CREATE TABLE anytour_hotels(id INT PRIMARY KEY,profile_json LONGTEXT,profile_sha256 VARCHAR(64),revision INT,is_active INT)');
    $db->exec('CREATE TABLE anytour_hotel_sources(anytour_hotel_id INT,namespace VARCHAR(64),external_key VARCHAR(128),acquired_via VARCHAR(64),source_json LONGTEXT,source_sha256 VARCHAR(64),first_seen_at DATETIME,last_seen_at DATETIME)');
    $c=new LocalTvCatalogV1($db);$c->discover([['id'=>105]],'user_search','2026-10-10 00:00:00');
    // Source/manual transactions precede the snapshot's deliberately rejected write probe.
    $c->saveSource(105,['id'=>105,'name'=>'Fixture hotel','images'=>array_map(static fn($n)=>'https://fixture.example.test/'.$n.'.jpg',range(1,130))],'2026-10-10 00:00:00');$c->setManualFields(105,['description'=>'Manual protected description'],2);
    $before=LocalTvSeedV1::readback($db);$db->probe=true;$snap=LocalTvCaptureV1::snapshot($db);$after=LocalTvSeedV1::readback($db);
    echo LocalTvSchemaV1::json(['snapshot'=>$snap,'before'=>$before,'after'=>$after,'rejected'=>$db->rejected,'dto'=>$c->read([105])]);
}finally{$server->exec('DROP DATABASE `'.$name.'`');}
'''
        r=subprocess.run(['php','-r',code,str(ROOT),str(SOURCE)],capture_output=True,text=True,timeout=30)
        self.assertEqual(r.returncode,0,r.stderr);data=json.loads(r.stdout)
        self.assertTrue(data['rejected']);self.assertEqual(data['before'],data['after']);self.assertEqual(data['after'],data['snapshot']['readback'])
        self.assertEqual(len(data['snapshot']['catalog_rows']),1);self.assertEqual(len(data['dto']['items'][0]['images']),130)
        self.assertEqual(data['dto']['items'][0]['description'],'Manual protected description')

if __name__=='__main__':unittest.main()
