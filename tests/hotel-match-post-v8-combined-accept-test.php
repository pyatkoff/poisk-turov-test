<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_post_v8_combined_accept.php';
function t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$a=[['provider'=>'anex','external_id'=>1,'target_local_hotel_id'=>10],['provider'=>'andromeda','external_id'=>'x','target_local_hotel_id'=>20]];
$b=[['provider'=>'anex','external_id'=>1,'target_local_hotel_id'=>10],['provider'=>'anex','external_id'=>2,'target_local_hotel_id'=>30]];
$m=hmpa_merge_rows($a,$b);t(count($m)===3,'union must dedupe same source');t(isset($m['anex:1']['post'],$m['anex:1']['coordinate']),'overlap must retain both origins');
$bad=false;try{hmpa_merge_rows($a,[['provider'=>'anex','external_id'=>1,'target_local_hotel_id'=>99]]);}catch(RuntimeException $e){$bad=$e->getMessage()==='allow_source_target_conflict';}t($bad,'same source different target must hard fail');
$p=['critical_ok'=>true,'shared'=>1,'shared_tokens'=>['vela'],'exact_bag'=>true,'anchor_ok'=>true,'ordered'=>true,'score'=>1.0,'token_diff'=>0];t(hmpa_coordinate_safe(['bucket'=>'prepared','reason'=>'coordinate_single_token_ultratight_place','pair'=>$p,'place_match'=>true,'distance_m'=>10]),'ultratight coordinate single should pass');t(!hmpa_coordinate_safe(['bucket'=>'prepared','reason'=>'coordinate_single_token_ultratight_place','pair'=>$p,'place_match'=>true,'distance_m'=>90]),'loose coordinate single must block');
$p2=['critical_ok'=>true,'shared'=>2,'shared_tokens'=>['trang','phu'],'exact_bag'=>false,'anchor_ok'=>true,'ordered'=>true,'score'=>0.8,'token_diff'=>1];t(hmpa_coordinate_safe(['bucket'=>'prepared','reason'=>'coordinate_two_token_nearest','pair'=>$p2,'place_match'=>false,'distance_m'=>200]),'two-brand coordinate candidate should pass');
$p2['shared_tokens']=['briza','beach'];t(!hmpa_coordinate_safe(['bucket'=>'prepared','reason'=>'coordinate_two_token_nearest','pair'=>$p2,'place_match'=>true,'distance_m'=>100]),'critical qualifier cannot be second brand token');
echo "combined accept guards ok\n";
