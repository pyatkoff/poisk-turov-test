from __future__ import annotations
import ast
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
SOURCE=Path(os.environ.get('LOCAL_TV_SCHEMA_SOURCE_ROOT',str(ROOT/'local-tv-source')))
spec=importlib.util.spec_from_file_location('content_control',ROOT/'scripts/deploy/int_server_executor_local_tv_content.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class ContentControl(unittest.TestCase):
    def wrapper(self):
        s=importlib.util.spec_from_file_location('content_wrapper',ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py');w=importlib.util.module_from_spec(s);s.loader.exec_module(w);return w
    def test_exact_operations_bound_calls_and_preserve_old_parsers(self):
        c=self.wrapper().core
        for operation,action in m.OPERATIONS.items():
            cmd=c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {operation}')
            self.assertEqual(cmd['provider_http_calls'],200 if action=='fill' else 0)
            self.assertEqual(cmd['action'],action);self.assertEqual(cmd['old_profile_writes'],0)
            for suffix in (' 250',' --old-plan',' 101'):
                with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {operation}{suffix}')
            with self.assertRaises(ValueError):m.activate(c,dict(cmd,provider_http_calls=True))
        self.assertEqual(c.parse_command(f'{c.PREFIX}{"a"*40} local-tv-seed-v1 int-andromeda-local-tv-frontier-20261010-v1')['action'],'frontier')
    def test_exact_bundle_keeps_legacy_writers_out_and_rejects_changed_client(self):
        data,files=m.bundle_source(SOURCE);self.assertTrue(data);self.assertEqual(set(files),set(m.BUNDLE_FILES))
        self.assertNotIn('v2/data/migrate-hotel-details-v1.php',files)
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory)
            for p in m.SOURCE_HASHES:
                f=root/p;f.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(SOURCE/p,f)
            (root/'v2/data/tourvisor-client-v1.php').write_text('unreviewed client')
            with self.assertRaisesRegex(ValueError,'reviewed_source_changed'):m.bundle_source(root)
    def test_stock_reservation_manifest_fresh_parent_and_no_replay_stay(self):
        c=self.wrapper().core;cmd=c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {next(iter(m.OPERATIONS))}');m.activate(c,cmd);ast.parse(c.REMOTE)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",c.REMOTE)
        self.assertIn('content_parent_changed',c.REMOTE);self.assertIn('content_independent_readback',c.REMOTE)
        self.assertIn("result['database_writes']=None",c.REMOTE)
        self.assertIn("mode not in ('local-tv-content-v1','reconcile',",c.REMOTE)
    def test_current_release_and_owner_are_required(self):
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

@unittest.skipUnless(os.environ.get('LOCAL_TV_SCHEMA_NATIVE_CI')=='1','isolated native MySQL CI required')
class ContentNative(unittest.TestCase):
    def run_native(self,scenario):
        code=r'''
declare(strict_types=1);
define('LOCAL_TV_SCHEMA_LIBRARY_ONLY',true);define('LOCAL_TV_SEED_LIBRARY_ONLY',true);define('LOCAL_TV_CONTENT_LIBRARY_ONLY',true);
require $argv[1].'/scripts/diagnostics/local_tv_schema_v1.php';require $argv[1].'/scripts/diagnostics/local_tv_seed_v1.php';require $argv[1].'/scripts/diagnostics/local_tv_content_v1.php';require $argv[2].'/v2/data/local-tv-catalog-v1.php';
$dsn=(string)getenv('LOCAL_TV_SCHEMA_TEST_DSN');if($dsn!=='mysql:host=127.0.0.1;port=3306;charset=utf8mb4' || getenv('LOCAL_TV_SCHEMA_TEST_PASSWORD')!=='local_tv_schema_test_only')throw new RuntimeException('fixture_only');
$server=new PDO($dsn,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$name='local_tv_content_ci_'.bin2hex(random_bytes(6));$dir=$argv[4];$scenario=$argv[3];
class ContentLostAck extends PDO{public bool $loseAck=false;public function commit():bool{$r=parent::commit();if($this->loseAck)throw new RuntimeException('lost_ack');return $r;}}
try{
    $server->exec('CREATE DATABASE `'.$name.'`');$db=new ContentLostAck($dsn.';dbname='.$name,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec(file_get_contents($argv[2].'/v2/data/migrations/20261010-local-tv-catalog.sql'));
    $db->exec('CREATE TABLE tour_price_observations(hotel_id INT,search_id INT,source VARCHAR(32),observed_at DATETIME)');$db->exec('CREATE TABLE hot_tours_current(hotel_id INT,fetched_at DATETIME)');
    $db->exec('CREATE TABLE catalog_hotel_details(hotel_id INT PRIMARY KEY,raw_json LONGTEXT,source_hash VARCHAR(64),fetched_at DATETIME,status VARCHAR(24))');
    $db->exec('CREATE TABLE anytour_hotels(id INT PRIMARY KEY,profile_json LONGTEXT,profile_sha256 VARCHAR(64),revision INT,is_active INT)');
    $db->exec('CREATE TABLE anytour_hotel_sources(anytour_hotel_id INT,namespace VARCHAR(64),external_key VARCHAR(128),acquired_via VARCHAR(64),source_json LONGTEXT,source_sha256 VARCHAR(64),first_seen_at DATETIME,last_seen_at DATETIME)');
    $db->exec("INSERT INTO anytour_hotel_sources VALUES(50,'legacy_catalog','101','manual','{}','x','2026-10-01 00:00:00','2026-10-01 00:00:00')");
    $db->exec("INSERT INTO catalog_hotel_details VALUES(102,NULL,NULL,'2026-10-10 00:00:00','not_found'),(104,NULL,NULL,'2026-10-10 00:00:00','failure'),(106,NULL,NULL,'2026-10-01 00:00:00','failure')");
    $c=new LocalTvCatalogV1($db);$c->discover(array_map(static fn($id)=>['id'=>$id],range(101,106)),'user_search','2026-10-01 00:00:00');$c->setManualFields(105,['description'=>'Manual protected description'],1);
    $scope=['pending'=>[]];foreach($db->query('SELECT * FROM local_tv_hotels ORDER BY id') as $r){$id=(int)$r['id'];$q=$db->prepare('SELECT * FROM catalog_hotel_details WHERE hotel_id=?');$q->execute([$id]);$d=$q->fetch(PDO::FETCH_ASSOC);$scope['pending'][]=['id'=>$id,'revision'=>(int)$r['revision'],'old_local_ids'=>[],'retained_reason'=>$id===103?'generic_accommodation_product':($d?'raw_missing':'cache_missing'),'retained_sha256'=>$d['source_hash']??null,'retained_fetched_at'=>$d['fetched_at']??null];}
    $before=LocalTvSeedV1::readback($db);$plan=LocalTvContentV1::plan($db,$scope,$dir,'2026-10-10 12:00:00');if($before!==LocalTvSeedV1::readback($db))throw new RuntimeException('plan_wrote');
    $calls=[];$result=null;$error=null;
    if($scenario==='target_drift')$c->discover([['id'=>777]],'user_search','2026-10-10 12:00:00');
    if($scenario==='edge_drift')$db->exec("INSERT INTO anytour_hotel_sources VALUES(51,'legacy_catalog','105','manual','{}','x','2026-10-01 00:00:00','2026-10-01 00:00:00')");
    if($scenario==='cache_drift')$db->exec("UPDATE catalog_hotel_details SET status='not_found' WHERE hotel_id=106");
    if($scenario==='lost_ack')$db->loseAck=true;
    if($scenario!=='plan'){
        mkdir($dir.'/fill',0700);
        try{$result=LocalTvContentV1::fill($db,$plan,$dir.'/fill','2026-10-10 12:00:00',static function(int $id)use(&$calls,$scenario):array{
            $calls[]=$id;if($scenario==='quota')throw new RuntimeException('Tourvisor HTTP 429 after 1 attempt(s)');
            return ['id'=>$scenario==='wrong_id' && $id===106?999:$id,'name'=>'Fixture hotel '.$id,'common'=>['description'=>'Imported description'],'images'=>array_map(static fn($n)=>'https://fixture.example.test/'.$id.'/'.$n.'.jpg',range(1,130))];
        });}catch(Throwable $e){$error=$e->getMessage();}
    }
    $db->loseAck=false;$read=LocalTvSeedV1::readback($db);$after=LocalTvSeedV1::inventory($db);
    echo LocalTvSchemaV1::json(['plan'=>LocalTvContentV1::summary($plan),'calls'=>$calls,'result'=>$result,'error'=>$error,'read'=>$read,'dto'=>$c->read([105]),'old_unchanged'=>$plan['protected_snapshots']===$after['images']]);
}finally{$server->exec('DROP DATABASE `'.$name.'`');}
'''
        with tempfile.TemporaryDirectory() as directory:
            r=subprocess.run(['php','-r',code,str(ROOT),str(SOURCE),scenario,directory],text=True,capture_output=True,timeout=30)
            self.assertEqual(r.returncode,0,r.stderr);return json.loads(r.stdout)
    def test_current_plan_excludes_all_old_edges_generic_known_absence_and_recent_failure(self):
        r=self.run_native('plan');self.assertEqual(r['plan']['eligible_ids'],[105,106]);self.assertEqual(r['calls'],[]);self.assertTrue(r['old_unchanged'])
        self.assertEqual(r['plan']['excluded'],{'known_not_found_without_new_observation':[102],'not_an_admitted_missing_source':[103],'old_legacy_edge':[101],'recent_failure':[104]})
    def test_real_fill_preserves_manual_description_full_gallery_and_old_bytes(self):
        r=self.run_native('fill');self.assertIsNone(r['error']);self.assertEqual(r['calls'],[105,106]);self.assertEqual(r['result']['filled'],2);self.assertTrue(r['old_unchanged'])
        self.assertEqual(r['dto']['items'][0]['description'],'Manual protected description');self.assertEqual(len(r['dto']['items'][0]['images']),130)
    def test_wrong_identity_cannot_be_saved(self):
        r=self.run_native('wrong_id');self.assertIsNone(r['error']);self.assertEqual(r['result']['filled'],1);self.assertEqual(r['read']['ready'],1);self.assertEqual(r['result']['state_updates'],1)
    def test_quota_stops_without_trying_remaining_ids(self):
        r=self.run_native('quota');self.assertEqual(r['calls'],[105]);self.assertTrue(r['result']['stopped']);self.assertEqual(r['result']['deferred'],1);self.assertEqual(r['read']['ready'],0)
    def test_target_and_old_edge_drift_stop_before_provider_calls(self):
        for scenario in ('target_drift','edge_drift'):
            with self.subTest(scenario=scenario):r=self.run_native(scenario);self.assertEqual(r['calls'],[]);self.assertIsNotNone(r['error'])
    def test_changed_cache_status_stops_without_requesting_that_hotel(self):
        r=self.run_native('cache_drift');self.assertEqual(r['calls'],[105]);self.assertEqual(r['error'],'content_unknown_no_replay');self.assertEqual(r['read']['ready'],1)
    def test_lost_real_commit_ack_is_unknown_with_effects_preserved_and_no_retry(self):
        r=self.run_native('lost_ack');self.assertEqual(r['error'],'content_unknown_no_replay');self.assertEqual(r['calls'],[105]);self.assertEqual(r['read']['ready'],1);self.assertTrue(r['old_unchanged'])

if __name__=='__main__':unittest.main()
