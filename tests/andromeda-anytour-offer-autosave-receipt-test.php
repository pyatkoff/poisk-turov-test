<?php
declare(strict_types=1);

/** Real runtime/consumer/producer and atomic save; only lookup/SQL boundaries are doubles. */
const RECEIPT_CASES = [
    'stored', 'missing_ingest', 'missing_partial', 'country_invalid', 'invalid_cohort',
    'mapping_failure', 'canonical_failure', 'unmapped', 'no_canonical', 'ingest_failure',
    'checkpoint_failure', 'write_failure', 'idempotent', 'second_page', 'expired',
    'unsafe_target', 'unsafe_directory', 'unbound_generation', 'invalid_ref', 'invalid_page',
    'missing_first', 'oversized_first', 'unsafe_first', 'closed_payload', 'complete_mode',
];

if (!isset($argv[1])) {
    foreach (RECEIPT_CASES as $case) {
        $process = proc_open([PHP_BINARY, __FILE__, $case], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('receipt subprocess unavailable');
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0 || trim($output) !== 'PASS ' . $case) {
            throw new RuntimeException('receipt case ' . $case . ': ' . $output . $errors);
        }
    }
    echo json_encode(['status' => 'passed', 'cases' => RECEIPT_CASES, 'case_count' => count(RECEIPT_CASES),
        'real_runtime_consumer_producer_atomic_save' => true, 'lookup_and_sql_boundaries' => 'test doubles',
        'supplier_http' => 0, 'live_db_reads' => 0, 'live_db_writes' => 0], JSON_THROW_ON_ERROR), "\n";
    exit;
}

$case = $argv[1];
if (!in_array($case, RECEIPT_CASES, true)) throw new InvalidArgumentException('unknown receipt case');
require_once dirname(__DIR__) . '/app/integrations/andromeda-anytour-offer-autosave.php';

function receipt_assert(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($GLOBALS['case'] . ': ' . $message);
}
function receipt_sql_error(string $state, int $driver): PDOException {
    $error = new PDOException('SELECT private_hotel_id FROM /private/secret-path password=secret-sentinel');
    $error->errorInfo = [$state, $driver, 'secret-sentinel'];
    return $error;
}
final class ReceiptStatement extends PDOStatement {
    public function __construct() {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $GLOBALS['case'] === 'no_canonical' ? [] : [['external_key' => '101', 'anytour_hotel_id' => '1001']];
    }
}
final class ReceiptPDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if ($GLOBALS['case'] === 'canonical_failure') throw receipt_sql_error('42S22', 1054);
        receipt_assert(str_contains($query, "s.namespace='legacy_catalog'"), 'unexpected lookup');
        return new ReceiptStatement();
    }
}
function anytour_andromeda_search3_current_mappings(PDO $db, int $country, array $offers): array {
    if ($GLOBALS['case'] === 'mapping_failure') throw receipt_sql_error('42S02', 1146);
    if ($GLOBALS['case'] === 'unmapped') return [];
    return [json_encode(['andromeda_catalog', '1001'], JSON_THROW_ON_ERROR) => 101];
}

// Execute the actual atomic cache writer, with failure injection only at its caller boundary.
$endpoint = file_get_contents(dirname(__DIR__) . '/v2/api-andromeda-search3-preview.php');
$start = strpos($endpoint, 'function anytour_andromeda_search3_save(');
$end = strpos($endpoint, '/** Owner-confirmed allowance:', $start);
receipt_assert($start !== false && $end !== false, 'atomic writer fixture unavailable');
eval(str_replace('function anytour_andromeda_search3_save(', 'function receipt_atomic_save(', substr($endpoint, $start, $end - $start)));
function anytour_andromeda_search3_save(string $path, array $value): bool {
    $diagnostic = str_ends_with($path, '-anytour-offer-autosave-result-v1.json');
    if ($diagnostic && $GLOBALS['case'] === 'write_failure') throw new RuntimeException('secret-sentinel');
    if (!$diagnostic && $GLOBALS['case'] === 'checkpoint_failure') return false;
    return receipt_atomic_save($path, $value);
}
function receipt_cleanup(string $root): void {
    foreach (new DirectoryIterator($root) as $item) {
        if ($item->isDot()) continue;
        $path = $item->getPathname();
        if ($item->isLink() || $item->isFile()) unlink($path); else receipt_cleanup($path);
    }
    rmdir($root);
}

$root = sys_get_temp_dir() . '/anytour-autosave-receipt-' . bin2hex(random_bytes(8));
$directory = $root . '/searches';
if (!mkdir($directory, 0700, true)) throw new RuntimeException('fixture directory');
ini_set('error_log', $root . '/error.log');
$ref = hash('sha256', 'private-receipt-test'); $generation = 7; $created = time() - ($case === 'expired' ? 1000 : 30);
$request = ['generation' => $generation, 'page' => 1, 'params' => [
    'departureId' => 1, 'countryId' => 1, 'dateFrom' => '2026-10-10', 'dateTo' => '2026-10-10',
    'nightsFrom' => 7, 'nightsTo' => 7, 'adults' => 2, 'childs' => [7], 'currency' => 'RUB',
]];
$offer = ['offer_ref' => 'offer_' . hash('sha256', 'private-offer'), 'operator' => 'FUN&SUN',
    'supplier_namespace' => 'andromeda_catalog', 'external_hotel_id' => '1001', 'local_hotel_id' => 101,
    'check_in' => '2026-10-10', 'nights' => 7, 'adults' => 2, 'children' => 1,
    'meal' => ['raw_label' => 'AI', 'label' => 'AI'], 'room_raw' => 'Deluxe Sea View',
    'placement_raw' => '2AD+1CH', 'price' => ['amount' => '185125', 'currency' => 'RUB']];
$first = ['status' => 'partial', 'search_ref' => $ref, 'generation' => $generation,
    'store' => ['version' => 1, 'search_ref' => $ref, 'generation' => $generation,
        'created_at' => $created, 'expires_at' => $created + 900,
        'snapshot' => ['provider' => 'andromeda', 'search_ref' => $ref, 'generation' => $generation,
            'page' => 1, 'pages_count' => 2, 'offers' => [$offer], 'rejected' => [], 'selection_enabled' => false]]];
$firstPath = $directory . '/' . $ref . '-1.json';
$page = 1; $ingestCalls = 0; $completeCalls = 0; $rows = [];

try {
    if ($case === 'invalid_cohort') $first['store']['snapshot']['selection_enabled'] = true;
    if ($case !== 'missing_first') receipt_atomic_save($firstPath, $first);
    if ($case === 'oversized_first') file_put_contents($firstPath, str_repeat('x', 3000001));
    if ($case === 'unsafe_first') { rename($firstPath, $root . '/outside.json'); symlink($root . '/outside.json', $firstPath); }
    if ($case === 'unsafe_directory') { rename($directory, $root . '/real-searches'); symlink($root . '/real-searches', $directory); }
    if ($case === 'unbound_generation') $request['generation'] = 8;
    if ($case === 'invalid_page') $request['page'] = '1';
    if ($case === 'country_invalid') $request['params']['countryId'] = 0;
    if ($case === 'complete_mode') { unset($request['page']); $page = null; }
    if ($case === 'second_page') {
        $request['page'] = $page = 2;
        $second = $first; $second['status'] = 'complete';
        $second['store']['created_at'] = $created + 10; $second['store']['expires_at'] = $created + 910;
        $second['store']['snapshot']['page'] = 2;
        receipt_atomic_save($directory . '/' . $ref . '-' . $created . '-2.json', $second);
    }
    $before = is_file($firstPath) ? file_get_contents($firstPath) : null;
    $receiptPath = $directory . '/' . $ref . '-' . $created . '-'
        . ($page === null ? 'complete' : 'page-' . $page) . '-anytour-offer-autosave-result-v1.json';
    $checkpointPath = $directory . '/' . $ref . '-' . $created . '-page-' . ($page ?? 1) . '-anytour-offer-autosave-v1.json';
    if ($case === 'unsafe_target') {
        file_put_contents($root . '/outside.txt', 'untouched'); symlink($root . '/outside.txt', $receiptPath);
    }
    $fixture = '<?php '; // A fresh process for each class-loading scenario.
    if ($case === 'missing_partial') $fixture .= 'final class AnyTourOfferSnapshotIngestV1 {}';
    elseif ($case !== 'missing_ingest') $fixture .= <<<'PHP'
final class AnyTourOfferSnapshotIngestV1 {
    public static function mergePartialSnapshot(PDO $db, string $provider, array $search, array $rows, DateTimeImmutable $at): array {
        ++$GLOBALS['ingestCalls']; $GLOBALS['rows'] = $rows;
        if ($GLOBALS['case'] === 'ingest_failure') throw receipt_sql_error('HY000', 1205);
        return ['offerCount' => count($rows)];
    }
    public static function replaceCompleteSnapshot(PDO $db, string $provider, array $search, array $rows, DateTimeImmutable $at): array {
        ++$GLOBALS['completeCalls']; throw new RuntimeException('unexpected complete replacement');
    }
}
PHP;
    file_put_contents($root . '/ingest.php', $fixture);
    putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE=' . $root . '/ingest.php');

    if ($case === 'closed_payload') {
        $result = ['published' => false, 'reason' => 'secret-sentinel', 'receivedOfferCount' => -1,
            'ownedOfferCount' => 5001, 'readyOfferCount' => '1', 'confirmationRequiredOfferCount' => 2,
            'params' => $request['params'], 'offer' => $offer, 'password' => 'secret-sentinel'];
        $error = receipt_sql_error('secret-sentinel', 100000);
        AnyTourAndromedaOfferAutosaveV1::recordResult($result, $request, $directory, $ref, $generation, 'secret-sentinel', $error);
    } else {
        $result = anytour_andromeda_anytour_offer_autosave_runtime($request, new ReceiptPDO(), [], $directory,
            $case === 'invalid_ref' ? '../invalid-ref' : $ref, $generation);
    }
    $reasons = ['missing_ingest' => 'local_ingest_unavailable', 'missing_partial' => 'partial_ingest_unavailable',
        'country_invalid' => 'country_invalid', 'unmapped' => 'no_current_mapped_offers',
        'no_canonical' => 'no_final_price_ready_resolved_offers', 'complete_mode' => 'cohort_incomplete',
        'unsafe_directory' => 'context_invalid', 'unbound_generation' => 'context_invalid',
        'invalid_ref' => 'context_invalid', 'invalid_page' => 'context_invalid', 'missing_first' => 'cohort_incomplete'];
    $failures = ['invalid_cohort', 'mapping_failure', 'canonical_failure', 'ingest_failure',
        'checkpoint_failure', 'expired', 'oversized_first', 'unsafe_first'];
    $published = in_array($case, ['stored', 'write_failure', 'idempotent', 'second_page', 'unsafe_target'], true);
    receipt_assert(($result['published'] ?? null) === $published, 'public/runtime published result changed');
    if (isset($reasons[$case])) receipt_assert($result['reason'] === $reasons[$case], 'rejection lost');
    if (in_array($case, $failures, true)) receipt_assert($result === ['published' => false, 'reason' => 'autosave_failed'], 'exception changed caller receipt');
    if ($case === 'idempotent') {
        $checkpointBytes = file_get_contents($checkpointPath);
        $again = anytour_andromeda_anytour_offer_autosave_runtime($request, new ReceiptPDO(), [], $directory, $ref, $generation);
        receipt_assert($again['reason'] === 'already_published' && $ingestCalls === 1
            && file_get_contents($checkpointPath) === $checkpointBytes, 'diagnostic became publication authority or replay');
    }
    receipt_assert($completeCalls === 0, 'partial path replaced whole snapshot');
    receipt_assert($ingestCalls === (in_array($case, ['stored', 'ingest_failure', 'checkpoint_failure', 'write_failure', 'idempotent', 'second_page', 'unsafe_target'], true) ? 1 : 0), 'ingest count changed');
    if ($published) receipt_assert(is_file($checkpointPath), 'diagnostic displaced success checkpoint');
    else receipt_assert(!is_file($checkpointPath), 'failure diagnostic masqueraded as success checkpoint');
    receipt_assert((is_file($firstPath) ? file_get_contents($firstPath) : null) === $before, 'retained state/observation changed');
    if ($case === 'unsafe_target') receipt_assert(is_link($receiptPath) && file_get_contents($root . '/outside.txt') === 'untouched', 'symlink target changed');
    $absent = in_array($case, ['write_failure', 'unsafe_target', 'unsafe_directory', 'unbound_generation',
        'invalid_ref', 'invalid_page', 'missing_first', 'oversized_first', 'unsafe_first'], true);
    if ($absent) receipt_assert(!is_file($receiptPath) || is_link($receiptPath), 'unsafe or unbound receipt written');
    else {
        $bytes = file_get_contents($receiptPath); $receipt = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        $keys = array_keys($receipt); sort($keys);
        $expectedKeys = ['version', 'provider', 'search_ref', 'generation', 'first_page_created_at', 'received_page',
            'snapshot_mode', 'recorded_at', 'published', 'reason', 'received_offer_count', 'owned_offer_count',
            'ready_offer_count', 'confirmation_required_offer_count', 'stage', 'error']; sort($expectedKeys);
        receipt_assert($keys === $expectedKeys && strlen($bytes) <= 2048, 'receipt schema or size expanded');
        receipt_assert((fileperms($receiptPath) & 0777) === 0600, 'receipt not private');
        receipt_assert($receipt['search_ref'] === $ref && $receipt['generation'] === $generation
            && $receipt['first_page_created_at'] === $created && $receipt['received_page'] === $page
            && $receipt['snapshot_mode'] === ($page === null ? 'complete_replace' : 'partial_additive'), 'receipt not cohort/page bound');
        foreach (['secret-sentinel', 'private_hotel_id', 'secret-path', $offer['offer_ref'], '185125', 'password', 'params'] as $forbidden) {
            receipt_assert(!str_contains($bytes, $forbidden), 'private payload or raw error escaped');
        }
        $stages = ['invalid_cohort' => 'consume', 'mapping_failure' => 'mapping', 'canonical_failure' => 'canonical_targets',
            'ingest_failure' => 'local_ingest', 'checkpoint_failure' => 'checkpoint', 'expired' => 'consume'];
        if (isset($stages[$case])) receipt_assert($receipt['stage'] === $stages[$case], 'exception stage lost');
        foreach (['mapping_failure' => ['42S02', 1146], 'canonical_failure' => ['42S22', 1054], 'ingest_failure' => ['HY000', 1205]] as $name => [$state, $driver]) {
            if ($case === $name) receipt_assert($receipt['error'] === ['type' => 'pdo', 'code' => 'unclassified',
                'sqlstate' => $state, 'driver_code' => $driver], 'safe SQL classification lost');
        }
        if ($case === 'checkpoint_failure') receipt_assert($receipt['error']['code'] === 'ANDROMEDA_ANYTOUR_CHECKPOINT_WRITE', 'checkpoint code lost');
        if ($case === 'closed_payload') receipt_assert($receipt['reason'] === 'unknown' && $receipt['stage'] === 'unknown'
            && $receipt['received_offer_count'] === null && $receipt['owned_offer_count'] === null
            && $receipt['ready_offer_count'] === null && $receipt['confirmation_required_offer_count'] === 2
            && $receipt['error'] === ['type' => 'pdo', 'code' => 'unclassified', 'sqlstate' => null, 'driver_code' => null], 'untrusted values admitted');
        if ($case === 'stored' || $case === 'second_page') {
            receipt_assert($receipt['published'] === true && $receipt['error'] === null
                && $receipt['ready_offer_count'] === 0 && $receipt['confirmation_required_offer_count'] === 1, 'published counters lost');
            receipt_assert($rows[0]['dto']['price'] === '185125' && $rows[0]['dto']['finalPriceReady'] === false
                && $rows[0]['dto']['context']['issued_at'] === $created + ($case === 'second_page' ? 10 : 0), 'price or observation authority changed');
        }
        if ($case === 'expired') receipt_assert($receipt['recorded_at'] >= $created + 900 && $receipt['first_page_created_at'] === $created, 'expired context renewed');
    }
    $log = is_file($root . '/error.log') ? file_get_contents($root . '/error.log') : '';
    receipt_assert(!str_contains($log, 'secret-sentinel') && !str_contains($log, 'SELECT private_hotel_id'), 'raw error remained in log');
    echo 'PASS ', $case, "\n";
} finally {
    putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE');
    receipt_cleanup($root);
}
