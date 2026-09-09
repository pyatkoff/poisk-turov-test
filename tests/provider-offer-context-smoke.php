<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/provider-offer-context.php';

$checks = 0;
function contextCheck(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Synthetic application contexts, NOT responses or confirmed IDs from Andromeda.
$base = [
    'provider' => 'tourvisor', 'supplier_namespace' => 'tourvisor',
    'generation' => 1, 'search_ref' => 'search:1', 'offer_ref' => 'offer:1',
    'external_hotel_id' => '5200', 'local_hotel_id' => null, 'operator_ref' => 'anex',
];
$current = array_intersect_key($base, array_flip(['provider', 'supplier_namespace', 'generation', 'search_ref']));
$first = AnyTourProviderOfferContext::fromArray($base);
contextCheck($first->toArray() === $base, 'preserve source, operator and unmapped ID');
contextCheck($first->matchesSearch($current), 'current search matches');
$copy = $first->toArray();
$copy['generation'] = 90;
contextCheck($first->matchesSearch($current), 'returned context cannot mutate retained state');

$hotelKeys = [];
$offerKeys = [];
foreach (['tourvisor', 'anex', 'andromeda'] as $provider) {
    $context = AnyTourProviderOfferContext::fromArray(array_replace($base, [
        'provider' => $provider, 'supplier_namespace' => $provider,
    ]));
    $hotelKeys[] = $context->hotelKey();
    $offerKeys[] = $context->offerKey();
    contextCheck($context->toArray()['local_hotel_id'] === null, 'no inferred mapping');
    contextCheck($context->toArray()['operator_ref'] === 'anex', 'operator does not choose provider');
}
contextCheck(count(array_unique($hotelKeys)) === 3, 'three equal numeric IDs are different hotels');
contextCheck(count(array_unique($offerKeys)) === 3, 'three sources do not overwrite equal offer IDs');

foreach ([
    ['provider' => 'andromeda'], ['supplier_namespace' => 'anex_online'],
    ['generation' => 2], ['search_ref' => 'point:2'],
] as $change) {
    $other = AnyTourProviderOfferContext::fromArray(array_replace($base, $change));
    contextCheck($other->offerKey() !== $first->offerKey(), 'source/search/generation isolated');
    contextCheck(!$first->matchesSearch(array_replace($current, $change)), 'reject stale or other source');
}
foreach ([['offer_ref' => 'offer:2'], ['external_hotel_id' => '005200']] as $change) {
    $other = AnyTourProviderOfferContext::fromArray(array_replace($base, $change));
    contextCheck($other->offerKey() !== $first->offerKey(), 'opaque offer/hotel IDs stay distinct');
}
$mapped = AnyTourProviderOfferContext::fromArray(array_replace($base, ['local_hotel_id' => 21477]));
contextCheck($mapped->hotelKey() === $first->hotelKey(), 'accepted local mapping does not rewrite supplier identity');
contextCheck($mapped->offerKey() === $first->offerKey(), 'mapping does not rewrite offer identity');
contextCheck($mapped->toArray()['local_hotel_id'] === 21477, 'retain explicit local mapping');
$delimitedA = AnyTourProviderOfferContext::fromArray(array_replace($base, ['search_ref' => 'a:b', 'offer_ref' => 'c']));
$delimitedB = AnyTourProviderOfferContext::fromArray(array_replace($base, ['search_ref' => 'a', 'offer_ref' => 'b:c']));
contextCheck($delimitedA->offerKey() !== $delimitedB->offerKey(), 'structured tuple avoids separator collision');
$reordered = AnyTourProviderOfferContext::fromArray(array_reverse($base, true));
contextCheck($reordered->offerKey() === $first->offerKey(), 'input key order is immaterial');
contextCheck($first->hotelKey() === AnyTourProviderOfferContext::fromArray(array_replace($base, ['generation' => 2]))->hotelKey(), 'hotel identity survives a new search');

$invalid = [
    ['generation' => 0], ['generation' => '1'], ['generation' => true],
    ['provider' => 'ANEX'], ['supplier_namespace' => 'bad:namespace'],
    ['search_ref' => ''], ['offer_ref' => 1], ['offer_ref' => []],
    ['offer_ref' => "bad\0id"], ['offer_ref' => "bad\nid"],
    ['offer_ref' => str_repeat('x', 2049)], ['offer_ref' => "\xff"],
    ['external_hotel_id' => 5200], ['external_hotel_id' => ' 5200'],
    ['local_hotel_id' => '21477'], ['local_hotel_id' => 0], ['local_hotel_id' => true],
    ['operator_ref' => false], ['search_ref' => 'https://example.invalid/private'],
    ['unexpected' => 'fixture-sensitive-value'],
];
foreach ($invalid as $change) {
    $rejected = false;
    try {
        AnyTourProviderOfferContext::fromArray(array_replace($base, $change));
    } catch (InvalidArgumentException $error) {
        $rejected = $error->getMessage() === 'invalid_provider_offer_context';
    }
    contextCheck($rejected, 'reject malformed input without echoing its contents');
}
foreach (array_keys($base) as $key) {
    $missing = $base;
    unset($missing[$key]);
    $rejected = false;
    try {
        AnyTourProviderOfferContext::fromArray($missing);
    } catch (InvalidArgumentException $error) {
        $rejected = true;
    }
    contextCheck($rejected, 'missing explicit context rejected');
}
foreach ([[], array_replace($current, ['generation' => '1']), $current + ['token' => 'fixture-secret']] as $badCurrent) {
    contextCheck(!$first->matchesSearch($badCurrent), 'malformed current context cannot match');
}
contextCheck(strpos($first->offerKey(), $base['offer_ref']) === false, 'key does not expose the opaque reference');
echo "Provider offer context: $checks checks passed\n";
