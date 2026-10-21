<?php
declare(strict_types=1);

final class Search3GatewayOutput extends RuntimeException
{
    public array $payload;
    public int $httpStatus;

    public function __construct(array $payload, int $httpStatus)
    {
        parent::__construct((string)($payload['error'] ?? 'gateway output'));
        $this->payload = $payload;
        $this->httpStatus = $httpStatus;
    }
}

function out($data, int $status = 200): void
{
    throw new Search3GatewayOutput((array)$data, $status);
}

function source_function(string $source, string $wanted): string
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $name = null;
        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token) && $token[0] === T_STRING) {
                $name = $token[1];
                break;
            }
            if ($token === '(') break;
        }
        if ($name !== $wanted) continue;

        $body = '';
        $depth = 0;
        $started = false;
        for ($j = $i; $j < $count; $j++) {
            $token = $tokens[$j];
            $text = is_array($token) ? $token[1] : $token;
            $body .= $text;
            if ($text === '{') {
                $started = true;
                $depth++;
            } elseif ($text === '}' && $started) {
                $depth--;
                if ($depth === 0) return $body;
            }
        }
    }
    throw new RuntimeException('Missing source function: ' . $wanted);
}

$gateway = file_get_contents(__DIR__ . '/../v2/api-v2.php');
if (!is_string($gateway)) throw new RuntimeException('Cannot read gateway source');

eval(source_function($gateway, 'strict_int'));
eval(source_function($gateway, 'search_results_limit'));

foreach ([25, 100, 101, 500, 1500, 5000] as $limit) {
    if (search_results_limit((string)$limit) !== $limit) {
        throw new RuntimeException('Valid limit was rewritten: ' . $limit);
    }
}

foreach ([0, -1, 5001, 'invalid', '100.5'] as $limit) {
    try {
        search_results_limit($limit);
        throw new RuntimeException('Invalid limit was accepted: ' . var_export($limit, true));
    } catch (Search3GatewayOutput $error) {
        if ($error->httpStatus !== 400 || ($error->payload['error'] ?? '') !== 'limit must be between 1 and 5000') {
            throw new RuntimeException('Invalid limit did not fail explicitly');
        }
    }
}

if (!str_contains($gateway, "search_results_limit(\$_GET['limit'] ?? 25)")) {
    throw new RuntimeException('search_results action does not use the validated limit owner');
}
if (str_contains($gateway, "bounded_int(\$_GET['limit'] ?? 25, 1, 100, 25)")) {
    throw new RuntimeException('Legacy silent 100 clamp is still active');
}

echo "SEARCH3_TOURVISOR_RESULTS_LIMIT_OK preserved=25,100,101,500,1500,5000 invalid=explicit-400\n";

/*
 * Isolated transport regression for the actual tv_get() source. Namespace stubs
 * win over any installed cURL extension, so this check cannot call a supplier.
 * A non-2xx body is deliberately forbidden from reaching the JSON decoder.
 */
eval(<<<'ERROR_FIXTURE'
namespace Search3GatewayErrorFixture;
const CURLOPT_RETURNTRANSFER = 1;
const CURLOPT_CONNECTTIMEOUT = 2;
const CURLOPT_TIMEOUT = 3;
const CURLOPT_HTTPHEADER = 4;
const CURLINFO_HTTP_CODE = 5;
const CURLINFO_TOTAL_TIME = 6;
final class Probe
{
    public static int $status = 200;
    public static int $errno = 0;
    public static string $body = '[]';
    public static int $calls = 0;
    public static int $decodes = 0;
    public static float $seconds = 1.234;
    public static string $url = '';
}
function jwt() { return 'offline-fixture-token'; }
function curl_init($url) { ++Probe::$calls; Probe::$url = $url; return (object)['url' => $url]; }
function curl_setopt_array($handle, $options) { return true; }
function curl_exec($handle) { return Probe::$body; }
function curl_errno($handle) { return Probe::$errno; }
function curl_getinfo($handle, $option) { return $option === CURLINFO_TOTAL_TIME ? Probe::$seconds : Probe::$status; }
function curl_close($handle) {}
function json_decode($body, $assoc)
{
    ++Probe::$decodes;
    if (Probe::$status < 200 || Probe::$status >= 300 || Probe::$errno !== 0) {
        throw new \RuntimeException('Non-success response reached the JSON decoder');
    }
    return \json_decode($body, $assoc);
}
function out($data, $status = 200) { throw new \Search3GatewayOutput((array)$data, $status); }
ERROR_FIXTURE);

foreach (['query_string', 'short_text', 'tv_failure_diagnostics', 'tv_get'] as $function) {
    eval("namespace Search3GatewayErrorFixture;\n" . source_function($gateway, $function));
}

// Repeated child ages are separate travellers at the actual query boundary.
// These namespaced cURL stubs cannot send a request, even with cURL installed.
foreach (['request_array', 'child_ages'] as $function) {
    eval("namespace Search3GatewayErrorFixture;\n" . source_function($gateway, $function));
}
$partyCases = [
    [[], [], 'adults=2'],
    [null, [], 'adults=2'],
    ['5', [5], 'adults=2&childs=5'],
    [['5', '5'], [5, 5], 'adults=2&childs=5&childs=5'],
    [[0, 0], [0, 0], 'adults=2&childs=0&childs=0'],
    [[0, 5, 5], [0, 5, 5], 'adults=2&childs=0&childs=5&childs=5'],
    [[17, 17, 17], [17, 17, 17], 'adults=2&childs=17&childs=17&childs=17'],
    [[5, 5, 5, 17], [5, 5, 5], 'adults=2&childs=5&childs=5&childs=5'],
    [['', 5, 5], [5, 5], 'adults=2&childs=5&childs=5'],
];
foreach ($partyCases as [$input, $expectedAges, $expectedQuery]) {
    $_GET = ['childs' => $input];
    $ages = \Search3GatewayErrorFixture\child_ages();
    if ($ages !== $expectedAges) throw new RuntimeException('Child traveller multiplicity changed');
    \Search3GatewayErrorFixture\Probe::$status = 200;
    \Search3GatewayErrorFixture\Probe::$errno = 0;
    \Search3GatewayErrorFixture\Probe::$body = '[]';
    \Search3GatewayErrorFixture\Probe::$calls = 0;
    \Search3GatewayErrorFixture\Probe::$decodes = 0;
    \Search3GatewayErrorFixture\tv_get('/tours/search', ['adults' => 2, 'childs' => $ages]);
    if (\Search3GatewayErrorFixture\Probe::$url !== 'https://api.tourvisor.ru/search/api/v1/tours/search?' . $expectedQuery
        || \Search3GatewayErrorFixture\Probe::$calls !== 1
        || \Search3GatewayErrorFixture\Probe::$decodes !== 1) {
        throw new RuntimeException('Supplier query lost a child or repeated transport');
    }
}
foreach ([[-1], [18], ['5.5'], ['invalid']] as $input) {
    $_GET = ['childs' => $input];
    \Search3GatewayErrorFixture\Probe::$calls = 0;
    try {
        \Search3GatewayErrorFixture\child_ages();
        throw new RuntimeException('Invalid child age accepted');
    } catch (Search3GatewayOutput $error) {
        if ($error->httpStatus !== 400
            || $error->payload !== ['ok' => false, 'error' => 'child age must be between 0 and 17']
            || \Search3GatewayErrorFixture\Probe::$calls !== 0) {
            throw new RuntimeException('Invalid child age did not stop before transport');
        }
    }
}
$_GET = ['hotelIds' => ['1', '1', '2']];
if (\Search3GatewayErrorFixture\request_array('hotelIds', 50) !== ['1', '2']) {
    throw new RuntimeException('Set filter deduplication changed');
}
$_GET = [];
echo 'SEARCH3_TOURVISOR_PARTY_TRANSPORT_OK cases=' . count($partyCases)
    . " invalid=4 filter_dedup=preserved supplier_http=0\n";

$errorCases = 0;
foreach ([429, 500, 503, 404, 302, 0, 700] as $status) {
    \Search3GatewayErrorFixture\Probe::$status = $status;
    \Search3GatewayErrorFixture\Probe::$errno = 0;
    // A valid large JSON error body must be ignored just like an empty one.
    \Search3GatewayErrorFixture\Probe::$body = '[{"detail":"' . str_repeat('x', 1048576) . '"}]';
    \Search3GatewayErrorFixture\Probe::$calls = 0;
    \Search3GatewayErrorFixture\Probe::$decodes = 0;
    try {
        \Search3GatewayErrorFixture\tv_get('/tours/search/OFFLINE', ['limit' => 25]);
        throw new RuntimeException('Non-success response did not terminate');
    } catch (Search3GatewayOutput $error) {
        $expected = ($status >= 400 && $status < 600) ? $status : 502;
        if ($error->httpStatus !== $expected || $error->payload !== [
            'ok' => false, 'error' => 'Tourvisor request failed', 'upstreamStatus' => $status,
            'diagnostics' => array_merge(['provider' => 'tourvisor', 'stage' => 'other', 'category' => 'http'],
                $status <= 599 ? ['httpStatus' => $status] : [], ['curlErrno' => 0, 'elapsedMs' => 1234]),
        ]) throw new RuntimeException('Existing upstream error contract changed');
    }
    if (\Search3GatewayErrorFixture\Probe::$decodes !== 0
        || \Search3GatewayErrorFixture\Probe::$calls !== 1) {
        throw new RuntimeException('Non-success response was decoded or transport repeated');
    }
    ++$errorCases;
}

\Search3GatewayErrorFixture\Probe::$status = 0;
\Search3GatewayErrorFixture\Probe::$errno = 28;
\Search3GatewayErrorFixture\Probe::$decodes = 0;
try {
    \Search3GatewayErrorFixture\tv_get('/tours/search/OFFLINE');
    throw new RuntimeException('Connection failure did not terminate');
} catch (Search3GatewayOutput $error) {
    if ($error->httpStatus !== 502 || $error->payload !== [
        'ok' => false, 'error' => 'Tourvisor connection error',
        'diagnostics' => ['provider' => 'tourvisor', 'stage' => 'other', 'category' => 'timeout',
            'httpStatus' => 0, 'curlErrno' => 28, 'elapsedMs' => 1234],
    ]) throw new RuntimeException('Existing connection error contract changed');
}
if (\Search3GatewayErrorFixture\Probe::$decodes !== 0) {
    throw new RuntimeException('Connection failure was decoded');
}

foreach (['/tours/search' => 'search_start', '/tours/search/123/status' => 'search_status',
    '/tours/search/123' => 'search_results', '/tours/search/123/continue' => 'search_continue',
    '/tours/exact-offer/flights' => 'flights', '/tours/exact-offer' => 'tour',
    '/unknown/private-token' => 'other'] as $path => $stage) {
    $facts = \Search3GatewayErrorFixture\tv_failure_diagnostics($path, 7, 0, 30.219);
    if ($facts !== ['provider' => 'tourvisor', 'stage' => $stage, 'category' => 'transport',
        'httpStatus' => 0, 'curlErrno' => 7, 'elapsedMs' => 30219]) {
        throw new RuntimeException('Safe failure stage/timing was lost');
    }
    if (str_contains(json_encode($facts), 'private-token') || str_contains(json_encode($facts), '123')) {
        throw new RuntimeException('Failure diagnostics disclosed path/identity');
    }
}
foreach ([NAN, INF, -1.0, 3601.0] as $seconds) {
    $facts = \Search3GatewayErrorFixture\tv_failure_diagnostics('/tours/search', 1000, 700, $seconds);
    if ($facts !== ['provider' => 'tourvisor', 'stage' => 'search_start', 'category' => 'transport']) {
        throw new RuntimeException('Invalid diagnostic numbers escaped bounds');
    }
}

$successCases = 0;
foreach (['[{"id":123,"price":"125500.00","currency":"RUB"}]', '[]', '{"progress":100}'] as $body) {
    \Search3GatewayErrorFixture\Probe::$status = 200;
    \Search3GatewayErrorFixture\Probe::$errno = 0;
    \Search3GatewayErrorFixture\Probe::$body = $body;
    \Search3GatewayErrorFixture\Probe::$decodes = 0;
    $actual = \Search3GatewayErrorFixture\tv_get('/tours/search/OFFLINE');
    if ($actual !== json_decode($body, true) || \Search3GatewayErrorFixture\Probe::$decodes !== 1) {
        throw new RuntimeException('Successful payload changed');
    }
    ++$successCases;
}

\Search3GatewayErrorFixture\Probe::$body = '{invalid';
\Search3GatewayErrorFixture\Probe::$decodes = 0;
if (\Search3GatewayErrorFixture\tv_get('/tours/search/OFFLINE') !== ['raw' => '{invalid']
    || \Search3GatewayErrorFixture\Probe::$decodes !== 1) {
    throw new RuntimeException('Existing malformed-success fallback changed');
}

echo 'SEARCH3_TOURVISOR_ERROR_RESPONSES_OK upstream=' . $errorCases
    . ' connection=1 success=' . $successCases . " malformed=1 supplier_http=0\n";

/*
 * The deployed root gateway can encounter a physical copy of helpers that its
 * canonical autosave dependency already loaded. Each case is a separate PHP
 * process, so a real redeclaration fatal is observable without killing this
 * test runner. No request is sent and the only DB stub throws before PDO/SQL.
 */
function search3_dependency_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function search3_dependency_remove(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (new DirectoryIterator($path) as $entry) {
            if (!$entry->isDot()) search3_dependency_remove($entry->getPathname());
        }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) {
        unlink($path);
    }
}

function search3_dependency_child(string $script, string $directory): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' . $directory . '/child-error.log', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $directory,
        ['PATH' => (string)getenv('PATH'), 'HOME' => $directory, 'ANYTOUR_LOCAL_TV_CATALOG_ENABLED' => '']
    );
    search3_dependency_assert(is_resource($process), 'Cannot start native PHP dependency fixture');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), (string)$stdout, (string)$stderr];
}

$dependencyRoot = sys_get_temp_dir() . '/search3-gateway-dependencies-' . bin2hex(random_bytes(8));
search3_dependency_assert(mkdir($dependencyRoot, 0700), 'Cannot create dependency fixture directory');
$dependencyCases = [
    'legacy-distinct-copies-fatal' => ['preload' => 'complete', 'root_marker' => false, 'db' => 'none', 'baseline' => true],
    'complete-canonical-root-disabled' => ['preload' => 'complete', 'root_marker' => false, 'db' => 'none', 'db_calls' => 0],
    'root-enabled-complete-db-reused' => ['preload' => 'complete', 'root_marker' => true, 'db' => 'complete', 'db_calls' => 1, 'caught' => true],
    'absent-db-loads-root-copy-without-config' => ['preload' => 'complete', 'root_marker' => true, 'db' => 'none', 'db_calls' => 0, 'caught' => true, 'root_db_loaded' => true],
    'canonical-marker-cannot-enable-root' => ['preload' => 'complete', 'root_marker' => false, 'canonical_marker' => true, 'db' => 'none', 'db_calls' => 0],
    'absent-helpers-load-root-copy' => ['preload' => 'none', 'root_marker' => false, 'db' => 'none', 'db_calls' => 0],
    'partial-hotel-helpers-caught' => ['preload' => 'partial', 'root_marker' => true, 'db' => 'complete', 'db_calls' => 0, 'caught' => true],
    'partial-db-helpers-caught' => ['preload' => 'complete', 'root_marker' => true, 'db' => 'partial', 'db_calls' => 0, 'caught' => true],
    'symlink-shared-helper-reused' => ['preload' => 'complete', 'root_marker' => false, 'db' => 'none', 'db_calls' => 0, 'symlink' => true],
];
$dependencyPassed = 0;
try {
    foreach ($dependencyCases as $name => $scenario) {
        $directory = $dependencyRoot . '/' . $name;
        $flat = $directory . '/root';
        $canonical = $directory . '/canonical/v2/data';
        search3_dependency_assert(mkdir($flat . '/data', 0700, true), 'Cannot create flat fixture');
        search3_dependency_assert(mkdir($canonical, 0700, true), 'Cannot create canonical fixture');
        foreach (['hotel-details-v1.php', 'db-v1.php'] as $file) {
            search3_dependency_assert(copy(__DIR__ . '/../v2/data/' . $file, $canonical . '/' . $file), 'Cannot copy canonical source');
            if ($file === 'hotel-details-v1.php' && !empty($scenario['symlink'])) {
                search3_dependency_assert(symlink($canonical . '/' . $file, $flat . '/data/' . $file), 'Cannot create shared helper symlink');
            } else {
                search3_dependency_assert(copy(__DIR__ . '/../v2/data/' . $file, $flat . '/data/' . $file), 'Cannot copy root source');
            }
        }
        search3_dependency_assert(copy(__DIR__ . '/../v2/data/local-tv-catalog-v1.php', $flat . '/data/local-tv-catalog-v1.php'), 'Cannot copy catalogue source');
        $marker = "{\"registry\":true,\"dailyHttpBudget\":0}\n";
        if (!empty($scenario['root_marker'])) file_put_contents($flat . '/data/local-tv-catalog-enabled.json', $marker);
        if (!empty($scenario['canonical_marker'])) file_put_contents($canonical . '/local-tv-catalog-enabled.json', $marker);
        $hook = "<?php\ndeclare(strict_types=1);\n" . source_function($gateway, 'tourvisor_autosave_results') . "\n";
        file_put_contents($flat . '/hook.php', $hook);
        $sourceFiles = [
            $flat . '/hook.php', $flat . '/data/local-tv-catalog-v1.php',
            $flat . '/data/hotel-details-v1.php', $flat . '/data/db-v1.php',
            $canonical . '/hotel-details-v1.php', $canonical . '/db-v1.php',
        ];
        $before = [];
        foreach ($sourceFiles as $file) $before[$file] = hash_file('sha256', $file);
        if (!empty($scenario['baseline'])) {
            // This is the prior unconditional include sequence, using the real
            // helper bytes in two distinct physical files rather than stubs.
            $program = "<?php\nrequire_once " . var_export($canonical . '/hotel-details-v1.php', true)
                . ";\nrequire_once " . var_export($flat . '/data/hotel-details-v1.php', true) . ";\n";
        } else {
            $program = "<?php\ndeclare(strict_types=1);\n\$scenario = " . var_export($scenario, true) . ";\n"
                . '$canonical = ' . var_export($canonical, true) . ";\n"
                . '$flat = ' . var_export($flat, true) . ";\n";
            $program .= <<<'DEPENDENCY_CHILD'
$GLOBALS['dependency_db_calls'] = 0;
if ($scenario['preload'] === 'complete') {
    require_once $canonical . '/hotel-details-v1.php';
} elseif ($scenario['preload'] === 'partial') {
    function v2_hotel_detail_object(array $payload): ?array { return null; }
}
if ($scenario['db'] !== 'none') {
    function v2_data_db(): PDO
    {
        ++$GLOBALS['dependency_db_calls'];
        throw new RuntimeException('Offline DB stop before PDO or SQL');
    }
    if ($scenario['db'] === 'complete') {
        function v2_data_db_config(): array { throw new RuntimeException('Unexpected config call'); }
        function v2_data_normalize_text(string $value): string { throw new RuntimeException('Unexpected normalization call'); }
        function v2_data_slug(string $value): string { throw new RuntimeException('Unexpected slug call'); }
    }
}
final class AnyTourTourvisorOfferAutosaveV1
{
    public static int $calls = 0;
    public static ?array $observed = null;
    public static function autosaveSearchResults(int $searchId, int $limit, array $response, DateTimeImmutable $now): void
    {
        ++self::$calls;
        self::$observed = ['search_id' => $searchId, 'limit' => $limit, 'response' => $response];
    }
}
require $flat . '/hook.php';
$_GET = [];
$_SERVER = [];
$response = [['id' => 101, 'tours' => [['id' => 'offline-tour', 'price' => '125500.00', 'currency' => 'RUB']]]];
$before = $response;
tourvisor_autosave_results(202610190001, 25, $response);
if ($response !== $before || AnyTourTourvisorOfferAutosaveV1::$calls !== 1
    || AnyTourTourvisorOfferAutosaveV1::$observed !== ['search_id' => 202610190001, 'limit' => 25, 'response' => $before]) {
    throw new RuntimeException('Hook changed the response or skipped/repeated existing offer autosave');
}
if ($GLOBALS['dependency_db_calls'] !== $scenario['db_calls']) {
    throw new RuntimeException('Unexpected DB call count before PDO/SQL');
}
$included = array_map('realpath', get_included_files());
$rootDbLoaded = in_array(realpath($flat . '/data/db-v1.php'), $included, true);
if ($rootDbLoaded !== !empty($scenario['root_db_loaded'])) {
    throw new RuntimeException('Original DB dependency loading differs from helper availability');
}
if ($rootDbLoaded) {
    $dbOwner = new ReflectionFunction('v2_data_db');
    if (realpath((string)$dbOwner->getFileName()) !== realpath($flat . '/data/db-v1.php')
        || v2_data_db_config() !== ['dsn' => '', 'user' => '', 'password' => '']) {
        throw new RuntimeException('Absent DB helpers did not retain the root owner without connection config');
    }
}
if ($scenario['preload'] !== 'partial') {
    if (LocalTvCatalogV1::enabled() !== $scenario['root_marker']) {
        throw new RuntimeException('Catalogue enabled marker moved away from its root owner');
    }
    $helper = new ReflectionFunction('v2_hotel_detail_object');
    $expected = $scenario['preload'] === 'none' ? $flat . '/data/hotel-details-v1.php' : $canonical . '/hotel-details-v1.php';
    if (realpath((string)$helper->getFileName()) !== realpath($expected)) {
        throw new RuntimeException('Hotel helper implementation owner changed');
    }
}
echo json_encode(['offer_autosave_calls' => AnyTourTourvisorOfferAutosaveV1::$calls,
    'db_calls' => $GLOBALS['dependency_db_calls'], 'response_unchanged' => true,
    'supplier_http' => 0, 'pdo_constructed' => 0, 'sql' => 0], JSON_THROW_ON_ERROR);
DEPENDENCY_CHILD;
        }
        $script = $directory . '/child.php';
        file_put_contents($script, $program);
        [$status, $stdout, $stderr] = search3_dependency_child($script, $directory);
        $errorLog = is_file($directory . '/child-error.log') ? (string)file_get_contents($directory . '/child-error.log') : '';
        if (!empty($scenario['baseline'])) {
            search3_dependency_assert($status !== 0 && str_contains($stderr . $errorLog, 'Cannot redeclare v2_hotel_detail_object'), $name . ': distinct-copy fatal was not reproduced');
        } else {
            search3_dependency_assert($status === 0, $name . ': guarded hook failed: ' . $stderr . $errorLog);
            search3_dependency_assert(json_decode($stdout, true, 512, JSON_THROW_ON_ERROR) === [
                'offer_autosave_calls' => 1, 'db_calls' => $scenario['db_calls'], 'response_unchanged' => true,
                'supplier_http' => 0, 'pdo_constructed' => 0, 'sql' => 0,
            ], $name . ': isolated hook receipt changed');
            search3_dependency_assert(str_contains($errorLog, 'LOCAL TV discovery could not be persisted') === !empty($scenario['caught']), $name . ': discovery failure was not contained as expected');
            search3_dependency_assert(!str_contains($stderr . $errorLog, 'Cannot redeclare'), $name . ': redeclaration still fatal');
        }
        foreach ($before as $file => $hash) {
            search3_dependency_assert(hash_file('sha256', $file) === $hash, $name . ': runtime source was modified');
        }
        foreach ([$flat . '/data/local-tv-catalog-enabled.json' => !empty($scenario['root_marker']),
            $canonical . '/local-tv-catalog-enabled.json' => !empty($scenario['canonical_marker'])] as $file => $exists) {
            search3_dependency_assert(is_file($file) === $exists && (!$exists || file_get_contents($file) === $marker), $name . ': catalogue marker changed');
        }
        ++$dependencyPassed;
    }
} finally {
    search3_dependency_remove($dependencyRoot);
}
echo 'SEARCH3_TOURVISOR_DEPENDENCY_REUSE_OK native_subprocesses=' . $dependencyPassed
    . " legacy_distinct_copy_fatal=1 supplier_http=0 pdo_constructed=0 sql=0\n";
