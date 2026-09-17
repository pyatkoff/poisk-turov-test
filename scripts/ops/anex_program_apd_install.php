<?php
/** One-shot additive install of direct-ANEX program/APD persistence tables. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_ANEX_APD_INSTALL_OPERATION = 'anex-program-apd-install-2506-20260918-v1';
const ANYTOUR_ANEX_APD_INSTALL_SOURCE = 'd8a11376535f6ea44ba0e83694a449fa54f84ab1';
const ANYTOUR_ANEX_APD_INSTALL_TABLES = [
    'anytour_anex_programs',
    'anytour_anex_program_contexts',
    'anytour_anex_apd_rates',
];

function aapi_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function aapi_db_name(string $dsn): string
{
    if (!str_starts_with($dsn,'mysql:')) throw new RuntimeException('Configured MySQL target required');
    $names=[];
    foreach (explode(';',substr($dsn,6)) as $part) if (str_starts_with($part,'dbname=')) $names[]=substr($part,7);
    if (count($names)!==1 || !preg_match('/^[A-Za-z0-9_.-]+$/D',$names[0])) {
        throw new RuntimeException('One explicit configured database required');
    }
    return $names[0];
}
function aapi_statements(): array
{
    $file=__DIR__.'/../../v2/data/migrations/20260918-anex-program-apd-cache.sql';
    $sql=file_get_contents($file);
    if (!is_string($sql) || $sql==='') throw new RuntimeException('Pinned ANEX APD migration missing');
    $sql=preg_replace('/^\s*--.*$/m','',$sql) ?? $sql;
    $sql=str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TABLE ',$sql);
    $out=[];
    foreach (array_filter(array_map('trim',explode(';',$sql))) as $statement) {
        if (!preg_match('/^CREATE TABLE (anytour_anex_[a-z_]+)\b/',$statement,$m)
            || !in_array($m[1],ANYTOUR_ANEX_APD_INSTALL_TABLES,true)) {
            throw new RuntimeException('Unexpected ANEX APD migration statement');
        }
        $out[]=$statement;
    }
    if (count($out)!==3) throw new RuntimeException('Pinned ANEX APD migration statement count changed');
    return $out;
}
function aapi_presence(PDO $pdo): array
{
    $q=$pdo->prepare(
        'SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES '
        .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('
        .implode(',',array_fill(0,count(ANYTOUR_ANEX_APD_INSTALL_TABLES),'?')).') ORDER BY TABLE_NAME'
    );
    $q->execute(ANYTOUR_ANEX_APD_INSTALL_TABLES);
    $rows=[];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[$row['TABLE_NAME']]=$row;
    return $rows;
}
function aapi_offer_store_state(PDO $pdo): array
{
    $tables=$pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
        ."AND TABLE_NAME IN ('anytour_offer_store_control','anytour_offers') ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);
    sort($tables,SORT_STRING);
    if ($tables!==['anytour_offer_store_control','anytour_offers']) {
        throw new RuntimeException('Current AnyTour offer store required');
    }
    $version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1 LIMIT 1')->fetchColumn();
    if ($version!==2) throw new RuntimeException('Current AnyTour offer-store schema v2 required');
    return [
        'schemaVersion'=>$version,
        'offerCount'=>(int)$pdo->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn(),
    ];
}
function aapi_install(PDO $pdo, callable $checkpoint): array
{
    if ($pdo->inTransaction()
        || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
        || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) {
        throw new RuntimeException('Dedicated exception-mode MySQL connection required');
    }

    $before=aapi_offer_store_state($pdo);
    if (aapi_presence($pdo)!==[]) throw new RuntimeException('Existing/partial ANEX APD schema requires inspection, not replay');
    if ((int)$pdo->query("SELECT GET_LOCK('anytour-anex-apd-install-2506-v1',0)")->fetchColumn()!==1) {
        throw new RuntimeException('Another ANEX APD installation owns the lock');
    }
    try {
        $identity=$pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($identity) || !is_string($identity['db']??null) || $identity['db']==='') {
            throw new RuntimeException('Target database identity unavailable');
        }
        $checkpoint([
            'status'=>'verified_before_ddl',
            'offerStoreSchemaVersion'=>$before['schemaVersion'],
            'offerCountBefore'=>$before['offerCount'],
            'tablesAbsent'=>3,
            'targetIdentitySha256'=>hash('sha256',aapi_json($identity)),
        ]);

        $statements=aapi_statements();
        foreach ($statements as $i=>$statement) {
            $step=['statement'=>$i+1,'statementSha256'=>hash('sha256',$statement)];
            $checkpoint(['status'=>'ddl_attempting']+$step);
            $pdo->exec($statement);
            $checkpoint(['status'=>'ddl_completed']+$step);
        }

        $presence=aapi_presence($pdo);
        $actual=array_keys($presence); sort($actual,SORT_STRING);
        $expected=ANYTOUR_ANEX_APD_INSTALL_TABLES; sort($expected,SORT_STRING);
        if ($actual!==$expected) throw new RuntimeException('Installed ANEX APD table set differs');
        foreach (ANYTOUR_ANEX_APD_INSTALL_TABLES as $table) {
            if (($presence[$table]['ENGINE']??null)!=='InnoDB' || ($presence[$table]['TABLE_TYPE']??null)!=='BASE TABLE') {
                throw new RuntimeException('Installed ANEX APD target is not InnoDB base table');
            }
        }
        $counts=[];
        foreach (ANYTOUR_ANEX_APD_INSTALL_TABLES as $table) {
            $counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        }
        if ($counts!==[
            'anytour_anex_programs'=>0,
            'anytour_anex_program_contexts'=>0,
            'anytour_anex_apd_rates'=>0,
        ]) throw new RuntimeException('Fresh ANEX APD tables are not empty');

        $after=aapi_offer_store_state($pdo);
        if ($after!==$before) throw new RuntimeException('Existing AnyTour offer-store state changed during install');

        return [
            'status'=>'anex_program_apd_schema_installed_verified',
            'offerStoreSchemaVersion'=>$after['schemaVersion'],
            'offerCountBefore'=>$before['offerCount'],
            'offerCountAfter'=>$after['offerCount'],
            'counts'=>$counts,
            'tablesCreated'=>3,
            'supplierCalls'=>0,
            'offerWrites'=>0,
            'mappingWrites'=>0,
            'publicFileWrites'=>0,
            'noReplay'=>true,
        ];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('anytour-anex-apd-install-2506-v1')");
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    $journal=null; $phase='before_ddl';
    try {
        if ($argc!==1 || basename(dirname(__DIR__,2))!=='payload') throw new RuntimeException('Fixed private invocation required');
        $dir=dirname(__DIR__,3);
        if (basename($dir)!==ANYTOUR_ANEX_APD_INSTALL_OPERATION) throw new RuntimeException('Wrong operation directory');
        $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if (($reservation['operation']??null)!==ANYTOUR_ANEX_APD_INSTALL_OPERATION
            || ($reservation['dataSource']??null)!==ANYTOUR_ANEX_APD_INSTALL_SOURCE
            || ($reservation['attempt']??null)!==1
            || ($reservation['noReplay']??null)!==true) throw new RuntimeException('Wrong reservation');

        $journal=fopen($dir.'/journal.jsonl','x+b');
        if (!$journal) throw new RuntimeException('Existing journal; no replay');
        $checkpoint=static function(array $state) use (&$journal,&$phase):void {
            $phase=(string)($state['status']??'unknown');
            $line=aapi_json($state+['noReplay'=>true])."\n";
            if (fwrite($journal,$line)!==strlen($line)||!fflush($journal)||!fsync($journal)) {
                throw new RuntimeException('Durable checkpoint failed');
            }
        };
        $checkpoint(['status'=>'reserved']);

        $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
        if (!is_file($root.'/config.php') || !is_file($root.'/api-v2.php')) throw new RuntimeException('Existing project root required');
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
            if (getenv($key)!==false && getenv($key)!=='') throw new RuntimeException('Unexpected DB override');
        }
        $configHash=hash_file('sha256',$root.'/config.php');
        require_once $root.'/config.php';
        require_once __DIR__.'/../../v2/data/db-v1.php';
        $config=v2_data_db_config();
        $expectedName=aapi_db_name($config['dsn']);
        $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT=>5,
        ]);
        if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==$expectedName) throw new RuntimeException('Selected database differs from project config');

        $result=aapi_install($pdo,$checkpoint);
        if ($configHash!==hash_file('sha256',$root.'/config.php')) throw new RuntimeException('Project configuration changed');
        $result += [
            'operation'=>ANYTOUR_ANEX_APD_INSTALL_OPERATION,
            'dataSource'=>ANYTOUR_ANEX_APD_INSTALL_SOURCE,
            'executionSource'=>$reservation['executionSource'],
            'run'=>$reservation['run'],
            'databaseNameSha256'=>hash('sha256',$expectedName),
            'projectConfigurationUnchanged'=>true,
        ];
        $checkpoint($result);
        $out=fopen($dir.'/result.json','x+b');
        $bytes=aapi_json($result)."\n";
        if (!$out || fwrite($out,$bytes)!==strlen($bytes) || !fflush($out) || !fsync($out)) {
            throw new RuntimeException('Final receipt incomplete');
        }
        fclose($out); fclose($journal); $journal=null;
        echo 'ANEX_PROGRAM_APD_SCHEMA_INSTALLED_VERIFIED result_sha256='.hash('sha256',$bytes)
            .' offers_unchanged='.$result['offerCountAfter']." supplier_calls=0\n";
    } catch (Throwable $e) {
        if (is_resource($journal)) {
            $line=json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($e)])."\n";
            fwrite($journal,$line);fflush($journal);fsync($journal);fclose($journal);
        }
        fwrite(STDERR,'ANEX_APD_INSTALL_STOPPED phase='.$phase.' class='.get_class($e)
            ."; DDL may be partial or complete, inspect journal, NO REPLAY\n");
        exit(1);
    }
}
