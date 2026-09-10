<?php
define('HB_LIBRARY_ONLY',true);require __DIR__.'/../scripts/diagnostics/hotel_observed_bulk_reconcile.php';
$dsn=getenv('ANEX_LINK_TEST_DSN');if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4')throw new RuntimeException('isolated only');$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tables=['andromeda_search_hotel_observations','andromeda_hotel_identities','anex_review_pair_exclusions','anex_hotel_decisions','anex_hotel_search_mappings','anex_search_hotel_observations','hotel_aliases','catalog_hotels'];foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
try{
$db->exec("CREATE TABLE catalog_hotels(id INT PRIMARY KEY,country_id INT,country_name VARCHAR(80),name VARCHAR(255),normalized_name VARCHAR(255),category INT NULL,is_active INT,ENGINE_DUMMY INT NULL) ENGINE=InnoDB");
$db->exec("CREATE TABLE hotel_aliases(id INT AUTO_INCREMENT PRIMARY KEY,hotel_id INT,alias VARCHAR(255),normalized_alias VARCHAR(255)) ENGINE=InnoDB");
$db->exec("CREATE TABLE anex_search_hotel_observations(anex_hotel_id INT PRIMARY KEY,hotel_name VARCHAR(300),country_id INT,anex_country_id INT,last_catalog_hotel_id BIGINT NULL,first_seen_utc DATETIME,last_seen_utc DATETIME,search_count BIGINT,last_checkin_from DATE,last_checkin_to DATE,last_source_sha CHAR(40)) ENGINE=InnoDB");
$db->exec("CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(32),scope VARCHAR(16),approval_policy VARCHAR(64),source_row_digest CHAR(64),mapping_digest CHAR(64),enabled INT) ENGINE=InnoDB");
$db->exec("CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(16)) ENGINE=InnoDB");
$db->exec("CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB");
$db->exec("CREATE TABLE andromeda_search_hotel_observations(observation_sha256 CHAR(64) PRIMARY KEY,search_evidence_sha256 CHAR(64),supplier_namespace VARCHAR(160),external_hotel_id VARCHAR(128),hotel_name VARCHAR(300),operator_refs_json TEXT,operator_names_json TEXT,country_id INT,country_name VARCHAR(160),region_name VARCHAR(180),category INT NULL,description_text TEXT NULL,image_url VARCHAR(2048) NULL,hotel_url VARCHAR(2048) NULL,content_sha256 CHAR(64),observed_at_utc DATETIME) ENGINE=InnoDB");
$db->exec("CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(128),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB");
$h=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?, ?,?,?,1,NULL)');$ao=$db->prepare("INSERT INTO anex_search_hotel_observations VALUES(?,?,?,5,NULL,NOW(),NOW(),10,'2026-09-20','2026-09-20',NULL)");$io=$db->prepare("INSERT INTO andromeda_search_hotel_observations VALUES(?,?,?,?,?,'[]','[]',?,?,'',?,NULL,NULL,NULL,?,NOW())");$ii=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,NULL,'pending',REPEAT('a',64),?,?)");
for($i=1;$i<=600;$i++){ $id=10000+$i;$name='UNIQUEPLACE'.$i;$h->execute([$id,4,'Turkey',$name,strtolower($name),5]);if($i<=350)$ao->execute([20000+$i,$name,4]);if($i<=250){$ext=(string)(30000+$i);$obs=hash('sha256','o'.$i);$io->execute([$obs,hash('sha256','s'.$i),'andromeda_catalog',$ext,$name,4,'Turkey',5,hash('sha256','c'.$i)]);$ev=hb_json(['source'=>['id'=>$ext,'name'=>$name]]);$ii->execute([$ext,hash('sha256',$ev),$ev]);}}
// protect one ANEX manual and one pair exclusion; make one exact name ambiguous.
$db->exec("INSERT INTO anex_hotel_decisions VALUES(20001,10001,'rejected')");$db->exec("INSERT INTO anex_review_pair_exclusions VALUES(20002,10002)");$h->execute([20000,4,'Turkey','UNIQUEPLACE3','uniqueplace3',5]);
$result=hb_reconcile($db,'test-bulk');
if($result['status']!=='completed'||!$result['readback_verified']||$result['supplier_calls']!==0)throw new RuntimeException('result');
if($result['anex']['accepted']!==347)throw new RuntimeException('anex count '.hb_json($result['anex']));
if($result['andromeda']['accepted']!==249)throw new RuntimeException('andromeda count '.hb_json($result['andromeda']));
if($result['accepted_total']!==596)throw new RuntimeException('total');
if((int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions')->fetchColumn()!==1)throw new RuntimeException('manual changed');
if((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='pending'")->fetchColumn()!==1)throw new RuntimeException('pending changed');
echo hb_json(['status'=>'passed','source_observations'=>600,'accepted'=>$result['accepted_total'],'supplier_calls'=>0,'application_database_writes'=>0]),"\n";
}finally{foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);}
