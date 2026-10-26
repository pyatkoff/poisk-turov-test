<?php
/** One fixed, read-only completeness/provenance observation for catalog hotel 475. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

final class AnyTourHotelProfileCompletenessReadV1
{
    public const SOURCE = '1d0e529e3fc0666783db1454c0765d3c16d63843';
    public const OPERATION = 'int-andromeda-local-profile-completeness-read-20261026-v1';
    public const HOTEL_ID = 475;
    public const COUNTRY_ID = 1;
    public const NAME = 'SUNNY DAYS PALMA DE MIRETTE RESORT';
    public const PRODUCER_RECORD_SHA256 = '6e2dcaad78922dc71375504118ccfeb9be03a7e412604e2e52fd5e40e94ffe87';
    public const PRODUCER_ARTIFACT_SHA256 = 'c67a9aca2c2efd00e4a187cc43a3b6d258bd5629457778ee03d82e28f0d63b00';
    public const DATABASE_SHA256 = '4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d';
    public const CONFIG_SHA256 = '121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def';
    public const HELPER_SHA256 = 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4';
    public const MAX_BYTES = 262144;

    private static function need(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
    private static function canonical(mixed $value): string {
        $sort = static function (mixed $v) use (&$sort): mixed {
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v, SORT_STRING);
            foreach ($v as $k => $part) $v[$k] = $sort($part);
            return $v;
        };
        return json_encode($sort($value), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    private static function digest(mixed $value): string { return hash('sha256', is_string($value) ? $value : self::canonical($value)); }
    public static function protected(string $site): array {
        $out=[];
        foreach (['config.php','data/db-v1.php'] as $name) {
            $path=$site.'/'.$name;
            self::need(is_file($path)&&!is_link($path)&&realpath($path)===$path&&filesize($path)>0&&filesize($path)<=2097152, 'protected_drift');
            $out[$name]=hash_file('sha256',$path);
        }
        return $out;
    }
    private static function database(PDO $db): string {
        $row=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($row)&&is_string($row['selected_database']??null)&&$row['selected_database']!==''
            &&is_string($row['server_uuid']??null)&&preg_match('/^[a-f0-9-]{36}$/Di',$row['server_uuid'])===1,'database_identity');
        return self::digest(['database'=>$row['selected_database'],'serverUuid'=>$row['server_uuid']]);
    }
    private static function textFact(mixed $value): array {
        $present=is_string($value)&&trim($value)!=='';
        return ['present'=>$present,'byteLength'=>$present?strlen($value):0,'sha256'=>$present?self::digest($value):null];
    }
    private static function tables(PDO $db): void {
        $rows=$db->query("SELECT TABLE_NAME,TABLE_TYPE,ENGINE FROM information_schema.TABLES WHERE BINARY TABLE_SCHEMA=BINARY DATABASE() AND TABLE_NAME IN ('catalog_hotels','catalog_hotel_details','local_tv_hotels') ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
        self::need(count($rows)===3,'schema_unavailable');
        foreach ($rows as $row) self::need($row['TABLE_TYPE']==='BASE TABLE'&&$row['ENGINE']==='InnoDB','schema_unavailable');
    }
    private static function sourceCard(string $raw,string $hash,string $at): array {
        self::need(strlen($raw)<=1048576&&hash_equals(hash('sha256',$raw),$hash),'retained_source_invalid');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at,new DateTimeZone('UTC'));
        self::need($date!==false&&$date->format('Y-m-d H:i:s')===$at,'retained_source_invalid');
        $payload=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        self::need(is_array($payload),'retained_source_invalid');
        $card=LocalTvCatalogV1::normalize($payload,self::HOTEL_ID)['content'];
        self::need(($card['name']??null)===self::NAME&&v2_hotel_detail_int($card['country']['id']??null)===self::COUNTRY_ID,'retained_source_invalid');
        return $card;
    }
    private static function consumer(PDO $db): array {
        $size=$db->query('SELECT COALESCE(OCTET_LENGTH(source_json),0)+OCTET_LENGTH(discovery_json)+OCTET_LENGTH(content_json)+OCTET_LENGTH(manual_json)+OCTET_LENGTH(source_absent_json) FROM local_tv_hotels WHERE id=475 LIMIT 1')->fetchColumn();
        if($size===false)return ['state'=>'missing','profile'=>null,'sourceSha256'=>null,'sourceFetchedAt'=>null];
        self::need(is_numeric($size)&&(int)$size<=2097152,'row_size_limit');
        $row=$db->query('SELECT state,source_json,source_sha256,source_fetched_at FROM local_tv_hotels WHERE id=475 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($row),'read_scope');
        if($row['source_json']!==null) self::sourceCard($row['source_json'],(string)$row['source_sha256'],(string)$row['source_fetched_at']);
        else self::need($row['source_sha256']===null&&$row['source_fetched_at']===null,'retained_source_invalid');
        $result=(new LocalTvCatalogV1($db))->read([self::HOTEL_ID],false);
        self::need($result['source']===LocalTvCatalogV1::SOURCE&&$result['catalog']==='local-tv'&&$result['requestedIds']===[475]&&$result['links']===[],'read_scope');
        if($result['missingIds']===[475])return ['state'=>'excluded','profile'=>null,'sourceSha256'=>$row['source_sha256'],'sourceFetchedAt'=>self::textFact($row['source_fetched_at'])];
        self::need($result['missingIds']===[]&&count($result['items'])===1&&$result['items'][0]['id']===475,'read_scope');
        $p=$result['items'][0];$manual=$p['manualFields'];sort($manual,SORT_STRING);
        return ['state'=>'observed','sourceSha256'=>$row['source_sha256'],'sourceFetchedAt'=>self::textFact($row['source_fetched_at']),
            'profile'=>['description'=>self::textFact($p['description']??null),'primaryImage'=>self::textFact($p['primaryImage']??null),
                'galleryCount'=>count($p['images']),'gallerySha256'=>self::digest($p['images']),'detailsAvailable'=>$p['detailsAvailable'],
                'revision'=>$p['revision'],'contentState'=>$p['contentState'],'manualFieldCount'=>count($manual),'manualFieldsSha256'=>self::digest($manual),
                'sourceAbsentCount'=>count($p['sourceAbsent']),'sourceAbsentSha256'=>self::digest($p['sourceAbsent'])]];
    }
    /** Extra arguments are native-fixture pins; the runtime CLI always supplies the literals above. */
    public static function read(PDO $db, string $site, string $expectedDatabase=self::DATABASE_SHA256,
        array $expectedProtected=['config.php'=>self::CONFIG_SHA256,'data/db-v1.php'=>self::HELPER_SHA256]): array {
        $receipt=['schemaVersion'=>1,'source'=>'anytour-hotel-profile-completeness-read-v1','state'=>'read_failed','observedAt'=>time(),
            'catalogHotelId'=>self::HOTEL_ID,'countryId'=>self::COUNTRY_ID,'producerNamespace'=>'catalog_hotels',
            'producerRecordSha256'=>self::PRODUCER_RECORD_SHA256,'producerArtifactSha256'=>self::PRODUCER_ARTIFACT_SHA256,
            'databaseSha256Before'=>null,'databaseSha256After'=>null,'protectedBefore'=>null,'protectedAfter'=>null,
            'supplierCalls'=>0,'databaseWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,
            'crossNamespaceBridge'=>'not_read_not_required','priceAuthority'=>false,'importAllowed'=>false,'profileSha256'=>null];
        $profile=null;$ownTransaction=false;
        try {
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                &&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            $receipt['protectedBefore']=self::protected($site);
            self::need($receipt['protectedBefore']===$expectedProtected,'protected_drift');
            $receipt['databaseSha256Before']=self::database($db);
            self::need($receipt['databaseSha256Before']===$expectedDatabase,'database_identity');
            self::tables($db);
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->exec('START TRANSACTION READ ONLY');$ownTransaction=true;
            try {
                // Bound all large fields before the existing DTO reader materializes this exact row.
                $size=$db->query("SELECT COALESCE(OCTET_LENGTH(d.description),0)+COALESCE(OCTET_LENGTH(d.images_json),0)+COALESCE(OCTET_LENGTH(d.infrastructure_json),0)+COALESCE(OCTET_LENGTH(d.meals_json),0)+COALESCE(OCTET_LENGTH(d.services_json),0)+COALESCE(OCTET_LENGTH(d.room_types),0)+COALESCE(OCTET_LENGTH(d.address),0)+COALESCE(OCTET_LENGTH(d.place),0)+COALESCE(OCTET_LENGTH(d.build_info),0)+COALESCE(OCTET_LENGTH(d.repair_info),0)+COALESCE(OCTET_LENGTH(d.square_info),0)+COALESCE(OCTET_LENGTH(h.primary_image_url),0) AS bytes FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id AND d.status='success' WHERE h.id=475 AND h.is_active=1 LIMIT 1")->fetchColumn();
                self::need($size===false||(is_numeric($size)&&(int)$size<=1048576),'row_size_limit');
                $rawSize=$db->query("SELECT OCTET_LENGTH(raw_json) FROM catalog_hotel_details WHERE hotel_id=475 AND status='success' LIMIT 1")->fetchColumn();
                self::need($rawSize!==false&&is_numeric($rawSize)&&(int)$rawSize>0&&(int)$rawSize<=1048576,'retained_source_unavailable');
                $raw=$db->query("SELECT raw_json,source_hash,fetched_at FROM catalog_hotel_details WHERE hotel_id=475 AND status='success' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                self::sourceCard($raw['raw_json'],(string)$raw['source_hash'],(string)$raw['fetched_at']);
                $result=hotel_presentation_read_many($db,[self::HOTEL_ID]);
                self::need(($result['requestedIds']??null)===[self::HOTEL_ID],'read_scope');
                if (($result['missingIds']??null)===[self::HOTEL_ID]) { $receipt['state']='catalog_hotel_missing_or_inactive'; }
                else {
                    self::need(($result['missingIds']??null)===[]&&count($result['items']??[])===1,'read_scope');
                    $item=$result['items'][0];
                    self::need(($item['id']??null)===self::HOTEL_ID&&($item['country']['id']??null)===self::COUNTRY_ID
                        &&($item['name']??null)===self::NAME,'producer_identity_drift');
                    $gallery=is_array($item['images']??null)?$item['images']:[];
                    $profile=['name'=>self::textFact($item['name']),'detailsAvailable'=>($item['detailsAvailable']??false)===true,
                        'description'=>self::textFact($item['description']??null),'primaryImage'=>self::textFact($item['primaryImage']??null),
                        'galleryCount'=>count($gallery),'gallerySha256'=>self::digest($gallery),
                        'roomTypes'=>self::textFact($item['roomTypes']??null),'address'=>self::textFact($item['address']??null),
                        'detailsFetchedAt'=>self::textFact($item['detailsFetchedAt']??null),
                        'retainedSource'=>['sha256'=>$raw['source_hash'],'fetchedAt'=>self::textFact($raw['fetched_at']),'identity'=>'verified_tourvisor_475'],
                        'consumer'=>self::consumer($db),
                        'provenance'=>['reader'=>'hotel-presentation-read-v1','detailsSource'=>'catalog_hotel_details_success_only',
                            'consumerReader'=>'local-tv-catalog-v1','manualOrOwnSource'=>'effective_manual_fields_hashed','generatedSeoAcceptedAsTruth'=>false]];
                    $receipt['profileSha256']=self::digest($profile);$receipt['state']='completed_read_only';
                }
            } finally { if ($ownTransaction&&$db->inTransaction()) $db->rollBack();$ownTransaction=false; }
            self::tables($db);
        } catch (Throwable $e) {
            if ($ownTransaction&&$db->inTransaction()) try {$db->rollBack();} catch (Throwable $ignored) {}
            $map=['protected_drift'=>'protected_drift','database_identity'=>'database_identity','caller_transaction'=>'caller_transaction',
                'producer_identity_drift'=>'producer_identity_drift','read_scope'=>'read_scope','schema_unavailable'=>'schema_unavailable','row_size_limit'=>'row_size_limit',
                'retained_source_invalid'=>'retained_source_invalid','retained_source_unavailable'=>'retained_source_unavailable'];
            $receipt['state']=$map[$e->getMessage()]??'read_failed';$profile=null;
        } finally {
            if ($receipt['databaseSha256Before']!==null) try {$receipt['databaseSha256After']=self::database($db);self::need($receipt['databaseSha256After']===$expectedDatabase,'database_identity');} catch (Throwable $ignored) {$receipt['state']='database_identity';$profile=null;}
            if ($receipt['protectedBefore']!==null) try {$receipt['protectedAfter']=self::protected($site);self::need($receipt['protectedAfter']===$expectedProtected,'protected_drift');} catch (Throwable $ignored) {$receipt['state']='protected_drift';$profile=null;}
        }
        if ($receipt['state']!=='completed_read_only') {$profile=null;$receipt['profileSha256']=null;}
        return ['receipt'=>$receipt,'profile'=>$profile];
    }
    public static function json(mixed $value): string { return self::canonical($value); }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');
        if ($source!==AnyTourHotelProfileCompletenessReadV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)
            ||$op!==AnyTourHotelProfileCompletenessReadV1::OPERATION||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            ||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/')) throw new RuntimeException('CLI_SCOPE');
        foreach (['DSN','DB_USER','DB_PASSWORD','DB_HOST','DB_NAME','DB_PORT'] as $key) if (getenv('ANYTOUR_DATA_'.$key)!==false) throw new RuntimeException('CLI_ENV');
        $path=$dir.'/local-profile-completeness-read.json';if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        if (AnyTourHotelProfileCompletenessReadV1::protected($site)!==['config.php'=>AnyTourHotelProfileCompletenessReadV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256]) throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $presentation=__DIR__.'/../../v2/data/hotel-presentation-read-v1.php';$helper=__DIR__.'/../../v2/data/db-v1.php';
        if (!is_file($presentation)||is_link($presentation)||hash_file('sha256',$presentation)!=='17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3'
            ||!is_file($helper)||is_link($helper)||hash_file('sha256',$helper)!==AnyTourHotelProfileCompletenessReadV1::HELPER_SHA256) throw new RuntimeException('CLI_SOURCE');
        $_SERVER['DOCUMENT_ROOT']=$site;require_once $presentation;require_once $helper;require_once __DIR__.'/../../v2/data/local-tv-catalog-v1.php';
        $result=AnyTourHotelProfileCompletenessReadV1::read(v2_data_db(),$site);
        $result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];$json=AnyTourHotelProfileCompletenessReadV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelProfileCompletenessReadV1::MAX_BYTES) throw new RuntimeException('CLI_SIZE');
        $old=umask(0077);try {$h=fopen($path,'x+b');}finally{umask($old);}if($h===false)throw new RuntimeException('CLI_CREATE');
        try{if(fwrite($h,$json)!==strlen($json)||!fflush($h)||!fsync($h))throw new RuntimeException('CLI_WRITE');}finally{fclose($h);}echo $json;
        exit(in_array($result['receipt']['state'],['completed_read_only','catalog_hotel_missing_or_inactive','producer_identity_drift'],true)?0:1);
    } catch (Throwable $ignored) { fwrite(STDERR,"LOCAL_PROFILE_COMPLETENESS_READ_FAILED\n"); exit(1); }
}
