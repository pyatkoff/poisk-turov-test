<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live234_secondary_canonical_consensus_v64.php';

function v64t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v64t(V64_OP==='hotel-match-live234-sealed-secondary-canonical-consensus-1971-20260926-v64','op');
v64t(V64_SOURCE_OP==='hotel-match-live234-sealed-secondary-salvage-1971-20260926-v63','source');
v64t(V64_SOURCE_SHA==='dc4ad9e16b5f800a363f406965e318b6eb69bdb2ea77eb0b856cfe4c010fce9c','source_sha');
v64t(V64_TV_BRIDGE===['bgoperator'=>'operator_115','operator_315'=>'operator_315','operator_342'=>'operator_342'],'bridge');

$proof=[
 'by_source'=>[
   'operator_115|10'=>[7=>true],
   'operator_315|20'=>[7=>true],
 ],
 'by_target'=>[
   7=>['operator_115'=>['10'=>true],'operator_315'=>['20'=>true]],
 ],
 'targets'=>[7=>true],
];
$idx=[
 'src'=>['99'=>[
   'operator_115'=>['10'=>[['evidence'=>[['operation'=>'s','result_sha256'=>str_repeat('a',64),'edge_sha256'=>str_repeat('b',64)]]]]],
   'operator_315'=>['20'=>[['evidence'=>[['operation'=>'s','result_sha256'=>str_repeat('c',64),'edge_sha256'=>str_repeat('d',64)]]]]],
 ]],
 'fp'=>[
   'operator_115|10'=>['99'=>true],
   'operator_315|20'=>['99'=>true],
 ],
];
$cur=[
 'registry'=>[
   'operator_115|10'=>[['local_hotel_id'=>7,'evidence_valid'=>true]],
   'operator_315|20'=>[['local_hotel_id'=>7,'evidence_valid'=>true]],
 ],
 'invalidAccepted'=>[],
 'catalogSource'=>[],
 'catalogTarget'=>[],
 'active'=>[7=>['id'=>7,'is_active'=>1,'country_name'=>'Турция']],
 'manual'=>[],
];
$r=v64_classify('99',$idx,$cur,$proof);
v64t($r['status']==='strict_cross_source_2plus_current_missing','strict');
v64t($r['candidate_local_hotel_id']===7,'target');
v64t($r['consensus_operator_count']===2,'lanes');
v64t($r['tv_proof_operator_count']===2,'proofs');

$cur['catalogTarget'][7]=[['external_hotel_id'=>'other','local_hotel_id'=>7,'decision_status'=>'accepted']];
$r=v64_classify('99',$idx,$cur,$proof);
v64t($r['status']==='hold_target_catalog_occupied','occupied');

$cur['catalogTarget']=[];
$cur['invalidAccepted']['operator_315|20']=true;
$r=v64_classify('99',$idx,$cur,$proof);
v64t($r['status']!=='strict_cross_source_2plus_current_missing','invalid_evidence_hold');

echo "MATCH_LIVE234_SECONDARY_CANONICAL_CONSENSUS_V64_TEST_OK\n";
