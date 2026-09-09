<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/integrations/andromeda-client.php';
require_once __DIR__ . '/../../app/integrations/andromeda-transport.php';

if (PHP_SAPI !== 'cli' || $argc !== 3 || $argv[1] !== '--execute') exit(2);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
umask(0077);

function save_probe_json(string $directory, string $name, array $value, bool $exclusive = false): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $path = $directory . '/' . $name . '.json';
    $flags = LOCK_EX | ($exclusive ? 0 : 0);
    if ($exclusive) {
        $handle = fopen($path, 'x');
        if ($handle === false) throw new RuntimeException('CHECKPOINT_WRITE_FAILED');
        try {
            if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('CHECKPOINT_WRITE_FAILED');
        } finally { fclose($handle); }
    } elseif (file_put_contents($path, $json, $flags) !== strlen($json)) {
        throw new RuntimeException('CHECKPOINT_WRITE_FAILED');
    }
    $readback = file_get_contents($path);
    if ($readback !== $json) throw new RuntimeException('CHECKPOINT_READBACK_FAILED');
    return hash('sha256', $json);
}

$out = $argv[2];
if (!is_dir($out) || is_link($out)) exit(2);
$username = getenv('ANDROMEDA_USERNAME');
$password = getenv('ANDROMEDA_PASSWORD');
if (!is_string($username) || $username === '' || !is_string($password) || $password === '') exit(2);

$params = AnyTourAndromedaClient::priceProbeParams();
$reservation = [
    'version' => 1,
    'state' => 'reserved',
    'operation' => 'one_saved_price_to_broninit',
    'params_sha256' => hash('sha256', json_encode($params, JSON_THROW_ON_ERROR)),
    'max_calls' => 3,
    'booking' => false,
    'calc' => false,
    'get_flights' => false,
    'automatic_retry' => false,
    'created_at' => gmdate('c'),
];
save_probe_json($out, 'reservation', $reservation, true);

$report = $reservation;
$phase = 'login';
try {
    $client = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true, true), true, true);
    $client->login($username, $password);
    $phase = 'price';
    $price = $client->price($params);
    if (!isset($price['PRICES'][0]['id']) || (!is_string($price['PRICES'][0]['id']) && !is_int($price['PRICES'][0]['id']))) {
        throw new RuntimeException('NO_PACKAGE_OFFER');
    }
    $offerId = (string) $price['PRICES'][0]['id'];
    if ($offerId === '' || strlen($offerId) > 4096) throw new RuntimeException('INVALID_PACKAGE_OFFER');
    $phase = 'broninit';
    $package = $client->package($offerId);
    $claim = $package['claimDocument'][0];
    $private = ['price_row' => $price['PRICES'][0], 'package' => $package];
    $privateJson = json_encode($private, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    foreach ([$username, $password, rawurlencode($username), rawurlencode($password)] as $secret) {
        if ($secret !== '' && strpos($privateJson, $secret) !== false) throw new RuntimeException('CREDENTIAL_ECHO');
    }
    $privateSha = save_probe_json($out, 'private-package', $private);
    $report += [
        'state' => 'captured',
        'price_rows' => count($price['PRICES']),
        'price_pages' => $price['PAGES_COUNT'],
        'price_id_sha256' => hash('sha256', $offerId),
        'catalog_key_sha256' => hash('sha256', (string) $claim['catalogKey']),
        'id_equals_catalog_key' => hash_equals($offerId, (string) $claim['catalogKey']),
        'claim_fields' => array_values(array_filter(array_keys($claim), 'is_string')),
        'private_package_sha256' => $privateSha,
        'selection_enabled' => false,
    ];
} catch (Throwable $error) {
    $report['state'] = 'unknown';
    $report['phase'] = $phase;
    $report['error'] = preg_match('/^[A-Z0-9_]{1,80}$/D', $error->getMessage()) ? $error->getMessage() : 'PACKAGE_PROBE_FAILED';
    $report['automatic_retry'] = false;
}
$report['finished_at'] = gmdate('c');
save_probe_json($out, 'result', $report);
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
exit($report['state'] === 'captured' ? 0 : 1);
