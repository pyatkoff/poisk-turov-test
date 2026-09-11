<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_global_tourvisor_rescue.php';

const HMGAN_OPERATION = 'hotel-match-global-approx-name-rescue-1971-20260911-v1';
const HMGAN_COORD_BLOCK_M = 5000.0;
const HMGAN_COORD_DIRECT_M = 1000.0;
const HMGAN_FUZZY_MIN = 0.78;
const HMGAN_CHAR_MIN = 0.82;
const HMGAN_MARGIN_MIN = 0.12;
const HMGAN_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmgan_structural(): array {
    static $x=null;
    if ($x!==null) return $x;
    $x=hmgcr_weak()+array_fill_keys([
        'village','center','centre','palace','villa','villas','boutique','inn','lodge','house','houses',
        'residence','residences','rooms','room','complex','complexes','collection','premium','luxury','deluxe',
        'international','beachfront','front','by','at','of'
    ],true);
    foreach (['annex','beach','garden','north','south'] as $critical) unset($x[$critical]);
    return $x;
}
function hmgan_tokens(string $name,array $places=[]): array {
    $place=hmgcr_place_token_set($places);$drop=hmgan_structural();$critical=array_fill_keys(['annex','beach','garden','north','south'],true);$out=[];
    foreach (fc_tokens($name,true) as $raw) {
        $t=fc_norm(hmgcr_latin((string)$raw));
        if ($t==='' || isset($critical[$t]) || isset($drop[$t]) || hmgcr_place_like($t,$place)) continue;
        $out[$t]=true;
    }
    return array_keys($out);
}
function hmgan_name_key(string $name,array $places=[]): string { return implode(' ',hmgan_tokens($name,$places)); }
function hmgan_trigrams(string $value): array {
    $v=preg_replace('/[^a-z0-9]+/','',fc_norm(hmgcr_latin($value)))??'';
    $n=strlen($v);if($n<3)return $v===''?[]:[$v];$out=[];
    for($i=0;$i<=$n-3;$i++)$out[substr($v,$i,3)]=true;
    return array_keys($out);
}
function hmgan_char_similarity(string $a,string $b): float {
    $a=preg_replace('/\s+/','',trim($a))??'';$b=preg_replace('/\s+/','',trim($b))??'';
    if($a===''||$b==='')return 0.0;if($a===$b)return 1.0;
    $max=max(strlen($a),strlen($b));$lev=$max?max(0.0,1.0-levenshtein($a,$b)/$max):0.0;
    similar_text($a,$b,$pct);return max($lev,$pct/100.0);
}
function hmgan_token_similarity(string $a,string $b): float {
    if($a===$b)return 1.0;$max=max(strlen($a),strlen($b));$min=min(strlen($a),strlen($b));if($min<5)return 0.0;
    $d=levenshtein($a,$b);$limit=$max>=8?2:1;if($d>$limit){similar_text($a,$b,$pct);return $pct>=86.0?$pct/100.0:0.0;}
    return max(0.0,1.0-$d/$max);
}
function hmgan_align(array $source,array $target): array {
    $pairs=[];$used=[];$sum=0.0;
    foreach($source as $s){$best=0.0;$bestJ=null;foreach($target as $j=>$t){if(isset($used[$j]))continue;$sim=hmgan_token_similarity((string)$s,(string)$t);if($sim>$best){$best=$sim;$bestJ=$j;}}
        if($bestJ!==null&&$best>=0.80){$used[$bestJ]=true;$sum+=$best;$pairs[]=['source'=>$s,'target'=>$target[$bestJ],'similarity'=>round($best,6)];}
    }
    $den=count($source)+count($target);$score=$den?2.0*$sum/$den:0.0;
    return ['aligned'=>count($pairs),'score'=>round($score,6),'pairs'=>$pairs];
}
function hmgan_pair(string $source,string $target,array $sourcePlaces,array $targetPlaces): array {
    $s=hmgan_tokens($source,$sourcePlaces);$t=hmgan_tokens($target,$targetPlaces);$align=hmgan_align($s,$t);
    $sk=implode(' ',$s);$tk=implode(' ',$t);$char=hmgan_char_similarity($sk,$tk);$critical=hmgcr_critical($source)===hmgcr_critical($target);
    $rank=min((float)$align['score'],$char);
    return ['fuzzy_score'=>$align['score'],'char_similarity'=>round($char,6),'rank_score'=>round($rank,6),'aligned'=>$align['aligned'],'pairs'=>$align['pairs'],'source_tokens'=>$s,'target_tokens'=>$t,'critical_ok'=>$critical,'source'=>$source,'target'=>$target];
}
function hmgan_best_pair(array $sourceNames,array $targetNames,array $sourcePlaces,array $targetPlaces): array {
    $best=['rank_score'=>0.0,'fuzzy_score'=>0.0,'char_similarity'=>0.0,'aligned'=>0,'critical_ok'=>false,'source'=>'','target'=>''];
    foreach($sourceNames as $s){$s=trim((string)$s);if($s==='')continue;foreach($targetNames as $t){$t=trim((string)$t);if($t==='')continue;$p=hmgan_pair($s,$t,$sourcePlaces,$targetPlaces);if(!($p['critical_ok']??false))continue;if($p['rank_score']>$best['rank_score']||($p['rank_score']===$best['rank_score']&&$p['aligned']>$best['aligned']))$best=$p;}}
    return $best;
}
function hmgan_build_index(array $hotels,array $names): array {
    $tri=[];$target=[];
    foreach($hotels as $id=>$h){$id=(int)$id;$country=(int)$h['country_id'];$places=[(string)$h['region_name'],(string)$h['subregion_name']];$target[$country][$id]=true;
        foreach($names[$id]??[(string)$h['name']] as $name){$key=hmgan_name_key((string)$name,$places);if($key==='')continue;foreach(hmgan_trigrams($key) as $g)$tri[$country][$g][$id]=true;}
    }
    return ['trigram'=>$tri,'targets'=>$target];
}
function hmgan_candidate_ids(array $sourceNames,array $sourcePlaces,int $country,array $index,int $limit=120): array {
    $hits=[];$sourceTri=[];
    foreach($sourceNames as $name){$key=hmgan_name_key((string)$name,$sourcePlaces);if($key==='')continue;foreach(hmgan_trigrams($key) as $g)$sourceTri[$g]=true;}
    foreach(array_keys($sourceTri) as $g)foreach(array_keys($index['trigram'][$country][$g]??[]) as $id)$hits[(int)$id]=($hits[(int)$id]??0)+1;
    arsort($hits,SORT_NUMERIC);$out=[];foreach($hits as $id=>$n){if($n<2)break;$out[]=(int)$id;if(count($out)>=$limit)break;}return $out;
}
function hmgan_route(array $pair,?float $distance,bool $place,float $margin): ?string {
    if(!($pair['critical_ok']??false))return null;
    if($distance!==null&&$distance>HMGAN_COORD_BLOCK_M)return null;
    if((int)($pair['aligned']??0)<2)return null;
    if((float)($pair['fuzzy_score']??0)<HMGAN_FUZZY_MIN||(float)($pair['char_similarity']??0)<HMGAN_CHAR_MIN)return null;
    if($margin<HMGAN_MARGIN_MIN)return null;
    if(!$place&&!($distance!==null&&$distance<=HMGAN_COORD_DIRECT_M))return null;
    return $place?'approx_name_direct_place':'approx_name_direct_coordinate';
}
function hmgan_decide(array $source,int $country,array $index,array $hotels,array $names,array $sameProviderClaims,array $excluded=[]): array {
    $ids=hmgan_candidate_ids($source['names'],$source['places'],$country,$index);$pool=[];$occupied=0;$excludedCount=0;
    foreach($ids as $id){$id=(int)$id;if(!isset($hotels[$id])||(int)$hotels[$id]['country_id']!==$country)continue;if(isset($sameProviderClaims[$id])){$occupied++;continue;}if(isset($excluded[$id])){$excludedCount++;continue;}
        $target=$hotels[$id];$targetPlaces=[(string)$target['region_name'],(string)$target['subregion_name']];$pair=hmgan_best_pair($source['names'],$names[$id]??[(string)$target['name']],$source['places'],$targetPlaces);if(!($pair['critical_ok']??false)||$pair['rank_score']<=0)continue;
        $distance=fc_dist($source['latitude'],$source['longitude'],$target['latitude']??null,$target['longitude']??null);$place=hmgcr_place($source['places'],$targetPlaces);$pool[]=['id'=>$id,'pair'=>$pair,'distance_m'=>$distance===null?null:round($distance,2),'place_match'=>$place,'target'=>$target];
    }
    if(!$pool)return ['bucket'=>'near','reason'=>$ids?'approx_candidates_protected_or_insufficient':'no_approx_name_candidate','candidate_count'=>count($ids),'occupied_count'=>$occupied,'excluded_count'=>$excludedCount];
    usort($pool,static fn($a,$b)=>$b['pair']['rank_score']<=>$a['pair']['rank_score'] ?: $b['pair']['aligned']<=>$a['pair']['aligned'] ?: $a['id']<=>$b['id']);
    $best=$pool[0];$second=$pool[1]??null;$margin=$second?((float)$best['pair']['rank_score']-(float)$second['pair']['rank_score']):1.0;
    $identity=(int)$best['pair']['aligned']>=2&&(float)$best['pair']['fuzzy_score']>=HMGAN_FUZZY_MIN&&(float)$best['pair']['char_similarity']>=HMGAN_CHAR_MIN;
    if($identity&&$best['distance_m']!==null&&(float)$best['distance_m']>HMGAN_COORD_BLOCK_M)return ['bucket'=>'hard_conflict','reason'=>'approx_identity_coordinate_conflict_gt_5km','target_local_hotel_id'=>$best['id'],'distance_m'=>$best['distance_m'],'score_margin'=>round($margin,6),'pair'=>$best['pair']];
    $route=hmgan_route($best['pair'],$best['distance_m']===null?null:(float)$best['distance_m'],(bool)$best['place_match'],$margin);
    if($route===null){$reason=(int)$best['pair']['aligned']<2?'approx_identity_tokens_lt_2':((float)$best['pair']['fuzzy_score']<HMGAN_FUZZY_MIN?'approx_fuzzy_score_low':((float)$best['pair']['char_similarity']<HMGAN_CHAR_MIN?'approx_char_similarity_low':($margin<HMGAN_MARGIN_MIN?'approx_winner_margin_small':'approx_direct_geo_missing')));
        return ['bucket'=>'near','reason'=>$reason,'target_local_hotel_id'=>$best['id'],'score_margin'=>round($margin,6),'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'pair'=>$best['pair']];}
    return ['bucket'=>'prepared','reason'=>$route,'target_local_hotel_id'=>$best['id'],'target_name'=>$best['target']['name'],'target_region'=>$best['target']['region_name'],'target_subregion'=>$best['target']['subregion_name'],'target_category'=>$best['target']['category']===null?null:(int)$best['target']['category'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'score_margin'=>round($margin,6),'pair'=>$best['pair']];
}
function hmgan_review(PDO $db,string $operation=HMGAN_OPERATION): array {
    if($operation!==HMGAN_OPERATION)throw new RuntimeException('HMGAN_OPERATION_SCOPE');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);[$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);$shaCountry=fc_sha_countries($db);$index=hmgan_build_index($hotels,$names);
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $anClaims=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1")->fetchAll(PDO::FETCH_ASSOC) as $r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$mapped=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $latest=[];$andObsCount=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$andObsCount[$id]=($andObsCount[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}
        $prepared=[];$near=[];$hard=[];$stats=['andromeda_examined'=>0,'anex_observed_examined'=>0,'anex_staging_examined'=>0,'protected'=>0,'country_unknown'=>0,'prepared'=>0,'prepared_andromeda'=>0,'prepared_anex'=>0,'prepared_live'=>0,'prepared_star_mismatch'=>0,'hard_conflict'=>0,'near'=>0,'route_place'=>0,'route_coordinate'=>0,'target_collisions'=>0];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(HMGAN_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMGAN_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['andromeda_examined']++;$source=hmgcr_andromeda_source($r,$obs);$d=hmgan_decide($source,$country,$index,$hotels,$names,$andClaims,[]);$row=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'live'=>(int)($andObsCount[$external]??0)>0,'observation_count'=>(int)($andObsCount[$external]??0),'source_names'=>$source['names'],'source_places'=>$source['places'],'source_category'=>$source['category']]+$d;$row['star_mismatch']=$d['bucket']==='prepared'&&isset($d['target_category'])&&$source['category']!==null&&(int)$d['target_category']!==(int)$source['category'];if($d['bucket']==='prepared')$prepared[]=$row;elseif($d['bucket']==='hard_conflict')$hard[]=$row;else$near[]=$row;}
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;$seen=[];$observations=$db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        $processAnex=function(int $id,int $country,array $obs,array $stage,bool $observed)use(&$prepared,&$near,&$hard,&$stats,$index,$hotels,$names,$anClaims,$excluded,$manual,$mapped){if(isset($manual[$id])||isset($mapped[$id])){$stats['protected']++;return;}$source=['names'=>array_values(array_unique(array_filter([(string)($obs['hotel_name']??''),(string)($stage['api_name']??''),(string)($stage['xml_name']??''),(string)($stage['xml_alternate_name']??'')],static fn($v)=>trim($v)!==''))),'places'=>array_values(array_unique(array_filter([(string)($stage['api_region']??''),(string)($stage['api_town']??'')],static fn($v)=>trim($v)!==''))),'latitude'=>$stage['latitude']??null,'longitude'=>$stage['longitude']??null];$d=hmgan_decide($source,$country,$index,$hotels,$names,$anClaims,$excluded[$id]??[]);$row=['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'live'=>$observed,'observation_count'=>(int)($obs['search_count']??0),'source_names'=>$source['names'],'source_places'=>$source['places'],'star_mismatch'=>false]+$d;if($d['bucket']==='prepared')$prepared[]=$row;elseif($d['bucket']==='hard_conflict')$hard[]=$row;else$near[]=$row;};
        foreach($observations as $o){$id=(int)$o['anex_hotel_id'];if(isset($seen[$id]))continue;$seen[$id]=true;$country=(int)$o['country_id'];if(!isset(HMGAN_CORE8[$country]))continue;$stats['anex_observed_examined']++;$processAnex($id,$country,$o,$staging[$id]??[],true);}
        foreach($staging as $id=>$s){$id=(int)$id;if(isset($seen[$id])||isset($manual[$id])||isset($mapped[$id]))continue;$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(HMGAN_CORE8[$country]))continue;$stats['anex_staging_examined']++;$processAnex($id,$country,[],$s,false);}
        $byTarget=[];foreach($prepared as $i=>$r)$byTarget[$r['provider'].':'.(int)$r['target_local_hotel_id']][]=$i;$demote=[];foreach($byTarget as $idxs)if(count($idxs)>1)foreach($idxs as $i)$demote[$i]=true;if($demote){$kept=[];foreach($prepared as $i=>$r){if(isset($demote[$i])){$r['bucket']='near';$r['reason']='same_provider_target_collision';$near[]=$r;$stats['target_collisions']++;}else$kept[]=$r;}$prepared=$kept;}
        foreach($prepared as $r){$stats['prepared']++;if($r['provider']==='andromeda')$stats['prepared_andromeda']++;else$stats['prepared_anex']++;if($r['live'])$stats['prepared_live']++;if($r['star_mismatch']??false)$stats['prepared_star_mismatch']++;if($r['reason']==='approx_name_direct_place')$stats['route_place']++;else$stats['route_coordinate']++;}$stats['near']=count($near);$stats['hard_conflict']=count($hard);
        $reasons=[];$countries=[];$providers=[];foreach(array_merge($prepared,$near,$hard) as $r){$providers[$r['provider']][$r['bucket']]=($providers[$r['provider']][$r['bucket']]??0)+1;$countries[(string)$r['country_id']][$r['bucket']]=($countries[(string)$r['country_id']][$r['bucket']]??0)+1;}foreach(array_merge($near,$hard) as $r)$reasons[$r['reason']]=($reasons[$r['reason']]??0)+1;ksort($reasons);ksort($countries);usort($prepared,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['observation_count']<=>$a['observation_count'])?:strcmp((string)$a['provider'],(string)$b['provider'])?:strcmp((string)$a['external_id'],(string)$b['external_id']));$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'global_approx_name_rescue_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'policy'=>['core8_only'=>true,'russia_abkhazia_excluded'=>true,'generic_identity_terms'=>['HOTEL','RESORT','SPA','VILLAGE','CENTER','PALACE','VILLA','BOUTIQUE'],'critical_qualifiers'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'min_aligned_identity_tokens'=>2,'fuzzy_min'=>HMGAN_FUZZY_MIN,'char_min'=>HMGAN_CHAR_MIN,'winner_margin_min'=>HMGAN_MARGIN_MIN,'direct_coordinate_max_m'=>HMGAN_COORD_DIRECT_M,'coordinate_conflict_block_m'=>HMGAN_COORD_BLOCK_M,'single_token_auto_accept'=>false,'coordinate_only_auto_accept'=>false,'no_geo_auto_accept'=>false,'same_provider_target_claim_blocks'=>true,'pair_exclusions_protected'=>true],'coverage'=>$coverage,'catalog_targets'=>count($hotels),'stats'=>$stats,'reason_counts'=>$reasons,'provider_counts'=>$providers,'country_counts'=>$countries,'prepared'=>$prepared,'hard_conflicts'=>$hard,'near'=>$near];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if (!defined('FC_LIBRARY_ONLY')) {
    fwrite(STDERR,"library-only diagnostic; run through the guarded MATCH workflow\n");
    exit(2);
}
