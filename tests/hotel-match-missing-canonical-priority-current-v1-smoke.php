<?php
declare(strict_types=1);
define('MCP_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_missing_canonical_priority_current_v1.php';

function mp_assert(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function mp_row(int $id,array $o=[]):array{
    return array_merge([
        'tv_hotel_id'=>(string)$id,'accepted_source_count'=>'1','accepted_provider_count'=>'1','provider_namespaces'=>'operator_5',
        'catalog_present'=>'1','catalog_active'=>'1','hotel_name'=>'Hotel '.$id,'country_id'=>'4','country_name'=>'Turkey',
        'region_id'=>'1','region_name'=>'Antalya','subregion_id'=>null,'subregion_name'=>null,'category'=>'5','latitude'=>'36.0','longitude'=>'30.0',
        'observations_24h'=>'0','observations_7d'=>'0','observations_total'=>'0','searches_24h'=>'0','searches_7d'=>'0','searches_total'=>'0','last_observed_at'=>null,
    ],$o);
}
$rows=[
    mp_row(1,['observations_24h'=>'20','observations_7d'=>'40','observations_total'=>'80','searches_24h'=>'4','searches_7d'=>'8','searches_total'=>'10']),
    mp_row(2,['observations_24h'=>'20','observations_7d'=>'40','observations_total'=>'80','searches_24h'=>'3','searches_7d'=>'8','searches_total'=>'10','accepted_source_count'=>'3','accepted_provider_count'=>'2','provider_namespaces'=>'operator_315,operator_5']),
    mp_row(3,['observations_7d'=>'100','observations_total'=>'120','searches_7d'=>'10','searches_total'=>'12']),
    mp_row(4,['observations_total'=>'500','searches_total'=>'20']),
    mp_row(5),
    mp_row(6,['country_name'=>'Russia']),
    mp_row(7,['country_name'=>'Абхазия']),
    mp_row(8,['catalog_active'=>'0']),
    mp_row(9,['catalog_present'=>'0','catalog_active'=>null,'hotel_name'=>null,'country_name'=>null]),
];
$out=mcp_build($rows);
mp_assert(count($out['ready_rows'])===5,'ready_count');
mp_assert(count($out['held_rows'])===4,'held_count');
mp_assert(array_column($out['ready_rows'],'tv_hotel_id')===[1,2,3,4,5],'demand_order');
mp_assert(array_column($out['ready_rows'],'priority_tier')===['live_24h','live_24h','live_7d','observed_historical','provider_only'],'tier_order');
mp_assert(($out['census']['by_status']['hold_non_sellable_country']??0)===2,'country_holds');
mp_assert(($out['census']['by_status']['hold_catalog_inactive']??0)===1,'inactive_hold');
mp_assert(($out['census']['by_status']['hold_catalog_missing']??0)===1,'missing_hold');
mp_assert(($out['census']['by_priority_tier']['live_24h']??0)===2,'live24_count');
mp_assert(mcp_is_non_sellable_country('Russian Federation'),'russian_federation');
mp_assert(!mcp_is_non_sellable_country('Turkey'),'turkey_sellable');

$dup=$rows; $dup[]=mp_row(1);
try{mcp_build($dup);throw new RuntimeException('duplicate_not_rejected');}catch(RuntimeException $e){mp_assert($e->getMessage()==='duplicate_tv_target','duplicate_fail_closed');}
$bad=mp_row(10,['accepted_provider_count'=>'2','provider_namespaces'=>'operator_5']);
try{mcp_build([$bad]);throw new RuntimeException('provider_mismatch_not_rejected');}catch(RuntimeException $e){mp_assert($e->getMessage()==='provider_namespace_count_mismatch','provider_fail_closed');}

echo "missing canonical priority current v1 smoke PASS\n";
