<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require dirname(__DIR__).'/scripts/diagnostics/hotel_match_current_direct_operator_key.php';
function ok($v,$m='assert'): void {if(!$v)throw new RuntimeException($m);}
$p=hmok_pair_input(['anex_hotel_id'=>5844,'andromeda_hotel_id'=>'3414','country_id'=>1,'hotel_name'=>'Swiss Heaven Sharming Inn Hotel','town'=>'Хадаба','operator_key'=>5,'hotel_url'=>'https://agent.anextour.ru/hotels/egypt/swiss','image_url'=>'https://gateway.samo.ru/web/data/hotel/200x200/5.5844.3414.jpg']);ok($p['anex_hotel_id']===5844&&$p['andromeda_hotel_id']==='3414');
try{hmok_pair_input(['anex_hotel_id'=>1,'andromeda_hotel_id'=>'2','country_id'=>1,'hotel_name'=>'Roulette','operator_scoped'=>true,'hotel_url'=>'https://agent.anextour.ru/hotels/egypt/roulette','image_url'=>'https://gateway.samo.ru/web/data/hotel/200x200/5.1.2.jpg']);throw new RuntimeException('quarantine failed');}catch(InvalidArgumentException $e){}
$g=hmok_name_guard(['Domina Coral Bay Prestige Pool'],['Domina Coral Bay Prestige Sea'],['Domina Coral Bay Prestige Pool']);ok($g['qualifier_conflict']===true,'qualifier');
$g=hmok_name_guard(['Empire Hotel Aqua Park'],['Empire Hotel Aqua Park'],['Empire Hotel Aqua Park']);ok($g['current_name_supported']&&$g['target_name_supported']&&!$g['qualifier_conflict'],'name');
$base=['anex'=>['accepted_local'=>10,'manual_hold'=>false,'pair_excluded'=>false,'can_accept'=>false,'status'=>'mapped'],'andromeda'=>['accepted_local'=>null,'can_accept'=>true,'status'=>'pending'],'resolved_local'=>10,'target_country'=>1,'target_occupied_anex'=>false,'target_occupied_andromeda'=>false,'geo'=>['coordinate_conflict'=>false,'direct_geo'=>true],'name_guard'=>['qualifier_conflict'=>false,'current_name_supported'=>true,'target_name_supported'=>true]];
ok(hmok_classify($base,$p)['status']==='candidate_andromeda');$x=$base;$x['andromeda']['accepted_local']=11;ok(hmok_classify($x,$p)['status']==='direct_conflict');$x=$base;$x['andromeda']['accepted_local']=10;ok(hmok_classify($x,$p)['status']==='already_same_local');
$x=$base;$x['anex']['accepted_local']=null;$x['anex']['can_accept']=true;$x['andromeda']['accepted_local']=10;$x['andromeda']['can_accept']=false;ok(hmok_classify($x,$p)['status']==='candidate_anex');
$x=$base;$x['anex']['accepted_local']=null;$x['anex']['can_accept']=true;$x['andromeda']['accepted_local']=null;$x['andromeda']['can_accept']=true;$x['resolved_local']=10;ok(hmok_classify($x,$p)['status']==='candidate_both');
$x=$base;$x['geo']['coordinate_conflict']=true;ok(hmok_classify($x,$p)['status']==='direct_conflict');$x=$base;$x['target_occupied_andromeda']=true;ok(hmok_classify($x,$p)['status']==='provider_occupancy_hold');$x=$base;$x['name_guard']['qualifier_conflict']=true;ok(hmok_classify($x,$p)['status']==='qualifier_conflict');
echo "hotel_match_current_direct_operator_key_test: PASS\n";
