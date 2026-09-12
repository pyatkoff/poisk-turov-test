<?php
declare(strict_types=1);

require __DIR__ . '/../app/integrations/three-provider-money-facts.php';
require __DIR__ . '/../app/integrations/three-provider-availability.php';
require __DIR__ . '/../app/integrations/three-provider-flight-details.php';
require __DIR__ . '/../app/integrations/three-provider-operator.php';
require __DIR__ . '/../app/integrations/three-provider-offer-contract.php';
require __DIR__ . '/../app/integrations/three-provider-offer-context.php';
require __DIR__ . '/../app/integrations/three-provider-quote-envelope.php';

$checks = 0;
function quote_env_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('quote_env_check_'.$checks);
}

function quote_env_raw_offer(string $provider = 'andromeda'): array
{
    return [
        'provider' => $provider,
        'operator' => 'ANEX',
        'local_hotel_id' => 3417,
        'provider_hotel_ref' => 'private-provider-hotel-76957',
        'search_ref' => 'private-search-20261005',
        'offer_ref' => 'private-offer-claim-ref',
        'checkin' => '2026-10-05',
        'nights' => 7,
        'adults' => 2,
        'children' => 0,
        'child_ages' => [],
        'meal' => [
            'raw' => 'AI',
            'family' => 'ai',
            'qualifiers' => ['plus' => false, 'without_alcohol' => false],
        ],
        'room' => ['raw' => 'Standard Room', 'normalized' => 'standard room'],
        'placement' => ['raw' => 'DBL', 'normalized' => 'dbl'],
        'availability' => [
            'hotel' => null,
            'flight_outbound_economy' => null,
            'flight_return_economy' => null,
        ],
        'search_price' => [
            'amount' => '119114',
            'currency' => 'RUB',
            'source' => $provider.'_search',
        ],
        'fuel_charge_reported' => null,
        'additional_prices_reported' => [],
        'observed_at' => '2026-09-12T00:00:00Z',
    ];
}

function quote_env_quote(): array
{
    return [
        'schema_version' => 1,
        'provider' => 'andromeda',
        'selection_enabled' => true,
        'booking_enabled' => false,
        'local_id' => 3417,
        'operator' => 'ANEX',
        'search_price' => ['amount' => '119114', 'currency' => 'RUB'],
        'package_price' => ['amount' => '124864', 'currency' => 'RUB'],
        'state' => 'quote_verified',
        'quote_state' => 'verified',
        'final_price' => ['amount' => '135643', 'currency' => 'RUB'],
        'final_price_verified' => true,
        'flight_selection_required' => false,
        'flights' => [
            ['direction' => '0', 'name' => 'OUT'],
            ['direction' => '1', 'name' => 'BACK'],
        ],
    ];
}

function quote_env_setup(string $provider = 'andromeda'): array
{
    $offer = AnyTourThreeProviderOfferContract::fromSearch(quote_env_raw_offer($provider));
    $retained = AnyTourThreeProviderOfferContext::retain($offer, 17, 2, 1789161000, 900);
    $current = [
        'provider' => $retained['provider'],
        'operator' => $retained['operator'],
        'local_hotel_id' => $retained['local_hotel_id'],
        'identity' => $retained['identity'],
        'generation' => $retained['generation'],
        'page' => $retained['page'],
    ];
    return [$offer, $retained, $current];
}

function quote_env_reject(callable $case): void
{
    try {
        $case();
        quote_env_check(false);
    } catch (InvalidArgumentException $error) {
        quote_env_check(true);
    }
}

[$offer, $retained, $current] = quote_env_setup();
$quote = quote_env_quote();
$out = AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $quote, 1789161300);

quote_env_check($out['schema_version'] === 1 && $out['provider'] === 'andromeda');
quote_env_check($out['local_hotel_id'] === 3417);
quote_env_check($out['operator'] === $offer['operator']);
quote_env_check($out['identity'] === $offer['identity']);
quote_env_check(strlen($out['quote_evidence_digest']) === 64
    && preg_match('/^[a-f0-9]{64}$/D', $out['quote_evidence_digest']) === 1);
quote_env_check($out['context']['generation'] === 17 && $out['context']['page'] === 2);
quote_env_check($out['context']['issued_at'] === 1789161000
    && $out['context']['expires_at'] === 1789161900);
quote_env_check($out['context']['current_context_verified'] === true);
quote_env_check($out['money']['search_price']['amount'] === '119114'
    && $out['money']['search_price']['source'] === 'andromeda_search');
quote_env_check($out['money']['package_buyer_price']['amount'] === '124864'
    && $out['money']['package_buyer_price']['source'] === 'andromeda_package');
quote_env_check($out['money']['quote_price']['amount'] === '135643'
    && $out['money']['quote_price']['source'] === 'andromeda_quote');
quote_env_check($out['money']['search_price_fuel_relation'] === 'unknown');
quote_env_check($out['money']['arithmetic_applied'] === false);
quote_env_check($out['money']['fuel_charge_reported'] === null
    && $out['money']['additional_prices_reported'] === []);
quote_env_check($out['quote_state'] === 'verified' && $out['final_price_verified'] === true);
quote_env_check($out['selection_state'] === 'disabled' && $out['booking_enabled'] === false);
quote_env_check(!isset($out['search_ref']) && !isset($out['offer_ref'])
    && !isset($out['supplier_offer_id']) && !isset($out['claiminc']));
quote_env_check(!isset($out['flights']) && !isset($out['final_price']));

$withoutPackage = $quote;
$withoutPackage['package_price'] = null;
$withoutPackageOut = AnyTourThreeProviderQuoteEnvelope::verified(
    $offer, $retained, $current, $withoutPackage, 1789161300
);
quote_env_check($withoutPackageOut['money']['package_buyer_price'] === null);
quote_env_check($withoutPackageOut['money']['quote_price']['amount'] === '135643');
quote_env_check($withoutPackageOut['quote_evidence_digest'] !== $out['quote_evidence_digest']);

$again = AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $quote, 1789161300);
quote_env_check($again === $out);

// Current context and retained offer must stay exact.
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $current; $x['generation'] = 18;
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $x, $quote, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $current; $x['page'] = 3;
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $x, $quote, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $current; $x['local_hotel_id'] = 3418;
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $x, $quote, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $current; $x['identity']['offer_ref_digest'] = str_repeat('a', 64);
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $x, $quote, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $retained; $x['page'] = 3;
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $x, $current, $quote, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $quote, 1789161900);
});

// Quote identity/display facts must bind to the exact canonical offer.
foreach ([
    ['local_id', 3418],
    ['operator', 'FUN&SUN'],
    ['provider', 'anex'],
    ['selection_enabled', false],
    ['booking_enabled', true],
    ['state', 'flight_selection_required'],
    ['quote_state', 'unverified'],
    ['final_price_verified', false],
    ['flight_selection_required', true],
] as [$key, $value]) {
    quote_env_reject(function () use ($offer, $retained, $current, $quote, $key, $value) {
        $x = $quote; $x[$key] = $value;
        AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $x, 1789161300);
    });
}

quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $quote; $x['claiminc'] = 'PRIVATE';
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $x, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $quote; unset($x['flights']);
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $x, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $quote; $x['flights'] = 'loaded';
    AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $x, 1789161300);
});

foreach ([
    ['search_price', ['amount' => '119115', 'currency' => 'RUB']],
    ['search_price', ['amount' => '119114', 'currency' => 'USD']],
    ['search_price', ['amount' => '119114', 'currency' => 'RUB', 'source' => 'private']],
    ['package_price', ['amount' => '0', 'currency' => 'RUB']],
    ['package_price', ['amount' => '124864', 'currency' => 'rub']],
    ['final_price', ['amount' => '0', 'currency' => 'RUB']],
    ['final_price', ['amount' => '135643.999', 'currency' => 'RUB']],
    ['final_price', ['amount' => '135643', 'currency' => 'RUB', 'delta' => '10779']],
] as [$key, $value]) {
    quote_env_reject(function () use ($offer, $retained, $current, $quote, $key, $value) {
        $x = $quote; $x[$key] = $value;
        AnyTourThreeProviderQuoteEnvelope::verified($offer, $retained, $current, $x, 1789161300);
    });
}

// Search-state capability is intentionally Andromeda-only.
[$anexOffer, $anexRetained, $anexCurrent] = quote_env_setup('anex');
$anexQuote = $quote;
$anexQuote['provider'] = 'anex';
quote_env_reject(function () use ($anexOffer, $anexRetained, $anexCurrent, $anexQuote) {
    AnyTourThreeProviderQuoteEnvelope::verified(
        $anexOffer, $anexRetained, $anexCurrent, $anexQuote, 1789161300
    );
});

// An already quote-enriched/tampered offer cannot be retained as a fresh search offer.
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $offer; $x['quote_state'] = 'verified'; $x['final_price_verified'] = true;
    AnyTourThreeProviderQuoteEnvelope::verified($x, $retained, $current, $quote, 1789161300);
});
quote_env_reject(function () use ($offer, $retained, $current, $quote) {
    $x = $offer; $x['money']['quote_price'] = [
        'amount' => '135643', 'currency' => 'RUB', 'source' => 'andromeda_quote'
    ];
    AnyTourThreeProviderQuoteEnvelope::verified($x, $retained, $current, $quote, 1789161300);
});

quote_env_check($offer['money']['search_price']['amount'] === '119114'
    && $offer['money']['quote_price'] === null);
quote_env_check($quote['final_price']['amount'] === '135643'
    && $quote['booking_enabled'] === false);

// No price relationship or delta is inferred from the observed 119114 -> 124864 -> 135643 chain.
quote_env_check(!isset($out['money']['delta']) && !isset($out['money']['total'])
    && $out['money']['search_price_fuel_relation'] === 'unknown');

echo 'Three-provider quote envelope: '.$checks." checks passed; supplier/DB/mapping/selection/booking=0.\n";
