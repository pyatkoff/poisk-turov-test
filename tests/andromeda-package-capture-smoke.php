<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/andromeda-package-capture.php';
$n = 0;
function ok(bool $condition): void { global $n; ++$n; if (!$condition) throw new RuntimeException('CHECK_' . $n); }
function denied(callable $f, string $error): void {
    try { $f(); } catch (RuntimeException|InvalidArgumentException $e) {
        ok($e->getMessage() === $error && $e->getPrevious() === null); return;
    }
    throw new RuntimeException('EXPECTED_' . $error);
}
function fixtureStore(array &$state): array {
    $criteria = ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260918','CHECKIN_END'=>'20260918',
        'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>8,'NIGHTS_TILL'=>8,'CURRENCYINC'=>643,'PAGE'=>2,'HOTELS'=>'3414'];
    $row = ['id'=>'0001|private-fixture&action=bron','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
        'price'=>123456,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'18.09.2026','nights'=>'8',
        'hotel'=>'Fixture hotel','operator'=>'Fixture operator','meal'=>'AI','mealKey'=>6,
        'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0'];
    $resolver = AnyTourAndromedaHotelResolver::fromRows([['supplier_namespace'=>'andromeda_catalog',
        'external_hotel_id'=>'3414','decision_status'=>'accepted','catalog_hotel_id'=>'900',
        'existing_catalog_hotel_id'=>'900']],str_repeat('b',64));
    $store = new AnyTourAndromedaOfferStore($state, true);
    $store->begin('saved_search', 1, 1000);
    $page = $store->capture(['PAGE'=>2,'PAGES_COUNT'=>3,'PRICES'=>[$row]], $criteria, 'saved_search', 1, 1001, $resolver);
    return [$store, ['provider'=>'andromeda','search_ref'=>'saved_search','generation'=>1,'page'=>2,
        'offer_ref'=>$page['offers'][0]['offer_ref'],'hotel_scope'=>'3414','operator_ref'=>'5']];
}
function client(callable $transport): AnyTourAndromedaClient {
    $client = new AnyTourAndromedaClient($transport, true, true);
    $client->restorePrivateSession(['sid'=>'capture-fixture-session','expires'=>time()+1800]);
    return $client;
}
function persistence(array &$disk): callable {
    return static function (array $next, array $expected) use (&$disk): array {
        ok($disk === $expected); // Compare/write/readback under the caller's existing lock.
        $disk = json_decode(json_encode($next, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
        return $disk;
    };
}
$raw = ['version'=>'1.01','claimDocument'=>[['catalogKey'=>'supplier-package-key','freightExternal'=>1,
    'buyerMoneys'=>[['buyerClaimMoney'=>[['net'=>'123456.70','currency'=>'RUB']]]],
    'moneys'=>[['money'=>[['net'=>'111000.00','currency'=>'RUB']]]]]]];
$state = []; [$store, $context] = fixtureStore($state);
$record = []; $disk = []; $calls = 0; $now = 1002; $mapping = true;
$persist = persistence($disk);
$clock = static function () use (&$now): int { return $now; };
$currentMapping = static function ($offer) use (&$mapping): bool {
    ok($offer['supplier_namespace'] === 'andromeda_catalog' && $offer['external_hotel_id'] === '3414');
    return $mapping;
};
$client = client(static function ($url) use (&$calls, &$disk, $raw): array {
    ++$calls;
    ok($disk['status'] === 'reserved');
    ok(strpos(json_encode($disk), 'private-fixture') === false);
    parse_str(parse_url($url, PHP_URL_QUERY), $params);
    ok($params['action'] === 'broninit' && $params['claiminc'] === '0001|private-fixture&action=bron');
    return ['status'=>200,'body'=>json_encode($raw)];
});
$off = new AnyTourAndromedaPackageCapture($record, $persist, $currentMapping);
denied(fn() => $off->capture($store, $client, $context), 'ANDROMEDA_PACKAGE_DISABLED');
ok($record === [] && $disk === [] && $calls === 0);
$capture = new AnyTourAndromedaPackageCapture($record, $persist, $currentMapping, true, $clock);
foreach (['provider'=>'tourvisor','page'=>1,'hotel_scope'=>'another-hotel','operator_ref'=>'another-operator','claiminc'=>'injected'] as $field=>$value) {
    $bad = $context; $bad[$field] = $value;
    denied(fn() => $capture->capture($store, $client, $bad), 'ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
}
ok($record === [] && $disk === [] && $calls === 0);
$mapping = false;
denied(fn() => $capture->capture($store, $client, $context), 'ANDROMEDA_PACKAGE_MAPPING_UNAVAILABLE');
ok($record === [] && $disk === [] && $calls === 0);
$mapping = true;
$result = $capture->capture($store, $client, $context);
ok($result['status'] === 'captured' && $result['private_package'] === $raw);
ok($result['context']['local_id'] === 900 && $result['context']['operator_ref'] === '5' && $result['context']['page'] === 2);
ok($result['identity_verified'] === false && $result['quote_verified'] === false && $result['selection_enabled'] === false);
ok($calls === 1);
denied(fn() => $capture->capture($store, $client, $context), 'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
// New server request restores the durable record; read is local, capture still refuses replay.
$restored = json_decode(json_encode($disk), true);
$again = new AnyTourAndromedaPackageCapture($restored, $persist, $currentMapping, true, $clock);
ok($again->read($store, $context) === $result);
denied(fn() => $again->capture($store, $client, $context), 'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
ok($calls === 1);
$restored['private_package']['claimDocument'][0]['catalogKey'] = 'tampered';
denied(fn() => $again->read($store, $context), 'ANDROMEDA_PACKAGE_NOT_CAPTURED');
$restored = $disk; $mapping = false;
denied(fn() => $again->read($store, $context), 'ANDROMEDA_PACKAGE_MAPPING_UNAVAILABLE');
$mapping = true; $now = 1900;
denied(fn() => $again->read($store, $context), 'EXPIRED_SEARCH');
$now = 1003;
ob_start(); var_dump($again); $debug = ob_get_clean();
ok(strpos($debug, 'supplier-package-key') === false && strpos($debug, '123456.70') === false);
denied(fn() => serialize($again), 'ANDROMEDA_SERIALIZATION_DISABLED');

foreach (['reserve_write', 'reserve_readback', 'transport', 'http', 'result_write', 'late', 'new_generation', 'mapping_revoked'] as $scenario) {
    $state = []; [$store, $context] = fixtureStore($state);
    $record = []; $disk = []; $calls = 0; $now = 1002; $mapping = true;
    $write = persistence($disk);
    $save = static function ($next, $expected) use ($scenario, $write) {
        if (($scenario === 'reserve_write' && $next['status'] === 'reserved')
            || ($scenario === 'result_write' && $next['status'] === 'captured')) throw new RuntimeException('private path');
        if ($scenario === 'reserve_readback') return [];
        return $write($next, $expected);
    };
    $probe = client(static function () use (&$calls, &$now, &$mapping, $store, $scenario, $raw, &$disk) {
        ++$calls; ok($disk['status'] === 'reserved');
        if ($scenario === 'transport') throw new RuntimeException('private transport URL');
        if ($scenario === 'http') return ['status'=>429,'body'=>'private supplier diagnostic'];
        if ($scenario === 'late') $now = 1900;
        if ($scenario === 'new_generation') $store->begin('new_search', 2, 1003);
        if ($scenario === 'mapping_revoked') $mapping = false;
        return ['status'=>200,'body'=>json_encode($raw)];
    });
    $capture = new AnyTourAndromedaPackageCapture($record, $save, $currentMapping, true, $clock);
    $error = in_array($scenario, ['reserve_write','reserve_readback','result_write'], true) ? 'ANDROMEDA_PACKAGE_CHECKPOINT_FAILED'
        : (in_array($scenario, ['late','new_generation','mapping_revoked'], true) ? 'ANDROMEDA_PACKAGE_CONTEXT_STALE' : 'ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN');
    denied(fn() => $capture->capture($store, $probe, $context), $error);
    ok($calls === (in_array($scenario, ['reserve_write','reserve_readback'], true) ? 0 : 1));
    denied(fn() => $capture->capture($store, $probe, $context), 'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
    denied(fn() => $capture->read($store, $context), 'ANDROMEDA_PACKAGE_NOT_CAPTURED');
    if ($scenario === 'result_write') ok($record['status'] === 'reserved' && $disk['status'] === 'reserved');
    if (in_array($scenario, ['transport','http'], true)) ok($disk['status'] === 'unknown');
    if (in_array($scenario, ['late','new_generation','mapping_revoked'], true)) ok($disk['status'] === 'stale' && $disk['private_package'] === $raw);
    if ($disk !== []) {
        $restored = json_decode(json_encode($disk), true);
        $restart = new AnyTourAndromedaPackageCapture($restored, $save, $currentMapping, true, $clock);
        denied(fn() => $restart->capture($store, $probe, $context), 'ANDROMEDA_PACKAGE_REPLAY_REFUSED');
    }
}
echo "Andromeda package capture: $n checks passed\n";
