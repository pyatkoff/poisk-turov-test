<?php
declare(strict_types=1);
/* MATCH identity evidence only. Existing client/transport, no database or bookings. */
require_once __DIR__.'/../../app/integrations/andromeda-client.php';
require_once __DIR__.'/../../app/integrations/andromeda-transport.php';
const MOB_OP='hotel-match-operator-original-batch-1971-20260915-v2';
const MOB_MAX_PRICE=120;
function mob_json(array $x): string {return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function mob_write(string $file,array $x): string {
    $raw=mob_json($x);$f=fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_output');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('output_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('output_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function mob_input(string $file,string $digest): array {if(!hash_equals($digest,hash_file('sha256',$file)))throw new RuntimeException('input_hash');$x=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);if(!is_array($x))throw new RuntimeException('input_shape');return $x;}
function mob_chunks(array $ids): array {
    $out=[];$part=[];foreach($ids as $id){if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,19}$/D',$id))throw new RuntimeException('target_id');if($part&&(count($part)>=30||strlen(implode(',',array_merge($part,[$id])))>300)){$out[]=$part;$part=[];}$part[]=$id;}if($part)$out[]=$part;return $out;
}
function mob_fact(array $r,array $wanted,string $operator,int $country,string $request,string $response): ?array {
    if((string)($r['operatorKey']??'')!==$operator||!isset($wanted[(string)($r['hotelKey']??'')]))throw new RuntimeException('supplier_filter_mismatch');
    if(!array_key_exists('isOperatorHotelKey',$r)||!in_array($r['isOperatorHotelKey'],[0,'0'],true))return null;
    $native=$r['original']['hotelKey']??null;$name=$r['hotel']??null;$original=$r['original']['hotel']??null;
    if((!is_int($native)&&!is_string($native))||!preg_match('/^[A-Za-z0-9_.-]{1,32}$/D',(string)$native)||(string)$native==='0'||!is_string($name)||trim($name)===''||strlen($name)>512||!is_string($original)||trim($original)===''||strlen($original)>512)return null;
    if(preg_match('/roulette|fortuna|фортуна|рулетк|excursion|экскурсион/ui',$name.' '.$original))return null;
    return ['operator_key'=>$operator,'native_hotel_id'=>(string)$native,'andromeda_hotel_id'=>(string)$r['hotelKey'],'country_id'=>$country,'hotel_name'=>$name,'original_name'=>$original,'town'=>is_string($r['town']??null)?mb_substr($r['town'],0,200):null,'operator_name'=>is_string($r['operator']??null)?mb_substr($r['operator'],0,200):null,'is_operator_hotel_key'=>false,'action'=>'price','request_sha256'=>$request,'response_sha256'=>$response];
}
function mob_inspect(array $rows,array $wanted,string $operator,int $country,string $request,string $response): array {
    $facts=[];$holds=[];$observations=[];
    foreach($rows as $i=>$r){
        if(!is_array($r)){$holds[]=['row'=>$i,'reason'=>'invalid_row'];continue;}
        $safe=[];foreach(['hotelKey','hotel','operatorKey','operator','isOperatorHotelKey'] as $k)if(isset($r[$k])&&is_scalar($r[$k]))$safe[$k]=is_string($r[$k])?mb_substr($r[$k],0,512):$r[$k];
        $safe['original']=array_intersect_key(is_array($r['original']??null)?$r['original']:[],['hotelKey'=>true,'hotel'=>true]);$observations[]=$safe;
        try{$f=mob_fact($r,$wanted,$operator,$country,$request,$response);}catch(RuntimeException $e){if($e->getMessage()!=='supplier_filter_mismatch')throw $e;$holds[]=['row'=>$i,'reason'=>'outside_requested_hotel_or_operator','identity'=>$safe];continue;}
        if($f===null){$holds[]=['row'=>$i,'reason'=>'unusable_or_operator_scoped_identity','identity'=>$safe];continue;}
        $f['source']='live_price';$facts[]=$f;
    }
    return ['facts'=>$facts,'holds'=>$holds,'observations'=>$observations];
}
function mob_plan(string $src): array {
    $current=mob_input($src.'/current/server/result.json','faaf39bf979dec33199765cbdf756b1b68c320fc4ca6135386dc0122ceee70ed');
    if($current['state']!=='completed_read_only')throw new RuntimeException('current_state');
    $eg=mob_input($src.'/egypt/all.json','01030bb9e23e0c87f8bed7c50628c8f56243b89f3e55a24151430766c1576641');
    $tr=mob_input($src.'/turkey/capture.json','f97b0dfc4e9ac605dfeefcf80702c45bfda07ca7eba82725047f5fd15f3c62a1');
    $operators=['5'=>'Anex Tour','315'=>'Fun&Sun','342'=>'Intourist','115'=>'Biblio Globus'];
    foreach([$eg['payload']['OPERATORS'],$tr['catalog']['payload']['OPERATORS']] as $rows){$dict=[];foreach($rows as $r)$dict[(string)$r['id']]=$r['name'];foreach($operators as $id=>$name)if(($dict[$id]??null)!==$name)throw new RuntimeException('saved_operator_binding');}
    $targets=[];foreach($current['targets'] as $t){if(($t['frequency']??0)<=0||!in_array($t['decision_status'],['accepted','pending'],true)||!in_array($t['country_id'],[1,4],true))continue;$targets[(string)$t['andromeda_hotel_id']]=$t;}
    $saved=mob_input($src.'/saved/price.json','c4e0722837b12a8c3979972a1145d6d0e43d50539efebbd7e68a416590dad6a9');
    $bar=mob_input($src.'/barcelo/result.json','99831abd548fd6a9da55fea7620019bec83d5cf2f2a1546e97d571791d91c327');
    $facts=[];$covered=[];
    foreach([[$saved['payload']['PRICES'],hash('sha256',mob_json($saved['params'])),'c4e0722837b12a8c3979972a1145d6d0e43d50539efebbd7e68a416590dad6a9'],[$bar['inspection']['rows'],hash('sha256',mob_json($bar['params'])),$bar['raw_response_sha256']]] as [$rows,$req,$hash]){
        foreach($rows as $r){$id=(string)($r['hotelKey']??'');if(!isset($targets[$id]))continue;$f=mob_fact($r,[$id=>true],'5',1,$req,$hash);if(!$f)continue;$k=$f['operator_key'].'|'.$f['native_hotel_id'].'|'.$id;$f['source']='saved_price';$facts[$k]=$f;$covered['5|'.$id]=true;}
    }
    $queries=[];foreach([1=>3,4=>5] as $country=>$state)foreach($operators as $op=>$name){$ids=[];foreach($targets as $id=>$t)if($t['country_id']===$country&&!isset($covered[$op.'|'.$id]))$ids[]=(string)$id;
        foreach(mob_chunks($ids) as $chunk){$p=['TOWNFROMINC'=>1,'STATEINC'=>$state,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>10,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'OPERATORS'=>(string)$op,'HOTELS'=>implode(',',$chunk),'GROUP_BY'=>32,'PACKETTYPE'=>0,'PAGE'=>1];AnyTourAndromedaClient::validatePriceParams($p);$queries[]=['country_id'=>$country,'operator_key'=>(string)$op,'hotel_ids'=>$chunk,'params'=>$p];}
    }
    if(count($targets)!==385||count($queries)>60)throw new RuntimeException('plan_scope');
    $prior=mob_input($src.'/previous/result.json','3db1ae69cbfa14d29bfd4780212628293936f2a920b5afea9c5441b88cb793f3');
    $consumed=hash('sha256',mob_json($queries[0]['params']));
    if($prior['operation_id']!=='hotel-match-operator-original-batch-1971-20260915-v1'||$prior['state']!=='stopped_no_retry'||$prior['price_calls']!==1||count($prior['facts'])!==6||$consumed!=='2d0fe9829779cd7535da8807ebfb5d13bd35238a38b4d7a0a2730e9e08ab1b73')throw new RuntimeException('prior_receipt_contract');
    foreach($prior['facts'] as $f){if($f['request_sha256']!==$consumed)throw new RuntimeException('prior_request');$key=$f['operator_key'].'|'.$f['native_hotel_id'].'|'.$f['andromeda_hotel_id'];$facts[$key]=$f;$covered[$f['operator_key'].'|'.$f['andromeda_hotel_id']]=true;}
    $previous=['operation_id'=>$prior['operation_id'],'result_sha256'=>'3db1ae69cbfa14d29bfd4780212628293936f2a920b5afea9c5441b88cb793f3','params'=>$queries[0]['params'],'request_sha256'=>$consumed,'response_sha256'=>$prior['http_calls'][1]['response_sha256'],'replayed'=>false];
    array_shift($queries);
    return ['target_count'=>count($targets),'operator_count'=>4,'target_operator_count'=>count($targets)*4,'saved_covered_count'=>count($covered),'saved_facts'=>array_values($facts),'queries'=>$queries,'query_count'=>count($queries),'previous_request'=>$previous,'current_result_sha256'=>'faaf39bf979dec33199765cbdf756b1b68c320fc4ca6135386dc0122ceee70ed'];
}
function mob_run(string $src,string $out): void {
    $reservation=json_decode(file_get_contents($out.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);$sha=getenv('GITHUB_SHA')?:'';
    if(($reservation['operation_id']??null)!==MOB_OP||($reservation['source_sha']??null)!==$sha||($reservation['state']??null)!=='reserved_before_supplier_access'||getenv('GITHUB_RUN_ATTEMPT')!=='1')throw new RuntimeException('reservation');
    $plan=mob_plan($src);mob_write($out.'/plan.json',$plan);$facts=[];foreach($plan['saved_facts'] as $f)$facts[$f['operator_key'].'|'.$f['native_hotel_id'].'|'.$f['andromeda_hotel_id']]=$f;
    $calls=[];$pages=[];$requests=0;$last=0.0;$responseDigest='';$status='completed';$phase='login';$seen=[];
    $transport=function(string $url,array $opts)use(&$calls,&$last,&$responseDigest):array{
        parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$action=$q['action']??'';if(!in_array($action,['login','price'],true))throw new RuntimeException('action');
        if(count($calls)>=MOB_MAX_PRICE+1)throw new RuntimeException('call_budget');$wait=1.1-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$last=microtime(true);
        $i=count($calls);$calls[]=['action'=>$action,'status'=>null];$t=new AnyTourAndromedaTransport(true);$r=$t($url,$opts);$calls[$i]['status']=$r['status'];
        if($action==='price'){$responseDigest=hash('sha256',$r['body']);$calls[$i]['response_sha256']=$responseDigest;}return $r;
    };
    try{
        $client=new AnyTourAndromedaClient($transport,true);$client->login(getenv('ANDROMEDA_USERNAME')?:'',getenv('ANDROMEDA_PASSWORD')?:'');$session=$client->privateSession();
        foreach($plan['queries'] as $qi=>$query){$wanted=array_fill_keys($query['hotel_ids'],true);$resolved=[];$phase='price';
            for($page=1;$page<=2;$page++){
                if($requests>=MOB_MAX_PRICE)throw new RuntimeException('price_budget');$params=$query['params'];$params['PAGE']=$page;$digest=hash('sha256',mob_json($params));if(isset($seen[$digest]))throw new RuntimeException('request_replay');$seen[$digest]=true;
                mob_write($out.'/request-'.str_pad((string)$requests,3,'0',STR_PAD_LEFT).'.json',['operation_id'=>MOB_OP,'request_sha256'=>$digest,'params'=>$params,'state'=>'reserved_before_supplier_access']);$requests++;
                $client=new AnyTourAndromedaClient($transport,true);$client->restorePrivateSession($session);$reply=$client->price($params);$valid=0;$held=0;$observed=[];
                $inspection=mob_inspect($reply['PRICES'],$wanted,$query['operator_key'],$query['country_id'],$digest,$responseDigest);$observed=$inspection['facts'];$held=count($inspection['holds']);$valid=count($observed);foreach($observed as $f){$key=$f['operator_key'].'|'.$f['native_hotel_id'].'|'.$f['andromeda_hotel_id'];$facts[$key]=$f;$resolved[$f['andromeda_hotel_id']]=true;}
                $row=['query_index'=>$qi,'page'=>$page,'operator_key'=>$query['operator_key'],'country_id'=>$query['country_id'],'hotel_ids'=>$query['hotel_ids'],'params'=>$params,'returned_rows'=>count($reply['PRICES']),'pages_count'=>$reply['PAGES_COUNT'],'valid_rows'=>$valid,'held_rows'=>$held,'request_sha256'=>$digest,'response_sha256'=>$responseDigest,'facts'=>$observed,'holds'=>$inspection['holds'],'observations'=>$inspection['observations']];
                mob_write($out.'/response-'.str_pad((string)($requests-1),3,'0',STR_PAD_LEFT).'.json',$row);unset($row['facts'],$row['observations']);$pages[]=$row;
                echo json_encode(['query'=>$qi,'page'=>$page,'operator'=>$query['operator_key'],'rows'=>count($reply['PRICES']),'valid'=>$valid,'unique_total'=>count($facts)])."\n";
                // Non-target or missing-original rows stay quarantined, never auto-mapped.
                if(count($resolved)===count($wanted)||$page>=$reply['PAGES_COUNT'])break;
            }
        }
    }catch(Throwable $e){$status='stopped_no_retry';$error=['phase'=>$phase,'class'=>get_class($e),'code'=>preg_match('/^[A-Za-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'sanitized_failure'];}
    $result=['operation_id'=>MOB_OP,'source_sha'=>$sha,'state'=>$status,'error'=>$error??null,'plan_summary'=>array_diff_key($plan,['queries'=>true,'saved_facts'=>true]),'supplier_calls'=>count($calls),'price_calls'=>$requests,'http_calls'=>$calls,'pages'=>$pages,'facts'=>array_values($facts),'unique_pair_count'=>count($facts),'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'all_calls'=>0,'booking_calls'=>0,'no_replay'=>true];
    $digest=mob_write($out.'/result.json',$result);mob_write($out.'/receipt.json',['operation_id'=>MOB_OP,'source_sha'=>$sha,'state'=>$status,'result_sha256'=>$digest,'readback_verified'=>true,'supplier_calls'=>count($calls),'database_writes'=>0,'no_replay'=>true]);echo json_encode(['state'=>$status,'unique_pairs'=>count($facts),'price_calls'=>$requests])."\n";
    if($status!=='completed')exit(2);
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){$mode=$argv[1]??'';if($mode==='plan'){echo mob_json(mob_plan($argv[2]));}elseif($mode==='run'){mob_run($argv[2],$argv[3]);}else{throw new RuntimeException('mode');}}
