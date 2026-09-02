<?php
require_once __DIR__ . '/seo-hotel-family-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-hotels-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-v1.php';

function v2_seo_content_pilot_maldives_catalog(): array
{
    return v2_seo_hotel_family_catalog(
        v2_seo_content_pilot_maldives(),
        v2_seo_maldives_hotel_records()
    );
}
