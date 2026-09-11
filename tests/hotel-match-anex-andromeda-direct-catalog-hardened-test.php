<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_andromeda_direct_catalog_hardened.php';
function ok2(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$rows=[];for($i=1;$i<=10;$i++)$rows['A'.$i]=['country_id'=>4,'names'=>['BRAND'.$i.' ISTANBUL HOTEL'],'places'=>['Istanbul']];
$loc=hmadcrh_locality_common($rows);$key=hmadcrh_locality_key($rows['A1']);ok2(isset($loc['common'][$key]['istanbul']),'frequent locality token detected');
$pair=['aligned_pairs'=>[['source'=>'unique','target'=>'unique','similarity'=>1.0],['source'=>'istanbul','target'=>'istanbul','similarity'=>1.0]]];$anchors=hmadcrh_identity_anchors($pair,['country_id'=>4,'places'=>['Istanbul']],$loc);ok2($anchors['identity_aligned']===1,'locality token excluded from identity anchors');ok2($anchors['locality_common_aligned']===1,'locality evidence retained separately');
$nonGeo=['aligned_pairs'=>[['source'=>'rixos','target'=>'rixos','similarity'=>1.0],['source'=>'premium','target'=>'premium','similarity'=>1.0]]];$a2=hmadcrh_identity_anchors($nonGeo,['country_id'=>4,'places'=>['Istanbul']],$loc);ok2($a2['identity_aligned']===2,'two real brand anchors remain');
echo "direct catalog locality hardening tests passed\n";
