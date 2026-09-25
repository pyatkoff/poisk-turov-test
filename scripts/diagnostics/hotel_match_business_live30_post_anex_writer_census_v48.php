<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const V48_OP='hotel-match-business-live30-post-anex-writer-census-1971-20260926-v48';
const V48_V47_OP='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260925-v47';
const V48_V47_DIGEST='5de4e12e61862cf42c9004965e0174f067ba7d665583ee96b5e3edc78260ab03';
const V48_EXPECTED=17;
const V48_BASELINE=['tv_live30_total'=>4399,'full_triple_total'=>2678,'tv_samo_missing_anex'=>761,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726];

function v48_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v48_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v48_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,256,JSON_THROW_ON_ERROR);v48_need(is_array($v),'json_shape');return $v;}
function v48_save(string $p,array $v):string{$raw=v48_json($v)."\n";$f=@fopen($p,'x+b');v48_need($f!==false,'exclusive_create');try{v48_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v48_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v48_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v48_excluded(string $c):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($c))===1;}
function v48_bucket(bool $s,bool $a):string{return $s?($a?'full_triple':'tv_samo_missing_anex'):($a?'tv_anex_missing_samo':'tv_only_missing_both');}

function v48_verify_v47(string $resultPath,string $receiptPath):array{
    v48_need(is_file($resultPath)&&!is_link($resultPath)&&is_file($receiptPath)&&!is_link($receiptPath),'v47_files');
    $raw=(string)file_get_contents($resultPath);$sha=hash('sha256',$raw);$r=json_decode($raw,true,256,JSON_THROW_ON_ERROR);$q=v48_load($receiptPath);
    v48_need(($q['result_sha256']??'')===$sha&&($q['readback_verified']??false)===true,'v47_receipt');
    v48_need(($r['operation']??'')===V48_V47_OP&&($r['state']??'')==='committed_verified','v47_state');
    v48_need((int)($r['inserted']??0)===V48_EXPECTED&&(int)($r['database_writes']??0)===V48_EXPECTED&&(int)($r['mapping_writes']??0)===V48_EXPECTED,'v47_counts');
    v48_need(($r['registry_readback_verified']??false)===true&&($r['readback_verified']??false)===true,'v47_readback');
    v48_need(($r['mapping_digest']??'')===V48_V47_DIGEST,'v47_digest');
    v48_need((int)($r['provider_http_calls']??-1)===0&&($q['provider_accessed']??true)===false,'v47_provider');
    $targets=[];foreach($r['rows']??[] as$x){if(!is_array($x))continue;$id=(int)($x['catalog_hotel_id']??0);if($id>0)$targets[$id]=true;}
    v48_need(count($targets)===V48_EXPECTED,'v47_targets');
    return ['result_sha256'=>$sha,'targets'=>$targets];
}
function v48_execute(PDO $db,array $v47,string $sourceSha):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];foreach(v48_query($db,"SELECT id,country_name,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id") as$r){$id=(int)$r['id'];if($id>0&&!v48_excluded((string)$r['country_name']))$hotels[$id]=true;}
        $samo=[];foreach(v48_query($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as$r){$id=(int)$r['local_hotel_id'];if(isset($hotels[$id]))$samo[$id]=true;}
        $cov=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];foreach(($cov['by_local']??[]) as$id=>$set){$id=(int)$id;if(isset($hotels[$id])&&is_array($set)&&$set!==[])$anex[$id]=true;}
        $cut=gmdate('Y-m-d H:i:s',time()-30*86400);$q=$db->prepare('SELECT hotel_id,MAX(last_seen_at) m FROM tour_operator_identity_observations GROUP BY hotel_id HAVING m>=?');$q->execute([$cut]);$tv=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as$r){$id=(int)$r['hotel_id'];if(isset($hotels[$id]))$tv[$id]=true;}
        $counts=['full_triple'=>0,'tv_samo_missing_anex'=>0,'tv_anex_missing_samo'=>0,'tv_only_missing_both'=>0];foreach($tv as$id=>$_)$counts[v48_bucket(isset($samo[$id]),isset($anex[$id]))]++;
        $states=[];$full=0;foreach(array_keys($v47['targets']) as$id){$b=isset($tv[$id])?v48_bucket(isset($samo[$id]),isset($anex[$id])):'not_tv_live30';$states[$b]=($states[$b]??0)+1;if($b==='full_triple')$full++;}
        $db->rollBack();
        $current=['tv_live30_total'=>count($tv),'full_triple_total'=>$counts['full_triple'],'tv_samo_missing_anex'=>$counts['tv_samo_missing_anex'],'tv_anex_missing_samo'=>$counts['tv_anex_missing_samo'],'tv_only_missing_both'=>$counts['tv_only_missing_both']];
        $delta=[];foreach(V48_BASELINE as$k=>$v)$delta[$k]=$current[$k]-$v;
        return ['operation'=>V48_OP,'state'=>'completed_read_only_post_anex_writer_census','generated_at_utc'=>gmdate('c'),'cutoff_utc'=>$cut,'source_sha'=>$sourceSha,'v47_result_sha256'=>$v47['result_sha256'],
            'baseline'=>V48_BASELINE,'current'=>$current,'delta_vs_v44'=>$delta,'v47_target_count'=>count($v47['targets']),'v47_targets_full_triple'=>$full,'v47_target_states'=>$states,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v48_self_test():void{
    v48_need(v48_bucket(true,true)==='full_triple','full');v48_need(v48_bucket(true,false)==='tv_samo_missing_anex','samo');v48_need(v48_bucket(false,true)==='tv_anex_missing_samo','anex');v48_need(v48_bucket(false,false)==='tv_only_missing_both','both');
    v48_need(V48_BASELINE['full_triple_total']===2678&&V48_BASELINE['tv_samo_missing_anex']===761,'baseline');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v48_self_test();echo"MATCH_POST_ANEX_WRITER_CENSUS_V48_SELFTEST_OK\n";exit;}
    v48_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$vr=(string)getenv('MATCH_V47_RESULT');$vq=(string)getenv('MATCH_V47_RECEIPT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v48_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V48_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'scope');$res=v48_load($dir.'/reservation.json');v48_need(($res['operation']??'')===V48_OP,'reservation');
    try{$v47=v48_verify_v47($vr,$vq);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$r=v48_execute(v2_data_db(),$v47,$sha);$h=v48_save($dir.'/result.json',$r);v48_save($dir.'/receipt.json',['operation'=>V48_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v48_json(['state'=>$r['state'],'current'=>$r['current'],'delta_vs_v44'=>$r['delta_vs_v44'],'v47_targets_full_triple'=>$r['v47_targets_full_triple'],'v47_target_states'=>$r['v47_target_states']])."\n";}
    catch(Throwable$e){$f=['operation'=>V48_OP,'state'=>'failed_read_only_post_anex_writer_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v48_save($dir.'/result.json',$f);v48_save($dir.'/receipt.json',['operation'=>V48_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
