<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-month-matrix-v1.php';

$m=v2_seo_core_month_matrix();
if(($m['state']??'')!=='core_month_matrix_ready') exit(1);
if(($m['country_month_count']??0)!==36) exit(2); // Turkey + Egypt + Maldives × 12
if(($m['resort_month_count']??0)!==60) exit(3); // 5 verified Turkey resorts × 12
if(($m['total_count']??0)!==96) exit(4);
$paths=array_column($m['rows'],'path');
foreach([
 '/country/turkey/january/',
 '/country/egypt/december/',
 '/country/maldives/september/',
 '/country/turkey/antalya/october/',
 '/country/turkey/kemer/june/',
 '/country/turkey/alanya/december/',
] as $path) if(!in_array($path,$paths,true)) exit(5);
if(count($paths)!==count(array_unique($paths))) exit(6);
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed','search_contract_changes','tourvisor_contract_changes'] as $flag) if(($m[$flag]??true)!==false) exit(7);
if(($m['publication_candidates']??null)!==[]) exit(8);
echo "SEO_CORE_MONTH_MATRIX_OK total=96 country_month=36 resort_month=60 publication=0\n";
