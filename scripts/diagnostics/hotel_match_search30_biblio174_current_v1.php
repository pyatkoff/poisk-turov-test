<?php
declare(strict_types=1);

const B174_OP = 'hotel-match-search30-biblio174-current-1971-20260921-v1';
const B174_INPUT_SHA = '4e798d1614f3eb90bc26b387944b23470d0800378b41d93bbb52f59a11899806';

function b174_req(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function b174_write(string $path, array $value): string {
    $raw = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $f = @fopen($path, 'x+b');
    b174_req(is_resource($f), 'exclusive_output');
    b174_req(fwrite($f, $raw) === strlen($raw), 'short_write');
    fflush($f); if (function_exists('fsync')) fsync($f); fclose($f);
    return hash('sha256', $raw);
}
function b174_safe(Throwable $e): string {
    $m = $e->getMessage();
    return is_string($m) && preg_match('/^[A-Za-z0-9_.:-]{1,96}$/D', $m) ? $m : 'sanitized_error';
}
function b174_selftest(): void {
    b174_req(strlen(B174_INPUT_SHA) === 64, 'sha');
    foreach (['0001', '1', '123456'] as $v) {
        b174_req(preg_match('/^[0-9]{1,32}$/D', $v) === 1 && preg_match('/[1-9]/', $v) === 1, 'raw_numeric');
    }
    echo "hotel-match-search30-biblio174-current-v1: PASS\n";
}

function b174_main(): void {
    b174_req(PHP_SAPI === 'cli' && (string)getenv('MATCH_OPERATION_ID') === B174_OP, 'operation_guard');
    $source = (string)getenv('MATCH_SOURCE_SHA');
    b174_req(preg_match('/^[0-9a-f]{40}$/D', $source) === 1, 'source_sha');
    $dir = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations/' . B174_OP;
    $rp = realpath($dir . '/reservation.json');
    $ip = realpath($dir . '/input.json');
    b174_req(is_string($rp) && is_string($ip) && hash_file('sha256', $ip) === B174_INPUT_SHA, 'input_guard');
    $reservation = json_decode((string)file_get_contents($rp), true, 32, JSON_THROW_ON_ERROR);
    b174_req(($reservation['operation_id'] ?? '') === B174_OP && ($reservation['source_sha'] ?? '') === $source && ($reservation['state'] ?? '') === 'reserved_before_db_access', 'reservation');
    $input = json_decode((string)file_get_contents($ip), true, 256, JSON_THROW_ON_ERROR);
    b174_req(($input['edge_count'] ?? 0) === 174 && ($input['unique_tv_hotels'] ?? 0) === 174 && count($input['edges'] ?? []) === 174, 'input_shape');
    b174_req(($input['source_pr'] ?? 0) === 3269 && ($input['source_run'] ?? 0) === 35541972495 && ($input['source_artifact_id'] ?? 0) === 10615368679, 'source_binding');
    b174_req(($input['source_result_sha256'] ?? '') === '76c740c4efbb95a2c2c44fd7fe69ecea30b5091c0e17dfec2bb4cf30da75cea2', 'result_binding');
    foreach ($input['edges'] as $edge) {
        b174_req((int)$edge['tv_hotel_id'] > 0 && (string)$edge['tour_id'] !== '' && preg_match('/^[0-9a-f]{64}$/D', (string)$edge['link_sha256']) === 1, 'edge_identity');
        b174_req(($edge['host'] ?? '') === 'www.bgoperator.ru' && ($edge['path'] ?? '') === '/price.shtml', 'edge_contract');
        foreach ($edge['numeric_query'] as $field => $list) {
            b174_req(is_string($field) && preg_match('/^[A-Za-z0-9_]{1,32}$/D', $field) === 1 && is_array($list), 'field_shape');
            foreach ($list as $v) b174_req(is_string($v) && preg_match('/^[0-9]{1,32}$/D', $v) === 1 && preg_match('/[1-9]/', $v) === 1, 'raw_numeric_shape');
        }
    }

    $root = realpath(getcwd());
    b174_req(is_string($root) && basename($root) === 'anytoour.ru', 'root');
    require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $out = [
        'operation_id' => B174_OP, 'source_sha' => $source, 'state' => 'failed_no_replay', 'reason' => null,
        'database_writes' => 0, 'mapping_writes' => 0, 'supplier_calls' => 0,
        'tourvisor_calls' => 0, 'samo_calls' => 0, 'direct_anex_calls' => 0, 'no_replay' => true,
    ];

    try {
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        foreach (['andromeda_hotel_identities', 'catalog_hotels'] as $table) {
            $q = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $q->execute([$table]);
            b174_req(strtoupper((string)$q->fetchColumn()) === 'INNODB', 'table_contract_' . $table);
        }

        $values = []; $fields = []; $tokenTv = [];
        foreach ($input['edges'] as $edge) {
            $tv = (int)$edge['tv_hotel_id'];
            foreach ($edge['numeric_query'] as $field => $list) {
                $fields[$field] = true;
                foreach ($list as $v) { $values[$v] = true; $tokenTv[$field][$v][$tv] = true; }
            }
        }
        $byExternal = []; $ids = array_keys($values);
        foreach (array_chunk($ids, 250) as $chunk) {
            $sql = "SELECT i.external_hotel_id,i.local_hotel_id,i.decision_status,i.evidence_json,h.name AS local_name,h.country_id,h.is_active FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='operator_115' AND i.external_hotel_id IN (" . implode(',', array_fill(0, count($chunk), '?')) . ") ORDER BY i.external_hotel_id,i.local_hotel_id";
            $q = $db->prepare($sql); $q->execute($chunk);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $k = (string)$row['external_hotel_id'];
                $byExternal[$k][] = [
                    'external_hotel_id' => $k, 'local_hotel_id' => (int)$row['local_hotel_id'],
                    'decision_status' => (string)$row['decision_status'],
                    'local_name' => $row['local_name'] === null ? null : (string)$row['local_name'],
                    'country_id' => $row['country_id'] === null ? null : (int)$row['country_id'],
                    'is_active' => $row['is_active'] === null ? null : (int)$row['is_active'],
                    'evidence_sha256' => hash('sha256', (string)($row['evidence_json'] ?? '')),
                ];
            }
        }

        $fieldStats = [];
        foreach (array_keys($fields) as $f) {
            $shared = 0; $maxMultiplicity = 0;
            foreach (($tokenTv[$f] ?? []) as $targets) { $n = count($targets); if ($n > 1) $shared++; if ($n > $maxMultiplicity) $maxMultiplicity = $n; }
            $fieldStats[$f] = [
                'links_with_field' => 0, 'values' => 0, 'unique_values' => count($tokenTv[$f] ?? []),
                'raw_values_shared_across_tv_hotels' => $shared, 'max_tv_hotel_multiplicity' => $maxMultiplicity,
                'matched_provider_rows' => 0, 'edges_any_match' => 0, 'edges_same_tv_target' => 0,
                'edges_other_target_only' => 0, 'edges_mixed_targets' => 0,
                'accepted_same_tv_target' => 0, 'pending_same_tv_target' => 0, 'conflict_same_tv_target' => 0,
            ];
        }

        $dossiers = [];
        foreach ($input['edges'] as $edge) {
            $per = []; $tv = (int)$edge['tv_hotel_id'];
            foreach ($edge['numeric_query'] as $field => $list) {
                $fieldStats[$field]['links_with_field']++; $fieldStats[$field]['values'] += count($list);
                $rows = [];
                foreach ($list as $v) foreach ($byExternal[$v] ?? [] as $row) $rows[] = $row;
                $same = array_values(array_filter($rows, fn($x) => (int)$x['local_hotel_id'] === $tv));
                $other = array_values(array_filter($rows, fn($x) => (int)$x['local_hotel_id'] !== $tv));
                $fieldStats[$field]['matched_provider_rows'] += count($rows);
                if ($rows) $fieldStats[$field]['edges_any_match']++;
                if ($same) {
                    $fieldStats[$field]['edges_same_tv_target']++;
                    foreach ($same as $x) { $k = $x['decision_status'] . '_same_tv_target'; if (isset($fieldStats[$field][$k])) $fieldStats[$field][$k]++; }
                }
                if ($other && !$same) $fieldStats[$field]['edges_other_target_only']++;
                if ($other && $same) $fieldStats[$field]['edges_mixed_targets']++;
                $per[$field] = ['values' => $list, 'matched_rows' => $rows, 'same_tv_target_count' => count($same), 'other_target_count' => count($other)];
            }
            $dossiers[] = [
                'tv_hotel_id' => $tv, 'tour_id' => (string)$edge['tour_id'], 'link_sha256' => (string)$edge['link_sha256'],
                'field_evidence' => $per, 'safe_to_write_now' => false,
            ];
        }
        ksort($fieldStats);
        $db->commit();
        $out['state'] = 'completed_read_only'; $out['read_at_utc'] = gmdate('c');
        $out['operator_namespace'] = 'operator_115'; $out['input_edges'] = 174;
        $out['unique_candidate_values'] = count($values); $out['field_stats'] = $fieldStats; $out['dossiers'] = $dossiers;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $out['reason'] = b174_safe($e);
    }

    $hash = b174_write($dir . '/result.json', $out);
    b174_write($dir . '/receipt.json', [
        'operation_id' => B174_OP, 'source_sha' => $source, 'state' => $out['state'], 'reason' => $out['reason'],
        'result_sha256' => $hash, 'readback_verified' => hash_file('sha256', $dir . '/result.json') === $hash,
        'database_writes' => 0, 'mapping_writes' => 0, 'supplier_calls' => 0,
        'tourvisor_calls' => 0, 'samo_calls' => 0, 'direct_anex_calls' => 0, 'no_replay' => true,
    ]);
    echo json_encode(['state' => $out['state'], 'reason' => $out['reason'], 'field_stats' => $out['field_stats'] ?? null, 'result_sha256' => $hash], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit($out['state'] === 'completed_read_only' ? 0 : 2);
}

if (($argv[1] ?? '') === '--self-test') { b174_selftest(); exit(0); }
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) b174_main();
