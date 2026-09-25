<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_fingerprint_collision_refinement_v41.php';
function v41t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v41t(V41_OP==='hotel-match-tv-samo-fingerprint-collision-refinement-1971-20260925-v41','op');
$x=v41_shared(['operator_315'=>['1','2']],['operator_315'=>['2','3']]);
v41t(($x['operator_315']??[])===['2'],'shared');
$x=v41_classify(['v37_direct_fingerprints'=>['operator_342'=>['9']],'current_operator_lanes'=>['operator_342'=>['9']],'candidate_current_distance_m'=>0,'candidate_current_name_jaccard'=>0.5]);
v41t($x['status']==='duplicate_local_hold','dup');
echo "MATCH_TV_SAMO_FINGERPRINT_COLLISION_REFINEMENT_V41_TEST_OK\n";
