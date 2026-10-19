<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_registry_consensus_v38.php';

function v38t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}

v38t(V38_NS===['operator_5'=>5,'operator_115'=>115,'operator_315'=>315,'operator_342'=>342],'namespaces');

$raw=v38_json(['source_operation'=>'hotel-match-live30-common4-continuation-acquire-1971-20260923-c1-n100-v1']);
$p=v38_provenance(['evidence_json'=>$raw,'evidence_sha256'=>hash('sha256',$raw)]);
v38t($p['class']==='tv_live30'&&$p['evidence_valid']===true,'tv_provenance');

$idx=[
 'tvFp'=>['operator_315|700'=>[44=>true]],
 'tvTargetNs'=>[44=>['operator_315'=>['700'=>true]]],
 'srcNs'=>['9000'=>[
   'operator_115'=>['600'=>true],
   'operator_315'=>['700'=>true],
   'operator_342'=>['800'=>true],
 ]],
 'srcFp'=>[
   'operator_115|600'=>['9000'=>true],
   'operator_315|700'=>['9000'=>true],
   'operator_342|800'=>['9000'=>true],
 ],
];
$unknown=['evidence_valid'=>true,'class'=>'unknown','operations'=>[],'evidence_sha256'=>str_repeat('a',64)];
$cur=[
 'registry'=>[
   'operator_115|600'=>[['local_hotel_id'=>44,'provenance'=>$unknown]],
   'operator_315|700'=>[['local_hotel_id'=>44,'provenance'=>$unknown]],
   'operator_342|800'=>[['local_hotel_id'=>44,'provenance'=>$unknown]],
 ],
 'catalogSource'=>[],'catalogTarget'=>[],'active'=>[44=>['id'=>44]],'manual'=>[],
];
$r=v38_classify('9000',$idx,[44=>'tv_only_missing_both'],$cur);
v38t($r['status']==='strict_cross_source_2plus_current_missing','strict_current_missing');
v38t($r['consensus_operator_count']===3&&$r['tv_proof_operator_count']===1,'consensus_counts');

$cur['registry']['operator_342|800'][0]['local_hotel_id']=45;
$r=v38_classify('9000',$idx,[44=>'tv_only_missing_both',45=>'tv_only_missing_both'],$cur);
v38t($r['status']==='conflict_registry_targets','conflict');

$cur['registry']['operator_342|800'][0]['local_hotel_id']=44;
$cur['catalogTarget'][44]=[['external_hotel_id'=>'different','local_hotel_id'=>44,'decision_status'=>'accepted']];
$r=v38_classify('9000',$idx,[44=>'tv_only_missing_both'],$cur);
v38t($r['status']==='hold_target_samo_occupied','target_guard');

echo "MATCH_SAMO_REGISTRY_CONSENSUS_V38_TEST_OK\n";
