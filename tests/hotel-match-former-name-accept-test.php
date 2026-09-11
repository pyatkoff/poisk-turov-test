<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_former_name_accept.php';
function hmfna_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$multi=['provider'=>'andromeda','country_id'=>1,'target_local_hotel_id'=>338,'source_name'=>'Novotel Beach','former_name'=>'NOVOTEL BEACH','identity_tokens'=>['novotel','beach'],'distance_m'=>null,'place_match'=>true,'single_token_coordinate_anchor'=>false,'rule'=>'exact_former_name_multi_token_direct_geo'];
hmfna_assert(hmfna_candidate_safe($multi),'multi-token exact former name + geo should pass');
$single=['provider'=>'anex','country_id'=>4,'target_local_hotel_id'=>37382,'source_name'=>'Cosmopolitan Resort','former_name'=>'COSMOPOLITAN RESORT','identity_tokens'=>['cosmopolitan'],'distance_m'=>5.0,'place_match'=>true,'single_token_coordinate_anchor'=>true,'rule'=>'exact_former_name_single_distinctive_coordinate'];
hmfna_assert(hmfna_candidate_safe($single),'distinctive single token <=100m should pass');
$bad=$single;$bad['distance_m']=101.0;hmfna_assert(!hmfna_candidate_safe($bad),'single token >100m blocked');
$bad=$single;$bad['former_name']='Other Resort';hmfna_assert(!hmfna_candidate_safe($bad),'former-name key mismatch blocked');
$bad=$multi;$bad['source_name']='Sunset Beach';$bad['former_name']='Sunset';hmfna_assert(!hmfna_candidate_safe($bad),'critical qualifier mismatch blocked');
$bad=$multi;$bad['place_match']=false;$bad['distance_m']=5001.0;hmfna_assert(!hmfna_candidate_safe($bad),'>5km conflict blocked');
echo "hotel-match-former-name-accept-test: ok\n";
