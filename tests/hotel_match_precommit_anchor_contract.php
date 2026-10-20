<?php
declare(strict_types=1);
// Offline regression of the actual provider178 predicate; no PDO/network calls.
define('MATCH_UNIT_TEST', true);
$writer = getenv('MATCH_TEST_WRITER') ?: __DIR__ . '/../scripts/diagnostics/hotel_match_v9_provider178_write_v1.php';
require $writer;
$dir = getenv('MATCH_FIXTURE_DIR') ?: __DIR__ . '/../input';
$hashes = [
    'catalog-tail.json' => '4918cfb628799f11c2f0a22d7be2d38f695653877c183b01c6dd1df17f16b903',
    'precommit178.json' => '8cb977214acf20bd93226103d752119d0615bb49692aa95191ede30bec4a0d1d',
    'identities.json' => '2135ee882e58ad2524075897239556c683860bfef8cc5d7fe4db496a50244a1d',
    'hotels.json' => '70d5c41ba73979080723017759bd95a903667c67f8ffa571497560e93af9a011',
    'manual.json' => '7feabb1cb259bd9dc52180cdbdf4b0a85033de0c25293419b771e4f57c9ea056',
];
$docs = [];
foreach ($hashes as $name => $sha) {
    need(is_file($dir . '/' . $name) && hash_file('sha256', $dir . '/' . $name) === $sha, 'fixture_hash:' . $name);
    $docs[$name] = loadj($dir . '/' . $name);
}
$pre = $docs['precommit178.json'];
need($pre['current_core_safe'] === 178 && count($pre['rows']) === 178, 'real178');
[$keys, $targets] = indexed($docs['identities.json']['rows']);
$hotels = []; foreach ($docs['hotels.json']['rows'] as $r) $hotels[(int)$r['id']] = $r;
// Two explicitly dated, disjoint projections; never claim a same-time live snapshot.
foreach ($docs['catalog-tail.json']['rows'] as $r) { need(!isset($hotels[(int)$r['id']]), 'catalog_overlap'); $hotels[(int)$r['id']] = $r; }
$manual = []; foreach ($docs['manual.json']['rows'] as $r) if ($r['catalog_hotel_id'] !== null) $manual[(int)$r['catalog_hotel_id']] = true;
$legacy = in_array('--legacy', $argv, true);
$counts = ['real_predicate_matches' => 0, 'legacy_false_holds' => 0, 'hash_matches' => 0, 'mutations_rejected' => 0, 'missing_fields_rejected' => 0, 'guard_cases_passed' => 0, 'metadata_separation_passed' => 0];
$fields = ['supplier_namespace', 'external_hotel_id', 'local_hotel_id', 'decision_status', 'catalog_sha256', 'evidence_sha256', 'evidence_json'];
foreach ($pre['rows'] as $r) {
    $s = ['supplier_namespace' => $r['supplier_namespace'], 'native_hotel_id' => $r['native_hotel_id'], 'tv_hotel_id' => (int)$r['tv_hotel_id'], 'expected_target' => $r['current_target'], 'expected_anchor' => $r['canonical_anchor']];
    $id = $s['tv_hotel_id']; $ak = 'andromeda_catalog|' . $id;
    need(isset($hotels[$id]) && count($targets[$ak] ?? []) === 1, 'fixture_target_anchor');
    $a = $targets[$ak][0];
    $why = reason($s, $keys, $targets, $hotels, $manual);
    if ($legacy) { need($why === 'canonical_anchor_row_changed', 'legacy_not_reproduced'); $counts['legacy_false_holds']++; continue; }
    need($why === null, 'actual_predicate_rejected:' . (string)$why); $counts['real_predicate_matches']++;
    $expected = $s['expected_anchor']['row_sha256'];
    need(precommitAnchorHashV1($a) === $expected, 'actual_hash'); $counts['hash_matches']++;
    need(precommitAnchorHashV1(array_reverse($a, true)) === $expected, 'key_order');
    $b = $a; $b['created_at'] = 'changed-metadata';
    need(precommitAnchorHashV1($b) === $expected && rh($b) !== rh($a), 'separate_old_vs_current_contract');
    need(reason($s, $keys, [$ak => [$b]], $hotels, $manual) === null, 'extra_metadata_false_hold');
    $counts['metadata_separation_passed']++;
    foreach ($fields as $f) {
        $b = $a; $b[$f] = is_int($b[$f]) ? $b[$f] + 1 : (string)$b[$f] . '!';
        need(precommitAnchorHashV1($b) !== $expected, 'mutation_hash:' . $f);
        need(reason($s, $keys, [$ak => [$b]], $hotels, $manual) !== null, 'mutation_predicate:' . $f);
        $counts['mutations_rejected']++;
        $b = $a; unset($b[$f]); $rejected = false;
        try { precommitAnchorHashV1($b); } catch (RuntimeException) { $rejected = true; }
        need($rejected, 'missing_field:' . $f); $counts['missing_fields_rejected']++;
    }
    $cases = [];
    $k = $keys; $k[keyOf($s)] = ['decision_status' => 'rejected'];
    $cases[] = [reason($s, $k, $targets, $hotels, $manual), 'provider_source_present'];
    $cases[] = [reason($s, $keys, $targets, $hotels, $manual + [$id => true]), 'manual_target_protected'];
    $cases[] = [reason($s, $keys, [$ak => [$a, $a]], $hotels, $manual), 'canonical_anchor_not_unique'];
    $cases[] = [reason($s, $keys, [], $hotels, $manual), 'canonical_anchor_not_unique'];
    $t = $targets; $t[$s['supplier_namespace'] . '|' . $id] = [$a];
    $cases[] = [reason($s, $keys, $t, $hotels, $manual), 'provider_target_occupied'];
    $h = $hotels; $h[$id]['is_active'] = 0;
    $cases[] = [reason($s, $keys, $targets, $h, $manual), 'target_missing_or_inactive'];
    $h = $hotels; $h[$id]['name'] .= ' changed';
    $cases[] = [reason($s, $keys, $targets, $h, $manual), 'target_facts_changed'];
    $h = $hotels; $h[$id]['country_name'] = 'Россия';
    $cases[] = [reason($s, $keys, $targets, $h, $manual), 'excluded_country'];
    $bad = $s; $bad['expected_anchor']['row_sha256'] = rh($a);
    $cases[] = [reason($bad, $keys, $targets, $hotels, $manual), 'canonical_anchor_row_changed'];
    foreach ($cases as [$actual, $want]) { need($actual === $want, 'guard:' . $want); $counts['guard_cases_passed']++; }
}
if (!$legacy) {
    need($counts['real_predicate_matches'] === 178 && $counts['mutations_rejected'] === 1246 && $counts['missing_fields_rejected'] === 1246 && $counts['guard_cases_passed'] === 1602, 'coverage_counts');
    // Verify the retired executable refuses execution before even reading inputs.
    $pipes = [];
    $p = proc_open([PHP_BINARY, $writer, '--execute'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['MATCH_INPUT_DIR' => '/must-not-read']);
    need(is_resource($p), 'child_start');
    $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($p);
    need($code !== 0 && str_contains($stderr . $stdout, 'TERMINAL_OPERATION_EXECUTION_DISABLED'), 'terminal_execution_refusal');
}
echo json_encode(['state' => $legacy ? 'legacy_bug_reproduced' : 'offline_contract_verified', 'counts' => $counts, 'fixture_sha256' => $hashes, 'database_access' => 0, 'mapping_writes' => 0, 'provider_calls' => 0, 'not_write_authority' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
