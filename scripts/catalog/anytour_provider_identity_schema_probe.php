<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function probe_fail(string $code): never
{
    fwrite(STDERR, "ANYTOUR_PROVIDER_IDENTITY_SCHEMA_PROBE_FAILED code={$code}\n");
    exit(1);
}

function probe_json(array $value): string
{
    ksort($value, SORT_STRING);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function probe_count(PDO $db, string $sql, string $failure): int
{
    try {
        $value = $db->query($sql)->fetchColumn();
    } catch (PDOException) {
        probe_fail($failure);
    }
    if (!is_int($value) && !is_string($value)) probe_fail($failure . '_SHAPE');
    $text = (string)$value;
    if (!preg_match('/\A[0-9]+\z/D', $text)) probe_fail($failure . '_SHAPE');
    return (int)$text;
}

try {
    $releaseSha = '';
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--release-sha=([a-f0-9]{40})$/D', $arg, $m) || $releaseSha !== '') probe_fail('USAGE');
        $releaseSha = $m[1];
    }
    if ($releaseSha === '') probe_fail('USAGE');
    $runtimeSha = trim((string)getenv('ANYTOUR_OPERATION_RELEASE_SHA'));
    if ($runtimeSha === '' || !hash_equals($runtimeSha, $releaseSha)) probe_fail('RELEASE_SHA_MISMATCH');

    $siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
    $helper = $siteRoot !== '' ? $siteRoot . '/data/db-v1.php' : __DIR__ . '/../../v2/data/db-v1.php';
    if (!is_file($helper)) probe_fail('DB_HELPER_MISSING');
    require_once $helper;
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    } catch (PDOException) {
        probe_fail('DATABASE_NAME_QUERY');
    }
    if ($database === '') probe_fail('DATABASE_NAME_EMPTY');

    $required = [
        'andromeda_hotel_identities' => [
            'supplier_namespace','external_hotel_id','local_hotel_id','decision_status',
        ],
        'catalog_hotels' => ['id','country_id','is_active'],
        'anytour_hotels' => ['id','is_active'],
        'anytour_hotel_sources' => [
            'namespace','external_key','anytour_hotel_id','acquired_via','source_json','source_sha256',
        ],
    ];
    $tables = [];
    foreach ($required as $table => $columns) {
        try {
            $stmt = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            $engine = $stmt->fetchColumn();
            $colStmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
            $colStmt->execute([$table]);
            $visible = array_map('strval', $colStmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException) {
            probe_fail('INFORMATION_SCHEMA_READ');
        }
        $missing = array_values(array_diff($columns, $visible));
        sort($missing, SORT_STRING);
        $tables[$table] = [
            'present' => $engine !== false,
            'engine' => $engine === false ? null : strtoupper((string)$engine),
            'required_columns_present' => $missing === [],
            'missing_required_columns' => $missing,
        ];
    }

    try {
        $other = $db->prepare("SELECT COUNT(DISTINCT TABLE_SCHEMA) FROM information_schema.TABLES WHERE TABLE_NAME='andromeda_hotel_identities'");
        $other->execute();
        $schemasWithAndromeda = (int)$other->fetchColumn();
    } catch (PDOException) {
        probe_fail('ANDROMEDA_SCHEMA_VISIBILITY');
    }

    $counts = [];
    if ($tables['andromeda_hotel_identities']['present'] && $tables['andromeda_hotel_identities']['required_columns_present']) {
        $counts['accepted_andromeda_rows'] = probe_count(
            $db,
            "SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL",
            'COUNT_ACCEPTED_ANDROMEDA'
        );
    } else {
        $counts['accepted_andromeda_rows'] = null;
    }
    if ($tables['catalog_hotels']['present'] && $tables['catalog_hotels']['required_columns_present']) {
        $counts['active_catalog_hotels'] = probe_count($db, 'SELECT COUNT(*) FROM catalog_hotels WHERE is_active=1', 'COUNT_CATALOG_HOTELS');
    } else {
        $counts['active_catalog_hotels'] = null;
    }
    if ($tables['anytour_hotels']['present'] && $tables['anytour_hotels']['required_columns_present']) {
        $counts['active_anytour_hotels'] = probe_count($db, 'SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1', 'COUNT_ANYTOUR_HOTELS');
    } else {
        $counts['active_anytour_hotels'] = null;
    }
    if ($tables['anytour_hotel_sources']['present'] && $tables['anytour_hotel_sources']['required_columns_present']) {
        $counts['legacy_catalog_sources'] = probe_count(
            $db,
            "SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'",
            'COUNT_LEGACY_SOURCES'
        );
        $counts['direct_andromeda_sources'] = probe_count(
            $db,
            "SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='provider_ref_digest:andromeda'",
            'COUNT_DIRECT_ANDROMEDA_SOURCES'
        );
    } else {
        $counts['legacy_catalog_sources'] = null;
        $counts['direct_andromeda_sources'] = null;
    }

    echo probe_json([
        'schema_version' => 1,
        'operation' => 'anytour-provider-identity-schema-probe',
        'mode' => 'read_only',
        'release_sha' => $releaseSha,
        'database_name_sha256' => hash('sha256', $database),
        'schemas_visible_with_andromeda_identity_table' => $schemasWithAndromeda,
        'tables' => $tables,
        'counts' => $counts,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'supplier_calls' => 0,
        'site_file_writes' => 0,
    ]) . "\n";
} catch (Throwable $error) {
    probe_fail('UNEXPECTED_' . preg_replace('/[^A-Z0-9_]/', '_', strtoupper(get_class($error))));
}
