<?php
declare(strict_types=1);

define('MATCH_PROVIDER_EVIDENCE_EXPORT_V2_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_evidence_current_export_v2.php';

function t2(bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$base = [
    'schema' => MATCH_PROVIDER_EVIDENCE_EXPORT_V2_SCHEMA,
    'state' => 'failed_no_replay',
    'database_writes' => 0,
    'mapping_writes' => 0,
    'supplier_calls' => 0,
    'tourvisor_calls' => 0,
    'samo_andromeda_calls' => 0,
];
$done = me_v2_complete_result($base, [
    'edge_count' => 2,
    'input_counts' => ['tourvisor_identity_rows' => 1],
]);
t2($done['state'] === 'completed_read_only', 'success must overwrite initial failed state');
t2($done['edge_count'] === 2, 'success payload retained');
foreach (['database_writes','mapping_writes','supplier_calls','tourvisor_calls','samo_andromeda_calls'] as $key) {
    t2((int)$done[$key] === 0, 'zero side effects preserved: ' . $key);
}

$edges = me_tv_edges([[
    'fingerprint' => str_repeat('a', 64),
    'first_seen_at' => '2026-09-17 00:00:00',
    'last_seen_at' => '2026-09-17 01:00:00',
    'observation_count' => 3,
    'source' => 'user_search',
    'country_id' => 4,
    'hotel_id' => 1221,
    'operator_id' => 25,
    'operator_name' => 'FUN&SUN',
    'native_id_type' => 'hotels',
    'native_id_value' => '30752',
    'native_id_conflict' => 0,
]]);
t2(count($edges) === 2, 'v1 normalization reused intact');
t2($edges[0]['authority'] === 'observation', 'passive remains observation');
t2($edges[1]['authority'] === 'direct', 'explicit safe native remains direct');

$tmp = tempnam(sys_get_temp_dir(), 'match-evidence-v2-');
t2($tmp !== false, 'temp input');
$out = $tmp . '.json';
file_put_contents($tmp, me_jsonl_bytes($edges));
$graph = escapeshellarg(__DIR__ . '/../scripts/diagnostics/hotel_match_provider_evidence_graph_v1.py');
$cmd = 'python3 ' . $graph . ' --input ' . escapeshellarg($tmp) . ' --output ' . escapeshellarg($out) . ' 2>&1';
exec($cmd, $lines, $status);
t2($status === 0, 'graph accepts v2 normalized bytes: ' . implode("\n", $lines));
$payload = json_decode((string)file_get_contents($out), true, 64, JSON_THROW_ON_ERROR);
t2(($payload['schema'] ?? '') === MATCH_PROVIDER_GRAPH_SCHEMA, 'graph schema parity');
t2((int)($payload['edge_count'] ?? -1) === 2, 'edge count parity');

@unlink($tmp);
@unlink($out);

echo "provider evidence current export v2 smoke PASS\n";
