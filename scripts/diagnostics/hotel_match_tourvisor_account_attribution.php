<?php
declare(strict_types=1);

// MATCH-only provenance diagnostic. No supplier client and no DB connection.
const HMTVA_OPERATION = 'hotel-match-tourvisor-account-attribution-1971-20260912-v2';
const HMTVA_HISTORICAL_SOURCE = 'e9cec95fae4a91d76241de08ad8d2a02f0f61baa';

function hmtva_token(string $value): string
{
    $value = trim($value);
    return stripos($value, 'Bearer ') === 0 ? trim(substr($value, 7)) : $value;
}

function hmtva_classify(string $envRegular, string $constantRegular, string $constantAnex): array
{
    // Reproduce the historical wrapper's branch ordering exactly.
    $raw = trim($envRegular);
    $selected = $raw !== '' ? 'env:TOURVISOR_JWT' : 'missing';
    if ($raw === '' && trim($constantAnex) !== '') {
        $raw = trim($constantAnex);
        $selected = 'constant:TOURVISOR_ANEX_JWT';
    }
    $legacy = hmtva_token($raw);
    $env = hmtva_token($envRegular);
    $constant = hmtva_token($constantRegular);
    $anex = hmtva_token($constantAnex);
    $regular = $env !== '' ? $env : $constant;
    return [
        'ordinary_env_present' => $env !== '',
        'ordinary_constant_present' => $constant !== '',
        'separate_constant_present' => $anex !== '',
        'legacy_selected_source' => $selected,
        'legacy_selected_nonempty' => $legacy !== '',
        'legacy_equals_ordinary' => $legacy !== '' && $regular !== '' && hash_equals($regular, $legacy),
        'legacy_equals_separate' => $legacy !== '' && $anex !== '' && hash_equals($anex, $legacy),
        'ordinary_and_separate_distinct' => $regular !== '' && $anex !== '' && !hash_equals($regular, $anex),
        'ordinary_constant_skipped' => trim($envRegular) === '' && $constant !== '',
    ];
}

function hmtva_self_test(): void
{
    $cases = [
        ['', 'regular-fixture', 'separate-fixture', 'constant:TOURVISOR_ANEX_JWT', false, true, true],
        ['regular-fixture', 'regular-fixture', 'separate-fixture', 'env:TOURVISOR_JWT', true, false, false],
        ['', 'regular-fixture', '', 'missing', false, false, true],
        ['', '', 'separate-fixture', 'constant:TOURVISOR_ANEX_JWT', false, true, false],
        ['', '', '', 'missing', false, false, false],
        [' Bearer same-fixture ', '', 'same-fixture', 'env:TOURVISOR_JWT', true, true, false],
    ];
    foreach ($cases as $i => $x) {
        $r = hmtva_classify($x[0], $x[1], $x[2]);
        if ($r['legacy_selected_source'] !== $x[3] || $r['legacy_equals_ordinary'] !== $x[4]
            || $r['legacy_equals_separate'] !== $x[5] || $r['ordinary_constant_skipped'] !== $x[6]) {
            throw new RuntimeException('self_test_failed_' . $i);
        }
        $json = json_encode($r, JSON_THROW_ON_ERROR);
        if (strpos($json, '-fixture') !== false || count($r) !== 9) {
            throw new RuntimeException('output_not_sanitized');
        }
    }
    echo "MATCH_ACCOUNT_ATTRIBUTION_TEST_OK cases=6\n";
}

if (($argv[1] ?? '') === '--self-test') {
    hmtva_self_test();
    exit(0);
}
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--live') {
    exit(64);
}

$buffer = ob_get_level();
ob_start(static function (string $output): string { return ''; });
$result = [
    'operation_id' => HMTVA_OPERATION,
    'historical_source_sha' => HMTVA_HISTORICAL_SOURCE,
    'status' => 'unknown',
    'historical_runtime_account_recorded' => false,
    'scope' => 'current_config_selection_only',
    'supplier_http_calls' => 0,
    'tv_search_slices' => 0,
    'database_connections' => 0,
    'database_queries' => 0,
    'database_writes' => 0,
    'mapping_writes' => 0,
    'config_writes' => 0,
    'no_replay' => true,
];
try {
    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') {
        throw new RuntimeException('root_guard');
    }
    $private = $root . '/_preview/search3-anex-candidate/.anex-private.php';
    $dbHelper = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
    if (!is_file($private) || !is_file($dbHelper)) {
        throw new RuntimeException('bootstrap_guard');
    }
    // Same bootstrap as the completed Marsa workflow. Config function only:
    // v2_data_db() is deliberately NOT called, so no PDO connection is made.
    require_once $private;
    require_once $dbHelper;
    if (!function_exists('v2_data_db_config')) {
        throw new RuntimeException('config_helper_guard');
    }
    v2_data_db_config();
    $result['selection'] = hmtva_classify(
        (string)getenv('TOURVISOR_JWT'),
        defined('TOURVISOR_JWT') ? (string)constant('TOURVISOR_JWT') : '',
        defined('TOURVISOR_ANEX_JWT') ? (string)constant('TOURVISOR_ANEX_JWT') : ''
    );
    $result['status'] = 'completed';
} catch (Throwable $e) {
    // Never return arbitrary config exception messages or stack traces.
    $result['reason'] = 'attribution_bootstrap_failed';
}
while (ob_get_level() > $buffer) {
    ob_end_clean();
}
echo "HMTVA_RESULT:", json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
exit($result['status'] === 'completed' ? 0 : 2);
