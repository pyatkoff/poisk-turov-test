<?php
/** Real-MySQL regression for the one-shot additive AnyTour profile backfill. */
declare(strict_types=1);
require_once __DIR__ . '/../scripts/catalog/anytour_catalog_backfill_apply.php';

$checks = 0;
function bfaVerify(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function bfaRefuses(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable) { bfaVerify(true, $message); return; }
    bfaVerify(false, $message);
}

$dsn = (string)getenv('ANYTOUR_BACKFILL_APPLY_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;dbname=anytour_backfill_apply_fixture;charset=utf8mb4$/D', $dsn)) {
    throw new RuntimeException('Explicit disposable loopback fixture required');
}
$password = (string)getenv('ANYTOUR_TEST_PASSWORD');
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$pdo = new PDO($dsn, 'root', $password, $options);
bfaVerify($pdo->query('SHOW TABLES')->fetchAll() === [], 'fixture starts empty');

$pdo->exec("CREATE TABLE catalog_hotels (
    id BIGINT PRIMARY KEY,name VARCHAR(255) NOT NULL,is_active TINYINT DEFAULT 1,
    country_id INT DEFAULT 4,country_name VARCHAR(255) DEFAULT 'Турция',
    region_id INT DEFAULT 8,region_name VARCHAR(255) DEFAULT 'Регион',
    subregion_id INT NULL,subregion_name VARCHAR(255) NULL,category INT DEFAULT 5,
    rating FLOAT NULL,hotel_type INT DEFAULT 2,latitude FLOAT NULL,longitude FLOAT NULL,
    primary_image_url VARCHAR(2048) NULL) ENGINE=InnoDB CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE catalog_hotel_details (
    hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),description TEXT,address TEXT,place TEXT,
    build_info TEXT,repair_info TEXT,square_info TEXT,images_json TEXT,infrastructure_json TEXT,
    meals_json TEXT,services_json TEXT,room_types TEXT,fetched_at DATETIME) ENGINE=InnoDB CHARSET=utf8mb4");
$sql = preg_replace('/^--.*$/m', '', (string)file_get_contents(__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql'));
foreach (array_values(array_filter(array_map('trim', explode(';', $sql)))) as $statement) $pdo->exec($statement);

$hotel = $pdo->prepare('INSERT INTO catalog_hotels(id,name,is_active) VALUES (?,?,1)');
$details = $pdo->prepare('INSERT INTO catalog_hotel_details(hotel_id,status,description,images_json,fetched_at) VALUES (?,\'success\',?,?,?)');
$good = json_encode(['https://fixture.test/hotel.jpg'], JSON_THROW_ON_ERROR);
$pdo->beginTransaction();
for ($id=1; $id<=1000; $id++) {
    $hotel->execute([$id, 'Baseline '.$id]);
    $details->execute([$id, 'Baseline description '.$id, $good, '2026-09-17 06:00:00']);
}
foreach ([2001,2002,2003] as $id) {
    $hotel->execute([$id, 'Next '.$id]);
    $details->execute([$id, 'Next description '.$id, $good, '2026-09-17 06:00:00']);
}
$pdo->commit();

$catalog = new AnyTourCanonicalCatalog($pdo);
$baselineIds = range(1, 1000);
$baselinePlan = $catalog->plan($baselineIds);
$baselineSeed = $catalog->seed($baselineIds, $baselinePlan['source_sha256']);
bfaVerify($baselineSeed['created'] === 1000 && $baselineSeed['verified_bridges'] === 1000, 'fixture has reviewed 1000-profile baseline');
bfaVerify(anytour_catalog_counts($pdo) === ['hotels'=>1000,'sources'=>1000], 'baseline counts match live operation precondition');

$sourceBefore = hash('sha256', json_encode([
    $pdo->query('SELECT * FROM catalog_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM catalog_hotel_details ORDER BY hotel_id')->fetchAll(PDO::FETCH_ASSOC),
], JSON_THROW_ON_ERROR));
$states = [];
$result = anytour_catalog_backfill_apply($pdo, 2, static function(array $state) use (&$states): void { $states[]=$state['status']; });
bfaVerify($result['status']==='backfill_seeded_verified' && $result['selected']===2, 'two next profiles are committed and verified');
bfaVerify($result['selectedIds']===[2001,2002], 'deterministic next-missing identities are used');
bfaVerify($result['before']===['hotels'=>1000,'sources'=>1000] && $result['after']===['hotels'=>1002,'sources'=>1002], 'only canonical profile/source counts grow');
bfaVerify($result['profilesWithDescription']===2 && $result['profilesWithImages']===2, 'new own profiles retain required presentation content');
bfaVerify($result['legacyWrites']===0 && $result['supplierCalls']===0 && $result['publicFileWrites']===0, 'apply changes no supplier/source/public boundary');
bfaVerify($states===['backfill_planned','seed_attempting','seed_committed_verified'], 'durable phases bracket the commit boundary');
$sourceAfter = hash('sha256', json_encode([
    $pdo->query('SELECT * FROM catalog_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM catalog_hotel_details ORDER BY hotel_id')->fetchAll(PDO::FETCH_ASSOC),
], JSON_THROW_ON_ERROR));
bfaVerify($sourceAfter===$sourceBefore, 'successful apply leaves saved source tables byte-equivalent');
$links=$catalog->legacyTargets([2001,2002]);
bfaVerify(array_keys($links)===[2001,2002] && count(array_unique(array_values($links)))===2, 'new legacy bridges point to independently allocated own profiles');

// A source change after planner evidence but before the write must abort before seed().
$other = new PDO($dsn, 'root', $password, $options);
$countsBeforeDrift = anytour_catalog_counts($pdo);
$drifted = false;
bfaRefuses(function() use ($pdo,$other,&$drifted): void {
    anytour_catalog_backfill_apply($pdo, 1, static function(array $state) use ($other,&$drifted): void {
        if (($state['status'] ?? '')==='backfill_planned' && !$drifted) {
            $other->exec("UPDATE catalog_hotel_details SET description='Changed after reviewed plan' WHERE hotel_id=2003");
            $drifted=true;
        }
    });
}, 'source drift after reviewed plan fails before canonical write');
bfaVerify($drifted, 'drift probe actually changed the remaining source profile');
bfaVerify(anytour_catalog_counts($pdo)===$countsBeforeDrift, 'failed exact-digest apply creates no canonical profile or bridge');
bfaVerify($catalog->legacyTargets([2003])===[], 'drifted source remains unowned');
bfaVerify(!$pdo->inTransaction(), 'failed apply leaves no caller transaction open');

// Fresh evidence after the changed source can proceed normally.
$final = anytour_catalog_backfill_apply($pdo, 1, static function(array $state): void {});
bfaVerify($final['selectedIds']===[2003] && $final['selected']===1, 'fresh plan can seed the changed source exactly once');
bfaVerify($final['after']===['hotels'=>1003,'sources'=>1003], 'final canonical coverage reaches all content-ready fixture profiles');
$empty=(new AnyTourCatalogBackfillV1($pdo))->planNext(1000);
bfaVerify($empty['selectedIds']===[] && $empty['content_ready_missing']===0, 'next-missing planner is empty after verified coverage');
bfaRefuses(fn()=>anytour_catalog_backfill_apply($pdo,1000,static function(array $state): void {}), 'empty cohort is never treated as another write operation');
bfaVerify(anytour_catalog_counts($pdo)===['hotels'=>1003,'sources'=>1003], 'repeat attempt performs no write');

echo "ANYTOUR_BACKFILL_APPLY_MYSQL_OK checks=$checks baseline=1000 created=3 drift_guard=1 exact_digest=1 real_mysql=1 live_database=0\n";
