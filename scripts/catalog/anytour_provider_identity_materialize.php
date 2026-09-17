<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function op_fail(string $code): never
{
    fwrite(STDERR, "ANYTOUR_PROVIDER_IDENTITY_OPERATION_FAILED code={$code}\n");
    exit(1);
}

function op_json(array $value): string
{
    ksort($value, SORT_STRING);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function op_tuple(string $namespace, string $external): string
{
    return $namespace . "\0" . $external;
}

function op_namespace(mixed $value): string
{
    if (!is_string($value) || !preg_match('/\A[a-z0-9_]{1,64}\z/D', $value)) op_fail('INVALID_SUPPLIER_NAMESPACE');
    return $value;
}

function op_external(mixed $value): string
{
    if ((!is_string($value) && !is_int($value)) || is_bool($value)) op_fail('INVALID_EXTERNAL_ID');
    $value = trim((string)$value);
    if ($value === '' || strlen($value) > 120 || preg_match('/[\x00-\x1F\x7F:]/', $value)) op_fail('INVALID_EXTERNAL_ID');
    return $value;
}

function op_positive_int(mixed $value): int
{
    if (is_int($value) && $value > 0) return $value;
    if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value)
        && filter_var($value, FILTER_VALIDATE_INT) !== false) return (int)$value;
    op_fail('INVALID_LOCAL_ID');
}

function op_db_files(): void
{
    $siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
    $helper = $siteRoot !== '' ? $siteRoot . '/data/db-v1.php' : __DIR__ . '/../../v2/data/db-v1.php';
    $bridge = trim((string)getenv('ANYTOUR_PROVIDER_BRIDGE_FILE'));
    if ($bridge === '') $bridge = __DIR__ . '/../../v2/data/anytour-provider-identity-bridge-v1.php';
    if (!is_file($helper) || !is_file($bridge)) op_fail('RUNTIME_FILES_MISSING');
    require_once $helper;
    require_once $bridge;
}

/** @return array{receipt:array,resolvable:list<array{supplier_namespace:string,external_hotel_id:string}>} */
function op_plan(PDO $db, string $releaseSha): array
{
    $query = $db->query("SELECT supplier_namespace,CAST(external_hotel_id AS CHAR) AS external_hotel_id,local_hotel_id
        FROM andromeda_hotel_identities
        WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL
        ORDER BY supplier_namespace,external_hotel_id,id");
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 50000) op_fail('ACCEPTED_SET_TOO_LARGE');

    $tuples = [];
    foreach ($rows as $row) {
        $namespace = op_namespace($row['supplier_namespace'] ?? null);
        $external = op_external($row['external_hotel_id'] ?? null);
        $local = op_positive_int($row['local_hotel_id'] ?? null);
        $key = op_tuple($namespace, $external);
        if (!array_key_exists($key, $tuples)) {
            $tuples[$key] = ['supplier_namespace'=>$namespace,'external_hotel_id'=>$external,'local_hotel_id'=>$local,'count'=>1];
        } else {
            ++$tuples[$key]['count'];
            if ($tuples[$key]['local_hotel_id'] !== $local) $tuples[$key]['local_hotel_id'] = 0;
        }
    }
    ksort($tuples, SORT_STRING);

    $unique = [];
    $ambiguous = [];
    $localIds = [];
    foreach ($tuples as $key => $tuple) {
        if ($tuple['count'] !== 1 || $tuple['local_hotel_id'] < 1) {
            $ambiguous[] = $key;
            continue;
        }
        $unique[$key] = $tuple;
        $localIds[$tuple['local_hotel_id']] = true;
    }

    $targets = [];
    foreach (array_chunk(array_keys($localIds), 500) as $chunk) {
        if ($chunk === []) continue;
        $sql = "SELECT CAST(s.external_key AS CHAR) AS legacy_id,s.anytour_hotel_id
            FROM anytour_hotel_sources s JOIN anytour_hotels h ON h.id=s.anytour_hotel_id
            WHERE s.namespace='legacy_catalog' AND h.is_active=1 AND s.external_key IN ("
            . implode(',', array_fill(0, count($chunk), '?')) . ') ORDER BY s.external_key,s.anytour_hotel_id';
        $stmt = $db->prepare($sql); $stmt->execute(array_map('strval', $chunk));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $legacy = op_positive_int($row['legacy_id'] ?? null);
            $own = op_positive_int($row['anytour_hotel_id'] ?? null);
            $targets[$legacy] = array_key_exists($legacy, $targets) ? 0 : $own;
        }
    }

    $resolvable = [];
    $planRows = [];
    $digests = [];
    $unresolved = 0;
    foreach ($unique as $tuple) {
        $local = $tuple['local_hotel_id'];
        $own = $targets[$local] ?? 0;
        $providerRef = $tuple['supplier_namespace'] . ':' . $tuple['external_hotel_id'];
        $digest = AnyTourProviderIdentityBridgeV1::providerRefDigest($providerRef);
        $planRows[] = [$tuple['supplier_namespace'],$tuple['external_hotel_id'],$local,$own,$digest];
        if ($own > 0) {
            $resolvable[] = ['supplier_namespace'=>$tuple['supplier_namespace'],'external_hotel_id'=>$tuple['external_hotel_id']];
            $digests[$digest] = $own;
        } else {
            ++$unresolved;
        }
    }

    $directExact = 0; $directConflicts = 0;
    foreach (array_chunk(array_keys($digests), 500) as $chunk) {
        if ($chunk === []) continue;
        $sql = "SELECT CAST(external_key AS CHAR) AS external_key,anytour_hotel_id,acquired_via
            FROM anytour_hotel_sources WHERE namespace='provider_ref_digest:andromeda' AND external_key IN ("
            . implode(',', array_fill(0, count($chunk), '?')) . ')';
        $stmt = $db->prepare($sql); $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $digest = (string)$row['external_key'];
            $expectedOwn = $digests[$digest] ?? 0;
            if ($expectedOwn > 0 && (int)$row['anytour_hotel_id'] === $expectedOwn
                && ($row['acquired_via'] ?? null) === 'match_accepted_bridge') ++$directExact;
            else ++$directConflicts;
        }
    }

    $hashBasis = [
        'release_sha'=>$releaseSha,
        'accepted_rows'=>count($rows),
        'ambiguous_refs'=>count($ambiguous),
        'rows'=>$planRows,
    ];
    $planSha = hash('sha256', op_json($hashBasis));
    $receipt = [
        'schema_version'=>1,
        'operation'=>'anytour-provider-identity-materialize',
        'provider'=>'andromeda',
        'mode'=>'plan',
        'release_sha'=>$releaseSha,
        'plan_sha256'=>$planSha,
        'accepted_rows'=>count($rows),
        'unique_accepted_refs'=>count($unique),
        'ambiguous_accepted_refs'=>count($ambiguous),
        'resolvable_refs'=>count($resolvable),
        'unresolved_refs'=>$unresolved,
        'direct_exact_before'=>$directExact,
        'direct_conflicts'=>$directConflicts,
        'mapping_writes'=>0,
        'supplier_calls'=>0,
        'profile_writes'=>0,
        'legacy_source_writes'=>0,
        'site_file_writes'=>0,
        'apply_blocked'=>$directConflicts > 0,
    ];
    return ['receipt'=>$receipt,'resolvable'=>$resolvable];
}

try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(mode|release-sha|expected-plan-sha)=(.+)$/D', $arg, $m) || isset($options[$m[1]])) {
            op_fail('USAGE');
        }
        $options[$m[1]] = $m[2];
    }
    $mode = $options['mode'] ?? '';
    $releaseSha = $options['release-sha'] ?? '';
    if (!in_array($mode, ['plan','apply'], true) || !preg_match('/\A[a-f0-9]{40}\z/D', $releaseSha)) op_fail('USAGE');
    $runtimeSha = trim((string)getenv('ANYTOUR_OPERATION_RELEASE_SHA'));
    if ($runtimeSha === '' || !hash_equals($runtimeSha, $releaseSha)) op_fail('RELEASE_SHA_MISMATCH');
    if ($mode === 'plan' && isset($options['expected-plan-sha'])) op_fail('PLAN_HAS_EXPECTED_HASH');
    if ($mode === 'apply' && (!isset($options['expected-plan-sha']) || !preg_match('/\A[a-f0-9]{64}\z/D', $options['expected-plan-sha']))) op_fail('APPLY_PLAN_HASH_REQUIRED');

    op_db_files();
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $before = op_plan($db, $releaseSha);
    if ($mode === 'plan') {
        echo op_json($before['receipt']) . "\n";
        exit(0);
    }
    if (!hash_equals($options['expected-plan-sha'], $before['receipt']['plan_sha256'])) op_fail('PLAN_HASH_MISMATCH');
    if ($before['receipt']['apply_blocked']) op_fail('DIRECT_TARGET_CONFLICT');

    $totals = ['created'=>0,'refreshed'=>0,'unchanged'=>0,'unresolved'=>0,'verified'=>0,'materialized'=>0];
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    foreach (array_chunk($before['resolvable'], 500) as $batch) {
        if ($batch === []) continue;
        $result = AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($db, $batch, $now);
        foreach (array_keys($totals) as $key) $totals[$key] += (int)($result[$key] ?? 0);
    }
    $after = op_plan($db, $releaseSha);
    $stable = hash_equals($before['receipt']['plan_sha256'], $after['receipt']['plan_sha256']);
    $receipt = [
        'schema_version'=>1,
        'operation'=>'anytour-provider-identity-materialize',
        'provider'=>'andromeda',
        'mode'=>'apply',
        'release_sha'=>$releaseSha,
        'plan_sha256'=>$before['receipt']['plan_sha256'],
        'post_plan_sha256'=>$after['receipt']['plan_sha256'],
        'source_stable'=>$stable,
        'requested'=>$before['receipt']['resolvable_refs'],
        'materialized'=>$totals['materialized'],
        'verified'=>$totals['verified'],
        'created'=>$totals['created'],
        'refreshed'=>$totals['refreshed'],
        'unchanged'=>$totals['unchanged'],
        'operation_unresolved'=>$totals['unresolved'],
        'catalog_unresolved'=>$before['receipt']['unresolved_refs'],
        'ambiguous_accepted_refs'=>$before['receipt']['ambiguous_accepted_refs'],
        'direct_conflicts_after'=>$after['receipt']['direct_conflicts'],
        'mapping_writes'=>0,
        'supplier_calls'=>0,
        'profile_writes'=>0,
        'legacy_source_writes'=>0,
        'site_file_writes'=>0,
    ];
    echo op_json($receipt) . "\n";
    if (!$stable || $after['receipt']['direct_conflicts'] > 0 || $totals['verified'] !== $totals['materialized']) op_fail('POST_APPLY_VERIFICATION');
} catch (Throwable $error) {
    op_fail('UNEXPECTED_' . preg_replace('/[^A-Z0-9_]/', '_', strtoupper(get_class($error))));
}
