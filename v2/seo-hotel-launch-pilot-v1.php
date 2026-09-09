<?php
require_once __DIR__ . '/seo-hotel-launch-candidate-v1.php';

/**
 * Explicit review-only first hotel-tour launch slice.
 *
 * This is not a publication manifest. It has no sitemap/robots/canonical/status
 * consumers and still requires fresh 100/100 readiness rows plus separate user
 * approval before any indexation work can happen.
 */
function v2_seo_hotel_launch_pilot_spec(): array
{
    return [
        'state' => 'proposal_only_requires_launch_approval',
        'selection_policy' => 'explicit_quality_tie_break_no_rank_claim',
        'countries' => [
            [
                'country_id' => 4,
                'paths' => [
                    '/country/turkey/hotel/aegean-park-1601/',
                    '/country/turkey/hotel/afytos-bodrum-city-71506/',
                    '/country/turkey/hotel/agon-hotel-65881/',
                ],
            ],
            [
                'country_id' => 8,
                'paths' => [
                    '/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/',
                    '/country/maldives/hotel/angsana-velavaru-74701/',
                    '/country/maldives/hotel/avani-fares-maldives-resort-82538/',
                ],
            ],
            [
                'country_id' => 1,
                'paths' => [
                    '/country/egypt/hotel/coral-hills-resort-162/',
                    '/country/egypt/hotel/dexon-roma-hotel-ex-roma-host-way-388/',
                    '/country/egypt/hotel/ecotel-dahab-bay-view-resort-322/',
                ],
            ],
        ],
    ];
}

function v2_seo_hotel_launch_pilot_proposal(array $readinessRows, ?int $nowEpoch = null): array
{
    $spec = v2_seo_hotel_launch_pilot_spec();
    return v2_seo_hotel_country_launch_slice_proposal(
        $readinessRows,
        $spec['countries'],
        [4, 8, 1],
        3,
        9,
        $nowEpoch
    );
}
