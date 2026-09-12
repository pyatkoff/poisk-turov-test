<?php
declare(strict_types=1);
/** MATCH #1971 read-only Andromeda ANEX operator-key ↔ direct ANEX evidence. */
const HM_DATE='__MATCH_DATE__';
const HM_OPERATION='__MATCH_OPERATION__';
const HM_NIGHTS=7;
const HM_ANDROMEDA_OPERATOR='5';
const HM_PAGE_CAP=100;
const HM_HOTEL_CAP=5000;
function hm_fail(string $stage,string $reason,array $extra=[]):void{
 echo 'MATCH_ANEXKEY_JSON:'.json_encode(array_merge(['status'=>'failed','stage'=>$stage,'reason'=>$reason,'operation_id'=>HM_OPERATION,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'raw_provider_bodies_recorded'=>false,'token_values_recorded'=>false],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;exit(2);
}
function hm_text($v,int $max=180):string{if(!is_string($v))return'';$v=trim((string)(preg_replace('/\s+/u',' ',$v)??''));return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);}
function hm_collect_rows($node,array &$rows):void{
 if(!is_array($node))return;
 if(isset($node['operatorKey'],$node['hotelKey'],$node['hotel'])&&is_array($node['original']??null)&&isset($node['original']['hotelKey']))$rows[]=$node;
 foreach($node as $v)if(is_array($v))hm_collect_rows($v,$rows);
}
function hm_image_ids(string $url):?array{
 if($url===''||!preg_match('~/([0-9]+)\.([0-9]+)\.([0-9]+)\.(?:jpe?g|png)(?:\?|$)~i',$url,$m))return null;
 return ['operator'=>(int)$m[1],'anex'=>(int)$m[2],'andromeda'=>(int)$m[3]];
}
try{
 ini_set('display_errors','0');ini_set('log_errors','0');
 if(!preg_match('/^20\d\d-\d\d-\d\d$/D',HM_DATE)||!str_starts_with(HM_OPERATION,'hotel-match-andromeda-anex-direct-key-matrix-1971-'))hm_fail('bootstrap','unarmed_source');
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')hm_fail('bootstrap','server_root_invalid');
 $preview=$root.'/_preview/search3-anex-candidate';$api=$preview.'/api-andromeda-search3-preview.php';if(!is_file($api))hm_fail('bootstrap','andromeda_runtime_missing');require_once $api;
 $private=$preview.'/.andromeda-private.php';if(!is_file($private))hm_fail('bootstrap','andromeda_private_missing');$cfg=require $private;if(!is_array($cfg))hm_fail('bootstrap','andromeda_config_invalid');
 require_once $root.'/config.php';$dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbHelper;$pdo=v2_data_db();
 $app=is_file($preview.'/app/integrations/anex-search.php')?$preview.'/app/integrations':$root.'/app/integrations';foreach(['anex-search','anex-search-mapping-registry'] as $f)require_once $app.'/'.$f.'.php';
 $criteria=['departureId'=>1,'countryId'=>4,'dateFrom'=>HM_DATE,'dateTo'=>HM_DATE,'nightsFrom'=>HM_NIGHTS,'nightsTo'=>HM_NIGHTS,'adults'=>2,'childs'=>[],'currency'=>'RUB','meal'=>'','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],'arrivalId'=>'','operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[],'onlyDirect'=>false,'onlyCharter'=>false,'hotelCategory'=>'','hotelRating'=>'','priceFrom'=>'','priceTo'=>''];
 $out=['status'=>'completed','operation_id'=>HM_OPERATION,'criteria'=>['departure'=>'Moscow','country'=>'Turkey','date'=>HM_DATE,'nights'=>HM_NIGHTS,'adults'=>2,'children'=>0,'currency'=>'RUB'],'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'raw_provider_bodies_recorded'=>false,'token_values_recorded'=>false];
 $token=trim((string)getenv('ANEX_API_TOKEN'));if($token===''&&defined('ANEX_API_TOKEN'))$token=trim((string)ANEX_API_TOKEN);if($token==='')hm_fail('anex','credential_missing');
 $client=new AnyTourAnexClient($token);$cache=[];$core=anytour_anex_search3_core($criteria);$q=$pdo->prepare('SELECT d.name departure_name,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');$q->execute([1,4]);$names=$q->fetch(PDO::FETCH_ASSOC);if(!$names)hm_fail('anex','local_dictionary_names_missing');
 $core['supplier_namespace']='anex_online';$core['departure_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_TOWNFROMS',[],$cache),[$names['departure_name']]);$core['destination_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$core['departure_id']],$cache),[$names['country_name']]);
 $dated=['TOWNFROMINC'=>$core['departure_id'],'STATEINC'=>$core['destination_id'],'CHECKIN_BEG'=>str_replace('-','',$core['checkin_begin']),'CHECKIN_END'=>str_replace('-','',$core['checkin_end']),'ADULT'=>2,'CHILD'=>0];$core['currency_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
 $resolver=AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();$ar=anytour_anex_search3_prices($client,$resolver,$core);$anex=[];
 foreach($ar['offers'] as $offer){$h=$offer['hotel']??[];$id=(string)($h['external_id']??'');$name=hm_text($h['name']??'');if($id===''||$name==='')continue;if(!isset($anex[$id]))$anex[$id]=['anex_hotel_id'=>$id,'name'=>$name,'local_id'=>$h['local_id']??null];if(count($anex)>HM_HOTEL_CAP)hm_fail('anex','hotel_cap_exceeded');}
 $req=['generation'=>1,'page'=>1,'params'=>$criteria,'andromeda_operator_ids'=>[HM_ANDROMEDA_OPERATOR]];$saved=anytour_andromeda_search3_catalog($cfg,$req);$saved['excluded_operator_ids']=$cfg['excluded_operator_ids']??[];$base=anytour_andromeda_search3_params($req,$pdo,$saved);$budgetDir=dirname((string)$cfg['catalog_path']);$transport=new AnyTourAndromedaTransport(true);
 $mk=static function()use($transport,$budgetDir){return new AnyTourAndromedaClient(static function($url,$opts)use($transport,$budgetDir){anytour_andromeda_search3_budget($budgetDir);return $transport($url,$opts);},true);};
 $ac=$mk();$ac->login((string)$cfg['username'],(string)$cfg['password']);$session=$ac->privateSession();if(!$session)hm_fail('andromeda','private_session_missing');
 $page=1;$pages=1;$offers=0;$quarantine=[];$bridges=[];$pageStats=[];
 do{
  if($page>HM_PAGE_CAP)hm_fail('andromeda','page_cap_exceeded',['last_pages_count'=>$pages]);$params=$base;$params['PAGE']=$page;$pc=$page===1?$ac:$mk();if($page!==1)$pc->restorePrivateSession($session);$raw=$pc->price($params);$projection=AnyTourAndromedaNormalizer::page($raw,$params,'match-anexkey',1);$pages=(int)$projection['pages_count'];$rows=[];hm_collect_rows($raw,$rows);$offers+=count($rows);
  foreach($rows as $r){if((int)($r['operatorKey']??0)!==5)continue;$aid=(int)($r['original']['hotelKey']??0);$did=(int)($r['hotelKey']??0);$name=hm_text($r['hotel']??'');$operatorSide=(int)($r['isOperatorHotelKey']??0)===1;$img=hm_text($r['hotelImage']??'',500);$url=hm_text($r['hotelUrl']??'',500);$imageIds=hm_image_ids($img);$imageOk=$imageIds!==null&&$imageIds['operator']===5&&$imageIds['anex']===$aid&&$imageIds['andromeda']===$did;
   if($operatorSide||$aid<=0||$did<=0){$quarantine[]=['andromeda_hotel_id'=>$did?:null,'anex_hotel_id'=>$aid?:null,'name'=>$name,'reason'=>$operatorSide?'operator_key_namespace':'missing_key'];continue;}
   $key=$did.'|'.$aid;$direct=$anex[(string)$aid]??null;$bridges[$key]=['andromeda_hotel_id'=>(string)$did,'anex_hotel_id'=>(string)$aid,'andromeda_name'=>$name,'direct_anex_name'=>$direct['name']??null,'direct_anex_local_id'=>$direct['local_id']??null,'direct_anex_present'=>$direct!==null,'town'=>hm_text($r['town']??''),'star'=>hm_text($r['star']??''),'hotel_url'=>$url,'hotel_image'=>$img,'image_pattern_verified'=>$imageOk,'image_ids'=>$imageIds];if(count($bridges)>HM_HOTEL_CAP)hm_fail('andromeda','hotel_cap_exceeded');
  }
  $pageStats[]=['page'=>$page,'pages_count'=>$pages,'raw_operator_rows'=>count($rows),'bridge_union'=>count($bridges)];$page++;
 }while($page<=$pages);
 $byAnd=[];$byAnex=[];foreach($bridges as $b){$byAnd[$b['andromeda_hotel_id']][$b['anex_hotel_id']]=true;$byAnex[$b['anex_hotel_id']][$b['andromeda_hotel_id']]=true;}$conflicts=[];foreach($byAnd as $id=>$x)if(count($x)>1)$conflicts[]=['side'=>'andromeda','id'=>$id,'counterparts'=>array_keys($x)];foreach($byAnex as $id=>$x)if(count($x)>1)$conflicts[]=['side'=>'anex','id'=>$id,'counterparts'=>array_keys($x)];
 $rows=array_values($bridges);usort($rows,fn($a,$b)=>[(int)$a['anex_hotel_id'],(int)$a['andromeda_hotel_id']]<=>[(int)$b['anex_hotel_id'],(int)$b['andromeda_hotel_id']]);
 $out['providers']=['anex'=>['hotel_count'=>count($anex),'external_search_pending'=>(bool)($ar['external_search_pending']??false)],'andromeda'=>['pages_consumed'=>count($pageStats),'final_pages_count'=>$pages,'operator_rows'=>$offers,'bridge_count'=>count($rows),'quarantine_count'=>count($quarantine),'pages'=>$pageStats]];$out['bridges']=$rows;$out['quarantine']=$quarantine;$out['bridge_conflicts']=$conflicts;$out['counts']=['bridges'=>count($rows),'direct_anex_joined'=>count(array_filter($rows,fn($x)=>$x['direct_anex_present'])),'image_pattern_verified'=>count(array_filter($rows,fn($x)=>$x['image_pattern_verified'])),'conflicts'=>count($conflicts)];$out['provider_calls']=['andromeda_login'=>1,'andromeda_price_pages'=>count($pageStats),'direct_anex_searchtour'=>'one bounded normalized price response plus dictionaries','tourvisor'=>0];
 echo 'MATCH_ANEXKEY_JSON:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){hm_fail('runtime','provider_exception',['exception_class'=>get_class($e),'safe_message'=>hm_text($e->getMessage(),160)]);}
