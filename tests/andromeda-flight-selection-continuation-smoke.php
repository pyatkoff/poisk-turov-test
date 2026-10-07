<?php
declare(strict_types=1);

final class AnyTourAndromedaClient {
    public function __construct(private array $package) {}
    public function package(string $id): array {
        if ($id !== 'opaque-claiminc') throw new RuntimeException('BAD_ID');
        return $this->package;
    }
    public function privateSession():array{return ['sid'=>'SID_gateway_initial'];}
}
require_once __DIR__.'/../app/integrations/andromeda-claim-actions.php';
require_once __DIR__.'/../app/integrations/andromeda-selected-quote.php';

$money=static fn(string $amount):array => [['buyerClaimMoney'=>[['net'=>$amount,'currency'=>'RUB']]]];
$package=[
    'version'=>'1.01',
    'claimDocument'=>[0=>[
        'catalogKey'=>'catalog-private','condition'=>'ccOffer','freightExternal'=>1,
        'buyerMoneys'=>$money('73092'),'moneys'=>[],'transports'=>[null],
    ]],
    'variants'=>[], 'groups'=>[],
];
$resolved=[
    'supplier_offer_id'=>'opaque-claiminc',
    'offer'=>[
        'local_hotel_id'=>67477,'operator'=>['id'=>'5','name'=>'ANEX'],
        'price'=>['amount'=>'73092','currency'=>'RUB'],
    ],
];
$details=static function(string $number,string $airline,string $airlineName,string $from,string $to,
    string $depart,string $arrive,string $bagage,string $bagageNote,string $duration):array{
    return [['detail'=>[[
        'external'=>'1','flight_number'=>$number,'marketing_airline'=>$airline,
        'full_marketing_airline'=>$airlineName,'departureAirportCode'=>$from,'arrivalAirportCode'=>$to,
        'depart_datetime'=>$depart,'arrival_datetime'=>$arrive,'bagage'=>$bagage,'bagage_note'=>$bagageNote,
        'SegmentDuration'=>$duration,'requestid'=>'detail-request-private','offer_id'=>'detail-offer-private',
        'externalOfferId'=>'detail-external-offer-private',
    ]]]];
};
$getFlights=$package;
$getFlights['groups']=[['group'=>[
    ['id'=>'g0','required'=>'true','oneItem'=>'true'],
    ['id'=>'g1','required'=>'true','oneItem'=>'true'],
]]];
$getFlights['variants']=[['transports'=>[['transport'=>[
    ['uid'=>'supplier_out_a','groupId'=>'g0','direction'=>'0','type'=>'ttAvia','name'=>'OUT A','datebeg'=>'2026-09-20','dateend'=>'2026-09-20',
        'details'=>$details('DP 994','DP','Pobeda','VKO','IST','2026-09-20T06:00:00','2026-09-20T11:10:00','0PC','No baggage','05:10')],
    ['uid'=>'supplier_out_b','groupId'=>'g0','direction'=>'0','type'=>'ttAvia','name'=>'OUT B','datebeg'=>'2026-09-20','dateend'=>'2026-09-20',
        'details'=>$details('DP 995','DP','Pobeda','VKO','IST','2026-09-20T07:35:00','2026-09-20T12:50:00','0PC','No baggage','05:15')],
    ['uid'=>'supplier_back_a','groupId'=>'g1','direction'=>'1','type'=>'ttAvia','name'=>'BACK A','datebeg'=>'2026-09-27','dateend'=>'2026-09-27',
        'details'=>$details('DP 996','DP','Pobeda','IST','VKO','2026-09-27T18:40:00','2026-09-27T23:25:00','0PC','No baggage','04:45')],
]]]]];

$initialCalls=[];$initialBudget=0;
$initialActions=new AnyTourAndromedaClaimActions('SID_initial',static function()use(&$initialBudget){++$initialBudget;},
    static function(string $url,string $post)use(&$initialCalls,$getFlights):array{
        parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
        $initialCalls[]=$query['action']??null;
        if(($query['action']??null)!=='get_flights')throw new RuntimeException('INITIAL_UNEXPECTED_ACTION');
        return ['status'=>200,'body'=>json_encode($getFlights,JSON_THROW_ON_ERROR)];
    });
$context=str_repeat('a',64);
$retained=null;
$initial=AnyTourAndromedaSelectedQuote::run($resolved,new AnyTourAndromedaClient($package),$initialActions,
    static function(array $claim,array $options)use(&$retained,$context):array{
        $counter=0;
        $built=AnyTourAndromedaFlightSelection::buildState($claim,$options,$context,
            static function(string $direction,int $index,array $item)use(&$counter):string{
                ++$counter;
                return 'flight_'.str_pad(dechex($counter),32,'0',STR_PAD_LEFT);
            });
        $retained=$built['state'];
        return $built['refs'];
    });
if(($initial['state']??null)!=='flight_selection_required'||($initial['final_price_verified']??null)!==false)throw new RuntimeException('INITIAL_STATE');
if($initialCalls!==['get_flights']||$initialBudget!==1)throw new RuntimeException('INITIAL_CALLS');
if(!is_array($retained)||count($initial['flights']??[])!==3)throw new RuntimeException('RETAINED_STATE');
foreach($initial['flights'] as $flight){
    if(!is_string($flight['flight_ref']??null)||!preg_match('/^flight_[a-f0-9]{32}$/D',$flight['flight_ref']))throw new RuntimeException('PUBLIC_REF');
}
$outFacts=$initial['flights'][1]['flight_details']??null;
if(!is_array($outFacts)||($outFacts['source']??null)!=='andromeda_transport_detail'
    ||($outFacts['external_transport']??null)!==true||($outFacts['flight_number']??null)!=='DP 995'
    ||($outFacts['airline_code']??null)!=='DP'||($outFacts['airline_name']??null)!=='Pobeda'
    ||($outFacts['departure_airport_code']??null)!=='VKO'||($outFacts['arrival_airport_code']??null)!=='IST'
    ||($outFacts['departure_datetime']??null)!=='2026-09-20T07:35:00'||($outFacts['arrival_datetime']??null)!=='2026-09-20T12:50:00'
    ||($outFacts['duration']??null)!=='05:15'||($outFacts['baggage_code']??null)!=='0PC'
    ||($outFacts['baggage_note']??null)!=='No baggage')throw new RuntimeException('PUBLIC_FLIGHT_FACTS');
$encoded=json_encode($initial,JSON_THROW_ON_ERROR);
foreach(['supplier_out_a','supplier_out_b','supplier_back_a','SID_initial','catalog-private',
    'detail-request-private','detail-offer-private','detail-external-offer-private'] as $secret){
    if(str_contains($encoded,$secret))throw new RuntimeException('PRIVATE_LEAK_'.$secret);
}

$selection=[
    'provider'=>'andromeda',
    'outbound_ref'=>$initial['flights'][1]['flight_ref'],
    'return_ref'=>$initial['flights'][2]['flight_ref'],
];
$chosen=AnyTourAndromedaFlightSelection::select($retained,$context,$selection);
if(($chosen['selected']['0']['uid']??null)!=='supplier_out_b'||($chosen['selected']['1']['uid']??null)!=='supplier_back_a')throw new RuntimeException('SELECTION_BINDING');

$supplierBackDefault=[
    'uid'=>'supplier_back_default','groupId'=>'g1','direction'=>'1','type'=>'ttAvia',
    'name'=>'BACK DEFAULT','datebeg'=>'2026-09-27','dateend'=>'2026-09-27',
];
$continuedCalls=[];$continuedBudget=0;
$continuedActions=new AnyTourAndromedaClaimActions('SID_continue',static function()use(&$continuedBudget){++$continuedBudget;},
    static function(string $url,string $post)use(&$continuedCalls,$money,$supplierBackDefault):array{
        parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
        $action=$query['action']??null;$continuedCalls[]=$action;
        parse_str($post,$form);
        $claim=json_decode($form['claim']??'',true,64,JSON_THROW_ON_ERROR);
        if($action==='changeservice'){
            $newUid=$query['NEW_UID']??null;
            if($newUid==='supplier_out_b'){
                if(isset($query['OLD_UID']))throw new RuntimeException('OUTBOUND_CHANGE_SHAPE');
                $claim['claimDocument'][0]['transports'][0]['transport'][]=$supplierBackDefault;
            }elseif($newUid==='supplier_back_a'){
                if(($query['OLD_UID']??null)!=='supplier_back_default')throw new RuntimeException('RETURN_CHANGE_SHAPE');
                $uids=[];
                foreach(($claim['claimDocument'][0]['transports']??[]) as $block){
                    if(!is_array($block)||!is_array($block['transport']??null))continue;
                    foreach($block['transport'] as $transport){
                        if(is_array($transport)&&is_string($transport['uid']??null))$uids[]=$transport['uid'];
                    }
                }
                if(!in_array('supplier_back_a',$uids,true)||in_array('supplier_back_default',$uids,true))throw new RuntimeException('RETURN_REPLACEMENT');
            }else{
                throw new RuntimeException('CHANGE_UID');
            }
            return ['status'=>200,'body'=>json_encode($claim,JSON_THROW_ON_ERROR)];
        }
        if($action==='calc'){
            $claim['claimDocument'][0]['buyerMoneys']=$money('81234');
            return ['status'=>200,'body'=>json_encode($claim,JSON_THROW_ON_ERROR)];
        }
        throw new RuntimeException('CONTINUATION_UNEXPECTED_ACTION');
    });
$final=AnyTourAndromedaSelectedQuote::continueWithFlights(
    $resolved,$chosen['claim'],$chosen['selected'],$continuedActions);
if($continuedCalls!==['changeservice','changeservice','calc']||$continuedBudget!==3)throw new RuntimeException('CONTINUATION_CALLS');
if(($final['state']??null)!=='quote_verified'||($final['final_price_verified']??null)!==true
    ||($final['final_price']['amount']??null)!=='81234'||($final['booking_enabled']??null)!==false)throw new RuntimeException('FINAL_QUOTE');
if(($final['flights'][0]['flight_details']['flight_number']??null)!=='DP 995'
    ||($final['flights'][1]['flight_details']['flight_number']??null)!=='DP 996'
    ||($final['flights'][0]['flight_details']['external_transport']??null)!==true
    ||($final['flights'][1]['flight_details']['external_transport']??null)!==true)throw new RuntimeException('FINAL_FLIGHT_FACTS');
$finalEncoded=json_encode($final,JSON_THROW_ON_ERROR);
foreach(['supplier_out_a','supplier_out_b','supplier_back_a','supplier_back_default','SID_continue','catalog-private',
    'detail-request-private','detail-offer-private','detail-external-offer-private'] as $secret){
    if(str_contains($finalEncoded,$secret))throw new RuntimeException('FINAL_PRIVATE_LEAK_'.$secret);
}

foreach([
    [$retained,str_repeat('b',64),$selection],
    [$retained,$context,['provider'=>'andromeda','outbound_ref'=>$selection['return_ref'],'return_ref'=>$selection['outbound_ref']]],
    [$retained,$context,['provider'=>'andromeda','outbound_ref'=>'supplier_out_b','return_ref'=>$selection['return_ref']]],
] as [$state,$ctx,$bad]){
    try{
        AnyTourAndromedaFlightSelection::select($state,$ctx,$bad);
        throw new RuntimeException('BAD_SELECTION_ACCEPTED');
    }catch(InvalidArgumentException|RuntimeException $e){
        if($e->getMessage()==='BAD_SELECTION_ACCEPTED')throw $e;
    }
}

// Execute the gateway continuation, including its real durable reservation and
// replay functions. Only offer resolution, supplier transport and fixture-file
// persistence are local seams; all claims and prices remain synthetic.
require_once __DIR__.'/../app/integrations/andromeda-quote-attempt-state.php';
$gatewaySource=file_get_contents(__DIR__.'/../v2/api-andromeda-quote-preview.php');
if(!is_string($gatewaySource))throw new RuntimeException('GATEWAY_SOURCE_UNREADABLE');
$extractGateway=static function(string $name)use($gatewaySource):string{
    $start=strpos($gatewaySource,'function '.$name.'(');
    if($start===false)throw new RuntimeException('GATEWAY_FUNCTION_MISSING');
    $open=strpos($gatewaySource,'{',$start);$depth=0;
    for($i=$open;$i<strlen($gatewaySource);++$i){
        if($gatewaySource[$i]==='{')++$depth;
        elseif($gatewaySource[$i]==='}'&&--$depth===0)return substr($gatewaySource,$start,$i-$start+1);
    }
    throw new RuntimeException('GATEWAY_FUNCTION_UNCLOSED');
};
foreach(['anytour_andromeda_quote_with_expiry','anytour_andromeda_quote_meta',
    'anytour_andromeda_quote_read','anytour_andromeda_quote_persist','anytour_andromeda_quote_reserve',
    'anytour_andromeda_quote_finish','anytour_andromeda_quote_unknown',
    'anytour_andromeda_quote_reprice_persist','anytour_andromeda_quote_reprice_projection',
    'anytour_andromeda_quote_reprice_spend','anytour_andromeda_quote_reprice_continue',
    'anytour_andromeda_quote_run','anytour_andromeda_quote_continue',
    'anytour_andromeda_quote_failure_category','anytour_andromeda_quote_supplier_failure'] as $name){
    eval($extractGateway($name));
}
$gatewayFixtureDirectory=sys_get_temp_dir().'/andromeda-ref-binding-'.bin2hex(random_bytes(6));
if(!mkdir($gatewayFixtureDirectory,0700)||!mkdir($gatewayFixtureDirectory.'/searches',0700))throw new RuntimeException('GATEWAY_FIXTURE_DIRECTORY');
$gatewayResolved=$resolved+[
    'context'=>['search_ref'=>str_repeat('c',64),'generation'=>1,'page'=>1,'offer_ref'=>'offer_'.str_repeat('d',64)],
    'criteria_sha256'=>str_repeat('e',64),'supplier_offer_sha256'=>str_repeat('f',64),
    'expires_at'=>time()+600,
];
$gatewayConfig=['catalog_path'=>$gatewayFixtureDirectory.'/catalog.json'];
$gatewayFactoryCalls=0;$gatewayCalls=[];$gatewayFailure=false;
$gatewayNewInitial=false;$gatewaySaveFailure=false;
function anytour_andromeda_quote_resolve(array $request,PDO $pdo,array $saved,array $config,string $session,array $listingPrices=[]):array{
    global $gatewayResolved;
    if(time()>=$gatewayResolved['expires_at'])throw new DomainException('offer_expired');
    return $gatewayResolved;
}
function anytour_andromeda_search3_save(string $path,array $data):void{
    global $gatewayFixtureDirectory,$gatewaySaveFailure;
    if(!str_starts_with($path,$gatewayFixtureDirectory.'/searches/'))throw new RuntimeException('GATEWAY_FIXTURE_WRITE_OUTSIDE');
    if(file_put_contents($path.'.tmp',json_encode($data,JSON_THROW_ON_ERROR))===false
        ||!rename($path.'.tmp',$path))throw new RuntimeException('GATEWAY_FIXTURE_WRITE');
    // Model a write that reaches disk but is not acknowledged to its caller.
    if($gatewaySaveFailure&&str_ends_with($path,'-quote-flight-context-v2.json')
        &&($data['state']['status']??null)==='ready'&&($data['state']['head_pair']??null)!==null){
        $gatewaySaveFailure=false;throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_FAILED');
    }
}
function anytour_andromeda_quote_supplier(array $config):array{
    global $gatewayFactoryCalls,$gatewayCalls,$gatewayFailure,$money,$gatewayNewInitial,$repriceInventory,$package;
    ++$gatewayFactoryCalls;
    if($gatewayNewInitial){
        return [new AnyTourAndromedaClient($package),new AnyTourAndromedaClaimActions('SID_gateway_initial',static function(){},
            static function(string $url,string $post)use(&$gatewayCalls,$repriceInventory):array{
                parse_str((string)parse_url($url,PHP_URL_QUERY),$query);$gatewayCalls[]=$query['action']??null;
                if(($query['action']??null)!=='get_flights')throw new RuntimeException('REPRICE_INITIAL_UNEXPECTED_ACTION');
                return ['status'=>200,'body'=>json_encode($repriceInventory,JSON_THROW_ON_ERROR)];
            })];
    }
    $actions=new AnyTourAndromedaClaimActions('SID_gateway_fixture',static function(){},
        static function(string $url,string $post)use(&$gatewayCalls,&$gatewayFailure,$money):array{
            parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
            $action=$query['action']??null;$gatewayCalls[]=$action;
            parse_str($post,$form);$claim=json_decode($form['claim']??'',true,64,JSON_THROW_ON_ERROR);
            if($action==='calc'){
                $claim['claimDocument'][0]['buyerMoneys']=$money('81234');
                // Provider row order and descriptive updates must not swap opaque refs.
                $rows=&$claim['claimDocument'][0]['transports'][0]['transport'];
                $rows[0]['name']='OUT B updated schedule';$rows=array_reverse($rows);
                if($gatewayFailure)$rows[0]['uid']='supplier_back_different';
            }elseif($action!=='changeservice')throw new RuntimeException('GATEWAY_UNEXPECTED_ACTION');
            return ['status'=>200,'body'=>json_encode($claim,JSON_THROW_ON_ERROR)];
        });
    return [null,$actions];
}
function anytour_andromeda_search3_budget(string $directory):void{
    global $gatewayFixtureDirectory,$repriceBudget;
    if($directory!==$gatewayFixtureDirectory)throw new RuntimeException('REPRICE_BUDGET_DIRECTORY');
    ++$repriceBudget;
}
// Only the supplier transport/clock are seams. Reservation, durable pacing,
// latest-head selection, public projection and commit are extracted production.
function anytour_andromeda_quote_reprice_actions(array $state,string $path,string $token,array $config):AnyTourAndromedaClaimActions{
    global $repriceClockMs,$repriceCalls,$repriceExpectedHead,$repriceNextHead,$repriceAmount,
        $repriceFailure,$repriceReentry,$repriceExpire,$gatewayResolved,$money;
    return new AnyTourAndromedaClaimActions($state['session_sid'],
        static function()use($state,$path,$token,$config,&$repriceClockMs):void{
            $repriceClockMs+=1100;
            anytour_andromeda_quote_reprice_spend($path,$state['context_sha256'],$token,dirname($config['catalog_path']),$repriceClockMs);
            AnyTourAndromedaFlightRepricingState::current(anytour_andromeda_quote_read($path,3000000)['state'],$state['context_sha256'],time());
        },
        static function(string $url,string $post)use(&$repriceCalls,&$repriceExpectedHead,&$repriceNextHead,&$repriceAmount,
            &$repriceFailure,&$repriceReentry,&$repriceExpire,&$gatewayResolved,$money):array{
            parse_str((string)parse_url($url,PHP_URL_QUERY),$query);parse_str($post,$form);
            $claim=json_decode($form['claim']??'',true,64,JSON_THROW_ON_ERROR);$action=$query['action']??null;
            $repriceCalls[]=['action'=>$action,'sid'=>$query['sid']??null,'old'=>$query['OLD_UID']??null,
                'new'=>$query['NEW_UID']??null,'head'=>$claim['fixture_head']??null];
            if(($query['sid']??null)!=='SID_gateway_initial'||($claim['fixture_head']??null)!==$repriceExpectedHead)throw new RuntimeException('REPRICE_SID_OR_LATEST_HEAD_LOST');
            if(is_callable($repriceReentry)){$reentry=$repriceReentry;$repriceReentry=null;$reentry();}
            if($action==='calc'){
                $claim['fixture_head']=$repriceNextHead;$claim['variants']=[];
                $claim['claimDocument'][0]['buyerMoneys']=$money($repriceAmount);
                if($repriceFailure==='uid')$claim['claimDocument'][0]['transports'][0]['transport'][0]['uid']='supplier_wrong';
                if($repriceFailure==='currency')$claim['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][0]['currency']='USD';
                if($repriceFailure==='rejection')return ['status'=>200,'body'=>json_encode(['error'=>['code'=>'SID_EXPIRED','message'=>'private supplier text']],JSON_THROW_ON_ERROR)];
            }elseif($action!=='changeservice')throw new RuntimeException('REPRICE_UNEXPECTED_ACTION');
            if($repriceExpire!==null&&$action===$repriceExpire)usleep(2100000);
            return ['status'=>200,'body'=>json_encode($claim,JSON_THROW_ON_ERROR)];
        });
}
$gatewayPdo=new class extends PDO{public function __construct(){}};
$seedGateway=static function()use(&$gatewayResolved,$gatewayConfig,$retained,$initial):array{
    $meta=anytour_andromeda_quote_meta($gatewayResolved,$gatewayConfig);
    $state=$retained;$state['context_sha256']=$meta['context_sha256'];
    anytour_andromeda_search3_save($meta['prefix'].'-quote-flight-state-v1.json',['state'=>$state]);
    $attempt=AnyTourAndromedaQuoteAttemptState::reserve($meta['context_sha256'],hash('sha256','andromeda-selected-quote-v1'));
    anytour_andromeda_search3_save($meta['prefix'].'-quote-v1.json',['state'=>AnyTourAndromedaQuoteAttemptState::completed(
        $attempt,anytour_andromeda_quote_with_expiry($initial,$gatewayResolved))]);
    return $meta;
};
$gatewayRequest=['flight_selection'=>$selection];
try{
    $gatewayMeta=$seedGateway();
    $gatewayFinal=anytour_andromeda_quote_continue($gatewayRequest,$gatewayPdo,[],$gatewayConfig,'fixture-session');
    if(($gatewayFinal['flights'][0]['direction']??null)!=='0'
        ||($gatewayFinal['flights'][0]['flight_ref']??null)!==$selection['outbound_ref']
        ||($gatewayFinal['flights'][1]['direction']??null)!=='1'
        ||($gatewayFinal['flights'][1]['flight_ref']??null)!==$selection['return_ref'])throw new RuntimeException('VERIFIED_PAIR_REFS_LOST_OR_SWAPPED');
    if(($gatewayFinal['final_price']??null)!==['amount'=>'81234','currency'=>'RUB']
        ||($gatewayFinal['package_price']??null)!==['amount'=>'73092','currency'=>'RUB']
        ||($gatewayFinal['search_price']??null)!==['amount'=>'73092','currency'=>'RUB']
        ||($gatewayFinal['expires_at']??null)!==$gatewayResolved['expires_at']
        ||($gatewayFinal['flights'][0]['name']??null)!=='OUT B updated schedule')throw new RuntimeException('GATEWAY_MONEY_EXPIRY_OR_CURRENT_FACT_CHANGED');
    if($gatewayFactoryCalls!==1||$gatewayCalls!==['changeservice','changeservice','calc'])throw new RuntimeException('GATEWAY_CONTINUATION_BUDGET_CHANGED');
    $checkpoint=$gatewayMeta['prefix'].'-quote-flight-v1.json';
    $completedGateway=anytour_andromeda_quote_read($checkpoint,131072)['state'];
    if($completedGateway['status']!=='completed'||$completedGateway['result']!==$gatewayFinal)throw new RuntimeException('VERIFIED_REFS_NOT_PERSISTED');
    $gatewayReplay=anytour_andromeda_quote_continue($gatewayRequest,$gatewayPdo,[],$gatewayConfig,'fixture-session');
    if($gatewayReplay!==$gatewayFinal||$gatewayFactoryCalls!==1||count($gatewayCalls)!==3)throw new RuntimeException('PAIR_REPLAY_LOST_REFS_OR_SPENT');
    // Older completed receipts remain local and valid until their existing deadline.
    $legacyCompleted=$completedGateway;
    foreach($legacyCompleted['result']['flights'] as &$flight)unset($flight['flight_ref']);
    unset($flight);
    anytour_andromeda_search3_save($checkpoint,['state'=>$legacyCompleted]);
    $legacyReplay=anytour_andromeda_quote_continue($gatewayRequest,$gatewayPdo,[],$gatewayConfig,'fixture-session');
    if($legacyReplay!==$legacyCompleted['result']||$gatewayFactoryCalls!==1||count($gatewayCalls)!==3)throw new RuntimeException('LEGACY_PAIR_REOPENED_OR_CHANGED');
    $otherSelection=$selection;$otherSelection['outbound_ref']=$initial['flights'][0]['flight_ref'];
    try{
        anytour_andromeda_quote_continue(['flight_selection'=>$otherSelection],$gatewayPdo,[],$gatewayConfig,'fixture-session');
        throw new RuntimeException('SECOND_PAIR_ACCEPTED');
    }catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_QUOTE_REPLAY_REFUSED')throw $e;}
    if($gatewayFactoryCalls!==1||count($gatewayCalls)!==3)throw new RuntimeException('SECOND_PAIR_SPENT');
    $gatewayExpires=$gatewayResolved['expires_at'];$gatewayResolved['expires_at']=time();
    try{
        anytour_andromeda_quote_continue($gatewayRequest,$gatewayPdo,[],$gatewayConfig,'fixture-session');
        throw new RuntimeException('EXPIRED_PAIR_ACCEPTED');
    }catch(DomainException $e){if($e->getMessage()!=='offer_expired')throw $e;}
    $gatewayResolved['expires_at']=$gatewayExpires;
    if($gatewayFactoryCalls!==1||count($gatewayCalls)!==3)throw new RuntimeException('EXPIRED_PAIR_SPENT');
    $reservedGateway=AnyTourAndromedaQuoteAttemptState::reserve($completedGateway['context_sha256'],$completedGateway['operation_sha256']);
    foreach([$reservedGateway,AnyTourAndromedaQuoteAttemptState::unknown($reservedGateway)] as $sealed){
        anytour_andromeda_search3_save($checkpoint,['state'=>$sealed]);
        try{
            anytour_andromeda_quote_continue($gatewayRequest,$gatewayPdo,[],$gatewayConfig,'fixture-session');
            throw new RuntimeException('SEALED_PAIR_ACCEPTED');
        }catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_QUOTE_REPLAY_REFUSED')throw $e;}
        if($gatewayFactoryCalls!==1||count($gatewayCalls)!==3)throw new RuntimeException('SEALED_PAIR_SPENT');
    }
    $gatewayResolved['context']['offer_ref']='offer_'.str_repeat('b',64);
    $failureMeta=$seedGateway();$gatewayFailure=true;
    try{
        anytour_andromeda_quote_continue($gatewayRequest,$gatewayPdo,[],$gatewayConfig,'fixture-session');
        throw new RuntimeException('FAILED_CALC_GOT_VERIFIED_REFS');
    }catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_SELECTED_FLIGHTS_INVALID')throw $e;}
    $failedState=anytour_andromeda_quote_read($failureMeta['prefix'].'-quote-flight-v1.json',131072)['state'];
    if($failedState['status']!=='unknown'||$failedState['result']!==null
        ||$gatewayFactoryCalls!==2||$gatewayCalls!==['changeservice','changeservice','calc','changeservice','changeservice','calc'])throw new RuntimeException('FAILED_PAIR_NOT_SEALED_OR_BUDGET_CHANGED');
    try{
        anytour_andromeda_quote_continue($gatewayRequest,$gatewayPdo,[],$gatewayConfig,'fixture-session');
        throw new RuntimeException('FAILED_PAIR_REPLAY_ACCEPTED');
    }catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_QUOTE_REPLAY_REFUSED')throw $e;}
    if($gatewayFactoryCalls!==2||count($gatewayCalls)!==6)throw new RuntimeException('FAILED_PAIR_REPLAY_SPENT');
    $publicGateway=json_encode($gatewayFinal,JSON_THROW_ON_ERROR);
    foreach(['supplier_out_a','supplier_out_b','supplier_back_a','SID_gateway_fixture','catalog-private',
        'detail-request-private','detail-offer-private','detail-external-offer-private'] as $private){
        if(str_contains($publicGateway,$private))throw new RuntimeException('GATEWAY_PRIVATE_LEAK_'.$private);
    }

    // Mint through the actual initial gateway, then exercise three different
    // pairs and local replay through its production continuation/ledger code.
    $repriceInventory=$getFlights;$repriceInventory['fixture_head']='initial';
    $rows=&$repriceInventory['variants'][0]['transports'][0]['transport'];
    $extra=$rows[0];$extra['uid']='supplier_out_c';$extra['name']='OUT C';$rows[]=$extra;
    $extra=$rows[2];$extra['uid']='supplier_back_b';$extra['name']='BACK B';$rows[]=$extra;
    foreach($rows as &$row){$row['details'][0]['detail'][0]['markup']='1500';$row['details'][0]['detail'][0]['currency']='RUB';}
    unset($row,$rows);
    $repriceBudget=0;$repriceCalls=[];$repriceClockMs=(int)floor(microtime(true)*1000);
    $repriceFailure=null;$repriceReentry=null;$repriceExpire=null;
    $newContext=static function(string $label,int $ttl=600)use(&$gatewayResolved,&$gatewayNewInitial,
        &$repriceClockMs,&$repriceExpectedHead,&$repriceNextHead,&$repriceAmount,&$repriceFailure,&$repriceExpire,
        $gatewayPdo,$gatewayConfig):array{
        $gatewayNewInitial=true;$gatewayResolved['context']['offer_ref']='offer_'.hash('sha256',$label);
        $gatewayResolved['expires_at']=time()+$ttl;$repriceClockMs=(int)floor(microtime(true)*1000);
        $repriceExpectedHead='initial';$repriceNextHead='head_a';$repriceAmount='81234';$repriceFailure=null;$repriceExpire=null;
        $preview=anytour_andromeda_quote_run([],$gatewayPdo,[],$gatewayConfig,'fixture-session');
        $meta=anytour_andromeda_quote_meta($gatewayResolved,$gatewayConfig);
        $inventory=anytour_andromeda_quote_read($meta['prefix'].'-quote-flight-state-v1.json',3000000)['state'];
        $refs=[];foreach($inventory['items'] as $ref=>$record)$refs[$record['item']['uid']]=$ref;
        $selection=static fn(string $out,string $back):array=>['provider'=>'andromeda','outbound_ref'=>$refs[$out],'return_ref'=>$refs[$back]];
        return ['preview'=>$preview,'meta'=>$meta,
            'a'=>$selection('supplier_out_b','supplier_back_a'),'b'=>$selection('supplier_out_a','supplier_back_b'),
            'c'=>$selection('supplier_out_c','supplier_back_a'),'d'=>$selection('supplier_out_a','supplier_back_a')];
    };
    $continue=static fn(array $selected):array=>anytour_andromeda_quote_continue(
        ['flight_selection'=>$selected],$gatewayPdo,[],$gatewayConfig,'fixture-session');
    $unknown=static function(array $response,int $used):void{
        if(($response['state']??null)!=='quote_unknown'||($response['quote_state']??null)!=='unverified'
            ||($response['final_price']??null)!==null||($response['final_price_verified']??null)!==false
            ||($response['booking_enabled']??null)!==false||($response['flight_selection_required']??null)!==false
            ||($response['repricing']??null)!==['enabled'=>false,'max_pairs'=>3,'used_pairs'=>$used,'remaining_pairs'=>0])throw new RuntimeException('GLOBAL_UNKNOWN_NOT_SAFE');
    };
    $fresh=$newContext('three-pairs');$factoryAfterInitial=$gatewayFactoryCalls;$initialActionsAfter=$gatewayCalls;
    if(($fresh['preview']['repricing']??null)!==['enabled'=>true,'max_pairs'=>3,'used_pairs'=>0,'remaining_pairs'=>3]
        ||($fresh['preview']['search_price_estimate']??null)!==['amount'=>'74592','currency'=>'RUB','source'=>'derived_search_estimate'])throw new RuntimeException('FRESH_REPRICE_CAPABILITY_OR_BASELINE');
    $repriceReentry=static function()use($continue,$fresh):void{
        try{$continue($fresh['b']);throw new RuntimeException('CONCURRENT_PAIR_ENTERED');}
        catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_QUOTE_REPLAY_REFUSED')throw $e;}
        $state=anytour_andromeda_quote_read($fresh['meta']['prefix'].'-quote-flight-context-v2.json',3000000)['state'];
        if($state['status']!=='reserved'||$state['action_count']!==1)throw new RuntimeException('CONCURRENT_REENTRY_SEALED_ACTIVE_OWNER');
    };
    $a=$continue($fresh['a']);$repriceExpectedHead='head_a';$repriceNextHead='head_b';$repriceAmount='82345';
    $b=$continue($fresh['b']);
    if($a['repricing']['used_pairs']!==1||$b['repricing']['used_pairs']!==2||$repriceBudget!==6||count($repriceCalls)!==6)throw new RuntimeException('A_B_ACTION_BUDGET');
    $aReordered=['return_ref'=>$fresh['a']['return_ref'],'provider'=>'andromeda','outbound_ref'=>$fresh['a']['outbound_ref']];
    $aReplay=$continue($aReordered);$aExpected=$a;$aExpected['repricing']=$b['repricing'];
    if($aReplay!==$aExpected||$repriceBudget!==6||count($repriceCalls)!==6)throw new RuntimeException('A_B_A_CACHE_PRICE_REFS_TIME_OR_SPEND');
    $headAfterCache=anytour_andromeda_quote_read($fresh['meta']['prefix'].'-quote-flight-context-v2.json',3000000)['state'];
    if(($headAfterCache['claim']['fixture_head']??null)!=='head_b')throw new RuntimeException('CACHED_A_REWOUND_RAW_B');
    $previewReplay=anytour_andromeda_quote_run([],$gatewayPdo,[],$gatewayConfig,'fixture-session');
    if($previewReplay['repricing']!==$b['repricing']||$gatewayFactoryCalls!==$factoryAfterInitial)throw new RuntimeException('INITIAL_REPLAY_FROZEN_METADATA_OR_AUTH');
    $repriceExpectedHead='head_b';$repriceNextHead='head_c';$repriceAmount='83456';$c=$continue($fresh['c']);
    if($c['repricing']!==['enabled'=>true,'max_pairs'=>3,'used_pairs'=>3,'remaining_pairs'=>0]
        ||$repriceBudget!==9||count($repriceCalls)!==9||$gatewayFactoryCalls!==$factoryAfterInitial||$gatewayCalls!==$initialActionsAfter)throw new RuntimeException('THREE_PAIR_CAP_OR_REAUTH_DISCOVERY');
    if($repriceCalls[6]['old']!=='supplier_out_a'||$repriceCalls[7]['old']!=='supplier_back_b'
        ||$repriceCalls[6]['head']!=='head_b'||$repriceCalls[8]['sid']!=='SID_gateway_initial')throw new RuntimeException('C_DID_NOT_EVOLVE_B_SUPPLIER_HEAD');
    foreach([$a,$b,$c] as $index=>$quote){
        $selection=[$fresh['a'],$fresh['b'],$fresh['c']][$index];
        if($quote['package_price']!==$fresh['preview']['package_price']||$quote['search_price']!==$fresh['preview']['search_price']
            ||$quote['search_price_estimate']!==$fresh['preview']['search_price_estimate']
            ||$quote['expires_at']!==$fresh['preview']['expires_at']||!is_int($quote['verified_at']??null)
            ||$quote['flights'][0]['flight_ref']!==$selection['outbound_ref']||$quote['flights'][1]['flight_ref']!==$selection['return_ref'])throw new RuntimeException('PAIR_PRICE_BASELINE_REFS_OR_EXPIRY_CHANGED');
        if(($quote['price_observation']['search_price_estimate']??null)!==$fresh['preview']['search_price_estimate']
            ||($quote['price_observation']['signed_delta_amount']??null)!==['6642.00','7753.00','8864.00'][$index])throw new RuntimeException('OBSERVATION_USED_MUTABLE_PREVIOUS_PRICE');
    }
    $cap=$continue($fresh['d']);
    if(($cap['final_price_verified']??null)!==false||($cap['final_price']??null)!==null||$cap['repricing']!==$c['repricing']
        ||($cap['failure_reason']??null)!=='ANDROMEDA_FLIGHT_REPRICE_BUDGET'||($cap['failure_category']??null)!=='quote_state'
        ||$repriceBudget!==9||count($repriceCalls)!==9)throw new RuntimeException('FOURTH_PAIR_NOT_BOUNDED_REVIEWABLE_REFUSAL');
    $cachedAtCap=$continue($fresh['a']);if($cachedAtCap['final_price']!==$a['final_price']||$cachedAtCap['repricing']!==$c['repricing']||$repriceBudget!==9)throw new RuntimeException('CAP_DISABLED_EXACT_CACHE');

    foreach(['uid','currency','rejection','write'] as $failure){
        $fixture=$newContext('failed-'.$failure);$baseBudget=$repriceBudget;$baseCalls=count($repriceCalls);
        $a=$continue($fixture['a']);$repriceExpectedHead='head_a';$repriceNextHead='head_b';$repriceAmount='82345';
        $repriceFailure=$failure;$gatewaySaveFailure=$failure==='write';$failed=$continue($fixture['b']);$unknown($failed,2);
        if($repriceBudget!==$baseBudget+6||count($repriceCalls)!==$baseCalls+6)throw new RuntimeException('FAILED_NEW_PAIR_BUDGET');
        $failedState=anytour_andromeda_quote_read($fixture['meta']['prefix'].'-quote-flight-context-v2.json',3000000)['state'];
        if($failedState['status']!=='unknown'||($failure!=='write'&&$failedState['claim']['fixture_head']!=='head_a'))throw new RuntimeException('FAILED_RESPONSE_PROMOTED_RAW_HEAD');
        if($failure!=='write'){
            $active=$failedState['pairs'][$failedState['active_pair']];
            if($active['result']!==null||$active['status']!=='reserved')throw new RuntimeException('FAILED_PAIR_GAINED_VERIFIED_REFS');
        }
        if($failure==='rejection'&&(($failed['failure_category']??null)!=='supplier_rejected'
            ||($failed['failure_stage']??null)!=='calc'||($failed['supplier_code']??null)!=='SID_EXPIRED'))throw new RuntimeException('SANITIZED_REJECTION_FACTS_LOST');
        $unknown($continue($fixture['a']),2);$unknown(anytour_andromeda_quote_run([],$gatewayPdo,[],$gatewayConfig,'fixture-session'),2);
        if($repriceBudget!==$baseBudget+6||count($repriceCalls)!==$baseCalls+6)throw new RuntimeException('GLOBAL_UNKNOWN_REPLAY_SPENT');
    }
    $fixture=$newContext('crash-ready-marker');$a=$continue($fixture['a']);$baseBudget=$repriceBudget;
    file_put_contents($fixture['meta']['prefix'].'-quote-flight-context-v2.lock',str_repeat('c',32));
    $unknown($continue($fixture['a']),1);$unknown($continue($fixture['b']),1);
    if($repriceBudget!==$baseBudget)throw new RuntimeException('CRASH_MARKER_REOPENED_READY_CACHE');
    file_put_contents($fixture['meta']['prefix'].'-quote-flight-context-v2.lock',str_repeat('c',33));
    try{$continue($fixture['a']);throw new RuntimeException('OVERSIZE_MARKER_ACCEPTED');}
    catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_QUOTE_CHECKPOINT_INVALID')throw $e;}
    if($repriceBudget!==$baseBudget)throw new RuntimeException('OVERSIZE_MARKER_SPENT');

    foreach(['changeservice','calc'] as $expireAt){
        $fixture=$newContext('expiry-'.$expireAt,2);$repriceClockMs=(int)floor(microtime(true)*1000)-10000;
        $repriceExpire=$expireAt;$baseBudget=$repriceBudget;
        try{$continue($fixture['a']);throw new RuntimeException('EXPIRED_MUTABLE_RESPONSE_VERIFIED');}
        catch(DomainException $e){if($e->getMessage()!=='offer_expired')throw $e;}
        $expiredState=anytour_andromeda_quote_read($fixture['meta']['prefix'].'-quote-flight-context-v2.json',3000000)['state'];
        $expectedSpend=$expireAt==='changeservice'?1:3;
        if($repriceBudget!==$baseBudget+$expectedSpend||$expiredState['status']!=='unknown'
            ||$expiredState['head_pair']!==null)throw new RuntimeException('EXPIRY_DID_NOT_SEAL_BEFORE_NEXT_CALL_OR_AFTER_CALC');
    }
    $publicReprice=json_encode([$fresh['preview'],$aReplay,$b,$c,$failed],JSON_THROW_ON_ERROR);
    foreach(['SID_gateway_initial','supplier_out_a','supplier_out_b','supplier_out_c','supplier_back_b','catalog-private',
        'fixture_head','head_a','head_b','private supplier text'] as $private){
        if(str_contains($publicReprice,$private))throw new RuntimeException('REPRICE_PRIVATE_LEAK_'.$private);
    }
}finally{
    foreach(glob($gatewayFixtureDirectory.'/searches/*')?:[] as $path)unlink($path);
    rmdir($gatewayFixtureDirectory.'/searches');rmdir($gatewayFixtureDirectory);
}

echo "Andromeda flight selection continuation: PASS; legacy refs, cap3 A/B/A/C latest-head/SID, durable budget/expiry/global seal, supplier/DB=0\n";
