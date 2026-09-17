<?php
/** Fixed on-host READ ONLY inspection of the provider-neutral AnyTour offer store. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_OFFER_CURRENT_OPERATION = 'anytour-offer-store-current-2693-20260917-v2';
const ANYTOUR_OFFER_CURRENT_SOURCE = 'a3f7b3b0aad1b7bd82dd3116fbc3e619466dd4ff';
const ANYTOUR_OFFER_CURRENT_PROVIDERS = ['tourvisor','anex','andromeda'];
const ANYTOUR_OFFER_CURRENT_TABLES = [
    'anytour_offer_store_control',
    'anytour_offer_refreshes',
    'anytour_offer_scope_state',
    'anytour_offers',
];

function anytour_offer_current_database(string $dsn): string
{
    if (!str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Configured MySQL target required');
    $names = [];
    foreach (explode(';', substr($dsn, 6)) as $part) {
        if (str_starts_with($part, 'dbname=')) $names[] = substr($part, 7);
    }
    if (count($names) !== 1 || !preg_match('/^[A-Za-z0-9_.-]+$/D', $names[0])) {
        throw new RuntimeException('One explicit configured database required');
    }
    return $names[0];
}

function anytour_offer_current_table_meta(PDO $pdo, array $names): array
{
    $stmt = $pdo->prepare('SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES '
        .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',', array_fill(0, count($names), '?')).')');
    $stmt->execute($names); $meta = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $meta[$row['TABLE_NAME']] = $row;
    return $meta;
}

function anytour_offer_current_int(mixed $value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

function anytour_offer_current_collect(PDO $pdo): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('Dedicated MySQL connection required');
    }
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $canonical = ['anytour_catalog_control','anytour_hotels','anytour_hotel_sources'];
        $names = array_merge($canonical, ANYTOUR_OFFER_CURRENT_TABLES);
        $meta = anytour_offer_current_table_meta($pdo, $names);
        foreach ($canonical as $name) {
            if (($meta[$name]['ENGINE'] ?? null) !== 'InnoDB' || ($meta[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('Canonical AnyTour catalogue is not installed in this project database');
            }
        }
        $canonicalVersion = (int)$pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        if ($canonicalVersion !== 1) throw new RuntimeException('Unsupported canonical catalogue version');
        $canonicalHotels = (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn();
        $canonicalLinks = (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();

        $tables = []; $present = 0;
        foreach (ANYTOUR_OFFER_CURRENT_TABLES as $name) {
            $m = $meta[$name] ?? null;
            if ($m) $present++;
            $tables[$name] = ['present'=>$m !== null,'engine'=>$m['ENGINE'] ?? null,'type'=>$m['TABLE_TYPE'] ?? null,'rows'=>null,'ddlSha256'=>null];
            if ($m && $m['ENGINE'] === 'InnoDB' && $m['TABLE_TYPE'] === 'BASE TABLE') {
                $tables[$name]['rows'] = (int)$pdo->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
                $ddl = $pdo->query("SHOW CREATE TABLE `$name`")->fetch(PDO::FETCH_NUM);
                $tables[$name]['ddlSha256'] = hash('sha256', (string)($ddl[1] ?? ''));
            }
        }
        $schemaState = $present === 0 ? 'absent' : ($present === count(ANYTOUR_OFFER_CURRENT_TABLES) ? 'complete' : 'partial');
        $controlVersion = null; $providerStats = []; $refreshStats = []; $scopeStats = []; $integrity = null; $overall = null;

        if ($schemaState === 'complete') {
            foreach (ANYTOUR_OFFER_CURRENT_TABLES as $name) {
                if ($tables[$name]['engine'] !== 'InnoDB' || $tables[$name]['type'] !== 'BASE TABLE') {
                    throw new RuntimeException('Offer store target is not an InnoDB base table');
                }
            }
            $controlVersion = (int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();

            $sql = "SELECT provider,COUNT(*) AS total_rows,
                SUM(is_active=1) AS active_rows,
                SUM(is_active=1 AND final_price_ready=1 AND currency='RUB' AND expires_at>UTC_TIMESTAMP()) AS current_rows,
                COUNT(DISTINCT CASE WHEN is_active=1 AND final_price_ready=1 AND currency='RUB' AND expires_at>UTC_TIMESTAMP() THEN anytour_hotel_id END) AS current_hotels,
                COUNT(DISTINCT scope_sha256) AS scopes,
                MIN(last_seen_at) AS oldest_last_seen_at,MAX(last_seen_at) AS newest_last_seen_at,
                MIN(CASE WHEN is_active=1 AND final_price_ready=1 AND currency='RUB' AND expires_at>UTC_TIMESTAMP() THEN display_price END) AS current_min_price,
                MAX(CASE WHEN is_active=1 AND final_price_ready=1 AND currency='RUB' AND expires_at>UTC_TIMESTAMP() THEN display_price END) AS current_max_price
                FROM anytour_offers GROUP BY provider ORDER BY provider";
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $providerStats[$row['provider']] = [
                    'totalRows'=>(int)$row['total_rows'],'activeRows'=>(int)$row['active_rows'],'currentRows'=>(int)$row['current_rows'],
                    'currentHotels'=>(int)$row['current_hotels'],'scopes'=>(int)$row['scopes'],
                    'oldestLastSeenAt'=>$row['oldest_last_seen_at'],'newestLastSeenAt'=>$row['newest_last_seen_at'],
                    'currentMinPrice'=>$row['current_min_price'],'currentMaxPrice'=>$row['current_max_price'],
                ];
            }
            foreach (ANYTOUR_OFFER_CURRENT_PROVIDERS as $provider) {
                if (!isset($providerStats[$provider])) $providerStats[$provider] = [
                    'totalRows'=>0,'activeRows'=>0,'currentRows'=>0,'currentHotels'=>0,'scopes'=>0,
                    'oldestLastSeenAt'=>null,'newestLastSeenAt'=>null,'currentMinPrice'=>null,'currentMaxPrice'=>null,
                ];
            }
            ksort($providerStats);

            foreach ($pdo->query('SELECT provider,status,COUNT(*) AS rows_count,MAX(started_at) AS newest_started_at,MAX(completed_at) AS newest_completed_at FROM anytour_offer_refreshes GROUP BY provider,status ORDER BY provider,status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $refreshStats[] = ['provider'=>$row['provider'],'status'=>$row['status'],'rows'=>(int)$row['rows_count'],
                    'newestStartedAt'=>$row['newest_started_at'],'newestCompletedAt'=>$row['newest_completed_at']];
            }
            foreach ($pdo->query("SELECT provider,COUNT(*) AS scopes,SUM(active_refresh_token IS NOT NULL) AS active_refreshes,SUM(latest_complete_refresh_token IS NOT NULL) AS completed_scopes,MAX(updated_at) AS newest_updated_at FROM anytour_offer_scope_state GROUP BY provider ORDER BY provider")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $scopeStats[$row['provider']] = ['scopes'=>(int)$row['scopes'],'activeRefreshes'=>(int)$row['active_refreshes'],
                    'completedScopes'=>(int)$row['completed_scopes'],'newestUpdatedAt'=>$row['newest_updated_at']];
            }
            $overallRow = $pdo->query("SELECT COUNT(*) AS total_rows,SUM(is_active=1) AS active_rows,
                SUM(is_active=1 AND final_price_ready=1 AND currency='RUB' AND expires_at>UTC_TIMESTAMP()) AS current_rows,
                COUNT(DISTINCT CASE WHEN is_active=1 AND final_price_ready=1 AND currency='RUB' AND expires_at>UTC_TIMESTAMP() THEN anytour_hotel_id END) AS current_hotels,
                COUNT(DISTINCT CASE WHEN is_active=1 AND final_price_ready=1 AND currency='RUB' AND expires_at>UTC_TIMESTAMP() THEN scope_sha256 END) AS current_scopes
                FROM anytour_offers")->fetch(PDO::FETCH_ASSOC);
            $overall = ['totalRows'=>anytour_offer_current_int($overallRow['total_rows'] ?? 0),'activeRows'=>anytour_offer_current_int($overallRow['active_rows'] ?? 0),
                'currentRows'=>anytour_offer_current_int($overallRow['current_rows'] ?? 0),'currentHotels'=>anytour_offer_current_int($overallRow['current_hotels'] ?? 0),
                'currentScopes'=>anytour_offer_current_int($overallRow['current_scopes'] ?? 0)];
            $overall['canonicalHotelCoveragePct'] = $canonicalHotels > 0 ? round(100 * $overall['currentHotels'] / $canonicalHotels, 2) : 0.0;

            $bad = $pdo->query("SELECT
                SUM(provider NOT IN ('tourvisor','anex','andromeda')) AS unknown_provider,
                SUM(final_price_ready<>1 OR currency<>'RUB' OR display_price<=0) AS invalid_listing_readiness,
                SUM(payload_sha256<>SHA2(payload_json,256)) AS payload_digest_mismatch,
                SUM(operator_sha256<>SHA2(operator_json,256)) AS operator_digest_mismatch
                FROM anytour_offers")->fetch(PDO::FETCH_ASSOC);
            $integrity = ['unknownProviderRows'=>anytour_offer_current_int($bad['unknown_provider'] ?? 0),
                'invalidListingReadinessRows'=>anytour_offer_current_int($bad['invalid_listing_readiness'] ?? 0),
                'payloadDigestMismatchRows'=>anytour_offer_current_int($bad['payload_digest_mismatch'] ?? 0),
                'operatorDigestMismatchRows'=>anytour_offer_current_int($bad['operator_digest_mismatch'] ?? 0)];
        }

        $identity = $pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
        $pdo->commit();
        return [
            'status'=>'current_read_only','observedAt'=>gmdate('c'),'schemaState'=>$schemaState,'offerStoreVersion'=>$controlVersion,
            'databaseNameSha256'=>hash('sha256',(string)$identity['db']),
            'targetIdentitySha256'=>hash('sha256',json_encode($identity,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
            'serverVersion'=>$identity['version'],'tables'=>$tables,
            'canonical'=>['activeHotels'=>$canonicalHotels,'legacyCatalogLinks'=>$canonicalLinks,'schemaVersion'=>$canonicalVersion],
            'overall'=>$overall,'providers'=>$providerStats,'refreshes'=>$refreshStats,'scopeState'=>$scopeStats,'integrity'=>$integrity,
            'databaseWrites'=>0,'supplierCalls'=>0,'migrationAuthorized'=>false,'populationAuthorized'=>false,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $pdo = null; $journal = null;
    try {
        if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private operation invocation required');
        $directory = dirname(__DIR__, 3);
        if (basename($directory) !== ANYTOUR_OFFER_CURRENT_OPERATION || !is_file($directory.'/reservation.json')) {
            throw new RuntimeException('Existing operation reservation required');
        }
        $reservation = json_decode(file_get_contents($directory.'/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? null) !== ANYTOUR_OFFER_CURRENT_OPERATION || ($reservation['dataSource'] ?? null) !== ANYTOUR_OFFER_CURRENT_SOURCE
            || ($reservation['attempt'] ?? null) !== 1) throw new RuntimeException('Operation provenance mismatch');
        $journal = fopen($directory.'/result.json', 'x+b');
        if (!$journal) throw new RuntimeException('Result already exists; no replay');

        $root = rtrim((string)getenv('HOME'), '/').'/www/anytoour.ru';
        if (!is_file($root.'/config.php') || !is_file($root.'/api-v2.php')) throw new RuntimeException('Existing project root required');
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
            if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected process DB override');
        }
        $configHash = hash_file('sha256', $root.'/config.php');
        require_once $root.'/config.php';
        require_once __DIR__.'/../../v2/data/db-v1.php';
        $config = v2_data_db_config(); $expectedName = anytour_offer_current_database($config['dsn']);
        if ($config['user'] === '') throw new RuntimeException('Configured project DB user required');
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $expectedName) throw new RuntimeException('Selected database differs from project configuration');
        $result = anytour_offer_current_collect($pdo);
        if ($configHash !== hash_file('sha256', $root.'/config.php')) throw new RuntimeException('Project configuration changed during inspection');
        $result['operation'] = ANYTOUR_OFFER_CURRENT_OPERATION; $result['dataSource'] = ANYTOUR_OFFER_CURRENT_SOURCE;
        $result['executionSource'] = $reservation['executionSource']; $result['run'] = $reservation['run'];
        $result['projectConfigurationUnchanged'] = true; $result['publicFileWrites'] = 0; $result['noReplay'] = true;
        $bytes = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if (fwrite($journal, $bytes) !== strlen($bytes) || !fflush($journal) || !fsync($journal)) throw new RuntimeException('Durable result failed');
        fclose($journal); $journal = null;
        echo 'ANYTOUR_OFFER_CURRENT_OK result_sha256='.hash('sha256',$bytes)." database_writes=0 supplier_calls=0\n";
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($journal)) {
            $error = json_encode(['status'=>'inspection_failed','class'=>get_class($e),'databaseWrites'=>0,'noReplay'=>true])."\n";
            fwrite($journal,$error); fflush($journal); fsync($journal); fclose($journal);
        }
        fwrite(STDERR,'ANYTOUR_OFFER_CURRENT_FAILED class='.get_class($e)." database_writes=0; inspect retained operation, no replay\n");
        exit(1);
    }
}
