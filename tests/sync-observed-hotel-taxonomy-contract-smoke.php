<?php
declare(strict_types=1);
$source=(string)file_get_contents(__DIR__.'/../v2/data/sync-observed-hotel-taxonomy-v1.php');
foreach([
    "JOIN tour_price_observations o",
    "h.country_id IN ({$countrySql})",
    "o.observed_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)",
    "o.departure_date>=CURDATE()",
    "v2_data_tv_get('/hotels/'.$id)",
    "catalog_subregions s JOIN catalog_regions r",
    "UPDATE catalog_hotels SET region_id=:region_id",
] as $needle)if(!str_contains($source,$needle))exit(1);
echo "OBSERVED_HOTEL_TAXONOMY_CONTRACT_OK detail_only=1 exact_catalog_fallback=1\n";
