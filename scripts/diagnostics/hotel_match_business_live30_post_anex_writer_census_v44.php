<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const V44_OP='hotel-match-business-live30-post-anex-writer-census-1971-20260925-v44';
const V44_V43_OP='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260925-v43';
const V44_V43_DIGEST='86acbd0baa8fd5df55a3e0814764cf4ef3f04a29047139698208d3ba5ff1f856';
const V44_BASELINE=['tv_live30_total'=>4399,'full_triple_total'=>2662,'tv_samo_missing_anex'=>777,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726];

function v44_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v44_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v44_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,256,JSON_THROW_ON_ERROR);v44_need(is_array($v),'json_shape');return$v;}
function v44_save(string $p,array $v):string{$raw=v44_json($v)."\n";$f=@fopen($p,'x+b');v44_need($f!==false,'exclusive_create');try{v44_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v44_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v44_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v44_excluded(string $c):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($c))===1;}
function v44_bucket(bool $s,bool $a):string{return $s?($a?'full_triple':'tv_samo_missing_anex'):($a?'tv_anex_missing_samo':'tv_only_missing_both');}
function v44_verify_v43(string $resultPath,string $receiptPath):array{
    v44_need(is_file($resultPath)&&!is_link($resultPath)&&is_file($receiptPath)&&!is_link($receiptPath),'v43_files');
    $raw=(string)file_get_contents($resultPath);$sha=hash('sha256',$raw);$r=json_decode($raw,true,256,JSON_THROW_ON_ERROR);$q=v44_load($receiptPath);
    v44_need(($q['result_sha256']??'')===$sha&&($q['readback_verified']??false)===true,'v43_receipt');
    v44_need(($r['operation']??'')===V44_V43_OP&&($r['state']??'')==='committed_verified','v43_state');
    v44_need((int)($r['inserted']??0)===16&&(int)($r['database_writes']??0)===16&&(int)($r['mapping_writes']??0)===16,'v43_counts');
    v44_need(($r['registry_readback_verified']??false)===true&&($r['mapping_digest']??'')===V44_V43_DIGEST,'v43_readback');
    v44_need((int)($r['provider_http_calls']??-1)===0,'v43_provider');
    $targets=[];foreach($r['rows']??[] as $x){if(!is_array($x))continue;$id=(int)($x['catalog_hotel_id']??0);if($id>0)$targets[$id]=true;}
    v44_need(count($targets)===16,'v43_targets');
    return ['result_sha256'=>$sha,'targets'=>$targets];
}
function v44_execute(PDO $db,array $v43,string $sourceSha):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];foreach(v44_query($db,"SELECT id,country_name,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id") as $r){$id=(int)$r['id'];if($id>0&&!v44_excluded((string)$r['country_name']))$hotels[$id]=true;}
        $samo=[];foreach(v44_query($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){$id=(int)$r['local_hotel_id'];if(isset($hotels[$id]))$samo[$id]=true;}
        $cov=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];foreach(($cov['by_local']??[]) as $id=>$set){$id=(int)$id;if(isset($hotels[$id])&&is_array($set)&&$set!==[])$anex[$id]=true;}
        $cut=gmdate('Y-m-d H:i:s',time()-30*86400);$q=$db->prepare('SELECT hotel_id,MAX(last_seen_at) m FROM tour_operator_identity_observations GROUP BY hotel_id HAVING m>=?');$q->execute([$cut]);$tv=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)$r['hotel_id'];if(isset($hotels[$id]))$tv[$id]=true;}
        $counts=['full_triple'=>0,'tv_samo_missing_anex'=>0,'tv_anex_missing_samo'=>0,'tv_only_missing_both'=>0];
        foreach($tv as $id=>$_)$counts[v44_bucket(isset($samo[$id]),isset($anex[$id]))]++;
        $v43States=[];$v43Full=0;foreach(array_keys($v43['targets']) as $id){$b=isset($tv[$id])?v44_bucket(isset($samo[$id]),isset($anex[$id])):'not_tv_live30';$v43States[$b]=($v43States[$b]??0)+1;if($b==='full_triple')$v43Full++;}
        $db->rollBack();
        $current=['tv_live30_total'=>count($tv),'full_triple_total'=>$counts['full_triple'],'tv_samo_missing_anex'=>$counts['tv_samo_missing_anex'],'tv_anex_missing_samo'=>$counts['tv_anex_missing_samo'],'tv_only_missing_both'=>$counts['tv_only_missing_both']];
        $delta=[];foreach(V44_BASELINE as $k=>$v)$delta[$k]=$current[$k]-$v;
        return ['operation'=>V44_OP,'state'=>'completed_read_only_post_anex_writer_census','generated_at_utc'=>gmdate('c'),'cutoff_utc'=>$cut,'source_sha'=>$sourceSha,'v43_result_sha256'=>$v43['result_sha256'],
            'baseline'=>V44_BASELINE,'current'=>$current,'delta_vs_v33'=>$delta,'v43_target_count'=>count($v43['targets']),'v43_targets_full_triple'=>$v43Full,'v43_target_states'=>$v43States,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v44_self_test():void{
    v44_need(v44_bucket(true,true)==='full_triple','f');v44_need(v44_bucket(true,false)==='tv_samo_missing_anex','s');v44_need(v44_bucket(false,true)==='tv_anex_missing_samo','a');v44_need(v44_bucket(false,false)==='tv_only_missing_both','b');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v44_self_test();echo"MATCH_POST_ANEX_WRITER_CENSUS_V44_SELFTEST_OK\n";exit;}
    v44_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$vr=(string)getenv('MATCH_V43_RESULT');$vq=(string)getenv('MATCH_V43_RECEIPT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v44_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V44_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'scope');$res=v44_load($dir.'/reservation.json');v44_need(($res['operation']??'')===V44_OP,'reservation');
    try{$v43=v44_verify_v43($vr,$vq);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$r=v44_execute(v2_data_db(),$v43,$sha);$h=v44_save($dir.'/result.json',$r);v44_save($dir.'/receipt.json',['operation'=>V44_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v44_json(['state'=>$r['state'],'current'=>$r['current'],'delta_vs_v33'=>$r['delta_vs_v33'],'v43_targets_full_triple'=>$r['v43_targets_full_triple'],'v43_target_states'=>$r['v43_target_states']])."\n";}
    catch(Throwable $e){$f=['operation'=>V44_OP,'state'=>'failed_read_only_post_anex_writer_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v44_save($dir.'/result.json',$f);v44_save($dir.'/receipt.json',['operation'=>V44_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
