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
