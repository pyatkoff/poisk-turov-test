<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_retained82_writer_v75.php';

$n75=0;
function t75(mixed $got,mixed $expected,string $name):void {global $n75;$n75++;if($got!==$expected)throw new RuntimeException($name.': '.json_encode($got));}
function t75_dir():string {$d=sys_get_temp_dir().'/match-w75-fixture-'.bin2hex(random_bytes(8));if(!mkdir($d,0700))throw new RuntimeException('test_dir');return$d;}
function t75_cleanup(string $dir):void {foreach(glob($dir.'/*')?:[] as $p){if(is_file($p))unlink($p);}rmdir($dir);}
$input=$argv[1]??(__DIR__.'/../input');
$manifest=w75_prepare($input);
t75(count($manifest),82,'real_input_count');
t75(count(array_unique(array_column($manifest,'anex_hotel_id'))),82,'real_native_unique');
foreach($manifest as $id=>$m){t75((int)$m['proof']['row']['tv_hotel_id'],$id,'proof_local');t75((string)$m['proof']['row']['external_hotel_id'],$m['anex_hotel_id'],'proof_native');}
t75(W75_POLICY,'owner_exact_and_strong_20260908','established_policy');
t75(W75_CLASS,'strong_candidate','established_class');
$bad=t75_dir();
foreach(['manifest.json','current97.json','tv-c35.json','tv-r1.json','tv-c0.json'] as $file)copy($input.'/'.$file,$bad.'/'.$file);
file_put_contents($bad.'/current97.json',"\n",FILE_APPEND);
try{w75_prepare($bad);throw new Exception('tamper_accepted');}catch(RuntimeException $e){t75($e->getMessage(),'proof_hash','tampered_current');}
t75_cleanup($bad);
$source=file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_retained82_writer_v75.php');
t75(str_contains($source,'SERIALIZABLE'),true,'serializable');
t75(str_contains($source,'FOR UPDATE'),true,'locking');
t75(str_contains($source,'commit_outcome_unknown_no_replay'),true,'unknown_commit');
t75(preg_match('/[\x27\x22](?:UPDATE|DELETE|REPLACE|ALTER|DROP)\s/i',$source),0,'insert_only_source');
echo "W75_REAL_INPUT_PURE_PASS $n75\n";

// This destructive fixture is restricted to a disposable localhost test database.
$dsn=(string)getenv('MATCH_WRITER_TEST_DSN');
if($dsn===''){echo "W75_MYSQL_NOT_EXECUTED\n";exit(0);}
a74_need($dsn==='mysql:host=127.0.0.1;port=3306;dbname=match_writer_test;charset=utf8mb4','fixture_dsn_only');
$db=new PDO($dsn,'root','fixture-only-75',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
a74_need($db->query('SELECT DATABASE()')->fetchColumn()==='match_writer_test','fixture_database_only');
$tables=['anex_review_pair_exclusions','anex_hotel_decisions','anex_hotel_search_mappings','andromeda_hotel_identities','tour_operator_identity_observations','catalog_hotels'];
foreach($tables as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
$db->exec('CREATE TABLE catalog_hotels (id INT PRIMARY KEY,name VARCHAR(255) NOT NULL,country_id INT,country_name VARCHAR(100),is_active INT) ENGINE=InnoDB');
$db->exec('CREATE TABLE anex_hotel_search_mappings (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(100),scope VARCHAR(32),approval_policy VARCHAR(100),source_row_digest CHAR(64),mapping_digest CHAR(64),enabled INT,KEY(catalog_hotel_id)) ENGINE=InnoDB');
$db->exec('CREATE TABLE anex_hotel_decisions (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(32)) ENGINE=InnoDB');
$db->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
$db->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace VARCHAR(64),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(32),PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB');
$db->exec('CREATE TABLE tour_operator_identity_observations (hotel_id INT PRIMARY KEY,last_seen_at DATETIME) ENGINE=InnoDB');
$hotel=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,1)');
$live=$db->prepare('INSERT INTO tour_operator_identity_observations VALUES(?,UTC_TIMESTAMP())');
foreach($manifest as $id=>$m){$h=$m['proof']['row']['catalog_hotel'];$hotel->execute([$id,$h['name'],$h['country_id'],$h['country_name']]);$live->execute([$id]);}
$head=str_repeat('a',40);$dirs=[];
try {
    // Fail on the LAST row: every earlier INSERT must roll back atomically.
    $db->exec("CREATE TRIGGER fixture_late_failure BEFORE INSERT ON anex_hotel_search_mappings FOR EACH ROW BEGIN IF NEW.anex_hotel_id=45123 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='late_fixture_failure'; END IF; END");
    $dirs[]= $dir=t75_dir();$r=w75_write($db,$manifest,$head,$dir);
    t75($r['state'],'rolled_back_no_writes','late_failure_rollback');
    t75($r['database_writes'],0,'late_failure_claimed_zero');
    t75((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn(),0,'late_failure_actual_zero');
    t75(file_exists($dir.'/commit-attempt.json'),false,'no_commit_after_failed_insert');
    t75($db->inTransaction(),false,'rollback_closed');
    $db->exec('DROP TRIGGER fixture_late_failure');

    // Full 82-row batch: source/target guards, policy and canonical post-COMMIT readback.
    $dirs[]= $dir=t75_dir();$r=w75_write($db,$manifest,$head,$dir);
    t75($r['state'],'committed_readback_verified','full_success');
    t75($r['inserted'],82,'full_inserted');t75($r['database_writes'],82,'full_write_count');
    t75($r['registry_readback_verified'],true,'canonical_postcommit');
    t75($r['preexisting_mappings_unchanged'],true,'preexisting_unchanged');
    t75(count($r['rows']),82,'full_readback_rows');
    t75((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn(),82,'actual_full_count');
    foreach($r['rows'] as $row){t75($row['match_class'],W75_CLASS,'row_class');t75($row['approval_policy'],W75_POLICY,'row_policy');}
    t75(file_exists($dir.'/commit-attempt.json'),true,'commit_record');

    // A later, separately reserved batch sees existing matches; it must not duplicate them.
    $dirs[]= $dir=t75_dir();$r=w75_write($db,$manifest,$head,$dir);
    t75($r['state'],'completed_no_new_writes','already_present');
    t75(count($r['already_effective']),82,'already82');
    t75($r['database_writes'],0,'already_no_writes');
    t75((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn(),82,'no_duplicate');

    // Four independent conflicts must not abort the other 78 candidates.
    $db->exec('DELETE FROM anex_hotel_search_mappings');
    $ids=array_keys($manifest);$rows=array_values($manifest);
    $q=$db->prepare("INSERT INTO anex_hotel_decisions VALUES(?,NULL,'rejected')");$q->execute([$rows[0]['anex_hotel_id']]);
    $q=$db->prepare('INSERT INTO anex_review_pair_exclusions VALUES(?,?)');$q->execute([$rows[1]['anex_hotel_id'],$ids[1]]);
    $q=$db->prepare('UPDATE catalog_hotels SET is_active=0 WHERE id=?');$q->execute([$ids[2]]);
    $q=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('operator_5',?,999999,'accepted')");$q->execute([$rows[3]['anex_hotel_id']]);
    $dirs[]= $dir=t75_dir();$r=w75_write($db,$manifest,$head,$dir);
    t75($r['state'],'committed_readback_verified','mixed_success');
    t75($r['inserted'],78,'mixed78');t75(count($r['held']),4,'held4');
    t75((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn(),78,'actual78');
    t75((int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions')->fetchColumn(),1,'manual_preserved');
    t75((int)$db->query('SELECT COUNT(*) FROM anex_review_pair_exclusions')->fetchColumn(),1,'exclusion_preserved');
    t75((int)$db->query('SELECT COUNT(*) FROM andromeda_hotel_identities')->fetchColumn(),1,'op5_preserved');
    $held=array_column($r['held'],'local_hotel_id');sort($held);$expected=array_slice($ids,0,4);sort($expected);t75($held,$expected,'exact_held_membership');
    echo "W75_MYSQL_TRANSACTION_PASS $n75\n";
} finally {if($db->inTransaction())$db->rollBack();foreach($dirs as $dir)t75_cleanup($dir);}
