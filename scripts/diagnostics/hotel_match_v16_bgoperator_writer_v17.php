<?php
declare(strict_types=1);

const HM17_OP='hotel-match-v16-bgoperator-writer-1971-20260922-v17';
const HM17_V16_RESULT_SHA='2f8602c57c18343cd6c4388675528d55f214e35f91e43d4b71902b5207ce1b3c';
const HM17_EXPECTED_ROWS=336;

function hm17_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hm17_is_list(array $a):bool{return array_is_list($a);}
function hm17_sort(mixed $v):mixed{
    if(!is_array($v))return $v;
    if(hm17_is_list($v))return array_map('hm17_sort',$v);
    ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=hm17_sort($x);return $v;
}
function hm17_json(mixed $v):string{return json_encode(hm17_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hm17_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);hm17_need(is_array($v),'json_shape');return $v;}
function hm17_save(string $p,array $v):string{$raw=hm17_json($v)."\n";$f=@fopen($p,'x+b');hm17_need($f!==false,'exclusive_create');try{hm17_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hm17_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hm17_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hm17_key(array $r):string{return (string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id'];}
function hm17_row_hash(array $r):string{return hash('sha256',hm17_json($r));}
function hm17_excluded_country(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}

function hm17_manifest(array $v16):array{
    hm17_need(($v16['operation']??'')==='hotel-match-v9-operator-edges-current-reconcile-1971-20260922-v16','v16_operation');
    hm17_need(($v16['state']??'')==='completed_read_only_current_reconcile','v16_state');
    hm17_need(($v16['writer_ready_count']??null)===HM17_EXPECTED_ROWS,'v16_ready_count');
    $rows=[];$keys=[];$targets=[];
    foreach(($v16['rows']??[]) as $r){
        if(!is_array($r)||($r['writer_ready']??false)!==true)continue;
        hm17_need(($r['status']??'')==='current_missing_edge','manifest_status');
        hm17_need(($r['supplier_namespace']??'')==='bgoperator','manifest_namespace');
        hm17_need(($r['safe_to_write_now']??null)===false,'manifest_not_authority');
        $ext=(string)($r['external_hotel_id']??'');$tv=(int)($r['tv_hotel_id']??0);
        hm17_need(preg_match('/^[1-9][0-9]{0,19}$/D',$ext)===1&&$tv>0,'manifest_ids');
        $k='bgoperator|'.$ext;hm17_need(!isset($keys[$k])&&!isset($targets[$tv]),'manifest_uniqueness');
        $keys[$k]=true;$targets[$tv]=true;
        $hotel=$r['catalog_hotel']??null;hm17_need(is_array($hotel)&&(int)($hotel['id']??0)===$tv,'manifest_target');
        $rows[]=[
            'supplier_namespace'=>'bgoperator','external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,
            'operator'=>(string)($r['operator']??'biblio'),'operator_id'=>(int)($r['operator_id']??18),
            'batch'=>(int)($r['batch']??0),'search_id'=>(string)($r['search_id']??''),'tour_id'=>(string)($r['tour_id']??''),
            'operator_link_sha256'=>(string)($r['operator_link_sha256']??''),'target'=>$hotel,
        ];
    }
    hm17_need(count($rows)===HM17_EXPECTED_ROWS&&count($keys)===HM17_EXPECTED_ROWS&&count($targets)===HM17_EXPECTED_ROWS,'manifest_count');
    usort($rows,fn($a,$b)=>strcmp($a['external_hotel_id'],$b['external_hotel_id']));
    return $rows;
}
function hm17_target_facts(array $h):array{
    $keys=['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'];
    $out=[];foreach($keys as $k)$out[$k]=$h[$k]??null;return $out;
}
function hm17_anchor_projection(array $a):array{
    $out=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$out[$k]=$a[$k]??null;return $out;
}
function hm17_validate_row(array $m,array $catalog,array $byKey,array $byTargetNs,array $anchors,array $manual):array{
    $tv=(int)$m['tv_hotel_id'];$ext=(string)$m['external_hotel_id'];$key='bgoperator|'.$ext;
    hm17_need(!isset($byKey[$key]),'source_key_now_present');
    $h=$catalog[$tv]??null;hm17_need(is_array($h)&&(int)($h['is_active']??0)===1,'target_missing_or_inactive');
    hm17_need(!hm17_excluded_country((string)($h['country_name']??'')),'excluded_country');
    hm17_need(hm17_target_facts($h)===hm17_target_facts($m['target']),'target_facts_drift');
    hm17_need(!isset($manual[$tv]),'manual_target_protected');
    hm17_need(empty($byTargetNs['bgoperator|'.$tv]??[]),'target_namespace_occupied');
    $a=$anchors[$tv]??[];hm17_need(count($a)===1,'canonical_anchor_not_unique');
    $anchor=$a[0];hm17_need(($anchor['decision_status']??'')==='accepted'&&(int)($anchor['local_hotel_id']??0)===$tv,'canonical_anchor_invalid');
    hm17_need(preg_match('/^[0-9a-f]{64}$/D',(string)($anchor['catalog_sha256']??''))===1,'canonical_catalog_hash');
    hm17_need(preg_match('/^[0-9a-f]{64}$/D',(string)($anchor['evidence_sha256']??''))===1,'canonical_evidence_hash');
    return ['target'=>$h,'anchor'=>$anchor];
}
function hm17_evidence(array $m,array $checked,string $sourceSha):array{
    return [
        'operation_id'=>HM17_OP,
        'rule'=>'saved_biblio_f4_direct_operator_link_plus_unique_current_andromeda_catalog_anchor',
        'source_sha'=>$sourceSha,
        'source_v16'=>[
            'run_id'=>35781360057,
            'artifact_id'=>10717868335,
            'artifact_digest'=>'sha256:c557146b0bef7d6b9a26b0984d36e2d3ce500a6ff16cd200b699451f84a30dd9',
            'result_sha256'=>HM17_V16_RESULT_SHA,
        ],
        'supplier_namespace'=>'bgoperator',
        'external_hotel_id'=>$m['external_hotel_id'],
        'tv_hotel_id'=>$m['tv_hotel_id'],
        'saved_operator_edge'=>[
            'operator'=>$m['operator'],'operator_id'=>$m['operator_id'],'batch'=>$m['batch'],
            'search_id'=>$m['search_id'],'tour_id'=>$m['tour_id'],'operator_link_sha256'=>$m['operator_link_sha256'],
        ],
        'target'=>hm17_target_facts($checked['target']),
        'canonical_anchor'=>hm17_anchor_projection($checked['anchor']),
        'raw_operator_url_exported'=>false,
        'supplier_calls'=>0,
    ];
}
function hm17_identity_indexes(array $rows):array{
    $byKey=[];$byTargetNs=[];$anchors=[];
    foreach($rows as $r){
        $k=(string)$r['supplier_namespace'].'|'.(string)$r['external_hotel_id'];hm17_need(!isset($byKey[$k]),'duplicate_current_source_key');$byKey[$k]=$r;
        if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){
            $byTargetNs[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;
            if($r['supplier_namespace']==='andromeda_catalog')$anchors[(int)$r['local_hotel_id']][]=$r;
        }
    }
    return [$byKey,$byTargetNs,$anchors];
}
function hm17_write(PDO $db,array $manifest,string $sourceSha,?string $opDir=null):array{
    hm17_need(count($manifest)===HM17_EXPECTED_ROWS,'writer_manifest_count');
    $ids=array_values(array_map(fn($r)=>(int)$r['tv_hotel_id'],$manifest));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $commitAttempted=false;$committed=false;$planned=[];
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    try{
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');
        $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();

        $before=hm17_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE');
        $beforeHashes=[];foreach($before as $r)$beforeHashes[hm17_key($r)]=hm17_row_hash($r);
        [$byKey,$byTargetNs,$anchors]=hm17_identity_indexes($before);

        $catalog=[];foreach(hm17_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $manual=[];foreach(hm17_query($db,"SELECT * FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']][]=$r;

        foreach($manifest as $m){
            $checked=hm17_validate_row($m,$catalog,$byKey,$byTargetNs,$anchors,$manual);
            $e=hm17_evidence($m,$checked,$sourceSha);$ej=hm17_json($e);$eh=hash('sha256',$ej);
            $planned[]=[
                'supplier_namespace'=>'bgoperator','external_hotel_id'=>$m['external_hotel_id'],'local_hotel_id'=>$m['tv_hotel_id'],
                'decision_status'=>'accepted','catalog_sha256'=>(string)$checked['anchor']['catalog_sha256'],
                'evidence_sha256'=>$eh,'evidence_json'=>$ej,
                'anchor_before_hash'=>hm17_row_hash($checked['anchor']),
            ];
        }
        hm17_need(count($planned)===HM17_EXPECTED_ROWS,'planned_count');

        $st=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach($planned as $p){$st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);hm17_need($st->rowCount()===1,'insert_count');}

        $after=hm17_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');
        hm17_need(count($after)===count($before)+HM17_EXPECTED_ROWS,'identity_count_delta');
        $afterByKey=[];foreach($after as $r)$afterByKey[hm17_key($r)]=$r;
        foreach($beforeHashes as $k=>$h)hm17_need(isset($afterByKey[$k])&&hm17_row_hash($afterByKey[$k])===$h,'preexisting_identity_changed');
        foreach($planned as $p){
            $k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];hm17_need(isset($afterByKey[$k]),'staged_insert_missing');
            foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hm17_need((string)$afterByKey[$k][$f]===(string)$p[$f],'staged_insert_mismatch');
            $anchorKey='andromeda_catalog|'.$p['local_hotel_id'];hm17_need(isset($afterByKey[$anchorKey])&&hm17_row_hash($afterByKey[$anchorKey])===$p['anchor_before_hash'],'anchor_changed_before_commit');
        }
        if($opDir!==null){
            hm17_save($opDir.'/pre-commit.json',['operation'=>HM17_OP,'state'=>'verified_before_commit','planned_writes'=>HM17_EXPECTED_ROWS,'preexisting_rows'=>count($before),'preexisting_rows_hash'=>hash('sha256',hm17_json($beforeHashes))]);
            hm17_save($opDir.'/commit-attempt.json',['operation'=>HM17_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>HM17_EXPECTED_ROWS]);
        }
        $commitAttempted=true;hm17_need($db->commit(),'commit_false');$committed=true;

        $placeholders=implode(',',array_fill(0,HM17_EXPECTED_ROWS,'?'));$params=array_map(fn($p)=>$p['external_hotel_id'],$planned);
        $post=hm17_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='bgoperator' AND external_hotel_id IN ($placeholders) ORDER BY external_hotel_id",$params);
        hm17_need(count($post)===HM17_EXPECTED_ROWS,'post_commit_count');
        $postBy=[];foreach($post as $r)$postBy[(string)$r['external_hotel_id']]=$r;
        foreach($planned as $p){$r=$postBy[$p['external_hotel_id']]??null;hm17_need(is_array($r),'post_commit_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hm17_need((string)$r[$f]===(string)$p[$f],'post_commit_mismatch');}
        return ['state'=>'committed_verified','database_writes'=>HM17_EXPECTED_ROWS,'mapping_writes'=>HM17_EXPECTED_ROWS,'inserted'=>HM17_EXPECTED_ROWS,'readback_verified'=>true,'preexisting_rows_preserved'=>count($before),'provider_calls'=>0,'supplier_calls'=>0];
    }catch(Throwable $e){
        $rolledBack=false;try{if($db->inTransaction())$rolledBack=$db->rollBack();}catch(Throwable){}
        if($committed)$state='post_commit_verification_failed_no_replay';
        elseif($commitAttempted)$state='commit_unknown_no_replay';
        else $state='rolled_back_no_write';
        $x=new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,80,'UTF-8')));
        $x->hm17_state=$state; // dynamic property retained only for immediate CLI catch on current PHP runtime
        throw $x;
    }
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $v=['operation'=>'hotel-match-v9-operator-edges-current-reconcile-1971-20260922-v16','state'=>'completed_read_only_current_reconcile','writer_ready_count'=>2,'rows'=>[]];
        echo "MATCH_V16_BGOPERATOR_WRITER_V17_SELFTEST_OK\n";exit;
    }
    hm17_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_V16_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hm17_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HM17_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha),'runtime_scope');
    hm17_need(hash_file('sha256',$input)===HM17_V16_RESULT_SHA,'v16_result_hash');$manifest=hm17_manifest(hm17_load($input));
    $res=hm17_load($dir.'/reservation.json');hm17_need(($res['operation']??'')===HM17_OP&&($res['state']??'')==='reserved_before_write','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hm17_write(v2_data_db(),$manifest,$sha,$dir);$h=hm17_save($dir.'/result.json',['operation'=>HM17_OP]+$result);hm17_save($dir.'/receipt.json',['operation'=>HM17_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>$result['readback_verified'],'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);echo hm17_json(['state'=>$result['state'],'inserted'=>$result['inserted'],'readback_verified'=>$result['readback_verified']])."\n";}
    catch(Throwable $e){
        $state=str_starts_with($e->getMessage(),'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($e->getMessage(),'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');
        $writes=$state==='rolled_back_no_write'?0:null;$f=['operation'=>HM17_OP,'state'=>$state,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$state!=='rolled_back_no_write'];
        $h=hm17_save($dir.'/result.json',$f);hm17_save($dir.'/receipt.json',['operation'=>HM17_OP,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$f['no_replay']]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
