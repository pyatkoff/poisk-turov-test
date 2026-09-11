<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_andromeda_mutual_graph_review.php';

const HMACPR_OPERATION='hotel-match-anex-andromeda-cell-priority-review-1971-20260912-v1';
const HMACPR_MIN_TV_POTENTIAL=5;
const HMACPR_MAX_RECOMMENDED_CELLS=80;

function hmacpr_shared_place(array $a,array $b):string{
    $aa=[];$bb=[];
    foreach($a as$v){$n=fc_norm($v);if($n!=='')$aa[$n]=(string)$v;}
    foreach($b as$v){$n=fc_norm($v);if($n!=='')$bb[$n]=(string)$v;}
    $shared=array_intersect_key($aa,$bb);
    if($shared)return (string)reset($shared);
    if($aa)return (string)reset($aa);
    if($bb)return (string)reset($bb);
    return '__country__';
}
function hmacpr_cell_key(int $country,string $place):string{return $country.'|'.fc_norm($place);}
function hmacpr_new_cell(int $country,string $place):array{return [
    'country_id'=>$country,'country_name'=>FC_COUNTRIES[$country]??(string)$country,'resort'=>$place,
    'safe_pairs'=>0,'exact_alias_overlap'=>0,'fuzzy_geo_overlap'=>0,'missing_third_potential'=>0,
    'unresolved_overlap'=>0,'same_local_benchmark'=>0,'recurring_pairs'=>0,'live_priority_pairs'=>0,
    'ambiguous_sources'=>0,'coordinate_conflicts'=>0,'existing_local_conflicts'=>0,'candidate_potential'=>0,'score'=>0,
    'tv_eligible'=>false,'tv_search_slices_planned'=>0,'tv_http_calls_planned'=>0,
];}
function hmacpr_score(array $c):int{
    return 4*(int)$c['exact_alias_overlap'] + 3*(int)$c['missing_third_potential'] + 2*(int)$c['recurring_pairs']
        + (int)$c['unresolved_overlap'] + (int)$c['live_priority_pairs']
        - 3*(int)$c['ambiguous_sources'] - 5*((int)$c['coordinate_conflicts']+(int)$c['existing_local_conflicts']);
}
function hmacpr_review(PDO $db,string $operation=HMACPR_OPERATION):array{
    if($operation!==HMACPR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);
        $an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);$andIndex=hmamgr_index($and);$andLoc=hmadcrh_locality_common($and);
        $stats=['anex_rows'=>count($an),'andromeda_rows'=>count($and),'anex_live_unresolved'=>0,'andromeda_live_unresolved'=>0,
            'examined_anex'=>0,'candidate_edges'=>0,'safe_edges'=>0,'exact_alias_edges'=>0,'fuzzy_geo_edges'=>0,
            'missing_third_edges'=>0,'unresolved_edges'=>0,'same_local_edges'=>0,'coordinate_conflicts'=>0,'existing_local_conflicts'=>0,
            'ambiguous_sources'=>0,'cells'=>0,'tv_eligible_cells'=>0,'recommended_cells'=>0];
        foreach($an as$r)if($r['live']&&!$r['local_ids'])$stats['anex_live_unresolved']++;
        foreach($and as$r)if($r['live']&&!$r['local_ids'])$stats['andromeda_live_unresolved']++;
        $cells=[];$pairs=[];
        foreach($an as$aid=>$a){
            if(!$a['live'] && $a['local_ids'])continue;
            $stats['examined_anex']++;
            $pool=hmamgr_candidates($a,$and,$andIndex);$safeForSource=[];
            foreach($pool as$andId=>$edge){
                $stats['candidate_edges']++;$b=$and[$andId]??null;if(!$b)continue;
                $place=hmacpr_shared_place($a['places'],$b['places']);$ck=hmacpr_cell_key((int)$a['country_id'],$place);
                if(!isset($cells[$ck]))$cells[$ck]=hmacpr_new_cell((int)$a['country_id'],$place);
                if(($edge['route']??null)==='coordinate_conflict'){$cells[$ck]['coordinate_conflicts']++;$stats['coordinate_conflicts']++;continue;}
                if(($edge['route']??null)===null)continue;
                $anchors=hmadcrh_identity_anchors($edge['pair'],$b,$andLoc);if((int)($anchors['identity_aligned']??0)<2)continue;
                $validation=hmamgr_validation($a['local_ids'],$b['local_ids']);
                if($validation==='different_local'){$cells[$ck]['existing_local_conflicts']++;$stats['existing_local_conflicts']++;continue;}
                $safeForSource[]=(string)$andId;$cells[$ck]['safe_pairs']++;$stats['safe_edges']++;
                $exact=str_starts_with((string)$edge['route'],'mutual_exact_');
                if($exact){$cells[$ck]['exact_alias_overlap']++;$stats['exact_alias_edges']++;}
                else{$cells[$ck]['fuzzy_geo_overlap']++;$stats['fuzzy_geo_edges']++;}
                if($validation==='anex_only'||$validation==='andromeda_only'){$cells[$ck]['missing_third_potential']++;$stats['missing_third_edges']++;}
                elseif($validation==='neither_side'){$cells[$ck]['unresolved_overlap']++;$stats['unresolved_edges']++;}
                elseif($validation==='same_local'){$cells[$ck]['same_local_benchmark']++;$stats['same_local_edges']++;}
                if(min((int)$a['observation_count'],(int)$b['observation_count'])>=2)$cells[$ck]['recurring_pairs']++;
                if(($a['live']||$b['live'])&&$validation!=='same_local')$cells[$ck]['live_priority_pairs']++;
                $pairs[]=['cell'=>$ck,'country_id'=>(int)$a['country_id'],'resort'=>$place,'anex_hotel_id'=>(int)$aid,
                    'andromeda_external_id'=>(string)$andId,'route'=>(string)$edge['route'],'validation'=>$validation,
                    'anex_observation_count'=>(int)$a['observation_count'],'andromeda_observation_count'=>(int)$b['observation_count'],
                    'anex_live'=>(bool)$a['live'],'andromeda_live'=>(bool)$b['live'],'distance_m'=>$edge['distance_m']??null];
            }
            if(count(array_unique($safeForSource))>1){$stats['ambiguous_sources']++;foreach(array_unique($safeForSource)as$andId){$b=$and[$andId]??null;if(!$b)continue;$place=hmacpr_shared_place($a['places'],$b['places']);$ck=hmacpr_cell_key((int)$a['country_id'],$place);if(isset($cells[$ck]))$cells[$ck]['ambiguous_sources']++;}}
        }
        foreach($cells as$k=>&$c){$c['candidate_potential']=(int)$c['missing_third_potential']+(int)$c['unresolved_overlap'];$c['score']=hmacpr_score($c);$c['tv_eligible']=$c['candidate_potential']>=HMACPR_MIN_TV_POTENTIAL && (int)$c['exact_alias_overlap']>0;if($c['tv_eligible'])$stats['tv_eligible_cells']++;}unset($c);
        uasort($cells,static fn($x,$y)=>$y['score']<=>$x['score']?:$y['candidate_potential']<=>$x['candidate_potential']?:$y['exact_alias_overlap']<=>$x['exact_alias_overlap']);
        $recommended=[];foreach($cells as$k=>$c){if(!$c['tv_eligible'])continue;$c['cell_key']=$k;$recommended[]=$c;if(count($recommended)>=HMACPR_MAX_RECOMMENDED_CELLS)break;}
        $stats['cells']=count($cells);$stats['recommended_cells']=count($recommended);
        $db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_core8_anex_andromeda_first_cell_priority_read_only',
            'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'tv_search_slices'=>0,'tv_http_calls'=>0,
            'historical_operations_replayed'=>false,'coverage'=>$coverage,'stats'=>$stats,'recommended_cells'=>$recommended,'cells'=>array_values($cells),'candidate_pairs'=>$pairs,
            'selection_policy'=>['score'=>'4*exact_alias_overlap + 3*missing_third + 2*recurring + unresolved + live_priority - 3*ambiguity - 5*conflict',
                'min_tv_candidate_potential'=>HMACPR_MIN_TV_POTENTIAL,'tourvisor_confirmation_only'=>true,'one_day_slices_later'=>true,'initial_dates_per_cell'=>2,
                'dates_and_stars'=>'chosen only after supplier-first discovery for the selected resort cell; star is segmentation/guard, never identity'],
            'guards'=>['core8_only'=>true,'current_saved_db_first'=>true,'critical_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],
                'generic_hotel_resort_spa_non_identity'=>true,'coordinate_conflict_block_m'=>HMAMGR_COORD_BLOCK_M,'identity_anchors_min'=>2,
                'existing_different_local_not_actionable'=>true,'stars_not_identity'=>true,'no_tourvisor_budget_spent'=>true]];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded cell-priority workflow\n");exit(64);}
