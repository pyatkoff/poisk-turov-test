<?php
declare(strict_types=1);

putenv('MATCH_STATE_CONSENSUS_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_state_country_consensus.php';

const MSAC_OP = 'hotel-match-state-accepted-country-consensus-1971-20260915-v1';
const MSAC_MIN_ACCEPTED_ANCHORS = 8;

function msac_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}
function msac_query(PDO $db, string $sql, array $args = []): array {
    $q = $db->prepare($sql);
    $q->execute($args);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function msac_write_exclusive(string $file, array $value): string {
    $raw = msac_json($value);
    $f = @fopen($file, 'x+b');
    if (!$f) throw new RuntimeException('exclusive_file');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('write');
        if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('sync');
        rewind($f);
        if (stream_get_contents($f) !== $raw) throw new RuntimeException('readback');
    } finally {
        fclose($f);
    }
    return hash('sha256', $raw);
}
function msac_state_explicit_observations(array $rows, array $aliases): array {
    $out = [];
    foreach ($rows as $r) {
        $e = mcr_evidence((string)($r['evidence_json'] ?? ''));
        $sk = mcr_state_key($e);
        if ($sk === null) continue;
        $ids = msc_explicit_countries(mcr_names($e), $aliases);
        if (count($ids) > 1) {
            $out[$sk]['multi_country_rows'] = (int)($out[$sk]['multi_country_rows'] ?? 0) + 1;
            continue;
        }
        if (count($ids) === 1) {
            $cid = (int)$ids[0];
            $out[$sk]['country_counts'][$cid] = (int)($out[$sk]['country_counts'][$cid] ?? 0) + 1;
        }
    }
    return $out;
}
function msac_accepted_country_consensus(array $accepted, array $allRows, array $coreIds): array {
    $aliases = msc_country_aliases($coreIds);
    $explicit = msac_state_explicit_observations($allRows, $aliases);
    $counts = [];
    $examples = [];
    foreach ($accepted as $r) {
        $e = mcr_evidence((string)($r['evidence_json'] ?? ''));
        $sk = mcr_state_key($e);
        $cid = (int)($r['local_country_id'] ?? 0);
        if ($sk === null || $cid <= 0) continue;
        $counts[$sk][$cid] = (int)($counts[$sk][$cid] ?? 0) + 1;
        if (count($examples[$sk][$cid] ?? []) < 8) {
            $examples[$sk][$cid][] = [
                'external_hotel_id' => (string)($r['external_hotel_id'] ?? ''),
                'local_hotel_id' => (int)($r['local_hotel_id'] ?? 0),
            ];
        }
    }
    $inferred = [];
    $rejected = [];
    foreach ($counts as $sk => $byCountry) {
        arsort($byCountry, SORT_NUMERIC);
        $winnerCid = (int)array_key_first($byCountry);
        $winner = (int)$byCountry[$winnerCid];
        $total = array_sum($byCountry);
        $otherAccepted = $total - $winner;
        $exp = $explicit[$sk] ?? [];
        $multi = (int)($exp['multi_country_rows'] ?? 0);
        $explicitCounts = is_array($exp['country_counts'] ?? null) ? $exp['country_counts'] : [];
        $explicitOther = 0;
        foreach ($explicitCounts as $cid => $n) if ((int)$cid !== $winnerCid) $explicitOther += (int)$n;
        $base = [
            'country_id' => $winnerCid,
            'country_name' => $coreIds[$winnerCid] ?? null,
            'accepted_anchor_count' => $winner,
            'accepted_total_for_state' => $total,
            'accepted_other_country_count' => $otherAccepted,
            'explicit_country_counts' => $explicitCounts,
            'explicit_other_country_count' => $explicitOther,
            'explicit_multi_country_rows' => $multi,
            'method' => 'current_accepted_andromeda_local_country_consensus',
            'examples' => $examples[$sk][$winnerCid] ?? [],
        ];
        if (!isset($coreIds[$winnerCid])) {
            $rejected[(string)$sk] = $base + ['reason' => 'winner_not_core8'];
            continue;
        }
        if ($winner < MSAC_MIN_ACCEPTED_ANCHORS) {
            $rejected[(string)$sk] = $base + ['reason' => 'insufficient_accepted_anchors'];
            continue;
        }
        if ($otherAccepted !== 0) {
            $rejected[(string)$sk] = $base + ['reason' => 'accepted_cross_country_split'];
            continue;
        }
        if ($multi !== 0 || $explicitOther !== 0) {
            $rejected[(string)$sk] = $base + ['reason' => 'explicit_country_contradiction'];
            continue;
        }
        $inferred[(string)$sk] = $base;
    }
    ksort($inferred, SORT_NATURAL);
    ksort($rejected, SORT_NATURAL);
    return ['inferred' => $inferred, 'rejected' => $rejected];
}

if (getenv('MATCH_STATE_ACCEPTED_COUNTRY_TEST_LIBRARY') === '1') return;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$op = (string)getenv('MATCH_OPERATION_ID');
$sha = (string)getenv('MATCH_SOURCE_SHA');
if ($op !== MSAC_OP || !preg_match('/^[0-9a-f]{40}$/D', $sha)) throw new RuntimeException('operation_or_source_guard');
$home = (string)getenv('HOME');
if ($home === '') throw new RuntimeException('home');
$dir = $home . '/.anytoour-match/operations/' . MSAC_OP;
$res = mcr_evidence((string)@file_get_contents($dir . '/reservation.json'));
if (($res['operation_id'] ?? '') !== MSAC_OP || ($res['source_sha'] ?? '') !== $sha || ($res['state'] ?? '') !== 'reserved_before_db_access') throw new RuntimeException('reservation');
$root = realpath(getcwd());
if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('root');
require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
$db = v2_data_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('START TRANSACTION READ ONLY');
try {
    $coreIds = [];
    foreach (msac_query($db, 'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c) {
        if (mcr_is_core8_name((string)$c['name'])) $coreIds[(int)$c['id']] = (string)$c['name'];
    }
    if (count($coreIds) < 6) throw new RuntimeException('core8');
    $marks = implode(',', array_fill(0, count($coreIds), '?'));

    $hotels = [];
    $forms = [];
    foreach (msac_query($db, "SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY country_id,id", array_keys($coreIds)) as $h) {
        $id = (int)$h['id'];
        $hotels[$id] = $h;
        $forms[$id] = [(string)$h['name']];
    }
    foreach (msac_query($db, "SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($marks) ORDER BY a.hotel_id,a.id", array_keys($coreIds)) as $a) {
        $id = (int)$a['hotel_id'];
        if (isset($forms[$id])) $forms[$id][] = (string)$a['alias'];
    }
    $exact = [];
    $tokenIndex = [];
    foreach ($forms as $id => $list) {
        $cid = (int)$hotels[$id]['country_id'];
        foreach (array_unique($list) as $raw) {
            $n = mcr_norm((string)$raw);
            if ($n === '') continue;
            $exact[$cid][$n][] = $id;
            foreach (mcr_tokens((string)$raw) as $t) $tokenIndex[$cid][$t][$id] = true;
        }
    }
    $geoExact = mcr_geo_exact_index($hotels, $forms);
    $aliases = msc_country_aliases($coreIds);
    [$countrylessExact, $countrylessTokens] = msc_countryless_indexes($hotels, $forms, $aliases);

    $allRows = msac_query($db, "SELECT external_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");
    $accepted = msac_query($db, "SELECT i.external_hotel_id,i.local_hotel_id,i.evidence_json,h.country_id AS local_country_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id AND h.is_active=1 WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL ORDER BY i.external_hotel_id");
    $consensus = msac_accepted_country_consensus($accepted, $allRows, $coreIds);
    $inferred = $consensus['inferred'];

    $pending = msac_query($db, "SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
    $routes = [];
    $candidates = [];
    $reasons = [];
    $statePending = [];
    foreach ($pending as $r) {
        $e = mcr_evidence((string)$r['evidence_json']);
        $sk = mcr_state_key($e);
        if ($sk === null || !isset($inferred[$sk])) continue;
        $statePending[$sk] = (int)($statePending[$sk] ?? 0) + 1;
        $cid = (int)$inferred[$sk]['country_id'];
        $names = mcr_names($e);
        $points = mcr_points($e);
        $explicit = msc_explicit_countries($names, $aliases);
        if ($explicit && $explicit !== [$cid]) {
            $sel = ['route' => 'hard_conflict', 'reason' => 'row_explicit_country_conflict', 'explicit_country_ids' => $explicit];
        } else {
            $base = mcr_select_candidate($names, $cid, $points, $hotels, $forms, $exact, $tokenIndex, $geoExact);
            if (($base['route'] ?? '') === 'needs_extra_evidence') {
                $countryless = msc_countryless_select($names, $cid, $points, $hotels, $forms, $aliases, $countrylessExact, $countrylessTokens);
                $sel = (($countryless['route'] ?? '') === 'needs_extra_evidence') ? $base : $countryless;
            } else {
                $sel = $base;
            }
        }
        $item = array_merge([
            'supplier_namespace' => 'andromeda_catalog',
            'external_hotel_id' => (string)$r['external_hotel_id'],
            'evidence_sha256' => (string)$r['evidence_sha256'],
            'state_key' => $sk,
            'inferred_country_id' => $cid,
            'inference' => $inferred[$sk],
            'frequency' => mcr_frequency($e),
        ], $sel);
        $routes[$item['route']][] = $item;
        $reasons[$item['reason']] = (int)($reasons[$item['reason']] ?? 0) + 1;
        if ($item['route'] === 'auto_accept_candidate') $candidates[] = $item;
    }
    foreach ($routes as &$list) {
        usort($list, fn($a, $b) => (($b['frequency'] ?? 0) <=> ($a['frequency'] ?? 0)) ?: strcmp((string)$a['external_hotel_id'], (string)$b['external_hotel_id']));
    }
    unset($list);
    usort($candidates, fn($a, $b) => (($b['frequency'] ?? 0) <=> ($a['frequency'] ?? 0)) ?: strcmp((string)$a['external_hotel_id'], (string)$b['external_hotel_id']));
    ksort($routes);
    ksort($reasons);
    ksort($statePending, SORT_NATURAL);

    $result = [
        'schema' => 'hotel-match-state-accepted-country-consensus/1',
        'operation_id' => MSAC_OP,
        'source_sha' => $sha,
        'state' => 'completed_read_only',
        'server_current' => true,
        'transaction' => 'REPEATABLE READ READ ONLY',
        'min_accepted_anchors' => MSAC_MIN_ACCEPTED_ANCHORS,
        'core8_countries' => $coreIds,
        'accepted_andromeda_rows' => count($accepted),
        'pending_andromeda_rows' => count($pending),
        'inferred_states' => $inferred,
        'rejected_states' => $consensus['rejected'],
        'pending_rows_in_inferred_states' => array_sum($statePending),
        'state_pending_counts' => $statePending,
        'candidate_count' => count($candidates),
        'candidates' => $candidates,
        'route_counts' => array_map('count', $routes),
        'reason_counts' => $reasons,
        'routes' => $routes,
        'supplier_calls' => 0,
        'tourvisor_calls' => 0,
        'external_calls' => 0,
        'booking_calls' => 0,
        'db_writes' => 0,
        'mapping_writes' => 0,
        'operator_5_writes' => 0,
        'no_replay' => true,
        'created_at' => gmdate('c'),
    ];
    $hash = msac_write_exclusive($dir . '/result.json', $result);
    $raw = (string)file_get_contents($dir . '/result.json');
    $decoded = mcr_evidence($raw);
    $ok = hash('sha256', $raw) === $hash && ($decoded['operation_id'] ?? '') === MSAC_OP && ($decoded['source_sha'] ?? '') === $sha && ($decoded['state'] ?? '') === 'completed_read_only' && (int)($decoded['db_writes'] ?? -1) === 0;
    if (!$ok) throw new RuntimeException('result_readback');
    $receipt = [
        'operation_id' => MSAC_OP,
        'source_sha' => $sha,
        'state' => 'completed_read_only',
        'result_sha256' => $hash,
        'readback_verified' => true,
        'no_replay' => true,
        'created_at' => gmdate('c'),
    ];
    msac_write_exclusive($dir . '/receipt.json', $receipt);
    $db->rollBack();
    echo 'MATCH_STATE_ACCEPTED_COUNTRY_REVIEW_OK ' . count($candidates) . "\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
