<?php
/** One-shot CURRENT READ ONLY inspection of the schema-v2 AnyTour offer store. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_OFFER_CURRENT_V3_OPERATION = 'anytour-offer-store-current-2693-20260917-v3';
const ANYTOUR_OFFER_CURRENT_V3_SOURCE = 'b90390c1c2008e07a91b053107d68822a45dd213';
const ANYTOUR_OFFER_CURRENT_V3_PROVIDERS = ['tourvisor','anex','andromeda'];
const ANYTOUR_OFFER_CURRENT_V3_TABLES = [
    'anytour_offer_store_control',
    'anytour_offer_refreshes',
    'anytour_offer_scope_state',
    'anytour_offers',
];

function anytour_offer_current_v3_database(string $dsn): string
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

function anytour_offer_current_v3_table_meta(PDO $pdo, array $names): array
{
    $stmt = $pdo->prepare('SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES '
        .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',', array_fill(0, count($names), '?')).')');
    $stmt->execute($names);
    $meta = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $meta[$row['TABLE_NAME']] = $row;
    return $meta;
}

function anytour_offer_current_v3_int(mixed $value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

function anytour_offer_current_v3_collect(PDO $pdo): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('Dedicated MySQL connection required');
    }
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $canonicalTables = ['anytour_catalog_control','anytour_hotels','anytour_hotel_sources'];
        $allTables = array_merge($canonicalTables, ANYTOUR_OFFER_CURRENT_V3_TABLES);
        $meta = anytour_offer_current_v3_table_meta($pdo, $allTables);
        foreach ($canonicalTables as $name) {
            if (($meta[$name]['ENGINE'] ?? null) !== 'InnoDB' || ($meta[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('Canonical AnyTour catalogue is not installed in this project database');
            }
        }
        foreach (ANYTOUR_OFFER_CURRENT_V3_TABLES as $name) {
            if (($meta[$name]['ENGINE'] ?? null) !== 'InnoDB' || ($meta[$name]['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('Complete AnyTour offer store is required');
            }
        }

        $canonicalVersion = (int)$pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        $offerStoreVersion = (int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        $canonicalHotels = (int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn();
        $canonicalLinks = (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();

        $tables = [];
        foreach (ANYTOUR_OFFER_CURRENT_V3_TABLES as $name) {
            $rows = (int)$pdo->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
            $ddl = $pdo->query("SHOW CREATE TABLE `$name`")->fetch(PDO::FETCH_NUM);
            $tables[$name] = ['rows'=>$rows,'engine'=>$meta[$name]['ENGINE'],'ddlSha256'=>hash('sha256',(string)($ddl[1] ?? ''))];
        }

        $visible = "o.is_active=1 AND o.final_price_ready=1 AND o.currency='RUB' AND o.display_price>0 "
            ."AND o.expires_at>UTC_TIMESTAMP() AND s.latest_complete_refresh_token IS NOT NULL "
            ."AND s.latest_complete_refresh_token=o.last_refresh_token "
            ."AND EXISTS (SELECT 1 FROM anytour_hotel_sources hs WHERE hs.namespace='legacy_catalog' "
            ."AND hs.external_key=CAST(o.legacy_hotel_id AS BINARY) AND hs.anytour_hotel_id=o.anytour_hotel_id)";
        $ready = "o.is_active=1 AND o.final_price_ready=1 AND o.currency='RUB' AND o.display_price>0 AND o.expires_at>UTC_TIMESTAMP()";
        $latestWithoutBridge = $ready." AND s.latest_complete_refresh_token IS NOT NULL AND s.latest_complete_refresh_token=o.last_refresh_token "
            ."AND NOT EXISTS (SELECT 1 FROM anytour_hotel_sources hs WHERE hs.namespace='legacy_catalog' "
            ."AND hs.external_key=CAST(o.legacy_hotel_id AS BINARY) AND hs.anytour_hotel_id=o.anytour_hotel_id)";
        $notLatest = $ready." AND (s.latest_complete_refresh_token IS NULL OR s.latest_complete_refresh_token<>o.last_refresh_token)";

        $providerSql = "SELECT o.provider,COUNT(*) AS raw_rows,SUM($ready) AS ready_rows,SUM($visible) AS visible_rows,"
            ."COUNT(DISTINCT CASE WHEN $visible THEN o.anytour_hotel_id END) AS visible_hotels,"
            ."COUNT(DISTINCT CASE WHEN $visible THEN o.scope_sha256 END) AS visible_scopes,"
            ."SUM($latestWithoutBridge) AS withheld_identity_rows,SUM($notLatest) AS not_latest_rows,"
            ."MIN(o.last_seen_at) AS raw_oldest_last_seen_at,MAX(o.last_seen_at) AS raw_newest_last_seen_at,"
            ."MIN(CASE WHEN $visible THEN o.last_seen_at END) AS visible_oldest_last_seen_at,"
            ."MAX(CASE WHEN $visible THEN o.last_seen_at END) AS visible_newest_last_seen_at,"
            ."MIN(CASE WHEN $visible THEN o.display_price END) AS visible_min_price,"
            ."MAX(CASE WHEN $visible THEN o.display_price END) AS visible_max_price "
            ."FROM anytour_offers o LEFT JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256 "
            ."GROUP BY o.provider ORDER BY o.provider";
        $providers = [];
        foreach ($pdo->query($providerSql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $providers[(string)$row['provider']] = [
                'rawRows'=>(int)$row['raw_rows'],
                'readyUnexpiredRows'=>(int)$row['ready_rows'],
                'visibleRows'=>(int)$row['visible_rows'],
                'visibleHotels'=>(int)$row['visible_hotels'],
                'visibleScopes'=>(int)$row['visible_scopes'],
                'withheldIdentityRows'=>(int)$row['withheld_identity_rows'],
                'notLatestCompleteRows'=>(int)$row['not_latest_rows'],
                'rawOldestLastSeenAt'=>$row['raw_oldest_last_seen_at'],
                'rawNewestLastSeenAt'=>$row['raw_newest_last_seen_at'],
                'visibleOldestLastSeenAt'=>$row['visible_oldest_last_seen_at'],
                'visibleNewestLastSeenAt'=>$row['visible_newest_last_seen_at'],
                'visibleMinPrice'=>$row['visible_min_price'],
                'visibleMaxPrice'=>$row['visible_max_price'],
            ];
        }
        foreach (ANYTOUR_OFFER_CURRENT_V3_PROVIDERS as $provider) {
            $providers[$provider] ??= [
                'rawRows'=>0,'readyUnexpiredRows'=>0,'visibleRows'=>0,'visibleHotels'=>0,'visibleScopes'=>0,
                'withheldIdentityRows'=>0,'notLatestCompleteRows'=>0,'rawOldestLastSeenAt'=>null,'rawNewestLastSeenAt'=>null,
                'visibleOldestLastSeenAt'=>null,'visibleNewestLastSeenAt'=>null,'visibleMinPrice'=>null,'visibleMaxPrice'=>null,
            ];
        }
        ksort($providers);

        $overallSql = "SELECT COUNT(*) AS raw_rows,SUM($ready) AS ready_rows,SUM($visible) AS visible_rows,"
            ."COUNT(DISTINCT CASE WHEN $visible THEN o.anytour_hotel_id END) AS visible_hotels,"
            ."COUNT(DISTINCT CASE WHEN $visible THEN o.scope_sha256 END) AS visible_scopes,"
            ."SUM($latestWithoutBridge) AS withheld_identity_rows,SUM($notLatest) AS not_latest_rows "
            ."FROM anytour_offers o LEFT JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256";
        $overallRow = $pdo->query($overallSql)->fetch(PDO::FETCH_ASSOC);
        $overall = [
            'rawRows'=>anytour_offer_current_v3_int($overallRow['raw_rows'] ?? 0),
            'readyUnexpiredRows'=>anytour_offer_current_v3_int($overallRow['ready_rows'] ?? 0),
            'visibleRows'=>anytour_offer_current_v3_int($overallRow['visible_rows'] ?? 0),
            'visibleHotels'=>anytour_offer_current_v3_int($overallRow['visible_hotels'] ?? 0),
            'visibleScopes'=>anytour_offer_current_v3_int($overallRow['visible_scopes'] ?? 0),
            'withheldIdentityRows'=>anytour_offer_current_v3_int($overallRow['withheld_identity_rows'] ?? 0),
            'notLatestCompleteRows'=>anytour_offer_current_v3_int($overallRow['not_latest_rows'] ?? 0),
        ];
        $overall['canonicalHotelCoveragePct'] = $canonicalHotels > 0 ? round(100*$overall['visibleHotels']/$canonicalHotels,2) : 0.0;

        $refreshes = [];
        foreach ($pdo->query('SELECT provider,status,COUNT(*) AS rows_count,MAX(started_at) AS newest_started_at,MAX(completed_at) AS newest_completed_at FROM anytour_offer_refreshes GROUP BY provider,status ORDER BY provider,status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $refreshes[] = ['provider'=>$row['provider'],'status'=>$row['status'],'rows'=>(int)$row['rows_count'],
                'newestStartedAt'=>$row['newest_started_at'],'newestCompletedAt'=>$row['newest_completed_at']];
        }
        $scopeState = [];
        foreach ($pdo->query('SELECT provider,COUNT(*) AS scopes,SUM(active_refresh_token IS NOT NULL) AS active_refreshes,SUM(latest_complete_refresh_token IS NOT NULL) AS completed_scopes,MAX(updated_at) AS newest_updated_at FROM anytour_offer_scope_state GROUP BY provider ORDER BY provider')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scopeState[(string)$row['provider']] = ['scopes'=>(int)$row['scopes'],'activeRefreshes'=>(int)$row['active_refreshes'],
                'completedScopes'=>(int)$row['completed_scopes'],'newestUpdatedAt'=>$row['newest_updated_at']];
        }

        $bad = $pdo->query("SELECT SUM(provider NOT IN ('tourvisor','anex','andromeda')) AS unknown_provider,"
            ."SUM(final_price_ready<>1 OR currency<>'RUB' OR display_price<=0) AS invalid_listing_readiness,"
            ."SUM(payload_sha256<>SHA2(payload_json,256)) AS payload_digest_mismatch,"
            ."SUM(operator_sha256<>SHA2(operator_json,256)) AS operator_digest_mismatch FROM anytour_offers")->fetch(PDO::FETCH_ASSOC);
        $integrity = [
            'unknownProviderRows'=>anytour_offer_current_v3_int($bad['unknown_provider'] ?? 0),
            'invalidListingReadinessRows'=>anytour_offer_current_v3_int($bad['invalid_listing_readiness'] ?? 0),
            'payloadDigestMismatchRows'=>anytour_offer_current_v3_int($bad['payload_digest_mismatch'] ?? 0),
            'operatorDigestMismatchRows'=>anytour_offer_current_v3_int($bad['operator_digest_mismatch'] ?? 0),
        ];

        $identity = $pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
        $pdo->commit();
        return [
            'status'=>'current_read_only','observedAt'=>gmdate('c'),'schemaState'=>'complete','offerStoreVersion'=>$offerStoreVersion,
            'databaseNameSha256'=>hash('sha256',(string)$identity['db']),
            'targetIdentitySha256'=>hash('sha256',json_encode($identity,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
            'serverVersion'=>$identity['version'],'tables'=>$tables,
            'canonical'=>['activeHotels'=>$canonicalHotels,'legacyCatalogLinks'=>$canonicalLinks,'schemaVersion'=>$canonicalVersion],
            'overall'=>$overall,'providers'=>$providers,'refreshes'=>$refreshes,'scopeState'=>$scopeState,'integrity'=>$integrity,
            'databaseWrites'=>0,'supplierCalls'=>0,'populationAuthorized'=>false,'migrationAuthorized'=>false,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function anytour_offer_current_v3_write_result(string $directory,array $result): string
{
    $bytes=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $out=fopen($directory.'/result.json','x+b');
    if(!$out||fwrite($out,$bytes)!==strlen($bytes)||!fflush($out)||!fsync($out))throw new RuntimeException('Durable result failed');
    fclose($out);return hash('sha256',$bytes);
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $pdo=null;$directory='';
    try {
        if($argc!==1||basename(dirname(__DIR__,2))!=='payload')throw new RuntimeException('Fixed private operation invocation required');
        $directory=dirname(__DIR__,3);
        if(basename($directory)!==ANYTOUR_OFFER_CURRENT_V3_OPERATION||!is_file($directory.'/reservation.json'))throw new RuntimeException('Existing operation reservation required');
        $reservation=json_decode((string)file_get_contents($directory.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if(($reservation['operation']??null)!==ANYTOUR_OFFER_CURRENT_V3_OPERATION||($reservation['dataSource']??null)!==ANYTOUR_OFFER_CURRENT_V3_SOURCE||($reservation['attempt']??null)!==1)throw new RuntimeException('Operation provenance mismatch');
        if(is_file($directory.'/result.json')||is_file($directory.'/started.json'))throw new RuntimeException('Existing operation state; no replay');
        $started=fopen($directory.'/started.json','x+b');if(!$started)throw new RuntimeException('Cannot reserve operation start');
        $startedBytes=json_encode(['status'=>'started','operation'=>ANYTOUR_OFFER_CURRENT_V3_OPERATION,'noReplay'=>true],JSON_THROW_ON_ERROR)."\n";
        if(fwrite($started,$startedBytes)!==strlen($startedBytes)||!fflush($started)||!fsync($started))throw new RuntimeException('Durable start failed');fclose($started);

        $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
        if(!is_file($root.'/config.php')||!is_file($root.'/api-v2.php'))throw new RuntimeException('Existing project root required');
        foreach(['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key){if(getenv($key)!==false&&getenv($key)!=='')throw new RuntimeException('Unexpected process DB override');}
        $configHash=hash_file('sha256',$root.'/config.php');require_once $root.'/config.php';require_once __DIR__.'/../../v2/data/db-v1.php';
        $config=v2_data_db_config();$expectedName=anytour_offer_current_v3_database((string)$config['dsn']);if((string)$config['user']==='')throw new RuntimeException('Configured project DB user required');
        $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$expectedName)throw new RuntimeException('Selected database differs from project configuration');
        $result=anytour_offer_current_v3_collect($pdo);if($configHash!==hash_file('sha256',$root.'/config.php'))throw new RuntimeException('Project configuration changed during inspection');
        $result['operation']=ANYTOUR_OFFER_CURRENT_V3_OPERATION;$result['dataSource']=ANYTOUR_OFFER_CURRENT_V3_SOURCE;$result['executionSource']=$reservation['executionSource'];$result['run']=$reservation['run'];
        $result['projectConfigurationUnchanged']=true;$result['publicFileWrites']=0;$result['noReplay']=true;
        $sha=anytour_offer_current_v3_write_result($directory,$result);
        echo 'ANYTOUR_OFFER_CURRENT_V3_OK result_sha256='.$sha.' visible_rows='.$result['overall']['visibleRows'].' raw_rows='.$result['overall']['rawRows']." database_writes=0 supplier_calls=0\n";
    } catch(Throwable $e) {
        if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
        if($directory!==''&&is_dir($directory)&&!is_file($directory.'/result.json')){try{anytour_offer_current_v3_write_result($directory,['status'=>'inspection_failed','class'=>get_class($e),'databaseWrites'=>0,'supplierCalls'=>0,'noReplay'=>true]);}catch(Throwable){}}
        fwrite(STDERR,'ANYTOUR_OFFER_CURRENT_V3_FAILED class='.get_class($e).' database_writes=0; no replay'."\n");exit(1);
    }
}
