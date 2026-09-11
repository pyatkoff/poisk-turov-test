<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_anex_andromeda_direct_catalog_review.php';

const HMADCRH_LOCALITY_MIN_DOCS = 5;
const HMADCRH_LOCALITY_MIN_SHARE = 0.03;

function hmadcrh_locality_key(array $row): string {
    $country=(int)($row['country_id']??0);$place='';
    foreach(($row['places']??[]) as $v){$n=fc_norm(hmgcr_latin((string)$v));if($n!==''){$place=$n;break;}}
    return $country.'|'.$place;
}
function hmadcrh_locality_common(array $rows): array {
    $totals=[];$docs=[];
    foreach($rows as $row){$key=hmadcrh_locality_key($row);if($key==='0|'||str_ends_with($key,'|'))continue;$totals[$key]=($totals[$key]??0)+1;$seen=[];$name=(string)(($row['names'][0]??''));foreach(hmadcr_identity_tokens($name,[]) as $t)$seen[(string)$t]=true;foreach(array_keys($seen) as $t)$docs[$key][$t]=($docs[$key][$t]??0)+1;}
    $common=[];foreach($docs as $key=>$counts){$n=max(1,(int)($totals[$key]??0));foreach($counts as $token=>$count){if($count>=HMADCRH_LOCALITY_MIN_DOCS&&($count/$n)>=HMADCRH_LOCALITY_MIN_SHARE)$common[$key][$token]=['docs'=>$count,'share'=>round($count/$n,6)];}}
    return ['totals'=>$totals,'common'=>$common];
}
function hmadcrh_identity_anchors(array $pair,array $target,array $locality): array {
    $key=hmadcrh_locality_key($target);$common=$locality['common'][$key]??[];$identity=[];$geo=[];
    foreach(($pair['aligned_pairs']??[]) as $p){$targetToken=(string)($p['target']??'');if($targetToken!==''&&isset($common[$targetToken])){$q=$p;$q['locality_docs']=$common[$targetToken]['docs'];$q['locality_share']=$common[$targetToken]['share'];$geo[]=$q;}else{$identity[]=$p;}}
    return ['identity'=>$identity,'locality_common'=>$geo,'identity_aligned'=>count($identity),'locality_common_aligned'=>count($geo),'locality_key'=>$key,'locality_hotel_count'=>(int)($locality['totals'][$key]??0)];
}
function hmadcrh_supplier_rows(PDO $db): array {
    $shaCountry=fc_sha_countries($db);$latest=[];$obsCount=[];
    foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$obsCount[$id]=($obsCount[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}
    $rows=[];foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['external_hotel_id'];$obs=$latest[$id]??null;$country=(int)($obs['country_id']??0);if(!isset(HMADCR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMADCR_CORE8[$country]))continue;$s=hmgcr_andromeda_source($r,$obs);$names=hmadcr_expand_names($s['names']);if(!$names)continue;$rows[$id]=['external_id'=>$id,'country_id'=>$country,'names'=>$names,'places'=>$s['places'],'latitude'=>$s['latitude'],'longitude'=>$s['longitude'],'category'=>$s['category'],'live'=>(int)($obsCount[$id]??0)>0,'observation_count'=>(int)($obsCount[$id]??0)];}
    return $rows;
}
function hmadcrh_review(PDO $db,string $operation=HMADCR_OPERATION): array {
    if($operation!==HMADCR_OPERATION)throw new RuntimeException('HMADCRH_OPERATION_SCOPE');
    $base=hmadcr_review($db,$operation);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{$rows=hmadcrh_supplier_rows($db);$locality=hmadcrh_locality_common($rows);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    $kept=[];$demoted=[];$hardeningMissing=0;$localityRemoved=0;
    foreach(($base['pairs']??[]) as $row){$id=(string)($row['andromeda_external_id']??'');$target=$rows[$id]??null;if(!$target){$row['bucket']='ambiguous';$row['reason']='hardening_target_missing_current_supplier_catalog';$demoted[]=$row;$hardeningMissing++;continue;}
        $anchors=hmadcrh_identity_anchors($row['pair']??[],$target,$locality);$row['identity_anchors']=$anchors;$localityRemoved+=(int)$anchors['locality_common_aligned'];
        if(($row['validation']??'')==='same_local'){$row['hardening_reason']='existing_same_local_validation_only';$kept[]=$row;continue;}
        $single=($row['reason']??'')==='direct_single_token_ultratight';$required=$single?1:2;$ok=(int)$anchors['identity_aligned']>=$required;
        if($single)$ok=$ok&&($row['place_match']??false)&&$row['distance_m']!==null&&(float)$row['distance_m']<=HMADCR_COORD_SINGLE_M;
        if(!$ok){$row['bucket']='ambiguous';$row['reason']=$single?'locality_hardened_single_identity_missing':'locality_common_identity_anchors_lt_2';$demoted[]=$row;continue;}
        $row['hardening_reason']='locality_hardened_identity_ok';$kept[]=$row;
    }
    $stats=$base['stats']??[];$stats['base_pairs']=count($base['pairs']??[]);$stats['hardened_pairs']=count($kept);$stats['locality_demoted']=count($demoted);$stats['hardening_target_missing']=$hardeningMissing;$stats['locality_common_aligned_removed']=$localityRemoved;$stats['high_confidence']=0;$stats['strong_candidate']=0;$stats['validated_same_local']=0;$stats['new_high_confidence']=0;$stats['new_strong_candidate']=0;$stats['both_unlinked']=0;$stats['one_side_only']=0;
    $validationCounts=[];$reasonCounts=$base['reason_counts']??[];
    foreach($kept as $r){$b=(string)($r['bucket']??'');if(isset($stats[$b]))$stats[$b]++;$v=(string)($r['validation']??'unknown');$validationCounts[$v]=($validationCounts[$v]??0)+1;if($v==='same_local')$stats['validated_same_local']++;else{if($b==='high_confidence')$stats['new_high_confidence']++;elseif($b==='strong_candidate')$stats['new_strong_candidate']++;if($v==='neither_side')$stats['both_unlinked']++;elseif(in_array($v,['anex_only','andromeda_only'],true))$stats['one_side_only']++;}}
    foreach($demoted as $r)$reasonCounts[$r['reason']]=($reasonCounts[$r['reason']]??0)+1;ksort($reasonCounts);ksort($validationCounts);
    $ambiguous=array_merge($base['ambiguous']??[],$demoted);$stats['ambiguous']=count($ambiguous);
    return ['status'=>'completed','operation_id'=>$operation,'mode'=>'direct_anex_andromeda_full_catalog_locality_hardened_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$base['coverage']??[],'stats'=>$stats,'country_buckets'=>$base['country_buckets']??[],'reason_counts'=>$reasonCounts,'validation_counts'=>$validationCounts,'pairs'=>$kept,'locality_demoted'=>$demoted,'hard_conflicts'=>$base['hard_conflicts']??[],'ambiguous'=>$ambiguous,'unmatched_anex'=>$base['unmatched_anex']??[],'hardening'=>['demotion_only'=>true,'locality_min_docs'=>HMADCRH_LOCALITY_MIN_DOCS,'locality_min_share'=>HMADCRH_LOCALITY_MIN_SHARE,'new_pair_min_non_local_identity_anchors'=>2,'single_token_ultratight_min_non_local_identity_anchors'=>1,'existing_same_local_validation_exempt_from_new_pair_gate'=>true,'second_snapshot_only_demotes'=>true]];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded direct catalog workflow\n");exit(64);}
