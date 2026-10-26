<?php
declare(strict_types=1);
$source=(string)getenv('LOCAL_PROFILE_SOURCE_ROOT');
if($source===''||realpath($source)!==$source)throw new RuntimeException('Immutable source required');
require_once $source.'/v2/data/hotel-presentation-read-v1.php';
require_once $source.'/v2/data/local-tv-catalog-v1.php';
require_once __DIR__.'/../scripts/catalog/anytour_hotel_profile_completeness_read_v1.php';
$server=(string)getenv('LOCAL_PROFILE_TEST_DSN');
if(!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server))throw new RuntimeException('Dedicated loopback server fixture required');
$password=(string)getenv('LOCAL_STAY_TEST_PASSWORD');$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$root=new PDO($server,'root',$password,$options);$name='profile_read_'.bin2hex(random_bytes(6));
$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
$db=new PDO(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'root',$password,$options);
$site=sys_get_temp_dir().'/profile-read-'.bin2hex(random_bytes(8));mkdir($site.'/data',0700,true);
file_put_contents($site.'/config.php','native fixture config');file_put_contents($site.'/data/db-v1.php','native fixture helper');
$checks=0;function pr_check(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('PROFILE_READ_CHECK:'.$label);}
function pr_read():array{global $db,$site,$expected,$protected;return AnyTourHotelProfileCompletenessReadV1::read($db,$site,$expected,$protected);}
try {
    $db->exec('CREATE TABLE catalog_hotels(id BIGINT PRIMARY KEY,name VARCHAR(255) NOT NULL,country_id BIGINT NOT NULL,country_name VARCHAR(255) NOT NULL,region_id BIGINT NULL,region_name VARCHAR(255) NULL,subregion_id BIGINT NULL,subregion_name VARCHAR(255) NULL,category INT NULL,rating DECIMAL(4,2) NULL,hotel_type INT NULL,latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,primary_image_url TEXT NULL,is_active TINYINT NOT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE catalog_hotel_details(hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),description MEDIUMTEXT,address TEXT,place TEXT,build_info TEXT,repair_info TEXT,square_info TEXT,images_json JSON,infrastructure_json JSON,meals_json JSON,services_json JSON,room_types TEXT,fetched_at DATETIME,raw_json MEDIUMTEXT,source_hash CHAR(64)) ENGINE=InnoDB');
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($source.'/v2/data/migrations/20261010-local-tv-catalog.sql'))??'';
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]as$part)if(trim($part)!=='')$db->exec($part);
    $stmt=$db->prepare('INSERT INTO catalog_hotels VALUES(475,?,1,"Египет",NULL,NULL,NULL,NULL,4,4.2,1,NULL,NULL,"https://img.example/a.jpg",1)');$stmt->execute([AnyTourHotelProfileCompletenessReadV1::NAME]);
    $payload=['id'=>475,'name'=>AnyTourHotelProfileCompletenessReadV1::NAME,'country'=>['id'=>1,'name'=>'Египет'],'common'=>['description'=>'Own factual description'],'images'=>['https://img.example/a.jpg']];
    $raw=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$raw);
    $stmt=$db->prepare("INSERT INTO catalog_hotel_details VALUES(475,'success','Own factual description','Address',NULL,NULL,NULL,NULL,JSON_ARRAY('https://img.example/b.jpg'),JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY(),'FAMILY SEA VIEW','2026-10-26 01:00:00',?,?)");$stmt->execute([$raw,$hash]);
    // Fixture setup only; runtime never calls a catalogue writer.
    $db->exec("INSERT INTO local_tv_hotels(id,first_seen_at,last_seen_at,pending_since,discovery_json,content_json,manual_json,source_absent_json) VALUES(475,'2026-10-26 01:00:00','2026-10-26 01:00:00','2026-10-26 01:00:00','{}','{}','{}','[]')");
    (new LocalTvCatalogV1($db))->saveSource(475,$payload,'2026-10-26 01:00:00');
    $db->exec("UPDATE local_tv_hotels SET manual_json='{\"description\":\"Private manual description\",\"name\":\"Manual display name\"}' WHERE id=475");
    $identity=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();
    $expected=hash('sha256',AnyTourHotelProfileCompletenessReadV1::json(['database'=>$identity['selected_database'],'serverUuid'=>$identity['server_uuid']]));
    $protected=AnyTourHotelProfileCompletenessReadV1::protected($site);$before=$db->query('SELECT * FROM local_tv_hotels WHERE id=475')->fetch();
    $done=pr_read();$r=$done['receipt'];$p=$done['profile'];
    pr_check($r['state']==='completed_read_only','complete');pr_check($p['description']['byteLength']===23,'legacy length');
    pr_check($p['consumer']['profile']['description']['sha256']===hash('sha256','Private manual description'),'manual effective');
    pr_check($p['consumer']['profile']['manualFieldCount']===2,'manual count');pr_check($p['retainedSource']['sha256']===$hash,'source hash');
    pr_check($p['consumer']['profile']['galleryCount']===1&&$p['galleryCount']===2,'separate galleries');
    foreach(['Own factual description','Private manual description','Manual display name','img.example','FAMILY SEA VIEW']as$private)pr_check(!str_contains(AnyTourHotelProfileCompletenessReadV1::json($done),$private),'no raw export');
    pr_check($before===$db->query('SELECT * FROM local_tv_hotels WHERE id=475')->fetch(),'unchanged bytes');
    foreach(['supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts']as$key)pr_check($r[$key]===0,'typed0 '.$key);
    pr_check(!$db->inTransaction()&&$r['protectedBefore']===$r['protectedAfter']&&$r['databaseSha256Before']===$r['databaseSha256After'],'post guards');
    $done['receipt']+=['operationId'=>AnyTourHotelProfileCompletenessReadV1::OPERATION,'sourceSha'=>AnyTourHotelProfileCompletenessReadV1::SOURCE,'controlSha'=>str_repeat('b',40)];
    fwrite(STDOUT,'PROFILE_READ_PAYLOAD='.base64_encode(AnyTourHotelProfileCompletenessReadV1::json($done))."\n");
    $db->beginTransaction();$held=pr_read();pr_check($held['receipt']['state']==='caller_transaction'&&$db->inTransaction(),'caller TX preserved');$db->rollBack();
    $db->exec('UPDATE catalog_hotels SET country_id=4 WHERE id=475');pr_check(pr_read()['receipt']['state']==='producer_identity_drift','country drift');$db->exec('UPDATE catalog_hotels SET country_id=1 WHERE id=475');
    $db->exec('UPDATE catalog_hotels SET is_active=0 WHERE id=475');pr_check(pr_read()['receipt']['state']==='catalog_hotel_missing_or_inactive','inactive');$db->exec('UPDATE catalog_hotels SET is_active=1 WHERE id=475');
    $db->exec("UPDATE catalog_hotel_details SET source_hash=REPEAT('0',64) WHERE hotel_id=475");pr_check(pr_read()['receipt']['state']==='retained_source_invalid','raw corrupt');$db->prepare('UPDATE catalog_hotel_details SET source_hash=? WHERE hotel_id=475')->execute([$hash]);
    $db->exec("UPDATE catalog_hotel_details SET status='error' WHERE hotel_id=475");pr_check(pr_read()['receipt']['state']==='retained_source_unavailable','raw unavailable');$db->exec("UPDATE catalog_hotel_details SET status='success' WHERE hotel_id=475");
    $bad=$payload;$bad['country']['id']='1garbage';$badRaw=json_encode($bad,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $db->prepare('UPDATE catalog_hotel_details SET raw_json=?,source_hash=? WHERE hotel_id=475')->execute([$badRaw,hash('sha256',$badRaw)]);
    pr_check(pr_read()['receipt']['state']==='retained_source_invalid','malformed country cannot prove identity');
    $db->prepare('UPDATE catalog_hotel_details SET raw_json=?,source_hash=? WHERE hotel_id=475')->execute([$raw,$hash]);
    $db->exec("UPDATE local_tv_hotels SET source_sha256=REPEAT('0',64) WHERE id=475");pr_check(pr_read()['receipt']['state']==='retained_source_invalid','consumer corrupt');$db->prepare('UPDATE local_tv_hotels SET source_sha256=? WHERE id=475')->execute([$hash]);
    $db->exec("UPDATE local_tv_hotels SET state='excluded' WHERE id=475");pr_check(pr_read()['profile']['consumer']['state']==='excluded','excluded explicit');$db->exec("UPDATE local_tv_hotels SET state='ready' WHERE id=475");
    $db->exec('ALTER TABLE catalog_hotel_details ENGINE=MyISAM');pr_check(pr_read()['receipt']['state']==='schema_unavailable','nontransactional');$db->exec('ALTER TABLE catalog_hotel_details ENGINE=InnoDB');
    file_put_contents($site.'/config.php','drift');pr_check(pr_read()['receipt']['state']==='protected_drift','protected drift');
    fwrite(STDOUT,'LOCAL_PROFILE_COMPLETENESS_OK checks='.$checks." database=NATIVE_MYSQL\n");
} finally {
    if($db->inTransaction())$db->rollBack();$db=null;$root->exec('DROP DATABASE `'.$name.'`');
    unlink($site.'/config.php');unlink($site.'/data/db-v1.php');rmdir($site.'/data');rmdir($site);
}
