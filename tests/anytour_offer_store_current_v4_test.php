<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/v2/data/anytour-provider-identity-bridge-v1.php';
require_once dirname(__DIR__).'/scripts/catalog/anytour_offer_store_current_v4.php';

function v4check(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
$dsn=getenv('ANYTOUR_OFFER_CURRENT_V4_TEST_DSN');
if(!is_string($dsn)||$dsn===''){fwrite(STDERR,"ANYTOUR_OFFER_CURRENT_V4_TEST_DSN required\n");exit(2);}
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_OFFER_CURRENT_V4_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
foreach([
"CREATE TABLE anytour_catalog_control(singleton_id TINYINT PRIMARY KEY,schema_version INT NOT NULL) ENGINE=InnoDB",
"CREATE TABLE anytour_hotels(id BIGINT UNSIGNED PRIMARY KEY,is_active TINYINT NOT NULL) ENGINE=InnoDB",
"CREATE TABLE anytour_hotel_sources(namespace VARCHAR(80) NOT NULL,external_key VARBINARY(255) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,acquired_via VARCHAR(80) NULL,source_json LONGTEXT NULL,source_sha256 CHAR(64) NULL,first_seen_at DATETIME NULL,last_seen_at DATETIME NULL,PRIMARY KEY(namespace,external_key)) ENGINE=InnoDB",
"CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(64) NOT NULL,external_hotel_id VARCHAR(120) NOT NULL,local_hotel_id BIGINT UNSIGNED NULL,decision_status VARCHAR(32) NOT NULL,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB",
"CREATE TABLE anytour_offer_store_control(singleton_id TINYINT PRIMARY KEY,schema_version INT NOT NULL) ENGINE=InnoDB",
"CREATE TABLE anytour_offer_refreshes(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider VARCHAR(32) NOT NULL,status VARCHAR(32) NOT NULL,started_at DATETIME NULL,completed_at DATETIME NULL) ENGINE=InnoDB",
"CREATE TABLE anytour_offer_scope_state(provider VARCHAR(32) NOT NULL,scope_sha256 CHAR(64) NOT NULL,active_refresh_token CHAR(64) NULL,latest_complete_refresh_token CHAR(64) NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(provider,scope_sha256)) ENGINE=InnoDB",
"CREATE TABLE anytour_offers(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,anytour_hotel_id BIGINT UNSIGNED NOT NULL,legacy_hotel_id BIGINT UNSIGNED NOT NULL,provider VARCHAR(32) NOT NULL,provider_hotel_ref_digest CHAR(64) NOT NULL,scope_sha256 CHAR(64) NOT NULL,last_refresh_token CHAR(64) NOT NULL,is_active TINYINT NOT NULL,final_price_ready TINYINT NOT NULL,currency VARCHAR(8) NOT NULL,display_price DECIMAL(12,2) NOT NULL,expires_at DATETIME NOT NULL) ENGINE=InnoDB",
] as $sql)$db->exec($sql);
$db->exec("INSERT INTO anytour_catalog_control VALUES(1,1); INSERT INTO anytour_offer_store_control VALUES(1,2)");
$db->exec("INSERT INTO anytour_hotels VALUES(1001,1),(1002,1)");
$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,1001,'fixture',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute(['11']);
$source=['schema_version'=>1,'provider'=>'andromeda','supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'500','accepted_local_hotel_id'=>22];
$json=json_encode($source,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$digest=hash('sha256','andromeda_catalog:500');
$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('provider_ref_digest:andromeda',?,1002,'match_accepted_bridge',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
    ->execute([$digest,$json,hash('sha256',$json)]);
$db->exec("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','500',22,'accepted')");
$scopeA=hash('sha256','scope-anex');$scopeD=hash('sha256','scope-andromeda');$tokenA=hash('sha256','token-anex');$tokenD=hash('sha256','token-andromeda');
$stmt=$db->prepare("INSERT INTO anytour_offer_scope_state VALUES(?,?,NULL,?,UTC_TIMESTAMP())");$stmt->execute(['anex',$scopeA,$tokenA]);$stmt->execute(['andromeda',$scopeD,$tokenD]);
$db->exec("INSERT INTO anytour_offer_refreshes(provider,status,started_at,completed_at) VALUES('anex','completed',UTC_TIMESTAMP(),UTC_TIMESTAMP()),('andromeda','completed',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$ins=$db->prepare("INSERT INTO anytour_offers(anytour_hotel_id,legacy_hotel_id,provider,provider_hotel_ref_digest,scope_sha256,last_refresh_token,is_active,final_price_ready,currency,display_price,expires_at) VALUES(?,?,?,?,?,?,1,1,'RUB',?,?)");
$ins->execute([1001,11,'anex',hash('sha256','anex:hotel'),$scopeA,$tokenA,'100000.00','2099-01-01 00:00:00']);
$ins->execute([1002,22,'andromeda',$digest,$scopeD,$tokenD,'110000.00','2099-01-01 00:00:00']);
$ins->execute([1002,23,'andromeda',hash('sha256','andromeda_catalog:501'),$scopeD,$tokenD,'120000.00','2099-01-01 00:00:00']);
$r=anytour_offer_current_v4_collect($db);
v4check($r['status']==='current_read_only','status');
v4check($r['offerStoreVersion']===2,'store version');
v4check($r['canonical']['activeHotels']===2&&$r['canonical']['legacyCatalogLinks']===1&&$r['canonical']['directAndromedaLinks']===1,'canonical counts');
v4check($r['canonical']['acceptedAndromedaRows']===1,'accepted count');
v4check($r['overall']['rawRows']===3&&$r['overall']['latestReadyRows']===3&&$r['overall']['currentVisibleRows']===2,'overall visibility');
v4check($r['overall']['currentVisibleHotels']===2&&$r['overall']['currentVisibleScopes']===2,'visible dimensions');
v4check($r['providers']['anex']['currentVisibleRows']===1,'legacy provider visibility');
v4check($r['providers']['andromeda']['latestReadyRows']===2&&$r['providers']['andromeda']['currentVisibleRows']===1,'direct provider fail closed');
v4check($r['databaseWrites']===0&&$r['supplierCalls']===0&&$r['publicFileWrites']===0,'read only receipt');
echo "anytour_offer_store_current_v4_test: OK\n";
