<?php
declare(strict_types=1);
require __DIR__.'/../app/integrations/andromeda-search.php';
$checks=0;
function check($condition): void { global $checks; if (!$condition) throw new RuntimeException('CHECK_FAILED'); ++$checks; }
function fails(callable $fn,string $expected): void {
    try { $fn(); } catch (Throwable $e) { check($e->getMessage()===$expected); return; }
    throw new RuntimeException('EXPECTED_FAILURE_'.$expected);
}
$criteria=AnyTourAndromedaClient::priceProbeParams();
$row=['id'=>'private-opaque-offer','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>'107193','currency'=>'RUB','currencyKey'=>643,'checkIn'=>'18.09.2026','nights'=>'8',
    'hotel'=>'Sharming Inn','operator'=>'Anex Tour','meal'=>'AI','mealKey'=>'6','andrMealKey'=>5,
    'room'=>'Standard Room','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$second=$row; $second['id']='private-other-offer'; $second['isOperatorHotelKey']=1;
$payload=['PAGE'=>1,'PAGES_COUNT'=>2,'PRICES'=>[$row,$second]];
$state=[]; $durable=[]; $calls=0;
$save=function(array $s) use (&$durable) { $durable=$s; return true; };
$transport=function($url) use (&$calls,&$durable,$payload) {
    check($durable['status']==='pending'); ++$calls;
    return ['status'=>200,'body'=>json_encode($calls===1?['sid'=>'fixture-session-private']:$payload)];
};
$client=new AnyTourAndromedaClient($transport,true);
$disabled=new AnyTourAndromedaSearch($state,$save);
fails(fn()=>$disabled->start($criteria,'s1',1,1000,$client,'fixture-user','fixture-password'),'ANDROMEDA_DISABLED');
check($calls===0 && $state===[]);
$handler=new AnyTourAndromedaSearch($state,$save,true);
$bad=$criteria; $bad['ADULT']=3;
fails(fn()=>$handler->start($bad,'s1',1,1000,$client,'u','p'),'ANDROMEDA_SEARCH_UNSUPPORTED');
check($state===[] && $calls===0);
$out=$handler->start($criteria,'s1',1,1000,$client,'fixture-user','fixture-password');
check($calls===2 && $out['status']==='partial' && count($out['offers'])===2);
check(count($out['hotels'])===2 && $out['hotels'][0]['card_key']!==$out['hotels'][1]['card_key']);
check($out['hotels'][0]['local_hotel_id']===null && $out['selection_enabled']===false);
check($out['offers'][0]['price']['amount']==='107193' && $out['offers'][0]['price']['fees']==='unknown');
$public=json_encode($out);
foreach (['private-opaque-offer','private-other-offer','fixture-session-private','fixture-password','fixture-user'] as $secret) check(strpos($public,$secret)===false);
check(strpos(json_encode($durable),'fixture-session-private')===false);
check($handler->resume('s1',1,1001)===$out && $calls===2);
fails(fn()=>$handler->start($criteria,'s1',1,1002,$client,'u','p'),'ANDROMEDA_SEARCH_REPLAY_REFUSED');
fails(fn()=>$handler->resume('s1',2,1002),'STALE_SEARCH');
fails(fn()=>$handler->resume('s1',1,1900),'EXPIRED_SEARCH');
fails(fn()=>serialize($handler),'ANDROMEDA_SERIALIZATION_DISABLED');

// Restoring an interrupted request returns pending; changing generation cannot replay it.
$pending=$durable; $pending['status']='pending'; $pending['store']['snapshot']=null;
$restored=new AnyTourAndromedaSearch($pending,$save,true);
check($restored->resume('s1',1,1002)['status']==='pending');
fails(fn()=>$restored->start($criteria,'s2',2,1002,$client,'u','p'),'ANDROMEDA_PREVIOUS_RESULT_UNKNOWN');
check($calls===2);

// Reservation persistence failure must prevent login, including in the same object.
$failed=[]; $failedCalls=0;
$never=new AnyTourAndromedaClient(function() use (&$failedCalls) { ++$failedCalls; throw new RuntimeException('private-details'); },true);
$broken=new AnyTourAndromedaSearch($failed,fn($s)=>false,true);
fails(fn()=>$broken->start($criteria,'s1',1,1000,$never,'u','p'),'ANDROMEDA_CHECKPOINT_UNAVAILABLE');
fails(fn()=>$broken->start($criteria,'s2',2,1000,$never,'u','p'),'ANDROMEDA_CHECKPOINT_UNAVAILABLE');
check($failedCalls===0);

// A supplier exception is isolated and sanitized; absence of a response is not empty availability.
$errorState=[];
$unavailable=new AnyTourAndromedaSearch($errorState,fn($s)=>true,true);
$error=$unavailable->start($criteria,'s1',1,1000,$never,'u','p');
check($error['status']==='unavailable' && $error['offers']===[] && $failedCalls===1);
check(strpos(json_encode($error),'private-details')===false);
fails(fn()=>$unavailable->start($criteria,'s2',2,1001,$never,'u','p'),'ANDROMEDA_PREVIOUS_RESULT_UNKNOWN');

// Completion persistence failure keeps durable pending; no success or same-instance retry leaks.
$completionState=[]; $saves=0; $successfulCalls=0;
$completion=new AnyTourAndromedaSearch($completionState,function($s) use (&$saves) { return ++$saves===1; },true);
$successful=new AnyTourAndromedaClient(function() use (&$successfulCalls,$payload) {
    return ['status'=>200,'body'=>json_encode(++$successfulCalls===1?['sid'=>'fixture-sid']:$payload)];
},true);
fails(fn()=>$completion->start($criteria,'s1',1,1000,$successful,'u','p'),'ANDROMEDA_CHECKPOINT_UNAVAILABLE');
fails(fn()=>$completion->resume('s1',1,1001),'ANDROMEDA_CHECKPOINT_UNAVAILABLE');
check($successfulCalls===2);

echo "Andromeda search: $checks checks passed\n";
if (isset($argv[1])) {
    $raw=file_get_contents($argv[1]);
    if (hash('sha256',$raw)!=='c4e0722837b12a8c3979972a1145d6d0e43d50539efebbd7e68a416590dad6a9') throw new RuntimeException('EVIDENCE_DIGEST_MISMATCH');
    $saved=json_decode($raw,true,32,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);
    $realState=[]; $realCalls=0;
    $replay=new AnyTourAndromedaClient(function() use (&$realCalls,$saved) {
        return ['status'=>200,'body'=>json_encode(++$realCalls===1?['sid'=>'offline-fixture-session']:$saved['payload'],JSON_THROW_ON_ERROR)];
    },true);
    $search=new AnyTourAndromedaSearch($realState,fn($s)=>true,true);
    $result=$search->start($saved['params'],'saved_34365549508',1,1000,$replay,'offline-user','offline-password');
    check(count($result['offers'])===50 && count($result['hotels'])===16 && $result['status']==='partial');
    check($search->resume('saved_34365549508',1,1001)===$result && $realCalls===2);
    $encoded=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    foreach ($saved['payload']['PRICES'] as $sourceRow) check(strpos($encoded,$sourceRow['id'])===false);
    file_put_contents($argv[2],$encoded);
    check(file_get_contents($argv[2])===$encoded);
    echo json_encode(['offers'=>count($result['offers']),'hotel_groups'=>count($result['hotels']),
        'status'=>$result['status'],'supplier_calls'=>0,'selection_enabled'=>false,
        'sha256'=>hash('sha256',$encoded)],JSON_THROW_ON_ERROR)."\n";
}
