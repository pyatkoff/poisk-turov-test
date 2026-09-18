<?php
declare(strict_types=1);

const M9_OP = 'hotel-match-mauritius-detail9-apply-1971-20260919-v2';
const M9_EXPECTED = [
    15640 => 11758,
    19015 => 11789,
    15490 => 11805,
    15779 => 15788,
    15788 => 26842,
    41420 => 28452,
    15637 => 44413,
    41477 => 60234,
    41416 => 106703,
];
const M9_COUNTRY = 27;
const M9_MATCH_CLASS = 'strong_candidate';
const M9_SCOPE = 'preview';
const M9_POLICY = 'owner_exact_and_strong_20260908';
const M9_SOURCE_RESULT_SHA = '042ef4e565d8059988140298f061d06ce83218d7264579fb14b36d60775923b1';

function m9_json(mixed $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function m9_put_new(string $path, array $value): string {
    $raw = m9_json($value) . "\n";
    $f = @fopen($path, 'x+b');
    if (!$f) throw new RuntimeException('durable_exists');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('durable_write');
        if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('durable_sync');
        rewind($f);
        if (stream_get_contents($f) !== $raw) throw new RuntimeException('durable_readback');
    } finally {
        fclose($f);
    }
    return hash('sha256', $raw);
}
function m9_rows(PDO $db, string $sql, array $params = []): array {
    $s = $db->prepare($sql);
    $s->execute(array_values($params));
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function m9_plan(string $path, string $expectedSha): array {
    if (!is_file($path)) throw new RuntimeException('plan_missing');
    $raw = (string)file_get_contents($path);
    if (hash('sha256', $raw) !== $expectedSha) throw new RuntimeException('plan_sha');
    $p = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($p) || ($p['operation'] ?? '') !== M9_OP || ($p['source_result_sha256'] ?? '') !== M9_SOURCE_RESULT_SHA) {
        throw new RuntimeException('plan_identity');
    }
    $pairs = $p['pairs'] ?? null;
    if (!is_array($pairs) || count($pairs) !== count(M9_EXPECTED)) throw new RuntimeException('plan_count');
    $seenA = []; $seenL = []; $normalized = [];
    foreach ($pairs as $r) {
        if (!is_array($r)) throw new RuntimeException('plan_row');
        $a = filter_var($r['anex_hotel_id'] ?? null, FILTER_VALIDATE_INT);
        $l = filter_var($r['catalog_hotel_id'] ?? null, FILTER_VALIDATE_INT);
        if ($a === false || $l === false || !isset(M9_EXPECTED[(int)$a]) || M9_EXPECTED[(int)$a] !== (int)$l) throw new RuntimeException('plan_pair');
        $a = (int)$a; $l = (int)$l;
        if (isset($seenA[$a]) || isset($seenL[$l])) throw new RuntimeException('plan_duplicate');
        $tokens = $r['signed_native_tokens'] ?? null;
        if (!is_array($tokens) || $tokens !== [$a]) throw new RuntimeException('plan_signed_tokens');
        $detailSha = $r['detail_sha256'] ?? '';
        $tourId = $r['tour_id'] ?? '';
        if (!is_string($detailSha) || !preg_match('/^[0-9a-f]{64}$/D', $detailSha)) throw new RuntimeException('plan_detail_sha');
        if (!is_string($tourId) || !preg_match('/^[1-9][0-9]{0,30}$/D', $tourId)) throw new RuntimeException('plan_tour_id');
        $seenA[$a] = true; $seenL[$l] = true;
        $normalized[$a] = [
            'anex_hotel_id'=>$a,
            'catalog_hotel_id'=>$l,
            'tour_id'=>$tourId,
            'detail_sha256'=>$detailSha,
            'signed_native_tokens'=>[$a],
        ];
    }
    ksort($normalized, SORT_NUMERIC);
    $expectedKeys = array_keys(M9_EXPECTED); sort($expectedKeys, SORT_NUMERIC);
    if (array_keys($normalized) !== $expectedKeys) throw new RuntimeException('plan_exact_set');
    return ['raw'=>$p, 'pairs'=>$normalized];
}

if (($argv[1] ?? '') === '--self-test') {
    $keys = array_keys(M9_EXPECTED); sort($keys, SORT_NUMERIC);
    if (count(M9_EXPECTED) !== 9 || $keys !== [15490,15637,15640,15779,15788,19015,41416,41420,41477]) throw new RuntimeException('manifest');
    if (M9_EXPECTED[15640] !== 11758 || M9_EXPECTED[41416] !== 106703 || M9_COUNTRY !== 27) throw new RuntimeException('manifest_values');
    if (M9_MATCH_CLASS !== 'strong_candidate' || M9_SCOPE !== 'preview' || M9_POLICY !== 'owner_exact_and_strong_20260908') throw new RuntimeException('contract');
    echo "M9_V2_SELFTEST_OK\n";
    exit(0);
}
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--execute') throw new RuntimeException('disabled');

$op = (string)getenv('OPERATION_ID');
$sourceSha = (string)getenv('MATCH_SOURCE_SHA');
$planPath = (string)getenv('MATCH_PLAN_PATH');
$planSha = (string)getenv('MATCH_PLAN_SHA256');
if ($op !== M9_OP || !preg_match('/^[0-9a-f]{40}$/D', $sourceSha) || !preg_match('/^[0-9a-f]{64}$/D', $planSha)) throw new RuntimeException('operation_guard');
$root = realpath((string)getenv('ANYTOUR_ROOT'));
if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('root_guard');
$plan = m9_plan($planPath, $planSha);
$base = rtrim((string)getenv('HOME'), '/') . '/.anytoour-match/operations';
if (!is_dir($base)) throw new RuntimeException('operations_root');
$dir = $base . '/' . M9_OP;
if (!@mkdir($dir, 0700)) throw new RuntimeException('operation_exists');
m9_put_new($dir.'/reservation.json', [
    'operation_id'=>M9_OP,
    'source_sha'=>$sourceSha,
    'state'=>'reserved_before_db_write',
    'plan_sha256'=>$planSha,
    'source_result_sha256'=>M9_SOURCE_RESULT_SHA,
    'expected_count'=>9,
    'supplier_calls'=>0,
    'no_replay'=>true,
]);

$db = null; $written = []; $held = []; $committed = false;
try {
    $dbFile = is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
    require_once $dbFile;
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $db->beginTransaction();

    $aids = array_keys(M9_EXPECTED);
    $lids = array_values(M9_EXPECTED);
    $ph = implode(',', array_fill(0, count($aids), '?'));
    $bothParams = array_merge($aids, $lids);
    $maps = m9_rows($db, "SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph) FOR UPDATE", $bothParams);
    $decisions = m9_rows($db, "SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph) FOR UPDATE", $bothParams);
    $exclusions = m9_rows($db, "SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph) OR catalog_hotel_id IN ($ph) FOR UPDATE", $bothParams);
    $hotels = m9_rows($db, "SELECT id,country_id,name,is_active,latitude,longitude FROM catalog_hotels WHERE id IN ($ph) FOR UPDATE", $lids);

    $byHotel = [];
    foreach ($hotels as $r) $byHotel[(int)$r['id']] = $r;
    $bySource = []; $byTarget = [];
    foreach ($maps as $r) {
        $bySource[(int)$r['anex_hotel_id']][] = $r;
        $byTarget[(int)$r['catalog_hotel_id']][] = $r;
    }
    $byExclusion = [];
    foreach ($exclusions as $r) $byExclusion[(int)$r['anex_hotel_id']][] = $r;
    $andromeda = [];
    foreach (m9_rows($db, "SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($ph)", $lids) as $r) {
        $andromeda[(int)$r['local_hotel_id']] = true;
    }

    $canonicalPlan = [
        'operation'=>M9_OP,
        'source_result_sha256'=>M9_SOURCE_RESULT_SHA,
        'match_class'=>M9_MATCH_CLASS,
        'scope'=>M9_SCOPE,
        'approval_policy'=>M9_POLICY,
        'pairs'=>array_values($plan['pairs']),
    ];
    $mappingDigest = hash('sha256', m9_json($canonicalPlan));
    $insert = $db->prepare('INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES (?,?,?,?,?,?,?,1)');

    foreach ($plan['pairs'] as $aid => $ev) {
        $lid = $ev['catalog_hotel_id'];
        $reasons = []; $same = false;
        foreach ($bySource[$aid] ?? [] as $r) {
            if ((int)$r['catalog_hotel_id'] === $lid) $same = true;
            else $reasons[] = 'source_mapped_other';
        }
        foreach ($byTarget[$lid] ?? [] as $r) {
            if ((int)$r['anex_hotel_id'] !== $aid) $reasons[] = 'target_occupied_other';
        }
        if ($same) $reasons[] = 'already_same';
        foreach ($decisions as $r) {
            $sameSource = (int)($r['anex_hotel_id'] ?? 0) === $aid;
            $sameTarget = isset($r['catalog_hotel_id']) && $r['catalog_hotel_id'] !== null && (int)$r['catalog_hotel_id'] === $lid;
            if ($sameSource || $sameTarget) { $reasons[] = 'manual_decision_present'; break; }
        }
        foreach ($byExclusion[$aid] ?? [] as $r) {
            if (isset($r['catalog_hotel_id']) && $r['catalog_hotel_id'] !== null && (int)$r['catalog_hotel_id'] === $lid) {
                $reasons[] = 'pair_excluded'; break;
            }
        }
        $hotel = $byHotel[$lid] ?? null;
        if (!$hotel || (int)$hotel['is_active'] !== 1 || (int)$hotel['country_id'] !== M9_COUNTRY) $reasons[] = 'target_not_active_expected_country';
        $reasons = array_values(array_unique($reasons));
        if ($reasons) {
            $held[] = ['anex_hotel_id'=>$aid,'catalog_hotel_id'=>$lid,'reasons'=>$reasons];
            continue;
        }

        $sourceRowDigest = hash('sha256', m9_json([
            'source_operation'=>'hotel-match-mauritius-retained-tour-detail-1971-20260919-v1',
            'source_result_sha256'=>M9_SOURCE_RESULT_SHA,
            'anex_hotel_id'=>$aid,
            'catalog_hotel_id'=>$lid,
            'tour_id'=>$ev['tour_id'],
            'detail_sha256'=>$ev['detail_sha256'],
            'signed_native_tokens'=>$ev['signed_native_tokens'],
            'authority'=>'direct_retained_tour_detail_hotellist',
        ]));
        $insert->execute([$aid,$lid,M9_MATCH_CLASS,M9_SCOPE,M9_POLICY,$sourceRowDigest,$mappingDigest]);
        $written[] = [
            'anex_hotel_id'=>$aid,
            'catalog_hotel_id'=>$lid,
            'source_row_digest'=>$sourceRowDigest,
            'mapping_digest'=>$mappingDigest,
            'creates_triple'=>isset($andromeda[$lid]),
        ];
    }

    m9_put_new($dir.'/precommit.json', [
        'operation_id'=>M9_OP,
        'source_sha'=>$sourceSha,
        'plan_sha256'=>$planSha,
        'mapping_digest'=>$mappingDigest,
        'written_intents'=>$written,
        'held'=>$held,
        'written_count'=>count($written),
        'held_count'=>count($held),
        'created_at'=>gmdate('c'),
    ]);
    $db->commit();
    $committed = true;

    $readback = [];
    foreach ($written as $w) {
        $rows = m9_rows($db, 'SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND catalog_hotel_id=?', [$w['anex_hotel_id'],$w['catalog_hotel_id']]);
        if (count($rows) !== 1) throw new RuntimeException('post_commit_row_count');
        $r = $rows[0];
        foreach (['match_class'=>M9_MATCH_CLASS,'scope'=>M9_SCOPE,'approval_policy'=>M9_POLICY,'enabled'=>1] as $k=>$v) {
            if ((string)($r[$k] ?? '') !== (string)$v) throw new RuntimeException('post_commit_'.$k);
        }
        if (($r['source_row_digest'] ?? '') !== $w['source_row_digest'] || ($r['mapping_digest'] ?? '') !== $w['mapping_digest']) throw new RuntimeException('post_commit_digest');
        $readback[] = $r;
    }

    $result = [
        'operation_id'=>M9_OP,
        'source_sha'=>$sourceSha,
        'status'=>'completed',
        'plan_sha256'=>$planSha,
        'source_result_sha256'=>M9_SOURCE_RESULT_SHA,
        'written_count'=>count($written),
        'held_count'=>count($held),
        'written'=>$written,
        'held'=>$held,
        'new_triples'=>count(array_filter($written, fn($x)=>$x['creates_triple'])),
        'post_commit_readback'=>$readback,
        'database_writes'=>count($written),
        'mapping_writes'=>count($written),
        'supplier_calls'=>0,
        'tourvisor_calls'=>0,
        'no_replay'=>true,
    ];
} catch (Throwable $e) {
    try { if ($db && $db->inTransaction()) $db->rollBack(); } catch (Throwable $ignored) {}
    $reason = preg_match('/^[A-Za-z0-9_.:-]+$/D', $e->getMessage()) ? $e->getMessage() : 'db_or_runtime_error';
    if ($committed) {
        $result = [
            'operation_id'=>M9_OP,
            'source_sha'=>$sourceSha,
            'status'=>'unknown_after_commit_no_replay',
            'reason_code'=>$reason,
            'committed_intents'=>$written,
            'held'=>$held,
            'database_writes'=>null,
            'mapping_writes'=>null,
            'supplier_calls'=>0,
            'tourvisor_calls'=>0,
            'no_replay'=>true,
        ];
    } else {
        $result = [
            'operation_id'=>M9_OP,
            'source_sha'=>$sourceSha,
            'status'=>'blocked_before_commit',
            'reason_code'=>$reason,
            'written_intents_before_failure'=>$written,
            'held'=>$held,
            'database_writes'=>0,
            'mapping_writes'=>0,
            'supplier_calls'=>0,
            'tourvisor_calls'=>0,
            'no_replay'=>true,
        ];
    }
}
$resultHash = m9_put_new($dir.'/result.json', $result);
m9_put_new($dir.'/receipt.json', [
    'operation_id'=>M9_OP,
    'source_sha'=>$sourceSha,
    'state'=>$result['status'],
    'result_sha256'=>$resultHash,
    'readback_verified'=>hash_file('sha256',$dir.'/result.json') === $resultHash,
    'written_count'=>$result['written_count'] ?? null,
    'mapping_writes'=>$result['mapping_writes'] ?? null,
    'no_replay'=>true,
]);
echo m9_json([
    'status'=>$result['status'],
    'written_count'=>$result['written_count'] ?? null,
    'held_count'=>$result['held_count'] ?? count($held),
    'new_triples'=>$result['new_triples'] ?? null,
    'result_sha256'=>$resultHash,
]) . "\n";
exit($result['status']==='completed' ? 0 : ($result['status']==='blocked_before_commit' ? 2 : 3));
