<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_literal_geo_union_review.php';

const HMLGUA_OPERATION='hotel-match-literal-geo-union-accept-1971-20260912-v1';
const HMLGUA_SOURCE_OPERATION='hotel-match-literal-geo-union-review-1971-20260911-v2';
const HMLGUA_SOURCE_RESULT_SHA256='30fa82d99eeac0bdf97a39e9136ebcedc3c155eae7abbfe0170be76ba0413078';
const HMLGUA_EXPECTED_SOURCE_ROWS=99;
const HMLGUA_MAX_WRITES=120;

function hmlgua_canonical_value(mixed $value): mixed {
    if (!is_array($value)) return $value;
    if ($value !== [] && array_keys($value) !== range(0,count($value)-1)) ksort($value,SORT_STRING);
    foreach($value as $k=>$v)$value[$k]=hmlgua_canonical_value($v);
    return $value;
}
function hmlgua_json(array $value): string {
    return json_encode(hmlgua_canonical_value($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function hmlgua_require_transactional(PDO $db): void {
    $tables=['catalog_hotels','catalog_hotel_details','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities'];
    $q=$db->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    foreach($tables as $table){$q->execute([$table]);$engine=$q->fetchColumn();if($engine===false)throw new RuntimeException('required_table_missing:'.$table);if(strcasecmp((string)$engine,'InnoDB')!==0)throw new RuntimeException('required_table_not_innodb:'.$table);}
}
function hmlgua_source_manifest(array $review): array {
    if(($review['status']??null)!=='completed'||($review['operation_id']??null)!==HMLGUA_SOURCE_OPERATION||($review['database_writes']??null)!==0||($review['mapping_writes']??null)!==0||($review['supplier_calls']??null)!==0||($review['historical_operations_replayed']??null)!==false||!is_array($review['prepared']??null)||count($review['prepared'])!==HMLGUA_EXPECTED_SOURCE_ROWS||(int)($review['stats']['prepared']??-1)!==HMLGUA_EXPECTED_SOURCE_ROWS||(int)($review['stats']['prepared_anex']??-1)!==7||(int)($review['stats']['prepared_andromeda']??-1)!==92)throw new RuntimeException('union_source_invalid');
    $out=[];$provider=['anex'=>0,'andromeda'=>0];
    foreach($review['prepared'] as $row){
        $p=(string)($row['provider']??'');$e=(string)($row['external_id']??'');$t=(int)($row['target_local_hotel_id']??0);$routes=array_values($row['routes']??[]);
        if(!isset($provider[$p])||$e===''||$t<1||!$routes)throw new RuntimeException('union_source_row_invalid');
        foreach($routes as $route)if(!in_array($route,['exact_ordered_literal','single_token_coord_le_1km','single_token_exact_place'],true))throw new RuntimeException('union_source_route_invalid');
        sort($routes,SORT_STRING);$key=hmigr_key($p,$e);if(isset($out[$key]))throw new RuntimeException('union_source_duplicate');
        $out[$key]=['provider'=>$p,'external_id'=>$e,'country_id'=>(int)($row['country_id']??0),'target_local_hotel_id'=>$t,'routes'=>$routes,'source_live'=>(bool)($row['live']??false),'source_observation_count'=>(int)($row['observation_count']??0)];$provider[$p]++;
    }
    if($provider!==['anex'=>7,'andromeda'=>92])throw new RuntimeException('union_source_provider_counts_invalid');
    return $out;
}
function hmlgua_current_candidate(array $source,int $country,array $literalIndex,array $singleIndex,array $hotels,array $names): array {
    $literalTargets=hmelmr_targets($source,$country,$literalIndex,$hotels,$names);
    $singleTargets=hmstg_targets($source,$country,$singleIndex,$hotels,$names);
    $cons=hmlgur_consensus($literalTargets,$singleTargets);
    if($cons['state']==='none')return ['ok'=>false,'reason'=>'current_no_target'];
    if($cons['state']==='conflict')return ['ok'=>false,'reason'=>'current_cross_lane_conflict','candidate_target_ids'=>$cons['candidate_target_ids']];
    $target=(int)$cons['target_local_hotel_id'];$literalEv=$literalTargets[$target]??null;$singleEv=$singleTargets[$target]??null;
    $literalEligible=$literalEv!==null&&count($literalTargets)===1&&count(hmelmr_keys($source['names']??[]))>0;
    $coordOk=false;$placeOk=false;$singleEligible=false;
    if($singleEv!==null&&count($singleTargets)===1&&count(hmstg_keys($source['names']??[]))>0){$coordOk=$singleEv['distance_m']!==null&&(float)$singleEv['distance_m']<=HMLGUR_COORD_ACCEPT_M;$placeOk=(bool)($singleEv['place_match']??false);$singleEligible=$coordOk||$placeOk;}
    $dist=$literalEv['distance_m']??($singleEv['distance_m']??null);
    if($dist!==null&&(float)$dist>HMLGUR_COORD_BLOCK_M)return ['ok'=>false,'reason'=>'current_coordinate_conflict_gt_5km','target_local_hotel_id'=>$target,'distance_m'=>(float)$dist];
    if(!$literalEligible&&!$singleEligible)return ['ok'=>false,'reason'=>'current_route_evidence_insufficient','target_local_hotel_id'=>$target];
    $routes=[];if($literalEligible)$routes[]='exact_ordered_literal';if($singleEligible)$routes[]=$coordOk?'single_token_coord_le_1km':'single_token_exact_place';sort($routes,SORT_STRING);
    return ['ok'=>true,'target_local_hotel_id'=>$target,'routes'=>$routes,'distance_m'=>$dist===null?null:(float)$dist,'literal_evidence'=>$literalEv,'single_evidence'=>$singleEv];
}
function hmlgua_accept(PDO $db,array $review,string $operation=HMLGUA_OPERATION,int $maxWrites=HMLGUA_MAX_WRITES): array {
    if($operation!==HMLGUA_OPERATION)throw new RuntimeException('operation_scope');if($maxWrites<1||$maxWrites>HMLGUA_MAX_WRITES)throw new RuntimeException('write_scope_limit_config');
    $manifest=hmlgua_source_manifest($review);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);hmlgua_require_transactional($db);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $before=fc_coverage($db);$written=[];$stats=['source_allowed'=>count($manifest),'source_anex'=>7,'source_andromeda'=>92,'already_resolved'=>0,'manual_protected'=>0,'andromeda_nonpending_protected'=>0,'current_source_missing'=>0,'country_changed'=>0,'target_changed'=>0,'routes_changed'=>0,'pair_excluded'=>0,'target_occupied'=>0,'same_provider_target_collision'=>0,'andromeda_evidence_integrity_changed'=>0,'current_guard_failed'=>0,'planned'=>0,'written'=>0,'written_anex'=>0,'written_andromeda'=>0,'written_live'=>0,'post_commit_verified'=>0];
    $skip=[];
    try{
        $db->beginTransaction();
        $manual=[];foreach($db->query('SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC)as$r)$manual[(int)$r['anex_hotel_id']]=$r;
        $existingAn=[];$anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC)as$r){$aid=(int)$r['anex_hotel_id'];$hid=(int)$r['catalog_hotel_id'];$existingAn[$aid]=$hid;$anClaims[$hid][$aid]=true;}
        foreach($manual as$aid=>$r)if(($r['decision_status']??'')==='accepted'&&$r['catalog_hotel_id']!==null)$anClaims[(int)$r['catalog_hotel_id']][(int)$aid]=true;
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $andRows=[];$andClaims=[];foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC)as$r){$id=(string)$r['external_hotel_id'];$andRows[$id]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$andClaims[(int)$r['local_hotel_id']][$id]=true;}

        $an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);[$hotels,$names]=mbr_catalog($db);$literalIndex=hmelmr_build_index($hotels,$names);$singleIndex=hmstg_index($hotels,$names);
        $provisional=[];
        foreach($manifest as$key=>$expected){$provider=$expected['provider'];$external=$expected['external_id'];
            if($provider==='anex'){
                $aid=(int)$external;if(isset($existingAn[$aid])){$stats['already_resolved']++;$skip[$key]='already_resolved';continue;}if(isset($manual[$aid])){$stats['manual_protected']++;$skip[$key]='manual_protected';continue;}$source=$an[$aid]??null;
            }else{
                $identity=$andRows[$external]??null;if(!$identity){$stats['current_source_missing']++;$skip[$key]='andromeda_identity_missing';continue;}if(($identity['decision_status']??'')!=='pending'||$identity['local_hotel_id']!==null){$stats['andromeda_nonpending_protected']++;$skip[$key]='andromeda_nonpending_protected';continue;}$source=$and[$external]??null;
            }
            if(!is_array($source)||!empty($source['local_ids'])){$stats['current_source_missing']++;$skip[$key]='current_unresolved_source_missing';continue;}
            $country=(int)($source['country_id']??0);if($country!==$expected['country_id']||!isset(HMLGUR_CORE8[$country])){$stats['country_changed']++;$skip[$key]='country_changed';continue;}
            $candidate=hmlgua_current_candidate($source,$country,$literalIndex,$singleIndex,$hotels,$names);if(!($candidate['ok']??false)){$stats['current_guard_failed']++;$skip[$key]=(string)($candidate['reason']??'current_guard_failed');continue;}
            $target=(int)$candidate['target_local_hotel_id'];if($target!==$expected['target_local_hotel_id']){$stats['target_changed']++;$skip[$key]='target_changed';continue;}$routes=$candidate['routes'];if($routes!==$expected['routes']){$stats['routes_changed']++;$skip[$key]='routes_changed';continue;}
            if($provider==='anex'){
                $aid=(int)$external;if(isset($excluded[$aid][$target])){$stats['pair_excluded']++;$skip[$key]='pair_excluded';continue;}$others=array_filter(array_keys($anClaims[$target]??[]),static fn($x)=>(int)$x!==$aid);
            }else{$others=array_filter(array_keys($andClaims[$target]??[]),static fn($x)=>(string)$x!==$external);}
            if($others){$stats['target_occupied']++;$skip[$key]='same_provider_target_occupied';continue;}
            $provisional[$key]=['provider'=>$provider,'external_id'=>$external,'country_id'=>$country,'target_local_hotel_id'=>$target,'routes'=>$routes,'distance_m'=>$candidate['distance_m'],'live'=>(bool)($source['live']??false),'observation_count'=>(int)($source['observation_count']??0),'source_names'=>array_values($source['names']??[]),'source_places'=>array_values($source['places']??[])];
        }
        $groups=[];foreach($provisional as$key=>$r)$groups[$r['provider'].':'.$r['target_local_hotel_id']][]=$key;
        foreach($groups as$keys)if(count($keys)>1)foreach($keys as$key){unset($provisional[$key]);$stats['same_provider_target_collision']++;$skip[$key]='same_provider_target_collision';}
        $stats['planned']=count($provisional);if($stats['planned']>$maxWrites)throw new RuntimeException('write_scope_limit');
        $insertAn=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $updateAnd=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=? AND catalog_sha256=?");
        $mappingDigest=fc_hash([$operation,'literal_geo_union_current_consensus_v1',HMLGUA_SOURCE_RESULT_SHA256]);
        foreach($provisional as$key=>$row){$provider=$row['provider'];$external=$row['external_id'];$target=(int)$row['target_local_hotel_id'];$evidence=['operation_id'=>$operation,'lane'=>'MATCH','provider'=>$provider,'external_id'=>$external,'country_id'=>$row['country_id'],'target_local_hotel_id'=>$target,'routes'=>$row['routes'],'distance_m'=>$row['distance_m'],'live'=>$row['live'],'observation_count'=>$row['observation_count'],'source_names'=>$row['source_names'],'source_places'=>$row['source_places'],'source_operation'=>HMLGUA_SOURCE_OPERATION,'source_result_sha256'=>HMLGUA_SOURCE_RESULT_SHA256,'server_current_recomputed'=>true,'generic_identity_tokens'=>['hotel','hotels','resort','resorts','spa','the','and','ex'],'coordinate_conflict_block_m'=>HMLGUR_COORD_BLOCK_M];
            if($provider==='anex'){
                $aid=(int)$external;if(isset($manual[$aid])||isset($existingAn[$aid])||isset($excluded[$aid][$target])||array_filter(array_keys($anClaims[$target]??[]),static fn($x)=>(int)$x!==$aid))throw new RuntimeException('anex_current_guard_changed:'.$aid);
                $sourceDigest=fc_hash($evidence);$insertAn->execute([$aid,$target,FC_POLICY,$sourceDigest,$mappingDigest]);if($insertAn->rowCount()!==1)throw new RuntimeException('anex_insert_not_one:'.$aid);$existingAn[$aid]=$target;$anClaims[$target][$aid]=true;$written[$key]=['provider'=>'anex','external_id'=>$external,'target_local_hotel_id'=>$target,'source_row_digest'=>$sourceDigest,'mapping_digest'=>$mappingDigest,'routes'=>$row['routes'],'live'=>$row['live']];$stats['written_anex']++;
            }else{
                $old=$andRows[$external]??null;if(!$old||($old['decision_status']??'')!=='pending'||$old['local_hotel_id']!==null)throw new RuntimeException('andromeda_current_guard_changed:'.$external);$oldHash=(string)($old['evidence_sha256']??'');$oldJson=(string)($old['evidence_json']??'');$catalogHash=(string)($old['catalog_sha256']??'');if($oldHash===''||$catalogHash===''||!hash_equals($oldHash,hash('sha256',$oldJson))){$stats['andromeda_evidence_integrity_changed']++;throw new RuntimeException('andromeda_evidence_integrity_changed:'.$external);}if(array_filter(array_keys($andClaims[$target]??[]),static fn($x)=>(string)$x!==$external))throw new RuntimeException('andromeda_target_guard_changed:'.$external);
                $evidence['prior_evidence_sha256']=$oldHash;$ejson=hmlgua_json($evidence);$ehash=hash('sha256',$ejson);$updateAnd->execute([$target,$ehash,$ejson,$external,$oldHash,$catalogHash]);if($updateAnd->rowCount()!==1)throw new RuntimeException('andromeda_update_not_one:'.$external);$andClaims[$target][$external]=true;$andRows[$external]['decision_status']='accepted';$andRows[$external]['local_hotel_id']=$target;$written[$key]=['provider'=>'andromeda','external_id'=>$external,'target_local_hotel_id'=>$target,'evidence_sha256'=>$ehash,'routes'=>$row['routes'],'live'=>$row['live']];$stats['written_andromeda']++;
            }
            $stats['written']++;if($row['live'])$stats['written_live']++;
        }
        if($stats['written']>$maxWrites)throw new RuntimeException('write_scope_limit');$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}

    $readAn=$db->prepare('SELECT catalog_hotel_id,source_row_digest,mapping_digest,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');
    $readAnd=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
    foreach($written as$key=>&$expected){if($expected['provider']==='anex'){$readAn->execute([(int)$expected['external_id']]);$actual=$readAn->fetch(PDO::FETCH_ASSOC);if(!$actual||(int)$actual['catalog_hotel_id']!==$expected['target_local_hotel_id']||(int)$actual['enabled']!==1||$actual['scope']!=='preview'||$actual['approval_policy']!==FC_POLICY||!hash_equals($expected['source_row_digest'],(string)$actual['source_row_digest'])||!hash_equals($expected['mapping_digest'],(string)$actual['mapping_digest']))throw new RuntimeException('post_commit_readback_failed:'.$key);}else{$readAnd->execute([(string)$expected['external_id']]);$actual=$readAnd->fetch(PDO::FETCH_ASSOC);if(!$actual||$actual['decision_status']!=='accepted'||(int)$actual['local_hotel_id']!==$expected['target_local_hotel_id']||!hash_equals($expected['evidence_sha256'],(string)$actual['evidence_sha256'])||!hash_equals($expected['evidence_sha256'],hash('sha256',(string)$actual['evidence_json'])))throw new RuntimeException('post_commit_readback_failed:'.$key);}$expected['readback']='verified';$stats['post_commit_verified']++;}unset($expected);
    $after=fc_coverage($db);ksort($skip,SORT_STRING);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>$stats['written'],'mapping_writes'=>$stats['written'],'supplier_calls'=>0,'tourvisor_calls'=>0,'historical_operations_replayed'=>false,'source_operation'=>HMLGUA_SOURCE_OPERATION,'source_result_sha256'=>HMLGUA_SOURCE_RESULT_SHA256,'source_rows'=>HMLGUA_EXPECTED_SOURCE_ROWS,'stats'=>$stats,'skipped_reason_counts'=>array_count_values(array_values($skip)),'coverage_before'=>$before,'coverage_after'=>$after,'rows'=>array_values($written),'post_commit_readback_verified'=>count($written)===$stats['post_commit_verified']];
}
