<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-surcharge-group-key.php';

$baseOffer = [
    'provider' => 'andromeda',
    'offer_ref' => 'offer-1',
    'local_hotel_id' => 101,
    'check_in' => '2026-11-01',
    'nights' => 7,
    'adults' => 2,
    'children' => 0,
    'room' => 'STD',
    'meal' => ['label' => 'AI'],
    'operator_ref' => '315',
    'transport_context' => [
        'program_ref' => '5',
        'tour_ref' => '3005',
        'spo_ref' => '40082967',
        'freight_external' => true,
    ],
    'price' => [
        'amount' => '100000',
        'currency' => 'RUB',
        'kind' => 'offer',
        'fees' => 'unknown',
        'final' => false,
    ],
];
$baseRequest = ['departureId' => 1, 'countryId' => 4];

$key = AndromedaSurchargeGroupKey::build($baseOffer, $baseRequest);
assert(is_string($key));
assert(str_starts_with($key, 'andromeda-surcharge-v3:'));
assert(strlen($key) === strlen('andromeda-surcharge-v3:') + 64);

assert(AndromedaSurchargeGroupKey::build($baseOffer, ['params' => $baseRequest]) === $key);
assert(AndromedaSurchargeGroupKey::build($baseOffer, ['params' => ['countryId' => '4', 'departureId' => '1']]) === $key);
assert(AndromedaSurchargeGroupKey::build($baseOffer, $baseRequest + ['childs' => []]) === $key);

$presentationVariant = $baseOffer;
$presentationVariant['offer_ref'] = 'offer-2';
$presentationVariant['local_hotel_id'] = 202;
$presentationVariant['room'] = 'DLX';
$presentationVariant['meal'] = ['label' => 'BB'];
$presentationVariant['price']['amount'] = '145000';
assert(AndromedaSurchargeGroupKey::build($presentationVariant, $baseRequest) === $key);

$spoVariant = $baseOffer;
$spoVariant['transport_context']['spo_ref'] = '39584402';
assert(AndromedaSurchargeGroupKey::build($spoVariant, $baseRequest) === $key);
$missingSpo = $baseOffer;
$missingSpo['transport_context']['spo_ref'] = null;
assert(AndromedaSurchargeGroupKey::build($missingSpo, $baseRequest) === $key);

// Owner rule: nights are not part of surcharge compatibility. 7/10/14 share one key.
foreach ([10, 14, 0] as $nights) {
    $nightVariant = $baseOffer;
    $nightVariant['nights'] = $nights;
    assert(AndromedaSurchargeGroupKey::build($nightVariant, $baseRequest) === $key,
        'number of nights must not split surcharge group');
}
$missingNights = $baseOffer;
unset($missingNights['nights']);
assert(AndromedaSurchargeGroupKey::build($missingNights, $baseRequest) === $key,
    'missing nights do not affect surcharge grouping');

// All other active discriminators stay strict.
$variants = [
    ['offer', 'operator_ref', '342'],
    ['transport', 'program_ref', '25'],
    ['transport', 'tour_ref', '1900'],
    ['offer', 'check_in', '2026-11-02'],
    ['offer', 'adults', 3],
    ['price', 'currency', 'USD'],
    ['request', 'departureId', 2],
    ['request', 'countryId', 5],
];
foreach ($variants as [$where, $field, $value]) {
    $offer = $baseOffer;
    $request = $baseRequest;
    if ($where === 'offer') {
        $offer[$field] = $value;
    } elseif ($where === 'transport') {
        $offer['transport_context'][$field] = $value;
    } elseif ($where === 'price') {
        $offer['price'][$field] = $value;
    } else {
        $request[$field] = $value;
    }
    $variant = AndromedaSurchargeGroupKey::build($offer, $request);
    assert(is_string($variant) && $variant !== $key, $field . ' must split surcharge group');
}

$familyOffer = $baseOffer;
$familyOffer['children'] = 2;
$familyRequest = $baseRequest + ['childs' => [7, 3]];
$familyKey = AndromedaSurchargeGroupKey::build($familyOffer, $familyRequest);
assert(is_string($familyKey) && $familyKey !== $key);
assert(AndromedaSurchargeGroupKey::build($familyOffer, $baseRequest + ['childs' => [3, 7]]) === $familyKey);
assert(AndromedaSurchargeGroupKey::build($familyOffer, ['params' => $baseRequest + ['childs' => ['7', '3']]]) === $familyKey);
$otherAges = AndromedaSurchargeGroupKey::build($familyOffer, $baseRequest + ['childs' => [7, 4]]);
assert(is_string($otherAges) && $otherAges !== $familyKey, 'different child ages must split surcharge group');

$oneChildOffer = $baseOffer;
$oneChildOffer['children'] = 1;
$oneChildKey = AndromedaSurchargeGroupKey::build($oneChildOffer, $baseRequest + ['childs' => [7]]);
assert(is_string($oneChildKey) && $oneChildKey !== $key, 'child count still splits surcharge group');

foreach ([
    $baseRequest,
    $baseRequest + ['childs' => []],
    $baseRequest + ['childs' => [7]],
    $baseRequest + ['childs' => [7, 18]],
    $baseRequest + ['childs' => [7, -1]],
    $baseRequest + ['childs' => [7, 'x']],
    $baseRequest + ['childs' => '7,3'],
] as $badParty) {
    assert(AndromedaSurchargeGroupKey::build($familyOffer, $badParty) === null);
}
assert(AndromedaSurchargeGroupKey::build($baseOffer, $baseRequest + ['childs' => [7]]) === null,
    'zero-child offer must reject contradictory ages');

$missingProgram = $baseOffer;
$missingProgram['transport_context']['program_ref'] = null;
assert(AndromedaSurchargeGroupKey::build($missingProgram, $baseRequest) === null);
$regular = $baseOffer;
$regular['transport_context']['freight_external'] = false;
assert(AndromedaSurchargeGroupKey::build($regular, $baseRequest) === null);
$unknownFreight = $baseOffer;
unset($unknownFreight['transport_context']['freight_external']);
assert(AndromedaSurchargeGroupKey::build($unknownFreight, $baseRequest) === null);

$missingTour = $baseOffer;
$missingTour['transport_context']['tour_ref'] = null;
$missingTourKey = AndromedaSurchargeGroupKey::build($missingTour, $baseRequest);
assert(is_string($missingTourKey) && $missingTourKey !== $key);

$invalidCases = [];
$invalid = $baseOffer; $invalid['provider'] = 'anex'; $invalidCases[] = [$invalid, $baseRequest];
$invalid = $baseOffer; $invalid['transport_context'] = null; $invalidCases[] = [$invalid, $baseRequest];
$invalid = $baseOffer; $invalid['transport_context']['program_ref'] = 'bad/ref'; $invalidCases[] = [$invalid, $baseRequest];
$invalid = $baseOffer; $invalid['transport_context']['tour_ref'] = 'bad/ref'; $invalidCases[] = [$invalid, $baseRequest];
$invalid = $baseOffer; $invalid['check_in'] = '2026-02-30'; $invalidCases[] = [$invalid, $baseRequest];
$invalid = $baseOffer; $invalid['adults'] = 0; $invalidCases[] = [$invalid, $baseRequest];
$invalid = $baseOffer; $invalid['children'] = -1; $invalidCases[] = [$invalid, $baseRequest];
$invalid = $baseOffer; $invalid['price']['currency'] = 'US'; $invalidCases[] = [$invalid, $baseRequest];
$invalidCases[] = [$baseOffer, ['departureId' => 0, 'countryId' => 4]];
$invalidCases[] = [$baseOffer, ['departureId' => 1, 'countryId' => 0]];
foreach ($invalidCases as [$offer, $request]) {
    assert(AndromedaSurchargeGroupKey::build($offer, $request) === null);
}

$flat = $baseOffer;
$flat['program_ref'] = $flat['transport_context']['program_ref'];
$flat['tour_ref'] = $flat['transport_context']['tour_ref'];
$flat['currency'] = $flat['price']['currency'];
unset($flat['transport_context'], $flat['price']);
assert(AndromedaSurchargeGroupKey::build($flat, $baseRequest) === null);

assert(!str_contains($familyKey, 'offer-1'));
assert(!str_contains($familyKey, '101'));
assert(!str_contains($familyKey, '100000'));
assert(!str_contains($familyKey, '40082967'));
assert(strlen($familyKey) === strlen('andromeda-surcharge-v3:') + 64);

echo "Andromeda surcharge group key evidence v3: OK nights_ignored=1\n";
