<?php
require_once __DIR__ . '/seo-content-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-turkey-v1.php';
require_once __DIR__ . '/seo-content-pilot-kemer-v1.php';
require_once __DIR__ . '/seo-content-pilot-antalya-v1.php';
require_once __DIR__ . '/seo-content-pilot-side-v1.php';
require_once __DIR__ . '/seo-content-pilot-belek-v1.php';
require_once __DIR__ . '/seo-content-pilot-alanya-v1.php';

/**
 * Review-only vertical slice for the first country -> resort SEO family.
 * No route/indexation side effects.
 */
function v2_seo_content_pilot_turkey_catalog(): array
{
    $turkey = v2_seo_content_pilot_turkey();
    $kemer = v2_seo_content_pilot_kemer();
    $antalya = v2_seo_content_pilot_antalya();
    $side = v2_seo_content_pilot_side();
    $belek = v2_seo_content_pilot_belek();
    $alanya = v2_seo_content_pilot_alanya();

    return v2_seo_content_catalog(
        [$turkey, $kemer, $antalya, $side, $belek, $alanya],
        [
            '/country/turkey/kemer/' => [
                'parent' => '/country/turkey/',
                'related' => ['/country/turkey/antalya/', '/country/turkey/side/'],
            ],
            '/country/turkey/antalya/' => [
                'parent' => '/country/turkey/',
                'related' => ['/country/turkey/kemer/', '/country/turkey/belek/'],
            ],
            '/country/turkey/side/' => [
                'parent' => '/country/turkey/',
                'related' => ['/country/turkey/belek/', '/country/turkey/alanya/'],
            ],
            '/country/turkey/belek/' => [
                'parent' => '/country/turkey/',
                'related' => ['/country/turkey/antalya/', '/country/turkey/side/'],
            ],
            '/country/turkey/alanya/' => [
                'parent' => '/country/turkey/',
                'related' => ['/country/turkey/side/', '/country/turkey/antalya/'],
            ],
        ]
    );
}
