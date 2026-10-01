#!/usr/bin/env python3
"""Offline contract tests. No network, SSH, production DB or supplier requests."""
from __future__ import annotations
import ast
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tarfile
import tempfile
import types
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('local_mass_control', ROOT/'scripts/deploy/int_server_executor_local_profile_plan.py')
local = importlib.util.module_from_spec(spec)
spec.loader.exec_module(local)
SHA, CONTROL = 'a'*40, 'b'*40

class AdmissionTest(unittest.TestCase):
    def core(self):
        def reject(_): raise ValueError('unrecognized')
        core = types.SimpleNamespace(PREFIX='/run-int-server-v1 ', SHA_RE=re.compile(r'[a-f0-9]{40}\Z'), parse_command=reject)
        local.register_parser(core)
        return core

    def test_only_two_exact_pairs_and_zero_authority(self):
        core=self.core()
        for operation,batch in [(local.OPERATION,local.BATCH),(local.MASS_OPERATION,local.MASS_BATCH)]:
            command=core.parse_command(f'{core.PREFIX}{SHA} {local.MODE} {operation} {batch}')
            self.assertEqual((0,0),(command['maximum_writes'],command['provider_http_calls']))
            self.assertEqual(batch,command['batch'])
        for operation,batch in [(local.OPERATION,local.MASS_BATCH),(local.MASS_OPERATION,local.BATCH),
                                (local.MASS_OPERATION.replace('-v1','-v2'),local.MASS_BATCH)]:
            with self.assertRaises(ValueError): core.parse_command(f'{core.PREFIX}{SHA} {local.MODE} {operation} {batch}')

    def test_no_arbitrary_id_limit_source_or_apply(self):
        core=self.core(); cmd=f'{core.PREFIX}{SHA} {local.MODE} {local.MASS_OPERATION} {local.MASS_BATCH}'
        for bad in [cmd+' --apply',cmd+' 250',cmd.replace(SHA,'z'*40),cmd.replace(local.MASS_BATCH,'all'),
                    cmd.replace(local.MODE,'local-profile-apply-4191')]:
            with self.assertRaises(ValueError): core.parse_command(bad)

    def test_bundle_has_only_owners_and_two_trusted_runners(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp)
            for path in local.SOURCE_FILES:
                target=root/path; target.parent.mkdir(parents=True,exist_ok=True); target.write_text('<?php // fixture')
            data,hashes=local.bundle_source(root)
            self.assertEqual(set(local.SOURCE_FILES)|{local.RUNNER,local.MASS_RUNNER,local.MASS2_RUNNER},set(hashes))
            with tarfile.open(fileobj=io.BytesIO(data),mode='r:gz') as archive:
                self.assertEqual(set(hashes)|{'manifest.json'},set(archive.getnames()))
                for name,digest in hashes.items():
                    self.assertEqual(digest,hashlib.sha256(archive.extractfile(name).read()).hexdigest())
                    self.assertEqual(0o600,archive.getmember(name).mode)

    def test_handler_and_php_are_readonly_and_bounded(self):
        ast.parse(local.REMOTE_MASS_HANDLER)
        source=(ROOT/local.MASS_RUNNER).read_text()
        for bad in ('->apply(', 'INSERT ', 'UPDATE ', 'DELETE ', 'ALTER ', 'CREATE TABLE', 'curl_'):
            self.assertNotIn(bad,source)
        self.assertIn('SET SESSION TRANSACTION READ ONLY',source)
        self.assertIn('LIMIT 250',source)
        self.assertIn('array_chunk($eligible,LPP_OWNER_MAX_BATCH,true)',source)
        self.assertIn("$owner->plan(count($scope),$through,$scope,true)",source)
        self.assertIn("basename($dir) === LPM_OPERATION",source)
        self.assertIn("'allow_url_fopen=0'",local.REMOTE_MASS_HANDLER)
        self.assertIn('socket_connect',local.REMOTE_MASS_HANDLER)
        self.assertNotIn('ANEX_API_TOKEN',local.REMOTE_MASS_HANDLER)

class ReceiptTest(unittest.TestCase):
    def fixture(self,tmp):
        root=Path(tmp); stage=root/'source'; target=stage/local.MASS_RUNNER
        target.parent.mkdir(parents=True); target.write_text('<?php')
        data={'schema_version':1,'state':'completed_read_only','operation_id':local.MASS_OPERATION,
              'source_sha':SHA,'control_source_sha':CONTROL,'batch':local.MASS_BATCH,
              'demand_through':'2026-10-02 00:00:00','active_profiles':501,'census_complete':True,
              'core_fields_present':0,'missing_field_counts':{'description':501},'eligible_profiles':0,
              'source_plans_prepared':0,'profiles_with_delta':0,'planned_fields':0,
              'classification_counts':{'D1_MANIFEST_UNKNOWN_HELD':501},'safe_to_apply':False,
              'd1_exclusion_state':'unknown_held','history_exclusion_state':'verified','ready_batches':0,
              'private_plan_sha256':'','provider_http_calls':0,'database_writes':0,
              'profile_writes':0,'mapping_writes':0,'schema_writes':0}
        private=dict(data,batches=[]); run=types.SimpleNamespace(returncode=0,stdout='',stderr=''); calls=[]
        ns={'operation':local.MASS_OPERATION,'source':SHA,'project':root,'op':root,
            'payload':{'batch':local.MASS_BATCH,'maximum_writes':0,'provider_http_calls':0,'local_profile_control_sha':CONTROL},
            'safe_file':lambda p,n:p.is_file() and not p.is_symlink() and p.stat().st_size<=n,
            'safe_json':lambda p,n:json.loads(p.read_text()),
            'fail':lambda r:(_ for _ in ()).throw(RuntimeError(r)),
            'os':os,'re':re,'hashlib':hashlib,
            'subprocess':types.SimpleNamespace(run=lambda *a,**k:calls.append((a,k)) or run)}
        exec(local.REMOTE_MASS_HANDLER,ns)
        return root,stage,data,private,run,calls,ns

    def save(self,root,data,private):
        p=root/'local-mass-plan.json';p.write_text(json.dumps(private))
        data['private_plan_sha256']=hashlib.sha256(p.read_bytes()).hexdigest()
        (root/'local-mass-receipt.json').write_text(json.dumps(data))

    def test_sanitized_zero_write_unknown_is_not_ready(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,private,run,calls,ns=self.fixture(tmp);self.save(root,data,private)
            self.assertEqual(data,ns['run_local_profile_mass_plan_4191'](stage));self.assertEqual(1,len(calls))

    def test_rejects_bad_authority_counts_scope_and_public_leaks(self):
        changes=[{'profile_writes':1},{'database_writes':True},{'safe_to_apply':True},
                 {'active_profiles':30001},{'active_profiles':500},{'ready_batches':1},
                 {'source_plans_prepared':1},{'source_sha':'c'*40},{'raw_profile':'private'},
                 {'classification_counts':{'arbitrary':501}},{'census_complete':False},
                 {'core_fields_present':502},{'missing_field_counts':{'description':502}}]
        for change in changes:
            with self.subTest(change=change),tempfile.TemporaryDirectory() as tmp:
                root,stage,data,private,run,calls,ns=self.fixture(tmp);data.update(change);self.save(root,data,private)
                with self.assertRaises(RuntimeError):ns['run_local_profile_mass_plan_4191'](stage)

    def test_missing_terminal_and_process_error_are_not_retried(self):
        for kind in ('missing','nonzero','stderr'):
            with self.subTest(kind=kind),tempfile.TemporaryDirectory() as tmp:
                root,stage,data,private,run,calls,ns=self.fixture(tmp);self.save(root,data,private)
                if kind=='missing':(root/'local-mass-receipt.json').unlink()
                elif kind=='nonzero':run.returncode=2
                else:run.stderr='failure'
                with self.assertRaisesRegex(RuntimeError,'no_replay'):ns['run_local_profile_mass_plan_4191'](stage)
                self.assertEqual(1,len(calls))

    def test_each_private_batch_is_bound_and_never_exposed(self):
        for corrupt in (False,True):
            with self.subTest(corrupt=corrupt),tempfile.TemporaryDirectory() as tmp:
                root,stage,data,private,run,calls,ns=self.fixture(tmp)
                data.update(active_profiles=1,eligible_profiles=1,source_plans_prepared=1,profiles_with_delta=1,
                    planned_fields=1,ready_batches=1,d1_exclusion_state='verified_terminal_manifest',
                    missing_field_counts={'description':1},classification_counts={'RETAINED_DELTA_PREPARED':1})
                private.update(data);f=root/'mass-batch-001.json';f.write_text('{"beforeProfileJson":"private"}')
                private['batches']=[{'file':f.name,'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),
                    'profiles':1,'scope_profiles':1,'plan_sha256':'d'*64}]
                self.save(root,data,private)
                if corrupt:f.write_text('changed')
                if corrupt:
                    with self.assertRaisesRegex(RuntimeError,'batch_digest'):ns['run_local_profile_mass_plan_4191'](stage)
                else:self.assertEqual(data,ns['run_local_profile_mass_plan_4191'](stage))

@unittest.skipUnless(shutil.which('php'),'PHP required')
class PhpPlannerTest(unittest.TestCase):
    FIXTURE=r'''
function ok($v){if(!$v)throw new RuntimeException('assertion');}
function rawRow($own,$local){
 $profile=['description'=>'','primaryImage'=>'https://example.invalid/a.jpg','images'=>['https://example.invalid/a.jpg'],
  'address'=>'A','place'=>'P','build'=>'2000','repair'=>'2020','square'=>'100',
  'hotelInformation'=>['infrastructure'=>['x'],'services'=>['x'],'meals'=>['x'],'roomTypes'=>['x']]];
 $raw=lpp_json($profile);$proof=lpp_json(['schema_version'=>1,'accepted_local_hotel_id'=>$local,
  'canonical_hotel_id'=>$own,'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('a',64)]);
 return ['id'=>$own,'revision'=>1,'profile_json'=>$raw,'profile_sha256'=>hash('sha256',$raw),'alias_count'=>1,
  'local_id'=>$local,'alias_acquired_via'=>'canonical_local_alias_v1','alias_source_json'=>$proof,
  'alias_source_sha256'=>hash('sha256',$proof),'prior_content_operations'=>0];
}
function snapshot($n){$rows=[];for($i=1;$i<=$n;$i++)$rows[100000+$i]=lpm_describe(rawRow(100000+$i,200000+$i));
 return ['activeProfiles'=>$n,'rows'=>$rows,'history'=>['state'=>'verified','localIds'=>[]]];}
function d1(){return ['state'=>'verified_terminal_manifest','ownIds'=>[],'legacyIds'=>[]];}
function planFor($scope){$selected=[];foreach($scope as $s){$raw=rawRow($s['anytourHotelId'],$s['localHotelId']);
 $selected[]=['anytourHotelId'=>$s['anytourHotelId'],'localHotelId'=>$s['localHotelId'],'expectedRevision'=>1,
 'expectedProfileSha256'=>$raw['profile_sha256'],'expectedAliasSha256'=>$raw['alias_source_sha256'],
 'beforeProfileJson'=>$raw['profile_json'],'patch'=>['description'=>'Saved card']];}
 $core=['schemaVersion'=>1,'contentPolicy'=>'sync_imported_retained_tv_v1','demandThrough'=>'2026-10-02 00:00:00',
 'limit'=>count($scope),'activeProfiles'=>count($scope),'scannedProfiles'=>count($scope),'selected'=>$selected,
 'contentScope'=>$scope,'held'=>[]];$core['planSha256']=lpm_digest($core);
 return ['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0]+$core;}
function seal($index,$plan){return ['index'=>$index,'scope'=>$plan['contentScope'],'sha'=>$plan['planSha256']];}
function rehash($p){$core=$p;unset($core['status'],$core['writes'],$core['supplierCalls'],$core['planSha256']);$p['planSha256']=lpm_digest($core);return $p;}
'''
    def php(self,body):
        result=subprocess.run(['php','-r','require $argv[1];\n'+self.FIXTURE+body,str(ROOT/local.MASS_RUNNER)],
                              capture_output=True,text=True,timeout=30)
        self.assertEqual(0,result.returncode,result.stderr+result.stdout)

    def test_501_independent_profiles_form_three_exact_batches(self):
        self.php("""$sizes=[];$a=lpm_prepare(snapshot(501),d1(),function($s)use(&$sizes){$sizes[]=count($s);return planFor($s);},'seal');
        ok($sizes===[250,250,1]);ok(count($a['batches'])===3);ok($a['profiles_with_delta']===501);
        ok($a['planned_fields']===501);ok($a['safe_to_apply']===false);ok($a['census_complete']===true);""")

    def test_2000_bound_and_live_demand_priority(self):
        self.php("""$s=snapshot(2001);$s['rows'][102001]['userSearches']=9;$calls=[];
        $a=lpm_prepare($s,d1(),function($scope)use(&$calls){$calls[]=$scope;return planFor($scope);},'seal');
        ok(count($calls)===8);ok($a['source_plans_prepared']===2000);ok($a['eligible_profiles']===2001);
        ok($a['classification_counts']['PLAN_BOUND_DEFERRED']===1);
        ok(in_array(102001,array_column($calls[0],'anytourHotelId'),true));""")

    def test_entire_old366_and_both_d1_identity_namespaces_excluded_before_owner(self):
        self.php("""$s=snapshot(3);$old=lpp_ids()[0];$s['rows'][$old]=lpm_describe(rawRow($old,990000));$s['activeProfiles']=4;
        $d=d1();$d['ownIds']=[100001];$d['legacyIds']=[200002];$s['history']['localIds']=[200003];$calls=0;
        $a=lpm_prepare($s,$d,function($scope)use(&$calls){++$calls;throw new RuntimeException('must not call');},'seal');
        ok($calls===0);ok($a['classification_counts']['D1_OVERLAP_HELD']===2);
        ok($a['classification_counts']['HISTORICAL_366_HELD']===2);""")

    def test_unknown_exclusion_manifest_blocks_all_owner_calls(self):
        self.php("""foreach(['d1','history']as $kind){$s=snapshot(501);$d=d1();
        if($kind==='d1')$d['state']='unknown_held';else $s['history']['state']='unknown_held';
        $a=lpm_prepare($s,$d,function(){throw new RuntimeException('must not call');},'seal');
        ok($a['source_plans_prepared']===0);ok($a['batches']===[]);ok(array_sum($a['classification_counts'])===501);}""")

    def test_no_delta_is_never_called_complete_and_missing_source_distinct(self):
        self.php("""foreach(['none','source','provenance']as $kind){$a=lpm_prepare(snapshot(1),d1(),function($scope)use($kind){
        $p=planFor($scope);$p['selected']=[];
        if($kind!=='none')$p['held'][100001]=['description'=>$kind==='source'?'source_missing_preserved':'SYNC_IMPORT_IDENTITY'];
        return rehash($p);},'seal');$state=['none'=>'NO_DELTA_NOT_PROVEN_COMPLETE','source'=>'SOURCE_MISSING','provenance'=>'SOURCE_PROVENANCE_HELD'][$kind];
        ok(($a['classification_counts'][$state]??0)===1);ok($a['profiles_with_delta']===0);ok($a['batches']===[]);}""")

    def test_nonempty_fields_prior_revision_and_alias_integrity_protected(self):
        self.php("""$row=rawRow(100001,200001);$d=lpm_describe($row);ok($d['missingFields']===['description']);
        $row['revision']=2;ok(lpm_describe($row)['state']==='PRIOR_OR_EDITORIAL_HELD');
        $row['revision']=1;$row['prior_content_operations']=1;ok(lpm_describe($row)['state']==='PRIOR_OR_EDITORIAL_HELD');
        $row['alias_count']=2;ok(lpm_describe($row)['state']==='ALIAS_HELD');
        $row['alias_count']=1;$row['alias_source_sha256']=str_repeat('0',64);ok(lpm_describe($row)['state']==='ALIAS_HELD');
        $row=rawRow(100001,200001);$row['profile_json']='changed';ok(lpm_describe($row)['state']==='PROFILE_INTEGRITY_HELD');""")

    def test_scope_hash_revision_and_foreign_patch_drift_do_not_seal_any_batch(self):
        self.php("""foreach(['hash','revision','scope','patch','duplicate','held','empty']as $kind){$saved=0;
        $a=lpm_prepare(snapshot(1),d1(),function($scope)use($kind){$p=planFor($scope);
        if($kind==='hash'){$p['planSha256']=str_repeat('0',64);return $p;}
        if($kind==='revision')$p['selected'][0]['expectedRevision']=2;
        if($kind==='scope')$p['contentScope'][0]['localHotelId']=99;
        if($kind==='patch')$p['selected'][0]['patch']['name']='not permitted';
        if($kind==='duplicate')$p['selected'][]=$p['selected'][0];
        if($kind==='held')$p['held'][99]=['profile'=>'bad'];
        if($kind==='empty')$p['selected'][0]['patch']=['description'=>''];return rehash($p);
        },function()use(&$saved){++$saved;return [];});ok($saved===0);ok(($a['classification_counts']['OWNER_PLAN_HELD']??0)===1);}""")

    def test_private_seal_is_write_once_and_0600(self):
        with tempfile.TemporaryDirectory() as tmp:
            path=json.dumps(str(Path(tmp)/'plan.json'))
            self.php(f"$p={path};$sha=lpp_save($p,['safe_to_apply'=>false]);ok(hash_file('sha256',$p)===$sha);ok((fileperms($p)&0777)===0600);"
                     "try{lpp_save($p,['changed'=>true]);throw new LogicException('replayed');}catch(RuntimeException $e){ok($e->getMessage()==='private_output');}")

    PDO_FIXTURE=r'''
class MassFakeStatement extends PDOStatement {
 public array $rows=[];private int $pos=0;
 public function __construct(private MassFakePDO $db,private string $kind){}
 public function execute(?array $params=null):bool {
  $this->pos=0;$this->rows=[];
  if($this->kind==='history'){foreach(lpp_ids()as $id)$this->rows[]=rawRow($id,900000+$id);}
  elseif($this->kind==='demand'){$this->rows=[['hotel_id'=>200501,'user_search_count'=>9,'observation_count'=>9,'demand_last_seen_at'=>'2026-10-01 00:00:00']];}
  elseif($this->kind==='page'){
   $after=$params['after_id'];$this->db->offsets[]=$after;
   for($i=1;$i<=$this->db->available;$i++){if(100000+$i>$after)$this->rows[]=rawRow(100000+$i,200000+$i);if(count($this->rows)===250)break;}
  }return true;
 }
 public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->rows[$this->pos++]??false;}
 public function fetchColumn(int $column=0):mixed{return $this->db->total;}
 public function closeCursor():bool{return true;}
}
class MassFakePDO extends PDO {
 public bool $transaction=false;public bool $rolledBack=false;public bool $committed=false;public array $sql=[];public array $offsets=[];
 public function __construct(public int $total,public int $available){}
 public function inTransaction():bool{return $this->transaction;}
 public function exec(string $statement):int|false{$this->sql[]=$statement;return 0;}
 public function beginTransaction():bool{ok(!$this->transaction);$this->transaction=true;return true;}
 public function commit():bool{$this->transaction=false;$this->committed=true;return true;}
 public function rollBack():bool{$this->transaction=false;$this->rolledBack=true;return true;}
 public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{ok(str_starts_with($query,'SELECT COUNT(*)'));return new MassFakeStatement($this,'count');}
 public function prepare(string $query,array $options=[]):PDOStatement|false {
  $this->sql[]=$query;ok(str_starts_with($query,'SELECT '));
  $kind=str_contains($query,'WHERE h.id IN')?'history':(str_contains($query,'FROM tour_price_observations')?'demand':'page');
  if($kind==='page')ok(str_contains($query,'LIMIT 250'));
  return new MassFakeStatement($this,$kind);
 }
}
'''
    def test_census_keyset_pages_share_one_readonly_snapshot(self):
        self.php(self.PDO_FIXTURE+"""$db=new MassFakePDO(501,501);$s=lpm_snapshot($db,'2026-10-02 00:00:00');
        ok($s['activeProfiles']===501&&count($s['rows'])===501);ok($s['history']['state']==='verified');
        ok($s['rows'][100501]['userSearches']===9);ok($db->offsets===[0,100250,100500]);
        ok($db->sql[0]==='SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        ok($db->sql[1]==='SET TRANSACTION READ ONLY');ok($db->committed&&!$db->rolledBack);""")

    def test_census_count_drift_rolls_back_without_completeness_claim(self):
        self.php(self.PDO_FIXTURE+"""$db=new MassFakePDO(501,500);try{lpm_snapshot($db,'2026-10-02 00:00:00');
        throw new LogicException('false complete');}catch(RuntimeException $e){ok($e->getMessage()==='census_count');}
        ok($db->rolledBack&&!$db->committed);""")

    def test_census_over_bound_stops_before_profile_reads(self):
        self.php(self.PDO_FIXTURE+"""$db=new MassFakePDO(30001,30001);try{lpm_snapshot($db,'2026-10-02 00:00:00');
        throw new LogicException('false complete');}catch(RuntimeException $e){ok($e->getMessage()==='catalogue_bound_not_complete');}
        ok($db->rolledBack&&!$db->committed);ok($db->offsets===[]);""")

if __name__=='__main__':unittest.main()
