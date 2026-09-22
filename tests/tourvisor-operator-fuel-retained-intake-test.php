<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/tourvisor-operator-fuel-retained-intake.php';

function tvfi_ok(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function tvfi_hash(string $value): string { return hash('sha256', $value); }
function tvfi_reject(array $row, string $message): void {
    try { AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($row); }
    catch (InvalidArgumentException|DomainException $expected) { return; }
    throw new RuntimeException($message);
}
function tvfi_search_reject(array $row, string $message): void {
    try { AnyTourTourvisorOperatorFuelRetainedIntakeV1::observationFromSearchRow($row); }
    catch (InvalidArgumentException|DomainException $expected) { return; }
    throw new RuntimeException($message);
}

$segment = static function(string $from, string $to, string $date, string $flight): array {
    return [
        'departure'=>['date'=>$date,'port'=>['id'=>$from,'shortName'=>$from]],
        'arrival'=>['date'=>$date,'port'=>['id'=>$to,'shortName'=>$to]],
        'company'=>['id'=>'ZF','name'=>'AZUR air'],
        'number'=>$flight,
        'fuelCharges'=>[['amount'=>16874,'currency'=>'RUB','name'=>'fuel']],
    ];
};
$base = [
    'tour_id'=>'43282561000937',
    'market'=>'RU-MOW',
    'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
    'tour'=>[
        'id'=>'43282561000937','adults'=>2,'childs'=>0,'currency'=>'RUB','date'=>'2026-10-11',
        'fuelCharge'=>33748,
        'operator'=>['name'=>'Intourist','russianName'=>'Интурист','fullName'=>'Интурист'],
        'nights'=>7,'hotel'=>['id'=>1006],'price'=>176951,
    ],
    'flights'=>[
        'error'=>null,
        'flights'=>[[
            'forward'=>[$segment('VKO','AYT','2026-10-11','ZF1001')],
            'backward'=>[$segment('AYT','VKO','2026-10-18','ZF1002')],
            'dateForward'=>'2026-10-11','dateBackward'=>'2026-10-18',
            'fuelCharge'=>['value'=>33748,'currency'=>'RUB'],
            'isDefault'=>true,'price'=>['value'=>176951,'currency'=>'RUB'],
        ]],
        'info'=>[
            'flags'=>['noFlight'=>false,'noInsurance'=>false,'noMeal'=>false,'noTransfer'=>false],
            'surcharges'=>[],
        ],
    ],
    'tour_response_sha256'=>tvfi_hash('tour-1'),
    'flights_response_sha256'=>tvfi_hash('flights-1'),
    'observed_at'=>1000,'expires_at'=>5000,
    'valid_from'=>'2026-10-01','valid_to'=>'2026-10-31',
];

$searchBase = $base;
unset($searchBase['tour'], $searchBase['tour_response_sha256']);
$searchBase['search_row'] = [
    'tour_id'=>'43282561000937','operator_name'=>'Интурист','date'=>'2026-10-11',
    'adults'=>2,'children'=>0,'currency'=>'RUB','fuel_charge'=>33748,
    // These fields deliberately differ across offers and must not enter fuel scope.
    'hotel_id'=>'1006','nights'=>7,'room_raw'=>'standard','meal_raw'=>'AI','price'=>176951,
];
$searchBase['search_response_sha256'] = tvfi_hash('search-1');
$searchObs = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observationFromSearchRow($searchBase);
tvfi_ok($searchObs['amount'] === '33748' && $searchObs['unit'] === 'party_roundtrip', 'search row fuel retained');
tvfi_ok($searchObs['scope']['outbound']['flight'] === 'ZF1001' && $searchObs['scope']['return']['flight'] === 'ZF1002', 'search row still requires exact flights');

$searchOther = $searchBase;
$searchOther['tour_id'] = '43282575574005';
$searchOther['search_row']['tour_id'] = '43282575574005';
$searchOther['search_row']['hotel_id'] = '1010';
$searchOther['search_row']['nights'] = 10;
$searchOther['search_row']['room_raw'] = 'family';
$searchOther['search_row']['meal_raw'] = 'UAI';
$searchOther['search_row']['price'] = 202269;
$searchOther['search_response_sha256'] = tvfi_hash('search-2');
$searchOther['flights_response_sha256'] = tvfi_hash('flights-2');
$searchObs2 = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observationFromSearchRow($searchOther);
tvfi_ok($searchObs2['scope'] === $searchObs['scope'], 'search hotel nights room meal and base do not split rule');
tvfi_ok($searchObs2['offer_ref_digest'] !== $searchObs['offer_ref_digest'], 'search rows remain independent offers');

$searchTarget = [
    'provider'=>'tourvisor','operator'=>'Интурист','scope'=>[
        'market'=>'RU-MOW','outbound'=>$searchObs['scope']['outbound'],'return'=>$searchObs['scope']['return'],'party'=>$searchObs['scope']['party'],
    ],
    'offer_ref_digest'=>tvfi_hash('search-target'),'flight_dates'=>['2026-10-11','2026-10-18'],
];
tvfi_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($searchTarget, [$searchObs,$searchObs2], 2000) !== null, 'two search+flights rows confirm rule without tour detail');

$searchConflict = $searchBase;
$searchConflict['search_row']['fuel_charge'] = 30000;
tvfi_search_reject($searchConflict, 'search and flights fuel conflict must reject');
$searchWrongParty = $searchBase;
$searchWrongParty['search_row']['adults'] = 1;
tvfi_search_reject($searchWrongParty, 'search row party mismatch must reject');
$searchAnex = $searchBase;
$searchAnex['search_row']['operator_name'] = 'ANEX';
tvfi_search_reject($searchAnex, 'ANEX search row must stay outside this intake');

$obs = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($base);
tvfi_ok($obs['operator_family'] === 'intourist', 'operator retained');
tvfi_ok($obs['amount'] === '33748' && $obs['currency'] === 'RUB', 'aggregate native fuel retained');
tvfi_ok($obs['unit'] === 'party_roundtrip' && $obs['base_relation'] === 'excluded', 'aggregate is not divided');
tvfi_ok($obs['scope']['outbound']['flight'] === 'ZF1001' && $obs['scope']['return']['flight'] === 'ZF1002', 'exact legs retained');
tvfi_ok(!isset($obs['scope']['nights']) && !isset($obs['scope']['hotel']), 'hotel and nights do not split rule');

$otherHotel = $base;
$otherHotel['tour_id'] = '43282575574005';
$otherHotel['tour']['id'] = '43282575574005';
$otherHotel['tour']['hotel']['id'] = 1010;
$otherHotel['tour']['nights'] = 10;
$otherHotel['tour_response_sha256'] = tvfi_hash('tour-2');
$otherHotel['flights_response_sha256'] = tvfi_hash('flights-2');
$obs2 = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($otherHotel);
tvfi_ok($obs2['scope'] === $obs['scope'], 'different hotel and nights reuse scope');
tvfi_ok($obs2['offer_ref_digest'] !== $obs['offer_ref_digest'] && $obs2['evidence_sha256'] !== $obs['evidence_sha256'], 'independent offers retained');

$target = [
    'provider'=>'tourvisor','operator'=>'Интурист','scope'=>[
        'market'=>'RU-MOW','outbound'=>$obs['scope']['outbound'],'return'=>$obs['scope']['return'],'party'=>$obs['scope']['party'],
    ],
    'offer_ref_digest'=>tvfi_hash('target'),'flight_dates'=>['2026-10-11','2026-10-18'],
];
tvfi_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$obs,$obs2], 2000) !== null, 'two retained tours confirm existing rule');

$nullSearchFuel = $base;
$nullSearchFuel['tour']['fuelCharge'] = null;
tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($nullSearchFuel)['amount'] === '33748', 'flights source fills absent detail fuel');

$conflict = $base; $conflict['tour']['fuelCharge'] = 30000;
tvfi_reject($conflict, 'conflicting tour and flights fuel must reject');
$multiLeg = $base; $multiLeg['flights']['flights'][0]['forward'][] = $segment('IST','AYT','2026-10-11','TK2');
tvfi_reject($multiLeg, 'multi-leg route must not be flattened');
$wrongParty = $base; $wrongParty['party'] = ['adults'=>1,'children'=>1,'child_ages'=>[5]];
tvfi_reject($wrongParty, 'exact party mismatch must reject');
$missingAge = $base; $missingAge['tour']['childs']=1; $missingAge['party']=['adults'=>2,'children'=>1,'child_ages'=>[]];
tvfi_reject($missingAge, 'missing child age must reject');
$otherOperator = $base; $otherOperator['tour']['operator']=['name'=>'ANEX'];
tvfi_reject($otherOperator, 'ANEX must stay outside this intake');
$unknownCharges = $base; $unknownCharges['flights']['info']['surcharges']=[['amount'=>10,'currency'=>'EUR','name'=>'insurance']];
tvfi_reject($unknownCharges, 'overlapping required charges must reject');
$twoDefaults = $base; $twoDefaults['flights']['flights'][]=$twoDefaults['flights']['flights'][0];
tvfi_reject($twoDefaults, 'ambiguous default combination must reject');
$differentFlight = $otherHotel; $differentFlight['flights']['flights'][0]['forward'][0]['number']='ZF9999';
$obs3 = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($differentFlight);
tvfi_ok($obs3['scope'] !== $obs['scope'], 'different flight must form different scope');

echo "PASS retained Tourvisor operator fuel intake\n";
