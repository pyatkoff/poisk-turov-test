<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const V60_OP='hotel-match-live-samo-missing-anex-operator13-current-1971-20260926-v60';
const V60_SOURCE_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v59';
const V60_EXPECTED_SINGLE=2;

function v60_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function v60_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v60_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v60_need(is_array($v),'json_shape');return $v;}
function v60_save(string $p,array $v):string{$raw=v60_json($v)."\n";$f=@fopen($p,'x+b');v60_need($f!==false,'exclusive_create');try{v60_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))v60_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v60_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v60_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function v60_native(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,7}$/D',$s)===1?$s:null;}
function v60_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function v60_target(array $h):array{$o=[];foreach(['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active'] as $k)$o[$k]=$h[$k]??null;return $o;}
function v60_anchor_projection(array $a):array{$o=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'] as $k)$o[$k]=$a[$k]??null;return $o;}

function v60_source_edges(array $source,string $sourceSha):array{
    v60_need(($source['operation']??'')===V60_SOURCE_OP,'source_operation');
    v60_need(($source['state']??'')==='completed_read_only','source_state');
    v60_need(($source['tourvisor_account']??'')==='TOURVISOR_ANEX_JWT','source_account');
    v60_need((int)($source['selected_batch_count']??0)===88&&(int)($source['selected_hotel_count']??0)===88,'source_scope');
    v60_need((int)($source['provider_calls']??-1)===274&&(int)($source['operation_tariff_units']??-1)===88,'source_calls');
    v60_need((int)($source['returned_edge_count']??0)===5&&(int)($source['captured_single_native_count']??0)===V60_EXPECTED_SINGLE,'source_counts');
    v60_need((int)($source['continue_calls']??-1)===0&&(int)($source['dates_calls']??-1)===0,'source_no_extra_search');
    v60_need((int)($source['database_writes']??-1)===0&&(int)($source['mapping_writes']??-1)===0&&($source['safe_to_write_now']??null)===false,'source_write_boundary');
    $rows=[];$semantic=[];$sourceTargets=[];$targetSources=[];
    foreach(($source['edges']??[]) as $i=>$e){
        if(!is_array($e)||($e['state']??'')!=='detail_identity_verified'||($e['namespace']??'')!=='anex'||(int)($e['operator_id']??0)!==13||($e['link_state']??'')!=='captured_single_native')continue;
        $ids=$e['positive_native_candidates']??null;v60_need(is_array($ids)&&count($ids)===1,'single_native_shape');
        $native=v60_native($ids[0]??null);$tv=(int)($e['tv_hotel_id']??0);
        v60_need($native!==null&&$tv>0,'single_native_identity');
        foreach(['search_id_sha256','tour_id_sha256','operator_link_sha256'] as $f)v60_need(v60_sha($e[$f]??null),'edge_hash_'.$f);
        $key=$native.'|'.$tv;
        if(isset($semantic[$key]))continue;
        $semantic[$key]=true;$sourceTargets[$native][$tv]=true;$targetSources[$tv][$native]=true;
        $rows[]=[
            'anex_hotel_id'=>$native,'tv_hotel_id'=>$tv,'operator_id'=>13,'namespace'=>'anex','batch'=>(int)($e['batch']??0),
            'source_operation'=>V60_SOURCE_OP,'source_result_sha256'=>$sourceSha,'source_edge_index'=>$i,
            'search_id_sha256'=>(string)$e['search_id_sha256'],'tour_id_sha256'=>(string)$e['tour_id_sha256'],
            'operator_link_sha256'=>(string)$e['operator_link_sha256'],'operator_link_host'=>(string)($e['operator_link_host']??''),
            'safe_to_write_now'=>false,
        ];
    }
    v60_need(count($rows)===V60_EXPECTED_SINGLE,'single_native_count');
    usort($rows,fn($a,$b)=>[$a['tv_hotel_id'],(int)$a['anex_hotel_id']]<=>[$b['tv_hotel_id'],(int)$b['anex_hotel_id']]);
    return ['rows'=>$rows,'source_targets'=>$sourceTargets,'target_sources'=>$targetSources];
}

function v60_anchor_state(array $aa):array{
    if(!$aa)return ['state'=>'canonical_anchor_missing','catalog_sha256'=>null,'anchors'=>[]];
    $cats=[];$out=[];
    foreach($aa as $a){
        if(($a['supplier_namespace']??'')!=='andromeda_catalog'||($a['decision_status']??'')!=='accepted'||$a['local_hotel_id']===null)
            return ['state'=>'canonical_anchor_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $raw=(string)($a['evidence_json']??'');$eh=(string)($a['evidence_sha256']??'');$cat=(string)($a['catalog_sha256']??'');
        if(!v60_sha($eh)||!v60_sha($cat)||hash('sha256',$raw)!==$eh)
            return ['state'=>'canonical_anchor_evidence_invalid','catalog_sha256'=>null,'anchors'=>[]];
        $cats[$cat]=true;$out[]=v60_anchor_projection($a);
    }
    usort($out,fn($a,$b)=>strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    if(count($cats)!==1)return ['state'=>'canonical_anchor_catalog_conflict','catalog_sha256'=>null,'anchors'=>$out];
    return ['state'=>'canonical_anchor_ok','catalog_sha256'=>array_key_first($cats),'anchors'=>$out];
}

function v60_classify(
    array $edge,?array $hotel,array $coverage,array $mappingRows,array $decisionRows,array $exclusions,
    array $anchors,array $inputSourceTargets,array $inputTargetSources,array $staged,array $observed
):array{
    $native=(string)$edge['anex_hotel_id'];$tv=(int)$edge['tv_hotel_id'];
    $anchor=v60_anchor_state($anchors[$tv]??[]);
    $status='current_missing_exact_key';

    if(count($inputSourceTargets[$native]??[])!==1)$status='hold_input_source_collision';
    elseif(count($inputTargetSources[$tv]??[])!==1)$status='hold_input_target_collision';
    else{
        $effectiveSource=$coverage['by_native'][(int)$native]??null;
        $targetEffective=array_keys($coverage['by_local'][$tv]??[]);sort($targetEffective,SORT_NUMERIC);
        $decision=$decisionRows[$native]??null;
        $mapping=$mappingRows[$native]??null;
        if($effectiveSource!==null){
            $status=(int)$effectiveSource===$tv?'already_resolved_same':'hold_source_occupied_effective';
        }elseif($decision!==null){
            // Any manual decision supersedes automated policy. If it is not an
            // effective same-target acceptance above, it remains protected.
            $status='hold_manual_source_protected';
        }elseif(isset($exclusions[$native][$tv])){
            $status='hold_pair_excluded';
        }elseif($mapping!==null){
            // Existing non-effective/disabled/non-approved row must not be
            // silently duplicated or overwritten by an insert-only writer.
            $status='hold_mapping_row_present_non_effective';
        }elseif(!$hotel||(int)($hotel['is_active']??0)!==1){
            $status='hold_target_missing_or_inactive';
        }elseif(v60_excluded((string)($hotel['country_name']??''))){
            $status='hold_excluded_country';
        }elseif(array_filter($targetEffective,fn($id)=>(string)$id!==$native)){
            $status='hold_target_effective_anex_other';
        }elseif($anchor['state']!=='canonical_anchor_ok'){
            $status='hold_'.$anchor['state'];
        }
    }
    $ready=$status==='current_missing_exact_key'&&$anchor['state']==='canonical_anchor_ok';
    $targetEffective=array_keys($coverage['by_local'][$tv]??[]);sort($targetEffective,SORT_NUMERIC);
    return $edge+[
        'status'=>$status,'writer_ready'=>$ready,'catalog_hotel'=>$hotel,'anchor_state'=>$anchor['state'],
        'unanimous_catalog_sha256'=>$anchor['catalog_sha256'],'anchors'=>$anchor['anchors'],
        'current_effective_source_target'=>$coverage['by_native'][(int)$native]??null,
        'current_effective_target_native_ids'=>array_map('intval',$targetEffective),
        'staged_anex_hotel_present'=>isset($staged[$native]),'search_observation_present'=>isset($observed[$native]),
        'safe_to_write_now'=>false,
    ];
}

function v60_execute(PDO $db,array $input,string $sourceSha,string $sourceResultSha):array{
    $ids=array_values(array_unique(array_map(fn($r)=>(int)$r['tv_hotel_id'],$input['rows'])));sort($ids,SORT_NUMERIC);
    v60_need(count($ids)>0,'target_ids');$ph=implode(',',array_fill(0,count($ids),'?'));
    $nativeIds=array_values(array_unique(array_map(fn($r)=>(string)$r['anex_hotel_id'],$input['rows'])));sort($nativeIds,SORT_NATURAL);

    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $catalog=[];foreach(v60_query($db,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$catalog[(int)$r['id']]=$r;
        $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);

        $mappingRows=[];foreach(v60_query($db,"SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id") as $r){
            $n=(string)$r['anex_hotel_id'];if(in_array($n,$nativeIds,true))$mappingRows[$n]=$r;
        }
        $decisionRows=[];foreach(v60_query($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id") as $r){
            $n=(string)$r['anex_hotel_id'];if(in_array($n,$nativeIds,true))$decisionRows[$n]=$r;
        }
        $exclusions=[];try{foreach(v60_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id") as $r){
            $n=(string)$r['anex_hotel_id'];if(in_array($n,$nativeIds,true))$exclusions[$n][(int)$r['catalog_hotel_id']]=true;
        }}catch(Throwable){}

        $anchors=[];foreach(v60_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id") as $r){
            $l=(int)$r['local_hotel_id'];if(in_array($l,$ids,true))$anchors[$l][]=$r;
        }
        $staged=[];try{foreach(v60_query($db,"SELECT anex_hotel_id FROM anex_hotels ORDER BY anex_hotel_id") as $r)$staged[(string)$r['anex_hotel_id']]=true;}catch(Throwable){}
        $observed=[];try{foreach(v60_query($db,"SELECT anex_hotel_id FROM anex_search_hotel_observations ORDER BY anex_hotel_id") as $r)$observed[(string)$r['anex_hotel_id']]=true;}catch(Throwable){}

        $rows=[];$status=[];$anchorCounts=[];$writerByTarget=[];$stagedCount=0;$obsCount=0;
        foreach($input['rows'] as $edge){
            $r=v60_classify($edge,$catalog[(int)$edge['tv_hotel_id']]??null,$coverage,$mappingRows,$decisionRows,$exclusions,$anchors,$input['source_targets'],$input['target_sources'],$staged,$observed);
            $rows[]=$r;$status[$r['status']]=($status[$r['status']]??0)+1;$anchorCounts[$r['anchor_state']]=($anchorCounts[$r['anchor_state']]??0)+1;
            if($r['writer_ready'])$writerByTarget[(int)$r['tv_hotel_id']]=(string)$r['anex_hotel_id'];
            if($r['staged_anex_hotel_present'])$stagedCount++;if($r['search_observation_present'])$obsCount++;
        }
        ksort($status);ksort($anchorCounts);ksort($writerByTarget,SORT_NUMERIC);$db->rollBack();
        return [
            'operation'=>V60_OP,'state'=>'completed_read_only_v59_current_audit','generated_at_utc'=>gmdate('c'),
            'source_sha'=>$sourceSha,'source_operation'=>V60_SOURCE_OP,'source_result_sha256'=>$sourceResultSha,
            'input_count'=>count($rows),'unique_native_ids'=>count($nativeIds),'unique_targets'=>count($ids),
            'status_counts'=>$status,'anchor_state_counts'=>$anchorCounts,'writer_ready_count'=>count($writerByTarget),
            'writer_ready_target_digest'=>hash('sha256',implode("\n",array_map(fn($tv,$native)=>$tv.'|'.$native,array_keys($writerByTarget),array_values($writerByTarget)))."\n"),
            'staged_anex_hotel_present_count'=>$stagedCount,'search_observation_present_count'=>$obsCount,
            'current_effective_anex_native_count'=>(int)$coverage['native_count'],'current_effective_anex_local_count'=>(int)$coverage['local_count'],
            'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
            'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function v60_self_test():void{
    $raw='{"ok":true}';$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'99','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
    $e=['anex_hotel_id'=>'5844','tv_hotel_id'=>100,'operator_id'=>13,'namespace'=>'anex','safe_to_write_now'=>false];
    $hotel=['id'=>100,'is_active'=>1,'country_name'=>'Турция'];
    $cov=['by_native'=>[],'by_local'=>[]];
    $r=v60_classify($e,$hotel,$cov,[],[],[],[100=>[$a]],['5844'=>[100=>true]],[100=>['5844'=>true]],[],[]);
    v60_need($r['status']==='current_missing_exact_key'&&$r['writer_ready']===true,'self_ready');
    $cov=['by_native'=>[5844=>101],'by_local'=>[101=>[5844=>true]]];
    $r=v60_classify($e,$hotel,$cov,[],[],[],[100=>[$a]],['5844'=>[100=>true]],[100=>['5844'=>true]],[],[]);
    v60_need($r['status']==='hold_source_occupied_effective'&&!$r['writer_ready'],'self_source');
    $cov=['by_native'=>[999=>100],'by_local'=>[100=>[999=>true]]];
    $r=v60_classify($e,$hotel,$cov,[],[],[],[100=>[$a]],['5844'=>[100=>true]],[100=>['5844'=>true]],[],[]);
    v60_need($r['status']==='hold_target_effective_anex_other'&&!$r['writer_ready'],'self_target');
    $bad=$a;$bad['evidence_sha256']=str_repeat('f',64);
    $r=v60_classify($e,$hotel,['by_native'=>[],'by_local'=>[]],[],[],[],[100=>[$bad]],['5844'=>[100=>true]],[100=>['5844'=>true]],[],[]);
    v60_need($r['anchor_state']==='canonical_anchor_evidence_invalid'&&!$r['writer_ready'],'self_anchor');
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(in_array('--self-test',$argv??[],true)){v60_self_test();echo "MATCH_V60_SELFTEST_OK\n";exit;}
    v60_need(($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$src=(string)getenv('MATCH_SOURCE_RESULT');$srcSha=(string)getenv('MATCH_SOURCE_RESULT_SHA');$sha=(string)getenv('MATCH_SOURCE_SHA');
    v60_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V60_OP&&is_file($src)&&v60_sha($srcSha)&&hash_file('sha256',$src)===$srcSha&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=v60_load($dir.'/reservation.json');v60_need(($reservation['operation']??'')===V60_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    try{
        $source=v60_load($src);$input=v60_source_edges($source,$srcSha);
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $result=v60_execute(v2_data_db(),$input,$sha,$srcSha);$h=v60_save($dir.'/result.json',$result);
        v60_save($dir.'/receipt.json',['operation'=>V60_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo v60_json(['state'=>$result['state'],'input_count'=>$result['input_count'],'status_counts'=>$result['status_counts'],'anchor_state_counts'=>$result['anchor_state_counts'],'writer_ready_count'=>$result['writer_ready_count'],'writer_ready_target_digest'=>$result['writer_ready_target_digest'],'staged_present'=>$result['staged_anex_hotel_present_count'],'observed_present'=>$result['search_observation_present_count']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>V60_OP,'state'=>'failed_read_only_v59_current_audit','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=v60_save($dir.'/result.json',$f);v60_save($dir.'/receipt.json',['operation'=>V60_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
