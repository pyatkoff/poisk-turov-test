<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_samo_live30_common4_acquire_v1.php';

const SLC4R2_OP='hotel-match-samo-live30-common4-retry-1971-20260924-v2';
const SLC4R2_V1_OP='hotel-match-samo-live30-common4-acquire-1971-20260924-v1';
const SLC4R2_MAX_IDS=12;

function slc4r2_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function slc4r2_save(string $p,array $v):string{$raw=slc4a_json($v)."\n";$f=@fopen($p,'x+b');slc4r2_need($f!==false,'exclusive_create');try{slc4r2_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))slc4r2_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function slc4r2_budget(string $private,int $call,string $action):void{
    $p=$private.'/monthly-requests.json';$lock=fopen($p.'.lock','c');slc4r2_need($lock!==false&&flock($lock,LOCK_EX),'budget_lock');
    try{$month=gmdate('Y-m');$v=is_file($p)?slc4a_load($p):[];if(($v['month']??'')!==$month)$v=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>SLC4A_MONTHLY_LIMIT,'scope'=>'this_integration'];
        $used=(int)($v['reserved_requests']??0);$limit=(int)($v['monthly_limit']??SLC4A_MONTHLY_LIMIT);slc4r2_need($limit===SLC4A_MONTHLY_LIMIT&&$used<$limit,'monthly_quota');
        $v['reserved_requests']=$used+1;$v['last_match_operation']=SLC4R2_OP;$v['last_match_call']=$call;$v['last_match_action']=$action;
        $tmp=$p.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,slc4a_json($v));chmod($tmp,0600);rename($tmp,$p);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function slc4r2_failed_groups(array $plan,array $v1):array{
    slc4r2_need(($v1['operation']??'')===SLC4R2_V1_OP,'v1_operation');
    slc4r2_need(($v1['state']??'')==='completed_read_only_partial','v1_state');
    slc4r2_need((int)($v1['planned_batch_count']??0)===40&&(int)($v1['batch_error_count']??0)===21,'v1_counts');
    $rows=slc4a_plan($plan);$groups=slc4a_groups($rows);slc4r2_need(count($groups)===40,'group_count');
    $v1b=$v1['batches']??null;slc4r2_need(is_array($v1b)&&count($v1b)===40,'v1_batches');
    $failed=[];$errCounts=[];
    foreach($v1b as $b){slc4r2_need(is_array($b),'v1_batch');$idx=(int)($b['batch']??0);slc4r2_need($idx>=1&&$idx<=40,'v1_batch_index');
        if(($b['state']??'')!=='supplier_error')continue;
        $g=$groups[$idx-1];slc4r2_need((int)$b['stateinc']===(int)$g['stateinc']&&(int)$b['operator_id']===(int)$g['operator_id']&&(int)$b['hotel_count']===count($g['hotel_ids']),'v1_group_mismatch');
        $reason=(string)($b['reason']??'');$errCounts[$reason]=($errCounts[$reason]??0)+1;$failed[]=['v1_batch'=>$idx]+$g+['v1_reason'=>$reason];
    }
    slc4r2_need(count($failed)===21,'failed_count');
    return [$failed,$errCounts];
}
function slc4r2_subgroups(array $failed):array{
    $out=[];$seen=[];
    foreach($failed as $g){$ids=$g['hotel_ids'];for($i=0;$i<count($ids);$i+=SLC4R2_MAX_IDS){$part=array_slice($ids,$i,SLC4R2_MAX_IDS);$joined=implode(',',$part);
            slc4r2_need(count($part)>=1&&count($part)<=12&&strlen($joined)<=300,'subgroup_bounds');
            $key=$g['stateinc'].'|'.$g['operator_id'].'|'.$joined;slc4r2_need(!isset($seen[$key]),'duplicate_subgroup');$seen[$key]=true;
            $orig=implode(',',$ids);slc4r2_need($joined!==$orig,'request_digest_replay');
            $out[]=['v1_batch'=>$g['v1_batch'],'v1_reason'=>$g['v1_reason'],'stateinc'=>$g['stateinc'],'operator_id'=>$g['operator_id'],'hotel_ids'=>$part,'hotels_bytes'=>strlen($joined)];
        }}
    usort($out,fn($a,$b)=>[$a['v1_batch'],$a['hotel_ids'][0]]<=>[$b['v1_batch'],$b['hotel_ids'][0]]);
    return $out;
}
function slc4r2_execute(string $root,string $dir,string $planPath,string $v1Path,string $sourceSha):int{
    $plan=slc4a_load($planPath);$v1=slc4a_load($v1Path);[$failed,$errCounts]=slc4r2_failed_groups($plan,$v1);$groups=slc4r2_subgroups($failed);
    $reservation=slc4a_load($dir.'/reservation.json');slc4r2_need(($reservation['operation']??'')===SLC4R2_OP&&($reservation['state']??'')==='reserved_before_provider','reservation');
    $app=dirname(__DIR__,2).'/app/integrations';require_once $app.'/andromeda-client.php';require_once $app.'/andromeda-transport.php';
    $cfg=slc4a_private_config($root);$private=dirname((string)$cfg['catalog_path']);$townfrom=slc4a_moscow_departure((string)$cfg['catalog_path']);
    $from=(new DateTimeImmutable('tomorrow',new DateTimeZone('Europe/Moscow')))->format('Ymd');$to=(new DateTimeImmutable('tomorrow +20 days',new DateTimeZone('Europe/Moscow')))->format('Ymd');
    $calls=0;$last=0.0;$evidenceDir=$dir.'/evidence-private';mkdir($evidenceDir,0700,true);
    $call=function(string $url,array $opts)use($private,$dir,&$calls,&$last){$q=[];parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$action=(string)($q['action']??'unknown');$next=$calls+1;slc4r2_need($next<=SLC4A_HTTP_CAP,'operation_http_cap');slc4r2_budget($private,$next,$action);$calls=$next;slc4r2_save($dir.'/http-'.str_pad((string)$calls,4,'0',STR_PAD_LEFT).'-reserved.json',['operation'=>SLC4R2_OP,'call'=>$calls,'action'=>$action,'state'=>'reserved_before_http']);$wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$tr=new AnyTourAndromedaTransport(true);$last=microtime(true);return $tr($url,$opts);};
    $state='failed_before_provider';$reason=null;$batchOut=[];$edgeMap=[];$errors=0;
    foreach($groups as $g)foreach($g['hotel_ids'] as $cid)$edgeMap[$cid.'|'.$g['operator_id']]=['catalog_id'=>(string)$cid,'stateinc'=>(int)$g['stateinc'],'operator_id'=>(int)$g['operator_id'],'namespace'=>SLC4A_OPS[(int)$g['operator_id']],'returned_catalog'=>false,'native_ids'=>[],'price_rows'=>0,'safe_to_write_now'=>false];
    try{
        slc4r2_save($dir.'/login-reserved.json',['operation'=>SLC4R2_OP,'state'=>'reserved_before_login']);$login=new AnyTourAndromedaClient($call,true);$login->login((string)$cfg['username'],(string)$cfg['password']);$session=$login->privateSession();slc4r2_need($session!==[],'login');slc4r2_save($dir.'/login-result.json',['operation'=>SLC4R2_OP,'state'=>'login_succeeded']);
        foreach($groups as $bi=>$g){$batch=$bi+1;$requested=array_fill_keys(array_map('strval',$g['hotel_ids']),true);$pages=0;$pcLast=null;$rowsSeen=0;$hashes=[];$bs='complete';$err=null;
            for($page=1;$page<=SLC4A_PAGE_CAP;$page++){
                $hotels=implode(',',$g['hotel_ids']);slc4r2_need(strlen($hotels)<=300&&count($g['hotel_ids'])<=12,'runtime_hotels_bound');
                $params=['TOWNFROMINC'=>$townfrom,'STATEINC'=>$g['stateinc'],'CHECKIN_BEG'=>$from,'CHECKIN_END'=>$to,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>$page,'OPERATORS'=>(string)$g['operator_id'],'HOTELS'=>$hotels,'GROUP_BY'=>32];
                slc4r2_save($dir.'/batch-'.str_pad((string)$batch,3,'0',STR_PAD_LEFT).'-page-'.$page.'-reserved.json',['operation'=>SLC4R2_OP,'batch'=>$batch,'page'=>$page,'v1_batch'=>$g['v1_batch'],'stateinc'=>$g['stateinc'],'operator_id'=>$g['operator_id'],'hotel_count'=>count($g['hotel_ids']),'hotels_bytes'=>strlen($hotels),'state'=>'reserved_before_price']);
                try{$cl=new AnyTourAndromedaClient($call,true);$cl->restorePrivateSession($session);$reply=$cl->price($params);}
                catch(Throwable $e){$bs='supplier_error';$err=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8'));$errors++;break;}
                $pages++;$pc=(int)($reply['PAGES_COUNT']??-1);slc4r2_need($pc>=0&&$pc<=SLC4A_PAGE_CAP,'pages_count');$pcLast=$pc;$raw=slc4a_json($reply);$hashes[]=hash('sha256',$raw);file_put_contents($evidenceDir.'/batch-'.str_pad((string)$batch,3,'0',STR_PAD_LEFT).'-page-'.$page.'.json',$raw."\n",LOCK_EX);
                foreach(($reply['PRICES']??[]) as $price){if(!is_array($price))continue;$bridge=slc4a_bridge($price,(int)$g['operator_id'],$requested);if($bridge['state']==='other_operator')continue;$rowsSeen++;
                    if(($bridge['catalog_id']??null)!==null){$key=$bridge['catalog_id'].'|'.$g['operator_id'];if(!isset($edgeMap[$key]))continue;$edgeMap[$key]['price_rows']++;if($bridge['state']==='exact_catalog_to_native'){$edgeMap[$key]['returned_catalog']=true;$edgeMap[$key]['native_ids'][$bridge['native_id']]=true;}elseif($bridge['state']==='catalog_only')$edgeMap[$key]['returned_catalog']=true;}
                }
                if($pc===0||$page>=$pc)break;
            }
            $batchOut[]=['batch'=>$batch,'v1_batch'=>$g['v1_batch'],'state'=>$bs,'reason'=>$err,'stateinc'=>$g['stateinc'],'operator_id'=>$g['operator_id'],'hotel_count'=>count($g['hotel_ids']),'hotels_bytes'=>$g['hotels_bytes'],'pages_count'=>$pcLast,'pages_drained'=>$pages,'price_rows_seen'=>$rowsSeen,'response_sha256s'=>$hashes];
        }
        $state=$errors===0?'completed_read_only':'completed_read_only_partial';
    }catch(Throwable $e){$reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8'));$state=$calls>0?'terminal_failed_no_replay':'failed_before_provider';}
    $edges=[];$counts=[];$byOp=[];$nativeTargets=[];
    foreach($edgeMap as $e){$ids=array_keys($e['native_ids']);sort($ids,SORT_NATURAL);unset($e['native_ids']);$e['positive_native_candidates']=$ids;$e['state']=count($ids)===1?'captured_single_native':(count($ids)>1?'captured_ambiguous_native':($e['returned_catalog']?'catalog_only':'not_returned_in_context'));$edges[]=$e;$counts[$e['state']]=($counts[$e['state']]??0)+1;$op=(string)$e['operator_id'];$byOp[$op][$e['state']]=($byOp[$op][$e['state']]??0)+1;if($e['state']==='captured_single_native')$nativeTargets[$e['namespace'].'|'.$ids[0]][$e['catalog_id']]=true;}
    ksort($counts);ksort($byOp);$unique=0;$coll=0;foreach($nativeTargets as $targets){if(count($targets)===1)$unique++;else$coll++;}
    $out=['operation'=>SLC4R2_OP,'state'=>$state,'reason'=>$reason,'source_sha'=>$sourceSha,'plan_operation'=>SLC4A_PLAN_OP,'plan_result_sha256'=>hash_file('sha256',$planPath),'v1_operation'=>SLC4R2_V1_OP,'v1_result_sha256'=>hash_file('sha256',$v1Path),'v1_failed_batch_count'=>21,'v1_error_reason_counts'=>$errCounts,'retry_subbatch_count'=>count($groups),'queried_edge_count'=>count($edges),'completed_batch_count'=>count($batchOut),'batch_error_count'=>$errors,'townfrom'=>$townfrom,'checkin_beg'=>$from,'checkin_end'=>$to,'nights'=>7,'adults'=>2,'operator_ids'=>array_keys(SLC4A_OPS),'samo_http_calls'=>$calls,'edge_state_counts'=>$counts,'operator_state_counts'=>$byOp,'single_native_source_unique_count'=>$unique,'single_native_source_collision_count'=>$coll,'batches'=>$batchOut,'edges'=>$edges,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    $h=slc4r2_save($dir.'/result.json',$out);slc4r2_save($dir.'/receipt.json',['operation'=>SLC4R2_OP,'state'=>$state,'result_sha256'=>$h,'provider_accessed'=>$calls>0,'samo_http_calls'=>$calls,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>$calls>0]);
    echo slc4a_json(['state'=>$state,'reason'=>$reason,'v1_failed_batch_count'=>21,'retry_subbatch_count'=>count($groups),'batch_error_count'=>$errors,'samo_http_calls'=>$calls,'edge_state_counts'=>$counts,'single_native_source_unique_count'=>$unique,'single_native_source_collision_count'=>$coll])."\n";
    return in_array($state,['completed_read_only','completed_read_only_partial'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){$f=[['v1_batch'=>1,'v1_reason'=>'x','stateinc'=>5,'operator_id'=>315,'hotel_ids'=>array_map('strval',range(10000001,10000030))]];$g=slc4r2_subgroups($f);slc4r2_need(count($g)===3&&count($g[0]['hotel_ids'])===12&&$g[0]['hotels_bytes']<=300,'split');echo "MATCH_SAMO_LIVE30_COMMON4_RETRY_V2_SELFTEST_OK\n";exit;}
    slc4r2_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$plan=(string)getenv('MATCH_PLAN_RESULT');$v1=(string)getenv('MATCH_V1_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');slc4r2_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===SLC4R2_OP&&is_file($plan)&&is_file($v1)&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'runtime_scope');exit(slc4r2_execute($root,$dir,$plan,$v1,$sha));
}
