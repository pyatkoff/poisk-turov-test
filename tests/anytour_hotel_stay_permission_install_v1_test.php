<?php
declare(strict_types=1);
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source===''||realpath($source)!==$source) throw new RuntimeException('Immutable fixture SOURCE required');
$stage=sys_get_temp_dir().'/anytour-permission-install-'.bin2hex(random_bytes(8));mkdir($stage.'/scripts/catalog',0700,true);mkdir($stage.'/v2/data/migrations',0700,true);
foreach (['anytour_hotel_stay_permission_install_v1.php','anytour_hotel_stay_schema_read_v1.php','anytour_hotel_stay_context_permission_read_v1.php','anytour_hotel_stay_permission_proof_read_v1.php'] as $file) copy(__DIR__.'/../scripts/catalog/'.$file,$stage.'/scripts/catalog/'.$file);
copy($source.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql',$stage.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');
require_once $stage.'/scripts/catalog/anytour_hotel_stay_permission_install_v1.php';
$server=(string)getenv('ANYTOUR_STAY_PERMISSION_INSTALL_V1_TEST_DSN');if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server)) throw new RuntimeException('Dedicated native MySQL fixture required');
$password=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD');$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
$root=new PDO($server,'root',$password,$options);$checks=0;$case=0;$fixturePassword='native-permission-install-only';
$site=$stage.'/anytoour.ru';mkdir($site.'/data',0700,true);copy($source.'/v2/data/db-v1.php',$site.'/data/db-v1.php');file_put_contents($site.'/config.php',"<?php // PRIVATE NATIVE CONFIG\n");
function pi_check(bool $ok,string $label): void {global $checks;++$checks;if (!$ok) throw new RuntimeException('PERMISSION_INSTALL_CHECK_FAILED:'.$label);}
function pi_sql(PDO $db,string $path): void {$sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path))??'';foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) if (trim($statement)!=='') $db->exec(trim($statement));}
function pi_identity(PDO $db): string {$r=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();return hash('sha256',AnyTourHotelStayContextPermissionReadV1::json(['database'=>$r['selected_database'],'serverUuid'=>$r['server_uuid']]));}
function pi_fixture(string $class=PDO::class,bool $global=false): PDO {
    global $source,$server,$password,$fixturePassword,$options,$root,$case;
    $name='stay_permission_install_'.(++$case);$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$setup=new PDO(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'root',$password,$options);
    pi_sql($setup,$source.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');
    if ($global) return $setup;
    $user='stay_perm_install_'.$case;$root->exec("CREATE USER '".$user."'@'%' IDENTIFIED BY '".$fixturePassword."'");
    $root->exec('GRANT CREATE,REFERENCES,SELECT,TRIGGER ON `'.str_replace('_','\\_',$name)."`.* TO '".$user."'@'%'");
    return new $class(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),$user,$fixturePassword,$options);
}
function pi_dependencies(PDO $db): array {
    global $site;$base=['config.php'=>hash_file('sha256',$site.'/config.php'),'data/db-v1.php'=>hash_file('sha256',$site.'/data/db-v1.php')];$identity=pi_identity($db);
    $rawData=['anchorEvidence'=>null,'sourceFacts'=>null,'currentCohorts'=>null,'draftConcepts'=>null,
        'privilegeProof'=>['CREATE'=>false,'REFERENCES'=>false,'SELECT'=>false,'TRIGGER'=>false,'allRequired'=>false,'scope'=>'direct_global_or_schema','state'=>'direct_metadata_observed'],
        'receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-stay-source-facts-read-v1','state'=>'anchor_missing','operationId'=>AnyTourHotelStayContextPermissionReadV1::RAW_OPERATION,
            'sourceSha'=>AnyTourHotelStayContextPermissionReadV1::SOURCE,'controlSha'=>AnyTourHotelStayPermissionProofReadV1::PARENT_CONTROL,'databaseSha256Before'=>$identity,'databaseSha256After'=>$identity,'protectedBefore'=>$base,'protectedAfter'=>$base,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'automaticAccepts'=>0]];
    $raw=['mode'=>'local-stay-source-facts-read-v1','status'=>'complete','operation_id'=>AnyTourHotelStayContextPermissionReadV1::RAW_OPERATION,'source_sha'=>AnyTourHotelStayContextPermissionReadV1::SOURCE,
        'local_stay_source_facts'=>['data'=>$rawData,'fileSha256'=>hash('sha256',AnyTourHotelStayContextPermissionReadV1::json($rawData)."\n"),'protectedBefore'=>$base,'protectedAfter'=>$base]];
    $failed=['mode'=>'local-stay-permission-proof-read-v1','operation_id'=>AnyTourHotelStayContextPermissionReadV1::FAILED_OPERATION,'source_sha'=>AnyTourHotelStayContextPermissionReadV1::SOURCE,'status'=>'unknown_no_replay','reason'=>'local_stay_permission_private_drift',
        'booking_calls'=>0,'database_writes'=>0,'lead_calls'=>0,'mapping_writes'=>0,'profile_writes'=>0,'schema_writes'=>0,'supplier_calls'=>0];
    $observation=[];foreach ($base as $key=>$sha) $observation[$key]=['matchesBaseline'=>true,'sha256'=>$sha,'status'=>'hashed'];
    $contextData=['protectedBefore'=>$observation,'protectedAfter'=>$observation,'receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-stay-protected-context-read-v1','operationId'=>AnyTourHotelStayContextPermissionReadV1::CONTEXT_OPERATION,
        'sourceSha'=>AnyTourHotelStayContextPermissionReadV1::SOURCE,'controlSha'=>AnyTourHotelStayContextPermissionReadV1::CONTEXT_CONTROL,'state'=>'completed_read_only','changedFiles'=>[],
        'failedPermissionOperation'=>AnyTourHotelStayContextPermissionReadV1::FAILED_OPERATION,'failedPermissionResultSha256'=>hash('sha256',AnyTourHotelStayContextPermissionReadV1::json($failed)),
        'parentOperation'=>AnyTourHotelStayContextPermissionReadV1::RAW_OPERATION,'parentResultSha256'=>hash('sha256',AnyTourHotelStayContextPermissionReadV1::json($raw)),'parentFileSha256'=>$raw['local_stay_source_facts']['fileSha256'],
        'phpExecuted'=>false,'helperExecuted'=>false,'databaseConnected'=>false,'applyAuthorized'=>false,'ddlAdmission'=>false,'priceAuthority'=>false,'databaseState'=>'NOT_MEASURED','accountState'=>'NOT_MEASURED','permissionState'=>'NOT_MEASURED',
        'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'automaticAccepts'=>0,'subprocessCalls'=>0,'baselineProtected'=>$base]];
    $context=['mode'=>'local-stay-protected-context-read-v1','status'=>'complete','operation_id'=>AnyTourHotelStayContextPermissionReadV1::CONTEXT_OPERATION,'source_sha'=>AnyTourHotelStayContextPermissionReadV1::SOURCE,
        'local_stay_protected_context'=>['data'=>$contextData,'fileSha256'=>hash('sha256',AnyTourHotelStayContextPermissionReadV1::json($contextData)."\n"),'failedPermissionResultSha256'=>$contextData['receipt']['failedPermissionResultSha256'],'parentResultSha256'=>$contextData['receipt']['parentResultSha256']]];
    return [$context,$failed,$raw,$identity];
}

function pi_authority(PDO $db): array {
    global $site;$deps=pi_dependencies($db);$data=AnyTourHotelStayContextPermissionReadV1::read($db,$deps[0],$deps[1],$deps[2],$site,$deps[3]);
    pi_check($data['receipt']['allRequiredProven']===true&&$data['receipt']['state']==='completed_read_only','native fresh positive permission fixture');
    // Fictional protocol parent for this fixture, never an altered retained production receipt.
    $data['receipt']+=['operationId'=>AnyTourHotelStayPermissionInstallV1::PARENT_OPERATION,'sourceSha'=>AnyTourHotelStayPermissionInstallV1::SOURCE,'controlSha'=>AnyTourHotelStayPermissionInstallV1::PARENT_CONTROL];
    $parent=['mode'=>'local-stay-context-permission-read-v1','operation_id'=>AnyTourHotelStayPermissionInstallV1::PARENT_OPERATION,'source_sha'=>AnyTourHotelStayPermissionInstallV1::SOURCE,'status'=>'complete',
        'local_stay_context_permission'=>['data'=>$data,'fileSha256'=>hash('sha256',AnyTourHotelStayPermissionInstallV1::json($data)."\n"),'usableProof'=>true]];return [$parent,...$deps];
}
function pi_run(PDO $db,array $authority,string $label): array {
    global $site,$stage;$r=AnyTourHotelStayPermissionInstallV1::install($db,$authority[0],$authority[1],$authority[2],$authority[3],$stage.'/'.$label.'.jsonl',$site,$authority[4]);
    foreach (['supplierCalls','databaseWrites','profileWrites','mappingWrites','automaticAccepts'] as $key) pi_check($r['receipt'][$key]===0,$label.':typed0 '.$key);
    pi_check(!$db->inTransaction(),$label.':transaction closed');pi_check(strlen(AnyTourHotelStayPermissionInstallV1::json($r))<262144,$label.':bounded');return $r;
}
function pi_refused(array $r,string $reason): void {pi_check($r['receipt']['state']==='preflight_refused'&&$r['receipt']['reason']===$reason,'refused '.$reason.' actual '.($r['receipt']['reason']??'null'));pi_check($r['receipt']['schemaWrites']===0&&$r['receipt']['attemptedTables']===[]&&$r['receipt']['createdTables']===[]&&!$r['receipt']['ddlOutcomeUnknown'],'refusal is known zero DDL');}
function pi_targets(PDO $db): array {return $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE BINARY TABLE_SCHEMA=BINARY DATABASE() AND TABLE_NAME IN ('anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2') ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);}

$db=pi_fixture();$a=pi_authority($db);$name=(string)$db->query('SELECT DATABASE()')->fetchColumn();
$root->exec("INSERT INTO `".$name."`.anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES ('{\"sentinel\":true}',SHA2('{\"sentinel\":true}',256),1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$sentinel=$db->query('SELECT profile_json,profile_sha256,revision FROM anytour_hotels')->fetchAll();$success=pi_run($db,$a,'success');$r=$success['receipt'];
pi_check($r['state']==='installed_empty'&&$r['schemaWrites']===3&&$r['createdTables']===AnyTourHotelStayPermissionInstallV1::TARGETS,'actual exactly3CREATE success with escaped schema grant');
pi_check($r['schemaVerification']==='permission_qualified'&&$r['rawSchemaState']==='blocked_server_capability','writer qualification is explicit, raw plan never rewritten');
pi_check($success['rawSchemaAfter']['plan']['state']==='blocked_server_capability'&&$success['rawSchemaAfter']['inventory']===$success['after'],'raw legacy result retained verbatim');
foreach (array_slice($success['after']['tables'],2) as $table) pi_check($table['issues']===['trigger_visibility_not_proven']&&$table['triggerCount']===0,'only legacy visibility issue qualified');
pi_check($r['tableRows']===array_map(static fn($name)=>['name'=>$name,'rows'=>0],AnyTourHotelStayPermissionInstallV1::TARGETS),'strict3empty rows');
pi_check($r['parentMetadataUnchanged']&&$sentinel===$db->query('SELECT profile_json,profile_sha256,revision FROM anytour_hotels')->fetchAll(),'parent metadata and application sentinel unchanged');
pi_check($r['rawSchemaAfterSha256']===hash('sha256',AnyTourHotelStayPermissionInstallV1::json($success['rawSchemaAfter'])),'raw observation hash bound');
$journal=file_get_contents($stage.'/success.jsonl');$events=array_map(static fn($line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),file($stage.'/success.jsonl',FILE_IGNORE_NEW_LINES));
pi_check(array_column($events,'stage')===['start','before_create','created','before_create','created','before_create','created','final']&&(fileperms($stage.'/success.jsonl')&0777)===0600,'exclusive durable 3intent/ACK journal');
pi_check($events[0]['permissionBeforeSha256']===$r['permissionBeforeSha256']&&$r['journalSha256']===hash('sha256',$journal),'journal binds fresh proof and final hash');
pi_refused(pi_run($db,$a,'success'),'journal');pi_check(file_get_contents($stage.'/success.jsonl')===$journal,'no replay or journal replacement');
$extra=$success['rawSchemaAfter'];$extra['inventory']['tables'][2]['issues'][]='columns';pi_check(AnyTourHotelStayPermissionInstallV1::postVerification($extra,$success['before'],$success['permissionPostBefore'],$success['permissionAfter'],$a[4])===null,'extra structural issue cannot qualify');
$incomplete=$success['permissionAfter'];$incomplete['receipt']['allRequiredProven']=false;pi_check(AnyTourHotelStayPermissionInstallV1::postVerification($success['rawSchemaAfter'],$success['before'],$success['permissionPostBefore'],$incomplete,$a[4])===null,'incomplete permission proof cannot qualify');

$db=pi_fixture(PDO::class,true);$a=pi_authority($db);$normal=pi_run($db,$a,'normal-ready');pi_check($normal['receipt']['state']==='installed_empty'&&$normal['receipt']['schemaVerification']==='raw_ready'&&$normal['rawSchemaAfter']['plan']['state']==='ready_no_op','normal legacy-ready path preserved');
$db=pi_fixture();$a=pi_authority($db);$other=pi_fixture();pi_refused(pi_run($other,$a,'wrong-db'),'permission_drift');pi_check(pi_targets($db)===[]&&pi_targets($other)===[],'wrong target has no DDL');
$db=pi_fixture();$a=pi_authority($db);$name=(string)$db->query('SELECT DATABASE()')->fetchColumn();$otherUser='stay_perm_other_account';$root->exec("CREATE USER '".$otherUser."'@'%' IDENTIFIED BY '".$fixturePassword."'");$root->exec('GRANT CREATE,REFERENCES,SELECT,TRIGGER ON `'.str_replace('_','\\_',$name)."`.* TO '".$otherUser."'@'%'");$otherAccount=new PDO(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),$otherUser,$fixturePassword,$options);pi_refused(pi_run($otherAccount,$a,'wrong-account'),'permission_drift');
$db=pi_fixture();$a=pi_authority($db);$current=(string)$db->query('SELECT CURRENT_USER()')->fetchColumn();$user=substr($current,0,strrpos($current,'@'));$root->exec("GRANT SELECT ON `unrelated\\_permission\\_%`.* TO '".$user."'@'%'");pi_refused(pi_run($db,$a,'grant-drift'),'permission_drift');
$db=pi_fixture();$a=pi_authority($db);$name=(string)$db->query('SELECT DATABASE()')->fetchColumn();$current=(string)$db->query('SELECT CURRENT_USER()')->fetchColumn();$user=substr($current,0,strrpos($current,'@'));
$root->exec('REVOKE TRIGGER ON `'.str_replace('_','\\_',$name)."`.* FROM '".$user."'@'%'");$root->exec('GRANT TRIGGER ON `'.$name."`.anytour_hotels TO '".$user."'@'%'");
pi_refused(pi_run($db,$a,'table-only-trigger'),'permission_drift');pi_check(pi_targets($db)===[],'table-only TRIGGER cannot admit first DDL');
$db=pi_fixture();$a=pi_authority($db);$config=file_get_contents($site.'/config.php');file_put_contents($site.'/config.php','changed');pi_refused(pi_run($db,$a,'private-drift'),'protected_drift');file_put_contents($site.'/config.php',$config);
$db=pi_fixture();$a=pi_authority($db);$bad=$a;$bad[0]['local_stay_context_permission']['data']['receipt']['controlSha']=str_repeat('e',40);pi_refused(pi_run($db,$bad,'parent-mismatch'),'permission_parent');
$db=pi_fixture();$a=pi_authority($db);$db->exec('CREATE TABLE anytour_hotel_room_concepts_v2 (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');pi_refused(pi_run($db,$a,'preexisting'),'schema_preflight');pi_check(count(pi_targets($db))===1,'preexisting target never overwritten');
$db=pi_fixture();$a=pi_authority($db);$name=(string)$db->query('SELECT DATABASE()')->fetchColumn();$root->exec('ALTER TABLE `'.$name.'`.anytour_hotel_sources MODIFY namespace VARCHAR(63) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');pi_refused(pi_run($db,$a,'parent-drift'),'schema_preflight');
$db=pi_fixture();$a=pi_authority($db);$db->exec('CREATE TABLE collision_fixture(id INT PRIMARY KEY,CONSTRAINT CK_ANYTOUR_HOTEL_STAY_MAPPING_V2_TARGET CHECK(id>0)) ENGINE=InnoDB');pi_refused(pi_run($db,$a,'collision'),'constraint_collision');
$db=pi_fixture();$a=pi_authority($db);$db->exec('SET SESSION foreign_key_checks=0');pi_refused(pi_run($db,$a,'session'),'session_guards');$db->exec('SET SESSION foreign_key_checks=1');
$db=pi_fixture();$a=pi_authority($db);$holder=new PDO($server,'root',$password,$options);$holder->query("SELECT GET_LOCK('anytour-hotel-stay-schema-v2',0)");pi_refused(pi_run($db,$a,'lock'),'lock_unavailable');$holder->query("SELECT RELEASE_LOCK('anytour-hotel-stay-schema-v2')");

final class PermissionInstallRoleReportPdo extends PDO {
    public bool $changed=false;
    public function query(string $sql,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
        if ($this->changed&&$sql===AnyTourHotelStayPermissionProofReadV1::BINDING_SQL) $sql=str_replace('CURRENT_ROLE() AS roles',"'fixture_unexpanded_role' AS roles",$sql);
        return $fetchMode===null?parent::query($sql):parent::query($sql,$fetchMode,...$args);
    }
}
$db=pi_fixture(PermissionInstallRoleReportPdo::class);$a=pi_authority($db);$db->changed=true;pi_refused(pi_run($db,$a,'role-report-drift'),'permission_drift');
final class PermissionInstallCapacityPdo extends PDO {
    public function query(string $sql,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
        if ($sql==='SELECT @@innodb_default_row_format,@@innodb_page_size') $sql="SELECT 'compact',16384";
        return $fetchMode===null?parent::query($sql):parent::query($sql,$fetchMode,...$args);
    }
}
$db=pi_fixture(PermissionInstallCapacityPdo::class);$a=pi_authority($db);pi_refused(pi_run($db,$a,'capacity-report'),'ddl_capability');
final class PermissionInstallPostProofDriftPdo extends PDO {
    public static PDO $root;public static string $database;public static string $user;
    public function exec(string $sql): int|false {
        $r=parent::exec($sql);
        if (str_starts_with($sql,'CREATE TABLE anytour_hotel_stay_mappings_v2 (')) {
            self::$root->exec('REVOKE TRIGGER ON `'.str_replace('_','\\_',self::$database)."`.* FROM '".self::$user."'@'%'");
            self::$root->exec('GRANT TRIGGER ON `'.self::$database."`.anytour_hotels TO '".self::$user."'@'%'");
        }return $r;
    }
}
PermissionInstallPostProofDriftPdo::$root=$root;$db=pi_fixture(PermissionInstallPostProofDriftPdo::class);PermissionInstallPostProofDriftPdo::$database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
$current=(string)$db->query('SELECT CURRENT_USER()')->fetchColumn();PermissionInstallPostProofDriftPdo::$user=substr($current,0,strrpos($current,'@'));$a=pi_authority($db);$postProof=pi_run($db,$a,'post-proof-drift');
pi_check($postProof['receipt']['state']==='verification_failed'&&$postProof['receipt']['reason']==='permission_drift'&&$postProof['receipt']['schemaWrites']===3&&!$postProof['receipt']['ddlOutcomeUnknown']&&count(pi_targets($db))===3,'post-DDL table-only TRIGGER drift retains3ACK and refuses success');

final class PermissionInstallMissingPostPdo extends PDO {
    public static PDO $root;
    public function exec(string $sql): int|false {
        $r=parent::exec($sql);
        if (str_starts_with($sql,'CREATE TABLE anytour_hotel_stay_mappings_v2 (')) {$name=(string)$this->query('SELECT DATABASE()')->fetchColumn();self::$root->exec('DROP TABLE `'.$name.'`.anytour_hotel_stay_mappings_v2');}
        return $r;
    }
}
PermissionInstallMissingPostPdo::$root=$root;$db=pi_fixture(PermissionInstallMissingPostPdo::class);$a=pi_authority($db);$missing=pi_run($db,$a,'missing-post-target');
pi_check($missing['receipt']['state']==='verification_failed'&&$missing['receipt']['schemaWrites']===3&&!$missing['receipt']['ddlOutcomeUnknown']&&$missing['receipt']['schemaVerification']===null&&count(pi_targets($db))===2,'missing post target never qualifies and retains3ACK');

final class PermissionInstallNonemptyPostPdo extends PDO {
    public static PDO $root;public static string $database;public static int $hotel;
    public function exec(string $sql): int|false {
        $r=parent::exec($sql);
        if (str_starts_with($sql,'CREATE TABLE anytour_hotel_stay_mappings_v2 (')) self::$root->exec('INSERT INTO `'.self::$database.'`.anytour_hotel_room_concepts_v2(anytour_hotel_id,local_key,name_ru,facts_json,created_at,updated_at) VALUES ('.self::$hotel.",X'66697874757265','fixture','{}',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        return $r;
    }
}
PermissionInstallNonemptyPostPdo::$root=$root;$db=pi_fixture(PermissionInstallNonemptyPostPdo::class);PermissionInstallNonemptyPostPdo::$database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
$root->exec("INSERT INTO `".PermissionInstallNonemptyPostPdo::$database."`.anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES ('{\\"nonempty\\":true}',SHA2('{\\"nonempty\\":true}',256),1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
PermissionInstallNonemptyPostPdo::$hotel=(int)$root->query('SELECT id FROM `'.PermissionInstallNonemptyPostPdo::$database.'`.anytour_hotels LIMIT 1')->fetchColumn();$a=pi_authority($db);$nonempty=pi_run($db,$a,'nonempty-post-target');
pi_check($nonempty['receipt']['state']==='verification_failed'&&$nonempty['receipt']['reason']==='rows_nonempty'&&$nonempty['receipt']['schemaWrites']===3&&!$nonempty['receipt']['ddlOutcomeUnknown']&&$nonempty['receipt']['tableRows']===[['name'=>AnyTourHotelStayPermissionInstallV1::TARGETS[0],'rows'=>1]],'nonempty post target never qualifies and retains3ACK');

final class PermissionInstallLostAckPdo extends PDO {public function exec(string $sql): int|false {$r=parent::exec($sql);if (str_starts_with($sql,'CREATE TABLE anytour_hotel_meal_concepts_v2 (')) throw new PDOException('FIXTURE LOST ACK AFTER COMMITTED SECOND CREATE');return $r;}}
$db=pi_fixture(PermissionInstallLostAckPdo::class);$a=pi_authority($db);$partial=pi_run($db,$a,'lost-ack');$r=$partial['receipt'];
pi_check($r['state']==='ddl_unknown'&&$r['schemaWrites']===1&&$r['ddlOutcomeUnknown']&&$r['createdTables']===[AnyTourHotelStayPermissionInstallV1::TARGETS[0]]&&$r['unknownTables']===[AnyTourHotelStayPermissionInstallV1::TARGETS[1]],'lost ACK truth retained');
pi_check(count(pi_targets($db))===2&&!in_array(AnyTourHotelStayPermissionInstallV1::TARGETS[2],pi_targets($db),true),'actual2tables retained, no third/drop/repair');pi_refused(pi_run($db,$a,'lost-ack'),'journal');

final class PermissionInstallTriggerPdo extends PDO {public static PDO $root;public function exec(string $sql): int|false {$r=parent::exec($sql);if (str_starts_with($sql,'CREATE TABLE anytour_hotel_stay_mappings_v2 (')) { $name=(string)$this->query('SELECT DATABASE()')->fetchColumn();self::$root->exec('CREATE TRIGGER `'.$name.'`.fixture_unexpected BEFORE INSERT ON `'.$name.'`.anytour_hotel_room_concepts_v2 FOR EACH ROW SET NEW.revision=NEW.revision');}return $r;}}
PermissionInstallTriggerPdo::$root=$root;$db=pi_fixture(PermissionInstallTriggerPdo::class);$a=pi_authority($db);$trigger=pi_run($db,$a,'unexpected-trigger');pi_check($trigger['receipt']['state']==='verification_failed'&&$trigger['receipt']['schemaWrites']===3&&$trigger['receipt']['schemaVerification']===null,'actual unexpected trigger never qualified, all3ACK retained');
final class PermissionInstallPrivateDriftPdo extends PDO {public static string $config;public function exec(string $sql): int|false {$r=parent::exec($sql);if (str_starts_with($sql,'CREATE TABLE anytour_hotel_stay_mappings_v2 (')) file_put_contents(self::$config,'post CREATE private drift');return $r;}}
PermissionInstallPrivateDriftPdo::$config=$site.'/config.php';$db=pi_fixture(PermissionInstallPrivateDriftPdo::class);$a=pi_authority($db);$post=pi_run($db,$a,'post-private');pi_check($post['receipt']['state']==='verification_failed'&&$post['receipt']['schemaWrites']===3&&!$post['receipt']['ddlOutcomeUnknown']&&$post['receipt']['protectedBefore']!==$post['receipt']['protectedAfter'],'postguard failure never loses3ACK');file_put_contents($site.'/config.php',$config);
pi_check(hash_file('sha256',__DIR__.'/../scripts/catalog/anytour_hotel_stay_schema_read_v1.php')==='d0dc691633975ae7e0c48af13aa9ffaf5c5a5014c766c50826c4dff393808a83','legacy schema reader unchanged');
echo 'HOTEL_STAY_PERMISSION_INSTALL_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0'.PHP_EOL;
