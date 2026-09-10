<?php
// Real SQL on disposable loopback MySQL; source fixture is not application DB.
define('CE_LIBRARY_ONLY',true);define('BR_LIBRARY_ONLY',true);
require __DIR__.'/../scripts/diagnostics/andromeda_country_expansion.php';
require __DIR__.'/../app/integrations/anex-search-mapping-registry.php';
require __DIR__.'/../scripts/diagnostics/andromeda_saved_bridge.php';
$dsn=getenv('ANEX_LINK_TEST_DSN');if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4')throw new RuntimeException('isolated_database_only');
$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$checks=0;
function ok($v,$m){global $checks;if(!$v)throw new RuntimeException($m);++$checks;}
function denied(callable $f){$fail=false;try{$f();}catch(Throwable $e){$fail=true;}ok($fail,'guard must reject');}
$fixture=json_decode(file_get_contents(getenv('BRIDGE_FIXTURE')),true,64,JSON_THROW_ON_ERROR);$p=$fixture['payload'];$data=$fixture['data'];$plan=$fixture['plan'];br_payload($p);
$tables=['anex_review_pair_exclusions','anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','andromeda_hotel_identities','hotel_aliases','catalog_hotels'];
function reset_pending($db,$p,$plan){
 $db->exec('DELETE FROM andromeda_hotel_identities');$s=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,NULL,'pending',?,?,?)");
 foreach($plan['rows'] as $r)$s->execute([$r['external_hotel_id'],$p['catalog_sha256'],hash('sha256',$r['evidence_json']),$r['evidence_json']]);
 $db->exec("INSERT INTO andromeda_hotel_identities VALUES('other_namespace','555',1,'accepted',REPEAT('a',64),REPEAT('b',64),'protected other namespace'),('andromeda_catalog','888888888',NULL,'conflict',REPEAT('a',64),REPEAT('b',64),'protected conflict')");
}
try{
 foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
 $db->exec('CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name VARCHAR(255),country_id INT,country_name VARCHAR(255),region_name VARCHAR(255),subregion_name VARCHAR(255),category VARCHAR(20),is_active INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE hotel_aliases(hotel_id INT,alias VARCHAR(255),KEY(hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,enabled INT,approval_policy VARCHAR(64),scope VARCHAR(16),match_class VARCHAR(32)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,decision_status VARCHAR(16)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_search_hotel_observations(anex_hotel_id INT PRIMARY KEY) ENGINE=InnoDB');
 $h=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,?,?,?,1)');foreach($data['local']['hotels'] as $r)$h->execute([$r['id'],$r['name'],8,'Мальдивы',$r['region_name'],$r['subregion_name'],$r['category']]);
 $a=$db->prepare('INSERT INTO hotel_aliases VALUES(?,?)');foreach($data['local']['aliases'] as $r)$a->execute([$r['hotel_id'],$r['alias']]);
 $a=$db->prepare("INSERT IGNORE INTO anex_hotel_search_mappings VALUES(?,?,1,'owner_exact_and_strong_20260908','preview','exact')");foreach($p['rows'] as $r)foreach($r['anex_bridges'] as $b){$a->execute([$b['id'],$r['local_hotel_id']]);$db->exec('INSERT IGNORE INTO anex_search_hotel_observations VALUES('.(int)$b['id'].')');}
 reset_pending($db,$p,$plan);$result=br_import($db,$p,$data,$plan);
 ok($result['updated']===61&&$result['readback_verified']===true,'actual61 promotions read after COMMIT');
 ok($result['preserved_count']===2&&$result['other_identities_unchanged']===true,'other identities preserved');
 ok($result['live_coverage']['all_three']===61,'actual registry intersection61');
 denied(fn()=>br_import($db,$p,$data,$plan));ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted'")->fetchColumn()===62,'successful batch not rewritten');
 reset_pending($db,$p,$plan);$last=end($p['rows']);$db->exec("UPDATE andromeda_hotel_identities SET evidence_sha256=REPEAT('c',64) WHERE external_hotel_id='".$last['external_hotel_id']."'");denied(fn()=>br_import($db,$p,$data,$plan));ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='pending'")->fetchColumn()===61,'stale last row leaves all pending');
 reset_pending($db,$p,$plan);$first=$p['rows'][0];$aid=(int)$first['anex_bridges'][0]['id'];$lid=(int)$first['local_hotel_id'];
 $db->exec("INSERT INTO anex_hotel_decisions VALUES($aid,$lid,'rejected')");denied(fn()=>br_import($db,$p,$data,$plan));$db->exec('DELETE FROM anex_hotel_decisions');
 $db->exec("INSERT INTO anex_review_pair_exclusions VALUES($aid,$lid)");denied(fn()=>br_import($db,$p,$data,$plan));$db->exec('DELETE FROM anex_review_pair_exclusions');
 foreach(['is_active=0',"country_id=4","subregion_name='Другой атолл'"] as $change){$db->exec('UPDATE catalog_hotels SET '.$change.' WHERE id='.$lid);denied(fn()=>br_import($db,$p,$data,$plan));$db->exec('UPDATE catalog_hotels SET is_active=1,country_id=8,subregion_name=NULL WHERE id='.$lid);}
 $db->exec("INSERT INTO catalog_hotels VALUES(2000000000,'Competitor',8,'Мальдивы','Мальдивы',NULL,4,1)");$a=$db->prepare('INSERT INTO hotel_aliases VALUES(2000000000,?)');$a->execute([$first['source']['name']]);denied(fn()=>br_import($db,$p,$data,$plan));$db->exec('DELETE FROM hotel_aliases WHERE hotel_id=2000000000');$db->exec('DELETE FROM catalog_hotels WHERE id=2000000000');
 $bad=$p;$bad['rows'][0]['anex_bridges'][0]['town']='Иной атолл';denied(fn()=>br_import($db,$bad,$data,$plan));
 $db->exec("CREATE TRIGGER reject_last_bridge BEFORE UPDATE ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.external_hotel_id='".$last['external_hotel_id']."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late failure'; END IF; END");
 denied(fn()=>br_import($db,$p,$data,$plan));ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='pending'")->fetchColumn()===61,'late error rolls back60updates');$db->exec('DROP TRIGGER reject_last_bridge');
 echo json_encode(['status'=>'passed','checks'=>$checks,'actual_saved_identities_tested'=>61,'application_database_writes'=>0,'supplier_calls'=>0]),"\n";
}finally{if($db->inTransaction())$db->rollBack();foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);}
