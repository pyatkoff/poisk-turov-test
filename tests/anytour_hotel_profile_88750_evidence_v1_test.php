<?php
declare(strict_types=1);
$source=(string)getenv('LOCAL_PROFILE_SOURCE_ROOT');if($source===''||realpath($source)!==$source)throw new RuntimeException('Immutable source required');
require_once $source.'/v2/data/local-tv-catalog-v1.php';
require_once __DIR__.'/../scripts/catalog/anytour_hotel_profile_88750_evidence_v1.php';
$server=(string)getenv('LOCAL_PROFILE_TEST_DSN');if(!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server))throw new RuntimeException('Dedicated loopback server fixture required');
$password=(string)getenv('LOCAL_STAY_TEST_PASSWORD');$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$root=new PDO($server,'root',$password,$options);$name='profile_88750_'.bin2hex(random_bytes(6));$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
$dsn=str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server);
final class Evidence88750ProbePdo extends PDO {
    public int $denied=0;public int $queries=0;public ?Closure $afterRollback=null;
    public function exec(string $sql):int|false{$r=parent::exec($sql);if($sql==='START TRANSACTION READ ONLY')try{parent::exec('UPDATE catalog_hotels SET is_active=is_active WHERE id=88750');throw new RuntimeException('READ_ONLY_NOT_ENFORCED');}
        catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1792)throw $e;++$this->denied;}return $r;}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{++$this->queries;return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);}
    public function rollBack():bool{$r=parent::rollBack();if($this->afterRollback!==null){$callback=$this->afterRollback;$this->afterRollback=null;$callback();}return $r;}
}
$db=new Evidence88750ProbePdo($dsn,'root',$password,$options);$setup=new PDO($dsn,'root',$password,$options);
$site=sys_get_temp_dir().'/profile-88750-'.bin2hex(random_bytes(8));mkdir($site.'/data',0700,true);file_put_contents($site.'/config.php','native evidence fixture config');file_put_contents($site.'/data/db-v1.php','native evidence fixture helper');
$checks=0;function pe_check(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('PROFILE_88750_CHECK:'.$label);}
function pe_json(mixed $value):string{return AnyTourHotelProfile88750EvidenceV1::json($value);}
function pe_fact(?string $text):array{return ['byteLength'=>$text===null?0:strlen($text),'present'=>$text!==null,'sha256'=>$text===null?null:hash('sha256',$text)];}
function pe_parent(array $pins):array{
    $target=['hotelId'=>88750,'countryId'=>4,'recordSha256'=>str_repeat('a',64),'state'=>'observed','reason'=>null,
        'source'=>['description'=>pe_fact(null),'sourceSha256'=>$pins['rawSha256'],'sourceFetchedAt'=>['byteLength'=>19,'present'=>true,'sha256'=>$pins['timeSha256']]],
        'consumer'=>['state'=>'observed','sourceSha256'=>$pins['rawSha256'],'sourceFetchedAt'=>['byteLength'=>19,'present'=>true,'sha256'=>$pins['timeSha256']],
            'profile'=>['contentState'=>'ready','revision'=>$pins['revision'],'manualFieldCount'=>0,'sourceAbsentCount'=>1,'sourceAbsentSha256'=>$pins['sourceAbsentSha256']]],
        'gaps'=>['sourceMissing'=>['description'],'consumerMissing'=>['description'],'manualProtectedMissing'=>[],'possibleFillFields'=>[]]];
    $items=[];for($i=1;$i<=20;++$i){$x=$target;$x['hotelId']=$i===20?88750:900000+$i;$items[]=$x;}
    $data=['receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-profile-batch-read-v1','state'=>'completed_read_only','reason'=>null,'requestedCount'=>20,'observedCount'=>20,'heldCount'=>0,'possibleGapCount'=>0,
        'operationId'=>AnyTourHotelProfile88750EvidenceV1::PARENT_OPERATION,'sourceSha'=>AnyTourHotelProfile88750EvidenceV1::PARENT_SOURCE,'controlSha'=>AnyTourHotelProfile88750EvidenceV1::PARENT_CONTROL,
        'priceAuthority'=>false,'importAllowed'=>false,'itemsSha256'=>hash('sha256',pe_json($items)),'protectedBefore'=>$pins['protected'],'protectedAfter'=>$pins['protected'],
        'supplierCalls'=>0,'databaseWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0],'items'=>$items];
    $public=array_fill_keys(['index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'],str_repeat('c',64));
    return ['schema_version'=>1,'mode'=>'local-profile-batch-read-v1','status'=>'complete','operation_id'=>AnyTourHotelProfile88750EvidenceV1::PARENT_OPERATION,'source_sha'=>AnyTourHotelProfile88750EvidenceV1::PARENT_SOURCE,
        'production_before'=>$public,'production_after'=>$public,'production_unchanged'=>true,'supplier_calls'=>0,'database_writes'=>0,'profile_writes'=>0,'mapping_writes'=>0,'schema_writes'=>0,'lead_calls'=>0,'booking_calls'=>0,
        'local_profile_batch'=>['data'=>$data,'fileSha256'=>hash('sha256',pe_json($data)."\n"),'protectedBefore'=>$pins['protected'],'protectedAfter'=>$pins['protected']]];
}
function pe_prepare(array $payload):array{
    global $db,$pins,$parent;$raw=pe_json($payload);$hash=hash('sha256',$raw);$at='2026-10-26 01:00:00';
    $db->prepare("UPDATE catalog_hotel_details SET status='success',raw_json=?,source_hash=?,fetched_at=? WHERE hotel_id=88750")->execute([$raw,$hash,$at]);
    $normal=LocalTvCatalogV1::normalize($payload,88750);$db->prepare('UPDATE local_tv_hotels SET state=\'ready\',revision=3,content_json=?,source_json=?,source_sha256=?,source_fetched_at=?,source_absent_json=?,manual_json=\'{}\' WHERE id=88750')
        ->execute([LocalTvCatalogV1::json($normal['content']),$raw,$hash,$at,LocalTvCatalogV1::json($normal['absent'])]);
    $pins['rawSha256']=$hash;$pins['timeSha256']=hash('sha256',$at);$pins['sourceAbsentSha256']=hash('sha256',pe_json($normal['absent']));$parent=pe_parent($pins);
    $pins['parentTargetSha256']=hash('sha256',pe_json($parent['local_profile_batch']['data']['items'][19]));return $payload;
}
function pe_read(?array $withParent=null):array{global $db,$site,$parent,$pins;return AnyTourHotelProfile88750EvidenceV1::read($db,$site,$withParent??$parent,$pins);}
function pe_held(array $r,string $reason):void{pe_check($r['receipt']['state']==='held'&&$r['receipt']['reason']===$reason,'held '.$reason.' actual '.$r['receipt']['reason']);pe_check($r['evidence']===null&&$r['receipt']['blockCount']===0&&$r['receipt']['textBytes']===0&&$r['receipt']['evidenceSha256']===null,'held clears all text');}
function pe_snapshot():string{global $db;$all=[];foreach(['catalog_hotels','catalog_hotel_details','local_tv_hotels']as$table)$all[$table]=$db->query('SELECT * FROM '.$table.' ORDER BY '.($table==='catalog_hotel_details'?'hotel_id':'id'))->fetchAll();return hash('sha256',pe_json($all));}
try{
    $db->exec('CREATE TABLE catalog_hotels(id BIGINT PRIMARY KEY,name VARCHAR(255),country_id BIGINT,is_active TINYINT) ENGINE=InnoDB');
    $db->exec('CREATE TABLE catalog_hotel_details(hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),raw_json MEDIUMTEXT,source_hash CHAR(64),fetched_at DATETIME) ENGINE=InnoDB');
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($source.'/v2/data/migrations/20261010-local-tv-catalog.sql'))??'';foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]as$part)if(trim($part)!=='')$db->exec($part);
    $db->exec("INSERT INTO catalog_hotels VALUES(88750,'THE X BELEK',4,1)");$db->exec("INSERT INTO catalog_hotel_details(hotel_id,status) VALUES(88750,'success')");
    $db->exec("INSERT INTO local_tv_hotels(id,first_seen_at,last_seen_at,pending_since,discovery_json,content_json,manual_json,source_absent_json) VALUES(88750,'2026-10-26 01:00:00','2026-10-26 01:00:00','2026-10-26 01:00:00','{}','{}','{}','[]')");
    $identity=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();
    $pins=['rawSha256'=>str_repeat('a',64),'timeSha256'=>str_repeat('b',64),'revision'=>3,'sourceAbsentSha256'=>AnyTourHotelProfile88750EvidenceV1::SOURCE_ABSENT_SHA256,
        'databaseSha256'=>hash('sha256',pe_json(['database'=>$identity['selected_database'],'serverUuid'=>$identity['server_uuid']])),
        'protected'=>AnyTourHotelProfileCompletenessReadV1::protected($site)];
    $payload=['id'=>88750,'name'=>'THE X BELEK','country'=>['id'=>4,'name'=>'Турция'],
        'common'=>['place'=>'<p>Песчаный берег.</p>','build'=>'2023','repair'=>'2025','square'=>'Большая территория','latitude'=>36.8,'longitude'=>31.1,'address'=>'private address','phone'=>'+7 123 456 78 90','site'=>'https://private.example'],
        'images'=>['https://img.example/a.jpg'],'infrastructure'=>['beach'=>'Собственный пляж','pools'=>'Открытый бассейн'],
        'services'=>['available'=>'Услуги для гостей','inRoom'=>'Балкон','free'=>'Wi-Fi'],
        'meals'=>['food'=>'Шведский стол','contactEmail'=>'private@example.test','nested'=>['private'=>'secret nested']],
        'descriptions'=>['overview'=>'Отель в Белеке.','website'=>'https://private.example','phones'=>'private phone','nested'=>['private'=>'secret nested']],
        'roomTypes'=>'FAMILY SEA VIEW'];
    pe_prepare($payload);pe_check($pins['sourceAbsentSha256']===AnyTourHotelProfile88750EvidenceV1::SOURCE_ABSENT_SHA256,'success fixture only description absent');
    $before=pe_snapshot();$done=pe_read();$r=$done['receipt'];$e=$done['evidence'];
    pe_check($r['state']==='completed_read_only','successful evidence');pe_check($db->denied===1&&!$db->inTransaction(),'native READ ONLY denied DML');pe_check($before===pe_snapshot(),'table bytes unchanged');
    pe_check($r['source']==='anytour-hotel-profile-88750-evidence-v1'&&$r['parentResultSha256']===AnyTourHotelProfile88750EvidenceV1::PARENT_SHA256&&$r['parentTargetSha256']===AnyTourHotelProfile88750EvidenceV1::PARENT_TARGET_SHA256,'literal lineage receipts');
    pe_check(count($e['blocks'])===11&&$r['blockCount']===11,'bounded exact useful blocks');
    $by=[];foreach($e['blocks']as$b){$by[$b['sourcePath']]=$b;pe_check($b['byteLength']===strlen($b['text'])&&$b['sha256']===hash('sha256',$b['text']),'each exact text provenance');}
    pe_check($by['$.common.place']['text']==='Песчаный берег.'&&$by['$.common.place']['consumerComparison']['state']==='equal','existing normalizer text');
    pe_check($by['$.descriptions.overview']['consumerPath']===null&&$by['$.descriptions.overview']['consumerComparison']===['state'=>'not_projected','sha256'=>null,'byteLength'=>null],'null projection explicit');
    pe_check($by['$.meals.food']['consumerPath']==='hotelInformation.meals.food'&&$by['$.meals.food']['consumerComparison']['state']==='equal','exact scalar projection');
    $export=pe_json($done);foreach(['private@example','private.example','private address','secret nested','FAMILY SEA VIEW','source_json','raw_json']as$private)pe_check(!str_contains($export,$private),'no private/nested/raw dump');
    pe_check(!array_key_exists('description',$e)&&!str_contains($export,'aggregateDescription'),'no synthesized description');
    foreach(['supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts']as$k)pe_check($r[$k]===0,'typed0 '.$k);
    $done['receipt']+=['operationId'=>AnyTourHotelProfile88750EvidenceV1::OPERATION,'sourceSha'=>AnyTourHotelProfile88750EvidenceV1::SOURCE,'controlSha'=>str_repeat('b',40)];fwrite(STDOUT,'PROFILE_88750_EVIDENCE_PAYLOAD='.base64_encode(pe_json($done))."\n");
    $bad=$parent;$bad['local_profile_batch']['data']['receipt']['controlSha']=str_repeat('d',40);$queries=$db->queries;pe_held(pe_read($bad),'parent_invalid');pe_check($db->queries===$queries,'invalid parent before DB calls');
    $db->beginTransaction();pe_held(pe_read(),'caller_transaction');pe_check($db->inTransaction(),'caller TX preserved');$db->rollBack();
    $db->exec("UPDATE catalog_hotel_details SET source_hash=REPEAT('0',64) WHERE hotel_id=88750");pe_held(pe_read(),'source_integrity');pe_prepare($payload);
    $changed=$payload;$changed['id']=475;pe_prepare($payload);$raw=pe_json($changed);$pins['rawSha256']=hash('sha256',$raw);$parent=pe_parent($pins);$pins['parentTargetSha256']=hash('sha256',pe_json($parent['local_profile_batch']['data']['items'][19]));
    $db->prepare('UPDATE catalog_hotel_details SET raw_json=?,source_hash=? WHERE hotel_id=88750')->execute([$raw,$pins['rawSha256']]);pe_held(pe_read(),'source_identity');pe_prepare($payload);
    $changed=$payload;$changed['common']['place']='Changed content';$raw=pe_json($changed);$db->prepare('UPDATE catalog_hotel_details SET raw_json=?,source_hash=? WHERE hotel_id=88750')->execute([$raw,hash('sha256',$raw)]);pe_held(pe_read(),'source_drift');pe_prepare($payload);
    $db->exec('UPDATE local_tv_hotels SET revision=4 WHERE id=88750');pe_held(pe_read(),'consumer_drift');pe_prepare($payload);
    $db->exec('UPDATE local_tv_hotels SET manual_json=\'{"description":""}\' WHERE id=88750');pe_held(pe_read(),'consumer_drift');pe_prepare($payload);
    $content=LocalTvCatalogV1::normalize($payload,88750)['content'];$content['place']='Previously stored place';$db->prepare('UPDATE local_tv_hotels SET content_json=? WHERE id=88750')->execute([LocalTvCatalogV1::json($content)]);$different=pe_read();pe_check($different['receipt']['state']==='completed_read_only'&&$different['evidence']['blocks'][0]['consumerComparison']['state']==='different','effective mismatch explicit');pe_prepare($payload);
    unset($content['place']);$db->prepare('UPDATE local_tv_hotels SET content_json=? WHERE id=88750')->execute([LocalTvCatalogV1::json($content)]);$missing=pe_read();pe_check($missing['evidence']['blocks'][0]['consumerComparison']['state']==='missing','projected absent explicit');pe_prepare($payload);
    $huge=$payload;$huge['common']['place']=str_repeat('x',8193);pe_prepare($huge);pe_held(pe_read(),'evidence_size_limit');pe_prepare($payload);
    $many=$payload;$many['descriptions']=[];for($i=0;$i<33;++$i)$many['descriptions']['fact'.$i]='A fact';pe_prepare($many);pe_held(pe_read(),'evidence_size_limit');pe_prepare($payload);
    $empty=$payload;foreach(['build','repair','square']as$key)unset($empty['common'][$key]);$empty['common']['place']='https://private.example';$empty['infrastructure']=['nested'=>['x'=>'retained']];$empty['services']=['nested'=>['x'=>'retained']];$empty['meals']=['nested'=>['x'=>'retained']];$empty['descriptions']=[];pe_prepare($empty);pe_held(pe_read(),'no_evidence');pe_prepare($payload);
    $wrapped=[$payload];pe_prepare($wrapped);$wrappedResult=pe_read();pe_check($wrappedResult['receipt']['state']==='completed_read_only'&&str_starts_with($wrappedResult['evidence']['blocks'][0]['sourcePath'],'$[0].'),'exact singleton wrapper provenance');pe_prepare($payload);
    $db->prepare('UPDATE catalog_hotel_details SET raw_json=? WHERE hotel_id=88750')->execute([str_repeat('x',1048577)]);pe_held(pe_read(),'row_size_limit');pe_prepare($payload);
    $db->exec('ALTER TABLE catalog_hotel_details ENGINE=MyISAM');pe_held(pe_read(),'schema_unavailable');$db->exec('ALTER TABLE catalog_hotel_details ENGINE=InnoDB');
    $db->afterRollback=static function()use($site){file_put_contents($site.'/config.php','drift after snapshot');};pe_held(pe_read(),'protected_drift');file_put_contents($site.'/config.php','native evidence fixture config');
    $db->afterRollback=static function()use($setup){$setup->exec('UPDATE local_tv_hotels SET revision=4 WHERE id=88750');};pe_held(pe_read(),'consumer_drift');pe_prepare($payload);
    fwrite(STDOUT,'LOCAL_PROFILE_88750_EVIDENCE_OK checks='.$checks." database=NATIVE_MYSQL\n");
}finally{if($db->inTransaction())$db->rollBack();$db=null;$setup=null;$root->exec('DROP DATABASE `'.$name.'`');unlink($site.'/config.php');unlink($site.'/data/db-v1.php');rmdir($site.'/data');rmdir($site);}
