<?php
define('HB3_LIBRARY_ONLY', true);
require __DIR__ . '/../scripts/diagnostics/hotel_observed_bulk_reconcile_v3.php';
$dsn = getenv('ANEX_LINK_TEST_DSN');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4') throw new RuntimeException('isolated only');
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = ['andromeda_search_hotel_observations','andromeda_hotel_identities','anex_review_pair_exclusions','anex_hotel_decisions','anex_hotel_search_mappings','anex_search_hotel_observations','anex_hotels','hotel_aliases','catalog_hotels'];
foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
try {
    $db->exec("CREATE TABLE catalog_hotels(id INT PRIMARY KEY,country_id INT,country_name VARCHAR(160),name VARCHAR(255),region_name VARCHAR(180),subregion_name VARCHAR(180),category INT NULL,is_active INT) ENGINE=InnoDB");
    $db->exec("CREATE TABLE hotel_aliases(id INT AUTO_INCREMENT PRIMARY KEY,hotel_id INT,alias VARCHAR(255),normalized_alias VARCHAR(255)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_hotels(anex_hotel_id INT PRIMARY KEY) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_search_hotel_observations(anex_hotel_id INT PRIMARY KEY,hotel_name VARCHAR(300),country_id INT,anex_country_id INT,last_catalog_hotel_id BIGINT NULL,first_seen_utc DATETIME,last_seen_utc DATETIME,search_count BIGINT,last_checkin_from DATE,last_checkin_to DATE,last_source_sha CHAR(40)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(32),scope VARCHAR(16),approval_policy VARCHAR(64),source_row_digest CHAR(64),mapping_digest CHAR(64),enabled INT) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(16)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE andromeda_search_hotel_observations(observation_sha256 CHAR(64) PRIMARY KEY,search_evidence_sha256 CHAR(64),supplier_namespace VARCHAR(160),external_hotel_id VARCHAR(128),hotel_name VARCHAR(300),operator_refs_json TEXT,operator_names_json TEXT,country_id INT,country_name VARCHAR(160),region_name VARCHAR(180),category INT NULL,description_text MEDIUMTEXT NULL,image_url VARCHAR(2048) NULL,hotel_url VARCHAR(2048) NULL,content_sha256 CHAR(64),observed_at_utc DATETIME) ENGINE=InnoDB");
    $db->exec("CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(128),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB");

    $hotel = $db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,?,?,?,1)');
    for ($i=1; $i<=300; $i++) $hotel->execute([10000+$i,4,'Turkey','SUNSET COAST RESORT '.$i,'Alanya','Alanya',5]);
    for ($i=1; $i<=100; $i++) $hotel->execute([11000+$i,4,'Turkey','ROYAL BLUE SEA PALACE '.$i,'Kemer','Kemer',5]);
    $hotel->execute([19000,2,'Russia','RUSSIA TEST RESORT','Sochi','Sochi',5]);
    // Make ANEX #5 ambiguous and Andromeda #2 have an equally-good runner-up.
    $hotel->execute([19505,4,'Turkey','SUNSET COAST RESORT 5','Alanya','Alanya',5]);
    $hotel->execute([19502,4,'Turkey','ROYAL BLUE SEA GRAND 2','Kemer','Kemer',5]);
    // One ANEX observation resolves only through a current alias.
    $db->exec("INSERT INTO hotel_aliases(hotel_id,alias,normalized_alias) VALUES(10007,'SUNSET ALIAS SEVEN','sunset alias seven')");

    $anexObservation = $db->prepare("INSERT INTO anex_search_hotel_observations VALUES(?,?,4,5,NULL,NOW(),NOW(),10,'2026-09-20','2026-09-20',NULL)");
    for ($i=1; $i<=300; $i++) {
        $name = $i === 6 ? 'SOLE HOTEL' : ($i === 7 ? 'SUNSET ALIAS SEVEN HOTEL' : 'SUNSET COAST RESORT '.$i.' HOTEL (EX. OLD '.$i.')');
        $anexObservation->execute([20000+$i,$name]);
    }
    $russiaObservation = $db->prepare("INSERT INTO anex_search_hotel_observations VALUES(?,?,2,5,NULL,NOW(),NOW(),10,'2026-09-20','2026-09-20',NULL)");
    $russiaObservation->execute([20999,'RUSSIA TEST RESORT HOTEL']);
    $db->exec("INSERT INTO anex_hotel_decisions VALUES(20001,10001,'rejected')");
    $db->exec("INSERT INTO anex_hotel_search_mappings VALUES(20002,10002,'strong_candidate','preview','owner_exact_and_strong_20260908',REPEAT('b',64),REPEAT('c',64),1)");
    $db->exec("INSERT INTO anex_hotels VALUES(20003)");
    $db->exec("INSERT INTO anex_review_pair_exclusions VALUES(20004,10004)");

    $observation = $db->prepare("INSERT INTO andromeda_search_hotel_observations VALUES(?,?,?,?,?,'[]','[]',4,'Turkey',?,?,NULL,NULL,NULL,?,NOW())");
    $identity = $db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,NULL,'pending',REPEAT('a',64),?,?)");
    for ($i=1; $i<=100; $i++) {
        $external = (string)(30000+$i);
        $region = $i === 3 ? '' : 'Kemer';
        $category = $i === 1 ? 4 : 5;
        $name = 'ROYAL BLUE SEA '.$i;
        $obsSha = hash('sha256', 'obs'.$i);
        $observation->execute([$obsSha,hash('sha256','search'.$i),'andromeda_catalog',$external,$name,$region,$category,hash('sha256','content'.$i)]);
        $prior = hb3_json(['source'=>['id'=>$external,'name'=>$name]]);
        $identity->execute([$external,hash('sha256',$prior),$prior]);
    }
    $rExternal = '39999'; $rName = 'RUSSIA TEST RESORT'; $rPrior = hb3_json(['source'=>['id'=>$rExternal,'name'=>$rName]]);
    $rObs = $db->prepare("INSERT INTO andromeda_search_hotel_observations VALUES(?,?,?,?,?,'[]','[]',2,'Russia','Sochi',5,NULL,NULL,NULL,?,NOW())");
    $rObs->execute([hash('sha256','r-obs'),hash('sha256','r-search'),'andromeda_catalog',$rExternal,$rName,hash('sha256','r-content')]);
    $identity->execute([$rExternal,hash('sha256',$rPrior),$rPrior]);

    $result = hb3_reconcile($db, 'test-v3');
    if ($result['status'] !== 'completed' || $result['readback_verified'] !== true || $result['supplier_calls'] !== 0) throw new RuntimeException('result '.hb3_json($result));
    // 300 ANEX - manual - existing - staged - excluded - ambiguous - one-token = 294.
    if ($result['anex']['accepted'] !== 294) throw new RuntimeException('anex '.hb3_json($result['anex']));
    // 100 Andromeda - category conflict - tie - missing region = 97; Russia is also blocked.
    if ($result['andromeda']['accepted'] !== 97) throw new RuntimeException('andromeda '.hb3_json($result['andromeda']));
    if ($result['accepted_total'] !== 391 || $result['database_writes'] !== 391) throw new RuntimeException('total '.hb3_json($result));
    if ($result['anex']['pair_excluded'] !== 1 || $result['anex']['blocked_country'] !== 1 || $result['andromeda']['blocked_country'] !== 1 || $result['andromeda']['category_conflict'] !== 1 || $result['andromeda']['ambiguous_margin'] !== 1 || $result['andromeda']['region_missing'] !== 1) throw new RuntimeException('guards '.hb3_json($result));
    if ((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='pending'")->fetchColumn() !== 4) throw new RuntimeException('pending preservation');
    if ((int)$db->query("SELECT COUNT(*) FROM anex_hotel_decisions")->fetchColumn() !== 1) throw new RuntimeException('manual preservation');
    if ((int)$db->query("SELECT COUNT(*) FROM anex_hotel_search_mappings WHERE anex_hotel_id=20999")->fetchColumn() !== 0) throw new RuntimeException('Russia ANEX mapped');
    echo hb3_json(['status'=>'passed','accepted'=>$result['accepted_total'],'anex'=>$result['anex']['accepted'],'andromeda'=>$result['andromeda']['accepted'],'supplier_calls'=>0,'application_database_writes'=>0]), "\n";
} finally {
    foreach ($tables as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
}
