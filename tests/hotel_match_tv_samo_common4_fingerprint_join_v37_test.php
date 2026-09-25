<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_common4_fingerprint_join_v37.php';

function v37t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}

v37t(V37_OP==='hotel-match-tv-samo-common4-fingerprint-join-1971-20260925-v37','op');
v37t(V37_DIRECT===['operator_315'=>25,'operator_342'=>43],'direct_namespaces');
v37t(V37_SUPPORT_TV===['bgoperator'=>18,'anex'=>13],'tv_support');
v37t(V37_SUPPORT_SAMO===['operator_115'=>115,'operator_5'=>5],'samo_support');

$n=['state'=>'captured_single_native','positive_native_candidates'=>['123']];
v37t(v37_single_native($n)==='123','single_native');
$n=['state'=>'captured_single_native','positive_native_candidates'=>['123','124']];
v37t(v37_single_native($n)===null,'ambiguous_native');

$frontier=[10=>'tv_only_missing_both',20=>'tv_anex_missing_samo'];
$tv=[
 ['namespace'=>'operator_315','operator_id'=>25,'native_id'=>'111','tv_hotel_id'=>10,'source_operation'=>'t1','source_result_sha256'=>str_repeat('a',64),'source_edge_sha256'=>str_repeat('b',64)],
 ['namespace'=>'operator_342','operator_id'=>43,'native_id'=>'222','tv_hotel_id'=>10,'source_operation'=>'t2','source_result_sha256'=>str_repeat('c',64),'source_edge_sha256'=>str_repeat('d',64)],
 ['namespace'=>'bgoperator','operator_id'=>18,'native_id'=>'333','tv_hotel_id'=>10,'source_operation'=>'t3','source_result_sha256'=>str_repeat('e',64),'source_edge_sha256'=>str_repeat('f',64)],
 ['namespace'=>'operator_315','operator_id'=>25,'native_id'=>'444','tv_hotel_id'=>20,'source_operation'=>'t4','source_result_sha256'=>str_repeat('1',64),'source_edge_sha256'=>str_repeat('2',64)]
];
$idx=v37_index_tv($tv,$frontier);
v37t(count($idx['fingerprints']['operator_315|111']??[])===1,'tv_index');

$samo=[
 '900'=>[
   'operator_315'=>['111'=>[['source_operation'=>'s1','source_result_sha256'=>str_repeat('3',64),'source_edge_sha256'=>str_repeat('4',64)]]],
   'operator_342'=>['222'=>[['source_operation'=>'s2','source_result_sha256'=>str_repeat('5',64),'source_edge_sha256'=>str_repeat('6',64)]]],
   'operator_115'=>['333'=>[['source_operation'=>'s3','source_result_sha256'=>str_repeat('7',64),'source_edge_sha256'=>str_repeat('8',64)]]],
 ],
 '901'=>[
   'operator_315'=>['444'=>[['source_operation'=>'s4','source_result_sha256'=>str_repeat('9',64),'source_edge_sha256'=>str_repeat('a',64)]]],
 ],
 '902'=>[
   'operator_115'=>['333'=>[['source_operation'=>'s5','source_result_sha256'=>str_repeat('b',64),'source_edge_sha256'=>str_repeat('c',64)]]],
 ],
];
$c=v37_candidate_rows($samo,$idx['fingerprints'],$frontier,[],[],[10=>true,20=>true]);
v37t(($c['status_counts']['strong_2plus_direct_mutual_unique']??0)===1,'strong');
v37t(($c['status_counts']['single_direct_review']??0)===1,'single');
v37t(($c['status_counts']['support_only_review']??0)===1,'support_only');

$strong=array_values(array_filter($c['rows'],fn($r)=>$r['status']==='strong_2plus_direct_mutual_unique'));
v37t(count($strong)===1&&$strong[0]['tv_hotel_id']===10,'strong_target');
v37t(isset($strong[0]['supporting_cross_namespace']['bgoperator_to_operator_115']),'support_not_promoted');

$conflictSamo=[
 '910'=>[
   'operator_315'=>['111'=>[['source_operation'=>'s','source_result_sha256'=>str_repeat('d',64),'source_edge_sha256'=>str_repeat('e',64)]]],
   'operator_342'=>['999'=>[['source_operation'=>'s','source_result_sha256'=>str_repeat('f',64),'source_edge_sha256'=>str_repeat('0',64)]]],
 ],
];
$tv2=$tv;
$tv2[]=['namespace'=>'operator_342','operator_id'=>43,'native_id'=>'999','tv_hotel_id'=>20,'source_operation'=>'t5','source_result_sha256'=>str_repeat('1',64),'source_edge_sha256'=>str_repeat('3',64)];
$idx2=v37_index_tv($tv2,$frontier);
$c2=v37_candidate_rows($conflictSamo,$idx2['fingerprints'],$frontier,[],[],[10=>true,20=>true]);
v37t(($c2['status_counts']['hold_conflicting_direct_votes']??0)===1,'direct_conflict');

echo "MATCH_TV_SAMO_COMMON4_FINGERPRINT_V37_TEST_OK\n";
