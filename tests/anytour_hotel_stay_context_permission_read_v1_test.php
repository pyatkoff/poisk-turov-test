<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_hotel_stay_context_permission_read_v1.php';
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source===''||realpath($source)!==$source) throw new RuntimeException('Immutable fixture SOURCE required');
$server=(string)getenv('ANYTOUR_STAY_CONTEXT_PERMISSION_V1_TEST_DSN');if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server)) throw new RuntimeException('Dedicated loopback MySQL fixture required');
$password=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD');$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
$root=new PDO($server,'root',$password,$options);$checks=0;
function cp_check(bool $ok,string $label): void {global $checks;++$checks;if (!$ok) throw new RuntimeException('CONTEXT_PERMISSION_CHECK_FAILED:'.$label);}
$site=sys_get_temp_dir().'/anytour-context-permission-'.bin2hex(random_bytes(8)).'/anytoour.ru';mkdir($site.'/data',0700,true);copy($source.'/v2/data/db-v1.php',$site.'/data/db-v1.php');file_put_contents($site.'/config.php',"<?php // PRIVATE FIXTURE CONFIG\n");
$name='stay_context_permission_fixture';$root->exec('CREATE DATABASE `'.$name.'`');$root->exec('CREATE TABLE `'.$name.'`.anytour_hotels(id INT PRIMARY KEY) ENGINE=InnoDB');$root->exec('INSERT INTO `'.$name.'`.anytour_hotels VALUES(7)');$escaped=str_replace('_','\\_',$name);$fixturePassword='native-context-proof-only';
$root->exec("CREATE USER 'context_perm_positive'@'%' IDENTIFIED BY '".$fixturePassword."'");$root->exec('GRANT CREATE,REFERENCES,SELECT,TRIGGER ON `'.$escaped."`.* TO 'context_perm_positive'@'%'");
$dsn=str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server);$db=new PDO($dsn,'context_perm_positive',$fixturePassword,$options);
function cp_identity(PDO $db): string {$r=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();return hash('sha256',AnyTourHotelStayContextPermissionReadV1::json(['database'=>$r['selected_database'],'serverUuid'=>$r['server_uuid']]));}
function cp_parents(PDO $db): array {
    global $site;$base=['config.php'=>hash_file('sha256',$site.'/config.php'),'data/db-v1.php'=>hash_file('sha256',$site.'/data/db-v1.php')];$identity=cp_identity($db);
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
function cp_read(PDO $db,array $parents,string $state): array {
    global $site;$r=AnyTourHotelStayContextPermissionReadV1::read($db,$parents[0],$parents[1],$parents[2],$site,$parents[3]);cp_check($r['receipt']['state']===$state,'state '.$state.' actual '.$r['receipt']['state']);
    foreach (['supplierCalls','databaseWrites','schemaWrites','profileWrites','mappingWrites','automaticAccepts'] as $key) cp_check($r['receipt'][$key]===0,'typed0 '.$key);
    foreach (['engineOperationBound','engineCliExecuted','oldAccountIdentityKnown','effectiveDenialClaimed','ddlAdmission'] as $key) cp_check($r['receipt'][$key]===false,'no invented authority '.$key);
    cp_check($r['receipt']['phpExecuted']===true&&$r['receipt']['pdoAttempted']===true,'supplied PDO is known attempted; PHP really executed');cp_check(strlen(AnyTourHotelStayContextPermissionReadV1::json($r))<262144,'bounded envelope');return $r;
}
$parents=cp_parents($db);$r=cp_read($db,$parents,'completed_read_only');$engine=$r['engine'];
cp_check($r['receipt']['allRequiredProven']===true&&$r['receipt']['databaseBindingKnown']&&$r['receipt']['accountBindingKnown']&&$r['receipt']['roleBindingKnown'],'new measurement binds actual fixture DB/account/role');
cp_check($r['receipt']['engineInvoked']===true&&$r['receipt']['helperAttempted']===false,'pure API invoked engine, never helper');
cp_check(!array_key_exists('operationId',$engine['receipt'])&&!array_key_exists('sourceSha',$engine['receipt'])&&!array_key_exists('controlSha',$engine['receipt']),'engine is unbound, no historical executed operation fields');
cp_check($r['receipt']['engineResultSha256']===hash('sha256',AnyTourHotelStayContextPermissionReadV1::json($engine)),'verbatim engine component hash');
cp_check($root->query('SELECT id FROM `'.$name.'`.anytour_hotels')->fetchColumn()===7,'metadata-only sentinel unchanged');
$public=AnyTourHotelStayContextPermissionReadV1::json($r);cp_check(!str_contains($public,'context_perm_positive')&&!str_contains($public,$name)&&!str_contains($public,'PRIVATE FIXTURE')&&!str_contains($public,$site),'no account/database/config/path export');

$config=file_get_contents($site.'/config.php');file_put_contents($site.'/config.php','changed');$held=AnyTourHotelStayContextPermissionReadV1::prepare($parents[0],$parents[1],$parents[2],$site,$parents[3]);
cp_check($held['receipt']['state']==='protected_refused'&&$held['engine']===null&&$held['receipt']['pdoAttempted']===false&&$held['receipt']['helperAttempted']===false&&$held['receipt']['engineInvoked']===false,'fresh changed config refuses before helper/PDO/engine');
cp_check($held['protectedContext']['changedFiles']===['config.php']&&$held['protectedContext']['protectedBefore']['config.php']['sha256']===hash('sha256','changed'),'refusal retains newly derived protected hash');file_put_contents($site.'/config.php',$config);
$helper=file_get_contents($site.'/data/db-v1.php');file_put_contents($site.'/data/db-v1.php','unreviewed helper');$held=AnyTourHotelStayContextPermissionReadV1::prepare($parents[0],$parents[1],$parents[2],$site,$parents[3]);cp_check($held['receipt']['state']==='protected_refused'&&$held['receipt']['helperAttempted']===false,'unknown live helper never executed');file_put_contents($site.'/data/db-v1.php',$helper);
unlink($site.'/config.php');$missing=AnyTourHotelStayContextPermissionReadV1::prepare($parents[0],$parents[1],$parents[2],$site,$parents[3]);cp_check($missing['protectedContext']['stable']===false&&$missing['protectedContext']['protectedBefore']['config.php']['status']==='missing'&&$missing['engine']===null,'unavailable file never claims stable baseline');file_put_contents($site.'/config.php',$config);
foreach ([0,1,2] as $index) {$bad=$parents;if ($index===0) $bad[0]['local_stay_protected_context']['data']['receipt']['controlSha']=str_repeat('0',40);elseif ($index===1) $bad[1]['reason']='another_failure';else $bad[2]['source_sha']=str_repeat('1',40);$badResult=cp_read($db,$bad,'parent_invalid');cp_check($badResult['engine']===null&&!$badResult['receipt']['engineInvoked'],'foreign full lineage rejected before engine');}

$root->exec('CREATE DATABASE contextpermissionother');$other=new PDO(str_replace(';charset=utf8mb4',';dbname=contextpermissionother;charset=utf8mb4',$server),'root',$password,$options);$wrong=cp_read($other,$parents,'engine_held');cp_check(!$wrong['receipt']['databaseBindingKnown']&&$wrong['engine']['receipt']['state']==='database_identity'&&$wrong['engine']['coverage']['metadataRowsBefore']===null,'wrong target refuses before grant queries');
$root->exec("CREATE USER 'context_perm_table'@'%' IDENTIFIED BY '".$fixturePassword."'");$root->exec('GRANT SELECT ON `'.$name."`.anytour_hotels TO 'context_perm_table'@'%'");$table=new PDO($dsn,'context_perm_table',$fixturePassword,$options);
$tableResult=cp_read($table,$parents,'completed_read_only');cp_check(!$tableResult['receipt']['allRequiredProven']&&$tableResult['receipt']['accountBindingKnown']&&$tableResult['engine']['privileges']['SELECT']['status']==='not_proven','stable measured table-only rights never become database-wide proof');

final class ContextPermissionUnavailablePdo extends PDO {
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {if ($query===AnyTourHotelStayPermissionProofReadV1::BINDING_SQL) throw new PDOException('PRIVATE BINDING ERROR');return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);}
}
$unavailable=new ContextPermissionUnavailablePdo($dsn,'context_perm_positive',$fixturePassword,$options);$noBinding=cp_read($unavailable,$parents,'engine_held');
cp_check($noBinding['engine']!==null&&!$noBinding['receipt']['databaseBindingKnown']&&!$noBinding['receipt']['accountBindingKnown']&&$noBinding['receipt']['engineInvoked'],'unbound failure retained without inventing identity or false attempt flags');cp_check(!str_contains(AnyTourHotelStayContextPermissionReadV1::json($noBinding),'PRIVATE BINDING ERROR'),'raw exception sanitized');
$fake=$engine;$fake['receipt']['operationId']=AnyTourHotelStayContextPermissionReadV1::FAILED_OPERATION;$invalid=AnyTourHotelStayContextPermissionReadV1::finish($r,$fake,$parents[2],$site,$parents[3]);cp_check($invalid['receipt']['state']==='engine_invalid'&&$invalid['engine']===null&&!$invalid['receipt']['allRequiredProven'],'fake historical execution fields rejected');
$fake=$engine;$fake['coverage']['partialRevokes']=true;$fake['receipt']['coverageSha256']=hash('sha256',AnyTourHotelStayContextPermissionReadV1::json(['coverage'=>$fake['coverage'],'privileges'=>$fake['privileges']]));$invalid=AnyTourHotelStayContextPermissionReadV1::finish($r,$fake,$parents[2],$site,$parents[3]);cp_check($invalid['receipt']['state']==='engine_invalid'&&$invalid['engine']===null,'malformed complete proof never wrapped as accepted');
file_put_contents($site.'/config.php','post-read change');$post=AnyTourHotelStayContextPermissionReadV1::after($r,$site);cp_check($post['receipt']['state']==='protected_drift'&&$post['engine']===$engine&&$post['receipt']['engineInvoked']===true&&$post['receipt']['pdoAttempted']===true&&!$post['receipt']['allRequiredProven'],'post measurement drift retains engine and truthful attempts');file_put_contents($site.'/config.php',$config);
cp_check(hash_file('sha256',__DIR__.'/../scripts/catalog/anytour_hotel_stay_permission_proof_read_v1.php')===AnyTourHotelStayContextPermissionReadV1::ENGINE_SHA256,'accepted engine source remains unchanged');
echo 'HOTEL_STAY_CONTEXT_PERMISSION_READ_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0 schema_writes=0'.PHP_EOL;
