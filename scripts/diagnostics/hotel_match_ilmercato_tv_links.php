<?php
declare(strict_types=1);
/* A single IL Mercato Tourvisor URL capture; no site, DB or mapping writes. */
const ITV_OP='hotel-match-ilmercato-tv-links-1971-20260915-v1';
const ITV_TARGET=2904;
function itv_public_url($s):?string{if(!is_string($s)||strlen($s)>8192)return null;$p=parse_url($s);if(!$p||!in_array(strtolower($p['scheme']??''),['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;parse_str($p['query']??'',$q);foreach(array_keys($q) as $k)if(preg_match('/token|password|secret|session|auth|login|credential|bearer/i',(string)$k))return null;return $s;}
function itv_row(array $h):array{$o=array_intersect_key($h,array_fill_keys(['id','name','country','region','subRegion','latitude','longitude'],true));if(isset($h['hotelDescriptionLink'])){$u=itv_public_url($h['hotelDescriptionLink']);$o['hotelDescriptionLink']=$u;if(!$u)$o['link_unavailable_or_sensitive']=true;}return $o;}
function itv_intourist(array $o):bool{return preg_match('/intourist|интурист/ui',implode(' ',array_filter([$o['name']??null,$o['russianName']??null,$o['fullName']??null],'is_string')))===1;}
function itv_query(array $params):string{foreach($params as $k=>$v)if(is_bool($v))$params[$k]=$v?'true':'false';return http_build_query($params,'','&',PHP_QUERY_RFC3986);}
function itv_get(string $path,array $params,string $token,array &$out):array{
    if(count($out['calls'])>=10)throw new RuntimeException('call_budget');
    if(!preg_match('~^/(operators|tours/search|tours/search/[1-9][0-9]*(/status)?|tours/[A-Za-z0-9_.-]+)$~D',$path))throw new RuntimeException('endpoint_guard');
    $query=itv_query($params);$url='https://api.tourvisor.ru/search/api/v1'.$path.($query!==''?'?'.$query:'');
    $delay=1.2-(microtime(true)-($out['_last_call']??0));if($delay>0)usleep((int)ceil($delay*1000000));$out['_last_call']=microtime(true);
    $headers=[];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>40,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_HEADERFUNCTION=>static function($c,$h)use(&$headers){$n=strlen($h);$a=explode(':',$h,2);if(count($a)===2&&preg_match('/^(retry-after|x-ratelimit[^:]*|ratelimit[^:]*)$/i',trim($a[0])))$headers[strtolower(trim($a[0]))]=trim($a[1]);return $n;}]);
    $i=count($out['calls']);$out['calls'][]=['path'=>$path,'params'=>$params,'status'=>null,'state'=>'requested_no_retry'];fwrite(STDERR,json_encode(['call'=>$i,'path'=>$path,'state'=>'before_http'])."\n");
    $raw=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $out['calls'][$i]=['path'=>$path,'params'=>$params,'status'=>$status,'body_sha256'=>is_string($raw)?hash('sha256',$raw):null,'rate_headers'=>$headers,'state'=>'response_received'];
    if($errno||!is_string($raw))throw new RuntimeException('network_stop_no_retry');
    if($status!==200)throw new RuntimeException('http_'.$status.'_stop_no_retry');
    if(isset($headers['retry-after']))throw new RuntimeException('retry_after_stop');
    foreach($headers as $k=>$v)if(str_contains($k,'remaining')&&ctype_digit($v)&&(int)$v<10-count($out['calls']))throw new RuntimeException('advertised_remaining_budget_insufficient');
    if(strlen($raw)>32*1024*1024)throw new RuntimeException('response_size');$d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($d))throw new RuntimeException('response_shape');return $d;
}
function itv_target(array $rows):?array{foreach($rows as $r)if(is_array($r)&&(string)($r['id']??'')==='2904'&&preg_match('/mercato/i',(string)($r['name']??'')))return $r;return null;}
function itv_run():array{
    if(PHP_SAPI!=='cli'||(getenv('MATCH_OPERATION_ID')?:'')!==ITV_OP||!preg_match('/^[0-9a-f]{40}$/D',getenv('MATCH_SOURCE_SHA')?:''))throw new RuntimeException('environment_guard');
    $home=rtrim((string)getenv('HOME'),'/');$root=realpath($home.'/www/anytoour.ru');if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');
    $out=['operation_id'=>ITV_OP,'source_sha'=>getenv('MATCH_SOURCE_SHA'),'state'=>'incomplete_read_only','target_hotel_id'=>ITV_TARGET,'calls'=>[],'new_searches'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'site_changes'=>0,'no_replay'=>true,'budget'=>['max_http_calls'=>10,'max_new_searches'=>1,'minimum_interval_seconds'=>1.2,'stop_on_quota_or_auth_error'=>true,'daily_remaining_not_assumed'=>true]];
    $db=null;$phase='saved_handle_read';
    try{
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');require_once $bootstrap;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        $q=$db->prepare('SELECT id,name,country_id FROM catalog_hotels WHERE id=?');$q->execute([ITV_TARGET]);$hotel=$q->fetch(PDO::FETCH_ASSOC);if(!$hotel||!preg_match('/mercato/i',$hotel['name'])||(int)$hotel['country_id']!==1)throw new RuntimeException('local_control_mismatch');$out['local_control']=$hotel;
        $q=$db->prepare('SELECT search_id,tour_id,operator_id,observed_at,country_id,departure_id FROM tour_price_observations WHERE hotel_id=? AND tour_id IS NOT NULL AND search_id IS NOT NULL ORDER BY observed_at DESC LIMIT 200');$q->execute([ITV_TARGET]);$saved=$q->fetchAll(PDO::FETCH_ASSOC);$db->rollBack();$out['saved_tour_handle_count']=count($saved);
        $phase='existing_token';$client=$root.(is_file($root.'/data/tourvisor-client-v1.php')?'/data/tourvisor-client-v1.php':'/v2/data/tourvisor-client-v1.php');if(!is_file($client)||is_link($client))throw new RuntimeException('existing_client_missing');require_once $client;$token=v2_data_tourvisor_token();if($token==='')throw new RuntimeException('token_missing');$out['existing_client_sha256']=hash_file('sha256',$client);$out['credential_source']='existing_server_TOURVISOR_JWT';
        $phase='operator_dictionary';$dict=itv_get('/operators',['departureId'=>1,'countryId'=>1],$token,$out);$matches=array_values(array_filter($dict,static fn($x)=>is_array($x)&&itv_intourist($x)));if(count($matches)!==1)throw new RuntimeException('operator_dictionary_ambiguous');$operator=$matches[0];$op=(int)$operator['id'];$out['tourvisor_operator']=$operator;
        $savedTour=null;foreach($saved as $s)if((int)$s['operator_id']===$op&&(int)$s['country_id']===1&&strtotime($s['observed_at'])>time()-86400){$savedTour=$s;break;}
        $h=null;$sid=null;
        if($savedTour){$phase='saved_provider_result';$sid=(string)$savedTour['search_id'];$out['saved_handle']=$savedTour;$reply=itv_get('/tours/search/'.$sid,['limit'=>500],$token,$out);$h=itv_target($reply);$out['saved_result_target_found']=$h!==null;}
        if(!$h){
            $phase='one_hotel_new_search';$params=['departureId'=>1,'countryId'=>1,'dateFrom'=>'2026-09-23','dateTo'=>'2026-09-23','nightsFrom'=>8,'nightsTo'=>8,'adults'=>2,'hotelIds'=>'2904','operatorIds'=>(string)$op,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
            $out['new_searches']=1;$reply=itv_get('/tours/search',$params,$token,$out);$sid=(string)($reply['searchId']??'');if(!preg_match('/^[1-9][0-9]*$/D',$sid))throw new RuntimeException('search_id_missing');$out['new_search_id']=$sid;
            $phase='search_status';for($p=0;$p<5;$p++){sleep(5);$s=itv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false],$token,$out);$out['last_search_status']=$s;if((float)($s['progress']??0)>=100)break;}
            $phase='single_hotel_result';$reply=itv_get('/tours/search/'.$sid,['limit'=>1],$token,$out);$h=itv_target($reply);if(!$h)throw new RuntimeException('target_not_returned');
        }
        $out['tourvisor_hotel']=itv_row($h);$out['tourvisor_hotel_search_id']=$sid;$link=$out['tourvisor_hotel']['hotelDescriptionLink']??null;$host=$link?strtolower((string)parse_url($link,PHP_URL_HOST)):'';
        if($host!=='intourist.ru'&&!str_ends_with($host,'.intourist.ru')){
            $tour=null;foreach($h['tours']??[] as $t)if(is_array($t)&&(int)($t['operator']['id']??0)===$op){$tour=$t;break;}
            $tid=(string)($tour['id']??($savedTour['tour_id']??''));if(!preg_match('/^[A-Za-z0-9_.-]{1,250}$/D',$tid))throw new RuntimeException('intourist_tour_missing');
            $phase='intourist_detail';$detail=itv_get('/tours/'.$tid,['currency'=>'RUB'],$token,$out);
            if((int)($detail['hotel']['id']??0)!==ITV_TARGET||(int)($detail['operator']['id']??0)!==$op||!itv_intourist($detail['operator']??[]))throw new RuntimeException('detail_identity_mismatch');
            $out['intourist_detail']=['tour_id'=>$tid,'hotel'=>itv_row($detail['hotel']),'operator'=>$detail['operator'],'operatorLink'=>itv_public_url($detail['operatorLink']??null)];
            if(!$out['intourist_detail']['operatorLink'])throw new RuntimeException('operator_url_unavailable_or_sensitive');
        }
        $out['state']='completed_read_only';$out['outcome']='target_links_captured';
    }catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['state']='stopped_read_only_no_retry';$out['phase']=$phase;$out['reason']=preg_match('/^[A-Za-z0-9_]+$/D',$e->getMessage())?$e->getMessage():get_class($e);}
    unset($out['_last_call']);$out['supplier_calls']=count($out['calls']);return $out;
}
if(($argv[1]??'')==='--self-test'){$n=0;$check=static function($v)use(&$n){if(!$v)throw new RuntimeException('self_test');$n++;};$check(itv_public_url('https://example.test/hotel/12114')!==null);$check(itv_public_url('https://example.test/?token=x')===null);$check(itv_public_url('https://u:p@example.test/hotel')===null);$check(itv_public_url('file:///etc/passwd')===null);$check(itv_query(['operatorStatus'=>false,'limit'=>1])==='operatorStatus=false&limit=1');$check(itv_intourist(['name'=>'Intourist']));$check(!itv_intourist(['name'=>'ANEX']));$check(itv_target([['id'=>2904,'name'=>'IL MERCATO HOTEL']])!==null);$check(itv_target([['id'=>2904,'name'=>'OTHER']])===null);$check(itv_target([['id'=>29,'name'=>'IL MERCATO']])===null);echo "$n target URL tests PASS\n";exit;}
try{$r=itv_run();echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";exit($r['state']==='completed_read_only'?0:2);}catch(Throwable $e){echo json_encode(['operation_id'=>ITV_OP,'state'=>'failed_before_execution','error_class'=>get_class($e),'supplier_calls'=>0,'no_replay'=>true])."\n";exit(2);}
