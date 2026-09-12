<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_exact_ordered_literal_mass_review.php';
function tassert3(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}

tassert3(hmelmr_tokens('Ramee Rose Hotel')===['ramee','rose'],'HOTEL should drop');
tassert3(hmelmr_tokens('Ramee Rose Hotel Apartments')===['ramee','rose','apartments'],'APARTMENTS must remain significant');
tassert3(hmelmr_tokens('Royal Club Suite & Spa Resort')===['royal','club','suite'],'CLUB and SUITE must remain significant');
tassert3(hmelmr_tokens('Nova City Hotel Istanbul')===['nova','city','istanbul'],'CITY must remain significant');
tassert3(hmelmr_tokens('Dong Duong')===['dong','duong']&&hmelmr_tokens('Duong Dong')===['duong','dong'],'order retained');
$hotels=[101=>['country_id'=>9,'name'=>'RAMEE ROSE HOTEL APARTMENTS','region_name'=>'','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>3],102=>['country_id'=>4,'name'=>'GOLDEN AGE','region_name'=>'','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>4],103=>['country_id'=>16,'name'=>'DUONG DONG','region_name'=>'','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>3]];
$names=[101=>['RAMEE ROSE HOTEL APARTMENTS'],102=>['GOLDEN AGE'],103=>['DUONG DONG']];$idx=hmelmr_build_index($hotels,$names);
tassert3(hmelmr_targets(['names'=>['Ramee Rose Hotel'],'places'=>[],'latitude'=>null,'longitude'=>null],9,$idx,$hotels,$names)===[],'missing APARTMENTS qualifier must not match');
tassert3(array_keys(hmelmr_targets(['names'=>['Golden Age Hotel'],'places'=>[],'latitude'=>null,'longitude'=>null],4,$idx,$hotels,$names))===[102],'generic HOTEL difference may match');
tassert3(hmelmr_targets(['names'=>['Dong Duong'],'places'=>[],'latitude'=>null,'longitude'=>null],16,$idx,$hotels,$names)===[],'token order swap must not match');
echo "hotel match exact ordered literal mass review tests passed\n";
