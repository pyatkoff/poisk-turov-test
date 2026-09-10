<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/andromeda-group-by-probe.php';
$p=AnyTourAndromedaClient::priceProbeParams();
AnyTourAndromedaClient::validatePriceParams($p);
AnyTourAndromedaClient::validatePriceParams($p+['GROUP_BY'=>32]);
foreach([0,1,33,'32',null,[],true] as $bad){
    try { AnyTourAndromedaClient::validatePriceParams($p+['GROUP_BY'=>$bad]); throw new RuntimeException('INVALID_GROUP_ACCEPTED'); }
    catch(InvalidArgumentException $e){}
}
$sent=[];
$c=new AnyTourAndromedaClient(static function($url)use(&$sent){parse_str(parse_url($url,PHP_URL_QUERY),$q);$sent[]=$q;
return ['status'=>200,'body'=>json_encode($q['action']==='login'?['sid'=>'synthetic-session']:['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[]])];},true);
$c->login('synthetic-user','synthetic-password');$c->price($p+['GROUP_BY'=>32]);
if(count($sent)!==2||$sent[1]['GROUP_BY']!=='32') throw new RuntimeException('GROUP_NOT_SENT');
$stats=group_stats(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[
['hotelKey'=>12,'operatorKey'=>5,'isOperatorHotelKey'=>0],
['hotelKey'=>12,'operatorKey'=>6,'isOperatorHotelKey'=>0],
['hotelKey'=>12,'operatorKey'=>5,'isOperatorHotelKey'=>1]]]);
if($stats['rows']!==3||$stats['unique_hotels']!==2||$stats['max_offers_per_hotel']!==2) throw new RuntimeException('IDENTITY_COUNT_INVALID');
echo "GROUP_BY validation, transport and namespace checks passed\n";
