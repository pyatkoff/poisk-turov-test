<?php
define('FC_LIBRARY_ONLY', true);
require __DIR__.'/../scripts/diagnostics/hotel_full_catalog_reconcile.php';
define('FD_LIBRARY_ONLY', true);
require __DIR__.'/../scripts/diagnostics/hotel_full_catalog_delta_reconcile.php';

$dsn = getenv('ANEX_LINK_TEST_DSN');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4') throw new RuntimeException('isolated_only');
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = ['anex_search_hotel_observations','andromeda_hotel_identities','anex_review_pair_exclusions','anex_hotel_decisions','anex_hotel_search_mappings','anex_hotels','hotel_aliases','catalog_hotel_details','catalog_hotels','catalog_countries'];
foreach ($tables as $t) $db->exec('DROP TABLE IF EXISTS '.$t);
function chk($v, $m) { if (!$v) throw new RuntimeException($m); }
try {
    $db->exec("CREATE TABLE catalog_countries(id INT PRIMARY KEY,name VARCHAR(100)) ENGINE=InnoDB");
    $q=$db->prepare('INSERT INTO catalog_countries VALUES(?,?)'); foreach(FC_COUNTRIES as $id=>$name) $q->execute([$id,$name]);
    $db->exec("CREATE TABLE catalog_hotels(id INT PRIMARY KEY,country_id INT,country_name VARCHAR(100),name VARCHAR(255),normalized_name VARCHAR(255),region_name VARCHAR(150),subregion_name VARCHAR(150),category INT NULL,latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,is_active INT) ENGINE=InnoDB");
    $db->exec("CREATE TABLE catalog_hotel_details(hotel_id INT PRIMARY KEY,latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL) ENGINE=InnoDB");
    $db->exec("CREATE TABLE hotel_aliases(id INT AUTO_INCREMENT PRIMARY KEY,hotel_id INT,alias VARCHAR(255),normalized_alias VARCHAR(255)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_hotels(anex_hotel_id INT PRIMARY KEY,source_fingerprint CHAR(24),xml_name VARCHAR(255),xml_alternate_name VARCHAR(255),xml_town_id INT NULL,api_name VARCHAR(255),api_country VARCHAR(255),api_region VARCHAR(255),api_town VARCHAR(255),api_town_id INT NULL,api_address VARCHAR(1024),latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,supplier_record_json MEDIUMTEXT,checked_at DATETIME,first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_search_hotel_observations(anex_hotel_id INT PRIMARY KEY,hotel_name VARCHAR(300),country_id INT,anex_country_id INT,last_catalog_hotel_id BIGINT NULL,first_seen_utc DATETIME,last_seen_utc DATETIME,search_count BIGINT,last_checkin_from DATE,last_checkin_to DATE,last_source_sha CHAR(40)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(32),scope VARCHAR(16),approval_policy VARCHAR(64),source_row_digest CHAR(64),mapping_digest CHAR(64),enabled INT) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(16)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB");

    $h=$db->prepare("INSERT INTO catalog_hotels VALUES(?,4,'Турция',?,?,?,?,?,?,?,1)");
    for($i=1;$i<=320;$i++) { $n='DELTA GRAND PALACE COAST '.str_pad((string)$i,3,'0',STR_PAD_LEFT); $h->execute([10000+$i,$n,strtolower($n),'Аланья','Аланья',5,36.5+$i/100000,31.5+$i/100000]); }

    $st=$db->prepare("INSERT INTO anex_hotels(anex_hotel_id,source_fingerprint,xml_name,xml_alternate_name,api_name,api_country,api_region,api_town,api_address,latitude,longitude,supplier_record_json,checked_at) VALUES(?,REPEAT('a',24),?,?,?,'Turkey','Аланья','Аланья','Street',?,?,'{}',NOW())");
    for($i=1;$i<=80;$i++) { $base='DELTA GRAND PALACE COAST '.str_pad((string)$i,3,'0',STR_PAD_LEFT); $src=$base.' BEACH'; $st->execute([20000+$i,$src,'',$src,36.5+$i/100000,31.5+$i/100000]); }
    $st->execute([29990,'DELTA GRAND PALACE COAST 081 BEACH','','DELTA GRAND PALACE COAST 081 BEACH',1,1]);

    $db->exec("INSERT INTO anex_hotel_search_mappings VALUES(20001,10001,'strong_candidate','preview','owner_exact_and_strong_20260908',REPEAT('d',64),REPEAT('e',64),1)");
    $db->exec("INSERT INTO anex_hotel_decisions VALUES(20002,10002,'rejected')");
    $db->exec("INSERT INTO anex_review_pair_exclusions VALUES(20003,10003)");

    $ai=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,?,'accepted',REPEAT('c',64),?,?)");
    $obs=$db->prepare("INSERT INTO anex_search_hotel_observations VALUES(?,?,4,5,NULL,NOW(),NOW(),10,'2026-09-20','2026-09-20',NULL)");
    for($i=81;$i<=120;$i++) { $name='DELTA GRAND PALACE COAST '.str_pad((string)$i,3,'0',STR_PAD_LEFT); $ev=fc_json(['source'=>['id'=>(string)(50000+$i),'name'=>$name.' RESORT','starKey'=>5]]); $ai->execute([(string)(50000+$i),10000+$i,hash('sha256',$ev),$ev]); $obs->execute([30000+$i,$name.' HOTEL']); }

    $pi=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,NULL,'pending',REPEAT('c',64),?,?)");
    for($i=121;$i<=180;$i++) { $name='DELTA GRAND PALACE COAST '.str_pad((string)$i,3,'0',STR_PAD_LEFT); $src=$name.' BEACH'; $ev=fc_json(['source'=>['id'=>(string)(60000+$i),'name'=>$src,'starKey'=>5,'town'=>'Аланья'],'candidate_ids'=>[10000+$i],'geography'=>['town'=>'Аланья','parent'=>'Аланья']]); $pi->execute([(string)(60000+$i),hash('sha256',$ev),$ev]); }
    $ev=fc_json(['source'=>['id'=>'69991','name'=>'DELTA GRAND PALACE COAST 181 BEACH','starKey'=>4,'town'=>'Аланья'],'candidate_ids'=>[10181],'geography'=>['town'=>'Аланья']]); $pi->execute(['69991',hash('sha256',$ev),$ev]);
    $ev=fc_json(['source'=>['id'=>'69992','name'=>'DELTA GRAND PALACE COAST 182 BEACH','starKey'=>5,'town'=>'Аланья'],'candidate_ids'=>[10183],'geography'=>['town'=>'Аланья']]); $pi->execute(['69992',hash('sha256',$ev),$ev]);

    $db->exec("CREATE TRIGGER reject_delta BEFORE INSERT ON anex_hotel_search_mappings FOR EACH ROW BEGIN IF NEW.anex_hotel_id=20050 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='late delta failure'; END IF; END");
    $fail=fd_reconcile($db,'delta-rollback'); chk($fail['status']==='failed_rolled_back','rollback status'); chk((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===1,'ANEX rollback'); chk((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='pending'")->fetchColumn()===62,'Andromeda rollback');
    $db->exec('DROP TRIGGER reject_delta');

    $r=fd_reconcile($db,'delta-success');
    chk($r['status']==='completed' && $r['readback_verified']===true,'completed'); chk($r['supplier_calls']===0,'supplier calls');
    chk($r['anex_staging']['accepted']===76,'staging count '.fc_json($r['anex_staging'])); chk($r['anex_observed_no_staging']['accepted']===40,'observed bridge count'); chk($r['andromeda']['accepted']===60,'Andromeda count '.fc_json($r['andromeda'])); chk($r['accepted_total']===176,'total '.$r['accepted_total']);
    chk($r['anex_staging']['geo_conflict']===1,'geo conflict'); chk((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE external_hotel_id IN ('69991','69992') AND decision_status='pending'")->fetchColumn()===2,'pending guards');
    echo fc_json(['status'=>'passed','writes'=>$r['accepted_total'],'staging'=>$r['anex_staging']['accepted'],'observed'=>$r['anex_observed_no_staging']['accepted'],'andromeda'=>$r['andromeda']['accepted'],'supplier_calls'=>0]),"\n";
} finally { if($db->inTransaction()) $db->rollBack(); foreach($tables as $t) $db->exec('DROP TABLE IF EXISTS '.$t); }
