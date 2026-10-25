<?php
declare(strict_types=1);
// The MAIN-owned runner is staged beside its exact immutable release SQL fixture.
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source==='') $source=dirname(__DIR__);
if (realpath($source)!==$source) throw new RuntimeException('Exact immutable fixture source root required');
$stage=sys_get_temp_dir().'/anytour-stay-context-install-fixture-'.bin2hex(random_bytes(8));
mkdir($stage.'/scripts/catalog',0700,true);mkdir($stage.'/v2/data/migrations',0700,true);
foreach (['anytour_hotel_stay_schema_read_v1.php','anytour_hotel_stay_context_install_v1.php'] as $name)
    copy(__DIR__.'/../scripts/catalog/'.$name,$stage.'/scripts/catalog/'.$name);
copy($source.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql',$stage.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');
require_once $stage.'/scripts/catalog/anytour_hotel_stay_context_install_v1.php';
$site=$stage.'/anytoour.ru';mkdir($site.'/data',0700,true);
copy($source.'/v2/data/db-v1.php',$site.'/data/db-v1.php');
file_put_contents($site.'/config.php',"<?php // NATIVE CONTEXT CONFIG\n");
$checks=0;$case=0;
function install_check(bool $ok,string $label): void {global $checks;++$checks;if (!$ok) throw new RuntimeException('INSTALL_CHECK_FAILED:'.$label);}
function install_sql(PDO $db,string $path): void {
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path))??'';
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) if (trim($statement)!=='') $db->exec(trim($statement));
}
$server=(string)getenv('ANYTOUR_STAY_CONTEXT_INSTALL_V1_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server)) throw new RuntimeException('Dedicated loopback MySQL fixture DSN required');
$password=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD');
$root=new PDO($server,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
function install_fixture(string $class=PDO::class): PDO {
    global $server,$password,$root,$options,$source,$case;
    $name='anytour_stay_context_install_v1_fixture_'.(++$case);$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
    $db=new $class(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'root',$password,$options);
    install_sql($db,$source.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');return $db;
}
function install_identity(PDO $db): string {
    $row=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
    return hash('sha256',AnyTourHotelStayContextInstallV1::json(['database'=>$row['selected_database'],'serverUuid'=>$row['server_uuid']]));
}
function install_protected(): array {
    global $site;return ['config.php'=>hash_file('sha256',$site.'/config.php'),'data/db-v1.php'=>hash_file('sha256',$site.'/data/db-v1.php')];
}
function install_rebind(array $parent): array {
    $parent['local_stay_install_context']['fileSha256']=hash('sha256',AnyTourHotelStayContextInstallV1::json($parent['local_stay_install_context']['data'])."\n");return $parent;
}
function install_parent(PDO $db): array {
    $data=AnyTourHotelStaySchemaReadV1::read($db);$protected=install_protected();
    install_check($data['receipt']['state']==='completed_read_only'&&$data['plan']['state']==='create_only_after_review','native completed CURRENT context');
    $data['receipt']=['schemaVersion'=>1,'source'=>'anytour-hotel-stay-install-context-read-v1','state'=>'completed_read_only',
        'schemaState'=>'completed_read_only','operationId'=>AnyTourHotelStayContextInstallV1::CONTEXT_OPERATION,
        'sourceSha'=>AnyTourHotelStayContextInstallV1::PARENT_SOURCE,'controlSha'=>AnyTourHotelStayContextInstallV1::CONTEXT_CONTROL,
        'databaseSha256'=>install_identity($db),'connectionAuthority'=>'current_root_config_and_accepted_helper',
        'helperAccepted'=>true,'phpExecuted'=>true,'oldDatabaseIdentityKnown'=>false,'schemaMatchesParent'=>true,'applyAuthorized'=>false,
        'changedFiles'=>[],'observedAt'=>time(),'parentReadOperation'=>AnyTourHotelStayContextInstallV1::PARENT_OPERATION,
        'failedInstallOperation'=>AnyTourHotelStayContextInstallV1::FAILED_OPERATION,'failedInstallReason'=>'local_stay_install_private_drift',
        'migrationSha256'=>AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,
        'inventorySha256'=>hash('sha256',AnyTourHotelStayContextInstallV1::json($data['inventory'])),
        'parentReadInventorySha256'=>hash('sha256',AnyTourHotelStayContextInstallV1::json($data['inventory'])),
        'planSha256'=>hash('sha256',AnyTourHotelStayContextInstallV1::json($data['plan'])),
        'currentProtectedBefore'=>$protected,'currentProtectedAfter'=>$protected,'previousProtected'=>$protected,
        'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0];
    return install_rebind(['schema_version'=>1,'status'=>'complete','mode'=>'local-stay-install-context-read-v1',
        'operation_id'=>AnyTourHotelStayContextInstallV1::CONTEXT_OPERATION,'source_sha'=>AnyTourHotelStayContextInstallV1::PARENT_SOURCE,
        'production_unchanged'=>true,'production_before'=>['fixture'=>hash('sha256','unchanged')],
        'production_after'=>['fixture'=>hash('sha256','unchanged')],
        'booking_calls'=>0,'database_writes'=>0,'lead_calls'=>0,'mapping_writes'=>0,'profile_writes'=>0,'schema_writes'=>0,'supplier_calls'=>0,
        'local_stay_install_context'=>['data'=>$data,'protectedBefore'=>$protected,'protectedAfter'=>$protected,
            'parentReadResultSha256'=>AnyTourHotelStayContextInstallV1::READ_RESULT_SHA256,
            'failedInstallResultSha256'=>AnyTourHotelStayContextInstallV1::FAILED_RESULT_SHA256]]);
}
function install_run(PDO $db,array $parent,string $label): array {
    global $stage,$site;
    $expected=$parent['local_stay_install_context']['data']['receipt']['databaseSha256'];
    $result=AnyTourHotelStayContextInstallV1::install($db,$parent,$stage.'/'.$label.'.jsonl',$site,$expected);
    foreach (['supplierCalls','databaseWrites','automaticAccepts'] as $key) install_check($result['receipt'][$key]===0,$label.':int0 '.$key);
    install_check(!$db->inTransaction(),$label.':transaction closed');
    install_check(strlen(AnyTourHotelStayContextInstallV1::json($result))<AnyTourHotelStayContextInstallV1::MAX_BYTES,$label.':bounded');return $result;
}
function install_targets(PDO $db): array {
    $q=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
        ('anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2') ORDER BY TABLE_NAME");
    return $q->fetchAll(PDO::FETCH_COLUMN);
}
function install_refused(array $r,string $reason): void {
    install_check($r['receipt']['state']==='preflight_refused'&&$r['receipt']['reason']===$reason,'refused '.$reason.' actual '.($r['receipt']['reason']??'null'));
    install_check($r['receipt']['schemaWrites']===0&&$r['receipt']['attemptedTables']===[]&&$r['receipt']['createdTables']===[]
        &&$r['receipt']['unknownTables']===[]&&$r['receipt']['ddlOutcomeUnknown']===false,'no DDL before refusal '.$reason);
}

// Same metadata on another database does not satisfy completed CURRENT context identity.
$db=install_fixture();$parent=install_parent($db);$other=install_fixture();
install_check(AnyTourHotelStaySchemaReadV1::json(AnyTourHotelStaySchemaReadV1::read($other)['inventory'])===
    AnyTourHotelStaySchemaReadV1::json($parent['local_stay_install_context']['data']['inventory']),'other DB has identical metadata');
install_refused(install_run($other,$parent,'wrong-db'),'context_identity');
install_check(install_targets($db)===[]&&install_targets($other)===[],'wrong CURRENT DB refused before any DDL');
$literal=$parent;$literal['local_stay_install_context']['data']['receipt']['databaseSha256']=AnyTourHotelStayContextInstallV1::DATABASE_SHA256;
$literal=install_rebind($literal);
$fixed=AnyTourHotelStayContextInstallV1::install($db,$literal,$stage.'/literal-runtime-db.jsonl',$site);
install_refused($fixed,'context_identity');install_check(install_targets($db)===[],'default expected identity is fixed runtime4cd52');

foreach (['controlSha','databaseWrites','helperAccepted'] as $field) {
    $bad=$parent;$bad['local_stay_install_context']['data']['receipt'][$field]=match($field){'controlSha'=>str_repeat('a',40),'databaseWrites'=>1,default=>false};
    install_refused(install_run($db,install_rebind($bad),'context-'.$field),'context_contract');
}
foreach (['parentReadResultSha256','failedInstallResultSha256'] as $field) {
    $bad=$parent;$bad['local_stay_install_context'][$field]=str_repeat('b',64);
    install_refused(install_run($db,$bad,'lineage-'.$field),'context_contract');
}
$bad=$parent;$bad['local_stay_install_context']['data']['inventory']['server']['version']='8.0.0';
install_refused(install_run($db,$bad,'context-metadata-hash'),'context_contract');
$config=file_get_contents($site.'/config.php');file_put_contents($site.'/config.php',"<?php // changed since CURRENT context\n");
install_refused(install_run($db,$parent,'protected-drift'),'protected_drift');
install_check(install_targets($db)===[],'context/lineage/protected refusals perform zero DDL');file_put_contents($site.'/config.php',$config);

$db=install_fixture();$parent=install_parent($db);
$db->exec("INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
    VALUES ('{\"fixture\":true}',SHA2('{\"fixture\":true}',256),1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$sentinel=$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll();
$result=install_run($db,$parent,'success');$r=$result['receipt'];
install_check($r['state']==='installed_empty'&&$r['reason']===null,'exact3table install');
install_check($r['createdTables']===AnyTourHotelStayContextInstallV1::TARGETS&&$r['attemptedTables']===$r['createdTables']
    &&$r['schemaWrites']===3&&$r['unknownTables']===[]&&!$r['ddlOutcomeUnknown'],'exact3 acknowledged DDL');
install_check($r['tableRows']===array_map(static fn($name)=>['name'=>$name,'rows'=>0],AnyTourHotelStayContextInstallV1::TARGETS),'all3 newly created tables empty');
install_check($r['parentMetadataUnchanged']===true&&$sentinel===$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll(),'parent metadata and application sentinel untouched');
install_check($r['beforeSha256']===hash('sha256',AnyTourHotelStaySchemaReadV1::json($result['before']))
    &&$r['afterSha256']===hash('sha256',AnyTourHotelStaySchemaReadV1::json($result['after'])),'before and after bound');
install_check($r['journalSha256']===hash_file('sha256',$stage.'/success.jsonl')&&(fileperms($stage.'/success.jsonl')&0777)===0600,'private durable journal bound');
$events=array_map(static fn($v)=>json_decode($v,true,512,JSON_THROW_ON_ERROR),file($stage.'/success.jsonl',FILE_IGNORE_NEW_LINES));
install_check(array_column($events,'stage')===['start','before_create','created','before_create','created','before_create','created','final'],'one before/ACK event each CREATE');
install_check($r['contextResultSha256']===hash('sha256',AnyTourHotelStayContextInstallV1::json($parent))
    &&$r['contextDatabaseSha256']===install_identity($db)&&$r['contextInventorySha256']===$r['beforeSha256'],'new receipt binds full CURRENT context and target');
install_check($events[0]['contextResultSha256']===$r['contextResultSha256']&&$events[0]['databaseSha256']===$r['contextDatabaseSha256'],'durable start binds new context before first CREATE');
install_check($r['protectedBefore']===install_protected()&&$r['protectedAfter']===$r['protectedBefore'],'strict CURRENT protected before/after');
$originalJournal=file_get_contents($stage.'/success.jsonl');$repeat=install_run($db,$parent,'success');install_refused($repeat,'journal');
install_check(file_get_contents($stage.'/success.jsonl')===$originalJournal,'completed original journal never overwritten');

$db=install_fixture();$parent=install_parent($db);$db->exec('CREATE TABLE anytour_hotel_room_concepts_v2 (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$preexisting=install_run($db,$parent,'existing');install_refused($preexisting,'schema_preflight');
install_check(count(install_targets($db))===1,'existing table never overwritten or remainingtables blindly created');

$db=install_fixture();$parent=install_parent($db);$db->exec('ALTER TABLE anytour_hotel_sources MODIFY namespace VARCHAR(63) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
install_refused(install_run($db,$parent,'parent-drift'),'schema_preflight');install_check(install_targets($db)===[],'parent drift before any CREATE');

$db=install_fixture();$parent=install_parent($db);$db->exec('CREATE TABLE collision_fixture (id INT PRIMARY KEY,CONSTRAINT CK_ANYTOUR_HOTEL_STAY_MAPPING_V2_TARGET CHECK(id>0)) ENGINE=InnoDB');
install_refused(install_run($db,$parent,'collision'),'constraint_collision');install_check(install_targets($db)===[],'case-insensitive namedCHECK collision before any CREATE');

$db=install_fixture();$parent=install_parent($db);$db->exec('SET SESSION foreign_key_checks=0');
install_refused(install_run($db,$parent,'fk-disabled'),'session_guards');install_check(install_targets($db)===[],'disabledFK not silently enabled or DDLexecuted');
$db->exec('SET SESSION foreign_key_checks=1');

final class InstallCompactCapacityFixturePdo extends PDO {
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false {
        if ($query==='SELECT @@innodb_default_row_format,@@innodb_page_size') return parent::query("SELECT 'compact',16384");
        return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);
    }
}
$db=install_fixture(InstallCompactCapacityFixturePdo::class);$parent=install_parent($db);
install_refused(install_run($db,$parent,'compact-capacity'),'ddl_capability');install_check(install_targets($db)===[],'834byte mapping index unsupportedcapacity stops before first CREATE');

$db=install_fixture();$parent=install_parent($db);$holder=new PDO($server,'root',$password,$options);
install_check((int)$holder->query("SELECT GET_LOCK('anytour-hotel-stay-schema-v2',0)")->fetchColumn()===1,'native competing fixedlock held');
install_refused(install_run($db,$parent,'lock-held'),'lock_unavailable');install_check(install_targets($db)===[],'competing schema writer stops before first CREATE');
$holder->query("SELECT RELEASE_LOCK('anytour-hotel-stay-schema-v2')");

$db=install_fixture();$parent=install_parent($db);$name=(string)$db->query('SELECT DATABASE()')->fetchColumn();
$root->exec("CREATE USER 'stay_context_install_limited'@'%' IDENTIFIED BY 'native-fixture-only'");
$root->exec('GRANT SELECT,CREATE,REFERENCES ON `'.$name.'`.* TO \'stay_context_install_limited\'@\'%\'');
$limited=new PDO(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'stay_context_install_limited','native-fixture-only',$options);
install_refused(install_run($limited,$parent,'missing-trigger-privilege'),'privileges');install_check(install_targets($db)===[],'TRIGGER metadata visibility preflight before all3 absent');

$db=install_fixture();$parent=install_parent($db);$bad=$parent;$bad['local_stay_install_context']['data']['receipt']['controlSha']=str_repeat('f',40);
install_refused(install_run($db,install_rebind($bad),'bad-parent'),'context_contract');install_check(install_targets($db)===[],'foreign parent rejected');
$db->beginTransaction();$nested=AnyTourHotelStayContextInstallV1::install($db,$parent,$stage.'/nested.jsonl',$site,install_identity($db));
install_check($nested['receipt']['state']==='preflight_refused'&&$nested['receipt']['reason']==='caller_transaction'&&$db->inTransaction(),'caller transaction remains owned');$db->rollBack();

final class InstallLostAckFixturePdo extends PDO {
    public function exec(string $statement): int|false {
        $result=parent::exec($statement);
        if (str_starts_with($statement,'CREATE TABLE anytour_hotel_meal_concepts_v2 (')) throw new PDOException('FICTIONAL ACK LOSS AFTER COMMITTED SECOND CREATE');
        return $result;
    }
}
$db=install_fixture(InstallLostAckFixturePdo::class);$parent=install_parent($db);$partial=install_run($db,$parent,'lost-ack');$r=$partial['receipt'];
install_check($r['state']==='ddl_unknown'&&$r['reason']==='ddl_execution_failed'&&$r['ddlOutcomeUnknown']===true,'lostACK explicitly UNKNOWN');
install_check($r['schemaWrites']===1&&$r['createdTables']===[AnyTourHotelStayContextInstallV1::TARGETS[0]]
    &&$r['attemptedTables']===array_slice(AnyTourHotelStayContextInstallV1::TARGETS,0,2)
    &&$r['unknownTables']===[AnyTourHotelStayContextInstallV1::TARGETS[1]],'ACK count not falsely zero or guessed second success');
install_check(count(install_targets($db))===2&&!in_array(AnyTourHotelStayContextInstallV1::TARGETS[2],install_targets($db),true),'actual2committed tables retained without DROP or third CREATE');
$events=array_map(static fn($v)=>json_decode($v,true,512,JSON_THROW_ON_ERROR),file($stage.'/lost-ack.jsonl',FILE_IGNORE_NEW_LINES));
install_check(array_column($events,'stage')===['start','before_create','created','before_create','final']&&$events[3]['table']===AnyTourHotelStayContextInstallV1::TARGETS[1],'second attempt persisted before lostACK');
install_check($partial['after']!==null&&$r['parentMetadataUnchanged']===true,'partial schema observed without transforming UNKNOWN into retry admission');

$lostJournal=file_get_contents($stage.'/lost-ack.jsonl');
install_refused(install_run($db,$parent,'lost-ack'),'journal');
install_check(file_get_contents($stage.'/lost-ack.jsonl')===$lostJournal&&count(install_targets($db))===2,'lostACK journal cannot be replayed or overwritten');

final class ContextInstallProtectedDriftFixturePdo extends PDO {
    public static string $configPath;
    public function exec(string $statement): int|false {
        $result=parent::exec($statement);
        if (str_starts_with($statement,'CREATE TABLE anytour_hotel_stay_mappings_v2 ('))
            file_put_contents(self::$configPath,"<?php // changed immediately after third committed CREATE\n");
        return $result;
    }
}
ContextInstallProtectedDriftFixturePdo::$configPath=$site.'/config.php';
$db=install_fixture(ContextInstallProtectedDriftFixturePdo::class);$parent=install_parent($db);
$changed=install_run($db,$parent,'post-protected-drift');$r=$changed['receipt'];
install_check($r['state']==='verification_failed'&&$r['reason']==='protected_drift'&&$r['schemaWrites']===3
    &&$r['createdTables']===AnyTourHotelStayContextInstallV1::TARGETS&&$r['ddlOutcomeUnknown']===false,'postDDL config drift retains3ACK known partial receipt');
install_check($r['protectedBefore']!==$r['protectedAfter']&&count(install_targets($db))===3,'postDDL protected drift is held without DROP');
file_put_contents($site.'/config.php',$config);

echo 'HOTEL_STAY_CONTEXT_INSTALL_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0'.PHP_EOL;
