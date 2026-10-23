<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/operator-fuel-rule-evidence.php';
require_once __DIR__ . '/../app/integrations/operator-fuel-rule-store.php';

function fuel_ok(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function fuel_h(string $v): string { return hash('sha256', $v); }

$party = ['adults'=>2,'children'=>0,'child_ages'=>[]];
$scopeA = [
    'market' => 'tourvisor:departure:1',
    'outbound' => ['origin'=>'VKO','destination'=>'AYT','carrier'=>'TK','flight'=>'TK212'],
    'return' => ['origin'=>'AYT','destination'=>'VKO','carrier'=>'TK','flight'=>'TK211'],
    'party' => $party,
];
$scopeB = [
    'market' => 'andromeda:departure:1',
    'outbound' => ['origin'=>'SVO','destination'=>'AYT','carrier'=>'SU','flight'=>'SU2142'],
    'return' => ['origin'=>'AYT','destination'=>'SVO','carrier'=>'SU','flight'=>'SU2143'],
    'party' => $party,
];
$base = [
    'operator'=>'FUN&SUN',
    'unit'=>'party_roundtrip', 'base_relation'=>'excluded', 'amount'=>'180.00', 'currency'=>'EUR',
    'observed_at'=>1000, 'expires_at'=>5000,
    'base_includes_other_required_charges'=>true,
];
$a = $base + [
    'provider'=>'tourvisor','scope'=>$scopeA,'direction'=>['market'=>'departure:1','destination'=>'country:4'],'source'=>'tourvisor_flights_fuel',
    'valid_from'=>'2026-09-01','valid_to'=>'2026-09-30',
    'offer_ref_digest'=>fuel_h('offer1'),'evidence_sha256'=>fuel_h('ev1'),
    'source_response_sha256'=>fuel_h('resp1'),
];
$b = $base + [
    'provider'=>'andromeda','scope'=>$scopeB,'direction'=>['market'=>'departure:1','destination'=>'country:4'],'source'=>'andromeda_claim_service',
    // A different evidence month is deliberately compatible: date is provenance,
    // not a reusable direction discriminator.
    'valid_from'=>'2026-10-01','valid_to'=>'2026-10-31',
    'offer_ref_digest'=>fuel_h('offer2'),'evidence_sha256'=>fuel_h('ev2'),
    'source_response_sha256'=>fuel_h('resp2'),
];

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('Интурист') === 'intourist', 'intourist family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('FUN & SUN') === 'fun_and_sun', 'fun family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('Библио-Глобус') === 'biblio_globus', 'bg family');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::operatorFamily('ANEX') === null, 'anex excluded from generic rule');

$obsA = AnyTourOperatorFuelRuleEvidenceV1::observation($a);
$obsB = AnyTourOperatorFuelRuleEvidenceV1::observation($b);
fuel_ok($obsA['schema_version'] === 2, 'v2 observation');
fuel_ok($obsA['scope']['outbound']['flight'] === 'TK212', 'exact flight retained as provenance');
fuel_ok($obsB['scope']['outbound']['flight'] === 'SU2142', 'different flight retained as provenance');
fuel_ok($obsA['direction'] === $obsB['direction'], 'different providers/flights collapse to one direction');
fuel_ok($obsA['direction'] === [
    'operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'
], 'canonical direction');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::observation($obsA) === $obsA, 'observation idempotent');
fuel_ok(!array_key_exists('exchange',$obsA),'pre-FX observation remains byte-compatible without exchange key');
fuel_ok(!array_key_exists('monitoring',$obsA),'legacy observations gain no synthetic monitoring fields');

$target = [
    'operator'=>'FUN&SUN',
    'search_params'=>['departureId'=>1,'countryId'=>4],
    'party'=>$party,
    'offer_ref_digest'=>fuel_h('target'),
];
$input = AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$b], 2000);
fuel_ok(is_array($input), 'two independent direction observations confirm');
fuel_ok(count($input['observations']) === 2, 'confirmed input retains two provenance rows');
fuel_ok(!array_key_exists('flight_dates', $input), 'target dates removed from reusable rule');
fuel_ok(!array_key_exists('scope', $input), 'exact flight scope removed from reusable rule input');
fuel_ok($input['direction'] === $obsA['direction'], 'direction emitted');

$monitor=$a;
$monitor['monitoring']=['tour_amount'=>'160.00','flight_amount'=>'180.00','currency'=>'EUR'];
$monitorObs=AnyTourOperatorFuelRuleEvidenceV1::observation($monitor);
fuel_ok($monitorObs['monitoring']['comparison']==='different', 'different stage facts retained');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::observation($monitorObs)===$monitorObs, 'monitoring canonical readback is idempotent');
$monitorOther=$monitor; $monitorOther['offer_ref_digest']=fuel_h('monitor-other');
$monitorOther['evidence_sha256']=fuel_h('monitor-other-evidence');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target,[$monitor,$monitorOther],2000)===null,
    'two matching flight values in monitoring cannot promote a pricing rule');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target,[$a,$monitorOther],2000)===null,
    'monitoring cannot supply the second legacy corroboration');
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target,[$a,$b,$monitor],2000)===$input,
    'retaining monitoring facts cannot change an existing legacy pricing input');
foreach (['binding','currency','classification','extra','shape','provider'] as $case) {
    $bad=$monitorObs;
    if ($case==='binding') $bad['monitoring']['flight_amount']='181.00';
    if ($case==='currency') $bad['monitoring']['currency']='RUB';
    if ($case==='classification') $bad['monitoring']['comparison']='matched';
    if ($case==='extra') $bad['monitoring']['private']='must not be retained';
    if ($case==='shape') $bad['monitoring']=null;
    if ($case==='provider') {
        $bad['provider']='andromeda'; $bad['scope']=$scopeB; $bad['source']='andromeda_claim_service';
    }
    try { AnyTourOperatorFuelRuleEvidenceV1::observation($bad); throw new RuntimeException('Missing invalid monitoring rejection: '.$case); }
    catch (InvalidArgumentException $expected) {
        fuel_ok($expected->getMessage()==='OPERATOR_FUEL_MONITORING','invalid monitoring cannot mint comparison facts: '.$case);
    }
}

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a], 2000) === null, 'one sample still not enough corroboration');
$conflict = $b; $conflict['amount'] = '200.00';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$conflict], 2000) === null, 'amount conflict blocks rule');
$wrongFlight = $b; $wrongFlight['scope']['outbound']['flight'] = 'SU999';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongFlight], 2000) !== null, 'different flight no longer splits rule');
$wrongCarrier = $b; $wrongCarrier['scope']['outbound']['carrier'] = 'PC';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongCarrier], 2000) !== null, 'different carrier no longer splits rule');
$wrongDestination = $b;
$wrongDestination['direction']['destination'] = 'country:5';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongDestination], 2000) === null, 'different destination remains different rule');
$wrongMarket = $b; $wrongMarket['direction']['market'] = 'departure:2';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongMarket], 2000) === null, 'different origin market remains different rule');
$wrongParty = $b; $wrongParty['scope']['party'] = ['adults'=>1,'children'=>1,'child_ages'=>[5]];
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$wrongParty], 2000) === null, 'party total not divided/reinterpreted');
$infantTarget = $target; $infantTarget['party']=['adults'=>2,'children'=>1,'child_ages'=>[1]];
$infantA=$a; $infantA['scope']['party']=$infantTarget['party'];
$infantB=$b; $infantB['scope']['party']=$infantTarget['party'];
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($infantTarget, [$infantA,$infantB], 2000) === null, 'infant kept outside generic fuel');
$unknownUnit = $b; $unknownUnit['unit'] = 'route_reported_unknown';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$unknownUnit], 2000) === null, 'unknown unit never promoted');
$unknownRelation = $b; $unknownRelation['base_relation'] = 'unknown';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$unknownRelation], 2000) === null, 'unknown inclusion never promoted');

// Per-person one-way rates are reusable across non-infant party composition.
$ppPartyA=['adults'=>1,'children'=>0,'child_ages'=>[]];
$ppPartyB=['adults'=>2,'children'=>0,'child_ages'=>[]];
$ppTarget=$target;
$ppTarget['party']=['adults'=>2,'children'=>1,'child_ages'=>[5]];
$ppA=$a;
$ppA['provider']='andromeda';$ppA['source']='andromeda_claim_service';
$ppA['unit']='per_person_one_way';$ppA['amount']='85.00';
$ppA['scope']['party']=$ppPartyA;
$ppA['offer_ref_digest']=fuel_h('pp-offer-a');$ppA['evidence_sha256']=fuel_h('pp-ev-a');
$ppA['source_response_sha256']=fuel_h('pp-resp-a');
$ppA['exchange']=[
    'from'=>'EUR','to'=>'RUB','rate'=>'99.26','source'=>'andromeda_claim_money',
    'observed_at'=>1500,'expires_at'=>2600,'evidence_sha256'=>fuel_h('pp-fx-a'),
];
$ppB=$b;
$ppB['provider']='andromeda';$ppB['source']='andromeda_claim_service';
$ppB['unit']='per_person_one_way';$ppB['amount']='85.00';
$ppB['scope']['party']=$ppPartyB;
$ppB['offer_ref_digest']=fuel_h('pp-offer-b');$ppB['evidence_sha256']=fuel_h('pp-ev-b');
$ppB['source_response_sha256']=fuel_h('pp-resp-b');
$ppB['exchange']=[
    'from'=>'EUR','to'=>'RUB','rate'=>'99.49','source'=>'andromeda_claim_money',
    'observed_at'=>1600,'expires_at'=>2700,'evidence_sha256'=>fuel_h('pp-fx-b'),
];
$ppInput=AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppTarget,[$ppA,$ppB],2000);
fuel_ok(is_array($ppInput),'per-person independent observations confirm');
fuel_ok(($ppInput['observations'][0]['unit']??null)==='per_person_one_way','per-person unit retained');
fuel_ok(($ppInput['party']??null)===$ppTarget['party'],'target party retained for later arithmetic');
fuel_ok(($ppInput['observations'][0]['party']??null)!==$ppTarget['party'],'source party remains provenance, not applicability key');
fuel_ok(($ppInput['exchange']['rate']??null)==='99.49','freshest supplier FX selected');
fuel_ok(($ppInput['exchange']['scope_sha256']??null)===AnyTourOperatorFuelRuleEvidenceV1::directionDigest($ppInput['direction']),'FX scoped to canonical direction');
fuel_ok(($ppInput['exchange']['from']??null)==='EUR'&&($ppInput['exchange']['to']??null)==='RUB','typed native RUB exchange');
$ppStaleA=$ppA;$ppStaleB=$ppB;
$ppStaleA['exchange']['expires_at']=$ppStaleB['exchange']['expires_at']=1999;
$ppStale=AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppTarget,[$ppStaleA,$ppStaleB],2000);
fuel_ok(is_array($ppStale)&&$ppStale['exchange']===null,'stale FX holds conversion without discarding fuel evidence');
$ppTieA=$ppA;$ppTieB=$ppB;
$ppTieA['exchange']['observed_at']=$ppTieB['exchange']['observed_at']=1600;
$ppTieA['exchange']['expires_at']=$ppTieB['exchange']['expires_at']=2700;
$ppTie=AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppTarget,[$ppTieA,$ppTieB],2000);
fuel_ok(is_array($ppTie)&&$ppTie['exchange']===null,'same-time conflicting FX is ambiguous');
$ppConflict=$ppB;$ppConflict['amount']='90.00';
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppTarget,[$ppA,$ppConflict],2000)===null,'per-person rate conflict blocks rule');
$ppInfant=$ppTarget;$ppInfant['party']=['adults'=>2,'children'=>1,'child_ages'=>[1]];
fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($ppInfant,[$ppA,$ppB],2000)===null,'per-person infant target stays separate');

$root = sys_get_temp_dir() . '/operator-fuel-v2-' . bin2hex(random_bytes(5));
$dir = $root . '/searches';
mkdir($dir, 0700, true);
$write = static function(string $path, array $value): bool {
    return file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), LOCK_EX) !== false;
};
$r1 = AnyTourOperatorFuelRuleStoreV1::append($dir, $a, $write);
$r2 = AnyTourOperatorFuelRuleStoreV1::append($dir, $b, $write);
$r3 = AnyTourOperatorFuelRuleStoreV1::append($dir, $b, $write);
fuel_ok($r1['status'] === 'created' && $r2['status'] === 'appended' && $r3['status'] === 'unchanged', 'direction store idempotency');
fuel_ok($r1['path'] === $r2['path'], 'different provider/flight stored under same direction key');
fuel_ok(str_contains(basename($r1['path']), 'operator-fuel-rule-v2-'), 'v2 namespace isolates old flight-key files');
$stored = AnyTourOperatorFuelRuleStoreV1::inputForTarget($dir, $target, 2000);
fuel_ok(is_array($stored) && count($stored['observations']) === 2, 'store returns confirmed direction input');
$envelope = AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($dir, $target, 2000);
fuel_ok(($envelope['state'] ?? null) === 'operator_fuel' && is_array($envelope['operator_fuel'] ?? null), 'autosave pricing envelope');

$otherDirection = $b;
$otherDirection['direction']['market']='departure:2';
$otherDirection['offer_ref_digest']=fuel_h('offer-other');
$otherDirection['evidence_sha256']=fuel_h('ev-other');
AnyTourOperatorFuelRuleStoreV1::append($dir,$otherDirection,$write);
fuel_ok(count(glob($dir.'/operator-fuel-rule-v2-*.json') ?: []) === 2, 'different origin produces second file');

// The observer sees only the already validated retained window, on success only.
$observedRows = null;
$observed = AnyTourOperatorFuelRuleStoreV1::append($dir, $b,
    static function(): bool { throw new RuntimeException('idempotent append must not write'); },
    static function(array $rows) use (&$observedRows): void { $observedRows = $rows; });
$expectedRows=[$obsA,$obsB];
usort($expectedRows,static fn(array $x,array $y): int=>[$x['observed_at'],$x['evidence_sha256']]<=>[$y['observed_at'],$y['evidence_sha256']]);
fuel_ok($observed === $r3 && $observedRows === $expectedRows, 'unchanged observer preserves receipt and window');
$observerRow=$a; $observerRow['evidence_sha256']=fuel_h('observer-success');
$observerResult=AnyTourOperatorFuelRuleStoreV1::append($dir,$observerRow,$write,
    static function(): void { throw new RuntimeException('observer unavailable'); });
fuel_ok($observerResult['written']===true && $observerResult['observationCount']===3,
    'observer failure cannot undo successful persistence');
$observerRow['evidence_sha256']=fuel_h('observer-failed-write'); $observerCalls=0;
try {
    AnyTourOperatorFuelRuleStoreV1::append($dir,$observerRow,static fn(): bool=>false,
        static function() use (&$observerCalls): void { $observerCalls++; });
    throw new RuntimeException('missing write failure');
} catch (RuntimeException $error) {
    fuel_ok($error->getMessage()==='OPERATOR_FUEL_STORE_WRITE' && $observerCalls===0,
        'failed persistence does not expose a summary');
}

$summaryRows=[];
$summaryRow=static function(int $n, ?string $tour, string $flight, string $currency='EUR',
    string $unit='party_roundtrip') use ($a): array {
    $row=$a; $row['evidence_sha256']=fuel_h('summary-'.$n);
    $row['amount']=$flight; $row['currency']=$currency; $row['unit']=$unit;
    $row['monitoring']=['tour_amount'=>$tour,'flight_amount'=>$flight,'currency'=>$currency];
    return AnyTourOperatorFuelRuleEvidenceV1::observation($row);
};
$summaryRows[]=$summaryRow(1,'0.00','0');
$summaryRows[]=$summaryRow(2,'160.01','180.02');
$summaryRows[]=$summaryRow(3,null,'180');
$summaryRows[]=$summaryRow(4,'10','12','RUB');
$summaryRows[]=$summaryRow(5,'5','6','EUR','route_reported_unknown');
$expired=$summaryRow(6,'1','1'); $expired['expires_at']=2000; $summaryRows[]=$expired;
$summaryRows[]=$obsB; // legacy Andromeda row must not enter TV comparison denominator.
$encoded=AnyTourOperatorFuelRuleStoreV1::monitoringHeader($summaryRows,2000);
fuel_ok(is_string($encoded) && strlen($encoded)<=2048 && preg_match('/\A[A-Za-z0-9_-]+\z/D',$encoded)===1,
    'bounded base64url summary');
$summary=json_decode(base64_decode(strtr($encoded,'-_','+/'),true),true,16,JSON_THROW_ON_ERROR);
$summaryKeys=array_keys($summary); sort($summaryKeys);
$allowed=['schema_version','source','operator_family','as_of','window_limit','observed_from','observed_to',
    'retained_count','fresh_count','expired_count','legacy_count','buckets',
    'complete_population','accuracy_evaluated','final_price_verified']; sort($allowed);
fuel_ok($summaryKeys===$allowed && $summary['retained_count']===7 && $summary['fresh_count']===5
    && $summary['expired_count']===1 && $summary['legacy_count']===1 && $summary['as_of']===2000
    && $summary['observed_from']===1000 && $summary['observed_to']===1000, 'exact allowlist and separate freshness counts');
fuel_ok($summary['buckets'][0]===['currency'=>'EUR','unit'=>'party_roundtrip','sample_count'=>3,
    'paired_count'=>2,'matched_count'=>1,'different_count'=>1,'unavailable_count'=>1,'zero_reference_count'=>1,
    'absolute_difference_minor_sum'=>2001], 'decimal arithmetic exact; unavailable and zero reference separate');
fuel_ok(count($summary['buckets'])===3 && $summary['buckets'][1]['unit']==='route_reported_unknown'
    && $summary['buckets'][1]['absolute_difference_minor_sum']===100
    && $summary['buckets'][2]['currency']==='RUB' && $summary['buckets'][2]['absolute_difference_minor_sum']===200,
    'currency and unit buckets never mix');
fuel_ok($summary['complete_population']===false && $summary['accuracy_evaluated']===false
    && $summary['final_price_verified']===false, 'summary never mints accuracy or price authority');
foreach (['offer_ref_digest','evidence_sha256','operator_raw','scope','direction','tour_amount','flight_amount'] as $privateKey) {
    fuel_ok(!str_contains(json_encode($summary),$privateKey), 'no private observation field: '.$privateKey);
}
foreach (['empty','future','mixed_direction','duplicate','invalid','over_cap','oversize'] as $case) {
    $rows=$summaryRows;
    if ($case==='empty') $rows=[];
    if ($case==='future') $rows[0]['observed_at']=2001;
    if ($case==='mixed_direction') $rows[0]['direction']['market']='departure:2';
    if ($case==='duplicate') $rows[]=$rows[0];
    if ($case==='invalid') $rows[0]['amount']='999999999999999999999.00';
    if ($case==='over_cap') $rows=array_fill(0,129,$rows[0]);
    if ($case==='oversize') {
        $rows=[];
        foreach (['AAA','BBB','CCC','DDD','EEE','FFF','GGG','HHH'] as $n=>$currency) {
            $rows[]=$summaryRow(20+$n,'1','2',$currency);
        }
    }
    fuel_ok(AnyTourOperatorFuelRuleStoreV1::monitoringHeader($rows,2000)===null,
        'unsafe or oversized summary absent: '.$case);
}
// Exercise the real store cap; observer must not receive the discarded 129th row.
$capDir=$root.'/cap/searches'; mkdir($capDir,0700,true); $capRows=[];
for ($i=0;$i<129;$i++) {
    $row=$summaryRow(100+$i,'1','2'); $row['observed_at']=1000+$i;
    AnyTourOperatorFuelRuleStoreV1::append($capDir,$row,$write,
        static function(array $rows) use (&$capRows): void { $capRows=$rows; });
}
$cap=json_decode(base64_decode(strtr(AnyTourOperatorFuelRuleStoreV1::monitoringHeader($capRows,2000),'-_','+/'),true),true,16,JSON_THROW_ON_ERROR);
fuel_ok(count($capRows)===128 && $cap['window_limit']===128 && $cap['retained_count']===128
    && $cap['observed_from']===1001 && $cap['observed_to']===1128 && $cap['buckets'][0]['sample_count']===128,
    'only current bounded window is summarized');
if (PHP_INT_SIZE>=8) {
    $maximumRows=[];
    for ($i=0;$i<128;$i++) $maximumRows[]=$summaryRow(300+$i,'0','999999999999.99');
    $maximum=json_decode(base64_decode(strtr(AnyTourOperatorFuelRuleStoreV1::monitoringHeader($maximumRows,2000),'-_','+/'),true),true,16,JSON_THROW_ON_ERROR);
    fuel_ok($maximum['buckets'][0]['absolute_difference_minor_sum']===12799999999999872,
        'maximum valid bounded amounts remain exact integers without float overflow');
}
array_map('unlink',glob($capDir.'/*')?:[]); rmdir($capDir); rmdir(dirname($capDir));

fuel_ok(AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, [$a,$b], 6000) === null, 'expired evidence not reused');
array_map('unlink', glob($dir.'/*') ?: []); rmdir($dir); rmdir($root);
echo "PASS operator fuel direction evidence/store\n";
