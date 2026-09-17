<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') throw new RuntimeException('STATE_DIAG_CLI_ONLY');
$site = isset($argv[1]) ? realpath((string)$argv[1]) : false;
$ops = isset($argv[2]) ? realpath((string)$argv[2]) : false;
if (!is_string($site) || $site === '' || !is_file($site . '/api-v2.php')) throw new RuntimeException('STATE_DIAG_SITE');
if (!is_string($ops) || $ops === '') throw new RuntimeException('STATE_DIAG_OPS');

$read = static function(string $operation) use ($ops): array {
    $dir = $ops . '/' . $operation;
    $startPath = $dir . '/search-start.json';
    $searchId = 0;
    if (is_file($startPath)) {
        $start = json_decode((string)file_get_contents($startPath), true);
        if (is_array($start)) $searchId = (int)($start['searchId'] ?? ($start['data']['searchId'] ?? 0));
    }
    $statePath = $searchId > 0
        ? rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'anytour-tourvisor-offer-' . hash('sha256', (string)$searchId) . '.json'
        : '';
    $state = null;
    if ($statePath !== '' && is_file($statePath)) {
        $decoded = json_decode((string)file_get_contents($statePath), true);
        if (is_array($decoded)) {
            $state = [
                'version'=>$decoded['version'] ?? null,
                'search_id'=>$decoded['search_id'] ?? null,
                'started_at'=>$decoded['started_at'] ?? null,
                'terminal_at'=>$decoded['terminal_at'] ?? null,
                'saved_at'=>$decoded['saved_at'] ?? null,
            ];
        }
    }
    return [
        'search_id'=>$searchId,
        'state_path'=>$statePath,
        'host_state_exists'=>$statePath !== '' && is_file($statePath),
        'state'=>$state,
        'ledger_state'=>is_file($dir . '/state') ? trim((string)file_get_contents($dir . '/state')) : null,
    ];
};

$cache = $site . '/.cache/catalogs';
$cacheFiles = is_dir($cache) ? glob($cache . '/*.json') : [];
$home = dirname(dirname($site));
$out = [
    'schema_version'=>1,
    'operation'=>'int-tourvisor-state-diagnose-20260917-v3',
    'status'=>'completed_read_only',
    'cli_sys_temp_dir'=>sys_get_temp_dir(),
    'v1'=>$read('int-tourvisor-production-writer-20260917-v1'),
    'v3'=>$read('int-tourvisor-production-writer-20260917-v3'),
    'v4'=>$read('int-tourvisor-production-writer-20260917-v4'),
    'catalog_cache'=>[
        'exists'=>is_dir($cache),
        'json_files'=>is_array($cacheFiles) ? count($cacheFiles) : 0,
        'shell_writable'=>is_dir($cache) && is_writable($cache),
    ],
    'production_api_sha256'=>hash_file('sha256', $site . '/api-v2.php'),
    'sibling_app_exists'=>file_exists($home . '/app') || is_link($home . '/app'),
    'sibling_v2_exists'=>file_exists($home . '/v2') || is_link($home . '/v2'),
    'supplier_calls'=>0,
    'db_calls'=>0,
    'writes'=>0,
];
echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
