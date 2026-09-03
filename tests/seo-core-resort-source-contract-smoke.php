<?php
declare(strict_types=1);
$source=(string)file_get_contents(__DIR__.'/../v2/data/seo-core-resort-cohort-source-v1.php');
foreach([
    'FROM tour_price_observations o',
    'JOIN catalog_hotels h ON h.id=o.hotel_id',
    'h.country_id=o.country_id',
    'h.is_active=1',
    'h.region_id AS region_id',
    'h.region_name AS region_name',
    "CASE h.country_id WHEN 1 THEN 'egypt' WHEN 8 THEN 'maldives' END AS country_slug",
    'h.region_id IS NOT NULL AND h.region_id>0',
    "h.country_id IN (1,8)",
    'v2_data_slug($regionName)',
    'o.departure_date>=CURDATE()',
    "o.currency='RUB'",
] as $needle)if(!str_contains($source,$needle))exit(1);
if(str_contains($source,'JOIN catalog_regions'))exit(2);
if(str_contains($source,'o.region_id='))exit(3);
if(str_contains($source,"c.slug IN ('egypt','maldives')"))exit(4);
echo "SEO_CORE_RESORT_SOURCE_CONTRACT_OK region_identity=catalog_hotel_direct country_route_slugs=core observation_region_nullable=1\n";
