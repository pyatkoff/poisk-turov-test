<?php
declare(strict_types=1);

require_once __DIR__ . '/anex_hotelcode_evidence.php';
require_once dirname(__DIR__, 2) . '/app/integrations/anex-client.php';

const HMAPH_OPERATION = 'hotel-match-anex-photos-hotelcode-evidence-1971-20260911-v1';
const HMAPH_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];
const HMAPH_MAX_TARGETS = 300;
const HMAPH_BATCH_SIZE = 30;

function hmaph_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function hmaph_int_set(array $values): array {
    $out = [];
    foreach ($values as $value) {
        $id = (int)$value;
        if ($id > 0) $out[$id] = true;
    }
    return $out;
}

function hmaph_str($value): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    return $value === '' ? null : mb_substr($value, 0, 500, 'UTF-8');
}

function hmaph_plan(PDO $db, array $strongResult, string $operation = HMAPH_OPERATION): array {
    if ($operation !== HMAPH_OPERATION) throw new RuntimeException('HMAPH_OPERATION_SCOPE');
    if (($strongResult['operation_id'] ?? null) !== 'hotel-match-anex-sold-details-strong-review-1971-20260911-v1'
        || ($strongResult['status'] ?? null) !== 'completed'
        || ($strongResult['historical_operations_replayed'] ?? null) !== false) {
        throw new RuntimeException('HMAPH_SOURCE_INVALID');
    }
    $prepared = [];
    foreach (($strongResult['prepared'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['anex_hotel_id'] ?? 0);
        if ($id > 0) $prepared[$id] = true;
    }
    if (count($prepared) !== 106) throw new RuntimeException('HMAPH_SOURCE_PREPARED_COUNT');

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $manual = hmaph_int_set($db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN));
        $mapped = hmaph_int_set($db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN));
        $pairExcluded = hmaph_int_set($db->query('SELECT DISTINCT anex_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_COLUMN));
        $known = hmaph_int_set($db->query('SELECT anex_hotel_id FROM anex_hotels UNION SELECT anex_hotel_id FROM anex_search_hotel_observations')->fetchAll(PDO::FETCH_COLUMN));
        $staging = [];
        foreach ($db->query('SELECT anex_hotel_id,api_name,xml_name,xml_alternate_name,api_country,api_region,api_town,latitude,longitude FROM anex_hotels')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $staging[(int)$row['anex_hotel_id']] = $row;
        }
        $observations = $db->query('SELECT anex_hotel_id,country_id,hotel_name,search_count,last_seen_utc FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id ASC')->fetchAll(PDO::FETCH_ASSOC);

        $stats = [
            'observed_rows'=>0,'core8_observed'=>0,'protected_manual'=>0,'protected_mapping'=>0,
            'pair_excluded'=>0,'strong_prepared_excluded'=>0,'selected'=>0,'truncated'=>0,
        ];
        $targets = [];
        foreach ($observations as $row) {
            $stats['observed_rows']++;
            $id = (int)$row['anex_hotel_id'];
            $country = (int)$row['country_id'];
            if ($id <= 0 || !isset(HMAPH_CORE8[$country])) continue;
            $stats['core8_observed']++;
            if (isset($manual[$id])) { $stats['protected_manual']++; continue; }
            if (isset($mapped[$id])) { $stats['protected_mapping']++; continue; }
            if (isset($pairExcluded[$id])) { $stats['pair_excluded']++; continue; }
            if (isset($prepared[$id])) { $stats['strong_prepared_excluded']++; continue; }
            if (count($targets) >= HMAPH_MAX_TARGETS) { $stats['truncated']++; continue; }
            $s = $staging[$id] ?? [];
            $names = [];
            foreach ([$row['hotel_name'] ?? null,$s['api_name'] ?? null,$s['xml_name'] ?? null,$s['xml_alternate_name'] ?? null] as $name) {
                $name = hmaph_str($name);
                if ($name !== null) $names[$name] = true;
            }
            $targets[] = [
                'anex_hotel_id'=>$id,
                'country_id'=>$country,
                'search_count'=>(int)($row['search_count'] ?? 0),
                'last_seen_utc'=>$row['last_seen_utc'] ?? null,
                'names'=>array_keys($names),
                'region'=>hmaph_str($s['api_region'] ?? null),
                'town'=>hmaph_str($s['api_town'] ?? null),
                'latitude'=>isset($s['latitude']) && is_numeric($s['latitude']) ? (float)$s['latitude'] : null,
                'longitude'=>isset($s['longitude']) && is_numeric($s['longitude']) ? (float)$s['longitude'] : null,
            ];
        }
        $stats['selected'] = count($targets);
        if (!$targets) throw new RuntimeException('HMAPH_NO_CURRENT_TARGETS');
        $targetIds = array_map(static fn(array $row): int => (int)$row['anex_hotel_id'], $targets);
        $targetSha = hash('sha256', hmaph_json($targetIds));
        $db->commit();
        return [
            'status'=>'ready','operation_id'=>$operation,'mode'=>'server_current_live_unresolved_core8_photos_evidence',
            'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,
            'strong_prepared_count'=>count($prepared),'target_sha256'=>$targetSha,'target_ids'=>$targetIds,'targets'=>$targets,
            'known_anex_ids'=>array_map('intval', array_keys($known)),'stats'=>$stats,
            'guards'=>['core8_only'=>true,'observed_only'=>true,'manual_decisions_overwritten'=>false,'existing_mappings_overwritten'=>false,'pair_exclusions_overwritten'=>false,'strong_acceptance_targets_duplicated'=>false],
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function hmaph_collect_urls($value, array &$urls, int $depth = 0): void {
    if ($depth > 8 || count($urls) >= 40) return;
    if (is_array($value)) {
        foreach ($value as $child) hmaph_collect_urls($child, $urls, $depth + 1);
        return;
    }
    if (!is_string($value) || $value === '') return;
    if (preg_match_all('~https://files\.anextour\.ru/[^\s"\'<>]+~iu', $value, $m)) {
        foreach ($m[0] as $url) {
            $url = rtrim($url, '.,);]');
            $urls[$url] = true;
            if (count($urls) >= 40) return;
        }
    }
}

function hmaph_collect_photo_rows($value, array &$rows, int $depth = 0): void {
    if ($depth > 8 || !is_array($value) || count($rows) > 500) return;
    if (array_key_exists('hotelKey', $value) && array_key_exists('photos', $value)) {
        $rows[] = $value;
        return;
    }
    foreach ($value as $child) if (is_array($child)) hmaph_collect_photo_rows($child, $rows, $depth + 1);
}

function hmaph_parse_photo_payload(array $payload, array $requestedIds, array $knownAnexIds): array {
    $requested = hmaph_int_set($requestedIds);
    $known = hmaph_int_set($knownAnexIds);
    $rows = [];
    hmaph_collect_photo_rows($payload, $rows);
    $evidence = [];
    $seenRequested = [];
    $counts = ['response_rows'=>0,'requested_rows'=>0,'unexpected_hotel_key'=>0,'confirmed'=>0,'confirmed_same_key'=>0,'confirmed_distinct_key'=>0,'insufficient'=>0,'conflicting'=>0,'invalid'=>0];
    foreach ($rows as $row) {
        $counts['response_rows']++;
        $hotelKey = (int)($row['hotelKey'] ?? 0);
        $isRequested = $hotelKey > 0 && isset($requested[$hotelKey]);
        if (!$isRequested) $counts['unexpected_hotel_key']++; else { $counts['requested_rows']++; $seenRequested[$hotelKey] = true; }
        $urls = [];
        hmaph_collect_urls($row['photos'] ?? [], $urls);
        $urlList = array_keys($urls);
        sort($urlList, SORT_STRING);
        $parsed = anytour_anex_hotelcode_evidence($urlList);
        $status = (string)($parsed['status'] ?? 'invalid_evidence');
        $code = $status === 'confirmed' ? (int)$parsed['hotel_code'] : null;
        if ($status === 'confirmed') {
            $counts['confirmed']++;
            $counts[$code === $hotelKey ? 'confirmed_same_key' : 'confirmed_distinct_key']++;
        } elseif ($status === 'insufficient_evidence') $counts['insufficient']++;
        elseif ($status === 'conflicting_codes') $counts['conflicting']++;
        else $counts['invalid']++;
        $evidence[] = [
            'response_hotel_key'=>$hotelKey > 0 ? $hotelKey : null,
            'requested'=>$isRequested,
            'url_count'=>count($urlList),
            'sample_urls'=>array_slice($urlList, 0, 3),
            'hotelcode_status'=>$status,
            'hotel_code'=>$code,
            'hotel_code_equals_response_hotel_key'=>$code !== null && $hotelKey > 0 ? $code === $hotelKey : null,
            'hotel_code_known_current_anex_id'=>$code !== null ? isset($known[$code]) : null,
        ];
    }
    $missing = [];
    foreach (array_keys($requested) as $id) if (!isset($seenRequested[$id])) $missing[] = (int)$id;
    sort($missing, SORT_NUMERIC);
    return ['counts'=>$counts,'evidence'=>$evidence,'missing_requested_ids'=>$missing];
}

function hmaph_collect(AnyTourAnexClient $client, array $plan, string $operation = HMAPH_OPERATION): array {
    if ($operation !== HMAPH_OPERATION || ($plan['operation_id'] ?? null) !== $operation || ($plan['status'] ?? null) !== 'ready') throw new RuntimeException('HMAPH_PLAN_INVALID');
    $ids = array_values(array_map('intval', $plan['target_ids'] ?? []));
    if (!$ids || count($ids) > HMAPH_MAX_TARGETS || count(array_unique($ids)) !== count($ids)) throw new RuntimeException('HMAPH_TARGET_INVALID');
    if (hash('sha256', hmaph_json($ids)) !== ($plan['target_sha256'] ?? '')) throw new RuntimeException('HMAPH_TARGET_HASH');

    $allEvidence = [];
    $allMissing = [];
    $totals = ['response_rows'=>0,'requested_rows'=>0,'unexpected_hotel_key'=>0,'confirmed'=>0,'confirmed_same_key'=>0,'confirmed_distinct_key'=>0,'insufficient'=>0,'conflicting'=>0,'invalid'=>0];
    foreach (array_chunk($ids, HMAPH_BATCH_SIZE) as $chunk) {
        $payload = $client->request('Hotels_PHOTOS', ['HOTELS'=>implode(',', $chunk)]);
        $parsed = hmaph_parse_photo_payload($payload, $chunk, $plan['known_anex_ids'] ?? []);
        foreach ($totals as $key => $_) $totals[$key] += (int)($parsed['counts'][$key] ?? 0);
        foreach ($parsed['evidence'] as $row) $allEvidence[] = $row;
        foreach ($parsed['missing_requested_ids'] as $id) $allMissing[(int)$id] = true;
    }
    $confirmed = array_values(array_filter($allEvidence, static fn(array $row): bool => $row['requested'] && $row['hotelcode_status'] === 'confirmed'));
    usort($confirmed, static fn(array $a, array $b): int => ((int)$a['response_hotel_key']) <=> ((int)$b['response_hotel_key']));
    $missing = array_map('intval', array_keys($allMissing)); sort($missing, SORT_NUMERIC);
    return [
        'status'=>'completed','operation_id'=>$operation,'mode'=>'anex_hotels_photos_hotelcode_evidence_only',
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>$client->requestsMade(),'historical_operations_replayed'=>false,
        'target_sha256'=>$plan['target_sha256'],'target_count'=>count($ids),'counts'=>$totals,'confirmed'=>$confirmed,'evidence'=>$allEvidence,'missing_requested_ids'=>$missing,
        'guards'=>['hotelcode_is_identity_evidence_only'=>true,'hotelcode_assumed_equal_to_anex_id'=>false,'mapping_acceptance_performed'=>false,'content_writes_performed'=>false],
    ];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    fwrite(STDERR, "library_only: use guarded workflow\n");
    exit(64);
}
