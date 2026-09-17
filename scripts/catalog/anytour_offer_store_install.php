<?php
/** One-shot installation of the provider-neutral AnyTour offer store. No population or supplier I/O. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_OFFER_INSTALL_OPERATION = 'anytour-offer-store-install-2693-20260917-v1';
const ANYTOUR_OFFER_INSTALL_SOURCE = 'a3f7b3b0aad1b7bd82dd3116fbc3e619466dd4ff';
const ANYTOUR_OFFER_INSTALL_PROOF_OPERATION = 'anytour-offer-store-current-2693-20260917-v2';
const ANYTOUR_OFFER_INSTALL_PROOF_SHA = 'a28dc6d3b32c5c0d4edf95188a07a59233d05e294ff7a815920a3895d2a33ce7';
const ANYTOUR_OFFER_INSTALL_TABLES = ['anytour_offer_store_control','anytour_offer_refreshes','anytour_offer_scope_state','anytour_offers'];

function anytour_offer_install_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function anytour_offer_install_database(string $dsn): string
{
    if (!str_starts_with($dsn,'mysql:')) throw new RuntimeException('Configured MySQL target required');
    $names=[]; foreach (explode(';',substr($dsn,6)) as $part) if (str_starts_with($part,'dbname=')) $names[]=substr($part,7);
    if (count($names)!==1 || !preg_match('/^[A-Za-z0-9_.-]+$/D',$names[0])) throw new RuntimeException('One explicit configured database required');
    return $names[0];
}
function anytour_offer_install_statements(): array
{
    $file=__DIR__.'/../../v2/data/migrations/20260916-anytour-offer-store.sql';
    $sql=file_get_contents($file); if ($sql===false) throw new RuntimeException('Pinned offer-store migration missing');
    $sql=preg_replace('/^\s*--.*$/m','',$sql);
    $sql=str_replace(['CREATE TABLE IF NOT EXISTS ','INSERT IGNORE INTO '],['CREATE TABLE ','INSERT INTO '],$sql);
    $out=[];
    foreach (array_filter(array_map('trim',explode(';',$sql))) as $statement) {
        if (!preg_match('/^(?:CREATE TABLE|INSERT INTO) (anytour_[a-z_]+)\b/',$statement,$m)
            || !in_array($m[1],ANYTOUR_OFFER_INSTALL_TABLES,true)) throw new RuntimeException('Unexpected offer-store migration statement');
        $out[]=$statement;
    }
    if (count($out)!==5) throw new RuntimeException('Pinned offer-store migration statement count changed');
    return $out;
}
function anytour_offer_install_presence(PDO $pdo): array
{
    $stmt=$pdo->prepare('SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count(ANYTOUR_OFFER_INSTALL_TABLES),'?')).') ORDER BY TABLE_NAME');
    $stmt->execute(ANYTOUR_OFFER_INSTALL_TABLES); $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[$row['TABLE_NAME']]=$row;
    return $rows;
}
function anytour_offer_install(PDO $pdo,array $proof,callable $checkpoint): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) {
        throw new RuntimeException('Dedicated exception-mode MySQL connection required');
    }
    if (($proof['status']??null)!=='current_read_only' || ($proof['operation']??null)!==ANYTOUR_OFFER_INSTALL_PROOF_OPERATION
        || ($proof['schemaState']??null)!=='absent' || ($proof['databaseWrites']??null)!==0 || ($proof['supplierCalls']??null)!==0
        || ($proof['canonical']['schemaVersion']??null)!==1 || ($proof['canonical']['activeHotels']??null)!==1000
        || ($proof['canonical']['legacyCatalogLinks']??null)!==1000) throw new RuntimeException('Exact absent CURRENT proof required');

    $identity=$pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
    if (!$identity || !hash_equals((string)$proof['targetIdentitySha256'],hash('sha256',anytour_offer_install_json($identity)))) throw new RuntimeException('Reviewed target database changed');
    if ((int)$pdo->query("SELECT GET_LOCK('anytour-offer-store-install-2693-v1',0)")->fetchColumn()!==1) throw new RuntimeException('Another offer-store installation owns the lock');
    try {
        $canonicalVersion=(int)$pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        $activeHotels=(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn();
        $legacyLinks=(int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
        if ($canonicalVersion!==1 || $activeHotels!==1000 || $legacyLinks!==1000) throw new RuntimeException('Canonical catalogue changed since CURRENT proof');
        if (anytour_offer_install_presence($pdo)!==[]) throw new RuntimeException('Existing/partial offer store requires inspection, not replay');

        $statements=anytour_offer_install_statements();
        $checkpoint(['status'=>'verified_before_ddl','tablesAbsent'=>4,'canonicalHotels'=>$activeHotels,'legacyCatalogLinks'=>$legacyLinks]);
        foreach ($statements as $i=>$statement) {
            $step=['statement'=>$i+1,'statementSha256'=>hash('sha256',$statement)];
            $checkpoint(['status'=>'ddl_attempting']+$step);
            $pdo->exec($statement);
            $checkpoint(['status'=>'ddl_completed']+$step);
        }
        $presence=anytour_offer_install_presence($pdo);
        if (array_keys($presence)!==ANYTOUR_OFFER_INSTALL_TABLES) {
            $sorted=array_keys($presence); $expected=ANYTOUR_OFFER_INSTALL_TABLES; sort($sorted); sort($expected);
            if ($sorted!==$expected) throw new RuntimeException('Installed table set differs');
        }
        foreach (ANYTOUR_OFFER_INSTALL_TABLES as $table) {
            if (($presence[$table]['ENGINE']??null)!=='InnoDB' || ($presence[$table]['TABLE_TYPE']??null)!=='BASE TABLE') throw new RuntimeException('Installed target is not InnoDB base table');
        }
        $version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        $counts=[]; foreach (ANYTOUR_OFFER_INSTALL_TABLES as $table) $counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $expected=['anytour_offer_store_control'=>1,'anytour_offer_refreshes'=>0,'anytour_offer_scope_state'=>0,'anytour_offers'=>0];
        if ($version!==1 || $counts!==$expected) throw new RuntimeException('Fresh offer-store counts/version differ');
        if ((int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn()!==1000
            || (int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn()!==1000) {
            throw new RuntimeException('Canonical catalogue changed during installation');
        }
        return ['status'=>'offer_store_installed_verified','schemaVersion'=>1,'counts'=>$counts,'canonicalHotels'=>1000,'legacyCatalogLinks'=>1000,
            'offersCreated'=>0,'databaseDataRowsAdded'=>1,'supplierCalls'=>0,'canonicalWrites'=>0,'publicFileWrites'=>0,'noReplay'=>true];
    } finally { $pdo->query("SELECT RELEASE_LOCK('anytour-offer-store-install-2693-v1')"); }
}

if (realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    $journal=null; $phase='before_ddl';
    try {
        if ($argc!==1 || basename(dirname(__DIR__,2))!=='payload') throw new RuntimeException('Fixed private invocation required');
        $dir=dirname(__DIR__,3); if (basename($dir)!==ANYTOUR_OFFER_INSTALL_OPERATION) throw new RuntimeException('Wrong operation directory');
        $reservation=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if (($reservation['operation']??null)!==ANYTOUR_OFFER_INSTALL_OPERATION || ($reservation['dataSource']??null)!==ANYTOUR_OFFER_INSTALL_SOURCE || ($reservation['attempt']??null)!==1) throw new RuntimeException('Wrong reservation');
        $journal=fopen($dir.'/journal.jsonl','x+b'); if (!$journal) throw new RuntimeException('Existing journal; no replay');
        $checkpoint=static function(array $state) use (&$journal,&$phase):void {$phase=$state['status'];$line=anytour_offer_install_json($state+['noReplay'=>true])."\n";if(fwrite($journal,$line)!==strlen($line)||!fflush($journal)||!fsync($journal))throw new RuntimeException('Durable checkpoint failed');};
        $checkpoint(['status'=>'reserved']);
        $proofFile=dirname($dir).'/'.ANYTOUR_OFFER_INSTALL_PROOF_OPERATION.'/result.json';
        if (!is_file($proofFile) || !hash_equals(ANYTOUR_OFFER_INSTALL_PROOF_SHA,hash_file('sha256',$proofFile))) throw new RuntimeException('Retained CURRENT proof differs');
        $proof=json_decode(file_get_contents($proofFile),true,32,JSON_THROW_ON_ERROR);
        $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
        if (!is_file($root.'/config.php') || !is_file($root.'/api-v2.php')) throw new RuntimeException('Existing project root required');
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) if(getenv($key)!==false&&getenv($key)!=='')throw new RuntimeException('Unexpected DB override');
        $configHash=hash_file('sha256',$root.'/config.php'); require_once $root.'/config.php'; require_once __DIR__.'/../../v2/data/db-v1.php';
        $config=v2_data_db_config();$expectedName=anytour_offer_install_database($config['dsn']);
        if (!hash_equals((string)$proof['databaseNameSha256'],hash('sha256',$expectedName))) throw new RuntimeException('Configured database differs from reviewed target');
        $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==$expectedName) throw new RuntimeException('Selected database differs from project config');
        $result=anytour_offer_install($pdo,$proof,$checkpoint);
        if ($configHash!==hash_file('sha256',$root.'/config.php')) throw new RuntimeException('Project configuration changed');
        $result+=['operation'=>ANYTOUR_OFFER_INSTALL_OPERATION,'readProofSha256'=>ANYTOUR_OFFER_INSTALL_PROOF_SHA,'dataSource'=>ANYTOUR_OFFER_INSTALL_SOURCE,'executionSource'=>$reservation['executionSource'],'run'=>$reservation['run'],'projectConfigurationUnchanged'=>true];
        $checkpoint($result);
        $out=fopen($dir.'/result.json','x+b');$bytes=anytour_offer_install_json($result)."\n";
        if(!$out||fwrite($out,$bytes)!==strlen($bytes)||!fflush($out)||!fsync($out))throw new RuntimeException('Final receipt incomplete');
        fclose($out);fclose($journal);$journal=null;
        echo 'ANYTOUR_OFFER_STORE_INSTALLED_VERIFIED result_sha256='.hash('sha256',$bytes)." offers=0 supplier_calls=0\n";
    } catch (Throwable $e) {
        if(is_resource($journal)){$line=json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($e)])."\n";fwrite($journal,$line);fflush($journal);fsync($journal);fclose($journal);}
        fwrite(STDERR,'ANYTOUR_OFFER_INSTALL_STOPPED phase='.$phase.' class='.get_class($e)."; DDL may be partial or complete, inspect journal, NO REPLAY\n");exit(1);
    }
}
