<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live234_v65_current_audit_v66.php';

$checks=0;
function check66(bool $ok,string $message):void { global $checks; $checks++; if(!$ok)throw new RuntimeException($message); }
function throws66(callable $fn,string $message):void { try{$fn();}catch(Throwable){check66(true,$message);return;}check66(false,$message); }
function fixture66():array {
    $lanes=[];
    foreach(V66_NS as $ns) $lanes[$ns]=['state'=>'not_observed','native_ids'=>[],'facts'=>0];
    $lanes['operator_115']=['state'=>'single_native','native_ids'=>[10],'facts'=>1];
    $lanes['operator_315']=['state'=>'single_native','native_ids'=>['20'],'facts'=>1];
    $c=['catalog_id'=>'99','lanes'=>$lanes,'names'=>['Example Hotel'],'lane_count'=>2,'retrieval'=>'exact_variant','geo_relation'=>'unproven'];
    $e=['candidate'=>$c,'dossier'=>['hotel_name'=>'EXAMPLE HOTEL','direct_anex_ids'=>['50']], 'dossier_sha256'=>str_repeat('a',64),'candidate_sha256'=>str_repeat('b',64)];
    $i=['catalogTargets'=>['99'=>[7=>true]],'nativeCatalog'=>['operator_115|10'=>['99'=>true],'operator_315|20'=>['99'=>true]],'catalogNatives'=>['99'=>['operator_115'=>['10'=>true],'operator_315'=>['20'=>true]]]];
    $row=['decision_status'=>'accepted','local_hotel_id'=>7,'evidence_valid'=>true,'evidence_sha256'=>str_repeat('c',64),'catalog_sha256'=>str_repeat('d',64)];
    $u=['hotels'=>[7=>['is_active'=>1,'country_name'=>'Турция']], 'live'=>[7=>true], 'manual'=>[], 'anex'=>[7=>['50']],
        'source'=>[],'target'=>[],'registry'=>['operator_115|10'=>[$row],'operator_315|20'=>[$row]]];
    return [$e,$i,$u];
}
[$e,$i,$u]=fixture66();
$r=v66_classify(7,$e,$i,$u);
check66($r['status']==='ready_for_guarded_writer' && $r['current_cross_source_lanes']===2 && $r['safe_to_write_now']===false,'two independent lanes');
$u['registry']=[];$r=v66_classify(7,$e,$i,$u);check66($r['status']==='hold','two provider lanes and name alone never accept');
[$e,$i,$u]=fixture66();unset($u['registry']['operator_315|20']);check66(v66_classify(7,$e,$i,$u)['status']==='ready_for_guarded_writer','one current lane is sufficient');
[$e,$i,$u]=fixture66();$i['catalogTargets']['99'][8]=true;check66(in_array('candidate_catalog_multiple_targets',v66_classify(7,$e,$i,$u)['reasons'],true),'same catalog two local targets');
[$e,$i,$u]=fixture66();$i['nativeCatalog']['operator_115|10']['100']=true;check66(v66_classify(7,$e,$i,$u)['status']==='hold','native multiple catalogs');
[$e,$i,$u]=fixture66();$i['catalogNatives']['99']['operator_115']['11']=true;check66(v66_classify(7,$e,$i,$u)['status']==='hold','cross dossier multi native');
[$e,$i,$u]=fixture66();$u['registry']['operator_315|20'][0]['local_hotel_id']=8;check66(v66_classify(7,$e,$i,$u)['status']==='hold','contradictory registry');
[$e,$i,$u]=fixture66();$u['registry']['operator_315|20'][0]['evidence_valid']=false;check66(v66_classify(7,$e,$i,$u)['status']==='hold','invalid evidence');
[$e,$i,$u]=fixture66();$u['registry']['operator_315|20'][]=['decision_status'=>'rejected'];check66(v66_classify(7,$e,$i,$u)['status']==='hold','protected lane decision');
[$e,$i,$u]=fixture66();$u['source']['99']=[['decision_status'=>'accepted','local_hotel_id'=>8,'evidence_valid'=>true]];check66(v66_classify(7,$e,$i,$u)['status']==='hold','occupied source');
[$e,$i,$u]=fixture66();$u['target'][7]=[['external_hotel_id'=>'100','decision_status'=>'accepted','evidence_valid'=>true]];check66(v66_classify(7,$e,$i,$u)['status']==='hold','occupied target');
[$e,$i,$u]=fixture66();$u['source']['99']=[['decision_status'=>'accepted','local_hotel_id'=>7,'evidence_valid'=>true]];$r=v66_classify(7,$e,$i,$u);check66($r['status']==='already_resolved_same','do not duplicate accepted catalog');
[$e,$i,$u]=fixture66();$u['manual'][7]=true;check66(v66_classify(7,$e,$i,$u)['status']==='hold','manual target');
[$e,$i,$u]=fixture66();$u['anex'][7]=[];check66(v66_classify(7,$e,$i,$u)['status']==='hold','lost effective ANEX');
[$e,$i,$u]=fixture66();$u['anex'][7]=['51'];check66(v66_classify(7,$e,$i,$u)['status']==='hold','changed effective ANEX');
[$e,$i,$u]=fixture66();$u['live']=[];check66(v66_classify(7,$e,$i,$u)['status']==='hold','no longer TV live30');
foreach(['Россия','Абхазия','Russia','Abkhazia'] as $country){[$e,$i,$u]=fixture66();$u['hotels'][7]['country_name']=$country;check66(v66_classify(7,$e,$i,$u)['status']==='hold','excluded geography');}
[$e,$i,$u]=fixture66();$u['hotels'][7]['is_active']=0;check66(v66_classify(7,$e,$i,$u)['status']==='hold','inactive');
check66(v66_support(['50'],[50])==='support_equal','mixed integer string normalization');
check66(v66_support(['50'],[51])==='support_different','different support');
check66(v66_support(['50','51'],[50])==='support_collision','multiple support');
check66(v66_support(['50'],[])==='none','missing support');
foreach([null,1.1,true,'0','01','-1','1;SQL'] as $bad) throws66(fn()=>v66_id($bad),'reject bad ID');
$raw='{"proof":"exact"}';$row=['supplier_namespace'=>'operator_115','external_hotel_id'=>'10','local_hotel_id'=>'7','decision_status'=>'accepted','evidence_json'=>$raw,'evidence_sha256'=>hash('sha256',$raw),'catalog_sha256'=>str_repeat('e',64)];
check66(v66_indexes([$row])['registry']['operator_115|10'][0]['evidence_valid']===true,'evidence byte hash');
$row['evidence_json']='{"proof":"tampered"}';check66(v66_indexes([$row])['registry']['operator_115|10'][0]['evidence_valid']===false,'tampered evidence');
$row['evidence_json']='null';$row['evidence_sha256']=hash('sha256','null');check66(v66_indexes([$row])['registry']['operator_115|10'][0]['evidence_valid']===false,'invalid evidence structure');
$dir=sys_get_temp_dir().'/v66-'.bin2hex(random_bytes(8));mkdir($dir,0700);$path=$dir.'/sealed.json';
$sha=v66_save($path,['test'=>true]);check66($sha===hash_file('sha256',$path),'sealed hash');throws66(fn()=>v66_save($path,['test'=>false]),'no overwrite');unlink($path);rmdir($dir);
$code=(string)file_get_contents(dirname(__DIR__).'/scripts/diagnostics/hotel_match_live234_v65_current_audit_v66.php');
check66(str_contains($code,'START TRANSACTION READ ONLY') && str_contains($code,'REPEATABLE READ') && str_contains($code,'->rollBack()'),'read-only snapshot');
check66(preg_match('/curl_|https?:\/\/|INSERT INTO|UPDATE .* SET|DELETE FROM|->commit\(/',$code)===0,'no supplier or DB write code');
check66(strpos($code,"v66_save(\$dir.'/execution-started.json'") < strpos($code,'v66_run(v2_data_db()'),'durable start before DB read');
if(isset($argv[1])) {
    check66(hash_file('sha256',$argv[1])===V66_SOURCE_SHA,'exact real input');$real=v66_load($argv[1]);$parsed=v66_source($real);
    check66(count($parsed['selected'])===49 && count($parsed['catalogTargets'])===48,'real scope');
    check66(array_keys($parsed['catalogTargets']['37255'])===[1772,1773],'real source collision');
    $real['strict_multi_lane_candidate_count']=50;throws66(fn()=>v66_source($real),'scope cannot broaden');
}
// Owner rule: one exact provider-qualified lane; the second lane is optional.
foreach (V66_NS as $only) {
    [$e,$i,$u]=fixture66(); $row=$u['registry']['operator_315|20'][0];
    foreach (V66_NS as $ns) $e['candidate']['lanes'][$ns]=['state'=>'not_observed','native_ids'=>[],'facts'=>0];
    $e['candidate']['lanes'][$only]=['state'=>'single_native','native_ids'=>['20'],'facts'=>1];
    $e['candidate']['lane_count']=1;
    $i['nativeCatalog']=[$only.'|20'=>['99'=>true]];
    $i['catalogNatives']=['99'=>[$only=>['20'=>true]]];
    $u['registry']=[$only.'|20'=>[$row]];
    $r=v66_classify(7,$e,$i,$u);
    check66($r['status']==='ready_for_guarded_writer' && $r['current_cross_source_lanes']===1 && $r['safe_to_write_now']===false,'single proven '.$only);
    $u['registry'][$only.'|20'][]=$row;
    check66(v66_classify(7,$e,$i,$u)['current_cross_source_lanes']===1,'duplicate does not add operator');
    $u['manual'][7]=true;
    check66(v66_classify(7,$e,$i,$u)['status']==='hold','one lane does not override manual');
    $u['manual']=[]; $u['source']['99']=[['decision_status'=>'accepted','local_hotel_id'=>8,'evidence_valid'=>true]];
    check66(v66_classify(7,$e,$i,$u)['status']==='hold','one lane does not override occupancy');
    $u['source']=[]; $u['registry']=[];
    $r=v66_classify(7,$e,$i,$u);
    check66($r['status']==='hold' && in_array('no_proven_cross_source_lane',$r['reasons'],true),'provider observation alone is not proof');
}
echo 'MATCH_V66_TEST_OK checks='.$checks."\n";
