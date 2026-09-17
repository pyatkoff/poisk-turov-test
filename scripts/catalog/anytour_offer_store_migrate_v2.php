<?php
/** One-shot offer-store v1 -> v2 migration for the verified-empty live AnyTour store. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_OFFER_MIGRATE_V2_OPERATION = 'anytour-offer-store-migrate-v2-2693-20260917-v1';
const ANYTOUR_OFFER_MIGRATE_V2_SOURCE = '173fb7abade6c672d57309b4bb867936e2860411';
const ANYTOUR_OFFER_MIGRATE_V2_TABLES = ['anytour_offer_store_control','anytour_offer_refreshes','anytour_offer_scope_state','anytour_offers'];

function anytour_offer_migrate_v2_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function anytour_offer_migrate_v2_database(string $dsn): string
{
    if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Configured MySQL target required');
    $names=[];foreach(explode(';',substr($dsn,6)) as $part)if(str_starts_with($part,'dbname='))$names[]=substr($part,7);
    if(count($names)!==1||!preg_match('/^[A-Za-z0-9_.-]+$/D',$names[0]))throw new RuntimeException('One explicit configured database required');
    return $names[0];
}
function anytour_offer_migrate_v2_statements(): array
{
    $path=__DIR__.'/../../v2/data/migrations/20260917-anytour-offer-store-v2.sql';
    $sql=file_get_contents($path);if($sql===false)throw new RuntimeException('Pinned v2 migration missing');
    $sql=preg_replace('/^\s*--.*$/m','',$sql);$statements=[];
    foreach(array_filter(array_map('trim',explode(';',$sql))) as $statement){
        if(!preg_match('/^(?:ALTER TABLE anytour_offers|UPDATE anytour_offer_store_control\b)/',$statement))throw new RuntimeException('Unexpected v2 migration statement');
        $statements[]=$statement;
    }
    if(count($statements)!==2)throw new RuntimeException('Pinned v2 migration statement count changed');
    return $statements;
}
function anytour_offer_migrate_v2_counts(PDO $pdo): array
{
    $out=[];foreach(ANYTOUR_OFFER_MIGRATE_V2_TABLES as $table)$out[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();return$out;
}
function anytour_offer_migrate_v2_index(PDO $pdo,string $name): array
{
    $stmt=$pdo->prepare("SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_offers' AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX");
    $stmt->execute([$name]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);if($rows===[])return[];
    if(count(array_unique(array_column($rows,'NON_UNIQUE')))!==1)throw new RuntimeException('Index metadata inconsistent');
    return['unique'=>(int)$rows[0]['NON_UNIQUE']===0,'columns'=>array_values(array_column($rows,'COLUMN_NAME'))];
}
function anytour_offer_migrate_v2(PDO $pdo,int $expectedHotels,int $expectedLinks,callable $checkpoint): array
{
    if($pdo->inTransaction()||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION)throw new RuntimeException('Dedicated exception-mode MySQL connection required');
    if((int)$pdo->query("SELECT GET_LOCK('anytour-offer-store-migrate-v2-2693',0)")->fetchColumn()!==1)throw new RuntimeException('Another offer-store migration owns the lock');
    try{
        $canonicalVersion=(int)$pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        $hotels=(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn();
        $links=(int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
        if($canonicalVersion!==1||$hotels!==$expectedHotels||$links!==$expectedLinks)throw new RuntimeException('Canonical catalogue differs from migration precondition');
        $version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        $counts=anytour_offer_migrate_v2_counts($pdo);
        if($version!==1||$counts!==['anytour_offer_store_control'=>1,'anytour_offer_refreshes'=>0,'anytour_offer_scope_state'=>0,'anytour_offers'=>0])throw new RuntimeException('Offer store is not verified-empty schema v1');
        $old=anytour_offer_migrate_v2_index($pdo,'uq_anytour_offer_identity');$new=anytour_offer_migrate_v2_index($pdo,'uq_anytour_offer_refresh_identity');
        if($old!==['unique'=>true,'columns'=>['provider','scope_sha256','identity_sha256']]||$new!==[])throw new RuntimeException('Offer identity index is not exact v1 state');
        $checkpoint(['status'=>'verified_before_ddl','schemaVersion'=>1,'counts'=>$counts,'canonicalHotels'=>$hotels,'legacyCatalogLinks'=>$links,'oldIndex'=>$old]);
        foreach(anytour_offer_migrate_v2_statements() as $i=>$statement){
            $step=['statement'=>$i+1,'statementSha256'=>hash('sha256',$statement)];$checkpoint(['status'=>'ddl_attempting']+$step);$pdo->exec($statement);$checkpoint(['status'=>'ddl_completed']+$step);
        }
        $version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();$counts=anytour_offer_migrate_v2_counts($pdo);
        $old=anytour_offer_migrate_v2_index($pdo,'uq_anytour_offer_identity');$new=anytour_offer_migrate_v2_index($pdo,'uq_anytour_offer_refresh_identity');
        if($version!==2||$counts!==['anytour_offer_store_control'=>1,'anytour_offer_refreshes'=>0,'anytour_offer_scope_state'=>0,'anytour_offers'=>0]||$old!==[]||$new!==['unique'=>true,'columns'=>['provider','scope_sha256','last_refresh_token','identity_sha256']])throw new RuntimeException('Offer store v2 readback differs');
        if((int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn()!==$expectedHotels||(int)$pdo->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn()!==$expectedLinks)throw new RuntimeException('Canonical catalogue changed during offer-store migration');
        return['status'=>'offer_store_v2_migrated_verified','schemaVersion'=>2,'counts'=>$counts,'canonicalHotels'=>$expectedHotels,'legacyCatalogLinks'=>$expectedLinks,'oldIndexPresent'=>false,'newIndex'=>$new,'offerRowsMigrated'=>0,'supplierCalls'=>0,'canonicalWrites'=>0,'publicFileWrites'=>0,'noReplay'=>true];
    }finally{$pdo->query("SELECT RELEASE_LOCK('anytour-offer-store-migrate-v2-2693')");}
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $journal=null;$phase='before_ddl';
    try{
        if($argc!==1||basename(dirname(__DIR__,2))!=='payload')throw new RuntimeException('Fixed private invocation required');
        $dir=dirname(__DIR__,3);if(basename($dir)!==ANYTOUR_OFFER_MIGRATE_V2_OPERATION)throw new RuntimeException('Wrong operation directory');
        $reservation=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if(($reservation['operation']??null)!==ANYTOUR_OFFER_MIGRATE_V2_OPERATION||($reservation['dataSource']??null)!==ANYTOUR_OFFER_MIGRATE_V2_SOURCE||($reservation['attempt']??null)!==1)throw new RuntimeException('Wrong reservation');
        $journal=fopen($dir.'/journal.jsonl','x+b');if(!$journal)throw new RuntimeException('Existing journal; no replay');
        $checkpoint=static function(array $state)use(&$journal,&$phase):void{$phase=$state['status'];$line=anytour_offer_migrate_v2_json($state+['noReplay'=>true])."\n";if(fwrite($journal,$line)!==strlen($line)||!fflush($journal)||!fsync($journal))throw new RuntimeException('Durable checkpoint failed');};
        $checkpoint(['status'=>'reserved']);
        $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';if(!is_file($root.'/config.php')||!is_file($root.'/api-v2.php'))throw new RuntimeException('Existing project root required');
        foreach(['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key)if(getenv($key)!==false&&getenv($key)!=='')throw new RuntimeException('Unexpected DB override');
        $configHash=hash_file('sha256',$root.'/config.php');require_once $root.'/config.php';require_once __DIR__.'/../../v2/data/db-v1.php';$config=v2_data_db_config();$expectedName=anytour_offer_migrate_v2_database($config['dsn']);
        $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$expectedName)throw new RuntimeException('Selected database differs from project configuration');
        $result=anytour_offer_migrate_v2($pdo,1000,1000,$checkpoint);if($configHash!==hash_file('sha256',$root.'/config.php'))throw new RuntimeException('Project configuration changed');
        $result+=['operation'=>ANYTOUR_OFFER_MIGRATE_V2_OPERATION,'dataSource'=>ANYTOUR_OFFER_MIGRATE_V2_SOURCE,'executionSource'=>$reservation['executionSource'],'run'=>$reservation['run'],'projectConfigurationUnchanged'=>true];$checkpoint($result);
        $out=fopen($dir.'/result.json','x+b');$bytes=anytour_offer_migrate_v2_json($result)."\n";if(!$out||fwrite($out,$bytes)!==strlen($bytes)||!fflush($out)||!fsync($out))throw new RuntimeException('Final receipt incomplete');fclose($out);fclose($journal);$journal=null;
        echo 'ANYTOUR_OFFER_STORE_V2_MIGRATED result_sha256='.hash('sha256',$bytes)." offers=0 supplier_calls=0\n";
    }catch(Throwable $e){if(is_resource($journal)){$line=json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($e)])."\n";fwrite($journal,$line);fflush($journal);fsync($journal);fclose($journal);}fwrite(STDERR,'ANYTOUR_OFFER_V2_MIGRATE_STOPPED phase='.$phase.' class='.get_class($e)."; inspect journal, NO REPLAY\n");exit(1);}
}
