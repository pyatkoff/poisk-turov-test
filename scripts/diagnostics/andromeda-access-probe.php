<?php
declare(strict_types=1);

// Deliberately not wired to any public endpoint or deployment workflow.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('zend.exception_ignore_args', '1');
ini_set('display_errors', '0');
require __DIR__ . '/../../app/integrations/andromeda-client.php';
require __DIR__ . '/../../app/integrations/andromeda-transport.php';

if ($argc !== 3 || $argv[1] !== '--execute') {
    echo "ANDROMEDA_PROBE_DISABLED: --execute <new-reservation-path> required\n";
    exit(2);
}
$username = getenv('ANDROMEDA_USERNAME');
$password = getenv('ANDROMEDA_PASSWORD');
if (!is_string($username) || $username === '' || !is_string($password) || $password === '') {
    echo "ANDROMEDA_CREDENTIALS_REQUIRED\n";
    exit(2);
}
// Fail before reservation/network if cURL is unavailable.
if (!function_exists('curl_init')) { echo "ANDROMEDA_CURL_REQUIRED\n"; exit(2); }
umask(0077);
$file = @fopen($argv[2], 'x+b');
if ($file === false) { echo "ANDROMEDA_RESERVATION_EXISTS_OR_UNWRITABLE\n"; exit(2); }
$write = static function (array $state) use ($file): void {
    $json = json_encode($state, JSON_THROW_ON_ERROR) . "\n";
    if (!rewind($file) || !ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
        throw new RuntimeException('CHECKPOINT_WRITE_FAILED');
    }
};
try {
    $write(['state' => 'reserved', 'actions' => ['login', 'townfrom'], 'created_at' => gmdate('c')]);
    $client = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(), true);
    $client->login($username, $password);
    unset($username, $password);
    $result = $client->catalog('townfrom');
    $report = ['state' => 'completed', 'actions' => ['login', 'townfrom'],
        'departure_count' => count($result['TOWNFROM']), 'finished_at' => gmdate('c')];
    $write($report);
    rewind($file);
    if (json_decode(stream_get_contents($file), true) !== $report) throw new RuntimeException('CHECKPOINT_READBACK_FAILED');
    echo json_encode($report, JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $ignored) {
    // Unknown execution is never replayed automatically; no supplier/exception text.
    try { $write(['state' => 'interrupted_result_unknown', 'retry' => false]); } catch (Throwable $ignoredWrite) {}
    echo "ANDROMEDA_PROBE_FAILED_RESULT_UNKNOWN\n";
    fclose($file);
    exit(1);
}
fclose($file);
