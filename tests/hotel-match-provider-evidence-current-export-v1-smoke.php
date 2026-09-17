<?php
declare(strict_types=1);

define('MATCH_PROVIDER_EVIDENCE_EXPORT_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_provider_evidence_current_export_v1.php';

function t(bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$tv = me_tv_edges([
    [
        'fingerprint' => str_repeat('a', 64), 'first_seen_at' => '2026-09-17 00:00:00', 'last_seen_at' => '2026-09-17 01:00:00',
        'observation_count' => 12, 'source' => 'user_search', 'country_id' => 4,
        'hotel_id' => 1221, 'operator_id' => 25, 'operator_name' => 'FUN&SUN',
        'native_id_type' => 'hotels', 'native_id_value' => '30752', 'native_id_conflict' => 0,
    ],
    [
        'fingerprint' => str_repeat('b', 64), 'first_seen_at' => '2026-09-17 00:00:00', 'last_seen_at' => '2026-09-17 01:00:00',
        'observation_count' => 2, 'source' => 'user_search', 'country_id' => 4,
        'hotel_id' => 2222, 'operator_id' => 99, 'operator_name' => 'Unknown operator',
        // Same numeric value is deliberately NOT identity authority for an unproved operator namespace.
        'native_id_type' => 'hotels', 'native_id_value' => '2222', 'native_id_conflict' => 0,
    ],
    [
        'fingerprint' => str_repeat('c', 64), 'first_seen_at' => '2026-09-17 00:00:00', 'last_seen_at' => '2026-09-17 01:00:00',
        'observation_count' => 4, 'source' => 'user_search', 'country_id' => 1,
        'hotel_id' => 3333, 'operator_id' => 43, 'operator_name' => 'Intourist',
        'native_id_type' => 'hotels', 'native_id_value' => '3333', 'native_id_conflict' => 1,
    ],
]);

t(count($tv) === 4, 'tv edge count');
t(count(array_filter($tv, fn($e) => $e['authority'] === 'observation')) === 3, 'all passive rows are observations');
$direct = array_values(array_filter($tv, fn($e) => $e['authority'] === 'direct'));
t(count($direct) === 1, 'only proven non-conflicting native row is direct');
t($direct[0]['source']['namespace'] === 'operator_315' && $direct[0]['source']['id'] === '30752', 'operator namespace mapping');
t($direct[0]['target']['namespace'] === 'tourvisor' && $direct[0]['target']['id'] === '1221', 'tourvisor target namespace');
t(count(array_filter($tv, fn($e) => $e['source']['namespace'] === 'operator_99')) === 0, 'numeric equality does not fabricate supplier identity');

$andromeda = me_andromeda_edges([
    [
        'supplier_namespace' => 'operator_315', 'external_hotel_id' => '30752', 'local_hotel_id' => '1221',
        'decision_status' => 'accepted', 'evidence_sha256' => str_repeat('d', 64), 'evidence_json' => '{}',
    ],
    [
        'supplier_namespace' => 'operator_342', 'external_hotel_id' => '25192', 'local_hotel_id' => null,
        'decision_status' => 'pending', 'evidence_sha256' => str_repeat('e', 64),
        'evidence_json' => json_encode(['provider_bridges' => [[
            'andromeda_hotel_id' => '2000073592', 'country_id' => 4, 'hotel_name' => 'Fixture Hotel',
        ]]], JSON_THROW_ON_ERROR),
    ],
    [
        'supplier_namespace' => 'operator_315', 'external_hotel_id' => '854061', 'local_hotel_id' => '99465',
        'decision_status' => 'conflict', 'evidence_sha256' => str_repeat('f', 64),
        'evidence_json' => json_encode(['review' => 'hold'], JSON_THROW_ON_ERROR),
    ],
]);

t($andromeda[0]['authority'] === 'accepted', 'accepted registry row remains accepted');
t($andromeda[0]['target']['namespace'] === 'tourvisor', 'accepted registry targets Tourvisor ID space');
$bridge = array_values(array_filter($andromeda, fn($e) => $e['evidence_type'] === 'andromeda_provider_bridge'));
t(count($bridge) === 1 && $bridge[0]['authority'] === 'corroboration', 'pending provider bridge is corroboration only');
t($bridge[0]['target']['namespace'] === 'andromeda_catalog', 'provider bridge target namespace');
$conflict = array_values(array_filter($andromeda, fn($e) => $e['polarity'] === 'conflict'));
$protect = array_values(array_filter($andromeda, fn($e) => $e['polarity'] === 'protect'));
t(count($conflict) === 1, 'conflict status becomes veto edge');
t(count($protect) === 1, 'protected review state becomes protect edge');

$anex = me_anex_mapping_edges([
    [
        'anex_hotel_id' => '10115', 'catalog_hotel_id' => '2160', 'match_class' => 'strong_candidate', 'scope' => 'preview',
        'approval_policy' => 'owner_exact_and_strong_20260908', 'source_row_digest' => str_repeat('1', 64),
        'mapping_digest' => str_repeat('2', 64), 'enabled' => 1,
    ],
    [
        'anex_hotel_id' => '99999', 'catalog_hotel_id' => '99999', 'match_class' => 'disabled', 'scope' => 'preview',
        'approval_policy' => 'none', 'source_row_digest' => str_repeat('3', 64), 'mapping_digest' => str_repeat('4', 64), 'enabled' => 0,
    ],
]);
t(count($anex) === 1, 'disabled ANEX mapping excluded');
t($anex[0]['authority'] === 'accepted' && $anex[0]['target']['namespace'] === 'tourvisor', 'enabled ANEX mapping accepted to Tourvisor');

$exclusions = me_anex_exclusion_edges([
    ['anex_hotel_id' => '1767', 'catalog_hotel_id' => '172'],
    ['anex_hotel_id' => 'bad', 'catalog_hotel_id' => '172'],
], 'anex_review_pair_exclusions', 'anex_pair_exclusion');
t(count($exclusions) === 1 && $exclusions[0]['authority'] === 'protected' && $exclusions[0]['polarity'] === 'protect', 'paired exclusion protected');

$edges = me_sort_dedupe_edges(array_merge($tv, $andromeda, $anex, $exclusions, $anex));
t(count($edges) === count(me_sort_dedupe_edges($edges)), 'dedupe idempotent');
$census = me_census($edges);
t(($census['by_authority']['accepted'] ?? 0) >= 2, 'accepted census');
t(($census['by_authority']['observation'] ?? 0) === 3, 'observation census');
t(($census['by_authority']['corroboration'] ?? 0) >= 2, 'corroboration census');

$tmp = tempnam(sys_get_temp_dir(), 'match-evidence-');
t($tmp !== false, 'temp file');
$out = $tmp . '.json';
file_put_contents($tmp, me_jsonl_bytes($edges));
$graph = escapeshellarg(__DIR__ . '/../scripts/diagnostics/hotel_match_provider_evidence_graph_v1.py');
$cmd = 'python3 ' . $graph . ' --input ' . escapeshellarg($tmp) . ' --output ' . escapeshellarg($out) . ' 2>&1';
exec($cmd, $lines, $status);
t($status === 0, 'merged graph accepts exporter JSONL: ' . implode("\n", $lines));
$payload = json_decode((string)file_get_contents($out), true, 64, JSON_THROW_ON_ERROR);
t(($payload['schema'] ?? '') === MATCH_PROVIDER_GRAPH_SCHEMA, 'graph schema parity');
t((int)($payload['edge_count'] ?? -1) === count($edges), 'graph edge count parity');

@unlink($tmp);
@unlink($out);

echo "provider evidence current export smoke PASS edges=" . count($edges) . "\n";
