<?php
declare(strict_types=1);
$source=(string)file_get_contents(__DIR__.'/../v2/data/seo-core-resort-cohort-source-v1.php');
foreach([
    'FROM tour_price_observations o',
    'JOIN catalog_hotels h ON h.id=o.hotel_id',
    'h.country_id=o.country_id',
    'h.is_active=1',
    'JOIN catalog_regions cr ON cr.id=h.region_id',
    'cr.country_id=h.country_id',
    'o.departure_date>=CURDATE()',
    "o.currency='RUB'",
] as $needle)if(!str_contains($source,$needle))exit(1);
if(str_contains($source,'JOIN tour_price_observations o ON o.region_id=cr.id'))exit(2);
echo "SEO_CORE_RESORT_SOURCE_CONTRACT_OK region_identity=catalog_hotel observation_region_nullable=1\n";
