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

$checks=0;
$money=static fn(string $amount):array => [['buyerClaimMoney'=>[['net'=>$amount,'currency'=>'RUB']]]];
$operatorMoney=static fn(string $usd,string $rub):array => [['money'=>[
    ['price'=>$usd,'net'=>$usd,'currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true'],
    ['price'=>$rub,'net'=>$rub,'currency'=>'RUB','rate'=>'89.83','isClaimCurrency'=>'false'],
]]];
$calcOperatorMoney=static fn():array => [['money'=>[
    ['price'=>'1510','net'=>'1402','priceForCommiss'=>'1349.79','sumCommission'=>'108','currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true'],
    ['price'=>'135643','net'=>'125942','priceForCommiss'=>'121251.64','sumCommission'=>'9702','currency'=>'RUB','rate'=>'89.83','isClaimCurrency'=>'false'],
]]];
$package=[
    'version'=>'1.01',
    'claimDocument'=>[0=>[
        'catalogKey'=>'catalog-reduced','condition'=>'ccOffer','freightExternal'=>1,
        'buyerMoneys'=>$money('124864'),'moneys'=>$operatorMoney('1390','124864'),'transports'=>[null],
    ]],
    'variants'=>[], 'groups'=>[],
];
$resolved=[
    'supplier_offer_id'=>'opaque-claiminc',
    'offer'=>['local_hotel_id'=>6319,'operator'=>['id'=>'5','name'=>'ANEX'],
        'price'=>['amount'=>'119114','currency'=>'RUB']],
];
$getFlights=$package;
$getFlights['groups']=[['group'=>[
    ['id'=>'20001','required'=>'true','oneItem'=>'true'],
    ['id'=>'20002','required'=>'true','oneItem'=>'true'],
]]];
$detail=static fn(string $route):array => [['detail'=>[ [
    'markup'=>'160','currency'=>'USD','route_index'=>$route,
    'requestid'=>'private-request-'.$route,'offer_id'=>'private-offer',
] ]]];
$getFlights['variants']=[['transports'=>[['transport'=>[
    ['uid'=>'out_uid','groupId'=>'20001','direction'=>'0','type'=>'ttAvia','name'=>'+160 USD OUT 101','datebeg'=>'2026-09-20','dateend'=>'2026-09-20',
        'details'=>$detail('0'),
        'departure'=>[['state'=>'Russia','town'=>'Moscow','port'=>'SVO']], 'arrival'=>[['state'=>'Egypt','town'=>'Sharm','port'=>'SSH']]],
    ['uid'=>'back_uid','groupId'=>'20002','direction'=>'1','type'=>'ttAvia','name'=>'BACK 102','datebeg'=>'2026-09-27','dateend'=>'2026-09-27',
        'details'=>$detail('1'),
        'departure'=>[['state'=>'Egypt','town'=>'Sharm','port'=>'SSH']], 'arrival'=>[['state'=>'Russia','town'=>'Moscow','port'=>'SVO']]],
]]]]];
$fuelServices=[['service'=>[
    ['type'=>'stOther','servicetype'=>'8','servicecategoryName'=>'Топливный сбор','price'=>'80','currencyAlias'=>'USD','routeIndex'=>'0','uid'=>'fuel_out'],
    ['type'=>'stOther','servicetype'=>'8','servicecategoryName'=>'Топливный сбор','price'=>'80','currencyAlias'=>'USD','routeIndex'=>'1','uid'=>'fuel_back'],
    ['type'=>'stOther','servicetype'=>'9','servicecategoryName'=>'Не топливо','price'=>'999','currencyAlias'=>'USD','routeIndex'=>'0','uid'=>'other_service'],
]]];
$reserved=0;$seen=[];
$request=static function(string $url,string $post)use(&$seen,$getFlights,$fuelServices,$money,$calcOperatorMoney):array{
    parse_str((string)parse_url($url,PHP_URL_QUERY),$q);
    if(($q['version']??null)!=='1.01'||!in_array($q['action']??null,['get_flights','changeservice','calc'],true))throw new RuntimeException('BAD_ACTION');
    parse_str($post,$form);$claim=json_decode($form['claim']??'',true,64,JSON_THROW_ON_ERROR);
    $seen[]=$q['action'];
    if($q['action']==='get_flights')$reply=$getFlights;
    elseif($q['action']==='changeservice'){
        if(!isset($q['NEW_UID'])||isset($q['OLD_UID']))throw new RuntimeException('BAD_CHANGE');
        $reply=$claim;
    }else{
        $reply=$claim;
        $reply['claimDocument'][0]['buyerMoneys']=$money('135643');
        $reply['claimDocument'][0]['moneys']=$calcOperatorMoney();
        $reply['claimDocument'][0]['services']=$fuelServices;
    }
    return ['status'=>200,'body'=>json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
};
$actions=new AnyTourAndromedaClaimActions('SID_test_123',static function()use(&$reserved){++$reserved;},$request);
$result=AnyTourAndromedaSelectedQuote::run($resolved,new AnyTourAndromedaClient($package),$actions);
if(($result['state']??null)!=='quote_verified')throw new RuntimeException('state');++$checks;
if(($result['final_price']['amount']??null)!=='135643'||($result['final_price']['currency']??null)!=='RUB')throw new RuntimeException('price');++$checks;
if(($result['package_price']['amount']??null)!=='124864'||($result['search_price']['amount']??null)!=='119114')throw new RuntimeException('provenance');++$checks;
if(($result['final_price_verified']??null)!==true||($result['booking_enabled']??null)!==false)throw new RuntimeException('flags');++$checks;
if($seen!==['get_flights','changeservice','changeservice','calc']||$reserved!==4)throw new RuntimeException('calls');++$checks;
if(count($result['flights']??[])!==2||($result['flights'][0]['direction']??null)!=='0'||($result['flights'][1]['direction']??null)!=='1')throw new RuntimeException('flights');++$checks;
$expectedMarkup=['amount'=>'160','currency'=>'USD','source'=>'andromeda_transport_detail','aggregation'=>'unknown'];
if(($result['flights'][0]['transport_markup_reported']??null)!==$expectedMarkup
    ||($result['flights'][1]['transport_markup_reported']??null)!==$expectedMarkup)throw new RuntimeException('transport markup');++$checks;
if(($result['fuel_surcharges_reported']??null)!==[
    ['amount'=>'80','currency'=>'USD','route_index'=>'0','source'=>'andromeda_claim_service'],
    ['amount'=>'80','currency'=>'USD','route_index'=>'1','source'=>'andromeda_claim_service'],
])throw new RuntimeException('fuel services');++$checks;
if(($result['operator_currency_rates_reported']??null)!==[
    ['currency'=>'USD','rate'=>'1','is_claim_currency'=>true,'source'=>'andromeda_claim_money','arithmetic_applied'=>false],
    ['currency'=>'RUB','rate'=>'89.83','is_claim_currency'=>false,'source'=>'andromeda_claim_money','arithmetic_applied'=>false],
])throw new RuntimeException('operator rates');++$checks;
if(($result['calc_money_facts_reported']??null)!==[
    ['currency'=>'USD','gross_amount'=>'1510','net_amount'=>'1402','commissionable_amount'=>'1349.79','commission_amount'=>'108','source'=>'andromeda_calc_money','arithmetic_applied'=>false],
    ['currency'=>'RUB','gross_amount'=>'135643','net_amount'=>'125942','commissionable_amount'=>'121251.64','commission_amount'=>'9702','source'=>'andromeda_calc_money','arithmetic_applied'=>false],
])throw new RuntimeException('calc money facts');++$checks;
if(isset($result['fuel_total'])||isset($result['surcharge_total'])||isset($result['price_with_fuel'])||isset($result['commission_rate'])||isset($result['derived_price']))throw new RuntimeException('synthetic arithmetic');++$checks;
$encoded=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
foreach(['opaque-claiminc','out_uid','back_uid','fuel_out','fuel_back','other_service','private-request-0','private-request-1','private-offer','SID_test_123','catalog-reduced'] as $secret)if(str_contains($encoded,$secret))throw new RuntimeException('private leak '.$secret);++$checks;

$ambiguous=$getFlights;
$ambiguous['variants'][0]['transports'][0]['transport'][]=['uid'=>'out_two','groupId'=>'20001','direction'=>'0','type'=>'ttAvia','name'=>'OUT 202'];
$reserved2=0;$seen2=[];
$actions2=new AnyTourAndromedaClaimActions('SID_test_456',static function()use(&$reserved2){++$reserved2;},
    static function(string $url,string $post)use(&$seen2,$ambiguous):array{
        parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$seen2[]=$q['action']??null;
        if(($q['action']??null)!=='get_flights')throw new RuntimeException('AMBIGUOUS_SHOULD_STOP');
        return ['status'=>200,'body'=>json_encode($ambiguous,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
    });
$choice=AnyTourAndromedaSelectedQuote::run($resolved,new AnyTourAndromedaClient($package),$actions2);
if(($choice['state']??null)!=='flight_selection_required'||($choice['final_price_verified']??null)!==false)throw new RuntimeException('ambiguous state');++$checks;
if($seen2!==['get_flights']||$reserved2!==1)throw new RuntimeException('ambiguous calls');++$checks;
if(($choice['fuel_surcharges_reported']??null)!==[])throw new RuntimeException('ambiguous fuel');++$checks;
if(($choice['calc_money_facts_reported']??null)!==[])throw new RuntimeException('ambiguous calc facts');++$checks;
if(($choice['operator_currency_rates_reported']??null)!==[
    ['currency'=>'USD','rate'=>'1','is_claim_currency'=>true,'source'=>'andromeda_claim_money','arithmetic_applied'=>false],
    ['currency'=>'RUB','rate'=>'89.83','is_claim_currency'=>false,'source'=>'andromeda_claim_money','arithmetic_applied'=>false],
])throw new RuntimeException('ambiguous rates');++$checks;
if(str_contains(json_encode($choice,JSON_THROW_ON_ERROR),'out_two'))throw new RuntimeException('uid leak');++$checks;

$noFlight=$package;$noFlight['claimDocument'][0]['freightExternal']=0;$noFlight['claimDocument'][0]['transports']=[];
$reserved3=0;$seen3=[];
$actions3=new AnyTourAndromedaClaimActions('SID_test_789',static function()use(&$reserved3){++$reserved3;},
    static function(string $url,string $post)use(&$seen3,$money,$operatorMoney):array{
        parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$seen3[]=$q['action']??null;
        if(($q['action']??null)!=='calc')throw new RuntimeException('NO_FLIGHT_BAD_ACTION');
        parse_str($post,$form);$claim=json_decode($form['claim'],true,64,JSON_THROW_ON_ERROR);
        $claim['claimDocument'][0]['buyerMoneys']=$money('130000');
        $claim['claimDocument'][0]['moneys']=$operatorMoney('1447.18','130000');
        return ['status'=>200,'body'=>json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
    });
$direct=AnyTourAndromedaSelectedQuote::run($resolved,new AnyTourAndromedaClient($noFlight),$actions3);
if(($direct['final_price']['amount']??null)!=='130000'||$seen3!==['calc']||$reserved3!==1)throw new RuntimeException('direct calc');++$checks;
if(($direct['fuel_surcharges_reported']??null)!==[])throw new RuntimeException('direct fuel');++$checks;
if(($direct['operator_currency_rates_reported'][1]['rate']??null)!=='89.83')throw new RuntimeException('direct rates');++$checks;
if(($direct['calc_money_facts_reported']??null)!==[
    ['currency'=>'USD','gross_amount'=>'1447.18','net_amount'=>'1447.18','commissionable_amount'=>null,'commission_amount'=>null,'source'=>'andromeda_calc_money','arithmetic_applied'=>false],
    ['currency'=>'RUB','gross_amount'=>'130000','net_amount'=>'130000','commissionable_amount'=>null,'commission_amount'=>null,'source'=>'andromeda_calc_money','arithmetic_applied'=>false],
])throw new RuntimeException('direct calc facts');++$checks;

// Malformed JSON money must not become a verified one-ruble quote via (string)true.
// Exercise the actual quote entrypoint and ClaimActions with an offline callback.
$scalarQuote=static function($packageNet,$finalNet,$optionalAmount,$rate)use($resolved,$noFlight,$getFlights,$fuelServices):array{
    $input=$noFlight;
    $input['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][0]['net']=$packageNet;
    $flight=$getFlights['variants'][0]['transports'][0]['transport'][0];
    $flight['details'][0]['detail'][0]['markup']=$optionalAmount;
    $input['claimDocument'][0]['transports']=[['transport'=>[$flight]]];
    $reply=$input;
    $reply['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][0]['net']=$finalNet;
    $reply['claimDocument'][0]['services']=[['service'=>[$fuelServices[0]['service'][0]]]];
    $reply['claimDocument'][0]['services'][0]['service'][0]['price']=$optionalAmount;
    $reply['claimDocument'][0]['moneys']=[['money'=>[
        ['price'=>'100','net'=>'100','currency'=>'USD','rate'=>$rate,'isClaimCurrency'=>'true'],
    ]]];
    $calls=[];$reservations=0;
    $actions=new AnyTourAndromedaClaimActions('SID_scalar_test',static function()use(&$reservations){++$reservations;},
        static function(string $url,string $post)use(&$calls,$reply):array{
            parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$calls[]=$q['action']??null;
            if(($q['action']??null)!=='calc')throw new RuntimeException('SCALAR_UNEXPECTED_ACTION');
            return ['status'=>200,'body'=>json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
        });
    try{return AnyTourAndromedaSelectedQuote::run($resolved,new AnyTourAndromedaClient($input),$actions);}
    finally{if($calls!==['calc']||$reservations!==1)throw new RuntimeException('SCALAR_RETRY_OR_MISSING_RESERVATION');}
};
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{
    throw new ErrorException($message,0,$severity,$file,$line);
});
try{
    foreach([true,false,null,[],['amount'=>'1'],'not-money'] as $invalid){
        try{
            $scalarQuote('124864',$invalid,'80','89.83');
            throw new RuntimeException('INVALID_MONEY_BECAME_VERIFIED');
        }catch(RuntimeException $e){
            if($e->getMessage()!=='ANDROMEDA_FINAL_PRICE_MISSING')throw $e;
        }
        ++$checks;
        $typed=$scalarQuote($invalid,'130000',$invalid,$invalid);
        if($typed['package_price']!==null||$typed['final_price']!==['amount'=>'130000','currency'=>'RUB']
            ||$typed['final_price_verified']!==true||$typed['booking_enabled']!==false
            ||$typed['fuel_surcharges_reported']!==[]||$typed['operator_currency_rates_reported']!==[]
            ||$typed['flights'][0]['transport_markup_reported']!==null)throw new RuntimeException('INVALID_FACT_COERCED');
        ++$checks;
    }
    foreach([1,80.5,'80.50'] as $valid){
        $typed=$scalarQuote($valid,$valid,$valid,'1.234567');
        if($typed['final_price']!==['amount'=>(string)$valid,'currency'=>'RUB']
            ||$typed['package_price']!==$typed['final_price']
            ||$typed['fuel_surcharges_reported'][0]['amount']!==(string)$valid
            ||$typed['flights'][0]['transport_markup_reported']['amount']!==(string)$valid
            ||$typed['operator_currency_rates_reported'][0]['rate']!=='1.234567'
            ||$typed['operator_currency_rates_reported'][0]['arithmetic_applied']!==false
            ||$typed['search_price']!==$resolved['offer']['price'])throw new RuntimeException('VALID_MONEY_CHANGED');
        ++$checks;
    }
    foreach([0,0.0,'0.00'] as $zero){
        $typed=$scalarQuote('124864','130000',$zero,89.83);
        if($typed['fuel_surcharges_reported'][0]['amount']!==(string)$zero
            ||$typed['flights'][0]['transport_markup_reported']['amount']!==(string)$zero
            ||$typed['operator_currency_rates_reported'][0]['rate']!=='89.83')throw new RuntimeException('EXPLICIT_ZERO_OR_NUMERIC_RATE_LOST');
        ++$checks;
    }
}finally{restore_error_handler();}

print("Andromeda selected quote: {$checks} checks passed\n");
