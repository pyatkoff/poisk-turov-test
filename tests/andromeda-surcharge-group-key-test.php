<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-surcharge-group-key.php';

$baseOffer = [
    'offer_id' => 'offer-1',
    'hotel_id' => 'hotel-1',
    'check_in' => '2026-10-30',
    'nights' => 7,
    'adults' => 2,
    'children' => 0,
    'meal' => 'AI',
    'room' => 'STD',
    'currency' => 'USD',
    'price' => 100000,
    'operator_ref' => 'op1',
    'program_ref' => 'program1',
    'tour_ref' => 'tour1',
    'spo_ref' => 'spo1',
    'initial_package_price' => 100000,
];
$baseRequest = ['departureId' => 1, 'countryId' => 4];

$key = AndromedaSurchargeGroupKey::build($baseOffer, $baseRequest);
assert(is_string($key));
assert(str_starts_with($key, 'andromeda-surcharge-v1:'));
assert(strlen($key) === strlen('andromeda-surcharge-v1:') + 64);

// Hotel presentation and PRICE amount are deliberately outside transport grouping.
$presentationVariant = $baseOffer;
$presentationVariant['offer_id'] = 'offer-2';
$presentationVariant['hotel_id'] = 'hotel-2';
$presentationVariant['room'] = 'DLX';
$presentationVariant['meal'] = 'BB';
$presentationVariant['price'] = 145000;
$presentationVariant['initial_package_price'] = 145000;
assert(AndromedaSurchargeGroupKey::build($presentationVariant, $baseRequest) === $key);

// Canonical numeric/string request forms produce the same key.
assert(AndromedaSurchargeGroupKey::build($baseOffer, ['countryId' => '4', 'departureId' => '1']) === $key);
$lowerCurrency = $baseOffer;
$lowerCurrency['currency'] = 'usd';
assert(AndromedaSurchargeGroupKey::build($lowerCurrency, $baseRequest) === $key);

// Every transport/context discriminator must split the group.
$variants = [
    ['offer', 'operator_ref', 'op2'],
    ['offer', 'program_ref', 'program2'],
    ['offer', 'tour_ref', 'tour2'],
    ['offer', 'spo_ref', 'spo2'],
    ['offer', 'check_in', '2026-10-31'],
    ['offer', 'nights', 8],
    ['offer', 'adults', 3],
    ['offer', 'children', 1],
    ['offer', 'currency', 'EUR'],
    ['request', 'departureId', 2],
    ['request', 'countryId', 5],
];
foreach ($variants as [$where, $field, $value]) {
    $offer = $baseOffer;
    $request = $baseRequest;
    if ($where === 'offer') {
        $offer[$field] = $value;
    } else {
        $request[$field] = $value;
    }
    $variant = AndromedaSurchargeGroupKey::build($offer, $request);
    assert(is_string($variant) && $variant !== $key, $field . ' must split surcharge group');
}

// A transport identity requires operator plus at least one program/tour/SPO ref.
$missingOperator = $baseOffer;
$missingOperator['operator_ref'] = null;
assert(AndromedaSurchargeGroupKey::build($missingOperator, $baseRequest) === null);
$missingTransport = $baseOffer;
$missingTransport['program_ref'] = null;
$missingTransport['tour_ref'] = null;
$missingTransport['spo_ref'] = null;
assert(AndromedaSurchargeGroupKey::build($missingTransport, $baseRequest) === null);

// Invalid exact context is never grouped; sharing evidence would be unsafe.
$invalidCases = [
    [['check_in' => '2026-02-30'], $baseRequest],
    [['nights' => 0], $baseRequest],
    [['adults' => 0], $baseRequest],
    [['children' => -1], $baseRequest],
    [['currency' => 'US'], $baseRequest],
    [$baseOffer, ['departureId' => 0, 'countryId' => 4]],
    [$baseOffer, ['departureId' => 1, 'countryId' => 0]],
];
foreach ($invalidCases as [$offerPatch, $request]) {
    $offer = array_is_list($offerPatch) ? $baseOffer : array_replace($baseOffer, $offerPatch);
    assert(AndromedaSurchargeGroupKey::build($offer, $request) === null);
}

// Invalid optional IDs must fail closed, not silently broaden the group.
$badProgram = $baseOffer;
$badProgram['program_ref'] = 'bad/ref';
assert(AndromedaSurchargeGroupKey::build($badProgram, $baseRequest) === null);

// The opaque key must not expose an offer/hotel/price identifier.
assert(!str_contains($key, 'offer-1'));
assert(!str_contains($key, 'hotel-1'));
assert(!str_contains($key, '100000'));

echo "Andromeda surcharge group key: OK\n";
