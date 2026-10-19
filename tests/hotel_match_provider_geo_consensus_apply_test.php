<?php
declare(strict_types=1);
putenv('MATCH_PROVIDER_GEO_APPLY_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_provider_geo_consensus_apply.php';
function mpga_t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$review=['candidates'=>[]];for($i=1;$i<=MPGA_PLAN_COUNT;$i++)$review['candidates'][]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(900000+$i),'route'=>'auto_accept_candidate','target'=>$i,'evidence_sha256'=>str_repeat('a',64),'state_key'=>'4','country_id'=>4];$plan=mpga_plan($review);mpga_t(count($plan)===MPGA_PLAN_COUNT&&$plan['900001']['target']===1,'full plan');
$anchors=['usable'=>[4=>['grand emin'=>['local_hotel_id'=>10,'local_hotel_name'=>'Grand Emin','anchor_count'=>2,'accepted_external_ids'=>['1','2'],'accepted_raw_names'=>['Grand Emin']],'other palace'=>['local_hotel_id'=>11,'local_hotel_name'=>'Other Palace','anchor_count'=>2,'accepted_external_ids'=>['3','4'],'accepted_raw_names'=>['Other Palace']]]],'ambiguous'=>[],'weak'=>[]];
$g=mpga_name_guard(['Grand Emin'],4,$anchors,10);mpga_t(($g['ok']??false)===true,'same accepted name anchor must pass');
$g=mpga_name_guard(['Grand Emin'],4,$anchors,11);mpga_t(($g['ok']??true)===false&&($g['reason']??'')==='accepted_name_anchor_other_target','conflicting accepted name anchor must hold');
$g=mpga_name_guard(['Unknown Multi Name'],4,$anchors,10);mpga_t(($g['ok']??false)===true,'absence of learned name must not veto stronger geo evidence');
$row=['decision_status'=>'accepted','local_hotel_id'=>10,'evidence_json'=>json_encode(['match_promotion'=>['operation_id'=>MPGA_OP,'target_local_hotel_id'=>10,'review_result_sha256'=>MPGA_REVIEW_SHA256]],JSON_THROW_ON_ERROR)];mpga_t(mpga_readback_ok($row,10),'readback helper');mpga_t(!mpga_readback_ok($row,11),'wrong target readback blocked');
echo "MATCH_PROVIDER_GEO_APPLY_TEST_OK\n";
