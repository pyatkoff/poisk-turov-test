<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_live30_target_catalog_v1.php';
$checks=0;
function tc30check(bool $ok,string $name): void {global $checks;++$checks;if(!$ok)throw new RuntimeException('target_catalog_test:'.$name);}
$hotel=['id'=>420,'name'=>'SHAMS ALAM RESORT','country_id'=>5,'country_name'=>'Египет','region_name'=>'Марса Алам',
    'subregion_name'=>null,'category'=>4,'is_active'=>1,'latitude'=>24.6907006,'longitude'=>35.0835745,'private_history'=>'never exported'];
$out=tc30_row($hotel,['9501','9501'],true,false);
tc30check(!isset($out['private_history'])&&$out['accepted_samo_ids']===['9501']&&$out['manual_hold']===true,'projection_and_occupancy');
foreach([['latitude'=>91],['longitude'=>-181],['is_active'=>0],['name'=>'https://secret.example/'],['id'=>'bad']] as $change){
    $held=false;try{tc30_row(array_replace($hotel,$change),[],false,false);}catch(RuntimeException $e){$held=true;}
    tc30check($held,'invalid_row_rejected');
}
tc30check(tc30_row(array_replace($hotel,['latitude'=>null,'longitude'=>null]),[],false,false)['latitude']===null,'missing_coordinates_preserved');
echo 'TV_TARGET_CATALOG_PURE_PASS '.$checks."\n";
if(getenv('MATCH_TV_TARGET_MYSQL_TEST')!=='1')exit(0);
$db=new PDO('mysql:host=127.0.0.1;port=33306;dbname=match_primary_fixture;charset=utf8mb4','root','match-fixture-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
tc30check($db->query('SELECT DATABASE()')->fetchColumn()==='match_primary_fixture','fixture_database_only');
$tables=['catalog_hotels','tour_operator_identity_observations','andromeda_hotel_identities','anex_hotel_decisions','anex_review_pair_exclusions'];
foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
$db->exec('CREATE TABLE catalog_hotels (id BIGINT PRIMARY KEY,name VARCHAR(255),country_id INT,country_name VARCHAR(100),region_name VARCHAR(100),subregion_name VARCHAR(100),category INT,is_active INT,latitude DOUBLE NULL,longitude DOUBLE NULL) ENGINE=InnoDB');
$db->exec('CREATE TABLE tour_operator_identity_observations (hotel_id BIGINT,last_seen_at DATETIME) ENGINE=InnoDB');
$db->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace VARCHAR(64),external_hotel_id VARCHAR(64),local_hotel_id BIGINT NULL,decision_status VARCHAR(32)) ENGINE=InnoDB');
$db->exec('CREATE TABLE anex_hotel_decisions (catalog_hotel_id BIGINT NULL) ENGINE=InnoDB');
$db->exec('CREATE TABLE anex_review_pair_exclusions (catalog_hotel_id BIGINT NULL) ENGINE=InnoDB');
for($id=1;$id<=7;++$id){
    $h=array_diff_key($hotel,['private_history'=>true]);$h['id']=$id;
    if($id===3)$h['is_active']=0;if($id===5)$h['country_name']='Россия';if($id===6)$h['country_name']='Абхазия';
    $db->prepare('INSERT INTO catalog_hotels VALUES (?,?,?,?,?,?,?,?,?,?)')->execute(array_values($h));
    if($id!==7)$db->exec('INSERT INTO tour_operator_identity_observations VALUES ('.$id.','.($id===4?'DATE_SUB(UTC_TIMESTAMP(),INTERVAL 31 DAY)':'UTC_TIMESTAMP()').')');
}
$db->exec("INSERT INTO andromeda_hotel_identities VALUES ('andromeda_catalog','9501',1,'accepted'),('andromeda_catalog','9502',2,'pending'),('operator_5','9000',2,'accepted')");
$db->exec('INSERT INTO anex_hotel_decisions VALUES (1)');$db->exec('INSERT INTO anex_review_pair_exclusions VALUES (2)');
$before=[];foreach($tables as $t)$before[$t]=$db->query('SELECT * FROM '.$t)->fetchAll(PDO::FETCH_ASSOC);
$rows=tc30_current($db);
tc30check(array_column($rows,'id')===[1,2],'complete_active_recent_foreign_cohort');
tc30check($rows[0]['accepted_samo_ids']===['9501']&&$rows[0]['manual_hold']===true,'occupied_target_included');
tc30check($rows[1]['accepted_samo_ids']===[]&&$rows[1]['exclusion_hold']===true,'pending_and_other_namespace_not_occupancy');
tc30check(!$db->inTransaction(),'read_transaction_closed');
foreach($tables as $t)tc30check($before[$t]===$db->query('SELECT * FROM '.$t)->fetchAll(PDO::FETCH_ASSOC),'rows_unchanged_'.$t);
echo 'TV_TARGET_CATALOG_MYSQL_PASS '.$checks."\n";
