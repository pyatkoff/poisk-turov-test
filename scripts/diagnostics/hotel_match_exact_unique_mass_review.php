<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_mass_identity_graph_review.php';

const HMEUMR_OPERATION='hotel-match-exact-unique-mass-review-1971-20260911-v1';
const HMEUMR_COORD_BLOCK_M=5000.0;
const HMEUMR_CORE8=[1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmeumr_other(array $claims,string|int $self):array{
    return array_values(array_filter($claims,static fn($x)=>(string)$x!==(string)$self));
}
function hmeumr_exact_targets(array $source,int $country,array $index,array $hotels,array $names):array{
    [$exactIds]=hmgcr_source_candidate_ids($source['names']??[],$source['places']??[],$country,$index);
    $out=[];
    foreach(array_unique(array_map('intval',$exactIds)) as $id){
        if($id<1||!isset($hotels[$id])||(int)$hotels[$id]['country_id']!==$country)continue;
        $target=$hotels[$id];$tp=[(string)($target['region_name']??''),(string)($target['subregion_name']??'')];
        $pair=hmgcr_best_pair($source['names']??[],$names[$id]??[(string)($target['name']??'')],$source['places']??[],$tp);
        if(!($pair['critical_ok']??false)||!($pair['exact_bag']??false)||(int)($pair['shared']??0)<2)continue;
        $distance=fc_dist($source['latitude']??null,$source['longitude']??null,$target['latitude']??null,$target['longitude']??null);
        $out[$id]=['target_local_hotel_id'=>$id,'target_name'=>(string)($target['name']??''),'target_region'=>(string)($target['region_name']??''),'target_subregion'=>(string)($target['subregion_name']??''),'target_category'=>$target['category']===null?null:(int)$target['category'],'pair'=>$pair,'place_match'=>hmgcr_place($source['places']??[],$tp),'distance_m'=>$distance===null?null:round((float)$distance,2)];
    }
    return $out;
}
function hmeumr_review(PDO $db,string $operation=HMEUMR_OPERATION):array{
    if($operation!==HMEUMR_OPERATION)throw new RuntimeException('HMEUMR_OPERATION_SCOPE');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);$an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);[$hotels,$names]=mbr_catalog($db);
        $all=[];foreach(array_keys($hotels)as$id){$id=(int)$id;if(isset(HMEUMR_CORE8[(int)$hotels[$id]['country_id']]))$all[$id]=true;}$index=hmgcr_build_index($all,$hotels,$names);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $andStatus=[];foreach($db->query("SELECT external_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchAll(PDO::FETCH_ASSOC)as$r)$andStatus[(string)$r['external_hotel_id']]=(string)$r['decision_status'];
        $anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $stats=['anex_rows'=>count($an),'andromeda_rows'=>count($and),'unresolved_anex'=>0,'unresolved_andromeda'=>0,'examined'=>0,'live_examined'=>0,'exact_candidate_rows'=>0,'exact_unique_raw'=>0,'exact_ambiguous'=>0,'coordinate_conflict_gt_5km'=>0,'manual_protected'=>0,'andromeda_nonpending_protected'=>0,'pair_exclusion_block'=>0,'target_occupancy_block'=>0,'global_uniqueness_demotions'=>0,'prepared'=>0,'prepared_anex'=>0,'prepared_andromeda'=>0,'prepared_live'=>0,'star_mismatch_annotated'=>0,'needs_extra'=>0,'hard_conflict'=>0];
        $rows=[];
        foreach($an as$aid=>$source){
            if($source['local_ids'])continue;if(!isset(HMEUMR_CORE8[(int)$source['country_id']]))continue;$stats['unresolved_anex']++;
            if(isset($manual[(int)$aid])){$stats['manual_protected']++;continue;}
            $rows[hmigr_key('anex',$aid)]=['provider'=>'anex','external_id'=>(string)$aid,'country_id'=>(int)$source['country_id'],'live'=>(bool)$source['live'],'observation_count'=>(int)$source['observation_count'],'source'=>$source];
        }
        foreach($and as$id=>$source){
            if($source['local_ids'])continue;if(!isset(HMEUMR_CORE8[(int)$source['country_id']]))continue;$stats['unresolved_andromeda']++;
            if(($andStatus[(string)$id]??'')!=='pending'){$stats['andromeda_nonpending_protected']++;continue;}
            $rows[hmigr_key('andromeda',$id)]=['provider'=>'andromeda','external_id'=>(string)$id,'country_id'=>(int)$source['country_id'],'live'=>(bool)$source['live'],'observation_count'=>(int)$source['observation_count'],'source'=>$source];
        }
        $out=[];
        foreach($rows as$key=>$node){
            $stats['examined']++;if($node['live'])$stats['live_examined']++;
            $targets=hmeumr_exact_targets($node['source'],$node['country_id'],$index,$hotels,$names);
            if(!$targets){$out[$key]=['bucket'=>'needs_extra_evidence','reason'=>'no_unique_exact_significant_alias']+$node;continue;}
            $stats['exact_candidate_rows']++;
            if(count($targets)!==1){$stats['exact_ambiguous']++;$out[$key]=['bucket'=>'needs_extra_evidence','reason'=>'exact_significant_alias_ambiguous','candidate_target_ids'=>array_map('intval',array_keys($targets))]+$node;continue;}
            $stats['exact_unique_raw']++;$target=(int)array_key_first($targets);$ev=$targets[$target];
            if($ev['distance_m']!==null&&(float)$ev['distance_m']>HMEUMR_COORD_BLOCK_M){$stats['coordinate_conflict_gt_5km']++;$out[$key]=['bucket'=>'hard_conflict','reason'=>'exact_alias_coordinate_conflict_gt_5km','target_local_hotel_id'=>$target,'evidence'=>$ev]+$node;continue;}
            if($node['provider']==='anex'&&isset($excluded[(int)$node['external_id']][$target])){$stats['pair_exclusion_block']++;$out[$key]=['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target_local_hotel_id'=>$target,'evidence'=>$ev]+$node;continue;}
            $other=$node['provider']==='anex'?hmeumr_other($anClaims[$target]??[],(int)$node['external_id']):hmeumr_other($andClaims[$target]??[],(string)$node['external_id']);
            if($other){$stats['target_occupancy_block']++;$out[$key]=['bucket'=>'needs_extra_evidence','reason'=>'same_provider_target_occupied','target_local_hotel_id'=>$target,'other_claims'=>$other,'evidence'=>$ev]+$node;continue;}
            $sourceCat=$node['source']['category']??null;$targetCat=$ev['target_category'];$starMismatch=$sourceCat!==null&&$targetCat!==null&&(int)$sourceCat!==(int)$targetCat;if($starMismatch)$stats['star_mismatch_annotated']++;
            $out[$key]=['bucket'=>'prepared','reason'=>'unique_exact_significant_alias_same_country','target_local_hotel_id'=>$target,'star_mismatch_annotation'=>$starMismatch,'evidence'=>$ev]+$node;
        }
        $groups=[];foreach($out as$key=>$row)if(($row['bucket']??'')==='prepared')$groups[$row['provider'].':'.$row['target_local_hotel_id']][]=$key;
        foreach($groups as$keys)if(count($keys)>1)foreach($keys as$key){$out[$key]['bucket']='needs_extra_evidence';$out[$key]['reason']='same_provider_exact_target_collision';$stats['global_uniqueness_demotions']++;}
        $prepared=[];$needs=[];$hard=[];foreach($out as$row){unset($row['source']);if($row['bucket']==='prepared'){$prepared[]=$row;$stats['prepared']++;$stats[$row['provider']==='anex'?'prepared_anex':'prepared_andromeda']++;if($row['live'])$stats['prepared_live']++;}elseif($row['bucket']==='hard_conflict'){$hard[]=$row;$stats['hard_conflict']++;}else{$needs[]=$row;$stats['needs_extra']++;}}
        $sort=static fn($a,$b)=>(($b['live']??false)<=>($a['live']??false))?:((int)($b['observation_count']??0)<=>(int)($a['observation_count']??0))?:strcmp((string)$a['external_id'],(string)$b['external_id']);usort($prepared,$sort);usort($needs,$sort);usort($hard,$sort);
        $db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_core8_unique_exact_significant_alias_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'needs_extra_evidence'=>$needs,'hard_conflicts'=>$hard,'guards'=>['all_unresolved_core8_examined'=>true,'exact_normalized_significant_alias_required'=>true,'significant_shared_tokens_min'=>2,'critical_qualifiers_preserved'=>['annex','beach','garden','north','south'],'generic_tokens_not_identity'=>['hotel','resort','spa'],'same_country_required'=>true,'unique_local_target_required'=>true,'coordinate_conflict_block_m'=>HMEUMR_COORD_BLOCK_M,'manual_decisions_protected'=>true,'pair_exclusions_protected'=>true,'same_provider_target_occupancy_protected'=>true,'global_same_provider_target_one_to_one'=>true,'stars_annotation_not_identity'=>true,'live_priority_does_not_lower_thresholds'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){$root=realpath(__DIR__.'/../..');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');echo fc_json(hmeumr_review(v2_data_db(),HMEUMR_OPERATION)),"\n";}
