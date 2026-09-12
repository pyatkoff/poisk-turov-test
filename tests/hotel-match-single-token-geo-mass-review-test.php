<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_single_token_geo_mass_review.php';
function tassert4(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
tassert4(hmstg_keys(['Seray Hotel'])['seray']['token']==='seray','single literal token expected');
tassert4(hmstg_keys(['Ramee Rose Hotel'])===[],'two-token name must not enter single-token lane');
$hotels=[101=>['country_id'=>4,'name'=>'SERAY','region_name'=>'Мармарис','subregion_name'=>'','latitude'=>36.8,'longitude'=>28.2,'category'=>3],102=>['country_id'=>4,'name'=>'SUNRISE','region_name'=>'Анталья','subregion_name'=>'','latitude'=>36.9,'longitude'=>30.7,'category'=>4],103=>['country_id'=>4,'name'=>'SUNRISE','region_name'=>'Кемер','subregion_name'=>'','latitude'=>36.6,'longitude'=>30.5,'category'=>4]];$names=[101=>['SERAY'],102=>['SUNRISE'],103=>['SUNRISE']];$idx=hmstg_index($hotels,$names);
$a=hmstg_targets(['names'=>['Seray Hotel'],'places'=>['Мармарис'],'latitude'=>36.8001,'longitude'=>28.2001],4,$idx,$hotels,$names);tassert4(array_keys($a)===[101],'unique single token should resolve');tassert4($a[101]['place_match']===true,'place confirmation expected');tassert4($a[101]['distance_m']!==null&&$a[101]['distance_m']<1000,'coordinate confirmation expected');
$b=hmstg_targets(['names'=>['Sunrise Hotel'],'places'=>['Анталья'],'latitude'=>null,'longitude'=>null],4,$idx,$hotels,$names);tassert4(count($b)===2,'ambiguous token must remain ambiguous');
echo "hotel match single token geo mass review tests passed\n";
