<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_full_catalog_reconcile.php';
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_current_cross_provider_fuzzy.php';
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_duplicate_evidence.php';

$checks=0;
$assert=static function(bool $ok,string $name)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($name);};

$a=hmpd_pair_best(['BE LIVE ADULTS ONLY LOS CACTUS'],['CUBANACAN LOS CACTUS ADULTS ONLY (EX. BE LIVE LOS CACTUS)']);
$assert(is_array($a)&&$a['shared']>=5&&$a['qualifier_ok'],'former_name_duplicate_support');
$b=hmpd_pair_best(['STELLA GARDENS RESORT'],['STELLA BEACH RESORT']);
$assert(is_array($b)&&!$b['qualifier_ok'],'beach_garden_duplicate_block');
$c=hmpd_pair_best(['DOMINA PRESTIGE POOL'],['DOMINA PRESTIGE SEA']);
$assert(is_array($c)&&!$c['qualifier_ok'],'pool_sea_duplicate_block');
$d=hmpd_pair_best(['SUNRISE NORTH HOTEL'],['SUNRISE SOUTH HOTEL']);
$assert(is_array($d)&&!$d['qualifier_ok'],'direction_duplicate_block');
$e=hmpd_pair_best(['WYNN CHILLI SALZA PATONG'],['CHILLI SALZA PATONG']);
$assert(is_array($e)&&abs($e['score']-0.75)<0.000001&&$e['shared']===3,'brand_prefix_support');
$assert(hmpd_pair_best([],['ABC'])===null,'empty_source_names');
$assert(hmpd_pair_best(['ABC'],[])===null,'empty_accepted_names');

echo "hotel_match_provider_duplicate_evidence_test: {$checks} checks PASS\n";
