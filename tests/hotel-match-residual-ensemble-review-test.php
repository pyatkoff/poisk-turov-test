<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_residual_ensemble_review.php';

function hmre_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
function hmre_ev(string $source,int $target,?float $distance=10.0,bool $critical=true): array {
    return ['source'=>$source,'target_local_hotel_id'=>$target,'rule'=>'test','distance_m'=>$distance,'row'=>['name'=>['critical_ok'=>$critical]]];
}

$baseAuto=['bucket'=>'auto_accept','reason'=>'unique_strict_name_country','target'=>['local_hotel_id'=>10]];
$d=hmre_decide($baseAuto,[]);
hmre_assert($d['bucket']==='needs_extra_evidence','base auto without fresh ensemble evidence must demote');
hmre_assert($d['reason']==='auto_candidate_failed_strict_ensemble_guard','demotion reason changed');

$d=hmre_decide(['bucket'=>'needs_extra_evidence','reason'=>'medium_fuzzy_geo'],[hmre_ev('current_strict',10)]);
hmre_assert($d['bucket']==='auto_accept'&&$d['target']===10,'single fresh strict target must auto');
hmre_assert($d['reason']==='single_strong_residual_identity','single-source reason changed');

$d=hmre_decide(['bucket'=>'needs_extra_evidence','reason'=>'supplier_evidence_needed'],[
    hmre_ev('direct_details_coordinate',10),hmre_ev('same_sold_date_tourvisor',10),
]);
hmre_assert($d['bucket']==='auto_accept'&&$d['target']===10,'agreeing direct evidence must auto');
hmre_assert($d['reason']==='multi_source_residual_identity','multi-source reason changed');
hmre_assert($d['sources']===['direct_details_coordinate','same_sold_date_tourvisor'],'source ordering changed');

$d=hmre_decide(['bucket'=>'needs_extra_evidence','reason'=>'supplier_evidence_needed'],[
    hmre_ev('direct_details_coordinate',10),hmre_ev('same_sold_date_tourvisor',11),
]);
hmre_assert($d['bucket']==='hard_conflict','independent target disagreement must hard block');
hmre_assert($d['reason']==='independent_evidence_target_conflict','target disagreement reason changed');

$baseConflict=['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km','target'=>['local_hotel_id'=>10]];
$d=hmre_decide($baseConflict,[hmre_ev('direct_details_coordinate',10)]);
hmre_assert($d['bucket']==='hard_conflict','one direct source must not override >5km historical conflict');
hmre_assert($d['reason']==='coordinate_conflict_requires_two_independent_direct_sources','coordinate override guard changed');

$d=hmre_decide($baseConflict,[hmre_ev('direct_details_coordinate',10),hmre_ev('same_sold_date_tourvisor',10)]);
hmre_assert($d['bucket']==='auto_accept'&&$d['target']===10,'two agreeing independent direct sources should resolve historical coordinate conflict');

$d=hmre_decide(['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected'],[hmre_ev('current_strict',10)]);
hmre_assert($d['bucket']==='hard_conflict'&&$d['reason']==='pair_exclusion_protected','pair exclusion must be absolute');

hmre_assert(hmre_evidence_row('x',['target_local_hotel_id'=>10,'name'=>['critical_ok'=>false],'distance_m'=>1])===null,'critical qualifier mismatch must not become evidence');
hmre_assert(hmre_evidence_row('x',['target_local_hotel_id'=>10,'name'=>['critical_ok'=>true],'distance_m'=>5000.01])===null,'>5km evidence must be rejected');
$ok=hmre_evidence_row('x',['target_local_hotel_id'=>10,'name'=>['critical_ok'=>true],'distance_m'=>4999.99]);
hmre_assert(is_array($ok)&&$ok['target_local_hotel_id']===10,'<=5km evidence unexpectedly rejected');

$thrown=false;try{hmre_validate_inputs([],[],[],[]);}catch(RuntimeException $e){$thrown=$e->getMessage()==='HMRE_BASE_INVALID';}
hmre_assert($thrown,'invalid immutable/current inputs must fail closed');

echo "hotel-match-residual-ensemble-review-test: OK\n";
