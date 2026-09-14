<?php
declare(strict_types=1);
require $argv[1] . '/v2/api-andromeda-search3-preview.php';
require $argv[1] . '/app/integrations/andromeda-saved-package-runtime.php';

function surcharge_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('surcharge_fixture_failed: ' . $label);
}
$root = $argv[2];
$ref = str_repeat('a', 64); $source = str_repeat('b', 40); $created = time(); $now = $created + 2;
$criteria = ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>643,'PAGE'=>1];
$row = ['id'=>'private-selected-offer','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>83080,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'22.09.2026','nights'=>'7',
    'hotel'=>'Fixture hotel','operator'=>'Fixture operator','meal'=>'RO','mealKey'=>1,
    'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$resolver = AnyTourAndromedaHotelResolver::fromRows([['supplier_namespace'=>'andromeda_catalog',
    'external_hotel_id'=>'3414','decision_status'=>'accepted','catalog_hotel_id'=>'900',
    'existing_catalog_hotel_id'=>'900']], str_repeat('c',64));
$state = []; $store = new AnyTourAndromedaOfferStore($state, true);
$store->begin($ref, 1, $created);
$page = $store->capture(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]], $criteria, $ref, 1, $now, $resolver);
$context = ['provider'=>'andromeda','search_ref'=>$ref,'generation'=>1,'page'=>1,'offer_ref'=>$page['offers'][0]['offer_ref']];
$clock = static function() use (&$now): int { return $now; };
$raw = ['version'=>'1.01','claimDocument'=>[['catalogKey'=>'private-package-key']]];
foreach (['estimated', 'zero', 'ambiguous', 'unknown', 'stale'] as $case) {
    $directory = $root . '/' . $case . '/searches'; mkdir($directory, 0700, true);
    anytour_andromeda_search3_save($directory . '/' . $ref . '-1.json', ['status'=>'complete','store'=>$state]);
    anytour_andromeda_search3_save($directory . '/' . $ref . '-auth.json', [
        'created_at'=>$created,'session'=>['sid'=>'private-fixture-session','expires'=>time()+1800]]);
    $path = $directory . '/' . $ref . '-' . $created . '-1-' . $context['offer_ref'] . '-surcharge-v1.json';
    $bootstrapCalls = 0; $flightCalls = 0; $allowed = true;
    $allows = static function(array $offer) use (&$allowed): bool { return $allowed && $offer['local_hotel_id'] === 900; };
    $bootstrap = static function($url) use (&$bootstrapCalls, $raw): array {
        ++$bootstrapCalls; parse_str(parse_url($url, PHP_URL_QUERY), $query);
        surcharge_check($query['action'] === 'broninit', 'only bootstrap action');
        return ['status'=>200,'body'=>json_encode($raw)];
    };
    $flightRequest = static function(string $url, string $post) use (&$flightCalls, &$allowed, $path, $raw, $case): array {
        ++$flightCalls;
        surcharge_check(json_decode(file_get_contents($path), true)['status'] === 'reserved', 'reservation precedes flight request');
        parse_str(parse_url($url, PHP_URL_QUERY), $query); parse_str($post, $params);
        surcharge_check($query['action'] === 'get_flights' && json_decode($params['claim'], true) === $raw, 'existing POST claim contract');
        if ($case === 'unknown') throw new RuntimeException('private-transport-outcome');
        if ($case === 'stale') $allowed = false;
        $markup = $case === 'zero' ? '0' : '100';
        $reply = $raw;
        $reply['claimDocument'][0]['moneys'] = [['money'=>[
            ['currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true'],
            ['currency'=>'RUB','rate'=>'100','isClaimCurrency'=>'false']]]];
        $reply['variants'] = [['transports'=>[['transport'=>[
            ['type'=>'ttAvia','details'=>[['detail'=>[['markup'=>$markup,'currency'=>'USD']]]]],
            ['type'=>'ttAvia','details'=>[['detail'=>[['markup'=>$case === 'ambiguous' ? '200' : $markup,'currency'=>'USD']]]]],
        ]]]]];
        return ['status'=>200,'body'=>json_encode($reply)];
    };
    $run = static fn() => anytour_andromeda_capture_saved_package(
        $directory, $context, $source, $allows, $bootstrap, true, $clock, true, $flightRequest);
    $initial = anytour_andromeda_capture_saved_package($directory, $context, $source, $allows, $bootstrap, true, $clock);
    surcharge_check(!isset($initial['surcharge']) && $bootstrapCalls === 1 && $flightCalls === 0 && !file_exists($path), 'existing capture remains default-off');
    $before = file_get_contents($directory . '/' . $ref . '-1.json');
    $receipt = $run(); $allowed = true;
    surcharge_check($bootstrapCalls === 1 && $flightCalls === 1, 'one reused package, one flight request');
    surcharge_check($run()['surcharge']['reused'] === true && $flightCalls === 1, 'all outcomes no-replay');
    surcharge_check(file_get_contents($directory . '/' . $ref . '-1.json') === $before, 'base offer snapshot unchanged');
    surcharge_check((fileperms($path) & 0777) === 0600, 'private sidecar permissions');
    $budget = json_decode(file_get_contents(dirname($directory) . '/monthly-requests.json'), true);
    surcharge_check($budget['reserved_requests'] === 2, 'same monthly budget counts both requests');
    $fact = anytour_andromeda_read_saved_surcharge($directory, $state, $created, $context, $allows, $now);
    $disk = json_decode(file_get_contents($path), true);
    surcharge_check(!str_contains(json_encode([$receipt, $fact, $disk]), 'private-'), 'sidecar and public result contain no raw claim or secrets');
    if (in_array($case, ['estimated', 'zero'], true)) {
        surcharge_check($fact !== null && $fact['state'] === 'estimated' && $fact['final_price_verified'] === false, 'estimate not final quote');
        surcharge_check($fact['search_price_with_surcharge']['amount'] === ($case === 'zero' ? '83080.00' : '93080.00'), 'party markup added once, not per leg');
        surcharge_check($fact['party_surcharge']['amount'] === ($case === 'zero' ? '0.00' : '10000.00'), 'operator conversion preserved');
        surcharge_check(anytour_andromeda_read_saved_surcharge($directory, $state, $created, $context, $allows, $now + 300) === null, 'local TTL expires');
        surcharge_check(anytour_andromeda_read_saved_surcharge($directory, $state, $created, $context, static fn()=>false, $now) === null, 'mapping invalidates cached money');
        $other = $state; $other['criteria']['ADULT'] = 3;
        surcharge_check(anytour_andromeda_read_saved_surcharge($directory, $other, $created, $context, $allows, $now) === null, 'different party criteria rejected');
        $disk['fact']['private_debug'] = 'private-secret';
        anytour_andromeda_search3_save($path, $disk);
        surcharge_check(!str_contains(json_encode(anytour_andromeda_read_saved_surcharge($directory, $state, $created, $context, $allows, $now)), 'private-'), 'explicit public whitelist');
        $disk['fact']['search_price_with_surcharge']['amount'] = '99999.00';
        anytour_andromeda_search3_save($path, $disk);
        surcharge_check(anytour_andromeda_read_saved_surcharge($directory, $state, $created, $context, $allows, $now) === null, 'inconsistent total rejected');
        surcharge_check($run()['surcharge']['fact'] === null && $flightCalls === 1, 'bad cache never authorizes retry');
    } else {
        surcharge_check($fact === null, 'unknown is not zero');
        surcharge_check($disk['status'] === ['ambiguous'=>'complete','unknown'=>'unknown','stale'=>'stale'][$case], 'durable terminal status');
    }
}
echo "Saved surcharge: one opt-in get_flights, same budget/lock, safe persisted estimate, local read, expiry/mapping/party invalidation, all outcomes no-replay passed offline.\n";
