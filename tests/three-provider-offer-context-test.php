<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-money-facts.php';
require __DIR__ . '/../app/integrations/three-provider-availability.php';
require __DIR__ . '/../app/integrations/three-provider-flight-details.php';
require __DIR__ . '/../app/integrations/three-provider-operator.php';
require __DIR__ . '/../app/integrations/three-provider-offer-contract.php';
require __DIR__ . '/../app/integrations/three-provider-offer-context.php';

$checks = 0;
function context_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('context_check_'.$checks);
    }
}

function context_offer(?int $local = 3417): array
{
    return AnyTourThreeProviderOfferContract::fromSearch([
        'provider' => 'andromeda',
        'operator' => 'ANEX',
        'local_hotel_id' => $local,
        'provider_hotel_ref' => 'opaque-hotel',
        'search_ref' => 'opaque-search',
        'offer_ref' => 'opaque-offer',
        'checkin' => '2026-10-05',
        'nights' => 7,
        'adults' => 2,
        'children' => 0,
        'child_ages' => [],
        'meal' => ['raw' => 'AI', 'family' => 'ai',
            'qualifiers' => ['plus' => false, 'without_alcohol' => false]],
        'room' => ['raw' => 'Standard', 'normalized' => 'standard'],
        'placement' => ['raw' => 'DBL', 'normalized' => 'dbl'],
        'availability' => [
            'hotel' => null,
            'flight_outbound_economy' => null,
            'flight_return_economy' => null,
        ],
        'search_price' => ['amount' => '119114', 'currency' => 'RUB', 'source' => 'andromeda_search'],
        'fuel_charge_reported' => null,
        'additional_prices_reported' => [],
        'observed_at' => '2026-09-11T03:00:00Z',
    ]);
}

function current_context(array $retained): array
{
    return [
        'provider' => $retained['provider'],
        'operator' => $retained['operator'],
        'local_hotel_id' => $retained['local_hotel_id'],
        'identity' => $retained['identity'],
        'generation' => $retained['generation'],
        'page' => $retained['page'],
    ];
}

$retained = AnyTourThreeProviderOfferContext::retain(context_offer(), 7, 3, 1000, 300);
context_check($retained['provider'] === 'andromeda');
context_check($retained['operator']['raw'] === 'ANEX' && $retained['operator']['canonical_verified'] === false);
context_check($retained['local_hotel_id'] === 3417);
context_check($retained['generation'] === 7 && $retained['page'] === 3);
context_check($retained['issued_at'] === 1000 && $retained['expires_at'] === 1300);
context_check($retained['current_context_verified'] === false);
context_check($retained['selection_state'] === 'disabled');
context_check(!isset($retained['offer_ref']) && !isset($retained['search_ref']));

$current = current_context($retained);
$result = AnyTourThreeProviderOfferContext::validate($retained, $current, 1299);
context_check($result === [
    'status' => 'current',
    'current_context_verified' => true,
    'selection_state' => 'disabled',
]);
context_check(AnyTourThreeProviderOfferContext::validate($retained, $current, 1300)['status'] === 'expired');

foreach (['provider', 'operator', 'local_hotel_id', 'generation', 'page'] as $key) {
    $changed = $current;
    $changed[$key] = match ($key) {
        'provider' => 'anex',
        'operator' => array_replace($current['operator'], ['raw' => 'Other operator']),
        'local_hotel_id' => 6929,
        'generation' => 8,
        'page' => 4,
    };
    if ($key === 'provider') {
        $changed['operator'] = AnyTourThreeProviderOperator::fromSearch('anex', 'ANEX');
    }
    $mismatch = AnyTourThreeProviderOfferContext::validate($retained, $changed, 1100);
    context_check($mismatch['status'] === 'mismatch' && $mismatch['current_context_verified'] === false);
}
foreach (['search_ref_digest', 'offer_ref_digest', 'provider_hotel_ref_digest'] as $key) {
    $changed = $current;
    $changed['identity'][$key] = str_repeat('b', 64);
    context_check(AnyTourThreeProviderOfferContext::validate($retained, $changed, 1100)['status'] === 'mismatch');
}

$bad = [
    function () { AnyTourThreeProviderOfferContext::retain(context_offer(null), 1, 1, 1000); },
    function () { AnyTourThreeProviderOfferContext::retain(context_offer(), 0, 1, 1000); },
    function () { AnyTourThreeProviderOfferContext::retain(context_offer(), 1, 0, 1000); },
    function () { AnyTourThreeProviderOfferContext::retain(context_offer(), 1, 1, 0); },
    function () { AnyTourThreeProviderOfferContext::retain(context_offer(), 1, 1, 1000, 59); },
    function () { AnyTourThreeProviderOfferContext::retain(context_offer(), 1, 1, 1000, 901); },
    function () {
        $offer = context_offer();
        $offer['identity']['offer_ref_digest'] = 'private-reference';
        AnyTourThreeProviderOfferContext::retain($offer, 1, 1, 1000);
    },
    function () use ($retained, $current) {
        $extra = $current;
        $extra['supplier_ref'] = 'not-allowed';
        AnyTourThreeProviderOfferContext::validate($retained, $extra, 1100);
    },
    function () use ($retained, $current) {
        $changed = $retained;
        $changed['selection_state'] = 'enabled';
        AnyTourThreeProviderOfferContext::validate($changed, $current, 1100);
    },
];
foreach ($bad as $case) {
    try {
        $case();
        context_check(false);
    } catch (InvalidArgumentException $e) {
        context_check(true);
    }
}

context_check(count($retained['identity']) === 3);
context_check($retained['identity']['offer_ref_digest'] === hash('sha256', 'opaque-offer'));

echo 'Three-provider offer context: '.$checks." checks passed; supplier/DB/mapping/selection=0.\n";
