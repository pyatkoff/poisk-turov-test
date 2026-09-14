<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};

$searchRef = str_repeat('a', 32);
$offerRef = 'anex_online:' . str_repeat('b', 64);
$offer = [
    'offer_key' => $offerRef,
    'provider' => 'anex', 'supplier_namespace' => 'anex_online', 'kind' => 'concrete',
    'hotel' => ['external_id' => '8121', 'local_id' => 6319, 'mapping_status' => 'resolved',
        'name' => 'APERION BEACH', 'star' => '4', 'country' => null, 'region' => null, 'town' => 'Side',
        'external_town_id' => '12'],
    'checkin' => '2026-09-20', 'checkout' => '2026-09-27', 'nights' => 7,
    'adults' => 2, 'children' => 0, 'infants' => null, 'meal' => 'AI', 'external_meal_id' => '7',
    'room' => 'STANDARD', 'external_room_id' => '10', 'hotel_place' => 'DBL', 'external_hotel_place_id' => '2',
    'price' => ['amount' => '100000', 'currency' => 'RUB'], 'converted_price' => null,
    'availability' => ['hotel' => 'Y', 'flight_outbound_economy' => 'Y', 'flight_return_economy' => 'Y'],
    'supplier_booking_flag' => true, 'final_price_verified' => false,
];
$stateTemplate = [
    'generation' => 9,
    'params' => ['countryId' => 1, 'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [],
        'hotelRating' => '', 'hotelCategory' => '', 'meal' => '', 'priceFrom' => '', 'priceTo' => ''],
    'gateway' => [
        'saved_offers' => ['search_ref' => $searchRef, 'created_at' => 1789220000, 'expires_at' => 1789220900,
            'offers' => [$offerRef => ['offer' => $offer, 'observed_at' => 1789220010,
                // SearchTour-scoped diagnostics only; not an authoritative B2B AdditionalPricesDaily binding.
                'supplier_tour_program_id' => '987654321', 'supplier_currency_id' => '345']]],
        'search' => ['offers' => [['offer_key' => $offerRef, 'kind' => 'concrete', 'hotel_external_id' => '8121']]],
    ],
    'expansions' => [], 'additional_prices' => [],
];
$request = ['action' => 'additional_prices', 'generation' => 9, 'search_ref' => $searchRef,
    'offer_ref' => $offerRef, 'local_hotel_id' => 6319];
$resolver = static function (string $namespace, $external): ?int {
    return $namespace === 'anex_online' && (string) $external === '8121' ? 6319 : null;
};
$metadata = static function (array $offers): array {
    return [6319 => ['id' => 6319, 'name' => 'APERION BEACH', 'country_id' => 1, 'country_name' => 'Turkey',
        'region_id' => 2, 'region_name' => 'Side', 'subregion_id' => 3, 'subregion_name' => 'Kizilagac',
        'category' => 4, 'rating' => 4.5]];
};
$clock = static function (): int { return 1789220100; };
$directCalls = 0;
$directFactory = static function () use (&$directCalls) {
    ++$directCalls;
    throw new RuntimeException('DIRECT_CLIENT_MUST_NOT_RUN');
};
$checkpoints = 0;
$checkpoint = static function (array &$state) use (&$checkpoints): void { ++$checkpoints; };
$additionalCalls = 0;
$additionalFactory = static function () use (&$additionalCalls) {
    ++$additionalCalls;
    throw new RuntimeException('B2B_CLIENT_MUST_NOT_RUN');
};

$state = $stateTemplate;
$result = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($result['status'] === 'additional_prices_unavailable', 'runtime APD is unavailable without authoritative B2B tour binding');
$assert(($result['additional_prices_reason'] ?? null) === 'b2b_tour_binding_unverified', 'runtime exposes the exact binding blocker');
$assert($directCalls === 0 && $additionalCalls === 0 && $checkpoints === 0, 'binding blocker stops before reservation or supplier client');
$assert($state['additional_prices'] === [], 'binding blocker creates no semantic APD attempt');

// Previously cached evidence keyed by the same unverified SearchTour identifiers is not surfaced as current B2B authority.
$digest = hash('sha256', implode("\0", ['987654321', '345', '2026-09-20', '7']));
$state = $stateTemplate;
$state['additional_prices'][$digest] = ['status' => 'complete', 'evidence' => [
    'source' => 'anex_b2b_additional_prices_daily', 'rows' => [[
        'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
        'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
    ]], 'total_count' => 1, 'truncated' => false,
]];
$cached = anytour_anex_search3_followup($request, $state, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($cached['status'] === 'additional_prices_unavailable'
    && ($cached['additional_prices_reason'] ?? null) === 'b2b_tour_binding_unverified',
    'unverified legacy cache never crosses runtime boundary');
$assert($additionalCalls === 0 && $checkpoints === 0, 'legacy cache refusal remains supplier-free');

$changed = anytour_anex_search3_followup($request, $stateTemplate,
    static function (): ?int { return 999; }, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($changed['status'] === 'identity_changed', 'current identity drift still wins before binding blocker');

$missing = anytour_anex_search3_followup($request, $stateTemplate, $resolver, $directFactory,
    static function (): array { return []; }, $clock, $checkpoint, $additionalFactory);
$assert($missing['status'] === 'not_available', 'current catalog/filter loss still wins before binding blocker');

$groupState = $stateTemplate;
$groupState['gateway']['saved_offers']['offers'][$offerRef]['offer']['kind'] = 'group_minimum';
$groupState['gateway']['search']['offers'][0]['kind'] = 'group_minimum';
$group = anytour_anex_search3_followup($request, $groupState, $resolver, $directFactory, $metadata, $clock, $checkpoint, $additionalFactory);
$assert($group['status'] === 'not_concrete', 'group minimum is still not eligible for additional-price followup');

// Keep the existing pure money-evidence helper independently usable for supplier-authoritative evidence once binding exists.
$childOffer = $offer;
$childOffer['children'] = 1;
$applied = anytour_anex_search3_additional_application(anytour_anex_search3_additional_evidence(['data' => [[
    'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
    'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
]], 'totalCount' => 1]), $childOffer);
$assert($applied['party_surcharge']['amount'] === '31269' && $applied['search_plus_additional']['amount'] === '131269',
    'pure application preserves exact party arithmetic for authoritative evidence');
$assert($applied['fuel_equivalence_verified'] === false && $applied['final_price_verified'] === false,
    'pure application does not claim Tourvisor fuel or final-price equivalence');

$missingChild = anytour_anex_search3_additional_application(anytour_anex_search3_additional_evidence(['data' => [[
    'price_adult' => '120', 'cashrate' => '104.23', 'price_converted_adult' => '12507.6',
]], 'totalCount' => 1]), $childOffer);
$assert($missingChild['application_state'] === 'unknown' && $missingChild['party_surcharge'] === null
    && $missingChild['arithmetic_applied'] === false, 'missing child rate remains unknown rather than zero');

$ambiguous = anytour_anex_search3_additional_application(anytour_anex_search3_additional_evidence(['data' => [[
    'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
    'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
], [
    'price_adult' => '121', 'price_chd' => '61', 'cashrate' => '104.23',
    'price_converted_adult' => '12611.83', 'price_converted_chd' => '6358.03',
]], 'totalCount' => 2]), $offer);
$assert($ambiguous['application_state'] === 'unknown' && $ambiguous['arithmetic_applied'] === false,
    'multiple program/date rows never choose one silently');

echo "ANEX retained additional-prices runtime binding: {$checks} checks passed; live_network=0\n";
