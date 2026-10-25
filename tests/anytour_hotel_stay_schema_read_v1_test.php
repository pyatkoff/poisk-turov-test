<?php
declare(strict_types=1);
// MAIN owns the runner; immutable release supplies its exact migration fixture.
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source==='') $source=dirname(__DIR__);
if (realpath($source)!==$source) throw new RuntimeException('Exact immutable fixture source root required');
$stage=sys_get_temp_dir().'/anytour-stay-schema-fixture-'.bin2hex(random_bytes(8));
mkdir($stage.'/scripts/catalog',0700,true);mkdir($stage.'/v2/data/migrations',0700,true);
copy(__DIR__.'/../scripts/catalog/anytour_hotel_stay_schema_read_v1.php',$stage.'/scripts/catalog/anytour_hotel_stay_schema_read_v1.php');
copy($source.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql',$stage.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');
require_once $stage.'/scripts/catalog/anytour_hotel_stay_schema_read_v1.php';
$checks=0;
function schema_check(bool $condition,string $label): void {
    global $checks;++$checks;if (!$condition) throw new RuntimeException('SCHEMA_CHECK_FAILED:'.$label);
}
function schema_sql(PDO $db,string $path): void {
    $sql=(string)file_get_contents($path);$sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) if (trim($statement)!=='') $db->exec(trim($statement));
}
function schema_result(PDO $db,string $planState): array {
    $result=AnyTourHotelStaySchemaReadV1::read($db);
    schema_check($result['receipt']['state']==='completed_read_only','technical read '.$planState.' actual '.$result['receipt']['state']);
    $issues=[];foreach ($result['inventory']['tables'] as $table) $issues[$table['name']]=$table['issues'];
    if ($result['plan']['state']!==$planState) {
        // Disposable, pinned fictional fixture only: expose the exact engine rendering for parser diagnosis.
        // The production runner continues to emit hashes only.
        $fixtureCheck=$db->query("SELECT c.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS t
            JOIN information_schema.CHECK_CONSTRAINTS c ON c.CONSTRAINT_SCHEMA=t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME=t.CONSTRAINT_NAME
            WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME='anytour_hotel_stay_mappings_v2'
            AND t.CONSTRAINT_NAME='ck_anytour_hotel_stay_mapping_v2_target' AND t.CONSTRAINT_TYPE='CHECK'")->fetchAll(PDO::FETCH_ASSOC);
        fwrite(STDERR,'FICTIONAL_FIXTURE_TARGET_CHECK='.AnyTourHotelStaySchemaReadV1::json($fixtureCheck).PHP_EOL);
    }
    schema_check($result['plan']['state']===$planState,'plan '.$planState.' actual '.$result['plan']['state']
        .' tableIssues='.AnyTourHotelStaySchemaReadV1::json($issues));
    schema_check(!$db->inTransaction(),'readonly transaction closed');
    foreach (['supplierCalls','databaseWrites','schemaWrites','automaticAccepts'] as $key) schema_check($result['receipt'][$key]===0,'int0 '.$key);
    schema_check($result['plan']['applyAuthorized']===false&&$result['plan']['requiresExplicitReview']===true
        &&$result['plan']['noDdlExecuted']===true,'diagnosis grants no DDL admission');
    schema_check($result['receipt']['inventorySha256']===hash('sha256',AnyTourHotelStaySchemaReadV1::json($result['inventory']))
        &&$result['receipt']['planSha256']===hash('sha256',AnyTourHotelStaySchemaReadV1::json($result['plan'])),'exact metadata and plan hashes');
    return $result;
}
function schema_table(array $result,string $name): array {
    $rows=array_values(array_filter($result['inventory']['tables'],static fn($t)=>$t['name']===$name));
    schema_check(count($rows)===1,'exact table '.$name);return $rows[0];
}
// The server stores the byte-length alias as LENGTH in CHECK metadata.
$ast=new ReflectionMethod(AnyTourHotelStaySchemaReadV1::class,'checkAst');
$byteLength=$ast->invoke(null,'OCTET_LENGTH(operator_key)>0');
schema_check($byteLength!==null&&$byteLength===$ast->invoke(null,'length(`operator_key`) > 0'),'MySQL byte-length serializer alias accepted');
schema_check($byteLength!==$ast->invoke(null,'CHAR_LENGTH(operator_key)>0'),'character-length semantics remain distinct');
$originalTarget=<<<'SQL'
(state='accepted' AND ((kind='room' AND room_concept_id IS NOT NULL AND meal_concept_id IS NULL) OR (kind='meal' AND meal_concept_id IS NOT NULL AND room_concept_id IS NULL))) OR (state<>'accepted' AND room_concept_id IS NULL AND meal_concept_id IS NULL)
SQL;
$serializedTarget=<<<'SQL'
(((`state` = _utf8mb4\'accepted\') and (((`kind` = _utf8mb4\'room\') and (`room_concept_id` is not null) and (`meal_concept_id` is null)) or ((`kind` = _utf8mb4\'meal\') and (`meal_concept_id` is not null) and (`room_concept_id` is null)))) or ((`state` <> _utf8mb4\'accepted\') and (`room_concept_id` is null) and (`meal_concept_id` is null)))
SQL;
schema_check(hash('sha256',$serializedTarget)==='277066ae33080f3ae77ce7b595e4fa919bd3c7963f6b9780f906225aed190545','exact retained native CHECK rendering');
$originalAst=$ast->invoke(null,$originalTarget);
schema_check($originalAst!==null,'full original target check parses');
schema_check($originalAst===$ast->invoke(null,$serializedTarget),'actual MySQL escaped-delimiter target metadata has exact contract AST');
foreach (["state = _utf8mb4\'accepted'","state = _utf8mb4'accepted\'","state = _utf8mb4\'acce\\pted\'"] as $malformed) {
    schema_check($ast->invoke(null,$malformed)===null,'mixed or embedded quote escapes remain rejected');
}

$dsn=(string)getenv('ANYTOUR_STAY_SCHEMA_V1_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$dsn)) throw new RuntimeException('Dedicated loopback MySQL fixture DSN required');
$migration=$source.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql';
schema_check(hash_file('sha256',$migration)===AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,'fixture migration pin');
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false]);
$db->exec('CREATE DATABASE anytour_stay_schema_v1_fixture CHARACTER SET utf8mb4');$db->exec('USE anytour_stay_schema_v1_fixture');
$empty=schema_result($db,'blocked_parent_prerequisites');
schema_check(array_column($empty['inventory']['tables'],'name')===AnyTourHotelStaySchemaReadV1::TABLES,'fixed5table inventory');
schema_check(count(array_filter($empty['inventory']['tables'],static fn($t)=>$t['exists']))===0,'physical absence proven');
schema_sql($db,$source.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');
$missing=schema_result($db,'create_only_after_review');
schema_check($missing['plan']['createTables']===array_slice(AnyTourHotelStaySchemaReadV1::TABLES,2),'exact missing3table plan');
$db->exec('CREATE TABLE anytour_hotel_room_concepts_v2 (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=MyISAM');
$wrongEngine=schema_result($db,'blocked_existing_drift');$wrongRoom=schema_table($wrongEngine,'anytour_hotel_room_concepts_v2');
schema_check($wrongRoom['exists']===true&&$wrongRoom['compatibility']==='wrong_engine','existing wrongengine distinct from absent');
schema_check(!in_array('anytour_hotel_room_concepts_v2',$wrongEngine['plan']['createTables'],true),'existing wrongengine never proposed CREATE');
$db->exec('DROP TABLE anytour_hotel_room_concepts_v2');schema_sql($db,$migration);
$ready=schema_result($db,'ready_no_op');
schema_check(count($ready['plan']['existingCompatibleTables'])===3&&$ready['plan']['createTables']===[],'exact actual migration all3 compatible');
schema_check($ready['inventory']['server']['checkEnforcement']==='supported','native MySQL check enforcement reviewed');
$checksFound=0;foreach ($ready['inventory']['tables'] as $t) if ($t['role']==='target') $checksFound+=count($t['checks']);
schema_check($checksFound===8,'all8check constraints checked');
$db->exec("CREATE USER 'stay_schema_fixture_reader'@'%' IDENTIFIED BY 'fixture-reader-only'");
$db->exec("GRANT SELECT ON anytour_stay_schema_v1_fixture.anytour_hotels TO 'stay_schema_fixture_reader'@'%'");
$db->exec("GRANT SELECT ON anytour_stay_schema_v1_fixture.anytour_hotel_sources TO 'stay_schema_fixture_reader'@'%'");
$readerDsn=str_replace(';charset=', ';dbname=anytour_stay_schema_v1_fixture;charset=', $dsn);
$reader=new PDO($readerDsn,'stay_schema_fixture_reader','fixture-reader-only',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false]);
$hidden=AnyTourHotelStaySchemaReadV1::read($reader);
schema_check($hidden['receipt']['state']==='read_failed'&&$hidden['inventory']===null,'privilege-hidden targets not asserted physically absent');
$db->exec("GRANT SELECT ON anytour_stay_schema_v1_fixture.* TO 'stay_schema_fixture_reader'@'%'");
$reader->exec('USE anytour_stay_schema_v1_fixture');$noTrigger=schema_result($reader,'blocked_server_capability');
schema_check($noTrigger['inventory']['server']['triggerVisibilityProven']===false,'SELECT-only account cannot prove absent write triggers');
$db->exec("GRANT TRIGGER ON anytour_stay_schema_v1_fixture.* TO 'stay_schema_fixture_reader'@'%'");
$reader->exec('USE anytour_stay_schema_v1_fixture');$triggerProof=schema_result($reader,'ready_no_op');
schema_check($triggerProof['inventory']['server']['triggerVisibilityProven']===true,'directschema TRIGGER privilege proves visibility without raw grantoutput');
$reader=null;$db->exec("DROP USER 'stay_schema_fixture_reader'@'%'");

$db->exec("INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
    VALUES ('{\"fixture\":true}',SHA2('{\"fixture\":true}',256),1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$inserted=schema_result($db,'ready_no_op');
schema_check($ready['receipt']['inventorySha256']===$inserted['receipt']['inventorySha256'],'ordinary insert AUTO_INCREMENT counter does not fake schema drift');
$sentinel=$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll();
$again=schema_result($db,'ready_no_op');
schema_check($sentinel===$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll(),'application sentinel unchanged by metadata diagnosis');
schema_check($again===$inserted,'deterministic unchanged metadata');
$db->exec('ALTER TABLE anytour_hotel_room_concepts_v2 MODIFY name_ru VARCHAR(128) NOT NULL');
$columns=schema_result($db,'blocked_existing_drift');
schema_check(in_array('columns',schema_table($columns,'anytour_hotel_room_concepts_v2')['issues'],true),'column drift blocked');
$db->exec('ALTER TABLE anytour_hotel_room_concepts_v2 MODIFY name_ru VARCHAR(255) NOT NULL');
$db->exec('ALTER TABLE anytour_hotel_room_concepts_v2 ADD UNIQUE KEY fixture_extra_unique(local_key)');
$index=schema_result($db,'blocked_existing_drift');
schema_check(in_array('indexes',schema_table($index,'anytour_hotel_room_concepts_v2')['issues'],true),'unexpected unique index blocked');
$db->exec('ALTER TABLE anytour_hotel_room_concepts_v2 DROP INDEX fixture_extra_unique');
$db->exec('ALTER TABLE anytour_hotel_room_concepts_v2 ALTER CHECK ck_anytour_hotel_room_concept_v2_json NOT ENFORCED');
$unenforced=schema_result($db,'blocked_existing_drift');
schema_check(in_array('checks',schema_table($unenforced,'anytour_hotel_room_concepts_v2')['issues'],true),'NOT ENFORCED check blocked');
$db->exec('ALTER TABLE anytour_hotel_room_concepts_v2 ALTER CHECK ck_anytour_hotel_room_concept_v2_json ENFORCED');
$db->exec('CREATE TRIGGER fixture_stay_trigger BEFORE INSERT ON anytour_hotel_room_concepts_v2 FOR EACH ROW SET NEW.revision=2');
$trigger=schema_result($db,'blocked_existing_drift');
schema_check(in_array('unexpected_trigger',schema_table($trigger,'anytour_hotel_room_concepts_v2')['issues'],true),'unexpected target write trigger blocked without exporting body');
$db->exec('DROP TRIGGER fixture_stay_trigger');
$db->exec('ALTER TABLE anytour_hotel_stay_mappings_v2 DROP CHECK ck_anytour_hotel_stay_mapping_v2_target');
$db->exec("ALTER TABLE anytour_hotel_stay_mappings_v2 ADD CONSTRAINT ck_anytour_hotel_stay_mapping_v2_target CHECK (
    ((state='accepted' AND (kind='room' AND room_concept_id IS NOT NULL AND meal_concept_id IS NULL))
    OR (kind='meal' AND meal_concept_id IS NOT NULL AND room_concept_id IS NULL))
    OR (state<>'accepted' AND room_concept_id IS NULL AND meal_concept_id IS NULL))");
$precedence=schema_result($db,'blocked_existing_drift');
schema_check(in_array('checks',schema_table($precedence,'anytour_hotel_stay_mappings_v2')['issues'],true),'same flattened tokens changed AND/OR precedence rejected');
$db->exec('DROP TABLE anytour_hotel_stay_mappings_v2');$sql=(string)file_get_contents($migration);
$sql=substr($sql,(int)strpos($sql,'CREATE TABLE anytour_hotel_stay_mappings_v2'));$db->exec($sql);
$db->exec('ALTER TABLE anytour_hotel_stay_mappings_v2 DROP FOREIGN KEY fk_anytour_hotel_stay_mapping_v2_hotel');
$db->exec('ALTER TABLE anytour_hotel_stay_mappings_v2 ADD CONSTRAINT fk_anytour_hotel_stay_mapping_v2_hotel
    FOREIGN KEY(anytour_hotel_id) REFERENCES anytour_hotels(id) ON DELETE CASCADE ON UPDATE RESTRICT');
$fk=schema_result($db,'blocked_existing_drift');
schema_check(in_array('foreign_keys',schema_table($fk,'anytour_hotel_stay_mappings_v2')['issues'],true),'changed FK action blocked');
$db->exec('DROP TABLE anytour_hotel_stay_mappings_v2');
$db->exec('ALTER TABLE anytour_hotel_sources MODIFY namespace VARCHAR(63) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
$parent=schema_result($db,'blocked_parent_prerequisites');
schema_check(in_array('parent_columns',schema_table($parent,'anytour_hotel_sources')['issues'],true),'parent FK source size drift blocked');
$db->exec('CREATE VIEW anytour_hotel_stay_mappings_v2 AS SELECT id FROM anytour_hotels');
$view=schema_result($db,'blocked_parent_prerequisites');$viewTable=schema_table($view,'anytour_hotel_stay_mappings_v2');
schema_check($viewTable['exists']===true&&$viewTable['compatibility']==='wrong_table_type'&&$viewTable['createSha256']===null,'view distinguished without exporting view SQL');
$public=AnyTourHotelStaySchemaReadV1::json($view);
schema_check(!str_contains($public,'CREATE TABLE')&&!str_contains($public,'CREATE VIEW')&&!str_contains($public,'CHECK (')
    &&!str_contains($public,'anytour_stay_schema_v1_fixture')&&!str_contains($public,'fixture-only-password'),'no raw DDL/database/credentials exported');
schema_check(strlen($public)<AnyTourHotelStaySchemaReadV1::MAX_BYTES,'bounded metadata receipt');
$db->beginTransaction();$nested=AnyTourHotelStaySchemaReadV1::read($db);
schema_check($nested['receipt']['state']==='read_failed'&&$nested['inventory']===null&&$db->inTransaction(),'caller transaction not consumed');$db->rollBack();
echo 'HOTEL_STAY_SCHEMA_READ_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0 schema_writes=0'.PHP_EOL;

unlink($stage.'/scripts/catalog/anytour_hotel_stay_schema_read_v1.php');unlink($stage.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');
rmdir($stage.'/scripts/catalog');rmdir($stage.'/scripts');rmdir($stage.'/v2/data/migrations');rmdir($stage.'/v2/data');rmdir($stage.'/v2');rmdir($stage);
