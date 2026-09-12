<?php
declare(strict_types=1);

/**
 * MATCH #1971: guarded CURRENT acceptance from immutable synchronized
 * Tourvisor ↔ ANEX ↔ Andromeda recurrence evidence.
 *
 * This is not a hard-coded delta. Every run re-evaluates all 71 tuples inside
 * one REPEATABLE READ write transaction and accepts only still-safe missing
 * provider sides. Recurrence alone never establishes identity.
 */
if (!defined('HMRCA_REVIEW_LIBRARY')) define('HMRCA_REVIEW_LIBRARY', true);
require_once __DIR__ . '/hotel_match_three_provider_recurrence_current_review.php';

const HMRCA_OPERATION = 'hotel-match-three-provider-recurrence-current-accept-1971-20260912-v1';
const HMRCA_POLICY = 'owner_exact_and_strong_20260908';

function hmrca_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function hmrca_hash(array $value): string { return hash('sha256', hmrca_json($value)); }
function hmrca_fail(string $reason): array {
    return ['status'=>'failed','operation_id'=>HMRCA_OPERATION,'reason'=>$reason,'examined'=>0,
        'database_writes'=>0,'mapping_writes'=>0,'committed'=>false,'post_commit_readback_count'=>0,
        'supplier_calls'=>0,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
        'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
}
function hmrca_in(array $values): array {
    $values=array_values(array_unique($values,SORT_REGULAR));
    if(!$values)return['NULL',[]];
    return[implode(',',array_fill(0,count($values),'?')),$values];
}
function hmrca_rows(PDO $db,string $sql,array $params,callable $key): array {
    $q=$db->prepare($sql);$q->execute($params);$out=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$key($row)][]=$row;
    return$out;
}
function hmrca_coverage(PDO $db): array {
    return [
        'anex_links'=>(int)$db->query("SELECT COUNT(*) FROM anex_hotel_search_mappings WHERE enabled=1")->fetchColumn(),
        'andromeda_accepted'=>(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchColumn(),
        'andromeda_pending'=>(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL")->fetchColumn(),
    ];
}
function hmrca_anex_evidence(array $row): array {
    return [
        'operation_id'=>HMRCA_OPERATION,'lane'=>'MATCH','provider'=>'anex',
        'rule'=>'current_andromeda_bridge_plus_recurrent_synchronized_tv_anchor',
        'anex_hotel_id'=>(int)$row['anex_hotel_id'],'tourvisor_local_hotel_id'=>(int)$row['tourvisor_id'],
        'andromeda_external_id'=>(string)$row['andromeda_external_id'],
        'observation_count'=>(int)$row['observation_count'],'distinct_date_count'=>(int)$row['distinct_date_count'],
        'alias_key'=>(string)$row['alias_key'],'significant_alias_tokens'=>(int)$row['significant_alias_tokens'],
        'coordinate_distances_m'=>$row['coordinate_distances_m']??[],
        'immutable_evidence_sha256'=>HMRCR_EVIDENCE_SHA256,'server_current_same_transaction'=>true,
    ];
}
function hmrca_andromeda_evidence(array $prior,array $row): array {
    return ['prior_evidence'=>$prior,'promotion'=>[
        'operation_id'=>HMRCA_OPERATION,'lane'=>'MATCH','provider'=>'andromeda',
        'rule'=>'current_anex_bridge_plus_recurrent_synchronized_tv_anchor',
        'andromeda_external_id'=>(string)$row['andromeda_external_id'],'tourvisor_local_hotel_id'=>(int)$row['tourvisor_id'],
        'anex_hotel_id'=>(int)$row['anex_hotel_id'],'observation_count'=>(int)$row['observation_count'],
        'distinct_date_count'=>(int)$row['distinct_date_count'],'alias_key'=>(string)$row['alias_key'],
        'significant_alias_tokens'=>(int)$row['significant_alias_tokens'],'coordinate_distances_m'=>$row['coordinate_distances_m']??[],
        'immutable_evidence_sha256'=>HMRCR_EVIDENCE_SHA256,'server_current_same_transaction'=>true,
    ]];
}

function hmrca_plan_locked(PDO $db,array $ev): array {
    if(!$db->inTransaction())throw new RuntimeException('transaction_required');
    $tuples=$ev['tuples']??[];
    if(count($tuples)!==71)throw new RuntimeException('tuple_count_changed');
    $tvIds=[];$anexIds=[];$andrIds=[];
    foreach($tuples as$t){$tvIds[]=(int)$t[0];$anexIds[]=(int)$t[1];if((string)$t[3]==='andromeda_catalog')$andrIds[]=(string)$t[2];}
    [$tvSql,$tvP]=hmrca_in($tvIds);[$anSql,$anP]=hmrca_in($anexIds);[$andrSql,$andrP]=hmrca_in($andrIds);

    $hotels=[];$q=$db->prepare("SELECT h.id,h.country_id,h.name,h.normalized_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.id IN ($tvSql) FOR UPDATE");
    $q->execute($tvP);foreach($q->fetchAll(PDO::FETCH_ASSOC) as$r)$hotels[(int)$r['id']]=$r;
    $aliases=[];$q=$db->prepare("SELECT hotel_id,alias,normalized_alias FROM hotel_aliases WHERE hotel_id IN ($tvSql) ORDER BY hotel_id,id");$q->execute($tvP);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as$r){$id=(int)$r['hotel_id'];foreach(['alias','normalized_alias'] as$k)if(trim((string)$r[$k])!=='')$aliases[$id][]=(string)$r[$k];}

    $anDec=hmrca_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($anSql) FOR UPDATE",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
    $anMap=hmrca_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($anSql) FOR UPDATE",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
    $anEx=hmrca_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($anSql) FOR UPDATE",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
    $anStage=hmrca_rows($db,"SELECT * FROM anex_hotels WHERE anex_hotel_id IN ($anSql)",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
    $anObs=hmrca_rows($db,"SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($anSql) ORDER BY search_count DESC,last_seen_utc DESC",$anP,static fn($r)=>(int)$r['anex_hotel_id']);

    $andr=[];$andrObs=[];
    if($andrIds){
        $andr=hmrca_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($andrSql) FOR UPDATE",$andrP,static fn($r)=>(string)$r['external_hotel_id']);
        $andrObs=hmrca_rows($db,"SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($andrSql) ORDER BY observed_at_utc DESC",$andrP,static fn($r)=>(string)$r['external_hotel_id']);
    }

    // Lock all existing provider claims so a target cannot become occupied between
    // CURRENT planning and apply in this transaction.
    $allDec=$db->query("SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    $allMap=$db->query("SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    $allAnd=$db->query("SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    $decided=[];$anOccup=[];
    foreach($allDec as$r){$id=(int)$r['anex_hotel_id'];$decided[$id]=true;if((string)$r['decision_status']==='accepted'&&$r['catalog_hotel_id']!==null)$anOccup[(int)$r['catalog_hotel_id']][$id]=true;}
    foreach($allMap as$r){$id=(int)$r['anex_hotel_id'];if((int)$r['enabled']!==1||$r['catalog_hotel_id']===null||isset($decided[$id]))continue;$anOccup[(int)$r['catalog_hotel_id']][$id]=true;}
    $andrOccup=[];foreach($allAnd as$r)if((string)$r['decision_status']==='accepted'&&$r['local_hotel_id']!==null)$andrOccup[(int)$r['local_hotel_id']][(string)$r['external_hotel_id']]=true;

    $buckets=['already_triple'=>[],'safe_missing_anex'=>[],'safe_missing_andromeda'=>[],'both_unlinked_tv_anchor'=>[],'protected_or_conflict'=>[],'not_current'=>[]];
    $reasons=[];$prepared=[];
    foreach($tuples as$t){
        [$tv,$anex,$ae,$ns,$tvName,$anName,$andrName,$alias,$obs,$dates,$mask]=$t;
        $tv=(int)$tv;$anex=(int)$anex;$ae=(string)$ae;$obs=(int)$obs;$dates=(int)$dates;
        $row=['tourvisor_id'=>$tv,'anex_hotel_id'=>$anex,'andromeda_namespace'=>(string)$ns,'andromeda_external_id'=>$ae,'observation_count'=>$obs,'distinct_date_count'=>$dates,'alias_key'=>(string)$alias];
        $target=$hotels[$tv]??null;
        if(!$target||(int)$target['is_active']!==1||(int)$target['country_id']!==HMRCR_COUNTRY_ID){$row['reason']='tourvisor_local_target_not_current_turkey';$buckets['not_current'][]=$row;$reasons[$row['reason']]=($reasons[$row['reason']]??0)+1;continue;}
        $localNames=array_merge([(string)$target['name'],(string)($target['normalized_name']??'')],$aliases[$tv]??[]);
        if(!hmrcr_shared_alias($localNames,[(string)$tvName])||!hmrcr_shared_alias($localNames,[(string)$anName])||!hmrcr_shared_alias($localNames,[(string)$andrName])){$row['reason']='current_local_name_alias_no_longer_supports_tuple';$buckets['protected_or_conflict'][]=$row;$reasons[$row['reason']]=($reasons[$row['reason']]??0)+1;continue;}
        $sig=hmrcr_sig_tokens((string)$alias);$row['significant_alias_tokens']=$sig;$row['local_name']=(string)$target['name'];
        [$tl,$to]=hmrcr_coord($target);$dist=[];
        $stage=$anStage[$anex][0]??[];[$al,$ao]=hmrcr_coord($stage);$ad=hmrcr_haversine($al,$ao,$tl,$to);if($ad!==null)$dist['anex_m']=(int)round($ad);
        $ai=$andr[$ae][0]??[];$prior=json_decode((string)($ai['evidence_json']??''),true);if(!is_array($prior))$prior=[];$src=is_array($prior['source']??null)?$prior['source']:[];$aobs=$andrObs[$ae][0]??[];[$dl,$do]=hmrcr_coord($src+$aobs);$dd=hmrcr_haversine($dl,$do,$tl,$to);if($dd!==null)$dist['andromeda_m']=(int)round($dd);$row['coordinate_distances_m']=$dist;
        if(($ad!==null&&$ad>5000)||($dd!==null&&$dd>5000)){$row['reason']='coordinate_conflict_gt_5km';$buckets['protected_or_conflict'][]=$row;$reasons[$row['reason']]=($reasons[$row['reason']]??0)+1;continue;}

        $anState='missing';$anReason='no_current_mapping';
        if(isset($anDec[$anex])){
            $accepted=array_values(array_filter($anDec[$anex],static fn($r)=>(string)$r['decision_status']==='accepted'&&$r['catalog_hotel_id']!==null));
            if(count($accepted)===1&&(int)$accepted[0]['catalog_hotel_id']===$tv){$anState='same';$anReason='manual_accepted_same';}
            elseif($accepted){$anState='conflict';$anReason='manual_accepted_other';}
            else{$anState='protected';$anReason='manual_decision_protected';}
        }else{
            $targets=[];foreach($anMap[$anex]??[] as$r)if((int)$r['enabled']===1&&$r['catalog_hotel_id']!==null)$targets[(int)$r['catalog_hotel_id']]=true;
            if(count($targets)===1&&isset($targets[$tv])){$anState='same';$anReason='policy_mapping_same';}
            elseif($targets){$anState='conflict';$anReason='existing_policy_mapping_other';}
            elseif(isset($anMap[$anex])){$anState='protected';$anReason='existing_disabled_mapping_preserved';}
        }
        foreach($anEx[$anex]??[] as$x)if((int)$x['catalog_hotel_id']===$tv&&$anState==='missing'){$anState='protected';$anReason='pair_exclusion_target';}
        if($anState==='missing'){$occ=array_values(array_filter(array_keys($anOccup[$tv]??[]),static fn($id)=>$id!==$anex));if($occ){$anState='conflict';$anReason='anex_target_occupied_by_other';$row['anex_target_occupants']=array_slice($occ,0,10);}}

        $drState='missing';$drReason='pending_null_local';
        if((string)$ns!=='andromeda_catalog'){$drState='protected';$drReason='unsupported_operator_specific_namespace';}
        elseif(!isset($andr[$ae])){$drState='not_current';$drReason='andromeda_identity_row_missing';}
        else{
            $accepted=array_values(array_filter($andr[$ae],static fn($r)=>(string)$r['decision_status']==='accepted'&&$r['local_hotel_id']!==null));
            $conflict=array_values(array_filter($andr[$ae],static fn($r)=>(string)$r['decision_status']==='conflict'));
            if(count($accepted)===1&&(int)$accepted[0]['local_hotel_id']===$tv){$drState='same';$drReason='accepted_same';}
            elseif($accepted){$drState='conflict';$drReason='accepted_other';}
            elseif($conflict){$drState='protected';$drReason='andromeda_conflict_protected';}
            elseif(count($andr[$ae])!==1||(string)($andr[$ae][0]['decision_status']??'')!=='pending'||$andr[$ae][0]['local_hotel_id']!==null){$drState='protected';$drReason='andromeda_nonpending_or_nonunique';}
        }
        if($drState==='missing'){$occ=array_values(array_filter(array_keys($andrOccup[$tv]??[]),static fn($id)=>(string)$id!==$ae));if($occ){$drState='conflict';$drReason='andromeda_target_occupied_by_other';$row['andromeda_target_occupants']=array_slice($occ,0,10);}}
        $row['anex_state']=$anState;$row['anex_reason']=$anReason;$row['andromeda_state']=$drState;$row['andromeda_reason']=$drReason;

        if($anState==='same'&&$drState==='same'){$bucket='already_triple';$row['reason']='both_current_same_target';}
        elseif($anState==='same'&&$drState==='missing'&&$obs>=2&&$dates>=2&&$sig>=2){$bucket='safe_missing_andromeda';$row['reason']='current_anex_bridge_plus_recurrent_synchronized_tv_anchor';$prepared[]=['provider'=>'andromeda']+$row;}
        elseif($drState==='same'&&$anState==='missing'&&$obs>=2&&$dates>=2&&$sig>=2){$bucket='safe_missing_anex';$row['reason']='current_andromeda_bridge_plus_recurrent_synchronized_tv_anchor';$prepared[]=['provider'=>'anex']+$row;}
        elseif($anState==='missing'&&$drState==='missing'){$bucket='both_unlinked_tv_anchor';$row['reason']='synchronized_tv_anchor_requires_operator_hotelcode_or_current_bridge';}
        elseif($anState==='not_current'||$drState==='not_current'){$bucket='not_current';$row['reason']=$anState==='not_current'?$anReason:$drReason;}
        else{$bucket='protected_or_conflict';$row['reason']=($sig<2)?'low_information_alias_needs_geo_or_operator_evidence':(($obs<2||$dates<2)?'recurrence_support_below_safe_threshold':'existing_protection_or_identity_conflict');}
        $buckets[$bucket][]=$row;$reasons[$row['reason']]=($reasons[$row['reason']]??0)+1;
    }

    // The plan itself must also be one-to-one; two currently-missing supplier IDs
    // may not claim the same local target in a single commit.
    $groups=[];foreach($prepared as$i=>$r)$groups[$r['provider'].':'.$r['tourvisor_id']][]=$i;
    $demote=[];foreach($groups as$idxs)if(count($idxs)>1)foreach($idxs as$i)$demote[$i]=true;
    if($demote){$keep=[];foreach($prepared as$i=>$r){if(isset($demote[$i])){$r['reason']='same_provider_plan_target_collision';$buckets['protected_or_conflict'][]=$r;$reasons[$r['reason']]=($reasons[$r['reason']]??0)+1;}else$keep[]=$r;}$prepared=$keep;}
    arsort($reasons);$counts=[];foreach($buckets as$k=>$v)$counts[$k]=count($v);
    return ['examined'=>71,'counts'=>$counts,'reason_counts'=>$reasons,'prepared'=>$prepared,'buckets'=>$buckets];
}

function hmrca_accept(PDO $db,array $ev): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    foreach(['anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','catalog_hotels','hotel_aliases'] as$table){$q=$db->query("SHOW TABLES LIKE ".$db->quote($table));if(!$q->fetchColumn())throw new RuntimeException('required_table_missing_'.$table);}
    $db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $before=hmrca_coverage($db);$writes=0;$acceptedAn=[];$acceptedAnd=[];$mappingDigest=hmrca_hash(['operation_id'=>HMRCA_OPERATION,'evidence_sha256'=>HMRCR_EVIDENCE_SHA256,'mode'=>'server_current_recurrence_v1']);
    try{
        $db->beginTransaction();$plan=hmrca_plan_locked($db,$ev);
        $insert=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
        foreach($plan['prepared'] as$row){
            $target=(int)$row['tourvisor_id'];if($target<1)throw new RuntimeException('prepared_target_missing');
            if($row['provider']==='anex'){
                $id=(int)$row['anex_hotel_id'];$e=hmrca_anex_evidence($row);$digest=hmrca_hash($e);$insert->execute([$id,$target,HMRCA_POLICY,$digest,$mappingDigest]);if($insert->rowCount()!==1)throw new RuntimeException('anex_insert_not_one');$acceptedAn[$id]=['target'=>$target,'source_row_digest'=>$digest,'observation_count'=>(int)$row['observation_count'],'distinct_date_count'=>(int)$row['distinct_date_count']];$writes++;
            }else{
                $external=(string)$row['andromeda_external_id'];$q=$db->prepare("SELECT evidence_sha256,evidence_json,decision_status,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");$q->execute([$external]);$identity=$q->fetch(PDO::FETCH_ASSOC);if(!$identity||(string)$identity['decision_status']!=='pending'||$identity['local_hotel_id']!==null)throw new RuntimeException('andromeda_current_identity_changed');$prior=json_decode((string)($identity['evidence_json']??''),true);if(!is_array($prior))$prior=[];$e=hmrca_andromeda_evidence($prior,$row);$json=hmrca_json($e);$hash=hash('sha256',$json);$update->execute([$target,$hash,$json,$external,$identity['evidence_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('andromeda_update_not_one');$acceptedAnd[$external]=['target'=>$target,'evidence_sha256'=>$hash,'observation_count'=>(int)$row['observation_count'],'distinct_date_count'=>(int)$row['distinct_date_count']];$writes++;
            }
        }
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}

    // Mandatory post-COMMIT per-row readback.
    $readback=0;
    $qAn=$db->prepare("SELECT catalog_hotel_id,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND scope='preview' AND approval_policy=?");
    foreach($acceptedAn as$id=>$x){$qAn->execute([(int)$id,HMRCA_POLICY]);$r=$qAn->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['catalog_hotel_id']!==(int)$x['target']||!hash_equals((string)$x['source_row_digest'],(string)$r['source_row_digest'])||!hash_equals($mappingDigest,(string)$r['mapping_digest'])||(int)$r['enabled']!==1)throw new RuntimeException('anex_post_commit_readback_failed');$readback++;}
    $qAnd=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
    foreach($acceptedAnd as$external=>$x){$qAnd->execute([(string)$external]);$r=$qAnd->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['local_hotel_id']!==(int)$x['target']||(string)$r['decision_status']!=='accepted'||!hash_equals((string)$x['evidence_sha256'],(string)$r['evidence_sha256']))throw new RuntimeException('andromeda_post_commit_readback_failed');$readback++;}
    if($readback!==$writes)throw new RuntimeException('post_commit_readback_count_mismatch');
    $after=hmrca_coverage($db);
    return ['status'=>'completed','operation_id'=>HMRCA_OPERATION,'mode'=>'server_current_recurrence_guarded_write','input'=>['slices'=>10,'observations'=>233,'tuples'=>71,'sha256'=>HMRCR_EVIDENCE_SHA256],
        'examined'=>71,'plan_counts'=>$plan['counts'],'reason_counts'=>$plan['reason_counts'],'planned_safe'=>count($plan['prepared']),
        'database_writes'=>$writes,'mapping_writes'=>$writes,'committed'=>true,'post_commit_readback_count'=>$readback,
        'accepted'=>['anex'=>array_values(array_map(static fn($id,$x)=>['external_id'=>(int)$id]+$x,array_keys($acceptedAn),array_values($acceptedAn))),'andromeda'=>array_values(array_map(static fn($id,$x)=>['external_id'=>(string)$id]+$x,array_keys($acceptedAnd),array_values($acceptedAnd)))],
        'coverage_before'=>$before,'coverage_after'=>$after,'guards'=>['all_71_revalidated_same_transaction'=>true,'opposite_provider_bridge_required'=>true,'recurrence_alone_accepts_identity'=>false,'safe_observations_gte'=>2,'safe_dates_gte'=>2,'significant_alias_tokens_gte'=>2,'generic_identity_tokens'=>['HOTEL','RESORT','SPA'],'significant_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'coordinate_conflict_auto_block_m'=>5000,'manual_decisions_preserved'=>true,'pair_exclusions_preserved'=>true,'existing_mappings_preserved'=>true,'same_provider_target_occupancy_blocked'=>true,'atomic_transaction'=>true,'post_commit_per_row_readback'=>true],
        'supplier_calls'=>0,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'historical_operations_replayed'=>false,'no_replay'=>true];
}

if(PHP_SAPI==='cli'&&($argv[1]??'')==='--self-test'){
    if(hmrcr_sig_tokens('HOTEL RESORT SPA')!==0)throw new RuntimeException('generic_identity_test');
    if(hmrcr_sig_tokens('CATAMARAN QUALITY TIMES HOTEL')!==3)throw new RuntimeException('significant_token_test');
    if(hmrcr_haversine(36.713018,31.563078,36.7130180,31.5630780)!==0.0)throw new RuntimeException('coordinate_format_test');
    $e=hmrca_anex_evidence(['anex_hotel_id'=>8280,'tourvisor_id'=>1067,'andromeda_external_id'=>'2000034099','observation_count'=>3,'distinct_date_count'=>3,'alias_key'=>'catamaran quality times','significant_alias_tokens'=>3,'coordinate_distances_m'=>[]]);
    if(($e['rule']??'')!=='current_andromeda_bridge_plus_recurrent_synchronized_tv_anchor'||($e['server_current_same_transaction']??false)!==true)throw new RuntimeException('evidence_test');
    echo "MATCH_RECURRENCE_CURRENT_ACCEPT_TEST_OK\n";exit(0);
}
if(PHP_SAPI==='cli'&&($argv[1]??'')==='--live'){
    try{
        $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('server_root_invalid');
        $helper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $helper;
        $result=hmrca_accept(v2_data_db(),hmrcr_evidence());
        echo 'HMRCA_RESULT:'.hmrca_json($result).PHP_EOL;
    }catch(Throwable $e){echo 'HMRCA_RESULT:'.hmrca_json(hmrca_fail('current_accept_failed')).PHP_EOL;exit(2);}
}
