<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-profile-enrichment-v1.php';

function enrich_need(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('CHECK_FAILED:' . $label);
}
function enrich_expect(callable $fn, string $needle, string $label): void
{
    try { $fn(); }
    catch (Throwable $e) {
        enrich_need(str_contains($e->getMessage(), $needle), $label . ':wrong:' . $e->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:' . $label . ':no_error');
}

$dsn = (string)getenv('ANYTOUR_PROFILE_ENRICH_TEST_DSN');
$password = (string)getenv('ANYTOUR_PROFILE_ENRICH_TEST_PASSWORD');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anytour_profile_enrich_fixture;charset=utf8mb4') {
    throw new RuntimeException('Dedicated disposable fixture DSN required');
}
$db = new PDO($dsn,'root',$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['tour_price_observations','catalog_hotel_details','catalog_hotels','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table) {
    $db->exec("DROP TABLE IF EXISTS `$table`");
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');
$db->exec('CREATE TABLE anytour_catalog_control(singleton_id TINYINT UNSIGNED PRIMARY KEY,schema_version INT UNSIGNED NOT NULL) ENGINE=InnoDB');
$db->exec('INSERT INTO anytour_catalog_control VALUES(1,1)');
$db->exec('CREATE TABLE anytour_hotels(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_json LONGTEXT NOT NULL,profile_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
$db->exec('CREATE TABLE anytour_hotel_sources(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    external_key VARBINARY(128) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    acquired_via VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_json LONGTEXT NOT NULL,source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,
    UNIQUE KEY uq_source(namespace,external_key),KEY ix_source_hotel(anytour_hotel_id),
    CONSTRAINT fk_source_hotel FOREIGN KEY(anytour_hotel_id) REFERENCES anytour_hotels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
$db->exec('CREATE TABLE catalog_hotels(
    id INT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL,country_id INT UNSIGNED NOT NULL,country_name VARCHAR(255) NOT NULL,
    region_id INT UNSIGNED NULL,region_name VARCHAR(255) NULL,subregion_id INT UNSIGNED NULL,subregion_name VARCHAR(255) NULL,
    category TINYINT UNSIGNED NULL,rating DECIMAL(3,2) NULL,hotel_type INT UNSIGNED NULL,
    latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,primary_image_url VARCHAR(1000) NULL,is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE catalog_hotel_details(
    hotel_id INT UNSIGNED PRIMARY KEY,status VARCHAR(32) NOT NULL,description TEXT NULL,address VARCHAR(500) NULL,place VARCHAR(255) NULL,
    build_info VARCHAR(255) NULL,repair_info VARCHAR(255) NULL,square_info VARCHAR(255) NULL,
    images_json LONGTEXT NULL,infrastructure_json LONGTEXT NULL,meals_json LONGTEXT NULL,services_json LONGTEXT NULL,
    room_types TEXT NULL,fetched_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec("CREATE TABLE tour_price_observations(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,hotel_id INT UNSIGNED NOT NULL,
    source ENUM('user_search','scheduled_monitor','hot_tours') NOT NULL,observed_at DATETIME NOT NULL,
    KEY ix_hotel_seen(hotel_id,observed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$insertHotel = static function(PDO $db, array $profile): int {
    $json = AnyTourProfileEnrichmentV1::json($profile);
    $q = $db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
        VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $q->execute([$json,hash('sha256',$json)]);
    return (int)$db->lastInsertId();
};
$insertAlias = static function(PDO $db, int $local, int $own): void {
    $payload = [
        'accepted_local_hotel_id'=>$local,'canonical_hotel_id'=>$own,
        'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>str_repeat('a',64),'schema_version'=>1,
    ];
    $json = AnyTourProfileEnrichmentV1::json($payload);
    $q = $db->prepare("INSERT INTO anytour_hotel_sources
        (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES('anytour_local_id',?,?,'canonical_local_alias_v1',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $q->execute([(string)$local,$own,$json,hash('sha256',$json)]);
};
$insertCatalog = static function(PDO $db, int $local, string $name, ?string $description, string $image, array $infra, array $services, string $meal, string $room): void {
    $q = $db->prepare('INSERT INTO catalog_hotels
        (id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,rating,hotel_type,latitude,longitude,primary_image_url,is_active)
        VALUES(?, ?,4,\'Египет\',40,\'Шарм-эль-Шейх\',401,\'Наама-Бей\',5,4.70,1,27.9000000,34.3000000,?,1)');
    $q->execute([$local,$name,$image]);
    $details = $db->prepare('INSERT INTO catalog_hotel_details
        (hotel_id,status,description,address,place,build_info,repair_info,square_info,images_json,infrastructure_json,meals_json,services_json,room_types,fetched_at)
        VALUES(?,\'success\',?,\'Адрес\',\'Наама-Бей\',\'2005\',\'2024\',\'10000 м²\',?,?,?,?,?,\'2026-09-17 20:00:00\')');
    $details->execute([
        $local,$description,
        json_encode([$image,str_replace('.jpg','-2.jpg',$image),str_replace('.jpg','-3.jpg',$image)],JSON_UNESCAPED_SLASHES),
        json_encode($infra,JSON_UNESCAPED_UNICODE),json_encode([$meal],JSON_UNESCAPED_UNICODE),
        json_encode($services,JSON_UNESCAPED_UNICODE),$room,
    ]);
};
$observe = static function(PDO $db, int $hotel, string $source, string $when, int $count): void {
    $q = $db->prepare('INSERT INTO tour_price_observations(hotel_id,source,observed_at) VALUES(?,?,?)');
    for ($i=0;$i<$count;$i++) $q->execute([$hotel,$source,$when]);
};

$baseProfile = static fn(string $name): array => [
    'name'=>$name,'country'=>['name'=>'Египет'],'region'=>null,'subRegion'=>null,'category'=>null,'rating'=>null,
    'description'=>null,'primaryImage'=>null,'images'=>[],'address'=>null,'place'=>null,'build'=>null,'repair'=>null,'square'=>null,
    'coordinates'=>null,'traits'=>[],
    'hotelInformation'=>['meals'=>['OWN HOTEL MEAL CONCEPT'],'roomTypes'=>'OWN HOTEL ROOM CONCEPT','services'=>[],'infrastructure'=>[]],
];

$p1=$baseProfile('Demand One'); $h1=$insertHotel($db,$p1); $insertAlias($db,101,$h1);
$insertCatalog($db,101,'Demand One','Saved description one','https://img.example/101.jpg',['Бассейн'],['Wi-Fi'],'SOURCE AI','SOURCE ROOM');
$observe($db,101,'user_search','2026-09-17 19:00:00',5);

$p2=$baseProfile('Demand Two');
$p2['description']='OWN DESCRIPTION MUST STAY';
$p2['hotelInformation']['infrastructure']=['OWN INFRA MUST STAY'];
$h2=$insertHotel($db,$p2); $insertAlias($db,202,$h2);
$insertCatalog($db,202,'Demand Two','CONFLICTING SOURCE DESCRIPTION','https://img.example/202.jpg',['SOURCE INFRA'],['Spa'],'SOURCE AI 2','SOURCE ROOM 2');
$observe($db,202,'user_search','2026-09-17 18:00:00',3);

$p3=$baseProfile('Demand Three'); $h3=$insertHotel($db,$p3); $insertAlias($db,303,$h3);
$insertCatalog($db,303,'Demand Three','Saved description three','https://img.example/303.jpg',['Gym'],['Kids club'],'SOURCE AI 3','SOURCE ROOM 3');
$observe($db,303,'scheduled_monitor','2026-09-17 17:00:00',10);

$p4=$baseProfile('No Detail'); $h4=$insertHotel($db,$p4); $insertAlias($db,404,$h4);
$db->exec("INSERT INTO catalog_hotels
    (id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,rating,hotel_type,latitude,longitude,primary_image_url,is_active)
    VALUES(404,'No Detail',4,'Египет',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1)");
$observe($db,404,'user_search','2026-09-17 20:00:00',20);

$engine = new AnyTourProfileEnrichmentV1($db);
$plan = $engine->plan(2,'2026-09-17T21:00:00Z');
enrich_need($plan['status']==='prepared_read_only' && $plan['writes']===0 && $plan['supplierCalls']===0,'read-only-plan');
enrich_need(count($plan['selected'])===2,'bounded-selection');
// 404 is highest demand but has no fillable saved facts, so the first two fillable demand rows are 101 then 202.
enrich_need($plan['selected'][0]['localHotelId']===101 && $plan['selected'][1]['localHotelId']===202,'demand-priority-fillable');
enrich_need(isset($plan['selected'][0]['patch']['description']) && isset($plan['selected'][0]['patch']['primaryImage']),'first-patch');
enrich_need(!isset($plan['selected'][0]['patch']['hotelInformation.meals']) && !isset($plan['selected'][0]['patch']['hotelInformation.roomTypes']),'stay-concepts-excluded');
enrich_need(!isset($plan['selected'][1]['patch']['description']),'existing-description-not-overwritten');
enrich_need(in_array('description',$plan['selected'][1]['preservedConflicts'],true),'description-conflict-recorded');
enrich_need(in_array('hotelInformation.infrastructure',$plan['selected'][1]['preservedConflicts'],true),'infrastructure-conflict-recorded');
enrich_need(($plan['roomMealFieldsExcluded'] ?? false)===true && ($plan['traitsExcluded'] ?? false)===true,'architecture-boundary');

$legacyBefore=(int)$db->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
$apply = $engine->apply('fixture-profile-fill-missing',2,'2026-09-17T21:00:00Z',$plan['planSha256']);
enrich_need($apply['status']==='committed_verified' && $apply['profilesUpdated']===2,'apply-two');
enrich_need($apply['profileWrites']===2 && $apply['provenanceWrites']===2,'write-counts');
enrich_need($apply['legacyWrites']===0 && $apply['mappingWrites']===0 && $apply['supplierCalls']===0,'ownership');
enrich_need((int)$db->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn()===$legacyBefore,'legacy-untouched');
enrich_need((int)$db->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='profile_enrichment:legacy_saved_v1'")->fetchColumn()===2,'provenance-two');

$readProfile = static function(PDO $db,int $id): array {
    $q=$db->prepare('SELECT profile_json,profile_sha256,revision FROM anytour_hotels WHERE id=?'); $q->execute([$id]); $r=$q->fetch(PDO::FETCH_ASSOC);
    enrich_need(is_array($r) && hash('sha256',$r['profile_json'])===$r['profile_sha256'],'profile-integrity');
    return ['profile'=>json_decode($r['profile_json'],true,512,JSON_THROW_ON_ERROR),'revision'=>(int)$r['revision']];
};
$r1=$readProfile($db,$h1); $r2=$readProfile($db,$h2); $r3=$readProfile($db,$h3);
enrich_need($r1['revision']===2 && $r2['revision']===2 && $r3['revision']===1,'revision-boundary');
enrich_need($r1['profile']['description']==='Saved description one','description-filled');
enrich_need(count($r1['profile']['images'])===3 && $r1['profile']['hotelInformation']['services']===['Wi-Fi'],'gallery-services-filled');
enrich_need($r1['profile']['hotelInformation']['meals']===['OWN HOTEL MEAL CONCEPT'],'own-meal-concept-unchanged');
enrich_need($r1['profile']['hotelInformation']['roomTypes']==='OWN HOTEL ROOM CONCEPT','own-room-concept-unchanged');
enrich_need($r2['profile']['description']==='OWN DESCRIPTION MUST STAY','description-preserved');
enrich_need($r2['profile']['hotelInformation']['infrastructure']===['OWN INFRA MUST STAY'],'infrastructure-preserved');
enrich_need($r2['profile']['hotelInformation']['services']===['Spa'],'missing-service-filled');

$repeat=$engine->plan(2,'2026-09-17T21:00:00Z');
enrich_need($repeat['selected'][0]['localHotelId']===303,'next-demand-fillable-after-applied-batch');

// Current-state drift changes the recomputed plan and must abort before another profile write.
$driftPlan=$engine->plan(1,'2026-09-17T21:00:00Z');
$db->exec("UPDATE anytour_hotels SET revision=revision+1 WHERE id=" . $h3);
enrich_expect(
    fn()=> $engine->apply('fixture-profile-drift',1,'2026-09-17T21:00:00Z',$driftPlan['planSha256']),
    'ANYTOUR_PROFILE_ENRICH_PLAN_DRIFT','plan-drift-blocks'
);

echo "ANYTOUR_PROFILE_ENRICHMENT_OK selected=2 updated=2 demand_first=1 fill_missing_only=1 room_meal_excluded=1 provenance=2\n";
