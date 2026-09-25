<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_registry_current_bridge_audit_v39.php';

function v39t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}

v39t(V39_V38_SHA==='fcb21a9ac50d3b1777db003f28d555d106ec8a81','v38_sha');
v39t(V39_FRONTIER_SHA==='5001297e29a920acc2d565e45fec0e4d0a5b2244e0ba60a860941be882ad1c77','frontier_sha');

$cur=[
 'registry'=>[
   'operator_115|11'=>[['local_hotel_id'=>42,'evidence_sha256'=>str_repeat('a',64),'evidence_valid'=>true,'operations'=>['hotel-match-live30-common4-acquire-x'],'catalog_sha256'=>str_repeat('1',64)]],
   'operator_315|31'=>[['local_hotel_id'=>42,'evidence_sha256'=>str_repeat('b',64),'evidence_valid'=>true,'operations'=>[],'catalog_sha256'=>str_repeat('1',64)]],
   'operator_342|34'=>[['local_hotel_id'=>42,'evidence_sha256'=>str_repeat('c',64),'evidence_valid'=>true,'operations'=>[],'catalog_sha256'=>str_repeat('1',64)]],
 ],
 'catalogSource'=>[],'catalogTarget'=>[],'catalogNonAccepted'=>[],
 'active'=>[42=>['country_name'=>'Турция']],'manual'=>[],
];
$in=[
 'andromeda_catalog_id'=>'9001','candidate_local_hotel_id'=>42,'consensus_operator_count'=>3,
 'lanes'=>[
   'operator_115'=>['state'=>'registry_unique','native_id'=>'11'],
   'operator_315'=>['state'=>'registry_unique','native_id'=>'31'],
   'operator_342'=>['state'=>'registry_unique','native_id'=>'34'],
 ],
];
$r=v39_classify($in,[42=>'tv_anex_missing_samo'],$cur);
v39t($r['status']==='deterministic_current_missing','deterministic');
v39t($r['operator_count']===3&&$r['current_exact_operator_count']===3,'counts');

$cur['registry']['operator_315|31'][0]['evidence_valid']=false;
$r=v39_classify($in,[42=>'tv_anex_missing_samo'],$cur);
v39t($r['status']==='hold_current_registry_drift','invalid_evidence_holds');

$cur['registry']['operator_315|31'][0]['evidence_valid']=true;
$cur['catalogSource']['9001']=[['external_hotel_id'=>'9001','local_hotel_id'=>42,'decision_status'=>'accepted']];
$cur['catalogTarget'][42]=$cur['catalogSource']['9001'];
$r=v39_classify($in,[42=>'tv_anex_missing_samo'],$cur);
v39t($r['status']==='already_resolved_same','already_resolved');

echo "MATCH_SAMO_REGISTRY_CURRENT_BRIDGE_AUDIT_V39_TEST_OK\n";
