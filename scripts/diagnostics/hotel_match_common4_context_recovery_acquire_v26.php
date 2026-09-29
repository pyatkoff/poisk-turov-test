<?php
declare(strict_types=1);

const C4V26_OP='hotel-match-common4-context-recovery-acquire-1971-20260925-v26';
const C4V26_POST_OP='hotel-match-samo-business-live30-common4-postwrite-1971-20260925-v24';
const C4V26_CENSUS_OP='hotel-match-common4-retained-context-census-1971-20260925-v25';
const C4V26_HTTP_CAP=500;
const C4V26_PAGE_CAP=1000;
const C4V26_MONTHLY_LIMIT=5000000;

function c4v26_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function c4v26_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function c4v26_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);c4v26_need(is_array($v),'json_shape');return$v;}
function c4v26_save(string $p,array $v):string{$raw=c4v26_json($v)."\n";$f=@fopen($p,'x+b');c4v26_need($f!==false,'exclusive_create');try{c4v26_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))c4v26_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function c4v26_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function c4v26_norm(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');$v=str_replace('ё','е',$v);return trim(preg_replace('/\s+/u',' ',$v)??$v);}
function c4v26_private_config(string $root):array{
    foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){
        if(!is_file($p)||is_link($p))continue;$v=require$p;
        if(is_array($v)&&($v['enabled']??false)===true&&is_string($v['catalog_path']??null)&&$v['catalog_path']!==''&&is_string($v['username']??null)&&is_string($v['password']??null))return$v;
    }
    throw new RuntimeException('andromeda_private_config_missing');
}
function c4v26_catalog_files(string $catalogPath):array{
    $files=[];if(is_file($catalogPath)&&!is_link($catalogPath))$files[]=$catalogPath;
    foreach(glob(dirname($catalogPath).'/countries/*.json')?:[] as $p)if(is_file($p)&&!is_link($p))$files[]=$p;
    $files=array_values(array_unique($files));sort($files,SORT_STRING);return$files;
}
function c4v26_moscow_departure(string $catalogPath):int{
    $ids=[];foreach(c4v26_catalog_files($catalogPath) as $p){$v=json_decode((string)file_get_contents($p),true);if(!is_array($v))continue;
        foreach(($v['townfrom']['payload']['TOWNFROM']??[]) as $r){if(!is_array($r))continue;$id=c4v26_id($r['id']??null);if($id===null)continue;
            foreach(['name','lName'] as $k){$n=c4v26_norm((string)($r[$k]??''));if(in_array($n,['moscow','moskva','москва'],true)){$ids[(int)$id]=true;break;}}}}
    $x=array_keys($ids);sort($x,SORT_NUMERIC);c4v26_need(count($x)===1,'moscow_departure_binding');return(int)$x[0];
}
function c4v26_budget(string $private,int $call,string $action):void{
    $p=$private.'/monthly-requests.json';$lock=fopen($p.'.lock','c');c4v26_need($lock!==false&&flock($lock,LOCK_EX),'budget_lock');
    try{$month=gmdate('Y-m');$v=is_file($p)?c4v26_load($p):[];if(($v['month']??'')!==$month)$v=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>C4V26_MONTHLY_LIMIT,'scope'=>'this_integration'];
        $used=(int)($v['reserved_requests']??0);$limit=(int)($v['monthly_limit']??C4V26_MONTHLY_LIMIT);c4v26_need($limit===C4V26_MONTHLY_LIMIT&&$used<$limit,'monthly_quota');
        $v['reserved_requests']=$used+1;$v['last_match_operation']=C4V26_OP;$v['last_match_call']=$call;$v['last_match_action']=$action;
        $tmp=$p.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,c4v26_json($v));chmod($tmp,0600);rename($tmp,$p);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function c4v26_targets(array $post):array{
    c4v26_need(($post['operation']??'')===C4V26_POST_OP&&($post['state']??'')==='samo_business_live30_common4_plan_ready','post_state');
    c4v26_need(($post['deduped_missing_lane_counts']??null)===['operator_5'=>1516,'operator_115'=>1218,'operator_315'=>1304,'operator_342'=>667],'post_missing_counts');
    c4v26_need((int)($post['deduped_lane_state_conflicts']??-1)===0,'post_conflicts');
    $out=[5=>[],315=>[]];
    foreach(($post['rows']??[]) as $r){
        if(!is_array($r)||($r['mapping_state']??'')!=='mapped_unique'||($r['saved_catalog_state']??'')!=='saved_catalog_ready')continue;
        $catalog=c4v26_id($r['andromeda_catalog_id']??null);$local=(int)($r['local_hotel_id']??0);$state=(int)($r['saved_stateinc']??0);
        if($catalog===null||$local<1)continue;
        foreach([[5,3,'operator_5'],[315,5,'operator_315']] as [$op,$wantedState,$ns]){
            if($state!==$wantedState)continue;$lane=$r['operator_lanes'][$ns]??null;
            if(is_array($lane)&&($lane['status']??'')==='missing'){$out[$op][$catalog]=$local;}
        }
    }
    c4v26_need(count($out[5])===286,'op5_state3_target_count');
    c4v26_need(count($out[315])===1100,'op315_state5_target_count');
    return$out;
}
function c4v26_validate_census(array $c):void{
    c4v26_need(($c['operation']??'')===C4V26_CENSUS_OP&&($c['state']??'')==='completed_supplier_free_retained_context_census','census_state');
    $t5=$c['top_contexts_by_operator']['5'][0]??null;$t315=$c['top_contexts_by_operator']['315'][0]??null;
    c4v26_need(is_array($t5)&&is_array($t315),'census_top');
    c4v26_need(($t5['criteria_sha256']??'')==='e3b6c0e8e26aa386e054474b8ce1eeddd3cb916dc4a52be533c2326176020c56'&&(int)($t5['missing_overlap_count']??0)===126,'census_op5');
    c4v26_need(($t315['criteria_sha256']??'')==='e314ee9d589d578b1220e86f9ac0e825e8fa8d89d32bc31119c3d74d726d15c7'&&(int)($t315['missing_overlap_count']??0)===507,'census_op315');
}
function c4v26_bridge(array $row,int $operator,array $wanted):?array{
    if((int)($row['operatorKey']??0)!==$operator)return null;
    $catalog=c4v26_id($row['hotelKey']??null);if($catalog===null||!isset($wanted[$catalog])||(string)($row['isOperatorHotelKey']??'')!=='0')return null;
    $orig=is_array($row['original']??null)?$row['original']:[];$native=c4v26_id($orig['hotelKey']??null);
    return['catalog_id'=>$catalog,'native_id'=>$native];
}
function c4v26_contexts(int $townfrom):array{
    return[
      ['key'=>'op315-state5-top','operator_id'=>315,'criteria'=>['TOWNFROMINC'=>$townfrom,'STATEINC'=>5,'CHECKIN_BEG'=>'20260929','CHECKIN_END'=>'20261005','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'OPERATORS'=>'315','GROUP_BY'=>32]],
      ['key'=>'op5-state3-top','operator_id'=>5,'criteria'=>['TOWNFROMINC'=>$townfrom,'STATEINC'=>3,'CHECKIN_BEG'=>'20261006','CHECKIN_END'=>'20261006','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'GROUP_BY'=>32]],
    ];
}
function c4v26_execute(string $root,string $dir,array $post,array $census,string $postSha,string $censusSha,string $sourceSha):array{
    c4v26_validate_census($census);$targets=c4v26_targets($post);
    $cfg=c4v26_private_config($root);$private=dirname((string)$cfg['catalog_path']);$townfrom=c4v26_moscow_departure((string)$cfg['catalog_path']);
    $app=dirname(__DIR__,2).'/app/integrations';require_once$app.'/andromeda-client.php';require_once$app.'/andromeda-transport.php';
    $calls=0;$last=0.0;$evidence=$dir.'/evidence-private';mkdir($evidence,0700,true);
    $call=function(string $url,array $opts)use($private,$dir,&$calls,&$last){
        $q=[];parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$action=(string)($q['action']??'unknown');$next=$calls+1;
        c4v26_need($next<=C4V26_HTTP_CAP,'operation_http_cap');c4v26_budget($private,$next,$action);$calls=$next;
        c4v26_save($dir.'/http-'.str_pad((string)$calls,4,'0',STR_PAD_LEFT).'-reserved.json',['operation'=>C4V26_OP,'call'=>$calls,'action'=>$action,'state'=>'reserved_before_http']);
        $wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$t=new AnyTourAndromedaTransport(true);$last=microtime(true);return$t($url,$opts);
    };
    c4v26_save($dir.'/login-reserved.json',['operation'=>C4V26_OP,'state'=>'reserved_before_login']);
    $login=new AnyTourAndromedaClient($call,true);$login->login((string)$cfg['username'],(string)$cfg['password']);$session=$login->privateSession();c4v26_need($session!==[],'login');
    c4v26_save($dir.'/login-result.json',['operation'=>C4V26_OP,'state'=>'login_succeeded']);
    $native=[];$priceRows=[];$failed=[];$contexts=[];
    foreach(c4v26_contexts($townfrom) as $ctx){
        $op=$ctx['operator_id'];$wanted=$targets[$op];$pages=0;$pc=null;$rows=0;$matched=0;$hashes=[];$status='complete';$reason=null;
        for($page=1;$page<=C4V26_PAGE_CAP;$page++){
            $params=$ctx['criteria'];$params['PAGE']=$page;
            c4v26_save($dir.'/context-'.$ctx['key'].'-page-'.$page.'-reserved.json',['operation'=>C4V26_OP,'context'=>$ctx['key'],'operator_id'=>$op,'page'=>$page,'state'=>'reserved_before_price']);
            try{$cl=new AnyTourAndromedaClient($call,true);$cl->restorePrivateSession($session);$reply=$cl->price($params);}
            catch(Throwable$e){$status='supplier_error';$reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8'));$failed[$op]=true;break;}
            $pages++;$pc=(int)($reply['PAGES_COUNT']??-1);c4v26_need($pc>=0&&$pc<=C4V26_PAGE_CAP,'pages_count');$raw=c4v26_json($reply);$hashes[]=hash('sha256',$raw);
            file_put_contents($evidence.'/context-'.$ctx['key'].'-page-'.$page.'.json',$raw."\n",LOCK_EX);
            foreach(($reply['PRICES']??[]) as $row){if(!is_array($row))continue;$rows++;$b=c4v26_bridge($row,$op,$wanted);if($b===null)continue;$key=$op.'|'.$b['catalog_id'];$priceRows[$key]=($priceRows[$key]??0)+1;if($b['native_id']!==null){$native[$key][$b['native_id']]=true;$matched++;}}
            if($pc===0||$page>=$pc)break;
        }
        $contexts[]=['context'=>$ctx['key'],'operator_id'=>$op,'criteria'=>$ctx['criteria'],'status'=>$status,'reason'=>$reason,'pages_count'=>$pc,'pages_drained'=>$pages,'price_rows_seen'=>$rows,'matched_native_rows'=>$matched,'response_sha256s'=>$hashes];
    }
    $edges=[];$counts=[];$byOp=[];
    foreach($targets as $op=>$wanted)foreach($wanted as $catalog=>$local){
        $key=$op.'|'.$catalog;$ids=array_keys($native[$key]??[]);sort($ids,SORT_NATURAL);
        if(isset($failed[$op]))$st='partial_unresolved_no_replay';elseif(count($ids)===1)$st='captured_single_native';elseif(count($ids)>1)$st='captured_ambiguous_native';else$st='not_returned_in_context';
        $edge=['catalog_id'=>(string)$catalog,'local_hotel_id'=>(int)$local,'operator_id'=>$op,'namespace'=>'operator_'.$op,'state'=>$st,'positive_native_candidates'=>$ids,'price_rows'=>(int)($priceRows[$key]??0),'safe_to_write_now'=>false];
        $edges[]=$edge;$counts[$st]=($counts[$st]??0)+1;$byOp[(string)$op][$st]=($byOp[(string)$op][$st]??0)+1;
    }
    ksort($counts);ksort($byOp);foreach($byOp as &$x)ksort($x);unset($x);
    $single=[];$collision=0;foreach($edges as $e)if($e['state']==='captured_single_native'){$k=$e['namespace'].'|'.$e['positive_native_candidates'][0];$single[$k][$e['local_hotel_id']]=true;}
    foreach($single as $locals)if(count($locals)>1)$collision++;
    $state=$failed===[]?'completed_read_only_context_recovery':'completed_read_only_context_recovery_partial';
    return['operation'=>C4V26_OP,'state'=>$state,'source_sha'=>$sourceSha,'postwrite_operation'=>C4V26_POST_OP,'postwrite_result_sha256'=>$postSha,'census_operation'=>C4V26_CENSUS_OP,'census_result_sha256'=>$censusSha,
      'townfrom'=>$townfrom,'target_counts_by_operator'=>['5'=>count($targets[5]),'315'=>count($targets[315])],'target_edge_count'=>count($targets[5])+count($targets[315]),'context_count'=>2,'failed_operator_context_count'=>count($failed),
      'contexts'=>$contexts,'edges'=>$edges,'edge_state_counts'=>$counts,'operator_state_counts'=>$byOp,'single_native_source_collision_count'=>$collision,
      'provider_http_calls'=>$calls,'samo_http_calls'=>$calls,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
function c4v26_self_test():void{
    $x=c4v26_bridge(['operatorKey'=>315,'hotelKey'=>'10','isOperatorHotelKey'=>0,'original'=>['hotelKey'=>'900']],315,['10'=>100]);
    c4v26_need($x===['catalog_id'=>'10','native_id'=>'900'],'bridge');
    c4v26_need(c4v26_bridge(['operatorKey'=>5,'hotelKey'=>'10','isOperatorHotelKey'=>1,'original'=>['hotelKey'=>'900']],5,['10'=>100])===null,'namespace');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){c4v26_self_test();echo"MATCH_COMMON4_CONTEXT_RECOVERY_ACQUIRE_V26_SELFTEST_OK\n";exit;}
    c4v26_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$postPath=(string)getenv('MATCH_POSTWRITE_RESULT');$postSha=(string)getenv('MATCH_POSTWRITE_SHA');$censusPath=(string)getenv('MATCH_CENSUS_RESULT');$censusSha=(string)getenv('MATCH_CENSUS_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
    c4v26_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===C4V26_OP&&is_file($postPath)&&is_file($censusPath)&&hash_file('sha256',$postPath)===$postSha&&hash_file('sha256',$censusPath)===$censusSha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');
    $reservation=c4v26_load($dir.'/reservation.json');c4v26_need(($reservation['operation']??'')===C4V26_OP&&($reservation['state']??'')==='reserved_before_provider','reservation');
    try{$out=c4v26_execute($root,$dir,c4v26_load($postPath),c4v26_load($censusPath),$postSha,$censusSha,$sourceSha);$h=c4v26_save($dir.'/result.json',$out);c4v26_save($dir.'/receipt.json',['operation'=>C4V26_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>$out['provider_http_calls']>0,'provider_http_calls'=>$out['provider_http_calls'],'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>$out['provider_http_calls']>0]);echo c4v26_json(['state'=>$out['state'],'target_edge_count'=>$out['target_edge_count'],'context_count'=>$out['context_count'],'failed_operator_context_count'=>$out['failed_operator_context_count'],'provider_http_calls'=>$out['provider_http_calls'],'edge_state_counts'=>$out['edge_state_counts'],'operator_state_counts'=>$out['operator_state_counts'],'single_native_source_collision_count'=>$out['single_native_source_collision_count']])."\n";exit(in_array($out['state'],['completed_read_only_context_recovery','completed_read_only_context_recovery_partial'],true)?0:2);}
    catch(Throwable$e){$reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8'));$f=['operation'=>C4V26_OP,'state'=>'terminal_failed_no_replay','reason'=>$reason,'provider_http_calls'=>null,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];$h=c4v26_save($dir.'/result.json',$f);c4v26_save($dir.'/receipt.json',['operation'=>C4V26_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>true,'provider_http_calls'=>null,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);fwrite(STDERR,$reason."\n");exit(2);}
}
