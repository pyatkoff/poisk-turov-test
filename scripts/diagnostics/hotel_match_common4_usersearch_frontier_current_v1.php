<?php
declare(strict_types=1);

/**
 * CURRENT COMMON4 user_search missing-edge frontier. READ ONLY.
 * No supplier/Tourvisor/SAMO access and no DB/mapping writes.
 */
const C4UF_OP = 'hotel-match-common4-usersearch-frontier-current-1971-20260919-v1';
const C4UF_MAX_OBSERVATIONS = 250000;
const C4UF_MAX_EDGES = 20000;
const C4UF_ROUTES = [
    13 => ['canonical'=>'anex','supplier_namespace'=>'operator_5'],
    25 => ['canonical'=>'funsun','supplier_namespace'=>'operator_315'],
    18 => ['canonical'=>'biblio_globus','supplier_namespace'=>'operator_115'],
    43 => ['canonical'=>'intourist','supplier_namespace'=>'operator_342'],
];

function c4uf_req(bool $ok, string $reason): void
{
    if (!$ok) throw new RuntimeException($reason);
}

function c4uf_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . "\n";
}

function c4uf_write_new(string $path, array $value): string
{
    $raw = c4uf_json($value);
    $fh = @fopen($path, 'x+b');
    c4uf_req(is_resource($fh), 'exclusive_output');
    try {
        c4uf_req(fwrite($fh, $raw) === strlen($raw) && fflush($fh), 'output_write');
        if (function_exists('fsync')) c4uf_req(fsync($fh), 'output_sync');
        rewind($fh);
        c4uf_req(stream_get_contents($fh) === $raw, 'output_readback');
    } finally {
        fclose($fh);
    }
    return hash('sha256', $raw);
}

function c4uf_read_json(string $path): array
{
    c4uf_req(!is_link($path) && realpath($path) === $path && is_file($path), 'input_path');
    $raw = file_get_contents($path);
    c4uf_req(is_string($raw) && strlen($raw) <= 1024*1024, 'input_read');
    $value = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    c4uf_req(is_array($value), 'input_shape');
    return $value;
}

function c4uf_q(PDO $db, string $sql, array $args=[]): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($args));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    c4uf_req(count($rows) <= C4UF_MAX_OBSERVATIONS, 'row_budget');
    return $rows;
}

function c4uf_opaque(string $name): bool
{
    return preg_match('/(?:^|[^\p{L}])(?:FORTUNA|ROULETTE|ФОРТУНА|РУЛЕТКА)(?:$|[^\p{L}])/iu', $name) === 1;
}

function c4uf_edge_key(int $operatorId, int $hotelId): string
{
    return $operatorId . ':' . $hotelId;
}

function c4uf_context_key(array $edge): string
{
    return implode('|', [
        $edge['operator_id'], $edge['departure_id'], $edge['country_id'],
        $edge['departure_date'], $edge['nights'], $edge['adults'],
        $edge['children_count'], $edge['child_ages_signature'],
    ]);
}

function c4uf_main(): void
{
    c4uf_req(PHP_SAPI === 'cli', 'cli_only');
    c4uf_req((string)getenv('MATCH_OPERATION_ID') === C4UF_OP, 'operation_guard');
    $sourceSha = (string)getenv('MATCH_SOURCE_SHA');
    c4uf_req(preg_match('/^[0-9a-f]{40}$/D', $sourceSha) === 1, 'source_sha');
    $opDir = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations/' . C4UF_OP;
    $reservation = c4uf_read_json($opDir . '/reservation.json');
    c4uf_req(($reservation['operation_id'] ?? '') === C4UF_OP, 'reservation_operation');
    c4uf_req(($reservation['source_sha'] ?? '') === $sourceSha, 'reservation_source');
    c4uf_req(($reservation['state'] ?? '') === 'reserved_before_db_access', 'reservation_state');

    $root = realpath(getcwd());
    c4uf_req(is_string($root) && basename($root) === 'anytoour.ru', 'root_guard');
    require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $result = [
        'operation_id'=>C4UF_OP,
        'source_sha'=>$sourceSha,
        'state'=>'failed_no_replay',
        'no_replay'=>true,
        'database_writes'=>0,
        'mapping_writes'=>0,
        'supplier_calls'=>0,
        'tourvisor_calls'=>0,
        'samo_calls'=>0,
    ];

    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    try {
        $required = ['tour_price_observations','andromeda_hotel_identities','catalog_hotels'];
        $engines = c4uf_q($db,
            'SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?)',
            $required
        );
        $engines = array_column($engines, 'ENGINE', 'TABLE_NAME');
        foreach ($required as $table) c4uf_req(strtoupper((string)($engines[$table] ?? '')) === 'INNODB', 'table_contract_' . $table);

        $countRow = c4uf_q($db, "SELECT COUNT(*) AS c FROM tour_price_observations WHERE source='user_search' AND operator_id IN (13,18,25,43)");
        $rawCount = (int)($countRow[0]['c'] ?? 0);
        c4uf_req($rawCount <= C4UF_MAX_OBSERVATIONS, 'observation_budget_exceeded');

        $observations = c4uf_q($db, "SELECT
                p.hotel_id,p.operator_id,p.country_id,p.region_id,p.subregion_id,p.departure_id,
                p.departure_date,p.nights,p.adults,p.children_count,p.child_ages_signature,
                p.search_id,p.tour_id,p.observed_at,h.name AS hotel_name,h.is_active
            FROM tour_price_observations p
            JOIN catalog_hotels h ON h.id=p.hotel_id
            WHERE p.source='user_search' AND p.operator_id IN (13,18,25,43)
            ORDER BY p.observed_at DESC,p.hotel_id,p.operator_id");

        $acceptedRows = c4uf_q($db, "SELECT supplier_namespace,local_hotel_id,external_hotel_id,decision_status
            FROM andromeda_hotel_identities
            WHERE supplier_namespace IN ('operator_5','operator_115','operator_315','operator_342')
              AND decision_status='accepted' AND local_hotel_id IS NOT NULL");
        $accepted = [];
        foreach ($acceptedRows as $row) {
            $ns = (string)$row['supplier_namespace'];
            $local = (int)$row['local_hotel_id'];
            if ($local <= 0) continue;
            $accepted[$ns . ':' . $local][] = (string)$row['external_hotel_id'];
        }

        $today = gmdate('Y-m-d');
        $edges = [];
        $opaque = [];
        foreach ($observations as $row) {
            $operatorId = (int)$row['operator_id'];
            $hotelId = (int)$row['hotel_id'];
            if (!isset(C4UF_ROUTES[$operatorId]) || $hotelId <= 0) continue;
            $name = trim((string)$row['hotel_name']);
            $key = c4uf_edge_key($operatorId, $hotelId);
            if (c4uf_opaque($name)) {
                $opaque[$key] = ['operator_id'=>$operatorId,'hotel_id'=>$hotelId,'hotel_name'=>$name];
                continue;
            }
            if (!isset($edges[$key])) {
                $route = C4UF_ROUTES[$operatorId];
                $date = (string)$row['departure_date'];
                $edges[$key] = [
                    'tv_hotel_id'=>$hotelId,
                    'hotel_name'=>$name,
                    'is_active'=>(int)$row['is_active'] === 1,
                    'operator_id'=>$operatorId,
                    'operator'=>$route['canonical'],
                    'target_supplier_namespace'=>$route['supplier_namespace'],
                    'country_id'=>(int)$row['country_id'],
                    'region_id'=>$row['region_id'] === null ? null : (int)$row['region_id'],
                    'subregion_id'=>$row['subregion_id'] === null ? null : (int)$row['subregion_id'],
                    'departure_id'=>(int)$row['departure_id'],
                    'departure_date'=>$date,
                    'nights'=>(int)$row['nights'],
                    'adults'=>(int)$row['adults'],
                    'children_count'=>(int)$row['children_count'],
                    'child_ages_signature'=>(string)$row['child_ages_signature'],
                    'search_id'=>(int)$row['search_id'],
                    'tour_id'=>$row['tour_id'] === null ? null : (string)$row['tour_id'],
                    'latest_observed_at'=>(string)$row['observed_at'],
                    'observation_rows'=>0,
                    'latest_future_context'=>null,
                ];
            }
            $edges[$key]['observation_rows']++;
            $date = (string)$row['departure_date'];
            if ($date >= $today && $edges[$key]['latest_future_context'] === null) {
                $edges[$key]['latest_future_context'] = [
                    'departure_id'=>(int)$row['departure_id'],
                    'country_id'=>(int)$row['country_id'],
                    'region_id'=>$row['region_id'] === null ? null : (int)$row['region_id'],
                    'subregion_id'=>$row['subregion_id'] === null ? null : (int)$row['subregion_id'],
                    'departure_date'=>$date,
                    'nights'=>(int)$row['nights'],
                    'adults'=>(int)$row['adults'],
                    'children_count'=>(int)$row['children_count'],
                    'child_ages_signature'=>(string)$row['child_ages_signature'],
                    'search_id'=>(int)$row['search_id'],
                    'tour_id'=>$row['tour_id'] === null ? null : (string)$row['tour_id'],
                    'observed_at'=>(string)$row['observed_at'],
                ];
            }
        }
        c4uf_req(count($edges) <= C4UF_MAX_EDGES, 'edge_budget_exceeded');

        $missing = [];
        $resolved = [];
        $byOperator = [];
        foreach ($edges as $edge) {
            $nsKey = $edge['target_supplier_namespace'] . ':' . $edge['tv_hotel_id'];
            $operator = $edge['operator'];
            if (!isset($byOperator[$operator])) $byOperator[$operator] = ['observed_edges'=>0,'accepted_same_provider'=>0,'missing_edges'=>0,'actionable_future'=>0,'observed_only'=>0];
            $byOperator[$operator]['observed_edges']++;
            if (isset($accepted[$nsKey]) && count($accepted[$nsKey]) > 0) {
                $edge['accepted_external_hotel_ids'] = array_values(array_unique($accepted[$nsKey]));
                $resolved[] = $edge;
                $byOperator[$operator]['accepted_same_provider']++;
            } else {
                $edge['actionable_future'] = $edge['is_active'] && is_array($edge['latest_future_context']);
                $missing[] = $edge;
                $byOperator[$operator]['missing_edges']++;
                if ($edge['actionable_future']) $byOperator[$operator]['actionable_future']++;
                else $byOperator[$operator]['observed_only']++;
            }
        }

        usort($missing, static function(array $a,array $b): int {
            $aa = !empty($a['actionable_future']) ? 1 : 0;
            $bb = !empty($b['actionable_future']) ? 1 : 0;
            if ($aa !== $bb) return $bb <=> $aa;
            $c = strcmp((string)$b['latest_observed_at'], (string)$a['latest_observed_at']);
            if ($c !== 0) return $c;
            $c = ((int)$b['observation_rows']) <=> ((int)$a['observation_rows']);
            if ($c !== 0) return $c;
            return ((int)$a['tv_hotel_id']) <=> ((int)$b['tv_hotel_id']);
        });

        $contexts = [];
        foreach ($missing as $edge) {
            if (empty($edge['actionable_future']) || !is_array($edge['latest_future_context'])) continue;
            $ctxEdge = $edge;
            foreach ($edge['latest_future_context'] as $k=>$v) $ctxEdge[$k] = $v;
            $ctxKey = c4uf_context_key($ctxEdge);
            if (!isset($contexts[$ctxKey])) {
                $contexts[$ctxKey] = [
                    'operator'=>$edge['operator'],
                    'operator_id'=>$edge['operator_id'],
                    'target_supplier_namespace'=>$edge['target_supplier_namespace'],
                    'departure_id'=>$ctxEdge['departure_id'],
                    'country_id'=>$ctxEdge['country_id'],
                    'departure_date'=>$ctxEdge['departure_date'],
                    'nights'=>$ctxEdge['nights'],
                    'adults'=>$ctxEdge['adults'],
                    'children_count'=>$ctxEdge['children_count'],
                    'child_ages_signature'=>$ctxEdge['child_ages_signature'],
                    'hotel_ids'=>[],
                ];
            }
            $contexts[$ctxKey]['hotel_ids'][] = $edge['tv_hotel_id'];
        }
        foreach ($contexts as &$context) {
            $context['hotel_ids'] = array_values(array_unique(array_map('intval', $context['hotel_ids'])));
            sort($context['hotel_ids'], SORT_NUMERIC);
            $context['hotel_count'] = count($context['hotel_ids']);
            $context['identity_batches_up_to_30'] = (int)ceil($context['hotel_count']/30);
        }
        unset($context);
        $contexts = array_values($contexts);
        usort($contexts, static fn(array $a,array $b): int => ($b['hotel_count'] <=> $a['hotel_count']) ?: strcmp($a['operator'], $b['operator']));

        $db->commit();
        $result += [
            'state'=>'completed_read_only',
            'read_at_utc'=>gmdate('c'),
            'today_utc'=>$today,
            'current_tv_to_samo_routes'=>[
                '13'=>'operator_5','25'=>'operator_315','18'=>'operator_115','43'=>'operator_342'
            ],
            'raw_user_search_observation_rows'=>$rawCount,
            'unique_user_search_operator_hotel_edges'=>count($edges),
            'opaque_fortuna_roulette_edges_excluded'=>count($opaque),
            'accepted_same_provider_edges'=>count($resolved),
            'missing_same_provider_edges'=>count($missing),
            'by_operator'=>$byOperator,
            'actionable_context_groups'=>count($contexts),
            'actionable_contexts'=>$contexts,
            'missing_edges'=>$missing,
            'database_writes'=>0,
            'mapping_writes'=>0,
            'supplier_calls'=>0,
            'tourvisor_calls'=>0,
            'samo_calls'=>0,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $result['reason'] = preg_match('/^[A-Za-z0-9_:.-]+$/D', $e->getMessage()) ? $e->getMessage() : 'sanitized_error';
    }

    $resultSha = c4uf_write_new($opDir . '/result.json', $result);
    c4uf_write_new($opDir . '/receipt.json', [
        'operation_id'=>C4UF_OP,
        'source_sha'=>$sourceSha,
        'state'=>$result['state'],
        'result_sha256'=>$resultSha,
        'readback_verified'=>hash('sha256', (string)file_get_contents($opDir . '/result.json')) === $resultSha,
        'database_writes'=>0,
        'mapping_writes'=>0,
        'supplier_calls'=>0,
        'tourvisor_calls'=>0,
        'samo_calls'=>0,
        'no_replay'=>true,
    ]);
    fwrite(STDOUT, json_encode([
        'state'=>$result['state'],
        'raw_user_search_observation_rows'=>$result['raw_user_search_observation_rows'] ?? null,
        'unique_edges'=>$result['unique_user_search_operator_hotel_edges'] ?? null,
        'accepted_same_provider_edges'=>$result['accepted_same_provider_edges'] ?? null,
        'missing_same_provider_edges'=>$result['missing_same_provider_edges'] ?? null,
        'by_operator'=>$result['by_operator'] ?? null,
        'actionable_context_groups'=>$result['actionable_context_groups'] ?? null,
        'result_sha256'=>$resultSha,
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");
    exit(($result['state'] ?? '') === 'completed_read_only' ? 0 : 2);
}

if (($argv[1] ?? '') === '--self-test') {
    c4uf_req(c4uf_opaque('Fortuna Antalya 5*'), 'opaque_en');
    c4uf_req(c4uf_opaque('РУЛЕТКА 4*'), 'opaque_ru');
    c4uf_req(!c4uf_opaque('FUN&SUN FAMILY HOTEL'), 'normal_hotel');
    c4uf_req(C4UF_ROUTES[13]['supplier_namespace'] === 'operator_5', 'route_anex');
    c4uf_req(C4UF_ROUTES[18]['supplier_namespace'] === 'operator_115', 'route_biblio');
    c4uf_req(C4UF_ROUTES[25]['supplier_namespace'] === 'operator_315', 'route_funsun');
    c4uf_req(C4UF_ROUTES[43]['supplier_namespace'] === 'operator_342', 'route_intourist');
    echo "hotel-match-common4-usersearch-frontier-current-v1: PASS\n";
    exit(0);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) c4uf_main();
