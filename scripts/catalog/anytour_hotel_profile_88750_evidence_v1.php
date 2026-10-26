<?php
/** Exact retained THE X BELEK evidence blocks; read-only, without synthesized description. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/anytour_hotel_profile_completeness_read_v1.php';

final class AnyTourHotelProfile88750EvidenceV1
{
    public const SOURCE='ccf5a1b1c8dcc9d3b796b40c85aca0f9407eb1a1';
    public const OPERATION='int-andromeda-local-profile-88750-evidence-20261026-v1';
    public const PARENT_OPERATION='int-andromeda-local-profile-batch-read-20261026-v1';
    public const PARENT_SOURCE='1d0e529e3fc0666783db1454c0765d3c16d63843';
    public const PARENT_CONTROL='e2a1074e3c7c335377024e88951467479da8331b';
    public const PARENT_BYTES=36952;
    public const PARENT_SHA256='ec2267edaebfa24f3f1b6d72b51834a047e063e1e2fbb86ba6988d34f58b22d8';
    public const PARENT_TARGET_SHA256='2022f9f9592c1f27cdefb3a9370c9ee84cee3de4139d5a33f5428b713e899a50';
    public const RAW_SHA256='f64669c666ea20e89bc3dc262c4559e2ff133da75f6c3c7a53e9104f451456a4';
    public const TIME_SHA256='bdbf170ed62868c1418d2b39610f8c042d6717fd84f968b516769c8f5073c45e';
    public const SOURCE_ABSENT_SHA256='8f515b15e10dfa8d9181889456d432e3b6b5d40458b1c41fbd864e9fa628a4d2';
    public const HOTEL_ID=88750;
    public const COUNTRY_ID=4;
    public const NAME='THE X BELEK';
    public const CONSUMER_REVISION=3;
    public const MAX_BYTES=262144;
    private const FIXED_PATHS=[
        'common.place'=>'place','common.build'=>'build','common.repair'=>'repair','common.square'=>'square',
        'infrastructure.beach'=>'hotelInformation.infrastructure.beach','infrastructure.territory'=>'hotelInformation.infrastructure.territory',
        'infrastructure.pools'=>'hotelInformation.infrastructure.pools','infrastructure.pool'=>'hotelInformation.infrastructure.pool',
        'infrastructure.sport'=>'hotelInformation.infrastructure.sport','infrastructure.internet'=>'hotelInformation.infrastructure.internet',
        'services.available'=>'hotelInformation.services.available','services.inRoom'=>'hotelInformation.services.inRoom',
        'services.child'=>'hotelInformation.services.child','services.animation'=>'hotelInformation.services.animation',
        'services.free'=>'hotelInformation.services.free','services.servicesPay'=>'hotelInformation.services.servicesPay',
    ];
    public static function json(mixed $v):string{return AnyTourHotelProfileCompletenessReadV1::json($v);}
    private static function digest(mixed $v):string{return hash('sha256',self::json($v));}
    private static function need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
    private static function sha(mixed $v):bool{return is_string($v)&&preg_match('/^[a-f0-9]{64}$/D',$v)===1;}
    private static function field(array $v,string $path,bool &$exists):mixed{
        foreach(explode('.',$path) as $key){if(!is_array($v)||!array_key_exists($key,$v)){$exists=false;return null;}$v=$v[$key];}
        $exists=true;return $v;
    }
    /** Strictly consume the retained immutable parent before runtime creates a PDO. */
    public static function parentFile(string $path):array{
        self::need(is_file($path)&&!is_link($path)&&realpath($path)===$path&&filesize($path)===self::PARENT_BYTES
            &&hash_file('sha256',$path)===self::PARENT_SHA256,'parent_invalid');
        $p=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);self::need(is_array($p),'parent_invalid');
        self::parentTarget($p);return $p;
    }
    /** Fixture overrides are supplied explicitly by the native test, never by the CLI. */
    private static function pins(array $override):array{
        $p=['rawSha256'=>self::RAW_SHA256,'timeSha256'=>self::TIME_SHA256,'revision'=>self::CONSUMER_REVISION,
            'sourceAbsentSha256'=>self::SOURCE_ABSENT_SHA256,'parentTargetSha256'=>self::PARENT_TARGET_SHA256,
            'databaseSha256'=>AnyTourHotelProfileCompletenessReadV1::DATABASE_SHA256,
            'protected'=>['config.php'=>AnyTourHotelProfileCompletenessReadV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256]];
        self::need(array_diff(array_keys($override),array_keys($p))===[],'fixture_pins');
        $p=array_replace($p,$override);foreach(['rawSha256','timeSha256','sourceAbsentSha256','parentTargetSha256','databaseSha256'] as $k)self::need(self::sha($p[$k]),'fixture_pins');
        self::need(is_int($p['revision'])&&$p['revision']>0&&is_array($p['protected'])&&array_keys($p['protected'])===['config.php','data/db-v1.php']
            &&self::sha($p['protected']['config.php'])&&self::sha($p['protected']['data/db-v1.php']),'fixture_pins');return $p;
    }
    public static function parentTarget(array $p,array $override=[]):array{
        $pins=self::pins($override);$outer=$p['local_profile_batch']??null;$data=$outer['data']??null;$r=$data['receipt']??null;$items=$data['items']??null;
        self::need(($p['schema_version']??null)===1&&($p['mode']??null)==='local-profile-batch-read-v1'&&($p['status']??null)==='complete'
            &&($p['operation_id']??null)===self::PARENT_OPERATION&&($p['source_sha']??null)===self::PARENT_SOURCE
            &&is_array($r)&&is_array($items)&&array_is_list($items)&&count($items)===20
            &&($r['source']??null)==='anytour-hotel-profile-batch-read-v1'&&($r['state']??null)==='completed_read_only'&&array_key_exists('reason',$r)&&$r['reason']===null
            &&($r['schemaVersion']??null)===1&&($r['operationId']??null)===self::PARENT_OPERATION&&($r['sourceSha']??null)===self::PARENT_SOURCE
            &&($r['controlSha']??null)===self::PARENT_CONTROL&&($r['requestedCount']??null)===20&&($r['observedCount']??null)===20
            &&($r['heldCount']??null)===0&&($r['possibleGapCount']??null)===0&&($r['priceAuthority']??null)===false&&($r['importAllowed']??null)===false
            &&($r['itemsSha256']??null)===self::digest($items)&&($outer['fileSha256']??null)===hash('sha256',self::json($data)."\n"),'parent_invalid');
        foreach(['supplier_calls','database_writes','profile_writes','mapping_writes','schema_writes','lead_calls','booking_calls'] as $k)self::need(($p[$k]??null)===0,'parent_invalid');
        foreach(['supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts'] as $k)self::need(($r[$k]??null)===0,'parent_invalid');
        self::need(($p['production_unchanged']??null)===true&&is_array($p['production_before']??null)&&$p['production_before']===($p['production_after']??null)
            &&($r['protectedBefore']??null)===($r['protectedAfter']??false)&&($r['protectedBefore']??null)===($outer['protectedBefore']??false)
            &&($r['protectedBefore']??null)===($outer['protectedAfter']??false),'parent_invalid');
        $target=null;$seen=[];foreach($items as $item){self::need(is_array($item)&&is_int($item['hotelId']??null)&&!isset($seen[$item['hotelId']])
                &&($item['state']??null)==='observed'&&array_key_exists('reason',$item)&&$item['reason']===null,'parent_invalid');$seen[$item['hotelId']]=true;
            if($item['hotelId']===self::HOTEL_ID)$target=$item;}
        self::need(is_array($target)&&self::digest($target)===$pins['parentTargetSha256']&&($target['countryId']??null)===self::COUNTRY_ID
            &&($target['source']['sourceSha256']??null)===$pins['rawSha256']&&($target['source']['sourceFetchedAt']['sha256']??null)===$pins['timeSha256']
            &&self::digest($target['source']['description']??null)===self::digest(['byteLength'=>0,'present'=>false,'sha256'=>null])
            &&($target['consumer']['state']??null)==='observed'&&($target['consumer']['sourceSha256']??null)===$pins['rawSha256']
            &&($target['consumer']['sourceFetchedAt']['sha256']??null)===$pins['timeSha256']&&($target['consumer']['profile']['contentState']??null)==='ready'
            &&($target['consumer']['profile']['revision']??null)===$pins['revision']&&($target['consumer']['profile']['manualFieldCount']??null)===0
            &&($target['consumer']['profile']['sourceAbsentCount']??null)===1&&($target['consumer']['profile']['sourceAbsentSha256']??null)===$pins['sourceAbsentSha256']
            &&($target['gaps']['sourceMissing']??null)===['description']&&($target['gaps']['consumerMissing']??null)===['description']
            &&($target['gaps']['manualProtectedMissing']??null)===[]&&($target['gaps']['possibleFillFields']??null)===[],'parent_invalid');return $target;
    }
    private static function database(PDO $db):string{
        $r=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($r)&&is_string($r['selected_database']??null)&&$r['selected_database']!==''&&strlen($r['selected_database'])<=64
            &&is_string($r['server_uuid']??null)&&preg_match('/^[a-f0-9-]{36}$/Di',$r['server_uuid'])===1,'database_identity');
        return self::digest(['database'=>$r['selected_database'],'serverUuid'=>$r['server_uuid']]);
    }
    private static function tables(PDO $db):void{
        $rows=$db->query("SELECT TABLE_NAME,TABLE_TYPE,ENGINE FROM information_schema.TABLES WHERE BINARY TABLE_SCHEMA=BINARY DATABASE() AND TABLE_NAME IN ('catalog_hotels','catalog_hotel_details','local_tv_hotels') ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
        self::need(count($rows)===3,'schema_unavailable');foreach($rows as $r)self::need($r['TABLE_TYPE']==='BASE TABLE'&&$r['ENGINE']==='InnoDB','schema_unavailable');
    }
    private static function source(PDO $db,array $pins):array{
        $catalog=$db->query('SELECT id,name,country_id,is_active FROM catalog_hotels WHERE id=88750 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($catalog)&&(int)$catalog['id']===self::HOTEL_ID&&(int)$catalog['country_id']===self::COUNTRY_ID
            &&$catalog['name']===self::NAME&&(int)$catalog['is_active']===1,'catalog_identity');
        $size=$db->query("SELECT OCTET_LENGTH(raw_json) FROM catalog_hotel_details WHERE hotel_id=88750 AND status='success' LIMIT 1")->fetchColumn();
        self::need($size!==false&&is_numeric($size)&&(int)$size>0,'source_unavailable');self::need((int)$size<=1048576,'row_size_limit');
        $r=$db->query("SELECT raw_json,source_hash,fetched_at FROM catalog_hotel_details WHERE hotel_id=88750 AND status='success' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($r)&&is_string($r['raw_json'])&&is_string($r['source_hash'])&&hash_equals($r['source_hash'],hash('sha256',$r['raw_json'])),'source_integrity');
        self::need($r['source_hash']===$pins['rawSha256']&&is_string($r['fetched_at'])&&hash('sha256',$r['fetched_at'])===$pins['timeSha256'],'source_drift');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$r['fetched_at'],new DateTimeZone('UTC'));self::need($date!==false&&$date->format('Y-m-d H:i:s')===$r['fetched_at'],'source_time');
        $payload=json_decode($r['raw_json'],true,64,JSON_THROW_ON_ERROR);self::need(is_array($payload)&&$payload!==[],'source_invalid');
        $prefix='$.';$hotel=$payload;if(array_is_list($payload)){self::need(count($payload)===1&&is_array($payload[0]),'source_wrapper');$hotel=$payload[0];$prefix='$[0].';}
        self::need(v2_hotel_detail_int($hotel['id']??null)===self::HOTEL_ID&&($hotel['name']??null)===self::NAME
            &&v2_hotel_detail_int($hotel['country']['id']??null)===self::COUNTRY_ID,'source_identity');
        $normal=LocalTvCatalogV1::normalize($payload,self::HOTEL_ID);self::need(($normal['content']['name']??null)===self::NAME
            &&LocalTvCatalogV1::text($normal['content']['description']??null)===null,'source_drift');return [$r,$hotel,$prefix];
    }
    private static function consumerRow(PDO $db):array{
        $size=$db->query('SELECT COALESCE(OCTET_LENGTH(source_json),0)+OCTET_LENGTH(discovery_json)+OCTET_LENGTH(content_json)+OCTET_LENGTH(manual_json)+OCTET_LENGTH(source_absent_json) FROM local_tv_hotels WHERE id=88750 LIMIT 1')->fetchColumn();
        self::need($size!==false&&is_numeric($size)&&(int)$size>=0,'consumer_unavailable');self::need((int)$size<=2097152,'row_size_limit');
        $r=$db->query('SELECT state,revision,source_json,source_sha256,source_fetched_at,discovery_json,content_json,manual_json,source_absent_json FROM local_tv_hotels WHERE id=88750 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($r),'consumer_unavailable');return $r;
    }
    private static function consumer(PDO $db,array $pins):array{
        $row=self::consumerRow($db);$manual=json_decode($row['manual_json'],true,64,JSON_THROW_ON_ERROR);$absent=json_decode($row['source_absent_json'],true,64,JSON_THROW_ON_ERROR);
        self::need($row['state']==='ready'&&(int)$row['revision']===$pins['revision']&&is_string($row['source_json'])
            &&hash('sha256',$row['source_json'])===$pins['rawSha256']&&$row['source_sha256']===$pins['rawSha256']
            &&is_string($row['source_fetched_at'])&&hash('sha256',$row['source_fetched_at'])===$pins['timeSha256']
            &&is_array($manual)&&count($manual)===0&&is_array($absent)&&array_is_list($absent)&&count($absent)===1
            &&self::digest($absent)===$pins['sourceAbsentSha256'],'consumer_drift');
        $result=(new LocalTvCatalogV1($db))->read([self::HOTEL_ID],false);
        self::need(($result['source']??null)===LocalTvCatalogV1::SOURCE&&($result['catalog']??null)==='local-tv'&&($result['requestedIds']??null)===[self::HOTEL_ID]
            &&($result['missingIds']??null)===[]&&($result['links']??null)===[]&&count($result['items']??[])===1,'consumer_drift');$p=$result['items'][0];
        self::need(($p['id']??null)===self::HOTEL_ID&&($p['contentState']??null)==='ready'&&($p['revision']??null)===$pins['revision']
            &&($p['detailsAvailable']??null)===true&&($p['manualFields']??null)===[]&&self::digest($p['sourceAbsent']??null)===$pins['sourceAbsentSha256']
            &&LocalTvCatalogV1::text($p['description']??null)===null,'consumer_drift');return [$p,$row];
    }
    private static function safeKey(string $key):bool{return preg_match('/^[A-Za-z][A-Za-z0-9_]{0,47}$/D',$key)===1
        &&preg_match('/phone|email|contact|url|site|booking|secret|token|password|account|address|credential|login|^tel$|^fax$|^mail$/i',$key)!==1;}
    private static function blocks(array $hotel,array $consumer,string $prefix):array{
        $paths=self::FIXED_PATHS;foreach(['meals','descriptions'] as $group){if(!is_array($hotel[$group]??null))continue;$keys=array_keys($hotel[$group]);sort($keys,SORT_STRING);
            foreach($keys as $key)if(is_string($key)&&self::safeKey($key)&&is_string($hotel[$group][$key]))$paths[$group.'.'.$key]=$group==='meals'?'hotelInformation.meals.'.$key:null;}
        $blocks=[];$bytes=0;foreach($paths as $path=>$projection){$exists=false;$value=self::field($hotel,$path,$exists);if(!$exists||!is_string($value))continue;
            self::need(strlen($value)<=32768,'evidence_size_limit');$text=LocalTvCatalogV1::text($value);if($text===null)continue;
            // Contact/URL-bearing blocks are excluded in full, never redacted into new source text.
            if(preg_match('~(?:https?://|www\.|mailto:|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|(?:\+\d|\b(?:tel|phone|fax)\s*:))~iu',$text)===1)continue;
            $length=strlen($text);self::need($length<=8192&&count($blocks)<32&&$bytes+$length<=65536,'evidence_size_limit');$bytes+=$length;
            $compare=['state'=>'not_projected','sha256'=>null,'byteLength'=>null];
            if($projection!==null){$present=false;$effective=self::field($consumer,$projection,$present);$effective=$present&&is_string($effective)?LocalTvCatalogV1::text($effective):null;
                $compare=$effective===null?['state'=>'missing','sha256'=>null,'byteLength'=>0]
                    :['state'=>$effective===$text?'equal':'different','sha256'=>hash('sha256',$effective),'byteLength'=>strlen($effective)];}
            $blocks[]=['sourcePath'=>$prefix.$path,'consumerPath'=>$projection,'text'=>$text,'sha256'=>hash('sha256',$text),'byteLength'=>$length,'consumerComparison'=>$compare];
        }self::need($blocks!==[],'no_evidence');return [$blocks,$bytes];
    }
    public static function read(PDO $db,string $site,array $parent,array $fixturePins=[]):array{
        $r=['schemaVersion'=>1,'source'=>'anytour-hotel-profile-88750-evidence-v1','state'=>'held','reason'=>'read_failed','observedAt'=>time(),
            'hotelId'=>self::HOTEL_ID,'countryId'=>self::COUNTRY_ID,'parentResultSha256'=>self::PARENT_SHA256,'parentTargetSha256'=>self::PARENT_TARGET_SHA256,
            'rawSha256Before'=>null,'rawSha256After'=>null,'sourceTimeSha256'=>null,'databaseSha256Before'=>null,'databaseSha256After'=>null,
            'protectedBefore'=>null,'protectedAfter'=>null,'blockCount'=>0,'textBytes'=>0,'evidenceSha256'=>null,
            'supplierCalls'=>0,'databaseWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,'priceAuthority'=>false,'importAllowed'=>false];
        $evidence=null;$ownTransaction=false;$pins=null;$sourceBefore=null;$consumerBefore=null;
        try{$pins=self::pins($fixturePins);self::parentTarget($parent,$fixturePins);
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            $r['protectedBefore']=AnyTourHotelProfileCompletenessReadV1::protected($site);self::need($r['protectedBefore']===$pins['protected'],'protected_drift');
            $r['databaseSha256Before']=self::database($db);self::need($r['databaseSha256Before']===$pins['databaseSha256'],'database_identity');self::tables($db);
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');$ownTransaction=true;
            try{[$sourceBefore,$hotel,$prefix]=self::source($db,$pins);$r['rawSha256Before']=$sourceBefore['source_hash'];$r['sourceTimeSha256']=hash('sha256',$sourceBefore['fetched_at']);
                [$consumer,$consumerBefore]=self::consumer($db,$pins);[$blocks,$bytes]=self::blocks($hotel,$consumer,$prefix);
                $evidence=['hotelId'=>self::HOTEL_ID,'countryId'=>self::COUNTRY_ID,'name'=>self::NAME,'rawSha256'=>$r['rawSha256Before'],'sourceTimeSha256'=>$r['sourceTimeSha256'],
                    'consumerRevision'=>$pins['revision'],'consumerState'=>'ready','manualFieldCount'=>0,'sourceAbsentCount'=>1,'sourceAbsentSha256'=>$pins['sourceAbsentSha256'],'blocks'=>$blocks];
                $r['blockCount']=count($blocks);$r['textBytes']=$bytes;
            }finally{if($ownTransaction&&$db->inTransaction())$db->rollBack();$ownTransaction=false;}
            self::tables($db);[$sourceAfter]=self::source($db,$pins);$r['rawSha256After']=$sourceAfter['source_hash'];
            self::need(self::digest($sourceBefore)===self::digest($sourceAfter),'source_drift');
            self::need(self::digest($consumerBefore)===self::digest(self::consumerRow($db)),'consumer_drift');$r['state']='completed_read_only';$r['reason']=null;
        }catch(Throwable $error){if($ownTransaction&&$db->inTransaction())try{$db->rollBack();}catch(Throwable $ignored){}
            $allowed=['parent_invalid','fixture_pins','caller_transaction','protected_drift','database_identity','schema_unavailable','catalog_identity','source_unavailable',
                'row_size_limit','source_integrity','source_drift','source_time','source_invalid','source_wrapper','source_identity','consumer_unavailable','consumer_drift','evidence_size_limit','no_evidence'];
            $r['reason']=in_array($error->getMessage(),$allowed,true)?$error->getMessage():'read_failed';
        }finally{
            if($r['databaseSha256Before']!==null)try{$r['databaseSha256After']=self::database($db);self::need($pins!==null&&$r['databaseSha256After']===$pins['databaseSha256'],'database_identity');}catch(Throwable $ignored){$r['state']='held';$r['reason']='database_identity';}
            if($r['protectedBefore']!==null)try{$r['protectedAfter']=AnyTourHotelProfileCompletenessReadV1::protected($site);self::need($pins!==null&&$r['protectedAfter']===$pins['protected'],'protected_drift');}catch(Throwable $ignored){$r['state']='held';$r['reason']='protected_drift';}
        }
        if($r['state']!=='completed_read_only'){$evidence=null;$r['blockCount']=$r['textBytes']=0;}$r['evidenceSha256']=$evidence===null?null:self::digest($evidence);
        $out=['receipt'=>$r,'evidence'=>$evidence];self::need(strlen(self::json($out))<=self::MAX_BYTES-1024,'result_size_limit');return $out;
    }
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{
        if($argc!==1)throw new RuntimeException('CLI_SCOPE');$site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');
        if($source!==AnyTourHotelProfile88750EvidenceV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)||$op!==AnyTourHotelProfile88750EvidenceV1::OPERATION
            ||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)
            ||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/'))throw new RuntimeException('CLI_SCOPE');
        foreach(['DSN','DB_USER','DB_PASSWORD','DB_HOST','DB_NAME','DB_PORT']as$key)if(getenv('ANYTOUR_DATA_'.$key)!==false)throw new RuntimeException('CLI_ENV');
        $path=$dir.'/local-profile-88750-evidence.json';if(file_exists($path)||is_link($path))throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $parent=AnyTourHotelProfile88750EvidenceV1::parentFile(dirname($dir).'/'.AnyTourHotelProfile88750EvidenceV1::PARENT_OPERATION.'/result.json');
        if(AnyTourHotelProfileCompletenessReadV1::protected($site)!==['config.php'=>AnyTourHotelProfileCompletenessReadV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256])throw new RuntimeException('CLI_PRIVATE_DRIFT');
        foreach(['db-v1.php'=>AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256,'hotel-details-v1.php'=>'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
            'hotel-presentation-read-v1.php'=>'17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3',
            'local-tv-catalog-v1.php'=>'abbed95e0343ce402cdcfb11ef7819da0a12e5bafb7e5bf7f22cbb9260167b11'] as $file=>$sha){$dependency=__DIR__.'/../../v2/data/'.$file;
            if(!is_file($dependency)||is_link($dependency)||hash_file('sha256',$dependency)!==$sha)throw new RuntimeException('CLI_SOURCE');}
        $_SERVER['DOCUMENT_ROOT']=$site;require_once __DIR__.'/../../v2/data/db-v1.php';require_once __DIR__.'/../../v2/data/local-tv-catalog-v1.php';
        $result=AnyTourHotelProfile88750EvidenceV1::read(v2_data_db(),$site,$parent);$result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];
        $json=AnyTourHotelProfile88750EvidenceV1::json($result)."\n";if(strlen($json)>AnyTourHotelProfile88750EvidenceV1::MAX_BYTES)throw new RuntimeException('CLI_SIZE');
        $old=umask(0077);try{$h=fopen($path,'x+b');}finally{umask($old);}if($h===false)throw new RuntimeException('CLI_CREATE');
        try{if(fwrite($h,$json)!==strlen($json)||!fflush($h)||!fsync($h))throw new RuntimeException('CLI_WRITE');}finally{fclose($h);}echo $json;
        exit($result['receipt']['state']==='completed_read_only'?0:1);
    }catch(Throwable $ignored){fwrite(STDERR,"LOCAL_PROFILE_88750_EVIDENCE_FAILED\n");exit(1);}
}
