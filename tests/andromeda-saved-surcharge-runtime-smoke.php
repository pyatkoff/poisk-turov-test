<?php
declare(strict_types=1);
require $argv[1] . '/v2/api-andromeda-search3-preview.php';
require $argv[1] . '/app/integrations/andromeda-saved-package-runtime.php';

function surcharge_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('surcharge_fixture_failed: ' . $label);
}
$root = $argv[2];
$ref = str_repeat('a', 64); $source = str_repeat('b', 40); $now = time(); $created = $now - 2;
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
    if (in_array($case, ['estimated', 'zero', 'ambiguous'], true)) {
        $diag = $disk['transport_money_diagnostic'] ?? null;
        surcharge_check(is_array($diag) && ($diag['schema_version'] ?? null) === 1, 'private transport diagnostic retained');
        surcharge_check(($diag['ttavia_option_count'] ?? null) === 2 && ($diag['transport_detail_count'] ?? null) === 2
            && ($diag['markup_key_count'] ?? null) === 2, 'diagnostic counts get_flights transport shape');
        surcharge_check(($diag['valid_markup_fact_count'] ?? null) === ($case === 'ambiguous' ? 2 : 1)
            && ($diag['distinct_markup_count'] ?? null) === ($case === 'ambiguous' ? 2 : 1), 'diagnostic distinct markup facts');
        surcharge_check(($diag['detail_currencies'] ?? null) === ['USD']
            && ($diag['markup_currencies'] ?? null) === ['USD']
            && ($diag['operator_rate_currencies'] ?? null) === ['RUB','USD'], 'diagnostic currencies bounded');
    }
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

// Choice-dependent get_flights money is actualized through exactly two
// changeservice calls and supplier calc. The selected markup is never used as the
// final price; calc is the only verified customer price authority.
$actualDir = $root . '/actualized/searches'; mkdir($actualDir, 0700, true);
anytour_andromeda_search3_save($actualDir . '/' . $ref . '-1.json', ['status'=>'complete','store'=>$state]);
anytour_andromeda_search3_save($actualDir . '/' . $ref . '-auth.json', [
    'created_at'=>$created,'session'=>['sid'=>'actual-fixture-session','expires'=>time()+1800]]);
$actualPath = $actualDir . '/' . $ref . '-' . $created . '-1-' . $context['offer_ref'] . '-surcharge-v1.json';
$actualBootstrapCalls = 0; $actualActions = []; $selectedUids = [];
$actualAllows = static fn(array $offer): bool => $offer['local_hotel_id'] === 900;
$actualBootstrap = static function($url) use (&$actualBootstrapCalls, $raw): array {
    ++$actualBootstrapCalls;
    return ['status'=>200,'body'=>json_encode($raw)];
};
$actualRequest = static function(string $url, string $post) use (&$actualActions, &$selectedUids): array {
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
    parse_str($post, $params);
    $action = $query['action'] ?? '';
    $claim = json_decode($params['claim'] ?? '', true);
    surcharge_check(is_array($claim), 'actualizer claim parsed');
    $actualActions[] = $action;
    if ($action === 'get_flights') {
        $claim['claimDocument'][0]['condition'] = 'ccOffer';
        $claim['claimDocument'][0]['buyerMoneys'] = [[ 'buyerClaimMoney' => [[
            'net'=>'83080','currency'=>'RUB'
        ]] ]];
        $claim['claimDocument'][0]['moneys'] = [[ 'money' => [[
            'currency'=>'RUB','rate'=>'1','isClaimCurrency'=>'true','price'=>'83080','net'=>'83080'
        ]] ]];
        $claim['groups'] = [[ 'group' => [
            ['id'=>'g0','required'=>'true','oneItem'=>'true'],
            ['id'=>'g1','required'=>'true','oneItem'=>'true'],
        ] ]];
        $claim['variants'] = [[ 'transports' => [[ 'transport' => [
            ['type'=>'ttAvia','direction'=>'0','groupId'=>'g0','uid'=>'out-expensive','name'=>'OUT EXP',
                'details'=>[[ 'detail'=>[['markup'=>'2000','currency'=>'RUB']] ]]],
            ['type'=>'ttAvia','direction'=>'0','groupId'=>'g0','uid'=>'out-cheap','name'=>'OUT CHEAP',
                'details'=>[[ 'detail'=>[['markup'=>'1000','currency'=>'RUB']] ]]],
            ['type'=>'ttAvia','direction'=>'1','groupId'=>'g1','uid'=>'ret-expensive','name'=>'RET EXP',
                'details'=>[[ 'detail'=>[['markup'=>'1500','currency'=>'RUB']] ]]],
            ['type'=>'ttAvia','direction'=>'1','groupId'=>'g1','uid'=>'ret-cheap','name'=>'RET CHEAP',
                'details'=>[[ 'detail'=>[['markup'=>'500','currency'=>'RUB']] ]]],
        ]]] ]];
    } elseif ($action === 'changeservice') {
        surcharge_check(isset($query['NEW_UID']), 'changeservice has selected uid');
        $selectedUids[] = $query['NEW_UID'];
    } elseif ($action === 'calc') {
        surcharge_check($selectedUids === ['out-cheap','ret-cheap'], 'cheapest options applied before calc');
        $claim['claimDocument'][0]['buyerMoneys'] = [[ 'buyerClaimMoney' => [[
            'net'=>'95000','currency'=>'RUB'
        ]] ]];
        $claim['claimDocument'][0]['moneys'] = [[ 'money' => [[
            'currency'=>'RUB','rate'=>'1','isClaimCurrency'=>'true',
            'price'=>'95000','net'=>'95000','priceForCommiss'=>'95000','sumCommission'=>'0'
        ]] ]];
    } else {
        throw new RuntimeException('unexpected actualizer action: ' . $action);
    }
    return ['status'=>200,'body'=>json_encode($claim)];
};
anytour_andromeda_capture_saved_package(
    $actualDir, $context, $source, $actualAllows, $actualBootstrap, true, $clock
);
$actualReceipt = anytour_andromeda_capture_saved_package(
    $actualDir, $context, $source, $actualAllows, $actualBootstrap, true, $clock, true, $actualRequest
);
surcharge_check($actualBootstrapCalls === 1, 'actualizer reuses captured package');
surcharge_check($actualActions === ['get_flights','changeservice','changeservice','calc'],
    'actualizer exact four-action sequence');
surcharge_check(($actualReceipt['surcharge']['final_price_verified'] ?? null) === true,
    'actualizer receipt verified');
surcharge_check(anytour_andromeda_read_saved_surcharge(
    $actualDir, $state, $created, $context, $actualAllows, $now
) === null, 'verified quote does not masquerade as estimate');
$actualPricing = anytour_andromeda_read_saved_pricing(
    $actualDir, $state, $created, $context, $actualAllows, $now
);
surcharge_check(is_array($actualPricing) && $actualPricing['state'] === 'verified',
    'private verified pricing readable');
surcharge_check(($actualPricing['verified_quote']['final_price'] ?? null)
    === ['amount'=>'95000','currency'=>'RUB'], 'supplier calc final retained');
surcharge_check(($actualPricing['verified_quote']['booking_enabled'] ?? null) === false,
    'actualizer never enables booking');
$actualDisk = json_decode(file_get_contents($actualPath), true);
surcharge_check(($actualDisk['actualization']['state'] ?? null) === 'verified'
    && ($actualDisk['actualization']['actions_used'] ?? null) === 4,
    'actualization terminal evidence retained');
surcharge_check(!str_contains(json_encode($actualDisk), 'actual-fixture-session'),
    'session secret not retained in sidecar');
$actualBudget = json_decode(file_get_contents(dirname($actualDir) . '/monthly-requests.json'), true);
surcharge_check(($actualBudget['reserved_requests'] ?? null) === 5,
    'broninit plus four actualization actions counted');

// Real consumer regression: ordinary Search3 listing reads the already-completed
// sidecar locally, uses base+surcharge only for list display, and leaves the retained
// offer/selected DTO at the original base price for later authoritative actualization.
$listingDir = $root . '/listing/searches'; mkdir($listingDir, 0700, true);
anytour_andromeda_search3_save($listingDir . '/' . $ref . '-1.json', ['status'=>'complete','store'=>$state]);
anytour_andromeda_search3_save($listingDir . '/' . $ref . '-auth.json', [
    'created_at'=>$created,'session'=>['sid'=>'listing-fixture-session','expires'=>time()+1800]]);
$listingPath = $listingDir . '/' . $ref . '-' . $created . '-1-' . $context['offer_ref'] . '-surcharge-v1.json';
$listingBootstrapCalls = 0; $listingFlightCalls = 0;
$listingAllows = static fn(array $offer): bool => $offer['local_hotel_id'] === 900;
$listingBootstrap = static function($url) use (&$listingBootstrapCalls, $raw): array {
    ++$listingBootstrapCalls;
    return ['status'=>200,'body'=>json_encode($raw)];
};
$listingFlight = static function(string $url, string $post) use (&$listingFlightCalls, $raw): array {
    ++$listingFlightCalls;
    $reply = $raw;
    $reply['claimDocument'][0]['moneys'] = [['money'=>[
        ['currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true'],
        ['currency'=>'RUB','rate'=>'100','isClaimCurrency'=>'false']]]];
    $reply['variants'] = [['transports'=>[['transport'=>[
        ['type'=>'ttAvia','details'=>[['detail'=>[['markup'=>'100','currency'=>'USD']]]]],
        ['type'=>'ttAvia','details'=>[['detail'=>[['markup'=>'100','currency'=>'USD']]]]],
    ]]]]];
    return ['status'=>200,'body'=>json_encode($reply)];
};
anytour_andromeda_capture_saved_package($listingDir, $context, $source, $listingAllows, $listingBootstrap, true, $clock);
$listingReceipt = anytour_andromeda_capture_saved_package(
    $listingDir, $context, $source, $listingAllows, $listingBootstrap, true, $clock, true, $listingFlight);
surcharge_check(($listingReceipt['surcharge']['fact']['search_price_with_surcharge']['amount'] ?? null) === '93080.00', 'listing fixture has retained estimate');
surcharge_check($listingBootstrapCalls === 1 && $listingFlightCalls === 1, 'listing producer calls bounded before projection');

$pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE catalog_hotels(id INTEGER,name TEXT,country_id INTEGER,country_name TEXT,region_id INTEGER,region_name TEXT,subregion_id INTEGER,subregion_name TEXT,category INTEGER,rating REAL,is_active INTEGER,primary_image_url TEXT)');
$pdo->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace TEXT,external_hotel_id TEXT,local_hotel_id INTEGER,decision_status TEXT)');
$pdo->exec("INSERT INTO catalog_hotels VALUES(900,'Fixture hotel',4,'Турция',10,'Анталья',11,'Кемер',5,4.7,1,NULL)");
$pdo->exec("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','3414',900,'accepted')");
$request = ['generation'=>1,'params'=>[
    'countryId'=>4,'dateFrom'=>'2026-09-22','dateTo'=>'2026-09-22','nightsFrom'=>7,'nightsTo'=>7,
    'adults'=>2,'childs'=>[],'meal'=>'','currency'=>'RUB','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],
    'arrivalId'=>'','operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[],'onlyDirect'=>false,'onlyCharter'=>false,
    'hotelCategory'=>'','hotelRating'=>'','priceFrom'=>'','priceTo'=>'']];
$listingPage = ['offers'=>$page['offers'],'search_ref'=>$ref,'generation'=>1,'page'=>1,'pages_count'=>1,'status'=>'complete'];
$beforeState = json_encode($state, JSON_THROW_ON_ERROR);
$projected = anytour_andromeda_search3_project($request, $pdo, $listingPage, [], [
    'directory'=>$listingDir,'store'=>$state,'created_at'=>$created]);
$tour = $projected['hotels'][0]['tours'][0] ?? null;
surcharge_check(is_array($tour), 'listing projection returns tour');
surcharge_check(($tour['price']['amount'] ?? null) === '93080.00', 'listing displays base plus retained surcharge');
surcharge_check(($tour['base_search_price']['amount'] ?? null) === '83080', 'listing preserves explicit base fact');
surcharge_check(($tour['search_surcharge']['party_surcharge']['amount'] ?? null) === '10000.00', 'listing exposes separate surcharge fact');
surcharge_check(($tour['search_surcharge']['final_price_verified'] ?? null) === false, 'listing estimate remains non-final');
surcharge_check(json_encode($state, JSON_THROW_ON_ERROR) === $beforeState, 'projection does not mutate retained base snapshot');
surcharge_check($listingBootstrapCalls === 1 && $listingFlightCalls === 1, 'listing projection is supplier-free');

unlink($listingPath);
$fallback = anytour_andromeda_search3_project($request, $pdo, $listingPage, [], [
    'directory'=>$listingDir,'store'=>$state,'created_at'=>$created]);
$fallbackTour = $fallback['hotels'][0]['tours'][0] ?? null;
surcharge_check(($fallbackTour['price']['amount'] ?? null) === '83080', 'missing surcharge cache keeps base listing price');
surcharge_check(!isset($fallbackTour['search_surcharge']) && !isset($fallbackTour['base_search_price']), 'missing surcharge remains unknown, never synthetic zero');
surcharge_check($listingBootstrapCalls === 1 && $listingFlightCalls === 1, 'missing cache never calls supplier from listing');

echo "Saved surcharge: one opt-in get_flights, same budget/lock, safe persisted estimate, supplier-free listing projection, base fallback, expiry/mapping/party invalidation, all outcomes no-replay passed offline.\n";
