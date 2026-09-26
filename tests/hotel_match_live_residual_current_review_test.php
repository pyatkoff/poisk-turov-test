<?php
declare(strict_types=1);
putenv('MATCH_TEST_LIBRARY=1');
require __DIR__ . '/../scripts/diagnostics/hotel_match_live_residual_current_review.php';
function t(bool $ok,string $msg):void{if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}}
t(mcr_norm('Sunrise Garden RESORT (EX. Old Hotel)')==='sunrise garden','generic/ex normalization');
t(mcr_norm('Blue SPA Hotel')==='blue','generic lodging tokens');
t(mcr_qualifier_ok('Sunrise Garden','Sunrise Garden Hotel'),'same qualifier');
t(!mcr_qualifier_ok('Sunrise Garden','Sunrise Beach'),'meaningful qualifier differs');
t(abs(mcr_score('Grand Emin Hotel','Grand Emin Resort')-1.0)<0.0001,'generic-insensitive score');
$d=mcr_distance(['latitude'=>36.713018,'longitude'=>31.563078],['latitude'=>36.713018,'longitude'=>31.563078]);t($d!==null&&$d<0.001,'distance exact');
$hotels=[10=>['id'=>10,'country_id'=>4,'name'=>'Grand Emin Hotel','region_name'=>null,'subregion_name'=>null,'latitude'=>null,'longitude'=>null],11=>['id'=>11,'country_id'=>4,'name'=>'Grand Beach Emin','region_name'=>null,'subregion_name'=>null,'latitude'=>null,'longitude'=>null]];
$forms=[10=>['Grand Emin Hotel'],11=>['Grand Beach Emin']];$exact=[4=>['grand emin'=>[10],'grand beach emin'=>[11]]];$tokens=[4=>['grand'=>[10=>true,11=>true],'emin'=>[10=>true,11=>true],'beach'=>[11=>true]]];
$r=mcr_select_candidate(['Grand Emin Resort'],4,[],$hotels,$forms,$exact,$tokens);t(($r['route']??'')==='auto_accept_candidate'&&($r['target']??0)===10,'unique exact accepted');
$r=mcr_select_candidate(['Grand Beach Emin'],4,[],$hotels,$forms,$exact,$tokens);t(($r['route']??'')==='auto_accept_candidate'&&($r['target']??0)===11,'qualifier exact accepted');
$hotels[10]['latitude']=36.0;$hotels[10]['longitude']=31.0;$r=mcr_select_candidate(['Grand Emin'],4,[['latitude'=>37.0,'longitude'=>31.0]],$hotels,$forms,$exact,$tokens);t(($r['route']??'')==='hard_conflict'&&($r['reason']??'')==='exact_name_coordinate_conflict_gt5km','gt5km blocked');
t(mcr_is_core8_name('Египет')&&mcr_is_core8_name('Turkey')&&!mcr_is_core8_name('Россия'),'core8 filter');
$e=['source'=>['name'=>'X','stateKey'=>5],'provider_bridges'=>[['andromeda_hotel_id'=>'123'],['andromeda_hotel_id'=>'123'],['andromeda_hotel_id'=>'0']]];t(mcr_state_key($e)==='5'&&mcr_provider_bridges($e)===['123'],'evidence extraction');
$g=mcr_direct_target_guard(['Greenport Hotel'],[['latitude'=>36.1,'longitude'=>31.1]],['name'=>'Greenport Resort','latitude'=>36.1,'longitude'=>31.1]);t(($g['ok']??false)===true,'direct target accepted');
$g=mcr_direct_target_guard(['Greenport Beach'],[],['name'=>'Greenport Garden','latitude'=>null,'longitude'=>null]);t(($g['ok']??true)===false&&($g['reason']??'')==='direct_target_qualifier_conflict','direct qualifier blocked');
$geoHotels=[20=>['id'=>20,'country_id'=>4,'name'=>'SUREYYA HOTEL LALELI','region_name'=>'Стамбул','subregion_name'=>'Лалели','latitude'=>null,'longitude'=>null],21=>['id'=>21,'country_id'=>4,'name'=>'SUREYYA BEACH HOTEL LALELI','region_name'=>'Стамбул','subregion_name'=>'Лалели','latitude'=>null,'longitude'=>null]];
$geoForms=[20=>['SUREYYA HOTEL LALELI'],21=>['SUREYYA BEACH HOTEL LALELI']];$geoExact=mcr_geo_exact_index($geoHotels,$geoForms);
t(isset($geoExact[4]['sureyya'][20]),'own geography suffix indexed');
$r=mcr_select_candidate(['Sureyya Hotel'],4,[],$geoHotels,$geoForms,[],[4=>['sureyya'=>[20=>true,21=>true]]],$geoExact);t(($r['route']??'')==='auto_accept_candidate'&&($r['reason']??'')==='unique_exact_name_plus_own_geography'&&($r['target']??0)===20,'geo suffix exact accepted');
$r=mcr_select_candidate(['Sureyya Beach Hotel'],4,[],$geoHotels,$geoForms,[],[4=>['sureyya'=>[20=>true,21=>true],'beach'=>[21=>true]]],$geoExact);t(($r['route']??'')==='auto_accept_candidate'&&($r['target']??0)===21,'meaningful qualifier preserved in geo suffix match');
$geoHotels[20]['latitude']=36.0;$geoHotels[20]['longitude']=31.0;$r=mcr_select_candidate(['Sureyya Hotel'],4,[['latitude'=>37.0,'longitude'=>31.0]],$geoHotels,$geoForms,[],[4=>['sureyya'=>[20=>true,21=>true]]],$geoExact);t(($r['route']??'')!=='auto_accept_candidate','geo suffix gt5km not auto accepted');
$north=['id'=>30,'country_id'=>1,'name'=>'FOO NORTH','region_name'=>'North','subregion_name'=>null,'latitude'=>null,'longitude'=>null];t(mcr_geo_strip_form('FOO NORTH',$north)===null,'meaningful north geography not stripped');
echo "hotel_match_live_residual_current_review_test: OK\n";
