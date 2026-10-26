<?php
/** Fixed retained producer IDs: current source/consumer completeness only, no writes or HTTP. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/anytour_hotel_profile_completeness_read_v1.php';

final class AnyTourHotelProfileBatchReadV1
{
    public const SOURCE='1d0e529e3fc0666783db1454c0765d3c16d63843';
    public const OPERATION='int-andromeda-local-profile-batch-read-20261026-v1';
    public const MANIFEST_SHA256='a591a3c09db7e77f251665ffe76782004a0645e7f2d0735b18e5845d75550d33';
    public const PRODUCER_ARTIFACT_SHA256='c67a9aca2c2efd00e4a187cc43a3b6d258bd5629457778ee03d82e28f0d63b00';
    public const PRODUCER_JSON_SHA256='0b79183d8e4d8615cd810151431859ca1cd43c5e1c066fb9d0b2a9e0930fe251';
    public const COUNT=20;
    public const MAX_BYTES=262144;
    public const FIELDS=['description','primaryImage','images'];

    public static function json(mixed $value): string {return AnyTourHotelProfileCompletenessReadV1::json($value);}
    private static function hash(mixed $value): string {return hash('sha256',self::json($value));}
    private static function need(bool $ok,string $reason): void {if(!$ok)throw new RuntimeException($reason);}
    private static function itemNeed(bool $ok,string $reason): void {if(!$ok)throw new DomainException($reason);}
    private static function keys(array $value,array $keys): bool {return count($value)===count($keys)&&array_diff($keys,array_keys($value))===[];}
    private static function sha(mixed $value): bool {return is_string($value)&&preg_match('/^[a-f0-9]{64}$/D',$value)===1;}
    private static function text(mixed $value): array {
        $present=is_string($value)&&trim($value)!=='';
        return ['present'=>$present,'byteLength'=>$present?strlen($value):0,'sha256'=>$present?hash('sha256',$value):null];
    }
    /** The immutable file is projected from exactly records[1:21] of the retained original. */
    public static function manifest(string $path): array {
        self::need(is_file($path)&&!is_link($path)&&realpath($path)===$path&&filesize($path)>0&&filesize($path)<=65536
            &&hash_file('sha256',$path)===self::MANIFEST_SHA256,'manifest_invalid');
        $m=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        self::need(is_array($m)&&self::keys($m,['schemaVersion','producerRunId','artifactId','artifactSha256','jsonSha256','sliceStart','count','records'])
            &&$m['schemaVersion']===1&&$m['producerRunId']===40269983482&&$m['artifactId']===12628445087
            &&$m['artifactSha256']===self::PRODUCER_ARTIFACT_SHA256&&$m['jsonSha256']===self::PRODUCER_JSON_SHA256
            &&$m['sliceStart']===1&&$m['count']===self::COUNT&&is_array($m['records'])&&array_is_list($m['records'])&&count($m['records'])===self::COUNT,'manifest_invalid');
        $seen=[];$flags=['publication_allowed','indexation_allowed','route_launch_allowed','sitemap_allowed','canonical_launch_allowed','explicit_user_indexation_approval_required'];
        foreach($m['records'] as $r) {
            self::need(is_array($r)&&self::keys($r,['recordId','path','type','status','name','countryId','hotelId','cohortEvidence','recordSha256','reviewFlags'])
                &&is_int($r['hotelId'])&&$r['hotelId']>0&&$r['hotelId']<=4294967295&&$r['hotelId']!==475&&!isset($seen[$r['hotelId']])
                &&is_int($r['countryId'])&&in_array($r['countryId'],[1,4,8],true)&&is_string($r['name'])&&strlen($r['name'])>0&&strlen($r['name'])<=512
                &&$r['recordId']==='hotel.'.$r['countryId'].'.'.$r['hotelId'].'.core-review.v1'&&$r['type']==='hotel_tours'&&$r['status']==='review'
                &&is_string($r['path'])&&preg_match('#^/country/[a-z0-9-]+/hotel/[a-z0-9-]+-'.$r['hotelId'].'/$#D',$r['path'])===1
                &&self::sha($r['recordSha256'])&&is_array($r['reviewFlags'])&&self::keys($r['reviewFlags'],$flags),'manifest_invalid');
            foreach($flags as $flag)self::need($r['reviewFlags'][$flag]===($flag==='explicit_user_indexation_approval_required'),'manifest_invalid');
            $c=$r['cohortEvidence'];self::need(is_array($c)&&self::keys($c,['observation_count','last_observed_at','selection_basis'])
                &&is_int($c['observation_count'])&&$c['observation_count']>0&&is_string($c['last_observed_at'])
                &&$c['selection_basis']==='fresh_first_party_inventory_observation_coverage','manifest_invalid');
            $seen[$r['hotelId']]=true;
        }
        return $m;
    }
    private static function database(PDO $db): string {
        $row=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($row)&&is_string($row['selected_database']??null)&&$row['selected_database']!==''&&strlen($row['selected_database'])<=64
            &&is_string($row['server_uuid']??null)&&preg_match('/^[a-f0-9-]{36}$/Di',$row['server_uuid'])===1,'database_identity');
        return self::hash(['database'=>$row['selected_database'],'serverUuid'=>$row['server_uuid']]);
    }
    private static function tables(PDO $db): void {
        $rows=$db->query("SELECT TABLE_NAME,TABLE_TYPE,ENGINE FROM information_schema.TABLES WHERE BINARY TABLE_SCHEMA=BINARY DATABASE() AND TABLE_NAME IN ('catalog_hotels','catalog_hotel_details','local_tv_hotels') ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
        self::need(count($rows)===3,'schema_unavailable');
        foreach($rows as $r)self::need($r['TABLE_TYPE']==='BASE TABLE'&&$r['ENGINE']==='InnoDB','schema_unavailable');
    }
    private static function sourceCard(mixed $raw,mixed $sha,mixed $at,array $target): array {
        self::itemNeed(is_string($raw)&&strlen($raw)>0&&strlen($raw)<=1048576&&self::sha($sha)&&hash_equals($sha,hash('sha256',$raw)),'retained_source_invalid');
        self::itemNeed(is_string($at),'retained_source_invalid');
        $stamp=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at,new DateTimeZone('UTC'));
        self::itemNeed($stamp!==false&&$stamp->format('Y-m-d H:i:s')===$at,'retained_source_invalid');
        try {$payload=json_decode($raw,true,64,JSON_THROW_ON_ERROR);self::itemNeed(is_array($payload),'retained_source_invalid');
            $card=LocalTvCatalogV1::normalize($payload,$target['hotelId'])['content'];
        }catch(DomainException $e){throw new DomainException('retained_source_invalid',0,$e);}
        catch(JsonException|InvalidArgumentException $e){throw new DomainException('retained_source_invalid',0,$e);}
        self::itemNeed(($card['name']??null)===$target['name']&&v2_hotel_detail_int($card['country']['id']??null)===$target['countryId'],'retained_source_identity');
        return $card;
    }
    private static function facts(array $p): array {
        $images=is_array($p['images']??null)?$p['images']:[];
        return ['description'=>self::text($p['description']??null),'primaryImage'=>self::text($p['primaryImage']??null),
            'galleryCount'=>count($images),'gallerySha256'=>self::hash($images)];
    }
    private static function consumer(PDO $db,array $target): array {
        $id=$target['hotelId'];$q=$db->prepare('SELECT COALESCE(OCTET_LENGTH(source_json),0)+OCTET_LENGTH(discovery_json)+OCTET_LENGTH(content_json)+OCTET_LENGTH(manual_json)+OCTET_LENGTH(source_absent_json) FROM local_tv_hotels WHERE id=? LIMIT 1');$q->execute([$id]);$bytes=$q->fetchColumn();
        if($bytes===false)return [['state'=>'missing','profile'=>null,'sourceSha256'=>null,'sourceFetchedAt'=>null],[]];
        self::need(is_numeric($bytes)&&(int)$bytes>=0&&(int)$bytes<=2097152,'row_size_limit');
        $q=$db->prepare('SELECT state,source_json,source_sha256,source_fetched_at FROM local_tv_hotels WHERE id=? LIMIT 1');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);self::need(is_array($row),'read_scope');
        if($row['source_json']!==null)self::sourceCard($row['source_json'],$row['source_sha256'],$row['source_fetched_at'],$target);
        else self::itemNeed($row['source_sha256']===null&&$row['source_fetched_at']===null,'retained_source_invalid');
        $result=(new LocalTvCatalogV1($db))->read([$id],false);
        self::need($result['source']===LocalTvCatalogV1::SOURCE&&$result['catalog']==='local-tv'&&$result['requestedIds']===[$id]&&$result['links']===[],'read_scope');
        $out=['state'=>'excluded','profile'=>null,'sourceSha256'=>$row['source_sha256'],'sourceFetchedAt'=>self::text($row['source_fetched_at'])];
        if($result['missingIds']===[$id])return [$out,[]];
        self::need($result['missingIds']===[]&&count($result['items'])===1&&$result['items'][0]['id']===$id,'read_scope');
        $p=$result['items'][0];$manual=$p['manualFields'];$absent=$p['sourceAbsent'];
        self::itemNeed(is_array($manual)&&array_is_list($manual)&&is_array($absent)&&array_is_list($absent),'consumer_invalid');
        foreach(array_merge($manual,$absent) as $field)self::itemNeed(is_string($field)&&strlen($field)>0&&strlen($field)<=256,'consumer_invalid');
        sort($manual,SORT_STRING);sort($absent,SORT_STRING);
        $out['state']='observed';$out['profile']=self::facts($p)+['detailsAvailable'=>$p['detailsAvailable'],'revision'=>$p['revision'],'contentState'=>$p['contentState'],
            'manualFieldCount'=>count($manual),'manualFieldsSha256'=>self::hash($manual),'sourceAbsentCount'=>count($absent),'sourceAbsentSha256'=>self::hash($absent)];
        return [$out,$manual];
    }
    private static function gaps(array $source,array $consumer,array $manual): array {
        $out=['sourceMissing'=>[],'consumerMissing'=>[],'manualProtectedMissing'=>[],'possibleFillFields'=>[]];
        foreach(self::FIELDS as $field) {
            $present=$field==='images'?$source['galleryCount']>0:$source[$field]['present'];
            if(!$present)$out['sourceMissing'][]=$field;
            if($consumer['state']!=='observed')continue;
            $p=$consumer['profile'];$missing=$field==='images'?$p['galleryCount']===0:!$p[$field]['present'];
            if(!$missing)continue;
            $out['consumerMissing'][]=$field;$protected=false;
            foreach($manual as $path)if($path===$field||str_starts_with($path,$field.'.')||str_starts_with($field,$path.'.')){$protected=true;break;}
            if($protected)$out['manualProtectedMissing'][]=$field;
            elseif($present)$out['possibleFillFields'][]=$field;
        }
        return $out;
    }
    private static function observe(PDO $db,array $target): array {
        $id=$target['hotelId'];$q=$db->prepare('SELECT id,name,country_id,is_active FROM catalog_hotels WHERE id=? LIMIT 1');$q->execute([$id]);$catalog=$q->fetch(PDO::FETCH_ASSOC);
        self::itemNeed(is_array($catalog)&&(int)$catalog['is_active']===1,'catalog_hotel_missing_or_inactive');
        self::itemNeed((int)$catalog['id']===$id&&(int)$catalog['country_id']===$target['countryId']&&$catalog['name']===$target['name'],'producer_identity_drift');
        $q=$db->prepare("SELECT OCTET_LENGTH(raw_json) FROM catalog_hotel_details WHERE hotel_id=? AND status='success' LIMIT 1");$q->execute([$id]);$bytes=$q->fetchColumn();
        self::itemNeed($bytes!==false&&is_numeric($bytes)&&(int)$bytes>0,'retained_source_unavailable');
        self::need((int)$bytes<=1048576,'row_size_limit');
        $q=$db->prepare("SELECT raw_json,source_hash,fetched_at FROM catalog_hotel_details WHERE hotel_id=? AND status='success' LIMIT 1");$q->execute([$id]);$raw=$q->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($raw),'read_scope');$card=self::sourceCard($raw['raw_json'],$raw['source_hash'],$raw['fetched_at'],$target);
        $source=self::facts($card)+['detailsAvailable'=>true,'sourceSha256'=>$raw['source_hash'],'sourceFetchedAt'=>self::text($raw['fetched_at'])];
        [$consumer,$manual]=self::consumer($db,$target);
        return ['source'=>$source,'consumer'=>$consumer,'gaps'=>self::gaps($source,$consumer,$manual)];
    }
    private static function blank(array $target,string $reason): array {
        return ['hotelId'=>$target['hotelId'],'countryId'=>$target['countryId'],'recordSha256'=>$target['recordSha256'],
            'state'=>'held','reason'=>$reason,'source'=>null,'consumer'=>null,'gaps'=>null];
    }
    /** Fixture-only expected pins are never accepted by the runtime CLI. */
    public static function read(PDO $db,string $site,array $manifest,string $expectedDatabase=AnyTourHotelProfileCompletenessReadV1::DATABASE_SHA256,
        array $expectedProtected=['config.php'=>AnyTourHotelProfileCompletenessReadV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256]): array {
        $r=['schemaVersion'=>1,'source'=>'anytour-hotel-profile-batch-read-v1','state'=>'held','reason'=>'read_failed','observedAt'=>time(),
            'manifestSha256'=>self::MANIFEST_SHA256,'producerArtifactSha256'=>self::PRODUCER_ARTIFACT_SHA256,'producerJsonSha256'=>self::PRODUCER_JSON_SHA256,
            'requestedCount'=>self::COUNT,'observedCount'=>0,'heldCount'=>self::COUNT,'possibleGapCount'=>0,
            'databaseSha256Before'=>null,'databaseSha256After'=>null,'protectedBefore'=>null,'protectedAfter'=>null,
            'supplierCalls'=>0,'databaseWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,
            'priceAuthority'=>false,'importAllowed'=>false,'itemsSha256'=>null];
        $items=[];$ownTransaction=false;$targets=$manifest['records']??[];
        try {
            self::need(self::hash($manifest)===self::hash(self::manifest(__DIR__.'/fixtures/local-profile-batch-20261026.json')),'manifest_invalid');
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            $r['protectedBefore']=AnyTourHotelProfileCompletenessReadV1::protected($site);self::need($r['protectedBefore']===$expectedProtected,'protected_drift');
            $r['databaseSha256Before']=self::database($db);self::need($r['databaseSha256Before']===$expectedDatabase,'database_identity');
            self::tables($db);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');$ownTransaction=true;
            try {
                foreach($targets as $target) {
                    $item=self::blank($target,'read_failed');
                    try {$parts=self::observe($db,$target);$item=array_replace($item,['state'=>'observed','reason'=>null],$parts);}
                    catch(DomainException $e){$allowed=['catalog_hotel_missing_or_inactive','producer_identity_drift','retained_source_unavailable','retained_source_invalid','retained_source_identity','consumer_invalid'];
                        $item['reason']=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'retained_source_invalid';}
                    catch(JsonException $e){$item['reason']='consumer_invalid';}
                    $items[]=$item;
                }
            }finally{if($ownTransaction&&$db->inTransaction())$db->rollBack();$ownTransaction=false;}
            self::tables($db);$r['state']='completed_read_only';$r['reason']=null;
        }catch(Throwable $e){
            if($ownTransaction&&$db->inTransaction())try{$db->rollBack();}catch(Throwable $ignored){}
            $allowed=['manifest_invalid','caller_transaction','protected_drift','database_identity','schema_unavailable','row_size_limit','read_scope'];
            $r['reason']=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'read_failed';
        }finally{
            if($r['databaseSha256Before']!==null)try{$r['databaseSha256After']=self::database($db);self::need($r['databaseSha256After']===$expectedDatabase,'database_identity');}catch(Throwable $ignored){$r['state']='held';$r['reason']='database_identity';}
            if($r['protectedBefore']!==null)try{$r['protectedAfter']=AnyTourHotelProfileCompletenessReadV1::protected($site);self::need($r['protectedAfter']===$expectedProtected,'protected_drift');}catch(Throwable $ignored){$r['state']='held';$r['reason']='protected_drift';}
        }
        if($r['state']!=='completed_read_only')$items=array_map(static fn($target)=>self::blank($target,$r['reason']),$targets);
        self::need(count($items)===self::COUNT,'manifest_invalid');
        foreach($items as $item)if($item['state']==='observed'){++$r['observedCount'];if($item['gaps']['possibleFillFields']!==[])++$r['possibleGapCount'];}
        $r['heldCount']=self::COUNT-$r['observedCount'];$r['itemsSha256']=self::hash($items);
        $out=['receipt'=>$r,'items'=>$items];self::need(strlen(self::json($out))<=self::MAX_BYTES-1024,'result_size_limit');return $out;
    }
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if($argc!==1)throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');
        if($source!==AnyTourHotelProfileBatchReadV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)||$op!==AnyTourHotelProfileBatchReadV1::OPERATION
            ||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)
            ||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/'))throw new RuntimeException('CLI_SCOPE');
        foreach(['DSN','DB_USER','DB_PASSWORD','DB_HOST','DB_NAME','DB_PORT'] as $key)if(getenv('ANYTOUR_DATA_'.$key)!==false)throw new RuntimeException('CLI_ENV');
        $path=$dir.'/local-profile-batch-read.json';if(file_exists($path)||is_link($path))throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $manifest=AnyTourHotelProfileBatchReadV1::manifest(__DIR__.'/fixtures/local-profile-batch-20261026.json');
        if(AnyTourHotelProfileCompletenessReadV1::protected($site)!==['config.php'=>AnyTourHotelProfileCompletenessReadV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256])throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $sourceFiles=['hotel-presentation-read-v1.php'=>'17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3',
            'hotel-details-v1.php'=>'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
            'local-tv-catalog-v1.php'=>'abbed95e0343ce402cdcfb11ef7819da0a12e5bafb7e5bf7f22cbb9260167b11',
            'db-v1.php'=>AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256];
        foreach($sourceFiles as $file=>$sha){$dependency=__DIR__.'/../../v2/data/'.$file;if(!is_file($dependency)||is_link($dependency)||hash_file('sha256',$dependency)!==$sha)throw new RuntimeException('CLI_SOURCE');}
        $_SERVER['DOCUMENT_ROOT']=$site;require_once __DIR__.'/../../v2/data/db-v1.php';require_once __DIR__.'/../../v2/data/local-tv-catalog-v1.php';
        $result=AnyTourHotelProfileBatchReadV1::read(v2_data_db(),$site,$manifest);
        $result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];$json=AnyTourHotelProfileBatchReadV1::json($result)."\n";
        if(strlen($json)>AnyTourHotelProfileBatchReadV1::MAX_BYTES)throw new RuntimeException('CLI_SIZE');
        $old=umask(0077);try{$h=fopen($path,'x+b');}finally{umask($old);}if($h===false)throw new RuntimeException('CLI_CREATE');
        try{if(fwrite($h,$json)!==strlen($json)||!fflush($h)||!fsync($h))throw new RuntimeException('CLI_WRITE');}finally{fclose($h);}echo $json;
        exit($result['receipt']['state']==='completed_read_only'?0:1);
    }catch(Throwable $ignored){fwrite(STDERR,"LOCAL_PROFILE_BATCH_READ_FAILED\n");exit(1);}
}
