<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_v16_bgoperator_writer_v17.php';

const HM20_OP='hotel-match-v19-consistent-multi-anchor-writer-1971-20260922-v20';
const HM20_INPUT_SHA='6ab8c62a5a77494c626e1735ac73f7e9a34ed5da4415c79cd78cb12d96a30ea1';
const HM20_EXPECTED_ROWS=73;

function hm20_manifest(array $v19):array{
    hm17_need(($v19['operation']??'')==='hotel-match-v18-residual-anchor-dossier-1971-20260922-v19','v19_operation');
    hm17_need(($v19['state']??'')==='completed_read_only_anchor_dossier','v19_state');
    hm17_need(($v19['verdict_counts']['consistent_multi_anchor']??null)===73,'v19_consistent_count');
    hm17_need(($v19['verdict_counts']['zero_anchor']??null)===4,'v19_zero_count');
    $rows=[];$sources=[];$nsTargets=[];$targets=[];$nsCounts=[];
    foreach(($v19['rows']??[]) as $r){
        if(!is_array($r)||($r['verdict']??'')!=='consistent_multi_anchor')continue;
        hm17_need(($r['safe_to_write_now']??null)===false,'v19_not_authority');
        $ns=(string)($r['supplier_namespace']??'');$ext=(string)($r['external_hotel_id']??'');$tv=(int)($r['tv_hotel_id']??0);
        hm17_need(in_array($ns,['bgoperator','operator_315','operator_342'],true)&&$ext!==''&&$tv>0,'manifest_identity');
        $source=$ns.'|'.$ext;$nt=$ns.'|'.$tv;
        hm17_need(!isset($sources[$source])&&!isset($nsTargets[$nt]),'manifest_collision');
        $sources[$source]=true;$nsTargets[$nt]=true;$targets[$tv]=true;$nsCounts[$ns]=($nsCounts[$ns]??0)+1;
        $target=$r['current_target']??$r['target']??null;hm17_need(is_array($target)&&(int)($target['id']??0)===$tv,'manifest_target');
        $anchors=$r['anchors']??null;hm17_need(is_array($anchors)&&count($anchors)>1,'manifest_anchors');
        $expectedCount=(int)($r['current_anchor_count']??0);hm17_need(count($anchors)===$expectedCount&&in_array($expectedCount,[2,3],true),'manifest_anchor_count');
        $catalog=(string)($r['unanimous_catalog_sha256']??'');hm17_need(preg_match('/^[0-9a-f]{64}$/D',$catalog)===1,'manifest_catalog_hash');
        $anchorSet=[];$safeAnchors=[];
        foreach($anchors as $a){
            hm17_need(is_array($a)&&($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===$tv&&($a['evidence_hash_valid']??false)===true,'manifest_anchor_valid');
            $ae=(string)($a['external_hotel_id']??'');$ac=(string)($a['catalog_sha256']??'');$ah=(string)($a['evidence_sha256']??'');
            hm17_need($ae!==''&&$ac===$catalog&&preg_match('/^[0-9a-f]{64}$/D',$ah)===1,'manifest_anchor_hash');
            $ak=$ae.'|'.$ac.'|'.$ah;hm17_need(!isset($anchorSet[$ak]),'manifest_anchor_duplicate');$anchorSet[$ak]=true;
            $safeAnchors[]=[
                'external_hotel_id'=>$ae,'local_hotel_id'=>$tv,'decision_status'=>'accepted',
                'catalog_sha256'=>$ac,'evidence_sha256'=>$ah,'source_projection'=>$a['source_projection']??[],
            ];
        }
        usort($safeAnchors,fn($a,$b)=>strcmp($a['external_hotel_id'],$b['external_hotel_id']));
        $rows[]=[
            'supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,
            'target'=>$target,'anchor_count'=>$expectedCount,'unanimous_catalog_sha256'=>$catalog,'anchors'=>$safeAnchors,
        ];
    }
    ksort($nsCounts);
    hm17_need(count($rows)===HM20_EXPECTED_ROWS&&count($sources)===73&&count($targets)===61,'manifest_counts');
    hm17_need($nsCounts===['bgoperator'=>51,'operator_315'=>10,'operator_342'=>12],'manifest_namespace_counts');
    usort($rows,fn($a,$b)=>[$a['supplier_namespace'],$a['external_hotel_id']]<=>[$b['supplier_namespace'],$b['external_hotel_id']]);
    return $rows;
}
function hm20_expected_anchor_set(array $m):array{
    $out=[];foreach($m['anchors'] as $a)$out[(string)$a['external_hotel_id'].'|'.(string)$a['catalog_sha256'].'|'.(string)$a['evidence_sha256']]=true;
    ksort($out);return $out;
}
function hm20_current_anchor_set(array $anchors,int $tv,string $catalog):array{
    $out=[];
    foreach($anchors as $a){
        hm17_need(($a['decision_status']??'')==='accepted'&&(int)($a['local_hotel_id']??0)===$tv,'anchor_target_or_status');
        $raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$ch=(string)($a['catalog_sha256']??'');
        hm17_need(hash('sha256',$raw)===$eh,'anchor_evidence_hash_drift');
        hm17_need($ch===$catalog,'anchor_catalog_hash_drift');
        $out[(string)$a['external_hotel_id'].'|'.$ch.'|'.$eh]=true;
    }
    ksort($out);return $out;
}
function hm20_evidence(array $m,array $currentAnchors,string $sourceSha):array{
    $safe=[];
    $byExternal=[];foreach($m['anchors'] as $a)$byExternal[(string)$a['external_hotel_id']]=$a;
    foreach($currentAnchors as $a){
        $ext=(string)$a['external_hotel_id'];$expected=$byExternal[$ext]??null;hm17_need(is_array($expected),'anchor_projection_missing');
        $safe[]=[
            'external_hotel_id'=>$ext,'local_hotel_id'=>(int)$a['local_hotel_id'],'decision_status'=>(string)$a['decision_status'],
            'catalog_sha256'=>(string)$a['catalog_sha256'],'evidence_sha256'=>(string)$a['evidence_sha256'],
            'source_projection'=>$expected['source_projection']??[],
        ];
    }
    usort($safe,fn($a,$b)=>strcmp($a['external_hotel_id'],$b['external_hotel_id']));
    return [
        'operation_id'=>HM20_OP,
        'rule'=>'v19_consistent_multi_anchor_full_set_current_recheck',
        'source_sha'=>$sourceSha,
        'source_v19'=>[
            'run_id'=>35782887272,'artifact_id'=>10719055880,
            'artifact_digest'=>'sha256:b4deb029b0eee4a0efd4ff482aeeda80dfb6502b0e0a946a53ae48f18a85a8b4',
            'result_sha256'=>HM20_INPUT_SHA,
        ],
        'supplier_namespace'=>$m['supplier_namespace'],'external_hotel_id'=>$m['external_hotel_id'],'tv_hotel_id'=>$m['tv_hotel_id'],
        'target'=>hm17_target_facts($m['target']),'accepted_anchor_set'=>$safe,
        'unanimous_catalog_sha256'=>$m['unanimous_catalog_sha256'],
        'raw_provider_url_exported'=>false,'raw_anchor_evidence_exported'=>false,'supplier_calls'=>0,
    ];
}
function hm20_write(PDO $db,array $manifest,string $sourceSha,?string $opDir=null):array{
    hm17_need(count($manifest)===HM20_EXPECTED_ROWS,'writer_manifest_count');
    $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$manifest)));sort($ids,SORT_NUMERIC);
    hm17_need(count($ids)===61,'writer_target_count');$ph=implode(',',array_fill(0,count($ids),'?'));
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
            $ns=$m['supplier_namespace'];$ext=$m['external_hotel_id'];$tv=(int)$m['tv_hotel_id'];$sourceKey=$ns.'|'.$ext;
            hm17_need(!isset($byKey[$sourceKey]),'source_key_now_present');
            $h=$catalog[$tv]??null;hm17_need(is_array($h)&&(int)($h['is_active']??0)===1,'target_missing_or_inactive');
            hm17_need(!hm17_excluded_country((string)($h['country_name']??'')),'excluded_country');
            hm17_need(hm17_target_facts($h)===hm17_target_facts($m['target']),'target_facts_drift');
            hm17_need(!isset($manual[$tv]),'manual_target_protected');
            hm17_need(empty($byTargetNs[$ns.'|'.$tv]??[]),'target_namespace_occupied');
            $aa=$anchors[$tv]??[];hm17_need(count($aa)===(int)$m['anchor_count'],'anchor_count_drift');
            $expected=hm20_expected_anchor_set($m);$current=hm20_current_anchor_set($aa,$tv,(string)$m['unanimous_catalog_sha256']);
            hm17_need($current===$expected,'anchor_set_drift');
            $anchorHashes=[];foreach($aa as $a)$anchorHashes[hm17_key($a)]=hm17_row_hash($a);
            $e=hm20_evidence($m,$aa,$sourceSha);$ej=hm17_json($e);$eh=hash('sha256',$ej);
            $planned[]=[
                'supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'local_hotel_id'=>$tv,'decision_status'=>'accepted',
                'catalog_sha256'=>$m['unanimous_catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej,
                'anchor_hashes'=>$anchorHashes,
            ];
        }
        hm17_need(count($planned)===HM20_EXPECTED_ROWS,'planned_count');
        $st=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)");
        foreach($planned as $p){$st->execute([$p['supplier_namespace'],$p['external_hotel_id'],$p['local_hotel_id'],$p['catalog_sha256'],$p['evidence_sha256'],$p['evidence_json']]);hm17_need($st->rowCount()===1,'insert_count');}
        $after=hm17_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');
        hm17_need(count($after)===count($before)+HM20_EXPECTED_ROWS,'identity_count_delta');
        $afterBy=[];foreach($after as $r)$afterBy[hm17_key($r)]=$r;
        foreach($beforeHashes as $k=>$h)hm17_need(isset($afterBy[$k])&&hm17_row_hash($afterBy[$k])===$h,'preexisting_identity_changed');
        foreach($planned as $p){
            $k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$afterBy[$k]??null;hm17_need(is_array($r),'staged_insert_missing');
            foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hm17_need((string)$r[$f]===(string)$p[$f],'staged_insert_mismatch');
            foreach($p['anchor_hashes'] as $ak=>$ah)hm17_need(isset($afterBy[$ak])&&hm17_row_hash($afterBy[$ak])===$ah,'anchor_changed_before_commit');
        }
        if($opDir!==null){
            hm17_save($opDir.'/pre-commit.json',['operation'=>HM20_OP,'state'=>'verified_before_commit','planned_writes'=>HM20_EXPECTED_ROWS,'preexisting_rows'=>count($before),'preexisting_rows_hash'=>hash('sha256',hm17_json($beforeHashes))]);
            hm17_save($opDir.'/commit-attempt.json',['operation'=>HM20_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>HM20_EXPECTED_ROWS]);
        }
        $commitAttempted=true;hm17_need($db->commit(),'commit_false');$committed=true;

        $postAll=hm17_query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');
        $post=[];foreach($postAll as $r)$post[hm17_key($r)]=$r;
        foreach($planned as $p){$k=$p['supplier_namespace'].'|'.$p['external_hotel_id'];$r=$post[$k]??null;hm17_need(is_array($r),'post_commit_missing');foreach(['local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'] as $f)hm17_need((string)$r[$f]===(string)$p[$f],'post_commit_mismatch');}
        return ['state'=>'committed_verified','database_writes'=>HM20_EXPECTED_ROWS,'mapping_writes'=>HM20_EXPECTED_ROWS,'inserted'=>HM20_EXPECTED_ROWS,'readback_verified'=>true,'preexisting_rows_preserved'=>count($before),'provider_calls'=>0,'supplier_calls'=>0];
    }catch(Throwable $e){
        try{if($db->inTransaction())$db->rollBack();}catch(Throwable){}
        $state=$committed?'post_commit_verification_failed_no_replay':($commitAttempted?'commit_unknown_no_replay':'rolled_back_no_write');
        throw new RuntimeException($state.':'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,80,'UTF-8')));
    }
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){echo "MATCH_V19_MULTI_ANCHOR_WRITER_V20_SELFTEST_OK\n";exit;}
    hm17_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_V19_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hm17_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HM20_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha),'runtime_scope');
    hm17_need(hash_file('sha256',$input)===HM20_INPUT_SHA,'v19_result_hash');$manifest=hm20_manifest(hm17_load($input));
    $res=hm17_load($dir.'/reservation.json');hm17_need(($res['operation']??'')===HM20_OP&&($res['state']??'')==='reserved_before_write','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hm20_write(v2_data_db(),$manifest,$sha,$dir);$h=hm17_save($dir.'/result.json',['operation'=>HM20_OP]+$result);hm17_save($dir.'/receipt.json',['operation'=>HM20_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>$result['readback_verified'],'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'no_replay'=>true]);echo hm17_json(['state'=>$result['state'],'inserted'=>$result['inserted'],'readback_verified'=>$result['readback_verified']])."\n";}
    catch(Throwable $e){$msg=$e->getMessage();$state=str_starts_with($msg,'commit_unknown_no_replay:')?'commit_unknown_no_replay':(str_starts_with($msg,'post_commit_verification_failed_no_replay:')?'post_commit_verification_failed_no_replay':'rolled_back_no_write');$writes=$state==='rolled_back_no_write'?0:null;$f=['operation'=>HM20_OP,'state'=>$state,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($msg,0,120,'UTF-8')),'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$state!=='rolled_back_no_write'];$h=hm17_save($dir.'/result.json',$f);hm17_save($dir.'/receipt.json',['operation'=>HM20_OP,'state'=>$state,'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_calls'=>0,'database_writes'=>$writes,'mapping_writes'=>$writes,'no_replay'=>$f['no_replay']]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
