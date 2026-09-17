<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/catalog/anytour_stay_candidates.php';

$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};

$dsn = getenv('ANYTOUR_STAY_CANDIDATES_TEST_DSN') ?: '';
$password = getenv('ANYTOUR_STAY_CANDIDATES_TEST_PASSWORD') ?: '';
if ($dsn === '') throw new RuntimeException('ANYTOUR_STAY_CANDIDATES_TEST_DSN required; this suite never skips SQL');
$db = new PDO($dsn, 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);

foreach (['anytour_stay_mappings','anytour_hotel_rooms','anytour_room_categories','anytour_meal_plans',
    'anytour_hotel_sources','anytour_hotels','anytour_catalog_control','hot_tours_current','tour_price_observations'] as $table) {
    $db->exec('DROP TABLE IF EXISTS ' . $table);
}
$db->exec(file_get_contents(__DIR__ . '/../v2/data/migrations/20260916-anytour-canonical-catalog.sql'));
$db->exec(file_get_contents(__DIR__ . '/../v2/data/migrations/20260916-anytour-stay-catalog.sql'));
// Match the live evidence-table collation instead of the old all-binary fixture. Exact
// LOCAL source/mapping keys remain VARBINARY through the real migrations above.
$db->exec("CREATE TABLE hot_tours_current (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT UNSIGNED NOT NULL, operator_id INT UNSIGNED NULL, meal_id INT UNSIGNED NULL,
    meal_name VARCHAR(180) NULL, fetched_at DATETIME NOT NULL,
    KEY idx_hot_hotel(hotel_id,fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE tour_price_observations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT UNSIGNED NOT NULL, operator_id INT UNSIGNED NULL, room_id INT UNSIGNED NULL,
    room_type VARCHAR(255) NULL, observed_at DATETIME NOT NULL,
    KEY idx_price_hotel_date(hotel_id,observed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$roomCollation = $db->query("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='tour_price_observations' AND COLUMN_NAME='room_type'")->fetchColumn();
$sourceKeyType = $db->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='anytour_hotel_sources' AND COLUMN_NAME='external_key'")->fetchColumn();
$mappingKeyType = $db->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='anytour_stay_mappings' AND COLUMN_NAME='external_key'")->fetchColumn();
$check($roomCollation === 'utf8mb4_unicode_ci', 'fixture reproduces live evidence label collation');
$check($sourceKeyType === 'varbinary' && $mappingKeyType === 'varbinary', 'fixture keeps exact LOCAL keys binary');

$profile = json_encode(['name' => 'Fixture Hotel'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$profileSha = hash('sha256', $profile);
$db->prepare("INSERT INTO anytour_hotels(id,profile_json,profile_sha256,revision,is_active,created_at,updated_at)
    VALUES(1,?,?,1,1,'2026-09-17 00:00:00','2026-09-17 00:00:00')")->execute([$profile, $profileSha]);
$source = json_encode(['id' => 102, 'name' => 'Fixture Hotel'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$sourceSha = hash('sha256', $source);
$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
    VALUES('legacy_catalog','102',1,'saved_catalog',?,?,'2026-09-17 00:00:00','2026-09-17 00:00:00')")->execute([$source, $sourceSha]);
// A prefix-looking key must never be treated as hotel 102 by numeric CAST alone.
$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
    VALUES('legacy_catalog','102suffix',1,'saved_catalog',?,?,'2026-09-17 00:00:00','2026-09-17 00:00:00')")->execute([$source, $sourceSha]);

$hot = $db->prepare('INSERT INTO hot_tours_current(hotel_id,operator_id,meal_id,meal_name,fetched_at) VALUES(?,?,?,?,?)');
$hot->execute([102,5,7,'AI','2026-09-17 00:10:00']);
$hot->execute([102,5,7,'All Inclusive','2026-09-17 00:20:00']);
$hot->execute([102,6,7,'AI','2026-09-17 00:30:00']);
$hot->execute([999,5,7,'AI','2026-09-17 00:40:00']);
$hot->execute([102,null,8,'UAI','2026-09-17 00:50:00']);

$obs = $db->prepare('INSERT INTO tour_price_observations(hotel_id,operator_id,room_id,room_type,observed_at) VALUES(?,?,?,?,?)');
$obs->execute([102,5,11,'Deluxe Sea View','2026-09-17 00:11:00']);
$obs->execute([102,5,11,'Deluxe Sea View','2026-09-17 00:12:00']);
$obs->execute([102,5,null,'Promo Room','2026-09-17 00:13:00']);
$obs->execute([999,5,12,'Other','2026-09-17 00:14:00']);
$obs->execute([102,null,13,'No operator','2026-09-17 00:15:00']);

$db->prepare("INSERT INTO anytour_stay_mappings(namespace,external_hotel_key,operator_key,kind,key_kind,external_key,
    anytour_hotel_id,room_id,meal_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at)
    VALUES('legacy_catalog','102','5','meal','code','7',1,NULL,NULL,'rejected','fixture-negative',?,'fixture','2026-09-17 00:00:00')")
    ->execute([str_repeat('a', 64)]);
$db->prepare("INSERT INTO anytour_stay_mappings(namespace,external_hotel_key,operator_key,kind,key_kind,external_key,
    anytour_hotel_id,room_id,meal_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at)
    VALUES('legacy_catalog','102','5','room','label','Promo Room',1,NULL,NULL,'rejected','fixture-room-negative',?,'fixture','2026-09-17 00:00:00')")
    ->execute([str_repeat('b', 64)]);

$beforeSources = (int)$db->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn();
$beforeMappings = (int)$db->query('SELECT COUNT(*) FROM anytour_stay_mappings')->fetchColumn();
$result = (new AnyTourStayCandidates($db))->collect(50);
$afterSources = (int)$db->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn();
$afterMappings = (int)$db->query('SELECT COUNT(*) FROM anytour_stay_mappings')->fetchColumn();

$check($result['status'] === 'read_only_evidence_inventory', 'explicit read-only status');
$check($result['source'] === 'saved_db_only', 'saved evidence source only');
$check($result['writes'] === 0 && $result['supplierCalls'] === 0 && $result['automaticAccepts'] === 0, 'no side effects claimed');
$check(count($result['localMealPlans']) === 10, 'ten local meal definitions');
$check($result['counts']['mealCandidates'] === 2, 'meal candidates scoped to exact bridged hotel/operator');
$check($result['counts']['roomCandidates'] === 2, 'room candidates scoped to exact bridged hotel/operator');
$check($beforeSources === $afterSources && $beforeMappings === $afterMappings, 'inventory performs no source/mapping writes');

$meal5 = array_values(array_filter($result['mealCandidates'], static fn($v) => $v['scope']['operatorKey'] === '5'))[0] ?? null;
$check(is_array($meal5), 'operator 5 meal found');
$check($meal5['hotelId'] === 1 && $meal5['scope']['hotelKey'] === '102', 'meal bound through exact current AnyTour source relation');
$check($meal5['sourceSha256'] === $sourceSha, 'exact source digest retained');
$check($meal5['reference'] === ['kind'=>'meal','keyKind'=>'code','externalKey'=>'7'], 'supplier meal id retained');
$check($meal5['observedCount'] === 2 && $meal5['lastSeenAt'] === '2026-09-17 00:20:00', 'meal frequency/freshness retained');
$check($meal5['labelConflict'] === true && $meal5['distinctLabels'] === 2, 'conflicting labels surfaced');
$check($meal5['decisionState'] === 'rejected', 'existing negative meal decision surfaced');
$check(!in_array('102suffix', array_column(array_column($result['mealCandidates'], 'scope'), 'hotelKey'), true), 'numeric prefix source key never aliases hotel id');

$roomCode = array_values(array_filter($result['roomCandidates'], static fn($v) => $v['reference']['keyKind'] === 'code'))[0] ?? null;
$roomLabel = array_values(array_filter($result['roomCandidates'], static fn($v) => $v['reference']['keyKind'] === 'label'))[0] ?? null;
$check(is_array($roomCode) && $roomCode['reference']['externalKey'] === '11', 'room id retained as binary-safe code key');
$check($roomCode['sampleLabel'] === 'Deluxe Sea View' && $roomCode['observedCount'] === 2, 'room evidence count retained');
$check(is_array($roomLabel) && $roomLabel['reference']['externalKey'] === 'Promo Room', 'unicode-collated room label retained exactly');
$check($roomLabel['decisionState'] === 'rejected', 'VARBINARY mapping matches unicode-collated label exactly');
$check($result['counts']['unmappedMeals'] === 1 && $result['counts']['unmappedRooms'] === 1, 'unmapped queue counts explicit after exact decisions');

try { (new AnyTourStayCandidates($db))->collect(0); $check(false, 'zero limit rejected'); }
catch (InvalidArgumentException) { $check(true, 'zero limit rejected'); }
try { (new AnyTourStayCandidates($db))->collect(5001); $check(false, 'oversized limit rejected'); }
catch (InvalidArgumentException) { $check(true, 'oversized limit rejected'); }

echo 'ANYTOUR_STAY_CANDIDATES_OK checks=' . $checks . " sql=REAL_MYSQL live_collation=utf8mb4_unicode_ci writes=0 supplier_calls=0\n";
