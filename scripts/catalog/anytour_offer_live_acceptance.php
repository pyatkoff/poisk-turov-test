<?php
/**
 * READ ONLY CURRENT acceptance for the AnyTour schema-v2 offer store.
 * It mirrors customer visibility: latest completed provider snapshot, unexpired
 * final-price-ready RUB offer, and a still-current legacy_catalog -> AnyTour bridge.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_OFFER_ACCEPT_OPERATION = 'anytour-offer-live-acceptance-2693-20260917-v1';
const ANYTOUR_OFFER_ACCEPT_SOURCE = 'b90390c1c2008e07a91b053107d68822a45dd213';
const ANYTOUR_OFFER_ACCEPT_PROVIDERS = ['tourvisor','anex','andromeda'];
const ANYTOUR_OFFER_ACCEPT_TABLES = [
    'anytour_offer_store_control',
    'anytour_offer_refreshes',
    'anytour_offer_scope_state',
    'anytour_offers',
];

function anytour_offer_accept_database(string $dsn): string
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

function anytour_offer_accept_table_meta(PDO $pdo, array $names): array
{
    $stmt = $pdo->prepare('SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES '
        .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',', array_fill(0, count($names), '?')).')');
    $stmt->execute($names); $meta = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $meta[$row['TABLE_NAME']] = $row;
    return $meta;
}

function anytour_offer_accept_int(mixed $value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

function anytour_offer_accept_collect(PDO $pdo): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('Dedicated MySQL connection required');
    }
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $canonical = ['anytour_catalog_control','anytour_hotels','anytour_hotel_sources'];
        $meta = anytour_offer_accept_table_meta($pdo, array_merge($canonical, ANYTOUR_OFFER_ACCEPT_TABLES));
        foreach (array_merge($canonical, ANYTOUR_OFFER_ACCEPT_TABLES) as $name) {
            if (($meta[$name]['ENGINE'] ?? null) !== 'InnoDB' || ($meta[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('Required AnyTour table is not an InnoDB base table: '.$name);
            }
        }
        $catalogVersion = (int)$pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        $offerVersion = (int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        if ($catalogVersion !== 1 || $offerVersion !== 2) throw new RuntimeException('Unsupported AnyTour schema version');

        $canonicalHotels = (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn();
        $canonicalLinks = (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
        $rawRows = (int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn();

        $storedByProvider = [];
        foreach ($pdo->query('SELECT provider,COUNT(*) AS rows_count,COUNT(DISTINCT scope_sha256) AS scopes FROM anytour_offers GROUP BY provider ORDER BY provider')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $storedByProvider[(string)$row['provider']] = ['rows'=>(int)$row['rows_count'],'scopes'=>(int)$row['scopes']];
        }

        $visibleSql = "SELECT o.provider,COUNT(*) AS visible_rows,COUNT(DISTINCT o.anytour_hotel_id) AS visible_hotels,
            COUNT(DISTINCT o.scope_sha256) AS visible_scopes,MIN(o.display_price) AS min_price,MAX(o.display_price) AS max_price,
            MIN(o.last_seen_at) AS oldest_seen,MAX(o.last_seen_at) AS newest_seen
            FROM anytour_offers o
            JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256
                AND s.latest_complete_refresh_token IS NOT NULL AND s.latest_complete_refresh_token=o.last_refresh_token
            WHERE o.is_active=1 AND o.final_price_ready=1 AND o.currency='RUB' AND o.expires_at>UTC_TIMESTAMP()
              AND EXISTS (SELECT 1 FROM anytour_hotel_sources hs WHERE hs.namespace='legacy_catalog'
                  AND hs.external_key=CAST(o.legacy_hotel_id AS CHAR) AND hs.anytour_hotel_id=o.anytour_hotel_id)
            GROUP BY o.provider ORDER BY o.provider";
        $visibleByProvider = [];
        foreach ($pdo->query($visibleSql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $visibleByProvider[(string)$row['provider']] = [
                'rows'=>(int)$row['visible_rows'],'hotels'=>(int)$row['visible_hotels'],'scopes'=>(int)$row['visible_scopes'],
                'minPrice'=>$row['min_price'],'maxPrice'=>$row['max_price'],
                'oldestSeenAt'=>$row['oldest_seen'],'newestSeenAt'=>$row['newest_seen'],
            ];
        }
        foreach (ANYTOUR_OFFER_ACCEPT_PROVIDERS as $provider) {
            $storedByProvider[$provider] ??= ['rows'=>0,'scopes'=>0];
            $visibleByProvider[$provider] ??= ['rows'=>0,'hotels'=>0,'scopes'=>0,'minPrice'=>null,'maxPrice'=>null,'oldestSeenAt'=>null,'newestSeenAt'=>null];
        }
        ksort($storedByProvider); ksort($visibleByProvider);

        $scopeState = [];
        foreach ($pdo->query("SELECT provider,COUNT(*) AS scopes,SUM(active_refresh_token IS NOT NULL) AS active_refreshes,
            SUM(latest_complete_refresh_token IS NOT NULL) AS completed_scopes,MAX(updated_at) AS newest_updated_at
            FROM anytour_offer_scope_state GROUP BY provider ORDER BY provider")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scopeState[(string)$row['provider']] = [
                'scopes'=>(int)$row['scopes'],'activeRefreshes'=>(int)$row['active_refreshes'],
                'completedScopes'=>(int)$row['completed_scopes'],'newestUpdatedAt'=>$row['newest_updated_at'],
            ];
        }
        foreach (ANYTOUR_OFFER_ACCEPT_PROVIDERS as $provider) {
            $scopeState[$provider] ??= ['scopes'=>0,'activeRefreshes'=>0,'completedScopes'=>0,'newestUpdatedAt'=>null];
        }
        ksort($scopeState);

        $refreshes = [];
        foreach ($pdo->query('SELECT provider,status,COUNT(*) AS rows_count,MAX(started_at) AS newest_started_at,MAX(completed_at) AS newest_completed_at FROM anytour_offer_refreshes GROUP BY provider,status ORDER BY provider,status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $refreshes[] = ['provider'=>(string)$row['provider'],'status'=>(string)$row['status'],'rows'=>(int)$row['rows_count'],
                'newestStartedAt'=>$row['newest_started_at'],'newestCompletedAt'=>$row['newest_completed_at']];
        }

        $visibleOverall = $pdo->query("SELECT COUNT(*) AS rows_count,COUNT(DISTINCT o.anytour_hotel_id) AS hotels,
            COUNT(DISTINCT o.scope_sha256) AS scopes
            FROM anytour_offers o
            JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256
                AND s.latest_complete_refresh_token IS NOT NULL AND s.latest_complete_refresh_token=o.last_refresh_token
            WHERE o.is_active=1 AND o.final_price_ready=1 AND o.currency='RUB' AND o.expires_at>UTC_TIMESTAMP()
              AND EXISTS (SELECT 1 FROM anytour_hotel_sources hs WHERE hs.namespace='legacy_catalog'
                  AND hs.external_key=CAST(o.legacy_hotel_id AS CHAR) AND hs.anytour_hotel_id=o.anytour_hotel_id)")->fetch(PDO::FETCH_ASSOC);
        $activeRefreshes = (int)$pdo->query('SELECT COUNT(*) FROM anytour_offer_scope_state WHERE active_refresh_token IS NOT NULL')->fetchColumn();
        $completedScopes = (int)$pdo->query('SELECT COUNT(*) FROM anytour_offer_scope_state WHERE latest_complete_refresh_token IS NOT NULL')->fetchColumn();

        $bad = $pdo->query("SELECT
            SUM(provider NOT IN ('tourvisor','anex','andromeda')) AS unknown_provider,
            SUM(final_price_ready<>1 OR currency<>'RUB' OR display_price<=0) AS invalid_listing,
            SUM(payload_sha256<>SHA2(payload_json,256)) AS payload_digest,
            SUM(operator_sha256<>SHA2(operator_json,256)) AS operator_digest
            FROM anytour_offers")->fetch(PDO::FETCH_ASSOC);
        $orphanLatest = (int)$pdo->query("SELECT COUNT(*) FROM anytour_offer_scope_state s
            LEFT JOIN anytour_offer_refreshes r ON r.refresh_token=s.latest_complete_refresh_token AND r.provider=s.provider AND r.scope_sha256=s.scope_sha256 AND r.status='completed'
            WHERE s.latest_complete_refresh_token IS NOT NULL AND r.refresh_token IS NULL")->fetchColumn();
        $withheldBridge = (int)$pdo->query("SELECT COUNT(*) FROM anytour_offers o
            JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256 AND s.latest_complete_refresh_token=o.last_refresh_token
            WHERE o.is_active=1 AND o.final_price_ready=1 AND o.currency='RUB' AND o.expires_at>UTC_TIMESTAMP()
              AND NOT EXISTS (SELECT 1 FROM anytour_hotel_sources hs WHERE hs.namespace='legacy_catalog'
                  AND hs.external_key=CAST(o.legacy_hotel_id AS CHAR) AND hs.anytour_hotel_id=o.anytour_hotel_id)")->fetchColumn();

        $identity = $pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
        $pdo->commit();

        $visibleRows = anytour_offer_accept_int($visibleOverall['rows_count'] ?? 0);
        $visibleHotels = anytour_offer_accept_int($visibleOverall['hotels'] ?? 0);
        return [
            'status'=>'live_acceptance_read_only','observedAt'=>gmdate('c'),
            'catalogSchemaVersion'=>$catalogVersion,'offerStoreSchemaVersion'=>$offerVersion,
            'canonical'=>['activeHotels'=>$canonicalHotels,'legacyCatalogLinks'=>$canonicalLinks],
            'overall'=>[
                'storedRows'=>$rawRows,'visibleRows'=>$visibleRows,'visibleHotels'=>$visibleHotels,
                'visibleScopes'=>anytour_offer_accept_int($visibleOverall['scopes'] ?? 0),
                'completedScopes'=>$completedScopes,'activeRefreshes'=>$activeRefreshes,
                'canonicalHotelCoveragePct'=>$canonicalHotels>0?round(100*$visibleHotels/$canonicalHotels,2):0.0,
            ],
            'storedByProvider'=>$storedByProvider,'visibleByProvider'=>$visibleByProvider,
            'scopeState'=>$scopeState,'refreshes'=>$refreshes,
            'integrity'=>[
                'unknownProviderRows'=>anytour_offer_accept_int($bad['unknown_provider'] ?? 0),
                'invalidListingRows'=>anytour_offer_accept_int($bad['invalid_listing'] ?? 0),
                'payloadDigestMismatchRows'=>anytour_offer_accept_int($bad['payload_digest'] ?? 0),
                'operatorDigestMismatchRows'=>anytour_offer_accept_int($bad['operator_digest'] ?? 0),
                'orphanLatestCompleteScopes'=>$orphanLatest,
                'withheldByCurrentBridgeRows'=>$withheldBridge,
            ],
            'databaseNameSha256'=>hash('sha256',(string)$identity['db']),
            'targetIdentitySha256'=>hash('sha256',json_encode($identity,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
            'serverVersion'=>$identity['version'],'databaseWrites'=>0,'supplierCalls'=>0,'selectionAuthority'=>false,
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
        if (basename($directory) !== ANYTOUR_OFFER_ACCEPT_OPERATION || !is_file($directory.'/reservation.json')) {
            throw new RuntimeException('Existing operation reservation required');
        }
        $reservation = json_decode(file_get_contents($directory.'/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? null) !== ANYTOUR_OFFER_ACCEPT_OPERATION || ($reservation['dataSource'] ?? null) !== ANYTOUR_OFFER_ACCEPT_SOURCE
            || ($reservation['attempt'] ?? null) !== 1 || ($reservation['databaseWrites'] ?? null) !== 0 || ($reservation['supplierCalls'] ?? null) !== 0) {
            throw new RuntimeException('Operation provenance mismatch');
        }
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
        $config = v2_data_db_config(); $expectedName = anytour_offer_accept_database($config['dsn']);
        if ($config['user'] === '') throw new RuntimeException('Configured project DB user required');
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5,
        ]);
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $expectedName) throw new RuntimeException('Selected database differs from project configuration');
        $result = anytour_offer_accept_collect($pdo);
        if ($configHash !== hash_file('sha256', $root.'/config.php')) throw new RuntimeException('Project configuration changed during inspection');
        $result['operation']=ANYTOUR_OFFER_ACCEPT_OPERATION;$result['dataSource']=ANYTOUR_OFFER_ACCEPT_SOURCE;
        $result['executionSource']=$reservation['executionSource'];$result['run']=$reservation['run'];
        $result['projectConfigurationUnchanged']=true;$result['publicFileWrites']=0;$result['noReplay']=true;
        $bytes=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if (fwrite($journal,$bytes)!==strlen($bytes)||!fflush($journal)||!fsync($journal)) throw new RuntimeException('Durable result failed');
        fclose($journal);$journal=null;
        echo 'ANYTOUR_OFFER_LIVE_ACCEPTANCE_OK result_sha256='.hash('sha256',$bytes)." database_writes=0 supplier_calls=0\n";
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($journal)) {
            $error=json_encode(['status'=>'acceptance_failed','class'=>get_class($e),'databaseWrites'=>0,'supplierCalls'=>0,'noReplay'=>true])."\n";
            fwrite($journal,$error);fflush($journal);fsync($journal);fclose($journal);
        }
        fwrite(STDERR,'ANYTOUR_OFFER_LIVE_ACCEPTANCE_FAILED class='.get_class($e)." database_writes=0 supplier_calls=0; inspect retained operation, no replay\n");
        exit(1);
    }
}
