<?php
declare(strict_types=1);
require_once __DIR__ . '/hotel_match_operator_fingerprint_room_evidence_v1.php';

const M80_OP = 'hotel-match-live80-room-current-1971-20260920-v2';
const M80_INPUT_SHA = '9c4472df212a45861596fd6c46c56670216fdd66239ec7679063a1bc81d4862b';

function m80_need(bool $ok, string $reason): void {
    if (!$ok) throw new RuntimeException($reason);
}
function m80_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}
function m80_save(string $path, array $v): string {
    $raw = m80_json($v);
    $f = fopen($path, 'xb');
    m80_need(is_resource($f), 'immutable_output');
    try {
        m80_need(fwrite($f, $raw) === strlen($raw) && fflush($f), 'output_write');
        if (function_exists('fsync')) m80_need(fsync($f), 'output_sync');
    } finally {
        fclose($f);
    }
    m80_need(file_get_contents($path) === $raw, 'output_readback');
    return hash('sha256', $raw);
}
function m80_rows(PDO $db, string $sql, array $params = []): array {
    $q = $db->prepare($sql);
    $q->execute($params);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    m80_need(count($rows) <= 20000, 'row_limit');
    return $rows;
}
function m80_pos_int(mixed $v): ?int {
    if (!is_scalar($v) || !preg_match('/^[1-9][0-9]*$/D', (string)$v)) return null;
    $n = (int)$v;
    return $n > 0 ? $n : null;
}
function m80_match_concepts(array $tvRows, array $samoRooms, int $contextSearchId): array {
    $tv = [];
    foreach ($tvRows as $row) {
        if (!is_array($row)) continue;
        $roomId = m80_pos_int($row['room_id'] ?? null);
        $raw = is_scalar($row['room_type'] ?? null) ? trim((string)$row['room_type']) : '';
        $key = hmf_room_key($raw);
        if ($roomId === null || $raw === '' || $key === '') continue;
        $tv[$key]['ids'][$roomId] = true;
        $tv[$key]['raw'][$raw] = true;
        if (isset($row['meal_id']) && $row['meal_id'] !== null) $tv[$key]['meals'][(string)$row['meal_id']] = true;
        if (isset($row['search_id']) && $row['search_id'] !== null) $tv[$key]['search_ids'][(string)$row['search_id']] = true;
        if (isset($row['tour_id']) && is_scalar($row['tour_id']) && trim((string)$row['tour_id']) !== '') $tv[$key]['tour_ids'][trim((string)$row['tour_id'])] = true;
        if (isset($row['observed_at']) && is_scalar($row['observed_at'])) $tv[$key]['observed_at'][(string)$row['observed_at']] = true;
    }

    $sa = [];
    foreach ($samoRooms as $row) {
        if (!is_array($row)) continue;
        $roomId = m80_pos_int($row['room_key'] ?? null);
        $raw = is_scalar($row['room_raw'] ?? null) ? trim((string)$row['room_raw']) : '';
        $key = hmf_room_key($raw);
        if ($roomId === null || $raw === '' || $key === '') continue;
        $sa[$key]['ids'][$roomId] = true;
        $sa[$key]['raw'][$raw] = true;
        foreach (($row['meal_variants'] ?? []) as $meal) {
            if (!is_array($meal)) continue;
            $mk = is_scalar($meal['meal_key'] ?? null) ? trim((string)$meal['meal_key']) : '';
            $mr = is_scalar($meal['meal_raw'] ?? null) ? trim((string)$meal['meal_raw']) : '';
            if ($mk !== '' || $mr !== '') $sa[$key]['meals'][$mk . '|' . $mr] = true;
        }
        foreach (($row['source_evidence'] ?? []) as $src) {
            if (!is_array($src)) continue;
            $file = is_scalar($src['file'] ?? null) ? trim((string)$src['file']) : '';
            $sha = is_scalar($src['sha256'] ?? null) ? trim((string)$src['sha256']) : '';
            if ($file !== '' && preg_match('/^[0-9a-f]{64}$/D', $sha)) $sa[$key]['sources'][$file . '|' . $sha] = true;
        }
    }

    $concepts = [];
    $holds = [];
    foreach (array_intersect(array_keys($tv), array_keys($sa)) as $key) {
        $tvIds = array_map('intval', array_keys($tv[$key]['ids'] ?? []));
        $saIds = array_map('intval', array_keys($sa[$key]['ids'] ?? []));
        sort($tvIds, SORT_NUMERIC);
        sort($saIds, SORT_NUMERIC);
        $base = [
            'room_key' => $key,
            'tv_room_ids' => $tvIds,
            'samo_room_keys' => $saIds,
            'tv_rooms' => array_values(array_keys($tv[$key]['raw'] ?? [])),
            'samo_rooms' => array_values(array_keys($sa[$key]['raw'] ?? [])),
            'tv_meal_ids' => array_values(array_keys($tv[$key]['meals'] ?? [])),
            'samo_meal_variants' => array_values(array_keys($sa[$key]['meals'] ?? [])),
            'tv_search_ids' => array_map('strval', array_values(array_keys($tv[$key]['search_ids'] ?? []))),
            'tv_tour_ids' => array_values(array_keys($tv[$key]['tour_ids'] ?? [])),
            'tv_observed_at' => array_values(array_keys($tv[$key]['observed_at'] ?? [])),
            'samo_sources' => array_values(array_keys($sa[$key]['sources'] ?? [])),
        ];
        foreach (['tv_rooms','samo_rooms','tv_meal_ids','samo_meal_variants','tv_search_ids','tv_tour_ids','tv_observed_at','samo_sources'] as $k) sort($base[$k], SORT_STRING);
        if (count($tvIds) === 1 && count($saIds) === 1) {
            $base['tv_room_id'] = $tvIds[0];
            $base['samo_room_key'] = $saIds[0];
            $base['evidence_class'] = in_array((string)$contextSearchId, $base['tv_search_ids'], true)
                ? 'same_search_exact_room_key'
                : 'same_context_exact_room_key';
            $base['safe_to_write_now'] = false;
            $concepts[] = $base;
        } else {
            $base['reason'] = count($tvIds) !== 1 ? 'ambiguous_tv_room_id' : 'ambiguous_samo_room_key';
            $base['safe_to_write_now'] = false;
            $holds[] = $base;
        }
    }
    usort($concepts, static fn(array $a, array $b): int => [$a['room_key'],$a['tv_room_id'],$a['samo_room_key']] <=> [$b['room_key'],$b['tv_room_id'],$b['samo_room_key']]);
    usort($holds, static fn(array $a, array $b): int => [$a['room_key'],$a['reason']] <=> [$b['room_key'],$b['reason']]);
    return ['concepts' => $concepts, 'holds' => $holds];
}

if (($argv[1] ?? '') === '--self-test') {
    $tv = [
        ['room_id'=>101,'room_type'=>'DELUXE ROOM','meal_id'=>1,'search_id'=>77,'tour_id'=>'t1','observed_at'=>'2026-09-20 00:00:00'],
        ['room_id'=>202,'room_type'=>'FAMILY SEA VIEW ROOM','meal_id'=>2,'search_id'=>78,'tour_id'=>'t2','observed_at'=>'2026-09-20 00:00:01'],
    ];
    $sa = [
        ['room_key'=>'501','room_raw'=>'Deluxe','meal_variants'=>[['meal_key'=>'6','meal_raw'=>'AI']],'source_evidence'=>[['file'=>'a.json','sha256'=>str_repeat('a',64)]]],
        ['room_key'=>'502','room_raw'=>'Family Sea View Room','meal_variants'=>[],'source_evidence'=>[]],
    ];
    $m = m80_match_concepts($tv,$sa,77);
    m80_need(count($m['concepts'])===2,'self_concept_count');
    $byKey = [];
    foreach ($m['concepts'] as $concept) $byKey[$concept['room_key']] = $concept;
    m80_need(isset($byKey['deluxe']) && $byKey['deluxe']['evidence_class']==='same_search_exact_room_key','self_deluxe');
    m80_need(isset($byKey['family sea view']) && $byKey['family sea view']['tv_room_id']===202 && $byKey['family sea view']['samo_room_key']===502,'self_qualifier');
    $tv[]=['room_id'=>303,'room_type'=>'DELUXE','meal_id'=>3,'search_id'=>79,'tour_id'=>'t3','observed_at'=>'2026-09-20 00:00:02'];
    $m2=m80_match_concepts($tv,$sa,77);
    m80_need(count($m2['concepts'])===1 && count($m2['holds'])===1 && $m2['holds'][0]['reason']==='ambiguous_tv_room_id','self_ambiguous');
    echo "MATCH_LIVE80_ROOM_SELFTEST_OK\n";
    exit(0);
}

m80_need(PHP_SAPI === 'cli' && (string)getenv('MATCH_OPERATION_ID') === M80_OP, 'operation_guard');
$dir = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations/' . M80_OP;
m80_need(realpath((string)getenv('MATCH_OPERATION_DIR')) === $dir, 'operation_directory');
$source = (string)getenv('MATCH_SOURCE_SHA');
m80_need(preg_match('/^[0-9a-f]{40}$/D', $source) === 1, 'source_sha');
m80_need(hash_file('sha256', $dir . '/input.json') === M80_INPUT_SHA, 'MÄ($tbRows) === 1 && strtoupper((string)$tbRows[0]['ENGINE']) === 'INNODB', 'table_engine');
    }
    $columns = [];
    foreach (m80_rows($db, 'SELECT COLUMN_NAME FROM information_schema.COLUMNNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', ['tour_price_observations']) as $c)) $columns[] = (string)$c['COLUMN_NAME'];
    foreach (['observed_at','source','search_id','hotel_id','tour_id','departure_date','nights','adults','children_count','child_ages_signature','meal_id','room_id','room_type','operator_id'] as $c)&í80_need(in_array($c, $columns, true), 'observation_column');
    
    $dossiers = [];
    $conceptCount = 0;
    $holdCount = 0;
    $sameSearch = 0;
    $sameContext = 0;
    $clustersWithTv = 0;
    $clustersWithConcept = 0;
    $tvObservationCount = 0;
    
    foreach ($input['rows'] as $row) {
        m80_need(is_array($row), 'input_row');
        $tvHotel = m80_pos_int($row['tv_hotel_id'] ?? null);
        $samoHotel = is_scalar($row['samo_hotel_id'] ?? null) ? trim((string)$row['samo_hotel_id']) : '';
        $nativeAnex = is_scalar($row['native_anex_id'] ?? null) ? trim((string)$row['