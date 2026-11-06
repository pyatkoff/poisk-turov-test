<?php
/**
 * Owner ANEX TOP500, projected to Tourvisor only through current MATCH acceptance.
 * No numeric identity inference. Unavailable mappings produce no popularity badges.
 */
declare(strict_types=1);
require_once __DIR__ . '/top500-anex-priority-v1.php';

function v2_hotel_popularity_legacy_ids(?AnyTourAnexSearchMappingRegistry $registry = null): array
{
    return v2_top500_resolution($registry)['tourvisor_ids'];
}

function v2_hotel_popularity_legacy_rank(int $legacyHotelId, ?AnyTourAnexSearchMappingRegistry $registry = null): ?int
{
    if ($legacyHotelId <= 0) return null;
    $index = array_search($legacyHotelId, v2_hotel_popularity_legacy_ids($registry), true);
    return $index === false ? null : $index + 1;
}

/** One read per HTML/result request, never a supplier refresh. */
function v2_hotel_popularity_registry(): ?AnyTourAnexSearchMappingRegistry
{
    static $loaded = false; static $registry = null;
    if (!$loaded) {
        $loaded = true;
        try {
            require_once __DIR__ . '/db-v1.php';
            $registry = v2_top500_registry(v2_data_db());
        } catch (Throwable $error) {
            $registry = null;
        }
    }
    return $registry;
}

function v2_hotel_popularity_badge_for_rank(?int $rank): ?string
{
    return $rank === null || $rank < 1 ? null : 'Популярный отель';
}
