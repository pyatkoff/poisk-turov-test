<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') throw new RuntimeException('WRITER_VERIFY_CLI_ONLY');
$site = isset($argv[1]) ? realpath((string)$argv[1]) : false;
$ops = isset($argv[2]) ? realpath((string)$argv[2]) : false;
$operation = $argv[3] ?? '';
$expectedApi = $argv[4] ?? '';
$expectedHelper = $argv[5] ?? '';
if (!is_string($site) || $site === '' || !is_file($site . '/api-v2.php')) throw new RuntimeException('WRITER_VERIFY_SITE');
if (!is_string($ops) || $ops === '' || !preg_match('/^[a-z0-9-]+$/D', $operation)) throw new RuntimeException('WRITER_VERIFY_ARGS');
if (!preg_match('/^[a-f0-9]{64}$/D', $expectedApi) || !preg_match('/^[a-f0-9]{64}$/D', $expectedHelper)) throw new RuntimeException('WRITER_VERIFY_HASH');

$ledger = $ops . '/' . $operation;
$receiptPath = $ledger . '/receipt.json';
if (!is_file($receiptPath) || !is_file($ledger . '/state')) throw new RuntimeException('WRITER_VERIFY_LEDGER');
$receipt = json_decode((string)file_get_contents($receiptPath), true, 32, JSON_THROW_ON_ERROR);
if (!is_array($receipt) || ($receipt['status'] ?? null) !== 'completed') throw new RuntimeException('WRITER_VERIFY_RECEIPT');
$searchId = (int)($receipt['search_id'] ?? 0);
if ($searchId < 1) throw new RuntimeException('WRITER_VERIFY_SEARCH');

$runtimeRoot = dirname($site);
$helper = $runtimeRoot . '/app/integrations/tourvisor-anytour-offer-autosave.php';
$dataRoot = $runtimeRoot . '/v2/data';
$stateDir = $site . '/.cache/catalogs/tourvisor-offer-autosave';
$statePath = $stateDir . '/anytour-tourvisor-offer-' . hash('sha256', (string)$searchId) . '.json';

$state = is_file($statePath) ? json_decode((string)file_get_contents($statePath), true, 16, JSON_THROW_ON_ERROR) : null;
$stateMode = is_file($statePath) ? (fileperms($statePath) & 0777) : null;
$guard = $stateDir . '/.htaccess';

$_SERVER['DOCUMENT_ROOT'] = $site;
require_once $site . '/data/db-v1.php';
$db = v2_data_db();
$db->exec('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $q = static fn(string $sql): int => (int)$db->query($sql)->fetchColumn();
    $dbState = [
        'completed_refreshes'=>$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='completed'"),
        'active_offers'=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1"),
        'ready_rub'=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1 AND final_price_ready=1 AND currency='RUB'"),
        'latest_scopes'=>$q("SELECT COUNT(*) FROM anytour_offer_scope_state WHERE provider='tourvisor' AND latest_complete_refresh_token IS NOT NULL"),
    ];
    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}

$out = [
    'schema_version'=>1,
    'operation'=>'int-tourvisor-production-writer-verify-20260917-v2',
    'status'=>'completed_read_only',
    'activation_operation'=>$operation,
    'activation_ledger_state'=>trim((string)file_get_contents($ledger . '/state')),
    'release_sha'=>$receipt['release_sha'] ?? null,
    'production_api_sha256'=>hash_file('sha256', $site . '/api-v2.php'),
    'api_matches_expected'=>hash_equals($expectedApi, hash_file('sha256', $site . '/api-v2.php')),
    'runtime_root'=>$runtimeRoot,
    'helper_exists'=>is_file($helper),
    'helper_sha256'=>is_file($helper) ? hash_file('sha256', $helper) : null,
    'helper_matches_expected'=>is_file($helper) && hash_equals($expectedHelper, hash_file('sha256', $helper)),
    'private_data_runtime_exists'=>is_dir($dataRoot) && is_file($dataRoot . '/anytour-offer-snapshot-ingest-v1.php'),
    'state_dir_exists'=>is_dir($stateDir),
    'deny_guard_exact'=>is_file($guard) && file_get_contents($guard) === "Require all denied\n",
    'state_exists'=>is_file($statePath),
    'state_mode'=>$stateMode,
    'state_terminal_persisted'=>is_array($state) && is_int($state['terminal_at'] ?? null),
    'state_saved_persisted'=>is_array($state) && is_int($state['saved_at'] ?? null),
    'state_search_id'=>is_array($state) ? ($state['search_id'] ?? null) : null,
    'db'=>$dbState,
    'receipt_after'=>$receipt['after'] ?? null,
    'supplier_calls'=>0,
    'db_writes'=>0,
    'public_writes'=>0,
];

if (!$out['api_matches_expected'] || !$out['helper_matches_expected'] || !$out['private_data_runtime_exists']
    || !$out['deny_guard_exact'] || !$out['state_exists'] || $out['state_mode'] !== 0600
    || !$out['state_terminal_persisted'] || !$out['state_saved_persisted']
    || $out['state_search_id'] !== $searchId
    || $dbState['completed_refreshes'] < (int)($receipt['after']['refreshes'] ?? 0)
    || $dbState['active_offers'] < (int)($receipt['after']['offers'] ?? 0)
    || $dbState['ready_rub'] < (int)($receipt['after']['ready'] ?? 0)) {
    echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
    throw new RuntimeException('WRITER_VERIFY_POSTCHECK');
}

echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
