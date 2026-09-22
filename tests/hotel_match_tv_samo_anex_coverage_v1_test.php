<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_anex_coverage_v1.php';

$facts=[
    10=>['name'=>'A','country_name'=>'Turkey','region_name'=>'Side','subregion_name'=>'Center','category'=>'5'],
    20=>['name'=>'B','country_name'=>'Turkey','region_name'=>'Side','subregion_name'=>'Center','category'=>'4'],
    30=>['name'=>'C','country_name'=>'Egypt','region_name'=>'Hurghada','subregion_name'=>'','category'=>'5'],
];
$x=hmtsac_bucket(
    [10,20,30],
    [10=>['samo-1'=>true],20=>['samo-2'=>true]],
    [10=>['anex-11'=>true],30=>['anex-33'=>true]],
    $facts
);
$e=['tv_total'=>3,'has_samo'=>2,'has_anex'=>2,'full_triple'=>1,'samo_only'=>1,'anex_only'=>1,'neither'=>0];
if(($x['counts']??null)!==$e)throw new RuntimeException('coverage_counts');
if(count($x['missing']['missing_samo']??[])!==1)throw new RuntimeException('missing_samo');
if(count($x['missing']['missing_anex']??[])!==1)throw new RuntimeException('missing_anex');
if(count($x['missing']['missing_both']??[])!==0)throw new RuntimeException('missing_both');
echo "MATCH_TV_SAMO_ANEX_COVERAGE_V1_TEST_OK\n";
