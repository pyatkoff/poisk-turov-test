<?php
/** New metadata-only context for one sealed pre-PHP install refusal. Never installs or retries. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/anytour_hotel_stay_schema_read_v1.php';

final class AnyTourHotelStayInstallContextReadV1
{
    public const OPERATION='int-andromeda-local-stay-install-context-read-20261026-v1';
    public const READ_OPERATION='int-andromeda-local-stay-schema-read-20261025-v1';
    public const FAILED_OPERATION='int-andromeda-local-stay-schema-install-20261026-v1';
    public const READ_CONTROL='a4faad3ba37937a44249df68e9a1530b3f5f258f';
    public const READ_SOURCE='30a7e7013edd7fe70a4700db0aafb2bf22ee05f8';
    public const HELPER_SHA256='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4';
    public const MAX_BYTES=262144;

    public static function json(mixed $value): string {return AnyTourHotelStaySchemaReadV1::json($value);}
    private static function need(bool $ok): void {if (!$ok) throw new RuntimeException('CONTEXT_CONTRACT');}
    private static function digest(mixed $value): string {return hash('sha256',self::json($value));}
    private static function protectedFiles(array $files): void
    {
        $keys=array_keys($files);sort($keys,SORT_STRING);self::need($keys===['config.php','data/db-v1.php']);
        foreach ($files as $sha) self::need(is_string($sha)&&preg_match('/^[a-f0-9]{64}$/D',$sha)===1);
    }

    private static function lineage(array $parent,array $failure): array
    {
        $data=$parent['local_stay_schema_read']['data']??null;$r=$data['receipt']??null;
        self::need(is_array($data)&&is_array($r)&&($parent['status']??null)==='complete'
            &&($parent['mode']??null)==='local-stay-schema-read-v1'&&($parent['operation_id']??null)===self::READ_OPERATION
            &&($parent['source_sha']??null)===self::READ_SOURCE&&($r['schemaVersion']??null)===1
            &&($r['source']??null)==='anytour-hotel-stay-schema-read-v1'&&($r['state']??null)==='completed_read_only'
            &&($r['operationId']??null)===self::READ_OPERATION&&($r['sourceSha']??null)===self::READ_SOURCE
            &&($r['controlSha']??null)===self::READ_CONTROL&&is_array($data['inventory']??null)&&is_array($data['plan']??null)
            &&($r['inventorySha256']??null)===self::digest($data['inventory'])
            &&($r['planSha256']??null)===self::digest($data['plan'])
            &&($r['migrationSha256']??null)===AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256);
        foreach (['supplierCalls','databaseWrites','schemaWrites','automaticAccepts'] as $key) self::need(($r[$key]??null)===0);
        $previous=$parent['local_stay_schema_read']['protectedBefore']??null;
        self::need(is_array($previous)&&$previous===($parent['local_stay_schema_read']['protectedAfter']??null));
        self::protectedFiles($previous);self::need($previous['data/db-v1.php']===self::HELPER_SHA256);
        self::need(($failure['schema_version']??null)===1&&($failure['mode']??null)==='local-stay-schema-install-v1'
            &&($failure['operation_id']??null)===self::FAILED_OPERATION&&($failure['source_sha']??null)===self::READ_SOURCE
            &&($failure['status']??null)==='unknown_no_replay'&&($failure['reason']??null)==='local_stay_install_private_drift'
            &&($failure['schema_write_outcome_unknown']??null)===false
            &&!array_key_exists('local_stay_schema_install',$failure));
        foreach (['booking_calls','database_writes','lead_calls','mapping_writes','profile_writes','schema_writes',
            'schema_writes_acknowledged','supplier_calls'] as $key) self::need(($failure[$key]??null)===0);
        return $data;
    }

    public static function unavailable(array $parent,array $failure,array $currentProtected): array
    {
        $old=self::lineage($parent,$failure);self::protectedFiles($currentProtected);
        self::need($currentProtected['data/db-v1.php']===self::HELPER_SHA256);
        $previous=$parent['local_stay_schema_read']['protectedBefore'];$changed=[];
        foreach (['config.php','data/db-v1.php'] as $name) if ($currentProtected[$name]!==$previous[$name]) $changed[]=$name;
        return ['receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-stay-install-context-read-v1','state'=>'connection_unavailable','observedAt'=>time(),
            'parentReadOperation'=>self::READ_OPERATION,'failedInstallOperation'=>self::FAILED_OPERATION,
            'parentReadInventorySha256'=>$old['receipt']['inventorySha256'],'failedInstallReason'=>'local_stay_install_private_drift',
            'migrationSha256'=>AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,
            'previousProtected'=>$previous,'currentProtectedBefore'=>$currentProtected,'currentProtectedAfter'=>$currentProtected,
            'changedFiles'=>$changed,'helperAccepted'=>true,'phpExecuted'=>true,
            'connectionAuthority'=>'not_examined','oldDatabaseIdentityKnown'=>false,'databaseSha256'=>null,
            'schemaState'=>null,'inventorySha256'=>null,'planSha256'=>null,'schemaMatchesParent'=>null,'applyAuthorized'=>false],
            'inventory'=>null,'plan'=>null];
    }

    private static function database(PDO $db): string
    {
        $row=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($row)&&is_string($row['selected_database']??null)&&$row['selected_database']!==''
            &&strlen($row['selected_database'])<=64&&is_string($row['server_uuid']??null)
            &&preg_match('/^[a-f0-9-]{36}$/Di',$row['server_uuid'])===1);
        return self::digest(['database'=>$row['selected_database'],'serverUuid'=>$row['server_uuid']]);
    }

    /** PDO is the CURRENT configured connection. No old database identity was measured or inferred. */
    public static function read(PDO $db,array $parent,array $failure,array $currentProtected): array
    {
        $result=self::unavailable($parent,$failure,$currentProtected);$r=&$result['receipt'];
        if ($db->inTransaction()||$db->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) {
            $r['state']='schema_unavailable';$r['schemaState']='read_failed';return $result;
        }
        try {
            $identity=self::database($db);
            $schema=AnyTourHotelStaySchemaReadV1::read($db);
            self::need(self::database($db)===$identity);
            $r['databaseSha256']=$identity;$r['connectionAuthority']='current_root_config_and_accepted_helper';
            $r['schemaState']=$schema['receipt']['state'];
            if (strlen(self::json($schema))>self::MAX_BYTES-8192) {
                $r['state']='schema_unavailable';$r['schemaState']='metadata_limit';return $result;
            }
            $result['inventory']=$schema['inventory'];$result['plan']=$schema['plan'];
            $r['inventorySha256']=$schema['receipt']['inventorySha256'];$r['planSha256']=$schema['receipt']['planSha256'];
            if ($schema['inventory']!==null) $r['schemaMatchesParent']=self::json($schema['inventory'])===self::json($parent['local_stay_schema_read']['data']['inventory']);
            $r['state']=$r['schemaState']==='completed_read_only'?'completed_read_only':'schema_unavailable';
        } catch (Throwable $ignored) {
            // A failed CURRENT connection/identity yields only protected-file facts, never raw errors or an old-target claim.
            $result=self::unavailable($parent,$failure,$currentProtected);
        }
        return $result;
    }

    public static function after(array $result,array $currentProtectedAfter): array
    {
        self::protectedFiles($currentProtectedAfter);$result['receipt']['currentProtectedAfter']=$currentProtectedAfter;
        if ($result['receipt']['currentProtectedBefore']!==$currentProtectedAfter) $result['receipt']['state']='protected_drift';
        return $result;
    }

    public static function fingerprint(string $site): array
    {
        $files=[];
        foreach (['config.php','data/db-v1.php'] as $name) {
            $path=$site.'/'.$name;self::need(is_file($path)&&!is_link($path)&&realpath($path)===$path
                &&filesize($path)>0&&filesize($path)<=2097152);
            $files[$name]=hash_file('sha256',$path);
        }
        return $files;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');
        $operation=(string)getenv('ANYTOUR_OPERATION_ID');$parentPath=(string)getenv('ANYTOUR_CONTEXT_PARENT_READ_PATH');
        $failurePath=(string)getenv('ANYTOUR_CONTEXT_FAILED_INSTALL_PATH');
        if (!preg_match('/^[a-f0-9]{40}$/D',$source)||!preg_match('/^[a-f0-9]{40}$/D',$control)
            ||$operation!==AnyTourHotelStayInstallContextReadV1::OPERATION||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            ||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)||basename($dir)!==$operation
            ||str_starts_with($dir.'/',$site.'/')
            ||$parentPath!==dirname($dir).'/'.AnyTourHotelStayInstallContextReadV1::READ_OPERATION.'/result.json'
            ||$failurePath!==dirname($dir).'/'.AnyTourHotelStayInstallContextReadV1::FAILED_OPERATION.'/result.json') throw new RuntimeException('CLI_SCOPE');
        foreach ([$parentPath,$failurePath] as $path) if (!is_file($path)||is_link($path)||filesize($path)>262144) throw new RuntimeException('CLI_LINEAGE');
        $path=$dir.'/local-stay-install-context-read.json';if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $parent=json_decode((string)file_get_contents($parentPath),true,512,JSON_THROW_ON_ERROR);
        $failure=json_decode((string)file_get_contents($failurePath),true,512,JSON_THROW_ON_ERROR);
        $before=AnyTourHotelStayInstallContextReadV1::fingerprint($site);
        $result=AnyTourHotelStayInstallContextReadV1::unavailable($parent,$failure,$before);
        $helper=__DIR__.'/../../v2/data/db-v1.php';
        if (!is_file($helper)||is_link($helper)||hash_file('sha256',$helper)!==AnyTourHotelStayInstallContextReadV1::HELPER_SHA256) throw new RuntimeException('CLI_HELPER');
        $_SERVER['DOCUMENT_ROOT']=$site;
        try {require_once $helper;$result=AnyTourHotelStayInstallContextReadV1::read(v2_data_db(),$parent,$failure,$before);}
        catch (Throwable $ignored) {} // Preserve a sanitized connection_unavailable receipt.
        $result=AnyTourHotelStayInstallContextReadV1::after($result,AnyTourHotelStayInstallContextReadV1::fingerprint($site));
        $result['receipt']+=['operationId'=>$operation,'sourceSha'=>$source,'controlSha'=>$control];
        $json=AnyTourHotelStayInstallContextReadV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStayInstallContextReadV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);try {$handle=fopen($path,'x+b');} finally {umask($old);}
        if ($handle===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {if (fwrite($handle,$json)!==strlen($json)||!fflush($handle)||!fsync($handle)) throw new RuntimeException('CLI_RECEIPT_WRITE');}
        finally {fclose($handle);}
        echo $json;exit($result['receipt']['state']==='protected_drift'?1:0);
    } catch (Throwable $ignored) {fwrite(STDERR,"LOCAL_STAY_INSTALL_CONTEXT_READ_FAILED\n");exit(1);}
}
