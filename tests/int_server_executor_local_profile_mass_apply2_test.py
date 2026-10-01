#!/usr/bin/env python3
from __future__ import annotations
import ast,hashlib,importlib.util,io,json,os,re,shutil,subprocess,tarfile,tempfile,types,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def load(name,path):
    s=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
m=load('mass2_apply_control','scripts/deploy/int_server_executor_local_profile_apply.py')
SHA='a'*40;CONTROL='b'*40
FIELDS=['address','build','description','hotelInformation.infrastructure','hotelInformation.meals',
        'hotelInformation.roomTypes','hotelInformation.services','images','place','primaryImage','repair','square']

class ControlTest(unittest.TestCase):
    def core(self):
        c=types.SimpleNamespace(PREFIX='/run-int-server-v1 ',SHA_RE=re.compile(r'[a-f0-9]{40}\Z'),
          parse_command=lambda b:(_ for _ in ()).throw(ValueError('old')),
          REMOTE="def run_match942(stage, mode, offset, limit):\n    pass\n    if mode=='match-tv942-write':\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if not isinstance(files,dict) or len(files)<20: fail('manifest')\n")
        m.register_parser(c);return c
    def test_exact_three_pairs_and_phase2_limit(self):
        c=self.core()
        pairs=[(m.OPERATION,m.BATCH,36),(m.MASS_OPERATION,m.MASS_BATCH,71),(m.MASS2_OPERATION,m.MASS2_BATCH,130)]
        for op,batch,n in pairs:
            cmd=c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {op} {batch}')
            self.assertEqual(n,cmd['maximum_profile_writes']);self.assertEqual(0,cmd['provider_http_calls'])
        for op,batch in [(m.MASS2_OPERATION,m.MASS_BATCH),(m.MASS_OPERATION,m.MASS2_BATCH),
                         (m.MASS2_OPERATION.replace('-v1','-v2'),m.MASS2_BATCH)]:
            with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {op} {batch}')
    def test_phase2_rejects_ids_counts_and_digest_args(self):
        c=self.core();cmd=f'{c.PREFIX}{SHA} {m.MODE} {m.MASS2_OPERATION} {m.MASS2_BATCH}'
        for bad in [cmd+' 130',cmd+' --ids=1',cmd+' '+m.MASS2_PLAN_SHA,cmd.replace(SHA,'z'*40)]:
            with self.assertRaises(ValueError):c.parse_command(bad)
        value=c.parse_command(cmd);value['maximum_profile_writes']=131
        with self.assertRaises(ValueError):m.activate(c,value)
    def test_same_registration_excludes_generic_collector_and_old_modes_survive(self):
        c=self.core();cmd=c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.MASS2_OPERATION} {m.MASS2_BATCH}')
        m.activate(c,cmd);ast.parse(c.REMOTE)
        self.assertIn('run_local_profile_mass_apply130_phase2',c.REMOTE)
        self.assertIn('run_local_profile_mass_apply71',c.REMOTE)
        self.assertIn('run_local_profile_apply_4191',c.REMOTE)
        self.assertEqual(2,c.REMOTE.count("if mode not in ('local-profile-apply-4191','reconcile',"))
    def test_bundle_is_existing_owners_plus_checked_control_files(self):
        original=m.__file__
        try:
            with tempfile.TemporaryDirectory() as tmp:
                control=Path(tmp)/'control';source=Path(tmp)/'source'
                m.__file__=str(control/'scripts/deploy/int_server_executor_local_profile_apply.py')
                for rel in m.CONTROL_FILES:
                    p=control/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('control '+rel)
                for rel in m.SOURCE_FILES:
                    p=source/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('source '+rel)
                data,hashes=m.bundle_source(source)
                self.assertEqual(set(m.CONTROL_FILES)|set(m.SOURCE_FILES),set(hashes))
                with tarfile.open(fileobj=io.BytesIO(data),mode='r:gz') as z:
                    self.assertEqual(set(hashes)|{'manifest.json'},set(z.getnames()))
                    for rel,digest in hashes.items():
                        self.assertEqual(digest,hashlib.sha256(z.extractfile(rel).read()).hexdigest())
                        self.assertEqual(0o600,z.getmember(rel).mode)
        finally:m.__file__=original
    def test_phase2_runner_has_no_supplier_or_direct_mutating_sql(self):
        src=(ROOT/m.MASS2_RUNNER).read_text()
        self.assertIn("$owner->apply(LPMA2_OPERATION",src)
        self.assertIn("mass2-apply130-consumed.json",src)
        self.assertIn(m.MASS2_PLAN_SHA,src)
        for bad in ('curl_','v2_data_tv_get','INSERT ','UPDATE ','DELETE ','ALTER ','CREATE TABLE'):
            self.assertNotIn(bad,src)
        self.assertIn("'allow_url_fopen=0'",m.MASS2_HANDLER)
        self.assertIn('socket_connect',m.MASS2_HANDLER)

@unittest.skipUnless(shutil.which('php'),'PHP required')
class PhpTest(unittest.TestCase):
    BASE=r'''
function ok($v){if(!$v)throw new LogicException('assertion');}
function rawRow($own,$local){
 $profile=['description'=>'','primaryImage'=>'','images'=>[],'address'=>'','place'=>'','build'=>'','repair'=>'','square'=>'',
  'hotelInformation'=>['infrastructure'=>[],'services'=>[],'meals'=>[],'roomTypes'=>[]]];
 $raw=lpp_json($profile);$proof=lpp_json(['schema_version'=>1,'accepted_local_hotel_id'=>$local,'canonical_hotel_id'=>$own,
  'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('a',64)]);
 return ['anytourHotelId'=>$own,'localHotelId'=>$local,'state'=>'RETAINED_DELTA_PREPARED','expectedRevision'=>1,
  'expectedProfileSha256'=>hash('sha256',$raw),'expectedAliasSha256'=>hash('sha256',$proof),'before'=>$raw,'proof'=>$proof,
  'missingFields'=>LPP_FIELDS];
}
function ident(){return ['schema_version'=>1,'operation_id'=>LPMA2_PLAN_OPERATION,'batch'=>LPMA2_PLAN_BATCH,
 'source_sha'=>LPMA2_PLAN_SOURCE,'control_source_sha'=>LPMA2_PLAN_CONTROL,'demand_through'=>'2026-10-01 23:19:31',
 'predecessor_private_plan_sha256'=>LPM2_PARENT_SHA,'safe_to_apply'=>false];}
function predecessor(){
 $own=[];$local=[];for($i=1;$i<=2000;$i++){$own[]=300000+$i;$local[]=400000+$i;}
 return ['state'=>'verified_terminal_predecessor','ownIds'=>$own,'localIds'=>$local];
}
function fixture(){
 $counts=['D1_OVERLAP_HELD'=>70,'HISTORICAL_366_HELD'=>366,'PLAN_BOUND_DEFERRED'=>9142,'PREDECESSOR_2000_HELD'=>2000,
 'PRIOR_OR_EDITORIAL_HELD'=>714,'RETAINED_DELTA_PREPARED'=>130,'SCREENED_FIELDS_PRESENT'=>1707,'SOURCE_MISSING'=>1868,
 'SOURCE_PROVENANCE_HELD'=>2];
 $rows=[];$files=[];$meta=[];$counter=0;$fields=LPP_FIELDS;
 foreach([44,43,43] as $bi=>$n){$scope=[];$selected=[];
  for($j=0;$j<$n;$j++){++$counter;$own=500000+$counter;$local=600000+$counter;$r=rawRow($own,$local);$rows[]=$r;
   $scope[]=['anytourHotelId'=>$own,'localHotelId'=>$local,'fields'=>$r['missingFields']];
   $patchFields=array_slice($fields,0,$counter<=12?11:10);$patch=[];foreach($patchFields as $field)$patch[$field]='saved-'.$field;
   $selected[]=['anytourHotelId'=>$own,'localHotelId'=>$local,'expectedRevision'=>1,
    'expectedProfileSha256'=>$r['expectedProfileSha256'],'expectedAliasSha256'=>$r['expectedAliasSha256'],
    'beforeProfileJson'=>$r['before'],'patch'=>$patch];
  }
  $plan=['schemaVersion'=>1,'contentPolicy'=>'sync_imported_retained_tv_v1','demandThrough'=>'2026-10-01 23:19:31',
   'limit'=>$n,'activeProfiles'=>$n,'scannedProfiles'=>$n,'selected'=>$selected,'contentScope'=>$scope,'held'=>[]];
  $plan['planSha256']=lpm_digest($plan);$plan=['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0]+$plan;
  $file=sprintf('mass2-batch-%03d.json',$bi+1);$files[$file]=lpp_json(ident()+['owner_plan'=>$plan]);
  $meta[]=['file'=>$file,'sha256'=>hash('sha256',$files[$file]),'profiles'=>$n,'scope_profiles'=>$n,'plan_sha256'=>$plan['planSha256']];
 }
 return [ident()+['active_profiles'=>15999,'source_plans_prepared'=>2000,'profiles_with_delta'=>130,'planned_fields'=>1312,
  'classification_counts'=>$counts,'rows'=>$rows,'batches'=>$meta],$files];
}
function batches(){[$index,$files]=fixture();return lpma2_batches($index,['own'=>[],'local'=>[]],predecessor(),fn($p)=>$files[$p]);}
function goodApply($p){$counts=[];foreach($p['selected'] as $s)foreach($s['patch'] as $field=>$v)$counts[$field]=($counts[$field]??0)+1;
 ksort($counts);return ['status'=>'committed_verified','operation'=>LPMA2_OPERATION,'planSha256'=>$p['planSha256'],
 'profilesUpdated'=>count($p['selected']),'profileWrites'=>count($p['selected']),'provenanceWrites'=>count($p['selected']),
 'fieldsFilled'=>array_sum($counts),'fieldCounts'=>$counts,'supplierCalls'=>0,'mappingWrites'=>0,'legacyWrites'=>0];}
'''
    def php(self,body):
        run=subprocess.run(['php','-r','require $argv[1];'+self.BASE+body,
                            str(ROOT/m.MASS2_RUNNER)],capture_output=True,text=True,timeout=30)
        self.assertEqual(0,run.returncode,run.stderr+run.stdout)
    def test_exact130_1312_three_batches_and_all_preflights_before_write(self):
        self.php("""$b=batches();ok(count($b)===3);$order='';$r=lpma2_execute($b,
        function($p)use(&$order){$order.='P';return $p;},function($p)use(&$order){$order.='A';return goodApply($p);},
        function()use(&$order){$order.='C';},function()use(&$order){$order.='K';});
        ok($order==='PPPCAKAKAK');ok($r['state']==='committed_verified');ok($r['profiles_verified']===130);
        ok($r['fields_verified']===1312);ok($r['batches_verified']===3);ok($r['profile_writes']===130);""")
    def test_current_drift_or_consume_failure_stops_before_any_apply(self):
        self.php("""foreach(['drift','consume'] as $kind){$calls=0;$r=lpma2_execute(batches(),
        function($p)use($kind){if($kind==='drift')$p['planSha256']=str_repeat('0',64);return $p;},
        function($p)use(&$calls){++$calls;return goodApply($p);},
        function()use($kind){if($kind==='consume')throw new RuntimeException('exists');},fn()=>null);
        ok($calls===0);ok($r['state']==='held_before_write');ok($r['profile_writes']===0);}""")
    def test_partial_or_checkpoint_failure_is_unknown_no_replay(self):
        self.php("""foreach(['apply','checkpoint'] as $kind){$calls=0;$checks=0;$r=lpma2_execute(batches(),fn($p)=>$p,
        function($p)use($kind,&$calls){++$calls;if($kind==='apply'&&$calls===2)throw new RuntimeException('unknown');return goodApply($p);},
        fn()=>null,function()use($kind,&$checks){++$checks;if($kind==='checkpoint')throw new RuntimeException('disk');});
        ok($r['state']==='unknown_no_replay');ok($r['profile_writes']==='unknown');ok($r['readback_verified']===false);
        ok($r['unknown_batch']===($kind==='apply'?2:1));}""")
    def test_predecessor_and_d1_namespaces_cannot_enter_batch(self):
        self.php("""foreach([['own'=>[500001=>true],'local'=>[]],['own'=>[],'local'=>[600001=>true]]] as $protected){
        [$i,$f]=fixture();try{lpma2_batches($i,$protected,predecessor(),fn($p)=>$f[$p]);throw new LogicException('accepted');}
        catch(RuntimeException $e){ok($e->getMessage()==='excluded_scope');}}
        [$i,$f]=fixture();$p=predecessor();$p['ownIds'][0]=500001;try{lpma2_batches($i,['own'=>[],'local'=>[]],$p,fn($x)=>$f[$x]);
        throw new LogicException('accepted');}catch(RuntimeException $e){ok($e->getMessage()==='excluded_scope');}""")
    def test_nonempty_before_image_revision_and_foreign_patch_rejected(self):
        self.php("""foreach(['nonempty','revision','field'] as $kind){[$i,$f]=fixture();$file=$i['batches'][0]['file'];$b=json_decode($f[$file],true);
        $p=$b['owner_plan'];if($kind==='nonempty'){$p['selected'][0]['beforeProfileJson']='{"description":"manual"}';
        $p['selected'][0]['expectedProfileSha256']=hash('sha256',$p['selected'][0]['beforeProfileJson']);$i['rows'][0]['expectedProfileSha256']=$p['selected'][0]['expectedProfileSha256'];}
        if($kind==='revision'){$p['selected'][0]['expectedRevision']=2;$i['rows'][0]['expectedRevision']=2;}
        if($kind==='field')$p['selected'][0]['patch']['name']='bad';$core=$p;unset($core['status'],$core['writes'],$core['supplierCalls'],$core['planSha256']);
        $p['planSha256']=lpm_digest($core);$b['owner_plan']=$p;$f[$file]=lpp_json($b);$i['batches'][0]['sha256']=hash('sha256',$f[$file]);
        $i['batches'][0]['plan_sha256']=$p['planSha256'];try{lpma2_batches($i,['own'=>[],'local'=>[]],predecessor(),fn($x)=>$f[$x]);
        throw new LogicException('accepted');}catch(RuntimeException $e){}}""")

class HandlerTest(unittest.TestCase):
    def fixture(self,tmp):
        root=Path(tmp);stage=root/'source';runner=stage/m.MASS2_RUNNER;runner.parent.mkdir(parents=True);runner.write_text('<?php')
        counts={f:100 for f in FIELDS};counts['address']=128;counts['build']=128;counts['description']=128;counts['hotelInformation.infrastructure']=128
        data={'schema_version':1,'operation_id':m.MASS2_OPERATION,'batch':m.MASS2_BATCH,'source_sha':SHA,
          'control_source_sha':CONTROL,'plan_source_sha':'a54255507643501abdeca150aeae19b84cb586f6',
          'private_plan_sha256':m.MASS2_PLAN_SHA,'requested_profiles':130,'state':'committed_verified',
          'profiles_verified':130,'fields_verified':1312,'field_counts':counts,'batches_verified':3,'profile_writes':130,
          'provenance_writes':130,'readback_verified':True,'unknown_batch':None,'sample_own_ids':[500001,500002,500003],'supplier_calls':0,'provider_http_calls':0,
          'mapping_writes':0,'legacy_writes':0,'schema_writes':0}
        (root/'local-mass2-apply-receipt.json').write_text(json.dumps(data))
        process=types.SimpleNamespace(returncode=0,stdout='',stderr='');calls=[]
        ns={'operation':m.MASS2_OPERATION,'source':SHA,'project':root,'op':root,'os':os,'re':re,
          'payload':{'batch':m.MASS2_BATCH,'maximum_profile_writes':130,'provider_http_calls':0,'local_profile_control_sha':CONTROL},
          'safe_file':lambda p,n:p.is_file() and p.stat().st_size<=n,'safe_json':lambda p,n:json.loads(p.read_text()),
          'fail':lambda r:(_ for _ in ()).throw(RuntimeError(r)),
          'subprocess':types.SimpleNamespace(run=lambda *a,**k:calls.append(1) or process)}
        exec(m.MASS2_HANDLER,ns);return root,stage,data,process,calls,ns
    def test_exact_complete_receipt(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,p,calls,ns=self.fixture(tmp);self.assertEqual(data,ns['run_local_profile_mass_apply130_phase2'](stage));self.assertEqual(1,len(calls))
    def test_false_complete_or_widened_authority_rejected(self):
        for change in [{'requested_profiles':131},{'profiles_verified':129},{'fields_verified':1311},{'batches_verified':2},
                       {'profile_writes':129},{'readback_verified':False},{'private_plan_sha256':'0'*64},
                       {'provider_http_calls':1},{'raw_profile':'private'}]:
            with self.subTest(change=change),tempfile.TemporaryDirectory() as tmp:
                root,stage,data,p,calls,ns=self.fixture(tmp);data.update(change);(root/'local-mass2-apply-receipt.json').write_text(json.dumps(data))
                with self.assertRaises(RuntimeError):ns['run_local_profile_mass_apply130_phase2'](stage)
    def test_hold_and_unknown_contracts(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,p,calls,ns=self.fixture(tmp);data.update(state='held_before_write',profiles_verified=0,fields_verified=0,
             field_counts=[],batches_verified=0,profile_writes=0,provenance_writes=0,readback_verified=False,sample_own_ids=[]);p.returncode=2
            (root/'local-mass2-apply-receipt.json').write_text(json.dumps(data));got=ns['run_local_profile_mass_apply130_phase2'](stage)
            self.assertEqual({},got['field_counts']);self.assertEqual(0,got['profile_writes'])
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,p,calls,ns=self.fixture(tmp);data.update(state='unknown_no_replay',profiles_verified=44,fields_verified=450,
             field_counts={'description':44,'primaryImage':44,'images':44,'address':44,'place':44,'build':44,'repair':44,
             'square':44,'hotelInformation.infrastructure':44,'hotelInformation.services':44,'hotelInformation.meals':10},
             batches_verified=1,profile_writes='unknown',provenance_writes='unknown',readback_verified=False,unknown_batch=2);p.returncode=2
            (root/'local-mass2-apply-receipt.json').write_text(json.dumps(data));got=ns['run_local_profile_mass_apply130_phase2'](stage)
            self.assertEqual('unknown',got['profile_writes']);self.assertEqual(2,got['unknown_batch'])
    def test_widened_payload_fails_before_php(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,p,calls,ns=self.fixture(tmp);ns['payload']['maximum_profile_writes']=131
            with self.assertRaisesRegex(RuntimeError,'scope'):ns['run_local_profile_mass_apply130_phase2'](stage)
            self.assertEqual([],calls)

if __name__=='__main__':unittest.main()
