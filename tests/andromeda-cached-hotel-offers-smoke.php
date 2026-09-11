<?php
declare(strict_types=1);
require $argv[1].'/v2/api-andromeda-search3-preview.php';

$checks=0;
function cache_check(bool $ok): void { global $checks; ++$checks; if(!$ok)throw new RuntimeException('cache_check_'.$checks); }
function cache_remove(string $path): void {
    if(is_file($path)||is_link($path)){unlink($path);return;}
    if(!is_dir($path))return;
    foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')cache_remove($path.'/'.$name);
    rmdir($path);
}

$root=$argv[2];cache_remove($root);mkdir($root.'/searches',0700,true);
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE catalog_departures(id INTEGER,name TEXT,is_active INTEGER)');
$pdo->exec('CREATE TABLE catalog_hotels(id INTEGER,name TEXT,country_id INTEGER,country_name TEXT,region_id INTEGER,region_name TEXT,subregion_id INTEGER,subregion_name TEXT,category INTEGER,rating REAL,is_active INTEGER,primary_image_url TEXT)');
$pdo->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace TEXT,external_hotel_id TEXT,local_hotel_id INTEGER,decision_status TEXT)');
$pdo->exec("INSERT INTO catalog_departures VALUES(1,'Москва',1)");
$pdo->exec("INSERT INTO catalog_hotels VALUES(900,'Cache Hotel',4,'Турция',10,'Анталья',11,'Кемер',5,4.7,1,NULL)");
$pdo->exec("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','3414',900,'accepted')");
$saved=['local_country_id'=>4,'townfrom'=>['payload'=>['TOWNFROM'=>[['id'=>1,'name'=>'Москва']]]],
    'all'=>['params'=>['STATEINC'=>5],'payload'=>['HOTELS'=>[['id'=>3414,'name'=>'Cache Hotel']],'OPERATORS'=>[]]],
    'excluded_operator_ids'=>[]];
$params=['departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-09-22','dateTo'=>'2026-09-22','nightsFrom'=>7,'nightsTo'=>7,
    'adults'=>2,'childs'=>[],'meal'=>'7','currency'=>'RUB','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],
    'arrivalId'=>'','operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[],'onlyDirect'=>false,'onlyCharter'=>false,
    'hotelCategory'=>'','hotelRating'=>'','priceFrom'=>'','priceTo'=>''];
$generation=3;$session='cache-session';$baseRequest=['generation'=>$generation,'page'=>1,'params'=>$params];
$criteria=anytour_andromeda_search3_params($baseRequest,$pdo,$saved);$refBase=$criteria;unset($refBase['PAGE']);
$ref=hash('sha256','paged-v1'.$session.json_encode($refBase));$now=time();
$resolver=AnyTourAndromedaHotelResolver::fromRows([['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'3414',
    'decision_status'=>'accepted','catalog_hotel_id'=>'900','existing_catalog_hotel_id'=>'900']],str_repeat('a',64));
$makeState=static function(int $page,int $pages,string $id,int $price,string $room)use($criteria,$ref,$generation,$now,$resolver):array{
    $storeState=[];$store=new AnyTourAndromedaOfferStore($storeState,true);$store->begin($ref,$generation,$now);
    $pageCriteria=$criteria;$pageCriteria['PAGE']=$page;
    $row=['id'=>$id,'hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,'price'=>$price,'currency'=>'RUB','currencyKey'=>643,
        'checkIn'=>'22.09.2026','nights'=>'7','hotel'=>'Cache Hotel','operator'=>'Anex Tour','meal'=>'AI','mealKey'=>6,
        'room'=>$room,'htplace'=>'DBL','adult'=>'2','child'=>'0'];
    $projection=$store->capture(['PAGE'=>$page,'PAGES_COUNT'=>$pages,'PRICES'=>[$row]],$pageCriteria,$ref,$generation,$now+1,$resolver);
    return ['version'=>1,'search_ref'=>$ref,'generation'=>$generation,'status'=>$page<$pages?'partial':'complete',
        'criteria'=>$pageCriteria,'store'=>$storeState,'error'=>null,'projection'=>$projection];
};
$one=$makeState(1,2,'private-offer-one',120000,'Standard');$two=$makeState(2,2,'private-offer-two',110000,'Superior');
$oneSave=$one;unset($oneSave['projection']);$twoSave=$two;unset($twoSave['projection']);
anytour_andromeda_search3_save($root.'/searches/'.$ref.'-1.json',$oneSave);
anytour_andromeda_search3_save($root.'/searches/'.$ref.'-'.$oneSave['store']['created_at'].'-2.json',$twoSave);
$seed=['provider'=>'andromeda','search_ref'=>$ref,'generation'=>$generation,'page'=>1,'offer_ref'=>$one['projection']['offers'][0]['offer_ref']];
$request=$baseRequest;$request['action']='hotel_offers';$request['hotel_scope']=['local_id'=>900,'seed'=>$seed];
$config=['catalog_path'=>$root.'/catalog.json','username'=>'unused','password'=>'unused'];
$result=anytour_andromeda_search3_run($request,$pdo,$saved,$config,$session);
cache_check($result['provider']==='andromeda'&&$result['grouped']===false&&$result['page']===1&&$result['pages_count']===1);
cache_check(count($result['hotels'])===1&&$result['hotels'][0]['local_id']===900&&count($result['hotels'][0]['tours'])===2);
cache_check(array_column($result['hotels'][0]['tours'],'room')===['Superior','Standard']);
cache_check(array_column(array_column($result['hotels'][0]['tours'],'offer_context'),'page')===[2,1]);
foreach($result['hotels'][0]['tours'] as $tour)cache_check(!isset($tour['offer_context']['hotel_scope']));
$json=json_encode($result,JSON_THROW_ON_ERROR);cache_check(!str_contains($json,'private-offer-one')&&!str_contains($json,'private-offer-two'));
cache_check(!file_exists($root.'/monthly-requests.json'));
cache_check(count(glob($root.'/searches/*.json'))===2);

$seedRequest=$request;unset($seedRequest['hotel_scope']);$seedRequest['offer_context']=$seed;$seedRequest['page']=1;
unlink($root.'/searches/'.$ref.'-'.$oneSave['store']['created_at'].'-2.json');
cache_check(anytour_andromeda_search3_cached_hotel_offers($request,$pdo,$saved,$config,$session,$seedRequest)===null);
anytour_andromeda_search3_save($root.'/searches/'.$ref.'-'.$oneSave['store']['created_at'].'-2.json',$twoSave);
$pdo->exec("UPDATE andromeda_hotel_identities SET decision_status='rejected'");
cache_check(anytour_andromeda_search3_cached_hotel_offers($request,$pdo,$saved,$config,$session,$seedRequest)===null);
cache_check(!file_exists($root.'/monthly-requests.json'));

echo 'Andromeda cached hotel offers: '.$checks." checks passed; complete retained search uses supplier requests=0.\n";
