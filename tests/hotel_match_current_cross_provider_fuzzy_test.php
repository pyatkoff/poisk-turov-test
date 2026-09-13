<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_full_catalog_reconcile.php';
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_current_cross_provider_fuzzy.php';

$checks=0;
$assert=static function(bool $ok,string $name)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($name);};

$assert(hmcf_tokens('THE GRAND HOTEL RESORT & SPA')===['grand'],'generic_only_removed');
$assert(in_array('beach',hmcf_tokens('Grand Beach Hotel'),true),'beach_preserved');
$assert(in_array('north',hmcf_tokens('Sunrise North Resort'),true),'north_preserved');
$assert(in_array('club',hmcf_tokens('Laguna Club Resort'),true),'club_preserved');
$a=hmcf_name_pair('Grand Signature Resort Hoi An by M Village','GRAND SIGNATURE BY M VILLAGE HOI AN RESORT');
$assert(abs($a['score']-1.0)<0.000001 && $a['shared']>=5,'reordered_generic_exact');
$assert($a['qualifier_ok'],'matching_qualifiers');
$b=hmcf_name_pair('Stella Gardens Resort','Stella Beach Resort');
$assert(!$b['qualifier_ok'],'garden_beach_guard');
$c=hmcf_name_pair('Domina Prestige Pool Hotel','Domina Prestige Sea Hotel');
$assert(!$c['qualifier_ok'],'pool_sea_guard');
$d=hmcf_name_pair('Sunrise North Hotel','Sunrise South Hotel');
$assert(!$d['qualifier_ok'],'direction_guard');
$e=hmcf_name_pair('Posh Club Sunrise Diamond','Sunrise Diamond');
$assert(!$e['qualifier_ok'],'club_guard');
$names=[1=>['ALPHA BETA HOTEL'],2=>['ALPHA GAMMA HOTEL']];
$rank=hmcf_best(['ALPHA BETA RESORT'],[1,2],$names);
$assert($rank[0]['id']===1 && abs($rank[0]['score']-1.0)<0.000001,'unique_best');
$assert(($rank[0]['score']-$rank[1]['score'])>0.20,'large_margin');

echo "hotel_match_current_cross_provider_fuzzy_test: {$checks} checks PASS\n";
