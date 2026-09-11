<?php
declare(strict_types=1);

if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_coordinate_anchor_review.php';

const HCAA_OPERATION = 'hotel-match-coordinate-anchor-accept-1971-20260911-v1';

function hcaa_require_transactional(PDO $db): void {
    $tables = [
        'catalog_hotels','catalog_hotel_details','hotel_aliases',
        'anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions',
        'anex_hotels','anex_search_hotel_observations','andromeda_hotel_identities',
    ];
    $q = $db->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    foreach ($tables as $table) {
        $q->execute([$table]);
        $engine = $q->fetchColumn();
        if ($engine === false) throw new RuntimeException('required_table_missing:'.$table);
        if (strcasecmp((string)$engine, 'InnoDB') !== 0) throw new RuntimeException('required_table_not_innodb:'.$table);
    }
}

function hcaa_geo_tokens(array $values): array {
    $out = [];
    foreach ($values as $value) {
        foreach (pcbr_identity_tokens(htxr_latin((string)$value)) as $token) $out[$token] = true;
    }
    return $out;
}

function hcaa_name_pair(array $sourceNames, array $sourcePlaces, array $targetNames, array $targetPlaces): array {
    $geo = hcaa_geo_tokens(array_merge($sourcePlaces, $targetPlaces));
    $best = [
        'safe'=>false,'shared'=>0,'identity_shared'=>0,'score'=>0.0,'ordered'=>false,
        'critical_ok'=>false,'source'=>'','target'=>'','source_tokens'=>[],'target_tokens'=>[],
        'shared_tokens'=>[],'identity_shared_tokens'=>[],
    ];
    foreach ($sourceNames as $source) {
        $source = trim((string)$source);
        if ($source === '') continue;
        $s = pcbr_identity_tokens(htxr_latin($source));
        if (count($s) < 2) continue;
        foreach ($targetNames as $target) {
            $target = trim((string)$target);
            if ($target === '') continue;
            $t = pcbr_identity_tokens(htxr_latin($target));
            if (count($t) < 2) continue;
            $critical = hcar_critical($source) === hcar_critical($target);
            $us = array_values(array_unique($s));
            $ut = array_values(array_unique($t));
            $sharedTokens = array_values(array_intersect($us, $ut));
            $identityShared = array_values(array_filter($sharedTokens, static fn($token) => !isset($geo[$token])));
            $shared = count($sharedTokens);
            $union = count(array_unique(array_merge($us, $ut)));
            $score = $union ? $shared / $union : 0.0;
            $ordered = hcar_ordered_contains($s, $t) || hcar_ordered_contains($t, $s);
            $safe = $critical && $shared >= 2 && count($identityShared) >= 1 && ($ordered || $score >= 0.45);
            $candidate = [
                'safe'=>$safe,'shared'=>$shared,'identity_shared'=>count($identityShared),
                'score'=>round($score, 6),'ordered'=>$ordered,'critical_ok'=>$critical,
                'source'=>$source,'target'=>$target,'source_tokens'=>$s,'target_tokens'=>$t,
                'shared_tokens'=>$sharedTokens,'identity_shared_tokens'=>$identityShared,
            ];
            $rank = [(int)$candidate['safe'],$candidate['identity_shared'],$candidate['shared'],(int)$candidate['ordered'],$candidate['score']];
            $bestRank = [(int)$best['safe'],$best['identity_shared'],$best['shared'],(int)$best['ordered'],$best['score']];
            if ($rank > $bestRank) $best = $candidate;
        }
    }
    return $best;
}

function hcaa_strict_candidate(
    array $sourceCoord,
    array $sourceNames,
    array $sourcePlaces,
    int $country,
    array $hotels,
    array $names,
    array $oppositeLocal,
    array $excluded = []
): array {
    [$lat,$lon] = mbr_coord($sourceCoord);
    if ($lat === null || $lon === null) return ['candidate'=>null,'reason'=>'source_coordinates_missing'];

    $strong = [];
    $nearby = 0;
    foreach ($hotels as $id => $hotel) {
        $id = (int)$id;
        if ((int)$hotel['country_id'] !== $country || isset($excluded[$id])) continue;
        $distance = fc_dist($lat,$lon,$hotel['latitude']??null,$hotel['longitude']??null);
        if ($distance === null || $distance > 500.0) continue;
        $nearby++;
        $pair = hcaa_name_pair(
            $sourceNames,
            $sourcePlaces,
            $names[$id] ?? [(string)$hotel['name']],
            [(string)($hotel['region_name']??''),(string)($hotel['subregion_name']??'')]
        );
        if (!$pair['safe']) continue;
        $strong[$id] = [
            'target_local_hotel_id'=>$id,
            'target_name'=>(string)$hotel['name'],
            'target_region'=>$hotel['region_name']??null,
            'target_subregion'=>$hotel['subregion_name']??null,
            'target_category'=>$hotel['category']===null?null:(int)$hotel['category'],
            'distance_m'=>round((float)$distance,2),
            'existing_opposite_provider_bridge'=>isset($oppositeLocal[$id]),
            'name'=>$pair,
        ];
    }
    if (!$strong) return ['candidate'=>null,'reason'=>'no_strong_identity_within_500m','nearby'=>$nearby];
    if (count($strong) !== 1) return ['candidate'=>null,'reason'=>'strong_identity_ambiguous','nearby'=>$nearby,'strong_targets'=>count($strong)];

    $candidate = array_values($strong)[0];
    $limit = $candidate['existing_opposite_provider_bridge'] ? 250.0 : 100.0;
    if ((float)$candidate['distance_m'] > $limit) {
        return ['candidate'=>null,'reason'=>'strong_identity_too_far','nearby'=>$nearby,'distance_m'=>$candidate['distance_m'],'limit_m'=>$limit];
    }
    $candidate['rule'] = $candidate['existing_opposite_provider_bridge']
        ? 'strict_coordinate_name_plus_existing_andromeda_bridge'
        : 'strict_unique_coordinate_name_alias';
    $candidate['nearby_500m'] = $nearby;
    return ['candidate'=>$candidate,'reason'=>'safe','nearby'=>$nearby];
}

function hcaa_candidate_safe(array $row): bool {
    if (($row['provider']??'') !== 'anex') return false;
    $country = (int)($row['country_id']??0);
    if (!isset(MBR_CORE8[$country])) return false;
    if ((int)($row['target_local_hotel_id']??0) <= 0) return false;
    $bridge = (bool)($row['existing_opposite_provider_bridge']??false);
    $distance = $row['distance_m']??null;
    if ($distance === null || (float)$distance > ($bridge ? 250.0 : 100.0)) return false;
    $pair = $row['name']??null;
    if (!is_array($pair) || !($pair['safe']??false) || !($pair['critical_ok']??false)) return false;
    if ((int)($pair['shared']??0) < 2 || (int)($pair['identity_shared']??0) < 1) return false;
    if (!($pair['ordered']??false) && (float)($pair['score']??0) < 0.45) return false;
    $rule = (string)($row['rule']??'');
    return in_array($rule,['strict_coordinate_name_plus_existing_andromeda_bridge','strict_unique_coordinate_name_alias'],true);
}

function hcaa_evidence(string $operation, array $row): array {
    return [
        'operation_id'=>$operation,'lane'=>'MATCH','provider'=>'anex','server_current'=>true,
        'rule'=>$row['rule']??null,'anex_hotel_id'=>(int)$row['external_id'],
        'country_id'=>(int)$row['country_id'],'source_names'=>$row['source_names']??[],
        'source_places'=>$row['source_places']??[],'search_count'=>(int)($row['observation_count']??0),
        'last_seen_utc'=>$row['last_seen_utc']??null,
        'target_local_hotel_id'=>(int)$row['target_local_hotel_id'],
        'target_name'=>$row['target_name']??null,'target_region'=>$row['target_region']??null,
        'target_subregion'=>$row['target_subregion']??null,'distance_m'=>$row['distance_m']??null,
        'existing_andromeda_tourvisor_bridge'=>(bool)($row['existing_opposite_provider_bridge']??false),
        'name_evidence'=>$row['name']??null,'nearby_500m'=>$row['nearby_500m']??null,
        'source_category'=>$row['source_category']??null,'target_category'=>$row['target_category']??null,
        'category_mismatch'=>(bool)($row['category_mismatch']??false),
    ];
}

function hcaa_accept(PDO $db, string $operation=HCAA_OPERATION, int $maxWrites=100): array {
    if ($operation !== HCAA_OPERATION) throw new RuntimeException('operation_scope');
    if ($maxWrites < 1 || $maxWrites > 100) throw new RuntimeException('write_scope_limit_config');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    hcaa_require_transactional($db);
    $db->exec('SET SESSION innodb_lock_wait_timeout=20');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

    $before = fc_coverage($db);
    $rows = [];
    $planned = 0;
    $writes = 0;
    $liveWrites = 0;
    $bridgeWrites = 0;
    $skipped = [
        'protected'=>0,'pair_excluded'=>0,'country_unknown'=>0,'not_safe'=>0,
        'source_coordinates_missing'=>0,'no_strong_identity_within_500m'=>0,
        'strong_identity_ambiguous'=>0,'strong_identity_too_far'=>0,'planner_guard'=>0,
    ];
    try {
        $db->beginTransaction();
        [$hotels,$names] = mbr_catalog($db);
        $db->query("SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        [, $andromedaLocal] = mbr_local_sets($db);

        $manual = array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)),true);
        $existing = array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)),true);
        $excluded = [];
        foreach ($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']] = true;
        }
        $staging = [];
        foreach ($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $s) $staging[(int)$s['anex_hotel_id']] = $s;
        $observed = [];
        foreach ($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $id=(int)$o['anex_hotel_id']; if (!isset($observed[$id])) $observed[$id]=$o;
        }

        $ids = array_values(array_unique(array_merge(array_keys($observed),array_keys($staging))));
        sort($ids,SORT_NUMERIC);
        $safeRows = [];
        foreach ($ids as $id) {
            $id=(int)$id;
            if (isset($manual[$id]) || isset($existing[$id])) { $skipped['protected']++; continue; }
            $s=$staging[$id]??[]; $o=$observed[$id]??null;
            $country=(int)($o['country_id']??0);
            if (!isset(MBR_CORE8[$country])) $country=(int)(fc_country($s['api_country']??'')??0);
            if (!isset(MBR_CORE8[$country])) { $skipped['country_unknown']++; continue; }
            $sourceNames=array_values(array_unique(array_filter([
                (string)($o['hotel_name']??''),(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')
            ],static fn($v)=>trim($v)!=='')));
            $sourcePlaces=array_values(array_unique(array_filter([
                (string)($s['api_region']??''),(string)($s['api_town']??''),(string)($o['region_name']??'')
            ],static fn($v)=>trim($v)!=='')));
            $coordSource=array_merge($s,$o??[]);
            $found=hcaa_strict_candidate($coordSource,$sourceNames,$sourcePlaces,$country,$hotels,$names,$andromedaLocal,$excluded[$id]??[]);
            if ($found['candidate']===null) {
                $reason=(string)$found['reason']; if (isset($skipped[$reason])) $skipped[$reason]++; else $skipped['not_safe']++;
                continue;
            }
            $c=$found['candidate'];
            $sourceCategory=mbr_numeric_category($s); if($sourceCategory===null&&$o)$sourceCategory=mbr_numeric_category($o);
            $mismatch=$sourceCategory!==null&&$c['target_category']!==null&&$sourceCategory!==$c['target_category'];
            $row=array_merge([
                'provider'=>'anex','external_id'=>$id,'country_id'=>$country,
                'observation_count'=>(int)($o['search_count']??0),'last_seen_utc'=>$o['last_seen_utc']??null,
                'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,
                'category_mismatch'=>$mismatch,
            ],$c);
            if (!hcaa_candidate_safe($row)) { $skipped['planner_guard']++; continue; }
            if (isset($excluded[$id][(int)$row['target_local_hotel_id']])) { $skipped['pair_excluded']++; continue; }
            $safeRows[]=$row;
        }

        $planned=count($safeRows);
        if ($planned > $maxWrites) throw new RuntimeException('write_scope_limit');
        $insert=$db->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $mappingDigest=fc_hash([$operation,'strict_coordinate_anchor_accept_v1']);
        foreach ($safeRows as $row) {
            $id=(int)$row['external_id']; $target=(int)$row['target_local_hotel_id'];
            if (isset($manual[$id]) || isset($existing[$id])) { $skipped['protected']++; continue; }
            if (isset($excluded[$id][$target])) { $skipped['pair_excluded']++; continue; }
            $evidence=hcaa_evidence($operation,$row); $digest=fc_hash($evidence);
            $insert->execute([$id,$target,MBR_POLICY,$digest,$mappingDigest]);
            if ($insert->rowCount()!==1) throw new RuntimeException('anex_insert_not_one:'.$id);
            $rows[$id]=['target'=>$target,'source_row_digest'=>$digest,'rule'=>$row['rule'],'live'=>(int)$row['observation_count']>0,'bridge'=>(bool)$row['existing_opposite_provider_bridge']];
            $existing[$id]=true; $writes++;
            if ((int)$row['observation_count']>0) $liveWrites++;
            if ($row['existing_opposite_provider_bridge']) $bridgeWrites++;
        }
        if ($writes > $maxWrites) throw new RuntimeException('write_scope_limit');
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    $read=$db->prepare('SELECT catalog_hotel_id,source_row_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');
    foreach ($rows as $id=>$expected) {
        $read->execute([(int)$id]); $actual=$read->fetch(PDO::FETCH_ASSOC);
        if (!$actual || (int)$actual['catalog_hotel_id']!==$expected['target'] || (int)$actual['enabled']!==1 || !hash_equals($expected['source_row_digest'],(string)$actual['source_row_digest'])) {
            throw new RuntimeException('anex_post_commit_readback_failed:'.$id);
        }
        $rows[$id]['readback']='verified';
    }
    $after=fc_coverage($db);
    return [
        'status'=>'committed_readback_verified','operation_id'=>$operation,'database_writes'=>$writes,
        'supplier_calls'=>0,'historical_operations_replayed'=>false,'planned'=>$planned,'skipped'=>$skipped,
        'live_writes'=>$liveWrites,'bridge_writes'=>$bridgeWrites,'anex_rows'=>$rows,
        'before'=>['coverage'=>$before],'after'=>['coverage'=>$after],
    ];
}
