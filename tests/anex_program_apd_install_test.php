<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/ops/anex_program_apd_install.php';

function need(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$dsn=(string)getenv('ANEX_APD_INSTALL_TEST_DSN');
$password=(string)getenv('ANEX_APD_INSTALL_TEST_PASSWORD');
if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

function reset_apd(PDO $pdo):void{
    foreach(ANYTOUR_ANEX_APD_INSTALL_TABLES as $t)$pdo->exec("DROP TABLE IF EXISTS `$t`");
}
$pdo->exec('DROP TABLE IF EXISTS anytour_offers');
$pdo->exec('DROP TABLE IF EXISTS anytour_offer_store_control');
$pdo->exec('CREATE TABLE anytour_offer_store_control(singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,schema_version INT UNSIGNED NOT NULL) ENGINE=InnoDB');
$pdo->exec('INSERT INTO anytour_offer_store_control(singleton_id,schema_version) VALUES(1,2)');
$pdo->exec('CREATE TABLE anytour_offers(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('INSERT INTO anytour_offers VALUES(NULL),(NULL)');
reset_apd($pdo);

need(count(aapi_statements())===3,'statement-count');
$events=[];
$result=aapi_install($pdo,function(array $s)use(&$events){$events[]=$s;});
need($result['status']==='anex_program_apd_schema_installed_verified','fresh-status');
need($result['offerStoreSchemaVersion']===2,'schema-v2');
need($result['offerCountBefore']===2&&$result['offerCountAfter']===2,'offers-unchanged');
need($result['counts']===['anytour_anex_programs'=>0,'anytour_anex_program_contexts'=>0,'anytour_anex_apd_rates'=>0],'fresh-empty');
need(count(array_filter($events,fn($e)=>($e['status']??'')==='ddl_completed'))===3,'three-ddl-complete');

$replay=false;
try{aapi_install($pdo,static function(array $s):void{});}catch(RuntimeException $e){$replay=str_contains($e->getMessage(),'Existing/partial');}
need($replay,'replay-refused');

reset_apd($pdo);
$pdo->exec('CREATE TABLE anytour_anex_programs(id INT PRIMARY KEY) ENGINE=InnoDB');
$partial=false;
try{aapi_install($pdo,static function(array $s):void{});}catch(RuntimeException $e){$partial=str_contains($e->getMessage(),'Existing/partial');}
need($partial,'partial-refused');
need(count(aapi_presence($pdo))===1,'partial-no-more-ddl');

reset_apd($pdo);
$pdo->exec('UPDATE anytour_offer_store_control SET schema_version=1 WHERE singleton_id=1');
$wrong=false;
try{aapi_install($pdo,static function(array $s):void{});}catch(RuntimeException $e){$wrong=str_contains($e->getMessage(),'schema v2');}
need($wrong,'wrong-store-version-refused');
need(aapi_presence($pdo)===[],'wrong-version-no-ddl');

echo "ANEX_APD_INSTALL_TEST_OK schema_v2=1 statements=3 offers_unchanged=1 replay_refused=1 partial_refused=1\n";
