<?php
declare(strict_types=1);

const SLC4A_OP='hotel-match-samo-business-live30-common4-acquire-1971-20260924-v2';
const SLC4A_PLAN_OP='hotel-match-samo-business-live30-common4-plan-1971-20260924-v2';
const SLC4A_OPS=[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'];
const SLC4A_MONTHLY_LIMIT=5000000;
const SLC4A_HTTP_CAP=3000;
const SLC4A_CHECKIN_BEG='20260926';
const SLC4A_CHECKIN_END='20261016';
const SLC4A_PAGE_CAP=1000;

function slc4a_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function slc4a_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function slc4a_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,128,JSON_THROW_ON_ERROR);slc4a_need(is_array($v),'json_shape');return $v;}
function slc4a_save(string $p,array $v):string{
    $raw=slc4a_json($v)."\n";$f=@fopen($p,'x+b');slc4a_need($f!==false,'exclusive_create');
    try{slc4a_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))slc4a_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function slc4a_norm(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');$v=str_replace('ё','е',$v);return trim(preg_replace('/\s+/u',' ',$v)??$v);}
function slc4a_pos(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function slc4a_private_config(string $root):array{
    foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){
        if(!is_file($p)||is_link($p))continue;$v=require $p;
        if(is_array($v)&&($v['enabled']??false)===true&&is_string($v['catalog_path']??null)&&$v['catalog_path']!==''&&is_string($v['username']??null)&&is_string($v['password']??null))return $v;
    }
    throw new RuntimeException('andromeda_private_config_missing');
}
function slc4a_catalog_files(string $catalogPath):array{
    $files=[];if(is_file($catalogPath)&&!is_link($catalogPath))$files[]=$catalogPath;
    foreach(glob(dirname($catalogPath).'/countries/*.json')?:[] as $p)if(is_file($p)&&!is_link($p))$files[]=$p;
    $files=array_values(array_unique($files));sort($files,SORT_STRING);return $files;
}
function slc4a_moscow_departure(string $catalogPath):int{
    $ids=[];
    foreach(slc4a_catalog_files($catalogPath) as $p){
        $v=json_decode((string)file_get_contents($p),true);
        if(!is_array($v))continue;
        foreach(($v['townfrom']['payload']['TOWNFROM']??[]) as $r){
            if(!is_array($r))continue;$id=slc4a_pos($r['id']??null);if($id===null)continue;
            foreach(['name','lName'] as $k){
                $n=slc4a_norm((string)($r[$k]??''));
                if(in_array($n,['moscow','moskva','москва'],true)){$ids[(int)$id]=true;break;}
            }
        }
    }
    $out=array_keys($ids);sort($out,SORT_NUMERIC);slc4a_need(count($out)===1,'moscow_departure_binding');return (int)$out[0];
}
function slc4a_budget(string $private,int $call,string $action):void{
    $p=$private.'/monthly-requests.json';$lock=fopen($p.'.lock','c');slc4a_need($lock!==false&&flock($lock,LOCK_EX),'budget_lock');
    try{
        $month=gmdate('Y-m');$v=is_file($p)?slc4a_load($p):[];
        if(($v['month']??'')!==$month)$v=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>SLC4A_MONTHLY_LIMIT,'scope'=>'this_integration'];
        $used=(int)($v['reserved_requests']??0);$limit=(int)($v['monthly_limit']??SLC4A_MONTHLY_LIMIT);
        slc4a_need($limit===SLC4A_MONTHLY_LIMIT&&$used<$limit,'monthly_quota');
        $v['reserved_requests']=$used+1;$v['last_match_operation']=SLC4A_OP;$v['last_match_call']=$call;$v['last_match_action']=$action;
        $tmp=$p.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,slc4a_json($v));chmod($tmp,0600);rename($tmp,$p);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function slc4a_plan(array $plan):array{
    slc4a_need(($plan['operation']??'')===SLC4A_PLAN_OP&&($plan['state']??'')==='samo_business_live30_common4_plan_ready','plan_state');
    slc4a_need(($plan['catalog_live30_count']??null)===1887&&($plan['mapped_source_count']??null)===1764&&($plan['mapped_unique_local_count']??null)===1711,'plan_business_counts');
    slc4a_need(($plan['unresolved_source_count']??null)===123&&($plan['collision_source_count']??null)===0,'plan_resolution_counts');
    slc4a_need(($plan['acquisition_ready_count']??null)===1753,'plan_acquisition_count');
    slc4a_need(($plan['saved_catalog_ready_count']??null)===1882&&($plan['saved_catalog_not_ready_count']??null)===5,'plan_catalog_counts');
    slc4a_need(($plan['missing_lane_counts']??null)===['operator_5'=>1685,'operator_115'=>1820,'operator_315'=>1459,'operator_342'=>1516],'plan_lane_counts');
    foreach(['provider_http_calls','tourvisor_calls','samo_calls','anex_calls','andromeda_calls','database_writes','mapping_writes'] as $k)slc4a_need(($plan[$k]??null)===0,'plan_authority_'.$k);
    slc4a_need(($plan['safe_to_write_now']??null)===false&&is_array($plan['rows']??null)&&count($plan['rows'])===1887,'plan_rows');
    $rows=[];$sourceSeen=[];
    foreach($plan['rows'] as $r){
        if(!is_array($r)||($r['acquisition_ready']??false)!==true)continue;
        $catalog=slc4a_pos($r['andromeda_catalog_id']??null);$state=(int)($r['saved_stateinc']??0);$missing=$r['missing_operator_ids']??null;
        slc4a_need($catalog!==null&&$state>0&&is_array($missing)&&$missing!==[],'plan_row');
        slc4a_need(!isset($sourceSeen[$catalog]),'plan_source_duplicate');$sourceSeen[$catalog]=true;
        $ops=[];foreach($missing as $op){$op=(int)$op;slc4a_need(isset(SLC4A_OPS[$op]),'plan_operator');$ops[$op]=true;}
        ksort($ops,SORT_NUMERIC);
        $rows[]=['catalog_id'=>$catalog,'stateinc'=>$state,'missing_operator_ids'=>array_keys($ops),'mapping_state'=>(string)($r['mapping_state']??''),'local_hotel_id'=>$r['local_hotel_id']??null];
    }
    slc4a_need(count($rows)===1753,'plan_ready_rows');return $rows;
}
function slc4a_chunks(array $ids):array{
    $ids=array_values(array_unique(array_map('strval',$ids)));sort($ids,SORT_NATURAL);$out=[];$cur=[];
    foreach($ids as $id){
        slc4a_need(slc4a_pos($id)!==null,'chunk_id');
        $candidate=[...$cur,$id];$serialized=implode(',',$candidate);
        if($cur!==[]&&(count($candidate)>12||strlen($serialized)>300)){
            slc4a_need(count($cur)<=12&&strlen(implode(',',$cur))<=300,'chunk_guard');$out[]=$cur;$cur=[$id];
        }else{$cur=$candidate;}
        slc4a_need(count($cur)<=12&&strlen(implode(',',$cur))<=300,'chunk_member_guard');
    }
    if($cur!==[])$out[]=$cur;return $out;
}
function slc4a_groups(array $rows):array{
    $buckets=[];
    foreach($rows as $r)foreach($r['missing_operator_ids'] as $op)$buckets[$r['stateinc'].'|'.$op][$r['catalog_id']]=true;
    $out=[];
    foreach($buckets as $key=>$set){
        [$state,$op]=array_map('intval',explode('|',$key,2));$ids=array_keys($set);
        foreach(slc4a_chunks($ids) as $chunk)$out[]=['stateinc'=>$state,'operator_id'=>$op,'hotel_ids'=>$chunk];
    }
    usort($out,fn($a,$b)=>[$a['stateinc'],$a['operator_id'],$a['hotel_ids'][0]]<=>[$b['stateinc'],$b['operator_id'],$b['hotel_ids'][0]]);
    foreach($out as $g)slc4a_need(count($g['hotel_ids'])>=1&&count($g['hotel_ids'])<=12&&strlen(implode(',',$g['hotel_ids']))<=300,'group_request_guard');
    return $out;
}
function slc4a_bridge(array $row,int $operator,array $requested):array{
    if((int)($row['operatorKey']??0)!==$operator)return ['state'=>'other_operator'];
    $hotel=slc4a_pos($row['hotelKey']??null);$flag=(string)($row['isOperatorHotelKey']??'');
    $original=is_array($row['original']??null)?$row['original']:[];
    $origHotel=slc4a_pos($original['hotelKey']??null);$origOp=(int)($original['operatorKey']??0);
    if($hotel!==null&&isset($requested[$hotel])&&$flag==='0'){
        if($origHotel!==null&&$origOp===$operator)return ['state'=>'exact_catalog_to_native','catalog_id'=>$hotel,'native_id'=>$origHotel];
        return ['state'=>'catalog_only','catalog_id'=>$hotel,'native_id'=>null];
    }
    if($flag==='1'&&$hotel!==null)return ['state'=>'unbound_operator_native','catalog_id'=>null,'native_id'=>$hotel];
    return ['state'=>'unbound'];
}
function slc4a_execute(string $root,string $dir,string $planPath,string $sourceSha):int{
    $plan=slc4a_load($planPath);$rows=slc4a_plan($plan);$reservation=slc4a_load($dir.'/reservation.json');
    slc4a_need(($reservation['operation']??'')===SLC4A_OP&&($reservation['state']??'')==='reserved_before_provider','reservation');
    $groups=slc4a_groups($rows);slc4a_need($groups!==[],'groups');
    $app=dirname(__DIR__,2).'/app/integrations';require_once $app.'/andromeda-client.php';require_once $app.'/andromeda-transport.php';
    $cfg=slc4a_private_config($root);$private=dirname((string)$cfg['catalog_path']);$townfrom=slc4a_moscow_departure((string)$cfg['catalog_path']);
    $from=SLC4A_CHECKIN_BEG;$to=SLC4A_CHECKIN_END;
    $calls=0;$last=0.0;$evidenceDir=$dir.'/evidence-private';mkdir($evidenceDir,0700,true);
    $call=function(string $url,array $opts)use($private,$dir,&$calls,&$last){
        $q=[];parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$action=(string)($q['action']??'unknown');
        $next=$calls+1;slc4a_need($next<=SLC4A_HTTP_CAP,'operation_http_cap');slc4a_budget($private,$next,$action);$calls=$next;
        slc4a_save($dir.'/http-'.str_pad((string)$calls,4,'0',STR_PAD_LEFT).'-reserved.json',['operation'=>SLC4A_OP,'call'=>$calls,'action'=>$action,'state'=>'reserved_before_http']);
        $wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$tr=new AnyTourAndromedaTransport(true);$last=microtime(true);return $tr($url,$opts);
    };
    $state='failed_before_provider';$reason=null;$batchOut=[];$edges=[];$batchErrors=0;
    try{
        slc4a_save($dir.'/login-reserved.json',['operation'=>SLC4A_OP,'state'=>'reserved_before_login']);
        $login=new AnyTourAndromedaClient($call,true);$login->login((string)$cfg['username'],(string)$cfg['password']);$session=$login->privateSession();slc4a_need($session!==[],'login');
        slc4a_save($dir.'/login-result.json',['operation'=>SLC4A_OP,'state'=>'login_succeeded']);
        $edgeMap=[];foreach($rows as $r)foreach($r['missing_operator_ids'] as $op)$edgeMap[$r['catalog_id'].'|'.$op]=['catalog_id'=>$r['catalog_id'],'stateinc'=>$r['stateinc'],'operator_id'=>$op,'namespace'=>SLC4A_OPS[$op],'returned_catalog'=>false,'native_ids'=>[],'price_rows'=>0,'safe_to_write_now'=>false];
        foreach($groups as $bi=>$g){
            $batch=$bi+1;$requested=array_fill_keys($g['hotel_ids'],true);$pages=0;$pagesCount=null;$rowsSeen=0;$unboundNative=[];$hashes=[];$batchState='complete';$err=null;
            for($page=1;$page<=SLC4A_PAGE_CAP;$page++){
                $params=['TOWNFROMINC'=>$townfrom,'STATEINC'=>$g['stateinc'],'CHECKIN_BEG'=>$from,'CHECKIN_END'=>$to,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>$page,'OPERATORS'=>(string)$g['operator_id'],'HOTELS'=>implode(',',$g['hotel_ids']),'GROUP_BY'=>32];
                slc4a_save($dir.'/batch-'.str_pad((string)$batch,3,'0',STR_PAD_LEFT).'-page-'.$page.'-reserved.json',['operation'=>SLC4A_OP,'batch'=>$batch,'page'=>$page,'stateinc'=>$g['stateinc'],'operator_id'=>$g['operator_id'],'hotel_count'=>count($g['hotel_ids']),'state'=>'reserved_before_price']);
                try{
                    $cl=new AnyTourAndromedaClient($call,true);$cl->restorePrivateSession($session);$reply=$cl->price($params);
                }catch(Throwable $e){$batchState='supplier_error';$err=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,100,'UTF-8'));$batchErrors++;break;}
                $pages++;$pc=(int)($reply['PAGES_COUNT']??-1);slc4a_need($pc>=0&&$pc<=SLC4A_PAGE_CAP,'pages_count');$pagesCount=$pc;
                $raw=slc4a_json($reply);$rh=hash('sha256',$raw);$hashes[]=$rh;
                file_put_contents($evidenceDir.'/batch-'.str_pad((string)$batch,3,'0',STR_PAD_LEFT).'-page-'.$page.'.json',$raw."\n",LOCK_EX);
                foreach(($reply['PRICES']??[]) as $price){
                    if(!is_array($price))continue;$bridge=slc4a_bridge($price,$g['operator_id'],$requested);
                    if($bridge['state']==='other_operator')continue;$rowsSeen++;
                    if(($bridge['catalog_id']??null)!==null){
                        $key=$bridge['catalog_id'].'|'.$g['operator_id'];if(!isset($edgeMap[$key]))continue;$edgeMap[$key]['price_rows']++;
                        if($bridge['state']==='exact_catalog_to_native'){$edgeMap[$key]['returned_catalog']=true;$edgeMap[$key]['native_ids'][$bridge['native_id']]=true;}
                        elseif($bridge['state']==='catalog_only')$edgeMap[$key]['returned_catalog']=true;
                    }elseif($bridge['state']==='unbound_operator_native'&&($bridge['native_id']??null)!==null)$unboundNative[$bridge['native_id']]=true;
                }
                if($pc===0||$page>=$pc)break;
            }
            $batchOut[]=['batch'=>$batch,'state'=>$batchState,'reason'=>$err,'stateinc'=>$g['stateinc'],'operator_id'=>$g['operator_id'],'hotel_count'=>count($g['hotel_ids']),'pages_count'=>$pagesCount,'pages_drained'=>$pages,'price_rows_seen'=>$rowsSeen,'unbound_operator_native_count'=>count($unboundNative),'response_sha256s'=>$hashes];
        }
        foreach($edgeMap as $e){
            $ids=array_keys($e['native_ids']);sort($ids,SORT_NATURAL);unset($e['native_ids']);$e['positive_native_candidates']=$ids;
            if(count($ids)===1)$e['state']='captured_single_native';
            elseif(count($ids)>1)$e['state']='captured_ambiguous_native';
            elseif($e['returned_catalog'])$e['state']='catalog_only';
            else $e['state']='not_returned_in_context';
            $edges[]=$e;
        }
        $state=$batchErrors===0?'completed_read_only':'completed_read_only_partial';
    }catch(Throwable $e){
        $reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8'));
        $state=$calls>0?'terminal_failed_no_replay':'failed_before_provider';
    }
    $counts=[];$byOp=[];$nativeTargets=[];
    foreach($edges as $e){$counts[$e['state']]=($counts[$e['state']]??0)+1;$op=(string)$e['operator_id'];$byOp[$op][$e['state']]=($byOp[$op][$e['state']]??0)+1;
        if($e['state']==='captured_single_native')$nativeTargets[$e['namespace'].'|'.$e['positive_native_candidates'][0]][$e['catalog_id']]=true;
    }
    ksort($counts);ksort($byOp);$unique=0;$colliding=0;foreach($nativeTargets as $targets){if(count($targets)===1)$unique++;else$colliding++;}
    $out=['operation'=>SLC4A_OP,'state'=>$state,'reason'=>$reason,'source_sha'=>$sourceSha,'plan_operation'=>SLC4A_PLAN_OP,'plan_result_sha256'=>hash_file('sha256',$planPath),
        'catalog_target_count'=>1887,'acquisition_ready_count'=>1753,'queried_edge_count'=>count($edges),'planned_batch_count'=>count($groups),'completed_batch_count'=>count($batchOut),'batch_error_count'=>$batchErrors,
        'townfrom'=>$townfrom,'checkin_beg'=>$from,'checkin_end'=>$to,'nights'=>7,'adults'=>2,'operator_ids'=>array_keys(SLC4A_OPS),'samo_http_calls'=>$calls,
        'edge_state_counts'=>$counts,'operator_state_counts'=>$byOp,'single_native_source_unique_count'=>$unique,'single_native_source_collision_count'=>$colliding,
        'batches'=>$batchOut,'edges'=>$edges,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    $h=slc4a_save($dir.'/result.json',$out);slc4a_save($dir.'/receipt.json',['operation'=>SLC4A_OP,'state'=>$state,'result_sha256'=>$h,'provider_accessed'=>$calls>0,'samo_http_calls'=>$calls,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>$calls>0]);
    echo slc4a_json(['state'=>$state,'reason'=>$reason,'planned_batch_count'=>count($groups),'completed_batch_count'=>count($batchOut),'batch_error_count'=>$batchErrors,'samo_http_calls'=>$calls,'edge_state_counts'=>$counts,'operator_state_counts'=>$byOp,'single_native_source_unique_count'=>$unique,'single_native_source_collision_count'=>$colliding])."\n";
    return in_array($state,['completed_read_only','completed_read_only_partial'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){
        $req=['2001'=>true,'2002'=>true];
        $x=slc4a_bridge(['operatorKey'=>315,'hotelKey'=>'2001','isOperatorHotelKey'=>0,'original'=>['hotelKey'=>'8123','operatorKey'=>315]],315,$req);
        slc4a_need($x===['state'=>'exact_catalog_to_native','catalog_id'=>'2001','native_id'=>'8123'],'bridge');
        $x=slc4a_bridge(['operatorKey'=>315,'hotelKey'=>'2001','isOperatorHotelKey'=>0],315,$req);slc4a_need($x['state']==='catalog_only','catalog');
        $rows=[['catalog_id'=>'1','stateinc'=>5,'missing_operator_ids'=>[5,315]],['catalog_id'=>'2','stateinc'=>5,'missing_operator_ids'=>[315]]];
        $g=slc4a_groups($rows);slc4a_need(count($g)===2,'groups');
        $long=array_map(fn($i)=>(string)(100000000000000000+$i),range(1,25));$chunks=slc4a_chunks($long);slc4a_need(count($chunks)>=3,'chunk_count');foreach($chunks as $c)slc4a_need(count($c)<=12&&strlen(implode(',',$c))<=300,'chunk_shape');
        echo "MATCH_SAMO_BUSINESS_LIVE30_COMMON4_ACQUIRE_V2_SELFTEST_OK\n";exit;
    }
    slc4a_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$plan=(string)getenv('MATCH_PLAN_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    slc4a_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===SLC4A_OP&&is_file($plan)&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'runtime_scope');
    exit(slc4a_execute($root,$dir,$plan,$sha));
}
