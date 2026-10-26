<?php
/** New permission/account-bound exactly-three CREATE admission. No old installer is invoked. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require_once __DIR__.'/anytour_hotel_stay_schema_read_v1.php';
require_once __DIR__.'/anytour_hotel_stay_context_permission_read_v1.php';

final class AnyTourHotelStayPermissionInstallV1
{
    public const OPERATION='int-andromeda-local-stay-permission-install-20261026-v1';
    public const PARENT_OPERATION='int-andromeda-local-stay-context-permission-read-20261026-v1';
    public const PARENT_CONTROL='b4fc7c3c00c56ba205ade7dabfc214490d8de279';
    public const PARENT_SHA256='ab218f78c62e1d57e9d6be54203134adedb07c382ad949719b130e0e9d4b031e';
    public const PARENT_FILE_SHA256='33d60c6a1f580b965547982f49d10c3a956bcb0c24fa19c7b94d128fdb76777b';
    public const PARENT_ENGINE_SHA256='c15febf3673ebefc1169e704b3b2e8eba3e1faf4962fb07c4ec637d6cc0ab068';
    public const SOURCE='1d0e529e3fc0666783db1454c0765d3c16d63843';
    public const DATABASE_SHA256='4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d';
    public const ACCOUNT_SHA256='7ef350d330c1c4e04d1232ba7649b559d85a4930a512746585cba4aadb9d4ef8';
    public const ROLE_SHA256='08f51cfad4b88bfff85676aac5da3d61ddacc7612f6106db7759ee5fff1411d0';
    public const COVERAGE_SHA256='f4fea51dd60b2e3944e4ab9bde80e4d5ba2fd8d8d12dda907f9b45a613a24a5b';
    public const METADATA_SHA256='fc2221f539a7fa83c66ad2fda24922e0bbf34b87bb97cddc2bd10e83ec17c59c';
    public const MAX_BYTES=262144;
    public const TARGETS=['anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2'];
    private const LOCK='anytour-hotel-stay-schema-v2';

    public static function json(mixed $value): string { return AnyTourHotelStaySchemaReadV1::json($value); }
    private static function need(bool $ok,string $reason): void { if (!$ok) throw new RuntimeException($reason); }
    private static function digest(mixed $value): string { return hash('sha256',self::json($value)); }

    private static function database(PDO $db): string
    {
        $row=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($row)&&is_string($row['selected_database']??null)&&$row['selected_database']!==''
            &&strlen($row['selected_database'])<=64&&is_string($row['server_uuid']??null)
            &&preg_match('/^[a-f0-9-]{36}$/Di',$row['server_uuid'])===1,'database_identity');
        return self::digest(['database'=>$row['selected_database'],'serverUuid'=>$row['server_uuid']]);
    }

    private static function statements(): array
    {
        $path=__DIR__.'/../../v2/data/migrations/20260918-anytour-hotel-stay-v2.sql';
        self::need(is_file($path)&&!is_link($path)&&hash_file('sha256',$path)===AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,'source_pin');
        $sql=file_get_contents($path);self::need(is_string($sql)&&strlen($sql)<32768,'source_pin');
        $sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
        $statements=array_values(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]),static fn($v)=>$v!==''));
        self::need(count($statements)===3,'source_pin');
        foreach (self::TARGETS as $i=>$table) self::need(str_starts_with($statements[$i],'CREATE TABLE '.$table.' ('),'source_pin');
        return $statements;
    }

    private static function noCollisions(PDO $db,array $statements): bool
    {
        preg_match_all('/\bCONSTRAINT\s+([a-z0-9_]+)\s+(?:FOREIGN KEY|CHECK)\b/',implode("\n",$statements),$m);
        $names=$m[1];self::need(count($names)===14&&count(array_unique($names))===14,'source_pin');
        $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE BINARY CONSTRAINT_SCHEMA=BINARY DATABASE()
            AND CONSTRAINT_TYPE IN (\'FOREIGN KEY\',\'CHECK\') AND LOWER(CONSTRAINT_NAME) IN ('.implode(',',array_fill(0,14,'?')).')');
        $q->execute($names);return (int)$q->fetchColumn()===0;
    }

    private static function binding(array $proof,string $expected): array
    {
        $r=$proof['receipt']??null;$e=$proof['engine']??null;$er=$e['receipt']??null;$c=$e['coverage']??null;
        self::need(is_array($r)&&is_array($e)&&is_array($er)&&is_array($c)
            &&($r['state']??null)==='completed_read_only'&&($r['allRequiredProven']??null)===true
            &&($r['databaseBindingKnown']??null)===true&&($r['accountBindingKnown']??null)===true&&($r['roleBindingKnown']??null)===true
            &&($r['engineOperationBound']??null)===false&&($r['engineCliExecuted']??null)===false&&($r['ddlAdmission']??null)===false
            &&($r['engineSourceSha256']??null)===AnyTourHotelStayContextPermissionReadV1::ENGINE_SHA256
            &&($r['engineResultSha256']??null)===self::digest($e)&&($er['state']??null)==='completed_read_only'
            &&($er['allRequiredProven']??null)===true&&($er['databaseSha256Before']??null)===$expected&&($er['databaseSha256After']??null)===$expected
            &&($c['state']??null)==='complete'&&($er['coverageSha256']??null)===self::digest(['coverage'=>$c,'privileges'=>$e['privileges']??null])
            &&($c['metadataSha256Before']??null)===($c['metadataSha256After']??null),'permission_proof');
        foreach (['account','role'] as $kind) self::need(is_string($er[$kind.'Sha256Before']??null)&&preg_match('/^[a-f0-9]{64}$/D',$er[$kind.'Sha256Before'])===1
            &&$er[$kind.'Sha256Before']===$er[$kind.'Sha256After'],'permission_proof');
        foreach (AnyTourHotelStayPermissionProofReadV1::PRIVILEGES as $key) self::need(($e['privileges'][$key]['status']??null)==='proven'&&($e['privileges'][$key]['coverage']??null)==='complete','permission_proof');
        return ['databaseSha256'=>$expected,'accountSha256'=>$er['accountSha256Before'],'roleSha256'=>$er['roleSha256Before'],
            'coverageSha256'=>$er['coverageSha256'],'metadataSha256'=>$c['metadataSha256Before']];
    }
    private static function parent(array $parent,string $expected): array
    {
        $item=$parent['local_stay_context_permission']??null;$data=$item['data']??null;$r=$data['receipt']??null;
        self::need(is_array($item)&&is_array($data)&&is_array($r)&&($parent['status']??null)==='complete'
            &&($parent['mode']??null)==='local-stay-context-permission-read-v1'&&($parent['operation_id']??null)===self::PARENT_OPERATION&&($parent['source_sha']??null)===self::SOURCE
            &&($r['operationId']??null)===self::PARENT_OPERATION&&($r['controlSha']??null)===self::PARENT_CONTROL&&($r['sourceSha']??null)===self::SOURCE
            &&($item['usableProof']??null)===true&&($item['fileSha256']??null)===hash('sha256',self::json($data)."\n"),'permission_parent');
        self::binding($data,$expected);return $data;
    }
    private static function permission(PDO $db,array $context,array $failed,array $raw,string $site,string $expected,array $binding): array
    {
        $p=AnyTourHotelStayContextPermissionReadV1::read($db,$context,$failed,$raw,$site,$expected);
        self::need(self::binding($p,$expected)===$binding,'permission_drift');return $p;
    }
    private static function currentBinding(PDO $db,array $binding): void
    {
        $row=$db->query(AnyTourHotelStayPermissionProofReadV1::BINDING_SQL)->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($row)&&isset($row['selected_database'],$row['server_uuid'],$row['account'],$row['roles'])
            &&self::digest(['database'=>$row['selected_database'],'serverUuid'=>$row['server_uuid']])===$binding['databaseSha256']
            &&self::digest(['account'=>$row['account']])===$binding['accountSha256']&&self::digest(['roles'=>$row['roles']])===$binding['roleSha256']
            &&(string)$row['lower_case_table_names']==='0'&&(string)$row['partial_revokes']==='0'&&$row['roles']==='NONE','permission_drift');
    }

    /** Writer-owned qualification only; the legacy reader output is never changed. */
    public static function postVerification(array $raw,array $before,array $firstProof,array $lastProof,string $expected): ?string
    {
        try {
            self::need(self::binding($firstProof,$expected)===self::binding($lastProof,$expected),'permission_drift');
            $inventory=$raw['inventory'];$plan=$raw['plan'];
            self::need($raw['receipt']['state']==='completed_read_only'&&$inventory['server']['checkEnforcement']==='supported'
                &&self::json(array_slice($inventory['tables'],0,2))===self::json(array_slice($before['tables'],0,2)),'postflight');
            if ($plan['state']==='ready_no_op'&&$plan['existingCompatibleTables']===self::TARGETS) return 'raw_ready';
            self::need($plan['state']==='blocked_server_capability'&&$inventory['server']['triggerVisibilityProven']===false
                &&$plan['createTables']===[]&&$plan['blockedTables']===self::TARGETS,'postflight');
            foreach (array_slice($inventory['tables'],2) as $table) self::need($table['exists']===true&&$table['triggerCount']===0
                &&$table['compatibility']==='incompatible'&&$table['issues']===['trigger_visibility_not_proven'],'postflight');
            return 'permission_qualified';
        } catch (Throwable $ignored) {return null;}
    }

    private static function fingerprint(string $site): array
    {
        $files=[];
        foreach (['config.php','data/db-v1.php'] as $name) {
            $path=$site.'/'.$name;self::need(is_file($path)&&!is_link($path)&&realpath($path)===$path
                &&filesize($path)>0&&filesize($path)<=2097152,'protected_drift');
            $files[$name]=hash_file('sha256',$path);
        }
        return $files;
    }

    private static function journalEvent($handle,array $event): void
    {
        $raw=self::json($event)."\n";$stat=fstat($handle);
        self::need(is_array($stat)&&$stat['size']+strlen($raw)<self::MAX_BYTES
            &&fwrite($handle,$raw)===strlen($raw)&&fflush($handle)&&fsync($handle),'journal');
    }

    /** Fixture-only expectedDatabase argument has no runtime CLI/env override. DDL ACKs never imply rollback. */
    public static function install(PDO $db,array $parent,array $context,array $failed,array $raw,string $journalPath,string $site,string $expectedDatabase=self::DATABASE_SHA256): array
    {
        $r=['schemaVersion'=>1,'source'=>'anytour-hotel-stay-permission-install-v1','state'=>'preflight_refused','reason'=>null,
            'migrationSha256'=>AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,'permissionOperation'=>self::PARENT_OPERATION,
            'permissionResultSha256'=>self::digest($parent),'permissionFileSha256'=>null,'permissionEngineSha256'=>null,
            'contextResultSha256'=>self::digest($context),'failedPermissionResultSha256'=>self::digest($failed),'rawResultSha256'=>self::digest($raw),
            'databaseSha256'=>null,'accountSha256'=>null,'roleSha256'=>null,'coverageSha256'=>null,'metadataSha256'=>null,
            'beforeSha256'=>null,'afterSha256'=>null,'permissionBeforeSha256'=>null,'permissionPostBeforeSha256'=>null,'permissionAfterSha256'=>null,
            'rawSchemaAfterSha256'=>null,'rawSchemaState'=>null,'schemaVerification'=>null,
            'protectedBefore'=>null,'protectedAfter'=>null,'attemptedTables'=>[],'createdTables'=>[],'unknownTables'=>[],'tableRows'=>[],
            'supplierCalls'=>0,'databaseWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,'ddlOutcomeUnknown'=>false,
            'parentMetadataUnchanged'=>null,'journalSha256'=>null];
        $before=null;$after=null;$permissionBefore=null;$permissionPostBefore=null;$permissionAfter=null;$rawSchemaAfter=null;$expectedProtected=null;$locked=false;$handle=null;$binding=null;
        try {
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            self::need(!file_exists($journalPath)&&!is_link($journalPath)&&realpath(dirname($journalPath))===dirname($journalPath),'journal');
            $accepted=self::parent($parent,$expectedDatabase);$binding=self::binding($accepted,$expectedDatabase);
            foreach ($binding as $key=>$value) $r[$key]=$value;
            $r['permissionFileSha256']=$parent['local_stay_context_permission']['fileSha256'];$r['permissionEngineSha256']=$accepted['receipt']['engineResultSha256'];
            $prepared=AnyTourHotelStayContextPermissionReadV1::prepare($context,$failed,$raw,$site,$expectedDatabase);
            self::need($prepared['receipt']['state']==='ready','protected_drift');$expectedProtected=$prepared['protectedContext']['baselineProtected'];
            $r['protectedBefore']=self::fingerprint($site);self::need($r['protectedBefore']===$expectedProtected,'protected_drift');$statements=self::statements();
            self::currentBinding($db,$binding);
            $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([self::LOCK]);self::need((int)$q->fetchColumn()===1,'lock_unavailable');$locked=true;
            $permissionBefore=self::permission($db,$context,$failed,$raw,$site,$expectedDatabase,$binding);$r['permissionBeforeSha256']=self::digest($permissionBefore);
            $fresh=AnyTourHotelStaySchemaReadV1::read($db);self::need($fresh['receipt']['state']==='completed_read_only','schema_preflight');
            $before=$fresh['inventory'];self::need(strlen(self::json($before))<=65536,'schema_preflight');$r['beforeSha256']=self::digest($before);
            self::need($fresh['plan']['state']==='create_only_after_review'&&$fresh['plan']['createTables']===self::TARGETS
                &&$fresh['plan']['blockedTables']===[]&&$before['server']['checkEnforcement']==='supported'
                &&$before['server']['triggerVisibilityProven']===true,'schema_preflight');
            self::need(self::noCollisions($db,$statements),'constraint_collision');
            $capacity=$db->query('SELECT @@innodb_default_row_format,@@innodb_page_size')->fetch(PDO::FETCH_NUM);
            self::need(is_array($capacity)&&strtolower((string)$capacity[0])==='dynamic'&&(int)$capacity[1]>=8192,'ddl_capability');
            $guards=$db->query('SELECT @@session.foreign_key_checks,@@session.unique_checks')->fetch(PDO::FETCH_NUM);
            self::need(is_array($guards)&&(int)$guards[0]===1&&(int)$guards[1]===1,'session_guards');
            self::need(self::fingerprint($site)===$expectedProtected,'protected_drift');
            $old=umask(0077);try {$handle=fopen($journalPath,'x+b');} finally {umask($old);}
            self::need(is_resource($handle)&&((fstat($handle)['mode']??0)&0777)===0600,'journal');
            self::journalEvent($handle,['stage'=>'start','operationId'=>self::OPERATION,'migrationSha256'=>$r['migrationSha256'],
                'databaseSha256'=>$r['databaseSha256'],'beforeSha256'=>$r['beforeSha256'],
                'permissionResultSha256'=>$r['permissionResultSha256'],'permissionBeforeSha256'=>$r['permissionBeforeSha256'],
                'accountSha256'=>$r['accountSha256'],'roleSha256'=>$r['roleSha256'],'coverageSha256'=>$r['coverageSha256'],'metadataSha256'=>$r['metadataSha256']]);
            foreach (self::TARGETS as $i=>$table) {
                self::currentBinding($db,$binding);
                self::need(self::fingerprint($site)===$expectedProtected,'protected_drift');
                self::journalEvent($handle,['stage'=>'before_create','table'=>$table,'statementSha256'=>hash('sha256',$statements[$i])]);
                $r['attemptedTables'][]=$table;
                try {self::need($db->exec($statements[$i])!==false,'ddl_execution_failed');}
                catch (Throwable $e) {$r['unknownTables']=[$table];$r['ddlOutcomeUnknown']=true;throw new RuntimeException('ddl_execution_failed');}
                $r['createdTables'][]=$table;$r['schemaWrites']=count($r['createdTables']);
                self::journalEvent($handle,['stage'=>'created','table'=>$table,'acknowledgedSchemaWrites'=>$r['schemaWrites']]);
            }
            $r['state']='verification_failed';
            self::currentBinding($db,$binding);
            $permissionPostBefore=self::permission($db,$context,$failed,$raw,$site,$expectedDatabase,$binding);$r['permissionPostBeforeSha256']=self::digest($permissionPostBefore);
            $post=AnyTourHotelStaySchemaReadV1::read($db);self::need($post['receipt']['state']==='completed_read_only','postflight');
            $rawSchemaAfter=$post;$r['rawSchemaAfterSha256']=self::digest($post);$r['rawSchemaState']=$post['plan']['state'];
            self::need(strlen(self::json($post['inventory']))<=65536,'postflight');$after=$post['inventory'];$r['afterSha256']=self::digest($after);
            $r['parentMetadataUnchanged']=self::json(array_slice($before['tables'],0,2))===self::json(array_slice($after['tables'],0,2));
            self::need($r['parentMetadataUnchanged'],'parent_metadata');
            foreach (self::TARGETS as $table) {
                $rows=$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
                self::need(is_numeric($rows)&&preg_match('/^[0-9]+$/D',(string)$rows)===1&&strlen((string)$rows)<16,'rows_nonempty');
                $r['tableRows'][]=['name'=>$table,'rows'=>(int)$rows];self::need((int)$rows===0,'rows_nonempty');
            }
            $permissionAfter=self::permission($db,$context,$failed,$raw,$site,$expectedDatabase,$binding);$r['permissionAfterSha256']=self::digest($permissionAfter);
            $r['schemaVerification']=self::postVerification($post,$before,$permissionPostBefore,$permissionAfter,$expectedDatabase);
            self::need($r['schemaVerification']!==null,'postflight');
            self::currentBinding($db,$binding);$r['state']='installed_empty';
        } catch (Throwable $e) {
            $allowed=['permission_parent','permission_proof','permission_drift','caller_transaction','source_pin','database_identity','protected_drift','lock_unavailable','schema_preflight',
                'constraint_collision','ddl_capability','session_guards','journal','ddl_execution_failed','postflight','parent_metadata','rows_nonempty'];
            $r['reason']=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'read_failed';
            $r['state']=$r['ddlOutcomeUnknown']?'ddl_unknown':($r['schemaWrites']>0?'verification_failed':'preflight_refused');
            if ($r['attemptedTables']!==[]&&$after===null) try {
                self::currentBinding($db,$binding);
                $post=AnyTourHotelStaySchemaReadV1::read($db);
                if ($post['receipt']['state']==='completed_read_only'&&strlen(self::json($post['inventory']))<=65536) {$after=$post['inventory'];$r['afterSha256']=self::digest($after);
                    $r['parentMetadataUnchanged']=$before!==null&&self::json(array_slice($before['tables'],0,2))===self::json(array_slice($after['tables'],0,2));}
            } catch (Throwable $ignored) {}
        } finally {
            if ($expectedProtected!==null) try {
                $r['protectedAfter']=self::fingerprint($site);
                self::need($r['protectedAfter']===$expectedProtected,'protected_drift');
            } catch (Throwable $ignored) {
                if ($r['reason']===null) $r['reason']='protected_drift';
                $r['state']=$r['ddlOutcomeUnknown']?'ddl_unknown':($r['schemaWrites']>0?'verification_failed':'preflight_refused');
            }
            if (is_resource($handle)) {
                try {self::journalEvent($handle,['stage'=>'final','state'=>$r['state'],'reason'=>$r['reason'],
                    'attemptedTables'=>$r['attemptedTables'],'createdTables'=>$r['createdTables'],'unknownTables'=>$r['unknownTables']]);}
                catch (Throwable $e) {if ($r['state']==='installed_empty') {$r['state']='verification_failed';$r['reason']='journal';}}
                fclose($handle);
                if (is_file($journalPath)&&!is_link($journalPath)) $r['journalSha256']=hash_file('sha256',$journalPath);
            }
            if ($locked) try {$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([self::LOCK]);} catch (Throwable $ignored) {}
        }
        $result=['receipt'=>$r,'before'=>$before,'after'=>$after,'permissionBefore'=>$permissionBefore,'permissionPostBefore'=>$permissionPostBefore,
            'permissionAfter'=>$permissionAfter,'rawSchemaAfter'=>$rawSchemaAfter];
        self::need(strlen(self::json($result))<self::MAX_BYTES-1024,'result_size');return $result;
    }

}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');$source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');
        $paths=[(string)getenv('ANYTOUR_PERMISSION_INSTALL_PARENT_PATH')=>[AnyTourHotelStayPermissionInstallV1::PARENT_OPERATION,8400,AnyTourHotelStayPermissionInstallV1::PARENT_SHA256],
            (string)getenv('ANYTOUR_CONTEXT_PERMISSION_CONTEXT_PATH')=>[AnyTourHotelStayContextPermissionReadV1::CONTEXT_OPERATION,2930,AnyTourHotelStayContextPermissionReadV1::CONTEXT_SHA256],
            (string)getenv('ANYTOUR_CONTEXT_PERMISSION_FAILURE_PATH')=>[AnyTourHotelStayContextPermissionReadV1::FAILED_OPERATION,573,AnyTourHotelStayContextPermissionReadV1::FAILED_SHA256],
            (string)getenv('ANYTOUR_CONTEXT_PERMISSION_RAW_PATH')=>[AnyTourHotelStayContextPermissionReadV1::RAW_OPERATION,3429,AnyTourHotelStayContextPermissionReadV1::RAW_SHA256]];
        if ($source!==AnyTourHotelStayPermissionInstallV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)||$op!==AnyTourHotelStayPermissionInstallV1::OPERATION||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            ||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/')||count($paths)!==4) throw new RuntimeException('CLI_SCOPE');
        $parents=[];foreach ($paths as $path=>$pin) {if ($path!==dirname($dir).'/'.$pin[0].'/result.json'||!is_file($path)||is_link($path)||filesize($path)!==$pin[1]||hash_file('sha256',$path)!==$pin[2]) throw new RuntimeException('CLI_PARENT');$parents[]=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);}
        foreach (['DSN','DB_USER','DB_PASSWORD','DB_HOST','DB_NAME','DB_PORT'] as $key) if (getenv('ANYTOUR_DATA_'.$key)!==false) throw new RuntimeException('CLI_ENV');
        $path=$dir.'/local-stay-permission-install.json';$journal=$dir.'/local-stay-permission-install-journal.jsonl';if (file_exists($path)||is_link($path)||file_exists($journal)||is_link($journal)) throw new RuntimeException('CLI_EXISTS');
        if (AnyTourHotelStayPermissionProofReadV1::fingerprint($site)!==AnyTourHotelStayContextPermissionReadV1::BASELINE) throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $pd=$parents[0]['local_stay_context_permission']['data'];$er=$pd['engine']['receipt'];$coverage=$pd['engine']['coverage'];
        if ($parents[0]['local_stay_context_permission']['fileSha256']!==AnyTourHotelStayPermissionInstallV1::PARENT_FILE_SHA256||$pd['receipt']['engineResultSha256']!==AnyTourHotelStayPermissionInstallV1::PARENT_ENGINE_SHA256
            ||$er['accountSha256Before']!==AnyTourHotelStayPermissionInstallV1::ACCOUNT_SHA256||$er['roleSha256Before']!==AnyTourHotelStayPermissionInstallV1::ROLE_SHA256
            ||$er['coverageSha256']!==AnyTourHotelStayPermissionInstallV1::COVERAGE_SHA256||$coverage['metadataSha256Before']!==AnyTourHotelStayPermissionInstallV1::METADATA_SHA256) throw new RuntimeException('CLI_PROOF');
        $helper=__DIR__.'/../../v2/data/db-v1.php';if (!is_file($helper)||is_link($helper)||hash_file('sha256',$helper)!==AnyTourHotelStayContextPermissionReadV1::BASELINE['data/db-v1.php']) throw new RuntimeException('CLI_HELPER');
        $_SERVER['DOCUMENT_ROOT']=$site;require_once $helper;
        $result=AnyTourHotelStayPermissionInstallV1::install(v2_data_db(),$parents[0],$parents[1],$parents[2],$parents[3],$journal,$site,AnyTourHotelStayPermissionInstallV1::DATABASE_SHA256);
        $result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];$json=AnyTourHotelStayPermissionInstallV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStayPermissionInstallV1::MAX_BYTES) throw new RuntimeException('CLI_SIZE');
        $old=umask(0077);try {$h=fopen($path,'x+b');} finally {umask($old);}if ($h===false) throw new RuntimeException('CLI_RECEIPT');
        try {if (fwrite($h,$json)!==strlen($json)||!fflush($h)||!fsync($h)) throw new RuntimeException('CLI_WRITE');} finally {fclose($h);}
        echo $json;exit($result['receipt']['state']==='installed_empty'?0:1);
    } catch (Throwable $ignored) {fwrite(STDERR,"LOCAL_STAY_PERMISSION_INSTALL_FAILED\n");exit(1);}
}
