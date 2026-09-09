<?php
require_once __DIR__.'/../app/integrations/andromeda-client.php';
$calls=[];
$client=new AnyTourAndromedaClient(static function($url)use(&$calls){
    parse_str(parse_url($url,PHP_URL_QUERY),$query);$calls[]=$query;
    return ['status'=>200,'body'=>json_encode($query['action']==='login'?['sid'=>'test_session_long_enough']:['PAGE'=>1,'PAGES_COUNT'=>0,'PRICES'=>[]])];
},true);
$client->login('test','test');
$params=['TOWNFROMINC'=>7,'STATEINC'=>3,'CHECKIN_BEG'=>'20270102','CHECKIN_END'=>'20270104','NIGHTS_FROM'=>6,'NIGHTS_TILL'=>9,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>1];
$client->price($params);
if(count($calls)!==2 || $calls[1]['TOWNFROMINC']!=='7' || isset($calls[1]['OPERATORS']) || $calls[1]['CHECKIN_END']!=='20270104')throw new RuntimeException('dynamic criteria not preserved');
try{$client->price($params);throw new LogicException('replayed');}catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_PRICE_REPLAY_REFUSED')throw $e;}
$bad=$params;$bad['CHECKIN_BEG']='20270230';
try{$client->price($bad);throw new LogicException('invalid date accepted');}catch(InvalidArgumentException $e){}
if(count($calls)!==2)throw new RuntimeException('validation spent supplier calls');
echo "Andromeda live criteria passed\n";

require_once __DIR__.'/../app/integrations/andromeda-search.php';
$state=[];$saved=[];$dynamicCalls=[];
$dynamicClient=new AnyTourAndromedaClient(static function($url)use(&$dynamicCalls,&$saved){
    if(($saved['status']??null)!=='pending')throw new LogicException('not durable');
    parse_str(parse_url($url,PHP_URL_QUERY),$query);$dynamicCalls[]=$query;
    return ['status'=>200,'body'=>json_encode($query['action']==='login'?['sid'=>'dynamic_fixture_session']:['PAGE'=>1,'PAGES_COUNT'=>0,'PRICES'=>[]])];
},true);
$handler=new AnyTourAndromedaSearch($state,static function($s)use(&$saved){$saved=$s;return true;},true,true);
$out=$handler->start($params,'dynamic1',1,time(),$dynamicClient,'test','test');
if($out['status']!=='complete'||$out['date_range']['to']!=='2027-01-04'||count($dynamicCalls)!==2||isset($dynamicCalls[1]['OPERATORS']))throw new RuntimeException('dynamic handler failed');
$again=$handler->resume('dynamic1',1,time());
if($again!==$out||count($dynamicCalls)!==2)throw new RuntimeException('resume requested supplier');
echo "Dynamic handler checkpoint and resume passed\n";

require_once __DIR__.'/../v2/api-andromeda-search3-preview.php';
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE catalog_hotels(id INTEGER,name TEXT,country_id INTEGER,is_active INTEGER); CREATE TABLE andromeda_hotel_identities(local_hotel_id INTEGER,external_hotel_id TEXT,supplier_namespace TEXT,decision_status TEXT); CREATE TABLE catalog_departures(id INTEGER,name TEXT,is_active INTEGER)");
$pdo->exec("INSERT INTO catalog_hotels VALUES(447,'Verginia',1,1),(9365,'Life',1,1),(501,'Conflict',1,1),(999,'Inactive',1,0),(888,'Other country',2,1); INSERT INTO catalog_departures VALUES(1,'Moscow',1)");
$pdo->exec("INSERT INTO andromeda_hotel_identities VALUES(447,'2000042763','andromeda_catalog','accepted'),(9365,'416247','andromeda_catalog','accepted'),(501,'2000073714','andromeda_catalog','conflict'),(447,'655','operator_5','accepted'),(999,'900','andromeda_catalog','accepted'),(888,'800','andromeda_catalog','accepted')");
$dictionary=['townfrom'=>['payload'=>['TOWNFROM'=>[['id'=>1,'name'=>'Moscow']]]],'all'=>['payload'=>['HOTELS'=>[['id'=>2000042763],['id'=>416247],['id'=>2000073714],['id'=>900],['id'=>800]]]]];
if(anytour_andromeda_search3_hotels([9365,447,447],$pdo,$dictionary)!=='2000042763,416247')throw new RuntimeException('accepted catalog filter missing or operator ID mixed in');
foreach([[],[501],[447,501],[999],[888],[12345]] as $ids)if(anytour_andromeda_search3_hotels($ids,$pdo,$dictionary)!==null)throw new RuntimeException('incomplete coverage narrowed search');
$missing=$dictionary;$missing['all']['payload']['HOTELS']=[];
if(anytour_andromeda_search3_hotels([447],$pdo,$missing)!==null)throw new RuntimeException('unknown catalog key used');
$request=['generation'=>1,'andromeda_operator_ids'=>['5'],'params'=>['countryId'=>'1','departureId'=>'1','dateFrom'=>gmdate('Y-m-d',time()+86400),'dateTo'=>gmdate('Y-m-d',time()+86400),'nightsFrom'=>8,'nightsTo'=>8,'adults'=>2,'meal'=>'7','hotelIds'=>['447']]];
$point=anytour_andromeda_search3_params($request,$pdo,$dictionary);
if($point['HOTELS']!=='2000042763'||$point['OPERATORS']!=='5')throw new RuntimeException('HTTP criteria lost hotel/operator filter');
$request['page']=2;
if(anytour_andromeda_search3_params($request,$pdo,$dictionary)['HOTELS']!==$point['HOTELS'])throw new RuntimeException('later page changed hotel filter');
$wire=[];$pointClient=new AnyTourAndromedaClient(static function($url)use(&$wire){
 parse_str(parse_url($url,PHP_URL_QUERY),$q);$wire[]=$q;
 return ['status'=>200,'body'=>json_encode($q['action']==='login'?['sid'=>'point_fixture_session']:['PAGE'=>1,'PAGES_COUNT'=>0,'PRICES'=>[]])];
},true);
$pointClient->login('test','test');$pointClient->price($point);
if($wire[1]['HOTELS']!=='2000042763'||$wire[1]['OPERATORS']!=='5')throw new RuntimeException('upstream hotel filter absent');
foreach(['0','1,0','1&OPERATORS=7',implode(',',range(1,31))] as $badHotels){
 $bad=$point;$bad['HOTELS']=$badHotels;
 try{AnyTourAndromedaClient::validatePriceParams($bad);throw new LogicException('invalid HOTELS accepted');}catch(InvalidArgumentException $expected){}
}
echo "Andromeda hotel filter: accepted catalog IDs, full coverage, country/activity, pagination and upstream request passed\n";

$pointState=[];$pointSaved=[];$pointCalls=0;
$fullClient=new AnyTourAndromedaClient(static function($url)use(&$pointCalls){
 ++$pointCalls;parse_str(parse_url($url,PHP_URL_QUERY),$q);
 return ['status'=>200,'body'=>json_encode($q['action']==='login'?['sid'=>'full_point_session']:['PAGE'=>1,'PAGES_COUNT'=>0,'PRICES'=>[]])];
},true);
$fullHandler=new AnyTourAndromedaSearch($pointState,static function($s)use(&$pointSaved){$pointSaved=$s;return true;},true,true);
$full=$fullHandler->start($point,'full_point',1,time(),$fullClient,'test','test');
if($full['status']!=='complete'||($pointSaved['store']['criteria']['HOTELS']??null)!=='2000042763')throw new RuntimeException('hotel criteria rejected by private store');
if($fullHandler->resume('full_point',1,time())!==$full||$pointCalls!==2)throw new RuntimeException('point store resume changed or spent API');
echo "Upstream hotel criteria retained through client, store and cached resume passed\n";
