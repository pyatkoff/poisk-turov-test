<?php
declare(strict_types=1);
namespace AnyTour {
    // Count the real shared transport's cURL calls without touching the network.
    function curl_init($url) { $GLOBALS['tv_wire']['url']=$url; ++$GLOBALS['tv_wire']['calls']; return new \stdClass(); }
    function curl_setopt_array($ch,$options) { $GLOBALS['tv_wire']['options']=$options; return true; }
    function curl_exec($ch) { return $GLOBALS['tv_wire']['body']; }
    function curl_errno($ch) { return $GLOBALS['tv_wire']['errno'] ?? 0; }
    function curl_getinfo($ch,$option) { return $option === CURLINFO_TOTAL_TIME ? ($GLOBALS['tv_wire']['seconds'] ?? 0.0) : $GLOBALS['tv_wire']['status']; }
    function json_decode($body,$associative=false) { $GLOBALS['tv_wire']['decodes']=($GLOBALS['tv_wire']['decodes'] ?? 0)+1; return \json_decode($body,$associative); }
    function curl_close($ch) { $GLOBALS['tv_wire']['closed']=true; }
}
namespace {
set_error_handler(static function(int $severity,string $message,string $file,int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message,0,$severity,$file,$line);
});
require_once __DIR__.'/../app/integrations/tourvisor-preview-gateway.php';
require_once __DIR__.'/../app/integrations/search3-messenger-handoff.php';
function tvg_ok(bool $ok,string $label): void {
    $GLOBALS['tv_checks']=($GLOBALS['tv_checks'] ?? 0)+1;
    if (!$ok) throw new RuntimeException('FAIL '.$label);
}
function tvg_denied(callable $call,string $code): void {
    try { $call(); } catch (InvalidArgumentException $e) { tvg_ok($e->getMessage()===$code,$code.' actual '.$e->getMessage()); return; }
    catch (\AnyTour\TourvisorInteractiveFailure $e) { tvg_ok(($e->response['error'] ?? null)===$code && $e->httpStatus===409,$code.' safe mismatch envelope'); return; }
    throw new RuntimeException('expected '.$code);
}

$GLOBALS['tv_wire']=['calls'=>0,'body'=>'{"searchId":91}','status'=>200];
\AnyTour\TourvisorInteractiveClient::get('/tours/search',['childs'=>[0,0,8],'onlyDirect'=>false],'fixture-token');
$wire=$GLOBALS['tv_wire'];
tvg_ok($wire['calls']===1 && $wire['url']==='https://api.tourvisor.ru/search/api/v1/tours/search?childs=0&childs=0&childs=8&onlyDirect=false','same fixed destination and repeated query');
tvg_ok($wire['options'][CURLOPT_TIMEOUT]===45 && $wire['options'][CURLOPT_CONNECTTIMEOUT]===10 && $wire['closed'],'same interactive timing and close');
// Exercise the actual extracted transport, including every allowlisted stage and all failure classes.
foreach (['/tours/search'=>'search_start','/tours/search/91/status'=>'search_status',
    '/tours/search/91/continue'=>'search_continue','/tours/search/91'=>'search_results',
    '/tours/private-offer-id'=>'tour','/tours/private-offer-id/flights'=>'flights','/other/private'=>'other'] as $path=>$stage) {
    foreach ([[410,0,'http',410],[502,0,'http',502],[200,28,'timeout',502],[0,7,'transport',502]] as [$status,$errno,$category,$http]) {
        $GLOBALS['tv_wire']=['calls'=>0,'body'=>'{"secret":"supplier-error-body"}','status'=>$status,'errno'=>$errno,'seconds'=>45.0124];
        try { \AnyTour\TourvisorInteractiveClient::get($path,['private'=>'query-secret'],'fixture-token-secret');
            throw new RuntimeException('expected transport failure'); }
        catch (\AnyTour\TourvisorInteractiveFailure $e) {
            $expected=['provider'=>'tourvisor','stage'=>$stage,'category'=>$category,'httpStatus'=>$status,'curlErrno'=>$errno,'elapsedMs'=>45012];
            tvg_ok($e->response['diagnostics']===$expected && $e->httpStatus===$http,'exact safe transport diagnostic '.$stage.'/'.$category);
            tvg_ok($GLOBALS['tv_wire']['calls']===1 && $GLOBALS['tv_wire']['closed'] && empty($GLOBALS['tv_wire']['decodes']),
                'one attempt, close, zero error-body decode');
            tvg_ok(array_keys($e->response)===($errno===0?['ok','error','upstreamStatus','diagnostics']:['ok','error','diagnostics']),
                'no raw body, URL, token or query in error projection');
        }
    }
}
foreach ([NAN,INF,-1.0,3600.1] as $seconds) {
    $facts=\AnyTour\TourvisorInteractiveClient::failureDiagnostics('/tours/fixture',-1,600,$seconds);
    tvg_ok(array_keys($facts)===['provider','stage','category'],'invalid numeric facts omitted');
}
$edge=\AnyTour\TourvisorInteractiveClient::failureDiagnostics('/tours/search/not-numeric/status',0,0,0.0);
tvg_ok($edge===['provider'=>'tourvisor','stage'=>'other','category'=>'http','httpStatus'=>0,'curlErrno'=>0,'elapsedMs'=>0],
    'strict stage recognition and literal measured zero preserved');
$now=time(); $clock=static fn():int=>$now;
$day=(new DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d');
$end=(new DateTimeImmutable($day))->modify('+7 days')->format('Y-m-d');
$params=['departureId'=>1,'countryId'=>4,'dateFrom'=>$day,'dateTo'=>$day,'nightsFrom'=>7,'nightsTo'=>7,
    'adults'=>2,'childs'=>[0,0,8],'currency'=>'RUB'];
$hotel=['uiId'=>1006,'namespace'=>'search3-ui','name'=>'Каталожный отель','resort'=>'Хургада'];
$listing=['id'=>'43282561000937','date'=>$day,'nights'=>7,'operator'=>['russianName'=>'Интурист','name'=>'Intourist']];
$tour=$listing+['hotel'=>['id'=>1006],'adults'=>2,'childs'=>3,'currency'=>'RUB','price'=>'209867',
    'departure'=>['id'=>1,'name'=>'Москва'],'roomType'=>'premium garden','meal'=>['name'=>'AI'],
    'placement'=>'2+3','fuelCharge'=>13789];
$leg=static fn(string $from,string $to,string $date,string $number):array=>[
    'number'=>$number,'departure'=>['date'=>$date,'port'=>['shortName'=>$from]],
    'arrival'=>['date'=>$date,'port'=>['shortName'=>$to]]];
$pair=['forward'=>[$leg('SVO','HRG',$day,'SU420')],'backward'=>[$leg('HRG','SVO',$end,'SU421')],
    'dateForward'=>$day,'dateBackward'=>$end,'price'=>['value'=>'209867','currency'=>'RUB'],'fuelCharge'=>13789];
$pairB=$pair; $pairB['backward'][0]['number']='SU425'; $pairB['price']['value']='233100.50'; $pairB['expiresAt']=$now+100;
$session=['search3_handoff_csrf'=>str_repeat('c',64),'anex_context_generation'=>17,'anex_offer_contexts'=>['keep'=>'original']];
$disk=[]; $saves=0; $calls=[]; $activeRid='';
$save=static function(array &$state)use(&$disk,&$saves):void{$disk=$state;++$saves;};
$catalog=static fn(int $id,int $country):?array=>($id===1006 && $country===4)?$hotel:null;
$transport=static function(string $path,array $query,array &$state)use(&$calls,&$disk,&$activeRid,$listing,$tour,$pair,$pairB):array{
    tvg_ok(($disk['tourvisor_requests'][$activeRid]['state'] ?? null)==='unknown','durable UNKNOWN before physical call');
    $calls[]=[$path,$query]; $state['anex_offer_contexts']['keep']='concurrent';
    return match($path){
        '/tours/search'=>['searchId'=>91],
        '/tours/search/91'=>[['id'=>1006,'tours'=>[$listing]]],
        '/tours/search/91/status'=>['progress'=>100],
        '/tours/search/91/continue'=>['continued'=>true],
        '/tours/43282561000937'=>$tour,
        '/tours/43282561000937/flights'=>['flights'=>[['placeholder'=>true],$pair,$pairB]],
        default=>throw new RuntimeException('unexpected route'),
    };
};
$n=0;
$request=static function(string $action,array $extra=[])use(&$n,&$session):array{
    $r=['action'=>$action,'provider'=>'tourvisor','request_id'=>md5((string)++$n),'csrf'=>str_repeat('c',64)];
    if($action!=='tv_search')$r+=['generation'=>$session['tourvisor_context_generation'],'search_ref'=>$session['tourvisor_context']['search_ref']];
    return $r+$extra;
};
$run=static function(array $r)use(&$session,&$activeRid,$now,$transport,$catalog,$save,$clock):array{
    $activeRid=$r['request_id']; return AnyTourTourvisorPreviewGateway::handle($r,$session,$now,$transport,$catalog,$save,$clock);
};
$searchRequest=$request('tv_search',['params'=>$params]); $started=$run($searchRequest);
tvg_ok($session['tourvisor_context']['params']['childs']===[0,0,8] && $session['tourvisor_context_generation']===1,'server owns generation and exact ordered party');
$again=$run($searchRequest); tvg_ok($again===$started && count($calls)===1,'same search operation cached without second HTTP');
$run($request('tv_status')); $run($request('tv_continue'));
$resultsRequest=$request('tv_results');$results=$run($resultsRequest); $ref=$results['offers'][0]['offer_ref'];
$resultsSession=$session;
tvg_ok($run($resultsRequest)===$results,'results replay retains its own context deadline without a checked tour');
tvg_ok(count($results['offers'])===1 && $results['offers'][0]['local_hotel_id']===1006,'server result membership with catalogue identity');
$checkedRequest=$request('tv_tour',['offer_ref'=>$ref]); $checked=$run($checkedRequest);
tvg_ok($checked['data']['fuelCharge']===13789 && $checked['data']['price']==='209867','supplier money and fuel unchanged');
$frozen=AnyTourTourvisorSelectedTourHandoff::freeze($checked['selector'],$session,$now,$clock);
tvg_ok($frozen['status']==='ready' && $frozen['record']['observation']['trip']['childAges']===[0,0,8]
    && $frozen['record']['observation']['price']['amount']==='209867','checked whole party captured with no fuel or infant arithmetic');
tvg_ok($frozen['record']['observation']['flights']['state']==='manager-deferred','manager path is independently ready');
$before=count($calls); $run($checkedRequest); tvg_ok(count($calls)===$before,'same checked tour response reused');
tvg_denied(fn()=>$run($request('tv_tour',['offer_ref'=>$ref])),'tv_operation_already_attempted');
$managerRequest=$request('tv_select',['offer_ref'=>$ref,'choice_ref'=>'manager-deferred']);
$managerSelected=$run($managerRequest);
$beforeFlightsSession=$session;
$flightsRequest=$request('tv_flights',['offer_ref'=>$ref]);
$flights=$run($flightsRequest);
tvg_ok(array_column($flights['choices'],'index')===[1,2],'opaque pair refs retain original returned indexes including skipped placeholder');
$selectedRequest=$request('tv_select',['offer_ref'=>$ref,'choice_ref'=>$flights['choices'][1]['choice_ref']]);
$selected=$run($selectedRequest);
$pairFrozen=AnyTourTourvisorSelectedTourHandoff::freeze($selected['selector'],$session,$now,$clock);
tvg_ok($pairFrozen['record']['observation']['price']['amount']==='233100.50'
    && str_contains($pairFrozen['record']['observation']['flights']['inward'][0]['label'],'SU425'),'selected pair keeps its own total and legs');
tvg_ok($pairFrozen['record']['observation']['price']['expiresAt']===$now+100,'shorter explicit pair expiry never inherits the tour deadline');
tvg_ok($run($flightsRequest)===$flights && $run($selectedRequest)===$selected
    && $run($managerRequest)===$managerSelected && count($calls)===6,'healthy tour, flights and both selection caches retain exact bytes with no HTTP');
tvg_ok(count($calls)===6 && $session['anex_context_generation']===17 && $session['anex_offer_contexts']['keep']==='concurrent','six ordinary supplier actions only, select local, foreign provider state preserved');
$producerRequest=['action'=>'handoff_freeze','csrf'=>str_repeat('c',64)]+$selected['selector'];
$producer=AnyTourSearch3MessengerHandoff::freeze($producerRequest,$session,$now,$save,$clock);
tvg_ok($producer['state']==='prepared','new server-captured TV record reaches SAME producer');
$receiver=getenv('SEARCH3_RECEIVER_SOURCE');
if(is_string($receiver)&&$receiver!==''){
    require_once $receiver.'/services/Search3TourHandoffSnapshot.php';
    tvg_ok(Search3TourHandoffSnapshot::normalize($frozen['record'])!==null
        && Search3TourHandoffSnapshot::normalize($pairFrozen['record'])!==null,'actual frozen receiver accepts both newly captured paths');
}

$bad=$request('tv_select',['offer_ref'=>$ref,'choice_ref'=>'manager-deferred','amount'=>'1']);
tvg_denied(fn()=>$run($bad),'tv_invalid_request');
$bad=$request('tv_tour',['offer_ref'=>'tourvisor:'.str_repeat('0',64)]);
tvg_denied(fn()=>$run($bad),'tv_offer_not_owned');
$bad=$request('tv_status');$bad['generation']=9;
tvg_denied(fn()=>$run($bad),'tv_stale_context');
$bad=$searchRequest;$bad['params']['childs']=[8];
tvg_denied(fn()=>$run($bad),'tv_request_changed');
$expired=$session;$expired['tourvisor_context']['expires_at']=$now;
$r=$request('tv_status');
tvg_denied(function()use($r,&$expired,$now,$transport,$catalog,$save,$clock){AnyTourTourvisorPreviewGateway::handle($r,$expired,$now,$transport,$catalog,$save,$clock);},'tv_stale_context');
foreach(['Coral Travel','Sunmar',null] as $operator){
    $blocked=$session; $blocked['tourvisor_context']['offers'][$ref]['operators']=$operator===null?[]:[$operator];
    unset($blocked['tourvisor_context']['offers'][$ref]['operations']['tv_tour']);
    $r=$request('tv_tour',['offer_ref'=>$ref]); $prior=count($calls);
    tvg_denied(function()use($r,&$blocked,$now,$transport,$catalog,$save,$clock){AnyTourTourvisorPreviewGateway::handle($r,$blocked,$now,$transport,$catalog,$save,$clock);},
        $operator===null?'tv_operator_unverified':'tv_operator_listing_only');
    tvg_ok(count($calls)===$prior,'blocked operator emits no HTTP');
}
$unknown=$session; $r=$request('tv_results');$physical=0;
$failing=static function()use(&$physical):array{++$physical;throw new RuntimeException('ambiguous');};
try{AnyTourTourvisorPreviewGateway::handle($r,$unknown,$now,$failing,$catalog,$save,$clock);}catch(RuntimeException $e){}
tvg_denied(function()use($r,&$unknown,$now,$failing,$catalog,$save,$clock){AnyTourTourvisorPreviewGateway::handle($r,$unknown,$now,$failing,$catalog,$save,$clock);},'tv_operation_unknown');
tvg_ok($physical===1,'UNKNOWN cannot replay physical supplier attempt');
$diagnosticSession=$session; $diagnosticRequest=$request('tv_status');
$GLOBALS['tv_wire']=['calls'=>0,'body'=>'private-error','status'=>0,'errno'=>28,'seconds'=>45.0001];
$diagnosticTransport=static fn(string $p,array $q,array &$state):array=>\AnyTour\TourvisorInteractiveClient::get($p,$q,'fixture-token');
try { AnyTourTourvisorPreviewGateway::handle($diagnosticRequest,$diagnosticSession,$now,$diagnosticTransport,$catalog,$save,$clock);
    throw new RuntimeException('expected gateway transport failure'); }
catch (\AnyTour\TourvisorInteractiveFailure $e) {
    tvg_ok($e->response['diagnostics']['stage']==='search_status' && $e->response['diagnostics']['elapsedMs']===45000,
        'gateway preserves same safe supplier facts');
}
tvg_ok($disk['tourvisor_requests'][$diagnosticRequest['request_id']]['state']==='unknown','failure keeps original durable UNKNOWN');
tvg_denied(function()use($diagnosticRequest,&$diagnosticSession,$now,$diagnosticTransport,$catalog,$save,$clock){
    AnyTourTourvisorPreviewGateway::handle($diagnosticRequest,$diagnosticSession,$now,$diagnosticTransport,$catalog,$save,$clock);
},'tv_operation_unknown');
tvg_ok($GLOBALS['tv_wire']['calls']===1,'diagnostic propagation grants no retry');
// A later provider410 revokes all previously cached responses for that exact offer.
$revoked=$beforeFlightsSession;
$goneRequest=$request('tv_flights',['offer_ref'=>$ref]); $goneCalls=0;
$gone=static function()use(&$goneCalls):array {
    ++$goneCalls; throw new \AnyTour\TourvisorInteractiveFailure(['ok'=>false,'error'=>'Tourvisor request failed'],410);
};
try { AnyTourTourvisorPreviewGateway::handle($goneRequest,$revoked,$now,$gone,$catalog,$save,$clock);
    throw new RuntimeException('expected provider410'); }
catch (\AnyTour\TourvisorInteractiveFailure $e) { tvg_ok($e->httpStatus===410,'actual provider410 propagated'); }
tvg_ok(!empty($disk['tourvisor_context']['offers'][$ref]['expired']),'provider410 expiry durably checkpointed');
foreach ([$checkedRequest,$managerRequest] as $cachedRequest) {
    tvg_denied(function()use($cachedRequest,&$revoked,$now,$gone,$catalog,$save,$clock) {
        AnyTourTourvisorPreviewGateway::handle($cachedRequest,$revoked,$now,$gone,$catalog,$save,$clock);
    },'tv_offer_not_owned');
}
tvg_ok($goneCalls===1,'revoked cache admission never repeats supplier HTTP');
tvg_ok($revoked['tourvisor_requests'][$goneRequest['request_id']]['state']==='unknown',
    'provider410 original UNKNOWN remains sealed');
// Search/results caches and a different current offer are unaffected by that revocation.
tvg_ok(AnyTourTourvisorPreviewGateway::handle($resultsRequest,$revoked,$now,$gone,$catalog,$save,$clock)===$results,
    'listing cache remains valid independently of selected-price authority');
$other=$session; $otherRef='tourvisor:'.str_repeat('a',64);
$other['tourvisor_context']['offers'][$otherRef]=$other['tourvisor_context']['offers'][$ref];
$other['tourvisor_context']['offers'][$otherRef]['expired']=true;
tvg_ok(AnyTourTourvisorPreviewGateway::handle($checkedRequest,$other,$now,$gone,$catalog,$save,$clock)===$checked,
    'different expired offer cannot revoke the healthy cached tour');
// Late old flights410 must not revoke the same tour checked in a newer search.
$raceSession=$beforeFlightsSession; $oldFlightRequest=$request('tv_flights',['offer_ref'=>$ref]);
$newChecked=null; $newTourRequest=null; $raceCalls=0;
$race410=static function(string $path,array $query,array &$state)use(&$raceCalls,&$newChecked,&$newTourRequest,
    &$activeRid,$params,$now,$transport,$catalog,$save,$clock,$ref):array {
    ++$raceCalls;
    $base=['provider'=>'tourvisor','csrf'=>str_repeat('c',64)];
    $start=$base+['action'=>'tv_search','request_id'=>md5('newer-search'),'params'=>$params];
    $activeRid=$start['request_id'];
    AnyTourTourvisorPreviewGateway::handle($start,$state,$now,$transport,$catalog,$save,$clock);
    $pins=['generation'=>$state['tourvisor_context_generation'],'search_ref'=>$state['tourvisor_context']['search_ref']];
    $read=$base+$pins+['action'=>'tv_results','request_id'=>md5('newer-results')];
    $activeRid=$read['request_id'];
    $rows=AnyTourTourvisorPreviewGateway::handle($read,$state,$now,$transport,$catalog,$save,$clock);
    tvg_ok($rows['offers'][0]['offer_ref']===$ref,'new search returns the same supplier tour identity');
    $newTourRequest=$base+$pins+['action'=>'tv_tour','request_id'=>md5('newer-tour'),'offer_ref'=>$ref];
    $activeRid=$newTourRequest['request_id'];
    $newChecked=AnyTourTourvisorPreviewGateway::handle($newTourRequest,$state,$now,$transport,$catalog,$save,$clock);
    throw new \AnyTour\TourvisorInteractiveFailure(['ok'=>false,'error'=>'Tourvisor request failed'],410);
};
try { AnyTourTourvisorPreviewGateway::handle($oldFlightRequest,$raceSession,$now,$race410,$catalog,$save,$clock);
    throw new RuntimeException('expected late provider410'); }
catch (\AnyTour\TourvisorInteractiveFailure $e) { tvg_ok($e->httpStatus===410,'old caller receives its original410'); }
tvg_ok($raceSession['tourvisor_context_generation']===2 && empty($raceSession['tourvisor_context']['offers'][$ref]['expired']),
    'old410 cannot expire the newer current offer');
$newFrozen=AnyTourTourvisorSelectedTourHandoff::freeze($newChecked['selector'],$raceSession,$now,$clock);
tvg_ok($newFrozen['status']==='ready' && $newFrozen['record']['observation']['price']['amount']==='209867',
    'new generation canonical record keeps its own price authority');
tvg_ok(AnyTourTourvisorSelectedTourHandoff::freeze($checked['selector'],$raceSession,$now,$clock)['status']!=='ready',
    'old selection remains noncurrent after the new search');
$raceBefore=count($calls);
tvg_ok(AnyTourTourvisorPreviewGateway::handle($newTourRequest,$raceSession,$now,$race410,$catalog,$save,$clock)===$newChecked,
    'new checked response remains cached with exact bytes');
tvg_denied(function()use($oldFlightRequest,&$raceSession,$now,$race410,$catalog,$save,$clock) {
    AnyTourTourvisorPreviewGateway::handle($oldFlightRequest,$raceSession,$now,$race410,$catalog,$save,$clock);
},'tv_stale_context');
tvg_ok($raceCalls===1 && count($calls)===$raceBefore
    && $raceSession['tourvisor_requests'][$oldFlightRequest['request_id']]['state']==='unknown',
    'old UNKNOWN stays sealed and neither response admission adds supplier calls');
$changed=$session; $r=$request('tv_results');
$concurrent=static function(string $p,array $q,array &$s):array{
    ++$s['tourvisor_context_generation']; $s['tourvisor_context']=['newer'=>'must remain']; return [];
};
tvg_denied(function()use($r,&$changed,$now,$concurrent,$catalog,$save,$clock){AnyTourTourvisorPreviewGateway::handle($r,$changed,$now,$concurrent,$catalog,$save,$clock);},'tv_context_changed');
tvg_ok($changed['tourvisor_context']===['newer'=>'must remain'],'late old response cannot overwrite a newer search');
$noSave=$session; $r=$request('tv_results');$physical=0;
try{AnyTourTourvisorPreviewGateway::handle($r,$noSave,$now,$failing,$catalog,static function(){throw new RuntimeException('disk');},$clock);}catch(RuntimeException $e){}
tvg_ok($physical===0,'failed reservation prevents HTTP');
$cachedDeadline=$session['tourvisor_requests'][$checkedRequest['request_id']]['expires_at'];
tvg_ok($cachedDeadline===$frozen['record']['observation']['price']['expiresAt'],'replay deadline is original observation deadline');
$late=$session; $r=$request('tv_results'); $physical=0; $tick=$now;
$lateClock=static function()use(&$tick):int{return $tick;};
$lateSave=static function(array &$s)use(&$tick,$now):void{$tick=$now+1801;};
tvg_denied(function()use($r,&$late,$now,$failing,$catalog,$lateSave,$lateClock){
    AnyTourTourvisorPreviewGateway::handle($r,$late,$now,$failing,$catalog,$lateSave,$lateClock);
},'tv_stale_context');
tvg_ok($physical===0 && $late['tourvisor_requests'][$r['request_id']]['state']==='unknown','checkpoint crossing context expiry emits zero physical calls and keeps UNKNOWN');
$late=$session; unset($late['tourvisor_context']['offers'][$ref]['operations']['tv_flights']);
$late['tourvisor_context']['offers'][$ref]['checked']['expires_at']=$now+1;
$r=$request('tv_flights',['offer_ref'=>$ref]);$physical=0;$tick=$now;
$lateSave=static function(array &$s)use(&$tick,$now):void{$tick=$now+2;};
tvg_denied(function()use($r,&$late,$now,$failing,$catalog,$lateSave,$lateClock){
    AnyTourTourvisorPreviewGateway::handle($r,$late,$now,$failing,$catalog,$lateSave,$lateClock);
},'tv_checked_tour_missing');
tvg_ok($physical===0,'checkpoint crossing original checked-tour expiry cannot fetch flights');
$heavy=$resultsSession;
for($i=0;$i<4;++$i)$heavy['tourvisor_requests'][md5('padding'.$i)]=[
    'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'ready','expires_at'=>$now+900,
    'reply'=>['data'=>str_repeat('x',875000)]];
$r=$request('tv_tour',['offer_ref'=>$ref]); $largeTour=$tour;$largeTour['padding']=str_repeat('x',400000);
$largeTransport=static fn():array=>$largeTour;
tvg_denied(function()use($r,&$heavy,$now,$largeTransport,$catalog,$save,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$heavy,$now,$largeTransport,$catalog,$save,$clock);
},'tv_context_limit');
tvg_ok(!isset($heavy['tourvisor_context']['offers'][$ref]['checked'])
    && strlen(json_encode([$heavy['tourvisor_context'],$heavy['tourvisor_requests']],JSON_THROW_ON_ERROR))<=4194304
    && empty($heavy['tourvisor_handoff_records']),'oversized candidate never enters any capture checkpoint or persisted context');
$badParty=$resultsSession; $r=$request('tv_tour',['offer_ref'=>$ref]); $wrongTour=$tour;$wrongTour['childs']=2;
tvg_denied(function()use($r,&$badParty,$now,$wrongTour,$catalog,$save,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$badParty,$now,static fn():array=>$wrongTour,$catalog,$save,$clock);
},'tv_tour_mismatch');
tvg_ok(empty($badParty['tourvisor_handoff_records']),'wrong party cannot mint a selected record');
// Diagnose the existing strict rejection without exporting either compared value.
foreach ([
    'tour_id'=>['id'=>'other-tour-secret'], 'hotel_id'=>['hotel'=>['id'=>999]],
    'adults'=>['adults'=>'2'], 'children'=>['childs'=>'3'],
    'departure_id'=>['departure'=>['id'=>999]], 'date'=>['date'=>'19.04.2027'], 'nights'=>['nights'=>'7'],
    'multiple'=>['id'=>'other-tour-secret','hotel'=>['id'=>999],'adults'=>'2','childs'=>'3',
        'departure'=>['id'=>999],'date'=>'19.04.2027','nights'=>'7'],
    'malformed_date'=>['date'=>['private'=>'SYNTHETIC_PRIVATE_SUPPLIER_TEXT']],
] as $field=>$delta) {
    $failedFields=$field==='multiple' ? ['tour_id','hotel_id','adults','children','departure_id','date','nights']
        : ($field==='malformed_date' ? ['date'] : [$field]);
    $fresh=$resultsSession; $r=$request('tv_tour',['offer_ref'=>$ref]);$bad=array_replace($tour,$delta);
    $bad['private']='SYNTHETIC_PRIVATE_SUPPLIER_TEXT';$calls=0;
    $transport=static function()use($bad,&$calls):array{++$calls;return $bad;};
    try { AnyTourTourvisorPreviewGateway::handle($r,$fresh,$now,$transport,$catalog,$save,$clock);
        throw new RuntimeException('expected mismatch'); }
    catch (Throwable $e) {
        tvg_ok($e instanceof \AnyTour\TourvisorInteractiveFailure,'strict mismatch carries safe detail for '.$field);
        tvg_ok($e->httpStatus===409 && $e->response['error']==='tv_tour_mismatch','same public mismatch status/code');
        $d=$e->response['diagnostics'];
        tvg_ok($d['provider']==='tourvisor' && $d['stage']==='tour' && $d['category']==='context'
            && $d['mismatchFields']===$failedFields,'only actual failed predicates '.$field);
        tvg_ok(array_keys($d['mismatchTypes'])===$failedFields,'fixed type fields');
        foreach($d['mismatchTypes'] as $pair)tvg_ok(array_keys($pair)===['supplier','context']
            && in_array($pair['supplier'],['null','integer','string','number','boolean','array','other'],true)
            && in_array($pair['context'],['null','integer','string','number','boolean','array','other'],true),'fixed type envelope');
        $encoded=json_encode($e->response,JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC_PRIVATE_SUPPLIER_TEXT','other-tour-secret','19.04.2027'] as $secret)
            tvg_ok(!str_contains($encoded,$secret),'no compared supplier value in diagnostics');
        tvg_ok($calls===1 && $fresh['tourvisor_requests'][$r['request_id']]['state']==='unknown'
            && empty($fresh['tourvisor_handoff_records']) && !isset($fresh['tourvisor_context']['offers'][$ref]['checked']),
            'mismatch leaves original unknown and cannot mint checked selection');
        tvg_denied(function()use($r,&$fresh,$now,$transport,$catalog,$save,$clock){
            AnyTourTourvisorPreviewGateway::handle($r,$fresh,$now,$transport,$catalog,$save,$clock);
        },'tv_operation_unknown');
        tvg_ok($calls===1,'diagnostic failure is never physically replayed');
        if(in_array('date',$failedFields,true))tvg_ok($d['dateFormats']===['supplier'=>$field==='malformed_date'?'non-string':'dmy','context'=>'iso'],'fixed date formats without dates');
        else tvg_ok(!isset($d['dateFormats']),'no unrelated date diagnostic');
    }
}
echo 'TV private session gateway: '.$GLOBALS['tv_checks']." checks PASS; supplier/bot network=0, DB writes=0.\n";
}
