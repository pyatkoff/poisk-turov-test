<?php
declare(strict_types=1);

const CNE19_OP='hotel-match-common4-crossnamespace-equivalence-1971-20260925-v19';
const CNE19_POST_OP='hotel-match-samo-business-live30-common4-postwrite-1971-20260925-v18';
const CNE19_APPROVED=[
 'owner_exact_and_strong_20260908'=>['exact'=>true,'strong_candidate'=>true],
 'owner_exact_operator_key_20260912'=>['exact_operator_key'=>true],
 'owner_exact_operator_key_20260912_v2'=>['exact_operator_key'=>true],
 'owner_coordinate_name_geo_rescue_20260912_v1'=>['coordinate_name_geo'=>true],
 'owner_multi_evidence_consensus_20260912_v1'=>['multi_evidence_consensus'=>true],
 'owner_current_exact_cross_provider_20260912'=>['exact_cross_provider'=>true],
];

function cne19_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function cne19_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function cne19_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function cne19_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);cne19_need(is_array($v),'json_shape');return $v;}
function cne19_save(string $p,array $v):string{$raw=cne19_json($v)."\n";$f=@fopen($p,'x+b');cne19_need($f!==false,'exclusive_create');try{cne19_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))cne19_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function cne19_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function cne19_set_add(array &$map,int $local,string $external):void{$map[$local][$external]=true;}
function cne19_sets(array $rows,string $ns):array{
    $byLocal=[];$bySource=[];
    foreach($rows as $r){
        if(($r['supplier_namespace']??'')!==$ns)continue;
        $ext=cne19_id($r['external_hotel_id']??null);$local=(int)($r['local_hotel_id']??0);
        cne19_need($ext!==null&&$local>0,'identity_shape');
        cne19_set_add($byLocal,$local,$ext);$bySource[$ext][$local]=true;
    }
    return ['by_local'=>$byLocal,'by_source'=>$bySource];
}
function cne19_effective_anex(PDO $db):array{
    $clauses=[];$args=[];
    foreach(CNE19_APPROVED as $policy=>$classes){
        $clauses[]='(m.approval_policy=? AND m.match_class IN ('.implode(',',array_fill(0,count($classes),'?')).'))';
        $args[]=$policy;foreach(array_keys($classes) as $class)$args[]=$class;
    }
    $mappingRows=cne19_query($db,
      'SELECT m.anex_hotel_id,m.catalog_hotel_id,m.match_class,m.approval_policy,m.enabled,m.scope,h.id AS existing_catalog_hotel_id '.
      'FROM anex_hotel_search_mappings m INNER JOIN catalog_hotels h ON h.id=m.catalog_hotel_id '.
      "WHERE m.enabled=1 AND m.scope='preview' AND (".implode(' OR ',$clauses).') ORDER BY m.anex_hotel_id',$args);
    $decisionRows=cne19_query($db,
      'SELECT d.anex_hotel_id,d.decision_status,d.catalog_hotel_id,h.id AS existing_catalog_hotel_id '.
      'FROM anex_hotel_decisions d LEFT JOIN catalog_hotels h ON h.id=d.catalog_hotel_id ORDER BY d.anex_hotel_id');
    try{$pairRows=cne19_query($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id');}
    catch(PDOException $e){$info=$e->errorInfo??[];$missing=(($info[0]??null)==='42S02'&&(int)($info[1]??0)===1146)||($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'&&(int)($info[1]??0)===1&&($info[2]??'')==='no such table: anex_review_pair_exclusions');if(!$missing)throw$e;$pairRows=[];}
    $effective=[];$origin=[];$seen=[];
    foreach($mappingRows as $r){
        $ext=cne19_id($r['anex_hotel_id']??null);$local=(int)($r['existing_catalog_hotel_id']??0);
        cne19_need($ext!==null&&!isset($seen[$ext]),'anex_mapping_duplicate');$seen[$ext]=true;
        if($local<1)continue;$effective[$ext]=$local;$origin[$ext]='approved_mapping';
    }
    $seen=[];
    foreach($decisionRows as $r){
        $ext=cne19_id($r['anex_hotel_id']??null);cne19_need($ext!==null&&!isset($seen[$ext]),'anex_decision_duplicate');$seen[$ext]=true;
        unset($effective[$ext],$origin[$ext]);
        $local=(int)($r['existing_catalog_hotel_id']??0);
        if(($r['decision_status']??'')==='accepted'&&$local>0){$effective[$ext]=$local;$origin[$ext]='manual_accepted';}
    }
    foreach($pairRows as $r){
        $ext=cne19_id($r['anex_hotel_id']??null);$local=(int)($r['catalog_hotel_id']??0);cne19_need($ext!==null&&$local>0,'anex_exclusion_shape');
        if(($effective[$ext]??null)===$local){unset($effective[$ext],$origin[$ext]);}
    }
    $byLocal=[];$bySource=[];
    foreach($effective as $ext=>$local){cne19_set_add($byLocal,(int)$local,(string)$ext);$bySource[(string)$ext][(int)$local]=true;}
    return ['by_local'=>$byLocal,'by_source'=>$bySource,'origin'=>$origin,'effective_source_count'=>count($effective),
      'mapping_rows'=>count($mappingRows),'decision_rows'=>count($decisionRows),'exclusion_rows'=>count($pairRows)];
}
function cne19_bridge_stats(array $left,array $right):array{
    $locals=array_values(array_intersect(array_keys($left),array_keys($right)));sort($locals,SORT_NUMERIC);
    $equal=[];$different=[];$collision=[];$leftMulti=0;$rightMulti=0;
    foreach($locals as $local){
        $a=array_keys($left[$local]??[]);$b=array_keys($right[$local]??[]);sort($a,SORT_NATURAL);sort($b,SORT_NATURAL);
        if(count($a)!==1||count($b)!==1){$collision[]=$local;if(count($a)!==1)$leftMulti++;if(count($b)!==1)$rightMulti++;continue;}
        if($a[0]===$b[0])$equal[]=$local;else$different[]=['local_hotel_id'=>$local,'left_external_id'=>$a[0],'right_external_id'=>$b[0]];
    }
    return ['common_local_count'=>count($locals),'single_single_count'=>count($equal)+count($different),
      'id_equal_count'=>count($equal),'id_different_count'=>count($different),
      'collision_local_count'=>count($collision),'left_non_single_local_count'=>$leftMulti,'right_non_single_local_count'=>$rightMulti,
      'equal_local_ids'=>$equal,'different_rows'=>$different,'collision_local_ids'=>$collision];
}
function cne19_missing_targets(array $post,string $namespace):array{
    $out=[];$seen=[];
    foreach(($post['rows']??[]) as $r){
        if(!is_array($r)||($r['mapping_state']??'')!=='mapped_unique')continue;
        $local=(int)($r['local_hotel_id']??0);if($local<1)continue;
        $lane=$r['operator_lanes'][$namespace]??null;if(!is_array($lane))continue;
        $sig=cne19_json($lane);
        if(isset($seen[$local])){cne19_need($seen[$local]===$sig,'post_lane_conflict');continue;}
        $seen[$local]=$sig;
        if(($lane['status']??'')==='missing')$out[$local]=true;
    }
    ksort($out,SORT_NUMERIC);return $out;
}
function cne19_candidates(array $missing,array $counterpartLocal,array $destLocal,array $destSource,string $bridge):array{
    $rows=[];$counts=['missing_target_count'=>count($missing),'counterpart_unique_count'=>0,'counterpart_none_count'=>0,'counterpart_collision_count'=>0,
      'destination_target_occupied_count'=>0,'destination_source_same_count'=>0,'destination_source_other_count'=>0,'safe_candidate_count'=>0];
    foreach(array_keys($missing) as $local){
        $ids=array_keys($counterpartLocal[$local]??[]);sort($ids,SORT_NATURAL);
        if(count($ids)===0){$counts['counterpart_none_count']++;continue;}
        if(count($ids)!==1){$counts['counterpart_collision_count']++;continue;}
        $counts['counterpart_unique_count']++;$ext=$ids[0];
        $targetIds=array_keys($destLocal[$local]??[]);
        if($targetIds!==[]){$counts['destination_target_occupied_count']++;continue;}
        $sourceLocals=array_keys($destSource[$ext]??[]);sort($sourceLocals,SORT_NUMERIC);
        if($sourceLocals!==[]){
            if(count($sourceLocals)===1&&(int)$sourceLocals[0]===(int)$local)$counts['destination_source_same_count']++;
            else $counts['destination_source_other_count']++;
            continue;
        }
        $counts['safe_candidate_count']++;
        $rows[]=['bridge'=>$bridge,'local_hotel_id'=>(int)$local,'external_hotel_id'=>(string)$ext,'safe_to_write_now'=>false];
    }
    return $counts+['rows'=>$rows];
}
function cne19_execute(PDO $db,array $post,string $postSha,string $sourceSha):array{
    cne19_need(($post['operation']??'')===CNE19_POST_OP&&($post['state']??'')==='samo_business_live30_common4_plan_ready','post_state');
    cne19_need((int)($post['deduped_unique_local_count']??0)===1711&&($post['deduped_missing_lane_counts']??null)===['operator_5'=>1516,'operator_115'=>1285,'operator_315'=>1304,'operator_342'=>668],'post_counts');
    cne19_need((int)($post['deduped_lane_state_conflicts']??-1)===0,'post_conflicts');
    $missing5=cne19_missing_targets($post,'operator_5');$missing115=cne19_missing_targets($post,'operator_115');
    cne19_need(count($missing5)===1516&&count($missing115)===1285,'missing_target_counts');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $ident=cne19_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL AND supplier_namespace IN ('operator_5','operator_115','bgoperator') ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        $op5=cne19_sets($ident,'operator_5');$op115=cne19_sets($ident,'operator_115');$bg=cne19_sets($ident,'bgoperator');
        $anex=cne19_effective_anex($db);
        $anexStats=cne19_bridge_stats($anex['by_local'],$op5['by_local']);
        $biblioStats=cne19_bridge_stats($bg['by_local'],$op115['by_local']);
        $anexCandidates=cne19_candidates($missing5,$anex['by_local'],$op5['by_local'],$op5['by_source'],'direct_anex_to_operator_5');
        $biblioCandidates=cne19_candidates($missing115,$bg['by_local'],$op115['by_local'],$op115['by_source'],'bgoperator_to_operator_115');
        $db->rollBack();
        return ['operation'=>CNE19_OP,'state'=>'completed_read_only_crossnamespace_equivalence','source_sha'=>$sourceSha,'postwrite_operation'=>CNE19_POST_OP,'postwrite_result_sha256'=>$postSha,
          'anex_registry'=>array_intersect_key($anex,array_flip(['effective_source_count','mapping_rows','decision_rows','exclusion_rows'])),
          'direct_anex_vs_operator_5'=>$anexStats,'bgoperator_vs_operator_115'=>$biblioStats,
          'operator_5_missing_crossfill'=>$anexCandidates,'operator_115_missing_crossfill'=>$biblioCandidates,
          'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function cne19_self_test():void{
    $s=cne19_bridge_stats([1=>['10'=>true],2=>['20'=>true],3=>['30'=>true,'31'=>true]],[1=>['10'=>true],2=>['21'=>true],3=>['30'=>true]]);
    cne19_need($s['common_local_count']===3&&$s['id_equal_count']===1&&$s['id_different_count']===1&&$s['collision_local_count']===1,'bridge_stats');
    $c=cne19_candidates([1=>true,2=>true,3=>true],[1=>['10'=>true],2=>['20'=>true,'21'=>true]],[3=>['30'=>true]],['10'=>[]],'x');
    cne19_need($c['safe_candidate_count']===1&&$c['counterpart_collision_count']===1&&$c['counterpart_none_count']===1,'candidates');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){cne19_self_test();echo "MATCH_COMMON4_CROSSNAMESPACE_EQUIVALENCE_V19_SELFTEST_OK\n";exit;}
    cne19_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$postPath=(string)getenv('MATCH_POSTWRITE_RESULT');$postSha=(string)getenv('MATCH_POSTWRITE_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
    cne19_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===CNE19_OP&&is_file($postPath)&&preg_match('/^[0-9a-f]{64}$/D',$postSha)===1&&hash_file('sha256',$postPath)===$postSha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');
    $reservation=cne19_load($dir.'/reservation.json');cne19_need(($reservation['operation']??'')===CNE19_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    try{require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=cne19_execute(v2_data_db(),cne19_load($postPath),$postSha,$sourceSha);$h=cne19_save($dir.'/result.json',$out);cne19_save($dir.'/receipt.json',['operation'=>CNE19_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo cne19_json(['state'=>$out['state'],'direct_anex_vs_operator_5'=>$out['direct_anex_vs_operator_5'],'bgoperator_vs_operator_115'=>$out['bgoperator_vs_operator_115'],'operator_5_missing_crossfill'=>array_diff_key($out['operator_5_missing_crossfill'],['rows'=>true]),'operator_115_missing_crossfill'=>array_diff_key($out['operator_115_missing_crossfill'],['rows'=>true])])."\n";}
    catch(Throwable$e){$f=['operation'=>CNE19_OP,'state'=>'failed_read_only_crossnamespace_equivalence','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160)),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=cne19_save($dir.'/result.json',$f);cne19_save($dir.'/receipt.json',['operation'=>CNE19_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
