<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/catalog/anytour_local_identity_alias_seed.php';

function alias_need(bool $ok, string $label): void { if (!$ok) throw new RuntimeException('CHECK_FAILED:' . $label); }

$dsn = (string)getenv('ANYTOUR_ALIAS_TEST_DSN');
$password = (string)getenv('ANYTOUR_ALIAS_TEST_PASSWORD');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anytour_alias_fixture;charset=utf8mb4') {
    throw new RuntimeException('Dedicated disposable fixture DSN required');
}
$db = new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table) $db->exec("DROP TABLE IF EXISTS `$table`");
$db->exec('SET FOREIGN_KEY_CHECKS=1');
$db->exec('CREATE TABLE anytour_catalog_control(singleton_id TINYINT UNSIGNED PRIMARY KEY,schema_version INT UNSIGNED NOT NULL) ENGINE=InnoDB');
$db->exec('INSERT INTO anytour_catalog_control VALUES(1,1)');
$db->exec('CREATE TABLE anytour_hotels(
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,profile_json LONGTEXT NOT NULL,profile_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1,is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
$db->exec('CREATE TABLE anytour_hotel_sources(
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,external_key VARBINARY(128) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,
 acquired_via VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,source_json LONGTEXT NOT NULL,source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,
 UNIQUE KEY uq_anytour_hotel_source(namespace,external_key),KEY idx_anytour_hotel_source_hotel(anytour_hotel_id),
 CONSTRAINT fk_alias_hotel FOREIGN KEY(anytour_hotel_id) REFERENCES anytour_hotels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');

$hotel = static function(PDO $db,string $name): int {
    $json=json_encode(['name'=>$name,'country'=>['name'=>'Египет']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $q=$db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $q->execute([$json,hash('sha256',$json)]); return (int)$db->lastInsertId();
};
$source = static function(PDO $db,string $ns,string $key,int $own,string $via,array $payload): void {
    ksort($payload,SORT_STRING);
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $q=$db->prepare('INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $q->execute([$ns,$key,$own,$via,$json,hash('sha256',$json)]);
};

$h1=$hotel($db,'One'); $h2=$hotel($db,'Two'); $h3=$hotel($db,'Three');
foreach ([[101,$h1],[202,$h2],[303,$h3]] as [$local,$own]) {
    $source($db,'legacy_catalog',(string)$local,$own,'canonical_seed_v1',['legacy_id'=>$local,'canonical_id'=>$own]);
}
$legacy2=$db->query("SELECT source_sha256 FROM anytour_hotel_sources WHERE namespace='legacy_catalog' AND external_key='202'")->fetchColumn();
$source($db,AnyTourLocalIdentityAliasSeed::ALIAS_NAMESPACE,'202',$h2,AnyTourLocalIdentityAliasSeed::ACQUIRED_VIA,[
    'accepted_local_hotel_id'=>202,'canonical_hotel_id'=>$h2,'derived_from_namespace'=>'legacy_catalog',
    'derived_from_source_sha256'=>(string)$legacy2,'schema_version'=>1,
]);

$beforeProfiles=(string)$db->query("SELECT SHA2(GROUP_CONCAT(CONCAT(id,':',profile_sha256) ORDER BY id SEPARATOR '|'),256) FROM anytour_hotels")->fetchColumn();
$beforeLegacy=(string)$db->query("SELECT SHA2(GROUP_CONCAT(CONCAT(id,':',source_sha256) ORDER BY id SEPARATOR '|'),256) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
$seed=new AnyTourLocalIdentityAliasSeed($db);
$plan=$seed->plan('fixture-own-local-alias','996a2ece8aef332c1a31ed6c7e2da790759bcc99');
alias_need($plan['status']==='prepared_read_only','plan status');
alias_need($plan['analysis']===['source_profiles'=>3,'existing_aliases'=>1,'missing'=>2,'unchanged'=>1,'conflicts'=>0],'plan counts');
alias_need($plan['writes']===0 && $plan['mapping_writes']===0 && $plan['profile_writes']===0,'plan ownership');

$applied=$seed->apply('fixture-own-local-alias','996a2ece8aef332c1a31ed6c7e2da790759bcc99',$plan['source_digest']);
alias_need($applied['status']==='committed_verified','apply status');
alias_need($applied['created']===2 && $applied['verified_aliases']===3 && $applied['missing']===0 && $applied['conflicts']===0,'apply counts');
alias_need($applied['mapping_writes']===0 && $applied['profile_writes']===0 && $applied['legacy_writes']===0 && $applied['supplier_calls']===0,'apply boundaries');

$afterProfiles=(string)$db->query("SELECT SHA2(GROUP_CONCAT(CONCAT(id,':',profile_sha256) ORDER BY id SEPARATOR '|'),256) FROM anytour_hotels")->fetchColumn();
$afterLegacy=(string)$db->query("SELECT SHA2(GROUP_CONCAT(CONCAT(id,':',source_sha256) ORDER BY id SEPARATOR '|'),256) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
alias_need(hash_equals($beforeProfiles,$afterProfiles),'profiles untouched');
alias_need(hash_equals($beforeLegacy,$afterLegacy),'legacy provenance untouched');
alias_need((int)$db->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='anytour_local_id'")->fetchColumn()===3,'all aliases exist');

$repeat=$seed->plan('fixture-own-local-alias-repeat','996a2ece8aef332c1a31ed6c7e2da790759bcc99');
alias_need($repeat['analysis']['missing']===0 && $repeat['analysis']['unchanged']===3 && $repeat['analysis']['conflicts']===0,'idempotent plan');

$db->exec("UPDATE anytour_hotel_sources SET source_json='{}',source_sha256='" . hash('sha256','{}') . "' WHERE namespace='anytour_local_id' AND external_key='303'");
$conflict=$seed->plan('fixture-own-local-alias-conflict','996a2ece8aef332c1a31ed6c7e2da790759bcc99');
alias_need($conflict['analysis']['conflicts']===1,'malformed alias surfaces conflict');
$blocked=false;
try { $seed->apply('fixture-own-local-alias-conflict','996a2ece8aef332c1a31ed6c7e2da790759bcc99',$conflict['source_digest']); }
catch (DomainException $e) { $blocked=$e->getMessage()==='ANYTOUR_LOCAL_ALIAS_CONFLICT'; }
alias_need($blocked,'conflict blocks writes');

echo "ANYTOUR_LOCAL_IDENTITY_ALIAS_SEED_OK source=3 created=2 verified=3 profile_writes=0 legacy_writes=0 supplier_calls=0\n";
