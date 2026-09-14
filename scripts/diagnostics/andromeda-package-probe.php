<?php
declare(strict_types=1);

/**
 * Selected-only replacement for the superseded first-PRICE probe. PRIVATE CLI.
 * No --execute compatibility, login/search, installer, HTTP route, or retry.
 * Deployment and this specific operation require their existing separate gates.
 */
const ANDROMEDA_RETAINED_RUNTIME = 'fdd099e36fdc1a20a561f285d22d0acb00f90e36';
const ANDROMEDA_RETAINED_FILES = [
    'app/integrations/andromeda-client.php' => 'ef45ade3710b26488e13cca995ac730baf6f0a699f9075a71d09174fd345f135',
    'app/integrations/andromeda-package-capture.php' => '1d64913636cfec93bcc38fd011c6a1960f370f35f7df503941efada771f5ab6f',
    'app/integrations/andromeda-saved-package-runtime.php' => '52b2b7c394f99359997451ca162137527dc0189ec69fbd3671b670079cb5211e',
    'app/integrations/andromeda-selected-offer.php' => '06f5f608cb310fe0e3eafc11987ae1f70fa829aae70866c6f87ea1eb603fbc01',
    'app/integrations/andromeda-transport.php' => '457273021d5363a6b3e593ca5807110d0513b06ea2861a64a6719f86e837a470',
    'api-andromeda-search3-preview.php' => '12cc0b0d4d9c0823eb78c6f78b3dfcbf6c692420bd9fa9209e620e860f437a28',
];

// Published #2439 handoff; independent from the immutable historical capture mode.
const ANDROMEDA_SURCHARGE_RUNTIME = 'b32993261eb8657004e7d4fd146b65a95264d4bc';
const ANDROMEDA_SURCHARGE_OPERATION = 'capture-selected-retained-surcharge-1717-v1';
const ANDROMEDA_SURCHARGE_FILES = [
    'app/integrations/andromeda-claim-actions.php' => 'fa729842f66c2cb9a6357c71fdf5a074f059e523d33387be4ab7d44285747077',
    'app/integrations/andromeda-claiminc-contract.php' => 'e8646bae9a9246237d5b987a0a331047561d0a4a4012ea22dab3c0aeb1f7bf1f',
    'app/integrations/andromeda-client.php' => '03fec9be9b7d24bb26d32216cd52752539945032144e5dbc969cb9d9c2928910',
    'app/integrations/andromeda-network-transport-failure.php' => '391372c421e6e8f4aeac82637ae56ba9f9f45a7894a8f0e0cc5297996d704a66',
    'app/integrations/andromeda-package-attempt-state.php' => 'd92a02e9de0004d039dc6b588e70f1923f9edb455a13a9460baf6f9175e636e0',
    'app/integrations/andromeda-package-capture.php' => '1d64913636cfec93bcc38fd011c6a1960f370f35f7df503941efada771f5ab6f',
    'app/integrations/andromeda-package-retry-policy.php' => '9e5a95b7524b5767ca567b8461467b85b741cf7ba11bafbf0e2a32917938f573',
    'app/integrations/andromeda-saved-package-runtime.php' => '28995a73f46f3e1a9d953c034cd5bf514d53c6c4d46d71d5ba593b7d255a8112',
    'app/integrations/andromeda-search-surcharge.php' => '0ee9f47a64e93929b48b422b20477703b12abbee3d2bc2b4a3a0de0f77df6cfd',
    'app/integrations/andromeda-selected-offer.php' => '06f5f608cb310fe0e3eafc11987ae1f70fa829aae70866c6f87ea1eb603fbc01',
    'app/integrations/andromeda-transport.php' => '6b93957a2a11b9c663f39982165f98e19b24871f2ef13be683304b71203c5c02',
    'api-andromeda-search3-preview.php' => '22beea5872edf737dafe92d5c4092adc80baa8d88bc46f5b26a52c1f7fe67efe',
];

/** Accept the existing publicSelection DTO, not a raw supplier ID or a price. */
function anytour_retained_package_input(string $raw, bool $withSurcharge = false): array
{
    if ($raw === '' || strlen($raw) > 16384) throw new RuntimeException('input_invalid');
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    $keys = ['version', 'operation', 'runtime_source', 'local_country_id', 'selection'];
    if (!is_array($input) || count($input) !== count($keys) || array_diff($keys, array_keys($input))
        || $input['version'] !== 1 || $input['operation'] !== ($withSurcharge ? ANDROMEDA_SURCHARGE_OPERATION : 'capture-selected-retained-offer-1717')
        || $input['runtime_source'] !== ($withSurcharge ? ANDROMEDA_SURCHARGE_RUNTIME : ANDROMEDA_RETAINED_RUNTIME)
        || !is_int($input['local_country_id']) || $input['local_country_id'] < 1
        || !is_array($input['selection'])) throw new RuntimeException('input_invalid');
    $selection = $input['selection'];
    $identity = ['provider', 'search_ref', 'generation', 'page', 'offer_ref', 'hotel_scope', 'operator_ref', 'local_id'];
    if (array_diff($identity, array_keys($selection))
        || array_diff(array_keys($selection), array_merge($identity, ['tour', 'quote_status', 'package_status']))
        || $selection['provider'] !== 'andromeda'
        || !is_string($selection['search_ref']) || !preg_match('/^[a-f0-9]{64}$/D', $selection['search_ref'])
        || !is_string($selection['offer_ref']) || !preg_match('/^offer_[a-f0-9]{64}$/D', $selection['offer_ref'])
        || !is_string($selection['operator_ref']) || $selection['operator_ref'] === ''
        || strlen($selection['operator_ref']) > 4096 || preg_match('/[\x00-\x20\x7f]/', $selection['operator_ref'])) {
        throw new RuntimeException('input_invalid');
    }
    foreach (['generation', 'page', 'local_id'] as $key) {
        if (!is_int($selection[$key]) || $selection[$key] < 1) throw new RuntimeException('input_invalid');
    }
    $scope = $selection['hotel_scope'];
    if ($scope !== null && (!is_string($scope) || $scope === '' || strlen($scope) > 4096
        || preg_match('/[\x00-\x20\x7f]/', $scope))) throw new RuntimeException('input_invalid');
    // Ignore display facts; the existing server store is the only offer authority.
    $input['selection'] = array_intersect_key($selection, array_flip($identity));
    return $input;
}

/** Hash the exact selected handoff while holding the shared publisher lock. */
function anytour_retained_package_runtime(string $root, bool $withSurcharge = false): string
{
    if ($root === '' || basename($root) !== 'anytoour.ru' || realpath($root) !== $root) {
        throw new RuntimeException('project_invalid');
    }
    $target = $root . '/_preview/search3-anex-candidate';
    foreach (($withSurcharge ? ANDROMEDA_SURCHARGE_FILES : ANDROMEDA_RETAINED_FILES) as $path => $expected) {
        $file = $target . '/' . $path;
        if (realpath($file) !== $file || !is_file($file) || !hash_equals($expected, hash_file('sha256', $file))) {
            throw new RuntimeException('runtime_not_installed');
        }
    }
    return $target;
}

/** Reuse installed AnyTour config/catalog/DB helpers, then the unchanged bridge. */
function anytour_retained_package_invoke(string $root, string $target, array $input): array
{
    $configPath = $target . '/.andromeda-private.php';
    $dbPath = $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    if (realpath($configPath) !== $configPath || !is_file($configPath)
        || realpath($dbPath) !== $dbPath || !is_file($dbPath)) throw new RuntimeException('private_runtime_missing');
    global $andromedaApp; // The existing API's detail helper uses this global module path.
    require_once $target . '/api-andromeda-search3-preview.php';
    require_once $target . '/app/integrations/andromeda-saved-package-runtime.php';
    require_once $dbPath;
    $config = require $configPath;
    if (!is_array($config) || ($config['enabled'] ?? null) !== true) throw new RuntimeException('private_runtime_missing');
    $catalog = anytour_andromeda_search3_catalog($config, ['params' => ['countryId' => $input['local_country_id']]]);
    // No SID/login/PRICE/broninit implementation here. Reservation, budget, mapping,
    // retained context, late replies and no-replay remain owned by this bridge.
    $withSurcharge = $input['operation'] === ANDROMEDA_SURCHARGE_OPERATION;
    return anytour_andromeda_capture_selected_package($config, $catalog, $input['selection'],
        v2_data_db(), $input['runtime_source'], true, null, null, $withSurcharge);
}

/** The callback is an in-process test seam; CLI input cannot select executable code. */
function anytour_retained_package_run(array $args, string $raw, string $root, callable $invoke): array
{
    $lock = null; $enteredBridge = false;
    $result = ['status' => 'blocked', 'reason' => 'operation_refused', 'automatic_retry' => false,
        'identity_verified' => false, 'quote_verified' => false, 'selection_enabled' => false];
    try {
        if (PHP_SAPI !== 'cli' || count($args) !== 2
            || !in_array($args[1], ['--capture-retained-package', '--capture-retained-surcharge'], true)) return $result;
        $withSurcharge = $args[1] === '--capture-retained-surcharge';
        $input = anytour_retained_package_input($raw, $withSurcharge);
        if (basename($root) !== 'anytoour.ru' || realpath($root) !== $root) throw new RuntimeException('project_invalid');
        // Share the existing publisher's lock. Never create/reset it or wait indefinitely.
        $lockPath = dirname($root, 2) . '/.anytoour-andromeda/grouped-search-update.lock';
        if (realpath($lockPath) !== $lockPath || !is_file($lockPath)) throw new RuntimeException('publication_lock_missing');
        $lock = fopen($lockPath, 'r+b');
        if (!$lock || !flock($lock, LOCK_SH | LOCK_NB)) throw new RuntimeException('publication_busy');
        $target = anytour_retained_package_runtime($root, $withSurcharge);
        $enteredBridge = true;
        $receipt = $invoke($root, $target, $input);
        if (($receipt['status'] ?? null) !== 'captured' || ($receipt['source'] ?? null) !== $input['runtime_source']
            || !is_bool($receipt['reused'] ?? null) || !is_string($receipt['package_sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $receipt['package_sha256'])
            || !is_array($receipt['context'] ?? null)) throw new RuntimeException('receipt_invalid');
        foreach ($input['selection'] as $key => $value) {
            if (!array_key_exists($key, $receipt['context']) || $receipt['context'][$key] !== $value) throw new RuntimeException('receipt_invalid');
        }
        foreach (['identity_verified', 'quote_verified', 'selection_enabled'] as $key) {
            if (($receipt[$key] ?? null) !== false) throw new RuntimeException('receipt_invalid');
        }
        $surcharge = [];
        if ($withSurcharge) {
            $value = $receipt['surcharge'] ?? null;
            if (!is_array($value) || !in_array($value['status'] ?? null, ['complete', 'unavailable'], true)
                || !is_bool($value['reused'] ?? null) || !array_key_exists('fact', $value)) {
                throw new RuntimeException('receipt_invalid');
            }
            $fact = $value['fact'];
            if (($value['status'] === 'unavailable' && $fact !== null)
                || ($value['status'] === 'complete' && (!is_array($fact)
                    || ($fact['provider'] ?? null) !== 'andromeda' || ($fact['state'] ?? null) !== 'estimated'
                    || ($fact['arithmetic_applied'] ?? null) !== true || ($fact['final_price_verified'] ?? null) !== false))) {
                throw new RuntimeException('receipt_invalid');
            }
            // Receipt reports availability, not raw transport/claim or an invented zero.
            $surcharge = ['surcharge_status' => $value['status'], 'surcharge_reused' => $value['reused']];
        }
        // Fixed allowlist only: no claim, context values, currency, cost or personal data.
        return array_replace($result, ['status' => 'captured', 'reason' => null,
            'runtime_source' => $input['runtime_source'], 'reused' => $receipt['reused'],
            'package_sha256' => $receipt['package_sha256']], $surcharge);
    } catch (Throwable $error) {
        $codes = ['input_invalid', 'project_invalid', 'runtime_not_installed', 'publication_lock_missing',
            'publication_busy', 'private_runtime_missing', 'receipt_invalid',
            'ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN', 'ANDROMEDA_PACKAGE_NOT_CAPTURED',
            'ANDROMEDA_PACKAGE_CONTEXT_STALE', 'ANDROMEDA_PACKAGE_CONTEXT_MISMATCH',
            'ANDROMEDA_PACKAGE_MAPPING_UNAVAILABLE', 'ANDROMEDA_PACKAGE_CHECKPOINT_INVALID'];
        $result['reason'] = in_array($error->getMessage(), $codes, true) ? $error->getMessage() : 'operation_unconfirmed';
        // Even a dependency error after invocation is not proof of zero supplier effect.
        $result['status'] = $enteredBridge ? 'unconfirmed' : 'blocked';
        return $result;
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    ini_set('display_errors', '0'); ini_set('log_errors', '0');
    ini_set('zend.exception_ignore_args', '1'); error_reporting(0); umask(0077);
    ob_start();
    // Reject the historical mode before reading input, loading secrets or touching files.
    $raw = $argc === 2 && in_array($argv[1], ['--capture-retained-package', '--capture-retained-surcharge'], true)
        ? file_get_contents('php://stdin', false, null, 0, 16385) : '';
    $receipt = anytour_retained_package_run($argv, is_string($raw) ? $raw : '',
        (string) getcwd(), 'anytour_retained_package_invoke');
    while (ob_get_level()) ob_end_clean();
    echo json_encode($receipt, JSON_THROW_ON_ERROR) . "\n";
    exit($receipt['status'] === 'captured' ? 0 : 1);
}
