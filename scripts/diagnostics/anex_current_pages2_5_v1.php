<?php
declare(strict_types=1);
const OP='int-anex-current-pages2-5-20260919-v1';
function put(string $d,string $n,string $b):string{$f=fopen($d.'/'.$n,'x');if(!$f)throw new RuntimeException('WRITE');fwrite($f,$b);fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$b);}
function main(string $root,string $ledger):array{
 $_SERVER['DOCUMENT_ROOT']=$root;$preview=$root.'/_preview/search3-anex-candidate';$api=$preview.'/api-anex-search3-preview.php';$private=$preview.'/.anex-private.php';
 if(!is_file($api)||!is_file($private))throw new RuntimeException('RUNTIME');require_once$private;require_once$api;
 $dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once$dbf;$db=v2_data_db();
 $token=trim((string)getenv('ANEX_API_TOKEN'));if($token===''&&defined('ANEX_API_TOKEN'))$token=trim((string)ANEX_API_TOKEN);if($token==='')throw new RuntimeException('TOKEN');
 $names=$db->query("SELECT d.name departure_name,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=1 AND c.id=4 AND d.is_active=1 AND c.is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
 if(!$names)throw new RuntimeException('DESTINATION');$client=new AnyTourAnexClient($token);$cache=[];
 $dep=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_TOWNFROMS',[],$cache),[$names['departure_name']]);
 $dated=['TOWNFROMINC'=>$dep,'CHECKIN_BEG'=>'20260919','CHECKIN_END'=>'20260922','ADULT'=>2,'CHILD'=>0];
 $state=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$dep],$cache),[$names['country_name']]);$dated['STATEINC']=$state;
 $currency=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
 $ctx=['supplier_namespace'=>'anex_online','departure_id'=>$dep,'destination_id'=>$state,'currency_id'=>$currency,'checkin_begin'=>'2026-09-19','checkin_end'=>'2026-09-22','nights_from'=>7,'nights_till'=>10,'adults'=>2,'children'=>0,'child_ages'=>[]];
 $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);$resolver=$registry->previewResolver();
 $q=$db->query("SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE country_id=4 AND last_checkin_from='2026-09-19' AND last_checkin_to='2026-09-22' AND last_seen_utc>='2026-09-19 15:10:18'");
 $seen=[];foreach($q->fetchAll(PDO::FETCH_COLUMN)as$id)$seen[(string)$id]=1;$all=$seen;$pages=[];$lastDigest=null;$target=[];
 for($page=2;$page<=5;$page++){
  $params=['TOWNFROMINC'=>$dep,'STATEINC'=>$state,'CURRENCY'=>$currency,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>10,'ADULT'=>2,'CHILD'=>0,
   'CHECKIN_BEG'=>'20260919','CHECKIN_END'=>'20260922','FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>$page,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
  $raw=$client->request('SearchTour_PRICES',$params);$bytes=json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  $sha=put($ledger,'page'.$page.'.raw.json',$bytes."\n");$norm=anytour_anex_normalize_prices($raw,$ctx,$resolver,[]);
  $hotels=[];$mapped=[];$new=[];$matches=[];
  foreach($norm['offers']as$o){$id=(string)$o['hotel']['external_id'];$hotels[$id]=1;if(is_int($o['hotel']['local_id']??null))$mapped[(string)$o['hotel']['local_id']]=1;
   if(!isset($all[$id]))$new[$id]=1;$all[$id]=1;$name=(string)($o['hotel']['name']??'');
   if(stripos($name,'IC HOTELS RESIDENCE')!==false)$matches[$id]=['anex_hotel_id'=>$id,'name'=>$name,'resolved_catalog_hotel_id'=>$o['hotel']['local_id']??null];}
  $digest=hash('sha256',implode(',',array_keys($hotels)));$repeat=$lastDigest!==null&&hash_equals($lastDigest,$digest);$lastDigest=$digest;
  $pages[]=['page'=>$page,'raw_sha256'=>$sha,'offers'=>count($norm['offers']),'unique_hotels'=>count($hotels),'mapped_catalog_hotels'=>count($mapped),
   'new_vs_previous_pages'=>count($new),'rejected_count'=>$norm['rejected_count'],'external_search_pending'=>$norm['external_search_pending'],'repeated_previous_page'=>$repeat,'target_matches'=>array_values($matches)];
  foreach($matches as$m)$target[$m['anex_hotel_id']]=$m;
  if(!$norm['offers']||$repeat)break;
 }
 return['operation'=>OP,'page1_observed_hotels'=>count($seen),'pages'=>$pages,'total_unique_hotels_page1_plus_new'=>count($all),'target_matches'=>array_values($target),
  'supplier_calls'=>3+count($pages),'supplier_pages_replayed'=>0,'db_writes'=>0,'apd_calls'=>0,'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0];
}
if(PHP_SAPI==='cli'){try{if(count($argv)!==3)throw new RuntimeException('ARGS');$root=realpath($argv[1]);$ledger=realpath($argv[2]);if(!$root||!$ledger||basename($root)!=='anytoour.ru'||basename($ledger)!==OP)throw new RuntimeException('ROOT');echo json_encode(main($root,$ledger),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable$e){fwrite(STDERR,"ANEX_PAGES2_5_FAILED ".preg_replace('/[^A-Z0-9_:-]+/i','_',substr($e->getMessage(),0,100))."\n");exit(2);}}
