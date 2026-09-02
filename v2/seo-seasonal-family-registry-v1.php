<?php
require_once __DIR__ . '/seo-resort-family-integrity-v1.php';
require_once __DIR__ . '/seo-content-pilot-turkey-v1.php';
require_once __DIR__ . '/seo-content-pilot-kemer-v1.php';
require_once __DIR__ . '/seo-content-pilot-antalya-v1.php';
require_once __DIR__ . '/seo-content-pilot-side-v1.php';
require_once __DIR__ . '/seo-content-pilot-belek-v1.php';
require_once __DIR__ . '/seo-content-pilot-alanya-v1.php';

/**
 * Explicit registry of destination families that are verified enough to bind
 * seasonal evidence. Registration never publishes routes or changes statuses.
 */
function v2_seo_seasonal_family_registry(): array
{
    $families = [
        'turkey' => [
            'country' => v2_seo_content_pilot_turkey(),
            'resorts' => [
                v2_seo_content_pilot_antalya(),
                v2_seo_content_pilot_kemer(),
                v2_seo_content_pilot_belek(),
                v2_seo_content_pilot_side(),
                v2_seo_content_pilot_alanya(),
            ],
        ],
    ];

    $out = [];
    $seenCountries = [];
    foreach ($families as $key => $family) {
        $key = trim((string)$key);
        if ($key === '' || !is_array($family)) throw new InvalidArgumentException('Invalid seasonal family registry entry');
        $country = is_array($family['country'] ?? null) ? $family['country'] : [];
        $resorts = is_array($family['resorts'] ?? null) ? $family['resorts'] : [];
        $integrity = v2_seo_resort_family_integrity($country, $resorts);
        $countryId = (int)($integrity['country_id'] ?? 0);
        if ($countryId <= 0) throw new InvalidArgumentException('Seasonal family country identity is invalid: ' . $key);
        if (isset($seenCountries[$countryId])) throw new InvalidArgumentException('Duplicate seasonal family country identity: ' . $countryId);
        if ((int)($integrity['blocked'] ?? 0) !== 0 || (int)($integrity['ready'] ?? 0) !== count($resorts)) {
            throw new InvalidArgumentException('Seasonal family is not launch-review ready: ' . $key);
        }
        if (($integrity['publication_candidates'] ?? null) !== []) {
            throw new InvalidArgumentException('Seasonal family crossed publication boundary: ' . $key);
        }
        $seenCountries[$countryId] = $key;
        $out[$key] = [
            'state' => 'verified_review_only_destination_family',
            'key' => $key,
            'country_id' => $countryId,
            'country' => $country,
            'resorts' => $resorts,
            'resort_count' => count($resorts),
            'publication_candidates' => [],
            'publication_allowed' => false,
            'copy_allowed' => false,
        ];
    }
    ksort($out);
    return $out;
}

function v2_seo_seasonal_family_registry_get(string $key): array
{
    $key = trim(strtolower($key));
    $families = v2_seo_seasonal_family_registry();
    if ($key === '' || !isset($families[$key])) throw new InvalidArgumentException('Unsupported verified seasonal family');
    return $families[$key];
}
