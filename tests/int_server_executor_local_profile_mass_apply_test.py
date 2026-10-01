#!/usr/bin/env python3
"""Exact sealed71 tests; no network, production DB or supplier calls."""
from __future__ import annotations
import ast,hashlib,importlib.util,io,json,os,re,shutil,subprocess,tarfile,tempfile,types,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('mass_apply',ROOT/'scripts/deploy/int_server_executor_local_profile_apply.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
SHA='a'*40;CONTROL='b'*40
FIELDS=['description','primaryImage','images','address','place','build','repair','square',
        'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes']

class ControlTest(unittest.TestCase):
    def core(self):
        c=types.SimpleNamespace(PREFIX='/run-int-server-v1 ',SHA_RE=re.compile(r'[a-f0-9]{40}\Z'),
            parse_command=lambda b:(_ for _ in ()).throw(ValueError('unrecognized')),
            REMOTE="def run_match942(stage, mode, offset, limit):\n    pass\n    if mode=='match-tv942-write':\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if mode not in ('reconcile',):\n        pass\n    if not isinstance(files,dict) or len(files)<20: fail('manifest')\n")
        m.register_parser(c);return c

    def test_only_exact_new71_and_historical36_pairs(self):
        c=self.core()
        for operation,batch,count in [(m.OPERATION,m.BATCH,36),(m.MASS_OPERATION,m.MASS_BATCH,71)]:
            command=c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {operation} {batch}')
            self.assertEqual(count,command['maximum_profile_writes']);self.assertEqual(0,command['provider_http_calls'])
        for op,batch in [(m.OPERATION,m.MASS_BATCH),(m.MASS_OPERATION,m.BATCH),(m.MASS_OPERATION.replace('-v1','-v2'),m.MASS_BATCH)]:
            with self.assertRaises(ValueError):c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {op} {batch}')

    def test_rejects_arbitrary_ids_limits_plan_digest_and_payload_widening(self):
        c=self.core();cmd=f'{c.PREFIX}{SHA} {m.MODE} {m.MASS_OPERATION} {m.MASS_BATCH}'
        for bad in [cmd+' 72',cmd+' --ids=1',cmd+' '+m.MASS_PLAN_SHA,cmd.replace(SHA,'z'*40)]:
            with self.assertRaises(ValueError):c.parse_command(bad)
        parsed=c.parse_command(cmd);parsed['maximum_profile_writes']=72
        with self.assertRaises(ValueError):m.activate(c,parsed)

    def test_same_mode_preserves_old_dispatch_and_collector_exclusion(self):
        c=self.core();command=c.parse_command(f'{c.PREFIX}{SHA} {m.MODE} {m.MASS_OPERATION} {m.MASS_BATCH}')
        m.activate(c,command);ast.parse(c.REMOTE)
        self.assertIn("database_writes']=72",c.REMOTE)
        self.assertEqual(2,c.REMOTE.count("if mode not in ('local-profile-apply-4191','reconcile',"))
        self.assertIn("operation!='"+m.MASS_OPERATION+"'",c.REMOTE)
        self.assertIn("database_writes']=data['profile_writes']+data['provenance_writes']",c.REMOTE)

    def test_bundle_carries_only_checked_control_and_existing_owners(self):
        original=m.__file__
        try:
            with tempfile.TemporaryDirectory() as tmp:
                control=Path(tmp)/'control';source=Path(tmp)/'release'
                m.__file__=str(control/'scripts/deploy/registration.py')
                for p in m.CONTROL_FILES:
                    f=control/p;f.parent.mkdir(parents=True,exist_ok=True);f.write_text('control '+p)
                for p in m.SOURCE_FILES:
                    f=source/p;f.parent.mkdir(parents=True,exist_ok=True);f.write_text('source '+p)
                data,hashes=m.bundle_source(source)
                self.assertEqual(set(m.CONTROL_FILES)|set(m.SOURCE_FILES),set(hashes))
                with tarfile.open(fileobj=io.BytesIO(data),mode='r:gz') as z:
                    self.assertEqual(set(hashes)|{'manifest.json'},set(z.getnames()))
                    for p,digest in hashes.items():
                        self.assertEqual(digest,hashlib.sha256(z.extractfile(p).read()).hexdigest())
                        self.assertEqual(0o600,z.getmember(p).mode)
        finally:m.__file__=original

    def test_php_cannot_write_via_anything_except_existing_owner(self):
        text=(ROOT/m.MASS_RUNNER).read_text()
        self.assertIn("$owner->apply(LPMA_OPERATION",text)
        self.assertIn("mass-apply71-consumed.json",text)
        self.assertIn(m.MASS_PLAN_SHA,text)
        self.assertIn('lpma_protected($db,lpp_d1($home))',text)
        for bad in ('INSERT ','UPDATE ','DELETE ','ALTER ','CREATE TABLE','curl_','v2_data_tv_get','->commit('):
            # The sole explicit commit is the READ ONLY historical-alias transaction.
            if bad=='->commit(':self.assertEqual(1,text.count(bad));continue
            self.assertNotIn(bad,text)
        self.assertIn("'allow_url_fopen=0'",m.MASS_HANDLER)
        self.assertIn('socket_connect',m.MASS_HANDLER)

class ReceiptTest(unittest.TestCase):
    def fixture(self,tmp):
        root=Path(tmp);stage=root/'source';runner=stage/m.MASS_RUNNER
        runner.parent.mkdir(parents=True);runner.write_text('<?php')
        data={'schema_version':1,'operation_id':m.MASS_OPERATION,'batch':m.MASS_BATCH,'source_sha':SHA,
            'control_source_sha':CONTROL,'plan_source_sha':'5e6797373c61f5b1ad4cb365a18cf15d66526b99',
            'private_plan_sha256':m.MASS_PLAN_SHA,'requested_profiles':71,'state':'committed_verified',
            'profiles_verified':71,'fields_verified':735,'field_counts':{**{f:71 for f in FIELDS[:10]},FIELDS[10]:25},
            'batches_verified':4,'profile_writes':71,'provenance_writes':71,'readback_verified':True,
            'unknown_batch':None,'supplier_calls':0,'provider_http_calls':0,'mapping_writes':0,'legacy_writes':0,'schema_writes':0}
        calls=[];process=types.SimpleNamespace(returncode=0,stdout='',stderr='')
        ns={'operation':m.MASS_OPERATION,'source':SHA,'project':root,'op':root,'os':os,'re':re,
            'payload':{'batch':m.MASS_BATCH,'maximum_profile_writes':71,'provider_http_calls':0,'local_profile_control_sha':CONTROL},
            'safe_file':lambda p,n:p.is_file() and not p.is_symlink() and p.stat().st_size<=n,
            'safe_json':lambda p,n:json.loads(p.read_text()),
            'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason)),
            'subprocess':types.SimpleNamespace(run=lambda *a,**k:calls.append((a,k)) or process)}
        exec(m.MASS_HANDLER,ns)
        return root,stage,data,calls,process,ns
    def save(self,root,data):(root/'local-mass-apply-receipt.json').write_text(json.dumps(data))

    def test_exact_complete_requires71_and735_and_four_readbacks(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,calls,p,ns=self.fixture(tmp);self.save(root,data)
            self.assertEqual(data,ns['run_local_profile_mass_apply71'](stage));self.assertEqual(1,len(calls))

    def test_false_complete_scope_counts_private_leaks_and_authority_rejected(self):
        for change in [{'requested_profiles':72},{'profile_writes':72},{'profiles_verified':70},
                       {'batches_verified':3},{'readback_verified':False},{'private_plan_sha256':'c'*64},
                       {'provider_http_calls':True},{'raw_profile':'private'},{'unknown_batch':1},
                       {'field_counts':{'description':735}},{'field_counts':{'unknown':1}}]:
            with self.subTest(change=change),tempfile.TemporaryDirectory() as tmp:
                root,stage,data,calls,p,ns=self.fixture(tmp);data.update(change);self.save(root,data)
                with self.assertRaises(RuntimeError):ns['run_local_profile_mass_apply71'](stage)

    def test_unknown_preserves_known_readbacks_but_never_reports_zero_writes(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,calls,p,ns=self.fixture(tmp)
            data.update(state='unknown_no_replay',profiles_verified=25,fields_verified=275,
                field_counts={f:25 for f in FIELDS[:11]},batches_verified=1,profile_writes='unknown',
                provenance_writes='unknown',readback_verified=False,unknown_batch=2);p.returncode=2;self.save(root,data)
            self.assertEqual(data,ns['run_local_profile_mass_apply71'](stage));self.assertEqual(1,len(calls))
            data['profile_writes']=0;self.save(root,data)
            with self.assertRaisesRegex(RuntimeError,'unknown_contract'):ns['run_local_profile_mass_apply71'](stage)

    def test_prewrite_hold_is_zero_and_missing_terminal_is_not_retried(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,calls,p,ns=self.fixture(tmp)
            data.update(state='held_before_write',profiles_verified=0,fields_verified=0,field_counts=[],
                batches_verified=0,profile_writes=0,provenance_writes=0,readback_verified=False);p.returncode=2;self.save(root,data)
            got=ns['run_local_profile_mass_apply71'](stage);self.assertEqual({},got['field_counts']);self.assertEqual(0,got['profile_writes'])
            (root/'local-mass-apply-receipt.json').unlink()
            with self.assertRaisesRegex(RuntimeError,'missing_no_replay'):ns['run_local_profile_mass_apply71'](stage)
            self.assertEqual(2,len(calls))

    def test_widened_payload_fails_before_php(self):
        with tempfile.TemporaryDirectory() as tmp:
            root,stage,data,calls,p,ns=self.fixture(tmp);ns['payload']['maximum_profile_writes']=72
            with self.assertRaisesRegex(RuntimeError,'scope'):ns['run_local_profile_mass_apply71'](stage)
            self.assertEqual([],calls)

@unittest.skipUnless(shutil.which('php'),'PHP required')
class PhpTest(unittest.TestCase):
    FIXTURE=r'''
function ok($v){if(!$v)throw new LogicException('assertion');}
function identity(){return ['schema_version'=>1,'operation_id'=>LPM_OPERATION,'batch'=>LPM_BATCH,
 'source_sha'=>LPMA_PLAN_SOURCE,'control_source_sha'=>LPMA_PLAN_CONTROL,'demand_through'=>'2026-10-01 22:03:08','safe_to_apply'=>false];}
function fixture(){
 $rows=[];$files=[];$meta=[];$counter=0;$fields=LPP_FIELDS;sort($fields,SORT_STRING);
 foreach([25,25,20,1]as $bi=>$n){$scope=[];$selected=[];
  for($j=0;$j<$n;$j++){++$counter;$own=100000+$counter;$local=200000+$counter;$before='{}';
   $row=['anytourHotelId'=>$own,'localHotelId'=>$local,'state'=>'RETAINED_DELTA_PREPARED',
    'expectedRevision'=>1,'expectedProfileSha256'=>hash('sha256',$before),'expectedAliasSha256'=>hash('sha256',(string)$own),'missingFields'=>$fields];
   $rows[]=$row;$scope[]=['anytourHotelId'=>$own,'localHotelId'=>$local,'fields'=>$fields];
   $patch=array_fill_keys(array_slice($fields,0,$counter<=25?11:10),'saved');
   $selected[]=array_intersect_key($row,array_flip(['anytourHotelId','localHotelId','expectedRevision','expectedProfileSha256','expectedAliasSha256']))+
    ['beforeProfileJson'=>$before,'patch'=>$patch];
  }
  $p=['schemaVersion'=>1,'contentPolicy'=>'sync_imported_retained_tv_v1','demandThrough'=>'2026-10-01 22:03:08',
   'limit'=>$n,'activeProfiles'=>$n,'scannedProfiles'=>$n,'selected'=>$selected,'contentScope'=>$scope,'held'=>[]];
  $p['planSha256']=lpm_digest($p);$p=['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0]+$p;
  $file=sprintf('mass-batch-%03d.json',$bi+1);$files[$file]=lpp_json(identity()+['owner_plan'=>$p]);
  $meta[]=['file'=>$file,'sha256'=>hash('sha256',$files[$file]),'profiles'=>$n,'scope_profiles'=>$n,'plan_sha256'=>$p['planSha256']];
 }
 return [identity()+['profiles_with_delta'=>71,'planned_fields'=>735,'rows'=>$rows,'batches'=>$meta],$files];
}
function batches(){[$index,$files]=fixture();return lpma_batches($index,['own'=>[],'local'=>[]],fn($p)=>$files[$p]);}
function goodApply($p){$counts=[];foreach($p['selected']as $s)foreach($s['patch']as $field=>$v)$counts[$field]=($counts[$field]??0)+1;ksort($counts);
 return ['status'=>'committed_verified','operation'=>LPMA_OPERATION,'planSha256'=>$p['planSha256'],
 'profilesUpdated'=>count($p['selected']),'profileWrites'=>count($p['selected']),'provenanceWrites'=>count($p['selected']),
 'fieldsFilled'=>array_sum($counts),'fieldCounts'=>$counts,'supplierCalls'=>0,'mappingWrites'=>0,'legacyWrites'=>0];}
function mutatePlan(&$index,&$files,$callback){$file=$index['batches'][0]['file'];$b=json_decode($files[$file],true);$p=$b['owner_plan'];
 $callback($p,$index);$core=$p;unset($core['status'],$core['writes'],$core['supplierCalls'],$core['planSha256']);$p['planSha256']=lpm_digest($core);
 $b['owner_plan']=$p;$files[$file]=lpp_json($b);$index['batches'][0]['sha256']=hash('sha256',$files[$file]);$index['batches'][0]['plan_sha256']=$p['planSha256'];}
'''
    def php(self,code):
        run=subprocess.run(['php','-r','require $argv[1];'+self.FIXTURE+code,str(ROOT/m.MASS_RUNNER)],capture_output=True,text=True,timeout=30)
        self.assertEqual(0,run.returncode,run.stderr+run.stdout)

    def test_exact_sealed_cohort_and_all_four_preflights_before_writes(self):
        self.php("""$b=batches();ok(count($b)===4);$order='';$r=lpma_execute($b,
        function($p)use(&$order){$order.='P';return $p;},function($p)use(&$order){$order.='A';return goodApply($p);},
        function()use(&$order){$order.='C';},function()use(&$order){$order.='K';});
        ok($order==='PPPPCAKAKAKAK');ok($r['state']==='committed_verified');ok($r['profiles_verified']===71);
        ok($r['fields_verified']===735);ok($r['profile_writes']===71);ok($r['readback_verified']===true);""")

    def test_current_drift_or_consumed_input_never_calls_apply(self):
        self.php("""foreach(['drift','consumed']as $kind){$calls=0;$consumes=0;$r=lpma_execute(batches(),
        function($p)use($kind){if($kind==='drift')$p['planSha256']=str_repeat('0',64);return $p;},
        function($p)use(&$calls){++$calls;return goodApply($p);},function()use($kind,&$consumes){++$consumes;if($kind==='consumed')throw new RuntimeException('exists');},fn()=>null);
        ok($calls===0);ok($r['state']==='held_before_write');ok($r['profile_writes']===0);ok($r['readback_verified']===false);}""")

    def test_mid_batch_error_preserves_known_commits_and_never_retries(self):
        self.php("""$calls=0;$checks=0;$r=lpma_execute(batches(),fn($p)=>$p,
        function($p)use(&$calls){if(++$calls===2)throw new RuntimeException('postcommit unknown');return goodApply($p);},fn()=>null,
        function()use(&$checks){++$checks;});ok($calls===2&&$checks===1);ok($r['profiles_verified']===25);
        ok($r['fields_verified']===275);ok($r['unknown_batch']===2);ok($r['state']==='unknown_no_replay');
        ok($r['profile_writes']==='unknown'&&$r['provenance_writes']==='unknown');""")

    def test_invalid_readback_and_checkpoint_failure_are_unknown_not_rollback(self):
        self.php("""foreach(['readback','checkpoint']as $kind){$calls=0;$r=lpma_execute(batches(),fn($p)=>$p,
        function($p)use($kind,&$calls){++$calls;$a=goodApply($p);if($kind==='readback')$a['profileWrites']=26;return $a;},fn()=>null,
        function()use($kind){if($kind==='checkpoint')throw new RuntimeException('disk error');});
        ok($calls===1);ok($r['state']==='unknown_no_replay');ok($r['profiles_verified']===($kind==='checkpoint'?25:0));
        ok($r['profile_writes']==='unknown');}""")

    def test_d1_own_and_historical_local_exclusions_block_private_batch(self):
        self.php("""foreach([['own'=>[100001=>true],'local'=>[]],['own'=>[],'local'=>[200001=>true]]]as $protected){
        [$i,$f]=fixture();try{lpma_batches($i,$protected,fn($p)=>$f[$p]);throw new LogicException('accepted');}
        catch(RuntimeException $e){ok($e->getMessage()==='excluded_scope');}}""")

    def test_digest_traversal_identity_and_count_widening_rejected(self):
        self.php("""foreach(['digest','path','identity','count','selected']as $kind){[$i,$f]=fixture();
        if($kind==='digest')$f[$i['batches'][0]['file']].='x';if($kind==='path')$i['batches'][0]['file']='../secret';
        if($kind==='identity')$i['operation_id']='renamed';if($kind==='count')$i['profiles_with_delta']=72;
        if($kind==='selected')$i['rows'][0]['state']='SOURCE_MISSING';
        try{lpma_batches($i,['own'=>[],'local'=>[]],fn($p)=>$f[$p]);throw new LogicException('accepted');}catch(RuntimeException $e){}}""")

    def test_nonempty_editorial_data_and_foreign_patch_cannot_be_overwritten(self):
        self.php("""foreach(['nonempty','revision','field','duplicate']as $kind){[$i,$f]=fixture();
        mutatePlan($i,$f,function(&$p,&$i)use($kind){
         if($kind==='nonempty'){$s=&$p['selected'][0];$s['beforeProfileJson']='{\"address\":\"manual\"}';$s['expectedProfileSha256']=hash('sha256',$s['beforeProfileJson']);$i['rows'][0]['expectedProfileSha256']=$s['expectedProfileSha256'];}
         if($kind==='revision'){$p['selected'][0]['expectedRevision']=2;$i['rows'][0]['expectedRevision']=2;}
         if($kind==='field')$p['selected'][0]['patch']['name']='not admitted';
         if($kind==='duplicate')$p['selected'][]=$p['selected'][0];});
        try{lpma_batches($i,['own'=>[],'local'=>[]],fn($p)=>$f[$p]);throw new LogicException('accepted');}catch(RuntimeException $e){}}""")

    def test_unknown_d1_fails_before_db_queries(self):
        self.php("""class EmptyPDO extends PDO{function __construct(){}}try{lpma_protected(new EmptyPDO(),['state'=>'unknown_held']);
        throw new LogicException('accepted');}catch(RuntimeException $e){ok($e->getMessage()==='d1_unknown');}""")

if __name__=='__main__':unittest.main()
