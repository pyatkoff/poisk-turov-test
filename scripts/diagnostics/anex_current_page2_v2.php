<?php
declare(strict_types=1);
const OP='int-anex-page2-current-20260919-v2';
function put(string$d,string$n,string$b):string{$f=fopen($d.'/'.$n,'x');if(!$f)throw new RuntimeException('WRITE');fwrite($f,$b);fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$b);}
function main(string$root,string$ledger):array{
 $_SERVER['DOCUMENT_ROOT']=$root;$config=$root.'/config.php';if(!is_file($config)||is_link($config))throw new RuntimeException('CONFIG');require_once$config;$preview=$root.'/_preview/search3-anex-candidate';$api=$preview.'/api-anex-search3-preview.php';if(!is_file($api)||is_link($api))throw new RuntimeException('API_RUNTIME');require_once$api;
 $app=is_file($preview.'/app/integrations/anex-search.php')?$preview.'/app/integrations':$root.'/app/integrations';require_once$app.'/anex-search.php';require_once$app.'/anex-search-mapping-registry.php';
 $dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once$dbf;$db=v2_data_db();
 $token=trim((string)getenv('ANEX_API_TOKEN'));if($token===''&&defined('ANEX_API_TOKEN'))$token=trim((string)ANEX_API_TOKEN);if($token==='')throw new RuntimeException('TOKEN');
 $client=new AnyTourAnexClient($token);$cache=[];$dep=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_TOWNFROMS',[],$cache),['Москва','Moscow']);
 $dated=['TOWNFROMINC'=>$dep,'CHECKIN_BEG'=>'20260919','CHECKIN_END'=>'20260922','ADULT'=>2,'CHILD'=>0];
 $state=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$dep],$cache),['Турция','Turkey']);$dated['STATEINC']=$state;
 $currency=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($client,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
 $params=['TOWNFROMINC'=>$dep,'STATEINC'=>$state,'CURRENCY'=>$currency,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>10,'ADULT'=>2,'CHILD'=>0,'CHECKIN_BEG'=>'20260919','CHECKIN_END'=>'20260922','FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>2,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
 $raw=$client->request('SearchTour_PRICES',$params);$bytes=json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$sha=put($ledger,'page2.raw.json',$bytes."\n");
 $ctx=['supplier_namespace'=>'anex_online','departure_id'=>$dep,'destination_id'=>$state,'currency_id'=>$currency,'checkin_begin'=>'2026-09-19','checkin_end'=>'2026-09-22','nights_from'=>7,'nights_till'=>10,'adults'=>2,'children'=>0,'child_ages'=>[]];
 $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);$norm=anytour_anex_normalize_prices($raw,$ctx,$registry->previewResolver(),[]);
 $q=$db->query("SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE country_id=4 AND last_checkin_from='2026-09-19' AND last_checkin_to='2026-09-22' AND last_seen_utc>='2026-09-19 15:10:18'");
 $page1=[];foreach($q->fetchAll(PDO::FETCH_COLUMN)as$id)$page1[(string)$id]=1;$hotels=[];$mapped=[];$new=[];$target=[];
 foreach($norm['offers']as$o){$id=(string)$o['hotel']['external_id'];$hotels[$id]=1;if(is_int($o['hotel']['local_id']??null))$mapped[(string)$o['hotel']['local_id']]=1;if(!isset($page1[$id]))$new[$id]=1;$name=(string)($o['hotel']['name']??'');if(stripos($name,'IC HOTELS RESIDENCE')!==false)$target[$id]=['anex_hotel_id'=>$id,'name'=>$name,'resolved_catalog_hotel_id'=>$o['hotel']['local_id']??null];}
 return['operation'=>OP,'page'=>2,'raw_sha256'=>$sha,'offers'=>count($norm['offers']),'unique_hotels'=>count($hotels),'mapped_catalog_hotels'=>count($mapped),'page1_observed_hotels'=>count($page1),'new_hotels_vs_page1'=>count($new),'external_search_pending'=>$norm['external_search_pending'],'rejected_count'=>$norm['rejected_count'],'target_matches'=>array_values($target),'supplier_calls'=>4,'page1_replayed'=>false,'db_writes'=>0,'apd_calls'=>0,'expand_calls'=>0,'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0];
}
if(PHP_SAPI==='cli'){try{if(count($argv)!==3)throw new RuntimeException('ARGS');$root=realpath($argv[1]);$ledger=realpath($argv[2]);if(!$root||!$ledger||basename($root)!=='anytoour.ru'||basename($ledger)!==OP)throw new RuntimeException('ROOT');echo json_encode(main($root,$ledger),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable$e){fwrite(STDERR,"ANEX_PAGE2_FAILED ".preg_replace('/[^A-Z0-9_:-]+/i','_',substr($e->getMessage(),0,120))."\n");exit(2);}}
