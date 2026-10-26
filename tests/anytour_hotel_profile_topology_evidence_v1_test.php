<?php
declare(strict_types=1);
$source=(string)getenv('LOCAL_PROFILE_SOURCE_ROOT');
if($source===''||realpath($source)!==$source)throw new RuntimeException('Immutable source required');
require_once __DIR__.'/../scripts/catalog/anytour_hotel_profile_topology_evidence_v1.php';
$server=(string)getenv('LOCAL_PROFILE_TEST_DSN');
if(!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server))throw new RuntimeException('Dedicated loopback fixture required');
$password=(string)getenv('LOCAL_STAY_TEST_PASSWORD');$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO($server,'root',$password,$options);$name='profile_topology_'.bin2hex(random_bytes(6));$admin->exec('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4');
$dsn=str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server);$setup=new PDO($dsn,'root',$password,$options);
$temp=sys_get_temp_dir().'/profile-topology-'.bin2hex(random_bytes(8));$stage=$temp.'/stage';$site=$temp.'/anytoour.ru';
foreach([$stage.'/v2/data',$stage.'/provenance',$site.'/data']as$dir)mkdir($dir,0700,true);
foreach(['db-v1.php','local-tv-catalog-v1.php','hotel-details-v1.php','hotel-presentation-read-v1.php']as$file)copy($source.'/v2/data/'.$file,$stage.'/v2/data/'.$file);
copy(__DIR__.'/../v2/data/db-v1.php',$stage.'/provenance/main-db-v1.php');copy(__DIR__.'/../v2/data/db-v1.php',$site.'/data/db-v1.php');
$configBody='<?php $GLOBALS["topology_fixture_selected"]=true; define("ANYTOUR_DATA_DSN",'.var_export($dsn,true).'); define("ANYTOUR_DATA_DB_USER","root"); define("ANYTOUR_DATA_DB_PASSWORD",'.var_export($password,true).');';
file_put_contents($site.'/config.php',$configBody);$_SERVER['DOCUMENT_ROOT']=$site;
require_once $stage.'/v2/data/local-tv-catalog-v1.php';
$contract=AnyTourHotelProfileTopologyEvidenceV1::contractFile((string)realpath(__DIR__.'/../scripts/catalog/fixtures/local-profile-topology-evidence-20261026.json'));
final class TopologyBindingStatement extends PDOStatement{
    public function __construct(private array $row){}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->row;}
}
final class TopologyProbePdo extends PDO{
    public int $denied=0;public int $bindings=0;public ?Closure $afterRollback=null;public ?string $drift=null;
    public function exec(string $sql):int|false{
        $r=parent::exec($sql);
        if($sql==='START TRANSACTION READ ONLY')try{parent::exec('UPDATE catalog_hotels SET is_active=is_active WHERE id=88750');throw new RuntimeException('READ_ONLY_NOT_ENFORCED');}
        catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1792)throw $e;++$this->denied;}return $r;
    }
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{
        $s=$fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);
        if(str_contains($query,'CURRENT_USER()')&&++$this->bindings>1&&$this->drift!==null){$row=$s->fetch(PDO::FETCH_ASSOC);$row[$this->drift].=' fixture drift';return new TopologyBindingStatement($row);}return $s;
    }
    public function rollBack():bool{$ok=parent::rollBack();if($this->afterRollback!==null){$call=$this->afterRollback;$this->afterRollback=null;$call();}return $ok;}
}
$checks=0;$factoryCalls=0;$lastDb=null;$nextDrift=null;$nextAfter=null;
function pt_check(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('PROFILE_TOPOLOGY_CHECK:'.$label);}
function pt_json(mixed $v):string{return AnyTourHotelProfileTopologyEvidenceV1::json($v);}
function pt_sort(array $v):array{return json_decode(pt_json($v),true,64,JSON_THROW_ON_ERROR);}
function pt_failed():array{
    return pt_sort(['booking_calls'=>0,'database_writes'=>0,'lead_calls'=>0,
        'local_profile_88750_evidence'=>['data'=>null,'fileSha256'=>null,'protectedAfter'=>null,
            'protectedBefore'=>['config.php'=>AnyTourHotelProfileTopologyEvidenceV1::ROOT_CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelProfileTopologyEvidenceV1::ROOT_HELPER_SHA256]],
        'mapping_writes'=>0,'mode'=>'local-profile-88750-evidence-v1','operation_id'=>AnyTourHotelProfileTopologyEvidenceV1::FAILED_OPERATION,
        'production_before'=>['index.php'=>'80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f','v2/api-v2.php'=>null,'v2/index.php'=>null,'v2/lead-adapter-v2.php'=>null],
        'profile_writes'=>0,'reason'=>'local_profile_88750_private_drift','schema_version'=>1,'schema_writes'=>0,
        'source_sha'=>AnyTourHotelProfileTopologyEvidenceV1::SOURCE,'status'=>'unknown_no_replay','supplier_calls'=>0]);
}
function pt_parent(array &$pins):array{
    $target=['hotelId'=>88750,'countryId'=>4,'recordSha256'=>str_repeat('a',64),'state'=>'observed','reason'=>null,
        'source'=>['description'=>['present'=>false,'byteLength'=>0,'sha256'=>null],'sourceSha256'=>$pins['rawSha256'],'sourceFetchedAt'=>['sha256'=>$pins['timeSha256']]],
        'consumer'=>['state'=>'observed','sourceSha256'=>$pins['rawSha256'],'sourceFetchedAt'=>['sha256'=>$pins['timeSha256']],
            'profile'=>['contentState'=>'ready','revision'=>3,'manualFieldCount'=>0,'sourceAbsentCount'=>1,'sourceAbsentSha256'=>$pins['sourceAbsentSha256']]],
        'gaps'=>['consumerMissing'=>['description'],'manualProtectedMissing'=>[],'possibleFillFields'=>[],'sourceMissing'=>['description']]];
    $items=[];for($i=0;$i<20;++$i){$x=$target;$x['hotelId']=$i===19?88750:800000+$i;$items[]=$x;}
    $historical=['config.php'=>AnyTourHotelProfileTopologyEvidenceV1::ROOT_CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelProfileTopologyEvidenceV1::STAGED_HELPER_SHA256];
    $data=['receipt'=>['source'=>'anytour-hotel-profile-batch-read-v1','state'=>'completed_read_only','reason'=>null,'schemaVersion'=>1,
        'controlSha'=>'e2a1074e3c7c335377024e88951467479da8331b','sourceSha'=>'1d0e529e3fc0666783db1454c0765d3c16d63843','operationId'=>AnyTourHotelProfileTopologyEvidenceV1::PARENT_OPERATION,
        'requestedCount'=>20,'observedCount'=>20,'heldCount'=>0,'possibleGapCount'=>0,'itemsSha256'=>hash('sha256',pt_json($items)),
        'supplierCalls'=>0,'databaseWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,
        'priceAuthority'=>false,'importAllowed'=>false,'protectedBefore'=>$historical,'protectedAfter'=>$historical],'items'=>$items];
    $public=['index.php'=>str_repeat('c',64)];$parent=pt_sort(['schema_version'=>1,'mode'=>'local-profile-batch-read-v1',
        'operation_id'=>AnyTourHotelProfileTopologyEvidenceV1::PARENT_OPERATION,'source_sha'=>'1d0e529e3fc0666783db1454c0765d3c16d63843','status'=>'complete',
        'booking_calls'=>0,'database_writes'=>0,'lead_calls'=>0,'mapping_writes'=>0,'profile_writes'=>0,'schema_writes'=>0,'supplier_calls'=>0,
        'production_unchanged'=>true,'production_before'=>$public,'production_after'=>$public,
        'local_profile_batch'=>['data'=>$data,'fileSha256'=>hash('sha256',pt_json($data)."\n"),'protectedBefore'=>$historical,'protectedAfter'=>$historical]]);
    $pins['parentSha256']=hash('sha256',pt_json($parent));$pins['parentTargetSha256']=hash('sha256',pt_json($parent['local_profile_batch']['data']['items'][19]));return $parent;
}
function pt_prepare(array $payload):void{
    global $setup,$pins,$parent;$raw=pt_json($payload);$at='2026-10-26 01:00:00';$sha=hash('sha256',$raw);$n=LocalTvCatalogV1::normalize($payload,88750);
    $setup->prepare("UPDATE catalog_hotel_details SET status='success',raw_json=?,source_hash=?,fetched_at=? WHERE hotel_id=88750")->execute([$raw,$sha,$at]);
    $setup->prepare("UPDATE local_tv_hotels SET state='ready',revision=3,content_json=?,source_json=?,source_sha256=?,source_fetched_at=?,source_absent_json=?,manual_json='{}' WHERE id=88750")
        ->execute([LocalTvCatalogV1::json($n['content']),$raw,$sha,$at,LocalTvCatalogV1::json($n['absent'])]);
    $pins['rawSha256']=$sha;$pins['timeSha256']=hash('sha256',$at);$pins['sourceAbsentSha256']=hash('sha256',pt_json($n['absent']));$parent=pt_parent($pins);
}
function pt_run(?array $f=null,?array $p=null):array{
    global $site,$stage,$contract,$failed,$parent,$pins,$factoryCalls,$lastDb,$nextDrift,$nextAfter,$options;
    return AnyTourHotelProfileTopologyEvidenceV1::run($site,$stage,$contract,$f??$failed,$p??$parent,$pins,
        static function(array $config)use(&$factoryCalls,&$lastDb,&$nextDrift,&$nextAfter,$options):PDO{
            ++$factoryCalls;$lastDb=new TopologyProbePdo($config['dsn'],$config['user'],$config['password'],$options);
            $lastDb->drift=$nextDrift;$lastDb->afterRollback=$nextAfter;$nextDrift=null;$nextAfter=null;return $lastDb;
        });
}
function pt_held(array $r,string $reason):void{
    pt_check($r['receipt']['state']==='held'&&$r['receipt']['reason']===$reason,'held '.$reason.' got '.$r['receipt']['reason']);
    pt_check($r['evidence']===null&&$r['receipt']['blockCount']===0&&$r['receipt']['textBytes']===0&&$r['receipt']['evidenceSha256']===null,'all text cleared');
}
function pt_snapshot():string{global $setup;$rows=[];foreach(['catalog_hotels','catalog_hotel_details','local_tv_hotels']as$t)$rows[$t]=$setup->query('SELECT * FROM '.$t.' ORDER BY '.($t==='catalog_hotel_details'?'hotel_id':'id'))->fetchAll();return hash('sha256',pt_json($rows));}
function pt_remove(string $p):void{if(is_link($p)||is_file($p)){unlink($p);return;}if(is_dir($p)){foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')pt_remove($p.'/'.$n);rmdir($p);}}
try{
    $setup->exec('CREATE TABLE catalog_hotels(id BIGINT PRIMARY KEY,name VARCHAR(255),country_id BIGINT,is_active TINYINT) ENGINE=InnoDB');
    $setup->exec('CREATE TABLE catalog_hotel_details(hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),raw_json MEDIUMTEXT,source_hash CHAR(64),fetched_at DATETIME) ENGINE=InnoDB');
    $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($source.'/v2/data/migrations/20261010-local-tv-catalog.sql'))??'';
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]as$part)if(trim($part)!=='')$setup->exec($part);
    $setup->exec("INSERT INTO catalog_hotels VALUES(88750,'THE X BELEK',4,1)");$setup->exec("INSERT INTO catalog_hotel_details(hotel_id,status) VALUES(88750,'success')");
    $setup->exec("INSERT INTO local_tv_hotels(id,first_seen_at,last_seen_at,pending_since,discovery_json,content_json,manual_json,source_absent_json) VALUES(88750,'2026-10-26 01:00:00','2026-10-26 01:00:00','2026-10-26 01:00:00','{}','{}','{}','[]')");
    $binding=$setup->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();
    $pins=['databaseSha256'=>hash('sha256',pt_json(['database'=>$binding['selected_database'],'serverUuid'=>$binding['server_uuid']])),
        'rootConfigSha256'=>hash('sha256',$configBody),'rawSha256'=>str_repeat('a',64),'timeSha256'=>str_repeat('b',64),'sourceAbsentSha256'=>AnyTourHotelProfileTopologyEvidenceV1::ABSENT_SHA256];
    $failed=pt_failed();pt_check(hash('sha256',pt_json($failed))===AnyTourHotelProfileTopologyEvidenceV1::FAILED_SHA256,'full actual immutable failed832 hash');
    $payload=['id'=>88750,'name'=>'THE X BELEK','country'=>['id'=>4,'name'=>'Турция'],
        'common'=>['place'=>'<p>Расположение отеля.</p>','build'=>'2023','repair'=>'2025','square'=>'Территория отеля','latitude'=>36.8,'longitude'=>31.1,'address'=>'private address','phone'=>'+7 123 456 78 90','site'=>'https://private.example'],
        'images'=>['https://img.example/a.jpg'],'infrastructure'=>['beach'=>'Собственный пляж','pools'=>'Бассейн'],
        'services'=>['available'=>'Услуги для гостей','inRoom'=>'Балкон','free'=>'Wi-Fi'],'meals'=>['food'=>'Шведский стол','contactEmail'=>'private@example.test'],
        'descriptions'=>['overview'=>'Отель в Белеке.','website'=>'https://private.example','nested'=>['private'=>'nested secret']],'roomTypes'=>'FAMILY SEA VIEW'];
    pt_prepare($payload);pt_check($pins['sourceAbsentSha256']===AnyTourHotelProfileTopologyEvidenceV1::ABSENT_SHA256,'only description source-absent');
    $trap=$stage.'/v2/config.php';file_put_contents($trap,'<?php file_put_contents('.var_export($temp.'/TRAP_EXECUTED',true).',"bad");');
    $calls=$factoryCalls;$held=pt_run();pt_held($held,'staged_config_present');pt_check($factoryCalls===$calls&&!file_exists($temp.'/TRAP_EXECUTED')&&!$held['receipt']['phases']['stagedHelperAttempted'],'adjacent trap not executed before PDO');unlink($trap);
    symlink($temp.'/missing',$trap);pt_held(pt_run(),'staged_config_present');unlink($trap);
    putenv('ANYTOUR_DATA_UNRECOGNIZED_OVERRIDE=1');pt_held(pt_run(),'environment_override');putenv('ANYTOUR_DATA_UNRECOGNIZED_OVERRIDE');
    $_SERVER['DOCUMENT_ROOT']=$temp;pt_held(pt_run(),'document_root');$_SERVER['DOCUMENT_ROOT']=$site;
    $bad=$failed;$bad['reason']='other';$calls=$factoryCalls;pt_held(pt_run($bad),'parent_invalid');pt_check($factoryCalls===$calls,'parents before loader and connection');
    $before=pt_snapshot();$done=pt_run();$r=$done['receipt'];$e=$done['evidence'];
    pt_check($r['state']==='completed_read_only','material topology-bound evidence success');pt_check($before===pt_snapshot(),'all table bytes unchanged');
    pt_check(($GLOBALS['topology_fixture_selected']??false)===true&&$done['topology']['selectedConfigSha256']===hash('sha256',$configBody),'real fallback loaded exact ROOT config');
    pt_check(!in_array($site.'/data/db-v1.php',get_included_files(),true)&&!in_array($stage.'/provenance/main-db-v1.php',get_included_files(),true),'ROOT and evidence helpers never executed');
    pt_check($lastDb instanceof TopologyProbePdo&&$lastDb->denied===1&&!$lastDb->inTransaction(),'actual READ ONLY rejects DML');
    foreach($r['phases']as$key=>$value)pt_check($value===($key!=='rootHelperExecuted'),'truthful successful phase '.$key);
    foreach(['database','account','role']as$key)pt_check($r[$key.'Sha256Before']===$r[$key.'Sha256After']&&preg_match('/^[a-f0-9]{64}$/D',$r[$key.'Sha256Before'])===1,'stable current '.$key);
    pt_check($r['oldAccountIdentityKnown']===false,'no historic continuity claimed');pt_check(count($e['blocks'])===11&&$r['blockCount']===11,'bounded useful text');
    $blocks=[];foreach($e['blocks']as$b){$blocks[$b['sourcePath']]=$b;pt_check(hash('sha256',$b['text'])===$b['sha256']&&strlen($b['text'])===$b['byteLength'],'text provenance');}
    pt_check($blocks['$.common.place']['text']==='Расположение отеля.'&&$blocks['$.common.place']['consumerComparison']['state']==='equal','existing normalizer and actual consumer');
    pt_check($blocks['$.descriptions.overview']['consumerComparison']['state']==='not_projected','unprojected explicit');
    $export=pt_json($done);foreach([$name,$password,'private address','private.example','private@example','nested secret','FAMILY SEA VIEW','ANYTOUR_DATA_DSN']as$private)if($private!=='')pt_check(!str_contains($export,$private),'no private/raw/qualifier dump');
    foreach(['supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts']as$key)pt_check($r[$key]===0,'typed0 '.$key);
    $done['receipt']+=['operationId'=>AnyTourHotelProfileTopologyEvidenceV1::OPERATION,'sourceSha'=>AnyTourHotelProfileTopologyEvidenceV1::SOURCE,'controlSha'=>str_repeat('b',40)];
    fwrite(STDOUT,'PROFILE_TOPOLOGY_EVIDENCE_PAYLOAD='.base64_encode(pt_json($done))."\n");
    $setup->beginTransaction();
    $caller=AnyTourHotelProfileTopologyEvidenceV1::run($site,$stage,$contract,$failed,$parent,$pins,static fn(array $config):PDO=>$setup);
    pt_held($caller,'caller_transaction');pt_check($setup->inTransaction(),'caller transaction preserved');$setup->rollBack();
    $old=$pins['databaseSha256'];$pins['databaseSha256']=str_repeat('0',64);$wrong=pt_run();pt_held($wrong,'database_identity');pt_check(!$wrong['receipt']['phases']['appDataRead'],'wrong DB before application rows');$pins['databaseSha256']=$old;
    $nextDrift='account';pt_held(pt_run(),'database_binding');$nextDrift='roles';pt_held(pt_run(),'database_binding');
    $setup->exec("UPDATE local_tv_hotels SET manual_json='{\"description\":\"\"}' WHERE id=88750");pt_held(pt_run(),'consumer_drift');pt_prepare($payload);
    $setup->exec("UPDATE catalog_hotel_details SET source_hash=REPEAT('0',64) WHERE hotel_id=88750");pt_held(pt_run(),'source_integrity');pt_prepare($payload);
    $nextAfter=static function()use($site){file_put_contents($site.'/config.php','changed');};pt_held(pt_run(),'topology_drift');file_put_contents($site.'/config.php',$configBody);
    $nextAfter=static function()use($trap){file_put_contents($trap,'<?php throw new RuntimeException("trap must not execute");');};pt_held(pt_run(),'topology_drift');unlink($trap);
    $huge=$payload;$huge['common']['place']=str_repeat('x',8193);pt_prepare($huge);pt_held(pt_run(),'evidence_size_limit');pt_prepare($payload);
    $empty=$payload;foreach(['build','repair','square']as$key)unset($empty['common'][$key]);$empty['common']['place']='https://private.example';$empty['infrastructure']=['nested'=>['x'=>'fact']];$empty['services']=['nested'=>['x'=>'fact']];$empty['meals']=['nested'=>['x'=>'fact']];$empty['descriptions']=[];pt_prepare($empty);pt_held(pt_run(),'no_evidence');pt_prepare($payload);
    $setup->exec('ALTER TABLE catalog_hotel_details ENGINE=MyISAM');pt_held(pt_run(),'schema_unavailable');$setup->exec('ALTER TABLE catalog_hotel_details ENGINE=InnoDB');
    fwrite(STDOUT,'LOCAL_PROFILE_TOPOLOGY_EVIDENCE_OK checks='.$checks." database=NATIVE_MYSQL\n");
}finally{
    if($lastDb instanceof PDO&&$lastDb->inTransaction())$lastDb->rollBack();$lastDb=null;$setup=null;$admin->exec('DROP DATABASE '.$name);pt_remove($temp);
}
