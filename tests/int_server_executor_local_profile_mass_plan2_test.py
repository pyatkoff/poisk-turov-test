#!/usr/bin/env python3
from __future__ import annotations
import ast,hashlib,importlib.util,io,json,os,re,shutil,subprocess,tarfile,tempfile,types,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def load(name,path):
    s=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
control=load('plan_control','scripts/deploy/int_server_executor_local_profile_plan.py')
SHA='a'*40;CONTROL='b'*40

class ControlTest(unittest.TestCase):
    def core(self):
        return types.SimpleNamespace(PREFIX='/run-int-server-v1 ',SHA_RE=re.compile(r'[a-f0-9]{40}\Z'),
            parse_command=lambda b:(_ for _ in ()).throw(ValueError('old')),
            REMOTE="def run_match942(stage, mode, offset, limit):\n    pass\n    if mode=='match-tv942-write':\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if not isinstance(files,dict) or len(files)<20: fail('manifest')\n")
    def test_exact_phase2_pair_zero_authority(self):
        c=self.core();control.register_parser(c)
        cmd=c.parse_command(f'{c.PREFIX}{SHA} {control.MODE} {control.MASS2_OPERATION} {control.MASS2_BATCH}')
        self.assertEqual(0,cmd['maximum_writes']);self.assertEqual(0,cmd['provider_http_calls'])
        for bad in [cmd['operation_id'].replace('-v1','-v2'),control.MASS_OPERATION]:
            with self.assertRaises(ValueError):
                c.parse_command(f'{c.PREFIX}{SHA} {control.MODE} {bad} {control.MASS2_BATCH}')
    def test_no_ids_limits_apply_or_parent_override(self):
        c=self.core();control.register_parser(c)
        cmd=f'{c.PREFIX}{SHA} {control.MODE} {control.MASS2_OPERATION} {control.MASS2_BATCH}'
        for bad in [cmd+' 2000',cmd+' --ids=1',cmd+' '+('0'*64),cmd.replace(control.MODE,'local-profile-apply-4191')]:
            with self.assertRaises(ValueError):c.parse_command(bad)
    def test_bundle_contains_successor_only_as_extra_trusted_runner(self):
        original=control.__file__
        try:
            with tempfile.TemporaryDirectory() as tmp:
                fake=Path(tmp)/'control';source=Path(tmp)/'source'
                control.__file__=str(fake/'scripts/deploy/int_server_executor_local_profile_plan.py')
                for rel in (control.RUNNER,control.MASS_RUNNER,control.MASS2_RUNNER,control.MASS3_RUNNER,control.RECOVERY_RUNNER):
                    p=fake/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(rel)
                for rel in control.SOURCE_FILES:
                    p=source/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(rel)
                data,hashes=control.bundle_source(source)
                self.assertEqual(set(control.SOURCE_FILES)|{control.RUNNER,control.MASS_RUNNER,control.MASS2_RUNNER,control.MASS3_RUNNER,control.RECOVERY_RUNNER},set(hashes))
                with tarfile.open(fileobj=io.BytesIO(data),mode='r:gz') as z:
                    self.assertEqual(set(hashes)|{'manifest.json'},set(z.getnames()))
        finally:control.__file__=original
    def test_remote_is_readonly_and_same_mode(self):
        c=self.core();control.register_parser(c)
        cmd=c.parse_command(f'{c.PREFIX}{SHA} {control.MODE} {control.MASS2_OPERATION} {control.MASS2_BATCH}')
        control.activate(c,cmd);ast.parse(c.REMOTE)
        self.assertIn('run_local_profile_mass2_plan_4191',c.REMOTE)
        self.assertIn("allow_url_fopen=0",control.REMOTE_MASS2_HANDLER)
        self.assertNotIn('--execute',control.REMOTE_MASS2_HANDLER)
        self.assertNotIn('ANEX_API_TOKEN',control.REMOTE_MASS2_HANDLER)

@unittest.skipUnless(shutil.which('php'),'PHP required')
class PhpTest(unittest.TestCase):
    BASE=r'''
function ok($v){if(!$v)throw new LogicException('assertion');}
function aliasProof($own,$local){return lpp_json(['schema_version'=>1,'accepted_local_hotel_id'=>$local,
 'canonical_hotel_id'=>$own,'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('a',64)]);}
function rawRow($own,$local,$search=0){$profile=['description'=>'','primaryImage'=>'x','images'=>['x'],'address'=>'A','place'=>'P',
 'build'=>'2000','repair'=>'2020','square'=>'100','hotelInformation'=>['infrastructure'=>['x'],'services'=>['x'],'meals'=>['x'],'roomTypes'=>['x']]];
 $raw=lpp_json($profile);$proof=aliasProof($own,$local);return ['id'=>$own,'revision'=>1,'profile_json'=>$raw,'profile_sha256'=>hash('sha256',$raw),
 'alias_count'=>1,'local_id'=>$local,'alias_acquired_via'=>'canonical_local_alias_v1','alias_source_json'=>$proof,
 'alias_source_sha256'=>hash('sha256',$proof),'prior_content_operations'=>0,'user_search_count'=>$search,'observation_count'=>0,'demand_last_seen_at'=>''];}
function snap($n,$offset=0){$rows=[];for($i=1;$i<=$n;$i++)$rows[100000+$offset+$i]=lpm_describe(rawRow(100000+$offset+$i,200000+$offset+$i));
 return ['activeProfiles'=>$n,'rows'=>$rows,'history'=>['state'=>'verified','localIds'=>[]]];}
function d1(){return ['state'=>'verified_terminal_manifest','ownIds'=>[],'legacyIds'=>[]];}
function goodPlan($scope){$sel=[];foreach($scope as $s){$r=rawRow($s['anytourHotelId'],$s['localHotelId']);$sel[]=[
 'anytourHotelId'=>$s['anytourHotelId'],'localHotelId'=>$s['localHotelId'],'expectedRevision'=>1,
 'expectedProfileSha256'=>$r['profile_sha256'],'expectedAliasSha256'=>$r['alias_source_sha256'],
 'beforeProfileJson'=>$r['profile_json'],'patch'=>['description'=>'saved']];}
 $core=['schemaVersion'=>1,'contentPolicy'=>'sync_imported_retained_tv_v1','demandThrough'=>'2026-10-02 00:00:00',
 'limit'=>count($scope),'activeProfiles'=>count($scope),'scannedProfiles'=>count($scope),'selected'=>$sel,'contentScope'=>$scope,'held'=>[]];
 $core['planSha256']=lpm_digest($core);return ['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0]+$core;}
function parentFixture(){
 $counts=['D1_OVERLAP_HELD'=>70,'HISTORICAL_366_HELD'=>366,'PLAN_BOUND_DEFERRED'=>11142,'PRIOR_OR_EDITORIAL_HELD'=>714,
 'RETAINED_DELTA_PREPARED'=>71,'SCREENED_FIELDS_PRESENT'=>1707,'SOURCE_MISSING'=>1920,'SOURCE_PROVENANCE_HELD'=>9];
 $rows=[];$states=[['RETAINED_DELTA_PREPARED',71],['SOURCE_MISSING',1920],['SOURCE_PROVENANCE_HELD',9]];
 $i=0;foreach($states as [$state,$n])for($j=0;$j<$n;$j++){++$i;$rows[]=['anytourHotelId'=>300000+$i,'localHotelId'=>400000+$i,'state'=>$state];}
 $private=['schema_version'=>1,'operation_id'=>LPM2_PARENT_OPERATION,'batch'=>LPM2_PARENT_BATCH,'source_sha'=>LPM2_PARENT_SOURCE,
 'control_source_sha'=>LPM2_PARENT_CONTROL,'safe_to_apply'=>false,'active_profiles'=>15999,'source_plans_prepared'=>2000,
 'profiles_with_delta'=>71,'planned_fields'=>735,'classification_counts'=>$counts,'rows'=>$rows];
 $public=$private;$public['state']='completed_read_only';$public['private_plan_sha256']=LPM2_PARENT_SHA;
 foreach(['provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes']as $k)$public[$k]=0;
 $outer=['status'=>'complete','mode'=>'local-profile-plan-4191','operation_id'=>LPM2_PARENT_OPERATION,'source_sha'=>LPM2_PARENT_SOURCE,
 'supplier_calls'=>0,'database_writes'=>0,'local_profile_plan'=>$public];
 $apply=['schema_version'=>1,'state'=>'committed_verified','operation_id'=>LPM2_PARENT_APPLY,'private_plan_sha256'=>LPM2_PARENT_SHA,
 'requested_profiles'=>71,'profiles_verified'=>71,'fields_verified'=>735,'batches_verified'=>4,'profile_writes'=>71,'provenance_writes'=>71,
 'readback_verified'=>true,'unknown_batch'=>null,'supplier_calls'=>0,'provider_http_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'schema_writes'=>0];
 return [$private,$public,$outer,$apply];
}
'''
    def php(self,body):
        run=subprocess.run(['php','-r','require $argv[1];'+self.BASE+body,str(ROOT/'scripts/diagnostics/local_profile_mass_plan2_4191.php')],
                           capture_output=True,text=True,timeout=30)
        self.assertEqual(0,run.returncode,run.stderr+run.stdout)
    def test_parent_exact2000_and_terminal_apply_required(self):
        self.php("""[$a,$b,$c,$d]=parentFixture();$p=lpm2_parent_values($a,$b,$c,$d);ok($p['state']==='verified_terminal_predecessor');
        ok(count($p['ownIds'])===2000&&count($p['localIds'])===2000);
        $d['readback_verified']=false;try{lpm2_parent_values($a,$b,$c,$d);throw new LogicException('accepted');}catch(RuntimeException $e){ok($e->getMessage()==='parent_apply');}""")
    def test_predecessor_own_and_local_are_excluded_before_owner(self):
        self.php("""[$a,$b,$c,$d]=parentFixture();$p=lpm2_parent_values($a,$b,$c,$d);$s=snap(2);
        $s['rows'][100001]['anytourHotelId']=$p['ownIds'][0];$s['rows'][$p['ownIds'][0]]=$s['rows'][100001];unset($s['rows'][100001]);$s['activeProfiles']=2;
        $s['rows'][100002]['localHotelId']=$p['localIds'][1];$calls=0;$x=lpm2_prepare($s,d1(),$p,function($q)use(&$calls){++$calls;return goodPlan($q);},fn($i,$q)=>[]);
        ok($calls===0);ok(($x['classification_counts']['PREDECESSOR_2000_HELD']??0)===2);""")
    def test_unknown_predecessor_blocks_all_candidates(self):
        self.php("""$calls=0;$x=lpm2_prepare(snap(501),d1(),['state'=>'unknown_held','ownIds'=>[],'localIds'=>[]],
        function()use(&$calls){++$calls;return [];},fn($i,$q)=>[]);ok($calls===0);ok($x['source_plans_prepared']===0);
        ok(($x['classification_counts']['PREDECESSOR_UNKNOWN_HELD']??0)===501);""")
    def test_next501_form_250_250_1_after_verified_predecessor(self):
        self.php("""$sizes=[];$p=['state'=>'verified_terminal_predecessor','ownIds'=>[],'localIds'=>[]];
        $x=lpm2_prepare(snap(501),d1(),$p,function($q)use(&$sizes){$sizes[]=count($q);return goodPlan($q);},
        fn($i,$q)=>['profiles'=>count($q['selected'])]);ok($sizes===[250,250,1]);ok($x['profiles_with_delta']===501);ok(count($x['batches'])===3);""")
    def test_d1_still_precedes_retained_planning(self):
        self.php("""$d=d1();$d['ownIds']=[100001];$calls=[];$x=lpm2_prepare(snap(2),$d,['state'=>'verified_terminal_predecessor','ownIds'=>[],'localIds'=>[]],
        function($q)use(&$calls){$calls[]=$q;return goodPlan($q);},fn($i,$q)=>[]);
        ok(($x['classification_counts']['D1_OVERLAP_HELD']??0)===1);ok(count($calls)===1&&count($calls[0])===1);""")
    def test_no_delta_and_source_missing_remain_distinct(self):
        self.php("""foreach(['none','source']as $kind){$x=lpm2_prepare(snap(1),d1(),['state'=>'verified_terminal_predecessor','ownIds'=>[],'localIds'=>[]],
        function($q)use($kind){$p=goodPlan($q);$p['selected']=[];if($kind==='source')$p['held'][100001]=['description'=>'source_missing_preserved'];
        $core=$p;unset($core['status'],$core['writes'],$core['supplierCalls'],$core['planSha256']);$p['planSha256']=lpm_digest($core);return $p;},fn($i,$q)=>[]);
        $state=$kind==='none'?'NO_DELTA_NOT_PROVEN_COMPLETE':'SOURCE_MISSING';ok(($x['classification_counts'][$state]??0)===1);}""")
    def test_phase2_source_has_no_http_or_write_path(self):
        source=(ROOT/'scripts/diagnostics/local_profile_mass_plan2_4191.php').read_text()
        self.assertNotIn('->apply(',source)
        for bad in ('curl_','INSERT ','UPDATE ','DELETE ','ALTER ','CREATE TABLE'):self.assertNotIn(bad,source)
        self.assertIn('SET SESSION TRANSACTION READ ONLY',source)
        self.assertIn("lpm2_parent($home)",source)

class HandlerTest(unittest.TestCase):
    def fixture(self,tmp):
        root=Path(tmp);stage=root/'source';runner=stage/control.MASS2_RUNNER;runner.parent.mkdir(parents=True);runner.write_text('<?php')
        data={'schema_version':1,'state':'completed_read_only','operation_id':control.MASS2_OPERATION,'source_sha':SHA,'control_source_sha':CONTROL,
          'batch':control.MASS2_BATCH,'demand_through':'2026-10-02 00:00:00','predecessor_private_plan_sha256':control.MASS_PARENT_SHA,
          'active_profiles':15999,'census_complete':True,'core_fields_present':4866,'missing_field_counts':{'description':11000},
          'eligible_profiles':1000,'source_plans_prepared':1000,'profiles_with_delta':20,'planned_fields':200,
          'classification_counts':{'PREDECESSOR_2000_HELD':2000,'RETAINED_DELTA_PREPARED':20,'SOURCE_MISSING':980,'SCREENED_FIELDS_PRESENT':12999},
          'safe_to_apply':False,'predecessor_exclusion_state':'verified_terminal_predecessor','predecessor_excluded_profiles':2000,
          'd1_exclusion_state':'verified_terminal_manifest','history_exclusion_state':'verified','ready_batches':1,'private_plan_sha256':'',
          'provider_http_calls':0,'database_writes':0,'profile_writes':0,'mapping_writes':0,'schema_writes':0}
        private=dict(data,batches=[]);batch=root/'mass2-batch-001.json';batch.write_text('{}')
        private['batches']=[{'file':batch.name,'sha256':hashlib.sha256(batch.read_bytes()).hexdigest(),'profiles':20,'scope_profiles':250,'plan_sha256':'d'*64}]
        p=root/'local-mass2-plan.json';p.write_text(json.dumps(private));data['private_plan_sha256']=hashlib.sha256(p.read_bytes()).hexdigest()
        (root/'local-mass2-receipt.json').write_text(json.dumps(data))
        process=types.SimpleNamespace(returncode=0,stdout='',stderr='');calls=[]
        ns={'operation':control.MASS2_OPERATION,'source':SHA,'project':root,'op':root,'os':os,'re':re,'hashlib':hashlib,
          'payload':{'batch':control.MASS2_BATCH,'maximum_writes':0,'provider_http_calls':0,'local_profile_control_sha':CONTROL},
          'safe_file':lambda p,n:p.is_file() and p.stat().st_size<=n,'safe_json':lambda p,n:json.loads(p.read_text()),
          'fail':lambda r:(_ for _ in ()).throw(RuntimeError(r)),'subprocess':types.SimpleNamespace(run=lambda *a,**k:calls.append(1) or process)}
        exec(control.REMOTE_MASS2_HANDLER,ns);return root,stage,data,process,calls,ns
    def test_sanitized_receipt_and_batch_digest(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,p,calls,ns=self.fixture(tmp);self.assertEqual(data,ns['run_local_profile_mass2_plan_4191'](stage));self.assertEqual(1,len(calls))
    def test_false_predecessor_or_write_authority_rejected(self):
        for change in [{'predecessor_excluded_profiles':1999},{'predecessor_exclusion_state':'unknown_held'},{'database_writes':1},{'safe_to_apply':True}]:
            with self.subTest(change=change),tempfile.TemporaryDirectory() as tmp:
                root,stage,data,p,calls,ns=self.fixture(tmp);data.update(change);(root/'local-mass2-receipt.json').write_text(json.dumps(data))
                with self.assertRaises(RuntimeError):ns['run_local_profile_mass2_plan_4191'](stage)

if __name__=='__main__':unittest.main()
