<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_single_token_geo_mass_review.php';

const HMLGUR_OPERATION='hotel-match-literal-geo-union-review-1971-20260911-v1';
const HMLGUR_COORD_BLOCK_M=5000.0;
const HMLGUR_COORD_ACCEPT_M=1000.0;
const HMLGUR_CORE8=[1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmlgur_target_ids(array $literalTargets,array $singleTargets):array{
    $ids=[];
    foreach(array_keys($literalTargets) as $id)$ids[(int)$id]=true;
    foreach(array_keys($singleTargets) as $id)$ids[(int)$id]=true;
    $out=array_map('intval',array_keys($ids));sort($out,SORT_NUMERIC);return $out;
}
function hmlgur_consensus(array $literalTargets,array $singleTargets):array{
    $ids=hmlgur_target_ids($literalTargets,$singleTargets);
    if(!$ids)return ['state'=>'none','target_local_hotel_id'=>null,'candidate_target_ids'=>[]];
    if(count($ids)!==1)return ['state'=>'conflict','target_local_hotel_id'=>null,'candidate_target_ids'=>$ids];
    return ['state'=>'unique','target_local_hotel_id'=>$ids[0],'candidate_target_ids'=>$ids];
}
function hmlgur_review(PDO $db,string $operation=HMLGUR_OPERATION):array{
    if($operation!==HMLGUR_OPERATION)throw new RuntimeException('HMLGUR_OPERATION_SCOPE');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);$an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);[$hotels,$names]=mbr_catalog($db);
        $literalIndex=hmelmr_build_index($hotels,$names);$singleIndex=hmstg_index($hotels,$names);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $andStatus=[];foreach($db->query("SELECT external_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchAll(PDO::FETCH_ASSOC)as$r)$andStatus[(string)$r['external_hotel_id']]=(string)$r['decision_status'];
        $anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $stats=['examined'=>0,'unresolved_anex'=>0,'unresolved_andromeda'=>0,'live_examined'=>0,'raw_target_none'=>0,'source_cross_lane_target_conflict'=>0,'literal_route_prepared'=>0,'single_geo_route_prepared'=>0,'both_routes_same_target'=>0,'coordinate_conflict_gt_5km'=>0,'pair_exclusion_block'=>0,'target_occupancy_block'=>0,'manual_protected'=>0,'andromeda_nonpending_protected'=>0,'global_uniqueness_demotions'=>0,'prepared'=>0,'prepared_anex'=>0,'prepared_andromeda'=>0,'prepared_live'=>0,'needs_extra'=>0,'hard_conflict'=>0];
        $nodes=[];
        foreach($an as$aid=>$s){if($s['local_ids']||!isset(HMLGUR_CORE8[(int)$s['country_id']]))continue;$stats['unresolved_anex']++;if(isset($manual[(int)$aid])){$stats['manual_protected']++;continue;}$nodes[hmigr_key('anex',$aid)]=['provider'=>'anex','external_id'=>(string)$aid,'country_id'=>(int)$s['country_id'],'live'=>(bool)$s['live'],'observation_count'=>(int)$s['observation_count'],'source'=>$s];}
        foreach($and as$id=>$s){if($s['local_ids']||!isset(HMLGUR_CORE8[(int)$s['country_id']]))continue;$stats['unresolved_andromeda']++;if(($andStatus[(string)$id]??'')!=='pending'){$stats['andromeda_nonpending_protected']++;continue;}$nodes[hmigr_key('andromeda',$id)]=['provider'=>'andromeda','external_id'=>(string)$id,'country_id'=>(int)$s['country_id'],'live'=>(bool)$s['live'],'observation_count'=>(int)$s['observation_count'],'source'=>$s];}
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
            if($singleEv!==null&&count($singleTargets)===1&&count(hmstg_keys($source['names']??[]))>0){$coordOk=$singleEv['distance_m']!==null&&(float)$singleEv['distance_m']<=HMLGUR_COORD_ACCEPT_M;$placeOk=(bool)($singleEv['place_match']??false);$singleEligible=$coordOk||$placeOk;}
            $dist=$literalEv['distance_m']??($singleEv['distance_m']??null);
            if($dist!==null&&(float)$dist>HMLGUR_COORD_BLOCK_M){$stats['coordinate_conflict_gt_5km']++;$rows[$key]=['bucket'=>'hard_conflict','reason'=>'literal_geo_union_coordinate_conflict_gt_5km','target_local_hotel_id'=>$target,'distance_m'=>$dist]+$node;continue;}
            if(!$literalEligible&&!$singleEligible){$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'unique_raw_target_but_route_evidence_insufficient','target_local_hotel_id'=>$target]+$node;continue;}
            if($node['provider']==='anex'&&isset($excluded[(int)$node['external_id']][$target])){$stats['pair_exclusion_block']++;$rows[$key]=['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target_local_hotel_id'=>$target]+$node;continue;}
            $other=$node['provider']==='anex'?hmeumr_other($anClaims[$target]??[],(int)$node['external_id']):hmeumr_other($andClaims[$target]??[],(string)$node['external_id']);
            if($other){$stats['target_occupancy_block']++;$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'same_provider_target_occupied','target_local_hotel_id'=>$target,'other_claims'=>$other]+$node;continue;}
            $routes=[];if($literalEligible){$routes[]='exact_ordered_literal';$stats['literal_route_prepared']++;}if($singleEligible){$routes[]=$coordOk?'single_token_coord_le_1km':'single_token_exact_place';$stats['single_geo_route_prepared']++;}if(count($routes)>1)$stats['both_routes_same_target']++;
            $ev=$literalEv??$singleEv;$rows[$key]=['bucket'=>'prepared','reason'=>'literal_geo_union_consensus','target_local_hotel_id'=>$target,'routes'=>$routes,'evidence'=>$ev]+$node;
        }
        $groups=[];foreach($rows as$key=>$r)if(($r['bucket']??'')==='prepared')$groups[$r['provider'].':'.$r['target_local_hotel_id']][]=$key;
        foreach($groups as$keys)if(count($keys)>1)foreach($keys as$key){$rows[$key]['bucket']='needs_extra_evidence';$rows[$key]['reason']='same_provider_union_target_collision';$stats['global_uniqueness_demotions']++;}
        $prepared=[];$needs=[];$hard=[];foreach($rows as$r){unset($r['source']);if($r['bucket']==='prepared'){$prepared[]=$r;$stats['prepared']++;$stats[$r['provider']==='anex'?'prepared_anex':'prepared_andromeda']++;if($r['live'])$stats['prepared_live']++;}elseif($r['bucket']==='hard_conflict'){$hard[]=$r;$stats['hard_conflict']++;}else{$needs[]=$r;$stats['needs_extra']++;}}
        $sort=static fn($a,$b)=>(($b['live']??false)<=>($a['live']??false))?:((int)($b['observation_count']??0)<=>(int)($a['observation_count']??0))?:strcmp((string)$a['external_id'],(string)$b['external_id']);usort($prepared,$sort);usort($needs,$sort);usort($hard,$sort);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_core8_literal_and_single_geo_union_consensus_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'needs_extra_evidence'=>$needs,'hard_conflicts'=>$hard,'guards'=>['full_current_core8'=>true,'literal_lane_recomputed'=>true,'single_token_geo_lane_recomputed'=>true,'per_source_all_raw_candidate_targets_must_agree'=>true,'source_cross_lane_conflict_blocks'=>true,'coordinate_conflict_block_m'=>HMLGUR_COORD_BLOCK_M,'single_token_independent_geo_required'=>true,'manual_decisions_protected'=>true,'pair_exclusions_protected'=>true,'same_provider_target_occupancy_protected'=>true,'global_same_provider_target_one_to_one'=>true,'generic_tokens_dropped_only'=>['hotel','hotels','resort','resorts','spa','the','and','ex'],'stars_not_identity'=>true]];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){$root=realpath(__DIR__.'/../..');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');echo fc_json(hmlgur_review(v2_data_db(),HMLGUR_OPERATION)),"\n";}
