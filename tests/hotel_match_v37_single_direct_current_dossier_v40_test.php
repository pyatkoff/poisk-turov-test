<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_v37_single_direct_current_dossier_v40.php';

function v40t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}

v40t(count(V40_EXPECTED)===9,'nine_inputs');
v40t(V40_EXPECTED['3995']===490&&V40_EXPECTED['6998']===155174,'pinned_pairs');

$row=[
 'candidate_local_hotel_id'=>42,'frontier_bucket'=>'tv_anex_missing_samo',
 'direct'=>[
   'operator_315'=>['state'=>'exact_unique','native_id'=>'700'],
   'operator_342'=>['state'=>'no_tv_fingerprint','native_id'=>'800'],
 ],
 'support'=>[
   'operator_115'=>['state'=>'source_namespace_missing'],
   'operator_5'=>['state'=>'no_tv_fingerprint','native_id'=>'900'],
 ],
];
$ev=['valid'=>true,'evidence_sha256'=>str_repeat('a',64),'catalog_sha256'=>str_repeat('b',64),'decision_status'=>'accepted'];
$cur=[
 'registry'=>[
   'operator_315|700'=>[['local_hotel_id'=>42,'evidence'=>$ev]],
   'operator_342|800'=>[['local_hotel_id'=>42,'evidence'=>$ev]],
 ],
 'catalogSource'=>[],'catalogTarget'=>[],
 'active'=>[42=>['id'=>42,'country_name'=>'Турция','name'=>'Test']],
 'manual'=>[],
];
$x=v40_classify('9000',$row,$cur);
v40t($x['status']==='current_missing_single_direct_exact','exact');
v40t($x['second_independent_proof_count']===1&&$x['second_independent_same_target_namespaces']===['operator_342'],'second_proof');

$cur['registry']['operator_342|800'][0]['local_hotel_id']=99;
$x=v40_classify('9000',$row,$cur);
v40t($x['status']==='conflict','other_operator_conflict');

unset($cur['registry']['operator_342|800']);
$cur['catalogSource']['9000']=[['local_hotel_id'=>42,'decision_status'=>'accepted','evidence_sha256'=>str_repeat('c',64)]];
$cur['catalogTarget'][42]=[['external_hotel_id'=>'9000','decision_status'=>'accepted','evidence_sha256'=>str_repeat('c',64)]];
$x=v40_classify('9000',$row,$cur);
v40t($x['status']==='already_resolved_same','resolved');

echo "MATCH_V37_SINGLE_DIRECT_CURRENT_DOSSIER_V40_TEST_OK\n";
