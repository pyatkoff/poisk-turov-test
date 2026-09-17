<?php
declare(strict_types=1);

require_once __DIR__ . '/../scripts/catalog/anytour_identity_only_hotel_content_v1.php';

$dsn=(string)getenv('ANYTOUR_IDENTITY_CONTENT_TEST_DSN');
$password=(string)getenv('ANYTOUR_IDENTITY_CONTENT_TEST_PASSWORD');
if ($dsn==='') throw new RuntimeException('test DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);

foreach (['tour_price_observations','catalog_hotel_details','catalog_hotels','anytour_hotel_sources','anytour_hotels'] as $table) $pdo->exec("DROP TABLE IF EXISTS `$table`");
$pdo->exec("CREATE TABLE anytour_hotels (id BIGINT UNSIGNED PRIMARY KEY, profile_json LONGTEXT NOT NULL, is_active TINYINT UNSIGNED NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE anytour_hotel_sources (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, namespace VARCHAR(64) NOT NULL, external_key VARBINARY(128) NOT NULL, anytour_hotel_id BIGINT UNSIGNED NOT NULL, UNIQUE KEY uq_source(namespace,external_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE catalog_hotels (
    id INT UNSIGNED PRIMARY KEY,name VARCHAR(255) NOT NULL,primary_image_url VARCHAR(2048) DEFAULT NULL,
    image_updated_at DATETIME DEFAULT NULL,synced_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE catalog_hotel_details (
    hotel_id INT UNSIGNED PRIMARY KEY,status VARCHAR(24) NOT NULL DEFAULT 'success',source_hash CHAR(64) DEFAULT NULL,
    country_id INT UNSIGNED DEFAULT NULL,region_id INT UNSIGNED DEFAULT NULL,subregion_id INT UNSIGNED DEFAULT NULL,name VARCHAR(255) DEFAULT NULL,
    category TINYINT UNSIGNED DEFAULT NULL,rating DECIMAL(4,2) DEFAULT NULL,hotel_type INT UNSIGNED DEFAULT NULL,
    description MEDIUMTEXT DEFAULT NULL,address VARCHAR(1000) DEFAULT NULL,place VARCHAR(1000) DEFAULT NULL,phone VARCHAR(255) DEFAULT NULL,
    site VARCHAR(1000) DEFAULT NULL,build_info TEXT DEFAULT NULL,repair_info TEXT DEFAULT NULL,square_info VARCHAR(1000) DEFAULT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,longitude DECIMAL(10,7) DEFAULT NULL,primary_image_url VARCHAR(2048) DEFAULT NULL,
    images_json MEDIUMTEXT DEFAULT NULL,infrastructure_json MEDIUMTEXT DEFAULT NULL,meals_json MEDIUMTEXT DEFAULT NULL,
    services_json MEDIUMTEXT DEFAULT NULL,room_types MEDIUMTEXT DEFAULT NULL,raw_json LONGTEXT DEFAULT NULL,
    fetched_at DATETIME NOT NULL,last_error VARCHAR(1000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE tour_price_observations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,hotel_id INT UNSIGNED NOT NULL,source VARCHAR(32) NOT NULL,observed_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$identity=static fn(string $name): array => [
    'name'=>$name,'country'=>['name'=>'Турция'],'region'=>['name'=>'Анталья'],'description'=>null,'primaryImage'=>null,'images'=>[],
    'hotelInformation'=>['infrastructure'=>[],'services'=>[],'meals'=>[],'roomTypes'=>null],'traits'=>[],
];
$contentful=$identity('Filled Hotel'); $contentful['description']='Уже заполнено';
$profiles=[1=>$identity('Identity Old'),2=>$contentful,3=>$identity('Identity Today'),4=>$identity('Fortuna Antalya'),5=>$identity('Identity Demand'),6=>$identity('Identity Generic Marked')];
$insHotel=$pdo->prepare('INSERT INTO anytour_hotels(id,profile_json,is_active) VALUES(?,?,1)');
$insSource=$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id) VALUES('legacy_catalog',?,?)");
$insCatalog=$pdo->prepare('INSERT INTO catalog_hotels(id,name) VALUES(?,?)');
foreach ($profiles as $ownId=>$profile) {
    $tvId=100+$ownId;
    $insHotel->execute([$ownId,json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    $insSource->execute([(string)$tvId,$ownId]);
    $insCatalog->execute([$tvId,$profile['name']]);
}
$detail=$pdo->prepare('INSERT INTO catalog_hotel_details(hotel_id,status,fetched_at) VALUES(?,?,?)');
$detail->execute([101,'success','2026-09-15 10:00:00']);
$detail->execute([102,'success','2026-09-15 10:00:00']);
$detail->execute([103,'success','2026-09-17 22:30:00']);
$detail->execute([106,'generic_product','2026-09-10 10:00:00']);
$obs=$pdo->prepare('INSERT INTO tour_price_observations(hotel_id,source,observed_at) VALUES(?,?,?)');
$obs->execute([105,'user_search','2026-09-17 20:00:00']);
$obs->execute([105,'collector','2026-09-17 21:00:00']);
$obs->execute([101,'collector','2026-09-16 12:00:00']);

$providerCalls=0;
$fake=function(int $hotelId) use (&$providerCalls): array {
    ++$providerCalls;
    return [[
        'id'=>$hotelId,'name'=>'Hydrated '.$hotelId,'category'=>5,'rating'=>4.5,'type'=>1,
        'country'=>['id'=>4,'name'=>'Турция'],'region'=>['id'=>10,'name'=>'Анталья'],'subRegion'=>['id'=>11,'name'=>'Кемер'],
        'common'=>['description'=>'Описание '.$hotelId,'address'=>'Address','place'=>'Place','latitude'=>36.5,'longitude'=>30.5],
        'images'=>['//cdn.example.test/'.$hotelId.'-1.jpg','//cdn.example.test/'.$hotelId.'-2.jpg','//cdn.example.test/'.$hotelId.'-3.jpg'],
        'infrastructure'=>['pool'=>'Бассейн'],'services'=>['wifi'=>'Wi-Fi'],'meals'=>['list'=>'AI'],'roomTypes'=>'Standard',
    ]];
};
$tool=new AnyTourIdentityOnlyHotelContentV1($pdo,$fake);
$plan=$tool->plan(10,'2026-09-17T22:00:00Z','2026-09-17T23:00:00Z');
$plan2=$tool->plan(10,'2026-09-17T22:00:00Z','2026-09-17T23:00:00Z');
if ($plan!==$plan2) throw new RuntimeException('identity-only plan drifted without DB change');
if ($providerCalls!==0 || $plan['supplierCalls']!==0 || $plan['writes']!==0) throw new RuntimeException('plan performed side effects');
$ids=array_column($plan['selected'],'tourvisorHotelId');
if ($ids!==[105,101]) throw new RuntimeException('identity-only demand order/filter failed: '.json_encode($ids));
if ((int)$plan['eligibleIdentityOnly']!==3 || (int)$plan['genericPreSkipped']!==1) throw new RuntimeException('identity-only cohort accounting failed');
if (in_array(102,$ids,true) || in_array(103,$ids,true) || in_array(104,$ids,true) || in_array(106,$ids,true)) throw new RuntimeException('excluded profile entered identity-only queue');

$result=$tool->hydrate(10,'2026-09-17T22:00:00Z','2026-09-17T23:00:00Z',$plan['planSha256'],1,10,500);
if ($result['success']!==2 || $result['failed']!==0 || $result['notFound']!==0 || $result['providerAttempts']!==2) throw new RuntimeException('fixture hydration result failed');
if ($providerCalls!==2) throw new RuntimeException('fixture provider call count failed');
foreach ([101,105] as $hotelId) {
    $row=$pdo->query('SELECT status,description,images_json FROM catalog_hotel_details WHERE hotel_id='.$hotelId)->fetch();
    if (($row['status']??null)!=='success' || !str_contains((string)$row['description'],'Описание') || !str_contains((string)$row['images_json'],'cdn.example.test')) throw new RuntimeException('saved detail missing');
    $image=$pdo->query('SELECT primary_image_url FROM catalog_hotels WHERE id='.$hotelId)->fetchColumn();
    if (!is_string($image) || !str_starts_with($image,'https://cdn.example.test/')) throw new RuntimeException('catalog primary image not materialized');
}

echo 'ANYTOUR_IDENTITY_ONLY_CONTENT_TEST_OK selected='.count($plan['selected']).' generic_skipped='.$plan['genericPreSkipped'].' hydrated='.$result['success']."\n";
