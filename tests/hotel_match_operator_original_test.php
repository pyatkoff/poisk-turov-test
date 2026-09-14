<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_original_batch.php';
$n=0;
function ok(bool $condition):void{global $n;if(!$condition)throw new RuntimeException('assertion_'.($n+1));$n++;}
function rejects(callable $f):bool{try{$f();return false;}catch(Throwable $e){return true;}}
$row=['hotelKey'=>177152,'operatorKey'=>5,'isOperatorHotelKey'=>0,'hotel'=>'Barcelo Tiran Sharm','original'=>['hotelKey'=>4158,'hotel'=>'Barcelo Tiran Sharm']];
$fact=mob_fact($row,['177152'=>true],'5',1,'request','response');
ok($fact['native_hotel_id']==='4158');ok($fact['andromeda_hotel_id']==='177152');ok($fact['operator_key']==='5');ok(!$fact['is_operator_hotel_key']);
ok(rejects(fn()=>mob_fact($row,['8801'=>true],'5',1,'r','s')));
ok(rejects(fn()=>mob_fact($row,['177152'=>true],'315',1,'r','s')));
foreach([1,'1',null,false,true,'unknown'] as $v){$r=$row;$r['isOperatorHotelKey']=$v;ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);}
foreach([null,0,'0',false,[],str_repeat('9',33),'a|b'] as $v){$r=$row;$r['original']['hotelKey']=$v;ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);}
$r=$row;unset($r['original']);ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);
$r=$row;$r['original']['hotel']='Fortuna 5*';ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);
$r=$row;$r['operatorKey']=115;$r['original']['hotelKey']='BG-001.2';$f=mob_fact($r,['177152'=>true],'115',1,'r','s');ok($f['native_hotel_id']==='BG-001.2'&&$f['operator_key']==='115');
$ids=array_map('strval',range(2000000001,2000001200));$chunks=mob_chunks($ids);ok(array_merge(...$chunks)===$ids);ok(count($chunks)>40);foreach($chunks as $chunk)if(count($chunk)>30||strlen(implode(',',$chunk))>300)throw new RuntimeException('chunk_budget');ok(true);
ok(mob_chunks([])===[]);ok(rejects(fn()=>mob_chunks(['0'])));ok(rejects(fn()=>mob_chunks(['177152,4158'])));
$dir=sys_get_temp_dir().'/mob-test-'.bin2hex(random_bytes(5));mkdir($dir);$digest=mob_write($dir.'/result.json',['mapping_writes'=>0]);ok($digest===hash_file('sha256',$dir.'/result.json'));ok(rejects(fn()=>@mob_write($dir.'/result.json',[])));unlink($dir.'/result.json');rmdir($dir);
$other=$row;$other['hotelKey']=999999;$scoped=$row;$scoped['isOperatorHotelKey']=1;
$ins=mob_inspect([$row,$other,$scoped],['177152'=>true],'5',1,'r','s');ok(count($ins['facts'])===1);ok(count($ins['holds'])===2);ok(count($ins['observations'])===3);ok($ins['holds'][0]['reason']==='outside_requested_hotel_or_operator');
if(is_file(__DIR__.'/../scripts/diagnostics/hotel_match_operator_original_store.php')){
    define('MOS_LIBRARY_ONLY',true);require __DIR__.'/../scripts/diagnostics/hotel_match_operator_original_store.php';
    $g=['operator_key'=>'5','native_hotel_id'=>'4158','case_conflict'=>false,'facts'=>[$fact]];
    $a=['177152'=>['decision_status'=>'accepted','local_hotel_id'=>37412,'evidence_json'=>'{}','evidence_sha256'=>str_repeat('a',64)]];
    $h=[37412=>['id'=>37412,'country_id'=>1,'name'=>'BARCELO TIRAN SHARM','is_active'=>1,'latitude'=>28.069,'longitude'=>34.442]];
    ok(mos_classify($g,$a,$h,false)['state']==='accepted');ok(mos_classify($g,$a,$h,false)['local_hotel_id']===37412);
    ok(mos_classify($g,$a,$h,true)['reason']==='existing_native_identity_protected');
    $bad=$a;$bad['177152']['decision_status']='conflict';ok(mos_classify($g,$bad,$h,false)['state']==='hold');
    $bad=$a;$bad['177152']['decision_status']='pending';$bad['177152']['local_hotel_id']=null;ok(mos_classify($g,$bad,$h,false)['state']==='pending');ok(mos_classify($g,$bad,$h,false)['local_hotel_id']===null);
    $bad=$h;$bad[37412]['country_id']=4;ok(mos_classify($g,$a,$bad,false)['state']==='hold');
    $bad=$h;$bad[37412]['is_active']=0;ok(mos_classify($g,$a,$bad,false)['state']==='hold');
    $bad=$h;$bad[37412]['name']='UNRELATED PROPERTY';ok(mos_classify($g,$a,$bad,false)['state']==='hold');
    $bad=$a;$bad['177152']['evidence_json']=json_encode(['source'=>['stateKey'=>5]]);ok(mos_classify($g,$bad,$h,false)['reason']==='catalog_country_conflict');
    $bad=$a;$bad['177152']['evidence_json']=json_encode(['source'=>['latitude'=>30.1,'longitude'=>34.4]]);ok(mos_classify($g,$bad,$h,false)['reason']==='coordinate_conflict_gt5km');
    ok(mos_distance(['latitude'=>28.069,'longitude'=>34.442],$h[37412])<0.001);ok(mos_distance([],[])===null);
    ok(!mos_name_guard(['Domina Aquamarine Beach'],'DOMINA AQUAMARINE POOL'));ok(!mos_name_guard(['Barcelo Tiran Beach'],'BARCELO TIRAN'));ok(!mos_name_guard(['Hotel Resort Spa'],'HOTEL RESORT SPA'));ok(mos_name_guard(['BARCELÓ TIRAN SHARM HOTEL'],'Barcelo Tiran Sharm'));
    $bad=$g;$bad['case_conflict']=true;ok(mos_classify($bad,$a,$h,false)['state']==='hold');
    $bad=$g;$copy=$fact;$copy['andromeda_hotel_id']='177153';$bad['facts'][]=$copy;$aa=$a;$aa['177153']=$a['177152'];ok(mos_classify($bad,$aa,$h,false)['state']==='accepted');$aa['177153']['local_hotel_id']=37413;$hh=$h;$hh[37413]=$h[37412];ok(mos_classify($bad,$aa,$hh,false)['reason']==='cross_catalog_conflicting_targets');
    $proofParams=['STATEINC'=>3,'OPERATORS'=>'5','HOTELS'=>'177152'];$req=hash('sha256',mos_json($proofParams));$f=$fact;$f['request_sha256']=$req;$f['response_sha256']=str_repeat('b',64);
    $p=['params'=>$proofParams,'request_sha256'=>$req,'response_sha256'=>$f['response_sha256']];$input=['state'=>'completed','operation_id'=>MOS_INPUT_OP,'source_sha'=>MOS_INPUT_SOURCE,'facts'=>[$f],'pages'=>[$p],'plan_summary'=>['previous_request'=>$p]];
    ok(count(mos_groups($input))===1);$ff=$f;$ff['operator_key']='315';$pp=$proofParams;$pp['OPERATORS']='315';$rr=hash('sha256',mos_json($pp));$ff['request_sha256']=$rr;$input['facts'][]=$ff;$input['pages'][]=['params'=>$pp,'request_sha256'=>$rr,'response_sha256'=>$ff['response_sha256']];ok(count(mos_groups($input))===2);
    $bad=$input;$bad['facts'][0]['andromeda_hotel_id']='999';ok(rejects(fn()=>mos_groups($bad)));$bad=$input;$bad['facts'][0]['is_operator_hotel_key']=true;ok(rejects(fn()=>mos_groups($bad)));$bad=$input;$bad['source_sha']=str_repeat('0',40);ok(rejects(fn()=>mos_groups($bad)));
}
echo $n." operator-original checks PASS\n";
