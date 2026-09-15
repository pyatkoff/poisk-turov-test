<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_strong_evidence_accept.php';
function hsea_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

$andPair=hfsr_pair('RIXOS PREMIUM BELEK','RIXOS PREMIUM CLUB BELEK','Белек',true);
$and=[
    'provider'=>'andromeda','rule'=>'strong_fuzzy_direct_geo_anex_bridge','target_local_hotel_id'=>123,'country_id'=>4,
    'pair'=>$andPair,'score_margin'=>0.20,'existing_anex_link'=>true,'source_places'=>['Белек'],'target_region'=>'Белек','target_subregion'=>'',
];
hsea_assert(hsea_andromeda_safe($and)===true,'safe Andromeda bridge accepted');
$bad=$and;$bad['score_margin']=0.10;hsea_assert(hsea_andromeda_safe($bad)===false,'small Andromeda margin blocked');
$bad=$and;$bad['source_places']=['Кемер'];hsea_assert(hsea_andromeda_safe($bad)===false,'Andromeda missing direct geography blocked');
$bad=$and;$bad['rule']='tourvisor_high_fuzzy_evidence_only';hsea_assert(hsea_andromeda_safe($bad)===false,'Andromeda evidence-only rule blocked');

$anPair=asbr_pair('RIXOS PREMIUM BELEK','RIXOS PREMIUM CLUB BELEK','Белек',true);
$an=[
    'provider'=>'anex','rule'=>'strong_fuzzy_direct_geo_andromeda_bridge','target_local_hotel_id'=>123,'country_id'=>4,
    'pair'=>$anPair,'score_margin'=>0.20,'existing_andromeda_link'=>true,'source_places'=>['Белек'],'target_region'=>'Белек','target_subregion'=>'','distance_m'=>900,
];
hsea_assert(hsea_anex_safe($an)===true,'safe ANEX bridge accepted');
$bad=$an;$bad['distance_m']=5001;hsea_assert(hsea_anex_safe($bad)===false,'ANEX coordinate conflict blocked');
$bad=$an;$bad['source_places']=['Кемер'];hsea_assert(hsea_anex_safe($bad)===false,'ANEX missing direct geography blocked');
$bad=$an;$bad['pair']['critical_ok']=false;hsea_assert(hsea_anex_safe($bad)===false,'ANEX critical qualifier mismatch blocked');

echo "ok\n";
