<?php
declare(strict_types=1);

/**
 * Review-only allowlist for seasonal editorial evidence.
 *
 * A source appearing here means only that its provenance may be accepted by the
 * evidence validator for the listed country/claim types. It does not make any
 * claim publishable and does not authorize copying facts that were not actually
 * observed in the referenced source.
 */
function v2_seo_seasonal_source_registry(): array
{
    return [
        4 => [
            'country' => 'Turkey',
            'sources' => [
                [
                    'source_id' => 'tr-mgm-climate',
                    'source_class' => 'official_meteorological',
                    'hosts' => ['www.mgm.gov.tr', 'mgm.gov.tr'],
                    'allowed_claim_types' => ['climate_temperature', 'climate_precipitation', 'sea_temperature'],
                    'review_only' => true,
                ],
            ],
        ],
        8 => [
            'country' => 'Maldives',
            'sources' => [
                [
                    'source_id' => 'mv-mms-climate',
                    'source_class' => 'official_meteorological',
                    'hosts' => ['www.meteorology.gov.mv', 'meteorology.gov.mv'],
                    'allowed_claim_types' => ['climate_temperature', 'climate_precipitation', 'daylight'],
                    'review_only' => true,
                ],
            ],
        ],
        1 => [
            'country' => 'Egypt',
            // Fail closed: EMA authority identity is verified, but a suitable
            // official HTTPS climate-normal/month source has not yet been
            // verified for the first seasonal content prototype.
            'sources' => [],
        ],
    ];
}

function v2_seo_seasonal_source_policy(int $countryId, string $sourceId, string $claimType): array
{
    $registry = v2_seo_seasonal_source_registry();
    $country = $registry[$countryId] ?? null;
    if (!is_array($country)) {
        return ['state' => 'blocked', 'code' => 'unknown_country_source_registry', 'allowed_hosts' => []];
    }

    foreach (($country['sources'] ?? []) as $source) {
        if (($source['source_id'] ?? '') !== $sourceId) {
            continue;
        }
        if (($source['review_only'] ?? false) !== true) {
            return ['state' => 'blocked', 'code' => 'source_not_review_only', 'allowed_hosts' => []];
        }
        if (!in_array($claimType, $source['allowed_claim_types'] ?? [], true)) {
            return ['state' => 'blocked', 'code' => 'claim_type_not_allowed_for_source', 'allowed_hosts' => []];
        }
        return [
            'state' => 'review_ready',
            'source_class' => (string)($source['source_class'] ?? ''),
            'allowed_hosts' => array_values($source['hosts'] ?? []),
            'publication_allowed' => false,
            'copy_allowed_without_evidence' => false,
        ];
    }

    return ['state' => 'blocked', 'code' => 'unverified_country_source', 'allowed_hosts' => []];
}
