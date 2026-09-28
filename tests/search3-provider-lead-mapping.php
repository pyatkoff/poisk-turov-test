<?php
// Execute only the real adapter's pure mapping; no bootstrap, HMAC or CRM writes.
require_once __DIR__.'/../v2/lead-price-v1.php';
require_once __DIR__.'/../v2/lead-idempotency-v1.php';
$source=file_get_contents(__DIR__.'/../v2/lead-adapter-v2.php');
$start=strpos($source,'const V2_LEAD_IBLOCK_ID');$end=strpos($source,"if(\$_SERVER['REQUEST_METHOD']==='GET')");
if($start===false||$end===false)throw new RuntimeException('Adapter mapping boundary changed');
eval(substr($source,$start,$end-$start));
function check($yes,$message){if(!$yes)throw new RuntimeException($message);}
function sample($provider){
 $ref=$provider==='anex'?'anex_online:'.str_repeat('a',64):'offer_'.str_repeat('b',64);
 return ['provider'=>$provider,'providerOfferRef'=>$ref,'tourId'=>$provider.':'.$ref,'providerQuoteExpiresAt'=>time()+600,
 'providerChoiceRef'=>$provider==='anex'?'anex_quote:'.str_repeat('d',64):null,
 'phone'=>'79990000000','name'=>'Fixture','consent'=>true,'departure'=>'Москва','hotel'=>'Exact hotel','country'=>'Турция','region'=>'Анталья',
 'date'=>'2026-10-13','nights'=>7,'adults'=>2,'childAges'=>[4,12],'meal'=>'AI','roomType'=>'FAMILY','operator'=>'Fixture operator',
 'price'=>101069.5,'flightPrice'=>101069.5,'flightFuel'=>null,'priceKind'=>'verified','finalPriceVerified'=>true,'currency'=>'RUB',
 'flight'=>'Туда · S7 3749 | Обратно · S7 3750','providerFlights'=>[['direction'=>'0','text'=>'Туда · S7 3749'],['direction'=>'1','text'=>'Обратно · S7 3750']]];
}
foreach(['andromeda','anex'] as $provider){
 $data=sample($provider);$built=lead_build($data);check(empty($built['errors']),json_encode($built));$lead=$built['lead'];$comments=$built['properties']['COMMENTS'];
 check($lead['provider']===$provider&&$lead['providerOfferRef']===$data['providerOfferRef'],'Exact provider identity');
 check(strpos($comments,$data['providerOfferRef'])!==false&&strpos($comments,'Tourvisor')===false,'Accurate manager source');
 check(strpos($comments,'S7 3749')!==false&&strpos($comments,'S7 3750')!==false,'Both flights retained');
 check($lead['selectedPrice']===101069.5&&$lead['flightFuel']===null,'Exact confirmed total and unknown fuel');
 check($lead['childAges']===[4,12]&&$built['properties']['DEPARTURE']==='Москва','Party and departure retained');
 check($built['element']['IBLOCK_ID']===4&&$built['properties']['SOURCE']===26,'Same CRM target');
 $key=lead_idempotency_key($lead);check($key===lead_idempotency_key(lead_build($data)['lead']),'Retry same identity');
 foreach([['flightPrice'=>101070],['tourId'=>'TV123'],['providerOfferRef'=>'bad'],['priceKind'=>'estimate'],['finalPriceVerified'=>false],['providerQuoteExpiresAt'=>1],['flightFuel'=>0],['currency'=>'EUR'],['providerFlights'=>[['direction'=>'0','text'=>'Туда · S7 3749']]],['flight'=>'changed'],['departure'=>''],['price'=>INF],['adults'=>0]] as $patch)
  check(!empty(lead_build(array_replace($data,$patch))['errors']),'Reject '.json_encode($patch));
 $changed=$data;$changed['flight']='Туда · NEW | Обратно · S7 3750';$changed['providerFlights'][0]['text']='Туда · NEW';
 check($key!==lead_idempotency_key(lead_build($changed)['lead']),'Another flight is another lead');
 $changed=$data;$changed['childAges']=[5,12];check($key!==lead_idempotency_key(lead_build($changed)['lead']),'Another party is another lead');
 $missing=$data;unset($missing['provider']);check(!empty(lead_build($missing)['errors']),'Provider identity cannot downgrade to TV');
}
$tv=['tourId'=>'TV123','phone'=>'89990000000','consent'=>true,'price'=>120000,'flightPrice'=>133500,'flight'=>'TV flight','adults'=>2,'childAges'=>[4]];
$lead=lead_build($tv);check(empty($lead['errors']),'Legacy TV accepted');check(!isset($lead['lead']['provider']),'Legacy TV shape unchanged');
check(strpos($lead['properties']['COMMENTS'],'V2 Tourvisor tourId: TV123')!==false,'Legacy label unchanged');
check($lead['lead']['selectedPrice']===133500&&$lead['lead']['priceDelta']===13500,'Legacy TV price unchanged');
check(lead_idempotency_key($lead['lead'])===hash('sha256',implode('|',['+79990000000','TV123','','TV flight','133500','',''])),'Legacy dedup byte-equivalent');
echo "Provider CRM mapping: exact source/offer/itinerary/total/party, strict rejection, dedup and legacy TV PASS\n";
