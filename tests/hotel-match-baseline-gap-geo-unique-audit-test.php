<?php
declare(strict_types=1);
define('HMBGG_LIBRARY_ONLY',true);define('HMBGC_LIBRARY_ONLY',true);define('HMBG_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_current_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_containment_audit.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_geo_unique_audit.php';
$locals=[1=>['_country_class'=>'turkey','name'=>'ELITE WORLD ISTANBUL','_aliases'=>[],'_geos'=>['Стамбул','Таксим']],2=>['_country_class'=>'turkey','name'=>'ELITE WORLD MARMARIS','_aliases'=>[],'_geos'=>['Мармарис','']],3=>['_country_class'=>'turkey','name'=>'OTHER','_aliases'=>['ELITE WORLD VAN'],'_geos'=>['Ван','']]];
$c=hmbgg_geo_candidates($locals,'turkey','elite world','Стамбул');if($c['all']!==[1,2,3]||$c['geo']!==[1])exit(2);
$c=hmbgg_geo_candidates($locals,'turkey','elite world','');if($c['geo']!==[])exit(3);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_baseline_gap_geo_unique_audit.php');foreach(['REPEATABLE READ','START TRANSACTION READ ONLY','containment_not_unique','current_geo_not_supported','safe_geo_scoped_unique','current_source_country_conflict','current_source_name_conflict'] as $n)if(strpos($s,$n)===false)exit(4);if(stripos($s,'INSERT INTO')!==false||stripos($s,'UPDATE ')!==false||stripos($s,'DELETE FROM')!==false)exit(5);echo "MATCH baseline-gap geo-unique source guards PASS; writes=0 supplier=0\n";
