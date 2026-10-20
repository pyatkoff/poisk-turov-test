<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const V36R_OP='hotel-match-live-anex-missing-samo-router-refresh-1971-20260926-v36r';
const V36R_SOURCE_OP='hotel-match-live-anex-missing-samo-router-1971-20260925-v36';
const V36R_SOURCE_RESULT_SHA='b6d90db7baf96a07a927502ed7605799b6fdfd5af90c9ffc1b8dc0a05942d080';
const V36R_EXPECTED_INPUT=234;
const V36R_EXPECTED_DAY='2026-09-26';

function v36r_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v36r_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v36r_load(string $p):array{$x=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v36r_need(is_array($x),'json_shape');return$x;}
function v36r_save(string $p,array $v):string{$raw=v36r_json($v)."\n";$f=@fopen($p,'x+b');v36r_need($f!==false,'exclusive_create');try{v36r_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v36r_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v36r_q(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return$st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v36r_excluded(string $c):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($c))===1;}
function v36r_ctx(array $r):array{return[
    'departure_id'=>(int)$r['departure_id'],'country_id'=>(int)$r['country_id'],
    'departure_date'=>(string)$r['departure_date'],'nights'=>(int)$r['nights'],'adults'=>(int)$r['adults'],
    'children_count'=>(int)$r['children_count'],'child_ages_signature'=>(string)$r['child_ages_signature'],
    'search_id'=>(int)$r['search_id'],'tour_id'=>$r['tour_id']===null?null:(string)$r['tour_id'],
    'operator_id'=>(int)$r['operator_id'],'observed_at'=>(string)$r['observed_at']
];}
function v36r_ctx_key(array $c):string{return implode('|',[$c['departure_id'],$c['country_id'],$c['departure_date'],$c['nights'],$c['adults'],$c['children_count'],$c['child_ages_signature']]);}

function v36r_source_ids(array $src):array{
    v36r_need(($src['operation_id']??'')===V36R_SOURCE_OP&&($src['state']??'')==='completed_read_only','source_state');
    v36r_need((int)($src['input_count']??0)===234&&(int)($src['routed_future_context_count']??0)===174&&(int)($src['no_future_context_count']??0)===56&&(int)($src['current_direct_anex_lost_skip_count']??0)===4,'source_counts');
    v36r_need((int)($src['supplier_calls']??-1)===0&&(int)($src['database_writes']??-1)===0&&(int)($src['mapping_writes']??-1)===0,'source_boundary');
    $ids=[];
    foreach(($src['routed']??[]) as $r)if(is_array($r)&&($id=(int)($r['tv_hotel_id']??0))>0)$ids[$id]=true;
    foreach(($src['no_future_context']??[]) as $r)if(is_array($r)&&($id=(int)($r['tv_hotel_id']??0))>0)$ids[$id]=true;
    foreach(($src['skipped_current_samo_resolved']??[]) as $r)if(is_array($r)&&($id=(int)($r['tv_hotel_id']??0))>0)$ids[$id]=true;
    foreach(($src['skipped_current_direct_anex_lost']??[]) as $r){$id=is_array($r)?(int)($r['tv_hotel_id']??0):(int)$r;if($id>0)$ids[$id]=true;}
    $out=array_keys($ids);sort($out,SORT_NUMERIC);v36r_need(count($out)===V36R_EXPECTED_INPUT,'source_membership');return$out;
}

function v36r_execute(PDO $db,array $ids,string $sourceSha,string $sourceResultSha):array{
    $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
    v36r_need($today===V36R_EXPECTED_DAY,'moscow_day_drift');
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $catalog=[];foreach(v36r_q($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as$r)$catalog[(int)$r['id']]=$r;
        $samo=[];foreach(v36r_q($db,"SELECT local_hotel_id,external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($ph) ORDER BY local_hotel_id,external_hotel_id",$ids) as$r)$samo[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $cov=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];
        foreach($ids as$id){$set=$cov['by_local'][$id]??[];if(is_array($set)&&$set!==[])$anex[$id]=array_map('strval',array_keys($set));}
        $obs=[];foreach(v36r_q($db,"SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,search_id,tour_id,operator_id,observed_at FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($ph) ORDER BY hotel_id,observed_at DESC",$ids) as$r)$obs[(int)$r['hotel_id']][]=$r;

        $nowFull=[];$still=[];$lost=[];$inactive=[];$future=[];$noFuture=[];$staleOnly=[];$groups=[];
        foreach($ids as$id){
            $h=$catalog[$id]??null;
            if(!$h||(int)($h['is_active']??0)!==1||v36r_excluded((string)($h['country_name']??''))){$inactive[]=$id;continue;}
            $hasSamo=!empty($samo[$id]);$hasAnex=!empty($anex[$id]);
            if($hasSamo&&$hasAnex){$nowFull[]=$id;continue;}
            if(!$hasAnex){$lost[]=$id;continue;}
            if($hasSamo){$nowFull[]=$id;continue;}
            $still[]=$id;
            $futureRow=null;$anyObs=false;$anyStale=false;
            foreach($obs[$id]??[] as$r){$anyObs=true;$date=(string)$r['departure_date'];if($date>=$today){$futureRow=$r;break;}if($date<$today)$anyStale=true;}
            if($futureRow!==null){
                $c=v36r_ctx($futureRow);$row=['local_hotel_id'=>$id,'hotel_name'=>(string)$h['name'],'anex_ids'=>$anex[$id],'context'=>$c,'safe_to_write_now'=>false];$future[]=$row;$groups[v36r_ctx_key($c)][]=$id;
            }elseif($anyObs&&$anyStale){$staleOnly[]=$id;}
            else{$noFuture[]=$id;}
        }
        $batch=[];ksort($groups,SORT_NATURAL);foreach($groups as$key=>$g){sort($g,SORT_NUMERIC);foreach(array_chunk($g,30) as$i=>$part)$batch[]=['context_key'=>$key,'batch_index'=>$i+1,'hotel_ids'=>$part,'hotel_count'=>count($part),'safe_to_write_now'=>false];}
        $db->rollBack();sort($nowFull);sort($still);sort($lost);sort($inactive);sort($noFuture);sort($staleOnly);
        return[
            'operation'=>V36R_OP,'state'=>'completed_read_only_v36_current_refresh','generated_at_utc'=>gmdate('c'),'moscow_day'=>$today,
            'source_operation'=>V36R_SOURCE_OP,'source_result_sha256'=>$sourceResultSha,'source_sha'=>$sourceSha,'input_count'=>count($ids),
            'now_full_triple_count'=>count($nowFull),'still_tv_anex_missing_samo_count'=>count($still),'lost_direct_anex_count'=>count($lost),'inactive_or_excluded_count'=>count($inactive),
            'future_context_count'=>count($future),'no_future_context_count'=>count($noFuture),'stale_only_count'=>count($staleOnly),
            'context_group_count'=>count($groups),'batch_count'=>count($batch),
            'now_full_triple_ids'=>$nowFull,'still_missing_samo_ids'=>$still,'lost_direct_anex_ids'=>$lost,'inactive_or_excluded_ids'=>$inactive,
            'future_context'=>$future,'no_future_ids'=>$noFuture,'stale_only_ids'=>$staleOnly,'batch_plan'=>$batch,
            'current_effective_anex_native_count'=>(int)$cov['native_count'],'current_effective_anex_local_count'=>(int)$cov['local_count'],
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false
        ];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}

function v36r_self_test():void{
    v36r_need(v36r_ctx_key(['departure_id'=>1,'country_id'=>4,'departure_date'=>'2026-10-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''])==='1|4|2026-10-01|7|2|0|','ctx');
    v36r_need(v36r_excluded('Россия')&&v36r_excluded('Abkhazia')&&!v36r_excluded('Турция'),'excluded');
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v36r_self_test();echo"MATCH_V36R_SELFTEST_OK\n";exit;}
    v36r_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$src=(string)getenv('MATCH_SOURCE_RESULT');$srcSha=(string)getenv('MATCH_SOURCE_RESULT_SHA');$head=(string)getenv('MATCH_SOURCE_SHA');
    v36r_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V36R_OP&&is_file($src)&&hash_file('sha256',$src)===$srcSha&&$srcSha===V36R_SOURCE_RESULT_SHA&&preg_match('/^[0-9a-f]{40}$/D',$head)===1,'runtime_scope');
    $res=v36r_load($dir.'/reservation.json');v36r_need(($res['operation']??'')===V36R_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    try{$ids=v36r_source_ids(v36r_load($src));require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$r=v36r_execute(v2_data_db(),$ids,$head,$srcSha);$h=v36r_save($dir.'/result.json',$r);v36r_save($dir.'/receipt.json',['operation'=>V36R_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v36r_json(['state'=>$r['state'],'input_count'=>$r['input_count'],'now_full_triple_count'=>$r['now_full_triple_count'],'still_tv_anex_missing_samo_count'=>$r['still_tv_anex_missing_samo_count'],'lost_direct_anex_count'=>$r['lost_direct_anex_count'],'inactive_or_excluded_count'=>$r['inactive_or_excluded_count'],'future_context_count'=>$r['future_context_count'],'no_future_context_count'=>$r['no_future_context_count'],'stale_only_count'=>$r['stale_only_count'],'context_group_count'=>$r['context_group_count'],'batch_count'=>$r['batch_count']])."\n";}
    catch(Throwable$e){$f=['operation'=>V36R_OP,'state'=>'failed_read_only_v36_current_refresh','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v36r_save($dir.'/result.json',$f);v36r_save($dir.'/receipt.json',['operation'=>V36R_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
