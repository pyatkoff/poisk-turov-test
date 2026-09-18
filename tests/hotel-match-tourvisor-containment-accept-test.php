<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tourvisor_containment_accept.php';
function htca_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

$pair=habfr_best_policy(['RIXOS PREMIUM BELEK'],['RIXOS PREMIUM CLUB BELEK'],'Белек');
$row=[
    'rule'=>'tourvisor_one_sided_identity_plus_direct_geo','target_local_hotel_id'=>123,'country_id'=>4,
    'pair'=>$pair,'score_margin'=>0.25,'distance_m'=>null,'source_places'=>['Белек'],'target_region'=>'Белек','target_subregion'=>'',
];
htca_assert(htca_candidate_safe($row)===true,'guarded containment candidate accepted');

$bad=$row;$bad['score_margin']=0.10;
htca_assert(htca_candidate_safe($bad)===false,'small target margin blocked');
$bad=$row;$bad['distance_m']=5001;
htca_assert(htca_candidate_safe($bad)===false,'coordinate conflict over 5km blocked');
$bad=$row;$bad['source_places']=['Анталья'];
htca_assert(htca_candidate_safe($bad)===false,'missing direct saved geography blocked');
$bad=$row;$bad['pair']=habfr_best_policy(['SUNRISE GARDEN'],['SUNRISE BEACH GARDEN'],'Хургада');
htca_assert(htca_candidate_safe($bad)===false,'critical qualifier mismatch blocked');
$bad=$row;$bad['rule']='tourvisor_high_fuzzy_evidence_only';
htca_assert(htca_candidate_safe($bad)===false,'evidence-only rule cannot write');

echo "ok\n";
