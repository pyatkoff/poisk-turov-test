<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/three-provider-price-actualization-observation.php';

$checks = 0;
function actualization_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('actualization_check_' . $checks);
}
function actualization_reject(callable $case, string $message): void
{
    try {
        $case();
        actualization_check(false);
    } catch (InvalidArgumentException $error) {
        actualization_check($error->getMessage() === $message);
    }
}
function actualization_listing(string $amount = '185125'): array
{
    return [
        'schema_version' => 1,
        'provider' => 'andromeda',
        'operator' => 'ANEX',
        'local_hotel_id' => 17449,
        'identity' => ['namespace' => 'andromeda', 'key' => 'safe-identity'],
        'tour' => [
            'checkin' => '2026-12-28',
            'nights' => 7,
            'party' => ['adults' => 3, 'children' => 0, 'child_ages' => []],
            'meal' => ['raw' => 'AI'],
            'room' => ['raw' => 'Standard'],
            'placement' => null,
            'availability' => [],
            'flight_details' => [],
            'observed_at' => '2026-09-16T08:00:00Z',
        ],
        'money' => ['search_price' => ['amount' => '185125', 'currency' => 'RUB']],
        'quote_state' => 'unknown',
        'final_price_verified' => false,
        'quote_evidence_digest' => null,
        'context' => [
            'generation' => 44,
            'page' => 1,
            'issued_at' => 1789545600,
            'expires_at' => 1789546500,
            'current_context_verified' => true,
        ],
        'selection_state' => 'disabled',
        'booking_enabled' => false,
        'finalPriceReady' => true,
        'finalPrice' => $amount,
        'price' => $amount,
        'currency' => 'RUB',
    ];
}
function actualization_quote(string $amount = '199390'): array
{
    $listing = actualization_listing();
    unset($listing['finalPriceReady'], $listing['finalPrice'], $listing['price'], $listing['currency']);
    $listing['quote_state'] = 'verified';
    $listing['final_price_verified'] = true;
    $listing['quote_evidence_digest'] = str_repeat('a', 64);
    $listing['money'] = [
        'search_price' => ['amount' => '185125', 'currency' => 'RUB'],
        'quote_price' => ['amount' => $amount, 'currency' => 'RUB', 'source' => 'andromeda_quote'],
    ];
    return $listing;
}

// Natural user actualization may prove that the listing changed; record facts, do not rewrite price.
$changed = AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(
    actualization_listing('185125'),
    actualization_quote('199390')
);
actualization_check($changed['provider'] === 'andromeda' && $changed['operator'] === 'ANEX');
actualization_check($changed['local_hotel_id'] === 17449);
actualization_check($changed['listing_price'] === ['amount' => '185125', 'currency' => 'RUB']);
actualization_check($changed['verified_quote_price'] === ['amount' => '199390', 'currency' => 'RUB']);
actualization_check($changed['exact_match'] === false);
actualization_check($changed['listing_final_price_ready'] === true && $changed['quote_final_price_verified'] === true);
actualization_check($changed['context'] === ['generation' => 44, 'page' => 1]);
actualization_check($changed['tour']['party']['adults'] === 3);

// A stable quote is equally valid evidence.
$stable = AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(
    actualization_listing('185125'),
    actualization_quote('185125')
);
actualization_check($stable['exact_match'] === true);
actualization_check($stable['listing_price'] === $stable['verified_quote_price']);

// Decimal spelling is not a price change. Preserve both supplier money facts exactly.
foreach ([
    ['199390', '199390.00', true],
    ['199390.0', '199390', true],
    ['199390.10', '199390.1', true],
    ['0.1', '0.10', true],
    ['999999999999.9', '999999999999.90', true],
    ['199390.00', '199390.01', false],
    ['999999999999.98', '999999999999.99', false],
] as [$listed, $quoted, $expected]) {
    $listing = actualization_listing($listed);
    $quote = actualization_quote($quoted);
    $before = [$listing, $quote];
    $observation = AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote($listing, $quote);
    actualization_check($observation['exact_match'] === $expected);
    actualization_check($observation['listing_price']['amount'] === $listed
        && $observation['verified_quote_price']['amount'] === $quoted);
    actualization_check([$listing, $quote] === $before);
}
foreach (['199390.001', '0199390', '1e5', '0.00'] as $invalid) {
    actualization_reject(static function () use ($invalid): void {
        AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(
            actualization_listing('199390'), actualization_quote($invalid));
    }, 'THREE_PROVIDER_ACTUALIZATION_QUOTE_MONEY');
}

// Fail closed: never compare different retained offers or unverified/not-ready money.
$wrongHotel = actualization_quote();
$wrongHotel['local_hotel_id'] = 999;
actualization_reject(static function () use ($wrongHotel): void {
    AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(actualization_listing(), $wrongHotel);
}, 'THREE_PROVIDER_ACTUALIZATION_IDENTITY');

$wrongParty = actualization_quote();
$wrongParty['tour']['party']['adults'] = 2;
actualization_reject(static function () use ($wrongParty): void {
    AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(actualization_listing(), $wrongParty);
}, 'THREE_PROVIDER_ACTUALIZATION_TOUR');

$wrongGeneration = actualization_quote();
$wrongGeneration['context']['generation'] = 45;
actualization_reject(static function () use ($wrongGeneration): void {
    AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(actualization_listing(), $wrongGeneration);
}, 'THREE_PROVIDER_ACTUALIZATION_CONTEXT');

$notReady = actualization_listing();
$notReady['finalPriceReady'] = false;
actualization_reject(static function () use ($notReady): void {
    AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote($notReady, actualization_quote());
}, 'THREE_PROVIDER_ACTUALIZATION_LISTING');

$notVerified = actualization_quote();
$notVerified['final_price_verified'] = false;
actualization_reject(static function () use ($notVerified): void {
    AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(actualization_listing(), $notVerified);
}, 'THREE_PROVIDER_ACTUALIZATION_QUOTE');

$wrongCurrency = actualization_quote();
$wrongCurrency['money']['quote_price']['currency'] = 'EUR';
actualization_reject(static function () use ($wrongCurrency): void {
    AnyTourThreeProviderPriceActualizationObservation::fromListingAndVerifiedQuote(actualization_listing(), $wrongCurrency);
}, 'THREE_PROVIDER_ACTUALIZATION_QUOTE_MONEY');

// Observation output is browser-safe and deliberately contains no private supplier refs/digests.
$json = json_encode($changed, JSON_THROW_ON_ERROR);
foreach (['supplier_offer_id', 'offer_ref', 'search_ref', 'claiminc', 'externalOfferId', 'quote_evidence_digest', 'safe-identity'] as $forbidden) {
    actualization_check(strpos($json, $forbidden) === false);
}

echo 'Three-provider natural actualization observation: ' . $checks . " checks passed; supplier/DB/price-write/booking=0.\n";
