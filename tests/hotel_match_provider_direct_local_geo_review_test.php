<?php
declare(strict_types=1);

putenv('MATCH_PROVIDER_DIRECT_LOCAL_GEO_TEST_LIBRARY=1');
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_direct_local_geo_review.php';

function td(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}

$hotels=[
 100=>['country_id'=>4,'region_id'=>10,'region_name'=>'Стамбул','subregion_id'=>20,'subregion_name'=>'Лалели'],
 101=>['country_id'=>4,'region_id'=>10,'region_name'=>'Стамбул','subregion_id'=>20,'subregion_name'=>'Лалели'],
 102=>['country_id'=>4,'region_id'=>10,'region_name'=>'Стамбул','subregion_id'=>21,'subregion_name'=>'Султанахмет'],
 200=>['country_id'=>5,'region_id'=>30,'region_name'=>'Дубай','subregion_id'=>40,'subregion_name'=>'Дейра'],
];
$idx=mpgd_local_geo_index($hotels);
td(($idx['usable'][4]['laleli']['scope']??'')==='subregion'&&($idx['usable'][4]['laleli']['scope_id']??0)===20,'unique subregion label');
td(($idx['usable'][4]['stambul']['scope']??'')==='region'&&($idx['usable'][4]['stambul']['scope_id']??0)===10,'unique region label');

$scope=[4=>['region'=>[10=>[100=>true,101=>true,102=>true]],'subregion'=>[20=>[100=>true,101=>true],21=>[102=>true]]],5=>['region'=>[30=>[200=>true]],'subregion'=>[40=>[200=>true]]]];
$d=mpgd_direct_local_ids(['source'=>['name'=>'Sureyya Hotel','townLName'=>'Laleli']],4,$idx,$scope);
td($d['status']==='ok'&&$d['ids']===[100,101],'provider town to local subregion');

$d2=mpgd_direct_local_ids(['source'=>['name'=>'Sureyya Hotel','townLName'=>'Istanbul']],4,$idx,$scope);
td($d2['status']==='no_direct_local_geo','non-identical transliteration not guessed');

$numeric=['usable'=>[],'rejected'=>[]];$labels=['usable'=>[],'rejected'=>[]];
$combined=mpgd_combined_geo_ids(['source'=>['name'=>'Sureyya Hotel','townLName'=>'Laleli']],4,$numeric,$labels,$idx,$scope);
td($combined['status']==='ok'&&$combined['ids']===[100,101]&&$combined['sources']===['direct_local_label'],'direct-only geography allowed');

$numeric=['usable'=>['regionKey=1'=>['country_id'=>4,'scope'=>'region','scope_id'=>10,'field'=>'regionKey','value'=>'1','anchors'=>30,'countries'=>[4=>30],'regions'=>[10=>30],'subregions'=>[]]],'rejected'=>[]];
$combined2=mpgd_combined_geo_ids(['source'=>['name'=>'Sureyya Hotel','townLName'=>'Laleli','regionKey'=>'1']],4,$numeric,$labels,$idx,$scope);
td($combined2['status']==='ok'&&$combined2['ids']===[100,101]&&count($combined2['sources'])===2,'intersects learned and direct geography');

$hotelName=mpgd_direct_local_ids(['source'=>['name'=>'Laleli','townLName'=>'Laleli']],4,$idx,$scope);
td($hotelName['status']==='no_direct_local_geo','hotel-name-equal label excluded');

echo "hotel_match_provider_direct_local_geo_review_test OK\n";
