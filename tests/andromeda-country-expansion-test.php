<?php
// Actual country-plan/SQL execution on disposable loopback MySQL only.
define('CE_LIBRARY_ONLY',true);
require __DIR__.'/../scripts/diagnostics/andromeda_country_expansion.php';
require __DIR__.'/../app/integrations/anex-search-mapping-registry.php';
$dsn=getenv('ANEX_LINK_TEST_DSN');if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4')throw new RuntimeException('isolated_database_only');
$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$checks=0;
function check($value,$message){global $checks;if(!$value)throw new RuntimeException($message);++$checks;}
function refused(callable $f){$failed=false;try{$f();}catch(Throwable $e){$failed=true;}check($failed,'expected guard failure');}
$tables=['anex_review_pair_exclusions','anex_hotel_decisions','anex_hotel_search_mappings','andromeda_hotel_identities','hotel_aliases','catalog_hotels'];
try{
 foreach($tables as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
 $db->exec('CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name VARCHAR(255),country_id INT,country_name VARCHAR(255),region_name VARCHAR(255),subregion_name VARCHAR(255),category INT,is_active INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE hotel_aliases(hotel_id INT,alias VARCHAR(255),KEY(hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,enabled INT,approval_policy VARCHAR(64),scope VARCHAR(16),match_class VARCHAR(32)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,decision_status VARCHAR(16)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT) ENGINE=InnoDB');
 $db->exec("INSERT INTO anex_hotel_search_mappings VALUES(800001,10001,1,'owner_exact_and_strong_20260908','preview','exact')");
 $db->exec("INSERT INTO anex_hotel_decisions VALUES(800002,10002,'accepted')");
 $insert=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,77,?,?,?,?,1)');$sources=[];
 $db->beginTransaction();for($i=1;$i<=1000;$i++){$name='PROPERTY '.str_pad((string)$i,4,'0',STR_PAD_LEFT);$insert->execute([10000+$i,$name.' HOTEL','Куба','Варадеро','Варадеро',4]);$sources[]=['id'=>90000+$i,'name'=>$name,'lName'=>$name,'stateKey'=>53,'state'=>'Куба','townKey'=>7,'town'=>'Варадеро'];}$db->commit();
 $data=['country_id'=>77,'supplier_country_id'=>53,'catalog'=>['action'=>'all','params'=>['STATEINC'=>53],'payload'=>['HOTELS'=>$sources,'TOWNTO'=>[
   ['id'=>7,'name'=>'Варадеро','state'=>53,'Region'=>1,'RegionName'=>'Матансас'],
   ['id'=>8,'name'=>'Гавана','state'=>53,'Region'=>2,'RegionName'=>'Гавана']]]],'local'=>ce_local($db,77)];
 $plan=ce_plan($data,$data['local']);check(ce_summary($plan)['counts']['accepted']===1000,'1000 complete unique-country candidates');
 check(ce_name('SUN HOTEL')!==ce_name('SUN ANNEX'),'real annex qualifier retained');
 check(ce_name('SUN HOTEL (EX. OLD)')===ce_name('SUN'),'former suffix only');
 check(ce_name('Boutique Saint Sophia')===ce_name('SAINT SOPHIA BOUTIQUE'),'whole words reordered');
 check(ce_name('PASA BEY')!==ce_name('PASABEY'),'no joined-word fuzzy match');
 foreach(['russia','abkhazia','turkey','egypt','../cuba'] as $country)refused(fn()=>ce_run(['operation_id'=>CE_OPERATION,'country'=>$country,'phase'=>'capture']));
 $bad=$data;$bad['local']['complete']=false;refused(fn()=>ce_plan($bad,$bad['local']));
 $bad=$data;$bad['local']['aliases'][]=['hotel_id'=>999999,'alias'=>'PROPERTY'];refused(fn()=>ce_plan($bad,$bad['local']));
 $bad=$data;$bad['catalog']['payload']['HOTELS'][0]['stateKey']=999;check(ce_plan($bad,$bad['local'])['rows'][0]['decision_status']==='conflict','foreign supplier country not accepted');
 $bad=$data;$bad['catalog']['payload']['HOTELS'][0]['town']='Гавана';$bad['catalog']['payload']['HOTELS'][0]['townKey']=8;check(ce_plan($bad,$bad['local'])['rows'][0]['decision_status']==='pending','wrong parent region not accepted');
 $bad=$data;$bad['catalog']['payload']['TOWNTO']=[];check(ce_plan($bad,$bad['local'])['rows'][0]['decision_status']==='pending','missing official geography not accepted');
 $bad=$data;$bad['local']['aliases'][]=['hotel_id'=>10002,'alias'=>'PROPERTY 0001'];check(ce_plan($bad,$bad['local'])['rows'][0]['decision_status']==='pending','full-country alias competition retained');
 $db->exec("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','90001',10001,'accepted',REPEAT('a',64),REPEAT('b',64),'owner decision'),('other_namespace','90002',10002,'conflict',REPEAT('a',64),REPEAT('b',64),'protected neighbor')");
 $before=ce_existing($db,false);$result=ce_import($db,$data,$plan);
 check($result['inserted']===999&&$result['new_counts']['accepted']===999,'999 new plus one preserved collision');
 check($result['readback_verified']===true&&count($result['rows'])===999,'every inserted row read after COMMIT');
 foreach($before as $key=>$hash)check(ce_existing($db,false)[$key]===$hash,'old namespace/decision hash preserved');
 check($result['live_coverage']['all_three']===2,'fresh actual ANEX intersection');
 $repeat=ce_import($db,$data,$plan);check($repeat['inserted']===0,'low-level insert-only preserves all previous rows');
 $db->exec("DELETE FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id<>'90001'");
 foreach(['is_active=0',"name='SOME OTHER PROPERTY'","region_name='Гавана',subregion_name='Гавана'"] as $change){
  $db->exec('UPDATE catalog_hotels SET '.$change.' WHERE id=11000');refused(fn()=>ce_import($db,$data,$plan));check(count(ce_existing($db,false))===2,'current-target guard leaves zero inserts');
  $db->exec("UPDATE catalog_hotels SET is_active=1,name='PROPERTY 1000 HOTEL',region_name='Варадеро',subregion_name='Варадеро' WHERE id=11000");
 }
 $db->exec("INSERT INTO hotel_aliases VALUES(10002,'PROPERTY 0001')");refused(fn()=>ce_import($db,$data,$plan));$db->exec('DELETE FROM hotel_aliases');
 $db->exec("CREATE TRIGGER reject_last_country_row BEFORE INSERT ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.external_hotel_id='91000' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late insert failure'; END IF; END");
 refused(fn()=>ce_import($db,$data,$plan));check(count(ce_existing($db,false))===2,'late SQL failure rolls back earlier 998 inserts');$db->exec('DROP TRIGGER reject_last_country_row');
 $path=sys_get_temp_dir().'/country-receipt-'.bin2hex(random_bytes(8));ce_save($path,['state'=>'reserved']);refused(fn()=>ce_save($path,['state'=>'reset']));check(ce_read($path)['state']==='reserved','reservation never overwritten');unlink($path);
 echo json_encode(['status'=>'passed','checks'=>$checks,'source_rows_tested'=>1000,'application_database_writes'=>0,'supplier_calls'=>0]),"\n";
}finally{if($db->inTransaction())$db->rollBack();foreach($tables as $table)$db->exec('DROP TABLE IF EXISTS '.$table);}
