from __future__ import annotations
import ast
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
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
