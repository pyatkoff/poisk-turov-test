<?php

/**
 * Stable reference pair for SEO2 Design System 2.0 convergence.
 *
 * This contract only selects representative pages for visual/content QA. It does
 * not alter routing, robots, canonical, sitemap or publication state.
 */
function v2_seo_ds2_reference_pages(): array
{
    return [
        'destination' => [
            'path' => '/country/turkey/kemer/',
            'type' => 'resort',
            'renderer' => 'v2_seo_render_resort',
            'purpose' => 'reference_destination_editorial_and_offer_layout',
        ],
        'hotel_tours' => [
            'path' => '/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/',
            'type' => 'hotel_tours',
            'renderer' => 'v2_seo_render_hotel_tour_review',
            'country_id' => 8,
            'hotel_id' => 65108,
            'purpose' => 'reference_hotel_tour_editorial_and_offer_layout',
            'publication_state' => 'review_noindex_requires_launch_approval',
        ],
    ];
}

function v2_seo_ds2_reference_viewports(): array
{
    return [375, 430, 768, 1024, 1440];
}
