<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_exact_ordered_mass_review.php';

const HMELMR_OPERATION='hotel-match-exact-ordered-literal-mass-review-1971-20260911-v1';
const HMELMR_COORD_BLOCK_M=5000.0;
const HMELMR_CORE8=[1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmelmr_tokens(string $name):array{
    $drop=['hotel'=>true,'hotels'=>true,'resort'=>true,'resorts'=>true,'spa'=>true,'the'=>true,'and'=>true,'ex'=>true];
    $n=fc_norm(hmgcr_latin($name));$out=[];
    foreach(explode(' ',$n) as$t){if($t===''||isset($drop[$t]))continue;$out[]=$t;}
    return $out;
}
function hmelmr_keys(array $names):array{
    $out=[];
    foreach($names as$name)foreach(hmeomr_name_variants((string)$name) as$v){$tokens=hmelmr_tokens($v);if(count($tokens)<2)continue;$key=implode(' ',$tokens);if($key!=='')$out[$key]=['variant'=>$v,'tokens'=>$tokens];}
    return $out;
}
function hmelmr_build_index(array $hotels,array $names):array{
    $idx=[];foreach($hotels as$id=>$h){$country=(int)$h['country_id'];if(!isset(HMELMR_CORE8[$country]))continue;foreach(hmelmr_keys($names[(int)$id]??[(string)$h['name']]) as$key=>$meta)$idx[$country][$key][(int)$id]=$meta;}return $idx;
}
function hmelmr_targets(array $source,int $country,array $idx,array $hotels,array $names):array{
    $out=[];
    foreach(hmelmr_keys($source['names']??[]) as$key=>$src)foreach($idx[$country][$key]??[] as$id=>$targetMeta){$id=(int)$id;if(!isset($hotels[$id])||(int)$hotels[$id]['country_id']!==$country)continue;$t=$hotels[$id];$distance=fc_dist($source['latitude']??null,$source['longitude']??null,$t['latitude']??null,$t['longitude']??null);$out[$id]=['target_local_hotel_id'=>$id,'target_name'=>(string)$t['name'],'target_region'=>(string)($t['region_name']??''),'target_subregion'=>(string)($t['subregion_name']??''),'target_category'=>$t['category']===null?null:(int)$t['category'],'source_variant'=>$src['variant'],'target_variant'=>$targetMeta['variant'],'significant_tokens'=>$src['tokens'],'distance_m'=>$distance===null?null:round((float)$distance,2)];}
    return $out;
}
function hmelmr_review(PDO $db,string $operation=HMELMR_OPERATION):array{
    if($operation!==HMELMR_OPERATION)throw new RuntimeException('HMELMR_OPERATION_SCOPE');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);$an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);[$hotels,$names]=mbr_catalog($db);$idx=hmelmr_build_index($hotels,$names);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $andStatus=[];foreach($db->query("SELECT external_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchAll(PDO::FETCH_ASSOC)as$r)$andStatus[(string)$r['external_hotel_id']]=(string)$r['decision_status'];
        $anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $stats=['anex_rows'=>count($an),'andromeda_rows'=>count($and),'examined'=>0,'unresolved_anex'=>0,'unresolved_andromeda'=>0,'live_examined'=>0,'exact_candidate_rows'=>0,'exact_unique_raw'=>0,'exact_ambiguous'=>0,'coordinate_conflict_gt_5km'=>0,'manual_protected'=>0,'andromeda_nonpending_protected'=>0,'pair_exclusion_block'=>0,'target_occupancy_block'=>0,'global_uniqueness_demotions'=>0,'prepared'=>0,'prepared_anex'=>0,'prepared_andromeda'=>0,'prepared_live'=>0,'star_mismatch_annotated'=>0,'needs_extra'=>0,'hard_conflict'=>0];$nodes=[];
        foreach($an as$aid=>$s){if($s['local_ids']||!isset(HMELMR_CORE8[(int)$s['country_id']]))continue;$stats['unresolved_anex']++;if(isset($manual[(int)$aid])){$stats['manual_protected']++;continue;}$nodes[hmigr_key('anex',$aid)]=['provider'=>'anex','external_id'=>(string)$aid,'country_id'=>(int)$s['country_id'],'live'=>(bool)$s['live'],'observation_count'=>(int)$s['observation_count'],'source'=>$s];}
        foreach($and as$id=>$s){if($s['local_ids']||!isset(HMELMR_CORE8[(int)$s['country_id']]))continue;$stats['unresolved_andromeda']++;if(($andStatus[(string)$id]??'')!=='pending'){$stats['andromeda_nonpending_protected']++;continue;}$nodes[hmigr_key('andromeda',$id)]=['provider'=>'andromeda','external_id'=>(string)$id,'country_id'=>(int)$s['country_id'],'live'=>(bool)$s['live'],'observation_count'=>(int)$s['observation_count'],'source'=>$s];}
        $rows=[];
        foreach($nodes as$key=>$node){$stats['examined']++;if($node['live'])$stats['live_examined']++;$targets=hmelmr_targets($node['source'],$node['country_id'],$idx,$hotels,$names);if(!$targets){$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'no_exact_ordered_literal_alias']+$node;continue;}$stats['exact_candidate_rows']++;if(count($targets)!==1){$stats['exact_ambiguous']++;$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'exact_ordered_literal_alias_ambiguous','candidate_target_ids'=>array_map('intval',array_keys($targets))]+$node;continue;}$stats['exact_unique_raw']++;$target=(int)array_key_first($targets);$ev=$targets[$target];if($ev['distance_m']!==null&&(float)$ev['distance_m']>HMELMR_COORD_BLOCK_M){$stats['coordinate_conflict_gt_5km']++;$rows[$key]=['bucket'=>'hard_conflict','reason'=>'exact_ordered_literal_alias_coordinate_conflict_gt_5km','target_local_hotel_id'=>$target,'evidence'=>$ev]+$node;continue;}if($node['provider']==='anex'&&isset($excluded[(int)$node['external_id']][$target])){$stats['pair_exclusion_block']++;$rows[$key]=['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target_local_hotel_id'=>$target,'evidence'=>$ev]+$node;continue;}$other=$node['provider']==='anex'?hmeumr_other($anClaims[$target]??[],(int)$node['external_id']):hmeumr_other($andClaims[$target]??[],(string)$node['external_id']);if($other){$stats['target_occupancy_block']++;$rows[$key]=['bucket'=>'needs_extra_evidence','reason'=>'same_provider_target_occupied','target_local_hotel_id'=>$target,'other_claims'=>$other,'evidence'=>$ev]+$node;continue;}$sourceCat=$node['source']['category']??null;$targetCat=$ev['target_category'];$starMismatch=$sourceCat!==null&&$targetCat!==null&&(int)$sourceCat!==(int)$targetCat;if($starMismatch)$stats['star_mismatch_annotated']++;$rows[$key]=['bucket'=>'prepared','reason'=>'unique_exact_ordered_literal_alias_same_country','target_local_hotel_id'=>$target,'star_mismatch_annotation'=>$starMismatch,'evidence'=>$ev]+$node;}
        $groups=[];foreach($rows as$key=>$r)if(($r['bucket']??'')==='prepared')$groups[$r['provider'].':'.$r['target_local_hotel_id']][]=$key;foreach($groups as$keys)if(count($keys)>1)foreach($keys as$key){$rows[$key]['bucket']='needs_extra_evidence';$rows[$key]['reason']='same_provider_exact_ordered_literal_target_collision';$stats['global_uniqueness_demotions']++;}
        $prepared=[];$needs=[];$hard=[];foreach($rows as$r){unset($r['source']);if($r['bucket']==='prepared'){$prepared[]=$r;$stats['prepared']++;$stats[$r['provider']==='anex'?'prepared_anex':'prepared_andromeda']++;if($r['live'])$stats['prepared_live']++;}elseif($r['bucket']==='hard_conflict'){$hard[]=$r;$stats['hard_conflict']++;}else{$needs[]=$r;$stats['needs_extra']++;}}
        $sort=static fn($a,$b)=>(($b['live']??false)<=>($a['live']??false))?:((int)($b['observation_count']??0)<=>(int)($a['observation_count']??0))?:strcmp((string)$a['external_id'],(string)$b['external_id']);usort($prepared,$sort);usort($needs,$sort);usort($hard,$sort);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_core8_exact_ordered_literal_alias_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared,'needs_extra_evidence'=>$needs,'hard_conflicts'=>$hard,'guards'=>['all_unresolved_core8_examined'=>true,'literal_ordered_normalized_sequence_exact'=>true,'min_significant_tokens'=>2,'generic_tokens_dropped_only'=>['hotel','hotels','resort','resorts','spa','the','and','ex'],'broad_fc_tokens_not_used'=>true,'former_name_alias_variants'=>true,'same_country_required'=>true,'unique_local_target_required'=>true,'coordinate_conflict_block_m'=>HMELMR_COORD_BLOCK_M,'manual_decisions_protected'=>true,'pair_exclusions_protected'=>true,'same_provider_target_occupancy_protected'=>true,'global_same_provider_target_one_to_one'=>true,'stars_annotation_not_identity'=>true,'live_priority_does_not_lower_thresholds'=>true]];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){$root=realpath(__DIR__.'/../..');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');echo fc_json(hmelmr_review(v2_data_db(),HMELMR_OPERATION)),"\n";}
