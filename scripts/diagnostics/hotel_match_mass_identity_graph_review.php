<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_andromeda_cluster_local_review.php';

const HMIGR_OPERATION='hotel-match-mass-identity-graph-review-1971-20260911-v1';
const HMIGR_COORD_BLOCK_M=5000.0;
const HMIGR_COORD_DIRECT_M=1000.0;

function hmigr_key(string $provider,string|int $external):string{return strtolower(trim($provider)).':'.trim((string)$external);}

function hmigr_source_target_guard(array $source,array $target,array $targetNames):array{
    $targetPlaces=array_values(array_filter([(string)($target['region_name']??''),(string)($target['subregion_name']??'')],static fn($v)=>trim($v)!==''));
    $pair=hmgcr_best_pair($source['names']??[],$targetNames,$source['places']??[],$targetPlaces);
    if(!($pair['critical_ok']??false))return ['ok'=>false,'reason'=>'critical_qualifier_conflict','pair'=>$pair];
    $distance=fc_dist($source['latitude']??null,$source['longitude']??null,$target['latitude']??null,$target['longitude']??null);
    if($distance!==null&&(float)$distance>HMIGR_COORD_BLOCK_M)return ['ok'=>false,'reason'=>'coordinate_conflict_gt_5km','pair'=>$pair,'distance_m'=>round((float)$distance,2)];
    $place=hmgcr_place($source['places']??[],$targetPlaces);$geo=$place||($distance!==null&&(float)$distance<=HMIGR_COORD_DIRECT_M);
    $shared=(int)($pair['shared']??0);$aligned=(int)($pair['aligned']??0);$fuzzy=(float)($pair['fuzzy_score']??0.0);$char=(float)($pair['char_similarity']??0.0);
    $strong=(bool)($pair['exact_bag']??false)
        ?($shared>=3||($shared>=2&&$geo))
        :($aligned>=3&&$fuzzy>=0.90&&$char>=0.90&&$geo&&(($pair['anchor_ok']??false)||($pair['ordered']??false)));
    return ['ok'=>$strong,'reason'=>$strong?'source_target_identity_ok':'source_target_identity_insufficient','pair'=>$pair,'place_match'=>$place,'distance_m'=>$distance===null?null:round((float)$distance,2),'geo'=>$geo];
}

function hmigr_direct_candidate(array $source,int $country,array $index,array $hotels,array $names):?array{
    $d=hmgtr_decide($source,$country,$index,$hotels,$names,[],[]);if(($d['bucket']??'')!=='prepared')return null;
    $target=(int)($d['target_local_hotel_id']??0);if($target<1||!isset($hotels[$target]))return null;
    $guard=hmigr_source_target_guard($source,$hotels[$target],$names[$target]??[(string)$hotels[$target]['name']]);if(!($guard['ok']??false))return null;
    return ['target_local_hotel_id'=>$target,'source'=>'direct_local','reason'=>(string)($d['reason']??'direct_local_prepared'),'guard'=>$guard,'local_resolution'=>$d];
}

function hmigr_add_vote(array &$votes,string $provider,string|int $external,int $target,string $source,array $meta=[]):void{
    if($target<1)return;$key=hmigr_key($provider,$external);$votes[$key][$target]['sources'][$source]=true;if($meta)$votes[$key][$target]['meta'][$source][]=$meta;
}

function hmigr_decide_votes(array $targetVotes):array{
    if(!$targetVotes)return ['bucket'=>'needs_extra_evidence','reason'=>'no_mass_graph_consensus','target_local_hotel_id'=>null,'evidence_sources'=>[]];
    if(count($targetVotes)>1)return ['bucket'=>'hard_conflict','reason'=>'mass_graph_target_disagreement','target_local_hotel_id'=>null,'candidate_targets'=>array_map('intval',array_keys($targetVotes)),'evidence_sources'=>[]];
    $target=(int)array_key_first($targetVotes);$sources=array_keys($targetVotes[$target]['sources']??[]);sort($sources,SORT_STRING);
    if(count($sources)<2)return ['bucket'=>'needs_extra_evidence','reason'=>'mass_graph_single_evidence_class','target_local_hotel_id'=>$target,'evidence_sources'=>$sources];
    return ['bucket'=>'auto_accept','reason'=>'mass_graph_multi_evidence_consensus','target_local_hotel_id'=>$target,'evidence_sources'=>$sources];
}

function hmigr_other(array $claims,string|int $self):array{return array_values(array_filter($claims,static fn($x)=>(string)$x!==(string)$self));}

function hmigr_apply_uniqueness(array $rows):array{
    $groups=[];foreach($rows as$key=>$row)if(($row['bucket']??'')==='auto_accept'&&($row['target_local_hotel_id']??null)!==null)$groups[(string)$row['provider'].':'.(int)$row['target_local_hotel_id']][]=$key;
    $demoted=0;foreach($groups as$keys)if(count($keys)>1)foreach($keys as$key){$rows[$key]['bucket']='needs_extra_evidence';$rows[$key]['reason']='same_provider_target_collision';$demoted++;}
    return [$rows,$demoted];
}

function hmigr_review(PDO $db,string $operation=HMIGR_OPERATION):array{
    if($operation!==HMIGR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);$an=hmamgr_anex_rows($db);$and=hmamgr_andromeda_rows($db);
        $anIndex=hmamgr_index($an);$andIndex=hmamgr_index($and);$anLoc=hmadcrh_locality_common($an);$andLoc=hmadcrh_locality_common($and);
        [$hotels,$names]=mbr_catalog($db);$all=[];foreach(array_keys($hotels)as$id)$all[(int)$id]=true;$localIndex=hmgcr_build_index($all,$hotels,$names);

        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC)as$r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $andStatus=[];foreach($db->query("SELECT external_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchAll(PDO::FETCH_ASSOC)as$r)$andStatus[(string)$r['external_hotel_id']]=(string)$r['decision_status'];
        $anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC)as$r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];

        $stats=['anex_rows'=>count($an),'andromeda_rows'=>count($and),'unresolved_anex'=>0,'unresolved_andromeda'=>0,'protected_manual'=>0,'protected_andromeda_nonpending'=>0,'direct_local_votes'=>0,'supplier_edges_examined'=>0,'supplier_edges_safe'=>0,'opposite_accepted_bridge_votes'=>0,'counterpart_direct_bridge_votes'=>0,'supplier_pair_confirmation_votes'=>0,'cluster_local_votes'=>0,'supplier_pair_hard_conflicts'=>0,'provisional_auto'=>0,'global_uniqueness_demotions'=>0,'auto_accept'=>0,'auto_anex'=>0,'auto_andromeda'=>0,'auto_live'=>0,'needs_extra_evidence'=>0,'hard_conflict'=>0,'manual_last'=>0,'estimated_new_triple'=>0,'target_occupancy_blocks'=>0,'pair_exclusion_blocks'=>0,'country_mismatch_blocks'=>0];
        $unresolved=[];
        foreach($an as$aid=>$row){if($row['local_ids'])continue;if(isset($manual[(int)$aid])){$stats['protected_manual']++;continue;}$key=hmigr_key('anex',$aid);$unresolved[$key]=['provider'=>'anex','external_id'=>(string)$aid,'country_id'=>(int)$row['country_id'],'live'=>(bool)$row['live'],'observation_count'=>(int)$row['observation_count'],'source'=>$row];$stats['unresolved_anex']++;}
        foreach($and as$id=>$row){if($row['local_ids'])continue;if(($andStatus[(string)$id]??'')!=='pending'){$stats['protected_andromeda_nonpending']++;continue;}$key=hmigr_key('andromeda',$id);$unresolved[$key]=['provider'=>'andromeda','external_id'=>(string)$id,'country_id'=>(int)$row['country_id'],'live'=>(bool)$row['live'],'observation_count'=>(int)$row['observation_count'],'source'=>$row];$stats['unresolved_andromeda']++;}

        $votes=[];$direct=[];$relationHard=[];
        foreach($unresolved as$key=>$node){$d=hmigr_direct_candidate($node['source'],$node['country_id'],$localIndex,$hotels,$names);if($d===null)continue;$direct[$key]=(int)$d['target_local_hotel_id'];hmigr_add_vote($votes,$node['provider'],$node['external_id'],$direct[$key],'direct_local',$d);$stats['direct_local_votes']++;}

        $forwardChoice=[];$reverseChoice=[];
        foreach($an as$aid=>$a){
            $pool=hmamgr_candidates($a,$and,$andIndex);
            foreach($pool as$andId=>$edge){
                $stats['supplier_edges_examined']++;if(($edge['route']??null)===null||$edge['route']==='coordinate_conflict')continue;
                $b=$and[$andId]??null;if(!$b)continue;$anchors=hmadcrh_identity_anchors($edge['pair'],$b,$andLoc);if((int)$anchors['identity_aligned']<2)continue;
                $validation=hmamgr_validation($a['local_ids'],$b['local_ids']);if($validation==='different_local'){$stats['supplier_pair_hard_conflicts']++;continue;}$stats['supplier_edges_safe']++;
                $keyA=hmigr_key('anex',$aid);$keyB=hmigr_key('andromeda',$andId);$ua=isset($unresolved[$keyA]);$ub=isset($unresolved[$keyB]);if(!$ua&&!$ub)continue;
                $localA=count($a['local_ids'])===1?(int)$a['local_ids'][0]:0;$localB=count($b['local_ids'])===1?(int)$b['local_ids'][0]:0;

                if($ub&&$localA>0&&isset($hotels[$localA])){$g=hmigr_source_target_guard($b,$hotels[$localA],$names[$localA]??[(string)$hotels[$localA]['name']]);if($g['ok']??false){hmigr_add_vote($votes,'andromeda',$andId,$localA,'opposite_accepted_bridge',['supplier_edge'=>$edge,'guard'=>$g]);$stats['opposite_accepted_bridge_votes']++;}}
                if($ua&&$localB>0&&isset($hotels[$localB])){$g=hmigr_source_target_guard($a,$hotels[$localB],$names[$localB]??[(string)$hotels[$localB]['name']]);if($g['ok']??false){hmigr_add_vote($votes,'anex',$aid,$localB,'opposite_accepted_bridge',['supplier_edge'=>$edge,'guard'=>$g]);$stats['opposite_accepted_bridge_votes']++;}}
                if($ub&&isset($direct[$keyA])&&isset($hotels[$direct[$keyA]])){$t=$direct[$keyA];$g=hmigr_source_target_guard($b,$hotels[$t],$names[$t]??[(string)$hotels[$t]['name']]);if($g['ok']??false){hmigr_add_vote($votes,'andromeda',$andId,$t,'counterpart_direct_bridge',['supplier_edge'=>$edge,'guard'=>$g]);$stats['counterpart_direct_bridge_votes']++;}}
                if($ua&&isset($direct[$keyB])&&isset($hotels[$direct[$keyB]])){$t=$direct[$keyB];$g=hmigr_source_target_guard($a,$hotels[$t],$names[$t]??[(string)$hotels[$t]['name']]);if($g['ok']??false){hmigr_add_vote($votes,'anex',$aid,$t,'counterpart_direct_bridge',['supplier_edge'=>$edge,'guard'=>$g]);$stats['counterpart_direct_bridge_votes']++;}}

                if($ua&&$ub&&isset($direct[$keyA],$direct[$keyB])&&$direct[$keyA]===$direct[$keyB]){
                    if(!isset($forwardChoice[(string)$aid]))$forwardChoice[(string)$aid]=hmamgr_choose($a,$and,$andIndex,$andLoc);
                    if(!isset($reverseChoice[(string)$andId]))$reverseChoice[(string)$andId]=hmamgr_choose($b,$an,$anIndex,$anLoc);
                    $f=$forwardChoice[(string)$aid];$r=$reverseChoice[(string)$andId];$mutual=($f['bucket']??'')==='candidate'&&(string)($f['target_id']??'')===(string)$andId&&($r['bucket']??'')==='candidate'&&(string)($r['target_id']??'')===(string)$aid;
                    if($mutual){$t=$direct[$keyA];hmigr_add_vote($votes,'anex',$aid,$t,'supplier_pair_confirmation',['supplier_edge'=>$edge]);hmigr_add_vote($votes,'andromeda',$andId,$t,'supplier_pair_confirmation',['supplier_edge'=>$edge]);$stats['supplier_pair_confirmation_votes']+=2;}
                }

                $cluster=hmaclr_resolve($a,$b,(int)$a['country_id'],$localIndex,$hotels,$names);
                if(($cluster['bucket']??'')==='prepared'){$t=(int)$cluster['target_local_hotel_id'];if($ua){hmigr_add_vote($votes,'anex',$aid,$t,'supplier_cluster_local',$cluster);$stats['cluster_local_votes']++;}if($ub){hmigr_add_vote($votes,'andromeda',$andId,$t,'supplier_cluster_local',$cluster);$stats['cluster_local_votes']++;}}
                elseif(($cluster['bucket']??'')==='hard_conflict'){if($ua)$relationHard[$keyA][(string)($cluster['reason']??'cluster_hard_conflict')]=true;if($ub)$relationHard[$keyB][(string)($cluster['reason']??'cluster_hard_conflict')]=true;}
            }
        }

        $rows=[];
        foreach($unresolved as$key=>$node){
            $decision=hmigr_decide_votes($votes[$key]??[]);$target=(int)($decision['target_local_hotel_id']??0);
            if($decision['bucket']==='auto_accept'){
                $stats['provisional_auto']++;
                if(!isset($hotels[$target])||(int)$hotels[$target]['country_id']!==$node['country_id']){$decision=['bucket'=>'hard_conflict','reason'=>'current_target_country_mismatch','target_local_hotel_id'=>$target,'evidence_sources'=>$decision['evidence_sources']];$stats['country_mismatch_blocks']++;}
                elseif($node['provider']==='anex'&&isset($excluded[(int)$node['external_id']][$target])){$decision=['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target_local_hotel_id'=>$target,'evidence_sources'=>$decision['evidence_sources']];$stats['pair_exclusion_blocks']++;}
                else{$other=$node['provider']==='anex'?hmigr_other($anClaims[$target]??[],(int)$node['external_id']):hmigr_other($andClaims[$target]??[],(string)$node['external_id']);if($other){$decision=['bucket'=>'needs_extra_evidence','reason'=>'same_provider_target_occupied','target_local_hotel_id'=>$target,'evidence_sources'=>$decision['evidence_sources'],'other_claims'=>$other];$stats['target_occupancy_blocks']++;}}
            }
            if(($decision['bucket']??'')==='needs_extra_evidence'&&isset($relationHard[$key])&&empty($votes[$key]))$decision=['bucket'=>'hard_conflict','reason'=>'supplier_relation_hard_conflict','target_local_hotel_id'=>null,'evidence_sources'=>[],'relation_reasons'=>array_keys($relationHard[$key])];
            $compactVotes=[];foreach(($votes[$key]??[])as$t=>$v)$compactVotes[(string)$t]=array_keys($v['sources']??[]);
            $rows[$key]=['provider'=>$node['provider'],'external_id'=>$node['external_id'],'country_id'=>$node['country_id'],'live'=>$node['live'],'observation_count'=>$node['observation_count'],'bucket'=>$decision['bucket'],'reason'=>$decision['reason'],'target_local_hotel_id'=>$decision['target_local_hotel_id']??null,'evidence_sources'=>$decision['evidence_sources']??[],'candidate_votes'=>$compactVotes];
        }

        [$rows,$stats['global_uniqueness_demotions']]=hmigr_apply_uniqueness($rows);
        $buckets=['auto_accept'=>[],'needs_extra_evidence'=>[],'hard_conflict'=>[],'manual_last'=>[]];
        foreach($rows as$row){$buckets[$row['bucket']][]=$row;$stats[$row['bucket']]++;if($row['bucket']==='auto_accept'){if($row['provider']==='anex')$stats['auto_anex']++;else$stats['auto_andromeda']++;if($row['live'])$stats['auto_live']++;}}
        foreach($buckets as&$set)usort($set,static fn($x,$y)=>(($y['live']?1:0)<=>($x['live']?1:0))?:($y['observation_count']<=>$x['observation_count'])?:($x['country_id']<=>$y['country_id'])?:strcmp($x['provider'],$y['provider'])?:strcmp((string)$x['external_id'],(string)$y['external_id']));unset($set);

        $before=[];foreach($anClaims as$local=>$ids)if($ids)$before[(int)$local]['anex']=true;foreach($andClaims as$local=>$ids)if($ids)$before[(int)$local]['andromeda']=true;$after=$before;
        foreach($buckets['auto_accept']as$row)$after[(int)$row['target_local_hotel_id']][$row['provider']]=true;$tripleDelta=0;foreach($after as$local=>$p){$was=!empty($before[$local]['anex'])&&!empty($before[$local]['andromeda']);$now=!empty($p['anex'])&&!empty($p['andromeda']);if(!$was&&$now)$tripleDelta++;}$stats['estimated_new_triple']=$tripleDelta;
        $reasonCounts=[];$evidenceCounts=[];foreach($buckets as$bucket=>$set)foreach($set as$row){$reasonCounts[$bucket][$row['reason']]=($reasonCounts[$bucket][$row['reason']]??0)+1;if($bucket==='auto_accept')foreach($row['evidence_sources']as$src)$evidenceCounts[$src]=($evidenceCounts[$src]??0)+1;}foreach($reasonCounts as&$x)ksort($x);unset($x);ksort($evidenceCounts);

        $db->commit();return ['status'=>'completed','operation_id'=>$operation,'mode'=>'current_core8_mass_three_provider_identity_graph_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'booking_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'stats'=>$stats,'reason_counts'=>$reasonCounts,'evidence_counts'=>$evidenceCounts,'buckets'=>$buckets,'guards'=>['current_snapshot_only'=>true,'core8_full_unresolved_universe'=>true,'blocking_indexes_used'=>true,'blocking_indexes_built_once'=>true,'multi_evidence_consensus_required'=>2,'coordinate_conflict_block_m'=>HMIGR_COORD_BLOCK_M,'critical_qualifiers_preserved'=>true,'manual_and_pair_exclusions_protected'=>true,'same_provider_target_occupancy_protected'=>true,'global_same_provider_one_to_one_required'=>true,'opposite_provider_same_local_allowed'=>true,'live_priority_does_not_lower_thresholds'=>true,'stars_not_identity'=>true,'supplier_calls'=>0,'database_writes'=>0]];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded mass identity graph workflow\n");exit(64);}
