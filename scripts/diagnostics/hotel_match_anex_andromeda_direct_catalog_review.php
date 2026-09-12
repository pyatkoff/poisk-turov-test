<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_global_approx_name_rescue.php';

const HMADCR_OPERATION = 'hotel-match-anex-andromeda-direct-catalog-review-1971-20260911-v1';
const HMADCR_COORD_BLOCK_M = 5000.0;
const HMADCR_COORD_DIRECT_M = 1000.0;
const HMADCR_COORD_SINGLE_M = 80.0;
const HMADCR_FUZZY_MIN = 0.82;
const HMADCR_CHAR_MIN = 0.86;
const HMADCR_MARGIN_MIN = 0.15;
const HMADCR_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmadcr_name_variants(string $name): array {
    $name=trim(preg_replace('/\s+/u',' ',$name)??$name);
    if($name==='')return [];
    $out=[$name=>true];
    if(preg_match_all('/\(\s*(?:ex\.?|former(?:ly)?)\s*[:.\-]?\s*([^\)]+)\)/iu',$name,$m,PREG_SET_ORDER)){
        $main=trim(preg_replace('/\s*\(\s*(?:ex\.?|former(?:ly)?)\s*[:.\-]?\s*[^\)]+\)/iu',' ',$name)??'');
        if($main!=='')$out[$main]=true;
        foreach($m as $hit){$former=trim((string)($hit[1]??''));if($former!=='')$out[$former]=true;}
    }
    return array_keys($out);
}
function hmadcr_expand_names(array $names): array {
    $out=[];foreach($names as $name)foreach(hmadcr_name_variants((string)$name) as $v)$out[$v]=true;return array_keys($out);
}
function hmadcr_identity_tokens(string $name,array $places=[]): array {
    $drop=array_fill_keys(['ex','former','formerly','old','oldname','exhotel'],true);$out=[];
    foreach(hmgan_tokens($name,$places) as $t){$t=(string)$t;if($t===''||isset($drop[$t])||preg_match('/^\d+(?:star|stars)?$/',$t))continue;$out[$t]=true;}
    return array_keys($out);
}
function hmadcr_key(array $tokens): string {$x=$tokens;sort($x,SORT_STRING);return implode(' ',$x);}
function hmadcr_pair(string $source,string $target,array $sourcePlaces,array $targetPlaces): array {
    $s=hmadcr_identity_tokens($source,$sourcePlaces);$t=hmadcr_identity_tokens($target,$targetPlaces);$shared=array_values(array_intersect($s,$t));$align=hmgan_align($s,$t);
    $sk=implode(' ',$s);$tk=implode(' ',$t);$char=hmgan_char_similarity($sk,$tk);$exact=$s&&$t&&hmadcr_key($s)===hmadcr_key($t);$critical=hmgcr_critical($source)===hmgcr_critical($target);
    $ordered=hmgcr_ordered($s,$t)||hmgcr_ordered($t,$s);$anchor=false;if(isset($s[0],$t[0]))$anchor=hmgan_token_similarity((string)$s[0],(string)$t[0])>=0.86;
    $rank=min((float)$align['score'],$char);
    return ['exact_bag'=>$exact,'critical_ok'=>$critical,'cross_script'=>hmgcr_cross_script($source,$target),'shared'=>count($shared),'shared_tokens'=>$shared,'aligned'=>(int)$align['aligned'],'fuzzy_score'=>(float)$align['score'],'char_similarity'=>round($char,6),'rank_score'=>round($rank,6),'token_diff'=>count(array_diff($s,$t))+count(array_diff($t,$s)),'ordered'=>$ordered,'anchor_ok'=>$anchor,'source_tokens'=>$s,'target_tokens'=>$t,'aligned_pairs'=>$align['pairs'],'source'=>$source,'target'=>$target];
}
function hmadcr_best_pair(array $sourceNames,array $targetNames,array $sourcePlaces,array $targetPlaces): array {
    $best=['exact_bag'=>false,'critical_ok'=>false,'shared'=>0,'aligned'=>0,'fuzzy_score'=>0.0,'char_similarity'=>0.0,'rank_score'=>0.0,'source'=>'','target'=>''];
    foreach($sourceNames as $s){$s=trim((string)$s);if($s==='')continue;foreach($targetNames as $t){$t=trim((string)$t);if($t==='')continue;$p=hmadcr_pair($s,$t,$sourcePlaces,$targetPlaces);if(!($p['critical_ok']??false))continue;
        $better=(int)$p['exact_bag']>(int)($best['exact_bag']??false)||((bool)$p['exact_bag']===(bool)($best['exact_bag']??false)&&($p['rank_score']>$best['rank_score']||($p['rank_score']===$best['rank_score']&&$p['aligned']>$best['aligned'])));if($better)$best=$p;}}
    return $best;
}
function hmadcr_build_index(array $rows): array {
    $exact=[];$token=[];$tri=[];
    foreach($rows as $id=>$r){$country=(int)$r['country_id'];foreach($r['names'] as $name){$tokens=hmadcr_identity_tokens((string)$name,$r['places']);if(!$tokens)continue;$key=hmadcr_key($tokens);if($key!=='')$exact[$country][$key][(string)$id]=true;foreach($tokens as $t)$token[$country][$t][(string)$id]=true;$nameKey=implode(' ',$tokens);foreach(hmgan_trigrams($nameKey) as $g)$tri[$country][$g][(string)$id]=true;}}
    return ['exact'=>$exact,'token'=>$token,'trigram'=>$tri];
}
function hmadcr_candidate_ids(array $source,int $country,array $index,int $limit=120): array {
    $exact=[];$tokens=[];$grams=[];
    foreach($source['names'] as $name){$ts=hmadcr_identity_tokens((string)$name,$source['places']);if(!$ts)continue;$key=hmadcr_key($ts);foreach(array_keys($index['exact'][$country][$key]??[]) as $id)$exact[(string)$id]=true;foreach($ts as $t)$tokens[$t]=true;foreach(hmgan_trigrams(implode(' ',$ts)) as $g)$grams[$g]=true;}
    $hits=[];foreach(array_keys($tokens) as $t)foreach(array_keys($index['token'][$country][$t]??[]) as $id)$hits[(string)$id]=($hits[(string)$id]??0)+3;foreach(array_keys($grams) as $g)foreach(array_keys($index['trigram'][$country][$g]??[]) as $id)$hits[(string)$id]=($hits[(string)$id]??0)+1;arsort($hits,SORT_NUMERIC);
    $out=$exact;foreach($hits as $id=>$score){if($score<2)break;$out[(string)$id]=true;if(count($out)>=$limit)break;}return array_keys($out);
}
function hmadcr_validation(array $anexLocal,?int $andromedaLocal): string {
    if($anexLocal&&$andromedaLocal!==null)return in_array($andromedaLocal,$anexLocal,true)?'same_local':'different_local';
    if($anexLocal)return 'anex_only';if($andromedaLocal!==null)return 'andromeda_only';return 'neither_side';
}
function hmadcr_route(array $pair,?float $distance,bool $place,float $margin,string $validation): ?string {
    if(!($pair['critical_ok']??false)||(int)($pair['aligned']??0)<1)return null;if($distance!==null&&$distance>HMADCR_COORD_BLOCK_M)return null;
    $shared=(int)($pair['shared']??0);$aligned=(int)($pair['aligned']??0);$direct=$place||($distance!==null&&$distance<=HMADCR_COORD_DIRECT_M);
    if($validation==='same_local'&&(($pair['exact_bag']??false)||($aligned>=2&&(float)$pair['rank_score']>=0.75)))return 'validated_existing_same_local';
    if(($pair['exact_bag']??false)&&$shared>=3)return ($pair['cross_script']??false)?'direct_translit_exact_3plus':'direct_exact_3plus';
    if(($pair['exact_bag']??false)&&$shared>=2&&$direct)return ($pair['cross_script']??false)?'direct_translit_exact_geo':'direct_exact_geo';
    if(($pair['exact_bag']??false)&&$shared>=2)return 'direct_exact_no_geo';
    if(($pair['exact_bag']??false)&&$shared===1&&$place&&$distance!==null&&$distance<=HMADCR_COORD_SINGLE_M)return 'direct_single_token_ultratight';
    if($aligned>=3&&(float)$pair['fuzzy_score']>=0.90&&(float)$pair['char_similarity']>=0.90&&$margin>=0.18&&$direct&&(($pair['anchor_ok']??false)||($pair['ordered']??false)))return 'direct_high_fuzzy_geo';
    if($aligned>=2&&(float)$pair['fuzzy_score']>=HMADCR_FUZZY_MIN&&(float)$pair['char_similarity']>=HMADCR_CHAR_MIN&&$margin>=HMADCR_MARGIN_MIN&&$direct&&(($pair['anchor_ok']??false)||($pair['ordered']??false)))return 'direct_strong_fuzzy_geo';
    return null;
}
function hmadcr_grade(string $reason): string {
    return in_array($reason,['validated_existing_same_local','direct_translit_exact_3plus','direct_exact_3plus','direct_translit_exact_geo','direct_exact_geo','direct_high_fuzzy_geo'],true)?'high_confidence':'strong_candidate';
}
function hmadcr_decide(array $source,int $country,array $rows,array $index): array {
    $ids=hmadcr_candidate_ids($source,$country,$index);$pool=[];
    foreach($ids as $id){if(!isset($rows[$id])||(int)$rows[$id]['country_id']!==$country)continue;$t=$rows[$id];$pair=hmadcr_best_pair($source['names'],$t['names'],$source['places'],$t['places']);if(!($pair['critical_ok']??false)||($pair['rank_score']??0)<=0)continue;$distance=fc_dist($source['latitude'],$source['longitude'],$t['latitude'],$t['longitude']);$place=hmgcr_place($source['places'],$t['places']);$validation=hmadcr_validation($source['local_ids'],$t['local_id']);$pool[]=['andromeda_external_id'=>(string)$id,'pair'=>$pair,'distance_m'=>$distance===null?null:round($distance,2),'place_match'=>$place,'validation'=>$validation,'target'=>$t];}
    if(!$pool)return ['bucket'=>'unmatched','reason'=>'no_direct_name_candidate','candidate_count'=>count($ids)];
    usort($pool,static function($a,$b){$ea=(int)($a['pair']['exact_bag']??false);$eb=(int)($b['pair']['exact_bag']??false);if($ea!==$eb)return $eb<=>$ea;if($a['pair']['rank_score']!==$b['pair']['rank_score'])return $b['pair']['rank_score']<=>$a['pair']['rank_score'];if($a['pair']['aligned']!==$b['pair']['aligned'])return $b['pair']['aligned']<=>$a['pair']['aligned'];return strcmp($a['andromeda_external_id'],$b['andromeda_external_id']);});
    $exactPool=array_values(array_filter($pool,static fn($x)=>(bool)($x['pair']['exact_bag']??false)));
    if(count($exactPool)>1){$same=array_values(array_filter($exactPool,static fn($x)=>$x['validation']==='same_local'));if(count($same)===1)$pool=$same;else return ['bucket'=>'ambiguous','reason'=>'multiple_direct_exact_candidates','candidate_ids'=>array_map(static fn($x)=>$x['andromeda_external_id'],$exactPool)];}
    $best=$pool[0];$second=$pool[1]??null;$margin=$second?((float)$best['pair']['rank_score']-(float)$second['pair']['rank_score']):1.0;
    $identity=(bool)($best['pair']['exact_bag']??false)||((int)$best['pair']['aligned']>=2&&(float)$best['pair']['fuzzy_score']>=HMADCR_FUZZY_MIN&&(float)$best['pair']['char_similarity']>=HMADCR_CHAR_MIN);
    if($identity&&$best['distance_m']!==null&&(float)$best['distance_m']>HMADCR_COORD_BLOCK_M)return ['bucket'=>'hard_conflict','reason'=>'direct_identity_coordinate_conflict_gt_5km']+$best+['score_margin'=>round($margin,6)];
    if($identity&&$best['validation']==='different_local')return ['bucket'=>'hard_conflict','reason'=>'direct_identity_existing_local_conflict']+$best+['score_margin'=>round($margin,6)];
    $route=hmadcr_route($best['pair'],$best['distance_m']===null?null:(float)$best['distance_m'],(bool)$best['place_match'],$margin,$best['validation']);
    if($route===null)return ['bucket'=>'ambiguous','reason'=>$margin<HMADCR_MARGIN_MIN?'direct_winner_margin_small':'direct_identity_or_geo_insufficient']+$best+['score_margin'=>round($margin,6)];
    $grade=hmadcr_grade($route);if($route==='direct_exact_no_geo')$grade='strong_candidate';
    return ['bucket'=>$grade,'reason'=>$route]+$best+['score_margin'=>round($margin,6)];
}
function hmadcr_review(PDO $db,string $operation=HMADCR_OPERATION): array {
    if($operation!==HMADCR_OPERATION)throw new RuntimeException('HMADCR_OPERATION_SCOPE');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);$shaCountry=fc_sha_countries($db);
        $andObs=[];$andObsCount=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$andObsCount[$id]=($andObsCount[$id]??0)+1;if(!isset($andObs[$id]))$andObs[$id]=$o;}
        $andRows=[];$countryUnknownAnd=0;foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['external_hotel_id'];$obs=$andObs[$id]??null;$country=(int)($obs['country_id']??0);if(!isset(HMADCR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMADCR_CORE8[$country])){$countryUnknownAnd++;continue;}$s=hmgcr_andromeda_source($r,$obs);$names=hmadcr_expand_names($s['names']);if(!$names)continue;$andRows[$id]=['external_id'=>$id,'country_id'=>$country,'names'=>$names,'places'=>$s['places'],'latitude'=>$s['latitude'],'longitude'=>$s['longitude'],'category'=>$s['category'],'live'=>(int)($andObsCount[$id]??0)>0,'observation_count'=>(int)($andObsCount[$id]??0),'local_id'=>($r['decision_status']==='accepted'&&$r['local_hotel_id']!==null)?(int)$r['local_hotel_id']:null,'decision_status'=>(string)$r['decision_status']];}
        $index=hmadcr_build_index($andRows);
        $anLocal=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC) as $r)$anLocal[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$anLocal[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $anObs=[];$anObsCount=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(int)$o['anex_hotel_id'];$anObsCount[$id]=max((int)($anObsCount[$id]??0),(int)($o['search_count']??0));if(!isset($anObs[$id]))$anObs[$id]=$o;}
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
        $ids=[];foreach(array_keys($staging) as $id)$ids[(int)$id]=true;foreach(array_keys($anObs) as $id)$ids[(int)$id]=true;ksort($ids,SORT_NUMERIC);
        $pairs=[];$ambiguous=[];$hard=[];$unmatched=[];$anexExamined=0;$countryUnknownAn=0;$statsByCountry=[];
        foreach(array_keys($ids) as $id){$stage=$staging[$id]??[];$obs=$anObs[$id]??[];$country=(int)($obs['country_id']??0);if(!isset(HMADCR_CORE8[$country]))$country=(int)(fc_country($stage['api_country']??'')??0);if(!isset(HMADCR_CORE8[$country])){$countryUnknownAn++;continue;}$names=hmadcr_expand_names(array_values(array_unique(array_filter([(string)($obs['hotel_name']??''),(string)($stage['api_name']??''),(string)($stage['xml_name']??''),(string)($stage['xml_alternate_name']??'')],static fn($v)=>trim($v)!==''))));if(!$names)continue;$source=['external_id'=>$id,'country_id'=>$country,'names'=>$names,'places'=>array_values(array_unique(array_filter([(string)($stage['api_region']??''),(string)($stage['api_town']??''),(string)($obs['region_name']??'')],static fn($v)=>trim($v)!==''))),'latitude'=>fc_num($stage['latitude']??null),'longitude'=>fc_num($stage['longitude']??null),'live'=>isset($anObs[$id]),'observation_count'=>(int)($anObsCount[$id]??0),'local_ids'=>array_map('intval',array_keys($anLocal[$id]??[]))];$anexExamined++;$d=hmadcr_decide($source,$country,$andRows,$index);$row=['anex_hotel_id'=>$id,'country_id'=>$country,'anex_names'=>$names,'anex_places'=>$source['places'],'anex_live'=>$source['live'],'anex_observation_count'=>$source['observation_count'],'anex_local_ids'=>$source['local_ids']]+$d;if(isset($d['target'])){$t=$d['target'];$row['andromeda_names']=$t['names'];$row['andromeda_places']=$t['places'];$row['andromeda_live']=$t['live'];$row['andromeda_observation_count']=$t['observation_count'];$row['andromeda_local_id']=$t['local_id'];unset($row['target']);}
            $bucket=$row['bucket'];$statsByCountry[$country][$bucket]=($statsByCountry[$country][$bucket]??0)+1;if($bucket==='high_confidence'||$bucket==='strong_candidate')$pairs[]=$row;elseif($bucket==='hard_conflict')$hard[]=$row;elseif($bucket==='ambiguous')$ambiguous[]=$row;else$unmatched[]=['anex_hotel_id'=>$id,'country_id'=>$country,'name'=>$names[0]??'','live'=>$source['live'],'reason'=>$row['reason']??'unmatched'];}
        $byTarget=[];foreach($pairs as $i=>$r)$byTarget[(string)$r['andromeda_external_id']][]=$i;$demote=[];foreach($byTarget as $target=>$idxs)if(count($idxs)>1){$same=array_values(array_filter($idxs,static fn($i)=>($pairs[$i]['validation']??'')==='same_local'));if(count($same)===1){foreach($idxs as $i)if($i!==$same[0])$demote[$i]=true;}else foreach($idxs as $i)$demote[$i]=true;}
        if($demote){$keep=[];foreach($pairs as $i=>$r){if(isset($demote[$i])){$r['bucket']='ambiguous';$r['reason']='andromeda_target_collision';$ambiguous[]=$r;}else$keep[]=$r;}$pairs=$keep;}
        $stats=['anex_catalog_core8'=>$anexExamined,'andromeda_catalog_core8'=>count($andRows),'anex_country_unknown'=>$countryUnknownAn,'andromeda_country_unknown'=>$countryUnknownAnd,'high_confidence'=>0,'strong_candidate'=>0,'validated_same_local'=>0,'new_high_confidence'=>0,'new_strong_candidate'=>0,'both_unlinked'=>0,'one_side_only'=>0,'existing_local_conflicts'=>count(array_filter($hard,static fn($r)=>($r['reason']??'')==='direct_identity_existing_local_conflict')),'hard_conflict'=>count($hard),'ambiguous'=>count($ambiguous),'unmatched_anex'=>count($unmatched),'target_collisions'=>count($demote)];
        $reasonCounts=[];$validationCounts=[];foreach($pairs as $r){$stats[$r['bucket']]++;$v=(string)$r['validation'];$validationCounts[$v]=($validationCounts[$v]??0)+1;$reasonCounts[$r['reason']]=($reasonCounts[$r['reason']]??0)+1;if($v==='same_local')$stats['validated_same_local']++;else{if($r['bucket']==='high_confidence')$stats['new_high_confidence']++;else$stats['new_strong_candidate']++;if($v==='neither_side')$stats['both_unlinked']++;else$stats['one_side_only']++;}}
        foreach($hard as $r){$reasonCounts[$r['reason']]=($reasonCounts[$r['reason']]??0)+1;$validationCounts[$r['validation']??'unknown']=($validationCounts[$r['validation']??'unknown']??0)+1;}foreach($ambiguous as $r)$reasonCounts[$r['reason']]=($reasonCounts[$r['reason']]??0)+1;ksort($reasonCounts);ksort($validationCounts);ksort($statsByCountry);
        usort($pairs,static fn($a,$b)=>(($a['bucket']==='high_confidence'?0:1)<=>($b['bucket']==='high_confidence'?0:1))?:((($b['anex_live']??false)?1:0)<=>((($a['anex_live']??false)?1:0)))?:($b['anex_observation_count']<=>$a['anex_observation_count'])?:($a['anex_hotel_id']<=>$b['anex_hotel_id']));
        $db->commit();return ['status'=>'completed','operation_id'=>$operation,'mode'=>'direct_anex_andromeda_full_catalog_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'stats'=>$stats,'country_buckets'=>$statsByCountry,'reason_counts'=>$reasonCounts,'validation_counts'=>$validationCounts,'pairs'=>$pairs,'hard_conflicts'=>$hard,'ambiguous'=>$ambiguous,'unmatched_anex'=>$unmatched,'guards'=>['full_available_core8_supplier_catalogs'=>true,'tourvisor_local_not_required_for_discovery'=>true,'existing_local_used_as_validation_only'=>true,'generic_hotel_resort_spa_not_identity'=>true,'former_name_variants'=>true,'deterministic_transliteration'=>true,'critical_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'coordinate_conflict_auto_block_m'=>HMADCR_COORD_BLOCK_M,'stars_not_identity'=>true,'single_token_requires_ultratight_geo'=>true,'coordinate_only_auto_accept'=>false,'russia_abkhazia_excluded'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded CURRENT direct-catalog workflow\n");exit(64);}
