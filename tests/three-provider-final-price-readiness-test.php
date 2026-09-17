<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/three-provider-money-facts.php';
require_once __DIR__ . '/../app/integrations/three-provider-availability.php';
require_once __DIR__ . '/../app/integrations/three-provider-flight-details.php';
require_once __DIR__ . '/../app/integrations/three-provider-operator.php';
require_once __DIR__ . '/../app/integrations/three-provider-offer-contract.php';
require_once __DIR__ . '/../app/integrations/three-provider-offer-context.php';
require_once __DIR__ . '/../app/integrations/three-provider-quote-envelope.php';
require_once __DIR__ . '/../app/integrations/three-provider-search-handoff.php';

$checks = 0;
function readiness_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('readiness_check_' . $checks);
}
function readiness_reject(callable $case): void
{
    try {
        $case();
        readiness_check(false);
    } catch (InvalidArgumentException $error) {
        readiness_check($error->getMessage() === 'THREE_PROVIDER_HANDOFF_PRICE');
    }
}
function readiness_raw(string $provider, int $adults, ?array $fuel, array $additional, string $amount): array
{
    return [
        'provider' => $provider,
        'operator' => $provider === 'anex' ? null : 'ANEX',
        'local_hotel_id' => 3417,
        'provider_hotel_ref' => 'private-' . $provider . '-hotel',
        'search_ref' => 'private-' . $provider . '-search',
        'offer_ref' => 'private-' . $provider . '-offer',
        'checkin' => '2026-10-05',
        'nights' => 7,
        'adults' => $adults,
        'children' => 0,
        'child_ages' => [],
        'meal' => ['raw' => 'AI', 'family' => 'ai', 'qualifiers' => ['plus' => false, 'without_alcohol' => false]],
        'room' => ['raw' => 'Standard Room', 'normalized' => 'standard room'],
        'placement' => $provider === 'andromeda' ? null : ['raw' => 'DBL', 'normalized' => 'dbl'],
        'availability' => ['hotel' => null, 'flight_outbound_economy' => null, 'flight_return_economy' => null],
        'search_price' => ['amount' => $amount, 'currency' => 'RUB', 'source' => $provider . '_search'],
        'fuel_charge_reported' => $fuel,
        'additional_prices_reported' => $additional,
        'observed_at' => '2026-09-15T08:30:00Z',
    ];
}
function readiness_setup(array $raw): array
{
    $offer = AnyTourThreeProviderOfferContract::fromSearch($raw);
    $retained = AnyTourThreeProviderOfferContext::retain($offer, 31, 1, 1789459200, 900);
    $current = [
        'provider' => $retained['provider'],
        'operator' => $retained['operator'],
        'local_hotel_id' => $retained['local_hotel_id'],
        'identity' => $retained['identity'],
        'generation' => 31,
        'page' => 1,
    ];
    return [$offer, $retained, $current];
}
$now = 1789459500;

// Tourvisor search/display price is ready only when the separately retained fuel fact exists.
[$tv, $tvRetained, $tvCurrent] = readiness_setup(readiness_raw(
    'tourvisor',
    2,
    ['amount' => '31710', 'currency' => 'RUB', 'source' => 'tourvisor_fuel'],
    [],
    '150824'
));
$tvReady = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer($tv, $tvRetained, $tvCurrent, $now);
readiness_check($tvReady['finalPriceReady'] === true);
readiness_check($tvReady['finalPrice'] === '150824' && $tvReady['price'] === '150824' && $tvReady['currency'] === 'RUB');
readiness_check($tvReady['money'] === $tv['money']);
readiness_check($tvReady['final_price_verified'] === false && $tvReady['quote_state'] === 'unknown');
readiness_check($tvReady['money']['fuel_charge_reported']['amount'] === '31710');

[$tvZero, $tvZeroRetained, $tvZeroCurrent] = readiness_setup(readiness_raw(
    'tourvisor',
    2,
    ['amount' => '0', 'currency' => 'RUB', 'source' => 'tourvisor_fuel'],
    [],
    '119114'
));
$tvZeroReady = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer($tvZero, $tvZeroRetained, $tvZeroCurrent, $now);
readiness_check($tvZeroReady['finalPriceReady'] === true && $tvZeroReady['price'] === '119114');

[$tvUnknown, $tvUnknownRetained, $tvUnknownCurrent] = readiness_setup(readiness_raw('tourvisor', 2, null, [], '119114'));
$tvNotReady = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer($tvUnknown, $tvUnknownRetained, $tvUnknownCurrent, $now);
readiness_check($tvNotReady['finalPriceReady'] === false);
readiness_check($tvNotReady['finalPrice'] === null && $tvNotReady['price'] === null);
readiness_check($tvNotReady['money']['search_price']['amount'] === '119114');

// Direct ANEX readiness consumes the existing per-passenger surcharge estimator result.
[$anex, $anexRetained, $anexCurrent] = readiness_setup(readiness_raw(
    'anex',
    2,
    null,
    [['kind' => 'fuel_adult', 'amount' => '5000', 'currency' => 'RUB', 'source' => 'anex_additional']],
    '100000'
));
$anexPriced = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($anex['money'], 2, 0);
$anexReady = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $anex, $anexRetained, $anexCurrent, $now, $anexPriced
);
readiness_check($anexReady['finalPriceReady'] === true);
readiness_check($anexReady['finalPrice'] === '110000' && $anexReady['price'] === '110000');
readiness_check($anexReady['money'] === $anexPriced && $anexReady['money']['arithmetic_applied'] === true);
readiness_check($anexReady['money']['search_price']['amount'] === '100000');
readiness_check($anexReady['final_price_verified'] === false);

$anexUnknown = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $anex, $anexRetained, $anexCurrent, $now
);
readiness_check($anexUnknown['finalPriceReady'] === false && $anexUnknown['price'] === null);
$tampered = $anexPriced;
$tampered['search_price_with_surcharge']['amount'] = '109999';
readiness_reject(static function () use ($anex, $anexRetained, $anexCurrent, $now, $tampered): void {
    AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer($anex, $anexRetained, $anexCurrent, $now, $tampered);
});

// Andromeda get_flights markup is already scoped to the current tourist party. It is
// listing-ready only when that explicit party surcharge is present, and is added once.
[$andromeda, $andromedaRetained, $andromedaCurrent] = readiness_setup(readiness_raw(
    'andromeda',
    3,
    null,
    [['kind' => 'party_transport_surcharge', 'amount' => '21556.80', 'currency' => 'RUB', 'source' => 'andromeda_get_flights_transport']],
    '144790'
));
$andromedaPriced = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($andromeda['money'], 3, 0);
$andromedaReady = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $andromeda, $andromedaRetained, $andromedaCurrent, $now, $andromedaPriced
);
readiness_check($andromedaReady['finalPriceReady'] === true);
readiness_check($andromedaReady['finalPrice'] === '166346.80' && $andromedaReady['price'] === '166346.80');
readiness_check($andromedaReady['money']['search_price_with_surcharge']['amount'] === '166346.80');
readiness_check($andromedaReady['money']['additional_prices_reported'][0]['source'] === 'andromeda_additional');
readiness_check($andromedaReady['money']['search_price']['amount'] === '144790');
readiness_check($andromedaReady['final_price_verified'] === false && $andromedaReady['quote_state'] === 'unknown');

// An explicit zero party surcharge is known transport money, not missing money.
[$andromedaZero, $andromedaZeroRetained, $andromedaZeroCurrent] = readiness_setup(readiness_raw(
    'andromeda',
    3,
    null,
    [['kind' => 'party_transport_surcharge', 'amount' => '0', 'currency' => 'RUB', 'source' => 'andromeda_additional']],
    '185125'
));
$andromedaZeroPriced = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($andromedaZero['money'], 3, 0);
$andromedaZeroReady = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $andromedaZero, $andromedaZeroRetained, $andromedaZeroCurrent, $now, $andromedaZeroPriced
);
readiness_check($andromedaZeroPriced['search_price_with_surcharge']['amount'] === '185125');
readiness_check($andromedaZeroReady['finalPriceReady'] === true
    && $andromedaZeroReady['finalPrice'] === '185125' && $andromedaZeroReady['price'] === '185125');
readiness_check($andromedaZeroReady['final_price_verified'] === false && $andromedaZeroReady['quote_state'] === 'unknown');

// Missing party surcharge is still fail-closed and never falls back to base search price.
$andromedaUnknown = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $andromeda, $andromedaRetained, $andromedaCurrent, $now
);
readiness_check($andromedaUnknown['finalPriceReady'] === false && $andromedaUnknown['finalPrice'] === null
    && $andromedaUnknown['price'] === null);

// Passenger counts must not multiply a supplier party-level markup. The neutral money
// result is identical and remains acceptable for the retained offer context.
$andromedaDifferentPartyArgs = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($andromeda['money'], 2, 0);
readiness_check($andromedaDifferentPartyArgs === $andromedaPriced);
$andromedaSameReady = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
    $andromeda, $andromedaRetained, $andromedaCurrent, $now, $andromedaDifferentPartyArgs
);
readiness_check($andromedaSameReady['finalPrice'] === '166346.80');

$tamperedAndromeda = $andromedaPriced;
$tamperedAndromeda['search_price_with_surcharge']['amount'] = '166346.79';
readiness_reject(static function () use ($andromeda, $andromedaRetained, $andromedaCurrent, $now, $tamperedAndromeda): void {
    AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
        $andromeda, $andromedaRetained, $andromedaCurrent, $now, $tamperedAndromeda
    );
});

// Browser-safe DTO still excludes private supplier/search/offer references.
foreach ([$tvReady, $anexReady, $andromedaReady, $andromedaZeroReady] as $dto) {
    $json = json_encode($dto, JSON_THROW_ON_ERROR);
    readiness_check(strpos($json, 'private-') === false);
    readiness_check(strpos($json, 'supplier_offer_id') === false && strpos($json, 'claiminc') === false);
    readiness_check($dto['selection_state'] === 'disabled' && $dto['booking_enabled'] === false);
}

echo 'Three-provider final-price readiness: ' . $checks . " checks passed; supplier/DB/mapping/selection/booking=0.\n";