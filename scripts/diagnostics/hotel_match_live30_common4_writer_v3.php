<?php
declare(strict_types=1);

const HMC4W_OP='hotel-match-live30-common4-writer-1971-20260923-v3';
const HMC4W_AUDIT_OP='hotel-match-live30-common4-current-1971-20260923-v2';
const HMC4W_AUDIT_SHA='69bbd1ab7673f0d5ecd2759604c6a6f7e1182a167ba21f78c3f7aeefe05a069f';
const HMC4W_EXPECTED_ROWS=172;
const HMC4W_ALLOWED_NS=['bgoperator'=>18,'operator_315'=>25,'operator_342'=>43];

function hmc4w_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmc4w_sort(mixed $v):mixed{
    if(!is_array($v))return $v;
    if(array_is_list($v))return array_map('hmc4w_sort',$v);
    ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hmc4w_sort($x);return $v;
}
function hmc4w_json(mixed $v):string{return json_encode(hmc4w_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc4w_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hmc4w_need(is_array($v),'json_shape');return $v;}
function hmc4w_save(string $p,array $v):string{
    $raw=hmc4w_json($v)."\n";$f=@fopen($p,'x+b');hmc4w_need($f!==false,'exclusive_create');
    try{hmc4w_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc4w_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmc4w_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmc4w_key(array $r):string{return (string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id'];}
function hmc4w_row_hash(array $r):string{return hash('sha256',hmc4w_json($r));}
function hmc4w_excluded_country(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmc4w_target_facts(array $h):array{
    $numeric=['id'=>true,'country_id'=>true,'region_id'=>true,'subregion_id'=>true,'is_active'=>true];
    $keys=['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'];$out=[];
    foreach($keys as $k){$v=$h[$k]??null;$out[$k]=isset($numeric[$k])&&$v!==null?(string)$v:($v===null?null:(string)$v);}return $out;
}
function hmc4w_anchor_projection(array $a):array{
    $out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;
    foreach(['local_hotel_id'] as $k)if($out[$k]!==null)$out[$k]=(string)$out[$k];
    return $out;
}
function hmc4w_anchor_list(array $rows):array{
    $out=array_map('hmc4w_anchor_projection',$rows);
    usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    return $out;
}
function hmc4w_manifest(array $audit):array{
    hmc4w_need(($audit['operation']??'')===HMC4W_AUDIT_OP,'audit_operation');
    hmc4w_need(($audit['state']??'')==='completed_read_only_current_audit','audit_state');
    hmc4w_need(($audit['writer_ready_count']??null)===HMC4W_EXPECTED_ROWS,'audit_ready_count');
    hmc4w_need(($audit['input_single_native_count']??null)===283,'audit_input_count');
    hmc4w_need(($audit['input_namespace_counts']??null)===['bgoperator'=>205,'operator_315'=>50,'operator_342'=>28],'audit_namespace_counts');
    $out=[];$keys=[];$targets=[];
    foreach(($audit['rows']??[]) as $r){
        if(!is_array($r)||($r['writer_ready']??false)!==true)continue;
        hmc4w_need(($r['status']??'')==='current_missing_edge'&&($r['anchor_state']??'')==='canonical_anchor_ok','manifest_status');
        hmc4w_need(($r['safe_to_write_now']??null)===false,'manifest_not_authority');
        $ns=(string)($r['supplier_namespace']??'');$ext=(string)($r['external_hotel_id']??'');$tv=(int)($r['tv_hotel_id']??0);
        hmc4w_need(isset(HMC4W_ALLOWED_NS[$ns])&&HMC4W_ALLOWED_NS[$ns]===(int)($r['operator_id']??0),'manifest_namespace');
        hmc4w_need(preg_match('/^[1-9][0-9]{0,19}$/D',$ext)===1&&$tv>0,'manifest_ids');
        $key=$ns.'|'.$ext;$target=$ns.'|'.$tv;hmc4w_need(!isset($keys[$key])&&!isset($targets[$target]),'manifest_uniqueness');$keys[$key]=true;$targets[$target]=true;
        $hotel=$r['catalog_hotel']??null;$anchors=$r['anchors']??null;$catalog=(string)($r['unanimous_catalog_sha256']??'');
        hmc4w_need(is_array($hotel)&&(int)($hotel['id']??0)===$tv&&is_array($anchors)&&count($anchors)>=1,'manifest_target_anchor');
        hmc4w_need(preg_match('/^[0-9a-f]{64}$/D',$catalog)===1,'manifest_catalog_hash');
        foreach($anchors as $a){
            hmc4w_need(is_array($a)&&($a['supplier_namespace']??'')==='andromeda_catalog'&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===$tv,'manifest_anchor');
            hmc4w_need(($a['catalog_sha256']??'')===$catalog&&preg_match('/^[0-9a-f]{64}$/D',(string)($a['evidence_sha256']??''))===1,'manifest_anchor_hash');
        }
        foreach(['source_result_sha256','search_id_sha256','tour_id_sha256','operator_link_sha256'] as $f)hmc4w_need(preg_match('/^[0-9a-f]{64}$/D',(string)($r[$f]??''))===1,'manifest_'.$f);
        $out[]=[
            'supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,'operator_id'=>(int)$r['operator_id'],
            'source_operation'=>(string)$r['source_operation'],'source_result_sha256'=>(string)$r['source_result_sha256'],
            'batch'=>(int)($r['batch']??0),'search_id_sha256'=>(string)$r['search_id_sha256'],'tour_id_sha256'=>(string)$r['tour_id_sha256'],
            'operator_link_sha256'=>(string)$r['operator_link_sha256'],'target'=>$hotel,
            'unanimous_catalog_sha256'=>$catalog,'anchors'=>hmc4w_anchor_list($anchors),
        ];
    }
    hmc4w_need(count($out)===HMC4W_EXPECTED_ROWS,'manifest_count');
    usort($out,fn($a,$b)=>strcmp($a['supplier_namespace'].'|'.$a['external_hotel_id'],$b['supplier_namespace'].'|'.$b['external_hotel_id']));
    return $out;
}
function hmc4w_identity_indexes(array $rows):array{
    $byKey=[];$byTargetNs=[];$anchors=[];
    foreach($rows as $r){
        $k=hmc4w_key($r);hmc4w_need(!isset($byKey[$k]),'duplicate_current_source_key');$byKey[$k]=$r;
        if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){
            $byTargetNs[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;
            if($r['supplier_namespace']==='andromeda_catalog')$anchors[(int)$r['local_hotel_id']][]=$r;
        }
    }
    return [$byKey,$byTargetNs,$anchors];
}
function hmc4w_validate_anchors(array $manifest,array $current):array{
    hmc4w_need(count($current)>=1,'canonical_anchor_missing');
    $catalog=[];
    foreach($current as $a){
        hmc4w_need(($a['supplier_namespace']??'')==='andromeda_catalog'&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===(int)$manifest['tv_hotel_id'],'canonical_anchor_invalid');
        $cat=(string)($a['catalog_sha256']??'');$eh=(string)($a['evidence_sha256']??'');$ej=(string)($a['evidence_json']??'');
        hmc4w_need(preg_match('/^[0-9a-f]{64}$/D',$cat)===1&&preg_match('/^[0-9a-f]{64}$/D',$eh)===1&&hash('sha256',$ej)===$eh,'anchor_evidence_hash_drift');
        $catalog[$cat]=true;
    }
    hmc4w_need(count($catalog)===1&&array_key_first($catalog)===$manifest['unanimous_catalog_sha256'],'anchor_catalog_drift');
    hmc4w_need(hmc4w_anchor_list($current)===$manifest['anchors'],'anchor_projection_drift');
    return $current;
}
function hmc4w_validate_row(array $m,array $catalog,array $byKey,array $byTargetNs,array $anchors,array $manual):array{
    $ns=$m['supplier_namespace'];$ext=$m['external_hotel_id'];$tv=(int)$m['tv_hotel_id'];$key=$ns.'|'.$ext;
    hmc4w_need(!isset($byKey[$key]),'source_key_now_present');
    $h=$catalog[$tv]??null;hmc4w_need(is_array($h)&&(int)($h['is_active']??0)===1,'target_missing_or_inactive');
    hmc4w_need(!hmc4w_excluded_country((string)($h['country_name']??'')),'excluded_country');
    hmc4w_need(hmc4w_target_facts($h)===hmc4w_target_facts($m['target']),'target_facts_drift');
    hmc4w_need(!isset($manual[$tv]),'manual_target_protected');
    hmc4w_need(empty($byTargetNs[$ns.'|'.$tv]??[]),'target_namespace_occupied');
    $aa=hmc4w_validate_anchors($m,$anchors[$tv]??[]);
    return ['target'=>$h,'anchors'=>$aa];
}
function hmc4w_evidence(array $m,array $checked,string $sourceSha):array{
    return [
        'operation_id'=>HMC4W_OP,'rule'=>'common4_single_native_plus_current_unanimous_andromeda_catalog_anchor',
        'source_sha'=>$sourceSha,'audit_result_sha256'=>HMC4W_AUDIT_SHA,
        'supplier_namespace'=>$m['supplier_namespace'],'external_hotel_id'=>$m['external_hotel_id'],'tv_hotel_id'=>$m['tv_hotel_id'],
        'saved_operator_edge'=>[
            'operator_id'=>$m['operator_id'],'source_operation'=>$m['source_operation'],'source_result_sha256'=>$m['source_result_sha256'],
            'batch'=>$m['batch'],'search_id_sha256'=>$m['search_id_sha256'],'tour_id_sha256'=>$m['tour_id_sha256'],
            'operator_link_sha256'=>$m['operator_link_sha256'],
        ],
        'target'=>hmc4w_target_facts($checked['target']),'canonical_anchors'=>hmc4w_anchor_list($checked['anchors']),
        'raw_operator_url_exported'=>false,'supplier_calls'=>0,
    ];
}
function hmc4w_write(PDO $db,array $manifest,string $sourceSha,?string $opDir=null):array{
    hmc4w_need(count($manifest)===HMC4W_EXPECTED_ROWS,'writer_manifest_count');
    $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$manifest)));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $commitAttempted=false;$committed=false;$planned=[];
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    try{
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
        $before=hmc4w_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE');
        $beforeHashes=[];foreach($before as $r)$beforeHashes[hmc4w_key($r)]=hmc4w_row_hash($r);
        [$byKey,$byTargetNs,$anchors]=hmc4w_identity_indexes($before);
        $catalog=[];foreach(hmc4w_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $manual=[];foreach(hmc4w_query($db,"SELECT * FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']][]=$r;

        foreach($manifest as $m){
            $checked=hmc4w_validate_row($m,$catalog,$byKey,$byTargetNs,$anchors,$manual);
            $e=hmc4w_evidence($m,$checked,$sourceSha);$ej=hmc4w_json($e);$eh=hash('sha256',$ej);
            $anchorHashes=[];foreach($checked['anchors'] as $a)$anchorHashes[hmc4w_key($a)]=hmc4w_row_hash($a);
            $planned[]=['supplier_namespace'=>$m['supplier_namespace'],'external_hotel_id'=>$m['external_hotel_id'],'local_hotel_id'=>$m['tv_hotel_id'],
                'decision_status'=>'accepted','catalog_sha256'=>$m['unanimous_catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej,'anchor_hashes'=>$anchorHashes];
        }
        hmc4w_need(count($planned)===HMC4W_EXPECTED_ROWS,'planned_count');
        $st=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach($planned as $p){$st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);hmc4w_need($st->rowCount()===1,'insert_count');}

        $after=hmc4w_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');
        hmc4w_need(count($after)===count($before)+HMC4W_EXPECTED_ROWS,'identity_count_delta');$afterBy=[];
        foreach($after as $r)$afterBy[hmc4w_key($r)]=$r;
        foreach($beforeHashes as $k=>$h)hmc4w_need(isset($afterBy[$k])&&hmc4w_row_hash($afterBy[$k])===$h,'preexisting_identity_changed');
        foreach($planned as $p){
            $k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$afterBy[$k]??null;hmc4w_need(is_array($r),'staged_insert_missing');
            foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hmc4w_need((string)$r[$f]===(string)$p[$f],'staged_insert_mismatch');
            foreach($p['anchor_hashes'] as $ak=>$ah)hmc4w_need(isset($afterBy[$ak])&&hmc4w_row_hash($afterBy[$ak])===$ah,'anchor_changed_before_commit');
        }
        if($opDir!==null){
            hmc4w_save($opDir.'/pre-commit.json',['operation'=>HMC4W_OP,'state'=>'verified_before_commit','planned_writes'=>HMC4W_EXPECTED_ROWS,'preexisting_rows'=>count($before),'preexisting_rows_hash'=>hash('sha256',hmc4w_json($beforeHashes))]);
            hmc4w_save($opDir.'/commit-attempt.json',['operation'=>HMC4W_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>HMC4W_EXPECTED_ROWS]);
        }
        $commitAttempted=true;hmc4w_need($db->commit(),'commit_false');$committed=true;

        $post=hmc4w_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');$postBy=[];foreach($post as $r)$postBy[hmc4w_key($r)]=$r;
        $byNs=[];
        foreach($planned as $p){
            $k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$postBy[$k]??null;hmc4w_need(is_array($r),'post_commit_missing');
            foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hmc4w_need((string)$r[$f]===(string)$p[$f],'post_commit_mismatch');
            $byNs[$p['supplier_namespace']]=($byNs[$p['supplier_namespace']]??0)+1;
        }
        ksort($byNs);
        return ['state'=>'committed_verified','database_writes'=>HMC4W_EXPECTED_ROWS,'mapping_writes'=>HMC4W_EXPECTED_ROWS,'inserted'=>HMC4W_EXPECTED_ROWS,
            'inserted_by_namespace'=>$byNs,'unique_targets'=>count($ids),'readback_verified'=>true,'preexisting_rows_preserved'=>count($before),'provider_calls'=>0,'supplier_calls'=>0];
    }catch(Throwable $e){
        try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}
        $state=$committed?'post_commit_verification_failed_no_replay':($commitAttempted?'commit_unknown_no_replay':'rolled_back_no_write');
        throw new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,90,'UTF-8')));
    }
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){echo "MATCH_LIVE30_COMMON4_WRITER_V3_SELFTEST_OK\n";exit;}
    hmc4w_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_AUDIT_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmc4w_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HMC4W_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    hmc4w_need(hash_file('sha256',$input)===HMC4W_AUDIT_SHA,'audit_result_hash');$manifest=hmc4w_manifest(hmc4w_load($input));
    $res=hmc4w_load($dir.'/reservation.json');hmc4w_need(($res['operation']??'')===HMC4W_OP&&($res['state']??'')==='reserved_before_write','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmc4w_write(v2_data_db(),$manifest,$sha,$dir);$h=hmc4w_save($dir.'/result.json',['operation'=>HMC4W_OP]+$result);
        hmc4w_save($dir.'/receipt.json',['operation'=>HMC4W_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>$result['readback_verified'],
            'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);
        echo hmc4w_json(['state'=>$result['state'],'inserted'=>$result['inserted'],'inserted_by_namespace'=>$result['inserted_by_namespace'],'unique_targets'=>$result['unique_targets'],'readback_verified'=>true])."\n";
    }catch(Throwable $e){
        $msg=$e->getMessage();$state=str_starts_with($msg,'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($msg,'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');
        $writes=$state==='rolled_back_no_write'?0:null;$f=['operation'=>HMC4W_OP,'state'=>$state,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($msg,0,140,'UTF-8')),
            'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$state!=='rolled_back_no_write'];
        $h=hmc4w_save($dir.'/result.json',$f);hmc4w_save($dir.'/receipt.json',['operation'=>HMC4W_OP,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,
            'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$f['no_replay']]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
