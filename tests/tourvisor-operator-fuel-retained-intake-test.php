<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/tourvisor-operator-fuel-retained-intake.php';

function tvfi_ok(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function tvfi_hash(string $value): string { return hash('sha256', $value); }
function tvfi_reject(array $row, string $message, ?string $code = null): void {
    try { AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($row); }
    catch (InvalidArgumentException|DomainException $expected) {
        tvfi_ok($code === null || $expected->getMessage() === $code, $message . ': wrong rejection code');
        return;
    }
    throw new RuntimeException($message);
}
function tvfi_search_reject(array $row, string $message, ?string $code = null): void {
    try { AnyTourTourvisorOperatorFuelRetainedIntakeV1::observationFromSearchRow($row); }
    catch (InvalidArgumentException|DomainException $expected) {
        tvfi_ok($code === null || $expected->getMessage() === $code, $message . ': wrong rejection code');
        return;
    }
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
tvfi_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($searchTarget, [$searchObs,$searchObs2], 2000) === null, 'two search+flights observations await accuracy evaluation');

$searchConflict = $searchBase;
$searchConflict['search_row']['fuel_charge'] = 30000;
$searchDifferent = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observationFromSearchRow($searchConflict);
tvfi_ok($searchDifferent['monitoring'] === ['tour_amount'=>'30000','flight_amount'=>'33748',
    'currency'=>'RUB','comparison'=>'different'], 'different search and flight amounts both retained');
$searchWrongParty = $searchBase;
$searchWrongParty['search_row']['adults'] = 1;
tvfi_search_reject($searchWrongParty, 'search row party mismatch must reject');
$searchAnex = $searchBase;
$searchAnex['search_row']['operator_name'] = 'ANEX';
tvfi_search_reject($searchAnex, 'ANEX search row must stay outside this intake');

$obs = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($base);
tvfi_ok($obs['operator_family'] === 'intourist', 'operator retained');
tvfi_ok($obs['amount'] === '33748' && $obs['currency'] === 'RUB', 'aggregate native fuel retained');
tvfi_ok($obs['unit'] === 'party_roundtrip' && $obs['base_relation'] === 'included', 'aggregate is already included in Tourvisor search total');
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
tvfi_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$obs,$obs2], 2000) === null, 'matching tours remain monitoring data until accuracy evaluation');
tvfi_ok($obs['monitoring']['comparison']==='matched', 'matching stage amounts retained for the same monitoring denominator');

$nullSearchFuel = $base;
$nullSearchFuel['tour']['fuelCharge'] = null;
tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($nullSearchFuel)['amount'] === '33748', 'flights source fills absent detail fuel');
tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($nullSearchFuel)['monitoring']['tour_amount'] === null,
    'missing detail fuel is not converted to zero');

// Branch tags are diagnostic metadata; all these original amount guards still reject.
$amountCases = [
    'flight-absent'=>[null,7101,'TV_FUEL_AMOUNT_FLIGHT_MISSING'],
    'flight-null'=>[null,7101,'TV_FUEL_AMOUNT_FLIGHT_MISSING'],
    'flight-shape'=>[['private-amount-canary'],7102,'TV_FUEL_AMOUNT_FLIGHT_SHAPE'],
    'value-absent'=>[['currency'=>'RUB'],7103,'TV_FUEL_AMOUNT_VALUE_MISSING'],
    'value-null'=>[['value'=>null,'currency'=>'RUB'],7103,'TV_FUEL_AMOUNT_VALUE_MISSING'],
    'value-format'=>[['value'=>'private-amount-canary','currency'=>'RUB'],7104,'TV_FUEL_AMOUNT_VALUE_FORMAT'],
    'value-bool'=>[['value'=>false,'currency'=>'RUB'],7104,'TV_FUEL_AMOUNT_VALUE_FORMAT'],
    'value-negative'=>[['value'=>-1,'currency'=>'RUB'],7104,'TV_FUEL_AMOUNT_VALUE_FORMAT'],
    'value-precision'=>[['value'=>1.001,'currency'=>'RUB'],7104,'TV_FUEL_AMOUNT_VALUE_FORMAT'],
    'value-zero'=>[['value'=>0,'currency'=>'RUB'],7105,'TV_FUEL_AMOUNT_NONPOSITIVE'],
    'value-string-zero'=>[['value'=>'0.00','currency'=>'RUB'],7105,'TV_FUEL_AMOUNT_NONPOSITIVE'],
    'tour-format'=>[$base['flights']['flights'][0]['fuelCharge'],7106,'TV_FUEL_AMOUNT_TOUR_VALUE'],
];
foreach ($amountCases as $kind=>[$fuel,$tag,$diagnostic]) {
    $bad = $base;
    $bad['flights']['flights'][0]['fuelCharge'] = $fuel;
    if ($kind === 'flight-absent') unset($bad['flights']['flights'][0]['fuelCharge']);
    if ($kind === 'tour-format') $bad['tour']['fuelCharge'] = 'private-amount-canary';
    $before = serialize($bad);
    try {
        AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($bad);
        throw new RuntimeException('Missing original amount rejection: '.$kind);
    } catch (InvalidArgumentException $error) {
        tvfi_ok($error->getMessage()==='TV_FUEL_AMOUNT' && $error->getCode()===$tag, 'outward original amount rejection retained: '.$kind);
        tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::amountDiagnostic($error)===$diagnostic, 'safe exact branch: '.$kind);
    }
    tvfi_ok(serialize($bad)===$before, 'diagnostic classification cannot mutate money or raw response: '.$kind);
}
foreach ([new RuntimeException('TV_FUEL_AMOUNT',7101),new InvalidArgumentException('private-amount-canary',7101),
          new InvalidArgumentException('TV_FUEL_AMOUNT',7999)] as $foreignError) {
    tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::amountDiagnostic($foreignError)===null, 'foreign exception cannot forge safe amount classification');
}

$conflict = $base; $conflict['tour']['fuelCharge'] = 30000;
$different = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($conflict);
tvfi_ok($different['monitoring']['comparison']==='different' && $different['monitoring']['tour_amount']==='30000'
    && $different['amount']==='33748', 'different valid stage amounts are monitoring evidence');
$largeDifference = $base; $largeDifference['tour']['fuelCharge']=1;
tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($largeDifference)['monitoring']['comparison']==='different',
    '80 percent objective is not an invented 20 percent per-sample tolerance');
$zeroDetail = $base; $zeroDetail['tour']['fuelCharge']=0;
tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($zeroDetail)['monitoring']['tour_amount']==='0',
    'explicit supplier zero is retained separately from missing');
$decimalDetail = $base; $decimalDetail['tour']['fuelCharge']='33748.00';
tvfi_ok(AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($decimalDetail)['monitoring']['comparison']==='matched',
    'decimal spelling is not a price difference');
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

// UI choice of a concrete alternative does not establish the retained default.
// Contract fixtures only: no private payload is reconstructed from UI text.
$alternative = $base['flights']['flights'][0];
$alternative['isDefault'] = false;
foreach (['tour'=>$base, 'search'=>$searchBase] as $kind=>$input) {
    $reject = $kind === 'tour' ? 'tvfi_reject' : 'tvfi_search_reject';
    $input['flights']['flights'][] = $alternative;

    $placeholder = $input;
    $placeholder['flights']['flights'][0]['forward'] = [];
    $placeholder['flights']['flights'][0]['backward'] = [];
    $reject($placeholder, $kind . ' must not borrow nondefault legs', 'TV_FUEL_FORWARD');

    $unmarked = $input;
    $unmarked['flights']['flights'][0]['isDefault'] = false;
    $reject($unmarked, $kind . ' must not guess a default', 'TV_FUEL_DEFAULT');

    $missingFuel = $input;
    $missingFuel['flights']['flights'][0]['fuelCharge'] = null;
    $reject($missingFuel, $kind . ' must not borrow nondefault fuel', 'TV_FUEL_AMOUNT');
}

$differentFlight = $otherHotel; $differentFlight['flights']['flights'][0]['forward'][0]['number']='ZF9999';
$obs3 = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($differentFlight);
tvfi_ok($obs3['scope'] !== $obs['scope'], 'different flight must form different scope');


 // Supplier dates use the same two exact calendar formats as the search adapter.
 // The raw response and its source hashes are retained, never rewritten.
$dotted = $base;
$dotted['tour']['date'] = '11.10.2026';
$dotted['flights']['flights'][0]['dateForward'] = '11.10.2026';
$dotted['flights']['flights'][0]['dateBackward'] = '18.10.2026';
foreach (['forward'=>'11.10.2026','backward'=>'18.10.2026'] as $leg=>$day) {
    foreach (['departure','arrival'] as $point) $dotted['flights']['flights'][0][$leg][0][$point]['date'] = $day;
}
$dotted['tour_response_sha256'] = AnyTourOperatorFuelRuleEvidenceV1::hash($dotted['tour']);
$dotted['flights_response_sha256'] = AnyTourOperatorFuelRuleEvidenceV1::hash($dotted['flights']);
$beforeDotted = $dotted;
$dottedObs = AnyTourTourvisorOperatorFuelRetainedIntakeV1::observation($dotted);
tvfi_ok($dotted === $beforeDotted, 'raw dotted supplier responses remain byte-equivalent inputs');
tvfi_ok($dottedObs['scope'] === $obs['scope'] && $dottedObs['amount'] === $obs['amount']
    && $dottedObs['base_relation'] === $obs['base_relation'], 'date notation changes neither flight scope nor money');
tvfi_ok($dottedObs['source_response_sha256'] === AnyTourOperatorFuelRuleEvidenceV1::hash([
    'tour'=>$dotted['tour_response_sha256'],'flights'=>$dotted['flights_response_sha256'],
]), 'date normalization retains original response provenance');

foreach ([null, [], 20261011, '', '31.02.2026', '2026-02-30', '1.10.2026',
    '11/10/2026', ' 11.10.2026', "11.10.2026\n", '2026-10-11T00:00:00Z'] as $invalid) {
    foreach (['tour','pair','segment'] as $field) {
        $badDate = $dotted;
        if ($field === 'tour') $badDate['tour']['date'] = $invalid;
        if ($field === 'pair') $badDate['flights']['flights'][0]['dateForward'] = $invalid;
        if ($field === 'segment') $badDate['flights']['flights'][0]['forward'][0]['departure']['date'] = $invalid;
        tvfi_reject($badDate, 'invalid raw calendar date rejected at '.$field, 'TV_FUEL_DATE');
    }
}
$wrongDate = $dotted; $wrongDate['tour']['date'] = '12.10.2026';
tvfi_reject($wrongDate, 'different calendar day cannot match after normalization', 'TV_FUEL_DATE');
$reverseDate = $dotted; $reverseDate['flights']['flights'][0]['dateBackward'] = '10.10.2026';
$reverseDate['flights']['flights'][0]['backward'][0]['departure']['date'] = '10.10.2026';
tvfi_reject($reverseDate, 'normalized return before departure is rejected', 'TV_FUEL_DATE');
$badPeriod = $dotted; $badPeriod['valid_from'] = '01.10.2026';
tvfi_reject($badPeriod, 'internal validity dates remain strict ISO', 'TV_FUEL_PERIOD');
$badSearchDate = $searchBase; $badSearchDate['search_row']['date'] = '11.10.2026';
tvfi_search_reject($badSearchDate, 'normalized internal search row remains strict ISO', 'TV_FUEL_DATE');

echo "PASS retained Tourvisor operator fuel intake\n";
