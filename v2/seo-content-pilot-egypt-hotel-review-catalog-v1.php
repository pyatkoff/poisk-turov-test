<?php
require_once __DIR__ . '/seo-hotel-family-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-egypt-v1.php';
require_once __DIR__ . '/seo-content-pilot-egypt-hotels-v1.php';

function v2_seo_content_pilot_egypt_hotel_review_catalog(): array
{
    $country = v2_seo_content_pilot_egypt();
    $country['data']['related_title'] = 'Туры в отели Египта';
    $country['data']['related'] = v2_seo_egypt_hotel_links();
    return v2_seo_hotel_family_catalog($country, v2_seo_egypt_hotel_records());
}
