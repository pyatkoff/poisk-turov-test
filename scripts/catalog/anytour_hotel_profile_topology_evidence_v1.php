<?php
/** Materially new topology/config-selection/same-PDO evidence contract; no writes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$pure=__DIR__.'/anytour_hotel_profile_completeness_read_v1.php';
if(!is_file($pure)||is_link($pure)||hash_file('sha256',$pure)!=='11e54194b60ec03b2d6bebf8492953685d524a782ac9d80657006e2d54b9f8d3')throw new RuntimeException('PURE_SOURCE');
require_once $pure;

final class AnyTourHotelProfileTopologyEvidenceV1
{
    public const SOURCE='ccf5a1b1c8dcc9d3b796b40c85aca0f9407eb1a1';
    public const OPERATION='int-andromeda-local-profile-topology-evidence-20261026-v1';
    public const PARENT_OPERATION='int-andromeda-local-profile-batch-read-20261026-v1';
    public const FAILED_OPERATION='int-andromeda-local-profile-88750-evidence-20261026-v1';
    public const CONTRACT_SHA256='9e7dc77ba18452218e0073dca02af3fa97831d94958f19767a15214a47018ca0';
    public const HANDOFF_BODY_SHA256='f474c8ec1cd383f321b0fd35cd9b8263ea864aa5150924c13379545a78a62603';
    public const FAILED_SHA256='8eefd9d1c36ae5bf5c2d8d07c5931da4238ec886f87bf2be01d521a3fabf9dcd';
    public const PARENT_SHA256='ec2267edaebfa24f3f1b6d72b51834a047e063e1e2fbb86ba6988d34f58b22d8';
    public const PARENT_TARGET_SHA256='2022f9f9592c1f27cdefb3a9370c9ee84cee3de4139d5a33f5428b713e899a50';
    public const ROOT_HELPER_SHA256='c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13';
    public const STAGED_HELPER_SHA256='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4';
    public const ROOT_CONFIG_SHA256='121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def';
    public const RAW_SHA256='f64669c666ea20e89bc3dc262c4559e2ff133da75f6c3c7a53e9104f451456a4';
    public const TIME_SHA256='bdbf170ed62868c1418d2b39610f8c042d6717fd84f968b516769c8f5073c45e';
    public const ABSENT_SHA256='8f515b15e10dfa8d9181889456d432e3b6b5d40458b1c41fbd864e9fa628a4d2';
    public const HOTEL_ID=88750;public const COUNTRY_ID=4;public const NAME='THE X BELEK';public const MAX_BYTES=262144;
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
    private static function need(bool $v,string $reason):void{if(!$v)throw new RuntimeException($reason);}
    private static function sha(mixed $v):bool{return is_string($v)&&preg_match('/^[a-f0-9]{64}$/D',$v)===1;}
    private static function field(array $v,string $path,bool &$exists):mixed{
        foreach(explode('.',$path)as$key){if(!is_array($v)||!array_key_exists($key,$v)){$exists=false;return null;}$v=$v[$key];}$exists=true;return $v;
    }
    /** New-class native fixtures may replace observations, never runtime CLI constants. */
    private static function pins(array $override):array{
        $p=['rawSha256'=>self::RAW_SHA256,'timeSha256'=>self::TIME_SHA256,'revision'=>3,'sourceAbsentSha256'=>self::ABSENT_SHA256,
            'parentSha256'=>self::PARENT_SHA256,'parentTargetSha256'=>self::PARENT_TARGET_SHA256,'failedSha256'=>self::FAILED_SHA256,
            'databaseSha256'=>AnyTourHotelProfileCompletenessReadV1::DATABASE_SHA256,'rootConfigSha256'=>self::ROOT_CONFIG_SHA256];
        self::need(array_diff(array_keys($override),array_keys($p))===[],'fixture_pins');$p=array_replace($p,$override);
        foreach($p as$k=>$v)self::need($k==='revision'?is_int($v)&&$v>0:self::sha($v),'fixture_pins');return $p;
    }
    public static function contractFile(string $path):array{
        $raw=self::safeBytes($path,65536);self::need(hash('sha256',$raw)===self::CONTRACT_SHA256,'contract_invalid');
        $c=json_decode($raw,true,64,JSON_THROW_ON_ERROR);self::need(is_array($c),'contract_invalid');self::contract($c);return $c;
    }
    private static function contract(array $c):void{
        $h=$c['ownerHandoff']??null;self::need(($c['schemaVersion']??null)===1&&is_array($h)&&($h['commentId']??null)===6412575747
            &&($h['issue']??null)===2506&&($h['repository']??null)==='pyatkoff/poisk-turov-test'&&($h['actorId']??null)===226193297
            &&($h['association']??null)==='OWNER'&&is_string($h['body']??null)&&hash('sha256',$h['body'])===self::HANDOFF_BODY_SHA256
            &&($h['bodySha256']??null)===self::HANDOFF_BODY_SHA256&&($h['createdAt']??null)==='2026-10-26T09:24:20Z'
            &&($h['updatedAt']??null)===$h['createdAt'],'contract_invalid');
        self::need(($c['failed']??null)===['operation'=>self::FAILED_OPERATION,'bytes'=>832,'sha256'=>self::FAILED_SHA256]
            &&($c['parent']['operation']??null)===self::PARENT_OPERATION&&($c['parent']['bytes']??null)===36952
            &&($c['parent']['sha256']??null)===self::PARENT_SHA256&&($c['parent']['targetSha256']??null)===self::PARENT_TARGET_SHA256
            &&($c['topology']['source']??null)===self::SOURCE&&($c['topology']['mainBase']??null)==='a7ceec4a7ba89ab0361ffd768beca60d7d819ea5'
            &&($c['topology']['rootHelperSha256']??null)===self::ROOT_HELPER_SHA256&&($c['topology']['stagedHelperSha256']??null)===self::STAGED_HELPER_SHA256
            &&($c['topology']['rootConfigSha256']??null)===self::ROOT_CONFIG_SHA256&&($c['topology']['stagedConfig']??null)==='v2/config.php'
            &&($c['topology']['stagedConfigMustBeAbsent']??null)===true&&($c['topology']['rootHelperExecuted']??null)===false
            &&($c['target']['hotelId']??null)===self::HOTEL_ID&&($c['target']['countryId']??null)===self::COUNTRY_ID
            &&($c['target']['name']??null)===self::NAME&&($c['target']['rawSha256']??null)===self::RAW_SHA256
            &&($c['target']['timeSha256']??null)===self::TIME_SHA256&&($c['target']['revision']??null)===3
            &&($c['target']['manualFieldCount']??null)===0&&($c['target']['sourceAbsentSha256']??null)===self::ABSENT_SHA256,'contract_invalid');
    }
    private static function safeBytes(string $path,int $limit):string{
        clearstatcache(true,$path);$s=@lstat($path);self::need(is_array($s)&&is_file($path)&&!is_link($path)&&realpath($path)===$path
            &&$s['size']>0&&$s['size']<=$limit,'unsafe_file');$h=@fopen($path,'rb');self::need(is_resource($h),'unsafe_file');
        try{$open=fstat($h);self::need(is_array($open)&&$open['ino']===$s['ino']&&$open['dev']===$s['dev'],'unsafe_file');
            $raw=stream_get_contents($h,$limit+1);$end=fstat($h);clearstatcache(true,$path);$last=@lstat($path);
            self::need(is_string($raw)&&strlen($raw)===$s['size']&&is_array($last)&&is_array($end)&&$last['ino']===$s['ino']&&$last['dev']===$s['dev']
                &&$end['size']===$s['size']&&$last['size']===$s['size']&&!is_link($path),'unsafe_file');return $raw;
        }finally{fclose($h);}
    }
    public static function parentsFiles(string $private):array{
        $out=[];foreach([[self::FAILED_OPERATION,832,self::FAILED_SHA256],[self::PARENT_OPERATION,36952,self::PARENT_SHA256]]as[$op,$bytes,$sha]){
            $dir=$private.'/'.$op;self::need(is_dir($dir)&&!is_link($dir)&&realpath($dir)===$dir,'parent_invalid');
            $raw=self::safeBytes($dir.'/result.json',$bytes);self::need(strlen($raw)===$bytes&&hash('sha256',$raw)===$sha,'parent_invalid');
            $p=json_decode($raw,true,64,JSON_THROW_ON_ERROR);self::need(is_array($p),'parent_invalid');$out[]=$p;
        }return $out;
    }
    private static function parents(array $failed,array $parent,array $pins):void{
        self::need(self::digest($failed)===$pins['failedSha256']&&($failed['schema_version']??null)===1
            &&($failed['mode']??null)==='local-profile-88750-evidence-v1'&&($failed['operation_id']??null)===self::FAILED_OPERATION
            &&($failed['source_sha']??null)===self::SOURCE&&($failed['status']??null)==='unknown_no_replay'
            &&($failed['reason']??null)==='local_profile_88750_private_drift'
            &&($failed['local_profile_88750_evidence']??null)===['data'=>null,'fileSha256'=>null,'protectedAfter'=>null,
                'protectedBefore'=>['config.php'=>self::ROOT_CONFIG_SHA256,'data/db-v1.php'=>self::ROOT_HELPER_SHA256]]
            &&!array_key_exists('production_after',$failed),'parent_invalid');
        foreach(['supplier_calls','database_writes','profile_writes','mapping_writes','schema_writes','lead_calls','booking_calls']as$k)self::need(($failed[$k]??null)===0,'parent_invalid');
        $box=$parent['local_profile_batch']??null;$data=$box['data']??null;$r=$data['receipt']??null;$items=$data['items']??null;
        self::need(self::digest($parent)===$pins['parentSha256']&&($parent['schema_version']??null)===1&&($parent['mode']??null)==='local-profile-batch-read-v1'
            &&($parent['operation_id']??null)===self::PARENT_OPERATION&&($parent['source_sha']??null)==='1d0e529e3fc0666783db1454c0765d3c16d63843'
            &&($parent['status']??null)==='complete'&&is_array($r)&&is_array($items)&&array_is_list($items)&&count($items)===20
            &&($r['source']??null)==='anytour-hotel-profile-batch-read-v1'&&($r['state']??null)==='completed_read_only'
            &&array_key_exists('reason',$r)&&$r['reason']===null&&($r['controlSha']??null)==='e2a1074e3c7c335377024e88951467479da8331b'
            &&($r['sourceSha']??null)==='1d0e529e3fc0666783db1454c0765d3c16d63843'&&($r['operationId']??null)===self::PARENT_OPERATION
            &&($r['requestedCount']??null)===20&&($r['observedCount']??null)===20&&($r['heldCount']??null)===0&&($r['possibleGapCount']??null)===0
            &&($r['itemsSha256']??null)===self::digest($items)&&($box['fileSha256']??null)===hash('sha256',self::json($data)."\n")
            &&($r['priceAuthority']??null)===false&&($r['importAllowed']??null)===false,'parent_invalid');
        foreach(['supplier_calls','database_writes','profile_writes','mapping_writes','schema_writes','lead_calls','booking_calls']as$k)self::need(($parent[$k]??null)===0,'parent_invalid');
        foreach(['supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts']as$k)self::need(($r[$k]??null)===0,'parent_invalid');
        self::need(($parent['production_unchanged']??null)===true&&($parent['production_before']??null)===($parent['production_after']??false)
            &&($r['protectedBefore']??null)===($r['protectedAfter']??false)&&($r['protectedBefore']??null)===($box['protectedBefore']??false)
            &&($r['protectedBefore']??null)===($box['protectedAfter']??false),'parent_invalid');
        $target=null;$seen=[];foreach($items as$item){self::need(is_array($item)&&is_int($item['hotelId']??null)&&!isset($seen[$item['hotelId']])
            &&($item['state']??null)==='observed'&&array_key_exists('reason',$item)&&$item['reason']===null,'parent_invalid');$seen[$item['hotelId']]=true;
            if($item['hotelId']===self::HOTEL_ID)$target=$item;}
        self::need(is_array($target)&&self::digest($target)===$pins['parentTargetSha256']&&($target['countryId']??null)===4
            &&($target['source']['sourceSha256']??null)===$pins['rawSha256']&&($target['source']['sourceFetchedAt']['sha256']??null)===$pins['timeSha256']
            &&($target['source']['description']['present']??null)===false&&($target['consumer']['sourceSha256']??null)===$pins['rawSha256']
            &&($target['consumer']['sourceFetchedAt']['sha256']??null)===$pins['timeSha256']&&($target['consumer']['state']??null)==='observed'
            &&($target['consumer']['profile']['contentState']??null)==='ready'&&($target['consumer']['profile']['revision']??null)===$pins['revision']
            &&($target['consumer']['profile']['manualFieldCount']??null)===0&&($target['consumer']['profile']['sourceAbsentCount']??null)===1
            &&($target['consumer']['profile']['sourceAbsentSha256']??null)===$pins['sourceAbsentSha256']
            &&($target['gaps']??null)===['consumerMissing'=>['description'],'manualProtectedMissing'=>[],'possibleFillFields'=>[],'sourceMissing'=>['description']],'parent_invalid');
    }
    private static function noEnvironment():void{
        $env=getenv();foreach(is_array($env)?array_keys($env):[]as$key)self::need(!str_starts_with($key,'ANYTOUR_DATA_'),'environment_override');
    }
    /** No config/helper execution occurs in this phase. */
    private static function topology(string $site,string $stage,array $pins):array{
        self::noEnvironment();self::need(realpath($site)===$site&&!is_link($site)&&is_dir($site)&&realpath($stage)===$stage&&!is_link($stage),'topology_invalid');
        self::need(($_SERVER['DOCUMENT_ROOT']??null)===$site,'document_root');
        $dir=$stage.'/v2';self::need(realpath($dir)===$dir&&is_dir($dir)&&!is_link($dir)&&is_readable($dir)&&is_executable($dir),'topology_invalid');
        clearstatcache(true,$dir.'/config.php');self::need(@lstat($dir.'/config.php')===false&&!file_exists($dir.'/config.php')&&!is_link($dir.'/config.php'),'staged_config_present');
        $config=self::safeBytes($site.'/config.php',2097152);$root=self::safeBytes($site.'/data/db-v1.php',2097152);
        $main=self::safeBytes($stage.'/provenance/main-db-v1.php',3282);$loader=self::safeBytes($stage.'/v2/data/db-v1.php',3861);
        self::need(hash('sha256',$config)===$pins['rootConfigSha256']&&hash('sha256',$root)===self::ROOT_HELPER_SHA256
            &&hash('sha256',$main)===self::ROOT_HELPER_SHA256&&strlen($main)===3282
            &&hash('sha256',$loader)===self::STAGED_HELPER_SHA256&&strlen($loader)===3861,'topology_hash');
        foreach(['local-tv-catalog-v1.php'=>'abbed95e0343ce402cdcfb11ef7819da0a12e5bafb7e5bf7f22cbb9260167b11',
            'hotel-details-v1.php'=>'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
            'hotel-presentation-read-v1.php'=>'17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3']as$file=>$sha)
            self::need(hash('sha256',self::safeBytes($stage.'/v2/data/'.$file,2097152))===$sha,'staged_source');
        return ['rootConfigSha256'=>$pins['rootConfigSha256'],'rootHelperSha256'=>self::ROOT_HELPER_SHA256,'mainHelperSha256'=>self::ROOT_HELPER_SHA256,
            'stagedHelperSha256'=>self::STAGED_HELPER_SHA256,'stagedConfigAbsent'=>true,'documentRootBound'=>true];
    }
    private static function binding(PDO $db):array{
        $r=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid,CURRENT_USER() AS account,CURRENT_ROLE() AS roles')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($r)&&is_string($r['selected_database']??null)&&$r['selected_database']!==''&&strlen($r['selected_database'])<=64
            &&is_string($r['server_uuid']??null)&&preg_match('/^[a-f0-9-]{36}$/Di',$r['server_uuid'])===1
            &&is_string($r['account']??null)&&$r['account']!==''&&strlen($r['account'])<=1024&&is_string($r['roles']??null)&&strlen($r['roles'])<=8192,'database_binding');
        return ['database'=>self::digest(['database'=>$r['selected_database'],'serverUuid'=>$r['server_uuid']]),
            'account'=>self::digest(['account'=>$r['account']]),'role'=>self::digest(['roles'=>$r['roles']])];
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


    /** Only the new class accepts explicit native fixture pins/factory; CLI uses literal pins and stock helper. */
    public static function run(string $site,string $stage,array $contract,array $failed,array $parent,array $fixturePins=[],?callable $factory=null):array{
        $r=['schemaVersion'=>1,'source'=>'anytour-hotel-profile-topology-evidence-v1','state'=>'held','reason'=>'read_failed','observedAt'=>time(),
            'hotelId'=>self::HOTEL_ID,'countryId'=>self::COUNTRY_ID,'contractSha256'=>self::CONTRACT_SHA256,'ownerHandoffBodySha256'=>self::HANDOFF_BODY_SHA256,
            'failedResultSha256'=>self::FAILED_SHA256,'parentResultSha256'=>self::PARENT_SHA256,'parentTargetSha256'=>self::PARENT_TARGET_SHA256,
            'rawSha256Before'=>null,'rawSha256After'=>null,'sourceTimeSha256'=>null,'databaseSha256Before'=>null,'databaseSha256After'=>null,
            'accountSha256Before'=>null,'accountSha256After'=>null,'roleSha256Before'=>null,'roleSha256After'=>null,'oldAccountIdentityKnown'=>false,
            'protectedBefore'=>null,'protectedAfter'=>null,'blockCount'=>0,'textBytes'=>0,'evidenceSha256'=>null,
            'supplierCalls'=>0,'databaseWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,'priceAuthority'=>false,'importAllowed'=>false,
            'phases'=>['phpExecuted'=>true,'stagedHelperAttempted'=>false,'pdoAttempted'=>false,'databaseConnected'=>false,'configSelected'=>false,'topologyVerified'=>false,'appDataRead'=>false,'rootHelperExecuted'=>false]];
        $top=['before'=>null,'after'=>null,'selectedConfigSha256'=>null];$evidence=null;$db=null;$own=false;$pins=null;$first=null;
        try{
            $pins=self::pins($fixturePins);self::contract($contract);self::parents($failed,$parent,$pins);
            $top['before']=self::topology($site,$stage,$pins);$r['protectedBefore']=['config.php'=>$top['before']['rootConfigSha256'],'data/db-v1.php'=>$top['before']['rootHelperSha256']];
            $r['phases']['topologyVerified']=true;
            self::need(!in_array($site.'/data/db-v1.php',get_included_files(),true),'root_helper_executed');
            $r['phases']['stagedHelperAttempted']=true;require_once $stage.'/v2/data/db-v1.php';
            self::need((new ReflectionFunction('v2_data_db_config'))->getFileName()===$stage.'/v2/data/db-v1.php','staged_loader_binding');
            $config=v2_data_db_config();
            self::need(in_array($site.'/config.php',get_included_files(),true)&&!in_array($stage.'/v2/config.php',get_included_files(),true)
                &&!in_array($site.'/data/db-v1.php',get_included_files(),true),'config_selection');
            self::need(self::topology($site,$stage,$pins)===$top['before'],'topology_drift');
            $r['phases']['configSelected']=true;$top['selectedConfigSha256']=$pins['rootConfigSha256'];
            self::need(is_array($config)&&is_string($config['dsn']??null)&&$config['dsn']!==''&&is_string($config['user']??null)&&$config['user']!=='','config_selection');
            $r['phases']['pdoAttempted']=true;$db=$factory!==null?$factory($config):v2_data_db();self::need($db instanceof PDO,'database_connect');$r['phases']['databaseConnected']=true;
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            $first=self::binding($db);$r['databaseSha256Before']=$first['database'];$r['accountSha256Before']=$first['account'];$r['roleSha256Before']=$first['role'];
            self::need($first['database']===$pins['databaseSha256'],'database_identity');self::tables($db);
            require_once $stage.'/v2/data/local-tv-catalog-v1.php';
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');$own=true;
            try{
                $r['phases']['appDataRead']=true;[$sourceBefore,$hotel,$prefix]=self::source($db,$pins);
                $r['rawSha256Before']=$sourceBefore['source_hash'];$r['sourceTimeSha256']=hash('sha256',$sourceBefore['fetched_at']);
                [$consumer,$consumerBefore]=self::consumer($db,$pins);[$blocks,$bytes]=self::blocks($hotel,$consumer,$prefix);
                $evidence=['hotelId'=>self::HOTEL_ID,'countryId'=>self::COUNTRY_ID,'name'=>self::NAME,'rawSha256'=>$r['rawSha256Before'],'sourceTimeSha256'=>$r['sourceTimeSha256'],
                    'consumerRevision'=>$pins['revision'],'consumerState'=>'ready','manualFieldCount'=>0,'sourceAbsentCount'=>1,'sourceAbsentSha256'=>$pins['sourceAbsentSha256'],'blocks'=>$blocks];
                $r['blockCount']=count($blocks);$r['textBytes']=$bytes;
            }finally{if($own&&$db->inTransaction())$db->rollBack();$own=false;}
            self::tables($db);[$sourceAfter]=self::source($db,$pins);$r['rawSha256After']=$sourceAfter['source_hash'];
            self::need(self::digest($sourceBefore)===self::digest($sourceAfter),'source_drift');
            self::need(self::digest($consumerBefore)===self::digest(self::consumerRow($db)),'consumer_drift');
            $r['state']='completed_read_only';$r['reason']=null;
        }catch(Throwable $error){
            if($own&&$db instanceof PDO&&$db->inTransaction())try{$db->rollBack();}catch(Throwable $ignored){}
            $allowed=['fixture_pins','contract_invalid','parent_invalid','unsafe_file','environment_override','topology_invalid','document_root','staged_config_present','topology_hash','staged_source','root_helper_executed',
                'staged_loader_binding','config_selection','topology_drift','database_connect','caller_transaction','database_binding','database_identity','schema_unavailable','catalog_identity','source_unavailable',
                'row_size_limit','source_integrity','source_drift','source_time','source_invalid','source_wrapper','source_identity','consumer_unavailable','consumer_drift','evidence_size_limit','no_evidence'];
            $r['state']='held';$r['reason']=in_array($error->getMessage(),$allowed,true)?$error->getMessage():'read_failed';
        }finally{
            if($first!==null&&$db instanceof PDO)try{
                $after=self::binding($db);$r['databaseSha256After']=$after['database'];$r['accountSha256After']=$after['account'];$r['roleSha256After']=$after['role'];
                self::need($after===$first,'database_binding');
            }catch(Throwable $ignored){$r['state']='held';$r['reason']='database_binding';}
            if($top['before']!==null&&$pins!==null)try{
                $r['protectedAfter']=AnyTourHotelProfileCompletenessReadV1::protected($site);$top['after']=self::topology($site,$stage,$pins);
                self::need($top['after']===$top['before']&&$r['protectedAfter']===$r['protectedBefore']
                    &&!in_array($site.'/data/db-v1.php',get_included_files(),true),'topology_drift');
            }catch(Throwable $ignored){$r['state']='held';$r['reason']='topology_drift';}
        }
        if($r['state']!=='completed_read_only'){$evidence=null;$r['blockCount']=$r['textBytes']=0;}
        $r['evidenceSha256']=$evidence===null?null:self::digest($evidence);$out=['receipt'=>$r,'topology'=>$top,'evidence'=>$evidence];
        self::need(strlen(self::json($out))<=self::MAX_BYTES-1024,'result_size_limit');return $out;
    }
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{
        if($argc!==1)throw new RuntimeException('CLI_SCOPE');$site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');
        if($source!==AnyTourHotelProfileTopologyEvidenceV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)||$op!==AnyTourHotelProfileTopologyEvidenceV1::OPERATION
            ||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)
            ||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/'))throw new RuntimeException('CLI_SCOPE');
        $path=$dir.'/local-profile-topology-evidence.json';if(file_exists($path)||is_link($path))throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $stage=(string)realpath(__DIR__.'/../..');$contract=AnyTourHotelProfileTopologyEvidenceV1::contractFile(__DIR__.'/fixtures/local-profile-topology-evidence-20261026.json');
        [$failed,$parent]=AnyTourHotelProfileTopologyEvidenceV1::parentsFiles(dirname($dir));
        if(function_exists('v2_data_db_config')||in_array($site.'/config.php',get_included_files(),true))throw new RuntimeException('CLI_PRELOADED');
        $_SERVER['DOCUMENT_ROOT']=$site;
        $result=AnyTourHotelProfileTopologyEvidenceV1::run($site,$stage,$contract,$failed,$parent);
        $result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];$json=AnyTourHotelProfileTopologyEvidenceV1::json($result)."\n";
        if(strlen($json)>AnyTourHotelProfileTopologyEvidenceV1::MAX_BYTES)throw new RuntimeException('CLI_SIZE');
        $old=umask(0077);try{$h=fopen($path,'x+b');}finally{umask($old);}if($h===false)throw new RuntimeException('CLI_CREATE');
        try{if(fwrite($h,$json)!==strlen($json)||!fflush($h)||!fsync($h))throw new RuntimeException('CLI_WRITE');}finally{fclose($h);}echo $json;
        exit($result['receipt']['state']==='completed_read_only'?0:1);
    }catch(Throwable $ignored){fwrite(STDERR,"LOCAL_PROFILE_TOPOLOGY_EVIDENCE_FAILED\n");exit(1);}
}
