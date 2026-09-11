<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_strict_review.php';

const HMRE_OPERATION = 'hotel-match-residual-ensemble-review-1971-20260911-v1';
const HMRE_BASE_OPERATION = 'hotel-match-residual-ensemble-base-1971-20260911-v1';
const HMRE_STRICT_OPERATION = 'hotel-match-residual-ensemble-strict-1971-20260911-v1';
const HMRE_COORD_OPERATION = 'hotel-match-anex-direct-details-coordinate-review-1971-20260911-v1';
const HMRE_COORD_RESULT_SHA256 = 'cc95fac4494f1fbef26e3eadfdf751a4b3caf828752837be885c0e060df88bcc';
const HMRE_SOLD_OPERATION = 'hotel-match-anex-tourvisor-sold-date-details-review-1971-20260911-v1';
const HMRE_SOLD_RESULT_SHA256 = '74504dd19773da26c34d4a63aed31774c8880efcfd97565d1cba69d8b5b09b42';

function hmre_key(string $provider, string|int $external): string {
    return strtolower(trim($provider)).':'.trim((string)$external);
}
function hmre_target_id(array $row): int {
    if (isset($row['target_local_hotel_id'])) return (int)$row['target_local_hotel_id'];
    if (is_array($row['target'] ?? null)) return (int)($row['target']['local_hotel_id'] ?? 0);
    return 0;
}
function hmre_validate_inputs(array $base,array $strict,array $coord,array $sold): void {
    if (($base['status']??null)!=='completed'||($base['operation_id']??null)!==HMRE_BASE_OPERATION||($base['database_writes']??null)!==0||($base['supplier_calls']??null)!==0||!is_array($base['buckets']??null)) throw new RuntimeException('HMRE_BASE_INVALID');
    if (($strict['status']??null)!=='completed'||($strict['operation_id']??null)!==HMRE_STRICT_OPERATION||($strict['database_writes']??null)!==0||($strict['supplier_calls']??null)!==0||!is_array($strict['candidates']??null)) throw new RuntimeException('HMRE_STRICT_INVALID');
    if (($coord['status']??null)!=='completed'||($coord['operation_id']??null)!==HMRE_COORD_OPERATION||($coord['database_writes']??null)!==0||($coord['mapping_writes']??null)!==0||($coord['supplier_calls']??null)!==0||!is_array($coord['prepared']??null)) throw new RuntimeException('HMRE_COORD_INVALID');
    if (($sold['status']??null)!=='completed'||($sold['operation_id']??null)!==HMRE_SOLD_OPERATION||($sold['database_writes']??null)!==0||!is_array($sold['prepared']??null)) throw new RuntimeException('HMRE_SOLD_INVALID');
}
function hmre_evidence_row(string $source,array $row): ?array {
    $target=hmre_target_id($row); if($target<1)return null;
    $critical=is_array($row['name']??null)?($row['name']['critical_ok']??true):true;
    if($critical!==true)return null;
    $distance=$row['distance_m']??($row['guard']['distance_m']??null);
    if($distance!==null&&(float)$distance>5000.0)return null;
    return ['source'=>$source,'target_local_hotel_id'=>$target,'rule'=>(string)($row['rule']??$row['reason']??$row['candidate_class']??$source),'distance_m'=>$distance===null?null:(float)$distance,'row'=>$row];
}
function hmre_decide(array $baseRow,array $evidence): array {
    $baseBucket=(string)($baseRow['bucket']??'needs_extra_evidence');$baseReason=(string)($baseRow['reason']??'unknown');
    if($baseReason==='pair_exclusion_protected')return ['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target'=>0,'sources'=>[]];
    $byTarget=[];foreach($evidence as $ev){$target=(int)($ev['target_local_hotel_id']??0);if($target>0)$byTarget[$target][]=$ev;}
    if(count($byTarget)>1)return ['bucket'=>'hard_conflict','reason'=>'independent_evidence_target_conflict','target'=>0,'sources'=>array_values(array_unique(array_map(static fn($e)=>(string)$e['source'],$evidence)))];
    if(count($byTarget)===1){
        $target=(int)array_key_first($byTarget);$evs=$byTarget[$target];$sources=array_values(array_unique(array_map(static fn($e)=>(string)$e['source'],$evs)));sort($sources,SORT_STRING);
        if($baseReason==='coordinate_conflict_gt_5km'&&!(in_array('direct_details_coordinate',$sources,true)&&in_array('same_sold_date_tourvisor',$sources,true)))return ['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_requires_two_independent_direct_sources','target'=>$target,'sources'=>$sources];
        return ['bucket'=>'auto_accept','reason'=>count($sources)>1?'multi_source_residual_identity':'single_strong_residual_identity','target'=>$target,'sources'=>$sources];
    }
    if($baseBucket==='hard_conflict')return ['bucket'=>'hard_conflict','reason'=>$baseReason,'target'=>hmre_target_id($baseRow),'sources'=>[]];
    if($baseBucket==='manual_last')return ['bucket'=>'manual_last','reason'=>$baseReason,'target'=>hmre_target_id($baseRow),'sources'=>[]];
    return ['bucket'=>'needs_extra_evidence','reason'=>$baseBucket==='auto_accept'?'auto_candidate_failed_strict_ensemble_guard':$baseReason,'target'=>hmre_target_id($baseRow),'sources'=>[]];
}
function hmre_review(PDO $db,array $base,array $strict,array $coord,array $sold,string $operation=HMRE_OPERATION): array {
    if($operation!==HMRE_OPERATION)throw new RuntimeException('HMRE_OPERATION_SCOPE');
    hmre_validate_inputs($base,$strict,$coord,$sold);
    $strictIndex=[];foreach($strict['candidates'] as $row){$provider=(string)($row['provider']??'');$external=(string)($row['external_id']??'');if($provider===''||$external==='')continue;$ev=hmre_evidence_row('current_strict',$row);if($ev!==null)$strictIndex[hmre_key($provider,$external)][]=$ev;}
    $coordIndex=[];foreach($coord['prepared'] as $row){$id=(int)($row['anex_hotel_id']??0);if($id<1)continue;$ev=hmre_evidence_row('direct_details_coordinate',$row);if($ev!==null)$coordIndex[$id][]=$ev;}
    $soldIndex=[];foreach($sold['prepared'] as $row){$id=(int)($row['anex_hotel_id']??0);if($id<1)continue;$ev=hmre_evidence_row('same_sold_date_tourvisor',$row);if($ev!==null)$soldIndex[$id][]=$ev;}

    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $mapped=[];$anexClaimed=[];
        foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC) as $r){$aid=(int)$r['anex_hotel_id'];$hid=(int)$r['catalog_hotel_id'];$mapped[$aid]=true;$anexClaimed[$hid][$aid]=true;}
        foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){$aid=(int)$r['anex_hotel_id'];$hid=(int)$r['catalog_hotel_id'];$anexClaimed[$hid][$aid]=true;}
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $active=[];foreach($db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16)')->fetchAll(PDO::FETCH_ASSOC) as $r)$active[(int)$r['id']]=$r;
        $andPending=array_fill_keys(array_map('strval',$db->query("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL")->fetchAll(PDO::FETCH_COLUMN)),true);
        $andClaimed=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$andClaimed[(int)$r['local_hotel_id']][(string)$r['external_hotel_id']]=true;
        $anObs=[];foreach($db->query('SELECT anex_hotel_id,MAX(search_count) search_count,MAX(last_seen_utc) last_seen_utc FROM anex_search_hotel_observations GROUP BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r)$anObs[(int)$r['anex_hotel_id']]=$r;
        $andObs=[];foreach($db->query("SELECT external_hotel_id,COUNT(*) observation_count,MAX(observed_at_utc) last_seen_utc FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' GROUP BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r)$andObs[(string)$r['external_hotel_id']]=$r;

        $rows=[];$dropped=['protected_current'=>0,'not_pending_current'=>0,'inactive_target'=>0,'target_claimed'=>0,'pair_excluded'=>0,'country_target_mismatch'=>0,'stale_overlay'=>0];
        foreach(['auto_accept','needs_extra_evidence','hard_conflict','manual_last'] as $bucket){
            foreach(($base['buckets'][$bucket]??[]) as $baseRow){
                if(!is_array($baseRow))continue;$provider=(string)($baseRow['provider']??'');$external=(string)($baseRow['external_id']??'');$country=(int)($baseRow['country_id']??0);if($provider===''||$external===''||!isset(MBR_CORE8[$country]))continue;$key=hmre_key($provider,$external);
                if($provider==='anex'){$aid=(int)$external;if(isset($manual[$aid])||isset($mapped[$aid])){$dropped['protected_current']++;continue;}}
                elseif($provider==='andromeda'){if(!isset($andPending[$external])){$dropped['not_pending_current']++;continue;}}
                else continue;
                $evidence=$strictIndex[$key]??[];
                if($provider==='anex'){$aid=(int)$external;foreach(array_merge($coordIndex[$aid]??[],$soldIndex[$aid]??[]) as $ev)$evidence[]=$ev;}

                $filtered=[];
                foreach($evidence as $ev){
                    $target=(int)($ev['target_local_hotel_id']??0);
                    if(!isset($active[$target])){$dropped['inactive_target']++;continue;}
                    if((int)$active[$target]['country_id']!==$country){$dropped['country_target_mismatch']++;continue;}
                    if($provider==='anex'){
                        $aid=(int)$external;
                        if(isset($excluded[$aid][$target])){$dropped['pair_excluded']++;continue;}
                        $other=array_filter(array_keys($anexClaimed[$target]??[]),static fn($x)=>(int)$x!==$aid);
                        if($other){$dropped['target_claimed']++;continue;}
                    }else{
                        $other=array_filter(array_keys($andClaimed[$target]??[]),static fn($x)=>(string)$x!==(string)$external);
                        if($other){$dropped['target_claimed']++;continue;}
                    }
                    $filtered[]=$ev;
                }
                $evidence=$filtered;
                $decision=hmre_decide($baseRow,$evidence);$target=(int)$decision['target'];
                if($target>0&&(!isset($active[$target])||(int)$active[$target]['country_id']!==$country)){$decision=['bucket'=>'needs_extra_evidence','reason'=>'base_target_not_current','target'=>0,'sources'=>[]];$dropped['stale_overlay']++;$target=0;}
                $obsCount=$provider==='anex'?(int)($anObs[(int)$external]['search_count']??0):(int)($andObs[$external]['observation_count']??0);$lastSeen=$provider==='anex'?($anObs[(int)$external]['last_seen_utc']??null):($andObs[$external]['last_seen_utc']??null);
                $rows[$key]=['provider'=>$provider,'external_id'=>$external,'country_id'=>$country,'live'=>$obsCount>0,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen,'bucket'=>$decision['bucket'],'reason'=>$decision['reason'],'base_bucket'=>$baseRow['bucket']??$bucket,'base_reason'=>$baseRow['reason']??null,'target_local_hotel_id'=>$target?:null,'target_name'=>$target&&isset($active[$target])?$active[$target]['name']:($baseRow['target']['name']??null),'evidence_sources'=>$decision['sources'],'evidence_count'=>count($evidence),'base'=>$baseRow];
            }
        }

        $autoByProviderTarget=[];foreach($rows as $key=>$row)if($row['bucket']==='auto_accept'&&$row['target_local_hotel_id']!==null)$autoByProviderTarget[$row['provider'].':'.$row['target_local_hotel_id']][]=$key;
        $collisionDemoted=0;foreach($autoByProviderTarget as $keys)if(count($keys)>1){foreach($keys as $key){$rows[$key]['bucket']='needs_extra_evidence';$rows[$key]['reason']='same_provider_target_collision';$collisionDemoted++;}}
        $buckets=['auto_accept'=>[],'needs_extra_evidence'=>[],'hard_conflict'=>[],'manual_last'=>[]];foreach($rows as $row)$buckets[$row['bucket']][]=$row;
        foreach($buckets as &$set)usort($set,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['observation_count']<=>$a['observation_count'])?:($a['country_id']<=>$b['country_id'])?:strcmp($a['provider'],$b['provider'])?:strcmp((string)$a['external_id'],(string)$b['external_id']));unset($set);
        $reasonCounts=[];$countryCounts=[];$sourceCounts=[];$providerAuto=['anex'=>0,'andromeda'=>0];$liveAuto=0;
        foreach($buckets as $bucket=>$set){foreach($set as $row){$reasonCounts[$bucket][$row['reason']]=($reasonCounts[$bucket][$row['reason']]??0)+1;$countryCounts[$bucket][(string)$row['country_id']]=($countryCounts[$bucket][(string)$row['country_id']]??0)+1;if($bucket==='auto_accept'){if(isset($providerAuto[$row['provider']]))$providerAuto[$row['provider']]++;if($row['live'])$liveAuto++;foreach($row['evidence_sources'] as $src)$sourceCounts[$src]=($sourceCounts[$src]??0)+1;}}}
        foreach($reasonCounts as &$x)ksort($x);unset($x);ksort($sourceCounts);
        $counts=['classified_total'=>count($rows),'auto_accept'=>count($buckets['auto_accept']),'needs_extra_evidence'=>count($buckets['needs_extra_evidence']),'hard_conflict'=>count($buckets['hard_conflict']),'manual_last'=>count($buckets['manual_last']),'auto_anex'=>$providerAuto['anex'],'auto_andromeda'=>$providerAuto['andromeda'],'auto_live'=>$liveAuto,'collision_demoted'=>$collisionDemoted,'strict_evidence_rows'=>count($strictIndex),'coordinate_evidence_rows'=>count($coordIndex),'sold_date_evidence_rows'=>count($soldIndex),'coordinate_sold_overlap'=>count(array_intersect(array_keys($coordIndex),array_keys($soldIndex)))];
        $db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'mass_current_residual_ensemble_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'counts'=>$counts,'dropped'=>$dropped,'reason_counts'=>$reasonCounts,'country_counts'=>$countryCounts,'auto_evidence_sources'=>$sourceCounts,'buckets'=>$buckets,'guards'=>['core8_only'=>true,'manual_decisions_overwritten'=>false,'pair_exclusions_overwritten'=>false,'existing_mappings_overwritten'=>false,'same_provider_existing_target_claim_blocks_auto'=>true,'coordinate_conflict_auto_block_m'=>5000,'coordinate_conflict_override_requires_direct_details_and_same_sold_date'=>true,'generic_hotel_resort_spa_not_identity'=>true,'critical_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'same_provider_target_collision_blocks_auto'=>true,'database_writes'=>0,'supplier_calls'=>0]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded mass residual workflow\n");exit(64);}
