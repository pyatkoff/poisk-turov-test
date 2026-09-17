<?php
/** One-shot bounded materialization of CURRENT accepted Andromeda identities onto existing AnyTour profiles. */
declare(strict_types=1);
require_once __DIR__ . '/../../v2/data/anytour-provider-identity-bridge-v1.php';

const ANYTOUR_ANDROMEDA_BRIDGE_OPERATION = 'local-andromeda-direct-bridge-materialize-2690-20260917-v1';
const ANYTOUR_ANDROMEDA_BRIDGE_SOURCE = '437fc4896a6f4741553e4109851d7faab7732dc2';
const ANYTOUR_ANDROMEDA_BRIDGE_LIMIT = 1000;

function anytour_andromeda_direct_count(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='provider_ref_digest:andromeda'")->fetchColumn();
}

/** @return list<array{supplier_namespace:string,external_hotel_id:string,local_hotel_id:int,anytour_hotel_id:int}> */
function anytour_andromeda_direct_candidates(PDO $pdo, int $limit): array
{
    if ($limit < 1 || $limit > 1000) throw new InvalidArgumentException('ANYTOUR_ANDROMEDA_BRIDGE_LIMIT');
    $sql = "SELECT a.supplier_namespace,CAST(a.external_hotel_id AS CHAR) AS external_hotel_id,
                   a.local_hotel_id,s.anytour_hotel_id
            FROM (
                SELECT supplier_namespace,external_hotel_id,MIN(local_hotel_id) AS local_hotel_id
                FROM andromeda_hotel_identities
                WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL
                GROUP BY supplier_namespace,external_hotel_id
                HAVING COUNT(*)=1 AND COUNT(DISTINCT local_hotel_id)=1
            ) a
            JOIN (
                SELECT CAST(external_key AS CHAR) AS external_key,MIN(anytour_hotel_id) AS anytour_hotel_id
                FROM anytour_hotel_sources
                WHERE namespace='legacy_catalog'
                GROUP BY external_key
                HAVING COUNT(*)=1
            ) s ON s.external_key=CAST(a.local_hotel_id AS CHAR)
            JOIN anytour_hotels h ON h.id=s.anytour_hotel_id AND h.is_active=1
            LEFT JOIN anytour_hotel_sources d
              ON d.namespace='provider_ref_digest:andromeda'
             AND d.external_key=SHA2(CONCAT(a.supplier_namespace,':',CAST(a.external_hotel_id AS CHAR)),256)
            WHERE d.external_key IS NULL
            ORDER BY a.supplier_namespace ASC,CAST(a.external_hotel_id AS CHAR) ASC
            LIMIT " . $limit;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $namespace = (string)($row['supplier_namespace'] ?? '');
        $external = (string)($row['external_hotel_id'] ?? '');
        $legacy = filter_var($row['local_hotel_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $own = filter_var($row['anytour_hotel_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!preg_match('/\A[a-z0-9_]{1,64}\z/D', $namespace)
            || $external === '' || strlen($external) > 120 || preg_match('/[\x00-\x1F\x7F:]/', $external)
            || $legacy === false || $own === false) {
            throw new RuntimeException('CURRENT accepted bridge candidate is malformed');
        }
        $out[] = [
            'supplier_namespace'=>$namespace,
            'external_hotel_id'=>$external,
            'local_hotel_id'=>(int)$legacy,
            'anytour_hotel_id'=>(int)$own,
        ];
    }
    return $out;
}

function anytour_andromeda_direct_plan_sha(array $rows): string
{
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function anytour_andromeda_direct_materialize(PDO $pdo, int $limit, DateTimeImmutable $now, callable $checkpoint): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
        || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
        throw new RuntimeException('Dedicated MySQL connection required');
    }
    if ((int)$pdo->query("SELECT GET_LOCK('anytour-andromeda-direct-bridge-2690-v1',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Another direct bridge materializer owns the lock');
    }
    try {
        $before = anytour_andromeda_direct_count($pdo);
        $selected = anytour_andromeda_direct_candidates($pdo, $limit);
        if ($selected === []) {
            return [
                'status'=>'andromeda_direct_bridge_nothing_to_materialize_verified',
                'selected'=>0,'created'=>0,'verified'=>0,
                'directBefore'=>$before,'directAfter'=>$before,
                'mappingWrites'=>0,'supplierCalls'=>0,'publicFileWrites'=>0,'noReplay'=>true,
            ];
        }
        $planSha = anytour_andromeda_direct_plan_sha($selected);
        $checkpoint(['status'=>'direct_bridge_planned','selected'=>count($selected),'planSha256'=>$planSha,'directBefore'=>$before]);

        $fresh = anytour_andromeda_direct_candidates($pdo, $limit);
        if (!hash_equals($planSha, anytour_andromeda_direct_plan_sha($fresh))) {
            throw new RuntimeException('CURRENT accepted bridge cohort changed before apply');
        }
        $refs = array_map(static fn(array $row): array => [
            'supplier_namespace'=>$row['supplier_namespace'],
            'external_hotel_id'=>$row['external_hotel_id'],
        ], $selected);
        $checkpoint(['status'=>'direct_bridge_attempting','selected'=>count($selected),'planSha256'=>$planSha]);
        $receipt = AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($pdo, $refs, $now);
        if (($receipt['source'] ?? '') !== 'anytour-provider-identity-bridge-v1'
            || ($receipt['provider'] ?? '') !== 'andromeda'
            || (int)($receipt['requested'] ?? -1) !== count($selected)
            || (int)($receipt['materialized'] ?? -1) !== count($selected)
            || (int)($receipt['verified'] ?? -1) !== count($selected)
            || (int)($receipt['created'] ?? -1) !== count($selected)
            || (int)($receipt['refreshed'] ?? -1) !== 0
            || (int)($receipt['unchanged'] ?? -1) !== 0
            || (int)($receipt['unresolved'] ?? -1) !== 0
            || (int)($receipt['mapping_writes'] ?? -1) !== 0
            || (int)($receipt['supplier_calls'] ?? -1) !== 0) {
            throw new RuntimeException('Committed direct bridge result differs from sealed cohort');
        }
        $checkpoint(['status'=>'direct_bridge_committed','receipt'=>$receipt]);

        $offerRows = array_map(static fn(array $row): array => [
            'provider'=>'andromeda',
            'provider_hotel_ref_digest'=>AnyTourProviderIdentityBridgeV1::providerRefDigest($row['supplier_namespace'].':'.$row['external_hotel_id']),
            'legacy_hotel_id'=>$row['local_hotel_id'],
            'anytour_hotel_id'=>$row['anytour_hotel_id'],
        ], $selected);
        $verified = AnyTourProviderIdentityBridgeV1::filterOfferRows($pdo, $offerRows);
        if (count($verified) !== count($selected)) {
            throw new RuntimeException('Post-COMMIT CURRENT direct bridge readback incomplete');
        }
        $after = anytour_andromeda_direct_count($pdo);
        if ($after !== $before + count($selected)) {
            throw new RuntimeException('Direct bridge count delta differs after COMMIT');
        }
        return [
            'status'=>'andromeda_direct_bridge_materialized_verified',
            'selected'=>count($selected),'created'=>count($selected),'verified'=>count($verified),
            'planSha256'=>$planSha,'directBefore'=>$before,'directAfter'=>$after,
            'receipt'=>$receipt,'mappingWrites'=>0,'supplierCalls'=>0,'publicFileWrites'=>0,'noReplay'=>true,
        ];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('anytour-andromeda-direct-bridge-2690-v1')");
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $journal = null; $phase = 'before_apply';
    try {
        if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private invocation required');
        $dir = dirname(__DIR__, 3);
        if (basename($dir) !== ANYTOUR_ANDROMEDA_BRIDGE_OPERATION) throw new RuntimeException('Wrong operation directory');
        $reservation = json_decode((string)file_get_contents($dir.'/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? '') !== ANYTOUR_ANDROMEDA_BRIDGE_OPERATION
            || ($reservation['dataSource'] ?? '') !== ANYTOUR_ANDROMEDA_BRIDGE_SOURCE
            || ($reservation['attempt'] ?? 0) !== 1 || ($reservation['noReplay'] ?? false) !== true) {
            throw new RuntimeException('Wrong reservation');
        }
        $journal = fopen($dir.'/journal.jsonl', 'x+b');
        if (!$journal) throw new RuntimeException('Existing journal; no replay');
        $checkpoint = static function(array $state) use (&$journal,&$phase): void {
            $phase = (string)($state['status'] ?? 'unknown');
            $line = json_encode($state + ['noReplay'=>true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
            if (fwrite($journal,$line)!==strlen($line) || !fflush($journal) || !fsync($journal)) throw new RuntimeException('Durable checkpoint failed');
        };
        $checkpoint(['status'=>'reserved']);

        $root = rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
            if (getenv($key) !== false && getenv($key) !== '') throw new RuntimeException('Unexpected DB override');
        }
        $configFile = $root.'/config.php';
        $configHash = hash_file('sha256',$configFile);
        if ($configHash === false) throw new RuntimeException('Project configuration missing');
        require_once $configFile;
        require_once $root.'/_preview/search3-local-candidate/data/db-v1.php';
        $config = v2_data_db_config();
        if (!str_starts_with((string)$config['dsn'],'mysql:') || trim((string)$config['user'])==='') throw new RuntimeException('Explicit project MySQL configuration required');
        $pdo = new PDO($config['dsn'],$config['user'],$config['password'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT=>5,
        ]);
        $result = anytour_andromeda_direct_materialize($pdo,ANYTOUR_ANDROMEDA_BRIDGE_LIMIT,new DateTimeImmutable('now',new DateTimeZone('UTC')),$checkpoint);
        if (!hash_equals($configHash,(string)hash_file('sha256',$configFile))) throw new RuntimeException('Project configuration changed');
        $result += [
            'operation'=>ANYTOUR_ANDROMEDA_BRIDGE_OPERATION,
            'dataSource'=>ANYTOUR_ANDROMEDA_BRIDGE_SOURCE,
            'executionSource'=>(string)($reservation['executionSource'] ?? ''),
            'run'=>(int)($reservation['run'] ?? 0),
            'projectConfigurationUnchanged'=>true,
        ];
        $checkpoint($result);
        $bytes = json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        $out = fopen($dir.'/result.json','x+b');
        if (!$out || fwrite($out,$bytes)!==strlen($bytes) || !fflush($out) || !fsync($out)) throw new RuntimeException('Final receipt incomplete');
        fclose($out); fclose($journal); $journal=null;
        echo 'ANYTOUR_ANDROMEDA_DIRECT_BRIDGE_VERIFIED result_sha256='.hash('sha256',$bytes).' created='.$result['created'].' supplier_calls=0'."\n";
    } catch (Throwable $error) {
        if (is_resource($journal)) {
            $line=json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($error)])."\n";
            fwrite($journal,$line); fflush($journal); fsync($journal); fclose($journal);
        }
        fwrite(STDERR,'ANYTOUR_ANDROMEDA_DIRECT_BRIDGE_STOPPED phase='.$phase.' class='.get_class($error).'; COMMIT may be complete, inspect journal/result before any next operation, NO REPLAY'."\n");
        exit(1);
    }
}
