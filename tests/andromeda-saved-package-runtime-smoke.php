<?php
declare(strict_types=1);
require $argv[1] . '/v2/api-andromeda-search3-preview.php';
require $argv[1] . '/app/integrations/andromeda-saved-package-runtime.php';

function check(bool $ok): void { if (!$ok) throw new RuntimeException('fixture_failed'); }
function refuse(callable $call): void {
    try { $call(); } catch (RuntimeException $expected) { return; }
    throw new LogicException('expected_refusal');
}
$root = $argv[2];
$ref = str_repeat('a',64); $source = str_repeat('b',40); $now = 1002; $calls = 0;
$criteria = ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>643,'PAGE'=>1];
$row = ['id'=>'private-selected-offer','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>83080,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'22.09.2026','nights'=>'7',
    'hotel'=>'Fixture hotel','operator'=>'Fixture operator','meal'=>'RO','mealKey'=>1,
    'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$resolver = AnyTourAndromedaHotelResolver::fromRows([['supplier_namespace'=>'andromeda_catalog',
    'external_hotel_id'=>'3414','decision_status'=>'accepted','catalog_hotel_id'=>'900',
    'existing_catalog_hotel_id'=>'900']], str_repeat('c',64));
$state = []; $store = new AnyTourAndromedaOfferStore($state,true);
$store->begin($ref,1,1000);
$page = $store->capture(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]],$criteria,$ref,1,1001,$resolver);
$context = ['provider'=>'andromeda','search_ref'=>$ref,'generation'=>1,'page'=>1,'offer_ref'=>$page['offers'][0]['offer_ref']];
$clock = static function() use (&$now):int { return $now; };
$allows = static fn(array $offer):bool => $offer['local_hotel_id']===900;
$raw = ['version'=>'1.01','claimDocument'=>[['catalogKey'=>'private-package-key']]];
foreach (['success','unknown'] as $case) {
    $directory=$root.'/'.$case.'/searches'; mkdir($directory,0700,true);
    anytour_andromeda_search3_save($directory.'/'.$ref.'-1.json',['status'=>'complete','store'=>$state]);
    anytour_andromeda_search3_save($directory.'/'.$ref.'-auth.json',[
        'created_at'=>1000,'session'=>['sid'=>'private-fixture-session','expires'=>time()+1800]]);
    $path=$directory.'/'.$ref.'-1000-1-'.$context['offer_ref'].'-package.json';
    $transport=static function($url) use (&$calls,$path,$case,$raw):array {
        ++$calls;
        $disk=json_decode(file_get_contents($path),true);
        check($disk['record']['status']==='reserved' && $disk['source']===str_repeat('b',40));
        parse_str(parse_url($url,PHP_URL_QUERY),$q);
        check($q['action']==='broninit' && $q['claiminc']==='private-selected-offer');
        if($case==='unknown')throw new RuntimeException('fixture_transport_failure');
        return ['status'=>200,'body'=>json_encode($raw)];
    };
    $run=static fn()=>anytour_andromeda_capture_saved_package($directory,$context,$source,$allows,$transport,true,$clock);
    if($case==='success') {
        refuse(fn()=>anytour_andromeda_capture_saved_package($directory,$context,$source,$allows,$transport));
        refuse(fn()=>anytour_andromeda_capture_saved_package($directory,$context,$source,static fn()=>false,$transport,true,$clock));
        check($calls===0 && !file_exists($path));
        $receipt=$run();
        check($receipt['status']==='captured' && !$receipt['reused'] && !$receipt['quote_verified']);
        check(!str_contains(json_encode($receipt),'private-'));
        $disk=json_decode(file_get_contents($path),true);
        check($disk['record']['private_package']===$raw && (fileperms($path)&0777)===0600);
        check($run()['reused']===true && $calls===1);
        $now=1900; refuse($run); check($calls===1); $now=1002;
        file_put_contents($path,'[]'); refuse($run); check($calls===1);
    } else {
        refuse($run); refuse($run);
        check($calls===2 && json_decode(file_get_contents($path),true)['record']['status']==='unknown');
    }
    $budget=json_decode(file_get_contents(dirname($directory).'/monthly-requests.json'),true);
    check($budget['reserved_requests']===1 && $budget['monthly_limit']===5000000);
}
echo "Saved package bridge: private capture/readback, current mapping, budget, expiry and unknown no-replay passed offline.\n";

// Exercise the concrete selected DTO -> current PDO mapping -> existing bridge path.
// SQLite is disposable; transport is injected, so no supplier/production DB is used.
$entryCalls = 0;
foreach (['entry-captured', 'entry-stale'] as $case) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE catalog_hotels (id INTEGER, country_id INTEGER, is_active INTEGER)');
    $pdo->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace TEXT, external_hotel_id TEXT, local_hotel_id INTEGER, decision_status TEXT)');
    $pdo->exec('INSERT INTO catalog_hotels VALUES (900,4,1),(901,4,1)');
    $pdo->exec("INSERT INTO andromeda_hotel_identities VALUES ('andromeda_catalog','3414',900,'accepted')");
    $saved = ['local_country_id' => 4];
    $config = ['catalog_path' => $root . '/' . $case . '/catalog.json'];
    $directory = dirname($config['catalog_path']) . '/searches';
    mkdir($directory, 0700, true);
    anytour_andromeda_search3_save($directory . '/' . $ref . '-1.json', ['status'=>'complete','store'=>$state]);
    anytour_andromeda_search3_save($directory . '/' . $ref . '-auth.json', [
        'created_at'=>1000,'session'=>['sid'=>'private-fixture-session','expires'=>time()+1800]]);
    $selection = anytour_andromeda_search3_detail_selection(['status'=>'complete','store'=>$state], $context, $now, $pdo, 4)['selected_offer'];
    $path = $directory . '/' . $ref . '-1000-1-' . $context['offer_ref'] . '-package.json';
    $transport = static function($url) use (&$entryCalls, $path, $case, $pdo, $raw): array {
        ++$entryCalls;
        $disk = json_decode(file_get_contents($path), true);
        check($disk['record']['status'] === 'reserved');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        check($query['action'] === 'broninit' && $query['claiminc'] === 'private-selected-offer');
        if ($case === 'entry-stale') $pdo->exec('UPDATE catalog_hotels SET is_active=0 WHERE id=900');
        return ['status'=>200,'body'=>json_encode($raw)];
    };
    $run = static fn() => anytour_andromeda_capture_selected_package($config, $saved, $selection, $pdo, $source, true, $transport, $clock);
    $before = $entryCalls;
    refuse(fn() => anytour_andromeda_capture_selected_package($config, $saved, $selection, $pdo, $source));
    refuse(fn() => anytour_andromeda_capture_selected_package($config, [], $selection, $pdo, $source, true, $transport, $clock));
    refuse(fn() => anytour_andromeda_capture_selected_package($config, ['local_country_id'=>1], $selection, $pdo, $source, true, $transport, $clock));
    refuse(fn() => anytour_andromeda_capture_selected_package($config, $saved, array_replace($selection, ['local_id'=>901]), $pdo, $source, true, $transport, $clock));
    refuse(fn() => anytour_andromeda_capture_selected_package($config, $saved, array_replace($selection, ['operator_ref'=>'wrong-operator']), $pdo, $source, true, $transport, $clock));
    $pdo->exec("UPDATE andromeda_hotel_identities SET decision_status='pending'");
    refuse($run);
    $pdo->exec("UPDATE andromeda_hotel_identities SET decision_status='accepted'");
    check($entryCalls === $before && !file_exists($path));
    if ($case === 'entry-captured') {
        $receipt = $run();
        check($receipt['context']['local_id'] === 900 && $receipt['context']['operator_ref'] === $selection['operator_ref']);
        check(!$receipt['identity_verified'] && !$receipt['quote_verified'] && !$receipt['selection_enabled']);
        check(!str_contains(json_encode($receipt), 'private-') && $run()['reused']);
        $pdo->exec('UPDATE andromeda_hotel_identities SET local_hotel_id=901');
        refuse($run);
    } else {
        refuse($run);
        $disk = json_decode(file_get_contents($path), true);
        check($disk['record']['status'] === 'stale');
        $pdo->exec('UPDATE catalog_hotels SET is_active=1 WHERE id=900');
        refuse($run);
    }
    check($entryCalls === $before + 1);
    $budget = json_decode(file_get_contents(dirname($directory) . '/monthly-requests.json'), true);
    check($budget['reserved_requests'] === 1);
}
// Exercise only rejected actions on the real pinned transport: these cannot reach cURL.
$baseUrl = 'https://gateway.samo.ru/api/?version=1.01&action=';
refuse(fn() => (new AnyTourAndromedaTransport())($baseUrl . 'broninit'));
foreach (['bron','bron_ticket','calc','get_flights','price'] as $action) {
    refuse(fn() => (new AnyTourAndromedaTransport(false, true))($baseUrl . $action));
}
check((new ReflectionMethod(AnyTourAndromedaTransport::class, '__construct'))->getNumberOfParameters() === 2);
echo "Selected package entry: existing DTO/current PDO mapping, same capture, stale no-replay and transport exclusions passed offline.\n";
