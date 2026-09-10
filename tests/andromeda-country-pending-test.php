<?php
// Execute the exact 403-row candidate promotion on disposable MySQL, with all saved country competitors.
define('CE_LIBRARY_ONLY',true);
require __DIR__.'/../scripts/diagnostics/andromeda_country_expansion.php';
require __DIR__.'/../scripts/diagnostics/andromeda_country_pending.php';
require __DIR__.'/../app/integrations/anex-search-mapping-registry.php';
$dsn=getenv('ANEX_LINK_TEST_DSN');if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4')throw new RuntimeException('disposable_mysql_only');
$bundle=json_decode(file_get_contents(getenv('ANDROMEDA_PENDING_BUNDLE')),true,64,JSON_THROW_ON_ERROR);
$request=json_decode(file_get_contents(getenv('ANDROMEDA_PENDING_REQUEST')),true,64,JSON_THROW_ON_ERROR)['request'];
$checks=0;
function ck($v,$why){global $checks;if(!$v)throw new RuntimeException($why);++$checks;}
function rejects(callable $f){$failed=false;try{$f();}catch(Throwable $e){$failed=true;}ck($failed,'guard must reject');}
ck(ce_hash(ce_pending_request($bundle))===CE_PENDING_REQUEST,'real full catalogue reproduces exact reviewed request');
ck($request['count']===403,'403 reviewed identities');
ck(ce_pending_names('SHERATON (EX.SOMETHING ELSE)')===['sheraton','else something'],'explicit former name supported');
ck(ce_pending_names('SUN (ANNEX)')===['annex sun'],'ordinary parentheses not removed');
ck(ce_pending_qualifiers('SUN ANNEX')!==ce_pending_qualifiers('SUN'),'annex distinction');
ck(ce_pending_qualifiers('SUN ADULTS ONLY 18+')!==ce_pending_qualifiers('SUN'),'adult restriction distinction');
ck(ce_pending_place('Северный Мале Атолл')!==ce_pending_place('Южный Мале Атолл'),'north south retained');
ck(ce_pending_place('Пхи Пхи о.')===ce_pending_place('Пхи-Пхи'),'explicit island suffix and separators');
ck(ce_pending_place('Хойан')===ce_pending_place('Хой Ан'),'same letters only');
ck(ce_pending_place('Халонг')!==ce_pending_place('Ханой'),'different city remains different');
$bad=$request;$bad['count']=404;rejects(fn()=>ce_pending_import(new PDO($dsn,'root',''),$bad,$bundle));
$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tables=['anex_review_pair_exclusions','anex_hotel_decisions','anex_hotel_search_mappings','andromeda_hotel_identities','hotel_aliases','catalog_hotels'];
try{
 foreach($tables as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
 $db->exec('CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name VARCHAR(255),country_id INT,country_name VARCHAR(255),region_name VARCHAR(255),subregion_name VARCHAR(255),category INT,is_active INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE hotel_aliases(hotel_id INT,alias VARCHAR(255),KEY(hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,enabled INT,approval_policy VARCHAR(64),scope VARCHAR(16),match_class VARCHAR(32)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,decision_status VARCHAR(16)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT) ENGINE=InnoDB');
 $hInsert=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,?,?,?,?)');$aInsert=$db->prepare('INSERT INTO hotel_aliases VALUES(?,?)');$original=[];$totalLocal=0;$db->beginTransaction();
 foreach($bundle as $c=>$data){foreach($data['capture']['local']['hotels'] as $h){$hInsert->execute([$h['id'],$h['name'],$h['country_id'],$h['country_name'],$h['region_name'],$h['subregion_name'],$h['category'],$h['is_active']]);++$totalLocal;}
  foreach($data['capture']['local']['aliases'] as $a)$aInsert->execute([$a['hotel_id'],$a['alias']]);
  foreach($data['plan']['rows'] as $r)$original[$r['external_hotel_id']]=$r;
 }$db->commit();ck($totalLocal===19229,'all saved local competitors loaded');
 $selected=[];foreach($request['countries'] as $country)foreach($country['rows'] as $r)$selected[$r['external_hotel_id']]=[$r,$country];
 $insert=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,NULL,'pending',?,?,?)");
 function seed_pending($db,$selected,$original,$insert){$db->beginTransaction();foreach($selected as $id=>[$r,$country]){$db->prepare("DELETE FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?")->execute([(string)$id]);$insert->execute([(string)$id,$country['catalog_sha256'],$r['expected_evidence_sha256'],$original[$id]['evidence_json']]);}$db->commit();}
 seed_pending($db,$selected,$original,$insert);$first=array_key_first($selected);$last=array_key_last($selected);$firstRow=$selected[$first][0];$lastRow=$selected[$last][0];
 $db->prepare("INSERT INTO andromeda_hotel_identities VALUES('other_namespace',?,NULL,'conflict',REPEAT('a',64),REPEAT('b',64),'do not change')")->execute([(string)$first]);
 $db->exec("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','999999991',NULL,'conflict',REPEAT('a',64),REPEAT('b',64),'protected conflict')");
 $before=ce_existing($db,false);$result=ce_pending_import($db,$request,$bundle);
 ck($result['updated']===403&&count($result['rows'])===403,'all pending delta written');ck($result['readback_verified']&&$result['other_identities_unchanged'],'commit/readback/preservation');ck($result['other_count']===2,'two protected fixtures preserved');
 foreach($result['rows'] as $r)ck($r['decision_status']==='accepted','every readback accepted');
 rejects(fn()=>ce_pending_import($db,$request,$bundle));ck(count(ce_existing($db,false))===405,'repeat never inserts');
 seed_pending($db,$selected,$original,$insert);
 $before=ce_existing($db,false);
 foreach(['evidence_sha256'=>str_repeat('c',64),'decision_status'=>'conflict'] as $column=>$value){
  $db->prepare("UPDATE andromeda_hotel_identities SET $column=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?")->execute([$value,(string)$last]);
  rejects(fn()=>ce_pending_import($db,$request,$bundle));$verify=$db->prepare("SELECT decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");$verify->execute([(string)$first]);ck($verify->fetchColumn()==='pending','stale last row leaves first untouched');seed_pending($db,$selected,$original,$insert);
 }
 $target=$firstRow['target'];
 foreach(['is_active=0',"region_name='DIFFERENT CITY',subregion_name='DIFFERENT TOWN'",'category=0'] as $change){
  $db->exec('UPDATE catalog_hotels SET '.$change.' WHERE id='.(int)$target['id']);rejects(fn()=>ce_pending_import($db,$request,$bundle));
  $db->prepare('UPDATE catalog_hotels SET is_active=1,region_name=?,subregion_name=?,category=? WHERE id=?')->execute([$target['region_name'],$target['subregion_name'],$target['category'],$target['id']]);
 }
 $other=$db->query('SELECT id FROM catalog_hotels WHERE country_id='.(int)$target['country_id'].' AND id<>'.(int)$target['id'].' ORDER BY id LIMIT 1')->fetchColumn();
 $aInsert->execute([$other,$firstRow['match_keys'][0]]);rejects(fn()=>ce_pending_import($db,$request,$bundle));$db->prepare('DELETE FROM hotel_aliases WHERE hotel_id=? AND alias=?')->execute([$other,$firstRow['match_keys'][0]]);
 $db->exec("CREATE TRIGGER pending_late_error BEFORE UPDATE ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.external_hotel_id='".(string)$last."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late failure'; END IF; END");
 rejects(fn()=>ce_pending_import($db,$request,$bundle));ck(ce_existing($db,false)===$before,'late SQL failure restores all403 original hashes');$db->exec('DROP TRIGGER pending_late_error');
 echo json_encode(['status'=>'passed','checks'=>$checks,'actual_saved_pending_rows'=>403,'full_local_hotels'=>$totalLocal,'application_database_writes'=>0,'supplier_calls'=>0]),"\n";
}finally{if($db->inTransaction())$db->rollBack();foreach($tables as $table)$db->exec('DROP TABLE IF EXISTS '.$table);}
