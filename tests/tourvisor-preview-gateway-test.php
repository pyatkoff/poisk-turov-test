<?php
declare(strict_types=1);
namespace AnyTour {
    // Count the real shared transport's cURL calls without touching the network.
    function curl_init($url) { $GLOBALS['tv_wire']['url']=$url; ++$GLOBALS['tv_wire']['calls']; return new \stdClass(); }
    function curl_setopt_array($ch,$options) { $GLOBALS['tv_wire']['options']=$options; return true; }
    function curl_exec($ch) { return $GLOBALS['tv_wire']['body']; }
    function curl_errno($ch) { return $GLOBALS['tv_wire']['errno'] ?? 0; }
    function curl_getinfo($ch,$option) { return $GLOBALS['tv_wire']['status']; }
    function curl_close($ch) { $GLOBALS['tv_wire']['closed']=true; }
}
namespace {
require_once __DIR__.'/../app/integrations/tourvisor-preview-gateway.php';
require_once __DIR__.'/../app/integrations/search3-messenger-handoff.php';
function tvg_ok(bool $ok,string $label): void {
    $GLOBALS['tv_checks']=($GLOBALS['tv_checks'] ?? 0)+1;
    if (!$ok) throw new RuntimeException('FAIL '.$label);
}
function tvg_denied(callable $call,string $code): void {
    try { $call(); } catch (InvalidArgumentException $e) { tvg_ok($e->getMessage()===$code,$code.' actual '.$e->getMessage()); return; }
    throw new RuntimeException('expected '.$code);
}

$GLOBALS['tv_wire']=['calls'=>0,'body'=>'{"searchId":91}','status'=>200];
\AnyTour\TourvisorInteractiveClient::get('/tours/search',['childs'=>[0,0,8],'onlyDirect'=>false],'fixture-token');
$wire=$GLOBALS['tv_wire'];
tvg_ok($wire['calls']===1 && $wire['url']==='https://api.tourvisor.ru/search/api/v1/tours/search?childs=0&childs=0&childs=8&onlyDirect=false','same fixed destination and repeated query');
tvg_ok($wire['options'][CURLOPT_TIMEOUT]===45 && $wire['options'][CURLOPT_CONNECTTIMEOUT]===10 && $wire['closed'],'same interactive timing and close');
foreach ([[502,0,'Tourvisor request failed'],[200,28,'Tourvisor connection error']] as [$status,$errno,$message]) {
    $GLOBALS['tv_wire']=['calls'=>0,'body'=>'{}','status'=>$status,'errno'=>$errno];
    try { \AnyTour\TourvisorInteractiveClient::get('/tours/fixture',[],'fixture-token'); }
    catch (\AnyTour\TourvisorInteractiveFailure $e) { tvg_ok($e->response['error']===$message && $e->httpStatus===502 && $GLOBALS['tv_wire']['calls']===1,'no retry on supplier failure'); }
}
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
$pairB=$pair; $pairB['backward'][0]['number']='SU425'; $pairB['price']['value']='233100.50';
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
$results=$run($request('tv_results')); $ref=$results['offers'][0]['offer_ref'];
tvg_ok(count($results['offers'])===1 && $results['offers'][0]['local_hotel_id']===1006,'server result membership with catalogue identity');
$checkedRequest=$request('tv_tour',['offer_ref'=>$ref]); $checked=$run($checkedRequest);
tvg_ok($checked['data']['fuelCharge']===13789 && $checked['data']['price']==='209867','supplier money and fuel unchanged');
$frozen=AnyTourTourvisorSelectedTourHandoff::freeze($checked['selector'],$session,$now,$clock);
tvg_ok($frozen['status']==='ready' && $frozen['record']['observation']['trip']['childAges']===[0,0,8]
    && $frozen['record']['observation']['price']['amount']==='209867','checked whole party captured with no fuel or infant arithmetic');
tvg_ok($frozen['record']['observation']['flights']['state']==='manager-deferred','manager path is independently ready');
$before=count($calls); $run($checkedRequest); tvg_ok(count($calls)===$before,'same checked tour response reused');
tvg_denied(fn()=>$run($request('tv_tour',['offer_ref'=>$ref])),'tv_operation_already_attempted');
$flights=$run($request('tv_flights',['offer_ref'=>$ref]));
tvg_ok(array_column($flights['choices'],'index')===[1,2],'opaque pair refs retain original returned indexes including skipped placeholder');
$selected=$run($request('tv_select',['offer_ref'=>$ref,'choice_ref'=>$flights['choices'][1]['choice_ref']]));
$pairFrozen=AnyTourTourvisorSelectedTourHandoff::freeze($selected['selector'],$session,$now,$clock);
tvg_ok($pairFrozen['record']['observation']['price']['amount']==='233100.50'
    && str_contains($pairFrozen['record']['observation']['flights']['inward'][0]['label'],'SU425'),'selected pair keeps its own total and legs');
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
echo 'TV private session gateway: '.$GLOBALS['tv_checks']." checks PASS; supplier/bot network=0, DB writes=0.\n";
}
