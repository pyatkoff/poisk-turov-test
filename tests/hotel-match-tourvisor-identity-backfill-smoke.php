<?php
declare(strict_types=1);

function check_backfill(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$root = dirname(__DIR__);
$migration = (string)file_get_contents($root . '/v2/data/migrations/20260917-tour-operator-identity-v2.sql');
foreach ([
    'MODIFY tour_id VARCHAR(220) NULL',
    'MODIFY operator_link VARCHAR(2048) NULL',
    'ADD COLUMN native_id_type VARCHAR(40) NULL',
    'ADD COLUMN native_id_value VARCHAR(255) NULL',
    'ADD COLUMN native_id_conflict TINYINT(1) NOT NULL DEFAULT 0',
    'idx_operator_identity_native',
] as $needle) check_backfill(str_contains($migration, $needle), 'migration contract: ' . $needle);

$script = $root . '/scripts/diagnostics/hotel_match_tourvisor_identity_backfill_v1.php';
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --self-test 2>&1';
exec($cmd, $output, $status);
check_backfill($status === 0, 'backfill self-test failed: ' . implode("\n", $output));
check_backfill(str_contains(implode("\n", $output), 'PASS'), 'backfill PASS marker');

$observer = (string)file_get_contents($root . '/v2/data/operator-identity-observer-v1.php');
check_backfill(str_contains($observer, "'tourvisor|' . $hotelId . '|' . $operatorId"), 'stable link-independent fingerprint');
check_backfill(str_contains($observer, 'operator_link=COALESCE(VALUES(operator_link),operator_link)'), 'later link enrichment');
check_backfill(str_contains($observer, "'native_id_conflict'=>0"), 'native conflict evidence field');

echo "hotel_match_tourvisor_identity_backfill_smoke: PASS\n";
