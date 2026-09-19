<?php
declare(strict_types=1);

$script = $argv[1] ?? null;
if (!is_string($script) || !is_file($script)) throw new RuntimeException('TEST_SCRIPT');

$rows = [];
for ($i = 0; $i < 9; ++$i) $rows[] = ['operatorKey' => 115, 'tourKey' => 110211811];
for ($i = 0; $i < 7; ++$i) $rows[] = ['operatorKey' => '115', 'tourKey' => '110183689'];
for ($i = 0; $i < 6; ++$i) $rows[] = ['operatorKey' => 315, 'tourKey' => 110183696];
$rows[] = ['operatorKey' => 342];
$rows[] = ['operatorKey' => 342, 'tourKey' => ''];
$rows[] = ['operatorKey' => 5, 'tourKey' => 'abc'];
if (count($rows) !== 25) throw new RuntimeException('FIXTURE_COUNT');

$input = tempnam(sys_get_temp_dir(), 'atk');
$out = tempnam(sys_get_temp_dir(), 'ato');
file_put_contents($input, json_encode(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>$rows], JSON_THROW_ON_ERROR));
$cmd = 'php ' . escapeshellarg($script) . ' < ' . escapeshellarg($input) . ' > ' . escapeshellarg($out);
exec($cmd, $lines, $rc);
if ($rc !== 0) throw new RuntimeException('EXEC');
$d = json_decode(file_get_contents($out), true, 64, JSON_THROW_ON_ERROR);
unlink($input); unlink($out);
$ok = static function(bool $v, string $m): void { if (!$v) throw new RuntimeException($m); };
$ok($d['scope'] === 'before_mapping_and_operator_filtering', 'scope');
$ok($d['overall']['rows'] === 25, 'rows');
$ok($d['overall']['with_tourKey'] === 22, 'with');
$ok($d['overall']['missing_tourKey'] === 2, 'missing');
$ok($d['overall']['invalid_tourKey'] === 1, 'invalid');
$ok($d['overall']['distinct_tourKey_count'] === 3, 'distinct');
$ok($d['operators']['115']['rows'] === 16 && $d['operators']['115']['with_tourKey'] === 16, 'op115');
$ok($d['operators']['315']['rows'] === 6 && $d['operators']['315']['with_tourKey'] === 6, 'op315');
$ok($d['operators']['342']['rows'] === 2 && $d['operators']['342']['missing_tourKey'] === 2, 'op342');
$ok($d['operators']['5']['rows'] === 1 && $d['operators']['5']['invalid_tourKey'] === 1, 'op5');
$encoded = json_encode($d, JSON_THROW_ON_ERROR);
$ok(!str_contains($encoded, 'offer_') && !str_contains($encoded, 'session') && !str_contains($encoded, 'hotel'), 'private leakage');
echo "ANDROMEDA_RAW_TOURKEY_COVERAGE_V2_OK\n";
