<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_translit_exact_review.php';

const HTSR_OPERATION = 'hotel-match-translit-strong-review-1971-20260911-v1';

function htsr_identity_tokens(string $name, string $region = ''): array {
    $tokens = pcbr_identity_tokens(htxr_latin($name));
    $drop = array_values(array_unique(array_merge(pcbr_region_tokens($region), ['adult','adults','only','16'])));
    return pcbr_without_tokens($tokens, $drop);
}
function htsr_critical_signature(string $name): array {
    $critical = ['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1];
    $out = [];
    foreach (pcbr_identity_tokens(htxr_latin($name)) as $t) if (isset($critical[$t])) $out[$t] = true;
    $keys = array_keys($out); sort($keys, SORT_STRING); return $keys;
}
function htsr_ordered_contains(array $small, array $large): bool {
    if (!$small || count($small) > count($large)) return false;
    $i = 0; $need = count($small);
    foreach ($large as $token) {
        if ($i < $need && $token === $small[$i]) $i++;
    }
    return $i === $need;
}
function htsr_pair(string $source, string $target, string $region, bool $bridge): array {
    if (!htxr_cross_script($source, $target)) return ['safe'=>false,'score'=>0.0,'shared'=>0];
    $s = htsr_identity_tokens($source, $region); $t = htsr_identity_tokens($target, $region);
    if (count($s) < 2 || count($t) < 2) return ['safe'=>false,'score'=>0.0,'shared'=>0];
    $criticalOk = htsr_critical_signature($source) === htsr_critical_signature($target);
    $anchorOk = isset($s[0], $t[0]) && $s[0] === $t[0];
    $sInT = htsr_ordered_contains($s, $t); $tInS = htsr_ordered_contains($t, $s);
    $oneSided = $sInT || $tInS;
    $shared = min(count($s), count($t));
    $diff = abs(count($s) - count($t));
    $score = (count($s) + count($t)) > 0 ? (2.0 * $shared / (count($s) + count($t))) : 0.0;
    $minTokens = min(count($s), count($t));
    $safe = $criticalOk && $anchorOk && $oneSided && $diff <= 1 && (
        ($bridge && $minTokens >= 2 && $score >= 0.80) ||
        (!$bridge && $minTokens >= 3 && $score >= 0.86)
    );
    return [
        'safe'=>$safe,'score'=>round($score,6),'shared'=>$shared,'source_tokens'=>$s,'target_tokens'=>$t,
        'token_diff'=>$diff,'critical_ok'=>$criticalOk,'anchor_ok'=>$anchorOk,'ordered_source_in_target'=>$sInT,
        'ordered_target_in_source'=>$tInS,'existing_opposite_provider_bridge'=>$bridge,
    ];
}
function htsr_best_pair(array $sourceNames, array $targetNames, string $region, bool $bridge): array {
    $best=['safe'=>false,'score'=>0.0,'shared'=>0,'source'=>'','target'=>''];
    foreach ($sourceNames as $source) {
        $source = trim((string)$source); if ($source === '') continue;
        foreach ($targetNames as $target) {
            $target = trim((string)$target); if ($target === '') continue;
            $pair = htsr_pair($source, $target, $region, $bridge);
            $better = (int)$pair['safe'] > (int)($best['safe'] ?? false)
                || ((bool)$pair['safe'] === (bool)($best['safe'] ?? false)
                    && ($pair['score'] > ($best['score'] ?? 0.0)
                        || ($pair['score'] === ($best['score'] ?? 0.0) && $pair['shared'] > ($best['shared'] ?? 0))));
            if ($better) $best = $pair + ['source'=>$source,'target'=>$target];
        }
    }
    return $best;
}
function htsr_geo_index(array $hotels): array {
    $idx=[];
    foreach ($hotels as $id=>$h) {
        $country=(int)$h['country_id'];
        foreach ([(string)$h['region_name'],(string)$h['subregion_name']] as $place) {
            $key=fc_norm(htxr_latin($place)); if ($key!=='') $idx[$country][$key][(int)$id]=true;
        }
    }
    return $idx;
}
function htsr_geo_pool(array $idx, int $country, array $sourcePlaces): array {
    $ids=[];
    foreach ($sourcePlaces as $place) {
        $key=fc_norm(htxr_latin((string)$place)); if ($key==='') continue;
        foreach (array_keys($idx[$country][$key]??[]) as $id) $ids[(int)$id]=true;
    }
    $out=array_keys($ids); sort($out,SORT_NUMERIC); return $out;
}
function htsr_candidate(array $sourceNames, array $sourcePlaces, array $coordSource, int $country, array $hotels, array $names, array $geoIndex, array $bridgeLocal, array $excluded=[]): array {
    $pool=htsr_geo_pool($geoIndex,$country,$sourcePlaces); if(!$pool) return ['candidate'=>null,'reason'=>'no_direct_geo','coordinate_conflicts'=>0,'safe_targets'=>0];
    $rank=[]; $coordConflicts=0;
    foreach($pool as $id){$id=(int)$id;if(isset($excluded[$id])||!isset($hotels[$id]))continue;$h=$hotels[$id];
        $guard=mbr_target_guard($coordSource,$h);if($guard['coordinate_conflict']){$coordConflicts++;continue;}
        $bridge=isset($bridgeLocal[$id]);$pair=htsr_best_pair($sourceNames,$names[$id]??[$h['name']],(string)$h['region_name'],$bridge);if(!($pair['safe']??false))continue;
        $rank[]=['id'=>$id,'pair'=>$pair,'bridge'=>$bridge,'guard'=>$guard];
    }
    if(!$rank)return ['candidate'=>null,'reason'=>'no_safe_pair','coordinate_conflicts'=>$coordConflicts,'safe_targets'=>0];
    usort($rank,static fn($a,$b)=>$b['pair']['score']<=>$a['pair']['score'] ?: $b['pair']['shared']<=>$a['pair']['shared'] ?: (int)$b['bridge']<=>(int)$a['bridge'] ?: $a['id']<=>$b['id']);
    $best=$rank[0];$second=$rank[1]??null;$margin=$second?($best['pair']['score']-$second['pair']['score']):1.0;
    if($second!==null && $margin<0.12)return ['candidate'=>null,'reason'=>'ambiguous_margin','coordinate_conflicts'=>$coordConflicts,'safe_targets'=>count($rank),'best_margin'=>round($margin,6)];
    $h=$hotels[$best['id']];
    return ['candidate'=>[
        'target_local_hotel_id'=>(int)$best['id'],'target_name'=>$h['name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],
        'target_category'=>$h['category']===null?null:(int)$h['category'],'pair'=>$best['pair'],'score_margin'=>round($margin,6),
        'distance_m'=>$best['guard']['distance_m']??null,'existing_opposite_provider_bridge'=>(bool)$best['bridge'],
        'rule'=>'cross_script_ordered_containment_direct_geo_unique_margin'
    ],'reason'=>'safe','coordinate_conflicts'=>$coordConflicts,'safe_targets'=>count($rank)];
}

function htsr_review(PDO $db,string $operation=HTSR_OPERATION): array {
    if($operation!==HTSR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);$geoIndex=htsr_geo_index($hotels);$shaCountry=fc_sha_countries($db);[$latest,$counts,$lastSeen]=mlp_observations($db);
        $rows=[];$stats=['andromeda_examined'=>0,'anex_examined'=>0,'live_examined'=>0,'prepared'=>0,'prepared_andromeda'=>0,'prepared_anex'=>0,'live_prepared'=>0,'bridge_prepared'=>0,'tourvisor_only_prepared'=>0,'category_mismatch_prepared'=>0,'ambiguous_margin'=>0,'no_direct_geo'=>0,'coordinate_conflict'=>0,'no_safe_pair'=>0];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$obsCount=(int)($counts[$external]??0);$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;$stats['andromeda_examined']++;if($obsCount>0)$stats['live_examined']++;
            [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($r,$obs);$found=htsr_candidate($sourceNames,$sourcePlaces,$coordSource,$country,$hotels,$names,$geoIndex,$anexLocal);$stats['coordinate_conflict']+=$found['coordinate_conflicts'];if($found['candidate']===null){$reason=$found['reason'];if(isset($stats[$reason]))$stats[$reason]++;continue;}
            $c=$found['candidate'];$mismatch=$sourceCategory!==null&&$c['target_category']!==null&&$sourceCategory!==$c['target_category'];$row=array_merge(['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'category_mismatch'=>$mismatch],$c);$rows[]=$row;$stats['prepared_andromeda']++;if($obsCount>0)$stats['live_prepared']++;if($c['existing_opposite_provider_bridge'])$stats['bridge_prepared']++;else$stats['tourvisor_only_prepared']++;if($mismatch)$stats['category_mismatch_prepared']++;
        }
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;$observed=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(int)$o['anex_hotel_id'];if(!isset($observed[$id]))$observed[$id]=$o;}
        $ids=array_values(array_unique(array_merge(array_keys($observed),array_keys($staging))));sort($ids,SORT_NUMERIC);
        foreach($ids as $id){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id]))continue;$s=$staging[$id]??[];$o=$observed[$id]??null;$country=(int)($o['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(MBR_CORE8[$country]))continue;$stats['anex_examined']++;$obsCount=(int)($o['search_count']??0);if($obsCount>0)$stats['live_examined']++;
            $sourceNames=array_values(array_unique(array_filter([(string)($o['hotel_name']??''),(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')],static fn($v)=>trim($v)!=='')));$sourcePlaces=array_values(array_unique(array_filter([(string)($s['api_region']??''),(string)($s['api_town']??''),(string)($o['region_name']??'')],static fn($v)=>trim($v)!=='')));$coordSource=array_merge($s,$o??[]);$sourceCategory=mbr_numeric_category($s);if($sourceCategory===null&&$o)$sourceCategory=mbr_numeric_category($o);
            $found=htsr_candidate($sourceNames,$sourcePlaces,$coordSource,$country,$hotels,$names,$geoIndex,$andromedaLocal,$excluded[$id]??[]);$stats['coordinate_conflict']+=$found['coordinate_conflicts'];if($found['candidate']===null){$reason=$found['reason'];if(isset($stats[$reason]))$stats[$reason]++;continue;}
            $c=$found['candidate'];$mismatch=$sourceCategory!==null&&$c['target_category']!==null&&$sourceCategory!==$c['target_category'];$rows[]=array_merge(['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'observation_count'=>$obsCount,'last_seen_utc'=>$o['last_seen_utc']??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'category_mismatch'=>$mismatch],$c);$stats['prepared_anex']++;if($obsCount>0)$stats['live_prepared']++;if($c['existing_opposite_provider_bridge'])$stats['bridge_prepared']++;else$stats['tourvisor_only_prepared']++;if($mismatch)$stats['category_mismatch_prepared']++;
        }
        $stats['prepared']=count($rows);usort($rows,static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: (int)$b['existing_opposite_provider_bridge']<=>(int)$a['existing_opposite_provider_bridge'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: strcmp((string)$a['provider'],(string)$b['provider']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));$coverage=fc_coverage($db);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$scope,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$rows];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
