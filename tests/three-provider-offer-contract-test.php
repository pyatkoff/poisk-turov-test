<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-money-facts.php';
require __DIR__ . '/../app/integrations/three-provider-availability.php';
require __DIR__ . '/../app/integrations/three-provider-offer-contract.php';

$checks = 0;
function offer_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('offer_check_'.$checks); }

function offer_fixture(string $provider = 'tourvisor', ?int $local = 1239): array
{
    return [
        'provider' => $provider,
        'operator' => 'ANEX',
        'local_hotel_id' => $local,
        'provider_hotel_ref' => 'supplier-hotel-opaque-8652',
        'search_ref' => 'search-opaque-20261005',
        'offer_ref' => 'offer-opaque-abc',
        'checkin' => '2026-10-05',
        'nights' => 7,
        'adults' => 2,
        'children' => 0,
        'child_ages' => [],
        'meal' => ['raw' => 'AI-WITHOUT ALCOHOL', 'family' => 'ai',
            'qualifiers' => ['plus' => false, 'without_alcohol' => true]],
        'room' => ['raw' => 'Standard Room', 'normalized' => 'standard room'],
        'placement' => ['raw' => 'DBL', 'normalized' => 'dbl'],
        'availability' => [
            'hotel' => null,
            'flight_outbound_economy' => null,
            'flight_return_economy' => null,
        ],
        'search_price' => ['amount' => '119114', 'currency' => 'RUB', 'source' => $provider.'_search'],
        'fuel_charge_reported' => $provider === 'tourvisor'
            ? ['amount' => '31710', 'currency' => 'RUB', 'source' => $provider.'_fuel']
            : null,
        'additional_prices_reported' => [],
        'flight_details_state' => 'not_loaded',
        'observed_at' => '2026-09-11T03:00:00Z',
    ];
}

$value = AnyTourThreeProviderOfferContract::fromSearch(offer_fixture());
offer_check($value['provider'] === 'tourvisor');
offer_check($value['operator'] === 'ANEX');
offer_check($value['local_hotel_id'] === 1239);
offer_check(isset($value['identity']['offer_ref_digest']) && strlen($value['identity']['offer_ref_digest']) === 64);
offer_check(!isset($value['identity']['offer_ref']) && !isset($value['supplier_offer_id']));
offer_check($value['checkin'] === '2026-10-05' && $value['nights'] === 7);
offer_check($value['party'] === ['adults' => 2, 'children' => 0, 'child_ages' => []]);
offer_check($value['meal']['family'] === 'ai' && $value['meal']['family_verified'] === true);
offer_check($value['meal']['qualifiers']['without_alcohol'] === true);
offer_check($value['room']['normalized'] === 'standard room' && $value['placement']['normalized'] === 'dbl');
offer_check($value['availability']['hotel']['canonical_state'] === 'unknown' && $value['flight_details_state'] === 'not_loaded');
offer_check($value['availability']['offer_availability_verified'] === false
    && $value['availability']['selection_eligible'] === false
    && $value['availability']['booking_eligible'] === false);
offer_check($value['money']['search_price']['amount'] === '119114');
offer_check($value['money']['fuel_charge_reported']['amount'] === '31710');
offer_check($value['money']['package_buyer_price'] === null && $value['money']['quote_price'] === null);
offer_check($value['quote_state'] === 'unknown' && $value['final_price_verified'] === false);
offer_check($value['selection_state'] === 'disabled');
offer_check(!isset($value['search_ref']) && !isset($value['offer_ref']) && !isset($value['provider_hotel_ref']));

$unmappedFixture = offer_fixture('anex', null);
$unmappedFixture['availability'] = [
    'hotel' => 'YYYY',
    'flight_outbound_economy' => 'Y',
    'flight_return_economy' => 'R',
];
$unmapped = AnyTourThreeProviderOfferContract::fromSearch($unmappedFixture);
offer_check($unmapped['local_hotel_id'] === null);
offer_check($unmapped['availability']['hotel']['raw'] === 'YYYY'
    && $unmapped['availability']['hotel']['canonical_state'] === 'unknown'
    && $unmapped['availability']['hotel']['canonical_verified'] === false);
offer_check($unmapped['money']['fuel_charge_reported'] === null);
offer_check($unmapped['meal']['family_verified'] === true);

$children = offer_fixture();
$children['children'] = 1;
$children['child_ages'] = [7];
$children['meal']['family'] = null;
$children['availability'] = 'on_request';
$children['flight_details_state'] = 'unknown';
$childValue = AnyTourThreeProviderOfferContract::fromSearch($children);
offer_check($childValue['party']['child_ages'] === [7]);
offer_check($childValue['meal']['family_verified'] === false);
offer_check($childValue['availability']['hotel']['evidence_state'] === 'missing');

$bad = [
    function () { $x = offer_fixture('other'); AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['provider_hotel_ref'] = ''; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['checkin'] = '2026-02-30'; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['children'] = 1; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['child_ages'] = [18]; $x['children'] = 1; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['meal']['family'] = '7'; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['availability'] = 'booked'; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['placement'] = ['raw' => 'DBL']; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['supplier_offer_id'] = 'PRIVATE'; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['search_price']['amount'] = '0'; AnyTourThreeProviderOfferContract::fromSearch($x); },
    function () { $x = offer_fixture(); $x['flight_details_state'] = 'loaded'; AnyTourThreeProviderOfferContract::fromSearch($x); },
];
foreach ($bad as $case) {
    try { $case(); offer_check(false); } catch (InvalidArgumentException $e) { offer_check(true); }
}

$raw = offer_fixture();
$out = AnyTourThreeProviderOfferContract::fromSearch($raw);
offer_check($raw['offer_ref'] === 'offer-opaque-abc');
offer_check(hash('sha256', $raw['offer_ref']) === $out['identity']['offer_ref_digest']);

echo 'Three-provider offer contract: '.$checks." checks passed; supplier/DB/selection=0.\n";
