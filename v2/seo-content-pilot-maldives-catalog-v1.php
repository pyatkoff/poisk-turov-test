<?php
require_once __DIR__ . '/seo-hotel-family-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-hotels-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-v1.php';

function v2_seo_content_pilot_maldives_catalog(): array
{
    // The public country page may be approved independently, but the hotel
    // family remains a review-only workspace. Project the parent into review
    // state rather than weakening the hotel-family safety contract.
    $country=v2_seo_content_pilot_maldives();
    $country['status']='review';
    return v2_seo_hotel_family_catalog(
        $country,
        v2_seo_maldives_hotel_records()
    );
}
