<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-raw-catalog-schema.php';
require_once __DIR__ . '/../../app/integrations/andromeda-transport.php';

function aps_save(string $dir, string $name, array $data): void
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

function aps_id(array $rows, array $names): int
{
    $wanted = array_map(static fn($v) => mb_strtolower(trim((string)$v)), $names);
    $found = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        foreach (['name','lName','alias'] as $field) {
            $value = isset($row[$field]) ? mb_strtolower(trim((string)$row[$field])) : '';
            if ($value !== '' && in_array($value, $wanted, true)) { $found[] = $row; break; }
        }
    }
    $unique = [];
    foreach ($found as $row) $unique[(string)($row['id'] ?? '')] = $row;
    if (count($unique) !== 1) throw new RuntimeException('DICTIONARY_VALUE_MISSING_OR_AMBIGUOUS');
    $id = filter_var(array_key_first($unique), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false) throw new RuntimeException('DICTIONARY_ID_INVALID');
    return $id;
}

if (PHP_SAPI !== 'cli' || $argc !== 3 || $argv[1] !== '--execute') { fwrite(STDERR, "CAPTURE_DISABLED\n"); exit(2); }
$username = getenv('ANDROMEDA_USERNAME');
$password = getenv('ANDROMEDA_PASSWORD');
if (!is_string($username) || $username === '' || !is_string($password) || $password === '') { fwrite(STDERR, "CREDENTIALS_REQUIRED\n"); exit(2); }
$dir = $argv[2];
if (!is_dir($dir) || is_link($dir)) { fwrite(STDERR, "CAPTURE_DIRECTORY_REQUIRED\n"); exit(2); }
umask(0077);

$operation = 'andromeda-price-original-schema-egypt-3a-v3-20260914';
$report = ['state'=>'reserved','operation'=>$operation,'source'=>getenv('GITHUB_SHA') ?: null,'supplier_calls_max'=>6,'raw_values_persisted'=>false];
aps_save($dir, 'checkpoint', $report);

try {
    // The protocol client intentionally caps one instance at four supplier requests.
    // Resolve dictionary IDs within one bounded instance, then use a fresh authenticated
    // instance for the single PRICE request rather than weakening the shared client guard.
    $catalogClient = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(), true);
    $catalogClient->login($username, $password);
    $townfrom = $catalogClient->catalog('townfrom');
    $departure = aps_id($townfrom['TOWNFROM'], ['Москва']);
    $state = $catalogClient->catalog('state', ['TOWNFROMINC'=>$departure]);
    $country = aps_id($state['STATE'], ['Египет']);
    $all = $catalogClient->catalog('all', ['TOWNFROMINC'=>$departure,'STATEINC'=>$country]);
    $meal = aps_id($all['MEAL'], ['AI','All Inclusive','Все включено']);
    $operator = aps_id($all['OPERATORS'], ['ANEX','ANEX TOUR','Анекс Тур']);

    $params = [
        'TOWNFROMINC'=>$departure,'STATEINC'=>$country,
        'CHECKIN_BEG'=>'20261208','CHECKIN_END'=>'20261208',
        'NIGHTS_FROM'=>10,'NIGHTS_TILL'=>10,
        'ADULT'=>3,'CHILD'=>0,
        'CURRENCYINC'=>643,'MEAL'=>(string)$meal,'OPERATORS'=>(string)$operator,
        'PACKETTYPE'=>0,'PAGE'=>1,
    ];
    $priceClient = new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true), true);
    $priceClient->login($username, $password);
    $reply = $priceClient->price($params);
    $schema = anytour_andromeda_raw_catalog_schema($reply);

    // The previous completed PRICE-schema evidence proved that `original` is an object,
    // while first-level freightExternal/departureTimes are strings and expose no surcharge.
    // Persist only nested field names/types here; supplier values remain deliberately absent.
    $originalRows = [];
    foreach (array_slice($reply['PRICES'] ?? [], 0, 50) as $priceRow) {
        if (is_array($priceRow) && is_array($priceRow['original'] ?? null)) $originalRows[] = $priceRow['original'];
    }
    $originalSchema = anytour_andromeda_raw_catalog_schema(['rows'=>$originalRows]);

    $report = array_merge($report, [
        'state'=>'completed',
        'criteria'=>['date'=>'2026-12-08','nights'=>10,'party'=>'3a','meal'=>'AI','operator'=>'ANEX','page'=>1],
        'schema'=>$schema,
        'original_rows_schema'=>$originalSchema,
        'finished_at'=>gmdate('c'),
    ]);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $report = array_merge($report, [
        'state'=>'unknown',
        'error'=>preg_match('/^[A-Z0-9_]{1,100}$/D',$message) ? $message : 'CAPTURE_FAILED',
        'finished_at'=>gmdate('c'),
    ]);
}
aps_save($dir, 'result', $report);
echo json_encode(['state'=>$report['state'],'operation'=>$operation,'raw_values_persisted'=>false], JSON_THROW_ON_ERROR) . "\n";
exit($report['state'] === 'completed' ? 0 : 1);
