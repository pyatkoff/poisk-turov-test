<?php
declare(strict_types=1);
require_once __DIR__ . '/hotel_match_operator_fingerprint_room_evidence_v1.php';

const M44_OP = 'hotel-match-live44-room-current-1971-20260920-v2';
const M44_INPUT_SHA = '8811a26e6416c2b10d605b2871f4615f5e2e6de68e78d5f9cac4dedd4e2d14f2';

function m44_need(bool $ok, string $reason): void {
    if (!$ok) throw new RuntimeException($reason);
}
function m44_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}
function m44_save(string $path, array $v): string {
    $raw = m44_json($v);
    $f = fopen($path, 'xb');
    m44_need(is_resource($f), 'immutable_output');
    try {
        m44_need(fwrite($f, $raw) === strlen($raw) && fflush($f), 'output_write');
        if (function_exists('fsync')) m44_need(fsync($f), 'output_sync');
    } finally {
        fclose($f);
    }
    m44_need(file_get_contents($path) === $raw, 'output_readback');
    return hash('sha256', $raw);
}
function m44_rows(PDO $db, string $sql, array $params = []): array {
    $q = $db->prepare($sql);
    $q->execute($params);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    m44_need(count($rows) <= 20000, 'row_limit');
    return $rows;
}
function m44_pos_int(mixed $v): ?int {
    if (!is_scalar($v) || !preg_match('/^[1-9][0-9]*$/D', (string)$v)) return null;
    $n = (int)$v;
    return $n > 0 ? $n : null;
}
function m44_match_concepts(array $tvRows, array $samoRooms, int $contextSearchId): array {
    $tv = [];
    foreach ($tvRows as $row) {
        if (!is_array($row)) continue;
        $roomId = m44_pos_int($row['room_id'] ?? null);
        $raw = is_scalar($row['room_type'] ?? null) ? trim((string)$row['room_type']) : '';
        $key = hmf_room_key($raw);
        if ($roomId === null || $raw === '' || $key === '') continue;
        $tv[$key]['ids'][$roomId] = true;
        $tv[$key]['raw'][$raw] = true;
        if (isset($row['meal_id']) && $row['meal_id'] !== null) $tv[$key]['meals'][(string)$row['meal_id']] = true;
        if (isset($row['search_id']) && $row['search_id'] !== null) $tv[$key]['search_ids'][] = (string)$row['search_id'];
        if (isset($row['tour_id']) && is_scalar($row['tour_id']) && trim((string)$row['tour_id']) !== '') $tv[$key]['tour_ids'][trim((string)$row['tour_id'])] = true;
        if (isset($row['observed_at']) && is_scalar($row['observed_at'])) $tv[$key]['observed_at'][(string)$row['observed_at']] = true;
    }

    $sa = [];
    foreach ($samoRooms as $row) {
        if (!is_array($row)) continue;
        $roomId = m44_pos_int($row['room_key'] ?? null);
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
        foreach (($row['source_evidence'] ?? []) as $e) {
            if (!is_array($e)) continue;
            $file = is_scalar($e['file'] ?? null) ? trim((string)$e['file']) : '';
            $sha = is_scalar($e['sha256'] ?? null) ? trim((string)$e['sha256']) : '';
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
        $searchIds = array_values(array_unique(array_map('strval', $tv[$key]['search_ids'] ?? [])));
        sort($searchIds, SORT_STRING);
        $base = [
            'room_key' => $key,
            'tv_room_ids' => $tvIds,
            'samo_room_keys' => $saIds,
            'tv_rooms' => array_values(array_keys($tv[$key]['raw'] ?? [])),
            'samo_rooms' => array_values(array_keys($sa[$key]['raw'] ?? [])),
            'tv_meal_ids' => array_values(array_keys($tv[$key]['meals'] ?? [])),
            'samo_meal_variants' => array_values(array_keys($sa[$key]['meals'] ?? [])),
            'tv_search_ids' => $searchIds,
            'tv_tour_ids' => array_values(array_keys($tv[$key]['tour_ids'] ?? [])),
            'tv_observed_at' => array_values(array_keys($tv[$key]['observed_at'] ?? [])),
            'samo_sources' => array_values(array_keys($sa[$key]['sources'] ?? [])),
        ];
        foreach (['tv_rooms','samo_rooms','tv_meal_ids','samo_meal_variants','tv_tour_ids','tv_observed_at','samo_sources'] as $k) sort($base[$k], SORT_STRING);
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
        ['room_id'=>202,'room_type'=>'FAMILY SEA VIEW ROOM','meal_id'=>2,'search_id'=>78,'tour_id'=>'t2','observed_at'=>'2026-09-20 00:00:01'],
        ['room_id'=>101,'room_type'=>'DELUXE ROOM','meal_id'=>1,'search_id'=>77,'tour_id'=>'t1','observed_at'=>'2026-09-20 00:00:00'],
    ];
    $sa = [
        ['room_key'=>'501','room_raw'=>'Deluxe','meal_variants'=>[['meal_key'=>'6','meal_raw'=>'AI']],'source_evidence'=>[['file'=>'a.json','sha256'=>str_repeat('a',64)]]],
        ['room_key'=>'502','room_raw'=>'Family Sea View Room','meal_variants'=>[],'source_evidence'=>[]],
    ];
    $m = m44_match_concepts($tv,$sa,77);
    m44_need(count($m['concepts'])===2,'self_concept_count');
    $byKey=[]; foreach ($m['concepts'] as $c) $byKey[$c['room_key']]=$c;
    m44_need(isset($byKey['deluxe']) && $byKey['deluxe']['evidence_class']==='same_search_exact_room_key','self_deluxe');
    m44_need(isset($byKey['family sea view']),'self_qualifier');
    $tv[]=['room_id'=>303,'room_type'=>'DELUXE','meal_id'=>3,'search_id'=>79,'tour_id'=>'t3','observed_at'=>'2026-09-20 00:00:02'];
    $m2=m44_match_concepts($tv,$sa,77);
    m44_need(count($m2['concepts'])===1 && count($m2['holds'])===1 && $m2['holds'][0]['reason']==='ambiguous_tv_room_id','self_ambiguous');
    echo "MATCH_LIVE44_ROOM_SELFTEST_OK\n";
    exit(0);
}

m44_need(PHP_SAPI === 'cli' && (string)getenv('MATCH_OPERATION_ID') === M44_OP, 'operation_guard');
$dir = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations/' . M44_OP;
m44_need(realpath((string)getenv('MATCH_OPERATION_DIR')) === $dir, 'operation_directory');
$source = (string)getenv('MATCH_SOURCE_SHA');
m44_need(preg_match('/^[0-9a-f]{40}$/D', $source) === 1, 'source_sha');
m44_need(hash_file('sha256', $dir . '/input.json') === M44_INPUT_SHA, 'input_digest');
$input = json_decode((string)file_get_contents($dir . '/input.json'), true, 128, JSON_THROW_ON_ERROR);
m44_need(($input['schema'] ?? '') === 'match-live44-room-current-input-v1', 'input_schema');
m44_need(($input['input_count'] ?? 0) === 44 && count($input['rows'] ?? []) === 44, 'input_count');
m44_need(($input['samo_room_observation_count'] ?? 0) === 119 && ($input['samo_unique_room_count'] ?? 0) === 119, 'input_room_counts');
$reservation = json_decode((string)file_get_contents($dir . '/reservation.json'), true, 64, JSON_THROW_ON_ERROR);
m44_need(($reservation['operation_id'] ?? '') === M44_OP && ($reservation['source_sha'] ?? '') === $source && ($reservation['state'] ?? '') === 'reserved_before_db_read', 'reservation_guard');

$base = [
    'operation_id' => M44_OP,
    'source_sha' => $source,
    'input_sha256' => M44_INPUT_SHA,
    'supplier_calls' => 0,
    'provider_calls' => 0,
    'tourvisor_calls' => 0,
    'samo_calls' => 0,
    'andromeda_calls' => 0,
    'direct_anex_calls' => 0,
    'database_writes' => 0,
    'mapping_writes' => 0,
    'room_mapping_writes' => 0,
    'no_replay' => true,
];
$db = null;
try {
    m44_save($dir . '/execution-reservation.json', $base + ['state'=>'started_before_db_read']);
    $root = realpath(getcwd());
    m44_need(is_string($root) && basename($root) === 'anytoour.ru', 'root_guard');
    require_once (is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php');
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

    foreach (['tour_price_observations','catalog_hotels','andromeda_hotel_identities'] as $table) {
        $r = m44_rows($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
        m44_need(count($r) === 1 && strtoupper((string)$r[0]['ENGINE']) === 'INNODB', 'table_engine');
    }
    $cols = [];
    foreach (m44_rows($db, 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', ['tour_price_observations']) as $r) $cols[]=(string)$r['COLUMN_NAME'];
    foreach (['observed_at','source','search_id','hotel_id','tour_id','departure_date','nights','adults','children_count','child_ages_signature','meal_id','room_id','room_type','operator_id'] as $c) m44_need(in_array($c,$cols,true),'observation_column');

    $dossiers = [];
    $conceptCount = 0;
    $holdCount = 0;
    $sameSearch = 0;
    $sameContext = 0;
    $clustersWithTv = 0;
    $clustersWithConcept = 0;
    $tvObservationCount = 0;

    foreach ($input['rows'] as $row) {
        m44_need(is_array($row), 'input_row');
        $tvHotel = m44_pos_int($row['tv_hotel_id'] ?? null);
        $samoHotel = is_scalar($row['samo_hotel_id'] ?? null) ? trim((string)$row['samo_hotel_id']) : '';
        $nativeAnex = is_scalar($row['native_anex_id'] ?? null) ? trim((string)$row['native_anex_id']) : '';
        $ctx = $row['context'] ?? [];
        m44_need($tvHotel !== null && preg_match('/^[1-9][0-9]*$/D',$samoHotel) && preg_match('/^[1-9][0-9]*$/D',$nativeAnex), 'input_identity');
        m44_need(is_array($ctx) && preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',(string)($ctx['departure_date']??'')), 'input_context');
        $active = m44_rows($db, 'SELECT id,is_active FROM catalog_hotels WHERE id=?', [$tvHotel]);
        $samoAccepted = m44_rows($db, "SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id=? AND decision_status='accepted'", [$samoHotel,$tvHotel]);
        if (count($active)!==1 || (int)$active[0]['is_active']!==1 || count($samoAccepted)!==1) {
            $dossiers[]=['tv_hotel_id'=>$tvHotel,'samo_hotel_id'=>$samoHotel,'native_anex_id'=>$nativeAnex,'state'=>'current_hotel_cluster_hold','safe_to_write_now'=>false];
            continue;
        }
        $params = [$tvHotel,(string)$ctx['departure_date'],(int)$ctx['nights'],(int)$ctx['adults'],(int)$ctx['children_count'],(string)$ctx['child_ages_signature']];
        $tvRows = m44_rows($db, "SELECT observed_at,search_id,tour_id,hotel_id,departure_date,nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id
            FROM tour_price_observations
            WHERE source='user_search' AND operator_id=13 AND hotel_id=? AND departure_date=? AND nights=? AND adults=? AND children_count=? AND child_ages_signature=?
              AND room_id IS NOT NULL AND room_id>0 AND room_type IS NOT NULL AND room_type<>''
            ORDER BY observed_at DESC,id DESC", $params);
        $tvObservationCount += count($tvRows);
        if ($tvRows !== []) $clustersWithTv++;
        $matched = m44_match_concepts($tvRows, is_array($row['samo_rooms'] ?? null) ? $row['samo_rooms'] : [], (int)$ctx['search_id']);
        if ($matched['concepts'] !== []) $clustersWithConcept++;
        foreach ($matched['concepts'] as &$c) {
            $c['tv_hotel_id']=$tvHotel;
            $c['samo_hotel_id']=$samoHotel;
            $c['native_anex_id']=$nativeAnex;
            $c['hotel_name']=(string)($row['hotel_name']??'');
            $c['context']=$ctx;
            $c['provider_operator']='anex';
            $c['native_anex_anchor']='retained_samo_original_hotel_key_exact';
            $c['accepted_hotel_evidence_sha256']=(string)($row['accepted_evidence_sha256']??'');
            $conceptCount++;
            if ($c['evidence_class']==='same_search_exact_room_key') $sameSearch++; else $sameContext++;
        }
        unset($c);
        foreach ($matched['holds'] as &$h) {
            $h['tv_hotel_id']=$tvHotel;
            $h['samo_hotel_id']=$samoHotel;
            $h['native_anex_id']=$nativeAnex;
            $h['hotel_name']=(string)($row['hotel_name']??'');
            $h['context']=$ctx;
            $holdCount++;
        }
        unset($h);
        $dossiers[]=[
            'tv_hotel_id'=>$tvHotel,
            'samo_hotel_id'=>$samoHotel,
            'native_anex_id'=>$nativeAnex,
            'hotel_name'=>(string)($row['hotel_name']??''),
            'context'=>$ctx,
            'tv_anex_observation_count'=>count($tvRows),
            'samo_room_count'=>count($row['samo_rooms']??[]),
            'room_concepts'=>$matched['concepts'],
            'room_holds'=>$matched['holds'],
            'state'=>$matched['concepts']!==[]?'room_evidence_found':($tvRows===[]?'no_current_tv_anex_room_observation':'no_exact_room_key_overlap'),
            'safe_to_write_now'=>false,
        ];
    }
    $clock = m44_rows($db, 'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];
    $db->rollBack();
    $result = $base + [
        'state'=>'completed_read_only',
        'read_at_utc'=>$clock,
        'input_clusters'=>44,
        'input_samo_room_observations'=>119,
        'input_unique_samo_rooms'=>119,
        'clusters_with_current_tv_anex_room_observations'=>$clustersWithTv,
        'current_tv_anex_room_observations'=>$tvObservationCount,
        'clusters_with_room_concepts'=>$clustersWithConcept,
        'room_concept_count'=>$conceptCount,
        'room_hold_count'=>$holdCount,
        'same_search_concepts'=>$sameSearch,
        'same_context_concepts'=>$sameContext,
        'dossiers'=>$dossiers,
    ];
} catch (Throwable $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    $reason = preg_match('/^[a-z0-9_]+$/D', $e->getMessage()) ? $e->getMessage() : 'database_or_runtime_error';
    $result = $base + ['state'=>'failed_no_replay','reason'=>$reason,'error_class'=>get_class($e)];
}
$hash = m44_save($dir . '/result.json', $result);
m44_save($dir . '/receipt.json', [
    'operation_id'=>M44_OP,
    'source_sha'=>$source,
    'state'=>$result['state'],
    'result_sha256'=>$hash,
    'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,
    'supplier_calls'=>0,
    'provider_calls'=>0,
    'database_writes'=>0,
    'mapping_writes'=>0,
    'room_mapping_writes'=>0,
    'no_replay'=>true,
]);
echo m44_json([
    'state'=>$result['state'],
    'clusters_with_current_tv_anex_room_observations'=>$result['clusters_with_current_tv_anex_room_observations']??null,
    'room_concept_count'=>$result['room_concept_count']??null,
    'room_hold_count'=>$result['room_hold_count']??null,
    'same_search_concepts'=>$result['same_search_concepts']??null,
    'same_context_concepts'=>$result['same_context_concepts']??null,
    'result_sha256'=>$hash,
]);
if ($result['state'] !== 'completed_read_only') exit(2);
