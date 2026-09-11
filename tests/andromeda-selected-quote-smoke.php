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
$package=[
    'version'=>'1.01',
    'claimDocument'=>[0=>[
        'catalogKey'=>'catalog-reduced','condition'=>'ccOffer','freightExternal'=>1,
        'buyerMoneys'=>$money('124864'),'transports'=>[null],
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
$getFlights['variants']=[['transports'=>[['transport'=>[
    ['uid'=>'out_uid','groupId'=>'20001','direction'=>'0','type'=>'ttAvia','name'=>'OUT 101','datebeg'=>'2026-09-20','dateend'=>'2026-09-20',
        'departure'=>[['state'=>'Russia','town'=>'Moscow','port'=>'SVO']], 'arrival'=>[['state'=>'Egypt','town'=>'Sharm','port'=>'SSH']]],
    ['uid'=>'back_uid','groupId'=>'20002','direction'=>'1','type'=>'ttAvia','name'=>'BACK 102','datebeg'=>'2026-09-27','dateend'=>'2026-09-27',
        'departure'=>[['state'=>'Egypt','town'=>'Sharm','port'=>'SSH']], 'arrival'=>[['state'=>'Russia','town'=>'Moscow','port'=>'SVO']]],
]]]]];
$reserved=0;$seen=[];
$request=static function(string $url,string $post)use(&$seen,$getFlights):array{
    parse_str((string)parse_url($url,PHP_URL_QUERY),$q);
    if(($q['version']??null)!=='1.01'||!in_array($q['action']??null,['get_flights','changeservice','calc'],true))throw new RuntimeException('BAD_ACTION');
    parse_str($post,$form);$claim=json_decode($form['claim']??'',true,64,JSON_THROW_ON_ERROR);
    $seen[]=$q['action'];
    if($q['action']==='get_flights')$reply=$getFlights;
    elseif($q['action']==='changeservice'){
        if(!isset($q['NEW_UID'])||isset($q['OLD_UID']))throw new RuntimeException('BAD_CHANGE');
        $reply=$claim;
    }else{
        $reply=$claim;$reply['claimDocument'][0]['buyerMoneys']=[["buyerClaimMoney"=>[["net"=>'135643',"currency"=>'RUB']]]];
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
$encoded=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
foreach(['opaque-claiminc','out_uid','back_uid','SID_test_123','catalog-reduced'] as $secret)if(str_contains($encoded,$secret))throw new RuntimeException('private leak '.$secret);++$checks;

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
if(str_contains(json_encode($choice,JSON_THROW_ON_ERROR),'out_two'))throw new RuntimeException('uid leak');++$checks;

$noFlight=$package;$noFlight['claimDocument'][0]['freightExternal']=0;$noFlight['claimDocument'][0]['transports']=[];
$reserved3=0;$seen3=[];
$actions3=new AnyTourAndromedaClaimActions('SID_test_789',static function()use(&$reserved3){++$reserved3;},
    static function(string $url,string $post)use(&$seen3):array{
        parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$seen3[]=$q['action']??null;
        if(($q['action']??null)!=='calc')throw new RuntimeException('NO_FLIGHT_BAD_ACTION');
        parse_str($post,$form);$claim=json_decode($form['claim'],true,64,JSON_THROW_ON_ERROR);
        $claim['claimDocument'][0]['buyerMoneys']=[["buyerClaimMoney"=>[["net"=>'130000',"currency"=>'RUB']]]];
        return ['status'=>200,'body'=>json_encode($claim,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
    });
$direct=AnyTourAndromedaSelectedQuote::run($resolved,new AnyTourAndromedaClient($noFlight),$actions3);
if(($direct['final_price']['amount']??null)!=='130000'||$seen3!==['calc']||$reserved3!==1)throw new RuntimeException('direct calc');++$checks;

print("Andromeda selected quote: {$checks} checks passed\n");
