<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-provider-identity-bridge-v2.php';

function a(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function j(array $value): string { ksort($value, SORT_STRING); return json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
function aliasRow(PDO $db, int $local, int $own): void {
    $source=['schema_version'=>1,'local_hotel_id'=>$local]; $json=j($source);
    $q=$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES('local_hotel_id',?,?,'canonical_local_alias',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $q->execute([(string)$local,$own,$json,hash('sha256',$json)]);
}
function legacyRow(PDO $db, int $local, int $own): void {
    $json=j(['id'=>$local,'name'=>'saved']);
    $q=$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES('legacy_catalog',?,?,'saved_catalog',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $q->execute([(string)$local,$own,$json,hash('sha256',$json)]);
}
function row(string $provider, string $ref, int $local, int $own): array {
    return ['provider'=>$provider,'provider_hotel_ref_digest'=>AnyTourProviderIdentityBridgeV2::providerRefDigest($ref),'legacy_hotel_id'=>$local,'anytour_hotel_id'=>$own];
}

$dsn=(string)getenv('ANYTOUR_PROVIDER_BRIDGE_TEST_DSN');
if($dsn===''){ echo "ANYTOUR_PROVIDER_IDENTITY_BRIDGE_V2_TEST_SKIPPED_NO_DSN\n"; exit(0); }
$db=new PDO($dsn,(string)getenv('ANYTOUR_PROVIDER_BRIDGE_TEST_USER'),(string)getenv('ANYTOUR_PROVIDER_BRIDGE_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS anytour_hotel_sources; DROP TABLE IF EXISTS andromeda_hotel_identities; DROP TABLE IF EXISTS anytour_hotels; SET FOREIGN_KEY_CHECKS=1');
$db->exec("CREATE TABLE anytour_hotels(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,is_active TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB");
$db->exec("CREATE TABLE anytour_hotel_sources(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,namespace VARCHAR(64) NOT NULL,external_key VARBINARY(128) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,acquired_via VARCHAR(64) NOT NULL,source_json JSON NOT NULL,source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,UNIQUE KEY uq_source(namespace,external_key)) ENGINE=InnoDB");
$db->exec("CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(64) NOT NULL,external_hotel_id VARCHAR(120) NOT NULL,decision_status VARCHAR(32) NOT NULL,local_hotel_id BIGINT UNSIGNED NULL) ENGINE=InnoDB");
$db->exec('INSERT INTO anytour_hotels(id,is_active) VALUES(11,1),(22,1),(33,1)');

// Own alias is sufficient without a Tourvisor/legacy source.
aliasRow($db,101,11);
a(count(AnyTourProviderIdentityBridgeV2::filterOfferRows($db,[row('tourvisor','tv:101',101,11)]))===1,'own alias must admit exact target');
a(count(AnyTourProviderIdentityBridgeV2::filterOfferRows($db,[row('anex','anex:101',101,11)]))===1,'ANEX compatibility must use own alias');

// Migration-only legacy fallback still works when no own alias exists.
legacyRow($db,202,22);
a(count(AnyTourProviderIdentityBridgeV2::filterOfferRows($db,[row('tourvisor','tv:202',202,22)]))===1,'legacy migration fallback missing');

// Once an own alias exists it is authoritative; legacy must not override conflict.
legacyRow($db,303,33); aliasRow($db,303,22);
a(count(AnyTourProviderIdentityBridgeV2::filterOfferRows($db,[row('tourvisor','tv:303',303,33)]))===0,'legacy must not override own alias');
a(count(AnyTourProviderIdentityBridgeV2::filterOfferRows($db,[row('tourvisor','tv:303',303,22)]))===1,'own alias target must win');

// A new accepted Andromeda identity may bootstrap from migration fallback once; the
// materialization transaction must establish local_hotel_id before direct binding.
$db->exec("INSERT INTO andromeda_hotel_identities VALUES('samo','7001','accepted',202)");
$result=AnyTourProviderIdentityBridgeV2::materializeAcceptedAndromeda($db,[['supplier_namespace'=>'samo','external_hotel_id'=>'7001']],new DateTimeImmutable('2026-09-17 20:00:00',new DateTimeZone('UTC')));
a($result['materialized']===1 && $result['verified']===1,'accepted identity was not materialized');
a($result['local_alias_created']===1,'materialization did not create first-party alias');
$q=$db->query("SELECT namespace,CAST(external_key AS CHAR) AS external_key,anytour_hotel_id,acquired_via FROM anytour_hotel_sources WHERE namespace='local_hotel_id' AND external_key='202'");
$alias=$q->fetch(PDO::FETCH_ASSOC); a(is_array($alias)&& (int)$alias['anytour_hotel_id']===22 && $alias['acquired_via']==='canonical_local_alias','alias readback mismatch');

// Prove direct provider admission survives removal of the migration-only legacy link.
$db->exec("DELETE FROM anytour_hotel_sources WHERE namespace='legacy_catalog' AND external_key='202'");
$andromeda=row('andromeda','samo:7001',202,22);
a(count(AnyTourProviderIdentityBridgeV2::filterOfferRows($db,[$andromeda]))===1,'direct provider path still depends on legacy catalog');

// CURRENT acceptance remains mandatory even with the own alias/direct source.
$db->exec("UPDATE andromeda_hotel_identities SET decision_status='pending' WHERE supplier_namespace='samo' AND external_hotel_id='7001'");
a(count(AnyTourProviderIdentityBridgeV2::filterOfferRows($db,[$andromeda]))===0,'pending provider identity leaked through direct binding');

echo "ANYTOUR_PROVIDER_IDENTITY_BRIDGE_V2_TEST_OK\n";
