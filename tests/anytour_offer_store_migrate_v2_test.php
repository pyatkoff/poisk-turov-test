<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_offer_store_migrate_v2.php';
function need_m(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('CHECK_FAILED:'.$label);}
function sql_m(PDO $pdo,string $path):void{$sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($path));foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $s){$s=trim($s);if($s!=='')$pdo->exec($s);}}
function reset_m(PDO $pdo):void{$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $t)$pdo->exec("DROP TABLE IF EXISTS `$t`");$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
function fixture_m(PDO $pdo,int $n=2):void{
 reset_m($pdo);sql_m($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');sql_m($pdo,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');
 $h=$pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
 $s=$pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?, 'fixture',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
 for($i=1;$i<=$n;$i++){$p=json_encode(['name'=>'Fixture '.$i]);$h->execute([$p,hash('sha256',$p)]);$id=(int)$pdo->lastInsertId();$raw=json_encode(['id'=>100+$i]);$s->execute([(string)(100+$i),$id,$raw,hash('sha256',$raw)]);}
}
$dsn=(string)getenv('ANYTOUR_OFFER_MIGRATE_V2_TEST_DSN');$password=(string)getenv('ANYTOUR_OFFER_MIGRATE_V2_TEST_PASSWORD');if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('fixture DSN required');
$pdo=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
fixture_m($pdo);need_m(count(anytour_offer_migrate_v2_statements())===2,'two pinned statements');
$events=[];$result=anytour_offer_migrate_v2($pdo,2,2,function(array $state)use(&$events){$events[]=$state;});
need_m($result['status']==='offer_store_v2_migrated_verified'&&$result['schemaVersion']===2&&$result['offerRowsMigrated']===0,'migration verified');
need_m($result['counts']===['anytour_offer_store_control'=>1,'anytour_offer_refreshes'=>0,'anytour_offer_scope_state'=>0,'anytour_offers'=>0],'zero rows preserved');
need_m($result['oldIndexPresent']===false&&$result['newIndex']===['unique'=>true,'columns'=>['provider','scope_sha256','last_refresh_token','identity_sha256']],'new unique exact');
need_m(count(array_filter($events,fn($e)=>($e['status']??'')==='ddl_completed'))===2,'two ddl completions');
$replay=false;try{anytour_offer_migrate_v2($pdo,2,2,static function(array $s):void{});}catch(RuntimeException $e){$replay=str_contains($e->getMessage(),'not verified-empty schema v1');}need_m($replay,'schema2 replay refused');
fixture_m($pdo);$pdo->exec("INSERT INTO anytour_offer_refreshes(refresh_token,provider,scope_sha256,status,started_at,lease_expires_at,completed_at) VALUES('".str_repeat('1',64)."','anex','".str_repeat('a',64)."','running',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),NULL)");
$nonempty=false;try{anytour_offer_migrate_v2($pdo,2,2,static function(array $s):void{});}catch(RuntimeException $e){$nonempty=str_contains($e->getMessage(),'not verified-empty schema v1');}need_m($nonempty,'nonempty refresh state refused');need_m((int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn()===1,'refusal stays v1');need_m(anytour_offer_migrate_v2_index($pdo,'uq_anytour_offer_identity')===['unique'=>true,'columns'=>['provider','scope_sha256','identity_sha256']],'refusal keeps old index');
fixture_m($pdo);$pdo->exec('ALTER TABLE anytour_offers DROP INDEX uq_anytour_offer_identity, ADD UNIQUE KEY uq_anytour_offer_refresh_identity(provider,scope_sha256,last_refresh_token,identity_sha256)');
$partial=false;try{anytour_offer_migrate_v2($pdo,2,2,static function(array $s):void{});}catch(RuntimeException $e){$partial=str_contains($e->getMessage(),'index is not exact v1');}need_m($partial,'partial ddl refused');need_m((int)$pdo->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn()===1,'partial state not silently adopted');
echo "ANYTOUR_OFFER_MIGRATE_V2_TEST_OK fresh=1 replay_refused=1 nonempty_refused=1 partial_refused=1\n";
