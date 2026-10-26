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
function tvg_limit(callable $call,string $stage,string $kind,string $phase,int $maximum): array {
    try { $call(); }
    catch (\AnyTour\TourvisorInteractiveFailure $e) {
        $d=$e->response['diagnostics'] ?? [];
        tvg_ok($e->httpStatus===409 && $e->response['error']==='tv_context_limit'
            && array_keys($e->response)===['ok','error','diagnostics'],'same local limit code/status, no upstream facts');
        tvg_ok(array_keys($d)===['provider','stage','category','limitKind','limitPhase','limitValue','limitMax']
            && $d['provider']==='tourvisor' && $d['stage']===$stage && $d['category']==='limit'
            && $d['limitKind']===$kind && $d['limitPhase']===$phase && $d['limitMax']===$maximum
            && is_int($d['limitValue']) && $d['limitValue']>=$maximum,'fixed measured limit profile');
        return $d;
    }
    throw new RuntimeException('expected diagnostic tv_context_limit');
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
// Missing child slots cannot silently turn a party into a smaller one.
foreach([[null],[0,null,8],[''],[0,' ',8],[false],[true],[0.0],[[0]],[0,0,8,9]] as $badAges){
    $badParams=$params;$badParams['childs']=$badAges;$badState=['keep'=>'untouched'];$attempts=0;$writes=0;
    $badInput=['provider'=>'tourvisor','action'=>'tv_search','request_id'=>md5(json_encode($badAges,JSON_THROW_ON_ERROR)),
        'csrf'=>str_repeat('c',64),'params'=>$badParams];
    try{AnyTourTourvisorPreviewGateway::handle($badInput,$badState,$now,
        static function()use(&$attempts):array{++$attempts;return ['searchId'=>91];},static fn()=>null,
        static function(array &$s)use(&$writes):void{++$writes;},$clock);throw new RuntimeException('expected invalid child party');}
    catch(\AnyTour\TourvisorInteractiveFailure $e){tvg_ok($e->httpStatus===400
        && $badState===['keep'=>'untouched'] && $attempts===0 && $writes===0,'invalid child party denied before reservation or dispatch');}
}
foreach([[0,0,8],['0','0','8'],[0],[17],[],'0'] as $ages){
    $validParams=$params;$validParams['childs']=$ages;
    $expected=is_array($ages)?array_map('intval',$ages):[0];
    tvg_ok(\AnyTour\TourvisorInteractiveClient::searchParams($validParams)['childs']===$expected,
        'literal infant zero, duplicate order and ordinary scalar age remain exact');
}
// JSON true must not become one adult/night/identity/filter before supplier dispatch.
foreach(['departureId','countryId','adults','nightsFrom','nightsTo','arrivalId','priceFrom','priceTo'] as $field){
    foreach([true,false,1.0,1.5,[],(object)['value'=>1]] as $index=>$value){
        $badParams=$params;$badParams[$field]=$value;$badState=['keep'=>'untouched'];$attempts=0;$writes=0;
        $badInput=['provider'=>'tourvisor','action'=>'tv_search','request_id'=>md5('numeric-'.$field.'-'.$index),
            'csrf'=>str_repeat('c',64),'params'=>$badParams];
        try{AnyTourTourvisorPreviewGateway::handle($badInput,$badState,$now,
            static function()use(&$attempts):array{++$attempts;return ['searchId'=>91];},static fn()=>null,
            static function(array &$s)use(&$writes):void{++$writes;},$clock);
            throw new RuntimeException('expected invalid numeric search input');}
        catch(\AnyTour\TourvisorInteractiveFailure $e){tvg_ok($e->httpStatus===400
            && $badState===['keep'=>'untouched'] && $attempts===0 && $writes===0,
            'invalid numeric '.$field.'/'.$index.' denied before reservation or dispatch');}
    }
}
foreach([1,6,'1','2','6'] as $adults){
    $validParams=$params;$validParams['adults']=$adults;
    $parsed=\AnyTour\TourvisorInteractiveClient::searchParams($validParams);
    tvg_ok($parsed['adults']===(int)$adults && $parsed['childs']===[0,0,8],
        'valid adult count stays exact with duplicate infant ages');
}
$stringParams=array_replace($params,['departureId'=>'1','countryId'=>'4','nightsFrom'=>'7','nightsTo'=>'7',
    'adults'=>'2','arrivalId'=>'1','priceFrom'=>'0','priceTo'=>'500000','onlyCharter'=>true,'onlyDirect'=>false]);
$parsed=\AnyTour\TourvisorInteractiveClient::searchParams($stringParams);
tvg_ok(array_intersect_key($parsed,array_flip(['departureId','countryId','nightsFrom','nightsTo','adults','arrivalId','priceFrom','priceTo']))
    ===['departureId'=>1,'countryId'=>4,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'arrivalId'=>1,'priceFrom'=>0,'priceTo'=>500000]
    && $parsed['onlyCharter']===true && $parsed['onlyDirect']===false,
    'integer strings, literal zero price and separate boolean flags remain compatible');
foreach([null,''] as $empty){
    $validParams=array_replace($params,['arrivalId'=>$empty,'priceFrom'=>$empty,'priceTo'=>$empty]);
    $parsed=\AnyTour\TourvisorInteractiveClient::searchParams($validParams);
    tvg_ok($parsed['arrivalId']===null && $parsed['priceFrom']===null && $parsed['priceTo']===null,
        'optional missing numeric filters stay missing');
}
$defaultParams=$params;unset($defaultParams['adults'],$defaultParams['nightsFrom'],$defaultParams['nightsTo']);
$parsed=\AnyTour\TourvisorInteractiveClient::searchParams($defaultParams);
tvg_ok($parsed['adults']===2 && $parsed['nightsFrom']===5 && $parsed['nightsTo']===14,
    'omitted ordinary search defaults unchanged');
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
// Supplier noPlaces is authoritative for each direction/connection, not a price or onDemand flag.
foreach (['forward','backward'] as $direction) foreach ([0,1] as $legIndex) {
    foreach ([true,null,0,'false'] as $noPlaces) {
        $unavailable=$pair;
        if($legIndex===1)$unavailable[$direction][]=$unavailable[$direction][0];
        $unavailable[$direction][$legIndex]['noPlaces']=$noPlaces;
        $available=$pairB;$available['forward'][0]['noPlaces']=false;$available['forward'][0]['onDemand']=true;
        $availabilitySession=$beforeFlightsSession;$physical=0;
        $availabilityRequest=$request('tv_flights',['offer_ref'=>$ref]);
        $availabilityTransport=static function()use($unavailable,$available,&$physical):array{++$physical;return ['flights'=>[$unavailable,$available]];};
        $availabilitySave=static function(array &$s):void{};
        $availability=AnyTourTourvisorPreviewGateway::handle($availabilityRequest,$availabilitySession,$now,
            $availabilityTransport,$catalog,$availabilitySave,$clock);
        tvg_ok(array_column($availability['choices'],'index')===[1],
            'unavailable or malformed noPlaces cannot become a choice; false with onDemand stays selectable');
        $forged='tourvisor_pair:'.hash('sha256',json_encode($unavailable,JSON_THROW_ON_ERROR));
        tvg_denied(fn()=>AnyTourTourvisorPreviewGateway::handle($request('tv_select',['offer_ref'=>$ref,'choice_ref'=>$forged]),
            $availabilitySession,$now,$availabilityTransport,$catalog,$availabilitySave,$clock),'tv_pair_missing');
        $picked=AnyTourTourvisorPreviewGateway::handle($request('tv_select',['offer_ref'=>$ref,'choice_ref'=>$availability['choices'][0]['choice_ref']]),
            $availabilitySession,$now,$availabilityTransport,$catalog,$availabilitySave,$clock);
        $pickedRecord=AnyTourTourvisorSelectedTourHandoff::freeze($picked['selector'],$availabilitySession,$now,$clock);
        tvg_ok($physical===1 && $pickedRecord['status']==='ready'
            && $pickedRecord['record']['observation']['price']['amount']==='233100.50'
            && $pickedRecord['record']['observation']['price']['finalPriceVerified']===false,
            'available choice keeps exact supplier price and qualification; selection never repeats HTTP');
    }
}
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
for($i=0;$i<23;++$i)$heavy['tourvisor_requests'][md5('padding'.$i)]=[
    'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'ready','expires_at'=>$now+900,
    'reply'=>['data'=>str_repeat('x',880000)]];
$r=$request('tv_tour',['offer_ref'=>$ref]); $largeTour=$tour;$largeTour['padding']=str_repeat('x',400000);
$largeTransport=static fn():array=>$largeTour;
$captureLimit=tvg_limit(function()use($r,&$heavy,$now,$largeTransport,$catalog,$save,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$heavy,$now,$largeTransport,$catalog,$save,$clock);
},'tour','context_bytes','capture',20971520);
tvg_ok(!isset($heavy['tourvisor_context']['offers'][$ref]['checked'])
    && strlen(json_encode([$heavy['tourvisor_context'],$heavy['tourvisor_requests']],JSON_THROW_ON_ERROR))<=20971520
    && empty($heavy['tourvisor_handoff_records']),'oversized candidate never enters any capture checkpoint or persisted context');
// Owner-authorized tenfold session budget: the former64 boundary and the640th
// operation admit new intent without resetting or replaying old receipt IDs.
foreach ([64,639] as $retainedCount) {
    $admitted=$resultsSession;
    while(count($admitted['tourvisor_requests'])<$retainedCount)
        $admitted['tourvisor_requests'][md5('admitted'.count($admitted['tourvisor_requests']))]=[
            'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'unknown'];
    $retained=$admitted['tourvisor_requests']; $otherProvider=$admitted['anex_offer_contexts'];
    $newIntent=$request('tv_search',['params'=>$params]); $admittedCalls=0; $admittedSaves=0;
    $admitSave=static function(array &$s)use(&$admittedSaves):void{++$admittedSaves;};
    $admitTransport=static function(string $path,array $query,array &$s)
        use(&$admittedCalls,$newIntent):array {
        tvg_ok($path==='/tours/search'
            && ($s['tourvisor_requests'][$newIntent['request_id']]['state'] ?? null)==='unknown',
            'new intent durably reserved before its one synthetic call');
        ++$admittedCalls; return ['searchId'=>92];
    };
    $reply=AnyTourTourvisorPreviewGateway::handle(
        $newIntent,$admitted,$now,$admitTransport,$catalog,$admitSave,$clock);
    tvg_ok($reply['data']['searchId']===92
        && count($admitted['tourvisor_requests'])===$retainedCount+1
        && $admittedCalls===1 && $admittedSaves===2,
        'former64 and640th operation admitted within640 budget');
    $before=$retained[$resultsRequest['request_id']];$after=$admitted['tourvisor_requests'][$resultsRequest['request_id']];
    unset($before['reply'],$after['reply_retired']);
    tvg_ok(!isset($after['reply']) && $after===$before,'old listing receipt metadata retained at new search');
    unset($retained[$resultsRequest['request_id']]);
    tvg_ok(array_intersect_key($admitted['tourvisor_requests'],$retained)===$retained,'all other receipts immutable after budget increase');
    tvg_ok($admitted['anex_offer_contexts']===$otherProvider,
        'other provider context unchanged by cap increase');
}
// The owner's separate fivefold byte budget admits the former4MiB state
// without rewriting a single old ready/UNKNOWN receipt or changing other bounds.
$byteAdmitted=$resultsSession;
for($i=0;$i<5;++$i)$byteAdmitted['tourvisor_requests'][md5('old-byte-reply'.$i)]=[
    'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'ready','expires_at'=>$now+900,
    'reply'=>['data'=>str_repeat('x',875000)]];
$byteRetained=$byteAdmitted['tourvisor_requests'];$byteOther=$byteAdmitted['anex_offer_contexts'];
$byteBefore=strlen(json_encode([$byteAdmitted['tourvisor_context'],$byteRetained],JSON_THROW_ON_ERROR));
$byteIntent=$request('tv_status');$byteCalls=0;$byteSaves=0;
$byteSave=static function(array &$s)use(&$byteSaves):void{++$byteSaves;};
$byteTransport=static function(string $path,array $query,array &$s)use(&$byteCalls,$byteIntent):array {
    tvg_ok($path==='/tours/search/91/status'
        && $s['tourvisor_requests'][$byteIntent['request_id']]['state']==='unknown',
        'fivefold byte budget retains durable reservation before dispatch');
    ++$byteCalls;return ['progress'=>100];
};
$byteReply=AnyTourTourvisorPreviewGateway::handle($byteIntent,$byteAdmitted,$now,$byteTransport,$catalog,$byteSave,$clock);
tvg_ok($byteBefore>4194304 && $byteBefore<20971520 && $byteReply['data']===['progress'=>100]
    && $byteCalls===1 && $byteSaves===2,'former4MiB boundary admits exactly one ordinary intent below20MiB');
tvg_ok(array_intersect_key($byteAdmitted['tourvisor_requests'],$byteRetained)===$byteRetained
    && $byteAdmitted['anex_offer_contexts']===$byteOther,'fivefold byte increase preserves old receipts and foreign state');
// No supplier calls: prove each local guard separately, retaining all UNKNOWN receipts.
$full=$resultsSession;
while(count($full['tourvisor_requests'])<640) $full['tourvisor_requests'][md5('full'.count($full['tourvisor_requests']))]=[
    'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'unknown'];
$fullBefore=$full; $physical=0; $limitSaves=0;
$limitSave=static function(array &$s)use(&$limitSaves):void{++$limitSaves;};
$r=$request('tv_tour',['offer_ref'=>$ref]);
$d=tvg_limit(function()use($r,&$full,$now,$failing,$catalog,$limitSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$full,$now,$failing,$catalog,$limitSave,$clock);
},'tour','request_count','admission',640);
tvg_ok($d['limitValue']===640 && $full===$fullBefore && $physical===0 && $limitSaves===0,
    '641st operation rejects before reservation, no eviction/reset/HTTP');
tvg_ok(AnyTourTourvisorPreviewGateway::handle($resultsRequest,$full,$now,$failing,$catalog,$limitSave,$clock)===$results
    && $full===$fullBefore && $physical===0,'original healthy cached request survives full cap without new attempt');
$overflow=$resultsSession; $overflow['tourvisor_requests'][md5('overflow')]=[
    'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'ready','expires_at'=>$now+900,'reply'=>str_repeat('b',20971520)];
$expectedBytes=strlen(json_encode([$overflow['tourvisor_context'],$overflow['tourvisor_requests']],JSON_THROW_ON_ERROR));
$overflowBefore=$overflow;
$d=tvg_limit(function()use($r,&$overflow,$now,$failing,$catalog,$limitSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$overflow,$now,$failing,$catalog,$limitSave,$clock);
},'tour','context_bytes','admission',20971520);
tvg_ok($d['limitValue']===$expectedBytes && $overflow===$overflowBefore && $physical===0 && $limitSaves===0,
    'size guard reports measured JSON bytes, not receipt cap or supplier status');
// Padding models retained native replies; it is never used as a supplier/identity fact.
$padState=static function(array $s,int $bytes)use($now):array {
    $key=md5('budget-padding');$s['tourvisor_requests'][$key]=[
        'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'ready','expires_at'=>$now+900,'reply'=>''];
    $size=strlen(json_encode([$s['tourvisor_context'],$s['tourvisor_requests']],JSON_THROW_ON_ERROR));
    $s['tourvisor_requests'][$key]['reply']=str_repeat('b',$bytes-$size);return $s;
};
$reserveOverflow=$padState($resultsSession,20971520-8);$reserveBefore=$reserveOverflow;
$d=tvg_limit(function()use($r,&$reserveOverflow,$now,$failing,$catalog,$limitSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$reserveOverflow,$now,$failing,$catalog,$limitSave,$clock);
},'tour','context_bytes','reservation',20971520);
tvg_ok($reserveOverflow===$reserveBefore && $physical===0 && $limitSaves===0,
    'reservation candidate cannot exceed20MiB or checkpoint an oversized state');
$tooMany=$resultsSession;
for($i=1;$i<2000;++$i)$tooMany['tourvisor_context']['offers']['fixture-'.$i]=$tooMany['tourvisor_context']['offers'][$ref];
$resultPhysical=0;$replyListing=$listing;$replyListing['id']='new-fixture-tour';
$resultTransport=static function()use(&$resultPhysical,$replyListing):array{++$resultPhysical;return [['id'=>1006,'tours'=>[$replyListing]]];};
$r=$request('tv_results');
$d=tvg_limit(function()use($r,&$tooMany,$now,$resultTransport,$catalog,$limitSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$tooMany,$now,$resultTransport,$catalog,$limitSave,$clock);
},'search_results','offer_count','results',2000);
tvg_ok($d['limitValue']===2001 && $resultPhysical===1 && $limitSaves===1
    && count($tooMany['tourvisor_context']['offers'])===2000
    && $tooMany['tourvisor_requests'][$r['request_id']]['state']==='unknown',
    '2001st offer rejects only candidate, original UNKNOWN and 2000 offer cap retained');
$replyOverflow=$padState($resultsSession,20971520-1500);$replyPhysical=0;
$replyListing=$listing;$replyListing['padding']=str_repeat('x',10000);
$replyTransport=static function()use(&$replyPhysical,$replyListing):array{++$replyPhysical;return [['id'=>1006,'tours'=>[$replyListing]]];};
$r=$request('tv_results');
$d=tvg_limit(function()use($r,&$replyOverflow,$now,$replyTransport,$catalog,$limitSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$replyOverflow,$now,$replyTransport,$catalog,$limitSave,$clock);
},'search_results','context_bytes','reply',20971520);
tvg_ok($replyPhysical===1 && $limitSaves===2
    && $replyOverflow['tourvisor_requests'][$r['request_id']]['state']==='unknown'
    && strlen(json_encode([$replyOverflow['tourvisor_context'],$replyOverflow['tourvisor_requests']],JSON_THROW_ON_ERROR))<=20971520,
    'reply cache rejection retains original UNKNOWN and bounded context without retry');
tvg_denied(function()use($r,&$replyOverflow,$now,$replyTransport,$catalog,$limitSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$replyOverflow,$now,$replyTransport,$catalog,$limitSave,$clock);
},'tv_operation_unknown');
tvg_ok($replyPhysical===1 && $limitSaves===2,'diagnostic limit failure grants no new supplier attempt');
$generationOverflow=$resultsSession;$generationOverflow['tourvisor_context_generation']=2147483647;
$r=$request('tv_search',['params'=>$params]);
$d=tvg_limit(function()use($r,&$generationOverflow,$now,$failing,$catalog,$limitSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($r,$generationOverflow,$now,$failing,$catalog,$limitSave,$clock);
},'search_start','generation','admission',2147483647);
tvg_ok($d['limitValue']===2147483648 && $physical===0 && $limitSaves===2,'generation overflow is distinct and cannot dispatch');
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
        foreach($d['mismatchTypes'] as $typePair)tvg_ok(array_keys($typePair)===['supplier','context']
            && in_array($typePair['supplier'],['null','integer','string','number','boolean','array','other'],true)
            && in_array($typePair['context'],['null','integer','string','number','boolean','array','other'],true),'fixed type envelope');
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
$dmy=(new DateTimeImmutable($day))->format('d.m.Y');$endDmy=(new DateTimeImmutable($end))->format('d.m.Y');
foreach ([[$day,$dmy,$day,$end],[$dmy,$day,$day,$end],[$dmy,$dmy,$day,$end],[$day,$day,$day,$end],
    ['2028-02-29','29.02.2028','2028-02-29','2028-03-07'],
    ['2026-12-30','30.12.2026','2026-12-30','2027-01-06']] as [$listedDate,$checkedDate,$calendarDay,$calendarEnd]) {
    $fresh=$resultsSession;$fresh['tourvisor_context']['offers'][$ref]['date']=$listedDate;
    $fresh['tourvisor_context']['params']['dateFrom']=$calendarDay;$fresh['tourvisor_context']['params']['dateTo']=$calendarDay;
    $nativeTour=$tour;$nativeTour['date']=$checkedDate;$physical=0;
    $nativePair=$pair;$nativePair['dateForward']=(new DateTimeImmutable($calendarDay))->format('d.m.Y');
    $nativePair['dateBackward']=(new DateTimeImmutable($calendarEnd))->format('d.m.Y');
    $nativePair['forward'][0]['departure']['date']=$nativePair['dateForward'];
    $nativePair['backward'][0]['departure']['date']=$calendarEnd.'T12:34:56';
    $transport=static function(string $path)use($nativeTour,$nativePair,&$physical):array{
        ++$physical;return str_ends_with($path,'/flights') ? ['flights'=>[$nativePair]] : $nativeTour;
    };
    $r=$request('tv_tour',['offer_ref'=>$ref]);
    $reply=AnyTourTourvisorPreviewGateway::handle($r,$fresh,$now,$transport,$catalog,$save,$clock);
    tvg_ok($reply['data']===$nativeTour,'equivalent supplier date preserves exact native checked DTO');
    $record=AnyTourTourvisorSelectedTourHandoff::freeze($reply['selector'],$fresh,$now,$clock);
    tvg_ok($record['status']==='ready' && $record['record']['observation']['trip']['departure']===$calendarDay,
        'equivalent valid calendar date becomes ISO only in canonical selected record');
    tvg_ok($record['record']['observation']['price']['amount']==='209867'
        && $record['record']['observation']['trip']['childAges']===[0,0,8]
        && $record['record']['observation']['price']['expiresAt']===$now+900,'money party and original freshness unchanged');
    tvg_ok(AnyTourTourvisorPreviewGateway::handle($r,$fresh,$now,$transport,$catalog,$save,$clock)===$reply && $physical===1,
        'same successful checked request reuses exact native bytes');
    $prepared=AnyTourSearch3MessengerHandoff::freeze(['action'=>'handoff_freeze','csrf'=>str_repeat('c',64)]+$reply['selector'],
        $fresh,$now,$save,$clock);
    tvg_ok($prepared['state']==='prepared','mixed date formats reach same existing producer without sends');
    $f=$request('tv_flights',['offer_ref'=>$ref]);
    $fl=AnyTourTourvisorPreviewGateway::handle($f,$fresh,$now,$transport,$catalog,$save,$clock);
    tvg_ok($fl['data']===['flights'=>[$nativePair]] && count($fl['choices'])===1,'mixed pair/leg dates preserve native DTO and original pair choice');
    $selected=AnyTourTourvisorPreviewGateway::handle($request('tv_select',['offer_ref'=>$ref,'choice_ref'=>$fl['choices'][0]['choice_ref']]),
        $fresh,$now,$transport,$catalog,$save,$clock);
    $pairRecord=AnyTourTourvisorSelectedTourHandoff::freeze($selected['selector'],$fresh,$now,$clock);
    tvg_ok($pairRecord['status']==='ready' && $pairRecord['record']['observation']['trip']['departure']===$calendarDay
        && $pairRecord['record']['observation']['price']['amount']==='209867' && $physical===2,
        'same calendar pair is capturable and selection stays local');
    unset($fresh['tourvisor_context']['offers'][$ref]['operations']['tv_flights'],$fresh['tourvisor_context']['offers'][$ref]['pairs']);
    $badPair=$nativePair;$badPair['backward'][0]['departure']['date']=$calendarDay;
    tvg_denied(function()use(&$fresh,$request,$ref,$now,$badPair,$catalog,$save,$clock){
        AnyTourTourvisorPreviewGateway::handle($request('tv_flights',['offer_ref'=>$ref]),$fresh,$now,
            static fn():array=>['flights'=>[$badPair]],$catalog,$save,$clock);
    },'tv_pair_mismatch');
}
foreach (['2027-02-29','31.02.2027','2027-02-30','1.01.2027','2027-1-01',null,[],true] as $invalidDate) {
    $fresh=$resultsSession;$fresh['tourvisor_context']['offers'][$ref]['date']=$invalidDate;
    $badTour=$tour;$badTour['date']=$invalidDate;
    tvg_denied(function()use(&$fresh,$request,$ref,$now,$badTour,$catalog,$save,$clock){
        AnyTourTourvisorPreviewGateway::handle($request('tv_tour',['offer_ref'=>$ref]),$fresh,$now,
            static fn():array=>$badTour,$catalog,$save,$clock);
    },'tv_tour_mismatch');
    tvg_ok(empty($fresh['tourvisor_handoff_records']),'even matching invalid/missing dates cannot mint a record');
}
$fresh=$resultsSession;$wrongDayTour=$tour;$wrongDayTour['date']=(new DateTimeImmutable($day))->modify('+1 day')->format('d.m.Y');
tvg_denied(function()use(&$fresh,$request,$ref,$now,$wrongDayTour,$catalog,$save,$clock){
    AnyTourTourvisorPreviewGateway::handle($request('tv_tour',['offer_ref'=>$ref]),$fresh,$now,
        static fn():array=>$wrongDayTour,$catalog,$save,$clock);
},'tv_tour_mismatch');
// Real PHP session write/unlock/read window; deterministic independent-provider update.
final class TVCheckpointSessionFixture implements SessionHandlerInterface {
    public string $data=''; public int $reads=0; public int $writes=0;
    public function __construct(public string $mode) {}
    public function open(string $path,string $name):bool{return true;}
    public function close():bool{return true;}
    public function destroy(string $id):bool{return true;}
    public function gc(int $max):int|false{return 0;}
    public function write(string $id,string $data):bool {
        ++$this->writes;if($this->mode==='write_fail')throw new RuntimeException('fixture_write_failed');
        $this->data=$data;return true;
    }
    public function read(string $id):string {
        ++$this->reads;if($this->reads!==2)return $this->data;
        if($this->mode==='read_fail')throw new RuntimeException('fixture_read_failed');
        $v=unserialize($this->data,['allowed_classes'=>false]);
        if($this->mode==='foreign_context')$v['offer_context']=['concurrent'=>'ANEX'];
        elseif($this->mode==='foreign_generation')$v['anex_context_generation']=23;
        elseif($this->mode==='foreign_offer')$v['anex_offer_contexts']['new']=['concurrent'=>'ANEX'];
        elseif($this->mode==='tv_generation')++$v['tourvisor_context_generation'];
        elseif($this->mode==='tv_request'){$key=array_key_first($v['tourvisor_requests']);$v['tourvisor_requests'][$key]['fingerprint']='changed';}
        elseif($this->mode==='csrf')$v['search3_handoff_csrf']=str_repeat('d',64);
        elseif($this->mode==='producer')$v['search3_messenger_handoffs']['new']=['pending'=>'changed'];
        elseif($this->mode==='other')$v['unrelated_private_key']='changed';
        elseif($this->mode==='nonstock')$v['offer_context']=['concurrent'=>'ANEX'];
        return serialize($v);
    }
}
// Repeated full listings are only discarded after a validated new generation.
// This is a native gateway fixture; no direct invocation of the retirement helper.
$retiring=$session;$retireCalls=0;$retireSaves=0;$retireDisk=$retiring;$retireRid='';
$retireSave=static function(array &$s)use(&$retireDisk,&$retireSaves):void{$retireDisk=$s;++$retireSaves;};
$retireListing=[['id'=>1006,'description'=>str_repeat('r',440000),'tours'=>[$listing]]];
$retireTransport=static function(string $path,array $query,array &$s)
    use(&$retireCalls,&$retireDisk,&$retireRid,$retireListing):array {
    tvg_ok(($retireDisk['tourvisor_requests'][$retireRid]['state'] ?? null)==='unknown','retirement uses the same durable UNKNOWN before dispatch');
    ++$retireCalls;return $path==='/tours/search'?['searchId'=>92]:$retireListing;
};
$retireIds=[$resultsRequest['request_id']];$oldResults=[];
for($i=0;$i<12;++$i){
    $read=$request('tv_results');$retireRid=$read['request_id'];$retireIds[]=$retireRid;$oldResults[$retireRid]=$read;
    AnyTourTourvisorPreviewGateway::handle($read,$retiring,$now,$retireTransport,$catalog,$retireSave,$clock);
}
$retireUnknown=md5('retire-unknown');$retiring['tourvisor_requests'][$retireUnknown]=[
    'fingerprint'=>str_repeat('f',64),'generation'=>1,'state'=>'unknown'];
$ambiguous=$retiring['tourvisor_requests'][$retireIds[1]];
$ambiguous['reply']['data']=[];
foreach(['extra-receipt','extra-envelope','wrong-provider','wrong-generation','wrong-offers','future-generation','expired-status','unknown-body'] as $kind){
    $v=$ambiguous;
    if($kind==='extra-receipt')$v['private_evidence']=['keep'=>true];
    if($kind==='extra-envelope')$v['reply']['selector']=['keep'=>'canonical'];
    if($kind==='wrong-provider')$v['reply']['provider']='other';
    if($kind==='wrong-generation')$v['reply']['generation']=2;
    if($kind==='wrong-offers')$v['reply']['offers'][0]['offer_ref']='ambiguous';
    if($kind==='future-generation'){$v['generation']=2;$v['reply']['generation']=2;}
    if($kind==='expired-status'){unset($v['reply']['offers']);$v['reply']['data']=['progress'=>100];$v['expires_at']=$now-1;}
    if($kind==='unknown-body')$v['state']='unknown';
    $retiring['tourvisor_requests'][md5('retire-'.$kind)]=$v;
}
$retireBefore=$retiring;$beforeBytes=strlen(json_encode([$retiring['tourvisor_context'],$retiring['tourvisor_requests']],JSON_THROW_ON_ERROR));
$nextSearch=$request('tv_search',['params'=>$params]);$retireRid=$nextSearch['request_id'];
$nextReply=AnyTourTourvisorPreviewGateway::handle($nextSearch,$retiring,$now,$retireTransport,$catalog,$retireSave,$clock);
$afterBytes=strlen(json_encode([$retiring['tourvisor_context'],$retiring['tourvisor_requests']],JSON_THROW_ON_ERROR));
tvg_ok($beforeBytes>5000000 && $afterBytes<50000 && $retireCalls===13 && $retireSaves===26,
    'twelve actual full listing replies retire at one ordinary new search without extra calls or checkpoints');
tvg_ok(count($retiring['tourvisor_requests'])===count($retireBefore['tourvisor_requests'])+1
    && array_diff_key($retiring,['tourvisor_context'=>true,'tourvisor_context_generation'=>true,'tourvisor_requests'=>true])
        ===array_diff_key($retireBefore,['tourvisor_context'=>true,'tourvisor_context_generation'=>true,'tourvisor_requests'=>true]),
    'all IDs and selected records, prepared producer, CSRF and foreign state retained');
foreach($retireBefore['tourvisor_requests'] as $id=>$before){
    $after=$retiring['tourvisor_requests'][$id];
    if(in_array($id,$retireIds,true)){
        $encoded=json_encode($before['reply'],JSON_THROW_ON_ERROR);unset($before['reply']);
        $proof=$after['reply_retired'];unset($after['reply_retired']);
        tvg_ok($after===$before && $proof===['reason'=>'previous-generation-results','sha256'=>hash('sha256',$encoded),'bytes'=>strlen($encoded)],
            'retired body has exact private JSON size/digest and unchanged terminal identity/deadline');
    }else tvg_ok($after===$before,'UNKNOWN/ambiguous/status/tour/flight/selection replies kept byte-exact');
}
tvg_ok($retireDisk===$retiring && AnyTourTourvisorPreviewGateway::handle($nextSearch,$retiring,$now,$retireTransport,$catalog,$retireSave,$clock)===$nextReply
    && $retireCalls===13 && $retireSaves===26,'retirement and new healthy cache durable; cached response adds no work');
foreach([$now-100,$now+901] as $replayAt){
    $oldRequest=$oldResults[$retireIds[1]];$before=$retiring;
    tvg_denied(function()use($oldRequest,&$retiring,$replayAt,$retireTransport,$catalog,$retireSave){
        AnyTourTourvisorPreviewGateway::handle($oldRequest,$retiring,$replayAt,$retireTransport,$catalog,$retireSave,static fn():int=>$replayAt);
    },'tv_stale_context');
    tvg_ok($retiring===$before && $retireCalls===13 && $retireSaves===26,'clock rollback/expiry cannot revive retired receipt or dispatch');
}
$restored=$retiring;$restored['tourvisor_context']=$retireBefore['tourvisor_context'];$restored['tourvisor_context_generation']=1;
tvg_denied(function()use($oldRequest,&$restored,$now,$retireTransport,$catalog,$retireSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($oldRequest,$restored,$now,$retireTransport,$catalog,$retireSave,$clock);
},'tv_stale_context');
tvg_ok($retireCalls===13 && $retireSaves===26,'retirement marker is irreversible even if context is accidentally restored');
$invalid=$retireBefore;$badSearch=$request('tv_search',['params'=>$params]);$badSearch['params']['unsupported']='invalid';
try{AnyTourTourvisorPreviewGateway::handle($badSearch,$invalid,$now,$retireTransport,$catalog,$retireSave,$clock);throw new RuntimeException('expected invalid party');}
catch(InvalidArgumentException $e){tvg_ok($e->getMessage()==='tv_invalid_request' && $invalid===$retireBefore
    && $retireCalls===13 && $retireSaves===26,'invalid new-search input never retires any body');}
$failed=$retireBefore;$failedSearch=$request('tv_search',['params'=>$params]);$failedSaves=0;
try{AnyTourTourvisorPreviewGateway::handle($failedSearch,$failed,$now,$retireTransport,$catalog,
    static function(array &$s)use(&$failedSaves):void{++$failedSaves;throw new RuntimeException('fixture_retirement_save_failed');},$clock);
    throw new RuntimeException('expected failed checkpoint');}
catch(RuntimeException $e){tvg_ok($e->getMessage()==='fixture_retirement_save_failed' && $retireCalls===13 && $failedSaves===1,'failed retirement checkpoint cannot dispatch');}
tvg_denied(function()use($failedSearch,&$failed,$now,$retireTransport,$catalog,$retireSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($failedSearch,$failed,$now,$retireTransport,$catalog,$retireSave,$clock);
},'tv_operation_unknown');
// A successful old in-flight listing cannot resurrect its body after a new search.
$lateListing=$retireBefore;$lateRead=$request('tv_results');$lateReadId=$lateRead['request_id'];$newLateReply=null;
$lateListingTransport=static function(string $path,array $query,array &$s)
    use($nextSearch,&$newLateReply,&$retireRid,$now,$retireTransport,$catalog,$retireSave,$clock,$retireListing):array {
    $retireRid=$nextSearch['request_id'];
    $newLateReply=AnyTourTourvisorPreviewGateway::handle($nextSearch,$s,$now,$retireTransport,$catalog,$retireSave,$clock);
    return $retireListing;
};
tvg_denied(function()use($lateRead,&$lateListing,$now,$lateListingTransport,$catalog,$retireSave,$clock){
    AnyTourTourvisorPreviewGateway::handle($lateRead,$lateListing,$now,$lateListingTransport,$catalog,$retireSave,$clock);
},'tv_context_changed');
tvg_ok($lateListing['tourvisor_context_generation']===2 && $lateListing['tourvisor_requests'][$lateReadId]['state']==='unknown'
    && isset($lateListing['tourvisor_requests'][$retireIds[1]]['reply_retired'])
    && $lateListing['tourvisor_requests'][$retireUnknown]===$retireBefore['tourvisor_requests'][$retireUnknown],
    'late response keeps both old UNKNOWN markers and the newer retirement');
tvg_ok(AnyTourTourvisorPreviewGateway::handle($nextSearch,$lateListing,$now,$retireTransport,$catalog,$retireSave,$clock)===$newLateReply
    && $retireCalls===14,'new cache survives late old response with no second dispatch');
ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');ini_set('session.serialize_handler','php_serialize');
foreach(['foreign_context','foreign_generation','foreign_offer','tv_generation','tv_request','csrf','producer','other','write_fail','read_fail','identity','nonstock'] as $mode){
    $handler=new TVCheckpointSessionFixture($mode);
    $handler->data=serialize(['search3_handoff_csrf'=>str_repeat('c',64),'anex_context_generation'=>17,
        'anex_offer_contexts'=>['old'=>['keep'=>'original']],'offer_context'=>['keep'=>'original'],
        'search3_messenger_handoffs'=>['keep'=>['pending'=>'original']]]);
    session_set_save_handler($handler,true);session_id(md5('fixture-'.$mode));session_start();
    $nativeRequest=$request('tv_search',['params'=>$params]);$nativeCalls=0;$error=null;$reply=null;
    $nativeTransport=static function()use(&$nativeCalls):array{++$nativeCalls;return ['searchId'=>91];};
    $nativeCheckpoint=$mode==='identity'?static function(array &$s):void{session_regenerate_id();throw new RuntimeException('handoff_session_changed');}
        :($mode==='nonstock'?static function(array &$s):void{AnyTourSearch3MessengerHandoff::checkpoint($s);}
        :[AnyTourSearch3MessengerHandoff::class,'checkpoint']);
    try{$reply=AnyTourTourvisorPreviewGateway::handle($nativeRequest,$_SESSION,$now,$nativeTransport,$catalog,$nativeCheckpoint,$clock);}
    catch(Throwable $caught){$error=$caught;}
    if(str_starts_with($mode,'foreign_')){
        tvg_ok($error===null && $reply['data']['searchId']===91 && $nativeCalls===1,'independent ANEX update does not abort own durable TV reservation '.$mode);
        tvg_ok($handler->writes===2 && $_SESSION['tourvisor_requests'][$nativeRequest['request_id']]['state']==='ready',
            'stock UNKNOWN and ready checkpoints each happen once');
        $persisted=unserialize($handler->data,['allowed_classes'=>false]);
        tvg_ok($_SESSION===$persisted && ($mode!=='foreign_context'||$_SESSION['offer_context']===['concurrent'=>'ANEX'])
            && ($mode!=='foreign_generation'||$_SESSION['anex_context_generation']===23)
            && ($mode!=='foreign_offer'||isset($_SESSION['anex_offer_contexts']['new'])),'foreign concurrent state survives without rollback');
        tvg_ok(AnyTourTourvisorPreviewGateway::handle($nativeRequest,$_SESSION,$now,$nativeTransport,$catalog,$nativeCheckpoint,$clock)===$reply
            && $nativeCalls===1 && $handler->writes===2,'same ready operation after foreign update is not dispatched again');
    } else {
        tvg_ok($error!==null && $nativeCalls===0,'own/shared/unknown drift or write/read/session failure still blocks before dispatch '.$mode);
        if(!in_array($mode,['write_fail','identity'],true))tvg_ok(
            unserialize($handler->data,['allowed_classes'=>false])['tourvisor_requests'][$nativeRequest['request_id']]['state']==='unknown',
            'failed durability check retains the original stored UNKNOWN marker');
    }
    if(session_status()===PHP_SESSION_ACTIVE)session_abort();
}
echo 'TV private session gateway: '.$GLOBALS['tv_checks']." checks PASS; supplier/bot network=0, DB writes=0.\n";
echo json_encode(['synthetic_listing_retirement'=>['before_bytes'=>$beforeBytes,'after_bytes'=>$afterBytes,
    'retired_bodies'=>count($retireIds),'retained_receipt_ids'=>count($retiring['tourvisor_requests']),
    'supplier_http_calls'=>0]],JSON_THROW_ON_ERROR)."\n";
}
