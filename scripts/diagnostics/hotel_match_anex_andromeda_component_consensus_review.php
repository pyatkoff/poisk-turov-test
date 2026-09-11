<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_andromeda_cluster_local_review.php';

const HMACCR_OPERATION='hotel-match-anex-andromeda-component-consensus-review-1971-20260911-v1';
const HMACCR_COORD_BLOCK_M=5000.0;
const HMACCR_COORD_DIRECT_M=1000.0;

function hmaccr_node(string $provider,int|string $id):string{return $provider.':'.(string)$id;}
function hmaccr_provider(string $node):string{return str_starts_with($node,'a:')?'anex':'andromeda';}
function hmaccr_id(string $node):string{return substr($node,2);}
function hmaccr_route_rank(string $route):int{
    return match($route){'mutual_exact_3plus'=>4,'mutual_exact_geo'=>3,'mutual_exact_no_geo'=>2,'mutual_high_fuzzy_geo'=>1,default=>0};
}
function hmaccr_components(array $adj):array{
    $seen=[];$out=[];
    foreach(array_keys($adj) as$start){
        if(isset($seen[$start]))continue;
        $stack=[$start];$seen[$start]=true;$nodes=[];
        while($stack){$n=array_pop($stack);$nodes[]=$n;foreach(array_keys($adj[$n]??[])as$m)if(!isset($seen[$m])){$seen[$m]=true;$stack[]=$m;}}
        sort($nodes,SORT_STRING);$out[]=$nodes;
    }
    usort($out,static fn($x,$y)=>count($y)<=>count($x)?:strcmp((string)($x[0]??''),(string)($y[0]??'')));
    return $out;
}
function hmaccr_source_local_guard(array $source,array $target,array $targetNames,array $edge):array{
    $tp=[(string)($target['region_name']??''),(string)($target['subregion_name']??'')];
    $pair=hmgcr_best_pair($source['names'],$targetNames,$source['places'],$tp);
    $distance=fc_dist($source['latitude'],$source['longitude'],$target['latitude']??null,$target['longitude']??null);
    $place=hmgcr_place($source['places'],$tp);
    if(!($pair['critical_ok']??false))return['bucket'=>'hard_conflict','reason'=>'source_local_critical_qualifier_conflict','pair'=>$pair,'distance_m'=>$distance===null?null:round((float)$distance,2),'place_match'=>$place];
    if($distance!==null&&(float)$distance>HMACCR_COORD_BLOCK_M)return['bucket'=>'hard_conflict','reason'=>'source_local_coordinate_conflict_gt_5km','pair'=>$pair,'distance_m'=>round((float)$distance,2),'place_match'=>$place];
    $shared=(int)($pair['shared']??0);$direct=$place||($distance!==null&&(float)$distance<=HMACCR_COORD_DIRECT_M);
    $route=(string)($edge['route']??'');$identity=(int)($edge['identity_anchors']['identity_aligned']??0);
    $localExact=($pair['exact_bag']??false)&&$shared>=2;
    $bridgeExact=in_array($route,['mutual_exact_3plus','mutual_exact_geo'],true)&&$identity>=2;
    $bridgeNoGeo=$route==='mutual_exact_no_geo'&&$identity>=3&&$shared>=1;
    if($localExact||($bridgeExact&&$direct)||$bridgeNoGeo){
        return['bucket'=>'prepared','reason'=>$localExact?'component_consensus_local_exact':($direct?'component_consensus_supplier_exact_direct_geo':'component_consensus_supplier_exact_no_geo'),'pair'=>$pair,'distance_m'=>$distance===null?null:round((float)$distance,2),'place_match'=>$place,'edge_route'=>$route,'edge_identity_anchors'=>$identity];
    }
    return['bucket'=>'near','reason'=>'component_consensus_local_support_insufficient','pair'=>$pair,'distance_m'=>$distance===null?null:round((float)$distance,2),'place_match'=>$place,'edge_route'=>$route,'edge_identity_anchors'=>$identity];
}
function hmaccr_review(PDO $db,string $operation=HMACCR_OPERATION):array{
    if($operation!==HMACCR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);
        $an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);$andIndex=hmamgr_index($and);$andLoc=hmadcrh_locality_common($and);
        [$hotels,$names]=mbr_catalog($db);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];

        $stats=['anex_rows'=>count($an),'andromeda_rows'=>count($and),'supplier_edges_examined'=>0,'supplier_edges_safe'=>0,'components'=>0,'components_single_local'=>0,'components_no_local'=>0,'components_multi_local'=>0,'nodes_in_single_local_components'=>0,'unresolved_nodes_examined'=>0,'direct_labeled_edge_candidates'=>0,'prepared_raw'=>0,'prepared'=>0,'prepared_anex'=>0,'prepared_andromeda'=>0,'prepared_live'=>0,'prepared_live_observations'=>0,'manual_block'=>0,'pair_exclusion_block'=>0,'target_occupancy_block'=>0,'local_guard_hard'=>0,'local_guard_near'=>0,'provider_target_collision_demotions'=>0];
        $adj=[];$edgeByNodes=[];$hardComponents=[];$near=[];$bench=[];
        foreach($an as$aid=>$a){
            $pool=hmamgr_candidates($a,$and,$andIndex);
            foreach($pool as$andId=>$edge){
                $stats['supplier_edges_examined']++;
                if(($edge['route']??null)===null||$edge['route']==='coordinate_conflict')continue;
                $b=$and[$andId]??null;if(!$b)continue;
                $anchors=hmadcrh_identity_anchors($edge['pair'],$b,$andLoc);
                if((int)$anchors['identity_aligned']<2)continue;
                $validation=hmamgr_validation($a['local_ids'],$b['local_ids']);
                if($validation==='different_local')continue;
                $stats['supplier_edges_safe']++;
                $na=hmaccr_node('a',(int)$aid);$nd=hmaccr_node('d',(string)$andId);
                $adj[$na][$nd]=true;$adj[$nd][$na]=true;
                $e=$edge;$e['identity_anchors']=$anchors;$e['validation']=$validation;
                $edgeByNodes[$na][$nd]=$e;$edgeByNodes[$nd][$na]=$e;
            }
        }
        $components=hmaccr_components($adj);$stats['components']=count($components);$raw=[];
        foreach($components as$ci=>$nodes){
            $locals=[];$anNodes=[];$andNodes=[];
            foreach($nodes as$n){
                $provider=hmaccr_provider($n);$id=hmaccr_id($n);
                $row=$provider==='anex'?($an[(string)$id]??null):($and[(string)$id]??null);
                if(!$row)continue;
                foreach($row['local_ids']??[]as$lid)$locals[(int)$lid]=true;
                if($provider==='anex')$anNodes[]=(int)$id;else$andNodes[]=(string)$id;
            }
            $localIds=array_keys($locals);
            if(!$localIds){$stats['components_no_local']++;continue;}
            if(count($localIds)>1){$stats['components_multi_local']++;$hardComponents[]=['component'=>$ci,'anex_ids'=>$anNodes,'andromeda_ids'=>$andNodes,'local_ids'=>array_map('intval',$localIds),'reason'=>'component_multiple_existing_local_labels'];continue;}
            $stats['components_single_local']++;$stats['nodes_in_single_local_components']+=count($nodes);
            $target=(int)$localIds[0];$t=$hotels[$target]??null;if(!$t){$hardComponents[]=['component'=>$ci,'local_ids'=>[$target],'reason'=>'component_local_target_missing'];continue;}
            $tn=$names[$target]??[(string)$t['name']];
            foreach($nodes as$n){
                $provider=hmaccr_provider($n);$id=hmaccr_id($n);$source=$provider==='anex'?($an[(string)$id]??null):($and[(string)$id]??null);if(!$source)continue;
                if($source['local_ids']??[]){$bench[]=['provider'=>$provider,'source_id'=>$provider==='anex'?(int)$id:(string)$id,'target_local_hotel_id'=>$target,'component'=>$ci,'component_local_ids'=>[$target]];continue;}
                $stats['unresolved_nodes_examined']++;
                $labeled=[];
                foreach(array_keys($adj[$n]??[])as$other){
                    $op=hmaccr_provider($other);$oid=hmaccr_id($other);$orow=$op==='anex'?($an[(string)$oid]??null):($and[(string)$oid]??null);if(!$orow)continue;
                    if(!in_array($target,array_map('intval',$orow['local_ids']??[]),true))continue;
                    $e=$edgeByNodes[$n][$other]??null;if(!$e)continue;
                    if(hmaccr_route_rank((string)($e['route']??''))<2)continue;
                    $labeled[]=['node'=>$other,'edge'=>$e,'neighbor_local_ids'=>$orow['local_ids'],'neighbor_names'=>$orow['names'],'neighbor_places'=>$orow['places']];
                }
                if(!$labeled)continue;
                $stats['direct_labeled_edge_candidates']++;
                usort($labeled,static fn($x,$y)=>hmaccr_route_rank((string)$y['edge']['route'])<=>hmaccr_route_rank((string)$x['edge']['route'])?:((float)($y['edge']['pair']['rank_score']??0)<=>(float)($x['edge']['pair']['rank_score']??0)));
                $best=$labeled[0];$guard=hmaccr_source_local_guard($source,$t,$tn,$best['edge']);
                $row=['provider'=>$provider,'source_id'=>$provider==='anex'?(int)$id:(string)$id,'country_id'=>(int)$source['country_id'],'target_local_hotel_id'=>$target,'target_name'=>$t['name'],'target_region'=>$t['region_name'],'target_subregion'=>$t['subregion_name'],'source_names'=>$source['names'],'source_places'=>$source['places'],'source_live'=>(bool)$source['live'],'source_observation_count'=>(int)$source['observation_count'],'component'=>$ci,'component_size'=>count($nodes),'component_anex_ids'=>$anNodes,'component_andromeda_ids'=>$andNodes,'component_local_ids'=>[$target],'direct_labeled_neighbors'=>$labeled,'local_guard'=>$guard];
                if(($guard['bucket']??'')==='hard_conflict'){$stats['local_guard_hard']++;$near[]=$row+['reason'=>$guard['reason']];continue;}
                if(($guard['bucket']??'')!=='prepared'){$stats['local_guard_near']++;$near[]=$row+['reason'=>$guard['reason']];continue;}
                if($provider==='anex'&&isset($manual[(int)$id])){$stats['manual_block']++;$near[]=$row+['reason'=>'anex_manual_protected'];continue;}
                if($provider==='anex'&&isset($excluded[(int)$id][$target])){$stats['pair_exclusion_block']++;$near[]=$row+['reason'=>'anex_pair_exclusion'];continue;}
                $others=$provider==='anex'?hmaclr_other($anClaims[$target]??[],(int)$id):hmaclr_other($andClaims[$target]??[],(string)$id);
                if($others){$stats['target_occupancy_block']++;$near[]=$row+['reason'=>'same_provider_target_occupied','other_provider_claims'=>$others];continue;}
                $row['reason']=$guard['reason'];$raw[]=$row;$stats['prepared_raw']++;
            }
        }
        $by=[];foreach($raw as$i=>$r)$by[$r['provider'].':'.$r['target_local_hotel_id']][]=$i;$demote=[];foreach($by as$idxs)if(count($idxs)>1)foreach($idxs as$i)$demote[$i]=true;
        $prepared=[];foreach($raw as$i=>$r){if(isset($demote[$i])){$stats['provider_target_collision_demotions']++;$near[]=$r+['reason'=>'component_provider_target_not_unique'];continue;}$prepared[]=$r;$stats['prepared']++;if($r['provider']==='anex')$stats['prepared_anex']++;else$stats['prepared_andromeda']++;if($r['source_live']){$stats['prepared_live']++;$stats['prepared_live_observations']+=(int)$r['source_observation_count'];}}
        usort($prepared,static fn($x,$y)=>((int)$y['source_live']<=>(int)$x['source_live'])?:((int)$y['source_observation_count']<=>(int)$x['source_observation_count'])?:strcmp((string)$x['provider'].':'.$x['source_id'],(string)$y['provider'].':'.$y['source_id']));
        $db->commit();
        return['status'=>'completed','operation_id'=>$operation,'mode'=>'current_core8_supplier_component_local_consensus_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'benchmark'=>$bench,'hard_components'=>$hardComponents,'near'=>$near,'guards'=>['supplier_edges_recomputed_current'=>true,'connected_component_single_local_label_required'=>true,'direct_labeled_neighbor_required'=>true,'transitive_only_inference_forbidden'=>true,'minimum_supplier_edge_route_rank'=>2,'supplier_locality_identity_anchors_min'=>2,'source_local_coordinate_conflict_block_m'=>HMACCR_COORD_BLOCK_M,'critical_qualifiers_supplier_and_local_required'=>true,'manual_pair_exclusion_same_provider_occupancy_protected'=>true,'provider_local_target_uniqueness_required'=>true,'stars_not_identity'=>true,'core8_only'=>true]];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded component consensus workflow\n");exit(64);}
