<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';
require_once __DIR__ . '/anex_hotelcode_evidence.php';

const AHQ_OPERATION = 'hotel-match-anex-hotelcode-current-queue-1971-20260911-v1';
const AHQ_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function ahq_collect_urls($value, array &$out, int $depth = 0): void {
    if ($depth > 6 || count($out) >= 64) return;
    if (is_array($value)) {
        foreach ($value as $child) ahq_collect_urls($child, $out, $depth + 1);
        return;
    }
    if (!is_string($value) || $value === '') return;
    if (preg_match_all('~https://files\.anextour\.ru/[^\s"\'<>]+~iu', $value, $m)) {
        foreach ($m[0] as $url) {
            $url = rtrim($url, '.,);]');
            $out[$url] = true;
            if (count($out) >= 64) return;
        }
    }
}

function ahq_saved_evidence(array $rows): array {
    $urls = [];
    foreach ($rows as $row) ahq_collect_urls($row, $urls);
    $list = array_keys($urls);
    sort($list, SORT_STRING);
    $parsed = anytour_anex_hotelcode_evidence($list);
    return ['urls' => $list, 'parsed' => $parsed];
}

function ahq_seed_from_review(array $row, array $sourceRows): array {
    $id = (int)$row['external_id'];
    $saved = ahq_saved_evidence($sourceRows);
    $parsed = $saved['parsed'];
    $bucket = 'operator_link_required';
    if (($parsed['status'] ?? '') === 'confirmed') $bucket = 'saved_hotelcode_evidence';
    elseif (($parsed['status'] ?? '') === 'conflict') $bucket = 'saved_hotelcode_conflict';
    elseif (($parsed['status'] ?? '') === 'invalid') $bucket = 'saved_hotelcode_invalid';
    $seed = [
        'anex_hotel_id' => $id,
        'country_id' => (int)$row['country_id'],
        'observed' => (bool)($row['observed'] ?? false),
        'search_count' => (int)($row['search_count'] ?? 0),
        'last_seen_utc' => $row['last_seen_utc'] ?? null,
        'review_reason' => (string)($row['reason'] ?? ''),
        'evidence_bucket' => $bucket,
        'saved_hotelcode' => ($parsed['status'] ?? '') === 'confirmed' ? (int)$parsed['hotel_code'] : null,
        'saved_url_count' => count($saved['urls']),
        'source_names' => array_values($row['source_names'] ?? []),
        'source_places' => array_values($row['source_places'] ?? []),
        'candidate_ids' => array_values(array_map('intval', $row['candidate_ids'] ?? [])),
        'target' => $row['target'] ?? null,
        'best' => $row['best'] ?? null,
        'guard' => $row['guard'] ?? null,
        'next_evidence' => 'Tourvisor ANEX-only result -> visible operator/hotel link -> ANEX page/media files.anextour.ru hotelCode -> country/name/geo/coordinate verification',
    ];
    if ($seed['saved_hotelcode'] !== null) {
        $seed['saved_hotelcode_matches_current_anex_id'] = $seed['saved_hotelcode'] === $id;
    }
    return $seed;
}

function ahq_sort_seeds(array &$rows): void {
    $rank = ['saved_hotelcode_conflict'=>0,'saved_hotelcode_evidence'=>1,'saved_hotelcode_invalid'=>2,'operator_link_required'=>3];
    usort($rows, static function(array $a, array $b) use ($rank): int {
        $ao = $a['observed'] ? 0 : 1; $bo = $b['observed'] ? 0 : 1;
        if ($ao !== $bo) return $ao <=> $bo;
        $cmp = ((int)$b['search_count']) <=> ((int)$a['search_count']);
        if ($cmp !== 0) return $cmp;
        $cmp = ($rank[$a['evidence_bucket']] ?? 9) <=> ($rank[$b['evidence_bucket']] ?? 9);
        if ($cmp !== 0) return $cmp;
        return ((int)$a['anex_hotel_id']) <=> ((int)$b['anex_hotel_id']);
    });
}

function ahq_review(PDO $db, string $operation): array {
    if ($operation !== AHQ_OPERATION) throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try {
        $coverage = fc_coverage($db);
        [$hotels,$names,$strict,$broad,$places,$catalogScope] = mbr_catalog($db);
        $manual = array_fill_keys(array_map('intval', $db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)), true);
        $existing = array_fill_keys(array_map('intval', $db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)), true);
        $excluded = [];
        foreach ($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']] = true;
        }
        $staging = [];
        foreach ($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s) $staging[(int)$s['anex_hotel_id']] = $s;
        $observations = $db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        $latestObservation = [];
        foreach ($observations as $o) {
            $id = (int)$o['anex_hotel_id'];
            if (!isset($latestObservation[$id])) $latestObservation[$id] = $o;
        }

        $seeds = [];
        $examined = ['observed'=>0,'staging_only'=>0,'protected'=>0,'auto_accept_current_rule'=>0,'needs_external_evidence'=>0];
        $seen = [];
        foreach ($observations as $o) {
            $id = (int)$o['anex_hotel_id']; $country = (int)$o['country_id'];
            if (!isset(AHQ_CORE8[$country]) || isset($seen[$id])) continue;
            $seen[$id] = true; $examined['observed']++;
            if (isset($manual[$id]) || isset($existing[$id])) { $examined['protected']++; continue; }
            $s = $staging[$id] ?? [];
            $source = ['observed'=>true,'search_count'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc'],'names'=>[$o['hotel_name'],$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];
            $row = mbr_review_anex($source,$id,$country,$hotels,$names,$strict,$broad,$places);
            $target = (int)($row['target']['local_hotel_id'] ?? 0);
            if ($target && isset($excluded[$id][$target])) { $row['bucket']='hard_conflict'; $row['reason']='pair_exclusion_protected'; }
            if (($row['bucket'] ?? '') === 'auto_accept') { $examined['auto_accept_current_rule']++; continue; }
            $examined['needs_external_evidence']++;
            $seeds[] = ahq_seed_from_review($row, [$o,$s]);
        }
        foreach ($staging as $id => $s) {
            if (isset($seen[$id]) || isset($manual[$id]) || isset($existing[$id])) continue;
            $country = fc_country($s['api_country'] ?? '');
            if (!$country || !isset(AHQ_CORE8[$country])) continue;
            $examined['staging_only']++;
            $source = ['observed'=>false,'search_count'=>0,'names'=>[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];
            $row = mbr_review_anex($source,(int)$id,$country,$hotels,$names,$strict,$broad,$places);
            $target = (int)($row['target']['local_hotel_id'] ?? 0);
            if ($target && isset($excluded[(int)$id][$target])) { $row['bucket']='hard_conflict'; $row['reason']='pair_exclusion_protected'; }
            if (($row['bucket'] ?? '') === 'auto_accept') { $examined['auto_accept_current_rule']++; continue; }
            $examined['needs_external_evidence']++;
            $seeds[] = ahq_seed_from_review($row, [$s]);
        }
        ahq_sort_seeds($seeds);
        $counts = ['tourvisor_anex_seeds'=>count($seeds),'observed_seeds'=>0,'staging_only_seeds'=>0,'saved_hotelcode_evidence'=>0,'saved_hotelcode_conflict'=>0,'saved_hotelcode_invalid'=>0,'operator_link_required'=>0];
        foreach ($seeds as $seed) {
            $counts[$seed['observed'] ? 'observed_seeds' : 'staging_only_seeds']++;
            $bucket = $seed['evidence_bucket']; if (isset($counts[$bucket])) $counts[$bucket]++;
        }
        $db->commit();
        return [
            'status'=>'completed','operation_id'=>$operation,'mode'=>'current_db_anex_hotelcode_queue_read_only','database_writes'=>0,'supplier_calls'=>0,
            'coverage'=>$coverage,'catalog_scope'=>$catalogScope,'examined'=>$examined,'counts'=>$counts,'seeds'=>$seeds,
            'guards'=>['core8_only'=>true,'manual_decisions_overwritten'=>false,'existing_mappings_overwritten'=>false,'pair_exclusions_overwritten'=>false,'coordinate_conflict_auto_block_m'=>5000,'historical_operations_replayed'=>false,'saved_files_anextour_hotelcode_is_evidence_only'=>true]
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['status'=>'failed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'reason'=>in_array($e->getMessage(),['operation_scope','country_contract_changed','hotel_scope_limit','alias_scope_limit'],true)?$e->getMessage():'runtime_failure'];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    fwrite(STDERR, "library_only: use guarded workflow with server DB helper\n");
    exit(64);
}
