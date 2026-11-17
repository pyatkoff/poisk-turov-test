<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const V52_OP='hotel-match-business-live30-post-anex-writer-census-1971-20260926-v52b';
const V52_V51_OP='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260926-v51';
const V52_V51_DIGEST='1fb6c78eb31808f9c0d466317c5c247740be4d4ca91d7f0f4488eaf2c89f1f41';
const V52_EXPECTED=12;
const V52_BASELINE=['tv_live30_total'=>4399,'full_triple_total'=>2695,'tv_samo_missing_anex'=>744,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726];

function v52_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v52_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v52_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,256,JSON_THROW_ON_ERROR);v52_need(is_array($v),'json_shape');return $v;}
function v52_save(string $p,array $v):string{$raw=v52_json($v)."\n";$f=@fopen($p,'x+b');v52_need($f!==false,'exclusive_create');try{v52_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v52_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v52_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v52_excluded(string $c):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($c))===1;}
function v52_bucket(bool $s,bool $a):string{return $s?($a?'full_triple':'tv_samo_missing_anex'):($a?'tv_anex_missing_samo':'tv_only_missing_both');}

function v52_verify_v51(string $resultPath,string $receiptPath):array{
    v52_need(is_file($resultPath)&&!is_link($resultPath)&&is_file($receiptPath)&&!is_link($receiptPath),'v51_files');
    $raw=(string)file_get_contents($resultPath);$sha=hash('sha256',$raw);$r=json_decode($raw,true,256,JSON_THROW_ON_ERROR);$q=v52_load($receiptPath);
    v52_need(($q['result_sha256']??'')===$sha&&($q['readback_verified']??false)===true,'v51_receipt');
    v52_need(($r['operation']??'')===V52_V51_OP&&($r['state']??'')==='committed_verified','v51_state');
    v52_need((int)($r['inserted']??0)===V52_EXPECTED&&(int)($r['database_writes']??0)===V52_EXPECTED&&(int)($r['mapping_writes']??0)===V52_EXPECTED,'v51_counts');
    v52_need(($r['registry_readback_verified']??false)===true&&($r['readback_verified']??false)===true,'v51_readback');
    v52_need(($r['mapping_digest']??'')===V52_V51_DIGEST,'v51_digest');
    v52_need((int)($r['provider_http_calls']??-1)===0&&($q['provider_accessed']??true)===false,'v51_provider');
    $targets=[];foreach($r['rows']??[] as$x){if(!is_array($x))continue;$id=(int)($x['catalog_hotel_id']??0);if($id>0)$targets[$id]=true;}
    v52_need(count($targets)===V52_EXPECTED,'v51_targets');
    return ['result_sha256'=>$sha,'targets'=>$targets];
}
function v52_execute(PDO $db,array $v51,string $sourceSha):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];foreach(v52_query($db,"SELECT id,country_name,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id") as$r){$id=(int)$r['id'];if($id>0&&!v52_excluded((string)$r['country_name']))$hotels[$id]=true;}
        $samo=[];foreach(v52_query($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as$r){$id=(int)$r['local_hotel_id'];if(isset($hotels[$id]))$samo[$id]=true;}
        $cov=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];foreach(($cov['by_local']??[]) as$id=>$set){$id=(int)$id;if(isset($hotels[$id])&&is_array($set)&&$set!==[])$anex[$id]=true;}
        $cut=gmdate('Y-m-d H:i:s',time()-30*86400);$q=$db->prepare('SELECT hotel_id,MAX(last_seen_at) m FROM tour_operator_identity_observations GROUP BY hotel_id HAVING m>=?');$q->execute([$cut]);$tv=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as$r){$id=(int)$r['hotel_id'];if(isset($hotels[$id]))$tv[$id]=true;}
        $counts=['full_triple'=>0,'tv_samo_missing_anex'=>0,'tv_anex_missing_samo'=>0,'tv_only_missing_both'=>0];foreach($tv as$id=>$_)$counts[v52_bucket(isset($samo[$id]),isset($anex[$id]))]++;
        $states=[];$full=0;foreach(array_keys($v51['targets']) as$id){$b=isset($tv[$id])?v52_bucket(isset($samo[$id]),isset($anex[$id])):'not_tv_live30';$states[$b]=($states[$b]??0)+1;if($b==='full_triple')$full++;}
        $db->rollBack();
        $current=['tv_live30_total'=>count($tv),'full_triple_total'=>$counts['full_triple'],'tv_samo_missing_anex'=>$counts['tv_samo_missing_anex'],'tv_anex_missing_samo'=>$counts['tv_anex_missing_samo'],'tv_only_missing_both'=>$counts['tv_only_missing_both']];
        $delta=[];foreach(V52_BASELINE as$k=>$v)$delta[$k]=$current[$k]-$v;
        return ['operation'=>V52_OP,'state'=>'completed_read_only_post_anex_writer_census','generated_at_utc'=>gmdate('c'),'cutoff_utc'=>$cut,'source_sha'=>$sourceSha,'v51_result_sha256'=>$v51['result_sha256'],
            'baseline'=>V52_BASELINE,'current'=>$current,'delta_vs_v48'=>$delta,'v51_target_count'=>count($v51['targets']),'v51_targets_full_triple'=>$full,'v51_target_states'=>$states,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v52_self_test():void{
    v52_need(v52_bucket(true,true)==='full_triple','full');v52_need(v52_bucket(true,false)==='tv_samo_missing_anex','samo');v52_need(v52_bucket(false,true)==='tv_anex_missing_samo','anex');v52_need(v52_bucket(false,false)==='tv_only_missing_both','both');
    v52_need(V52_BASELINE['full_triple_total']===2695&&V52_BASELINE['tv_samo_missing_anex']===744,'baseline');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v52_self_test();echo"MATCH_POST_ANEX_WRITER_CENSUS_V52_SELFTEST_OK\n";exit;}
    v52_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$vr=(string)getenv('MATCH_V51_RESULT');$vq=(string)getenv('MATCH_V51_RECEIPT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v52_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V52_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'scope');$res=v52_load($dir.'/reservation.json');v52_need(($res['operation']??'')===V52_OP,'reservation');
    try{$v51=v52_verify_v51($vr,$vq);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$r=v52_execute(v2_data_db(),$v51,$sha);$h=v52_save($dir.'/result.json',$r);v52_save($dir.'/receipt.json',['operation'=>V52_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v52_json(['state'=>$r['state'],'current'=>$r['current'],'delta_vs_v48'=>$r['delta_vs_v48'],'v51_targets_full_triple'=>$r['v51_targets_full_triple'],'v51_target_states'=>$r['v51_target_states']])."\n";}
    catch(Throwable$e){$f=['operation'=>V52_OP,'state'=>'failed_read_only_post_anex_writer_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v52_save($dir.'/result.json',$f);v52_save($dir.'/receipt.json',['operation'=>V52_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
