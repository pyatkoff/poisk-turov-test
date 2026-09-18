<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_pending_bridge_accept.php';
function pba_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$row=['country_id'=>2,'target_local_hotel_id'=>2958,'source_names'=>['Baramee Resortel Phuket'],'source_places'=>['Пхукет'],'strict_identity'=>['source'=>'Baramee Resortel Phuket','target'=>'BARAMEE RESORTEL','identity_tokens'=>['baramee','resortel']],'source_category'=>3,'target_category'=>3,'category_mismatch'=>false];
$e=pba_evidence(PBA_OPERATION,$row,['candidate_ids'=>[2958]]);
pba_assert($e['promotion']['lane']==='MATCH','lane');
pba_assert($e['promotion']['target']===2958,'target');
pba_assert($e['promotion']['server_current']===true,'server_current');
pba_assert($e['prior_evidence']['candidate_ids']===[2958],'prior preserved');
pba_assert(pcbr_strict_pair(['Pickalbatros Aqua Park Resort Hurghada'],['GRAVITY HOTEL & AQUA PARK HURGHADA'],'Хургада')===null,'unsafe brand swap stays blocked');
echo "ok\n";
