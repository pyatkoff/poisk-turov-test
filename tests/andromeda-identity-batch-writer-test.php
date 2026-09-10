<?php
// Real 92-row writer against disposable loopback MySQL only; no application secrets.
$dsn=getenv('ANEX_LINK_TEST_DSN');
if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4')throw new RuntimeException('test_database_required');
$fixture=json_decode(file_get_contents(getenv('ANDROMEDA_BATCH_FIXTURE')),true,64,JSON_THROW_ON_ERROR);
$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$checks=0;
function ensure_batch($yes,$message){global $checks;if(!$yes)throw new RuntimeException($message);++$checks;}
$directory=sys_get_temp_dir().'/andromeda-batch-'.bin2hex(random_bytes(8));$root=$directory.'/anytoour.ru';mkdir($root.'/data',0700,true);
file_put_contents($root.'/data/db-v1.php', <<<'HELPER'
<?php
function v2_data_db(){
 $dsn=getenv('ANEX_LINK_TEST_DSN');
 if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4')throw new RuntimeException();
 return new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}
HELPER
);
$source=substr(file_get_contents(__DIR__.'/../app/integrations/anex-search-mapping-registry.php'),5).'\n';
$source=str_replace('\\n',"\n",$source).substr(file_get_contents(__DIR__.'/../scripts/diagnostics/andromeda_identity_batch_accept.php'),5);
function invoke_batch($payload,$success){
 global $source,$root;
 $pipes=[];$process=proc_open([PHP_BINARY,'-r',$source],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$root);
 if(!is_resource($process))throw new RuntimeException('child');
 $raw=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$offset=0;
 while($offset<strlen($raw)){$written=fwrite($pipes[0],substr($raw,$offset));if(!$written)throw new RuntimeException('pipe');$offset+=$written;}
 fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
 $result=json_decode($out,true,64,JSON_THROW_ON_ERROR);
 ensure_batch($err==='', 'no diagnostics leakage');
 ensure_batch($code===($success?0:1),'expected writer outcome: '.$out);
 return $result;
}
function seed_identities(){
 global $db,$fixture;
 $db->exec('DELETE FROM andromeda_hotel_identities');
 $insert=$db->prepare('INSERT INTO andromeda_hotel_identities VALUES (?,?,?,?,?,?,?)');
 foreach($fixture['identities'] as $row)$insert->execute(['andromeda_catalog',$row['external_hotel_id'],null,'pending',$row['catalog_sha256'],hash('sha256',$row['evidence_json']),$row['evidence_json']]);
 $insert->execute(['other_provider','790',1003,'accepted',str_repeat('b',64),hash('sha256','{}'),'{}']);
 $insert->execute(['andromeda_catalog','9999999999',null,'conflict',str_repeat('b',64),hash('sha256','{}'),'{}']);
}
$tables=['andromeda_hotel_identities','hotel_aliases','anex_review_pair_exclusions','anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','catalog_hotels'];
try{
 foreach($tables as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
 $db->exec('CREATE TABLE catalog_hotels (id INT PRIMARY KEY,name VARCHAR(500),country_id INT,region_name VARCHAR(300),subregion_name VARCHAR(300),is_active INT) ENGINE=InnoDB CHARACTER SET utf8mb4');
 $db->exec('CREATE TABLE hotel_aliases (hotel_id INT,alias VARCHAR(500),KEY(hotel_id)) ENGINE=InnoDB CHARACTER SET utf8mb4');
 $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB CHARACTER SET utf8mb4');
 $db->exec('CREATE TABLE anex_search_hotel_observations(anex_hotel_id INT PRIMARY KEY) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,decision_status VARCHAR(16),catalog_hotel_id INT) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(32),approval_policy VARCHAR(64),enabled INT,scope VARCHAR(16)) ENGINE=InnoDB');
 $db->beginTransaction();
 $insert=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,?,1)');
 foreach($fixture['hotels'] as $h)$insert->execute([$h['id'],$h['name'],$h['country_id'],$h['region_name'],$h['subregion_name']]);
 $insert=$db->prepare('INSERT INTO hotel_aliases VALUES(?,?)');foreach($fixture['aliases'] as $a)$insert->execute([$a['hotel_id'],$a['alias']]);
 $bridges=[];foreach($fixture['request']['rows'] as $row)foreach($row['anex_bridges'] as $b)$bridges[$b['anex_id']]=$b['local_id'];
 $insert=$db->prepare("INSERT INTO anex_hotel_search_mappings VALUES(?,?,'strong_candidate','owner_exact_and_strong_20260908',1,'preview')");
 foreach($bridges as $id=>$target){$insert->execute([$id,$target]);$db->exec('INSERT INTO anex_search_hotel_observations VALUES('.(int)$id.')');}
 seed_identities();$db->commit();
 $payload=$fixture['request'];$out=invoke_batch($payload,true);
 ensure_batch($out['updated']===92&&$out['readback_verified']===true,'all92 and post-COMMIT readback');
 ensure_batch($out['other_identities_unchanged']===true&&$out['other_identity_count']===2,'protected namespace/conflict unchanged');
 ensure_batch(count($out['rows'])===92&&$out['counts']['accepted']===92,'all readback rows');
 $again=invoke_batch($payload,false);ensure_batch($again['database_transaction_rolled_back']===true,'raw replay refused');
 seed_identities();$last=end($payload['rows']);
 $db->prepare("UPDATE andromeda_hotel_identities SET decision_status='conflict' WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?")->execute([$last['external_hotel_id']]);
 invoke_batch($payload,false);ensure_batch((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted'")->fetchColumn()===0,'last-row conflict rolls back earlier updates');
 foreach(['country_id=1','is_active=0',"region_name='Стамбул'"] as $change){
  seed_identities();$db->exec('UPDATE catalog_hotels SET '.$change.' WHERE id=1003');invoke_batch($payload,false);
  ensure_batch((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='pending'")->fetchColumn()===92,'target conflict zero writes');
  $db->exec("UPDATE catalog_hotels SET country_id=4,is_active=1,region_name='Кемер' WHERE id=1003");
 }
 seed_identities();$db->exec("INSERT INTO catalog_hotels VALUES(9999999,'OTHER HOTEL',4,'Кемер','Бельдиби',1)");$db->exec("INSERT INTO hotel_aliases VALUES(9999999,'ASIA HOTEL')");invoke_batch($payload,false);
 $db->exec('DELETE FROM hotel_aliases WHERE hotel_id=9999999');$db->exec('DELETE FROM catalog_hotels WHERE id=9999999');
 seed_identities();$bridgeId=array_key_first($bridges);$target=$bridges[$bridgeId];
 $db->exec("INSERT INTO anex_hotel_decisions VALUES($bridgeId,'rejected',NULL)");invoke_batch($payload,false);$db->exec('DELETE FROM anex_hotel_decisions');
 $db->exec("INSERT INTO anex_review_pair_exclusions VALUES($bridgeId,$target)");invoke_batch($payload,false);$db->exec('DELETE FROM anex_review_pair_exclusions');
 seed_identities();$db->exec("UPDATE andromeda_hotel_identities SET catalog_sha256=REPEAT('c',64) WHERE external_hotel_id='790' AND supplier_namespace='andromeda_catalog'");invoke_batch($payload,false);
 seed_identities();$bad=$payload;$bad['rows'][0]['local_hotel_id']=1;$result=invoke_batch($bad,false);ensure_batch($result['phase']==='request','tampered entire payload rejected before DB');
 echo json_encode(['status'=>'passed','checks'=>$checks,'actual_rows_tested'=>92,'application_database_writes'=>0,'supplier_calls'=>0]),"\n";
}finally{
 if($db->inTransaction())$db->rollBack();foreach($tables as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
 unlink($root.'/data/db-v1.php');rmdir($root.'/data');rmdir($root);rmdir($directory);
}
