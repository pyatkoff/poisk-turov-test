<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_supplier_bridge.php';

const HMSBF_MIN_SCORE = 82;
const HMSBF_MIN_MARGIN = 12;
const HMSBF_MIN_SYMMETRIC = 0.75;
const HMSBF_QUALIFIERS = ['annex'=>true,'beach'=>true,'garden'=>true,'gardens'=>true,'north'=>true,'south'=>true,'posh'=>true,'adult'=>true,'adults'=>true,'pool'=>true,'sea'=>true,'prestige'=>true,'aquamarine'=>true];

function hmsbf_sets(array $names): array {
    $out=[];
    foreach($names as $name)foreach(hmsb_keys($name) as $key){
        $tokens=array_values(array_filter(explode(' ',$key),static fn($x)=>$x!==''));
        if($tokens)$out[$key]=$tokens;
    }
    return $out;
}
function hmsbf_qualifier_conflict(array $a,array $b): bool {
    $qa=[];$qb=[];
    foreach($a as $t)if(isset(HMSBF_QUALIFIERS[$t]))$qa[$t]=true;
    foreach($b as $t)if(isset(HMSBF_QUALIFIERS[$t]))$qb[$t]=true;
    return array_keys($qa)!==array_keys($qb);
}
function hmsbf_metric(array $a,array $b): array {
    $aa=array_fill_keys($a,true);$bb=array_fill_keys($b,true);
    $common=count(array_intersect_key($aa,$bb));$na=count($aa);$nb=count($bb);
    if($common===0||$na===0||$nb===0)return['score'=>0,'common'=>0,'symmetric'=>0.0,'jaccard'=>0.0,'overlap'=>0.0,'qualifier_conflict'=>hmsbf_qualifier_conflict($a,$b)];
    $symmetric=$common/max($na,$nb);$jaccard=$common/($na+$nb-$common);$overlap=$common/min($na,$nb);
    $score=(int)round(100*(0.50*$symmetric+0.30*$jaccard+0.20*$overlap));
    return['score'=>$score,'common'=>$common,'symmetric'=>$symmetric,'jaccard'=>$jaccard,'overlap'=>$overlap,'qualifier_conflict'=>hmsbf_qualifier_conflict($a,$b)];
}
function hmsbf_add_target(array &$tokenIndex,array &$targetNames,int $country,int $local,array $names): void {
    $sets=hmsbf_sets($names);if(!$sets)return;
    $targetNames[$country][$local]=$sets;
    foreach($sets as $tokens)foreach(array_keys(array_fill_keys($tokens,true)) as $token)$tokenIndex[$country][$token][$local]=true;
}
function hmsbf_rank(array $sourceNames,int $country,array $tokenIndex,array $targetNames): array {
    $sourceSets=hmsbf_sets($sourceNames);if(!$sourceSets)return['status'=>'no_source_tokens'];
    $candidates=[];$exact=false;
    foreach($sourceSets as $sourceKey=>$tokens){
        foreach($tokens as $token)foreach(array_keys($tokenIndex[$country][$token]??[]) as $local)$candidates[(int)$local]=true;
        foreach($targetNames[$country]??[] as $local=>$sets)if(isset($sets[$sourceKey])){$exact=true;$candidates[(int)$local]=true;}
    }
    if($exact)return['status'=>'exact_key_skipped'];
    if(!$candidates)return['status'=>'no_shared_token'];
    $rank=[];
    foreach(array_keys($candidates) as $local){
        $best=null;$bestConflict=null;
        foreach($sourceSets as $sourceKey=>$a)foreach($targetNames[$country][$local]??[] as $targetKey=>$b){
            $m=hmsbf_metric($a,$b)+['source_key'=>$sourceKey,'target_key'=>$targetKey];
            if($m['qualifier_conflict']){if($bestConflict===null||$m['score']>$bestConflict['score'])$bestConflict=$m;continue;}
            if($best===null||$m['score']>$best['score']||($m['score']===$best['score']&&$m['common']>$best['common']))$best=$m;
        }
        if($best!==null)$rank[]=['local_id'=>(int)$local]+$best;
        elseif($bestConflict!==null)$rank[]=['local_id'=>(int)$local]+$bestConflict;
    }
    if(!$rank)return['status'=>'no_comparable_candidate'];
    usort($rank,static fn($a,$b)=>$b['score']<=>$a['score']?:$b['common']<=>$a['common']?:$a['local_id']<=>$b['local_id']);
    $best=$rank[0];$runner=$rank[1]??null;$margin=$best['score']-(int)($runner['score']??0);
    return['status'=>'ranked','best'=>$best,'runner_up'=>$runner,'margin'=>$margin,'candidate_count'=>count($rank)];
}
function hmsbf_status(array $rank,array $geo,bool $pairExcluded=false): string {
    if($pairExcluded||($geo['coordinate_conflict']??false))return'hard_conflict';
    $best=$rank['best']??[];
    if(($best['qualifier_conflict']??false))return'qualifier_conflict';
    if((int)($best['score']??0)<HMSBF_MIN_SCORE||(int)($best['common']??0)<2||(float)($best['symmetric']??0)<HMSBF_MIN_SYMMETRIC)return'below_strong_threshold';
    if((int)($rank['margin']??0)<HMSBF_MIN_MARGIN)return'ambiguous_margin';
    return($geo['direct_geo']??false)?'strong_fuzzy_geo':'strong_fuzzy_needs_independent_evidence';
}
function hmsbf_collision_hold(array $rows): array {
    $eligible=['strong_fuzzy_geo'=>true,'strong_fuzzy_needs_independent_evidence'=>true];$groups=[];
    foreach($rows as $i=>$r)if(isset($eligible[$r['status']??'']))$groups[$r['provider']][(int)$r['target_local_id']][]=$i;
    foreach($groups as $locals)foreach($locals as $ids){$externals=[];foreach($ids as $i)$externals[(string)$rows[$i]['external_id']]=true;if(count($externals)>1)foreach($ids as $i){$rows[$i]['status']='duplicate_provider_hold';$rows[$i]['duplicate_provider_external_ids']=array_keys($externals);sort($rows[$i]['duplicate_provider_external_ids'],SORT_STRING);}}
    return $rows;
}
function hmsbf_target_row(array $rank,array $hotels): ?array {
    $local=(int)($rank['best']['local_id']??0);return $local>0&&isset($hotels[$local])?$hotels[$local]:null;
}

function hmsbf_review(PDO $db,string $operation): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];$q=$db->query('SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,h.category,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY h.id');foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$hotels[(int)$r['id']]=$r;
        $anexAccepted=[];$anexLocal=[];$sql="SELECT m.anex_hotel_id external_id,m.catalog_hotel_id local_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".FC_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)";
        foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){$local=(int)$r['local_id'];if(isset($hotels[$local])){$anexAccepted[(int)$r['external_id']]=$local;$anexLocal[$local]=true;}}
        foreach($db->query("SELECT anex_hotel_id external_id,catalog_hotel_id local_id FROM anex_hotel_decisions d WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)")->fetchAll(PDO::FETCH_ASSOC) as $r){$local=(int)$r['local_id'];if(isset($hotels[$local])){$anexAccepted[(int)$r['external_id']]=$local;$anexLocal[$local]=true;}}
        $andLocal=[];$andAcceptedRows=[];foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$local=(int)$r['local_hotel_id'];if(isset($hotels[$local])){$andLocal[$local]=true;$andAcceptedRows[]=$r;}}
        $anexOnly=array_diff_key($anexLocal,$andLocal);$andOnly=array_diff_key($andLocal,$anexLocal);
        $anexStage=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r)$anexStage[(int)$r['anex_hotel_id']]=$r;
        $anexObs=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];if(!isset($anexObs[$id]))$anexObs[$id]=$r;}
        $andObs=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['external_hotel_id'];if(!isset($andObs[$id]))$andObs[$id]=$r;}
        $shaCountry=fc_sha_countries($db);
        $anexTokens=[];$anexNames=[];foreach($anexAccepted as $external=>$local){if(!isset($anexOnly[$local]))continue;$s=$anexStage[$external]??[];$o=$anexObs[$external]??[];$names=hmsb_names([$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??'',$o['hotel_name']??'']);hmsbf_add_target($anexTokens,$anexNames,(int)$hotels[$local]['country_id'],$local,$names);}
        $andTokens=[];$andNames=[];foreach($andAcceptedRows as $r){$local=(int)$r['local_hotel_id'];if(!isset($andOnly[$local]))continue;$external=(string)$r['external_hotel_id'];$ev=fc_evidence($r['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];$o=$andObs[$external]??[];$names=hmsb_names([$src['name']??'',$src['lName']??'',$o['hotel_name']??'']);hmsbf_add_target($andTokens,$andNames,(int)$hotels[$local]['country_id'],$local,$names);}
        $rows=[];$stats=['anex_unresolved_examined'=>0,'andromeda_pending_examined'=>0,'anex_only_targets'=>count($anexOnly),'andromeda_only_targets'=>count($andOnly),'ranked'=>0,'exact_key_skipped'=>0,'no_shared_token'=>0,'no_source_tokens'=>0];
        $pending=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($pending as $r){$external=(string)$r['external_hotel_id'];$o=$andObs[$external]??null;$country=(int)($o['country_id']??0);if(!isset(HMSB_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMSB_CORE8[$country]))continue;$stats['andromeda_pending_examined']++;$ev=fc_evidence($r['evidence_json']??'');$src=$ev['source']??[];if(!is_array($src))$src=[];$geo=$ev['geography']??[];if(!is_array($geo))$geo=[];$names=hmsb_names([$src['name']??'',$src['lName']??'',$o['hotel_name']??'']);$rank=hmsbf_rank($names,$country,$anexTokens,$anexNames);if($rank['status']!=='ranked'){$stats[$rank['status']]=($stats[$rank['status']]??0)+1;continue;}$stats['ranked']++;$target=hmsbf_target_row($rank,$hotels);if(!$target)continue;$source=$src;if($o)foreach($o as $k=>$v)if(!array_key_exists($k,$source))$source[$k]=$v;$source['places']=hmsb_names([$src['town']??'',$geo['town']??'',$geo['parent']??'',$o['region_name']??'']);$g=hmsb_direct_geo($source,$target);$rows[]=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'target_local_id'=>(int)$target['id'],'target'=>hmsb_target($target),'source_names'=>$names,'source_places'=>$source['places'],'score'=>$rank['best']['score'],'common_tokens'=>$rank['best']['common'],'symmetric_coverage'=>round((float)$rank['best']['symmetric'],4),'best_source_key'=>$rank['best']['source_key'],'best_target_key'=>$rank['best']['target_key'],'runner_up_local_id'=>$rank['runner_up']['local_id']??null,'runner_up_score'=>$rank['runner_up']['score']??null,'margin'=>$rank['margin'],'candidate_count'=>$rank['candidate_count'],'qualifier_conflict'=>$rank['best']['qualifier_conflict'],'geo'=>$g,'status'=>hmsbf_status($rank,$g),'source_category'=>hmsb_numeric_category($src),'target_category'=>$target['category']===null?null:(int)$target['category'],'bridge_provider'=>'anex','not_write_authority'=>true];}
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $allAnex=$anexStage;foreach($anexObs as $id=>$o)if(!isset($allAnex[$id]))$allAnex[$id]=[];
        foreach($allAnex as $id=>$s){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id]))continue;$o=$anexObs[$id]??[];$country=(int)($o['country_id']??0);if(!isset(HMSB_CORE8[$country]))$country=fc_country($s['api_country']??'')??0;if(!isset(HMSB_CORE8[$country]))continue;$stats['anex_unresolved_examined']++;$names=hmsb_names([$o['hotel_name']??'',$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??'']);$rank=hmsbf_rank($names,$country,$andTokens,$andNames);if($rank['status']!=='ranked'){$stats[$rank['status']]=($stats[$rank['status']]??0)+1;continue;}$stats['ranked']++;$target=hmsbf_target_row($rank,$hotels);if(!$target)continue;$source=$s;$source['places']=hmsb_names([$s['api_region']??'',$s['api_town']??'']);$g=hmsb_direct_geo($source,$target);$pairExcluded=isset($excluded[$id][(int)$target['id']]);$rows[]=['provider'=>'anex','external_id'=>(string)$id,'country_id'=>$country,'target_local_id'=>(int)$target['id'],'target'=>hmsb_target($target),'source_names'=>$names,'source_places'=>$source['places'],'score'=>$rank['best']['score'],'common_tokens'=>$rank['best']['common'],'symmetric_coverage'=>round((float)$rank['best']['symmetric'],4),'best_source_key'=>$rank['best']['source_key'],'best_target_key'=>$rank['best']['target_key'],'runner_up_local_id'=>$rank['runner_up']['local_id']??null,'runner_up_score'=>$rank['runner_up']['score']??null,'margin'=>$rank['margin'],'candidate_count'=>$rank['candidate_count'],'qualifier_conflict'=>$rank['best']['qualifier_conflict'],'geo'=>$g,'status'=>hmsbf_status($rank,$g,$pairExcluded),'pair_excluded'=>$pairExcluded,'observed'=>(bool)$o,'search_count'=>(int)($o['search_count']??0),'bridge_provider'=>'andromeda','not_write_authority'=>true];}
        $rows=hmsbf_collision_hold($rows);$counts=[];$provider=[];foreach($rows as $r){$counts[$r['status']]=($counts[$r['status']]??0)+1;$provider[$r['provider']][$r['status']]=($provider[$r['provider']][$r['status']]??0)+1;}ksort($counts);ksort($provider);usort($rows,static fn($a,$b)=>strcmp($a['provider'],$b['provider'])?:((int)($b['search_count']??0)<=>(int)($a['search_count']??0))?:((int)$b['score']<=>(int)$a['score'])?:strcmp($a['external_id'],$b['external_id']));
        $db->commit();return['schema'=>'hotel-match-current-supplier-fuzzy-bridge/1','status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'thresholds'=>['min_score'=>HMSBF_MIN_SCORE,'min_margin'=>HMSBF_MIN_MARGIN,'min_symmetric'=>HMSBF_MIN_SYMMETRIC,'min_common_tokens'=>2],'stats'=>$stats,'status_counts'=>$counts,'provider_status_counts'=>$provider,'rows'=>$rows,'guards'=>['missing_third_only'=>true,'exact_keys_skipped_after_completed_v1'=>true,'supplier_identity_names_not_local_names'=>true,'hotel_resort_spa_ignored'=>true,'former_name_aliases'=>true,'meaningful_qualifier_conflict_blocks_strong'=>true,'symmetric_coverage_prevents_destination_only_containment'=>true,'large_runner_up_margin_required'=>true,'direct_geo_required_for_strong_auto_candidate'=>true,'coordinate_conflict_gt_5km_blocks'=>true,'duplicate_provider_local_groups_hold'=>true,'manual_and_pair_exclusions_preserved'=>true,'category_is_signal_only'=>true,'starKey_not_identity'=>true,'no_mapping_authority'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
