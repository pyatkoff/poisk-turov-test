<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/catalog/anytour_catalog_coverage_census.php';

function coverage_need(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('CHECK_FAILED:' . $label);
}

$dsn = (string)getenv('ANYTOUR_COVERAGE_TEST_DSN');
$password = (string)getenv('ANYTOUR_COVERAGE_TEST_PASSWORD');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anytour_coverage_fixture;charset=utf8mb4') {
    throw new RuntimeException('Dedicated disposable fixture DSN required');
}
$pdo = new PDO($dsn, 'root', $password, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `$table`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec('CREATE TABLE anytour_catalog_control(singleton_id TINYINT UNSIGNED PRIMARY KEY,schema_version INT UNSIGNED NOT NULL) ENGINE=InnoDB');
$pdo->exec('INSERT INTO anytour_catalog_control VALUES(1,1)');
$pdo->exec('CREATE TABLE anytour_hotels(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_json LONGTEXT NOT NULL,profile_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
$pdo->exec('CREATE TABLE anytour_hotel_sources(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    external_key VARBINARY(128) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,
    acquired_via VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_json LONGTEXT NOT NULL,source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,
    UNIQUE KEY uq_anytour_hotel_source(namespace,external_key),
    CONSTRAINT fk_cov_source_hotel FOREIGN KEY(anytour_hotel_id) REFERENCES anytour_hotels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');

$insertProfile = static function(PDO $pdo, array $profile, int $active=1): int {
    $json = json_encode($profile, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at)
        VALUES(?,?,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $stmt->execute([$json,hash('sha256',$json),$active]);
    return (int)$pdo->lastInsertId();
};
$insertSource = static function(PDO $pdo, int $hotel, string $namespace, string $external, string $via): void {
    $source = json_encode(['namespace'=>$namespace,'external'=>$external], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare('INSERT INTO anytour_hotel_sources
        (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $stmt->execute([$namespace,$external,$hotel,$via,$source,hash('sha256',$source)]);
};

$full = $insertProfile($pdo, [
    'name'=>'Полный отель','country'=>['name'=>'Египет'],'region'=>['name'=>'Шарм-эль-Шейх'],'subRegion'=>null,
    'coordinates'=>['latitude'=>27.91,'longitude'=>34.33],'category'=>5,'rating'=>4.8,
    'description'=>'Проверенное описание собственного профиля AnyTour.',
    'primaryImage'=>'https://img.example/hotel-main.jpg',
    'images'=>['https://img.example/hotel-main.jpg','https://img.example/hotel-2.jpg','https://img.example/hotel-3.jpg'],
    'address'=>'Тестовый адрес','place'=>'Наама-Бей','build'=>'2005','repair'=>'2024','square'=>'10000 м²',
    'hotelInformation'=>['infrastructure'=>['Бассейн'],'services'=>['Wi-Fi'],'meals'=>['legacy AI'],'roomTypes'=>'legacy rooms'],
    'traits'=>['family'=>true],
]);
$identityOnly = $insertProfile($pdo, [
    'name'=>'Только identity','country'=>['name'=>'Турция'],'region'=>null,'subRegion'=>null,
    'category'=>null,'rating'=>null,'description'=>null,'primaryImage'=>null,'images'=>[],
    'coordinates'=>null,'hotelInformation'=>['infrastructure'=>[],'services'=>[],'meals'=>[],'roomTypes'=>null],'traits'=>[],
]);
$cardPartial = $insertProfile($pdo, [
    'name'=>'Карточка','country'=>['name'=>'ОАЭ'],'region'=>['name'=>'Дубай'],'subRegion'=>null,
    'category'=>4,'rating'=>null,'description'=>null,'primaryImage'=>'https://img.example/card.jpg','images'=>['https://img.example/card.jpg'],
    'coordinates'=>null,'hotelInformation'=>['infrastructure'=>[],'services'=>[],'meals'=>[],'roomTypes'=>null],'traits'=>[],
]);
$otherPartial = $insertProfile($pdo, [
    'name'=>'Частичный','country'=>['name'=>'Мальдивы'],'region'=>null,'subRegion'=>null,
    'category'=>null,'rating'=>4.1,'description'=>'Есть только часть контента','primaryImage'=>null,'images'=>[],
    'coordinates'=>null,'hotelInformation'=>['infrastructure'=>[],'services'=>['Трансфер'],'meals'=>[],'roomTypes'=>null],'traits'=>[],
]);
$inactive = $insertProfile($pdo, ['name'=>'Неактивный','country'=>['name'=>'Египет']], 0);

$insertSource($pdo,$full,'legacy_catalog','101','saved_catalog');
$insertSource($pdo,$full,'provider_ref_digest:andromeda',hash('sha256','andromeda:1'),'match_accepted_bridge');
$insertSource($pdo,$identityOnly,'legacy_catalog','102','saved_catalog');
$insertSource($pdo,$cardPartial,'provider_ref_digest:andromeda',hash('sha256','andromeda:2'),'match_accepted_bridge');
$insertSource($pdo,$otherPartial,'future_provider','x-1','fixture');
$insertSource($pdo,$inactive,'legacy_catalog','999','saved_catalog');

$before = [
    'hotels'=>(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn(),
    'sources'=>(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn(),
    'profile_sum'=>(string)$pdo->query("SELECT SHA2(GROUP_CONCAT(profile_sha256 ORDER BY id SEPARATOR ''),256) FROM anytour_hotels")->fetchColumn(),
];
$result = (new AnyTourCatalogCoverageCensus($pdo))->run(
    'fixture-canonical-coverage',
    '996a2ece8aef332c1a31ed6c7e2da790759bcc99'
);
$after = [
    'hotels'=>(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn(),
    'sources'=>(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn(),
    'profile_sum'=>(string)$pdo->query("SELECT SHA2(GROUP_CONCAT(profile_sha256 ORDER BY id SEPARATOR ''),256) FROM anytour_hotels")->fetchColumn(),
];
coverage_need($before === $after, 'census is read-only');
coverage_need($result['status'] === 'completed_read_only' && $result['writes'] === 0 && $result['supplier_calls'] === 0, 'read-only receipt');
coverage_need($result['profiles'] === ['total'=>5,'active'=>4,'inactive'=>1], 'profile counts');
coverage_need($result['readiness']['identityReady'] === 4, 'identity readiness');
coverage_need($result['readiness']['geoReady'] === 1, 'geo readiness');
coverage_need($result['readiness']['cardReady'] === 2, 'card readiness');
coverage_need($result['readiness']['detailReady'] === 1, 'detail readiness');
coverage_need($result['readiness']['identityOnly'] === 1, 'identity-only count');
coverage_need($result['readiness']['partial'] === 2, 'partial count');
coverage_need($result['fields']['gallery3Plus'] === 1 && $result['fields']['description'] === 2, 'field coverage');
coverage_need($result['sources']['active_hotel_modes'] === [
    'none'=>0,'legacyOnly'=>1,'directOnly'=>1,'legacyAndDirect'=>1,'otherOrMixed'=>1,
], 'source-mode split');
coverage_need(($result['sources']['namespaces']['legacy_catalog']['hotels'] ?? null) === 3, 'legacy namespace distinct hotels includes inactive source owner');
coverage_need(($result['sources']['namespaces']['provider_ref_digest:andromeda']['rows'] ?? null) === 2, 'direct provider rows');
coverage_need(($result['integrity']['ok'] ?? false) === true, 'clean integrity');
coverage_need(!$pdo->inTransaction(), 'transaction closed');

$pdo->exec("UPDATE anytour_hotels SET profile_sha256=REPEAT('0',64) WHERE id=" . $full);
$bad = (new AnyTourCatalogCoverageCensus($pdo))->run(
    'fixture-canonical-coverage-corrupt',
    '996a2ece8aef332c1a31ed6c7e2da790759bcc99'
);
coverage_need($bad['integrity']['profileHashMismatch'] === 1 && $bad['integrity']['ok'] === false, 'hash corruption surfaced, never hidden');
coverage_need($bad['writes'] === 0 && $bad['mapping_writes'] === 0 && $bad['legacy_writes'] === 0, 'corruption audit stays read-only');

echo "ANYTOUR_CATALOG_COVERAGE_CENSUS_OK active=4 identity=4 card=2 detail=1 identity_only=1 partial=2 writes=0 supplier_calls=0\n";
