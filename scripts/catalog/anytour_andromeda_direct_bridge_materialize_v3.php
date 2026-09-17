<?php
/** Unique v3 no-replay runner over the already-proven bounded next-missing bridge materializer. */
declare(strict_types=1);
require_once __DIR__ . '/anytour_andromeda_direct_bridge_materialize_v2.php';

const ANYTOUR_ANDROMEDA_BRIDGE_V3_OPERATION = 'local-andromeda-direct-bridge-materialize-2690-20260917-v3';
const ANYTOUR_ANDROMEDA_BRIDGE_V3_SOURCE = '1b5076f43f91a0cbe9dfb2f8308af11363868030';
const ANYTOUR_ANDROMEDA_BRIDGE_V3_LIMIT = 1000;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $journal = null; $phase = 'before_apply';
    try {
        if ($argc !== 1 || basename(dirname(__DIR__, 2)) !== 'payload') throw new RuntimeException('Fixed private invocation required');
        $dir = dirname(__DIR__, 3);
        if (basename($dir) !== ANYTOUR_ANDROMEDA_BRIDGE_V3_OPERATION) throw new RuntimeException('Wrong operation directory');
        $reservation = json_decode((string)file_get_contents($dir.'/reservation.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($reservation['operation'] ?? '') !== ANYTOUR_ANDROMEDA_BRIDGE_V3_OPERATION
            || ($reservation['dataSource'] ?? '') !== ANYTOUR_ANDROMEDA_BRIDGE_V3_SOURCE
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
        $result = anytour_andromeda_direct_materialize($pdo,ANYTOUR_ANDROMEDA_BRIDGE_V3_LIMIT,new DateTimeImmutable('now',new DateTimeZone('UTC')),$checkpoint);
        if (!hash_equals($configHash,(string)hash_file('sha256',$configFile))) throw new RuntimeException('Project configuration changed');
        $result += [
            'operation'=>ANYTOUR_ANDROMEDA_BRIDGE_V3_OPERATION,
            'dataSource'=>ANYTOUR_ANDROMEDA_BRIDGE_V3_SOURCE,
            'executionSource'=>(string)($reservation['executionSource'] ?? ''),
            'run'=>(int)($reservation['run'] ?? 0),
            'projectConfigurationUnchanged'=>true,
        ];
        $checkpoint($result);
        $bytes = json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        $out = fopen($dir.'/result.json','x+b');
        if (!$out || fwrite($out,$bytes)!==strlen($bytes) || !fflush($out) || !fsync($out)) throw new RuntimeException('Final receipt incomplete');
        fclose($out); fclose($journal); $journal=null;
        echo 'ANYTOUR_ANDROMEDA_DIRECT_BRIDGE_V3_VERIFIED result_sha256='.hash('sha256',$bytes).' created='.$result['created'].' supplier_calls=0'."\n";
    } catch (Throwable $error) {
        if (is_resource($journal)) {
            $line=json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($error)])."\n";
            fwrite($journal,$line); fflush($journal); fsync($journal); fclose($journal);
        }
        fwrite(STDERR,'ANYTOUR_ANDROMEDA_DIRECT_BRIDGE_V3_STOPPED phase='.$phase.' class='.get_class($error).'; COMMIT may be complete, inspect journal/result before any next operation, NO REPLAY'."\n");
        exit(1);
    }
}
