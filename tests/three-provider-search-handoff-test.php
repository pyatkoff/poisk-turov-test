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
function handoff_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('handoff_check_'.$checks);
}
function handoff_reject(callable $case): void
{
    try {
        $case();
        handoff_check(false);
    } catch (InvalidArgumentException $error) {
        handoff_check(true);
    }
}

function handoff_raw(string $provider, string $suffix = 'a'): array
{
    $operator = $provider === 'anex' ? null : 'ANEX';
    return [
        'provider' => $provider,
        'operator' => $operator,
        'local_hotel_id' => 3417,
        'provider_hotel_ref' => 'private-'.$provider.'-hotel-'.$suffix,
        'search_ref' => 'private-'.$provider.'-search-'.$suffix,
        'offer_ref' => 'private-'.$provider.'-offer-'.$suffix,
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
        'placement' => $provider === 'andromeda'
            ? null
            : ['raw' => 'DBL', 'normalized' => 'dbl'],
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
        'fuel_charge_reported' => $provider === 'tourvisor'
            ? ['amount' => '31710', 'currency' => 'RUB', 'source' => 'tourvisor_fuel']
            : null,
        'additional_prices_reported' => [],
        'observed_at' => '2026-09-12T01:30:00Z',
    ];
}

function handoff_setup(string $provider, string $suffix = 'a'): array
{
    $offer = AnyTourThreeProviderOfferContract::fromSearch(handoff_raw($provider, $suffix));
    $retained = AnyTourThreeProviderOfferContext::retain($offer, 23, 1, 1789177500, 900);
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

function handoff_quote(): array
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
            ['direction' => '0', 'name' => 'PRIVATE-FLIGHT-OUT'],
            ['direction' => '1', 'name' => 'PRIVATE-FLIGHT-BACK'],
        ],
    ];
}

$now = 1789177800;
$searchOutputs = [];
foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    [$offer, $retained, $current] = handoff_setup($provider);
    $out = AnyTourThreeProviderSearchHandoff::fromSearchOffer($offer, $retained, $current, $now);
    $searchOutputs[$provider] = $out;

    handoff_check(array_keys($out) === [
        'schema_version', 'provider', 'operator', 'local_hotel_id', 'identity', 'tour',
        'money', 'quote_state', 'final_price_verified', 'quote_evidence_digest',
        'context', 'selection_state', 'booking_enabled',
    ]);
    handoff_check($out['provider'] === $provider && $out['local_hotel_id'] === 3417);
    handoff_check($out['identity'] === $offer['identity']);
    handoff_check($out['tour']['checkin'] === '2026-10-05' && $out['tour']['nights'] === 7);
    handoff_check($out['tour']['party'] === ['adults' => 2, 'children' => 0, 'child_ages' => []]);
    handoff_check($out['tour']['meal']['family'] === 'ai');
    handoff_check($out['money']['search_price']['amount'] === '119114');
    handoff_check($out['money']['package_buyer_price'] === null && $out['money']['quote_price'] === null);
    handoff_check($out['money']['arithmetic_applied'] === false
        && $out['money']['search_price_fuel_relation'] === 'unknown');
    handoff_check($out['quote_state'] === 'unknown' && $out['final_price_verified'] === false
        && $out['quote_evidence_digest'] === null);
    handoff_check($out['selection_state'] === 'disabled' && $out['booking_enabled'] === false);
    handoff_check($out['context']['generation'] === 23 && $out['context']['page'] === 1
        && $out['context']['current_context_verified'] === true);

    $json = json_encode($out, JSON_THROW_ON_ERROR);
    foreach ([
        'private-'.$provider.'-search-a', 'private-'.$provider.'-offer-a',
        'private-'.$provider.'-hotel-a', 'supplier_offer_id', 'claiminc', 'SID', 'UID',
    ] as $private) {
        handoff_check(strpos($json, $private) === false);
    }
}

handoff_check($searchOutputs['tourvisor']['money']['fuel_charge_reported']['amount'] === '31710');
handoff_check($searchOutputs['anex']['money']['fuel_charge_reported'] === null);
handoff_check($searchOutputs['andromeda']['money']['fuel_charge_reported'] === null);
handoff_check($searchOutputs['anex']['operator']['raw'] === null);
handoff_check($searchOutputs['tourvisor']['operator']['raw'] === 'ANEX'
    && $searchOutputs['tourvisor']['operator']['canonical_verified'] === false);

// Verified Andromeda quote enriches only money/provenance, never INT selection authority.
[$andromeda, $retained, $current] = handoff_setup('andromeda');
$quote = handoff_quote();
$verified = AnyTourThreeProviderSearchHandoff::fromVerifiedQuote(
    $andromeda, $retained, $current, $quote, $now
);
handoff_check($verified['provider'] === 'andromeda' && $verified['identity'] === $andromeda['identity']);
handoff_check($verified['money']['search_price'] === [
    'amount' => '119114', 'currency' => 'RUB', 'source' => 'andromeda_search'
]);
handoff_check($verified['money']['package_buyer_price'] === [
    'amount' => '124864', 'currency' => 'RUB', 'source' => 'andromeda_package'
]);
handoff_check($verified['money']['quote_price'] === [
    'amount' => '135643', 'currency' => 'RUB', 'source' => 'andromeda_quote'
]);
handoff_check($verified['money']['arithmetic_applied'] === false
    && $verified['money']['search_price_fuel_relation'] === 'unknown');
handoff_check($verified['quote_state'] === 'verified' && $verified['final_price_verified'] === true);
handoff_check(is_string($verified['quote_evidence_digest'])
    && preg_match('/^[a-f0-9]{64}$/D', $verified['quote_evidence_digest']) === 1);
handoff_check($verified['selection_state'] === 'disabled' && $verified['booking_enabled'] === false);
handoff_check(!isset($verified['money']['delta']) && !isset($verified['money']['total']));
$verifiedJson = json_encode($verified, JSON_THROW_ON_ERROR);
foreach (['PRIVATE-FLIGHT-OUT', 'PRIVATE-FLIGHT-BACK', 'supplier_offer_id', 'claiminc', 'SID', 'UID'] as $private) {
    handoff_check(strpos($verifiedJson, $private) === false);
}

// A provider failure or bad quote must not mutate the safe search handoff.
$baseline = AnyTourThreeProviderSearchHandoff::fromSearchOffer($andromeda, $retained, $current, $now);
$badQuote = $quote;
$badQuote['local_id'] = 3418;
handoff_reject(static function () use ($andromeda, $retained, $current, $badQuote, $now): void {
    AnyTourThreeProviderSearchHandoff::fromVerifiedQuote($andromeda, $retained, $current, $badQuote, $now);
});
handoff_check(AnyTourThreeProviderSearchHandoff::fromSearchOffer(
    $andromeda, $retained, $current, $now
) === $baseline);

// Verified quote enrichment is currently Andromeda-only.
foreach (['tourvisor', 'anex'] as $provider) {
    [$offer, $providerRetained, $providerCurrent] = handoff_setup($provider);
    $providerQuote = $quote;
    $providerQuote['provider'] = $provider;
    $providerQuote['operator'] = $offer['operator']['raw'];
    handoff_reject(static function () use ($offer, $providerRetained, $providerCurrent, $providerQuote, $now): void {
        AnyTourThreeProviderSearchHandoff::fromVerifiedQuote(
            $offer, $providerRetained, $providerCurrent, $providerQuote, $now
        );
    });
}

// Current-context and A->B race guards.
[$offerA, $retainedA, $currentA] = handoff_setup('andromeda', 'a');
[$offerB, $retainedB, $currentB] = handoff_setup('andromeda', 'b');
handoff_check($offerA['identity']['offer_ref_digest'] !== $offerB['identity']['offer_ref_digest']);
handoff_reject(static function () use ($offerA, $retainedA, $currentB, $now): void {
    AnyTourThreeProviderSearchHandoff::fromSearchOffer($offerA, $retainedA, $currentB, $now);
});
handoff_reject(static function () use ($offerA, $retainedA, $currentA): void {
    AnyTourThreeProviderSearchHandoff::fromSearchOffer($offerA, $retainedA, $currentA, 1789178400);
});
foreach ([
    ['generation', 24], ['page', 2], ['local_hotel_id', 3418],
] as [$key, $value]) {
    handoff_reject(static function () use ($offerA, $retainedA, $currentA, $key, $value, $now): void {
        $x = $currentA;
        $x[$key] = $value;
        AnyTourThreeProviderSearchHandoff::fromSearchOffer($offerA, $retainedA, $x, $now);
    });
}

// Nested canonical facts are fail-closed at the final handoff, not blindly copied.
$tamperCases = [
    static function (array $offer): array {
        $offer['money']['search_price']['source'] = 'andromeda_quote'; return $offer;
    },
    static function (array $offer): array {
        $offer['money']['quote_price'] = ['amount' => '135643', 'currency' => 'RUB', 'source' => 'andromeda_quote']; return $offer;
    },
    static function (array $offer): array {
        $offer['room']['comparison_scope'] = 'package_identity'; return $offer;
    },
    static function (array $offer): array {
        $offer['meal']['family_verified'] = false; return $offer;
    },
    static function (array $offer): array {
        $offer['availability']['hotel']['canonical_state'] = 'available'; return $offer;
    },
    static function (array $offer): array {
        $offer['flight_details']['automatic_fetch_allowed'] = true; return $offer;
    },
    static function (array $offer): array {
        $offer['observed_at'] = '2026-02-30T01:30:00Z'; return $offer;
    },
    static function (array $offer): array {
        $offer['party']['children'] = 1; return $offer;
    },
];
foreach ($tamperCases as $tamper) {
    handoff_reject(static function () use ($andromeda, $retained, $current, $tamper, $now): void {
        $x = $tamper($andromeda);
        AnyTourThreeProviderSearchHandoff::fromSearchOffer($x, $retained, $current, $now);
    });
}

// Quote state cannot be forged through the search-offer path.
handoff_reject(static function () use ($andromeda, $retained, $current, $now): void {
    $x = $andromeda;
    $x['quote_state'] = 'verified';
    $x['final_price_verified'] = true;
    AnyTourThreeProviderSearchHandoff::fromSearchOffer($x, $retained, $current, $now);
});

// Final DTO remains deterministic for the same immutable input/context.
handoff_check(AnyTourThreeProviderSearchHandoff::fromVerifiedQuote(
    $andromeda, $retained, $current, $quote, $now
) === $verified);

echo 'Three-provider SEARCH handoff: '.$checks." checks passed; supplier/DB/mapping/selection/booking=0.\n";
