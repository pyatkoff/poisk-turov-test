<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$raw = stream_get_contents(STDIN);
if (!is_string($raw) || $raw === '' || strlen($raw) > 4_194_304) {
    throw new RuntimeException('ANDROMEDA_RAW_TOURKEY_INPUT');
}
try {
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    throw new RuntimeException('ANDROMEDA_RAW_TOURKEY_JSON');
}
if (!is_array($payload) || !isset($payload['PRICES']) || !is_array($payload['PRICES']) || count($payload['PRICES']) > 5000) {
    throw new RuntimeException('ANDROMEDA_RAW_TOURKEY_SHAPE');
}

$id = static function(mixed $value): ?string {
    if (is_int($value)) $value = (string)$value;
    if (!is_string($value)) return null;
    $value = trim($value);
    return preg_match('/\A[1-9][0-9]{0,18}\z/D', $value) === 1 ? $value : null;
};
$operator = static function(mixed $value): string {
    if (is_int($value)) return (string)$value;
    if (is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', trim($value)) === 1) return trim($value);
    return '__missing_or_invalid__';
};

$overall = ['rows' => 0, 'with_tourKey' => 0, 'missing_tourKey' => 0, 'invalid_tourKey' => 0, 'distinct_tourKeys' => []];
$byOperator = [];
foreach ($payload['PRICES'] as $row) {
    if (!is_array($row)) throw new RuntimeException('ANDROMEDA_RAW_TOURKEY_ROW');
    ++$overall['rows'];
    $op = $operator($row['operatorKey'] ?? null);
    if (!isset($byOperator[$op])) {
        $byOperator[$op] = ['rows' => 0, 'with_tourKey' => 0, 'missing_tourKey' => 0, 'invalid_tourKey' => 0, 'distinct_tourKeys' => []];
    }
    ++$byOperator[$op]['rows'];

    if (!array_key_exists('tourKey', $row) || $row['tourKey'] === null || $row['tourKey'] === '') {
        ++$overall['missing_tourKey'];
        ++$byOperator[$op]['missing_tourKey'];
        continue;
    }
    $tourKey = $id($row['tourKey']);
    if ($tourKey === null) {
        ++$overall['invalid_tourKey'];
        ++$byOperator[$op]['invalid_tourKey'];
        continue;
    }
    ++$overall['with_tourKey'];
    ++$byOperator[$op]['with_tourKey'];
    $overall['distinct_tourKeys'][$tourKey] = true;
    $byOperator[$op]['distinct_tourKeys'][$tourKey] = true;
}

$finalize = static function(array $stats): array {
    $keys = array_keys($stats['distinct_tourKeys']);
    sort($keys, SORT_STRING);
    return [
        'rows' => $stats['rows'],
        'with_tourKey' => $stats['with_tourKey'],
        'missing_tourKey' => $stats['missing_tourKey'],
        'invalid_tourKey' => $stats['invalid_tourKey'],
        'distinct_tourKey_count' => count($keys),
        'tourKeys' => $keys,
    ];
};

ksort($byOperator, SORT_STRING);
$operators = [];
foreach ($byOperator as $key => $stats) $operators[$key] = $finalize($stats);

$out = [
    'version' => 2,
    'source' => 'raw_andromeda_price',
    'scope' => 'before_mapping_and_operator_filtering',
    'overall' => $finalize($overall),
    'operators' => $operators,
];
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
