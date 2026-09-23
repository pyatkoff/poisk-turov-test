<?php
declare(strict_types=1);

require_once __DIR__.'/../scripts/diagnostics/hotel_match_live234_secondary_cross_source_v1.php';

function need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}

$edges=[
    ['tv_hotel_id'=>1,'operator_id'=>25,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>[111]],
    ['tv_hotel_id'=>2,'operator_id'=>43,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['222']],
    ['tv_hotel_id'=>3,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['333']],
    ['tv_hotel_id'=>4,'operator_id'=>25,'state'=>'detail_identity_mismatch','link_state'=>'captured_single_native','positive_native_candidates'=>['444']],
    ['tv_hotel_id'=>5,'operator_id'=>43,'state'=>'detail_identity_verified','link_state'=>'captured_ambiguous_native','positive_native_candidates'=>['555','556']],
];
$obs=[
    ['supplier_namespace'=>'operator_315','external_hotel_id'=>'111','observed_at_utc'=>'2026-09-23 10:00:00'],
    ['supplier_namespace'=>'operator_342','external_hotel_id'=>'222','observed_at_utc'=>'2026-09-23 10:01:00'],
];
$out=AnyTourMatchLive234SecondaryCrossSourceV1::reconcile($edges,$obs,[],[],'2026-09-23 09:00:00');
need($out['writer_ready_count']===2,'ready_count');
need(($out['state_counts']['writer_ready_exact_cross_source']??0)===2,'ready_state');
need(($out['state_counts']['bg_namespace_bridge_unproven']??0)===1,'bg_hold');
need(($out['state_counts']['tv_detail_not_verified']??0)===1,'detail_hold');
need(($out['state_counts']['tv_not_single_native']??0)===1,'ambiguous_hold');
need($out['provider_http_calls']===0&&$out['database_writes']===0&&$out['mapping_writes']===0,'read_only');

$accepted=[
    ['supplier_namespace'=>'operator_315','external_hotel_id'=>'111','local_hotel_id'=>1,'decision_status'=>'accepted'],
];
$out=AnyTourMatchLive234SecondaryCrossSourceV1::reconcile([$edges[0]],[$obs[0]],$accepted,[],'2026-09-23 09:00:00');
need(($out['state_counts']['resolved_same_secondary']??0)===1,'resolved_same');

$stale=[
    ['supplier_namespace'=>'operator_315','external_hotel_id'=>'111','observed_at_utc'=>'2026-09-23 08:59:59'],
];
$out=AnyTourMatchLive234SecondaryCrossSourceV1::reconcile([$edges[0]],$stale,[],[],'2026-09-23 09:00:00');
need(($out['state_counts']['andromeda_exact_native_stale']??0)===1,'stale');

$missing=[];
$out=AnyTourMatchLive234SecondaryCrossSourceV1::reconcile([$edges[0]],$missing,[],[],'2026-09-23 09:00:00');
need(($out['state_counts']['andromeda_exact_native_not_observed']??0)===1,'missing');

echo "MATCH_LIVE234_SECONDARY_CROSS_SOURCE_V1_TEST_OK\n";
