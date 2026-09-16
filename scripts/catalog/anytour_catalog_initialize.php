<?php
/** Fixed first installation and saved-data seed. Never publishes or calls suppliers. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/anytour_catalog_current.php';
const ANYTOUR_INIT_OPERATION = 'anytour-catalog-initialize-1646-20260916-v1';
const ANYTOUR_INIT_PROOF_SHA = 'cecf60316e783cfaffb40d61b7f5f0d91c1f93d6b088276b2e167e82d70b5c5e';
const ANYTOUR_INIT_TABLES = ['anytour_catalog_control','anytour_hotels','anytour_hotel_sources','anytour_meal_plans','anytour_room_categories','anytour_hotel_rooms','anytour_stay_mappings'];

function anytour_init_statements(): array
{
    $statements=[];
    foreach (['20260916-anytour-canonical-catalog.sql','20260916-anytour-stay-catalog.sql'] as $name) {
        $sql=file_get_contents(__DIR__.'/../../v2/data/migrations/'.$name);
        if ($sql===false) throw new RuntimeException('Pinned migration missing');
        $sql=preg_replace('/^--.*$/m','',$sql);
        // Fail on a raced/partial installation rather than silently adopting existing objects.
        $sql=str_replace(['CREATE TABLE IF NOT EXISTS ','INSERT IGNORE INTO '],['CREATE TABLE ','INSERT INTO '],$sql);
        foreach (array_filter(array_map('trim',explode(';',$sql))) as $statement) {
            if (!preg_match('/^(?:CREATE TABLE|INSERT INTO) (anytour_[a-z_]+)\s*\(/',$statement,$m)
                || !in_array($m[1],ANYTOUR_INIT_TABLES,true)) throw new RuntimeException('Unexpected migration statement');
            $statements[]=$statement;
        }
    }
    if (count($statements)!==10) throw new RuntimeException('Pinned migration statement count changed');
    return $statements;
}

function anytour_initialize(PDO $pdo,array $proof,callable $checkpoint): array
{
    if ($pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
        || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) throw new RuntimeException('Dedicated MySQL connection required');
    $pre=$proof['preflight'] ?? [];
    $ids=AnyTourCanonicalCatalog::ids($pre['requestedIds'] ?? []);
    if (($proof['status'] ?? '')!=='current_read_only' || ($pre['target_schema_state'] ?? '')!=='absent'
        || count($ids)!==1000 || ($pre['profiles_with_description'] ?? 0)!==1000 || ($pre['profiles_with_images'] ?? 0)!==1000) {
        throw new RuntimeException('Reviewed complete 1000-profile absent-schema proof required');
    }
    $statements=anytour_init_statements();
    $identity=$pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
    if (!preg_match('/^8\.0\./',(string)$identity['version'])
        || !hash_equals($proof['targetIdentitySha256'],hash('sha256',AnyTourCanonicalCatalog::json($identity)))) {
        throw new RuntimeException('Reviewed target database/version changed');
    }
    if ((int)$pdo->query("SELECT GET_LOCK('anytour-canonical-initialize-1646-v1',0)")->fetchColumn()!==1) throw new RuntimeException('Another initialization owns the lock');
    try {
        $exists=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,7,'?')).')');
        $exists->execute(ANYTOUR_INIT_TABLES);
        if ($exists->fetchAll(PDO::FETCH_COLUMN)!==[]) throw new RuntimeException('Existing/partial target requires inspection, not replay');
        foreach (['catalog_hotels','catalog_hotel_details'] as $table) {
            $ddl=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            if (!hash_equals($proof['tables'][$table]['ddlSha256'],hash('sha256',(string)$ddl[1]))) throw new RuntimeException('Source schema changed');
        }
        $catalog=new AnyTourCanonicalCatalog($pdo);
        $current=$catalog->preflight($ids);
        if ($current['target_schema_state']!=='absent' || !hash_equals($pre['source_sha256'],$current['source_sha256'])
            || $current['missingIds']!==[] || $current['unnamedIds']!==[]) throw new RuntimeException('Retained source cohort changed');
        $checkpoint(['status'=>'verified_before_ddl','sourceSha256'=>$current['source_sha256'],'tablesAbsent'=>7]);
        foreach ($statements as $i=>$statement) {
            $step=['statement'=>$i+1,'statementSha256'=>hash('sha256',$statement)];
            $checkpoint(['status'=>'ddl_attempting']+$step);
            $pdo->exec($statement);
            $checkpoint(['status'=>'ddl_verified']+$step);
        }
        $counts=[];
        foreach (ANYTOUR_INIT_TABLES as $table) $counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($counts!==array_combine(ANYTOUR_INIT_TABLES,[1,0,0,10,10,0,0])) throw new RuntimeException('Fresh schema seed counts differ');
        $checkpoint(['status'=>'schema_installed','counts'=>$counts]);
        // The unchanged seed owns COMMIT and independent readback. Any failure after this
        // boundary is conservatively unknown/unverified, never permission to run it again.
        $checkpoint(['status'=>'seed_attempting','profiles'=>1000,'sourceSha256'=>$pre['source_sha256']]);
        $seed=$catalog->seed($ids,$pre['source_sha256']);
        if ($seed['status']!=='committed_verified' || $seed['created']!==1000 || $seed['verified_bridges']!==1000
            || $seed['source_snapshots_refreshed']!==0) throw new RuntimeException('Committed seed result differs');
        $checkpoint(['status'=>'seed_committed_verified','seed'=>$seed]);
        $bridges=$catalog->legacyTargets($ids);
        $profiles=$catalog->read(array_values($bridges));
        if (count($bridges)!==1000 || count($profiles['items'])!==1000 || $profiles['missingIds']!==[]) throw new RuntimeException('Own-ID profile readback incomplete');
        $descriptions=$images=0; $samples=[]; $reverse=array_flip($bridges);
        foreach ($profiles['items'] as $profile) {
            if (trim((string)($profile['description'] ?? ''))!=='') $descriptions++;
            if (!empty($profile['images'])) $images++;
            if (count($samples)<5) $samples[]=['anytourId'=>$profile['id'],'legacyId'=>$reverse[$profile['id']],
                'name'=>$profile['name'],'imageCount'=>count($profile['images'] ?? [])];
        }
        if ($descriptions!==1000 || $images!==1000) throw new RuntimeException('Canonical content readback differs');
        if (!hash_equals($pre['source_sha256'],$catalog->preflight($ids)['source_sha256'])) throw new RuntimeException('Legacy source changed during operation');
        foreach (ANYTOUR_INIT_TABLES as $table) $counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($counts!==array_combine(ANYTOUR_INIT_TABLES,[1,1000,1000,10,10,0,0])) throw new RuntimeException('Final catalogue counts differ');
        return ['status'=>'initialized_seeded_verified','counts'=>$counts,'seed'=>$seed,'profilesWithDescription'=>$descriptions,
            'profilesWithImages'=>$images,'samples'=>$samples,'sourceCohortUnchanged'=>true,'legacyWrites'=>0,
            'realRoomMappingsCreated'=>0,'realMealMappingsCreated'=>0,'supplierCalls'=>0,'noReplay'=>true];
    } finally { $pdo->query("SELECT RELEASE_LOCK('anytour-canonical-initialize-1646-v1')"); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    $journal=null; $phase='before_ddl';
    try {
        if ($argc!==1 || basename(dirname(__DIR__,2))!=='payload') throw new RuntimeException('Fixed private invocation required');
        $dir=dirname(__DIR__,3);
        if (basename($dir)!==ANYTOUR_INIT_OPERATION) throw new RuntimeException('Wrong operation directory');
        $r=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if ($r['operation']!==ANYTOUR_INIT_OPERATION || $r['dataSource']!==ANYTOUR_CURRENT_SOURCE || $r['attempt']!==1) throw new RuntimeException('Wrong reservation');
        $journal=fopen($dir.'/journal.jsonl','x+b');
        if (!$journal) throw new RuntimeException('Existing journal; no replay');
        $checkpoint=static function(array $state) use (&$journal,&$phase): void {
            $phase=$state['status']; $line=AnyTourCanonicalCatalog::json($state+['noReplay'=>true])."\n";
            if (fwrite($journal,$line)!==strlen($line) || !fflush($journal) || !fsync($journal)) throw new RuntimeException('Durable checkpoint failed');
        };
        $checkpoint(['status'=>'reserved']);
        $proofFile=dirname($dir).'/'.ANYTOUR_CURRENT_OPERATION.'/result.json';
        if (!is_file($proofFile) || !hash_equals(ANYTOUR_INIT_PROOF_SHA,hash_file('sha256',$proofFile))) throw new RuntimeException('Retained on-host read proof differs');
        $proof=json_decode(file_get_contents($proofFile),true,32,JSON_THROW_ON_ERROR);
        $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
        foreach (['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key) {
            if (getenv($key)!==false && getenv($key)!=='') throw new RuntimeException('Unexpected DB override');
        }
        $configHash=hash_file('sha256',$root.'/config.php');
        require_once $root.'/config.php'; require_once __DIR__.'/../../v2/data/db-v1.php';
        $config=v2_data_db_config(); $expectedName=anytour_current_database($config['dsn']);
        if (!hash_equals($proof['databaseNameSha256'],hash('sha256',$expectedName))) throw new RuntimeException('Configured database differs from reviewed target');
        $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        $result=anytour_initialize($pdo,$proof,$checkpoint);
        if ($configHash!==hash_file('sha256',$root.'/config.php')) throw new RuntimeException('Project configuration changed');
        $result+=['operation'=>ANYTOUR_INIT_OPERATION,'readProofSha256'=>ANYTOUR_INIT_PROOF_SHA,'dataSource'=>ANYTOUR_CURRENT_SOURCE,
            'executionSource'=>$r['executionSource'],'run'=>$r['run'],'projectConfigurationUnchanged'=>true,'publicFileWrites'=>0];
        $checkpoint($result);
        $out=fopen($dir.'/result.json','x+b'); $bytes=AnyTourCanonicalCatalog::json($result)."\n";
        if (!$out || fwrite($out,$bytes)!==strlen($bytes) || !fflush($out) || !fsync($out)) throw new RuntimeException('Final receipt incomplete');
        fclose($out); fclose($journal); $journal=null;
        echo 'ANYTOUR_CATALOG_INITIALIZED_VERIFIED result_sha256='.hash('sha256',$bytes)." own_hotels=1000 supplier_calls=0\n";
    } catch (Throwable $e) {
        if (is_resource($journal)) {
            $line=json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($e)])."\n";
            fwrite($journal,$line); fflush($journal); fsync($journal); fclose($journal);
        }
        fwrite(STDERR,'ANYTOUR_INIT_STOPPED phase='.$phase.' class='.get_class($e)."; DDL/commit may be partial or complete, inspect journal, NO REPLAY\n");
        exit(1);
    }
}
