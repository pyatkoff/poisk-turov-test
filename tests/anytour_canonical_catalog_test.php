<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-canonical-catalog-v1.php';
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable) { check(true, $message); return; }
    check(false, $message);
}
check(AnyTourCanonicalCatalog::ids([9,'2',9,1]) === [1,2,9], 'stable explicit deduplication');
foreach ([[], [0], [-1], [true], [1.0], ['1e3'], ['01'], [' 1'], ['+1'], ['1 OR 1=1'],
    ['x'=>1], [[1]], [null], [str_repeat('9',30)], range(1,1001)] as $bad) {
    rejects(fn() => AnyTourCanonicalCatalog::ids($bad), 'invalid batch rejected');
}
check(count(AnyTourCanonicalCatalog::ids(range(1,1000))) === 1000, 'mass batch accepted');
$input = ['id'=>7001,'name'=>'Отель','country'=>['id'=>4,'name'=>'Турция'],
    'region'=>['id'=>8,'name'=>'Регион'],'subRegion'=>null,'type'=>2,'description'=>'Описание',
    'primaryImage'=>'https://fixture.test/a.jpg','images'=>['https://fixture.test/a.jpg'],
    'meals'=>['list'=>'HB+'],'roomTypes'=>'Suite Sea View','services'=>['7'=>'Wi-Fi'],
    'infrastructure'=>['beach'=>'sand'],'detailsFetchedAt'=>'2026-09-16 01:00:00'];
$before = $input; $profile = AnyTourCanonicalCatalog::initialProfile($input);
check($input === $before, 'source array not mutated');
check(!isset($profile['id']) && !isset($profile['type']) && !isset($profile['country']['id']) && !isset($profile['region']['id']), 'no foreign IDs masquerade as own IDs');
check($profile['country']['name'] === 'Турция', 'geography names preserved');
check($profile['hotelInformation']['meals']['list'] === 'HB+', 'descriptive meal distinctions preserved');
check($profile['hotelInformation']['roomTypes'] === 'Suite Sea View', 'descriptive room conditions preserved');
check($profile['traits'] === [], 'source type does not become invented confirmed trait');
check($profile['description'] === 'Описание' && $profile['images'] === $input['images'], 'materialized content preserved');
rejects(fn() => AnyTourCanonicalCatalog::initialProfile(['name'=>'']), 'unnamed source not materialized');
$schema = file_get_contents(__DIR__ . '/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
check(!preg_match('/^\s*(?:ALTER|DROP|TRUNCATE|DELETE)\b/im', $schema), 'migration is additive only');
if (in_array('--unit-only', $argv, true)) {
    echo "ANYTOUR_CANONICAL_PURE_OK checks=$checks SQL_NOT_RUN=1\n"; exit;
}

// Refuse arbitrary/live hosts and database names. No project config or production secret is loaded.
$dsn = (string)getenv('ANYTOUR_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;dbname=anytour_catalog_fixture;charset=utf8mb4$/D', $dsn)) {
    throw new RuntimeException('Explicit disposable loopback MySQL fixture DSN required');
}
function connection(): PDO {
    global $dsn;
    return new PDO($dsn, 'root', (string)getenv('ANYTOUR_TEST_PASSWORD'), [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
}
$pdo = connection();
check($pdo->query('SHOW TABLES')->fetchAll() === [], 'test database must start empty');
$pdo->exec("CREATE TABLE catalog_hotels (
    id BIGINT PRIMARY KEY, name VARCHAR(255) NOT NULL, is_active TINYINT DEFAULT 1,
    country_id INT DEFAULT 4,country_name VARCHAR(255) DEFAULT 'Турция',
    region_id INT DEFAULT 8,region_name VARCHAR(255) DEFAULT 'Регион',
    subregion_id INT NULL,subregion_name VARCHAR(255) NULL,category INT DEFAULT 5,
    rating FLOAT NULL,hotel_type INT DEFAULT 2,latitude FLOAT NULL,longitude FLOAT NULL,
    primary_image_url VARCHAR(2048) NULL
) ENGINE=InnoDB CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE catalog_hotel_details (
    hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),description TEXT,address TEXT,place TEXT,
    build_info TEXT,repair_info TEXT,square_info TEXT,images_json TEXT,infrastructure_json TEXT,
    meals_json TEXT,services_json TEXT,room_types TEXT,fetched_at DATETIME
) ENGINE=InnoDB CHARSET=utf8mb4");
// Existing MATCH is a protected input, not another seed target.
$pdo->exec("CREATE TABLE tour_operator_hotel_identities (
    id BIGINT PRIMARY KEY,provider_key VARCHAR(32),provider_hotel_id VARCHAR(128),
    local_hotel_id BIGINT,match_status VARCHAR(32),evidence_json TEXT
) ENGINE=InnoDB CHARSET=utf8mb4");
$pdo->exec("INSERT INTO catalog_hotels (id,name) VALUES (7001,'Saved hotel one'),(7002,'Saved hotel two'),(7003,'Inactive')");
$pdo->exec('UPDATE catalog_hotels SET is_active=0 WHERE id=7003');
$pdo->exec("INSERT INTO catalog_hotel_details (hotel_id,status,description,images_json,meals_json,room_types,fetched_at)
    VALUES (7001,'success','Saved description','[\"https://fixture.test/a.jpg\"]','{\"list\":\"HB+\"}','Suite Sea View','2026-09-16 01:00:00'),
    (7002,'failure','Must not be used',NULL,NULL,NULL,'2026-09-16 01:00:00')");
$pdo->exec("INSERT INTO tour_operator_hotel_identities VALUES
    (1,'operator_5','41',7001,'accepted','{\"manual\":true}'),
    (2,'operator_315','41',7002,'pending','{\"hold\":true}')");
function legacyDigest(PDO $pdo): string {
    $result=[];
    foreach (['catalog_hotels','catalog_hotel_details','tour_operator_hotel_identities'] as $table) {
        $result[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
    }
    return hash('sha256',AnyTourCanonicalCatalog::json($result));
}
function ownCount(PDO $pdo, string $table): int {
    if (!in_array($table,['anytour_hotels','anytour_hotel_sources'],true)) throw new LogicException('test table');
    return (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
}
$catalog = new AnyTourCanonicalCatalog($pdo);
rejects(fn()=>$catalog->plan([7001]), 'missing schema fails without installing it');
check(count($pdo->query('SHOW TABLES')->fetchAll()) === 3, 'reader did not install schema');
$legacyBefore = legacyDigest($pdo);
foreach (explode(';',preg_replace('/^--.*$/m','',$schema)) as $sql) if (trim($sql)!=='') $pdo->exec($sql);
check(legacyDigest($pdo) === $legacyBefore, 'migration leaves old catalogues and MATCH byte-equivalent');
$catalog->assertSchema();
$ids = [7002,7001,7003,7099,7001];
$plan = $catalog->plan($ids);
check($plan['writes']===0 && $plan['source_profiles']===2 && $plan['missingIds']===[7003,7099], 'plan truthful missing/inactive');
check(ownCount($pdo,'anytour_hotels')===0, 'planning does not seed');
rejects(fn()=>$catalog->seed($ids,str_repeat('0',64)), 'wrong digest rejected');
check(ownCount($pdo,'anytour_hotels')===0, 'wrong digest wrote nothing');
$result=$catalog->seed($ids,$plan['source_sha256']);
check($result['created']===2 && $result['verified_bridges']===2, 'initial seed committed and reread');
$targets=$catalog->legacyTargets([7001,7002,7099]);
check(count($targets)===2 && $targets[7001]!==7001 && $targets[7002]!==7002, 'IDs allocated independently, not copied');
$canonical=$catalog->read(array_values($targets));
check($canonical['items'][0]['name']==='Saved hotel one', 'real canonical reader materializes content');
check($canonical['items'][1]['description']===null, 'failed source description not fabricated');
check($canonical['items'][0]['catalog']==='anytour', 'own catalogue provenance');
check(!isset($canonical['items'][0]['country']['id']), 'source geography IDs stay out of own profile');
check(legacyDigest($pdo)===$legacyBefore, 'seed preserves all original catalogues and matches');
$repeat=$catalog->seed($ids,$plan['source_sha256']);
check($repeat['created']===0 && $repeat['unchanged']===2 && ownCount($pdo,'anytour_hotels')===2, 'same seed is idempotent');

// Editorial update simulates a future authorized editor, not an import capability.
$edited=$canonical['items'][0]; unset($edited['id'],$edited['catalog'],$edited['revision']);
$edited['name']='Our curated hotel'; $edited['description']='Our editorial text';
$edited['primaryImage']='https://fixture.test/our.jpg'; $edited['images']=[$edited['primaryImage']];
$editedJson=AnyTourCanonicalCatalog::json($edited);
$stmt=$pdo->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=?,revision=revision+1 WHERE id=?');
$stmt->execute([$editedJson,hash('sha256',$editedJson),$targets[7001]]);
$pdo->exec("UPDATE catalog_hotels SET name='Changed supplier name' WHERE id=7001");
rejects(fn()=>$catalog->seed($ids,$plan['source_sha256']), 'stale source fingerprint blocks import');
$refreshPlan=$catalog->plan($ids); $refresh=$catalog->seed($ids,$refreshPlan['source_sha256']);
check($refresh['source_snapshots_refreshed']===1 && $refresh['created']===0, 'refresh updates source only');
$actual=$catalog->read([$targets[7001]])['items'][0];
check($actual['name']===$edited['name'] && $actual['description']===$edited['description'] && $actual['images']===$edited['images'] && $actual['revision']===2, 'all editorial fields and revision survive');
$source=json_decode($pdo->query("SELECT source_json FROM anytour_hotel_sources WHERE external_key='7001'")->fetchColumn(),true);
check($source['name']==='Changed supplier name', 'latest source separately available for review');
$pdo->exec('UPDATE catalog_hotels SET is_active=0 WHERE id=7001');
$gone=$catalog->plan([7001]); $goneResult=$catalog->seed([7001],$gone['source_sha256']);
check($goneResult['missingIds']===[7001] && count($catalog->read([$targets[7001]])['items'])===1, 'source removal never deletes canonical hotel');
$pdo->exec('UPDATE catalog_hotels SET is_active=1 WHERE id=7001');
$pdo->exec('UPDATE anytour_hotels SET is_active=0 WHERE id='.(int)$targets[7002]);
check($catalog->read([$targets[7002]])['missingIds']===[$targets[7002]], 'canonical disable respected');
$disabledPlan=$catalog->plan([7002]); $catalog->seed([7002],$disabledPlan['source_sha256']);
check($catalog->read([$targets[7002]])['items']===[], 'seed never reactivates editorial disable');

// This layer translates the accepted legacy target only; it never guesses providers by equal IDs.
$accepted=(int)$pdo->query("SELECT local_hotel_id FROM tour_operator_hotel_identities WHERE id=1 AND match_status='accepted'")->fetchColumn();
check($catalog->legacyTargets([$accepted])[$accepted]===$targets[7001], 'accepted MATCH target translates without rematching');
check($catalog->legacyTargets([41])===[], 'provider code is not mistaken for legacy ID');
check($pdo->query('SELECT match_status FROM tour_operator_hotel_identities WHERE id=2')->fetchColumn()==='pending', 'pending mapping never auto-accepted');

// SQL uniqueness/FK protect arbitrary future namespaces without a hardcoded three-provider limit.
$add=$pdo->prepare("INSERT INTO anytour_hotel_sources
    (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
    VALUES (?,?,?,'fixture','{}',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
foreach ([['operator_a','41'],['operator_b','41'],['operator_a','AbC'],['operator_a','abc'],['operator_a','abc ']] as [$namespace,$key]) {
    $add->execute([$namespace,$key,$targets[7001],hash('sha256','{}')]);
}
check(ownCount($pdo,'anytour_hotel_sources')===7, 'namespace, case and exact source-key bytes stay distinct');
rejects(fn()=>$add->execute(['operator_a','41',$targets[7002],hash('sha256','{}')]), 'duplicate source identity cannot be reassigned');
rejects(fn()=>$add->execute(['operator_a','unknown',99999999,hash('sha256','{}')]), 'dangling canonical link rejected');

// Trigger forces failure after one insert: both canonical records and bridges roll back.
$pdo->exec("INSERT INTO catalog_hotels (id,name) VALUES (8001,'First'),(8002,'ROLLBACK_MARKER')");
$pdo->exec("CREATE TRIGGER catalogue_fixture_failure BEFORE INSERT ON anytour_hotels FOR EACH ROW
    BEGIN IF NEW.profile_json LIKE '%ROLLBACK_MARKER%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture failure'; END IF; END");
$rollbackPlan=$catalog->plan([8001,8002]); $count=ownCount($pdo,'anytour_hotels'); $links=ownCount($pdo,'anytour_hotel_sources');
rejects(fn()=>$catalog->seed([8001,8002],$rollbackPlan['source_sha256']), 'mid-batch failure propagates');
check(ownCount($pdo,'anytour_hotels')===$count && ownCount($pdo,'anytour_hotel_sources')===$links && $catalog->legacyTargets([8001,8002])===[], 'whole seed transaction rolled back');
$pdo->exec('DROP TRIGGER catalogue_fixture_failure');

// A second real SQL connection proves overlapping seed writers cannot both proceed.
$other=connection(); $other->beginTransaction();
$other->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1 FOR UPDATE');
$pdo->exec('SET SESSION innodb_lock_wait_timeout=1');
rejects(fn()=>$catalog->seed([8001,8002],$rollbackPlan['source_sha256']), 'concurrent lock prevents seed');
check(!$pdo->inTransaction() && ownCount($pdo,'anytour_hotels')===$count, 'blocked seed leaves no partial transaction');
$other->rollBack();
check($catalog->seed([8001,8002],$rollbackPlan['source_sha256'])['created']===2, 'seed proceeds after lock released');
$pdo->beginTransaction(); rejects(fn()=>$catalog->plan([7001]), 'nested caller transaction rejected');
check($pdo->inTransaction(), 'caller transaction not committed'); $pdo->rollBack();

// Meaningful batch: 1000 saved hotels, not a micro demo, with deterministic repeat.
$insert=$pdo->prepare('INSERT INTO catalog_hotels (id,name) VALUES (?,?)');
$bulk=range(100001,101000);
foreach ($bulk as $id) $insert->execute([$id,'Mass fixture '.$id]);
$legacyBefore=legacyDigest($pdo); $mass=$catalog->plan($bulk);
$result=$catalog->seed($bulk,$mass['source_sha256']);
check($result['created']===1000 && $result['verified_bridges']===1000, 'all thousand copied and reread');
check($catalog->seed($bulk,$mass['source_sha256'])['unchanged']===1000, 'thousand repeated without duplicate hotels');
check(legacyDigest($pdo)===$legacyBefore, 'mass import preserves sources and MATCH');
$massTargets=array_values($catalog->legacyTargets($bulk));
check(count($catalog->read($massTargets)['items'])===1000, 'independent bulk reader works');
$pdo->exec('UPDATE anytour_catalog_control SET schema_version=2 WHERE singleton_id=1');
rejects(fn()=>$catalog->seed([7001],$refreshPlan['source_sha256']), 'unknown schema version rejected');
$pdo->exec('UPDATE anytour_catalog_control SET schema_version=1 WHERE singleton_id=1');
$pdo->exec("UPDATE anytour_hotels SET profile_json='{}' WHERE id=".(int)$targets[7001]);
rejects(fn()=>$catalog->read([$targets[7001]]), 'canonical content/hash corruption not served');
echo "ANYTOUR_CANONICAL_CATALOG_OK checks=$checks real_mysql=1 bulk=1000 source_writes=0 supplier_calls=0\n";
