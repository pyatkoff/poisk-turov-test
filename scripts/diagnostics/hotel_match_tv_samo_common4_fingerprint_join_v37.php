<?php
declare(strict_types=1);

const V37_OP='hotel-match-tv-samo-common4-fingerprint-join-1971-20260925-v37';
const V37_FRONTIER_OP='hotel-match-business-live30-frontier-plan-1971-20260925-v33';
const V37_FRONTIER_RESULT_SHA='efaeb33f14a1a2aba4c207254be388e470636e2ad965b461a5a671c3bee72184';
const V37_FRONTIER_TARGET_SHA='5001297e29a920acc2d565e45fec0e4d0a5b2244e0ba60a860941be882ad1c77';
const V37_MAX_OPERATION_DIRS=5000;
const V37_MAX_RESULT_BYTES=67108864;
const V37_DIRECT=['operator_315'=>25,'operator_342'=>43];
const V37_SUPPORT_TV=['bgoperator'=>18,'anex'=>13];
const V37_SUPPORT_SAMO=['operator_115'=>115,'operator_5'=>5];

function v37_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v37_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v37_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v37_need(is_array($v),'json_shape');return $v;}
function v37_save(string $p,array $v):string{$raw=v37_json($v)."\n";$f=@fopen($p,'x+b');v37_need($f!==false,'exclusive_create');try{v37_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))v37_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v37_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function v37_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function v37_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v37_assoc(array $v):bool{return !array_is_list($v);}
function v37_single_native(array $n):?string{
    $ids=$n['positive_native_candidates']??null;
    if(!is_array($ids)||count($ids)!==1)return null;
    $state=(string)($n['link_state']??$n['state']??'');
    if(!in_array($state,['captured_single_native','detail_identity_verified'],true))return null;
    return v37_id($ids[0]??null);
}
function v37_node_hash(array $n):string{
    $p=[];
    foreach(['tv_hotel_id','catalog_id','operator_id','namespace','link_state','state','positive_native_candidates'] as $k)if(array_key_exists($k,$n))$p[$k]=$n[$k];
    return hash('sha256',v37_json($p));
}
function v37_collect_node(mixed $node,string $op,string $resultSha,array &$tv,array &$samo,int &$visited,int $depth=0):void{
    if($depth>28||!is_array($node))return;
    if(++$visited>2500000)throw new RuntimeException('node_cap');
    if(v37_assoc($node)){
        $native=v37_single_native($node);
        $ns=trim((string)($node['namespace']??''));
        $operator=(int)($node['operator_id']??0);
        if($native!==null&&$ns!==''){
            $nodeHash=v37_node_hash($node);
            $tvId=(int)($node['tv_hotel_id']??0);
            if($tvId>0){
                $allowed=(V37_DIRECT[$ns]??V37_SUPPORT_TV[$ns]??null);
                if($allowed!==null&&$allowed===$operator){
                    $key=$ns.'|'.$native.'|'.$tvId.'|'.$op.'|'.$nodeHash;
                    $tv[$key]=['namespace'=>$ns,'operator_id'=>$operator,'native_id'=>$native,'tv_hotel_id'=>$tvId,'source_operation'=>$op,'source_result_sha256'=>$resultSha,'source_edge_sha256'=>$nodeHash];
                }
            }
            $catalog=v37_id($node['catalog_id']??null);
            if($catalog!==null){
                $allowed=(V37_DIRECT[$ns]??V37_SUPPORT_SAMO[$ns]??null);
                if($allowed!==null&&$allowed===$operator){
                    $key=$ns.'|'.$native.'|'.$catalog.'|'.$op.'|'.$nodeHash;
                    $samo[$key]=['namespace'=>$ns,'operator_id'=>$operator,'native_id'=>$native,'catalog_id'=>$catalog,'source_operation'=>$op,'source_result_sha256'=>$resultSha,'source_edge_sha256'=>$nodeHash];
                }
            }
        }
    }
    foreach($node as $v)if(is_array($v))v37_collect_node($v,$op,$resultSha,$tv,$samo,$visited,$depth+1);
}
function v37_scan_operations(string $root):array{
    v37_need(is_dir($root)&&!is_link($root),'operations_root');
    $dirs=[];foreach(new DirectoryIterator($root) as $e){if($e->isDot()||$e->isLink()||!$e->isDir())continue;$dirs[]=$e->getPathname();}
    sort($dirs,SORT_STRING);v37_need(count($dirs)<=V37_MAX_OPERATION_DIRS,'operation_dir_cap');
    $tv=[];$samo=[];$files=0;$parsed=0;$visited=0;$skippedLarge=0;$skippedJson=0;
    foreach($dirs as $dir){
        if(basename($dir)===V37_OP)continue;
        $p=$dir.'/result.json';if(!is_file($p)||is_link($p))continue;$files++;
        $size=filesize($p);if($size===false||$size<2)continue;if($size>V37_MAX_RESULT_BYTES){$skippedLarge++;continue;}
        $raw=(string)file_get_contents($p);$sha=hash('sha256',$raw);
        try{$r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){$skippedJson++;continue;}
        if(!is_array($r))continue;$parsed++;$op=trim((string)($r['operation']??basename($dir)));if($op==='')$op=basename($dir);
        v37_collect_node($r,$op,$sha,$tv,$samo,$visited);
    }
    return ['tv_edges'=>array_values($tv),'samo_edges'=>array_values($samo),'operation_dirs'=>count($dirs),'result_files'=>$files,'parsed_results'=>$parsed,'skipped_large'=>$skippedLarge,'skipped_json'=>$skippedJson,'visited_nodes'=>$visited];
}
function v37_frontier(string $path):array{
    $raw=(string)file_get_contents($path);v37_need(hash('sha256',$raw)===V37_FRONTIER_RESULT_SHA,'frontier_result_sha');
    $r=json_decode($raw,true,128,JSON_THROW_ON_ERROR);v37_need(is_array($r),'frontier_shape');
    v37_need(($r['operation']??'')===V37_FRONTIER_OP&&($r['state']??'')==='completed_read_only_business_live30_frontier_plan','frontier_state');
    v37_need((int)($r['non_full_triple_total']??0)===1737&&($r['target_set_sha256']??'')===V37_FRONTIER_TARGET_SHA,'frontier_scope');
    v37_need(($r['bucket_counts']??null)===['tv_samo_missing_anex'=>777,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726],'frontier_counts');
    $out=[];foreach($r['rows']??[] as $x){if(!is_array($x))continue;$id=(int)($x['local_hotel_id']??0);$b=(string)($x['bucket']??'');if($id>0&&isset($r['bucket_counts'][$b]))$out[$id]=$b;}
    v37_need(count($out)===1737,'frontier_rows');return $out;
}
function v37_index_tv(array $edges,array $frontier):array{
    $idx=[];$byNs=[];$byHotel=[];$kept=0;
    foreach($edges as $e){$tv=(int)$e['tv_hotel_id'];if(!isset($frontier[$tv]))continue;$ns=$e['namespace'];$native=$e['native_id'];$k=$ns.'|'.$native;
        $idx[$k][$tv]=true;$byNs[$ns][$k]=true;$byHotel[$tv][$ns][$native]=true;$kept++;}
    return ['fingerprints'=>$idx,'by_namespace'=>$byNs,'by_hotel'=>$byHotel,'kept_edges'=>$kept];
}
function v37_group_samo(array $edges):array{
    $out=[];$byNs=[];foreach($edges as $e){$cid=$e['catalog_id'];$ns=$e['namespace'];$native=$e['native_id'];$out[$cid][$ns][$native][]=$e;$byNs[$ns][$ns.'|'.$native]=true;}return ['catalogs'=>$out,'by_namespace'=>$byNs];
}
function v37_support_vote(string $samoNs,string $tvNs,mixed $native,array $tvIndex):array{
    $native=(string)$native;$ids=array_keys($tvIndex[$tvNs.'|'.$native]??[]);sort($ids,SORT_NUMERIC);return $ids;
}
function v37_candidate_rows(array $samo,array $tvIndex,array $frontier,array $currentCatalogBySource,array $currentCatalogByTarget,array $active):array{
    $rows=[];$preStrongByTarget=[];$classCounts=[];$bucketCounts=[];$namespaceShared=['operator_315'=>0,'operator_342'=>0,'bgoperator_to_operator_115'=>0,'anex_to_operator_5'=>0];
    foreach($samo as $cid=>$nsMap){
        $directVotes=[];$directEvidence=[];$directNsCollision=[];$support=[];
        foreach(V37_DIRECT as $ns=>$op){
            $natives=array_keys($nsMap[$ns]??[]);sort($natives,SORT_NATURAL);
            if(count($natives)>1){$directNsCollision[$ns]=$natives;continue;}
            if(count($natives)===1){$native=(string)$natives[0];$targets=array_keys($tvIndex[$ns.'|'.$native]??[]);sort($targets,SORT_NUMERIC);if(count($targets)===1){$tv=(int)$targets[0];$directVotes[$ns]=$tv;$namespaceShared[$ns]++;$ev=$nsMap[$ns][$native];$directEvidence[$ns]=['native_id'=>$native,'tv_hotel_id'=>$tv,'source_edges'=>array_map(fn($x)=>['operation'=>$x['source_operation'],'result_sha256'=>$x['source_result_sha256'],'edge_sha256'=>$x['source_edge_sha256']],$ev)];}elseif(count($targets)>1)$directEvidence[$ns]=['native_id'=>$native,'ambiguous_tv_hotel_ids'=>$targets];}
        }
        $supportSpecs=[['samo'=>'operator_115','tv'=>'bgoperator','name'=>'bgoperator_to_operator_115'],['samo'=>'operator_5','tv'=>'anex','name'=>'anex_to_operator_5']];
        foreach($supportSpecs as $sp){$natives=array_keys($nsMap[$sp['samo']]??[]);sort($natives,SORT_NATURAL);if(count($natives)===1){$native=(string)$natives[0];$targets=v37_support_vote($sp['samo'],$sp['tv'],$native,$tvIndex);if(count($targets)===1){$support[$sp['name']]=['native_id'=>$native,'tv_hotel_id'=>(int)$targets[0]];$namespaceShared[$sp['name']]++;}}}
        $votes=array_values($directVotes);$uniq=array_values(array_unique($votes));sort($uniq,SORT_NUMERIC);
        $status='no_direct_shared_fingerprint';$tv=null;
        if($directNsCollision)$status='hold_samo_namespace_collision';
        elseif(count($directVotes)>=2&&count($uniq)===1){$tv=(int)$uniq[0];$status='preliminary_strong_2plus_direct';}
        elseif(count($directVotes)>=2&&count($uniq)>1)$status='hold_conflicting_direct_votes';
        elseif(count($directVotes)===1){$tv=(int)$votes[0];$status='single_direct_review';}
        elseif($support)$status='support_only_review';
        $currentSource=$currentCatalogBySource[$cid]??[];$targetCurrent=$tv!==null?($currentCatalogByTarget[$tv]??[]):[];
        if($tv!==null&&!isset($frontier[$tv])){$status='outside_frontier';$tv=null;}
        if($tv!==null&&(!isset($active[$tv])||$active[$tv]!==true))$status='hold_target_inactive';
        if($tv!==null&&$currentSource){$targets=array_keys($currentSource);sort($targets,SORT_NUMERIC);if(count($targets)===1&&(int)$targets[0]===$tv)$status='resolved_same';else$status='hold_current_source_conflict';}
        if($status==='preliminary_strong_2plus_direct')$preStrongByTarget[$tv][]=$cid;
        $row=['samo_catalog_id'=>$cid,'status'=>$status,'tv_hotel_id'=>$tv,'frontier_bucket'=>$tv!==null?($frontier[$tv]??null):null,'direct_votes'=>$directVotes,'direct_evidence'=>$directEvidence,'supporting_cross_namespace'=>$support,'current_source_targets'=>array_map('intval',array_keys($currentSource)),'current_target_catalog_ids'=>array_map('strval',array_keys($targetCurrent)),'safe_to_write_now'=>false];
        $rows[$cid]=$row;
    }
    foreach($preStrongByTarget as $tv=>$cids)if(count($cids)>1)foreach($cids as $cid)if(($rows[$cid]['status']??'')==='preliminary_strong_2plus_direct')$rows[$cid]['status']='hold_mutual_target_collision';
    foreach($rows as &$r){$s=$r['status'];$classCounts[$s]=($classCounts[$s]??0)+1;$b=$r['frontier_bucket']??'none';$bucketCounts[$b][$s]=($bucketCounts[$b][$s]??0)+1;if($s==='preliminary_strong_2plus_direct')$r['status']='strong_2plus_direct_mutual_unique';}unset($r);
    $classCounts=[];$bucketCounts=[];foreach($rows as $r){$s=$r['status'];$classCounts[$s]=($classCounts[$s]??0)+1;$b=$r['frontier_bucket']??'none';$bucketCounts[$b][$s]=($bucketCounts[$b][$s]??0)+1;}
    ksort($classCounts);ksort($bucketCounts);foreach($bucketCounts as &$x)ksort($x);unset($x);
    $out=array_values($rows);usort($out,fn($a,$b)=>[$a['status'],$a['frontier_bucket']??'',$a['tv_hotel_id']??PHP_INT_MAX,$a['samo_catalog_id']]<=>[$b['status'],$b['frontier_bucket']??'',$b['tv_hotel_id']??PHP_INT_MAX,$b['samo_catalog_id']]);
    return ['rows'=>$out,'status_counts'=>$classCounts,'bucket_status_counts'=>$bucketCounts,'shared_fingerprint_counts'=>$namespaceShared];
}
function v37_execute(PDO $db,string $operationsRoot,string $frontierPath,string $sourceSha):array{
    $frontier=v37_frontier($frontierPath);$scan=v37_scan_operations($operationsRoot);$tv=v37_index_tv($scan['tv_edges'],$frontier);$samo=v37_group_samo($scan['samo_edges']);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];foreach(v37_query($db,"SELECT id,is_active FROM catalog_hotels WHERE id IN (SELECT hotel_id FROM tour_operator_identity_observations GROUP BY hotel_id)") as $r)$active[(int)$r['id']]=(int)$r['is_active']===1;
        $bySource=[];$byTarget=[];foreach(v37_query($db,"SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'") as $r){$cid=(string)$r['external_hotel_id'];if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$l=(int)$r['local_hotel_id'];$bySource[$cid][$l]=true;$byTarget[$l][$cid]=true;}}
        $db->rollBack();
        $cand=v37_candidate_rows($samo['catalogs'],$tv['fingerprints'],$frontier,$bySource,$byTarget,$active);
        $strong=array_values(array_filter($cand['rows'],fn($r)=>$r['status']==='strong_2plus_direct_mutual_unique'));
        $single=array_values(array_filter($cand['rows'],fn($r)=>$r['status']==='single_direct_review'));
        $support=array_values(array_filter($cand['rows'],fn($r)=>$r['status']==='support_only_review'));
        return ['operation'=>V37_OP,'state'=>'completed_read_only_tv_samo_common4_fingerprint_join','generated_at_utc'=>gmdate('c'),'source_sha'=>$sourceSha,
            'frontier_total'=>count($frontier),'frontier_target_sha256'=>V37_FRONTIER_TARGET_SHA,
            'scan'=>['operation_dirs'=>$scan['operation_dirs'],'result_files'=>$scan['result_files'],'parsed_results'=>$scan['parsed_results'],'skipped_large'=>$scan['skipped_large'],'skipped_json'=>$scan['skipped_json'],'visited_nodes'=>$scan['visited_nodes']],
            'evidence_counts'=>['tv_edges_all'=>count($scan['tv_edges']),'tv_edges_frontier'=>$tv['kept_edges'],'samo_edges_all'=>count($scan['samo_edges']),'samo_catalog_ids'=>count($samo['catalogs'])],
            'shared_fingerprint_counts'=>$cand['shared_fingerprint_counts'],'status_counts'=>$cand['status_counts'],'bucket_status_counts'=>$cand['bucket_status_counts'],
            'strong_candidate_count'=>count($strong),'single_direct_review_count'=>count($single),'support_only_review_count'=>count($support),'rows'=>$cand['rows'],
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function v37_self_test():void{
    $frontier=[10=>'tv_only_missing_both',20=>'tv_anex_missing_samo'];
    $tv=[
      ['namespace'=>'operator_315','operator_id'=>25,'native_id'=>'111','tv_hotel_id'=>10,'source_operation'=>'t','source_result_sha256'=>str_repeat('a',64),'source_edge_sha256'=>str_repeat('b',64)],
      ['namespace'=>'operator_342','operator_id'=>43,'native_id'=>'222','tv_hotel_id'=>10,'source_operation'=>'t','source_result_sha256'=>str_repeat('a',64),'source_edge_sha256'=>str_repeat('c',64)],
      ['namespace'=>'bgoperator','operator_id'=>18,'native_id'=>'333','tv_hotel_id'=>10,'source_operation'=>'t','source_result_sha256'=>str_repeat('a',64),'source_edge_sha256'=>str_repeat('d',64)]
    ];
    $samo=['900'=>[
      'operator_315'=>['111'=>[['source_operation'=>'s','source_result_sha256'=>str_repeat('e',64),'source_edge_sha256'=>str_repeat('f',64)]]],
      'operator_342'=>['222'=>[['source_operation'=>'s','source_result_sha256'=>str_repeat('e',64),'source_edge_sha256'=>str_repeat('1',64)]]],
      'operator_115'=>['333'=>[['source_operation'=>'s','source_result_sha256'=>str_repeat('e',64),'source_edge_sha256'=>str_repeat('2',64)]]]
    ]];
    $idx=v37_index_tv($tv,$frontier);$c=v37_candidate_rows($samo,$idx['fingerprints'],$frontier,[],[],[10=>true,20=>true]);
    v37_need(($c['status_counts']['strong_2plus_direct_mutual_unique']??0)===1,'self_strong');
    v37_need(($c['rows'][0]['supporting_cross_namespace']['bgoperator_to_operator_115']['tv_hotel_id']??0)===10,'self_support');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v37_self_test();echo "MATCH_TV_SAMO_COMMON4_FINGERPRINT_V37_SELFTEST_OK\n";exit;}
    v37_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$ops=(string)getenv('MATCH_OPERATIONS_ROOT');$frontier=(string)getenv('MATCH_FRONTIER_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v37_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V37_OP&&is_dir($ops)&&is_file($frontier)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=v37_load($dir.'/reservation.json');v37_need(($reservation['operation']??'')===V37_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$r=v37_execute(v2_data_db(),$ops,$frontier,$sha);$h=v37_save($dir.'/result.json',$r);v37_save($dir.'/receipt.json',['operation'=>V37_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v37_json(['state'=>$r['state'],'strong_candidate_count'=>$r['strong_candidate_count'],'single_direct_review_count'=>$r['single_direct_review_count'],'support_only_review_count'=>$r['support_only_review_count'],'status_counts'=>$r['status_counts'],'bucket_status_counts'=>$r['bucket_status_counts'],'shared_fingerprint_counts'=>$r['shared_fingerprint_counts']])."\n";}
    catch(Throwable $e){$f=['operation'=>V37_OP,'state'=>'failed_read_only_tv_samo_common4_fingerprint_join','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v37_save($dir.'/result.json',$f);v37_save($dir.'/receipt.json',['operation'=>V37_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
