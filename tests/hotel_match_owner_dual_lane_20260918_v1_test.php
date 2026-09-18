<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_owner_dual_lane_20260918_v1.php';

function t(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
t(HMD_TV_EXTRA_CAP===300,'extra_cap');
t(HMD_A_BATCH_SIZE===30,'batch30');
t(HMD_B_MAX_CONTEXTS===3,'b_contexts');
$x=hmd_detail_link(['operatorLink'=>'https://online.anextour.ru/x?HOTELLIST=5844']);
t($x['native_ids']===[5844],'hotellist');
t(hmd_room_key('DELUXE FAMILY SEA VIEW ROOM')==='deluxe family sea view','room_keep_qualifiers');
t(hmd_room_key('ROOM STANDARD')==='standard','room_drop_generic');
t(hmd_room_key('FAMILY SUITE')==='family suite','family_suite');\nt(hmd_room_key('HOTEL FAMILY ROOM')==='family hotel','room_only_generic_room_removed');\nt(hmd_detail_kill(7,7)===false,'detail_kill_minimum_sample');\nt(hmd_detail_kill(8,2)===true,'detail_kill_25pct');\nt(hmd_detail_kill(12,2)===false,'detail_kill_below_25pct');\nt(hmd_country_key('Турция')==='turkey'&&hmd_country_key('Turkey')==='turkey','country_key');\n$d=hmd_dist(36.713018,31.563078,36.7130180,31.5630780);t($d!==null&&$d<1,'geo_distance');
$front=[];$obs=[];for($i=1;$i<=35;$i++){$front[$i]=['id'=>$i,'country_id'=>4,'region_id'=>10,'region_name'=>'Antalya','subregion_name'=>'','category'=>5,'name'=>'Hotel '.$i];$obs[]=['hotel_id'=>$i,'departure_id'=>1,'country_id'=>4,'departure_date'=>'2026-10-01','nights'=>7,'obs'=>100-$i];}
$a=hmd_plan_a($obs,$front);t(count($a['target_ids'])===35,'plan_a_all_under60');t($a['date_from']==='2026-10-01'&&$a['date_to']==='2026-10-07','plan_a_week');t($a['mode_nights']===7,'plan_a_nights');
$b=hmd_plan_b($obs,$front);t(count($b)>=1,'plan_b_exists');t($b[0]['region_id']===10&&$b[0]['star']===5&&$b[0]['date_from']==='2026-10-01'&&$b[0]['date_to']==='2026-10-07','plan_b_resort_star_window');\nt(hmd_offer_date('05.10.2026')==='2026-10-05'&&hmd_offer_date('20261005')==='2026-10-05'&&hmd_offer_date('2026-10-05')==='2026-10-05','provider_offer_dates');
$m=hmd_tv_merge([['id'=>10,'tours'=>[['id'=>'a','roomType'=>'FAMILY SEA VIEW']]]],[['id'=>10,'tours'=>[['id'=>'b','roomType'=>'DELUXE SEA VIEW']]],['id'=>11,'tours'=>[]]]);
$s=hmd_tv_sig($m);t($s===['hotels'=>2,'tours'=>2],'tv_union');
echo "MATCH_OWNER_DUAL_LANE_TEST_OK\n";
