<?php
declare(strict_types=1);

const V64_OP='hotel-match-live234-sealed-secondary-canonical-consensus-1971-20260926-v64b';
const V64_SOURCE_OP='hotel-match-live234-sealed-secondary-salvage-1971-20260926-v63';
const V64_SOURCE_SHA='dc4ad9e16b5f800a363f406965e318b6eb69bdb2ea77eb0b856cfe4c010fce9c';
const V64_SOURCE_EDGES=48;
const V64_SOURCE_TARGETS=41;
const V64_NS=['operator_115'=>115,'operator_315'=>315,'operator_342'=>342];
const V64_TV_BRIDGE=['bgoperator'=>'operator_115','operator_315'=>'operator_315','operator_342'=>'operator_342'];
const V64_MAX_DIRS=5000;
const V64_MAX_RESULT_BYTES=67108864;
const V64_NODE_CAP=2500000;
const V64_SOURCE_CHILD_HASHES=[
    'hotel-match-live234-tv-secondary-1971-20260923-o0-n78-v1'=>'fb8cb7d6acbcc921aa1d6f8a1399190e4b0fb2e418d23c9ac0c84c6e470c20e4',
    'hotel-match-live234-tv-secondary-1971-20260923-o78-n78-v1'=>'28f9dae4037d5e296751616d8cdb7ae753382d215ee628d2a3d1c4c29a935988',
    'hotel-match-live234-tv-secondary-1971-20260923-o156-n78-v1'=>'0b396a1132ab21a1a7ad21eb777be988a8d1fbd432912b3bef19b45940dcc4de',
];

function v64_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v64_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v64_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v64_need(is_array($v),'json_shape');return$v;}
function v64_save(string $p,array $v):string{$raw=v64_json($v)."\n";$f=@fopen($p,'x+b');v64_need($f!==false,'exclusive_create');try{v64_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v64_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v64_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function v64_int(mixed $v):?int{$s=v64_id($v);if($s===null||strlen($s)>9)return null;$x=(int)$s;return$x>0?$x:null;}
function v64_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function v64_assoc(array $x):bool{return !array_is_list($x);}
function v64_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return$st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v64_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function v64_single_native(array $n):?string{
    $ids=$n['positive_native_candidates']??null;
    if(!is_array($ids)||count($ids)!==1)return null;
    $state=(string)($n['link_state']??$n['state']??'');
    if(!str_contains($state,'single_native')&&$state!=='detail_identity_verified')return null;
    return v64_id(array_values($ids)[0]??null);
}
function v64_node_hash(array $n):string{
    $p=[];foreach(['catalog_id','andromeda_catalog_id','namespace','supplier_namespace','operator_id','link_state','state','positive_native_candidates','external_hotel_id'] as$k)if(array_key_exists($k,$n))$p[$k]=$n[$k];
    return hash('sha256',v64_json($p));
}
function v64_source_edges(array $src):array{
    v64_need(($src['operation']??'')===V64_SOURCE_OP,'source_op');
    v64_need(($src['state']??'')==='completed_server_read_only_sealed_secondary_salvage','source_state');
    v64_need((int)($src['current_membership_edges']??0)===48&&(int)($src['detail_verified_single_native_count']??0)===48&&(int)($src['detail_verified_ambiguous_count']??-1)===0,'source_counts');
    v64_need(($src['single_native_namespace_counts']??null)===['bgoperator'=>39,'operator_315'=>2,'operator_342'=>7],'source_ns_counts');
    $proof=[];$bySource=[];$byTarget=[];$targets=[];
    foreach($src['single_native_edges']??$src['edges']??[] as$i=>$e){
        if(!is_array($e)||($e['state']??'')!=='detail_identity_verified'||($e['link_state']??'')!=='captured_single_native')continue;
        $tv=v64_int($e['tv_hotel_id']??null);$rawNs=(string)($e['namespace']??'');$native=v64_single_native($e);$ns=V64_TV_BRIDGE[$rawNs]??null;
        v64_need($tv!==null&&$native!==null&&$ns!==null,'source_edge_shape');
        $op=(int)($e['operator_id']??0);$expect=['bgoperator'=>18,'operator_315'=>25,'operator_342'=>43][$rawNs]??0;v64_need($op===$expect,'source_operator');
        $k=$ns.'|'.$native.'|'.$tv;
        if(isset($proof[$k]))continue;
        $child=(string)($e['source_child']??'');$childSha=(string)($e['source_result_sha256']??'');
        v64_need(isset(V64_SOURCE_CHILD_HASHES[$child])&&V64_SOURCE_CHILD_HASHES[$child]===$childSha,'source_child_hash');
        $row=['target'=>$tv,'namespace'=>$ns,'native_id'=>$native,'source_raw_namespace'=>$rawNs,'source_operator_id'=>$op,'source_edge_index'=>$i,
            'source_child'=>$child,'source_result_sha256'=>$childSha,
            'tour_id_sha256'=>(string)($e['tour_id_sha256']??''),'operator_link_sha256'=>(string)($e['operator_link_sha256']??'')];
        foreach(['tour_id_sha256','operator_link_sha256'] as$f)v64_need(v64_sha($row[$f]),'source_hash_'.$f);
        $proof[$k]=$row;$bySource[$ns.'|'.$native][$tv]=true;$byTarget[$tv][$ns][$native]=true;$targets[$tv]=true;
    }
    v64_need(count($proof)===V64_SOURCE_EDGES&&count($targets)===V64_SOURCE_TARGETS,'source_exact_scope');
    return['proofs'=>$proof,'by_source'=>$bySource,'by_target'=>$byTarget,'targets'=>$targets];
}
function v64_collect_samo(mixed $node,string $op,string $rsha,array &$out,int &$visited,int $depth=0):void{
    if($depth>28||!is_array($node))return;if(++$visited>V64_NODE_CAP)throw new RuntimeException('node_cap');
    if(v64_assoc($node)){
        $ns=trim((string)($node['supplier_namespace']??$node['namespace']??''));
        if(isset(V64_NS[$ns])){
            $native=v64_single_native($node);
            if($native===null){
                $state=(string)($node['link_state']??$node['state']??'');
                if(str_contains($state,'single_native')||isset($node['source_edge_sha256'])||isset($node['operator_link_sha256']))$native=v64_id($node['external_hotel_id']??null);
            }
            $cid=v64_id($node['catalog_id']??$node['andromeda_catalog_id']??null);
            $operator=(int)($node['operator_id']??V64_NS[$ns]);
            if($native!==null&&$cid!==null&&$operator===V64_NS[$ns]){
                $eh=v64_node_hash($node);$k=$cid.'|'.$ns.'|'.$native;
                if(!isset($out[$k]))$out[$k]=['catalog_id'=>$cid,'namespace'=>$ns,'native_id'=>$native,'evidence'=>[]];
                $ek=$op.'|'.$rsha.'|'.$eh;if(!isset($out[$k]['evidence'][$ek])&&count($out[$k]['evidence'])<12)$out[$k]['evidence'][$ek]=['operation'=>$op,'result_sha256'=>$rsha,'edge_sha256'=>$eh];
            }
        }
    }
    foreach($node as$v)if(is_array($v))v64_collect_samo($v,$op,$rsha,$out,$visited,$depth+1);
}
function v64_scan(string $root):array{
    v64_need(is_dir($root)&&!is_link($root),'ops_root');$dirs=[];foreach(new DirectoryIterator($root)as$e){if($e->isDot()||$e->isLink()||!$e->isDir())continue;$dirs[]=$e->getPathname();}
    sort($dirs,SORT_STRING);v64_need(count($dirs)<=V64_MAX_DIRS,'dir_cap');$edges=[];$stats=['operation_dirs'=>count($dirs),'result_files'=>0,'parsed_results'=>0,'skipped_large'=>0,'visited_nodes'=>0];
    foreach($dirs as$d){if(basename($d)===V64_OP)continue;$p=$d.'/result.json';if(!is_file($p)||is_link($p))continue;$stats['result_files']++;$z=filesize($p);if($z===false||$z<2)continue;if($z>V64_MAX_RESULT_BYTES){$stats['skipped_large']++;continue;}$raw=(string)file_get_contents($p);$rsha=hash('sha256',$raw);try{$r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){continue;}if(!is_array($r))continue;$stats['parsed_results']++;$op=(string)($r['operation']??basename($d));v64_collect_samo($r,$op,$rsha,$edges,$stats['visited_nodes']);}
    $rows=[];foreach($edges as$r){$r['evidence']=array_values($r['evidence']);$rows[]=$r;}
    return['edges'=>$rows,'stats'=>$stats];
}
function v64_indexes(array $scan):array{
    $src=[];$fp=[];foreach($scan['edges']as$e){$cid=$e['catalog_id'];$ns=$e['namespace'];$native=$e['native_id'];$src[$cid][$ns][$native][]=$e;$fp[$ns.'|'.$native][$cid]=true;}return compact('src','fp');
}
function v64_current(PDO $db,array $targets):array{
    $ids=array_map('intval',array_keys($targets));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];foreach(v64_query($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids)as$r){$id=(int)$r['id'];if((int)$r['is_active']===1&&!v64_excluded((string)$r['country_name']))$active[$id]=$r;}
        $registry=[];$catalogSource=[];$catalogTarget=[];$invalidAccepted=[];
        foreach(v64_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id,local_hotel_id")as$r){
            $ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];
            if($ns==='andromeda_catalog'){$catalogSource[$ext][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$catalogTarget[(int)$r['local_hotel_id']][]=$r;continue;}
            if(!isset(V64_NS[$ns])||($r['decision_status']??'')!=='accepted'||$r['local_hotel_id']===null)continue;
            $raw=(string)($r['evidence_json']??'');$eh=(string)($r['evidence_sha256']??'');$valid=v64_sha($eh)&&hash('sha256',$raw)===$eh;
            $row=['local_hotel_id'=>(int)$r['local_hotel_id'],'evidence_valid'=>$valid,'catalog_sha256'=>(string)($r['catalog_sha256']??''),'evidence_sha256'=>$eh];
            $registry[$ns.'|'.$ext][]=$row;if(!$valid)$invalidAccepted[$ns.'|'.$ext]=true;
        }
        $manual=[];try{foreach(v64_query($db,"SELECT DISTINCT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$ids)as$r)$manual[(int)$r['catalog_hotel_id']]=true;}catch(Throwable){}
        $db->rollBack();return compact('active','registry','catalogSource','catalogTarget','manual','invalidAccepted');
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v64_lane(string $cid,string $ns,array $idx,array $cur,array $proof):array{
    $natives=array_keys($idx['src'][$cid][$ns]??[]);sort($natives,SORT_NATURAL);
    if(count($natives)!==1)return['state'=>$natives===[]?'source_namespace_missing':'source_namespace_multi_native','native_ids'=>$natives,'target'=>null,'tv_proof'=>false];
    $native=(string)$natives[0];$sources=array_keys($idx['fp'][$ns.'|'.$native]??[]);sort($sources,SORT_NATURAL);
    if(count($sources)!==1)return['state'=>'source_fingerprint_catalog_collision','native_id'=>$native,'source_count'=>count($sources),'target'=>null,'tv_proof'=>false];
    $regs=$cur['registry'][$ns.'|'.$native]??[];if(isset($cur['invalidAccepted'][$ns.'|'.$native]))return['state'=>'registry_invalid_evidence','native_id'=>$native,'target'=>null,'tv_proof'=>false];
    $targets=[];foreach($regs as$r)if($r['evidence_valid'])$targets[(int)$r['local_hotel_id']]=true;
    if(count($targets)!==1)return['state'=>$targets===[]?'registry_missing':'registry_target_collision','native_id'=>$native,'target'=>null,'target_count'=>count($targets),'tv_proof'=>false];
    $target=(int)array_key_first($targets);$proofTargets=array_keys($proof['by_source'][$ns.'|'.$native]??[]);sort($proofTargets,SORT_NUMERIC);
    $tv=count($proofTargets)===1&&(int)$proofTargets[0]===$target&&count($proof['by_target'][$target][$ns]??[])===1;
    $ev=[];foreach($idx['src'][$cid][$ns][$native]??[]as$e)foreach($e['evidence']??[]as$x)$ev[$x['operation'].'|'.$x['result_sha256'].'|'.$x['edge_sha256']]=$x;
    return['state'=>'registry_unique','native_id'=>$native,'target'=>$target,'tv_proof'=>$tv,'source_evidence'=>array_values($ev)];
}
function v64_classify(string $cid,array $idx,array $cur,array $proof):array{
    $lanes=[];$targets=[];$laneCount=0;$tvProof=0;$ambig=false;
    foreach(array_keys(V64_NS)as$ns){$r=v64_lane($cid,$ns,$idx,$cur,$proof);$lanes[$ns]=$r;if($r['state']==='registry_unique'){$laneCount++;$targets[(int)$r['target']][$ns]=true;if($r['tv_proof'])$tvProof++;}elseif(!in_array($r['state'],['source_namespace_missing','registry_missing'],true))$ambig=true;}
    $ts=array_keys($targets);$target=count($ts)===1?(int)$ts[0]:null;$status=$laneCount===0?'consensus_0':($laneCount===1?'consensus_1':'registry_2plus_review');
    if(count($ts)>1)$status='hold_conflicting_registry_targets';elseif($ambig&&$laneCount>=2)$status='hold_lane_ambiguity';elseif($laneCount>=2&&$tvProof>=1)$status='strict_cross_source_2plus';
    if($status==='strict_cross_source_2plus'&&$target!==null){
        $srcRows=$cur['catalogSource'][$cid]??[];$same=array_filter($srcRows,fn($r)=>(($r['decision_status']??'')==='accepted'&&(int)($r['local_hotel_id']??0)===$target));
        $otherSrc=array_filter($srcRows,fn($r)=>(($r['decision_status']??'')==='accepted'&&(int)($r['local_hotel_id']??0)!==$target));$otherTarget=array_filter($cur['catalogTarget'][$target]??[],fn($r)=>(string)($r['external_hotel_id']??'')!==$cid);
        if(!isset($proof['targets'][$target]))$status='hold_target_outside_v63';
        elseif($same)$status='already_resolved_same';
        elseif($otherSrc)$status='hold_source_catalog_occupied';
        elseif($otherTarget)$status='hold_target_catalog_occupied';
        elseif(!isset($cur['active'][$target]))$status='hold_target_inactive_or_excluded';
        elseif(isset($cur['manual'][$target]))$status='hold_manual_target';
        else$status='strict_cross_source_2plus_current_missing';
    }
    return['andromeda_catalog_id'=>$cid,'candidate_local_hotel_id'=>$target,'consensus_operator_count'=>$laneCount,'tv_proof_operator_count'=>$tvProof,'status'=>$status,'lanes'=>$lanes,'safe_to_write_now'=>false];
}
function v64_execute(PDO$db,string$ops,array$src,string$sourceSha):array{
    $proof=v64_source_edges($src);$scan=v64_scan($ops);$idx=v64_indexes($scan);$cur=v64_current($db,$proof['targets']);$sources=array_keys($idx['src']);sort($sources,SORT_NATURAL);$rows=[];$counts=[];$strict=0;$dist=['0'=>0,'1'=>0,'2'=>0,'3'=>0];
    foreach($sources as$cid){$r=v64_classify((string)$cid,$idx,$cur,$proof);$n=min(3,max(0,(int)$r['consensus_operator_count']));$dist[(string)$n]++;$counts[$r['status']]=($counts[$r['status']]??0)+1;if($r['status']==='strict_cross_source_2plus_current_missing')$strict++;if($r['candidate_local_hotel_id']!==null&&isset($proof['targets'][(int)$r['candidate_local_hotel_id']]))$rows[]=$r;}
    ksort($counts);usort($rows,fn($a,$b)=>[$a['status'],$a['candidate_local_hotel_id']??PHP_INT_MAX,$a['andromeda_catalog_id']]<=>[$b['status'],$b['candidate_local_hotel_id']??PHP_INT_MAX,$b['andromeda_catalog_id']]);
    return['operation'=>V64_OP,'state'=>'completed_read_only_live234_secondary_canonical_consensus','source_sha'=>$sourceSha,'source_result_sha256'=>V64_SOURCE_SHA,
        'input_edge_count'=>count($proof['proofs']),'input_target_count'=>count($proof['targets']),'input_target_lane_histogram'=>v64_target_hist($proof['by_target']),
        'operation_scan'=>$scan['stats'],'samo_source_count'=>count($sources),'samo_semantic_edges'=>count($scan['edges']),
        'consensus_distribution'=>$dist,'candidate_status_counts'=>$counts,'strict_current_missing_count'=>$strict,'rows'=>$rows,
        'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
function v64_target_hist(array$byTarget):array{$h=[];foreach($byTarget as$x){$n=0;foreach($x as$ns=>$ids)$n+=count($ids);$h[(string)$n]=($h[(string)$n]??0)+1;}ksort($h,SORT_NUMERIC);return$h;}
function v64_self_test():void{
    $proof=['by_source'=>['operator_115|10'=>[7=>true]],'by_target'=>[7=>['operator_115'=>['10'=>true]]],'targets'=>[7=>true]];
    $idx=['src'=>['99'=>['operator_115'=>['10'=>[['evidence'=>[]]]],'operator_315'=>['20'=>[['evidence'=>[]]]]]],'fp'=>['operator_115|10'=>['99'=>true],'operator_315|20'=>['99'=>true]]];
    $cur=['registry'=>['operator_115|10'=>[['local_hotel_id'=>7,'evidence_valid'=>true]],'operator_315|20'=>[['local_hotel_id'=>7,'evidence_valid'=>true]]],'invalidAccepted'=>[],'catalogSource'=>[],'catalogTarget'=>[],'active'=>[7=>['id'=>7]],'manual'=>[]];
    $r=v64_classify('99',$idx,$cur,$proof);v64_need($r['status']==='strict_cross_source_2plus_current_missing'&&$r['tv_proof_operator_count']===1,'strict');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v64_self_test();echo"MATCH_LIVE234_SECONDARY_CANONICAL_CONSENSUS_V64_SELFTEST_OK\n";exit;}
    v64_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$ops=(string)getenv('MATCH_OPERATIONS_ROOT');$srcp=(string)getenv('MATCH_SOURCE_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v64_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V64_OP&&is_dir($ops)&&is_file($srcp)&&hash_file('sha256',$srcp)===V64_SOURCE_SHA&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $res=v64_load($dir.'/reservation.json');v64_need(($res['operation']??'')===V64_OP&&($res['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$out=v64_execute(v2_data_db(),$ops,v64_load($srcp),$sha);$h=v64_save($dir.'/result.json',$out);v64_save($dir.'/receipt.json',['operation'=>V64_OP,'state'=>$out['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0]);echo v64_json(['state'=>$out['state'],'input_edge_count'=>$out['input_edge_count'],'input_target_count'=>$out['input_target_count'],'input_target_lane_histogram'=>$out['input_target_lane_histogram'],'consensus_distribution'=>$out['consensus_distribution'],'candidate_status_counts'=>$out['candidate_status_counts'],'strict_current_missing_count'=>$out['strict_current_missing_count']])."\n";}
    catch(Throwable$e){$f=['operation'=>V64_OP,'state'=>'failed_read_only_live234_secondary_canonical_consensus','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,180,'UTF-8')),'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];$h=v64_save($dir.'/result.json',$f);v64_save($dir.'/receipt.json',['operation'=>V64_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
