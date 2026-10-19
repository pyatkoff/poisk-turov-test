<?php
declare(strict_types=1);
putenv('MATCH_PROVIDER_DIRECT_LOCAL_GEO_APPLY_TEST_LIBRARY=1');
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_direct_local_geo_apply.php';

function ta(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$review=['candidates'=>[]];
for($i=1;$i<=MPGDA_PLAN_COUNT;$i++)$review['candidates'][]=[
 'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(2000000000+$i),'route'=>'auto_accept_candidate','target'=>$i,
 'evidence_sha256'=>str_repeat('a',64),'state_key'=>'4','country_id'=>4,'reason'=>'provider_geo_consensus_unique_exact'
];
$plan=mpgda_plan($review);ta(count($plan)===MPGDA_PLAN_COUNT&&$plan['2000000001']['target']===1,'full immutable plan');

$row=['decision_status'=>'accepted','local_hotel_id'=>10,'evidence_json'=>json_encode(['match_promotion'=>[
 'operation_id'=>MPGDA_OP,'target_local_hotel_id'=>10,'review_operation_id'=>MPGDA_REVIEW_OP,'review_result_sha256'=>MPGDA_REVIEW_SHA256
]],JSON_THROW_ON_ERROR)];
ta(mpgda_readback_ok($row,10),'readback must pass exact provenance');
ta(!mpgda_readback_ok($row,11),'readback wrong target blocked');

$reviewBad=$review;$reviewBad['candidates'][0]['route']='needs_extra_evidence';
$failed=false;try{mpgda_plan($reviewBad);}catch(Throwable){$failed=true;}ta($failed,'non auto candidate rejected');
$reviewConflict=$review;$reviewConflict['candidates'][]=$reviewConflict['candidates'][0];$reviewConflict['candidates'][MPGDA_PLAN_COUNT]['target']=999;
$failed=false;try{mpgda_plan($reviewConflict);}catch(Throwable){$failed=true;}ta($failed,'duplicate conflicting target rejected');

echo "MATCH_PROVIDER_DIRECT_LOCAL_GEO_APPLY_TEST_OK\n";
