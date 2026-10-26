<?php
declare(strict_types=1);
$source=(string)getenv('LOCAL_PROFILE_SOURCE_ROOT');
if($source===''||realpath($source)!==$source)throw new RuntimeException('Immutable source required');
require_once $source.'/v2/data/local-tv-catalog-v1.php';
require_once __DIR__.'/../scripts/catalog/anytour_hotel_profile_batch_read_v1.php';
$server=(string)getenv('LOCAL_PROFILE_TEST_DSN');
if(!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server))throw new RuntimeException('Dedicated loopback server fixture required');
$password=(string)getenv('LOCAL_STAY_TEST_PASSWORD');$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$root=new PDO($server,'root',$password,$options);$name='profile_batch_'.bin2hex(random_bytes(6));
$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$dsn=str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server);
final class BatchReadProbePdo extends PDO {
    public int $readOnlyDenied=0;
    public function exec(string $sql): int|false {
        $r=parent::exec($sql);
        if($sql==='START TRANSACTION READ ONLY')try{parent::exec('UPDATE catalog_hotels SET is_active=is_active WHERE id=485');throw new RuntimeException('READ_ONLY_NOT_ENFORCED');}
        catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1792)throw $e;++$this->readOnlyDenied;}
        return $r;
    }
}
$db=new BatchReadProbePdo($dsn,'root',$password,$options);
$site=sys_get_temp_dir().'/profile-batch-'.bin2hex(random_bytes(8));mkdir($site.'/data',0700,true);
file_put_contents($site.'/config.php','native batch fixture config');file_put_contents($site.'/data/db-v1.php','native batch fixture helper');
$checks=0;function pb_check(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('PROFILE_BATCH_CHECK:'.$label);}
function pb_read():array{global $db,$site,$manifest,$identity,$protected;return AnyTourHotelProfileBatchReadV1::read($db,$site,$manifest,$identity,$protected);}
function pb_payload(array $target,string $description='Retained factual description'):array{return ['id'=>$target['hotelId'],'name'=>$target['name'],'country'=>['id'=>$target['countryId'],'name'=>'Fixture country'],'common'=>['description'=>$description],'images'=>['https://img.example/a.jpg','https://img.example/b.jpg']];}
function pb_raw(array $target,array $payload):void{global $db;$raw=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$q=$db->prepare("UPDATE catalog_hotel_details SET status='success',raw_json=?,source_hash=?,fetched_at='2026-10-26 01:00:00' WHERE hotel_id=?");$q->execute([$raw,hash('sha256',$raw),$target['hotelId']]);}
function pb_snapshot():string{global $db;$all=[];foreach(['catalog_hotels','catalog_hotel_details','local_tv_hotels'] as $table)$all[$table]=$db->query('SELECT * FROM '.$table.' ORDER BY '.($table==='catalog_hotel_details'?'hotel_id':'id'))->fetchAll();return hash('sha256',AnyTourHotelProfileBatchReadV1::json($all));}
try {
    $db->exec('CREATE TABLE catalog_hotels(id BIGINT PRIMARY KEY,name VARCHAR(512) NOT NULL,country_id BIGINT NOT NULL,is_active TINYINT NOT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE catalog_hotel_details(hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),description MEDIUMTEXT,raw_json MEDIUMTEXT,source_hash CHAR(64),fetched_at DATETIME) ENGINE=InnoDB');
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($source.'/v2/data/migrations/20261010-local-tv-catalog.sql'))??'';
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]as$part)if(trim($part)!=='')$db->exec($part);
    $manifest=AnyTourHotelProfileBatchReadV1::manifest((string)realpath(__DIR__.'/../scripts/catalog/fixtures/local-profile-batch-20261026.json'));
    $catalogInsert=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,1)');$detailInsert=$db->prepare("INSERT INTO catalog_hotel_details(hotel_id,status,description) VALUES(?,'success','Misleading normalized column must not be truth')");
    $localInsert=$db->prepare("INSERT INTO local_tv_hotels(id,first_seen_at,last_seen_at,pending_since,discovery_json,content_json,manual_json,source_absent_json) VALUES(?,'2026-10-26 01:00:00','2026-10-26 01:00:00','2026-10-26 01:00:00','{}','{}','{}','[]')");
    foreach($manifest['records'] as $target){$id=$target['hotelId'];$catalogInsert->execute([$id,$target['name'],$target['countryId']]);$detailInsert->execute([$id]);pb_raw($target,pb_payload($target));$localInsert->execute([$id]);(new LocalTvCatalogV1($db))->saveSource($id,pb_payload($target),'2026-10-26 01:00:00');}
    $binding=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();$identity=hash('sha256',AnyTourHotelProfileBatchReadV1::json(['database'=>$binding['selected_database'],'serverUuid'=>$binding['server_uuid']]));
    $protected=AnyTourHotelProfileCompletenessReadV1::protected($site);$before=pb_snapshot();$done=pb_read();
    pb_check($done['receipt']['state']==='completed_read_only'&&$done['receipt']['observedCount']===20&&$done['receipt']['heldCount']===0,'all20 bounded observed');
    pb_check($done['receipt']['possibleGapCount']===0&&count($done['items'])===20,'complete count');pb_check($db->readOnlyDenied===1,'actual READ ONLY denial');
    pb_check($done['items'][0]['source']['description']['sha256']===hash('sha256','Retained factual description'),'RAW normalized facts');
    pb_check($done['items'][0]['source']['description']['sha256']!==hash('sha256','Misleading normalized column must not be truth'),'normalized column not accepted');
    pb_check($done['items'][0]['consumer']['profile']['galleryCount']===2,'gallery observed');
    pb_check($before===pb_snapshot()&&!$db->inTransaction(),'all bytes unchanged transaction closed');
    pb_check(!in_array(475,array_column($done['items'],'hotelId'),true),'old475 excluded');
    foreach(['supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts'] as $k)pb_check($done['receipt'][$k]===0,'typed0 '.$k);
    $export=AnyTourHotelProfileBatchReadV1::json($done);foreach(['Retained factual description','Misleading normalized column','img.example'] as $private)pb_check(!str_contains($export,$private),'no raw export '.$private);
    $second=$manifest['records'][1];$third=$manifest['records'][2];$fourth=$manifest['records'][3];$fifth=$manifest['records'][4];$sixth=$manifest['records'][5];$seventh=$manifest['records'][6];
    $db->prepare('DELETE FROM local_tv_hotels WHERE id=?')->execute([$second['hotelId']]);
    $db->prepare('UPDATE local_tv_hotels SET manual_json=? WHERE id=?')->execute(['{"description":"","primaryImage":null,"images":[]}',$third['hotelId']]);
    $db->prepare("UPDATE local_tv_hotels SET content_json='{}',discovery_json='{}',source_json=NULL,source_sha256=NULL,source_fetched_at=NULL,state='pending' WHERE id=?")->execute([$fourth['hotelId']]);
    $db->prepare("UPDATE catalog_hotel_details SET source_hash=REPEAT('0',64) WHERE hotel_id=?")->execute([$fifth['hotelId']]);
    $foreign=pb_payload($sixth);$foreign['id']=475;pb_raw($sixth,$foreign);
    $db->prepare("UPDATE catalog_hotel_details SET status='failure' WHERE hotel_id=?")->execute([$seventh['hotelId']]);
    $before=pb_snapshot();$mixed=pb_read();$items=$mixed['items'];
    pb_check($mixed['receipt']['state']==='completed_read_only'&&$mixed['receipt']['observedCount']===17&&$mixed['receipt']['heldCount']===3,'per-target holds');
    pb_check($items[1]['state']==='observed'&&$items[1]['consumer']['state']==='missing'&&$items[1]['gaps']['consumerMissing']===[]&&$items[1]['gaps']['possibleFillFields']===[],'missing consumer explicit no creation authority');
    pb_check($items[2]['gaps']['consumerMissing']===AnyTourHotelProfileBatchReadV1::FIELDS&&$items[2]['gaps']['manualProtectedMissing']===AnyTourHotelProfileBatchReadV1::FIELDS&&$items[2]['gaps']['possibleFillFields']===[],'manual empty protected');
    pb_check($items[3]['gaps']['possibleFillFields']===AnyTourHotelProfileBatchReadV1::FIELDS&&$mixed['receipt']['possibleGapCount']===1,'possible retained source fill distinguished');
    pb_check($items[4]['state']==='held'&&$items[4]['reason']==='retained_source_invalid'&&$items[4]['source']===null,'corrupt raw held');
    pb_check($items[5]['state']==='held'&&$items[5]['source']===null,'foreign raw held');
    pb_check($items[6]['state']==='held'&&$items[6]['reason']==='retained_source_unavailable','unavailable raw held');
    pb_check($before===pb_snapshot(),'mixed read unchanged');
    $mixed['receipt']+=['operationId'=>AnyTourHotelProfileBatchReadV1::OPERATION,'sourceSha'=>AnyTourHotelProfileBatchReadV1::SOURCE,'controlSha'=>str_repeat('b',40)];
    fwrite(STDOUT,'PROFILE_BATCH_READ_PAYLOAD='.base64_encode(AnyTourHotelProfileBatchReadV1::json($mixed))."\n");
    $db->beginTransaction();$held=pb_read();pb_check($held['receipt']['state']==='held'&&$held['receipt']['reason']==='caller_transaction'&&$db->inTransaction(),'caller TX preserved');$db->rollBack();
    pb_check(count(array_filter($held['items'],static fn($item)=>$item['source']!==null))===0,'fatal clears observations');
    $db->prepare('UPDATE catalog_hotels SET name=? WHERE id=?')->execute(['Other hotel',$manifest['records'][0]['hotelId']]);$changed=pb_read();pb_check($changed['items'][0]['reason']==='producer_identity_drift','name drift');
    $db->prepare('UPDATE catalog_hotels SET name=? WHERE id=?')->execute([$manifest['records'][0]['name'],$manifest['records'][0]['hotelId']]);
    $empty=pb_payload($manifest['records'][0],'');$empty['images']=[];pb_raw($manifest['records'][0],$empty);$absent=pb_read();pb_check($absent['items'][0]['gaps']['sourceMissing']===AnyTourHotelProfileBatchReadV1::FIELDS,'actual source absence');
    pb_raw($manifest['records'][0],pb_payload($manifest['records'][0]));
    $db->prepare("UPDATE local_tv_hotels SET state='excluded' WHERE id=?")->execute([$manifest['records'][0]['hotelId']]);$excluded=pb_read();pb_check($excluded['items'][0]['consumer']['state']==='excluded'&&$excluded['items'][0]['gaps']['possibleFillFields']===[],'excluded not fillable');
    $db->prepare("UPDATE local_tv_hotels SET state='ready' WHERE id=?")->execute([$manifest['records'][0]['hotelId']]);
    $db->prepare('UPDATE catalog_hotel_details SET raw_json=? WHERE hotel_id=?')->execute([str_repeat('x',1048577),$manifest['records'][0]['hotelId']]);$limit=pb_read();pb_check($limit['receipt']['state']==='held'&&$limit['receipt']['reason']==='row_size_limit'&&$limit['receipt']['observedCount']===0,'limit holds whole batch');
    pb_raw($manifest['records'][0],pb_payload($manifest['records'][0]));
    $db->exec('ALTER TABLE catalog_hotel_details ENGINE=MyISAM');$schema=pb_read();pb_check($schema['receipt']['reason']==='schema_unavailable'&&$schema['receipt']['observedCount']===0,'nontransactional');$db->exec('ALTER TABLE catalog_hotel_details ENGINE=InnoDB');
    file_put_contents($site.'/config.php','drift');$drift=pb_read();pb_check($drift['receipt']['reason']==='protected_drift'&&$drift['receipt']['observedCount']===0,'protected drift');
    fwrite(STDOUT,'LOCAL_PROFILE_BATCH_OK checks='.$checks." database=NATIVE_MYSQL\n");
}finally{
    if($db->inTransaction())$db->rollBack();$db=null;$root->exec('DROP DATABASE `'.$name.'`');
    unlink($site.'/config.php');unlink($site.'/data/db-v1.php');rmdir($site.'/data');rmdir($site);
}
