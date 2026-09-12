<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_andromeda_component_consensus_review.php';

function cc_assert(bool $ok,string $msg):void{if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}}
$adj=['a:1'=>['d:2'=>true],'d:2'=>['a:1'=>true,'a:3'=>true],'a:3'=>['d:2'=>true],'a:9'=>['d:8'=>true],'d:8'=>['a:9'=>true]];
$c=hmaccr_components($adj);
cc_assert(count($c)===2,'component count');
cc_assert(count($c[0])===3,'largest component first');
cc_assert(hmaccr_route_rank('mutual_exact_3plus')>hmaccr_route_rank('mutual_exact_no_geo'),'route rank');
cc_assert(hmaccr_provider('a:123')==='anex'&&hmaccr_provider('d:123')==='andromeda','provider parse');
cc_assert(hmaccr_id('d:2000074069')==='2000074069','id parse');
$src=['names'=>['Crystal Sands'],'places'=>['Хиккадува'],'latitude'=>6.086596,'longitude'=>80.145012];
$t=['name'=>'CRYSTAL SANDS GALLE','region_name'=>'Галле','subregion_name'=>null,'latitude'=>6.08663,'longitude'=>80.14501];
$edge=['route'=>'mutual_exact_geo','identity_anchors'=>['identity_aligned'=>2]];
$g=hmaccr_source_local_guard($src,$t,['CRYSTAL SANDS GALLE'],$edge);
cc_assert(($g['bucket']??'')==='prepared','exact local guard prepared');
cc_assert(($g['distance_m']??999)<10,'direct coordinate');
$bad=$src;$bad['latitude']=7.0;$bad['longitude']=81.0;
$g2=hmaccr_source_local_guard($bad,$t,['CRYSTAL SANDS GALLE'],$edge);
cc_assert(($g2['bucket']??'')==='hard_conflict','gt5km block');
echo "component consensus helpers ok\n";
