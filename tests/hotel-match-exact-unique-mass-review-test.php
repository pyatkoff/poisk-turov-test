<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_exact_unique_mass_review.php';

function tassert(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}

$hotels=[
  101=>['country_id'=>4,'name'=>'GOLDEN AGE','region_name'=>'Мармарис','subregion_name'=>'','latitude'=>null,'longitude'=>null,'category'=>4],
  102=>['country_id'=>4,'name'=>'NORTH GARDEN','region_name'=>'Кемер','subregion_name'=>'','latitude'=>36.6,'longitude'=>30.5,'category'=>4],
];
$names=[101=>['GOLDEN AGE'],102=>['NORTH GARDEN']];
$idx=hmgcr_build_index([101=>true,102=>true],$hotels,$names);
$source=['names'=>['Golden Age Hotel'],'places'=>['Marmaris'],'latitude'=>null,'longitude'=>null,'category'=>4];
$targets=hmeumr_exact_targets($source,4,$idx,$hotels,$names);
tassert(array_keys($targets)===[101],'two significant-token exact alias should resolve one target');
tassert(($targets[101]['pair']['exact_bag']??false)===true,'exact bag expected');
tassert((int)($targets[101]['pair']['shared']??0)===2,'two significant shared tokens expected');

$bad=['names'=>['South Garden Hotel'],'places'=>['Kemer'],'latitude'=>36.6,'longitude'=>30.5,'category'=>4];
$badTargets=hmeumr_exact_targets($bad,4,$idx,$hotels,$names);
tassert($badTargets===[],'meaningful NORTH/SOUTH qualifier mismatch must block');

tassert(hmeumr_other(['1','2','3'],'2')===['1','3'],'same-provider self claim filtering');
echo "hotel match exact unique mass review tests passed\n";
