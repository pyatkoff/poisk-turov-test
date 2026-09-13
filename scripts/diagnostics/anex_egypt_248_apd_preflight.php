<?php
declare(strict_types=1);

const ANEX_EGYPT_248_APD_PREFLIGHT_OPERATION = 'anex-egypt-248-apd-preflight-20260914-v1';
const ANEX_EGYPT_248_APD_SEMANTIC_OPERATION = 'anex-egypt-248-apd-retained-20260914-v1';
const ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL = 248;
const ANEX_EGYPT_248_APD_PREFLIGHT_EXTERNAL_HOTEL = '10449';

function anex_egypt_248_apd_preflight_category(string $stage): string
{
    return [
        'input' => 'invalid_input',
        'runtime_paths' => 'runtime_path_unavailable',
        'root_config' => 'root_config_unavailable',
        'private_config' => 'private_config_unavailable',
        'integration_source' => 'integration_source_unavailable',
        'tokens' => 'token_unavailable',
        'db_helper' => 'db_helper_unavailable',
        'db_connect' => 'db_unavailable',
        'local_context' => 'local_context_unavailable',
        'identity' => 'identity_unavailable',
        'receipt_path' => 'receipt_path_unavailable',
    ][$stage] ?? 'preflight_unconfirmed';
}

function anex_egypt_248_apd_preflight_main(array $input): array
{
    $out = [
        'schema_version' => 1,
        'operation_id' => ANEX_EGYPT_248_APD_PREFLIGHT_OPERATION,
        'semantic_operation_id' => ANEX_EGYPT_248_APD_SEMANTIC_OPERATION,
        'status' => 'blocked',
        'blocker_stage' => 'input',
        'blocker_category' => 'invalid_input',
        'supplier_calls' => 0,
        'additional_prices_calls' => 0,
        'tourvisor_calls' => 0,
        'andromeda_calls' => 0,
        'booking_calls' => 0,
        'broninit_calls' => 0,
        'mapping_writes' => 0,
        'semantic_reservation_written' => false,
    ];
    $stage = 'input';
    try {
        if (array_keys($input) !== ['operation_id', 'source_sha']
            || ($input['operation_id'] ?? null) !== ANEX_EGYPT_248_APD_PREFLIGHT_OPERATION
            || !is_string($input['source_sha'] ?? null)
            || !preg_match('/\A[a-f0-9]{40}\z/D', $input['source_sha'])) {
            throw new RuntimeException('PREFLIGHT_INPUT');
        }
        $out['source_sha'] = $input['source_sha'];

        $stage = 'runtime_paths';
        $home = (string) getenv('HOME');
        $root = realpath($home . '/www/anytoour.ru');
        $preview = $root ? realpath($root . '/_preview/search3-anex-candidate') : false;
        $private = realpath($home . '/.anytoour-anex');
        $cwd = realpath((string) getcwd());
        if ($home === '' || !$root || !$preview || !$private || !$cwd
            || $preview !== $root . '/_preview/search3-anex-candidate'
            || $private !== $home . '/.anytoour-anex'
            || !in_array($cwd, [$root, $preview], true)) {
            throw new RuntimeException('PREFLIGHT_RUNTIME_PATHS');
        }

        $stage = 'root_config';
        if (!is_file($root . '/config.php') || !is_readable($root . '/config.php')) {
            throw new RuntimeException('PREFLIGHT_ROOT_CONFIG');
        }
        require_once $root . '/config.php';

        $stage = 'private_config';
        if (!is_file($private . '/search3-preview.php') || !is_readable($private . '/search3-preview.php')) {
            throw new RuntimeException('PREFLIGHT_PRIVATE_CONFIG');
        }
        require_once $private . '/search3-preview.php';

        $stage = 'integration_source';
        $registryFile = $preview . '/app/integrations/anex-search-mapping-registry.php';
        if (!is_file($registryFile) || !is_readable($registryFile)) {
            throw new RuntimeException('PREFLIGHT_INTEGRATION_SOURCE');
        }
        require_once $registryFile;
        if (!class_exists('AnyTourAnexSearchMappingRegistry')) {
            throw new RuntimeException('PREFLIGHT_INTEGRATION_SOURCE');
        }

        $stage = 'tokens';
        $searchTokenReady = defined('ANEX_API_TOKEN') && is_string(ANEX_API_TOKEN) && trim(ANEX_API_TOKEN) !== '';
        $b2bTokenReady = defined('ANEX_B2B_TOKEN') && is_string(ANEX_B2B_TOKEN) && trim(ANEX_B2B_TOKEN) !== '';
        $out['token_presence'] = ['search' => $searchTokenReady, 'b2b' => $b2bTokenReady];
        if (!$searchTokenReady || !$b2bTokenReady) throw new RuntimeException('PREFLIGHT_TOKEN');

        $stage = 'db_helper';
        $db = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        if (!is_file($db) || !is_readable($db)) throw new RuntimeException('PREFLIGHT_DB_HELPER');
        require_once $db;
        if (!function_exists('v2_data_db')) throw new RuntimeException('PREFLIGHT_DB_HELPER');

        $stage = 'db_connect';
        $pdo = v2_data_db();
        if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('PREFLIGHT_DB_CONNECT');
        }

        $stage = 'local_context';
        $q = $pdo->prepare("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name,h.id hotel_id FROM catalog_departures d CROSS JOIN catalog_countries c JOIN catalog_hotels h ON h.country_id=c.id WHERE d.is_active=1 AND c.is_active=1 AND h.is_active=1 AND h.id=? AND d.name IN ('Москва','Moscow') AND c.name IN ('Египет','Egypt') LIMIT 2");
        $q->execute([ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1 || (int) ($rows[0]['hotel_id'] ?? 0) !== ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL) {
            throw new RuntimeException('PREFLIGHT_LOCAL_CONTEXT');
        }
        $out['local_context'] = ['hotel_id' => ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL, 'ready' => true];

        $stage = 'identity';
        $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        $resolved = $registry->resolve('anex_online', ANEX_EGYPT_248_APD_PREFLIGHT_EXTERNAL_HOTEL, 'preview');
        $out['identity'] = ['external_hotel_id' => ANEX_EGYPT_248_APD_PREFLIGHT_EXTERNAL_HOTEL,
            'local_hotel_id' => is_int($resolved) ? $resolved : null, 'ready' => $resolved === ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL];
        if ($resolved !== ANEX_EGYPT_248_APD_PREFLIGHT_LOCAL_HOTEL) throw new RuntimeException('PREFLIGHT_IDENTITY');

        $stage = 'receipt_path';
        $semanticPath = $private . '/' . ANEX_EGYPT_248_APD_SEMANTIC_OPERATION . '.json';
        $lockPath = $semanticPath . '.lock';
        $receiptExists = is_file($semanticPath);
        $receiptStatus = null;
        if ($receiptExists && is_readable($semanticPath)) {
            try {
                $prior = json_decode((string) file_get_contents($semanticPath), true, 32, JSON_THROW_ON_ERROR);
                if (is_array($prior) && is_string($prior['status'] ?? null)
                    && in_array($prior['status'], ['reserved', 'unknown', 'completed'], true)) {
                    $receiptStatus = $prior['status'];
                } else {
                    $receiptStatus = 'other';
                }
            } catch (Throwable $ignored) {
                $receiptStatus = 'unreadable';
            }
        }
        $lockReady = is_file($lockPath) ? is_writable($lockPath) : is_writable($private);
        $out['receipt_path'] = [
            'semantic_receipt_exists' => $receiptExists,
            'semantic_receipt_status' => $receiptStatus,
            'lock_path_ready' => $lockReady,
            'private_dir_writable' => is_writable($private),
        ];
        if (!$lockReady || !is_writable($private)) throw new RuntimeException('PREFLIGHT_RECEIPT_PATH');

        $out['status'] = $receiptExists ? 'semantic_operation_already_present' : 'ready';
        $out['blocker_stage'] = null;
        $out['blocker_category'] = null;
        return $out;
    } catch (Throwable $ignored) {
        $out['blocker_stage'] = $stage;
        $out['blocker_category'] = anex_egypt_248_apd_preflight_category($stage);
        return $out;
    }
}

if (!defined('ANYTOUR_ANEX_EGYPT_248_APD_PREFLIGHT_LIBRARY_ONLY')) {
    $raw = file_get_contents('php://stdin', false, null, 0, 8192);
    try {
        $input = is_string($raw) ? json_decode($raw, true, 8, JSON_THROW_ON_ERROR) : null;
        if (!is_array($input)) throw new RuntimeException('PREFLIGHT_INPUT');
        $result = anex_egypt_248_apd_preflight_main($input);
    } catch (Throwable $ignored) {
        $result = anex_egypt_248_apd_preflight_main([]);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}
