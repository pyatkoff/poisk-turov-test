<?php
declare(strict_types=1);

putenv('MATCH_PROVIDER_GEO_LABEL_TEST_LIBRARY=1');
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_geo_label_consensus_review.php';

function t(bool $ok, string $msg): void { if (!$ok) throw new RuntimeException($msg); }
function evidence(string $name, array $extra): string {
    return json_encode(['source'=>array_merge(['name'=>$name,'stateKey'=>'6'],$extra)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}

$labels = mpgl_geo_labels([
    'source'=>[
        'name'=>'Grand Emin Hotel',
        'regionName'=>'Laleli',
        'regionKey'=>'44',
        'stateName'=>'Turkey',
        'city_title'=>'Istanbul',
        'hotelAreaName'=>'Grand Emin Hotel',
        'areaCode'=>'12',
    ],
    'geography'=>['atollName'=>'North Male Atoll','localityLabel'=>'Laleli'],
]);
t(isset($labels['region=laleli']), 'region label');
t(isset($labels['city=istanbul']), 'city label');
t(isset($labels['atoll=north male atoll']), 'atoll label');
t(isset($labels['locality=laleli']), 'locality label');
t(!array_filter(array_keys($labels), fn($k)=>str_contains($k,'44')||str_contains($k,'turkey')||str_contains($k,'grand emin')), 'reject keys state hotel');

$accepted=[];
for($i=1;$i<=5;$i++) $accepted[]=[
    'external_hotel_id'=>(string)$i,
    'evidence_json'=>evidence('Hotel '.$i,['regionName'=>'Laleli']),
    'local_country_id'=>4,'local_region_id'=>10,'local_subregion_id'=>20,
];
$c=mpgl_build_consensus($accepted);
t(isset($c['usable']['region=laleli']), 'five unanimous anchors usable');
t($c['usable']['region=laleli']['scope']==='subregion' && $c['usable']['region=laleli']['scope_id']===20, 'subregion scope');

$conflict=$accepted;
$conflict[]=['external_hotel_id'=>'6','evidence_json'=>evidence('Hotel 6',['regionName'=>'Laleli']),'local_country_id'=>5,'local_region_id'=>11,'local_subregion_id'=>21];
$cc=mpgl_build_consensus($conflict);
t(!isset($cc['usable']['region=laleli']) && ($cc['rejected']['region=laleli']['reason']??'')==='country_not_unanimous', 'country split rejected');

$scope=[4=>['subregion'=>[20=>[100=>true,101=>true]],'region'=>[10=>[100=>true,101=>true,102=>true]]]];
$allow=mpgl_allowed_ids(['source'=>['name'=>'Sureyya Hotel','regionName'=>'Laleli']],4,$c,$scope);
t($allow['status']==='ok' && $allow['ids']===[100,101], 'allowed ids from consensus');

$hotels=[
 100=>['id'=>100,'name'=>'Sureyya Hotel','category'=>'3','latitude'=>null,'longitude'=>null,'region_name'=>'Istanbul','subregion_name'=>'Laleli'],
 101=>['id'=>101,'name'=>'Different Place','category'=>'3','latitude'=>null,'longitude'=>null,'region_name'=>'Istanbul','subregion_name'=>'Laleli'],
];
$forms=[100=>['Sureyya Hotel'],101=>['Different Place']];
$sel=mpg_select(['Sureyya Hotel'],[],[100,101],$hotels,$forms);
t(($sel['route']??'')==='auto_accept_candidate' && ($sel['target']??0)===100, 'restricted exact candidate');

$hotelNameLabel=mpgl_geo_labels(['source'=>['name'=>'Laleli','regionName'=>'Laleli']]);
t($hotelNameLabel===[], 'label equal hotel name excluded');

$weak=mpgl_geo_labels(['source'=>['name'=>'Example Hotel','regionName'=>'Other','cityName'=>'Unknown']]);
t($weak===[], 'weak labels excluded');

echo "hotel_match_provider_geo_label_consensus_review_test OK\n";
