<?php
declare(strict_types=1);
// The MAIN-owned runner is staged beside its exact immutable release SQL fixture.
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source==='') $source=dirname(__DIR__);
if (realpath($source)!==$source) throw new RuntimeException('Exact immutable fixture source root required');
$stage=sys_get_temp_dir().'/anytour-stay-install-fixture-'.bin2hex(random_bytes(8));
mkdir($stage.'/scripts/catalog',0700,true);mkdir($stage.'/v2/data/migrations',0700,true);
foreach (['anytour_hotel_stay_schema_read_v1.php','anytour_hotel_stay_schema_install_v1.php'] as $name)
    copy(__DIR__.'/../scripts/catalog/'.$name,$stage.'/scripts/catalog/'.$name);
copy($source.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql',$stage.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');
require_once $stage.'/scripts/catalog/anytour_hotel_stay_schema_install_v1.php';
$checks=0;$case=0;
function install_check(bool $ok,string $label): void {global $checks;++$checks;if (!$ok) throw new RuntimeException('INSTALL_CHECK_FAILED:'.$label);}
function install_sql(PDO $db,string $path): void {
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path))??'';
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) if (trim($statement)!=='') $db->exec(trim($statement));
}
$server=(string)getenv('ANYTOUR_STAY_SCHEMA_INSTALL_V1_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server)) throw new RuntimeException('Dedicated loopback MySQL fixture DSN required');
$password=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD');
$root=new PDO($server,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
function install_fixture(string $class=PDO::class): PDO {
    global $server,$password,$root,$options,$source,$case;
    $name='anytour_stay_install_v1_fixture_'.(++$case);$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
    $db=new $class(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'root',$password,$options);
    install_sql($db,$source.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');return $db;
}
function install_parent(PDO $db): array {
    $data=AnyTourHotelStaySchemaReadV1::read($db);
    install_check($data['receipt']['state']==='completed_read_only'&&$data['plan']['state']==='create_only_after_review','native parent read');
    $data['receipt']+=['operationId'=>AnyTourHotelStaySchemaInstallV1::PARENT_OPERATION,
        'sourceSha'=>AnyTourHotelStaySchemaInstallV1::PARENT_SOURCE,'controlSha'=>AnyTourHotelStaySchemaInstallV1::PARENT_CONTROL];
    return ['status'=>'complete','mode'=>'local-stay-schema-read-v1','operation_id'=>AnyTourHotelStaySchemaInstallV1::PARENT_OPERATION,
        'source_sha'=>AnyTourHotelStaySchemaInstallV1::PARENT_SOURCE,'local_stay_schema_read'=>['data'=>$data]];
}
function install_run(PDO $db,array $parent,string $label): array {
    global $stage;$result=AnyTourHotelStaySchemaInstallV1::install($db,$parent,$stage.'/'.$label.'.jsonl');
    foreach (['supplierCalls','databaseWrites','automaticAccepts'] as $key) install_check($result['receipt'][$key]===0,$label.':int0 '.$key);
    install_check(!$db->inTransaction(),$label.':transaction closed');
    install_check(strlen(AnyTourHotelStaySchemaInstallV1::json($result))<AnyTourHotelStaySchemaInstallV1::MAX_BYTES,$label.':bounded');return $result;
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

$db=install_fixture();$parent=install_parent($db);
$db->exec("INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
    VALUES ('{\"fixture\":true}',SHA2('{\"fixture\":true}',256),1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$sentinel=$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll();
$result=install_run($db,$parent,'success');$r=$result['receipt'];
install_check($r['state']==='installed_empty'&&$r['reason']===null,'exact3table install');
install_check($r['createdTables']===AnyTourHotelStaySchemaInstallV1::TARGETS&&$r['attemptedTables']===$r['createdTables']
    &&$r['schemaWrites']===3&&$r['unknownTables']===[]&&!$r['ddlOutcomeUnknown'],'exact3 acknowledged DDL');
install_check($r['tableRows']===array_map(static fn($name)=>['name'=>$name,'rows'=>0],AnyTourHotelStaySchemaInstallV1::TARGETS),'all3 newly created tables empty');
install_check($r['parentMetadataUnchanged']===true&&$sentinel===$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll(),'parent metadata and application sentinel untouched');
install_check($r['beforeSha256']===hash('sha256',AnyTourHotelStaySchemaReadV1::json($result['before']))
    &&$r['afterSha256']===hash('sha256',AnyTourHotelStaySchemaReadV1::json($result['after'])),'before and after bound');
install_check($r['journalSha256']===hash_file('sha256',$stage.'/success.jsonl')&&(fileperms($stage.'/success.jsonl')&0777)===0600,'private durable journal bound');
$events=array_map(static fn($v)=>json_decode($v,true,512,JSON_THROW_ON_ERROR),file($stage.'/success.jsonl',FILE_IGNORE_NEW_LINES));
install_check(array_column($events,'stage')===['start','before_create','created','before_create','created','before_create','created','final'],'one before/ACK event each CREATE');
$originalJournal=file_get_contents($stage.'/success.jsonl');$repeat=install_run($db,$parent,'success');install_refused($repeat,'schema_preflight');
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
$root->exec("CREATE USER 'stay_install_limited'@'%' IDENTIFIED BY 'native-fixture-only'");
$root->exec('GRANT SELECT,CREATE,REFERENCES ON `'.$name.'`.* TO \'stay_install_limited\'@\'%\'');
$limited=new PDO(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'stay_install_limited','native-fixture-only',$options);
install_refused(install_run($limited,$parent,'missing-trigger-privilege'),'privileges');install_check(install_targets($db)===[],'TRIGGER metadata visibility preflight before all3 absent');

$db=install_fixture();$parent=install_parent($db);$bad=$parent;$bad['local_stay_schema_read']['data']['receipt']['controlSha']=str_repeat('f',40);
install_refused(install_run($db,$bad,'bad-parent'),'parent_contract');install_check(install_targets($db)===[],'foreign parent rejected');
$db->beginTransaction();$nested=AnyTourHotelStaySchemaInstallV1::install($db,$parent,$stage.'/nested.jsonl');
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
install_check($r['schemaWrites']===1&&$r['createdTables']===[AnyTourHotelStaySchemaInstallV1::TARGETS[0]]
    &&$r['attemptedTables']===array_slice(AnyTourHotelStaySchemaInstallV1::TARGETS,0,2)
    &&$r['unknownTables']===[AnyTourHotelStaySchemaInstallV1::TARGETS[1]],'ACK count not falsely zero or guessed second success');
install_check(count(install_targets($db))===2&&!in_array(AnyTourHotelStaySchemaInstallV1::TARGETS[2],install_targets($db),true),'actual2committed tables retained without DROP or third CREATE');
$events=array_map(static fn($v)=>json_decode($v,true,512,JSON_THROW_ON_ERROR),file($stage.'/lost-ack.jsonl',FILE_IGNORE_NEW_LINES));
install_check(array_column($events,'stage')===['start','before_create','created','before_create','final']&&$events[3]['table']===AnyTourHotelStaySchemaInstallV1::TARGETS[1],'second attempt persisted before lostACK');
install_check($partial['after']!==null&&$r['parentMetadataUnchanged']===true,'partial schema observed without transforming UNKNOWN into retry admission');

echo 'HOTEL_STAY_SCHEMA_INSTALL_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0'.PHP_EOL;
