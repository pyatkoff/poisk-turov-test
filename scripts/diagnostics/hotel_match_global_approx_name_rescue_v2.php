<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_global_approx_name_rescue.php';

const HMGAN2_OPERATION = 'hotel-match-global-approx-name-rescue-1971-20260911-v2';
const HMGAN2_BASE_ARTIFACT = '10263237042';
const HMGAN2_LOCALITY_MIN_DOCS = 5;
const HMGAN2_LOCALITY_MIN_SHARE = 0.03;

function hmgan2_allowlist(): array {
    return ['190031'=>1441,'2000068203'=>115356,'2000121515'=>143533,'2000034238'=>16944,'2000081107'=>83106,'2000112459'=>138070,'2000023151'=>28630,'2000024852'=>47216,'2000025411'=>37401,'2000026984'=>31775,'2000030646'=>43474,'2000031001'=>44665,'2000035013'=>51262,'2000037585'=>55945,'2000039594'=>57823,'2000039948'=>4301,'2000041217'=>151606,'2000054721'=>23743,'2000059960'=>161981,'2000062315'=>159008,'2000074362'=>22946,'2000074453'=>82574,'2000084803'=>103965,'2000084805'=>101850,'2000086056'=>108634,'2000090135'=>117718,'2000091531'=>65757,'2000092528'=>124296,'2000094199'=>122319,'2000097038'=>76154,'2000097301'=>75234,'2000098734'=>49250,'2000103858'=>128586,'2000116720'=>49185,'232642'=>2925,'256738'=>2850,'280016'=>4406,'34673'=>4203,'374817'=>24467,'412971'=>695,'67976'=>1672];
}
function hmgan2_locality_key(array $hotel): string {
    $country=(int)($hotel['country_id']??0);
    $region=trim((string)($hotel['region_name']??''));
    if($region==='')$region=trim((string)($hotel['subregion_name']??''));
    return $country.'|'.fc_norm(hmgcr_latin($region));
}
function hmgan2_locality_common(array $hotels): array {
    $totals=[];$docs=[];
    foreach($hotels as $hotel){
        $key=hmgan2_locality_key($hotel);if($key==='0|'||str_ends_with($key,'|'))continue;
        $totals[$key]=($totals[$key]??0)+1;
        $seen=[];foreach(hmgan_tokens((string)($hotel['name']??''),[]) as $t)$seen[(string)$t]=true;
        foreach(array_keys($seen) as $t)$docs[$key][$t]=($docs[$key][$t]??0)+1;
    }
    $common=[];
    foreach($docs as $key=>$counts){$n=max(1,(int)($totals[$key]??0));foreach($counts as $token=>$count){if($count>=HMGAN2_LOCALITY_MIN_DOCS&&($count/$n)>=HMGAN2_LOCALITY_MIN_SHARE)$common[$key][$token]=['docs'=>$count,'share'=>round($count/$n,6)];}}
    return ['totals'=>$totals,'common'=>$common];
}
function hmgan2_identity_pairs(array $pair,array $target,array $locality): array {
    $key=hmgan2_locality_key($target);$common=$locality['common'][$key]??[];$identity=[];$geo=[];
    foreach(($pair['pairs']??[]) as $p){$targetToken=(string)($p['target']??'');if($targetToken!==''&&isset($common[$targetToken])){$q=$p;$q['locality_docs']=$common[$targetToken]['docs'];$q['locality_share']=$common[$targetToken]['share'];$geo[]=$q;}else{$identity[]=$p;}}
    return ['identity'=>$identity,'locality_common'=>$geo,'identity_aligned'=>count($identity),'locality_common_aligned'=>count($geo),'locality_key'=>$key,'locality_hotel_count'=>(int)($locality['totals'][$key]??0)];
}
function hmgan2_review(PDO $db,string $operation=HMGAN2_OPERATION): array {
    if($operation!==HMGAN2_OPERATION)throw new RuntimeException('HMGAN2_OPERATION_SCOPE');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);[$hotels,$names]=mbr_catalog($db);$index=hmgan_build_index($hotels,$names);$locality=hmgan2_locality_common($hotels);$shaCountry=fc_sha_countries($db);$allow=hmgan2_allowlist();
        $rows=[];foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchAll(PDO::FETCH_ASSOC) as $r)$rows[(string)$r['external_hotel_id']]=$r;
        $latest=[];$obsCount=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$obsCount[$id]=($obsCount[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}
        $claims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$claims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $prepared=[];$demoted=[];$stale=[];$stats=['allowlist'=>count($allow),'current_rechecked'=>0,'prepared'=>0,'prepared_live'=>0,'demoted_locality_common'=>0,'stale_or_changed'=>0,'star_mismatch'=>0,'locality_tokens_removed'=>0];
        foreach($allow as $external=>$expectedTarget){
            $r=$rows[$external]??null;if(!$r||($r['decision_status']??'')!=='pending'||$r['local_hotel_id']!==null){$stale[]=['external_id'=>$external,'expected_target_local_hotel_id'=>$expectedTarget,'reason'=>'source_no_longer_pending_null'];$stats['stale_or_changed']++;continue;}
            $obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(HMGAN_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMGAN_CORE8[$country])){$stale[]=['external_id'=>$external,'expected_target_local_hotel_id'=>$expectedTarget,'reason'=>'current_country_not_core8'];$stats['stale_or_changed']++;continue;}
            $source=hmgcr_andromeda_source($r,$obs);$d=hmgan_decide($source,$country,$index,$hotels,$names,$claims,[]);$stats['current_rechecked']++;
            if(($d['bucket']??'')!=='prepared'||(int)($d['target_local_hotel_id']??0)!==(int)$expectedTarget){$stale[]=['external_id'=>$external,'expected_target_local_hotel_id'=>$expectedTarget,'current_bucket'=>$d['bucket']??null,'current_reason'=>$d['reason']??null,'current_target_local_hotel_id'=>$d['target_local_hotel_id']??null,'reason'=>'current_resolver_disagrees_with_v1'];$stats['stale_or_changed']++;continue;}
            $target=$hotels[(int)$expectedTarget]??null;if(!$target){$stale[]=['external_id'=>$external,'expected_target_local_hotel_id'=>$expectedTarget,'reason'=>'target_missing_current_catalog'];$stats['stale_or_changed']++;continue;}
            $anchors=hmgan2_identity_pairs($d['pair'],$target,$locality);$stats['locality_tokens_removed']+=(int)$anchors['locality_common_aligned'];
            $starMismatch=$source['category']!==null&&$target['category']!==null&&(int)$source['category']!==(int)$target['category'];
            $row=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'target_local_hotel_id'=>(int)$expectedTarget,'target_name'=>$target['name'],'target_region'=>$target['region_name'],'target_subregion'=>$target['subregion_name'],'source_names'=>$source['names'],'source_places'=>$source['places'],'live'=>(int)($obsCount[$external]??0)>0,'observation_count'=>(int)($obsCount[$external]??0),'star_mismatch'=>$starMismatch,'distance_m'=>$d['distance_m']??null,'place_match'=>$d['place_match']??null,'score_margin'=>$d['score_margin']??null,'pair'=>$d['pair'],'identity_anchors'=>$anchors];
            if($anchors['identity_aligned']<2){$row['reason']='locality_common_identity_anchors_lt_2';$demoted[]=$row;$stats['demoted_locality_common']++;continue;}
            $row['reason']='approx_name_v2_locality_hardened';$prepared[]=$row;$stats['prepared']++;if($row['live'])$stats['prepared_live']++;if($starMismatch)$stats['star_mismatch']++;
        }
        $targetUse=[];foreach($prepared as $i=>$row)$targetUse[(int)$row['target_local_hotel_id']][]=$i;$collisions=[];foreach($targetUse as $target=>$idxs)if(count($idxs)>1)$collisions[$target]=$idxs;
        if($collisions){$keep=[];foreach($prepared as $i=>$row){if(isset($collisions[(int)$row['target_local_hotel_id']])){$row['reason']='v2_same_provider_target_collision';$demoted[]=$row;$stats['prepared']--;if($row['live'])$stats['prepared_live']--;if($row['star_mismatch'])$stats['star_mismatch']--; }else$keep[]=$row;}$prepared=$keep;}
        $stats['demoted_total']=count($demoted);$stats['stale_or_changed']=count($stale);usort($prepared,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['observation_count']<=>$a['observation_count'])?:strcmp((string)$a['external_id'],(string)$b['external_id']));$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'v1_artifact_allowlist_current_recheck_locality_hardening_read_only','base_operation'=>HMGAN_OPERATION,'base_artifact'=>HMGAN2_BASE_ARTIFACT,'base_candidates'=>count($allow),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'policy'=>['base_v1_never_replayed'=>true,'server_current_pure_resolver_recheck'=>true,'locality_common_min_docs'=>HMGAN2_LOCALITY_MIN_DOCS,'locality_common_min_share'=>HMGAN2_LOCALITY_MIN_SHARE,'min_non_local_identity_anchors'=>2,'same_provider_occupancy_guard'=>true,'core8_only'=>true,'coordinate_conflict_block_m'=>HMGAN_COORD_BLOCK_M],'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'demoted'=>$demoted,'stale_or_changed'=>$stale];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if (!defined('FC_LIBRARY_ONLY')) { fwrite(STDERR,"library-only diagnostic; run through guarded MATCH workflow\n"); exit(2); }
