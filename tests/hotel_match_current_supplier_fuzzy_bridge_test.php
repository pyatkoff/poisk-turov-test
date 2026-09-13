<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_current_supplier_fuzzy_bridge.php';
$checks=0;$assert=static function(bool $ok,string $name)use(&$checks){$checks++;if(!$ok)throw new RuntimeException($name);};

$m=hmsbf_metric(['mira','beach','bodrum'],['beach','bodrum']);
$assert($m['symmetric']<HMSBF_MIN_SYMMETRIC&&$m['score']<HMSBF_MIN_SCORE,'mira_not_lost_by_containment');
$m=hmsbf_metric(['sunrise','diamond','beach','grand','select'],['sunrise','diamond','beach']);
$assert($m['symmetric']<HMSBF_MIN_SYMMETRIC,'grand_select_not_silently_dropped');
$m=hmsbf_metric(['blue','waves','hotel'],['blue','waves','resort']);
$assert($m['common']===2&&$m['score']<HMSBF_MIN_SCORE,'generic_word_difference_not_enough_after_direct_metric');
$assert(hmsbf_qualifier_conflict(['sunrise','diamond','beach'],['sunrise','diamond'])===true,'beach_qualifier_conflict');
$assert(hmsbf_qualifier_conflict(['sunrise','diamond'],['sunrise','diamond'])===false,'same_qualifiers_ok');

$tokenIndex=[];$targetNames=[];
hmsbf_add_target($tokenIndex,$targetNames,4,101,['Alpha Blue Premium Palace']);
hmsbf_add_target($tokenIndex,$targetNames,4,102,['Alpha Green Garden Deluxe']);
$r=hmsbf_rank(['Alpha Blue Premium Palace Hotel'],4,$tokenIndex,$targetNames);
$assert($r['status']==='exact_key_skipped','completed_exact_lane_not_replayed');
$r=hmsbf_rank(['Alpha Blue Premium Palace Collection'],4,$tokenIndex,$targetNames);
$assert($r['status']==='ranked'&&$r['best']['local_id']===101,'best_candidate_ranked');
$assert($r['best']['score']>=HMSBF_MIN_SCORE&&$r['margin']>=HMSBF_MIN_MARGIN,'large_margin_strong_name');
$geo=['coordinate_conflict'=>false,'direct_geo'=>true];
$assert(hmsbf_status($r,$geo)==='strong_fuzzy_geo','strong_requires_direct_geo_for_auto_candidate');
$geo=['coordinate_conflict'=>false,'direct_geo'=>false];
$assert(hmsbf_status($r,$geo)==='strong_fuzzy_needs_independent_evidence','strong_name_without_geo_held');
$geo=['coordinate_conflict'=>true,'direct_geo'=>true];
$assert(hmsbf_status($r,$geo)==='hard_conflict','over_5km_blocks');
$geo=['coordinate_conflict'=>false,'direct_geo'=>true];
$assert(hmsbf_status($r,$geo,true)==='hard_conflict','pair_exclusion_blocks');

$tokenIndex=[];$targetNames=[];
hmsbf_add_target($tokenIndex,$targetNames,4,201,['Alpha Blue Premium Palace']);
hmsbf_add_target($tokenIndex,$targetNames,4,202,['Alpha Blue Premium Collection']);
$r=hmsbf_rank(['Alpha Blue Premium Palace Collection'],4,$tokenIndex,$targetNames);
$assert($r['status']==='ranked'&&$r['best']['score']>=HMSBF_MIN_SCORE&&$r['margin']<HMSBF_MIN_MARGIN,'close_runner_up_is_ambiguous');
$assert(hmsbf_status($r,['coordinate_conflict'=>false,'direct_geo'=>true])==='ambiguous_margin','margin_guard_blocks');

$tokenIndex=[];$targetNames=[];
hmsbf_add_target($tokenIndex,$targetNames,16,301,['Diamond Hotel Phu Quoc']);
$r=hmsbf_rank(['Fortuna Hotel Phu Quoc'],16,$tokenIndex,$targetNames);
$assert($r['status']==='ranked','destination_overlap_is_seen');
$assert(($r['best']['score']??100)<HMSBF_MIN_SCORE,'destination_only_overlap_not_strong');

$rows=[
 ['provider'=>'andromeda','external_id'=>'a','target_local_id'=>77,'status'=>'strong_fuzzy_geo'],
 ['provider'=>'andromeda','external_id'=>'b','target_local_id'=>77,'status'=>'strong_fuzzy_needs_independent_evidence'],
 ['provider'=>'anex','external_id'=>'9','target_local_id'=>77,'status'=>'strong_fuzzy_geo'],
];
$out=hmsbf_collision_hold($rows);
$assert($out[0]['status']==='duplicate_provider_hold'&&$out[1]['status']==='duplicate_provider_hold','same_provider_collision_held');
$assert($out[2]['status']==='strong_fuzzy_geo','other_provider_not_collision');

echo "hotel_match_current_supplier_fuzzy_bridge_test: {$checks} checks PASS\n";
