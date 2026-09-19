<?php
declare(strict_types=1);
const OP='int-local-db-reader-postfix-20260919-v1';
function params():array{return[
 'departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-09-19','dateTo'=>'2026-09-22','nightsFrom'=>7,'nightsTo'=>10,'adults'=>2,'childs'=>[],
 'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>null,'regionIds'=>['20'],
 'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>null,'priceTo'=>null,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];}
function http(string$url,string$method='GET',?string$body=null,array$headers=[]):array{$c=curl_init($url);$h=array_merge(['Accept: application/json,text/html','User-Agent: AnyTour-INT-Reader-Acceptance/1'],$headers);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>$h,CURLOPT_CUSTOMREQUEST=>$method]);if($body!==null)curl_setopt($c,CURLOPT_POSTFIELDS,$body);$b=curl_exec($c);$e=curl_errno($c);$s=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return['status'=>$s,'errno'=>$e,'body'=>is_string($b)?$b:''];}
function summary(array$d):array{$counts=(array)($d['providerOfferCounts']??[]);ksort($counts);$providers=[];foreach($counts as$k=>$v)$providers[$k]=(int)$v;return[
 'source'=>$d['source']??null,'scopeVersion'=>$d['scopeVersion']??null,'scopeDigest'=>$d['scopeDigest']??null,'matchMode'=>$d['matchMode']??null,'partial'=>$d['partial']??null,
 'hotelCount'=>(int)($d['hotelCount']??0),'eligibleHotelCount'=>(int)($d['eligibleHotelCount']??0),'offerCount'=>(int)($d['offerCount']??0),
 'storedOfferCount'=>(int)($d['storedOfferCount']??0),'withheldOfferCount'=>(int)($d['withheldOfferCount']??0),'categoryFilteredOfferCount'=>(int)($d['categoryFilteredOfferCount']??0),
 'providerOfferCounts'=>$providers,'selectionAuthority'=>$d['selectionAuthority']??null,'sourceScopeDigests'=>array_values($d['sourceScopeDigests']??[]),
 'hotel_ids'=>array_values(array_map(fn($h)=>(int)($h['anytourHotelId']??0),array_slice($d['hotels']??[],0,5000)))];}
function main(string$root):array{
 $_SERVER['DOCUMENT_ROOT']=$root;$preview=$root.'/_preview/search3-local-candidate';$reader=$preview.'/data/search3-local-results-read-v1.php';
 if(!is_file($reader)||is_link($reader))throw new RuntimeException('READER_FILE');require_once$reader;
 $direct=search3_local_results_build(v2_data_db(),params(),new DateTimeImmutable('now',new DateTimeZone('UTC')));
 $body=json_encode(['params'=>params()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 $h=http('https://anytoour.ru/_preview/search3-local-candidate/data/search3-local-results-read-v1.php','POST',$body,['Content-Type: application/json','X-Requested-With: AnyTourSearch3']);
 $j=json_decode($h['body'],true,64,JSON_THROW_ON_ERROR);if($h['status']!==200||($j['ok']??null)!==true||!is_array($j['data']??null))throw new RuntimeException('HTTP_READER');
 $public=$j['data'];$page=http('https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/');
 $provider=$preview.'/search3-local-db-provider-v1.js';$assets=$preview.'/assets.php';$providerBytes=is_file($provider)&&!is_link($provider)?file_get_contents($provider):false;$assetsBytes=is_file($assets)&&!is_link($assets)?file_get_contents($assets):false;
 $directSummary=summary($direct);$publicSummary=summary($public);
 return['operation'=>OP,'direct'=>$directSummary,'http'=>['status'=>$h['status'],'body_sha256'=>hash('sha256',$h['body']),'summary'=>$publicSummary],
  'same_scope_digest'=>is_string($directSummary['scopeDigest'])&&hash_equals($directSummary['scopeDigest'],(string)$publicSummary['scopeDigest']),
  'same_offer_count'=>$directSummary['offerCount']===$publicSummary['offerCount'],'same_hotel_count'=>$directSummary['hotelCount']===$publicSummary['hotelCount'],
  'same_provider_counts'=>$directSummary['providerOfferCounts']===$publicSummary['providerOfferCounts'],
  'target15851_visible'=>in_array(15851,$directSummary['hotel_ids'],true),
  'runtime'=>['reader_sha256'=>hash_file('sha256',$reader),'provider_file_present'=>$providerBytes!==false,'provider_sha256'=>$providerBytes===false?null:hash('sha256',$providerBytes),
   'provider_points_to_reader'=>$providerBytes!==false&&str_contains($providerBytes,'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php'),
   'assets_mentions_provider'=>$assetsBytes!==false&&str_contains($assetsBytes,'search3-local-db-provider-v1'),
   'page_http'=>$page['status'],'page_mentions_provider'=>str_contains($page['body'],'search3-local-db-provider-v1')],
  'provider_calls'=>0,'db_writes'=>0,'runtime_writes'=>0,'lead_calls'=>0,'booking_calls'=>0];
}
if(PHP_SAPI==='cli'){try{if(count($argv)!==2)throw new RuntimeException('ARGS');$root=realpath($argv[1]);if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('ROOT');echo json_encode(main($root),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable$e){fwrite(STDERR,"LOCAL_DB_READER_POSTFIX_FAILED ".preg_replace('/[^A-Z0-9_:-]+/i','_',substr($e->getMessage(),0,120))."\n");exit(2);}}
