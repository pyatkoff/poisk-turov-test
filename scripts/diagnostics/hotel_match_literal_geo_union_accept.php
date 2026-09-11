<?php
declare(strict_types=1);

if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_literal_geo_union_review.php';
require_once __DIR__ . '/hotel_match_current_bulk_accept.php';

const HMLGUA_OPERATION = 'hotel-match-literal-geo-union-accept-1971-20260912-v1';
const HMLGUA_POLICY = 'owner_exact_and_strong_20260908';

function hmlgua_anex_evidence(string $operation, array $row): array {
    return [
        'operation_id'=>$operation,
        'lane'=>'MATCH',
        'provider'=>'anex',
        'rule'=>'literal_geo_union_consensus',
        'external_id'=>(int)$row['external_id'],
        'country_id'=>(int)$row['country_id'],
        'target_local_hotel_id'=>(int)$row['target_local_hotel_id'],
        'routes'=>array_values(array_map('strval',$row['routes'] ?? [])),
        'live'=>(bool)($row['live'] ?? false),
        'observation_count'=>(int)($row['observation_count'] ?? 0),
        'evidence'=>$row['evidence'] ?? null,
        'server_current_same_transaction'=>true,
    ];
}

function hmlgua_andromeda_evidence(string $operation, array $prior, array $row): array {
    return [
        'prior_evidence'=>$prior,
        'promotion'=>[
            'operation_id'=>$operation,
            'lane'=>'MATCH',
            'provider'=>'andromeda',
            'rule'=>'literal_geo_union_consensus',
            'external_id'=>(string)$row['external_id'],
            'country_id'=>(int)$row['country_id'],
            'target_local_hotel_id'=>(int)$row['target_local_hotel_id'],
            'routes'=>array_values(array_map('strval',$row['routes'] ?? [])),
            'live'=>(bool)($row['live'] ?? false),
            'observation_count'=>(int)($row['observation_count'] ?? 0),
            'evidence'=>$row['evidence'] ?? null,
            'server_current_same_transaction'=>true,
        ],
    ];
}

function hmlgua_plan_current_in_transaction(PDO $db): array {
    if (!$db->inTransaction()) throw new RuntimeException('transaction_required');

    // Lock every mutable identity/protection surface before deriving the delta.
    $manualRows=$db->query('SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $mappingRows=$db->query('SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $exclusionRows=$db->query('SELECT * FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $identityRows=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);

    $manual=[];
    foreach($manualRows as$r)$manual[(int)$r['anex_hotel_id']]=true;
    $mapped=[];$anClaims=[];
    foreach($mappingRows as$r){
        if((int)($r['enabled']??0)!==1)continue;
        $aid=(int)$r['anex_hotel_id'];$hid=(int)$r['catalog_hotel_id'];
        $mapped[$aid]=true;$anClaims[$hid][]=$aid;
    }
    foreach($manualRows as$r){
        if((string)($r['decision_status']??'')!=='accepted'||$r['catalog_hotel_id']===null)continue;
        $anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
    }
    $excluded=[];
    foreach($exclusionRows as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

    $andStatus=[];$andClaims=[];$identityByExternal=[];
    foreach($identityRows as$r){
        $external=(string)$r['external_hotel_id'];$identityByExternal[$external]=$r;
        $andStatus[$external]=(string)$r['decision_status'];
        if((string)$r['decision_status']==='accepted'&&$r['local_hotel_id']!==null)$andClaims[(int)$r['local_hotel_id']][]=$external;
    }

    $coverage=fc_coverage($db);
    $an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);[$hotels,$names]=mbr_catalog($db);
    $literalIndex=hmelmr_build_index($hotels,$names);$singleIndex=hmstg_index($hotels,$names);
    $stats=['examined'=>0,'unresolved_anex'=>0,'unresolved_andromeda'=>0,'live_examined'=>0,'raw_target_none'=>0,'source_cross_lane_target_conflict'=>0,'literal_route_prepared'=>0,'single_geo_route_prepared'=>0,'both_routes_same_target'=>0,'coordinate_conflict_gt_5km'=>0,'pair_exclusion_block'=>0,'target_occupancy_block'=>0,'manual_protected'=>0,'existing_mapping_protected'=>0,'andromeda_nonpending_protected'=>0,'global_uniqueness_demotions'=>0,'prepared'=>0,'prepared_anex'=>0,'prepared_andromeda'=>0,'prepared_live'=>0,'needs_extra'=>0,'hard_conflict'=>0];
    $nodes=[];
    foreach($an as$aid=>$s){
        if($s['local_ids']||!isset(HMLGUR_CORE8[(int)$s['country_id']]))continue;
        $stats['unresolved_anex']++;
        if(isset($manual[(int)$aid])){$stats['manual_protected']++;continue;}
        if(isset($mapped[(int)$aid])){$stats['existing_mapping_protected']++;continue;}
        $nodes[hmigr_key('anex',$aid)]=['provider'=>'anex','external_id'=>(string)$aid,'country_id'=>(int)$s['country_id'],'live'=>(bool)$s['live'],'observation_count'=>(int)$s['observation_count'],'source'=>$s];
    }
    foreach($and as$id=>$s){
        if($s['local_ids']||!isset(HMLGUR_CORE8[(int)$s['country_id']]))continue;
        $stats['unresolved_andromeda']++;
        if(($andStatus[(string)$id]??'')!=='pending'){$stats['andromeda_nonpending_protected']++;continue;}
        $nodes[hmigr_key('andromeda',$id)]=['provider'=>'andromeda','external_id'=>(string)$id,'country_id'=>(int)$s['country_id'],'live'=>(bool)$s['live'],'observation_count'=>(int)$s['observation_count'],'source'=>$s];
    }

    $rows=[];
    foreach($nodes as$key=>$node){
        $stats['examined']++;if($node['live'])$stats['live_examined']++;
        $source=$node['source'];$country=$node['country_id'];
        $literalTargets=hmelmr_targets($source,$country,$literalIndex,$hotels,$names);
        $singleTargets=hmstg_targets($source,$country,$singleIndex,$hotels,$names);
        $cons=hmlgur_consensus($literalTargets,$singleTargets);
        if($cons['state']==='none'){$stats['raw_target_none']++;$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'no_literal_or_single_geo_target']+$node;continue;}
        if($cons['state']==='conflict'){$stats['source_cross_lane_target_conflict']++;$rows[$key]=['bucket'=>'hard_conflict','reason'=>'source_cross_lane_target_conflict','candidate_target_ids'=>$cons['candidate_target_ids']]+$node;continue;}
        $target=(int)$cons['target_local_hotel_id'];$literalEv=$literalTargets[$target]??null;$singleEv=$singleTargets[$target]??null;
        $literalEligible=$literalEv!==null&&count($literalTargets)===1&&count(hmelmr_keys($source['names']??[]))>0;
        $singleEligible=false;$coordOk=false;$placeOk=false;
        if($singleEv!==null&&count($singleTargets)===1&&count(hmstg_keys($source['names']??[]))>0){
            $coordOk=$singleEv['distance_m']!==null&&(float)$singleEv['distance_m']<=HMLGUR_COORD_ACCEPT_M;
            $placeOk=(bool)($singleEv['place_match']??false);$singleEligible=$coordOk||$placeOk;
        }
        $dist=$literalEv['distance_m']??($singleEv['distance_m']??null);
        if($dist!==null&&(float)$dist>HMLGUR_COORD_BLOCK_M){$stats['coordinate_conflict_gt_5km']++;$rows[$key]=['bucket'=>'hard_conflict','reason'=>'literal_geo_union_coordinate_conflict_gt_5km','target_local_hotel_id'=>$target,'distance_m'=>$dist]+$node;continue;}
        if(!$literalEligible&&!$singleEligible){$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'unique_raw_target_but_route_evidence_insufficient','target_local_hotel_id'=>$target]+$node;continue;}
        if($node['provider']==='anex'&&isset($excluded[(int)$node['external_id']][$target])){$stats['pair_exclusion_block']++;$rows[$key]=['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target_local_hotel_id'=>$target]+$node;continue;}
        $other=$node['provider']==='anex'?hmeumr_other($anClaims[$target]??[],(int)$node['external_id']):hmeumr_other($andClaims[$target]??[],(string)$node['external_id']);
        if($other){$stats['target_occupancy_block']++;$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'same_provider_target_occupied','target_local_hotel_id'=>$target,'other_claims'=>$other]+$node;continue;}
        $routes=[];
        if($literalEligible){$routes[]='exact_ordered_literal';$stats['literal_route_prepared']++;}
        if($singleEligible){$routes[]=$coordOk?'single_token_coord_le_1km':'single_token_exact_place';$stats['single_geo_route_prepared']++;}
        if(count($routes)>1)$stats['both_routes_same_target']++;
        $ev=$literalEv??$singleEv;
        $rows[$key]=['bucket'=>'prepared','reason'=>'literal_geo_union_consensus','target_local_hotel_id'=>$target,'routes'=>$routes,'evidence'=>$ev]+$node;
    }

    $groups=[];
    foreach($rows as$key=>$r)if(($r['bucket']??'')==='prepared')$groups[$r['provider'].':'.$r['target_local_hotel_id']][]=$key;
    foreach($groups as$keys)if(count($keys)>1)foreach($keys as$key){$rows[$key]['bucket']='needs_extra_evidence';$rows[$key]['reason']='same_provider_union_target_collision';$stats['global_uniqueness_demotions']++;}

    $prepared=[];$needs=[];$hard=[];
    foreach($rows as$r){
        unset($r['source']);
        if($r['bucket']==='prepared'){
            $prepared[]=$r;$stats['prepared']++;$stats[$r['provider']==='anex'?'prepared_anex':'prepared_andromeda']++;if($r['live'])$stats['prepared_live']++;
        }elseif($r['bucket']==='hard_conflict'){$hard[]=$r;$stats['hard_conflict']++;}
        else{$needs[]=$r;$stats['needs_extra']++;}
    }
    $sort=static fn($a,$b)=>(($b['live']??false)<=>($a['live']??false))?:((int)($b['observation_count']??0)<=>(int)($a['observation_count']??0))?:strcmp((string)$a['external_id'],(string)$b['external_id']);
    usort($prepared,$sort);usort($needs,$sort);usort($hard,$sort);
    return ['coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'needs_extra_evidence'=>$needs,'hard_conflicts'=>$hard,'identity_by_external'=>$identityByExternal];
}

function hmlgua_accept(PDO $db,string $operation=HMLGUA_OPERATION):array{
    if($operation!==HMLGUA_OPERATION)throw new RuntimeException('operation_scope');
    fc_require_tables($db);mba_require_transactional($db);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET SESSION innodb_lock_wait_timeout=30');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $writes=0;$committed=false;$acceptedAn=[];$acceptedAnd=[];$mappingDigest=fc_hash([$operation,'literal_geo_union_server_current_v1']);
    try{
        $db->beginTransaction();
        $plan=hmlgua_plan_current_in_transaction($db);$before=$plan['coverage'];
        $insert=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
        foreach($plan['prepared'] as$row){
            $target=(int)$row['target_local_hotel_id'];
            if($target<1)throw new RuntimeException('prepared_target_missing');
            if($row['provider']==='anex'){
                $id=(int)$row['external_id'];$evidence=hmlgua_anex_evidence($operation,$row);$digest=fc_hash($evidence);
                $insert->execute([$id,$target,HMLGUA_POLICY,$digest,$mappingDigest]);
                if($insert->rowCount()!==1)throw new RuntimeException('anex_insert_not_one');
                $acceptedAn[$id]=['target'=>$target,'source_row_digest'=>$digest,'routes'=>$row['routes']??[],'live'=>(bool)($row['live']??false)];$writes++;
            }else{
                $external=(string)$row['external_id'];$identity=$plan['identity_by_external'][$external]??null;
                if(!is_array($identity)||(string)$identity['decision_status']!=='pending'||$identity['local_hotel_id']!==null)throw new RuntimeException('andromeda_current_identity_missing');
                $prior=fc_evidence($identity['evidence_json']??'');$evidence=hmlgua_andromeda_evidence($operation,$prior,$row);$json=fc_json($evidence);$hash=hash('sha256',$json);
                $update->execute([$target,$hash,$json,$external,$identity['evidence_sha256']]);
                if($update->rowCount()!==1)throw new RuntimeException('andromeda_concurrent_change');
                $acceptedAnd[$external]=['target'=>$target,'evidence_sha256'=>$hash,'routes'=>$row['routes']??[],'live'=>(bool)($row['live']??false)];$writes++;
            }
        }
        $db->commit();$committed=true;

        $qAn=$db->prepare("SELECT catalog_hotel_id,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND scope='preview' AND approval_policy=?");
        foreach($acceptedAn as$id=>$expected){
            $qAn->execute([(int)$id,HMLGUA_POLICY]);$actual=$qAn->fetch(PDO::FETCH_ASSOC);
            if(!$actual||(int)$actual['catalog_hotel_id']!==(int)$expected['target']||!hash_equals((string)$expected['source_row_digest'],(string)$actual['source_row_digest'])||!hash_equals($mappingDigest,(string)$actual['mapping_digest'])||(int)$actual['enabled']!==1)throw new RuntimeException('anex_post_commit_readback_failed');
        }
        $qAnd=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
        foreach($acceptedAnd as$external=>$expected){
            $qAnd->execute([(string)$external]);$actual=$qAnd->fetch(PDO::FETCH_ASSOC);
            if(!$actual||(int)$actual['local_hotel_id']!==(int)$expected['target']||(string)$actual['decision_status']!=='accepted'||!hash_equals((string)$expected['evidence_sha256'],(string)$actual['evidence_sha256']))throw new RuntimeException('andromeda_post_commit_readback_failed');
        }
        $after=fc_coverage($db);
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'server_current_literal_geo_union_guarded_write','database_writes'=>$writes,'mapping_writes'=>$writes,'committed'=>true,'supplier_calls'=>0,'tourvisor_calls'=>0,'historical_operations_replayed'=>false,'planned'=>['total'=>(int)$plan['stats']['prepared'],'anex'=>(int)$plan['stats']['prepared_anex'],'andromeda'=>(int)$plan['stats']['prepared_andromeda'],'live'=>(int)$plan['stats']['prepared_live']],'plan_stats'=>$plan['stats'],'coverage_before'=>$before,'coverage_after'=>$after,'accepted'=>['anex'=>array_values(array_map(static fn($id,$r)=>['external_id'=>(int)$id]+$r,array_keys($acceptedAn),array_values($acceptedAn))),'andromeda'=>array_values(array_map(static fn($id,$r)=>['external_id'=>(string)$id]+$r,array_keys($acceptedAnd),array_values($acceptedAnd)))],'post_commit_readback_count'=>count($acceptedAn)+count($acceptedAnd),'readback_verified'=>true,'guards'=>['current_db_same_transaction'=>true,'core8_only'=>true,'manual_decisions_protected'=>true,'pair_exclusions_protected'=>true,'existing_mappings_overwritten'=>false,'same_provider_target_occupancy_protected'=>true,'cross_lane_target_agreement_required'=>true,'coordinate_conflict_block_m'=>HMLGUR_COORD_BLOCK_M,'single_token_independent_geo_required'=>true,'global_same_provider_target_one_to_one'=>true,'generic_tokens_dropped_only'=>['hotel','hotels','resort','resorts','spa','the','and','ex'],'meaningful_qualifiers_preserved'=>['annex','beach','garden','north','south'],'stars_not_identity'=>true,'supplier_calls'=>0,'tourvisor_calls'=>0]];
    }catch(Throwable$e){
        if($db->inTransaction())$db->rollBack();
        $known=['operation_scope','required_table_missing','required_transactional_table_missing','transaction_required','prepared_target_missing','anex_insert_not_one','andromeda_current_identity_missing','andromeda_concurrent_change','anex_post_commit_readback_failed','andromeda_post_commit_readback_failed','country_contract_changed','hotel_scope_limit','alias_scope_limit','write_scope_limit'];
        $message=$e->getMessage();$lower=strtolower($message);$reason=in_array($message,$known,true)?$message:'runtime_failure';
        if($e instanceof PDOException&&strpos($lower,'lock wait timeout')!==false)$reason='db_lock_timeout';
        if($e instanceof PDOException&&strpos($lower,'deadlock')!==false)$reason='db_deadlock';
        return ['status'=>'failed','operation_id'=>$operation,'mode'=>'server_current_literal_geo_union_guarded_write','database_writes'=>$committed?$writes:0,'mapping_writes'=>$committed?$writes:0,'committed'=>$committed,'supplier_calls'=>0,'tourvisor_calls'=>0,'historical_operations_replayed'=>false,'reason'=>$reason,'readback_verified'=>false];
    }
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $root=realpath(__DIR__.'/../..');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    echo fc_json(hmlgua_accept(v2_data_db(),HMLGUA_OPERATION)),"\n";
}
