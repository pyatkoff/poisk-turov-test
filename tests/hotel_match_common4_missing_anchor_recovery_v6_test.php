<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_missing_anchor_recovery_v6.php';
function r6ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
r6ok(r6_norm('MÖVENPICK / Soma-Bay')==='mövenpick soma bay','norm_unicode');
r6ok(r6_classify([])==='no_exact_candidate','no');
r6ok(r6_classify([['identity_state'=>'missing','geo'=>['coordinate_class'=>'unknown']]])==='unique_exact_observation_only','obs');
r6ok(r6_classify([['identity_state'=>'accepted_elsewhere','geo'=>['coordinate_class'=>'unknown']]])==='candidate_accepted_elsewhere_conflict','accepted_elsewhere');
r6ok(r6_classify([['identity_state'=>'pending','geo'=>['coordinate_class'=>'coord_le_1km']]])==='unique_exact_pending_candidate','pending_close');
r6ok(r6_classify([['identity_state'=>'pending','geo'=>['coordinate_class'=>'coord_gt_5km']]])==='unique_exact_pending_geo_conflict','pending_far');
r6ok(r6_classify([['identity_state'=>'pending','geo'=>['coordinate_class'=>'unknown']],['identity_state'=>'pending','geo'=>['coordinate_class'=>'unknown']]])==='ambiguous_exact_candidates','ambiguous');
$g=r6_candidate_geo(['latitude'=>36.8000,'longitude'=>31.9000,'town'=>'Side'],['latitude'=>36.8001,'longitude'=>31.9001,'region_name'=>'Side']);
r6ok(($g['distance_m']??999)<100&&$g['place_match']===true,'geo');
echo "MATCH_COMMON4_MISSING_ANCHOR_RECOVERY_V6_TEST_OK\n";
