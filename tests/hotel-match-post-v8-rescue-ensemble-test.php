<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_post_v8_rescue_ensemble.php';
function t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$base=['critical_ok'=>true,'shared'=>2,'shared_tokens'=>['sheraton','miramar'],'exact_bag'=>true,'anchor_ok'=>true,'ordered'=>true,'score'=>1.0,'token_diff'=>0];
t(hmpv8_pair_safe(['pair'=>$base,'reason'=>'cross_provider_generic_free_exact','place_match'=>true,'distance_m'=>null,'score_margin'=>1.0],true),'ordered exact brand pair should pass');
$green=$base;$green['shared_tokens']=['villa','green'];$green['anchor_ok']=false;$green['ordered']=false;
t(!hmpv8_pair_safe(['pair'=>$green,'reason'=>'cross_provider_generic_free_exact','place_match'=>true,'distance_m'=>null,'score_margin'=>1.0],true),'Villa Green reorder must block');
$briza=$base;$briza['exact_bag']=false;$briza['shared_tokens']=['briza','beach'];$briza['score']=0.8;$briza['anchor_ok']=true;$briza['ordered']=true;$briza['token_diff']=1;
t(!hmpv8_pair_safe(['pair'=>$briza,'reason'=>'cross_provider_high_overlap_direct_geo','place_match'=>true,'distance_m'=>null,'score_margin'=>0.3],true),'critical beach cannot be second identity token');
$jaz=$briza;$jaz['shared_tokens']=['jaz','aquaviva'];
t(hmpv8_pair_safe(['pair'=>$jaz,'reason'=>'cross_provider_high_overlap_direct_geo','place_match'=>true,'distance_m'=>null,'score_margin'=>0.3],true),'two real brand tokens direct geo should pass');
$hagia=$briza;$hagia['shared']=7;$hagia['shared_tokens']=['hagia','sofia','mansions','curio','collection','by','hilton'];$hagia['score']=0.93;$hagia['token_diff']=1;
t(hmpv8_pair_safe(['pair'=>$hagia,'reason'=>'cross_provider_high_overlap_no_geo','place_match'=>false,'distance_m'=>null,'score_margin'=>0.4],true),'strong no-geo opposite bridge should pass');
t(!hmpv8_pair_safe(['pair'=>$hagia,'reason'=>'cross_provider_high_overlap_no_geo','place_match'=>false,'distance_m'=>null,'score_margin'=>0.4],false),'no-geo without bridge must block');
$single=['critical_ok'=>true,'shared'=>1,'shared_tokens'=>['vela'],'exact_bag'=>true,'anchor_ok'=>true,'ordered'=>true,'score'=>1.0,'token_diff'=>0];
t(hmpv8_pair_safe(['pair'=>$single,'reason'=>'tourvisor_single_token_ultratight_place','place_match'=>true,'distance_m'=>20,'score_margin'=>1.0],false),'ultratight single place should pass');
t(!hmpv8_pair_safe(['pair'=>$single,'reason'=>'tourvisor_single_token_ultratight_place','place_match'=>true,'distance_m'=>120,'score_margin'=>1.0],false),'loose single token must block');
echo "post-v8 promotion guards ok\n";
