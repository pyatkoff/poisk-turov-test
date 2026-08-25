<?php
/**
 * V2-only Tourvisor gateway.
 * V2 secrets live in v2/config.php on the server and are never committed.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$privateConfig=__DIR__.'/config.php';
if(is_file($privateConfig)) require_once $privateConfig;
function out($data,int $status=200):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function jwt():string{$token=trim((string)getenv('TOURVISOR_JWT'));if($token!=='')return $token;if(defined('TOURVISOR_JWT')&&trim((string)TOURVISOR_JWT)!=='')return trim((string)TOURVISOR_JWT);return '';}
function query_string(array $params):string{$parts=[];foreach($params as $key=>$value){if($value===null||$value==='')continue;if(is_bool($value))$value=$value?'true':'false';if(is_array($value)){foreach($value as $item){if($item===''||$item===null)continue;$parts[]=rawurlencode($key).'='.rawurlencode((string)$item);}}else{$parts[]=rawurlencode($key).'='.rawurlencode((string)$value);}}return implode('&',$parts);}
function tv_get(string $path,array $params=[]):array{$token=jwt();if($token==='')out(['ok'=>false,'error'=>'TOURVISOR_JWT is not configured for V2'],500);$url='https://api.tourvisor.ru/search/api/v1'.$path;$qs=query_string($params);if($qs!=='')$url.='?'.$qs;$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json']]);$body=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($errno)out(['ok'=>false,'error'=>'Tourvisor connection error','detail'=>$error],502);$decoded=json_decode((string)$body,true);if($code<200||$code>=300)out(['ok'=>false,'error'=>'Tourvisor HTTP '.$code,'data'=>$decoded??$body],$code>=400&&$code<600?$code:502);return is_array($decoded)?$decoded:['raw'=>$body];}
function request_array(string $key):array{$value=$_GET[$key]??[];if($value===''||$value===null)return[];return is_array($value)?array_values(array_filter($value,fn($v)=>$v!==''&&$v!==null)):[$value];}
function search_id():int{$id=(int)($_GET['searchId']??0);if($id<=0)out(['ok'=>false,'error'=>'searchId is required'],400);return $id;}
$action=(string)($_GET['action']??$_POST['action']??'health');
switch($action){
case'health':$data=tv_get('/departures');out(['ok'=>true,'source'=>'tourvisor-direct','departuresCount'=>is_array($data)?count($data):0]);
case'departures':out(tv_get('/departures',['departureCountryId'=>$_GET['departureCountryId']??1]));
case'countries':out(tv_get('/countries',['departureId'=>$_GET['departureId']??null,'onlyCharter'=>filter_var($_GET['onlyCharter']??false,FILTER_VALIDATE_BOOLEAN),'onlyDirect'=>filter_var($_GET['onlyDirect']??false,FILTER_VALIDATE_BOOLEAN)]));
case'arrivals':out(tv_get('/arrivals',['departureId'=>$_GET['departureId']??null,'onlyCharter'=>filter_var($_GET['onlyCharter']??false,FILTER_VALIDATE_BOOLEAN),'onlyDirect'=>filter_var($_GET['onlyDirect']??false,FILTER_VALIDATE_BOOLEAN)]));
case'regions':out(tv_get('/regions',['countryId'=>$_GET['countryId']??null,'arrivalId'=>$_GET['arrivalId']??null]));
case'subregions':out(tv_get('/subregions',['countryId'=>$_GET['countryId']??null,'regionId'=>$_GET['regionId']??null]));
case'meals':out(tv_get('/meals'));
case'operators':out(tv_get('/operators',['departureId'=>$_GET['departureId']??null,'countryId'=>$_GET['countryId']??null]));
case'hotel_types':out(tv_get('/hotel-types',['countryId'=>$_GET['countryId']??null]));
case'hotel_services':out(tv_get('/hotel-group-services',['countryId'=>$_GET['countryId']??null,'regionIds'=>request_array('regionIds')]));
case'hotels':out(tv_get('/hotels',['countryId'=>$_GET['countryId']??null,'regionId'=>$_GET['regionId']??null,'category'=>$_GET['category']??null,'types'=>request_array('types'),'rating'=>$_GET['rating']??null,'page'=>$_GET['page']??1,'limit'=>$_GET['limit']??100]));
case'dates':out(tv_get('/tours/dates',['departureId'=>$_GET['departureId']??null,'countryId'=>$_GET['countryId']??null,'arrivalId'=>$_GET['arrivalId']??null,'onlyCharter'=>filter_var($_GET['onlyCharter']??false,FILTER_VALIDATE_BOOLEAN)]));
case'search_start':out(tv_get('/tours/search',[
    'departureId'=>$_GET['departureId']??null,'countryId'=>$_GET['countryId']??null,'dateFrom'=>$_GET['dateFrom']??null,'dateTo'=>$_GET['dateTo']??null,
    'nightsFrom'=>$_GET['nightsFrom']??null,'nightsTo'=>$_GET['nightsTo']??null,'adults'=>$_GET['adults']??null,'childs'=>request_array('childs'),
    'meal'=>$_GET['meal']??null,'hotelCategory'=>$_GET['hotelCategory']??null,'hotelRating'=>$_GET['hotelRating']??null,'hotelTypes'=>request_array('hotelTypes'),
    'hotelIds'=>request_array('hotelIds'),'hotelServices'=>request_array('hotelServices'),'arrivalId'=>$_GET['arrivalId']??null,
    'regionIds'=>request_array('regionIds'),'subregionIds'=>request_array('subregionIds'),'operatorIds'=>request_array('operatorIds'),
    'priceFrom'=>$_GET['priceFrom']??null,'priceTo'=>$_GET['priceTo']??null,'currency'=>$_GET['currency']??'RUB',
    'onlyCharter'=>filter_var($_GET['onlyCharter']??false,FILTER_VALIDATE_BOOLEAN),'onlyDirect'=>filter_var($_GET['onlyDirect']??false,FILTER_VALIDATE_BOOLEAN)
]));
case'search_continue':$id=search_id();out(tv_get('/tours/search/'.$id.'/continue'));
case'search_status':$id=search_id();out(tv_get('/tours/search/'.$id.'/status',['operatorStatus'=>false]));
case'search_results':$id=search_id();out(tv_get('/tours/search/'.$id,['limit'=>$_GET['limit']??25]));
case'tour':$id=trim((string)($_GET['tourId']??''));if($id==='')out(['ok'=>false,'error'=>'tourId is required'],400);out(tv_get('/tours/'.rawurlencode($id),['currency'=>$_GET['currency']??'RUB']));
case'flights':$id=trim((string)($_GET['tourId']??''));if($id==='')out(['ok'=>false,'error'=>'tourId is required'],400);out(tv_get('/tours/'.rawurlencode($id).'/flights',['currency'=>$_GET['currency']??'RUB']));
case'rooms':out(tv_get('/rooms',['ids'=>request_array('ids')]));
default:out(['ok'=>false,'error'=>'Unknown V2 action'],404);
}
