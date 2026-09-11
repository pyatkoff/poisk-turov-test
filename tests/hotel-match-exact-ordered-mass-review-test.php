<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_exact_ordered_mass_review.php';
function tassert2(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}

tassert2(hmeomr_tokens('Nova City Hotel Istanbul')===['nova','city','istanbul'],'CITY must remain significant while HOTEL drops');
tassert2(hmeomr_tokens('Dong Duong')===['dong','duong'],'source order retained');
tassert2(hmeomr_tokens('Duong Dong')===['duong','dong'],'target order retained');
tassert2(implode(' ',hmeomr_tokens('Dong Duong'))!==implode(' ',hmeomr_tokens('Duong Dong')),'token-order swap must not match');
$vars=hmeomr_name_variants('CORAL SEA WATER WORLD (EX. CORAL SEA RESORT)');
tassert2(in_array('CORAL SEA WATER WORLD',$vars,true)&&in_array('CORAL SEA RESORT',$vars,true),'EX former name must split into aliases');
$hotels=[101=>['country_id'=>4,'name'=>'GOLDEN AGE','region_name'=>'','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>4],102=>['country_id'=>4,'name'=>'DUONG DONG','region_name'=>'','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>3]];$names=[101=>['GOLDEN AGE'],102=>['DUONG DONG']];$idx=hmeomr_build_index($hotels,$names);
$a=hmeomr_targets(['names'=>['Golden Age Hotel'],'places'=>[],'latitude'=>null,'longitude'=>null],4,$idx,$hotels,$names);tassert2(array_keys($a)===[101],'ordered exact significant alias should resolve');
$b=hmeomr_targets(['names'=>['Dong Duong'],'places'=>[],'latitude'=>null,'longitude'=>null],4,$idx,$hotels,$names);tassert2($b===[],'reversed significant-token order must not resolve');
echo "hotel match exact ordered mass review tests passed\n";
