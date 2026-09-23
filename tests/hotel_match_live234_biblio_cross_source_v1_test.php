<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live234_biblio_cross_source_v1.php';
function bg_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}

$edge=['tv_hotel_id'=>101,'operator_id'=>18,'state'=>'detail_identity_verified','link_state'=>'captured_single_native','positive_native_candidates'=>['9001']];
$obs=['supplier_namespace'=>'operator_115','external_hotel_id'=>'9001','observed_at_utc'=>'2026-09-23 10:00:00'];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],[$obs],[],[],'2026-09-23 09:00:00');
bg_need($out['writer_ready_count']===1,'exact_ready');
bg_need(($out['writer_ready'][0]['namespace']??null)==='operator_115','namespace');
bg_need(($out['writer_ready'][0]['external_hotel_id']??null)==='9001','external');

$legacy=['supplier_namespace'=>'bgoperator','external_hotel_id'=>'9001','observed_at_utc'=>'2026-09-23 10:00:00'];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],[$legacy],[],[],'2026-09-23 09:00:00');
bg_need(($out['state_counts']['andromeda_operator_115_exact_native_not_observed']??0)===1,'legacy_namespace_not_equated');

$accepted=[['supplier_namespace'=>'operator_115','external_hotel_id'=>'9001','local_hotel_id'=>101,'decision_status'=>'accepted']];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],[$obs],$accepted,[],'2026-09-23 09:00:00');
bg_need(($out['state_counts']['resolved_same_secondary']??0)===1,'resolved_same');

$blocked=[['supplier_namespace'=>'operator_115','external_hotel_id'=>'9001','local_hotel_id'=>101]];
$out=AnyTourMatchLive234BiblioCrossSourceV1::reconcile([$edge],[$obs],[],$blocked,'2026-09-23 09:00:00');
bg_need(($out['state_counts']['manual_or_exclusion_block']??0)===1,'blocked');

echo "MATCH_LIVE234_BIBLIO_CROSS_SOURCE_V1_TEST_OK\n";
