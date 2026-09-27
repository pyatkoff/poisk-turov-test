<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_current15_samo82_anex_v74.php';

const W75_OP='hotel-match-retained82-writer-1971-20260927-v75';
const W75_CURRENT_SHA='00a1ffe4177da1441f8c3d823a22c8ca7c1ac72b285f192389c6521413e902cc';
const W75_SOURCES=[
    'tv-c35.json'=>'0d6c09a008710f3727c9904086f579122e492afc87eba4924d0356975568503c',
    'tv-r1.json'=>'4cd23630e97bb31b81bb7e2980a85e51145fff862346fbee51d0e1a6e85bad24',
    'tv-c0.json'=>'bec4c11bcb9099a0e8ad61c1d0afce81236e73ab96e8137057e38828765502a2',
];
const W75_POLICY='owner_exact_and_strong_20260908';
const W75_CLASS='strong_candidate';
const W75_COLUMNS='anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled';
function w75_doc(string $path,string $hash):array{$raw=a74_read($path);a74_need(hash_equals($hash,hash('sha256',$raw)),'proof_hash');$d=json_decode($raw,true,256,JSON_THROW_ON_ERROR);a74_need(is_array($d),'proof_shape');return$d;}
function w75_digest(array $v):string{return hash('sha256',a74_json($v));}
/** Validate the original three exact-native audits, not an inferred operator_5 equality. */
function w75_prepare(string $dir):array{
    $pairs=a74_pairs(a74_read($dir.'/manifest.json'));
    $current=w75_doc($dir.'/current97.json',W75_CURRENT_SHA);
    a74_need(($current['state']??'')==='completed_read_only_current'&&($current['operation']??'')===A74_OP&&count($current['anex_rows']??[])===82,'current_scope');
    $cur=[];foreach($current['anex_rows'] as $r){$id=(int)$r['local_hotel_id'];a74_need(!isset($cur[$id])&&isset($pairs[$id])&&$pairs[$id]===(string)$r['anex_hotel_id'],'current_pair');a74_need($r['status']==='source_missing_needs_identity_proof'&&$r['reasons']===[],'current_not_open');$cur[$id]=$r;}
    $matches=[];$byN=[];$byT=[];
    foreach(W75_SOURCES as $file=>$hash){
        $d=w75_doc($dir.'/'.$file,$hash);a74_need(($d['database_writes']??null)===0&&($d['mapping_writes']??null)===0&&is_array($d['rows']??null),'source_not_read_only');
        foreach($d['rows'] as $i=>$r){
            if(($r['supplier_namespace']??'')!=='anex'||($r['operator_id']??null)!==13||($r['kind']??'')!=='anex')continue;
            $id=(int)$r['tv_hotel_id'];$n=(string)$r['external_hotel_id'];a74_need($id>0&&preg_match('/^[1-9][0-9]{0,7}$/D',$n)===1,'proof_identity');
            $byN[$n][$id]=true;$byT[$id][$n]=true;
            if(($pairs[$id]??null)!==$n)continue;
            a74_need(($r['status']??'')==='current_missing_exact_key'&&($r['anchor_state']??'')==='canonical_anchor_missing','historical_guard_not_anchor_only');
            a74_need(in_array(strtolower((string)$r['operator_link_host']),['agent.anextour.ru','online.anextour.ru','anextour.ru'],true),'proof_host');
            a74_need(in_array('hotellist',array_map('strtolower',$r['query_keys']),true),'proof_hotellist');
            foreach(['source_result_sha256','search_id_sha256','tour_id_sha256','operator_link_sha256'] as $key)a74_need(preg_match('/^[0-9a-f]{64}$/D',(string)($r[$key]??''))===1,'proof_provenance');
            a74_need((int)($r['catalog_hotel']['id']??0)===$id&&!empty($r['catalog_hotel']['name'])&&!empty($r['catalog_hotel']['country_name']),'proof_target');
            $matches[$id][]=['parent_result_sha256'=>$hash,'json_pointer'=>'/rows/'.$i,'row'=>$r];
        }
    }
    $out=[];foreach($pairs as $id=>$n){a74_need(count($matches[$id]??[])===1&&count($byN[$n]??[])===1&&count($byT[$id]??[])===1,'proof_global_uniqueness');$out[$id]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'proof'=>$matches[$id][0],'current_row'=>$cur[$id]];}
    a74_need(count($out)===82,'proof_count');return$out;
}
function w75_index(array $rows):array{$out=[];foreach($rows as $r){$n=(string)$r['anex_hotel_id'];a74_need(!isset($out[$n]),'duplicate_mapping');$out[$n]=$r;}return$out;}
function w75_verify(array $before,array $planned,array $after,bool $exactCount):void{
    $index=w75_index($after);if($exactCount)a74_need(count($index)===count($before)+count($planned),'mapping_count_delta');
    foreach($before as $n=>$r)a74_need(isset($index[$n])&&w75_digest($r)===w75_digest($index[$n]),'preexisting_mapping_changed');
    foreach($planned as $p){$r=$index[$p['anex_hotel_id']]??null;a74_need(is_array($r),'written_row_missing');foreach(explode(',',W75_COLUMNS) as $field)a74_need((string)$r[$field]===(string)$p[$field],'written_row_mismatch');}
}
/** SERIALIZABLE, insert-only; unrelated conflicts are held, never force-accepted. */
function w75_write(PDO $db,array $manifest,string $head,string $dir):array{
    require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
    a74_need(count($manifest)===82&&!$db->inTransaction(),'writer_scope');
    $attempt=false;$committed=false;$sqlStarted=false;$rolledBack=false;$planned=[];$held=[];$same=[];
    try{
        foreach(['anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','catalog_hotels','andromeda_hotel_identities','tour_operator_identity_observations'] as $table){$e=a74_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);a74_need(count($e)===1&&strtoupper((string)$e[0]['ENGINE'])==='INNODB','nontransactional_table');}
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');a74_need($db->beginTransaction(),'begin');
        $all=a74_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');$before=w75_index($all);
        $dec=a74_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
        $exc=a74_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001 FOR UPDATE');
        $ids=array_keys($manifest);$native=array_values(array_map(fn($r)=>$r['anex_hotel_id'],$manifest));$ih=implode(',',array_fill(0,count($ids),'?'));$nh=implode(',',array_fill(0,count($native),'?'));
        $c=['hotels'=>[],'live'=>[],'mapping_source'=>[],'mapping_target'=>[],'manual_source'=>[],'manual_target'=>[],'excluded_source'=>[],'op5_source'=>[]];
        foreach($all as $r){$c['mapping_source'][(string)$r['anex_hotel_id']][]=$r;$c['mapping_target'][(int)$r['catalog_hotel_id']][]=$r;}
        foreach($dec as $r){$c['manual_source'][(string)$r['anex_hotel_id']][]=$r;if($r['catalog_hotel_id']!==null)$c['manual_target'][(int)$r['catalog_hotel_id']][]=$r;}
        foreach($exc as $r)$c['excluded_source'][(string)$r['anex_hotel_id']][]=$r;
        foreach(a74_q($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($ih) ORDER BY id FOR UPDATE",$ids) as $r)$c['hotels'][(int)$r['id']]=$r;
        foreach(a74_q($db,"SELECT hotel_id,last_seen_at FROM tour_operator_identity_observations WHERE hotel_id IN ($ih) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) ORDER BY hotel_id FOR UPDATE",$ids) as $r)$c['live'][(int)$r['hotel_id']]=true;
        foreach(a74_q($db,"SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='operator_5' AND external_hotel_id IN ($nh) ORDER BY external_hotel_id FOR UPDATE",$native) as $r)$c['op5_source'][(string)$r['external_hotel_id']][]=$r;
        $c['effective']=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
        foreach($manifest as $id=>$m){
            $n=$m['anex_hotel_id'];$r=a74_anex($id,$n,$c);
            if($r['status']==='already_effective_same'){$same[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n];continue;}
            $reasons=$r['reasons'];$h=$c['hotels'][$id]??[];$old=$m['proof']['row']['catalog_hotel'];
            foreach(['id','name','country_id','country_name'] as $key)if((string)($h[$key]??'')!==(string)($old[$key]??'')){$reasons[]='target_facts_changed';break;}
            if($r['status']!=='source_missing_needs_identity_proof'||$reasons){$held[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'reasons'=>array_values(array_unique($reasons))];continue;}
            $evidence=['operation'=>W75_OP,'source_sha'=>$head,'authority'=>'verified_TV_operator13_HOTELLIST','current_result_sha256'=>W75_CURRENT_SHA,'proof'=>$m['proof'],'current_target'=>$h,'provider_http_calls'=>0];
            $planned[]=['anex_hotel_id'=>$n,'catalog_hotel_id'=>$id,'match_class'=>W75_CLASS,'scope'=>'preview','approval_policy'=>W75_POLICY,'source_row_digest'=>w75_digest($evidence),'mapping_digest'=>'','enabled'=>1,'evidence'=>$evidence];
        }
        a74_need(count($planned)+count($same)+count($held)===82,'classification_count');
        $digest=w75_digest(['operation'=>W75_OP,'audit'=>W75_CURRENT_SHA,'rows'=>array_map(fn($p)=>[$p['anex_hotel_id'],$p['catalog_hotel_id'],$p['source_row_digest']],$planned)]);
        foreach($planned as &$p)$p['mapping_digest']=$digest;unset($p);
        a74_save($dir.'/write-plan.json',['operation'=>W75_OP,'source_sha'=>$head,'rows'=>$planned,'already_effective'=>$same,'held'=>$held,'preexisting_mapping_count'=>count($before),'preexisting_mapping_sha256'=>w75_digest($before)]);
        if(!$planned){$db->rollBack();return['state'=>'completed_no_new_writes','inserted'=>0,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true,'already_effective'=>$same,'held'=>$held,'rows'=>[]];}
        $st=$db->prepare('INSERT INTO anex_hotel_search_mappings ('.W75_COLUMNS.') VALUES (?,?,?,?,?,?,?,?)');a74_need($st!==false,'insert_prepare');
        foreach($planned as $p){$sqlStarted=true;a74_need($st->execute(array_map(fn($key)=>$p[$key],explode(',',W75_COLUMNS)))&&$st->rowCount()===1,'insert_count');}
        w75_verify($before,$planned,a74_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),true);
        a74_need(a74_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001')===$dec,'manual_decisions_changed');
        a74_need(a74_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001')===$exc,'exclusions_changed');
        $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($planned as $p)a74_need($reg->resolve('anex_online',$p['anex_hotel_id'],'preview')===$p['catalog_hotel_id'],'staged_registry_mismatch');
        a74_save($dir.'/commit-attempt.json',['operation'=>W75_OP,'source_sha'=>$head,'planned_writes'=>count($planned),'state'=>'commit_attempt_no_replay']);$attempt=true;a74_need($db->commit(),'commit_false');$committed=true;
        $db->exec('START TRANSACTION READ ONLY');$after=a74_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001');w75_verify($before,$planned,$after,false);
        $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($planned as $p)a74_need($reg->resolve('anex_online',$p['anex_hotel_id'],'preview')===$p['catalog_hotel_id'],'post_registry_mismatch');$db->rollBack();
        return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'inserted'=>count($planned),'database_writes'=>count($planned),'mapping_writes'=>count($planned),'readback_verified'=>true,'preexisting_mappings_unchanged'=>true,'registry_readback_verified'=>true,'already_effective'=>$same,'held'=>$held,'rows'=>array_map(fn($p)=>array_diff_key($p,['evidence'=>true]),$planned)];
    }catch(Throwable $e){
        if($db->inTransaction())try{$rolledBack=$db->rollBack();}catch(Throwable){}
        $state=$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':($rolledBack?'rolled_back_no_writes':'failed_before_commit'));
        $writes=$committed?count($planned):(($attempt||($sqlStarted&&!$rolledBack))?null:0);
        return['state'=>$state,'reason'=>preg_match('/^[a-z][a-z0-9_]{0,99}$/D',$e->getMessage())===1?$e->getMessage():'writer_failed','error_class'=>get_class($e),'commit_attempted'=>$attempt,'commit_completed'=>$committed,'database_writes'=>$writes,'mapping_writes'=>$writes,'readback_verified'=>false,'rows'=>[]];
    }
}
function w75_main(array $argv):int{
    a74_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    a74_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===W75_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'runtime_scope');
    $reservation=json_decode(a74_read($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);a74_need(($reservation['operation']??'')===W75_OP&&($reservation['source_sha']??'')===$head&&($reservation['state']??'')==='reserved_before_db_write','reservation');
    foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json'] as $file)a74_need(!file_exists($dir.'/'.$file),'terminal_no_replay');
    $manifest=w75_prepare(dirname(__DIR__,2).'/input');a74_save($dir.'/execution-started.json',['operation'=>W75_OP,'source_sha'=>$head,'scope'=>82]);
    try{$boot=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');a74_need(is_file($boot)&&!is_link($boot),'bootstrap_missing');require_once $boot;a74_need(function_exists('v2_data_db'),'db_factory');$result=w75_write(v2_data_db(),$manifest,$head,$dir);}
    catch(Throwable $e){$result=['state'=>'failed_before_writer','error_class'=>get_class($e),'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false,'rows'=>[]];}
    $result+=['operation'=>W75_OP,'source_sha'=>$head,'current_result_sha256'=>W75_CURRENT_SHA,'provider_http_calls'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];
    $hash=a74_save($dir.'/result.json',$result);a74_save($dir.'/receipt.json',['operation'=>W75_OP,'source_sha'=>$head,'state'=>$result['state'],'result_sha256'=>$hash,'result_file_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'database_readback_verified'=>$result['readback_verified'],'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'provider_http_calls'=>0,'no_replay'=>true]);
    echo a74_json(array_diff_key($result,['rows'=>true]))."\n";return in_array($result['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(w75_main($argv));
