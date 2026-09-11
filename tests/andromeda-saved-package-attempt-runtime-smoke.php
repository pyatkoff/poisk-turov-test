<?php
declare(strict_types=1);

require $argv[1] . '/v2/api-andromeda-search3-preview.php';
require $argv[1] . '/app/integrations/andromeda-saved-package-runtime.php';
require_once $argv[1] . '/app/integrations/andromeda-selected-offer.php';

$checks = 0;
function attempt_runtime_check(bool $ok): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('attempt_runtime_check_' . $checks);
}
function attempt_runtime_refuse(callable $call, ?string $message = null): void {
    try { $call(); }
    catch (RuntimeException $error) {
        if ($message !== null) attempt_runtime_check($error->getMessage() === $message);
        return;
    }
    throw new RuntimeException('attempt_runtime_expected_refusal');
}

$root = $argv[2];
$ref = str_repeat('d', 64);
$source = str_repeat('e', 40);
$now = 1002;
$criteria = [
    'TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>643,'PAGE'=>1,
];
$row = [
    'id'=>'private-selected-claiminc','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>83080,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'22.09.2026','nights'=>'7',
    'hotel'=>'Attempt Hotel','operator'=>'Attempt Operator','meal'=>'AI','mealKey'=>7,
    'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0',
];
$resolver = AnyTourAndromedaHotelResolver::fromRows([[
    'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'3414',
    'decision_status'=>'accepted','catalog_hotel_id'=>'900','existing_catalog_hotel_id'=>'900',
]], str_repeat('f', 64));
$state = [];
$store = new AnyTourAndromedaOfferStore($state, true);
$store->begin($ref, 1, 1000);
$page = $store->capture(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]], $criteria, $ref, 1, 1001, $resolver);
$context = [
    'provider'=>'andromeda','search_ref'=>$ref,'generation'=>1,'page'=>1,
    'offer_ref'=>$page['offers'][0]['offer_ref'],
];
$clock = static function() use (&$now): int { return $now; };
$allows = static fn(array $offer): bool => ($offer['local_hotel_id'] ?? null) === 900;
$resolved = AnyTourAndromedaSelectedOffer::resolve($store, $context, $allows, $now);
$expectedClaim = hash('sha256', 'private-selected-claiminc');
$expectedContext = hash('sha256', json_encode($resolved['context'], JSON_THROW_ON_ERROR));
$expectedOperation = hash('sha256', 'andromeda-package-refresh-v2|' . $source);
$raw = ['version'=>'1.01','claimDocument'=>[['catalogKey'=>'reduced-package-key']]];

foreach (['success','typed-network','generic'] as $case) {
    $directory = $root . '/' . $case . '/searches';
    mkdir($directory, 0700, true);
    anytour_andromeda_search3_save($directory . '/' . $ref . '-1.json', ['status'=>'complete','store'=>$state]);
    anytour_andromeda_search3_save($directory . '/' . $ref . '-auth.json', [
        'created_at'=>1000,'session'=>['sid'=>'attempt-fixture-session','expires'=>time()+1800],
    ]);
    $stem = $directory . '/' . $ref . '-1000-1-' . $context['offer_ref'];
    $packagePath = $stem . '-package.json';
    $attemptPath = $stem . '-package-attempt-v2.json';
    $calls = 0;
    $transport = static function($url) use (&$calls, $case, $raw): array {
        ++$calls;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        attempt_runtime_check(($query['action'] ?? null) === 'broninit');
        attempt_runtime_check(($query['claiminc'] ?? null) === 'private-selected-claiminc');
        if ($case === 'typed-network') {
            throw new AnyTourAndromedaNetworkTransportFailure('private-network-detail');
        }
        if ($case === 'generic') throw new RuntimeException('private-generic-detail');
        return ['status'=>200,'body'=>json_encode($raw, JSON_THROW_ON_ERROR)];
    };
    $run = static fn() => anytour_andromeda_capture_saved_package(
        $directory, $context, $source, $allows, $transport, true, $clock);

    if ($case === 'success') {
        $receipt = $run();
        attempt_runtime_check($receipt['status'] === 'captured' && $receipt['reused'] === false);
        attempt_runtime_check($calls === 1);
    } else {
        attempt_runtime_refuse($run, 'ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN');
        attempt_runtime_check($calls === 1);
    }

    $attemptEnvelope = json_decode(file_get_contents($attemptPath), true, 32, JSON_THROW_ON_ERROR);
    $attempt = $attemptEnvelope['state'];
    attempt_runtime_check($attemptEnvelope['source'] === $source);
    attempt_runtime_check($attempt['version'] === 2 && $attempt['attempt'] === 1);
    attempt_runtime_check($attempt['claiminc_sha256'] === $expectedClaim);
    attempt_runtime_check($attempt['context_sha256'] === $expectedContext);
    attempt_runtime_check($attempt['operation_sha256'] === $expectedOperation);
    $attemptJson = json_encode($attemptEnvelope, JSON_THROW_ON_ERROR);
    attempt_runtime_check(!str_contains($attemptJson, 'private-selected-claiminc'));
    attempt_runtime_check(!str_contains($attemptJson, 'private-network-detail'));
    attempt_runtime_check(!str_contains($attemptJson, 'private-generic-detail'));
    attempt_runtime_check((fileperms($attemptPath) & 0777) === 0600);

    if ($case === 'success') {
        attempt_runtime_check($attempt['status'] === 'completed' && $attempt['failure_class'] === 'none');
        attempt_runtime_check($run()['reused'] === true && $calls === 1);
    } else {
        $expectedStatus = $case === 'typed-network' ? 'unknown_transport' : 'unknown_unclassified';
        $expectedFailure = $case === 'typed-network' ? 'network_transport' : 'unclassified';
        attempt_runtime_check($attempt['status'] === $expectedStatus && $attempt['failure_class'] === $expectedFailure);
        $package = json_decode(file_get_contents($packagePath), true, 32, JSON_THROW_ON_ERROR);
        attempt_runtime_check(($package['record']['status'] ?? null) === 'unknown');
        attempt_runtime_refuse($run);
        attempt_runtime_check($calls === 1);
    }
}

// A valid v2 network sidecar by itself is evidence only; this runtime does not consume it as retry permission.
$directory = $root . '/sidecar-only/searches';
mkdir($directory, 0700, true);
anytour_andromeda_search3_save($directory . '/' . $ref . '-1.json', ['status'=>'complete','store'=>$state]);
anytour_andromeda_search3_save($directory . '/' . $ref . '-auth.json', [
    'created_at'=>1000,'session'=>['sid'=>'attempt-fixture-session','expires'=>time()+1800],
]);
$stem = $directory . '/' . $ref . '-1000-1-' . $context['offer_ref'];
$attemptPath = $stem . '-package-attempt-v2.json';
$reserved = AnyTourAndromedaPackageAttemptState::reserveFirst(
    'private-selected-claiminc', $expectedContext, $expectedOperation);
$network = AnyTourAndromedaPackageAttemptState::failed(
    $reserved, new AnyTourAndromedaNetworkTransportFailure('not-persisted'));
anytour_andromeda_search3_save($attemptPath, ['source'=>$source,'state'=>$network]);
$calls = 0;
$never = static function() use (&$calls): array { ++$calls; return ['status'=>500,'body'=>'']; };
attempt_runtime_refuse(static fn() => anytour_andromeda_capture_saved_package(
    $directory, $context, $source, $allows, $never, true, $clock), 'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
attempt_runtime_check($calls === 0 && !file_exists($stem . '-package.json'));
attempt_runtime_check(!str_contains(file_get_contents($attemptPath), 'private-selected-claiminc'));

echo 'Saved package attempt runtime: ' . $checks . " checks passed; live retry disabled, supplier/SSH/production DB=0.\n";
