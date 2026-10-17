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
final class Probe
{
    public static int $status = 200;
    public static int $errno = 0;
    public static string $body = '[]';
    public static int $calls = 0;
    public static int $decodes = 0;
}
function jwt() { return 'offline-fixture-token'; }
function curl_init($url) { ++Probe::$calls; return (object)['url' => $url]; }
function curl_setopt_array($handle, $options) { return true; }
function curl_exec($handle) { return Probe::$body; }
function curl_errno($handle) { return Probe::$errno; }
function curl_getinfo($handle, $option) { return Probe::$status; }
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

foreach (['query_string', 'short_text', 'tv_get'] as $function) {
    eval("namespace Search3GatewayErrorFixture;\n" . source_function($gateway, $function));
}

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
    ]) throw new RuntimeException('Existing connection error contract changed');
}
if (\Search3GatewayErrorFixture\Probe::$decodes !== 0) {
    throw new RuntimeException('Connection failure was decoded');
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
