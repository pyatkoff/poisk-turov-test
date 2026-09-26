<?php
declare(strict_types=1);

/** Insert-only retained14 writer. Including this file never opens a database. */
const V69_OP='hotel-match-retained14-writer-1971-20260927-v69';
const V69_AUDIT_OP='hotel-match-retained29-current-1971-20260927-v68';
const V69_AUDIT_SHA='0b592a30a5c301d9631433042c5239baf9cc7f7f427cac5895c9de9300ed4eb9';
const V69_RECEIPT_SHA='636f24b549f4e7aa96c5d3d0e151fe167e37d793f01cf4afbe4b12fb03be3ad3';
const V69_SOURCE_SHA='42829f8f7a7988f3f033bfd8e377758b537ccb95c3ace9a09d953191bfe17810';
const V69_PLAN_SHA='048523ef5a1d8440e39c6341afb531db6346b8786a63312c6328d70f2c1f70b8';
const V69_AUDIT_HEAD='cd9b5503678cc3c426f491181f0cc5c0cf745ef7';
const V69_PAIRS=[11742=>'183505',11748=>'191231',11771=>'364948',11773=>'364934',27691=>'246797',45455=>'2000034442',60000=>'2000055823',64351=>'2000042345',64355=>'2000056913',64722=>'2000055559',71376=>'2000061777',113617=>'2000090568',117800=>'2000090560',119844=>'2000106853'];
const V69_FIELDS=['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'];

function v69_need(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);}
function v69_json(mixed $value):string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v69_canonical(mixed $v):mixed {if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=v69_canonical($x);return $v;}
function v69_hash(array $row):string {return hash('sha256',v69_json(v69_canonical($row)));}
function v69_read(string $path):string {v69_need(is_file($path)&&!is_link($path)&&filesize($path)<=8388608,'input_file');$raw=file_get_contents($path);v69_need(is_string($raw),'input_read');return $raw;}
function v69_decode(string $raw,string $sha):array {v69_need(hash_equals($sha,hash('sha256',$raw)),'input_hash');$v=json_decode($raw,true,128,JSON_THROW_ON_ERROR);v69_need(is_array($v),'input_shape');return $v;}
function v69_save(string $path,array $value):string {$raw=v69_json($value)."\n";$f=@fopen($path,'x+b');v69_need($f!==false,'exclusive_record');try{v69_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'record_write');if(function_exists('fsync'))v69_need(fsync($f),'record_sync');}finally{fclose($f);}return hash('sha256',$raw);}

/** Pure sealed-input selection. It does not authorize execution or treat readiness as acceptance. */
function v69_prepare(string $auditRaw,string $receiptRaw,string $sourceRaw,string $planRaw):array {
    $audit=v69_decode($auditRaw,V69_AUDIT_SHA);$receipt=v69_decode($receiptRaw,V69_RECEIPT_SHA);
    $source=v69_decode($sourceRaw,V69_SOURCE_SHA);v69_decode($planRaw,V69_PLAN_SHA);
    v69_need(($audit['operation']??null)===V69_AUDIT_OP&&($audit['state']??null)==='completed_read_only_retained29_current'&&($audit['source_sha']??null)===V69_AUDIT_HEAD,'audit_state');
    v69_need(($receipt['operation']??null)===V69_AUDIT_OP&&($receipt['result_sha256']??null)===V69_AUDIT_SHA&&($receipt['readback_verified']??null)===true&&($receipt['no_replay']??null)===true,'audit_receipt');
    foreach([$audit,$receipt] as $a)foreach(['database_writes','mapping_writes','provider_http_calls'] as $k)v69_need(($a[$k]??null)===0,'audit_not_zero');
    v69_need(($audit['selected_pairs']??null)===29&&($audit['input_dossiers']??null)===175&&($audit['candidate_pairs_examined']??null)===161&&($audit['safe_to_write_now']??null)===false,'audit_scope');
    $dossiers=[];foreach($source['dossiers'] as $d){$id=(int)$d['local_hotel_id'];v69_need(!isset($dossiers[$id]),'duplicate_dossier');$dossiers[$id]=$d;}
    $ready=[];$hold=[];$seen=[];
    foreach($audit['rows'] as $row){
        $id=(int)$row['local_hotel_id'];v69_need(!isset($seen[$id]),'duplicate_audit_row');$seen[$id]=true;
        if($row['status']==='hold'){$hold[$id]=(string)$row['andromeda_catalog_id'];continue;}
        v69_need($row['status']==='ready_for_guarded_writer'&&$row['reasons']===[]&&($row['safe_to_write_now']??null)===false,'ready_state');
        v69_need(isset(V69_PAIRS[$id])&&V69_PAIRS[$id]===(string)$row['andromeda_catalog_id'],'ready_allowlist');
        $d=$dossiers[$id]??null;v69_need(is_array($d)&&hash('sha256',v69_json($d))===$row['v65_dossier_sha256'],'dossier_binding');
        $matches=array_values(array_filter($d['candidates'],fn($c)=>(string)$c['catalog_id']===V69_PAIRS[$id]));
        v69_need(count($matches)===1&&hash('sha256',v69_json($matches[0]))===$row['v65_candidate_sha256'],'candidate_binding');
        $ready[$id]=['audit_row'=>$row,'dossier'=>$d,'candidate'=>$matches[0]];
    }
    ksort($ready,SORT_NUMERIC);ksort($hold,SORT_NUMERIC);
    v69_need(array_keys($ready)===array_keys(V69_PAIRS)&&count($hold)===15&&count($seen)===29,'exact_ready_set');
    return ['ready'=>$ready,'hold'=>$hold,'source_raw'=>$sourceRaw,'plan_raw'=>$planRaw,'execution_authorized'=>false,'safe_to_write_now'=>false];
}

/** Reuse the checked classifier; all29 collision indexes remain in scope, only14 may pass to SQL. */
function v69_assess(array $bundle,array $current):array {
    require_once __DIR__.'/hotel_match_live234_v65_current_package_v67.php';
    v67_package_check();$input=v66_prepare_bulk($bundle['source_raw'],$bundle['plan_raw']);$rows=[];
    v69_need(array_keys($bundle['ready'])===array_keys(V69_PAIRS),'bundle_ready_set');
    foreach(V69_PAIRS as $id=>$catalog){
        $entry=$input['selected'][$id]??null;v69_need(is_array($entry)&&(string)$entry['candidate']['catalog_id']===$catalog,'current_source_binding');
        v69_need(hash('sha256',v69_json($bundle['ready'][$id]['candidate']))===$entry['candidate_sha256']&&hash('sha256',v69_json($bundle['ready'][$id]['dossier']))===$entry['dossier_sha256'],'bundle_evidence_drift');
        $row=v66_classify($id,$entry,$input,$current,true);
        v69_need($row['status']==='ready_for_guarded_writer'&&$row['reasons']===[],'current_guard_'.$id);
        v69_need(empty($current['source'][$catalog]??[])&&empty($current['target'][$id]??[]),'insert_only_'.$id);
        $rows[$id]=$row;
    }
    return $rows;
}
function v69_query(PDO $db,string $sql,array $args=[]):array {$st=$db->prepare($sql);v69_need($st!==false&&$st->execute(array_values($args)),'query');$rows=$st->fetchAll(PDO::FETCH_ASSOC);v69_need(count($rows)<=100000,'row_cap');return $rows;}
function v69_index(array $rows):array {$out=[];foreach($rows as $r){$k=$r['supplier_namespace'].'|'.$r['external_hotel_id'];v69_need(!isset($out[$k]),'duplicate_identity');$out[$k]=$r;}return $out;}
function v69_verify(array $before,array $planned,array $after):array {
    $post=v69_index($after);v69_need(count($post)===count($before)+count($planned),'identity_count_delta');
    foreach($before as $key=>$row)v69_need(isset($post[$key])&&v69_hash($post[$key])===v69_hash($row),'preexisting_identity_changed');
    $read=[];foreach($planned as $p){$key=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$post[$key]??null;v69_need(is_array($r),'written_identity_missing');
        foreach(V69_FIELDS as $f)v69_need(isset($r[$f])&&(string)$r[$f]===(string)$p[$f],'written_identity_mismatch');
        v69_need(hash('sha256',(string)$r['evidence_json'])===$r['evidence_sha256'],'written_evidence_hash');
        $read[]=['supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>(string)$r['external_hotel_id'],'local_hotel_id'=>(int)$r['local_hotel_id'],'decision_status'=>$r['decision_status'],'catalog_sha256'=>$r['catalog_sha256'],'evidence_sha256'=>$r['evidence_sha256']];
    }return $read;
}

/** Caller already owns the exclusive operation reservation. No UPDATE, UPSERT, DELETE or supplier client. */
function v69_write(PDO $db,array $bundle,string $head,string $dir):array {
    require_once __DIR__.'/hotel_match_live234_v65_current_package_v67.php';v67_package_check();
    v69_need(preg_match('/^[0-9a-f]{40}$/D',$head)===1,'head');
    v69_need(is_dir($dir)&&!is_link($dir)&&basename($dir)===V69_OP&&!file_exists($dir.'/commit-attempt.json'),'writer_directory');
    $attempted=false;$committed=false;$rolledBack=false;$planned=[];$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    try{
        v69_need(!$db->inTransaction(),'nested_transaction');
        $tables=['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions'];
        foreach($tables as $table){$r=v69_query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);v69_need(count($r)===1&&strtoupper((string)$r[0]['ENGINE'])==='INNODB','transactional_table');}
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');v69_need($db->beginTransaction(),'begin');
        $all=v69_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001 FOR UPDATE');$before=v69_index($all);
        $ids=array_keys(V69_PAIRS);$ph=implode(',',array_fill(0,count($ids),'?'));$native=[];
        foreach($bundle['ready'] as $entry)foreach($entry['dossier']['direct_anex_ids'] as $id)$native[(int)$id]=true;
        $native=array_keys($native);sort($native,SORT_NUMERIC);v69_need($native!==[],'anex_anchor_ids');$nh=implode(',',array_fill(0,count($native),'?'));
        foreach(['anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions'] as $table)v69_query($db,"SELECT anex_hotel_id FROM $table WHERE anex_hotel_id IN ($nh) ORDER BY anex_hotel_id FOR UPDATE",$native);
        $hotels=[];foreach(v69_query($db,"SELECT id,name,country_name,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $h)$hotels[(int)$h['id']]=$h;
        $manual=[];foreach(v69_query($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        $live=[];foreach(v69_query($db,"SELECT hotel_id,last_seen_at FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=? ORDER BY hotel_id FOR UPDATE",[...$ids,gmdate('Y-m-d H:i:s',time()-30*86400)]) as $r)$live[(int)$r['hotel_id']]=true;
        $lanes=array_values(array_filter($all,fn($r)=>in_array($r['supplier_namespace'],['andromeda_catalog','operator_5','operator_115','operator_315','operator_342'],true)));
        $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];foreach($ids as $id)$anex[$id]=v66_ids(array_keys($coverage['by_local'][$id]??[]));
        $current=v66_indexes($lanes)+compact('hotels','manual','live','anex');$assessed=v69_assess($bundle,$current);
        foreach($assessed as $id=>$row){$entry=$bundle['ready'][$id];
            // Fingerprint the actual retained catalog candidate, not an invented full dictionary.
            $catalogSha=hash('sha256',v69_json($entry['candidate']));
            $evidence=['operation_id'=>V69_OP,'rule'=>'retained_exact_operator_primary_catalog_identity_v69','source_sha'=>$head,'audit_result_sha256'=>V69_AUDIT_SHA,'source_result_sha256'=>V69_SOURCE_SHA,'retained_plan_sha256'=>V69_PLAN_SHA,'catalog_sha256_semantics'=>'retained_v65_candidate_projection_not_full_dictionary','source'=>$entry['candidate'],'target'=>$hotels[$id],'current_validation'=>$row,'direct_anex_ids'=>$anex[$id],'required_exact_operator_lanes'=>1,'provider_http_calls'=>0];
            $ej=v69_json($evidence);$planned[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>V69_PAIRS[$id],'local_hotel_id'=>$id,'decision_status'=>'accepted','catalog_sha256'=>$catalogSha,'evidence_sha256'=>hash('sha256',$ej),'evidence_json'=>$ej];
        }
        v69_need(count($planned)===14,'planned_count');
        $st=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");v69_need($st!==false,'insert_prepare');
        foreach($planned as $p){v69_need($st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]),'insert');v69_need($st->rowCount()===1,'insert_count');}
        v69_verify($before,$planned,v69_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001'));
        v69_save($dir.'/pre-commit.json',['operation'=>V69_OP,'state'=>'verified_before_commit','source_sha'=>$head,'audit_result_sha256'=>V69_AUDIT_SHA,'planned_writes'=>14,'rows'=>$planned,'preexisting_identity_count'=>count($before),'preexisting_identity_sha256'=>v69_hash($before)]);
        v69_save($dir.'/commit-attempt.json',['operation'=>V69_OP,'state'=>'commit_attempt_no_replay','source_sha'=>$head,'planned_writes'=>14]);
        $attempted=true;v69_need($db->commit(),'commit_false');$committed=true;
        $read=v69_verify($before,$planned,v69_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001'));
        return ['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'database_writes'=>14,'mapping_writes'=>14,'readback_verified'=>true,'preexisting_identities_unchanged'=>true,'rows'=>$read,'hold_untouched'=>15];
    }catch(Throwable $e){
        if(!$attempted&&$db->inTransaction())try{$rolledBack=$db->rollBack();}catch(Throwable){}
        $state=$attempted?($committed?'committed_readback_unconfirmed':'commit_outcome_unknown_no_replay'):($rolledBack?'rolled_back_no_writes':'failed_before_commit');
        $reason=preg_match('/^[a-z][a-z0-9_]{0,99}$/D',$e->getMessage())===1?$e->getMessage():'writer_failed';
        return ['state'=>$state,'reason'=>$reason,'commit_attempted'=>$attempted,'commit_completed'=>$committed,'database_writes'=>$attempted?($committed?14:null):0,'mapping_writes'=>$attempted?($committed?14:null):0,'transaction_rolled_back'=>$rolledBack,'readback_verified'=>false,'rows'=>[]];
    }
}

function v69_main(array $argv):int {
    v69_need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    v69_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===V69_OP,'runtime_scope');
    $bundle=v69_prepare(v69_read((string)getenv('MATCH_AUDIT_RESULT')),v69_read((string)getenv('MATCH_AUDIT_RECEIPT')),v69_read((string)getenv('MATCH_SOURCE_RESULT')),v69_read((string)getenv('MATCH_RETAINED_PLAN')));
    $reservation=json_decode(v69_read($dir.'/reservation.json'),true,128,JSON_THROW_ON_ERROR);
    v69_need(($reservation['operation']??null)===V69_OP&&($reservation['source_sha']??null)===$head&&($reservation['audit_result_sha256']??null)===V69_AUDIT_SHA&&($reservation['state']??null)==='reserved_before_db_write'&&($reservation['scope']??null)==='retained14_insert_only','reservation');
    foreach(['execution-started.json','result.json','receipt.json','pre-commit.json','commit-attempt.json'] as $f)v69_need(!file_exists($dir.'/'.$f),'terminal_no_replay');
    v69_save($dir.'/execution-started.json',['operation'=>V69_OP,'source_sha'=>$head,'state'=>'reserved_before_db_access']);
    try{
        require_once __DIR__.'/hotel_match_live234_v65_current_package_v67.php';v67_package_check();
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');v69_need(is_file($bootstrap)&&!is_link($bootstrap),'db_bootstrap');require_once $bootstrap;v69_need(function_exists('v2_data_db'),'db_factory');
        $db=v2_data_db();v69_need($db instanceof PDO,'db_type');$result=v69_write($db,$bundle,$head,$dir);
    }catch(Throwable $e){$result=['state'=>'failed_before_writer','reason'=>'preflight_failed','commit_attempted'=>false,'commit_completed'=>false,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false,'rows'=>[]];}
    $result+=['operation'=>V69_OP,'source_sha'=>$head,'audit_result_sha256'=>V69_AUDIT_SHA,'provider_http_calls'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
    $sha=v69_save($dir.'/result.json',$result);v69_save($dir.'/receipt.json',['operation'=>V69_OP,'source_sha'=>$head,'state'=>$result['state'],'result_sha256'=>$sha,'result_file_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'database_readback_verified'=>$result['readback_verified'],'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'provider_http_calls'=>0,'no_replay'=>true]);
    echo v69_json(array_diff_key($result,['rows'=>true]))."\n";return $result['state']==='committed_readback_verified'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(v69_main($argv));
