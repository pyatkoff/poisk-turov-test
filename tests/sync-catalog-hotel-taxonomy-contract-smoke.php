<?php
declare(strict_types=1);
$source=(string)file_get_contents(__DIR__.'/../v2/data/sync-catalog-v1.php');
foreach([
    "$row['countryId']",
    "$row['countryName']",
    "$row['regionId']",
    "$row['regionName']",
    "$row['subregionId']",
    "$row['subRegionId']",
    "$row['subregionName']",
    "$row['subRegionName']",
    "$row['latitude']",
    "$row['longitude']",
] as $needle)if(!str_contains($source,$needle))exit(1);
if(!str_contains($source,"'region_id'=>$resolvedRegionId"))exit(2);
if(!str_contains($source,"'region_name'=>$resolvedRegionName"))exit(3);
echo "SYNC_CATALOG_HOTEL_TAXONOMY_OK scalar_fallbacks=1\n";
