<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/collect-anytour-canonical-hotel-details-v1.php';

function canonical_content_need(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('CHECK_FAILED:' . $label);
}
function canonical_content_expect(callable $fn, string $needle, string $label): void
{
    try { $fn(); }
    catch (Throwable $e) {
        canonical_content_need(str_contains($e->getMessage(),$needle),$label . ':wrong:' . $e->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:' . $label . ':no_error');
}

$dsn=(string)getenv('ANYTOUR_CANONICAL_CONTENT_TEST_DSN');
$password=(string)getenv('ANYTOUR_CANONICAL_CONTENT_TEST_PASSWORD');
if ($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anytour_canonical_content_fixture;charset=utf8mb4') {
    throw new RuntimeException('Dedicated disposable fixture DSN required');
}
$db=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['hot_tours_current','tour_price_observations','catalog_hotel_details','catalog_hotels','anytour_hotel_sources','anytour_hotels'] as $table) $db->exec("DROP TABLE IF EXISTS `$table`");
$db->exec('SET FOREIGN_KEY_CHECKS=1');
$db->exec('CREATE TABLE anytour_hotels(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,profile_json LONGTEXT NOT NULL,profile_sha256 CHAR(64) NOT NULL,is_active TINYINT UNSIGNED NOT NULL DEFAULT 1) ENGINE=InnoDB');
$db->exec('CREATE TABLE anytour_hotel_sources(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,namespace VARCHAR(64) NOT NULL,external_key VARBINARY(128) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,acquired_via VARCHAR(64) NOT NULL,source_json LONGTEXT NOT NULL,source_sha256 CHAR(64) NOT NULL,UNIQUE KEY uq_source(namespace,external_key)) ENGINE=InnoDB');
$db->exec('CREATE TABLE catalog_hotels(id INT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL) ENGINE=InnoDB');
$db->exec('CREATE TABLE catalog_hotel_details(hotel_id INT UNSIGNED PRIMARY KEY,status VARCHAR(32) NOT NULL,fetched_at DATETIME NULL) ENGINE=InnoDB');
$db->exec("CREATE TABLE tour_price_observations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,hotel_id INT UNSIGNED NOT NULL,source ENUM('user_search','scheduled_monitor','hot_tours') NOT NULL,observed_at DATETIME NOT NULL) ENGINE=InnoDB");
$db->exec('CREATE TABLE hot_tours_current(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,hotel_id INT UNSIGNED NOT NULL,fetched_at DATETIME NOT NULL) ENGINE=InnoDB');

$baseProfile=static fn(string $name): array => [
    'name'=>$name,'country'=>['name'=>'Турция'],'description'=>null,'primaryImage'=>null,'images'=>[],
    'address'=>null,'place'=>null,'build'=>null,'repair'=>null,'square'=>null,
    'hotelInformation'=>['infrastructure'=>[],'services'=>[]],
];
$insertHotel=static function(PDO $db,array $profile): int {
    $json=json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $q=$db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,is_active) VALUES(?,?,1)');
    $q->execute([$json,hash('sha256',$json)]); return (int)$db->lastInsertId();
};
$insertAlias=static function(PDO $db,int $local,int $own): void {
    $payload=['accepted_local_hotel_id'=>$local,'canonical_hotel_id'=>$own,'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('a',64),'schema_version'=>1];
    $json=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $q=$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256) VALUES('anytour_local_id',?,?,'canonical_local_alias_v1',?,?)");
    $q->execute([(string)$local,$own,$json,hash('sha256',$json)]);
};
$catalog=static function(PDO $db,int $id,string $name): void { $q=$db->prepare('INSERT INTO catalog_hotels(id,name) VALUES(?,?)'); $q->execute([$id,$name]); };
$observe=static function(PDO $db,int $id,string $when,int $count): void { $q=$db->prepare("INSERT INTO tour_price_observations(hotel_id,source,observed_at) VALUES(?,'user_search',?)"); for($i=0;$i<$count;$i++)$q->execute([$id,$when]); };

// Demand-bearing identity-only profile: must rank first.
$h1=$insertHotel($db,$baseProfile('Demand Hotel')); $insertAlias($db,101,$h1); $catalog($db,101,'Demand Hotel'); $observe($db,101,'2026-09-17 20:00:00',5);
// Provider-only identity-only profile: must remain eligible after demand rows.
$h2=$insertHotel($db,$baseProfile('Provider Only')); $insertAlias($db,202,$h2); $catalog($db,202,'Provider Only');
// Generic product: never hydrate as a concrete hotel.
$h3=$insertHotel($db,$baseProfile('Fortuna Kusadasi')); $insertAlias($db,303,$h3); $catalog($db,303,'Fortuna Kusadasi');
// Content-ready own profile: no supplier request needed.
$ready=$baseProfile('Ready Hotel'); $ready['description']='Own description'; $ready['primaryImage']='https://img.example/ready.jpg'; $ready['images']=['https://img.example/ready.jpg']; $ready['address']='A'; $ready['place']='P'; $ready['build']='2020'; $ready['repair']='2025'; $ready['square']='1000'; $ready['hotelInformation']=['infrastructure'=>['Pool'],'services'=>['Wi-Fi']];
$h4=$insertHotel($db,$ready); $insertAlias($db,404,$h4); $catalog($db,404,'Ready Hotel'); $observe($db,404,'2026-09-17 21:00:00',20);
// Freshly saved identity-only row: freshness suppresses another HTTP request.
$h5=$insertHotel($db,$baseProfile('Fresh Saved')); $insertAlias($db,505,$h5); $catalog($db,505,'Fresh Saved');
$db->exec("INSERT INTO catalog_hotel_details(hotel_id,status,fetched_at) VALUES(505,'success','2026-09-17 21:00:00')");

$plan=anytour_canonical_content_pending_rows($db,'2025-09-18 00:00:00','2026-09-16 00:00:00',10);
canonical_content_need(count($plan['rows'])===2,'two-eligible');
canonical_content_need((int)$plan['rows'][0]['hotel_id']===101,'demand-first');
canonical_content_need((int)$plan['rows'][1]['hotel_id']===202,'provider-only-fallback');
canonical_content_need($plan['generic_skipped']===1,'generic-skipped');
canonical_content_need($plan['content_ready_skipped']===1,'content-ready-skipped');
canonical_content_need((int)$plan['rows'][1]['user_search_count']===0,'provider-only-zero-demand-preserved');
canonical_content_need(anytour_canonical_content_profile_needs_content($baseProfile('X')),'identity-only-needs-content');
canonical_content_need(!anytour_canonical_content_profile_needs_content($ready),'ready-does-not-need-content');

// Alias integrity is fail-closed; malformed own aliases never become supplier requests.
$db->exec("UPDATE anytour_hotel_sources SET source_sha256='" . str_repeat('0',64) . "' WHERE anytour_hotel_id=" . $h2);
canonical_content_expect(fn()=>anytour_canonical_content_pending_rows($db,'2025-09-18 00:00:00','2026-09-16 00:00:00',10),'ANYTOUR_CANONICAL_CONTENT_ALIAS_INTEGRITY','alias-integrity');

echo "ANYTOUR_CANONICAL_CONTENT_COLLECTOR_OK eligible=2 demand_first=1 provider_only=1 generic_guard=1 fresh_guard=1 alias_integrity=1\n";
