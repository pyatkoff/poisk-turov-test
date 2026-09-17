<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_offer_store_install.php';
function need(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function apply_sql(PDO $pdo,string $file):void{$sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($file));foreach(array_filter(array_map('trim',explode(';',$sql))) as $s)$pdo->exec($s);}
function reset_targets(PDO $pdo):void{$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control'] as $t)$pdo->exec("DROP TABLE IF EXISTS `$t`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
$dsn=(string)getenv('ANYTOUR_OFFER_INSTALL_TEST_DSN');$password=(string)getenv('ANYTOUR_OFFER_INSTALL_TEST_PASSWORD');
if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $t)$pdo->exec("DROP TABLE IF EXISTS `$t`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
apply_sql($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
$hotel=$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
$source=$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
for($i=1;$i<=1000;$i++){$profile=json_encode(['name'=>'Fixture '.$i]);$hotel->execute([$profile,hash('sha256',$profile),1,1]);$own=(int)$pdo->lastInsertId();$legacy=(string)(100000+$i);$raw=json_encode(['id'=>(int)$legacy]);$source->execute([$legacy,$own,'fixture',$raw,hash('sha256',$raw)]);}
$identity=$pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@port AS port,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
$proof=['status'=>'current_read_only','operation'=>ANYTOUR_OFFER_INSTALL_PROOF_OPERATION,'schemaState'=>'absent','databaseWrites'=>0,'supplierCalls'=>0,'databaseNameSha256'=>hash('sha256',(string)$identity['db']),'targetIdentitySha256'=>hash('sha256',anytour_offer_install_json($identity)),'canonical'=>['schemaVersion'=>1,'activeHotels'=>1000,'legacyCatalogLinks'=>1000]];
need(count(anytour_offer_install_statements())===5,'exact migration statement count');
$events=[];$result=anytour_offer_install($pdo,$proof,function(array $s)use(&$events){$events[]=$s;});
need($result['status']==='offer_store_installed_verified'&&$result['offersCreated']===0,'fresh install verified');
need($result['counts']===['anytour_offer_store_control'=>1,'anytour_offer_refreshes'=>0,'anytour_offer_scope_state'=>0,'anytour_offers'=>0],'fresh zero-offer counts');
need((int)$pdo->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()===1000&&(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn()===1000,'canonical rows unchanged');
need(count(array_filter($events,fn($e)=>($e['status']??'')==='ddl_completed'))===5,'five durable DDL completions');
$replayFailed=false;try{anytour_offer_install($pdo,$proof,static function(array $s):void{});}catch(RuntimeException $e){$replayFailed=str_contains($e->getMessage(),'Existing/partial');}need($replayFailed,'completed install refuses replay');
reset_targets($pdo);$pdo->exec('CREATE TABLE anytour_offer_store_control(singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,schema_version INT UNSIGNED NOT NULL) ENGINE=InnoDB');
$partialFailed=false;try{anytour_offer_install($pdo,$proof,static function(array $s):void{});}catch(RuntimeException $e){$partialFailed=str_contains($e->getMessage(),'Existing/partial');}need($partialFailed,'partial state refused');
need(count(anytour_offer_install_presence($pdo))===1,'partial refusal made no additional tables');
reset_targets($pdo);
$bad=$proof;$bad['canonical']['activeHotels']=999;$proofFailed=false;try{anytour_offer_install($pdo,$bad,static function(array $s):void{});}catch(RuntimeException $e){$proofFailed=str_contains($e->getMessage(),'Exact absent CURRENT proof');}need($proofFailed,'stale proof refused before DDL');
need(anytour_offer_install_presence($pdo)===[],'invalid proof made no tables');
echo "ANYTOUR_OFFER_INSTALL_TEST_OK canonical=1000 statements=5 offers=0 replay_refused=1 partial_refused=1\n";
