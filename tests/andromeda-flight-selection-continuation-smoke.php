<?php
declare(strict_types=1);

final class AnyTourAndromedaClient {
    public function __construct(private array $package) {}
    public function package(string $id): array {
        if ($id !== 'opaque-claiminc') throw new RuntimeException('BAD_ID');
        return $this->package;
    }
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

echo "Andromeda flight selection continuation: PASS\n";
