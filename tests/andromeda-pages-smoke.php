<?php
require_once __DIR__.'/../app/integrations/andromeda-search.php';
$row=['id'=>'opaque-page-offer','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
 'price'=>'107193','currency'=>'RUB','currencyKey'=>643,'checkIn'=>'18.09.2026','nights'=>'8',
 'hotel'=>'Sharming Inn','operator'=>'Anex Tour','meal'=>'AI','mealKey'=>'6','andrMealKey'=>5,
 'room'=>'Standard Room','htplace'=>'DBL','adult'=>'2','child'=>'0','star'=>'4*','town'=>'Sharm',
 'hotelImage'=>'https://gateway.samo.ru/web/data/hotel/image.jpg','hotelUrl'=>'https://agent.example.com/hotel'];
$criteria=AnyTourAndromedaClient::priceProbeParams();unset($criteria['OPERATORS']);
$calls=[];$state1=[];$state2=[];$checkpoint=[];
$transport=static function($url)use(&$calls,&$checkpoint,$row){
 if(($checkpoint['status']??null)!=='pending')throw new LogicException('no pending checkpoint');
 parse_str(parse_url($url,PHP_URL_QUERY),$q);$calls[]=$q;
 if($q['action']==='login')return ['status'=>200,'body'=>json_encode(['sid'=>'private_pagination_fixture'])];
 return ['status'=>200,'body'=>json_encode(['PAGE'=>(int)$q['PAGE'],'PAGES_COUNT'=>2,'PRICES'=>[$row]])];
};
$save=static function($state)use(&$checkpoint){$checkpoint=$state;return true;};
$client1=new AnyTourAndromedaClient($transport,true);
$one=(new AnyTourAndromedaSearch($state1,$save,true,true))->start($criteria,'same_search',1,time(),$client1,'test','test');
$client2=new AnyTourAndromedaClient($transport,true);$client2->restorePrivateSession($client1->privateSession());
$criteria['PAGE']=2;
$handler2=new AnyTourAndromedaSearch($state2,$save,true,true);
$two=$handler2->start($criteria,'same_search',1,time(),$client2,'test','test');
if(count($calls)!==3||$calls[2]['action']!=='price'||$two['page']!==2||$two['offers'][0]['offer_ref']!==$one['offers'][0]['offer_ref'])throw new RuntimeException('pagination/session/dedup failed');
if($two['offers'][0]['hotel_content']['image_url']!==$row['hotelImage']||$two['offers'][0]['hotel_content']['category']!==4)throw new RuntimeException('content dropped');
$read=$handler2->resume('same_search',1,time());if($read!==$two||count($calls)!==3)throw new RuntimeException('resume spent API');
if(strpos(json_encode($two),'private_pagination_fixture')!==false||strpos(json_encode($two),'opaque-page-offer')!==false)throw new RuntimeException('private field exposed');
echo "Andromeda pages: shared session, page match, stable offer refs, content, durable resume passed\n";
