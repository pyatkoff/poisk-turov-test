<?php
declare(strict_types=1);

const SBW5_AUDIT_OP='hotel-match-common4-retained-context-v26b-current-1971-20260925-v29b';
const SBW5_NS=['operator_5'=>5,'operator_115'=>115,'operator_315'=>315,'operator_342'=>342];
const SBW5_WRITER_OP_RE='/^hotel-match-common4-retained-context-v29b-writer-1971-20260925-v30-w[12]$/D';

function sbw5_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function sbw5_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('sbw5_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=sbw5_sort($x);return $v;}
function sbw5_json(mixed $v):string{return json_encode(sbw5_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function sbw5_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);sbw5_need(is_array($v),'json_shape');return $v;}
function sbw5_save(string $p,array $v):string{$raw=sbw5_json($v)."\n";$f=@fopen($p,'x+b');sbw5_need($f!==false,'exclusive_create');try{sbw5_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))sbw5_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function sbw5_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function sbw5_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function sbw5_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function sbw5_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function sbw5_key(array $r):string{return (string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id'];}
function sbw5_row_hash(array $r):string{return hash('sha256',sbw5_json($r));}
function sbw5_target(array $h):array{$out=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$out[$k]=$h[$k]??null;return $out;}
function sbw5_anchor_projection(array $a):array{$out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;if($out['local_hotel_id']!==null)$out['local_hotel_id']=(int)$out['local_hotel_id'];return $out;}
function sbw5_anchor_set(array $aa):array{
    $out=[];foreach($aa as $a){sbw5_need(is_array($a),'anchor_shape');$p=sbw5_anchor_projection($a);sbw5_need($p['supplier_namespace']==='andromeda_catalog'&&$p['decision_status']==='accepted'&&sbw5_id($p['external_hotel_id'])!==null&&(int)$p['local_hotel_id']>0&&sbw5_sha($p['catalog_sha256'])&&sbw5_sha($p['evidence_sha256']),'anchor_fields');$k=(string)$p['external_hotel_id'].'|'.$p['catalog_sha256'].'|'.$p['evidence_sha256'];sbw5_need(!isset($out[$k]),'anchor_duplicate');$out[$k]=$p;}ksort($out,SORT_STRING);return $out;
}
function sbw5_manifest(array $audit,int $offset,int $expected):array{
    sbw5_need(($audit['operation']??'')===SBW5_AUDIT_OP&&($audit['state']??'')==='completed_read_only_samo_business_common4_current','audit_state');
    sbw5_need((int)($audit['captured_single_native_edge_count']??0)===801&&(int)($audit['writer_ready_total']??0)===301,'audit_wave1_counts');
    sbw5_need(($audit['writer_ready_counts']??null)===['operator_315'=>29,'operator_5'=>272],'audit_wave1_namespace');
    foreach(['provider_http_calls','tourvisor_calls','samo_calls','anex_calls','andromeda_calls','database_writes','mapping_writes'] as $k)sbw5_need((int)($audit[$k]??-1)===0,'audit_zero_'.$k);
    sbw5_need(($audit['safe_to_write_now']??null)===false&&sbw5_sha($audit['source_result_sha256']??null),'audit_authority');
    $total=(int)($audit['writer_ready_total']??-1);sbw5_need($total>=1&&$total<=6480,'audit_ready_total');
    $counts=$audit['writer_ready_counts']??null;sbw5_need(is_array($counts)&&array_sum(array_map('intval',$counts))===$total,'audit_ready_counts');
    sbw5_need($total===301&&(($offset===0&&$expected===300)||($offset===300&&$expected===1)),'wave1_exact_slice');
    $all=[];$sources=[];$targets=[];
    foreach(($audit['rows']??[]) as $r){
        if(!is_array($r)||($r['writer_ready']??false)!==true)continue;
        sbw5_need(($r['status']??'')==='writer_ready'&&($r['anchor_state']??'')==='canonical_anchor_ok'&&($r['safe_to_write_now']??null)===false,'row_state');
        $ns=(string)($r['supplier_namespace']??'');$op=(int)($r['operator_id']??0);$ext=sbw5_id($r['external_hotel_id']??null);$local=(int)($r['local_hotel_id']??0);
        sbw5_need(isset(SBW5_NS[$ns])&&SBW5_NS[$ns]===$op&&$ext!==null&&$local>0,'row_identity');sbw5_need(in_array($ns,['operator_315','operator_5'],true)&&in_array($op,[315,5],true),'wave1_namespace_only');
        sbw5_need(($r['source_operation']??'')===($audit['source_operation']??null)&&($r['source_result_sha256']??'')===($audit['source_result_sha256']??null)&&sbw5_sha($r['source_edge_sha256']??null),'row_source');
        $edgeHashes=$r['source_edge_sha256s']??null;$catalogIds=$r['input_catalog_ids']??null;sbw5_need(is_array($edgeHashes)&&$edgeHashes!==[]&&is_array($catalogIds)&&$catalogIds!==[],'row_evidence_lists');
        $eh=[];foreach($edgeHashes as $h){sbw5_need(sbw5_sha($h),'row_edge_hash');$eh[$h]=true;}$eh=array_keys($eh);sort($eh,SORT_STRING);
        $ci=[];foreach($catalogIds as $id){$id=sbw5_id($id);sbw5_need($id!==null,'row_catalog_id');$ci[$id]=true;}$ci=array_keys($ci);sort($ci,SORT_NATURAL);
        $target=$r['catalog_hotel']??null;$anchors=$r['anchors']??null;$cat=(string)($r['unanimous_catalog_sha256']??'');
        sbw5_need(is_array($target)&&(int)($target['id']??0)===$local&&is_array($anchors)&&count($anchors)>=1&&sbw5_sha($cat),'row_target');
        $aset=sbw5_anchor_set($anchors);foreach($aset as $a)sbw5_need((int)$a['local_hotel_id']===$local&&$a['catalog_sha256']===$cat,'row_anchor_binding');
        $sk=$ns.'|'.$ext;$tk=$ns.'|'.$local;sbw5_need(!isset($sources[$sk]),'writer_source_collision');sbw5_need(!isset($targets[$tk]),'writer_target_collision');$sources[$sk]=true;$targets[$tk]=true;
        $all[]=['supplier_namespace'=>$ns,'operator_id'=>$op,'external_hotel_id'=>$ext,'local_hotel_id'=>$local,'source_operation'=>(string)$r['source_operation'],'source_result_sha256'=>(string)$r['source_result_sha256'],'source_edge_sha256s'=>$eh,'input_catalog_ids'=>$ci,'target'=>$target,'unanimous_catalog_sha256'=>$cat,'anchors'=>$aset];
    }
    sbw5_need(count($all)===$total,'writer_ready_row_count');usort($all,fn($a,$b)=>[$a['supplier_namespace'],$a['external_hotel_id'],$a['local_hotel_id']]<=>[$b['supplier_namespace'],$b['external_hotel_id'],$b['local_hotel_id']]);
    $slice=array_slice($all,$offset,$expected);sbw5_need(count($slice)===$expected,'slice_count');return ['total'=>$total,'rows'=>$slice];
}
function sbw5_indexes(array $rows):array{
    $byKey=[];$byTarget=[];$anchors=[];
    foreach($rows as $r){$k=sbw5_key($r);$byKey[$k][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$local=(int)$r['local_hotel_id'];$byTarget[(string)$r['supplier_namespace'].'|'.$local][]=$r;if(($r['supplier_namespace']??'')==='andromeda_catalog')$anchors[$local][]=$r;}}
    return [$byKey,$byTarget,$anchors];
}
function sbw5_current_anchors(array $m,array $aa):array{
    $valid=[];foreach($aa as $a){if(($a['supplier_namespace']??'')!=='andromeda_catalog'||($a['decision_status']??'')!=='accepted'||(int)($a['local_hotel_id']??0)!==(int)$m['local_hotel_id'])continue;$raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');sbw5_need(sbw5_sha($eh)&&hash('sha256',$raw)===$eh,'anchor_evidence_hash_drift');sbw5_need(sbw5_sha($cat)&&$cat===$m['unanimous_catalog_sha256'],'anchor_catalog_drift');$valid[]=$a;}
    sbw5_need(sbw5_anchor_set($valid)===$m['anchors'],'anchor_set_drift');return $valid;
}
function sbw5_evidence(array $m,array $target,array $anchors,string $writerOp,string $sourceSha,string $auditSha):array{
    return ['operation_id'=>$writerOp,'rule'=>'samo_business_live30_wave5_exact_operator_native_current_anchor_v17','source_sha'=>$sourceSha,'audit'=>['operation'=>SBW5_AUDIT_OP,'result_sha256'=>$auditSha],
        'acquisition'=>['operation'=>$m['source_operation'],'result_sha256'=>$m['source_result_sha256'],'source_edge_sha256s'=>$m['source_edge_sha256s'],'input_catalog_ids'=>$m['input_catalog_ids']],
        'supplier_namespace'=>$m['supplier_namespace'],'operator_id'=>$m['operator_id'],'external_hotel_id'=>$m['external_hotel_id'],'local_hotel_id'=>$m['local_hotel_id'],
        'target'=>sbw5_target($target),'canonical_anchors'=>array_values(sbw5_anchor_set($anchors)),'provider_calls'=>0,'raw_supplier_payload_exported'=>false];
}
function sbw5_write(PDO $db,array $manifest,string $writerOp,string $sourceSha,string $auditSha,?string $opDir=null):array{
    $count=count($manifest);sbw5_need($count>=1&&$count<=300,'writer_count');$ids=array_values(array_unique(array_map(fn($r)=>(int)$r['local_hotel_id'],$manifest)));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $commitAttempted=false;$committed=false;$planned=[];$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    try{
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
        $before=sbw5_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE');$beforeHashes=[];foreach($before as $r)$beforeHashes[sbw5_key($r)]=sbw5_row_hash($r);[$byKey,$byTarget,$anchors]=sbw5_indexes($before);
        $catalog=[];foreach(sbw5_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $manual=[];foreach(sbw5_query($db,"SELECT * FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']][]=$r;
        foreach($manifest as $m){
            $ns=$m['supplier_namespace'];$ext=$m['external_hotel_id'];$local=(int)$m['local_hotel_id'];sbw5_need(empty($byKey[$ns.'|'.$ext]??[]),'source_key_now_present');
            $h=$catalog[$local]??null;sbw5_need(is_array($h)&&(int)($h['is_active']??0)===1&&!sbw5_excluded((string)($h['country_name']??'')),'target_guard');sbw5_need(sbw5_target($h)===sbw5_target($m['target']),'target_facts_drift');
            sbw5_need(!isset($manual[$local]),'manual_target_protected');sbw5_need(empty($byTarget[$ns.'|'.$local]??[]),'target_namespace_occupied');
            $aa=sbw5_current_anchors($m,$anchors[$local]??[]);$ev=sbw5_evidence($m,$h,$aa,$writerOp,$sourceSha,$auditSha);$ej=sbw5_json($ev);$eh=hash('sha256',$ej);$anchorHashes=[];foreach($aa as $a)$anchorHashes[sbw5_key($a)]=sbw5_row_hash($a);
            $planned[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'local_hotel_id'=>$local,'decision_status'=>'accepted','catalog_sha256'=>$m['unanimous_catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej,'anchor_hashes'=>$anchorHashes];
        }
        $st=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach($planned as $p){$st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);sbw5_need($st->rowCount()===1,'insert_count');}
        $after=sbw5_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');sbw5_need(count($after)===count($before)+$count,'identity_count_delta');$afterBy=[];foreach($after as $r)$afterBy[sbw5_key($r)]=$r;foreach($beforeHashes as $k=>$h)sbw5_need(isset($afterBy[$k])&&sbw5_row_hash($afterBy[$k])===$h,'preexisting_identity_changed');
        foreach($planned as $p){$k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$afterBy[$k]??null;sbw5_need(is_array($r),'staged_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)sbw5_need((string)$r[$f]===(string)$p[$f],'staged_mismatch');foreach($p['anchor_hashes'] as $ak=>$ah)sbw5_need(isset($afterBy[$ak])&&sbw5_row_hash($afterBy[$ak])===$ah,'anchor_changed');}
        if($opDir!==null){sbw5_save($opDir.'/pre-commit.json',['operation'=>$writerOp,'state'=>'verified_before_commit','planned_writes'=>$count,'audit_result_sha256'=>$auditSha]);sbw5_save($opDir.'/commit-attempt.json',['operation'=>$writerOp,'state'=>'commit_attempt_no_replay','planned_writes'=>$count]);}
        $commitAttempted=true;sbw5_need($db->commit(),'commit_false');$committed=true;
        $post=sbw5_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');$postBy=[];foreach($post as $r)$postBy[sbw5_key($r)]=$r;$byNs=[];
        foreach($planned as $p){$k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$postBy[$k]??null;sbw5_need(is_array($r),'post_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)sbw5_need((string)$r[$f]===(string)$p[$f],'post_mismatch');$byNs[$p['supplier_namespace']]=($byNs[$p['supplier_namespace']]??0)+1;}ksort($byNs);
        return ['state'=>'committed_verified','inserted'=>$count,'inserted_by_namespace'=>$byNs,'unique_targets'=>count($ids),'database_writes'=>$count,'mapping_writes'=>$count,'readback_verified'=>true,'preexisting_rows_preserved'=>count($before),'provider_http_calls'=>0,'supplier_calls'=>0];
    }catch(Throwable $e){try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}$state=$committed?'post_commit_verification_failed_no_replay':($commitAttempted?'commit_unknown_no_replay':'rolled_back_no_write');throw new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')));}
}
function sbw5_self_test():void{
    $anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'10','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)];
    $rows=[];
    for($i=0;$i<301;$i++){
        $ns=$i<29?'operator_315':'operator_5';$op=$i<29?315:5;
        $local=100+$i;$ext=(string)(900+$i);$catalog=(string)(10+$i);
        $anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$catalog,'local_hotel_id'=>$local,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)];
        $rows[]=['supplier_namespace'=>$ns,'operator_id'=>$op,'external_hotel_id'=>$ext,'local_hotel_id'=>$local,'source_operation'=>'hotel-match-common4-retained-context-acquire-1971-20260925-v26b','source_result_sha256'=>str_repeat('c',64),'source_edge_sha256'=>str_repeat('d',64),'source_edge_sha256s'=>[str_repeat('d',64)],'input_catalog_ids'=>[$catalog],'catalog_hotel'=>['id'=>$local,'name'=>'X','country_id'=>1,'country_name'=>'Turkey','region_id'=>2,'region_name'=>'R','subregion_id'=>3,'subregion_name'=>'S','category'=>'5','is_active'=>1],'unanimous_catalog_sha256'=>str_repeat('a',64),'anchors'=>[$anchor],'status'=>'writer_ready','anchor_state'=>'canonical_anchor_ok','writer_ready'=>true,'safe_to_write_now'=>false];
    }
    $audit=['operation'=>SBW5_AUDIT_OP,'state'=>'completed_read_only_samo_business_common4_current','source_operation'=>'hotel-match-common4-retained-context-acquire-1971-20260925-v26b','source_result_sha256'=>str_repeat('c',64),'captured_single_native_edge_count'=>801,'writer_ready_counts'=>['operator_315'=>29,'operator_5'=>272],'writer_ready_total'=>301,'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    $m1=sbw5_manifest($audit,0,300);$m2=sbw5_manifest($audit,300,1);sbw5_need($m1['total']===301&&count($m1['rows'])===300&&$m2['total']===301&&count($m2['rows'])===1,'self_manifest');
    $c1=[];foreach($m1['rows'] as $r)$c1[$r['supplier_namespace']]=($c1[$r['supplier_namespace']]??0)+1;ksort($c1);sbw5_need($c1===['operator_315'=>29,'operator_5'=>271]&&$m2['rows'][0]['supplier_namespace']==='operator_5','self_slice_namespaces');
    sbw5_need(preg_match(SBW5_WRITER_OP_RE,'hotel-match-common4-retained-context-v29b-writer-1971-20260925-v30-w1')===1&&preg_match(SBW5_WRITER_OP_RE,'hotel-match-common4-retained-context-v29b-writer-1971-20260925-v30-w2')===1,'self_writer_operation_guard');
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){sbw5_self_test();echo "MATCH_COMMON4_RETAINED_CONTEXT_V29B_WRITER_V30_SELFTEST_OK\n";exit;}
    sbw5_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$auditPath=(string)getenv('MATCH_AUDIT_RESULT');$auditSha=(string)getenv('MATCH_AUDIT_SHA');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');$writerOp=(string)getenv('MATCH_WRITER_OPERATION');$expected=(int)getenv('MATCH_EXPECTED_WRITES');$offset=(int)getenv('MATCH_WRITE_OFFSET');
    sbw5_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===$writerOp&&preg_match(SBW5_WRITER_OP_RE,$writerOp)===1&&is_file($auditPath)&&sbw5_sha($auditSha)&&hash_file('sha256',$auditPath)===$auditSha&&preg_match('/^[0-9a-f]{40}$/D',$sourceSha)===1,'runtime_scope');
    $reservation=sbw5_load($dir.'/reservation.json');sbw5_need(($reservation['operation']??'')===$writerOp&&($reservation['state']??'')==='reserved_before_write'&&(int)($reservation['expected_writes']??0)===$expected&&(int)($reservation['write_offset']??-1)===$offset,'reservation');
    try{$manifest=sbw5_manifest(sbw5_load($auditPath),$offset,$expected);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$result=sbw5_write(v2_data_db(),$manifest['rows'],$writerOp,$sourceSha,$auditSha,$dir);$result['operation']=$writerOp;$result['audit_operation']=SBW5_AUDIT_OP;$result['audit_result_sha256']=$auditSha;$result['writer_ready_total']=$manifest['total'];$result['write_offset']=$offset;$h=sbw5_save($dir.'/result.json',$result);sbw5_save($dir.'/receipt.json',['operation'=>$writerOp,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);echo sbw5_json(['operation'=>$writerOp,'state'=>$result['state'],'inserted'=>$result['inserted'],'inserted_by_namespace'=>$result['inserted_by_namespace'],'unique_targets'=>$result['unique_targets']])."\n";}
    catch(Throwable $e){$msg=$e->getMessage();$state=str_starts_with($msg,'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($msg,'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');$writes=$state==='rolled_back_no_write'?0:null;$f=['operation'=>$writerOp,'state'=>$state,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($msg,0,180,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$state!=='rolled_back_no_write'];$h=sbw5_save($dir.'/result.json',$f);sbw5_save($dir.'/receipt.json',['operation'=>$writerOp,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$f['no_replay']]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
