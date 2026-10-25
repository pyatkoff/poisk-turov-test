<?php
declare(strict_types=1);
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source==='') $source=dirname(__DIR__);
if (realpath($source)!==$source) throw new RuntimeException('Exact immutable fixture source root required');
$stage=sys_get_temp_dir().'/anytour-stay-context-fixture-'.bin2hex(random_bytes(8));
mkdir($stage.'/scripts/catalog',0700,true);mkdir($stage.'/v2/data/migrations',0700,true);
foreach (['anytour_hotel_stay_schema_read_v1.php','anytour_hotel_stay_install_context_read_v1.php'] as $name)
    copy(__DIR__.'/../scripts/catalog/'.$name,$stage.'/scripts/catalog/'.$name);
copy($source.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql',$stage.'/v2/data/migrations/20260918-anytour-hotel-stay-v2.sql');
require_once $stage.'/scripts/catalog/anytour_hotel_stay_install_context_read_v1.php';
$checks=0;$case=0;
function context_check(bool $ok,string $label): void {global $checks;++$checks;if (!$ok) throw new RuntimeException('CONTEXT_CHECK_FAILED:'.$label);}
function context_sql(PDO $db,string $path): void {
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path))??'';
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) if (trim($statement)!=='') $db->exec(trim($statement));
}
$server=(string)getenv('ANYTOUR_STAY_INSTALL_CONTEXT_V1_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server)) throw new RuntimeException('Dedicated loopback MySQL fixture DSN required');
$password=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
$root=new PDO($server,'root',$password,$options);
function context_fixture(): PDO {
    global $server,$password,$root,$options,$source,$case;
    $name='anytour_stay_context_v1_fixture_'.(++$case);$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
    $db=new PDO(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'root',$password,$options);
    context_sql($db,$source.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');return $db;
}
function context_parent(PDO $db,array $protected): array {
    $data=AnyTourHotelStaySchemaReadV1::read($db);
    context_check($data['receipt']['state']==='completed_read_only'&&$data['plan']['state']==='create_only_after_review','native parent metadata');
    $data['receipt']+=['operationId'=>AnyTourHotelStayInstallContextReadV1::READ_OPERATION,
        'sourceSha'=>AnyTourHotelStayInstallContextReadV1::READ_SOURCE,'controlSha'=>AnyTourHotelStayInstallContextReadV1::READ_CONTROL];
    return ['status'=>'complete','mode'=>'local-stay-schema-read-v1','operation_id'=>AnyTourHotelStayInstallContextReadV1::READ_OPERATION,
        'source_sha'=>AnyTourHotelStayInstallContextReadV1::READ_SOURCE,
        'local_stay_schema_read'=>['data'=>$data,'protectedBefore'=>$protected,'protectedAfter'=>$protected]];
}
function context_failure(): array {
    return ['schema_version'=>1,'mode'=>'local-stay-schema-install-v1','operation_id'=>AnyTourHotelStayInstallContextReadV1::FAILED_OPERATION,
        'source_sha'=>AnyTourHotelStayInstallContextReadV1::READ_SOURCE,'status'=>'unknown_no_replay','reason'=>'local_stay_install_private_drift',
        'schema_write_outcome_unknown'=>false,'booking_calls'=>0,'database_writes'=>0,'lead_calls'=>0,'mapping_writes'=>0,
        'profile_writes'=>0,'schema_writes'=>0,'schema_writes_acknowledged'=>0,'supplier_calls'=>0];
}
function context_result(PDO $db,array $parent,array $failure,array $protected): array {
    $r=AnyTourHotelStayInstallContextReadV1::read($db,$parent,$failure,$protected);
    foreach (['supplierCalls','databaseWrites','schemaWrites','automaticAccepts'] as $key) context_check($r['receipt'][$key]===0,'native int0 '.$key);
    context_check($r['receipt']['applyAuthorized']===false&&$r['receipt']['oldDatabaseIdentityKnown']===false,'no install authority or invented old DBidentity');
    context_check(is_int($r['receipt']['observedAt'])&&abs(time()-$r['receipt']['observedAt'])<=5,'actual epoch observation');
    context_check(strlen(AnyTourHotelStayInstallContextReadV1::json($r))<AnyTourHotelStayInstallContextReadV1::MAX_BYTES,'bounded context');
    return $r;
}

$site=$stage.'/anytoour.ru';mkdir($site.'/data',0700,true);
copy($source.'/v2/data/db-v1.php',$site.'/data/db-v1.php');
file_put_contents($site.'/config.php',"<?php // FICTIONAL OLD CONFIG\n");
$previous=AnyTourHotelStayInstallContextReadV1::fingerprint($site);$db=context_fixture();$parent=context_parent($db,$previous);$failure=context_failure();
$parentBefore=AnyTourHotelStayInstallContextReadV1::json($parent);$failureBefore=AnyTourHotelStayInstallContextReadV1::json($failure);
file_put_contents($site.'/config.php',"<?php // FICTIONAL CURRENT CONFIG\n");
$current=AnyTourHotelStayInstallContextReadV1::fingerprint($site);
$db->exec("INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
    VALUES ('{\"context-fixture\":true}',SHA2('{\"context-fixture\":true}',256),1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$sentinel=$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll();
$result=context_result($db,$parent,$failure,$current);$r=$result['receipt'];
context_check($r['state']==='completed_read_only'&&$r['schemaState']==='completed_read_only','CURRENT metadata completed');
context_check($r['changedFiles']===['config.php']&&$r['currentProtectedBefore']===$current&&$r['previousProtected']===$previous,'actual config bytechange identified only by hash');
context_check($r['helperAccepted']===true&&$r['phpExecuted']===true&&$r['connectionAuthority']==='current_root_config_and_accepted_helper','CURRENT PDO authority bounded to accepted helper');
context_check($result['plan']['state']==='create_only_after_review'&&$result['plan']['createTables']===array_slice(AnyTourHotelStaySchemaReadV1::TABLES,2),'CURRENT2parents compatible and3targets absent');
context_check($r['schemaMatchesParent']===true,'same schema comparison is a structural fact only');
$identity=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
$expected=hash('sha256',AnyTourHotelStayInstallContextReadV1::json(['database'=>$identity['selected_database'],'serverUuid'=>$identity['server_uuid']]));
context_check($r['databaseSha256']===$expected,'CURRENT exactdatabase/serveridentity hash');
context_check($r['inventorySha256']===hash('sha256',AnyTourHotelStayInstallContextReadV1::json($result['inventory']))
    &&$r['planSha256']===hash('sha256',AnyTourHotelStayInstallContextReadV1::json($result['plan'])),'metadata andplan bound');
context_check($sentinel===$db->query('SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels')->fetchAll(),'application sentinel unchanged');
context_check(AnyTourHotelStayInstallContextReadV1::json($parent)===$parentBefore&&AnyTourHotelStayInstallContextReadV1::json($failure)===$failureBefore,'retained lineages notmutated');
$public=AnyTourHotelStayInstallContextReadV1::json($result);
context_check(!str_contains($public,'context-fixture')&&!str_contains($public,'FICTIONAL')&&!str_contains($public,(string)$identity['selected_database'])
    &&!str_contains($public,(string)$identity['server_uuid'])&&!str_contains($public,'mysql:')&&!str_contains($public,$site),'no application values/configbytes/DBnames/UUID/DSN/path exported');

$other=context_fixture();$sameSchema=context_result($other,$parent,$failure,$current);
context_check($sameSchema['receipt']['schemaMatchesParent']===true&&$sameSchema['receipt']['databaseSha256']!==$r['databaseSha256']
    &&$sameSchema['receipt']['oldDatabaseIdentityKnown']===false,'identical otherDB schema never establishes priorDBidentity');

$drift=AnyTourHotelStayInstallContextReadV1::after($result,['config.php'=>hash('sha256','later config'),'data/db-v1.php'=>$current['data/db-v1.php']]);
context_check($drift['receipt']['state']==='protected_drift'&&$drift['receipt']['databaseSha256']===$r['databaseSha256']
    &&$drift['receipt']['applyAuthorized']===false,'afterread private drift preserves newfact with explicit hold');
$unchanged=AnyTourHotelStayInstallContextReadV1::after($result,$current);
context_check($unchanged===$result,'stable afterhash preserves exactresult');

foreach (['source_sha','reason','schema_write_outcome_unknown','schema_writes_acknowledged'] as $field) {
    $bad=$failure;$bad[$field]=match($field){'source_sha'=>str_repeat('a',40),'reason'=>'another_failure','schema_write_outcome_unknown'=>true,default=>1};
    try {AnyTourHotelStayInstallContextReadV1::read($db,$parent,$bad,$current);throw new RuntimeException('EXPECTED_REJECTION');}
    catch (RuntimeException $e) {context_check($e->getMessage()==='CONTEXT_CONTRACT','foreign failedinstall binding '.$field);}
}
$bad=$parent;$bad['local_stay_schema_read']['data']['receipt']['controlSha']=str_repeat('b',40);
try {AnyTourHotelStayInstallContextReadV1::read($db,$bad,$failure,$current);throw new RuntimeException('EXPECTED_REJECTION');}
catch (RuntimeException $e) {context_check($e->getMessage()==='CONTEXT_CONTRACT','foreign oldread control rejected');}
$badHelper=$current;$badHelper['data/db-v1.php']=hash('sha256','unreviewed helper');
try {AnyTourHotelStayInstallContextReadV1::unavailable($parent,$failure,$badHelper);throw new RuntimeException('EXPECTED_REJECTION');}
catch (RuntimeException $e) {context_check($e->getMessage()==='CONTEXT_CONTRACT','unreviewed helper cannot enter PHP PDO producer');}

$db->beginTransaction();$nested=AnyTourHotelStayInstallContextReadV1::read($db,$parent,$failure,$current);
context_check($nested['receipt']['state']==='schema_unavailable'&&$nested['receipt']['databaseSha256']===null
    &&$nested['inventory']===null&&$db->inTransaction(),'caller transaction retained without a new DBidentityclaim');$db->rollBack();
$missing=AnyTourHotelStayInstallContextReadV1::unavailable($parent,$failure,$current);
context_check($missing['receipt']['state']==='connection_unavailable'&&$missing['receipt']['connectionAuthority']==='not_examined'
    &&$missing['receipt']['databaseSha256']===null&&$missing['receipt']['schemaState']===null&&$missing['inventory']===null,'failedconnection retains hashfacts only');

echo 'HOTEL_STAY_INSTALL_CONTEXT_READ_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0 schema_writes=0'.PHP_EOL;
