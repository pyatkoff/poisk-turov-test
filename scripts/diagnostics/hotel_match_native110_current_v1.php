<?php
declare(strict_types=1);
/** Fixed saved110 CURRENT review. No provider, acceptance policy or write entrypoint. */
require_once __DIR__.'/hotel_match_primary_proof_audit_v1.php';

const NC110_OP = 'int-andromeda-match-native-current-20261001-v1';
const NC110_BATCH = 'native110-20260928';
const NC110_MANIFEST_SHA = '53b95f676f081614bce32f39b1179fadb32ee313043101b55da8ed1156437393';
const NC110_PROTECTED = '2000086118';
const NC110_NS = ['anex'=>'operator_5','bg'=>'operator_115','funsun'=>'operator_315','intourist'=>'operator_342'];
const NC110_OPERATORS = ['anex'=>13,'bg'=>18,'funsun'=>25,'intourist'=>43];

function nc110_scope(array $manifest): array {
    w76_need(($manifest['schema']??null)==='match-native110-review/1'
        && ($manifest['batch']??null)===NC110_BATCH
        && ($manifest['protected_catalog_id']??null)===NC110_PROTECTED
        && w76_sha($manifest['source_plan_sha256']??null),'native110_manifest_header');
    $rows=$manifest['rows']??null;
    w76_need(is_array($rows)&&array_is_list($rows)&&count($rows)===110,'native110_scope_count');
    $ids=[];$targets=[];$protected=0;
    foreach($rows as $row){
        w76_need(is_array($row)&&is_string($row['catalog_id']??null)
            && preg_match('/^[1-9][0-9]{0,31}$/D',$row['catalog_id'])===1
            && !isset($ids[$row['catalog_id']]),'native110_catalog_id');
        $ids[$row['catalog_id']]=true;
        if($row['catalog_id']===NC110_PROTECTED){++$protected;continue;}
        w76_need(is_array($row['native_by_operator']??null)
            && array_diff(array_keys($row['native_by_operator']),array_keys(NC110_NS))===[],'native110_namespaces');
        foreach($row['native_by_operator'] as $values){
            w76_need(is_array($values)&&array_is_list($values)&&count($values)<=32,'native110_native_shape');
            foreach($values as $n)w76_need(is_string($n)&&preg_match('/^[1-9][0-9]{0,31}$/D',$n)===1,'native110_native_id');
        }
        foreach(['tv_candidates','local_anchor_ids'] as $key)w76_need(is_array($row[$key]??null)&&array_is_list($row[$key])&&count($row[$key])<=100,'native110_target_shape');
        foreach($row['tv_candidates'] as $c){
            w76_need(is_array($c)&&isset(NC110_NS[$c['operator']??''])
                && is_int($c['tv_hotel_id']??null)&&$c['tv_hotel_id']>0
                && is_string($c['native_id']??null)&&in_array($c['native_id'],$row['native_by_operator'][$c['operator']]??[],true)
                && is_string($c['tv_native_id']??null)&&preg_match('/^[1-9][0-9]{0,31}$/D',$c['tv_native_id'])===1,'native110_target_identity');
            $targets[$c['tv_hotel_id']]=true;
        }
        foreach($row['local_anchor_ids'] as $id){w76_need(is_int($id)&&$id>0,'native110_anchor_id');$targets[$id]=true;}
    }
    w76_need($protected===1,'native110_protection');
    unset($ids[NC110_PROTECTED]);
    return ['catalog_ids'=>array_map('strval',array_keys($ids)),'target_ids'=>array_keys($targets)];
}

function nc110_fact(array $row,string $cat,string $ns,string $native): bool {
    if(in_array($ns,['operator_315','operator_342'],true))return w78_fact($row,$cat,$ns,$native);
    if(!in_array($ns,['operator_5','operator_115'],true))return false;
    $original=$row['original']??null;$operator=substr($ns,9);
    return is_array($original)&&(string)($row['hotelKey']??'')===$cat
        && (string)($row['operatorKey']??($original['operatorKey']??''))===$operator
        && (!isset($original['operatorKey'])||(string)$original['operatorKey']===$operator)
        && (string)($original['hotelKey']??'')===$native
        && !in_array($row['isOperatorHotelKey']??false,[true,1,'1','true'],true);
}

function nc110_raw_file(string $root,string $relative): array {
    w76_need(preg_match('~^hotel-match-[a-zA-Z0-9_-]+/(?:evidence-private/)?[a-zA-Z0-9_.-]+\.json$~D',$relative)===1,'retained_relative_path');
    $base=realpath($root);$path=$root.'/'.$relative;
    w76_need($base!==false&&realpath($path)===$base.'/'.$relative&&is_file($path)&&!is_link($path)
        &&filesize($path)>0&&filesize($path)<=16777216,'retained_file');
    $bytes=file_get_contents($path);w76_need(is_string($bytes),'retained_read');
    $raw=json_decode($bytes,true,128,JSON_THROW_ON_ERROR);w76_need(is_array($raw),'retained_json');
    return ['raw'=>$raw,'sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes)];
}

/** Read each referenced raw file once. Missing/drifted rows do not stop other facts. */
function nc110_raw(string $root,array $facts): array {
    $files=[];$out=[];$read=0;$bytes=0;
    foreach($facts as $index=>$fact){
        $out[$index]=['raw_verified'=>false,'references'=>[],'failures'=>[]];
        foreach($fact['evidence']??[] as $pointer){
            $source=$pointer['source_file']??null;$sha=$pointer['sha256']??null;$ptr=$pointer['json_pointer']??null;
            if(!is_string($source)||!preg_match('~^operations/(hotel-match-[a-zA-Z0-9_-]+)/(?:evidence-private/)?[a-zA-Z0-9_.-]+\.json$~D',$source)
                ||!w76_sha($sha)||!is_string($ptr)||!preg_match('~^/(?:PRICES|prices)/[0-9]{1,8}$~D',$ptr)){
                $out[$index]['failures'][]='raw_pointer_shape';continue;
            }
            $relative=substr($source,strlen('operations/'));
            $files[$relative][]=['index'=>$index,'sha'=>$sha,'pointer'=>$ptr];
        }
    }
    ksort($files,SORT_STRING);
    foreach($files as $relative=>$requests){
        $path=$root.'/'.$relative;
        $size=is_file($path)&&!is_link($path)?filesize($path):0;$failure=null;$raw=null;$digest=null;
        try{
            w76_need($read<1000&&$bytes+$size<=536870912,'raw_inventory_cap');
            $file=nc110_raw_file($root,$relative);$raw=$file['raw'];$digest=$file['sha256'];++$read;$bytes+=$file['bytes'];unset($file);
        }catch(Throwable $e){$failure=in_array($e->getMessage(),['retained_file','retained_digest','raw_file_digest_conflict','raw_inventory_cap'],true)?$e->getMessage():'raw_file_invalid';}
        foreach($requests as $r){
            $i=$r['index'];$fact=$facts[$i];$valid=false;$why=$failure;
            if($raw!==null)try{
                if(!hash_equals($r['sha'],$digest)){$why='retained_digest';}
                else $valid=nc110_fact(w78_ptr($raw,$r['pointer']),(string)$fact['catalog_id'],(string)$fact['supplier_namespace'],(string)$fact['native_id']);
                if(!$valid&&$why===null)$why='raw_native_identity_mismatch';
            }catch(Throwable $e){$why='raw_pointer_missing';}
            $out[$i]['raw_verified']=$out[$i]['raw_verified']||$valid;
            $out[$i]['references'][]=['source_file'=>'operations/'.$relative,'sha256'=>$r['sha'],'json_pointer'=>$r['pointer'],'verified'=>$valid];
            if($why!==null)$out[$i]['failures'][]=$why;
        }
        unset($raw);
    }
    foreach($out as &$r)$r['failures']=array_values(array_unique($r['failures']));unset($r);
    return ['facts'=>$out,'files_read'=>$read,'bytes_read'=>$bytes];
}

function nc110_saved(string $root,array $manifest): array {
    $scope=nc110_scope($manifest);
    $native=pm1_terminal($root,PM1_NATIVE_OP,PM1_NATIVE_SHA,['completed_retained_native_scan']);
    $facts=[];$global=[];
    foreach($native['native_facts']??[] as $f){
        w76_need(is_array($f)&&in_array($f['supplier_namespace']??null,NC110_NS,true),'native110_fact_namespace');
        $ns=$f['supplier_namespace'];$n=(string)$f['native_id'];$cat=(string)$f['catalog_id'];
        w76_need(preg_match('/^[1-9][0-9]{0,31}$/D',$n)===1&&preg_match('/^[1-9][0-9]{0,31}$/D',$cat)===1,'native110_fact_identity');
        $global[$ns][$n][$cat]=true;
        if(in_array($cat,$scope['catalog_ids'],true))$facts[]=$f;
    }
    w76_need(count($native['native_facts']??[])===3262,'native110_native_membership');
    $raw=nc110_raw($root,$facts);$byCatalog=[];
    foreach($facts as $i=>$f){
        $byCatalog[(string)$f['catalog_id']][]=['namespace'=>$f['supplier_namespace'],'native_id'=>(string)$f['native_id'],
            'unique_catalog_in_saved_union'=>count($global[$f['supplier_namespace']][(string)$f['native_id']])===1,
            'raw'=>$raw['facts'][$i]];
    }
    $producers=[];$tvProofs=[];
    foreach($manifest['rows'] as $r){
        if($r['catalog_id']===NC110_PROTECTED)continue;
        foreach($r['tv_candidates'] as $candidate){
            $lane=$candidate['operator'];$key=$r['catalog_id'].'|'.$lane.'|'.$candidate['tv_hotel_id'];
            if(!in_array($lane,['funsun','intourist'],true)){$tvProofs[$key]=['state'=>'namespace_bridge_review_required','safe_to_write_now'=>false];continue;}
            $checks=[];
            foreach([PM1_PAIRS[420],PM1_PAIRS[16944]] as $pin){
                $op=$pin['producer'];
                if(!array_key_exists($op,$producers))try{$producers[$op]=pm1_terminal($root,$op,$pin['producer_sha'],['completed_read_only']);}catch(Throwable $e){$producers[$op]=null;}
                $spec=['operator'=>NC110_OPERATORS[$lane],'namespace'=>NC110_NS[$lane],'native'=>$candidate['native_id'],'producer'=>$op,'producer_sha'=>$pin['producer_sha']];
                $checks[]=['source_operation'=>$op,'source_result_sha256'=>$pin['producer_sha'],'audit'=>$producers[$op]===null?['state'=>'producer_unavailable']:pp1_producer($producers[$op],$candidate['tv_hotel_id'],$spec)];
            }
            $tvProofs[$key]=['producers'=>$checks,'safe_to_write_now'=>false];
        }
    }
    return ['native_facts_examined'=>3262,'source_facts'=>$byCatalog,'raw_files_read'=>$raw['files_read'],'raw_bytes_read'=>$raw['bytes_read'],'tv_proofs'=>$tvProofs];
}

/** Observations only: no READY verdict and no change to owner acceptance criteria. */
function nc110_classify(array $request,array $sources,array $targets,array $operators,array $manual,array $excluded,array $hotels,array $live): array {
    $cat=$request['catalog_id'];$source=$sources[$cat]??[];
    $out=['catalog_id'=>$cat,'safe_to_write_now'=>false];
    if($cat===NC110_PROTECTED)return $out+['state'=>'protected_not_examined'];
    $holds=[];$row=count($source)===1?$source[0]:null;
    if(!$row)$holds[]='current_source_not_unique';
    elseif($row['decision_status']!=='pending'||$row['local_hotel_id']!==null)$holds[]='current_source_not_pending_null';
    if($row&&!w76_evidence_valid($row))$holds[]='current_source_evidence_invalid';
    $out['source_catalog_digest_matches_saved']=$row!==null&&($row['catalog_sha256']??null)===$request['catalog_sha256'];
    $out['source_evidence_digest_matches_saved']=$row!==null&&($row['evidence_sha256']??null)===$request['evidence_sha256'];
    if($row&&(!$out['source_catalog_digest_matches_saved']||!$out['source_evidence_digest_matches_saved']))$holds[]='current_source_revision_differs';
    $out['source_revision']=$row?array_intersect_key($row,array_flip(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256'])):null;
    $history=$row?json_decode((string)$row['evidence_json'],true):null;
    $out['source_history_present']=is_array($history['source']??null);
    $out['source_history_catalog_id_matches']=is_array($history['source']??null)&&(string)($history['source']['id']??'')===$cat;
    $out['source_history_sha256']=$out['source_history_present']?w76_hash($history['source']):null;
    if(!$out['source_history_catalog_id_matches'])$holds[]='current_source_history_review';
    $out['targets']=[];
    $typed=[];foreach($request['tv_candidates'] as $c)$typed['tv|'.$c['tv_hotel_id']]=['kind'=>'tv_candidate','id'=>$c['tv_hotel_id']];
    foreach($request['local_anchor_ids'] as $id)$typed['local|'.$id]=['kind'=>'independent_local_anchor','id'=>$id];
    foreach($typed as $target){
        $id=$target['id'];$h=$hotels[$id]??null;$reasons=[];
        if(!$h||(int)$h['is_active']!==1)$reasons[]='target_missing_or_inactive';
        if(isset($manual[$id])||isset($excluded[$id]))$reasons[]='target_manual_or_exclusion';
        foreach($targets[$id]??[] as $occupant)if((string)$occupant['external_hotel_id']!==$cat)$reasons[]='target_catalog_occupied';
        $out['targets'][]=$target+['catalog_record'=>$h,'tv_live30_observed'=>isset($live[$id]),'holds'=>array_values(array_unique($reasons))];
    }
    $out['operator_rows']=[];
    foreach($request['native_by_operator'] as $lane=>$values)foreach($values as $n){
        $current=$operators[NC110_NS[$lane]][$n]??[];
        $out['operator_rows'][]=['namespace'=>NC110_NS[$lane],'native_id'=>$n,'current_rows'=>$current];
    }
    return $out+['state'=>'current_review_observed','holds'=>array_values(array_unique($holds))];
}

function nc110_current(PDO $db,array $manifest): array {
    $scope=nc110_scope($manifest);$ids=$scope['catalog_ids'];$targets=$scope['target_ids'];
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $all=w76_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001");
        w76_need(count($all)<=50000,'native110_identity_cap');$c=pm1_context($all);
        $ph=implode(',',array_fill(0,count($ids),'?'));$sources=[];
        foreach(w76_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ph) ORDER BY external_hotel_id",$ids) as $r)$sources[(string)$r['external_hotel_id']][]=$r;
        $ph=implode(',',array_fill(0,count($targets),'?'));$hotels=[];$manual=[];$excluded=[];$live=[];
        foreach(w76_q($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$targets) as $r)$hotels[(int)$r['id']]=$r;
        foreach(w76_q($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$targets) as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        foreach(w76_q($db,"SELECT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph)",$targets) as $r)$excluded[(int)$r['catalog_hotel_id']]=true;
        foreach(w76_q($db,"SELECT DISTINCT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)",$targets) as $r)$live[(int)$r['hotel_id']]=true;
        $rows=[];foreach($manifest['rows'] as $r)$rows[]=nc110_classify($r,$sources,$c['targets'],$c['operators'],$manual,$excluded,$hotels,$live);
        $db->rollBack();return $rows;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Bounded per-source readback: identities and proof states, no raw values or history. */
function nc110_review_rows(array $manifest,array $rows,array $saved): array {
    $current=[];foreach($rows as $row)$current[$row['catalog_id']]=$row;
    $out=[];
    foreach($manifest['rows'] as $request){
        $cat=$request['catalog_id'];$row=$current[$cat];
        $r=['catalog_id'=>$cat,'state'=>$row['state'],'safe_to_write_now'=>false,
            'holds'=>$row['holds']??[],
            'catalog_digest_matches_saved'=>$row['source_catalog_digest_matches_saved']??false,
            'evidence_digest_matches_saved'=>$row['source_evidence_digest_matches_saved']??false,
            'source_history_id_matches'=>$row['source_history_catalog_id_matches']??false,
            'native_checks'=>[],'operator_checks'=>[],'targets'=>[],'tv_checks'=>[]];
        if($cat===NC110_PROTECTED){$out[]=$r;continue;}
        foreach($saved['source_facts'][$cat]??[] as $f)$r['native_checks'][]=[
            'namespace'=>$f['namespace'],'native_id'=>$f['native_id'],
            'global_saved_unique'=>$f['unique_catalog_in_saved_union'],
            'raw_verified'=>$f['raw']['raw_verified'],'failures'=>$f['raw']['failures']];
        foreach($row['operator_rows'] as $o){
            $ids=[];foreach($o['current_rows'] as $c)if($c['local_hotel_id']!==null)$ids[(int)$c['local_hotel_id']]=true;
            $r['operator_checks'][]=['namespace'=>$o['namespace'],'native_id'=>$o['native_id'],
                'current_identity_count'=>count($o['current_rows']),'current_local_hotel_ids'=>array_keys($ids)];
        }
        foreach($row['targets'] as $t)$r['targets'][]=[
            'kind'=>$t['kind'],'id'=>$t['id'],'tv_live30_observed'=>$t['tv_live30_observed'],'holds'=>$t['holds']];
        foreach($request['tv_candidates'] as $c){
            $p=$saved['tv_proofs'][$cat.'|'.$c['operator'].'|'.$c['tv_hotel_id']];$proofs=[];
            foreach($p['producers']??[] as $producer)$proofs[]=[
                'source_operation'=>$producer['source_operation'],'source_result_sha256'=>$producer['source_result_sha256'],
                'state'=>$producer['audit']['state'],'failures'=>$producer['audit']['failures']??[]];
            $r['tv_checks'][]=['tv_hotel_id'=>$c['tv_hotel_id'],'operator'=>$c['operator'],
                'native_id'=>$c['native_id'],'tv_native_id'=>$c['tv_native_id'],
                'state'=>$p['state']??'saved_producers_reviewed','producers'=>$proofs];
        }
        $out[]=$r;
    }
    return $out;
}

function nc110_main(array $args): void {
    w76_need(count($args)===2&&$args[1]==='--current','native110_disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($root)===$root&&basename($root)==='anytoour.ru'&&realpath($dir)===$dir&&basename($dir)===NC110_OP
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'native110_execution_scope');
    $reservation=pm1_read(dirname($dir),basename($dir).'/reservation.json',null,1048576);
    w76_need(($reservation['operation']??null)===NC110_OP&&($reservation['source_sha']??null)===$head
        &&($reservation['batch']??null)===NC110_BATCH&&($reservation['maximum_writes']??null)===0
        &&($reservation['provider_http_calls']??null)===0&&($reservation['state']??null)==='reserved_before_db_read',
        'native110_reservation_binding');
    $path=__DIR__.'/fixtures/hotel_match_native110_current_v1.json';
    w76_need(is_file($path)&&!is_link($path)&&hash_file('sha256',$path)===NC110_MANIFEST_SHA,'native110_manifest_digest');
    $manifest=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);nc110_scope($manifest);
    foreach(['native110-current-manifest.json','native110-current-summary.json'] as $f)w76_need(!file_exists($dir.'/'.$f),'native110_no_replay');
    $saved=nc110_saved(dirname($dir),$manifest);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $rows=nc110_current(v2_data_db(),$manifest);
    $result=['schema'=>'native110-current-review/1','operation'=>NC110_OP,'source_sha'=>$head,'batch'=>NC110_BATCH,
        'captured_at_utc'=>gmdate('c'),'rows'=>$rows,'saved_evidence'=>$saved,'provider_http_calls'=>0,'database_writes'=>0,
        'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true,'acceptance_policy_changed'=>false,
        'review_rows'=>nc110_review_rows($manifest,$rows,$saved)];
    $sha=w76_save($dir.'/native110-current-manifest.json',$result);
    $rawVerified=0;foreach($saved['source_facts'] as $facts)foreach($facts as $f)if($f['raw']['raw_verified'])++$rawVerified;
    $summary=['state'=>'completed_native110_current_review','operation'=>NC110_OP,'source_sha'=>$head,'batch'=>NC110_BATCH,
        'manifest_sha256'=>$sha,'sources_requested'=>110,'sources_examined'=>109,'protected_skipped'=>1,
        'current_rows_returned'=>count($rows),'review_rows'=>$result['review_rows'],
        'raw_verified_facts'=>$rawVerified,'raw_files_read'=>$saved['raw_files_read'],
        'raw_bytes_read'=>$saved['raw_bytes_read'],'native_facts_examined'=>3262,'provider_http_calls'=>0,'database_writes'=>0,
        'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true,'acceptance_policy_changed'=>false];
    w76_save($dir.'/native110-current-summary.json',$summary);echo w76_json($summary)."\n";
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)nc110_main($argv);
