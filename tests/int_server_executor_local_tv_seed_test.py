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
import types
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SOURCE = Path(os.environ.get('LOCAL_TV_SCHEMA_SOURCE_ROOT', str(ROOT/'local-tv-source')))
spec = importlib.util.spec_from_file_location('seed_contract',ROOT/'scripts/deploy/int_server_executor_local_tv_seed.py')
assert spec and spec.loader
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)


class SeedControl(unittest.TestCase):
    def wrapper(self):
        spec=importlib.util.spec_from_file_location('seed_wrapper',ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py')
        w=importlib.util.module_from_spec(spec);spec.loader.exec_module(w);return w

    def test_fixed_scope_preserves_existing_schema_and_old_parsers(self):
        w=self.wrapper();c=w.core
        for operation,action in m.OPERATIONS.items():
            command=c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {operation}')
            self.assertEqual(command['action'],action)
            for key in ('provider_http_calls','old_profile_writes','mapping_writes','schema_writes'):self.assertEqual(command[key],0)
            for suffix in (' 20',' --apply',' ../old-plan.json',' 1868'):
                with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {operation}{suffix}')
        schema=c.parse_command(f'{c.PREFIX}{"a"*40} local-tv-schema-v1 int-andromeda-local-tv-schema-inspect-20261010-v1')
        self.assertEqual(schema['action'],'inspect')

    def test_bundle_contains_only_reviewed_pure_source_and_private_runners(self):
        bundle,hashes=m.bundle_source(SOURCE)
        self.assertTrue(bundle);self.assertEqual(set(hashes),set(m.BUNDLE_FILES))
        self.assertNotIn('v2/data/tourvisor-v1.php',hashes)
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory)
            for p in m.SOURCE_HASHES:
                target=root/p;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(SOURCE/p,target)
            p=root/'v2/data/local-tv-catalog-v1.php';p.write_text(p.read_text()+'\n// unreviewed source')
            with self.assertRaisesRegex(ValueError,'reviewed_source_changed'):m.bundle_source(root)

    def test_stock_reservation_host_keys_manifest_and_no_replay_remain(self):
        w=self.wrapper();c=w.core
        command=c.parse_command(f'{c.PREFIX}{"a"*40} {m.MODE} {next(iter(m.OPERATIONS))}')
        m.activate(c,command);ast.parse(c.REMOTE)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",c.REMOTE)
        self.assertIn('seed-plan.json',c.REMOTE)
        self.assertIn("mode not in ('local-tv-seed-v1','reconcile',",c.REMOTE)
        self.assertIn('initial', (ROOT/'scripts/diagnostics/local_tv_seed_v1.php').read_text().lower())
        self.assertIn('StrictHostKeyChecking=yes',(ROOT/'scripts/deploy/int_server_executor.py').read_text())
        altered=dict(command,provider_http_calls=True)
        with self.assertRaises(ValueError):m.activate(c,altered)

    def test_current_release_and_owner_are_required(self):
        c=self.wrapper().core;sha='a'*40;control='b'*40
        body=f'{c.PREFIX}{sha} {m.MODE} {next(iter(m.OPERATIONS))}'
        event={'issue':{'number':4217},'comment':{'id':98,'user':{'id':226193297},'author_association':'OWNER','body':body}}
        def api(path,token):
            if path=='/issues/comments/98':return {'body':body,'user':{'id':226193297}}
            if path=='/git/ref/heads/main':return {'object':{'sha':control}}
            if path=='/git/ref/heads/release/search3-production-ready-v1':return {'object':{'sha':sha}}
            raise AssertionError(path)
        with mock.patch.object(c,'api_get',side_effect=api):self.assertEqual(c.checked_event('fake',event,control)['mode'],m.MODE)
        event['comment']['user']['id']=1
        with self.assertRaisesRegex(ValueError,'owner'):c.checked_event('fake',event,control)


@unittest.skipUnless(os.environ.get('LOCAL_TV_SCHEMA_NATIVE_CI')=='1','native isolated MySQL CI required')
class SeedNative(unittest.TestCase):
    def run_native(self,scenario):
        code=r'''
declare(strict_types=1);
define('LOCAL_TV_SCHEMA_LIBRARY_ONLY',true);define('LOCAL_TV_SEED_LIBRARY_ONLY',true);
require $argv[1].'/scripts/diagnostics/local_tv_schema_v1.php';
require $argv[1].'/scripts/diagnostics/local_tv_seed_v1.php';
require $argv[2].'/v2/data/local-tv-catalog-v1.php';
$scenario=$argv[3];$directory=$argv[4];
$dsn=(string)getenv('LOCAL_TV_SCHEMA_TEST_DSN');
if($dsn!=='mysql:host=127.0.0.1;port=3306;charset=utf8mb4' || getenv('LOCAL_TV_SCHEMA_TEST_PASSWORD')!=='local_tv_schema_test_only')throw new RuntimeException('fixture_only');
$server=new PDO($dsn,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='local_tv_seed_ci_'.bin2hex(random_bytes(6));$server->exec('CREATE DATABASE `'.$name.'`');
class LostSeedAck extends PDO{public function commit():bool{$result=parent::commit();throw new RuntimeException('lost_commit_ack');}}
try{
    $class=$scenario==='lost_ack'?LostSeedAck::class:PDO::class;
    $db=new $class($dsn.';dbname='.$name,'root','local_tv_schema_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec(file_get_contents($argv[2].'/v2/data/migrations/20261010-local-tv-catalog.sql'));
    $db->exec('CREATE TABLE tour_price_observations(hotel_id INT,search_id INT,source VARCHAR(32),observed_at DATETIME)');
    $db->exec('CREATE TABLE hot_tours_current(hotel_id INT,fetched_at DATETIME)');
    $db->exec('CREATE TABLE catalog_hotel_details(hotel_id INT PRIMARY KEY,raw_json LONGTEXT,source_hash VARCHAR(64),fetched_at DATETIME)');
    $db->exec('CREATE TABLE anytour_hotels(id INT PRIMARY KEY,profile_json LONGTEXT,profile_sha256 VARCHAR(64),revision INT,is_active INT)');
    $db->exec('CREATE TABLE anytour_hotel_sources(anytour_hotel_id INT,namespace VARCHAR(64),external_key VARCHAR(128),acquired_via VARCHAR(64),source_json LONGTEXT,source_sha256 VARCHAR(64),first_seen_at DATETIME,last_seen_at DATETIME)');
    $db->exec("INSERT INTO tour_price_observations VALUES(101,1,'user_search','2026-10-01 00:00:00'),(101,2,'scheduled_monitor','2026-10-02 00:00:00'),(103,3,'hot_tours','2026-10-03 00:00:00'),(999,4,'demo','2026-10-01 00:00:00')");
    $db->exec("INSERT INTO hot_tours_current VALUES(102,'2026-10-01 00:00:00')");
    $db->exec('CREATE TABLE anytour_offers(provider VARCHAR(32),legacy_hotel_id INT,observed_at DATETIME,last_seen_at DATETIME)');
    $db->exec("INSERT INTO anytour_offers VALUES('tourvisor',104,'2026-10-01 00:00:00','2026-10-05 00:00:00'),('anex',999,'2026-10-01 00:00:00','2026-10-05 00:00:00')");
    $profile=['id'=>50,'name'=>'Manual old name','description'=>'Manual protected description'];$json=LocalTvCatalogV1::json($profile);
    $db->prepare('INSERT INTO anytour_hotels VALUES(?,?,?,?,?)')->execute([50,$json,hash('sha256',$json),1,1]);
    $seed=['id'=>101,'name'=>'Retained hotel 101','description'=>'Old imported description','images'=>[]];
    $q=$db->prepare('INSERT INTO anytour_hotel_sources VALUES(?,?,?,?,?,?,?,?)');
    foreach([['legacy_catalog','101','saved_catalog',$seed],['anex:accepted','native-77','manual_review',['decision'=>'manual accepted provider sentinel']]] as $s){
        $json=LocalTvCatalogV1::json($s[3]);$q->execute([50,$s[0],$s[1],$s[2],$json,hash('sha256',$json),'2026-10-01 00:00:00','2026-10-01 00:00:00']);
    }
    $q=$db->prepare('INSERT INTO catalog_hotel_details VALUES(?,?,?,?)');
    foreach([101,103,999] as $id){
        $card=['id'=>$id,'name'=>'Retained hotel '.$id,'common'=>['description'=>'Fresh imported description'],'images'=>array_map(static fn($n)=>'https://fixture.example.test/'.$id.'/'.$n.'.jpg',range(1,130))];
        $json=LocalTvCatalogV1::json($card);$q->execute([$id,$json,$id===103?str_repeat('0',64):hash('sha256',$json),'2026-10-09 00:00:00']);
    }
    $plan=LocalTvSeedV1::inventory($db,$directory);$summary=LocalTvSeedV1::summary($plan);
    $before=(new LocalTvCatalogV1($db))->counts();$error=null;$result=null;$other=null;
    if($scenario==='changed_cache')$db->exec("UPDATE catalog_hotel_details SET raw_json='{}' WHERE hotel_id=101");
    if($scenario==='changed_profile')$db->exec("UPDATE anytour_hotels SET profile_json='{}' WHERE id=50");
    if($scenario==='nonempty')(new LocalTvCatalogV1($db))->register(777,'2026-10-01 00:00:00','2026-10-01 00:00:00',[]);
    if($scenario==='lock'){$other=new PDO($dsn.';dbname='.$name,'root','local_tv_schema_test_only');$other->query("SELECT GET_LOCK('anytour-local-tv-daily',0)");}
    if($scenario==='wrong_db')$plan['schema']['database_sha256']=str_repeat('0',64);
    if($scenario==='apply' || $scenario==='lost_ack' || $scenario==='changed_cache' || $scenario==='changed_profile' || $scenario==='nonempty' || $scenario==='lock' || $scenario==='wrong_db'){
        $apply=$directory.'/apply';mkdir($apply,0700);
        try{$result=LocalTvSeedV1::apply($db,$plan,$apply,'2026-10-10 00:00:00');}catch(Throwable $e){$error=$e->getMessage();}
    }
    $read=LocalTvSeedV1::readback($db);$dto=(new LocalTvCatalogV1($db))->read([50],true);
    $after=LocalTvSeedV1::inventory($db);
    if($other)$other->query("SELECT RELEASE_LOCK('anytour-local-tv-daily')");
    echo LocalTvSchemaV1::json(['summary'=>$summary,'before'=>$before,'error'=>$error,'result'=>$result,'read'=>$read,'dto'=>$dto,
        'old_unchanged'=>$after['images']['profiles']===$plan['images']['profiles'] && $after['images']['sources']===$plan['images']['sources'],
        'raw_snapshot_sha'=>hash_file('sha256',$directory.'/retained.jsonl')]);
}finally{$server->exec('DROP DATABASE `'.$name.'`');}
'''
        with tempfile.TemporaryDirectory() as directory:
            result=subprocess.run(['php','-r',code,str(ROOT),str(SOURCE),scenario,directory],text=True,capture_output=True,env=os.environ,timeout=30)
            self.assertEqual(result.returncode,0,result.stderr);return json.loads(result.stdout)

    def test_actual_inventory_reads_all_history_without_registering_dictionary(self):
        r=self.run_native('inventory');self.assertEqual(r['summary']['observed'],4)
        self.assertEqual(r['summary']['retained_valid'],1);self.assertEqual(r['summary']['retained_invalid'],1)
        self.assertEqual(r['before']['discovered'],0);self.assertEqual(r['read']['discovered'],0)
        self.assertTrue(r['old_unchanged']);self.assertEqual(r['raw_snapshot_sha'],r['summary']['snapshots']['retained']['sha256'])

    def test_actual_transfer_preserves_manual_content_full_gallery_provider_snapshot_and_counts(self):
        r=self.run_native('apply');self.assertIsNone(r['error']);self.assertEqual(r['result']['filled'],1)
        self.assertEqual(r['read']['discovered'],4);self.assertEqual(r['read']['ready'],1);self.assertEqual(r['read']['unfinished'],3)
        self.assertEqual(r['read']['links'],1);self.assertTrue(r['old_unchanged'])
        item=r['dto']['items'][0];self.assertEqual(item['description'],'Manual protected description')
        self.assertEqual(item['name'],'Manual old name');self.assertEqual(len(item['images']),130)
        self.assertEqual(r['dto']['links'],[{'oldLocalId':50,'tourvisorHotelId':101}])
        self.assertNotIn('source_json',item)

    def test_lost_ack_after_real_new_link_commit_stops_without_filling_source(self):
        r=self.run_native('lost_ack');self.assertEqual(r['error'],'seed_unknown_no_replay')
        self.assertEqual(r['read']['links'],1);self.assertEqual(r['read']['ready'],0);self.assertTrue(r['old_unchanged'])

    def test_current_cache_and_profile_drift_are_held_before_new_data_write(self):
        for scenario in ('changed_cache','changed_profile'):
            with self.subTest(scenario=scenario):
                r=self.run_native(scenario);self.assertEqual(r['error'],'seed_current_inventory_changed');self.assertEqual(r['read']['discovered'],0)

    def test_wrong_target_nonempty_initial_target_and_active_collector_are_held(self):
        for scenario in ('wrong_db','nonempty','lock'):
            with self.subTest(scenario=scenario):
                r=self.run_native(scenario);self.assertIsNone(r['result']);self.assertEqual(r['read']['ready'],0)
                self.assertEqual(r['read']['discovered'],1 if scenario=='nonempty' else 0)


if __name__=='__main__':unittest.main()
