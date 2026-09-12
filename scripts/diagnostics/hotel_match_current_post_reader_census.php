<?php
declare(strict_types=1);
/**
 * MATCH #1971 — one CURRENT read-only census after the ANEX registry reader repair.
 * No supplier/Tourvisor calls and no database mutations.
 */
const HM_POST_READER_OP = 'hotel-match-current-post-reader-census-1971-20260912-v1';
const HM_APPROVED = [
    'owner_exact_and_strong_20260908' => ['exact', 'strong_candidate'],
    'owner_exact_operator_key_20260912' => ['exact_operator_key'],
    'owner_exact_operator_key_20260912_v2' => ['exact_operator_key'],
    'owner_coordinate_name_geo_rescue_20260912_v1' => ['coordinate_name_geo'],
    'owner_multi_evidence_consensus_20260912_v1' => ['multi_evidence_consensus'],
    'owner_current_exact_cross_provider_20260912' => ['exact_cross_provider'],
];

function hmCountry($value): ?string {
    $s = mb_strtolower(trim((string)$value), 'UTF-8');
    $s = str_replace('ё', 'е', $s);
    $s = preg_replace('/[\s_\-]+/u', ' ', $s);
    $map = [
        'egypt' => ['египет','egypt'], 'turkey' => ['турция','turkey','türkiye','turkiye'],
        'thailand' => ['таиланд','thailand'], 'uae' => ['оаэ','объединенные арабские эмираты','united arab emirates','uae'],
        'vietnam' => ['вьетнам','vietnam','viet nam'], 'srilanka' => ['шри ланка','sri lanka'],
        'maldives' => ['мальдивы','maldives'], 'cuba' => ['куба','cuba'],
    ];
    foreach ($map as $key => $labels) if (in_array($s, $labels, true)) return $key;
    return null;
}
function hmNorm($value): string {
    $s = mb_strtoupper(trim((string)$value), 'UTF-8');
    $s = str_replace(['Ё','&'], ['Е',' AND '], $s);
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    $tokens = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $generic = ['HOTEL'=>1,'HOTELS'=>1,'RESORT'=>1,'SPA'=>1,'EX'=>1];
    $tokens = array_values(array_filter($tokens, static fn($t) => !isset($generic[$t])));
    return implode(' ', $tokens);
}
function hmDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r = 6371.0088; $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dlat = deg2rad($lat2-$lat1); $dlon = deg2rad($lon2-$lon1);
    $a = sin($dlat/2)**2 + cos($p1)*cos($p2)*sin($dlon/2)**2;
    return $r * 2 * atan2(sqrt($a), sqrt(max(0.0, 1-$a)));
}
function hmColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->query('SHOW COLUMNS FROM `'.$table.'`');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
}
function hmTableExists(PDO $pdo, string $table): bool {
    $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]); return $q->fetchColumn() !== false;
}
function hmRows(PDO $pdo, string $table, array $wanted, int $limit): array {
    $allowed = ['catalog_hotels','hotel_aliases','anex_hotels','anex_hotel_search_mappings','anex_hotel_decisions',
        'anex_review_pair_exclusions','anex_search_hotel_observations','andromeda_hotel_identities','andromeda_search_hotel_observations'];
    if (!in_array($table, $allowed, true) || !hmTableExists($pdo, $table)) return [];
    $cols = hmColumns($pdo, $table); $sel = array_values(array_intersect($wanted, $cols));
    if (!$sel) return [];
    $sql = 'SELECT `'.implode('`,`',$sel).'` FROM `'.$table.'` LIMIT '.($limit+1);
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > $limit) throw new RuntimeException('bound_exceeded_'.$table);
    return $rows;
}
function hmApproved(array $row): bool {
    $policy = $row['approval_policy'] ?? null; $class = $row['match_class'] ?? null;
    return is_string($policy) && is_string($class) && in_array($class, HM_APPROVED[$policy] ?? [], true);
}
function hmEvidenceSources(array $value, int $depth=0): array {
    if ($depth > 12) return [];
    $out=[];
    if ((isset($value['name']) || isset($value['lName'])) && (isset($value['id']) || isset($value['hotelKey']) || isset($value['external_hotel_id']))) {
        $out[]=$value;
    }
    foreach ($value as $key=>$child) {
        if (is_array($child) && !in_array((string)$key,['candidates','targets','local','hotels','offers'],true)) {
            $out=array_merge($out,hmEvidenceSources($child,$depth+1));
        }
    }
    return $out;
}
function hmExternalNames(array $row): array {
    $raw = json_decode((string)($row['evidence_json'] ?? ''), true);
    if (!is_array($raw)) return [];
    $id = (string)($row['external_hotel_id'] ?? ''); $names=[];
    foreach (hmEvidenceSources($raw) as $src) {
        $sid=(string)($src['id'] ?? $src['hotelKey'] ?? $src['external_hotel_id'] ?? '');
        if ($id !== '' && $sid !== '' && $sid !== $id) continue;
        foreach (['name','lName'] as $key) if (isset($src[$key]) && is_scalar($src[$key])) {
            $n=hmNorm((string)$src[$key]); if ($n!=='') $names[$n]=true;
        }
    }
    return array_keys($names);
}
function hmInt($v): ?int { return is_numeric($v) && (int)$v > 0 ? (int)$v : null; }
function hmFloat($v): ?float { return is_numeric($v) ? (float)$v : null; }

if (in_array('--self-test', $_SERVER['argv'] ?? [], true)) {
    assert_options(ASSERT_ACTIVE,1);
    if (hmCountry('Турция') !== 'turkey' || hmCountry('Россия') !== null) exit(2);
    if (hmNorm('Aperion Beach Hotel (EX. Sea Paradise)') !== 'APERION BEACH SEA PARADISE') exit(3);
    if (hmNorm('NORTH GARDEN RESORT SPA') !== 'NORTH GARDEN') exit(4);
    if (hmDistanceKm(36.713018,31.563078,36.713018,31.563078) > 0.001) exit(5);
    if (!hmApproved(['approval_policy'=>'owner_exact_operator_key_20260912_v2','match_class'=>'exact_operator_key'])) exit(6);
    if (hmApproved(['approval_policy'=>'unknown','match_class'=>'exact'])) exit(7);
    echo "MATCH post-reader census self-test PASS; network=0 database=0\n"; exit(0);
}

error_reporting(0); ob_start(); $pdo=null;
try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('cli_only');
    $root=realpath(getcwd()); if (!$root || basename($root)!=='anytoour.ru') throw new RuntimeException('root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $pdo=v2_data_db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->exec('START TRANSACTION READ ONLY');

    $locals=hmRows($pdo,'catalog_hotels',['id','name','country_id','country_name','region_name','subregion_name','latitude','longitude','lat','lon','lng','is_active'],200000);
    $active=[];$countryById=[];$localCountry=[];$localCoords=[];$localIndex=[];
    foreach($locals as $r){
        $cc=hmCountry($r['country_name']??''); $id=hmInt($r['id']??null); if($cc===null||$id===null||(int)($r['is_active']??0)!==1)continue;
        $active[$id]=$r; $countryById[(string)($r['country_id']??'')]=$cc; $localCountry[$id]=$cc;
        $lat=hmFloat($r['latitude']??$r['lat']??null);$lon=hmFloat($r['longitude']??$r['lon']??$r['lng']??null);if($lat!==null&&$lon!==null)$localCoords[$id]=[$lat,$lon];
        $k=hmNorm($r['name']??''); if($k!=='')$localIndex[$cc.'|'.$k][$id]=true;
    }
    $aliases=hmRows($pdo,'hotel_aliases',['hotel_id','alias','name'],1000000);
    foreach($aliases as $r){$id=hmInt($r['hotel_id']??null);if($id===null||!isset($active[$id]))continue;$k=hmNorm($r['alias']??$r['name']??'');if($k!=='')$localIndex[$localCountry[$id].'|'.$k][$id]=true;}

    $maps=hmRows($pdo,'anex_hotel_search_mappings',['anex_hotel_id','catalog_hotel_id','match_class','scope','approval_policy','enabled','mapping_digest'],100000);
    $resolved=[];$mappingAmbiguous=[];$approvedRows=0;
    foreach($maps as $r){
        $aid=hmInt($r['anex_hotel_id']??null);$lid=hmInt($r['catalog_hotel_id']??null);
        if($aid===null||$lid===null||!isset($active[$lid])||(int)($r['enabled']??0)!==1||($r['scope']??null)!=='preview'||!hmApproved($r))continue;
        $approvedRows++; if(isset($resolved[$aid])&&$resolved[$aid]!==$lid){$mappingAmbiguous[$aid]=true;unset($resolved[$aid]);continue;} if(!isset($mappingAmbiguous[$aid]))$resolved[$aid]=$lid;
    }
    $decisions=hmRows($pdo,'anex_hotel_decisions',['anex_hotel_id','catalog_hotel_id','decision_status','status','decision'],100000);
    $protected=[];$manualAccepted=0;
    foreach($decisions as $r){$aid=hmInt($r['anex_hotel_id']??null);if($aid===null)continue;unset($resolved[$aid]);$protected[$aid]=true;$status=(string)($r['decision_status']??$r['status']??$r['decision']??'');$lid=hmInt($r['catalog_hotel_id']??null);if($status==='accepted'&&$lid!==null&&isset($active[$lid])){$resolved[$aid]=$lid;$manualAccepted++;}}
    $exclusions=hmRows($pdo,'anex_review_pair_exclusions',['anex_hotel_id','catalog_hotel_id','local_hotel_id'],100000);
    $excludedByAnex=[];
    foreach($exclusions as $r){$aid=hmInt($r['anex_hotel_id']??null);$lid=hmInt($r['catalog_hotel_id']??$r['local_hotel_id']??null);if($aid===null||$lid===null)continue;$excludedByAnex[$aid][$lid]=true;if(($resolved[$aid]??null)===$lid)unset($resolved[$aid]);}

    $staging=hmRows($pdo,'anex_hotels',['anex_hotel_id','xml_name','xml_alternate_name','api_name','api_country','api_region','api_town','latitude','longitude'],100000);
    $stagingById=[];
    foreach($staging as $r){$aid=hmInt($r['anex_hotel_id']??null);if($aid!==null)$stagingById[$aid]=$r;}
    $observations=hmRows($pdo,'anex_search_hotel_observations',['anex_hotel_id','hotel_name','country_id','anex_country_id','last_catalog_hotel_id','search_count','last_seen_utc'],200000);
    $obs=[];
    foreach($observations as $r){$aid=hmInt($r['anex_hotel_id']??null);if($aid===null)continue;$o=&$obs[$aid];if(!isset($o))$o=['occurrences'=>0,'names'=>[],'country_id'=>null,'last_catalog_hotel_id'=>null,'last_seen_utc'=>null];$o['occurrences']+=max(1,(int)($r['search_count']??1));$n=trim((string)($r['hotel_name']??''));if($n!=='')$o['names'][$n]=true;if(($r['country_id']??null)!==null)$o['country_id']=(string)$r['country_id'];if(($r['last_catalog_hotel_id']??null)!==null)$o['last_catalog_hotel_id']=hmInt($r['last_catalog_hotel_id']);if(($r['last_seen_utc']??'')>$o['last_seen_utc'])$o['last_seen_utc']=$r['last_seen_utc'];unset($o);}

    $andRows=hmRows($pdo,'andromeda_hotel_identities',['supplier_namespace','external_hotel_id','country_id','local_hotel_id','decision_status','evidence_json'],100000);
    $andAccepted=[];$andNamesByTarget=[];$andPending=[];$andStatus=[];
    foreach($andRows as $r){$status=(string)($r['decision_status']??'unknown');$andStatus[$status]=($andStatus[$status]??0)+1;$eid=(string)($r['external_hotel_id']??'');$lid=hmInt($r['local_hotel_id']??null);$cc=$countryById[(string)($r['country_id']??'')]??($lid!==null?($localCountry[$lid]??null):null);$names=hmExternalNames($r);
        if($status==='accepted'&&$lid!==null&&isset($active[$lid])){$andAccepted[$lid]=true;foreach($names as $n)$andNamesByTarget[$lid][$n]=true;}
        elseif($status==='pending')$andPending[]=['external_hotel_id'=>$eid,'country_class'=>$cc,'names'=>$names];
    }
    $andObs=hmRows($pdo,'andromeda_search_hotel_observations',['supplier_namespace','external_hotel_id','hotel_name','country_id','country_name','region_name'],200000);
    $andObsFreq=[];foreach($andObs as $r){$eid=(string)($r['external_hotel_id']??'');if($eid!=='')$andObsFreq[$eid]=($andObsFreq[$eid]??0)+1;}

    $anexNamesByTarget=[];
    foreach($resolved as $aid=>$lid){$names=[];if(isset($stagingById[$aid]))foreach(['xml_name','xml_alternate_name','api_name'] as $key){$n=hmNorm($stagingById[$aid][$key]??'');if($n!=='')$names[$n]=true;}if(isset($obs[$aid]))foreach(array_keys($obs[$aid]['names']) as $raw){$n=hmNorm($raw);if($n!=='')$names[$n]=true;}foreach(array_keys($names) as $n)$anexNamesByTarget[$lid][$n]=true;}

    $live=[];$liveCandidates=[];$coordConflicts=0;$liveOccurrences=0;$liveAbsentStaging=0;
    foreach($obs as $aid=>$o){
        if(isset($resolved[$aid])||isset($protected[$aid]))continue;
        $cc=$countryById[(string)($o['country_id']??'')]??hmCountry($stagingById[$aid]['api_country']??'');if($cc===null)continue;
        $liveOccurrences+=$o['occurrences'];$st=isset($stagingById[$aid]);if(!$st)$liveAbsentStaging++;
        $entry=['anex_hotel_id'=>$aid,'occurrences'=>$o['occurrences'],'country_class'=>$cc,'names'=>array_keys($o['names']),'staging_present'=>$st,'last_seen_utc'=>$o['last_seen_utc']];$live[]=$entry;
        $votes=[];
        foreach(array_keys($o['names']) as $raw){$key=hmNorm($raw);if($key==='')continue;$targets=array_keys($localIndex[$cc.'|'.$key]??[]);if(count($targets)===1)$votes[$targets[0]][$key]=true;}
        if(count($votes)!==1)continue;$lid=(int)array_key_first($votes);if(isset($excludedByAnex[$aid][$lid]))continue;
        $distance=null;$blocked=false;$sr=$stagingById[$aid]??null;
        if($sr&&isset($localCoords[$lid])){$lat=hmFloat($sr['latitude']??null);$lon=hmFloat($sr['longitude']??null);if($lat!==null&&$lon!==null){$distance=hmDistanceKm($lat,$lon,$localCoords[$lid][0],$localCoords[$lid][1]);if($distance>5.0){$blocked=true;$coordConflicts++;}}}
        if($blocked)continue;$keys=array_keys($votes[$lid]);$bridge=array_values(array_filter($keys,static fn($k)=>isset($andNamesByTarget[$lid][$k])));
        $liveCandidates[]=['anex_hotel_id'=>$aid,'local_hotel_id'=>$lid,'occurrences'=>$o['occurrences'],'country_class'=>$cc,'normalized_names'=>$keys,'andromeda_exact_name_bridge'=>!empty($bridge),'bridge_names'=>$bridge,'coordinate_km'=>$distance,'staging_present'=>$st];
    }
    usort($live,static fn($a,$b)=>$b['occurrences']<=>$a['occurrences'] ?: $a['anex_hotel_id']<=>$b['anex_hotel_id']);
    usort($liveCandidates,static fn($a,$b)=>($b['andromeda_exact_name_bridge']<=>$a['andromeda_exact_name_bridge']) ?: ($b['occurrences']<=>$a['occurrences']));

    $andCandidates=[];
    foreach($andPending as $p){$cc=$p['country_class'];if($cc===null||!$p['names'])continue;$votes=[];foreach($p['names'] as $key){$targets=array_keys($localIndex[$cc.'|'.$key]??[]);if(count($targets)===1)$votes[$targets[0]][$key]=true;}if(count($votes)!==1)continue;$lid=(int)array_key_first($votes);$keys=array_keys($votes[$lid]);$bridge=array_values(array_filter($keys,static fn($k)=>isset($anexNamesByTarget[$lid][$k])));$andCandidates[]=['external_hotel_id'=>$p['external_hotel_id'],'local_hotel_id'=>$lid,'country_class'=>$cc,'normalized_names'=>$keys,'anex_exact_name_bridge'=>!empty($bridge),'bridge_names'=>$bridge,'live_observation_count'=>$andObsFreq[$p['external_hotel_id']]??0];}
    usort($andCandidates,static fn($a,$b)=>($b['anex_exact_name_bridge']<=>$a['anex_exact_name_bridge']) ?: ($b['live_observation_count']<=>$a['live_observation_count']));

    $anexTargets=[];foreach($resolved as $lid)if(isset($active[$lid]))$anexTargets[$lid]=true;
    $triple=count(array_intersect_key($anexTargets,$andAccepted));$anexOnly=count(array_diff_key($anexTargets,$andAccepted));$andOnly=count(array_diff_key($andAccepted,$anexTargets));
    $liveBridge=count(array_filter($liveCandidates,static fn($r)=>$r['andromeda_exact_name_bridge']));
    $andBridge=count(array_filter($andCandidates,static fn($r)=>$r['anex_exact_name_bridge']));
    $andPendingCore8=count(array_filter($andPending,static fn($r)=>$r['country_class']!==null));
    $andPendingLive=count(array_filter($andPending,static fn($r)=>($andObsFreq[$r['external_hotel_id']]??0)>0));

    $pdo->exec('ROLLBACK');
    $out=['status'=>'completed','operation_id'=>HM_POST_READER_OP,'source_sha'=>getenv('MATCH_SOURCE_SHA')?:null,'generated_at_utc'=>gmdate('c'),
        'counts'=>['core8_active_local'=>count($active),'approved_anex_mapping_rows'=>$approvedRows,'resolved_anex_ids'=>count($resolved),'manual_accepted'=>$manualAccepted,'mapping_ambiguous'=>count($mappingAmbiguous),'anex_observation_ids'=>count($obs),'live_unresolved_core8_anex'=>count($live),'live_unresolved_occurrences'=>$liveOccurrences,'live_unresolved_absent_staging'=>$liveAbsentStaging,'live_unique_exact_candidates'=>count($liveCandidates),'live_exact_cross_provider_bridge_candidates'=>$liveBridge,'live_coordinate_gt5km_blocked'=>$coordConflicts,'andromeda_statuses'=>$andStatus,'andromeda_pending_core8'=>$andPendingCore8,'andromeda_pending_live_observed'=>$andPendingLive,'andromeda_unique_exact_candidates'=>count($andCandidates),'andromeda_exact_anex_bridge_candidates'=>$andBridge,'triple_unique_local'=>$triple,'anex_tv_only_unique_local'=>$anexOnly,'andromeda_tv_only_unique_local'=>$andOnly],
        'priority_live_anex'=>array_slice($live,0,250),'prepared_live_anex_candidates'=>array_slice($liveCandidates,0,1000),'prepared_andromeda_candidates'=>array_slice($andCandidates,0,1500),
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
} catch(Throwable $e) {
    if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    $out=['status'=>'failed','operation_id'=>HM_POST_READER_OP,'safe_message'=>'post_reader_census_failed','error_class'=>get_class($e),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
}
while(ob_get_level())ob_end_clean();echo 'MATCH_POST_READER:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit(($out['status']??'')==='completed'?0:2);
