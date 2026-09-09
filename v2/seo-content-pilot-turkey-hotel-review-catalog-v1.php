<?php
require_once __DIR__ . '/seo-hotel-family-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-turkey-v1.php';
require_once __DIR__ . '/seo-content-pilot-turkey-hotels-v1.php';

/**
 * Isolated review catalog for Turkey hotel-tour children.
 *
 * The production Turkey parent is already an approved/indexable launch record, so
 * we clone it only inside this review catalog and attach hotel links to the clone.
 * The real country record and its public navigation remain unchanged.
 */
function v2_seo_content_pilot_turkey_hotel_review_catalog(): array
{
    $reviewParent = v2_seo_content_pilot_turkey();
    $reviewParent['id'] = 'country.turkey.hotel-review.v1';
    $reviewParent['status'] = 'review';
    $reviewParent['data']['related_title'] = 'Туры в отели Турции — review';
    $reviewParent['data']['related'] = v2_seo_turkey_hotel_links();

    return v2_seo_hotel_family_catalog(
        $reviewParent,
        v2_seo_turkey_hotel_records()
    );
}
