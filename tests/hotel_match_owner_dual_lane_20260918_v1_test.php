<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_owner_dual_lane_20260918_v1.php';

function t(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
function qhome():string{$p=sys_get_temp_dir().'/hmd-quota-'.bin2hex(random_bytes(6));if(!mkdir($p,0700,true))throw new RuntimeException('quota_tmp');return$p;}
function qclean(string $home):void{foreach([hmd_tv_operation_path($home),hmd_tv_daily_path($home)]as$p)if(is_file($p))unlink($p);$q=rtrim($home,'/').'/.anytour-match/provider-quotas';if(is_dir($q))rmdir($q);$m=rtrim($home,'/').'/.anytour-match';if(is_dir($m))rmdir($m);if(is_dir($home))rmdir($home);}
t(HMD_TV_DAILY_LIMIT===3000,'daily_cap');
t(HMD_TV_OPERATION_CAP===300,'operation_cap');
t(HMD_TV_EXTRA_CAP===0,'legacy_supplemental_disabled');
t(HMD_A_BATCH_SIZE===30,'batch30');
t(HMD_B_MAX_CONTEXTS===3,'b_contexts');
$x=hmd_detail_link(['operatorLink'=>'https://online.anextour.ru/x?HOTELLIST=5844']);
t($x['native_ids']===[5844],'hotellist');
t(hmd_room_key('DELUXE FAMILY SEA VIEW ROOM')==='deluxe family sea view','room_keep_qualifiers');
t(hmd_room_key('ROOM STANDARD')==='standard','room_drop_generic');
t(hmd_room_key('FAMILY SUITE')==='family suite','family_suite');
t(hmd_room_key('HOTEL FAMILY ROOM')==='family hotel','room_only_generic_room_removed');
t(hmd_detail_kill(7,7)===false,'detail_kill_minimum_sample');
t(hmd_detail_kill(8,2)===true,'detail_kill_25pct');
t(hmd_detail_kill(12,2)===false,'detail_kill_below_25pct');
t(hmd_country_key('Турция')==='turkey'&&hmd_country_key('Turkey')==='turkey','country_key');
t(hmd_resort_match('Antalya',hmd_resort_aliases('Анталья')),'resort_cross_provider_alias');
$d=hmd_dist(36.713018,31.563078,36.7130180,31.5630780);t($d!==null&&$d<1,'geo_distance');
$front=[];$obs=[];for($i=1;$i<=35;$i++){$front[$i]=['id'=>$i,'country_id'=>4,'region_id'=>10,'region_name'=>'Antalya','subregion_name'=>'','category'=>5,'name'=>'Hotel '.$i];$obs[]=['hotel_id'=>$i,'departure_id'=>1,'country_id'=>4,'departure_date'=>'2026-10-01','nights'=>7,'obs'=>100-$i];}
$a=hmd_plan_a($obs,$front);t(count($a['target_ids'])===35,'plan_a_all_under60');t($a['date_from']==='2026-10-01'&&$a['date_to']==='2026-10-07','plan_a_week');t($a['mode_nights']===7,'plan_a_nights');
$b=hmd_plan_b($obs,$front);t(count($b)>=1,'plan_b_exists');t($b[0]['region_id']===10&&$b[0]['star']===5&&$b[0]['date_from']==='2026-10-01'&&$b[0]['date_to']==='2026-10-07','plan_b_resort_star_window');
t(hmd_offer_date('05.10.2026')==='2026-10-05'&&hmd_offer_date('20261005')==='2026-10-05'&&hmd_offer_date('2026-10-05')==='2026-10-05','provider_offer_dates');
$m=hmd_tv_merge([['id'=>10,'tours'=>[['id'=>'a','roomType'=>'FAMILY SEA VIEW']]]],[['id'=>10,'tours'=>[['id'=>'b','roomType'=>'DELUXE SEA VIEW']]],['id'=>11,'tours'=>[]]]);
$s=hmd_tv_sig($m);t($s===['hotels'=>2,'tours'=>2],'tv_union');
$provider=file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_owner_dual_lane_provider_v1.php');
$ab=substr($provider,strpos($provider,'function hmd_anex_search'),strpos($provider,'function hmd_and_search')-strpos($provider,'function hmd_anex_search'));
t(!str_contains($ab,'SearchTour_TOWNS')&&!str_contains($ab,'SearchTour_STARS')&&!str_contains($ab,"'TOWNTOINC'")&&!str_contains($ab,"'STARS'"),'direct_anex_client_contract');
t(str_contains($ab,"['checkIn']")&&str_contains($provider,"['checkIn']"),'provider_selected_checkin');
$q1=qhome();$p1=hmd_tv_daily_path($q1);file_put_contents($p1,hmd_json(['owner_daily_limit'=>3000,'known_prior_attempt_floor'=>25,'match_new_attempts'=>2975,'accounted_requests'=>3000])."\n");$blocked=false;try{hmd_tv_quota($q1,'T1');}catch(RuntimeException$e){$blocked=$e->getMessage()==='tv_daily_cap';}t($blocked&&hmd_tv_used($q1)===0,'hard_daily_cap_blocks_before_attempt');qclean($q1);
$q2=qhome();$p2=hmd_tv_daily_path($q2);file_put_contents($p2,hmd_json(['owner_daily_limit'=>3000,'known_prior_attempt_floor'=>25,'match_new_attempts'=>2974,'accounted_requests'=>2999])."\n");t(hmd_tv_quota($q2,'T2')===1,'last_daily_slot_reserved');$j=json_decode((string)file_get_contents($p2),true);t(($j['match_new_attempts']??0)===2975&&($j['accounted_requests']??0)===3000&&(($j['operations'][HMD_OP]??0)===1),'daily_ledger_fsynced_before_http');$blocked=false;try{hmd_tv_quota($q2,'T2');}catch(RuntimeException$e){$blocked=$e->getMessage()==='tv_daily_cap';}t($blocked&&hmd_tv_used($q2)===1,'daily_cap_stays_closed');qclean($q2);
echo "MATCH_OWNER_DUAL_LANE_TEST_OK\n";
