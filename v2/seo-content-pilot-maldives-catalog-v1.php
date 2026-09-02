<?php
require_once __DIR__ . '/seo-content-catalog-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-avani-fares-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-ayada-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-banyan-vabbinfaru-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-barcelo-nasandhura-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-coco-bodu-hithi-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-furaveri-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-grand-park-kodhipparu-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-hard-rock-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-kagi-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-kandima-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-kurumba-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-lux-south-ari-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-machchafushi-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-nh-reethi-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-nika-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-nooe-kunaavashi-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-royal-island-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-saii-lagoon-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-sheraton-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-taj-coral-reef-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-velassaru-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-villa-nautica-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-villa-park-v1.php';
require_once __DIR__ . '/seo-content-pilot-maldives-westin-v1.php';

/**
 * Review-only Maldives country -> hotel-tour catalog.
 *
 * This makes the existing hotel family part of the shared SEO2 registry/graph.
 * It has no routing, sitemap, canonical or indexation side effects. All child
 * records remain review-only and therefore cannot become publication candidates.
 */
function v2_seo_content_pilot_maldives_catalog(): array
{
    $records = [
        v2_seo_content_pilot_maldives(),
        v2_seo_content_pilot_maldives_avani_fares(),
        v2_seo_content_pilot_maldives_ayada(),
        v2_seo_content_pilot_maldives_banyan_vabbinfaru(),
        v2_seo_content_pilot_maldives_barcelo_nasandhura(),
        v2_seo_content_pilot_maldives_coco_bodu_hithi(),
        v2_seo_content_pilot_maldives_furaveri(),
        v2_seo_content_pilot_maldives_grand_park_kodhipparu(),
        v2_seo_content_pilot_maldives_hard_rock(),
        v2_seo_content_pilot_maldives_kagi(),
        v2_seo_content_pilot_maldives_kandima(),
        v2_seo_content_pilot_maldives_kurumba(),
        v2_seo_content_pilot_maldives_lux_south_ari(),
        v2_seo_content_pilot_maldives_machchafushi(),
        v2_seo_content_pilot_maldives_nh_reethi(),
        v2_seo_content_pilot_maldives_nika(),
        v2_seo_content_pilot_maldives_nooe_kunaavashi(),
        v2_seo_content_pilot_maldives_royal_island(),
        v2_seo_content_pilot_maldives_saii_lagoon(),
        v2_seo_content_pilot_maldives_sheraton(),
        v2_seo_content_pilot_maldives_taj_coral_reef(),
        v2_seo_content_pilot_maldives_velassaru(),
        v2_seo_content_pilot_maldives_villa_nautica(),
        v2_seo_content_pilot_maldives_villa_park(),
        v2_seo_content_pilot_maldives_westin(),
    ];

    $relations = [];
    foreach (array_slice($records, 1) as $record) {
        $path = (string)($record['path'] ?? '');
        if ($path === '') {
            throw new InvalidArgumentException('Maldives hotel-tour catalog record is missing path');
        }
        $relations[$path] = ['parent' => '/country/maldives/'];
    }

    return v2_seo_content_catalog($records, $relations);
}
