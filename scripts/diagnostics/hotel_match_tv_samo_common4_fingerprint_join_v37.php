<?php
declare(strict_types=1);

const V37_OP = 'hotel-match-tv-samo-common4-fingerprint-join-1971-20260925-v37';
const V37_DIRECT = ['operator_315'=>'operator_315','operator_342'=>'operator_342'];
const V37_SUPPORT = ['operator_115'=>'bgoperator','operator_5'=>'anex'];
const V37_FRONTIER_TOTAL = 1737;
const V37_FRONTIER_SHA = '5001297e29a920acc2d565e45fec0e4d0a5b2244e0ba60a860941be882ad1c77';

function v37_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function v37_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v37_save(string $path,array $value):string{
    $raw=v37_json($value)."\n";$f=@fopen($path,'x+b');v37_need($f!==false,'exclusive_create');
    try{v37_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))v37_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function v37_load(string $path):array{$v=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);v37_need(is_array($v),'json_shape');return $v;}
function v37_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function v37_int(mixed $v):?int{$s=v37_id($v);if($s===null||strlen($s)>9)return null;$n=(int)$s;return $n>0?$n:null;}
function v37_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function v37_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v37_assoc(array $a):bool{return !array_is_list($a);}
function v37_native_from_node(array $node):?string{
    $ids=$node['positive_native_candidates']??null;
    if(is_array($ids)&&count($ids)===1){$x=v37_id(array_values($ids)[0]??null);if($x!==null)return $x;}
    $state=(string)($node['link_state']??$node['state']??'');
    if(str_contains($state,'single_native')||isset($node['source_edge_sha256'])||isset($node['operator_link_sha256'])){
        $x=v37_id($node['external_hotel_id']??null);if($x!==null)return $x;
    }
    return null;
}
function v37_ns_from_node(array $node):?string{
    $ns=trim((string)($node['supplier_namespace']??$node['namespace']??''));
    $allowed=['operator_315'=>true,'operator_342'=>true,'operator_115'=>true,'operator_5'=>true,'bgoperator'=>true,'anex'=>true];
    return isset($allowed[$ns])?$ns:null;
}
function v37_projection_hash(array $node):string{return hash('sha256',v37_json($node));}
function v37_add_edge(array &$side,string $entity,string $ns,string $native,string $op,string $resultSha,string $edgeSha):void{
    $k=$entity.'|'.$ns.'|'.$native;
    if(!isset($side[$k]))$side[$k]=['entity'=>$entity,'namespace'=>$ns,'native_id'=>$native,'evidence'=>[]];
    $evk=$op.'|'.$resultSha.'|'.$edgeSha;
    if(!isset($side[$k]['evidence'][$evk])&&count($side[$k]['evidence'])<12){
        $side[$k]['evidence'][$evk]=['operation'=>$op,'result_sha256'=>$resultSha,'edge_sha256'=>$edgeSha];
    }
}
function v37_walk(mixed $node,string $op,string $resultSha,array &$tv,array &$samo,array &$shape,int $depth=0):void{
    if($depth>28||!is_array($node))return;
    if(v37_assoc($node)){
        $ns=v37_ns_from_node($node);$native=$ns!==null?v37_native_from_node($node):null;
        if($ns!==null&&$native!==null){
            $edgeSha=v37_projection_hash($node);
            $tvId=v37_int($node['tv_hotel_id']??null);
            if($tvId!==null&&in_array($ns,['operator_315','operator_342','bgoperator','anex'],true)){
                v37_add_edge($tv,(string)$tvId,$ns,$native,$op,$resultSha,$edgeSha);$shape['tv_edges_seen']++;
            }
            $catalog=v37_id($node['catalog_id']??$node['andromeda_catalog_id']??null);
            if($catalog!==null&&in_array($ns,['operator_315','operator_342','operator_115','operator_5'],true)){
                v37_add_edge($samo,$catalog,$ns,$native,$op,$resultSha,$edgeSha);$shape['samo_edges_seen']++;
            }
        }
    }
    foreach($node as $v)if(is_array($v))v37_walk($v,$op,$resultSha,$tv,$samo,$shape,$depth+1);
}
function v37_scan_operations(string $root):array{
    v37_need(is_dir($root)&&!is_link($root),'operations_root');$tv=[];$samo=[];$shape=['operation_dirs_examined'=>0,'result_files_parsed'=>0,'result_files_skipped_size'=>0,'tv_edges_seen'=>0,'samo_edges_seen'=>0];
    foreach(new DirectoryIterator($root) as $e){
        if($e->isDot()||$e->isLink()||!$e->isDir())continue;
        if(++$shape['operation_dirs_examined']>5000)throw new RuntimeException('operation_dir_cap');
        $op=$e->getFilename();if(!str_starts_with($op,'hotel-match-'))continue;
        $p=$e->getPathname().'/result.json';if(!is_file($p)||is_link($p))continue;$size=filesize($p);if($size===false||$size<2||$size>64*1024*1024){$shape['result_files_skipped_size']++;continue;}
        $raw=(string)file_get_contents($p);$resultSha=hash('sha256',$raw);
        try{$r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){continue;}if(!is_array($r))continue;
        $shape['result_files_parsed']++;v37_walk($r,$op,$resultSha,$tv,$samo,$shape);
    }
    foreach([$tv,$samo] as &$set)foreach($set as &$row)$row['evidence']=array_values($row['evidence']);unset($row,$set);
    return ['tv_edges'=>$tv,'samo_edges'=>$samo,'shape'=>$shape];
}
function v37_frontier(array $f):array{
    v37_need(($f['operation']??'')==='hotel-match-business-live30-frontier-plan-1971-20260925-v33','frontier_operation');
    v37_need(($f['state']??'')==='completed_read_only_business_live30_frontier_plan','frontier_state');
    v37_need((int)($f['non_full_triple_total']??0)===V37_FRONTIER_TOTAL,'frontier_total');
    v37_need(($f['target_set_sha256']??'')===V37_FRONTIER_SHA,'frontier_sha');
    $expect=['tv_samo_missing_anex'=>777,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726];v37_need(($f['bucket_counts']??null)===$expect,'frontier_buckets');
    $out=[];foreach($f['rows']??[] as $r){if(!is_array($r))continue;$id=v37_int($r['local_hotel_id']??null);$b=(string)($r['bucket']??'');if($id===null||!isset($expect[$b]))continue;$out[$id]=$b;}
    v37_need(count($out)===V37_FRONTIER_TOTAL,'frontier_rows');return$out;
}
function v37_indexes(array $scan,array $frontier):array{
    $tvFp=[];$tvTargetNs=[];$samoFp=[];$samoSourceNs=[];
    foreach($scan['tv_edges'] as $e){$tv=(int)$e['entity'];if(!isset($frontier[$tv]))continue;$ns=$e['namespace'];$native=$e['native_id'];$fp=$ns.'|'.$native;$tvFp[$fp][$tv]=true;$tvTargetNs[$tv][$ns][$native]=true;}
    foreach($scan['samo_edges'] as $e){$src=$e['entity'];$ns=$e['namespace'];$native=$e['native_id'];$fp=$ns.'|'.$native;$samoFp[$fp][$src]=true;$samoSourceNs[$src][$ns][$native]=true;}
    return compact('tvFp','tvTargetNs','samoFp','samoSourceNs');
}
function v37_match_one(string $src,string $samoNs,string $tvNs,array $idx):array{
    $natives=array_keys($idx['samoSourceNs'][$src][$samoNs]??[]);
    if(count($natives)!==1)return ['state'=>$natives===[]?'source_namespace_missing':'source_namespace_multi_native','namespace'=>$samoNs,'tv_namespace'=>$tvNs,'native_ids'=>$natives,'target'=>null];
    $native=(string)$natives[0];$samoSources=array_keys($idx['samoFp'][$samoNs.'|'.$native]??[]);
    if(count($samoSources)!==1)return ['state'=>'samo_fingerprint_source_collision','namespace'=>$samoNs,'tv_namespace'=>$tvNs,'native_id'=>$native,'target'=>null,'source_count'=>count($samoSources)];
    $targets=array_keys($idx['tvFp'][$tvNs.'|'.$native]??[]);
    if(count($targets)!==1)return ['state'=>$targets===[]?'no_tv_fingerprint':'tv_fingerprint_target_collision','namespace'=>$samoNs,'tv_namespace'=>$tvNs,'native_id'=>$native,'target'=>null,'target_count'=>count($targets)];
    $target=(int)$targets[0];$targetNatives=array_keys($idx['tvTargetNs'][$target][$tvNs]??[]);
    if(count($targetNatives)!==1)return ['state'=>'tv_target_namespace_multi_native','namespace'=>$samoNs,'tv_namespace'=>$tvNs,'native_id'=>$native,'target'=>$target,'target_native_count'=>count($targetNatives)];
    return ['state'=>'exact_unique','namespace'=>$samoNs,'tv_namespace'=>$tvNs,'native_id'=>$native,'target'=>$target];
}
function v37_current_state(PDO $db,array $frontier):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];foreach(v37_query($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1") as $r){$id=(int)$r['id'];if(isset($frontier[$id])&&!v37_excluded((string)$r['country_name']))$active[$id]=$r;}
        $bySource=[];$byTarget=[];
        foreach(v37_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id,local_hotel_id") as $r){
            $src=(string)$r['external_hotel_id'];$bySource[$src][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$byTarget[(int)$r['local_hotel_id']][]=$r;
        }
        $manual=[];foreach(v37_query($db,"SELECT DISTINCT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL") as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        $db->rollBack();return compact('active','bySource','byTarget','manual');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v37_classify_source(string $src,array $idx,array $frontier,array $current):array{
    $direct=[];$support=[];$directTargets=[];$supportTargets=[];$quality=[];
    foreach(V37_DIRECT as $samoNs=>$tvNs){$m=v37_match_one($src,$samoNs,$tvNs,$idx);$direct[$samoNs]=$m;$quality[$m['state']]=($quality[$m['state']]??0)+1;if($m['state']==='exact_unique')$directTargets[(int)$m['target']][$samoNs]=true;}
    foreach(V37_SUPPORT as $samoNs=>$tvNs){$m=v37_match_one($src,$samoNs,$tvNs,$idx);$support[$samoNs]=$m;$quality['support_'.$m['state']]=($quality['support_'.$m['state']]??0)+1;if($m['state']==='exact_unique')$supportTargets[(int)$m['target']][$samoNs]=true;}
    $dTargets=array_keys($directTargets);$sTargets=array_keys($supportTargets);$candidate=null;$base='no_shared_fingerprint';$directOps=0;$supportOps=0;
    if(count($dTargets)>1){$base='conflict_direct_targets';}
    elseif(count($dTargets)===1){$candidate=(int)$dTargets[0];$directOps=count($directTargets[$candidate]);$supportOps=count($supportTargets[$candidate]??[]);$base=$directOps>=2?'strong_two_direct':($supportOps>0?'single_direct_plus_support':'single_direct');if(array_diff($sTargets,[$candidate]))$base='conflict_support_target';}
    elseif(count($sTargets)>1){$base='conflict_support_targets';}
    elseif(count($sTargets)===1){$candidate=(int)$sTargets[0];$supportOps=count($supportTargets[$candidate]);$base='support_only';}
    if($candidate!==null&&!isset($frontier[$candidate])){$base='hold_target_outside_frontier';$candidate=null;}
    $status=$base;$currentState='not_applicable';
    if($candidate!==null&&str_starts_with($base,'strong_two_direct')){
        $srcRows=$current['bySource'][$src]??[];$targetRows=$current['byTarget'][$candidate]??[];
        $same=array_filter($srcRows,fn($r)=>(($r['decision_status']??'')==='accepted'&&(int)($r['local_hotel_id']??0)===$candidate));
        $foreignSource=array_filter($srcRows,fn($r)=>(($r['decision_status']??'')==='accepted'&&(int)($r['local_hotel_id']??0)!==$candidate));
        $foreignTarget=array_filter($targetRows,fn($r)=>(string)($r['external_hotel_id']??'')!==$src);
        if($same){$currentState='already_resolved_same';$status='strong_two_direct_already_resolved_same';}
        elseif($foreignSource){$currentState='source_occupied';$status='hold_source_occupied';}
        elseif($foreignTarget){$currentState='target_samo_occupied';$status='hold_target_samo_occupied';}
        elseif(!isset($current['active'][$candidate])){$currentState='target_inactive_or_excluded';$status='hold_target_inactive_or_excluded';}
        elseif(isset($current['manual'][$candidate])){$currentState='manual_target_protected';$status='hold_manual_target';}
        else{$currentState='current_missing_bridge';$status='strong_two_direct_current_missing';}
    }
    return ['andromeda_catalog_id'=>$src,'candidate_local_hotel_id'=>$candidate,'frontier_bucket'=>$candidate!==null?($frontier[$candidate]??null):null,'status'=>$status,'base_status'=>$base,'current_state'=>$currentState,'direct_operator_count'=>$directOps,'support_operator_count'=>$supportOps,'direct'=>$direct,'support'=>$support,'quality_counts'=>$quality,'safe_to_write_now'=>false];
}
function v37_execute(PDO $db,string $operationsRoot,array $frontierDoc,string $sourceSha):array{
    $frontier=v37_frontier($frontierDoc);$scan=v37_scan_operations($operationsRoot);$idx=v37_indexes($scan,$frontier);$current=v37_current_state($db,$frontier);
    $sources=array_keys($idx['samoSourceNs']);sort($sources,SORT_NATURAL);$rows=[];$status=[];$lift=['tv_samo_missing_anex'=>0,'tv_anex_missing_samo'=>0,'tv_only_missing_both'=>0];$sharedDirect=['operator_315'=>0,'operator_342'=>0];$sharedSupport=['operator_115_bgoperator'=>0,'operator_5_anex'=>0];
    foreach($sources as $src){$r=v37_classify_source((string)$src,$idx,$frontier,$current);if($r['status']==='no_shared_fingerprint')continue;$rows[]=$r;$status[$r['status']]=($status[$r['status']]??0)+1;if($r['status']==='strong_two_direct_current_missing'&&isset($lift[$r['frontier_bucket']]))$lift[$r['frontier_bucket']]++;}
    foreach(['operator_315','operator_342'] as $ns)foreach($idx['samoFp'] as $fp=>$srcs)if(str_starts_with($fp,$ns.'|')){[$x,$native]=explode('|',$fp,2);if(isset($idx['tvFp'][$ns.'|'.$native]))$sharedDirect[$ns]++;}
    foreach([['operator_115','bgoperator','operator_115_bgoperator'],['operator_5','anex','operator_5_anex']] as [$sns,$tns,$label])foreach($idx['samoFp'] as $fp=>$srcs)if(str_starts_with($fp,$sns.'|')){[$x,$native]=explode('|',$fp,2);if(isset($idx['tvFp'][$tns.'|'.$native]))$sharedSupport[$label]++;}
    ksort($status);usort($rows,fn($a,$b)=>[$a['status'],$a['candidate_local_hotel_id']??PHP_INT_MAX,$a['andromeda_catalog_id']]<=>[$b['status'],$b['candidate_local_hotel_id']??PHP_INT_MAX,$b['andromeda_catalog_id']]);
    $strong=(int)($status['strong_two_direct_current_missing']??0);$single=(int)($status['single_direct']??0)+(int)($status['single_direct_plus_support']??0);$support=(int)($status['support_only']??0);$conflict=0;foreach($status as $k=>$n)if(str_starts_with($k,'conflict_')||str_starts_with($k,'hold_'))$conflict+=(int)$n;
    return ['operation'=>V37_OP,'state'=>'completed_read_only_tv_samo_common4_fingerprint_join','generated_at_utc'=>gmdate('c'),'source_sha'=>$sourceSha,
        'frontier_total'=>count($frontier),'frontier_target_sha256'=>V37_FRONTIER_SHA,'operation_scan'=>$scan['shape'],
        'tv_semantic_edges'=>count($scan['tv_edges']),'samo_semantic_edges'=>count($scan['samo_edges']),'tv_fingerprint_count'=>count($idx['tvFp']),'samo_fingerprint_count'=>count($idx['samoFp']),
        'shared_direct_fingerprints'=>$sharedDirect,'shared_support_fingerprints'=>$sharedSupport,'candidate_status_counts'=>$status,
        'strong_current_missing_count'=>$strong,'single_direct_review_count'=>$single,'support_only_review_count'=>$support,'conflict_or_hold_count'=>$conflict,
        'lift_potential_by_v33_bucket'=>$lift,'rows'=>$rows,
        'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
function v37_self_test():void{
    $scan=['tv_edges'=>[
        '1|operator_315|700'=>['entity'=>'1','namespace'=>'operator_315','native_id'=>'700','evidence'=>[]],
        '1|operator_342|800'=>['entity'=>'1','namespace'=>'operator_342','native_id'=>'800','evidence'=>[]],
        '1|bgoperator|900'=>['entity'=>'1','namespace'=>'bgoperator','native_id'=>'900','evidence'=>[]],
    ],'samo_edges'=>[
        '10|operator_315|700'=>['entity'=>'10','namespace'=>'operator_315','native_id'=>'700','evidence'=>[]],
        '10|operator_342|800'=>['entity'=>'10','namespace'=>'operator_342','native_id'=>'800','evidence'=>[]],
        '10|operator_115|900'=>['entity'=>'10','namespace'=>'operator_115','native_id'=>'900','evidence'=>[]],
    ]];$idx=v37_indexes($scan,[1=>'tv_only_missing_both']);
    $m=v37_match_one('10','operator_315','operator_315',$idx);v37_need($m['state']==='exact_unique'&&$m['target']===1,'direct');
    $current=['active'=>[1=>['id'=>1]],'bySource'=>[],'byTarget'=>[],'manual'=>[]];$r=v37_classify_source('10',$idx,[1=>'tv_only_missing_both'],$current);
    v37_need($r['status']==='strong_two_direct_current_missing'&&$r['direct_operator_count']===2&&$r['support_operator_count']===1,'strong');
    unset($scan['samo_edges']['10|operator_342|800']);$idx=v37_indexes($scan,[1=>'tv_only_missing_both']);$r=v37_classify_source('10',$idx,[1=>'tv_only_missing_both'],$current);
    v37_need($r['status']==='single_direct_plus_support','single_support');
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v37_self_test();echo "MATCH_TV_SAMO_COMMON4_FINGERPRINT_JOIN_V37_SELFTEST_OK\n";exit;}
    v37_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$ops=(string)getenv('MATCH_OPERATIONS_ROOT');$frontierPath=(string)getenv('MATCH_FRONTIER_RESULT');$frontierSha=(string)getenv('MATCH_FRONTIER_RESULT_SHA');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v37_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V37_OP&&is_dir($ops)&&is_file($frontierPath)&&preg_match('/^[0-9a-f]{64}$/D',$frontierSha)===1&&hash_file('sha256',$frontierPath)===$frontierSha&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=v37_load($dir.'/reservation.json');v37_need(($reservation['operation']??'')===V37_OP&&($reservation['state']??'')==='reserved_before_read_only_join','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$out=v37_execute(v2_data_db(),$ops,v37_load($frontierPath),$sha);$h=v37_save($dir.'/result.json',$out);v37_save($dir.'/receipt.json',['operation'=>V37_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v37_json(['state'=>$out['state'],'strong_current_missing_count'=>$out['strong_current_missing_count'],'single_direct_review_count'=>$out['single_direct_review_count'],'support_only_review_count'=>$out['support_only_review_count'],'candidate_status_counts'=>$out['candidate_status_counts'],'lift_potential_by_v33_bucket'=>$out['lift_potential_by_v33_bucket']])."\n";}
    catch(Throwable $e){$f=['operation'=>V37_OP,'state'=>'failed_read_only_tv_samo_common4_fingerprint_join','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,180,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];$h=v37_save($dir.'/result.json',$f);v37_save($dir.'/receipt.json',['operation'=>V37_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
