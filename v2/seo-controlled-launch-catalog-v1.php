<?php
require_once __DIR__ . '/seo-content-pilot-turkey-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-egypt-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-v1.php';
require_once __DIR__ . '/seo-content-pilot-seasonal-september-v1.php';

/** Exact controlled production SEO catalog. hotel_tours remain deliberately absent. */
function v2_seo_controlled_launch_catalog(): array
{
    return v2_seo_content_catalog(
        [
            v2_seo_content_pilot_turkey(),
            v2_seo_content_pilot_kemer(),
            v2_seo_content_pilot_antalya(),
            v2_seo_content_pilot_side(),
            v2_seo_content_pilot_belek(),
            v2_seo_content_pilot_alanya(),
            v2_seo_content_pilot_egypt(),
            v2_seo_content_pilot_maldives(),
            v2_seo_content_pilot_antalya_september(),
            v2_seo_content_pilot_maldives_september(),
        ],
        [
            '/country/turkey/kemer/' => ['parent'=>'/country/turkey/','related'=>['/country/turkey/antalya/','/country/turkey/side/']],
            '/country/turkey/antalya/' => ['parent'=>'/country/turkey/','related'=>['/country/turkey/kemer/','/country/turkey/belek/']],
            '/country/turkey/side/' => ['parent'=>'/country/turkey/','related'=>['/country/turkey/belek/','/country/turkey/alanya/']],
            '/country/turkey/belek/' => ['parent'=>'/country/turkey/','related'=>['/country/turkey/antalya/','/country/turkey/side/']],
            '/country/turkey/alanya/' => ['parent'=>'/country/turkey/','related'=>['/country/turkey/side/','/country/turkey/antalya/']],
            '/country/turkey/antalya/september/' => ['parent'=>'/country/turkey/antalya/','related'=>['/country/turkey/','/country/turkey/kemer/','/country/turkey/belek/']],
            '/country/maldives/september/' => ['parent'=>'/country/maldives/','related'=>[]],
        ]
    );
}
