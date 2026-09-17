<?php
/** One-shot additive install of AnyTour compatible-scope metadata. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_SCOPE_INDEX_OPERATION = 'anytour-offer-scope-index-install-2690-20260917-v1';
const ANYTOUR_SCOPE_INDEX_SOURCE = '91f62404f0b7ebab79fc54d4f42d852deb916596';
const ANYTOUR_SCOPE_INDEX_EXISTING_TABLES = ['anytour_offer_store_control','anytour_offer_refreshes','anytour_offer_scope_state','anytour_offers'];

function anytour_scope_index_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function anytour_scope_index_database(string $dsn): string
{
    if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Configured MySQL target required');
    $names=[];foreach(explode(';',substr($dsn,6)) as $part)if(str_starts_with($part,'dbname='))$names[]=substr($part,7);
    if(count($names)!==1||!preg_match('/^[A-Za-z0-9_.-]+$/D',$names[0]))throw new RuntimeException('One explicit configured database required');
    return $names[0];
}
function anytour_scope_index_statement(): string
{
    $path=__DIR__.'/../../v2/data/migrations/20260917-anytour-offer-scope-index.sql';
    $sql=file_get_contents($path);if($sql===false)throw new RuntimeException('Pinned scope-index migration missing');
    $sql=preg_replace('/^\s*--.*$/m','',$sql);$statements=array_values(array_filter(array_map('trim',explode(';',(string)$sql))));
    if(count($statements)!==1||!preg_match('/^CREATE TABLE IF NOT EXISTS anytour_offer_scopes\b/',$statements[0]))throw new RuntimeException('Unexpected scope-index migration');
    return $statements[0];
}
function anytour_scope_index_table_exists(PDO $pdo): bool
{
    $stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_offer_scopes' AND TABLE_TYPE='BASE TABLE'");
    return (int)$stmt->fetchColumn()===1;
}
function anytour_scope_index_existing_counts(PDO $pdo): array
{
    $out=[];foreach(ANYTOUR_SCOPE_INDEX_EXISTING_TABLES as $table)$out[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();return$out;
}
function anytour_scope_index_catalog_counts(PDO $pdo): array
{
    return [
        'activeHotels'=>(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn(),
        'sourceLinks'=>(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn(),
    ];
}
function anytour_scope_index_schema(PDO $pdo): array
{
    $columns=$pdo->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_offer_scopes' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    $indexes=$pdo->query("SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_offer_scopes' ORDER BY INDEX_NAME,SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
    return ['columns'=>$columns,'indexes'=>$indexes];
}
function anytour_scope_index_schema_valid(array $schema): bool
{
    $names=array_column($schema['columns']??[],'COLUMN_NAME');
    if($names!==['scope_sha256','scope_version','family_sha256','params_json','params_sha256','first_seen_at','last_seen_at'])return false;
    $index=[];foreach($schema['indexes']??[] as $row)$index[$row['INDEX_NAME']][]=$row['COLUMN_NAME'];
    return ($index['PRIMARY']??null)===['scope_sha256']&&($index['ix_anytour_offer_scope_family_seen']??null)===['family_sha256','last_seen_at']&&count($index)===2;
}
function anytour_scope_index_install(PDO $pdo,callable $checkpoint): array
{
    if($pdo->inTransaction()||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION)throw new RuntimeException('Dedicated exception-mode MySQL connection required');
    if((int)$pdo->query("SELECT GET_LOCK('anytour-offer-scope-index-install-2690',0)")->fetchColumn()!==1)throw new RuntimeException('Another scope-index install owns the lock');
    try{
        $version=(int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        if($version!==2)throw new RuntimeException('Offer store schema v2 required');
        if(anytour_scope_index_table_exists($pdo))throw new RuntimeException('Scope index already exists; inspect/no replay');
        $before=anytour_scope_index_existing_counts($pdo);$catalogBefore=anytour_scope_index_catalog_counts($pdo);
        $statement=anytour_scope_index_statement();$checkpoint(['status'=>'verified_before_ddl','offerStoreSchemaVersion'=>$version,'existingCounts'=>$before,'catalogCounts'=>$catalogBefore,'statementSha256'=>hash('sha256',$statement)]);
        $checkpoint(['status'=>'ddl_attempting','statementSha256'=>hash('sha256',$statement)]);$pdo->exec($statement);$checkpoint(['status'=>'ddl_completed','statementSha256'=>hash('sha256',$statement)]);
        if(!anytour_scope_index_table_exists($pdo))throw new RuntimeException('Scope index not created');
        $schema=anytour_scope_index_schema($pdo);if(!anytour_scope_index_schema_valid($schema))throw new RuntimeException('Scope index schema differs');
        $after=anytour_scope_index_existing_counts($pdo);$catalogAfter=anytour_scope_index_catalog_counts($pdo);
        if($before!==$after||$catalogBefore!==$catalogAfter)throw new RuntimeException('Existing AnyTour data changed during scope-index install');
        $rows=(int)$pdo->query('SELECT COUNT(*) FROM anytour_offer_scopes')->fetchColumn();
        return ['status'=>'scope_index_installed_verified','offerStoreSchemaVersion'=>2,'existingCounts'=>$after,'catalogCounts'=>$catalogAfter,'scopeIndexRows'=>$rows,'schema'=>$schema,'supplierCalls'=>0,'offerWrites'=>0,'mappingWrites'=>0,'canonicalWrites'=>0,'publicFileWrites'=>0,'oldScopeBackfill'=>0,'noReplay'=>true];
    }finally{$pdo->query("SELECT RELEASE_LOCK('anytour-offer-scope-index-install-2690')");}
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $journal=null;$phase='before_ddl';
    try{
        if($argc!==1||basename(dirname(__DIR__,2))!=='payload')throw new RuntimeException('Fixed private invocation required');
        $dir=dirname(__DIR__,3);if(basename($dir)!==ANYTOUR_SCOPE_INDEX_OPERATION)throw new RuntimeException('Wrong operation directory');
        $reservation=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if(($reservation['operation']??null)!==ANYTOUR_SCOPE_INDEX_OPERATION||($reservation['dataSource']??null)!==ANYTOUR_SCOPE_INDEX_SOURCE||($reservation['attempt']??null)!==1)throw new RuntimeException('Wrong reservation');
        $journal=fopen($dir.'/journal.jsonl','x+b');if(!$journal)throw new RuntimeException('Existing journal; no replay');
        $checkpoint=static function(array $state)use(&$journal,&$phase):void{$phase=$state['status'];$line=anytour_scope_index_json($state+['noReplay'=>true])."\n";if(fwrite($journal,$line)!==strlen($line)||!fflush($journal)||!fsync($journal))throw new RuntimeException('Durable checkpoint failed');};
        $checkpoint(['status'=>'reserved']);
        $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';if(!is_file($root.'/config.php')||!is_file($root.'/api-v2.php'))throw new RuntimeException('Existing project root required');
        foreach(['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key)if(getenv($key)!==false&&getenv($key)!=='')throw new RuntimeException('Unexpected DB override');
        $configHash=hash_file('sha256',$root.'/config.php');require_once $root.'/config.php';require_once __DIR__.'/../../v2/data/db-v1.php';$config=v2_data_db_config();$expectedName=anytour_scope_index_database($config['dsn']);
        $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$expectedName)throw new RuntimeException('Selected database differs from project configuration');
        $result=anytour_scope_index_install($pdo,$checkpoint);if($configHash!==hash_file('sha256',$root.'/config.php'))throw new RuntimeException('Project configuration changed');
        $result+=['operation'=>ANYTOUR_SCOPE_INDEX_OPERATION,'dataSource'=>ANYTOUR_SCOPE_INDEX_SOURCE,'executionSource'=>$reservation['executionSource'],'run'=>$reservation['run'],'projectConfigurationUnchanged'=>true];$checkpoint($result);
        $out=fopen($dir.'/result.json','x+b');$bytes=anytour_scope_index_json($result)."\n";if(!$out||fwrite($out,$bytes)!==strlen($bytes)||!fflush($out)||!fsync($out))throw new RuntimeException('Final receipt incomplete');fclose($out);fclose($journal);$journal=null;
        echo 'ANYTOUR_SCOPE_INDEX_INSTALLED result_sha256='.hash('sha256',$bytes).' rows='.$result['scopeIndexRows']." supplier_calls=0\n";
    }catch(Throwable $e){if(is_resource($journal)){$line=json_encode(['status'=>'stopped_inspect_no_replay','lastPhase'=>$phase,'class'=>get_class($e)])."\n";fwrite($journal,$line);fflush($journal);fsync($journal);fclose($journal);}fwrite(STDERR,'ANYTOUR_SCOPE_INDEX_INSTALL_STOPPED phase='.$phase.' class='.get_class($e)."; inspect journal, NO REPLAY\n");exit(1);}
}
