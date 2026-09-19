<?php
declare(strict_types=1);

const HM_COMMON4_TV_OP = 'hotel-match-common4-tv-operator-dictionary-1971-20260919-v1';
const HM_COMMON4_TV_DAY = '2026-09-19';
const HM_COMMON4_TV_LIMIT = 3000;
const HM_COMMON4_TV_DEPARTURE = 1;
const HM_COMMON4_TV_COUNTRY = 4;
const HM_COMMON4_TV_BODY_LIMIT = 4 * 1024 * 1024;

function hm_c4tv_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

function hm_c4tv_write_new(string $path, array $value): string
{
    $raw = hm_c4tv_json($value);
    $fh = @fopen($path, 'x+b');
    if ($fh === false) throw new RuntimeException('durable_create:' . basename($path));
    try {
        if (fwrite($fh, $raw) !== strlen($raw) || !fflush($fh)) throw new RuntimeException('durable_write');
        if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('durable_sync');
        rewind($fh);
        if (stream_get_contents($fh) !== $raw) throw new RuntimeException('durable_readback');
    } finally {
        fclose($fh);
    }
    return hash('sha256', $raw);
}

function hm_c4tv_read_locked($fh): array
{
    rewind($fh);
    $raw = stream_get_contents($fh);
    $value = json_decode((string)$raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('ledger_shape');
    return $value;
}

function hm_c4tv_write_locked($fh, array $value): void
{
    $raw = hm_c4tv_json($value);
    rewind($fh);
    if (!ftruncate($fh, 0)) throw new RuntimeException('ledger_truncate');
    if (fwrite($fh, $raw) !== strlen($raw) || !fflush($fh)) throw new RuntimeException('ledger_write');
    if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('ledger_sync');
}

function hm_c4tv_charge_one(string $opDir): array
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
    if ($today !== HM_COMMON4_TV_DAY) throw new RuntimeException('provider_day_mismatch');

    $quotaDir = rtrim((string)getenv('HOME'), '/') . '/.anytour-match/provider-quotas';
    $dayFile = $quotaDir . '/tourvisor-test-' . HM_COMMON4_TV_DAY . '.json';
    $opFile = $quotaDir . '/tourvisor-' . HM_COMMON4_TV_OP . '.json';
    if (!is_file($dayFile)) throw new RuntimeException('shared_ledger_missing');
    if (file_exists($opFile)) throw new RuntimeException('operation_ledger_exists_no_replay');

    $fh = fopen($dayFile, 'r+b');
    if ($fh === false) throw new RuntimeException('shared_ledger_open');
    try {
        if (!flock($fh, LOCK_EX)) throw new RuntimeException('shared_ledger_lock');
        $ledger = hm_c4tv_read_locked($fh);
        $ownerLimit = (int)($ledger['owner_daily_limit'] ?? 0);
        if ($ownerLimit <= 0) throw new RuntimeException('owner_daily_limit_missing');
        $limit = min(HM_COMMON4_TV_LIMIT, $ownerLimit);
        if ($limit !== HM_COMMON4_TV_LIMIT) throw new RuntimeException('owner_daily_limit_below_3000');

        $accounted = (int)($ledger['accounted_requests'] ?? 0);
        $floor = (int)($ledger['known_prior_attempt_floor'] ?? 0);
        $match = (int)($ledger['match_new_attempts'] ?? 0);
        $chargedBefore = max($accounted, $floor + $match);
        if ($chargedBefore < 0 || $chargedBefore >= $limit) throw new RuntimeException('daily_budget_exhausted');

        $ledger['match_new_attempts'] = $match + 1;
        $ledger['accounted_requests'] = $chargedBefore + 1;
        if (!isset($ledger['operations']) || !is_array($ledger['operations'])) $ledger['operations'] = [];
        if (array_key_exists(HM_COMMON4_TV_OP, $ledger['operations'])) throw new RuntimeException('operation_already_accounted_no_replay');
        $ledger['operations'][HM_COMMON4_TV_OP] = 1;
        hm_c4tv_write_locked($fh, $ledger);
        $chargedAfter = $chargedBefore + 1;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    hm_c4tv_write_new($opFile, [
        'operation' => HM_COMMON4_TV_OP,
        'provider_day' => HM_COMMON4_TV_DAY,
        'status' => 'provider_accessed_terminal_no_replay',
        'used' => 1,
        'action' => 'operator_dictionary',
        'accounted_before' => $chargedBefore,
        'accounted_after' => $chargedAfter,
        'owner_daily_limit' => HM_COMMON4_TV_LIMIT,
    ]);
    hm_c4tv_write_new($opDir . '/quota-charge.json', [
        'operation' => HM_COMMON4_TV_OP,
        'provider_day' => HM_COMMON4_TV_DAY,
        'action' => 'operator_dictionary',
        'physical_http_calls_reserved' => 1,
        'accounted_before' => $chargedBefore,
        'accounted_after' => $chargedAfter,
        'daily_limit' => HM_COMMON4_TV_LIMIT,
    ]);
    return ['before' => $chargedBefore, 'after' => $chargedAfter];
}

function hm_c4tv_token(string $root): string
{
    $env = trim((string)getenv('TOURVISOR_JWT'));
    if ($env !== '') return $env;
    require_once rtrim($root, '/') . '/config.php';
    $token = defined('TOURVISOR_JWT') ? trim((string)TOURVISOR_JWT) : '';
    if ($token === '' || str_contains($token, "\n") || str_contains($token, "\r")) throw new RuntimeException('tourvisor_token_invalid');
    return $token;
}

function hm_c4tv_fetch_once(string $token): array
{
    $url = 'https://api.tourvisor.ru/search/api/v1/operators?departureId=' . HM_COMMON4_TV_DEPARTURE . '&countryId=' . HM_COMMON4_TV_COUNTRY;
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('curl_init');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno !== 0) throw new RuntimeException('tourvisor_connection_error:' . $errno . ':' . $error);
    if (!is_string($body) || strlen($body) > HM_COMMON4_TV_BODY_LIMIT) throw new RuntimeException('tourvisor_body_limit');
    if ($status !== 200) throw new RuntimeException('tourvisor_http_' . $status);
    if ($token !== '' && str_contains($body, $token)) throw new RuntimeException('sensitive_response_token');
    if (preg_match('/"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:/i', $body)) throw new RuntimeException('sensitive_response_field');
    $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('tourvisor_dictionary_shape');
    return ['http_status' => $status, 'body' => $body, 'data' => $data];
}

function hm_c4tv_execute(string $root, string $opDir): array
{
    require_once $opDir . '/payload/hotel_match_common4_operator_binding_v1.php';
    $reservation = json_decode((string)file_get_contents($opDir . '/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($reservation['operation'] ?? null) !== HM_COMMON4_TV_OP || ($reservation['state'] ?? null) !== 'reserved_before_provider_access') {
        throw new RuntimeException('reservation_mismatch');
    }

    $token = hm_c4tv_token($root);
    $quota = hm_c4tv_charge_one($opDir); // Must happen before the one physical HTTP call.
    $reply = hm_c4tv_fetch_once($token); // Deliberately no retry: one ledger charge == one physical HTTP.

    $response = [
        'operation' => HM_COMMON4_TV_OP,
        'http_status' => $reply['http_status'],
        'raw_body_sha256' => hash('sha256', $reply['body']),
        'data' => $reply['data'],
    ];
    $responseSha = hm_c4tv_write_new($opDir . '/operator-dictionary-response.json', $response);

    $state = 'completed_read_only';
    $reason = null;
    $resolved = [];
    try {
        $resolved = hm_common4_resolve($reply['data'], 'tourvisor');
    } catch (Throwable $e) {
        $state = 'completed_hold';
        $reason = $e->getMessage();
    }

    return [
        'operation' => HM_COMMON4_TV_OP,
        'state' => $state,
        'reason' => $reason,
        'provider' => 'tourvisor',
        'namespace' => 'tourvisor',
        'route' => ['departure_id' => HM_COMMON4_TV_DEPARTURE, 'country_id' => HM_COMMON4_TV_COUNTRY],
        'resolved' => $resolved,
        'dictionary_response_sha256' => $responseSha,
        'physical_http_calls' => 1,
        'daily_accounted_before' => $quota['before'],
        'daily_accounted_after' => $quota['after'],
        'daily_limit' => HM_COMMON4_TV_LIMIT,
        'db_writes' => 0,
        'mapping_writes' => 0,
        'search_calls' => 0,
        'detail_calls' => 0,
        'no_replay' => true,
    ];
}

if (in_array('--self-test', $argv ?? [], true)) {
    require_once __DIR__ . '/hotel_match_common4_operator_binding_v1.php';
    $sample = ['operators' => [
        ['id' => 13, 'name' => 'ANEX'],
        ['id' => 25, 'name' => 'FUN&SUN'],
        ['id' => 18, 'name' => 'Библио Глобус'],
        ['id' => 43, 'name' => 'Интурист'],
    ]];
    $r = hm_common4_resolve($sample, 'tourvisor');
    if (array_keys($r) !== ['anex', 'funsun', 'biblio_globus', 'intourist']) throw new RuntimeException('selftest_keys');
    if ($r['funsun']['provider_operator_id'] !== '25' || $r['intourist']['provider_operator_id'] !== '43') throw new RuntimeException('selftest_values');
    echo "hotel-match-common4-tv-operator-dictionary-v1: PASS\n";
    exit(0);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? '') !== '--execute') throw new RuntimeException('disabled');
    $root = (string)getenv('ANYTOUR_ROOT');
    $opDir = (string)getenv('MATCH_OPERATION_DIR');
    if ($root === '' || $opDir === '' || !is_dir($opDir)) throw new RuntimeException('runtime_paths');

    $result = null;
    try {
        $result = hm_c4tv_execute($root, $opDir);
    } catch (Throwable $e) {
        $result = [
            'operation' => HM_COMMON4_TV_OP,
            'state' => file_exists($opDir . '/quota-charge.json') ? 'terminal_failed_no_replay' : 'failed_before_provider_access',
            'reason' => get_class($e) . ':' . $e->getMessage(),
            'physical_http_calls' => file_exists($opDir . '/quota-charge.json') ? 1 : 0,
            'db_writes' => 0,
            'mapping_writes' => 0,
            'no_replay' => file_exists($opDir . '/quota-charge.json'),
        ];
    }
    $resultSha = hm_c4tv_write_new($opDir . '/result.json', $result);
    hm_c4tv_write_new($opDir . '/receipt.json', [
        'operation' => HM_COMMON4_TV_OP,
        'state' => $result['state'],
        'result_sha256' => $resultSha,
        'no_replay' => (bool)($result['no_replay'] ?? false),
    ]);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit(($result['state'] ?? '') === 'failed_before_provider_access' ? 1 : 0);
}
