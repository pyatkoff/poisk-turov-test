<?php
declare(strict_types=1);
const HOTEL=15851,REGION=20,OP='int-anex-hotel15851-current-20260919-v1';
const URL='https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php';

function req(int $g):array{return['generation'=>$g,'params'=>[
'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-09-19','dateTo'=>'2026-09-22','nightsFrom'=>7,'nightsTo'=>10,
'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[(string)HOTEL],
'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[(string)REGION],'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>'',
'priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false]];}

function best(array $d):?array{
 if(($d['provider']??null)!=='anex'||!preg_match('/^[a-f0-9]{32}$/D',(string)($d['search_ref']??'')))return null;$b=null;
 foreach(($d['hotels']??[])as$h){if(!is_array($h)||($h['local_id']??null)!==HOTEL)continue;
  foreach(($h['tours']??[])as$t){$p=$t['price']??null;$a=is_array($p)&&($p['currency']??null)==='RUB'?(string)($p['amount']??''):'';
   if(!is_array($t)||($t['kind']??null)!=='concrete'||($t['search_ref']??null)!==$d['search_ref']
    ||!preg_match('/^anex_online:[a-f0-9]{64}$/D',(string)($t['offer_ref']??''))
    ||!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$a)||!preg_match('/[1-9]/',$a))continue;
   $r=['search_ref'=>$d['search_ref'],'offer_ref'=>$t['offer_ref'],'local_hotel_id'=>HOTEL,'search_price'=>$a,
    'checkin'=>$t['checkin']??null,'nights'=>$t['nights']??null,'flight_type'=>$t['flight_type']??null];
   if($b===null||(float)$a<(float)$b['search_price'])$b=$r;
  }
 }return$b;
}
function summary(array $p):array{
 $d=$p['data']??null;if(($p['ok']??null)!==true||!is_array($d))return['ok'=>false,'error'=>$p['error']??'invalid_response'];
 $hc=0;$tc=0;$cc=0;$names=[];foreach(($d['hotels']??[])as$h){if(!is_array($h))continue;++$hc;
  if(($h['local_id']??null)===HOTEL&&is_string($h['name']??null))$names[]=mb_substr(trim($h['name']),0,120,'UTF-8');
  foreach(($h['tours']??[])as$t){if(!is_array($t))continue;++$tc;if(($t['kind']??null)==='concrete')++$cc;}}
 return['ok'=>true,'provider'=>$d['provider']??null,'date_range'=>$d['date_range']??null,'hotel_count'=>$hc,'tour_count'=>$tc,
  'concrete_tour_count'=>$cc,'target_hotel_present'=>$names!==[],'target_hotel_names'=>array_values(array_unique($names)),
  'external_search_pending'=>$d['external_search_pending']??null];
}
function http(array $p,string $cookie):array{
 $ch=curl_init(URL);if($ch===false)throw new RuntimeException('HTTP_INIT');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($p,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
  CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>90,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,
  CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Origin: https://anytoour.ru',
  'Referer: https://anytoour.ru/_preview/search3-local-candidate/poisk-turov/','Sec-Fetch-Site: same-origin',
  'X-Requested-With: AnyTourSearch3','User-Agent: AnyTour-INT-Acceptance/1']]);
 $raw=curl_exec($ch);$e=curl_errno($ch);$s=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 return['status'=>$s,'errno'=>$e,'body'=>is_string($raw)?$raw:''];
}
function put(string $dir,string $name,string $bytes):string{
 $f=fopen($dir.'/'.$name,'x');if($f===false)throw new RuntimeException('WRITE_OPEN');
 try{if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f)||(function_exists('fsync')&&!fsync($f)))throw new RuntimeException('WRITE');}
 finally{fclose($f);}$h=hash('sha256',$bytes);if(!hash_equals($h,(string)hash_file('sha256',$dir.'/'.$name)))throw new RuntimeException('READBACK');return$h;
}
function dbs(PDO $db,string $start):array{
 $q=$db->prepare("SELECT provider,COUNT(*) n,SUM(is_active=1 AND expires_at>UTC_TIMESTAMP()) cur,
 SUM(is_active=1 AND expires_at>UTC_TIMESTAMP() AND final_price_ready=1) ready,
 SUM(is_active=1 AND expires_at>UTC_TIMESTAMP() AND final_price_verified=1) verified,
 SUM(last_seen_at>=:st) fresh,MIN(display_price) lo,MAX(display_price) hi,MAX(last_seen_at) seen,MAX(expires_at) exp
 FROM anytour_offers WHERE anytour_hotel_id=:h AND checkin BETWEEN '2026-09-19' AND '2026-09-22'
 AND nights BETWEEN 7 AND 10 AND adults=2 AND children=0 GROUP BY provider ORDER BY provider");
 $q->execute(['st'=>$start,'h'=>HOTEL]);$rows=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r)$rows[]=[
 'provider'=>$r['provider'],'rows_total'=>(int)$r['n'],'current_rows'=>(int)$r['cur'],'current_ready'=>(int)$r['ready'],
 'current_verified'=>(int)$r['verified'],'seen_since_start'=>(int)$r['fresh'],'min_price'=>$r['lo'],'max_price'=>$r['hi'],
 'latest_seen'=>$r['seen'],'latest_expiry'=>$r['exp']];
 $q=$db->prepare("SELECT COUNT(*) n,MAX(completed_at) t FROM anytour_offer_refreshes WHERE provider='anex' AND status='completed' AND completed_at>=:st");
 $q->execute(['st'=>$start]);$x=$q->fetch(PDO::FETCH_ASSOC)?:['n'=>0,'t'=>null];
 return['offers'=>$rows,'anex_completed_refreshes_since_start'=>(int)$x['n'],'anex_latest_completed_since_start'=>$x['t']];
}
function cat(PDO $db):array{
 $q=$db->prepare('SELECT profile_json,profile_sha256,revision,is_active,updated_at FROM anytour_hotels WHERE id=? LIMIT 1');$q->execute([HOTEL]);$r=$q->fetch(PDO::FETCH_ASSOC);$p=null;
 if(is_array($r)&&hash_equals((string)$r['profile_sha256'],hash('sha256',(string)$r['profile_json'])))try{$p=json_decode((string)$r['profile_json'],true,64,JSON_THROW_ON_ERROR);}catch(Throwable){}
 $imgs=[];if(is_array($p))foreach([$p['primaryImage']??null,...(is_array($p['images']??null)?$p['images']:[])]as$v)if(is_string($v)&&str_starts_with($v,'https://'))$imgs[$v]=1;
 $q=$db->prepare('SELECT namespace,external_key,acquired_via,last_seen_at FROM anytour_hotel_sources WHERE anytour_hotel_id=? ORDER BY namespace,external_key LIMIT 50');$q->execute([HOTEL]);$src=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC)as$x){$v=(string)$x['external_key'];$src[]=['namespace'=>$x['namespace'],'external_key'=>preg_match('/^[1-9][0-9]{0,18}$/D',$v)?$v:null,
 'external_key_sha256'=>hash('sha256',$v),'acquired_via'=>$x['acquired_via'],'last_seen_at'=>$x['last_seen_at']];}
 return['exists'=>is_array($r),'active'=>is_array($r)&&(int)$r['is_active']===1,'revision'=>is_array($r)?(int)$r['revision']:null,
 'updated_at'=>$r['updated_at']??null,'name'=>is_array($p)?($p['name']??null):null,'country'=>is_array($p)?($p['country']['name']??$p['countryName']??null):null,
 'region'=>is_array($p)?($p['region']['name']??$p['regionName']??null):null,'image_count'=>count($imgs),'sources'=>$src];
}
function rt(string $root):array{
 $a=['endpoint'=>$root.'/_preview/search3-anex-candidate/api-anex-search3-preview.php',
 'preview_autosave'=>$root.'/_preview/search3-anex-candidate/app/integrations/anex-anytour-offer-autosave.php',
 'shared_autosave'=>$root.'/app/integrations/anex-anytour-offer-autosave.php'];$o=[];
 foreach($a as$k=>$p)$o[$k]=is_file($p)&&!is_link($p)?['present'=>true,'sha256'=>hash_file('sha256',$p),'bytes'=>filesize($p)]:['present'=>false,'sha256'=>null,'bytes'=>null];return$o;
}
function selftest():void{
 $p=req(158511919);if($p['params']['hotelIds']!==['15851']||$p['params']['regionIds']!==['20'])throw new RuntimeException('TEST1');
 $ref=str_repeat('a',32);$d=['provider'=>'anex','search_ref'=>$ref,'hotels'=>[['local_id'=>HOTEL,'name'=>'X','tours'=>[
 ['kind'=>'concrete','offer_ref'=>'anex_online:'.str_repeat('b',64),'search_ref'=>$ref,'price'=>['amount'=>'200000','currency'=>'RUB']],
 ['kind'=>'concrete','offer_ref'=>'anex_online:'.str_repeat('c',64),'search_ref'=>$ref,'price'=>['amount'=>'150000','currency'=>'RUB']]]]]];
 if((best($d)['search_price']??null)!=='150000')throw new RuntimeException('TEST2');
 if((summary(['ok'=>true,'data'=>$d])['target_hotel_present']??false)!==true)throw new RuntimeException('TEST3');
 if(best(['provider'=>'anex','search_ref'=>$ref,'hotels'=>[]])!==null)throw new RuntimeException('TEST4');
 echo "ANEX_CURRENT_HOTEL15851_SELFTEST_OK checks=4\n";
}
function main(string $root,string $ledger):array{
 if(realpath($root)!==$root||basename($root)!=='anytoour.ru'||is_link($root)||!is_dir($ledger)||is_link($ledger)||basename($ledger)!==OP)throw new RuntimeException('ROOT');
 $_SERVER['DOCUMENT_ROOT']=$root;$f=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once$f;$db=v2_data_db();if(!$db instanceof PDO)throw new RuntimeException('DB');
 $start=gmdate('Y-m-d H:i:s');$g=158511919;$cookie=$ledger.'/session.cookies';
 $out=['operation'=>OP,'started_at'=>$start,'scope'=>req($g)['params'],'catalog'=>cat($db),'runtime'=>rt($root),'before'=>dbs($db,$start),
 'http'=>['search'=>null,'batch'=>null],'best'=>null,'batch'=>null,'after'=>null,'status'=>'reserved','diagnostic_db_writes'=>0,'lead_calls'=>0,
 'booking_calls'=>0,'offer_calls'=>0,'expand_calls'=>0,'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0];
 put($ledger,'reservation.json',json_encode(['operation'=>OP,'started_at'=>$start],JSON_THROW_ON_ERROR)."\n");
 $h=http(req($g),$cookie);$out['http']['search']=['status'=>$h['status'],'errno'=>$h['errno'],'sha256'=>put($ledger,'search.raw.json',$h['body'])];
 try{$j=json_decode($h['body'],true,64,JSON_THROW_ON_ERROR);}catch(Throwable){$j=null;}
 $out['search']=is_array($j)?summary($j):['ok'=>false,'error'=>'invalid_json'];$d=is_array($j)&&($j['ok']??null)===true&&is_array($j['data']??null)?$j['data']:null;
 $b=is_array($d)?best($d):null;$out['best']=$b;
 if($h['status']===200&&$b!==null){$bp=['action'=>'additional_prices_batch','generation'=>$g,'search_ref'=>$b['search_ref'],'items'=>[['offer_ref'=>$b['offer_ref'],'local_hotel_id'=>HOTEL]]];
  $x=http($bp,$cookie);$out['http']['batch']=['status'=>$x['status'],'errno'=>$x['errno'],'sha256'=>put($ledger,'batch.raw.json',$x['body'])];
  try{$bj=json_decode($x['body'],true,64,JSON_THROW_ON_ERROR);}catch(Throwable){$bj=null;}$i=is_array($bj)&&($bj['ok']??null)===true?($bj['data']['offers'][0]??null):null;
  $out['batch']=is_array($i)?['status'=>$i['status']??null,'finalPriceReady'=>$i['finalPriceReady']??null,'finalPrice'=>$i['finalPrice']??null,
  'price'=>$i['price']??null,'retryable'=>$i['retryable']??null,'retry_reason'=>$i['retry_reason']??null]:['status'=>'no_item'];}
 sleep(2);$out['after']=dbs($db,$start);$out['status']='completed_terminal';
 put($ledger,'result.json',json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");return$out;
}
if(PHP_SAPI==='cli'){try{
 if(($argv[1]??'')==='--self-test'){selftest();exit;}
 if(count($argv)!==3)throw new RuntimeException('ARGS');$r=main(realpath($argv[1])?:'',realpath($argv[2])?:'');
 echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,"ANEX_CURRENT_HOTEL15851_FAILED ".preg_replace('/[^A-Z0-9_:-]+/i','_',substr($e->getMessage(),0,100))."\n");exit(2);}}
