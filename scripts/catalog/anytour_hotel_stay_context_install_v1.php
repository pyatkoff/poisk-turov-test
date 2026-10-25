<?php
/** New CURRENT-context-bound CREATE-only admission. Historical failed installer is never invoked. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require_once __DIR__.'/anytour_hotel_stay_schema_read_v1.php';

final class AnyTourHotelStayContextInstallV1
{
    public const OPERATION='int-andromeda-local-stay-context-install-20261026-v1';
    public const CONTEXT_OPERATION='int-andromeda-local-stay-install-context-read-20261026-v1';
    public const CONTEXT_CONTROL='af256ad98636480ee1e62c59808bad2a612d6420';
    public const CONTEXT_RESULT_SHA256='21ce19017ad4211a67d8f9e053d2ed033cfa3f5350493d0797084eb31867f1b0';
    public const CONTEXT_FILE_SHA256='4f60fd91f95774b08e5b44611a20c6202d989eb96fda9a573a011101bb0ef043';
    public const DATABASE_SHA256='4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d';
    public const PARENT_OPERATION='int-andromeda-local-stay-schema-read-20261025-v1';
    public const FAILED_OPERATION='int-andromeda-local-stay-schema-install-20261026-v1';
    public const PARENT_SOURCE='30a7e7013edd7fe70a4700db0aafb2bf22ee05f8';
    public const READ_RESULT_SHA256='96e41780998f973f109674d40e6aec9f6d175a769d1a1ff21e8d6b536a706f86';
    public const FAILED_RESULT_SHA256='fbf6bd8808816daa6b40c7ac1647c0ccf6ecb0f9157d8feaae36ce31647b8921';
    public const HELPER_SHA256='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4';
    public const CONFIG_SHA256='121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def';
    public const INVENTORY_SHA256='8a37c3c6a3cd4894afff942268d6c669f1ae81f7403253bc58f0b8ae5f38c32e';
    public const PLAN_SHA256='6f1c556fe1a6bf3182f3a49686850f6fcb479cf94e03210881a9faf216b603f6';
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

    /** Direct schema/global grants only: roles/table-only visibility is deliberately not inferred. */
    private static function privileges(PDO $db): bool
    {
        $user=(string)$db->query('SELECT CURRENT_USER()')->fetchColumn();$at=strrpos($user,'@');
        if ($at===false||strlen($user)>288) return false;
        $grantee="'".str_replace("'","''",substr($user,0,$at))."'@'".str_replace("'","''",substr($user,$at+1))."'";
        $q=$db->prepare("SELECT PRIVILEGE_TYPE FROM information_schema.USER_PRIVILEGES WHERE BINARY GRANTEE=BINARY ?
            AND PRIVILEGE_TYPE IN ('CREATE','REFERENCES','TRIGGER','SELECT')
            UNION SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES
            WHERE BINARY GRANTEE=BINARY ? AND BINARY TABLE_SCHEMA=BINARY DATABASE()
            AND PRIVILEGE_TYPE IN ('CREATE','REFERENCES','TRIGGER','SELECT') LIMIT 9");
        $q->execute([$grantee,$grantee]);$found=$q->fetchAll(PDO::FETCH_COLUMN);sort($found,SORT_STRING);
        return $found===['CREATE','REFERENCES','SELECT','TRIGGER'];
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

    /** This completed context, rather than the historical read alone, is the target authority. */
    private static function context(array $parent,string $expectedDatabase): array
    {
        $c=$parent['local_stay_install_context']??null;$data=$c['data']??null;$r=$data['receipt']??null;
        $inventory=$data['inventory']??null;$plan=$data['plan']??null;
        self::need(is_array($c)&&is_array($data)&&is_array($r)&&is_array($inventory)&&is_array($plan)
            &&preg_match('/^[a-f0-9]{64}$/D',$expectedDatabase)===1
            &&($parent['schema_version']??null)===1&&($parent['status']??null)==='complete'
            &&($parent['mode']??null)==='local-stay-install-context-read-v1'
            &&($parent['operation_id']??null)===self::CONTEXT_OPERATION&&($parent['source_sha']??null)===self::PARENT_SOURCE
            &&($parent['production_unchanged']??null)===true&&is_array($parent['production_before']??null)
            &&$parent['production_before']===($parent['production_after']??null)
            &&($c['parentReadResultSha256']??null)===self::READ_RESULT_SHA256
            &&($c['failedInstallResultSha256']??null)===self::FAILED_RESULT_SHA256
            &&($c['fileSha256']??null)===hash('sha256',self::json($data)."\n")
            &&($r['schemaVersion']??null)===1&&($r['source']??null)==='anytour-hotel-stay-install-context-read-v1'
            &&($r['state']??null)==='completed_read_only'&&($r['schemaState']??null)==='completed_read_only'
            &&($r['operationId']??null)===self::CONTEXT_OPERATION&&($r['controlSha']??null)===self::CONTEXT_CONTROL
            &&($r['sourceSha']??null)===self::PARENT_SOURCE&&($r['databaseSha256']??null)===$expectedDatabase
            &&($r['connectionAuthority']??null)==='current_root_config_and_accepted_helper'
            &&($r['helperAccepted']??null)===true&&($r['phpExecuted']??null)===true
            &&($r['oldDatabaseIdentityKnown']??null)===false&&($r['schemaMatchesParent']??null)===true
            &&($r['applyAuthorized']??null)===false&&($r['changedFiles']??null)===[]
            &&is_int($r['observedAt']??null)&&$r['observedAt']>0
            &&($r['parentReadOperation']??null)===self::PARENT_OPERATION
            &&($r['failedInstallOperation']??null)===self::FAILED_OPERATION
            &&($r['failedInstallReason']??null)==='local_stay_install_private_drift'
            &&($r['migrationSha256']??null)===AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256
            &&($r['inventorySha256']??null)===self::digest($inventory)
            &&($r['parentReadInventorySha256']??null)===$r['inventorySha256']
            &&($r['planSha256']??null)===self::digest($plan)
            &&($plan['state']??null)==='create_only_after_review'&&($plan['createTables']??null)===self::TARGETS
            &&($plan['blockedTables']??null)===[]&&($plan['existingCompatibleTables']??null)===[]
            &&($plan['applyAuthorized']??null)===false&&($plan['noDdlExecuted']??null)===true
            &&($plan['requiresExplicitReview']??null)===true
            &&($plan['migrationSha256']??null)===AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,'context_contract');
        foreach (['booking_calls','database_writes','lead_calls','mapping_writes','profile_writes','schema_writes','supplier_calls'] as $key)
            self::need(($parent[$key]??null)===0,'context_contract');
        foreach (['supplierCalls','databaseWrites','schemaWrites','automaticAccepts'] as $key) self::need(($r[$key]??null)===0,'context_contract');
        $protected=$r['currentProtectedBefore']??null;
        self::need(is_array($protected)&&array_keys($protected)===['config.php','data/db-v1.php']
            &&is_string($protected['config.php'])&&preg_match('/^[a-f0-9]{64}$/D',$protected['config.php'])===1
            &&$protected['data/db-v1.php']===self::HELPER_SHA256
            &&$protected===($r['currentProtectedAfter']??null)&&$protected===($r['previousProtected']??null)
            &&$protected===($c['protectedBefore']??null)&&$protected===($c['protectedAfter']??null),'context_contract');
        self::need(($inventory['server']['family']??null)==='mysql'&&($inventory['server']['checkEnforcement']??null)==='supported'
            &&($inventory['server']['triggerVisibilityProven']??null)===true
            &&array_column($inventory['tables']??[],'name')===AnyTourHotelStaySchemaReadV1::TABLES,'context_contract');
        foreach ($inventory['tables'] as $i=>$table) self::need(($table['exists']??null)===($i<2)
            &&($table['compatibility']??null)===($i<2?'compatible':'absent'),'context_contract');
        return $data;
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
    public static function install(PDO $db,array $parent,string $journalPath,string $site,string $expectedDatabase=self::DATABASE_SHA256): array
    {
        $r=['schemaVersion'=>1,'source'=>'anytour-hotel-stay-context-install-v1','state'=>'preflight_refused','reason'=>null,
            'migrationSha256'=>AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,'parentReadOperation'=>self::PARENT_OPERATION,
            'parentReadInventorySha256'=>null,'contextOperation'=>self::CONTEXT_OPERATION,'contextControlSha'=>self::CONTEXT_CONTROL,
            'contextResultSha256'=>self::digest($parent),'contextFileSha256'=>null,'contextInventorySha256'=>null,
            'contextPlanSha256'=>null,'contextDatabaseSha256'=>null,'parentReadResultSha256'=>self::READ_RESULT_SHA256,
            'failedInstallResultSha256'=>self::FAILED_RESULT_SHA256,'databaseSha256'=>null,'beforeSha256'=>null,'afterSha256'=>null,
            'protectedBefore'=>null,'protectedAfter'=>null,'attemptedTables'=>[],'createdTables'=>[],'unknownTables'=>[],'tableRows'=>[],
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,'ddlOutcomeUnknown'=>false,
            'parentMetadataUnchanged'=>null,'journalSha256'=>null];
        $before=null;$after=null;$expectedProtected=null;$locked=false;$handle=null;
        try {
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            self::need(!file_exists($journalPath)&&!is_link($journalPath)&&realpath(dirname($journalPath))===dirname($journalPath),'journal');
            $context=self::context($parent,$expectedDatabase);$expected=$context['inventory'];$cr=$context['receipt'];
            $r['contextFileSha256']=$parent['local_stay_install_context']['fileSha256'];
            $r['contextInventorySha256']=$cr['inventorySha256'];$r['contextPlanSha256']=$cr['planSha256'];
            $r['contextDatabaseSha256']=$cr['databaseSha256'];$r['parentReadInventorySha256']=$cr['parentReadInventorySha256'];
            $expectedProtected=$cr['currentProtectedBefore'];$r['protectedBefore']=self::fingerprint($site);
            self::need($r['protectedBefore']===$expectedProtected,'protected_drift');
            $statements=self::statements();$r['databaseSha256']=self::database($db);
            self::need($r['databaseSha256']===$expectedDatabase,'context_identity');
            $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([self::LOCK]);self::need((int)$q->fetchColumn()===1,'lock_unavailable');$locked=true;
            self::need(self::database($db)===$expectedDatabase,'context_identity');
            $fresh=AnyTourHotelStaySchemaReadV1::read($db);self::need($fresh['receipt']['state']==='completed_read_only','schema_preflight');
            $before=$fresh['inventory'];self::need(strlen(self::json($before))<=65536,'schema_preflight');$r['beforeSha256']=self::digest($before);
            self::need($fresh['plan']['state']==='create_only_after_review'&&$fresh['plan']['createTables']===self::TARGETS
                &&self::json($before)===self::json($expected)&&self::digest($fresh['plan'])===$r['contextPlanSha256'],'schema_preflight');
            self::need(self::privileges($db),'privileges');
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
                'contextResultSha256'=>$r['contextResultSha256'],'contextInventorySha256'=>$r['contextInventorySha256']]);
            foreach (self::TARGETS as $i=>$table) {
                self::need(self::database($db)===$expectedDatabase,'context_identity');
                self::need(self::fingerprint($site)===$expectedProtected,'protected_drift');
                self::journalEvent($handle,['stage'=>'before_create','table'=>$table,'statementSha256'=>hash('sha256',$statements[$i])]);
                $r['attemptedTables'][]=$table;
                try {self::need($db->exec($statements[$i])!==false,'ddl_execution_failed');}
                catch (Throwable $e) {$r['unknownTables']=[$table];$r['ddlOutcomeUnknown']=true;throw new RuntimeException('ddl_execution_failed');}
                $r['createdTables'][]=$table;$r['schemaWrites']=count($r['createdTables']);
                self::journalEvent($handle,['stage'=>'created','table'=>$table,'acknowledgedSchemaWrites'=>$r['schemaWrites']]);
            }
            $r['state']='verification_failed';
            self::need(self::database($db)===$expectedDatabase,'context_identity');
            $post=AnyTourHotelStaySchemaReadV1::read($db);self::need($post['receipt']['state']==='completed_read_only','postflight');
            self::need(strlen(self::json($post['inventory']))<=65536,'postflight');$after=$post['inventory'];$r['afterSha256']=self::digest($after);
            self::need($post['plan']['state']==='ready_no_op'&&$post['plan']['existingCompatibleTables']===self::TARGETS,'postflight');
            $r['parentMetadataUnchanged']=self::json(array_slice($before['tables'],0,2))===self::json(array_slice($after['tables'],0,2));
            self::need($r['parentMetadataUnchanged'],'parent_metadata');
            foreach (self::TARGETS as $table) {
                $rows=$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
                self::need(is_numeric($rows)&&preg_match('/^[0-9]+$/D',(string)$rows)===1&&strlen((string)$rows)<16,'rows_nonempty');
                $r['tableRows'][]=['name'=>$table,'rows'=>(int)$rows];self::need((int)$rows===0,'rows_nonempty');
            }
            self::need(self::database($db)===$expectedDatabase,'context_identity');$r['state']='installed_empty';
        } catch (Throwable $e) {
            $allowed=['context_contract','caller_transaction','source_pin','database_identity','context_identity','protected_drift','lock_unavailable','schema_preflight',
                'privileges','constraint_collision','ddl_capability','session_guards','journal','ddl_execution_failed','postflight','parent_metadata','rows_nonempty'];
            $r['reason']=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'read_failed';
            $r['state']=$r['ddlOutcomeUnknown']?'ddl_unknown':($r['schemaWrites']>0?'verification_failed':'preflight_refused');
            if ($r['attemptedTables']!==[]&&$after===null) try {
                self::need(self::database($db)===$expectedDatabase,'context_identity');
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
        $result=['receipt'=>$r,'before'=>$before,'after'=>$after];
        self::need(strlen(self::json($result))<self::MAX_BYTES-1024,'result_size');return $result;
    }

}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');
        $operation=(string)getenv('ANYTOUR_OPERATION_ID');$parentPath=(string)getenv('ANYTOUR_CONTEXT_INSTALL_PARENT_PATH');
        if ($source!==AnyTourHotelStayContextInstallV1::PARENT_SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)
            ||$operation!==AnyTourHotelStayContextInstallV1::OPERATION||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            ||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)||basename($dir)!==$operation||str_starts_with($dir.'/',$site.'/')
            ||$parentPath!==dirname($dir).'/'.AnyTourHotelStayContextInstallV1::CONTEXT_OPERATION.'/result.json'
            ||!is_file($parentPath)||is_link($parentPath)||filesize($parentPath)!==8528
            ||hash_file('sha256',$parentPath)!==AnyTourHotelStayContextInstallV1::CONTEXT_RESULT_SHA256) throw new RuntimeException('CLI_CONTEXT');
        $parent=json_decode((string)file_get_contents($parentPath),true,512,JSON_THROW_ON_ERROR);
        $c=$parent['local_stay_install_context']??null;$r=$c['data']['receipt']??null;
        if (!is_array($r)||($c['fileSha256']??null)!==AnyTourHotelStayContextInstallV1::CONTEXT_FILE_SHA256
            ||($r['inventorySha256']??null)!==AnyTourHotelStayContextInstallV1::INVENTORY_SHA256
            ||($r['planSha256']??null)!==AnyTourHotelStayContextInstallV1::PLAN_SHA256
            ||($r['databaseSha256']??null)!==AnyTourHotelStayContextInstallV1::DATABASE_SHA256
            ||($r['currentProtectedBefore']??null)!==['config.php'=>AnyTourHotelStayContextInstallV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelStayContextInstallV1::HELPER_SHA256]) throw new RuntimeException('CLI_CONTEXT');
        $path=$dir.'/local-stay-context-install.json';$journal=$dir.'/local-stay-context-install-journal.jsonl';
        if (file_exists($path)||is_link($path)||file_exists($journal)||is_link($journal)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        foreach ($r['currentProtectedBefore'] as $name=>$sha) if (!is_file($site.'/'.$name)||is_link($site.'/'.$name)
            ||realpath($site.'/'.$name)!==$site.'/'.$name||hash_file('sha256',$site.'/'.$name)!==$sha) throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $helper=__DIR__.'/../../v2/data/db-v1.php';
        if (!is_file($helper)||is_link($helper)||hash_file('sha256',$helper)!==AnyTourHotelStayContextInstallV1::HELPER_SHA256) throw new RuntimeException('CLI_HELPER');
        $_SERVER['DOCUMENT_ROOT']=$site;require_once $helper;
        $result=AnyTourHotelStayContextInstallV1::install(v2_data_db(),$parent,$journal,$site,AnyTourHotelStayContextInstallV1::DATABASE_SHA256);
        $result['receipt']+=['operationId'=>$operation,'sourceSha'=>$source,'controlSha'=>$control];
        $json=AnyTourHotelStayContextInstallV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStayContextInstallV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);try {$handle=fopen($path,'x+b');} finally {umask($old);}
        if ($handle===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {if (fwrite($handle,$json)!==strlen($json)||!fflush($handle)||!fsync($handle)) throw new RuntimeException('CLI_RECEIPT_WRITE');}
        finally {fclose($handle);}
        echo $json;exit($result['receipt']['state']==='installed_empty'?0:1);
    } catch (Throwable $e) {fwrite(STDERR,"LOCAL_STAY_CONTEXT_INSTALL_FAILED\n");exit(1);}
}
