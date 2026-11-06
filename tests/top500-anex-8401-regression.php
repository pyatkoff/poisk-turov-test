<?php
declare(strict_types=1);

// Offline regression for the owner's ANEX 8401 (Rubi Platinum, source rank 76).
// Every accepted pair below is synthetic, NOT a current mapping for that hotel.
require_once __DIR__.'/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__.'/../v2/data/top-hotels-v1.php';
require_once __DIR__.'/../v2/data/top500-daily-plan-v1.php';

$checks = [];
function top8401_check(bool $condition, string $name): void
{
    if (!$condition) throw new RuntimeException('TOP500_ANEX_8401_FAIL: '.$name);
    $GLOBALS['checks'][] = $name;
}

function top8401_pair(int $anexId, int $tvId, array $changes = []): array
{
    return array_replace([
        'anex_hotel_id' => $anexId,
        'catalog_hotel_id' => $tvId,
        'existing_catalog_hotel_id' => $tvId,
        'enabled' => 1,
        'scope' => 'preview',
        'approval_policy' => 'owner_exact_and_strong_20260908',
        'match_class' => 'exact',
    ], $changes);
}

function top8401_plan(array $priority, array $hotels): array
{
    $plan = v2_top500_daily_plan($priority, $hotels,
        [['departure_id' => 1, 'country_id' => 4, 'is_active' => 1]],
        '2026-11-06', '2026-11-12', 1);
    return array_merge([], ...array_column($plan['targets'], 'hotel_ids'));
}

// A separate accepted fixture keeps both historical and current planners nonempty.
$anchor = top8401_pair(22879, 900001);
$mappedPair = top8401_pair(8401, 900076);
$hotels = array_map(static fn(int $id): array => [
    'id' => $id, 'country_id' => 4, 'country_name' => 'Synthetic', 'is_active' => 1,
], [900001, 8401, 900076, 27426]);

$unmapped = AnyTourAnexSearchMappingRegistry::fromRows([$anchor]);
$unmappedIds = v2_priority_hotel_ids($unmapped);
top8401_check(top8401_plan($unmappedIds, $hotels) === [900001],
    'catalog row 8401 cannot substitute for an absent accepted ANEX 8401 edge');
top8401_check(!in_array(27426, $unmappedIds, true),
    'historical XML candidate 27426 is not an accepted fallback');

$source = v2_top500_anex_ids();
top8401_check(count($source) === 492 && $source[75] === 8401,
    'ANEX 8401 keeps owner position 76 in the 492-ID roster');
top8401_check(hash('sha256', implode(',', $source)) ===
    'cac5f3cc765390702974cdb063333881fbf6bd70cab1296b02ef17118facbfe4',
    'every owner ID and its order match the pre-correction roster');
top8401_check(in_array(8401, v2_top500_resolution($unmapped)['unresolved_anex_ids'], true),
    'ANEX 8401 is explicitly unresolved without a current accepted edge');

$accepted = AnyTourAnexSearchMappingRegistry::fromRows([$anchor, $mappedPair]);
$resolvedIds = v2_priority_hotel_ids($accepted);
top8401_check($resolvedIds === [900001, 900076]
    && top8401_plan($resolvedIds, $hotels) === [900001, 900076],
    'an accepted synthetic edge selects its TV target instead of the same-number hotel');

$rejected = AnyTourAnexSearchMappingRegistry::fromRows([$anchor, $mappedPair], [[
    'anex_hotel_id' => 8401, 'decision_status' => 'rejected',
    'catalog_hotel_id' => 900076, 'existing_catalog_hotel_id' => 900076,
]]);
top8401_check(top8401_plan(v2_priority_hotel_ids($rejected), $hotels) === [900001],
    'manual rejection of ANEX 8401 leaves no numeric fallback');

$excluded = AnyTourAnexSearchMappingRegistry::fromRows([$anchor, $mappedPair], [], [[
    'anex_hotel_id' => 8401, 'catalog_hotel_id' => 900076,
]]);
top8401_check(top8401_plan(v2_priority_hotel_ids($excluded), $hotels) === [900001],
    'pair exclusion of ANEX 8401 leaves no numeric fallback');

$missing = AnyTourAnexSearchMappingRegistry::fromRows([
    $anchor, top8401_pair(8401, 900076, ['existing_catalog_hotel_id' => null]),
]);
top8401_check(top8401_plan(v2_priority_hotel_ids($missing), $hotels) === [900001],
    'a missing mapped target cannot fall back to catalog 8401');

$inactiveHotels = array_map(static function (array $hotel): array {
    if ($hotel['id'] === 900076) $hotel['is_active'] = 0;
    return $hotel;
}, $hotels);
top8401_check(top8401_plan($resolvedIds, $inactiveHotels) === [900001],
    'an inactive mapped TV target cannot fall back to active catalog 8401');

$sameNumber = AnyTourAnexSearchMappingRegistry::fromRows([$anchor, top8401_pair(8401, 8401)]);
top8401_check(top8401_plan(v2_priority_hotel_ids($sameNumber), $hotels) === [900001, 8401],
    'equal numbers are permitted only with an explicit accepted synthetic pair');

foreach (['tourvisor', 'anytour', 'legacy_catalog', 'anex_web'] as $namespace) {
    top8401_check($accepted->resolve($namespace, 8401, 'preview') === null,
        'unsupported source namespace remains unresolved: '.$namespace);
}
top8401_check($accepted->resolve('anex_online', 8401, 'production') === null,
    'priority projection does not enable the production ANEX resolver');
top8401_check(v2_priority_hotel_ids() === [],
    'unavailable registry does not expose raw owner IDs');

echo json_encode([
    'state' => 'TOP500_ANEX_8401_OK', 'checks' => count($checks),
    'fixture_pairs_only' => true, 'resolved_targets' => $resolvedIds,
    'unmapped_targets' => $unmappedIds,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
