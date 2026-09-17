<?php
declare(strict_types=1);
define('MCP_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_missing_canonical_priority_current_v1.php';
define('MCP2_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_missing_canonical_priority_current_v2.php';
function v2a(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$targets=mcp2_provider_targets([
 ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'1','local_hotel_id'=>'100'],
 ['supplier_namespace'=>'operator_315','external_hotel_id'=>'2','local_hotel_id'=>'100'],
 ['supplier_namespace'=>'operator_315','external_hotel_id'=>'2','local_hotel_id'=>'100'],
 ['supplier_namespace'=>'operator_342','external_hotel_id'=>'3','local_hotel_id'=>'200'],
],[
 ['anex_hotel_id'=>'4','catalog_hotel_id'=>'100'],['anex_hotel_id'=>'4','catalog_hotel_id'=>'100'],['anex_hotel_id'=>'5','catalog_hotel_id'=>'300'],
]);
v2a(count($targets)===3,'targets');v2a(count($targets[100]['sources'])===3,'dedupe_sources');v2a(count($targets[100]['providers'])===3,'provider_breadth');
$canonical=mcp2_canonical_set([['external_key'=>'200']]);
$demand=mcp2_demand_map([
 ['tv_hotel_id'=>'100','observations_24h'=>'8','observations_7d'=>'20','observations_total'=>'40','searches_24h'=>'2','searches_7d'=>'5','searches_total'=>'9','last_observed_at'=>'2026-09-17 12:00:00'],
]);
$catalog=mcp2_catalog_map([
 ['id'=>'100','country_id'=>'4','country_name'=>'Turkey','region_id'=>'1','region_name'=>'Antalya','subregion_id'=>null,'subregion_name'=>null,'name'=>'HOTEL 100','category'=>'5','latitude'=>'36.0','longitude'=>'30.0','is_active'=>'1'],
 ['id'=>'300','country_id'=>'1','country_name'=>'Russia','region_id'=>'2','region_name'=>'X','subregion_id'=>null,'subregion_name'=>null,'name'=>'HOTEL 300','category'=>'4','latitude'=>null,'longitude'=>null,'is_active'=>'1'],
]);
$raw=mcp2_raw_rows($targets,$canonical,$demand,$catalog);v2a(count($raw)===2,'canonical_subtraction');
$built=mcp_build($raw);v2a(count($built['ready_rows'])===1,'ready');v2a($built['ready_rows'][0]['tv_hotel_id']===100,'ready_id');v2a($built['ready_rows'][0]['accepted_provider_count']===3,'breadth');v2a($built['ready_rows'][0]['priority_tier']==='live_24h','demand_tier');v2a(count($built['held_rows'])===1&&$built['held_rows'][0]['status']==='hold_non_sellable_country','hold_country');
try{mcp2_canonical_set([['external_key'=>'200'],['external_key'=>'200']]);throw new RuntimeException('dup_not_rejected');}catch(RuntimeException$e){v2a($e->getMessage()==='duplicate_canonical_legacy','dup');}
echo "missing canonical priority current v2 smoke PASS\n";
