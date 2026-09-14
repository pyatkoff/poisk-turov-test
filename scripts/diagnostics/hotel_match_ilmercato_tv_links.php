<?php
declare(strict_types=1);
/* One never-attempted saved Intourist tour detail. No searches or database writes. */
const ITV_OP='hotel-match-ilmercato-tv-links-1971-20260915-v3';
const ITV_PRIOR='324a3a6d87e3f8b10ac541747f1fd0bc14b093a32883d092abeccfad70784c6f';
const ITV_HANDLE='caedc7d8efcef4fcf2691ff0f16a76278e41b1824382bc3d38a3f26b029c3b81';
const ITV_TOUR='43277415737179';
const ITV_TARGET=2904;
const ITV_OPERATOR=43; // Independently recorded by the completed v1 /operators response.
function itv_public_url($s):?string{if(!is_string($s)||strlen($s)>8192)return null;$p=parse_url($s);if(!$p||!in_array(strtolower($p['scheme']??''),['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;parse_str($p['query']??'',$q);foreach(array_keys($q) as $k)if(preg_match('/token|password|secret|session|auth|login|credential|bearer/i',(string)$k))return null;return $s;}
function itv_row(array $h):array{$o=array_intersect_key($h,array_fill_keys(['id','name','country','region','subRegion','latitude','longitude'],true));if(array_key_exists('hotelDescriptionLink',$h)){$u=itv_public_url($h['hotelDescriptionLink']);$o['hotelDescriptionLink']=$u;if(!$u)$o['link_unavailable_or_sensitive']=true;}return $o;}
function itv_intourist(array $o):bool{return preg_match('/intourist|интурист/ui',implode(' ',array_filter([$o['name']??null,$o['russianName']??null,$o['fullName']??null],'is_string')))===1;}
function itv_query(array $params):string{foreach($params as $k=>$v)if(is_bool($v))$params[$k]=$v?'true':'false';return http_build_query($params,'','&',PHP_QUERY_RFC3986);}
function itv_get(string $path,array $params,string $token,array &$out):array{
    if(count($out['calls'])>=1)throw new RuntimeException('call_budget');
    if($path!=='/tours/'.ITV_TOUR||$params!==['currency'=>'RUB'])throw new RuntimeException('exact_detail_guard');
    $query=itv_query($params);$url='https://api.tourvisor.ru/search/api/v1'.$path.($query!==''?'?'.$query:'');
    $requestHash=hash('sha256',$path.'?'.$query);foreach($out['calls'] as $old)if(($old['request_sha256']??'')===$requestHash)throw new RuntimeException('detail_replay');
    $delay=1.2-(microtime(true)-($out['_last_call']??0));if($delay>0)usleep((int)ceil($delay*1000000));$out['_last_call']=microtime(true);
    $headers=[];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>40,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_HEADERFUNCTION=>static function($c,$h)use(&$headers){$n=strlen($h);$a=explode(':',$h,2);if(count($a)===2&&preg_match('/^(retry-after|x-ratelimit[^:]*|ratelimit[^:]*)$/i',trim($a[0])))$headers[strtolower(trim($a[0]))]=trim($a[1]);return $n;}]);
    $i=count($out['calls']);$entry=['path'=>$path,'params'=>$params,'request_sha256'=>$requestHash,'status'=>null,'state'=>'requested_no_retry'];$out['calls'][]=$entry;
    fwrite(STDERR,json_encode(['operation_id'=>ITV_OP,'call'=>$i,'request'=>$entry])."\n");fflush(STDERR);
    $raw=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $out['calls'][$i]=array_merge($entry,['status'=>$status,'body_sha256'=>is_string($raw)?hash('sha256',$raw):null,'rate_headers'=>$headers,'state'=>'response_received']);
    if($errno||!is_string($raw))throw new RuntimeException('network_stop_no_retry');
    if($status!==200)throw new RuntimeException('http_'.$status.'_stop_no_retry');
    if(isset($headers['retry-after']))throw new RuntimeException('retry_after_stop');
    foreach($headers as $k=>$v)if(str_contains($k,'remaining')&&ctype_digit($v)&&(int)$v<1-count($out['calls']))throw new RuntimeException('advertised_remaining_budget_insufficient');
    if(strlen($raw)>32*1024*1024)throw new RuntimeException('response_size');$d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($d))throw new RuntimeException('response_shape');return $d;
}
function itv_target(array $rows):?array{foreach($rows as $r)if(is_array($r)&&(string)($r['id']??'')==='2904'&&preg_match('/mercato/i',(string)($r['name']??'')))return $r;return null;}
function itv_run():array{
    if(PHP_SAPI!=='cli'||(getenv('MATCH_OPERATION_ID')?:'')!==ITV_OP||!preg_match('/^[0-9a-f]{40}$/D',getenv('MATCH_SOURCE_SHA')?:'')||(getenv('MATCH_PRIOR_RESULT_SHA')?:'')!==ITV_PRIOR||(getenv('MATCH_HANDLE_RESULT_SHA')?:'')!==ITV_HANDLE)throw new RuntimeException('environment_guard');
    $root=realpath(rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru');if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');
    $out=['operation_id'=>ITV_OP,'source_sha'=>getenv('MATCH_SOURCE_SHA'),'prior_result_sha256'=>ITV_PRIOR,'state'=>'incomplete_read_only','target_hotel_id'=>ITV_TARGET,'tourvisor_operator'=>['id'=>ITV_OPERATOR,'name'=>'Интурист','source'=>'verified_v1_dictionary'],'calls'=>[],'new_searches'=>0,'andromeda_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'site_changes'=>0,'no_replay'=>true,'budget'=>['max_http_calls'=>1,'max_new_searches'=>0,'minimum_interval_seconds'=>1.2,'stop_on_quota_or_auth_error'=>true,'daily_remaining_not_assumed'=>true]];
    $phase='existing_token';
    try{
        $client=$root.(is_file($root.'/data/tourvisor-client-v1.php')?'/data/tourvisor-client-v1.php':'/v2/data/tourvisor-client-v1.php');if(!is_file($client)||is_link($client))throw new RuntimeException('existing_client_missing');
        if(hash_file('sha256',$client)!=='03764ec6732eb7a698b5090fee20675438db1fd43c5f26ae85b9c8c374d46167')throw new RuntimeException('existing_client_changed');
        require_once $client;$token=v2_data_tourvisor_token();if($token==='')throw new RuntimeException('token_missing');$out['credential_source']='existing_server_TOURVISOR_JWT';
        $phase='saved_intourist_detail';$detail=itv_get('/tours/'.ITV_TOUR,['currency'=>'RUB'],$token,$out);
        $out['observed_detail_identity']=['hotel_id'=>$detail['hotel']['id']??null,'hotel_name'=>$detail['hotel']['name']??null,'operator_id'=>$detail['operator']['id']??null];
        if((int)($detail['hotel']['id']??0)!==ITV_TARGET||!preg_match('/mercato/i',(string)($detail['hotel']['name']??''))||(int)($detail['operator']['id']??0)!==ITV_OPERATOR||!itv_intourist($detail['operator']??[]))throw new RuntimeException('detail_identity_mismatch');
        $out['intourist_detail']=['tour_id'=>ITV_TOUR,'hotel'=>itv_row($detail['hotel']),'operator'=>$detail['operator'],'operatorLink'=>itv_public_url($detail['operatorLink']??null)];
        if(array_key_exists('hotelDescriptionLink',$detail))$out['top_level_hotelDescriptionLink']=itv_public_url($detail['hotelDescriptionLink']);
        if(!$out['intourist_detail']['operatorLink'])throw new RuntimeException('operator_url_unavailable_or_sensitive');
        $out['state']='completed_read_only';$out['outcome']='target_links_captured';
    }catch(Throwable $e){$out['state']='stopped_read_only_no_retry';$out['phase']=$phase;$out['reason']=preg_match('/^[A-Za-z0-9_]+$/D',$e->getMessage())?$e->getMessage():get_class($e);}
    unset($out['_last_call']);$out['supplier_calls']=count($out['calls']);return $out;
}
if(($argv[1]??'')==='--self-test'){$n=0;$check=static function($v)use(&$n){if(!$v)throw new RuntimeException('self_test');$n++;};$check(itv_public_url('https://example.test/hotel/12114')!==null);$check(itv_public_url('https://example.test/?token=x')===null);$check(itv_public_url('https://u:p@example.test/hotel')===null);$check(itv_public_url('file:///etc/passwd')===null);$check(itv_query(['operatorStatus'=>false,'limit'=>1])==='operatorStatus=false&limit=1');$check(itv_intourist(['name'=>'Intourist']));$check(!itv_intourist(['name'=>'ANEX']));$check(itv_target([['id'=>2904,'name'=>'IL MERCATO HOTEL']])!==null);$check(itv_target([['id'=>2904,'name'=>'OTHER']])===null);$check(itv_target([['id'=>29,'name'=>'IL MERCATO']])===null);$check(ITV_TOUR==='43277415737179');$check(itv_row(['id'=>2904,'name'=>'IL Mercato'])===['id'=>2904,'name'=>'IL Mercato']);echo "$n target URL tests PASS\n";exit;}
try{$r=itv_run();echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";exit($r['state']==='completed_read_only'?0:2);}catch(Throwable $e){echo json_encode(['operation_id'=>ITV_OP,'state'=>'failed_before_execution','error_class'=>get_class($e),'supplier_calls'=>0,'no_replay'=>true])."\n";exit(2);}
