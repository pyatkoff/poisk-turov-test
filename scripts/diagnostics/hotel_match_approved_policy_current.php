<?php
declare(strict_types=1);

// One-shot MATCH reader audit. No supplier calls, mapping writes or publication.
const HMAP_OPERATION = 'hotel-match-approved-policy-current-1971-20260912-v1';
const HMAP_CANDIDATE_SOURCE = '65feecaf9c858b8675ab8b14e25354df59622c29';
const HMAP_COHORT = [32731,32599,41882,32772,32562,32769,34078,35115,35688,32826,44411,35835,41114,29860,32734,29013,30716,30720,35869,33241,8803,29426,38133,32745,44573,32743,32880,32783,39601,45225,28882,28869,32616,17097,32572,44596,35275,32724,30424,32686,29000,45226,43984,44939,24105,16605,30538,34961,40360,44050,44139];

function hmap_json(array $data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}
function hmap_save(string $path, array $data): string {
    $raw = hmap_json($data);
    $handle = fopen($path, 'x');
    if ($handle === false) throw new RuntimeException('exclusive_receipt_failed');
    try {
        if (!chmod($path, 0600)) throw new RuntimeException('receipt_mode_failed');
        $offset = 0;
        while ($offset < strlen($raw)) {
            $written = fwrite($handle, substr($raw, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('receipt_write_failed');
            $offset += $written;
        }
        if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) throw new RuntimeException('receipt_flush_failed');
    } finally { fclose($handle); }
    if (file_get_contents($path) !== $raw) throw new RuntimeException('receipt_readback_failed');
    return hash('sha256', $raw);
}
function hmap_rows(PDO $db, string $sql): array {
    if (!str_starts_with($sql, 'SELECT ') || str_contains($sql, ';')) throw new RuntimeException('select_only');
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 50000) throw new RuntimeException('row_cap');
    return $rows;
}
function hmap_delta(array $rows, callable $before, callable $after): array {
    $delta = []; $changed = [];
    foreach ($rows as $row) {
        $id = (string)$row['anex_hotel_id'];
        $old = $before($id); $new = $after($id);
        if ($old !== null && $old !== $new) $changed[] = ['anex_hotel_id'=>$id,'before'=>$old,'after'=>$new];
        if ($old === null && $new !== null) {
            if ($new !== (int)$row['catalog_hotel_id'] || $new !== (int)$row['existing_catalog_hotel_id']) throw new RuntimeException('delta_target_mismatch');
            $delta[] = $row + ['before'=>null,'after'=>$new];
        }
    }
    return ['newly_visible_existing_rows'=>$delta,'previously_resolved_changed'=>$changed];
}
if (in_array('--self-test', $_SERVER['argv'] ?? [], true)) {
    $row = ['anex_hotel_id'=>7,'catalog_hotel_id'=>17,'existing_catalog_hotel_id'=>17];
    $null = static fn(string $id): ?int => null;
    $same = static fn(string $id): ?int => 17;
    $other = static fn(string $id): ?int => 18;
    $checks = [count(HMAP_COHORT) === 51, count(array_unique(HMAP_COHORT)) === 51,
        count(hmap_delta([$row], $null, $same)['newly_visible_existing_rows']) === 1,
        hmap_delta([$row], $same, $same)['previously_resolved_changed'] === [],
        count(hmap_delta([$row], $same, $other)['previously_resolved_changed']) === 1,
        count(hmap_delta([$row], $same, $null)['previously_resolved_changed']) === 1];
    try { hmap_delta([$row], $null, $other); $checks[] = false; }
    catch (RuntimeException $e) { $checks[] = $e->getMessage() === 'delta_target_mismatch'; }
    foreach ($checks as $ok) if (!$ok) throw new RuntimeException('self_test_failed');
    echo 'HMAP self-test PASS ' . count($checks) . "\n"; exit(0);
}

error_reporting(0); ob_start(); $db = null; $readTransaction = false; $dir = null; $stage = 'preflight';
try {
    if (PHP_SAPI !== 'cli' || !class_exists('AnyTourAnexSearchMappingRegistryCandidate1971', false)
        || !defined('HMAP_CANDIDATE_SHA256')) throw new RuntimeException('bundled_cli_required');
    $source = (string)getenv('MATCH_SOURCE_SHA');
    if (!preg_match('/^[a-f0-9]{40}$/D', $source)) throw new RuntimeException('source_sha_required');
    $root = realpath(getcwd()); $home = realpath((string)getenv('HOME'));
    if (!$root || basename($root) !== 'anytoour.ru' || !$home) throw new RuntimeException('root_guard');
    $base = $home . '/.anytoour-match/operations';
    $preview = $root . '/_preview/search3-anex-candidate';
    $app = is_file($preview . '/app/integrations/anex-search.php') ? $preview . '/app' : $root . '/app';
    $reader = $app . '/integrations/anex-search-mapping-registry.php';
    if (!is_dir($base) || is_link($base) || !is_file($reader) || is_link($reader)) throw new RuntimeException('existing_roots_required');
    $next = $base . '/' . HMAP_OPERATION;
    if (file_exists($next) || !mkdir($next, 0700)) throw new RuntimeException('operation_exists_no_replay');
    $dir = $next;
    hmap_save($dir . '/reservation.json', ['operation_id'=>HMAP_OPERATION,'source_sha'=>$source,
        'candidate_source_sha'=>HMAP_CANDIDATE_SOURCE,'candidate_sha256'=>HMAP_CANDIDATE_SHA256,
        'state'=>'reserved_before_db_access','read_only'=>true,'no_replay'=>true]);
    $stage = 'current_read';
    require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    require_once $reader;
    if (realpath((new ReflectionClass('AnyTourAnexSearchMappingRegistry'))->getFileName()) !== realpath($reader)) throw new RuntimeException('reader_path_mismatch');
    $db = v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY'); $readTransaction = true;
    $rows = hmap_rows($db, 'SELECT m.anex_hotel_id,m.catalog_hotel_id,m.enabled,m.scope,m.approval_policy,m.match_class,m.mapping_digest,m.source_row_digest,h.id AS existing_catalog_hotel_id,h.name,h.country_id,h.country_name,h.region_name,h.subregion_name,h.latitude,h.longitude,h.is_active FROM anex_hotel_search_mappings m LEFT JOIN catalog_hotels h ON h.id=m.catalog_hotel_id ORDER BY m.anex_hotel_id LIMIT 50001');
    $decisions = hmap_rows($db, 'SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001');
    $exclusions = hmap_rows($db, 'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001');
    $old = AnyTourAnexSearchMappingRegistry::fromPdo($db);
    $new = AnyTourAnexSearchMappingRegistryCandidate1971::fromPdo($db);
    $oldResolve = static fn(string $id): ?int => $old->resolve('anex_online', $id, 'preview');
    $newResolve = static fn(string $id): ?int => $new->resolve('anex_online', $id, 'preview');
    $delta = hmap_delta($rows, $oldResolve, $newResolve);
    $decisionChanges = [];
    foreach ($decisions as $row) {
        $id = (string)$row['anex_hotel_id'];
        if ($oldResolve($id) !== $newResolve($id)) $decisionChanges[] = $id;
    }
    $cohort = []; $unresolvedBefore = 0; $unresolvedAfter = 0;
    foreach (HMAP_COHORT as $id) {
        $a = $oldResolve((string)$id); $b = $newResolve((string)$id);
        $unresolvedBefore += (int)($a === null); $unresolvedAfter += (int)($b === null);
        $cohort[] = ['anex_hotel_id'=>$id,'before'=>$a,'after'=>$b];
    }
    $db->exec('ROLLBACK'); $readTransaction = false; $db = null;
    $safe = $delta['previously_resolved_changed'] === [] && $decisionChanges === [];
    $out = ['operation_id'=>HMAP_OPERATION,'status'=>'read_only_complete','source_sha'=>$source,
        'candidate_source_sha'=>HMAP_CANDIDATE_SOURCE,'candidate_sha256'=>HMAP_CANDIDATE_SHA256,
        'deployed_reader_relative_path'=>substr($reader,strlen($root)),
        'deployed_reader_sha256'=>hash_file('sha256',$reader),'generated_at_utc'=>gmdate('c'),
        'mapping_rows'=>count($rows),'current_resolved_count'=>$old->count(),'candidate_resolved_count'=>$new->count(),
        'newly_visible_count'=>count($delta['newly_visible_existing_rows']),'comparison'=>$delta,
        'manual_decisions_count'=>count($decisions),'manual_resolution_changes'=>$decisionChanges,
        'manual_sha256'=>hash('sha256',hmap_json($decisions)),'exclusions_count'=>count($exclusions),
        'exclusions_sha256'=>hash('sha256',hmap_json($exclusions)),
        'cohort_null_before'=>$unresolvedBefore,'cohort_null_after'=>$unresolvedAfter,'cohort'=>$cohort,
        'existing_identity_preserved'=>$safe,'identity_correctness_not_proved_by_readback'=>true,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'preview_published'=>false,'no_replay'=>true];
    $stage = 'receipt';
    $digest = hmap_save($dir . '/result.json', $out);
    hmap_save($dir . '/receipt.json', ['operation_id'=>HMAP_OPERATION,'state'=>'read_only_complete',
        'result_sha256'=>$digest,'readback_verified'=>true,'database_writes'=>0,'no_replay'=>true]);
    ob_end_clean(); echo 'HMAP_JSON:' . hmap_json($out); exit($safe ? 0 : 2);
} catch (Throwable $error) {
    if ($db && $readTransaction) { try { $db->exec('ROLLBACK'); } catch (Throwable $ignored) {} }
    $out = ['operation_id'=>HMAP_OPERATION,'status'=>'failed','stage'=>$stage,'reason'=>in_array($error->getMessage(), ['bundled_cli_required','source_sha_required','root_guard','existing_roots_required','operation_exists_no_replay','reader_path_mismatch','row_cap','delta_target_mismatch','receipt_readback_failed','exclusive_receipt_failed','receipt_mode_failed','receipt_write_failed','receipt_flush_failed'],true) ? $error->getMessage() : 'audit_stopped',
        'error_class'=>get_class($error),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'preview_published'=>false,'no_replay'=>true];
    if ($dir) { try { hmap_save($dir . '/failure.json',$out); } catch (Throwable $ignored) {} }
    ob_end_clean(); echo 'HMAP_JSON:' . hmap_json($out); exit(2);
}
