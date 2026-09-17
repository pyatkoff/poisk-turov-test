<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/catalog/anytour_andromeda_direct_bridge_materialize_v2.php';

function op_need(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('CHECK_FAILED:'.$label);}
function op_sql(PDO $db,string $path):void{$sql=(string)file_get_contents($path);$sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $s){$s=trim($s);if($s!=='')$db->exec($s);}}

$dsn=(string)getenv('ANYTOUR_ANDROMEDA_BRIDGE_OP_TEST_DSN');
if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_ANDROMEDA_BRIDGE_OP_TEST_PASSWORD'),[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>false,
]);
foreach(['andromeda_hotel_identities','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $t)$db->exec("DROP TABLE IF EXISTS `$t`");
op_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
$db->exec("CREATE TABLE andromeda_hotel_identities (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 supplier_namespace VARCHAR(64) NOT NULL,
 external_hotel_id VARCHAR(120) NOT NULL,
 local_hotel_id BIGINT UNSIGNED NULL,
 decision_status VARCHAR(32) NOT NULL,
 KEY ix_external(external_hotel_id),KEY ix_tuple(supplier_namespace,external_hotel_id,decision_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$at='2026-09-17 15:00:00';
$hotel=$db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,?,?,?)');
$legacy=$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,'fixture',?,?,?,?)");
$owns=[];
foreach([[101,'Alpha',1],[202,'Inactive',0],[303,'Existing',1],[404,'Next',1]] as[$local,$name,$active]){
 $profile=json_encode(['name'=>$name],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hotel->execute([$profile,hash('sha256',$profile),$active,$at,$at]);$own=(int)$db->lastInsertId();$owns[$local]=$own;
 $source=json_encode(['local'=>$local],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$legacy->execute([(string)$local,$own,$source,hash('sha256',$source),$at,$at]);
}
$identity=$db->prepare('INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status) VALUES(?,?,?,?)');
$identity->execute(['andromeda_catalog','7001',101,'accepted']);
$identity->execute(['andromeda_catalog','7002',202,'accepted']);
$identity->execute(['andromeda_catalog','7003',101,'pending']);
$identity->execute(['andromeda_catalog','7004',101,'accepted']);
$identity->execute(['andromeda_catalog','7004',101,'accepted']);
$identity->execute(['operator_315','8001',303,'accepted']);
$identity->execute(['operator_315','8002',404,'accepted']);

// Simulate a previous terminal batch: these current accepted identities are already directly bound and must never be selected again.
$prior=AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($db,[
 ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7001'],
 ['supplier_namespace'=>'operator_315','external_hotel_id'=>'8001'],
],new DateTimeImmutable('2026-09-17T15:00:00Z'));
op_need($prior['created']===2&&$prior['verified']===2,'fixture-prior-batch');
$before=anytour_andromeda_direct_count($db);op_need($before===2,'before-two-direct');
$planned=anytour_andromeda_direct_candidates($db,1000);
op_need(count($planned)===1,'only-next-missing-eligible');
op_need($planned[0]['supplier_namespace']==='operator_315'&&$planned[0]['external_hotel_id']==='8002','next-missing-exact-tuple');
op_need($planned[0]['local_hotel_id']===404&&$planned[0]['anytour_hotel_id']===$owns[404],'next-missing-exact-target');

$checkpoints=[];
$result=anytour_andromeda_direct_materialize($db,1000,new DateTimeImmutable('2026-09-17T15:01:00Z'),static function(array $state)use(&$checkpoints):void{$checkpoints[]=$state;});
op_need($result['status']==='andromeda_direct_bridge_materialized_verified','status');
op_need($result['selected']===1&&$result['created']===1&&$result['verified']===1,'one-created');
op_need($result['directBefore']===2&&$result['directAfter']===3,'exact-count-delta');
op_need($result['mappingWrites']===0&&$result['supplierCalls']===0&&$result['publicFileWrites']===0,'scope-zeroes');
op_need(count($checkpoints)===3&&$checkpoints[0]['status']==='direct_bridge_planned'&&$checkpoints[2]['status']==='direct_bridge_committed','durable-phases');

$digest=AnyTourProviderIdentityBridgeV1::providerRefDigest('operator_315:8002');
$row=$db->prepare("SELECT anytour_hotel_id,acquired_via FROM anytour_hotel_sources WHERE namespace='provider_ref_digest:andromeda' AND external_key=?");$row->execute([$digest]);$saved=$row->fetch(PDO::FETCH_ASSOC);
op_need(is_array($saved)&&(int)$saved['anytour_hotel_id']===$owns[404]&&$saved['acquired_via']==='match_accepted_bridge','direct-row-target');

// The pure operation is empty after v2; the live runner itself is NO-REPLAY by operation directory/journal.
$second=anytour_andromeda_direct_materialize($db,1000,new DateTimeImmutable('2026-09-17T15:02:00Z'),static function(array $state):void{});
op_need($second['status']==='andromeda_direct_bridge_nothing_to_materialize_verified'&&$second['selected']===0,'idempotent-empty-plan');

// Current MATCH revocation makes the new direct row unusable; no fallback through legacy is allowed for a present direct digest.
$db->exec("UPDATE andromeda_hotel_identities SET decision_status='pending' WHERE external_hotel_id='8002'");
$visible=AnyTourProviderIdentityBridgeV1::filterOfferRows($db,[['provider'=>'andromeda','provider_hotel_ref_digest'=>$digest,'legacy_hotel_id'=>404,'anytour_hotel_id'=>$owns[404]]]);
op_need($visible===[],'revocation-fail-closed');

echo "ANYTOUR_ANDROMEDA_DIRECT_BRIDGE_OPERATION_V2_OK prior_skipped=2 selected=1 created=1 inactive_hidden=1 ambiguous_hidden=1 revoked_hidden=1\n";
