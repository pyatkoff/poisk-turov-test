<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const V56_OP='hotel-match-business-live30-post-anex-writer-census-1971-20260926-v56';
const V56_V55_OP='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260926-v55';
const V56_V55_DIGEST='79dfa2123f09d9ed0156b29251a06907598037634fc6fd094c8d99581434665c';
const V56_EXPECTED=2;
const V56_BASELINE=['tv_live30_total'=>4399,'full_triple_total'=>2707,'tv_samo_missing_anex'=>732,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726];

function v56_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v56_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v56_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,256,JSON_THROW_ON_ERROR);v56_need(is_array($v),'json_shape');return $v;}
function v56_save(string $p,array $v):string{$raw=v56_json($v)."\n";$f=@fopen($p,'x+b');v56_need($f!==false,'exclusive_create');try{v56_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v56_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v56_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v56_excluded(string $c):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($c))===1;}
function v56_bucket(bool $s,bool $a):string{return $s?($a?'full_triple':'tv_samo_missing_anex'):($a?'tv_anex_missing_samo':'tv_only_missing_both');}

function v56_verify_v55(string $resultPath,string $receiptPath):array{
    v56_need(is_file($resultPath)&&!is_link($resultPath)&&is_file($receiptPath)&&!is_link($receiptPath),'v55_files');
    $raw=(string)file_get_contents($resultPath);$sha=hash('sha256',$raw);$r=json_decode($raw,true,256,JSON_THROW_ON_ERROR);$q=v56_load($receiptPath);
    v56_need(($q['result_sha256']??'')===$sha&&($q['readback_verified']??false)===true,'v55_receipt');
    v56_need(($r['operation']??'')===V56_V55_OP&&($r['state']??'')==='committed_verified','v55_state');
    v56_need((int)($r['inserted']??0)===V56_EXPECTED&&(int)($r['database_writes']??0)===V56_EXPECTED&&(int)($r['mapping_writes']??0)===V56_EXPECTED,'v55_counts');
    v56_need(($r['registry_readback_verified']??false)===true&&($r['readback_verified']??false)===true,'v55_readback');
    v56_need(($r['mapping_digest']??'')===V56_V55_DIGEST,'v55_digest');
    v56_need((int)($r['provider_http_calls']??-1)===0&&($q['provider_accessed']??true)===false,'v55_provider');
    $targets=[];foreach($r['rows']??[] as$x){if(!is_array($x))continue;$id=(int)($x['catalog_hotel_id']??0);if($id>0)$targets[$id]=true;}
    v56_need(count($targets)===V56_EXPECTED,'v55_targets');
    return ['result_sha256'=>$sha,'targets'=>$targets];
}
function v56_execute(PDO $db,array $v55,string $sourceSha):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];foreach(v56_query($db,"SELECT id,country_name,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id") as$r){$id=(int)$r['id'];if($id>0&&!v56_excluded((string)$r['country_name']))$hotels[$id]=true;}
        $samo=[];foreach(v56_query($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as$r){$id=(int)$r['local_hotel_id'];if(isset($hotels[$id]))$samo[$id]=true;}
        $cov=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];foreach(($cov['by_local']??[]) as$id=>$set){$id=(int)$id;if(isset($hotels[$id])&&is_array($set)&&$set!==[])$anex[$id]=true;}
        $cut=gmdate('Y-m-d H:i:s',time()-30*86400);$q=$db->prepare('SELECT hotel_id,MAX(last_seen_at) m FROM tour_operator_identity_observations GROUP BY hotel_id HAVING m>=?');$q->execute([$cut]);$tv=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as$r){$id=(int)$r['hotel_id'];if(isset($hotels[$id]))$tv[$id]=true;}
        $counts=['full_triple'=>0,'tv_samo_missing_anex'=>0,'tv_anex_missing_samo'=>0,'tv_only_missing_both'=>0];foreach($tv as$id=>$_)$counts[v56_bucket(isset($samo[$id]),isset($anex[$id]))]++;
        $states=[];$full=0;foreach(array_keys($v55['targets']) as$id){$b=isset($tv[$id])?v56_bucket(isset($samo[$id]),isset($anex[$id])):'not_tv_live30';$states[$b]=($states[$b]??0)+1;if($b==='full_triple')$full++;}
        $db->rollBack();
        $current=['tv_live30_total'=>count($tv),'full_triple_total'=>$counts['full_triple'],'tv_samo_missing_anex'=>$counts['tv_samo_missing_anex'],'tv_anex_missing_samo'=>$counts['tv_anex_missing_samo'],'tv_only_missing_both'=>$counts['tv_only_missing_both']];
        $delta=[];foreach(V56_BASELINE as$k=>$v)$delta[$k]=$current[$k]-$v;
        return ['operation'=>V56_OP,'state'=>'completed_read_only_post_anex_writer_census','generated_at_utc'=>gmdate('c'),'cutoff_utc'=>$cut,'source_sha'=>$sourceSha,'v55_result_sha256'=>$v55['result_sha256'],
            'baseline'=>V56_BASELINE,'current'=>$current,'delta_vs_v52b'=>$delta,'v55_target_count'=>count($v55['targets']),'v55_targets_full_triple'=>$full,'v55_target_states'=>$states,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v56_self_test():void{
    v56_need(v56_bucket(true,true)==='full_triple','full');v56_need(v56_bucket(true,false)==='tv_samo_missing_anex','samo');v56_need(v56_bucket(false,true)==='tv_anex_missing_samo','anex');v56_need(v56_bucket(false,false)==='tv_only_missing_both','both');
    v56_need(V56_BASELINE['full_triple_total']===2707&&V56_BASELINE['tv_samo_missing_anex']===732,'baseline');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v56_self_test();echo"MATCH_POST_ANEX_WRITER_CENSUS_V56_SELFTEST_OK\n";exit;}
    v56_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$vr=(string)getenv('MATCH_V55_RESULT');$vq=(string)getenv('MATCH_V55_RECEIPT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v56_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V56_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'scope');$res=v56_load($dir.'/reservation.json');v56_need(($res['operation']??'')===V56_OP,'reservation');
    try{$v55=v56_verify_v55($vr,$vq);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$r=v56_execute(v2_data_db(),$v55,$sha);$h=v56_save($dir.'/result.json',$r);v56_save($dir.'/receipt.json',['operation'=>V56_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v56_json(['state'=>$r['state'],'current'=>$r['current'],'delta_vs_v52b'=>$r['delta_vs_v52b'],'v55_targets_full_triple'=>$r['v55_targets_full_triple'],'v55_target_states'=>$r['v55_target_states']])."\n";}
    catch(Throwable$e){$f=['operation'=>V56_OP,'state'=>'failed_read_only_post_anex_writer_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v56_save($dir.'/result.json',$f);v56_save($dir.'/receipt.json',['operation'=>V56_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
