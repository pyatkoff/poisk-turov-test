<?php
declare(strict_types=1);

const C4R26_OP='hotel-match-common4-retained-context-acquire-1971-20260925-v26';
const C4R26_POST_OP='hotel-match-samo-business-live30-common4-postwrite-1971-20260925-v24';
const C4R26_OPS=[5=>'operator_5',315=>'operator_315'];
const C4R26_CONTEXTS=[
    ['operator_id'=>315,'stateinc'=>5,'checkin_beg'=>'20260929','checkin_end'=>'20261005','nights'=>7,'meal'=>null],
    ['operator_id'=>315,'stateinc'=>5,'checkin_beg'=>'20261011','checkin_end'=>'20261011','nights'=>7,'meal'=>null],
    ['operator_id'=>315,'stateinc'=>5,'checkin_beg'=>'20261016','checkin_end'=>'20261016','nights'=>7,'meal'=>null],
    ['operator_id'=>5,'stateinc'=>3,'checkin_beg'=>'20261005','checkin_end'=>'20261005','nights'=>7,'meal'=>null],
    ['operator_id'=>5,'stateinc'=>3,'checkin_beg'=>'20261006','checkin_end'=>'20261006','nights'=>7,'meal'=>null],
    ['operator_id'=>5,'stateinc'=>5,'checkin_beg'=>'20261011','checkin_end'=>'20261011','nights'=>7,'meal'=>null],
    ['operator_id'=>5,'stateinc'=>5,'checkin_beg'=>'20261012','checkin_end'=>'20261012','nights'=>7,'meal'=>'5,7'],
    ['operator_id'=>5,'stateinc'=>5,'checkin_beg'=>'20261025','checkin_end'=>'20261025','nights'=>7,'meal'=>'5,7'],
];
const C4R26_HTTP_CAP=1000;
const C4R26_PAGE_CAP=1000;
const C4R26_MONTHLY_LIMIT=5000000;

function c4r26_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function c4r26_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function c4r26_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);c4r26_need(is_array($v),'json_shape');return $v;}
function c4r26_save(string $p,array $v):string{$raw=c4r26_json($v)."\n";$f=@fopen($p,'x+b');c4r26_need($f!==false,'exclusive_create');try{c4r26_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))c4r26_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function c4r26_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function c4r26_norm(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');$v=str_replace('ё','е',$v);return trim(preg_replace('/\s+/u',' ',$v)??$v);}
function c4r26_private_config(string $root):array{
    foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){
        if(!is_file($p)||is_link($p))continue;$v=require$p;
        if(is_array($v)&&($v['enabled']??false)===true&&is_string($v['catalog_path']??null)&&$v['catalog_path']!==''&&is_string($v['username']??null)&&is_string($v['password']??null))return$v;
    }
    throw new RuntimeException('andromeda_private_config_missing');
}
function c4r26_catalog_files(string $catalogPath):array{
    $files=[];if(is_file($catalogPath)&&!is_link($catalogPath))$files[]=$catalogPath;
    foreach(glob(dirname($catalogPath).'/countries/*.json')?:[] as $p)if(is_file($p)&&!is_link($p))$files[]=$p;
    $files=array_values(array_unique($files));sort($files,SORT_STRING);return$files;
}
function c4r26_moscow_departure(string $catalogPath):int{
    $ids=[];
    foreach(c4r26_catalog_files($catalogPath) as $p){
        $v=json_decode((string)file_get_contents($p),true);if(!is_array($v))continue;
        foreach(($v['townfrom']['payload']['TOWNFROM']??[]) as $r){
            if(!is_array($r))continue;$id=c4r26_id($r['id']??null);if($id===null)continue;
            foreach(['name','lName'] as $k){$n=c4r26_norm((string)($r[$k]??''));if(in_array($n,['moscow','moskva','москва'],true)){$ids[(int)$id]=true;break;}}
        }
    }
    $x=array_keys($ids);sort($x,SORT_NUMERIC);c4r26_need(count($x)===1,'moscow_departure_binding');return(int)$x[0];
}
function c4r26_budget(string $private,int $call,string $action):void{
    $p=$private.'/monthly-requests.json';$lock=fopen($p.'.lock','c');c4r26_need($lock!==false&&flock($lock,LOCK_EX),'budget_lock');
    try{
        $month=gmdate('Y-m');$v=is_file($p)?c4r26_load($p):[];
        if(($v['month']??'')!==$month)$v=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>C4R26_MONTHLY_LIMIT,'scope'=>'this_integration'];
        $used=(int)($v['reserved_requests']??0);$limit=(int)($v['monthly_limit']??C4R26_MONTHLY_LIMIT);
        c4r26_need($limit===C4R26_MONTHLY_LIMIT&&$used<$limit,'monthly_quota');
        $v['reserved_requests']=$used+1;$v['last_match_operation']=C4R26_OP;$v['last_match_call']=$call;$v['last_match_action']=$action;
        $tmp=$p.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,c4r26_json($v));chmod($tmp,0600);rename($tmp,$p);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function c4r26_targets(array $post):array{
    c4r26_need(($post['operation']??'')===C4R26_POST_OP&&($post['state']??'')==='samo_business_live30_common4_plan_ready','post_state');
    c4r26_need(($post['deduped_missing_lane_counts']??null)===['operator_5'=>1516,'operator_115'=>1218,'operator_315'=>1304,'operator_342'=>667],'post_missing_counts');
    c4r26_need((int)($post['deduped_lane_state_conflicts']??-1)===0,'post_conflicts');
    $out=[];$byOp=[5=>0,315=>0];$byStateOp=[];
    foreach(($post['rows']??[]) as $r){
        if(!is_array($r)||($r['mapping_state']??'')!=='mapped_unique'||($r['saved_catalog_state']??'')!=='saved_catalog_ready')continue;
        $catalog=c4r26_id($r['andromeda_catalog_id']??null);$local=(int)($r['local_hotel_id']??0);$state=(int)($r['saved_stateinc']??0);
        c4r26_need($catalog!==null&&$local>0&&in_array($state,[3,5],true),'target_row');
        foreach(C4R26_OPS as $op=>$ns){
            $lane=$r['operator_lanes'][$ns]??null;if(!is_array($lane)||($lane['status']??'')!=='missing')continue;
            c4r26_need(!isset($out[$state][$op][$catalog]),'target_duplicate');
            $out[$state][$op][$catalog]=$local;$byOp[$op]++;$byStateOp[$state][$op]=($byStateOp[$state][$op]??0)+1;
        }
    }
    c4r26_need($byOp===[5=>1562,315=>1336],'target_operator_counts');
    return ['targets'=>$out,'by_operator'=>$byOp,'by_state_operator'=>$byStateOp,'edge_count'=>array_sum($byOp)];
}
function c4r26_bridge(array $row,int $operator,array $wanted):?array{
    if((int)($row['operatorKey']??0)!==$operator)return null;
    $catalog=c4r26_id($row['hotelKey']??null);if($catalog===null||!isset($wanted[$catalog])||(string)($row['isOperatorHotelKey']??'')!=='0')return null;
    $orig=is_array($row['original']??null)?$row['original']:[];$native=c4r26_id($orig['hotelKey']??null);
    if($native===null)return ['catalog_id'=>$catalog,'native_id'=>null];
    return ['catalog_id'=>$catalog,'native_id'=>$native];
}
function c4r26_execute(string $root,string $dir,array $post,string $postSha,string $sourceSha):array{
    $plan=c4r26_targets($post);$targets=$plan['targets'];
    $cfg=c4r26_private_config($root);$private=dirname((string)$cfg['catalog_path']);$townfrom=c4r26_moscow_departure((string)$cfg['catalog_path']);c4r26_need($townfrom===1,'moscow_departure_expected_1');
    $app=dirname(__DIR__,2).'/app/integrations';require_once $app.'/andromeda-client.php';require_once $app.'/andromeda-transport.php';
    $calls=0;$last=0.0;$evidence=$dir.'/evidence-private';mkdir($evidence,0700,true);
    $call=function(string $url,array $opts)use($private,$dir,&$calls,&$last){
        $q=[];parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$action=(string)($q['action']??'unknown');$next=$calls+1;
        c4r26_need($next<=C4R26_HTTP_CAP,'operation_http_cap');c4r26_budget($private,$next,$action);$calls=$next;
        c4r26_save($dir.'/http-'.str_pad((string)$calls,4,'0',STR_PAD_LEFT).'-reserved.json',['operation'=>C4R26_OP,'call'=>$calls,'action'=>$action,'state'=>'reserved_before_http']);
        $wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));
        $t=new AnyTourAndromedaTransport(true);$last=microtime(true);return$t($url,$opts);
    };
    c4r26_save($dir.'/login-reserved.json',['operation'=>C4R26_OP,'state'=>'reserved_before_login']);
    $login=new AnyTourAndromedaClient($call,true);$login->login((string)$cfg['username'],(string)$cfg['password']);$session=$login->privateSession();c4r26_need($session!==[],'login');
    c4r26_save($dir.'/login-result.json',['operation'=>C4R26_OP,'state'=>'login_succeeded']);
    $native=[];$priceRows=[];$seenContexts=[];$failed=[];$contexts=[];
    foreach(C4R26_CONTEXTS as $ci=>$spec){
        $state=(int)$spec['stateinc'];$op=(int)$spec['operator_id'];$nights=(int)$spec['nights'];$wanted=$targets[$state][$op]??[];if($wanted===[])continue;
        $ck=($ci+1).'|'.$state.'|'.$op.'|'.$spec['checkin_beg'].'|'.$spec['checkin_end'].'|'.$nights.'|'.($spec['meal']??'');
        $pages=0;$pc=null;$matched=0;$rows=0;$hashes=[];$status='complete';$reason=null;
        for($page=1;$page<=C4R26_PAGE_CAP;$page++){
            $params=['TOWNFROMINC'=>$townfrom,'STATEINC'=>$state,'CHECKIN_BEG'=>$spec['checkin_beg'],'CHECKIN_END'=>$spec['checkin_end'],'NIGHTS_FROM'=>$nights,'NIGHTS_TILL'=>$nights,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>$page,'OPERATORS'=>(string)$op,'GROUP_BY'=>32];
            if($spec['meal']!==null)$params['MEAL']=$spec['meal'];
            c4r26_save($dir.'/context-'.$state.'-'.$op.'-'.$nights.'-page-'.$page.'-reserved.json',['operation'=>C4R26_OP,'stateinc'=>$state,'operator_id'=>$op,'nights'=>$nights,'page'=>$page,'state'=>'reserved_before_price']);
            try{$cl=new AnyTourAndromedaClient($call,true);$cl->restorePrivateSession($session);$reply=$cl->price($params);}
            catch(Throwable$e){$status='supplier_error';$reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8'));$failed[$state.'|'.$op]=true;break;}
            $pages++;$pc=(int)($reply['PAGES_COUNT']??-1);c4r26_need($pc>=0&&$pc<=C4R26_PAGE_CAP,'pages_count');$raw=c4r26_json($reply);$rh=hash('sha256',$raw);$hashes[]=$rh;
            file_put_contents($evidence.'/context-'.$state.'-'.$op.'-'.$nights.'-page-'.$page.'.json',$raw."\n",LOCK_EX);
            foreach(($reply['PRICES']??[]) as $row){
                if(!is_array($row))continue;$rows++;$b=c4r26_bridge($row,$op,$wanted);if($b===null)continue;$catalog=$b['catalog_id'];$key=$state.'|'.$op.'|'.$catalog;$seenContexts[$key][$ck]=true;$priceRows[$key]=($priceRows[$key]??0)+1;
                if($b['native_id']!==null){$native[$key][$b['native_id']]=true;$matched++;}
            }
            if($pc===0||$page>=$pc)break;
        }
        $contexts[]=['context'=>$ck,'stateinc'=>$state,'operator_id'=>$op,'checkin_beg'=>$spec['checkin_beg'],'checkin_end'=>$spec['checkin_end'],'nights'=>$nights,'meal'=>$spec['meal'],'status'=>$status,'reason'=>$reason,'pages_count'=>$pc,'pages_drained'=>$pages,'price_rows_seen'=>$rows,'matched_native_rows'=>$matched,'response_sha256s'=>$hashes];
    }
    $edges=[];$counts=[];$byOp=[];
    foreach($targets as $state=>$ops)foreach($ops as $op=>$wanted)foreach($wanted as $catalog=>$local){
        $key=$state.'|'.$op.'|'.$catalog;$ids=array_keys($native[$key]??[]);sort($ids,SORT_NATURAL);
        if(isset($failed[$state.'|'.$op]))$stateName='partial_unresolved_no_replay';
        elseif(count($ids)===1)$stateName='captured_single_native';
        elseif(count($ids)>1)$stateName='captured_ambiguous_native';
        else $stateName='not_returned_in_context';
        $edge=['catalog_id'=>(string)$catalog,'local_hotel_id'=>(int)$local,'stateinc'=>(int)$state,'operator_id'=>(int)$op,'namespace'=>C4R26_OPS[$op],
          'state'=>$stateName,'positive_native_candidates'=>$ids,'price_rows'=>(int)($priceRows[$key]??0),'observed_context_count'=>count($seenContexts[$key]??[]),'safe_to_write_now'=>false];
        $edges[]=$edge;$counts[$stateName]=($counts[$stateName]??0)+1;$byOp[(string)$op][$stateName]=($byOp[(string)$op][$stateName]??0)+1;
    }
    ksort($counts);ksort($byOp);foreach($byOp as &$x)ksort($x);unset($x);
    $single=[];$collision=0;foreach($edges as $e)if($e['state']==='captured_single_native'){$k=$e['namespace'].'|'.$e['positive_native_candidates'][0];$single[$k][$e['local_hotel_id']]=true;}
    foreach($single as $locals)if(count($locals)>1)$collision++;
    $state=$failed===[]?'completed_read_only_broad_recovery':'completed_read_only_broad_recovery_partial';
    return ['operation'=>C4R26_OP,'state'=>$state,'source_sha'=>$sourceSha,'postwrite_operation'=>C4R26_POST_OP,'postwrite_result_sha256'=>$postSha,
      'townfrom'=>$townfrom,'selected_contexts'=>C4R26_CONTEXTS,'adults'=>2,
      'target_edge_count'=>$plan['edge_count'],'target_counts_by_operator'=>$plan['by_operator'],'target_counts_by_state_operator'=>$plan['by_state_operator'],
      'context_count'=>count($contexts),'failed_state_operator_count'=>count($failed),'contexts'=>$contexts,'edges'=>$edges,
      'edge_state_counts'=>$counts,'operator_state_counts'=>$byOp,'single_native_source_collision_count'=>$collision,
      'provider_http_calls'=>$calls,'samo_http_calls'=>$calls,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
function c4r26_self_test():void{
    $wanted=['10'=>100];$x=c4r26_bridge(['operatorKey'=>315,'hotelKey'=>'10','isOperatorHotelKey'=>0,'original'=>['hotelKey'=>'900']],315,$wanted);
    c4r26_need($x===['catalog_id'=>'10','native_id'=>'900'],'bridge');
    c4r26_need(c4r26_bridge(['operatorKey'=>315,'hotelKey'=>'10','isOperatorHotelKey'=>1,'original'=>['hotelKey'=>'900']],315,$wanted)===null,'namespace');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){c4r26_self_test();echo "MATCH_COMMON4_RETAINED_CONTEXT_ACQUIRE_V26_SELFTEST_OK\n";exit;}
    c4r26_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$postPath=(string)getenv('MATCH_POSTWRITE_RESULT');$postSha=(string)getenv('MATCH_POSTWRITE_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
    c4r26_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===C4R26_OP&&is_file($postPath)&&preg_match('/^[0-9a-f]{64}$/D',$postSha)===1&&hash_file('sha256',$postPath)===$postSha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');
    $reservation=c4r26_load($dir.'/reservation.json');c4r26_need(($reservation['operation']??'')===C4R26_OP&&($reservation['state']??'')==='reserved_before_provider','reservation');
    try{$out=c4r26_execute($root,$dir,c4r26_load($postPath),$postSha,$sourceSha);$h=c4r26_save($dir.'/result.json',$out);c4r26_save($dir.'/receipt.json',['operation'=>C4R26_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>$out['provider_http_calls']>0,'provider_http_calls'=>$out['provider_http_calls'],'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>$out['provider_http_calls']>0]);echo c4r26_json(['state'=>$out['state'],'target_edge_count'=>$out['target_edge_count'],'context_count'=>$out['context_count'],'failed_state_operator_count'=>$out['failed_state_operator_count'],'provider_http_calls'=>$out['provider_http_calls'],'edge_state_counts'=>$out['edge_state_counts'],'operator_state_counts'=>$out['operator_state_counts'],'single_native_source_collision_count'=>$out['single_native_source_collision_count']])."\n";exit(in_array($out['state'],['completed_read_only_broad_recovery','completed_read_only_broad_recovery_partial'],true)?0:2);}
    catch(Throwable$e){$reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8'));$f=['operation'=>C4R26_OP,'state'=>'terminal_failed_no_replay','reason'=>$reason,'provider_http_calls'=>null,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];$h=c4r26_save($dir.'/result.json',$f);c4r26_save($dir.'/receipt.json',['operation'=>C4R26_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>true,'provider_http_calls'=>null,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);fwrite(STDERR,$reason."\n");exit(2);}
}
