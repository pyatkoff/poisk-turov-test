<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-raw-catalog-schema.php';
require_once __DIR__ . '/../../app/integrations/andromeda-transport.php';

function transport_schema_capture_id(array $rows, string $name): int
{
    $found = array_values(array_filter($rows, static fn($row) => is_array($row) && trim((string)($row['name'] ?? '')) === $name));
    if (count($found) !== 1) throw new RuntimeException('DIRECTION_MISSING_OR_AMBIGUOUS');
    $id = filter_var($found[0]['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new RuntimeException('DIRECTION_ID_INVALID');
    return $id;
}

function transport_schema_capture_save(string $dir, string $name, array $data): void
{
    $raw = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    $path = $dir . '/' . $name . '.json';
    $f = @fopen($path, 'x+b');
    if (!$f) throw new RuntimeException('CAPTURE_EXISTS_OR_UNWRITABLE');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('CAPTURE_WRITE_FAILED');
        rewind($f);
        if (stream_get_contents($f) !== $raw) throw new RuntimeException('CAPTURE_READBACK_FAILED');
    } finally { fclose($f); }
}

if (PHP_SAPI !== 'cli' || $argc !== 3 || $argv[1] !== '--execute') { fwrite(STDERR, "CAPTURE_DISABLED\n"); exit(2); }
$username = getenv('ANDROMEDA_USERNAME');
$password = getenv('ANDROMEDA_PASSWORD');
if (!is_string($username) || $username === '' || !is_string($password) || $password === '') { fwrite(STDERR, "CREDENTIALS_REQUIRED\n"); exit(2); }
$dir = $argv[2];
if (!is_dir($dir) || is_link($dir)) { fwrite(STDERR, "CAPTURE_DIRECTORY_REQUIRED\n"); exit(2); }
umask(0077);

$operation = 'andromeda-transport-schema-egypt-v1-20260914';
$report = ['state'=>'reserved','operation'=>$operation,'source'=>getenv('GITHUB_SHA') ?: null,'supplier_calls_max'=>4,'raw_values_persisted'=>false];
transport_schema_capture_save($dir, 'checkpoint', $report);

try {
    $client = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(), true);
    $client->login($username, $password);
    $townfrom = $client->catalog('townfrom');
    $departure = transport_schema_capture_id($townfrom['TOWNFROM'], 'Москва');
    $state = $client->catalog('state', ['TOWNFROMINC'=>$departure]);
    $country = transport_schema_capture_id($state['STATE'], 'Египет');
    $schema = anytour_andromeda_discover_all_schema($client, $departure, $country);
    $report += ['state'=>'completed','schema'=>$schema,'finished_at'=>gmdate('c')];
} catch (Throwable $e) {
    $message = $e->getMessage();
    $report += ['state'=>'unknown','error'=>preg_match('/^[A-Z0-9_]{1,100}$/D',$message) ? $message : 'CAPTURE_FAILED','finished_at'=>gmdate('c')];
}
transport_schema_capture_save($dir, 'result', $report);
echo json_encode(['state'=>$report['state'],'operation'=>$operation,'raw_values_persisted'=>false], JSON_THROW_ON_ERROR) . "\n";
exit($report['state'] === 'completed' ? 0 : 1);
