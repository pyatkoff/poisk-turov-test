#!/usr/bin/env python3
from __future__ import annotations
import ast,hashlib,importlib.util,json,os,re,shutil,subprocess,tempfile,types,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def load(name,path):
    s=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
control=load('plan3_control','scripts/deploy/int_server_executor_local_profile_plan.py')
SHA='a'*40;CONTROL='b'*40

class ControlTest(unittest.TestCase):
    def core(self):
        c=types.SimpleNamespace(PREFIX='/run-int-server-v1 ',SHA_RE=re.compile(r'[a-f0-9]{40}\Z'),
            parse_command=lambda b:(_ for _ in ()).throw(ValueError('old')),
            REMOTE="def run_match942(stage, mode, offset, limit):\n    pass\n    if mode=='match-tv942-write':\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if not isinstance(files,dict) or len(files)<20: fail('manifest')\n")
        control.register_parser(c);return c
    def test_exact_phase3_pair_zero_authority(self):
        c=self.core();cmd=c.parse_command(f'{c.PREFIX}{SHA} {control.MODE} {control.MASS3_OPERATION} {control.MASS3_BATCH}')
        self.assertEqual(0,cmd['maximum_writes']);self.assertEqual(0,cmd['provider_http_calls'])
        for op,batch in [(control.MASS3_OPERATION,control.MASS2_BATCH),(control.MASS2_OPERATION,control.MASS3_BATCH),
                         (control.MASS3_OPERATION.replace('-v1','-v2'),control.MASS3_BATCH)]:
            with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{SHA} {control.MODE} {op} {batch}')
    def test_no_ids_limits_digest_or_apply(self):
        c=self.core();cmd=f'{c.PREFIX}{SHA} {control.MODE} {control.MASS3_OPERATION} {control.MASS3_BATCH}'
        for bad in [cmd+' 2000',cmd+' --ids=1',cmd+' '+('0'*64),cmd.replace(control.MODE,'local-profile-apply-4191')]:
            with self.assertRaises(ValueError):c.parse_command(bad)
    def test_same_registration_readonly_handler_and_no_supplier(self):
        c=self.core();cmd=c.parse_command(f'{c.PREFIX}{SHA} {control.MODE} {control.MASS3_OPERATION} {control.MASS3_BATCH}')
        control.activate(c,cmd);ast.parse(c.REMOTE)
        self.assertIn('run_local_profile_mass3_plan_4191',c.REMOTE)
        self.assertIn("'allow_url_fopen=0'",control.REMOTE_MASS3_HANDLER)
        self.assertNotIn('--execute',control.REMOTE_MASS3_HANDLER);self.assertNotIn('ANEX_API_TOKEN',control.REMOTE_MASS3_HANDLER)

@unittest.skipUnless(shutil.which('php'),'PHP required')
class PhpTest(unittest.TestCase):
    BASE=r'''
function ok($v){if(!$v)throw new LogicException('assertion');}
function rawRow($own,$local){$profile=['description'=>'','primaryImage'=>'x','images'=>['x'],'address'=>'A','place'=>'P',
 'build'=>'2000','repair'=>'2020','square'=>'100','hotelInformation'=>['infrastructure'=>['x'],'services'=>['x'],'meals'=>['x'],'roomTypes'=>['x']]];
 $raw=lpp_json($profile);$proof=lpp_json(['schema_version'=>1,'accepted_local_hotel_id'=>$local,'canonical_hotel_id'=>$own,
 'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('a',64)]);
 return ['id'=>$own,'revision'=>1,'profile_json'=>$raw,'profile_sha256'=>hash('sha256',$raw),'alias_count'=>1,'local_id'=>$local,
 'alias_acquired_via'=>'canonical_local_alias_v1','alias_source_json'=>$proof,'alias_source_sha256'=>hash('sha256',$proof),
 'prior_content_operations'=>0];}
function snap($n){$rows=[];for($i=1;$i<=$n;$i++)$rows[700000+$i]=lpm_describe(rawRow(700000+$i,800000+$i));
 return ['activeProfiles'=>$n,'rows'=>$rows,'history'=>['state'=>'verified','localIds'=>[]]];}
function d1(){return ['state'=>'verified_terminal_manifest','ownIds'=>[],'legacyIds'=>[]];}
function p1(){return ['state'=>'verified_terminal_predecessor','ownIds'=>[],'localIds'=>[]];}
function p2(){return ['state'=>'verified_terminal_predecessor2','ownIds'=>[],'localIds'=>[]];}
function goodPlan($scope){$sel=[];foreach($scope as $s){$r=rawRow($s['anytourHotelId'],$s['localHotelId']);$sel[]=[
 'anytourHotelId'=>$s['anytourHotelId'],'localHotelId'=>$s['localHotelId'],'expectedRevision'=>1,
 'expectedProfileSha256'=>$r['profile_sha256'],'expectedAliasSha256'=>$r['alias_source_sha256'],
 'beforeProfileJson'=>$r['profile_json'],'patch'=>['description'=>'saved']];}
 $core=['schemaVersion'=>1,'contentPolicy'=>'sync_imported_retained_tv_v1','demandThrough'=>'2026-10-02 00:00:00',
 'limit'=>count($scope),'activeProfiles'=>count($scope),'scannedProfiles'=>count($scope),'selected'=>$sel,'contentScope'=>$scope,'held'=>[]];
 $core['planSha256']=lpm_digest($core);return ['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0]+$core;}
function parent2Fixture(){
 $counts=['D1_OVERLAP_HELD'=>70,'HISTORICAL_366_HELD'=>366,'PLAN_BOUND_DEFERRED'=>9142,'PREDECESSOR_2000_HELD'=>2000,
 'PRIOR_OR_EDITORIAL_HELD'=>714,'RETAINED_DELTA_PREPARED'=>130,'SCREENED_FIELDS_PRESENT'=>1707,'SOURCE_MISSING'=>1868,'SOURCE_PROVENANCE_HELD'=>2];
 $rows=[];$i=0;foreach([['RETAINED_DELTA_PREPARED',130],['SOURCE_MISSING',1868],['SOURCE_PROVENANCE_HELD',2]]as [$state,$n])
  for($j=0;$j<$n;$j++){++$i;$rows[]=['anytourHotelId'=>900000+$i,'localHotelId'=>1000000+$i,'state'=>$state];}
 $private=['schema_version'=>1,'operation_id'=>LPM3_PARENT2_OPERATION,'batch'=>LPM3_PARENT2_BATCH,'source_sha'=>LPM3_PARENT2_SOURCE,
 'control_source_sha'=>LPM3_PARENT2_CONTROL,'predecessor_private_plan_sha256'=>LPM2_PARENT_SHA,'safe_to_apply'=>false,
 'active_profiles'=>15999,'source_plans_prepared'=>2000,'profiles_with_delta'=>130,'planned_fields'=>1312,
 'classification_counts'=>$counts,'rows'=>$rows];
 $public=$private;$public['state']='completed_read_only';$public['private_plan_sha256']=LPM3_PARENT2_SHA;
 $public['predecessor_exclusion_state']='verified_terminal_predecessor';$public['predecessor_excluded_profiles']=2000;
 foreach(['provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes']as $k)$public[$k]=0;
 $outer=['status'=>'complete','mode'=>'local-profile-plan-4191','operation_id'=>LPM3_PARENT2_OPERATION,'source_sha'=>LPM3_PARENT2_SOURCE,
 'supplier_calls'=>0,'database_writes'=>0,'local_profile_plan'=>$public];
 $apply=['schema_version'=>1,'state'=>'committed_verified','operation_id'=>LPM3_PARENT2_APPLY,'private_plan_sha256'=>LPM3_PARENT2_SHA,
 'requested_profiles'=>130,'profiles_verified'=>130,'fields_verified'=>1312,'batches_verified'=>3,'profile_writes'=>130,'provenance_writes'=>130,
 'readback_verified'=>true,'unknown_batch'=>null,'supplier_calls'=>0,'provider_http_calls'=>0,'mapping_writes'=>0,'legacy_writes'=>0,'schema_writes'=>0];
 return [$private,$public,$outer,$apply];
}
'''
    def php(self,body):
        run=subprocess.run(['php','-r','require $argv[1];'+self.BASE+body,
                            str(ROOT/'scripts/diagnostics/local_profile_mass_plan3_4191.php')],
                           capture_output=True,text=True,timeout=30)
        self.assertEqual(0,run.returncode,run.stderr+run.stdout)
    def test_parent2_requires_terminal_apply_and_derives_exact2000(self):
        self.php("""[$a,$b,$c,$d]=parent2Fixture();$p=lpm3_parent2_values($a,$b,$c,$d);
        ok($p['state']==='verified_terminal_predecessor2');ok(count($p['ownIds'])===2000&&count($p['localIds'])===2000);
        $d['readback_verified']=false;try{lpm3_parent2_values($a,$b,$c,$d);throw new LogicException('accepted');}
        catch(RuntimeException $e){ok($e->getMessage()==='parent2_apply');}""")
    def test_both_predecessor_namespaces_excluded_before_owner(self):
        self.php("""$s=snap(2);$p1=['state'=>'verified_terminal_predecessor','ownIds'=>[700001],'localIds'=>[]];
        $p2=['state'=>'verified_terminal_predecessor2','ownIds'=>[],'localIds'=>[800002]];$calls=0;
        $x=lpm3_prepare($s,d1(),$p1,$p2,function()use(&$calls){++$calls;return [];},fn($i,$p)=>[]);
        ok($calls===0);ok(($x['classification_counts']['PREDECESSOR_2000_HELD']??0)===1);
        ok(($x['classification_counts']['PREDECESSOR2_2000_HELD']??0)===1);""")
    def test_unknown_second_predecessor_blocks_all_owner_calls(self):
        self.php("""$calls=0;$x=lpm3_prepare(snap(501),d1(),p1(),['state'=>'unknown_held','ownIds'=>[],'localIds'=>[]],
        function()use(&$calls){++$calls;return [];},fn($i,$p)=>[]);
        ok($calls===0);ok($x['source_plans_prepared']===0);ok(($x['classification_counts']['PREDECESSOR2_UNKNOWN_HELD']??0)===501);""")
    def test_next501_still_batches250_250_1(self):
        self.php("""$sizes=[];$x=lpm3_prepare(snap(501),d1(),p1(),p2(),function($s)use(&$sizes){$sizes[]=count($s);return goodPlan($s);},
        fn($i,$p)=>['profiles'=>count($p['selected'])]);ok($sizes===[250,250,1]);ok($x['profiles_with_delta']===501);ok(count($x['batches'])===3);""")
    def test_d1_still_excludes_before_owner(self):
        self.php("""$d=d1();$d['ownIds']=[700001];$calls=[];$x=lpm3_prepare(snap(2),$d,p1(),p2(),
        function($s)use(&$calls){$calls[]=$s;return goodPlan($s);},fn($i,$p)=>[]);
        ok(($x['classification_counts']['D1_OVERLAP_HELD']??0)===1);ok(count($calls)===1&&count($calls[0])===1);""")
    def test_phase3_runner_readonly(self):
        src=(ROOT/'scripts/diagnostics/local_profile_mass_plan3_4191.php').read_text()
        self.assertNotIn('->apply(',src)
        for bad in ('curl_','INSERT ','UPDATE ','DELETE ','ALTER ','CREATE TABLE'):self.assertNotIn(bad,src)
        self.assertIn('SET SESSION TRANSACTION READ ONLY',src)
        self.assertIn("require_once __DIR__.'/local_profile_mass_plan2_4191.php';",src)
        self.assertNotIn('local_profile_mass_apply2_4191.php',src)

class HandlerTest(unittest.TestCase):
    def fixture(self,tmp):
        root=Path(tmp);stage=root/'source';runner=stage/control.MASS3_RUNNER;runner.parent.mkdir(parents=True);runner.write_text('<?php')
        counts={'PREDECESSOR_2000_HELD':2000,'PREDECESSOR2_2000_HELD':2000,'RETAINED_DELTA_PREPARED':20,
                'SOURCE_MISSING':980,'SCREENED_FIELDS_PRESENT':10999}
        data={'schema_version':1,'state':'completed_read_only','operation_id':control.MASS3_OPERATION,'source_sha':SHA,
          'control_source_sha':CONTROL,'batch':control.MASS3_BATCH,'demand_through':'2026-10-02 00:00:00',
          'predecessor_private_plan_sha256':control.MASS_PARENT_SHA,'predecessor2_private_plan_sha256':control.MASS2_PARENT_SHA,
          'active_profiles':15999,'census_complete':True,'core_fields_present':5000,'missing_field_counts':{'description':10000},
          'eligible_profiles':1000,'source_plans_prepared':1000,'profiles_with_delta':20,'planned_fields':200,
          'classification_counts':counts,'safe_to_apply':False,'predecessor_exclusion_state':'verified_terminal_predecessor',
          'predecessor_excluded_profiles':2000,'predecessor2_exclusion_state':'verified_terminal_predecessor2',
          'predecessor2_excluded_profiles':2000,'d1_exclusion_state':'verified_terminal_manifest','history_exclusion_state':'verified',
          'ready_batches':1,'private_plan_sha256':'','provider_http_calls':0,'database_writes':0,'profile_writes':0,'mapping_writes':0,'schema_writes':0}
        private=dict(data,batches=[]);batch=root/'mass3-batch-001.json';batch.write_text('{}')
        private['batches']=[{'file':batch.name,'sha256':hashlib.sha256(batch.read_bytes()).hexdigest(),'profiles':20,'scope_profiles':250,'plan_sha256':'d'*64}]
        p=root/'local-mass3-plan.json';p.write_text(json.dumps(private));data['private_plan_sha256']=hashlib.sha256(p.read_bytes()).hexdigest()
        (root/'local-mass3-receipt.json').write_text(json.dumps(data))
        process=types.SimpleNamespace(returncode=0,stdout='',stderr='');calls=[]
        ns={'operation':control.MASS3_OPERATION,'source':SHA,'project':root,'op':root,'os':os,'re':re,'hashlib':hashlib,
          'payload':{'batch':control.MASS3_BATCH,'maximum_writes':0,'provider_http_calls':0,'local_profile_control_sha':CONTROL},
          'safe_file':lambda p,n:p.is_file() and p.stat().st_size<=n,'safe_json':lambda p,n:json.loads(p.read_text()),
          'fail':lambda r:(_ for _ in ()).throw(RuntimeError(r)),'subprocess':types.SimpleNamespace(run=lambda *a,**k:calls.append(1) or process)}
        exec(control.REMOTE_MASS3_HANDLER,ns);return root,stage,data,calls,ns
    def test_sanitized_receipt_and_exact_dual_predecessors(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,calls,ns=self.fixture(tmp);self.assertEqual(data,ns['run_local_profile_mass3_plan_4191'](stage));self.assertEqual(1,len(calls))
    def test_false_predecessor_count_or_write_authority_rejected(self):
        for change in [{'predecessor2_excluded_profiles':1999},{'predecessor_exclusion_state':'unknown_held'},
                       {'database_writes':1},{'safe_to_apply':True}]:
            with self.subTest(change=change),tempfile.TemporaryDirectory() as tmp:
                root,stage,data,calls,ns=self.fixture(tmp);data.update(change);(root/'local-mass3-receipt.json').write_text(json.dumps(data))
                with self.assertRaises(RuntimeError):ns['run_local_profile_mass3_plan_4191'](stage)

if __name__=='__main__':unittest.main()
