<?php
declare(strict_types=1);

require_once __DIR__.'/../scripts/diagnostics/hotel_match_live234_biblio_cross_source_v1.php';

function need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}

$edge = ['tv_hotel_id'=>1,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['111']];
$obs = [['supplier_namespace'=>'operator_115','external_hotel_id'=>'111','observed_at_utc'=>'2026-09-23 10:00:00']];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],$obs,[],[],'2026-09-23 09:00:00');
need($out['writer_ready_count']===1,'ready_count');
need(($out['writer_ready'][0]['namespace']??null)==='operator_115','namespace');
need($out['safe_to_write_now']===false,'writer_guard');

$wrongNamespace=[['supplier_namespace'=>'bgoperator','external_hotel_id'=>'111','observed_at_utc'=>'2026-09-23 10:00:00']];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],$wrongNamespace,[],[],'2026-09-23 09:00:00');
need(($out['state_counts']['andromeda_exact_native_not_observed']??0)===1,'no_cross_namespace_numeric_inference');

$accepted=[['supplier_namespace'=>'operator_115','external_hotel_id'=>'111','local_hotel_id'=>1,'decision_status'=>'accepted']];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],$obs,$accepted,[],'2026-09-23 09:00:00');
need(($out['state_counts']['resolved_same_secondary']??0)===1,'resolved_same');

$stale=[['supplier_namespace'=>'operator_115','external_hotel_id'=>'111','observed_at_utc'=>'2026-09-23 08:59:59']];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],$stale,[],[],'2026-09-23 09:00:00');
need(($out['state_counts']['andromeda_exact_native_stale']??0)===1,'stale');

$blocked=[['supplier_namespace'=>'operator_115','external_hotel_id'=>'111','local_hotel_id'=>1]];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],$obs,[],$blocked,'2026-09-23 09:00:00');
need(($out['state_counts']['manual_or_exclusion_block']??0)===1,'blocked');

$collision=[
    $edge,
    ['tv_hotel_id'=>2,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['111']],
];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile($collision,$obs,[],[],'2026-09-23 09:00:00');
need(($out['state_counts']['tv_source_collision']??0)===2,'source_collision');

$targetOccupied=[['supplier_namespace'=>'operator_115','external_hotel_id'=>'999','local_hotel_id'=>1,'decision_status'=>'accepted']];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],$obs,$targetOccupied,[],'2026-09-23 09:00:00');
need(($out['state_counts']['target_namespace_occupied_other']??0)===1,'target_occupied');

$sourceOccupied=[['supplier_namespace'=>'operator_115','external_hotel_id'=>'111','local_hotel_id'=>9,'decision_status'=>'accepted']];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],$obs,$sourceOccupied,[],'2026-09-23 09:00:00');
need(($out['state_counts']['source_occupied_other']??0)===1,'source_occupied');

$notVerified=$edge;$notVerified['state']='detail_identity_mismatch';
$ambiguous=$edge;$ambiguous['link_state']='captured_ambiguous_native';$ambiguous['positive_native_candidates']=['111','112'];
$wrongOperator=$edge;$wrongOperator['operator_id']=25;
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$notVerified,$ambiguous,$wrongOperator],$obs,[],[],'2026-09-23 09:00:00');
need(($out['state_counts']['tv_detail_not_verified']??0)===1,'detail_guard');
need(($out['state_counts']['tv_not_single_native']??0)===1,'single_guard');
need(($out['state_counts']['operator_not_biblio']??0)===1,'operator_guard');
need($out['provider_http_calls']===0&&$out['database_writes']===0&&$out['mapping_writes']===0,'read_only');

echo "MATCH_LIVE234_BIBLIO_CROSS_SOURCE_V1_TEST_OK\n";
