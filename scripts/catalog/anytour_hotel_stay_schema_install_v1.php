<?php
/** Fixed, owner-admitted CREATE-only LOCAL schema installation. No DML, resume or rollback. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require_once __DIR__.'/anytour_hotel_stay_schema_read_v1.php';

final class AnyTourHotelStaySchemaInstallV1
{
    public const OPERATION='int-andromeda-local-stay-schema-install-20261026-v1';
    public const PARENT_OPERATION='int-andromeda-local-stay-schema-read-20261025-v1';
    public const PARENT_CONTROL='a4faad3ba37937a44249df68e9a1530b3f5f258f';
    public const PARENT_SOURCE='30a7e7013edd7fe70a4700db0aafb2bf22ee05f8';
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

    private static function parent(array $parent): array
    {
        $data=$parent['local_stay_schema_read']['data']??null;$r=$data['receipt']??null;$inventory=$data['inventory']??null;$plan=$data['plan']??null;
        self::need(is_array($data)&&is_array($r)&&is_array($inventory)&&is_array($plan)
            &&($parent['status']??null)==='complete'&&($parent['mode']??null)==='local-stay-schema-read-v1'
            &&($parent['operation_id']??null)===self::PARENT_OPERATION&&($parent['source_sha']??null)===self::PARENT_SOURCE
            &&($r['operationId']??null)===self::PARENT_OPERATION&&($r['controlSha']??null)===self::PARENT_CONTROL
            &&($r['sourceSha']??null)===self::PARENT_SOURCE&&($r['schemaVersion']??null)===1
            &&($r['source']??null)==='anytour-hotel-stay-schema-read-v1'&&($r['state']??null)==='completed_read_only'
            &&($r['migrationSha256']??null)===AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256
            &&($r['inventorySha256']??null)===self::digest($inventory)&&($r['planSha256']??null)===self::digest($plan)
            &&($plan['state']??null)==='create_only_after_review'&&($plan['createTables']??null)===self::TARGETS
            &&($plan['blockedTables']??null)===[]&&($plan['existingCompatibleTables']??null)===[]
            &&($plan['applyAuthorized']??null)===false&&($plan['noDdlExecuted']??null)===true,'parent_contract');
        foreach (['supplierCalls','databaseWrites','schemaWrites','automaticAccepts'] as $key) self::need(($r[$key]??null)===0,'parent_contract');
        self::need(($inventory['server']['family']??null)==='mysql'&&($inventory['server']['checkEnforcement']??null)==='supported'
            &&($inventory['server']['triggerVisibilityProven']??null)===true
            &&array_column($inventory['tables']??[],'name')===AnyTourHotelStaySchemaReadV1::TABLES,'parent_contract');
        foreach ($inventory['tables'] as $i=>$table) self::need(($table['exists']??null)===($i<2)
            &&($table['compatibility']??null)===($i<2?'compatible':'absent'),'parent_contract');
        return $inventory;
    }

    private static function journalEvent($handle,array $event): void
    {
        $raw=self::json($event)."\n";$stat=fstat($handle);
        self::need(is_array($stat)&&$stat['size']+strlen($raw)<self::MAX_BYTES
            &&fwrite($handle,$raw)===strlen($raw)&&fflush($handle)&&fsync($handle),'journal');
    }

    /** schemaWrites counts acknowledged CREATE responses; ddlOutcomeUnknown preserves an unacknowledged attempt. */
    public static function install(PDO $db,array $parent,string $journalPath): array
    {
        $r=['schemaVersion'=>1,'source'=>'anytour-hotel-stay-schema-install-v1','state'=>'preflight_refused','reason'=>null,
            'migrationSha256'=>AnyTourHotelStaySchemaReadV1::MIGRATION_SHA256,'parentReadOperation'=>self::PARENT_OPERATION,
            'parentReadInventorySha256'=>null,'databaseSha256'=>null,'beforeSha256'=>null,'afterSha256'=>null,
            'attemptedTables'=>[],'createdTables'=>[],'unknownTables'=>[],'tableRows'=>[],
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,'ddlOutcomeUnknown'=>false,
            'parentMetadataUnchanged'=>null,'journalSha256'=>null];
        $before=null;$after=null;$locked=false;$handle=null;
        try {
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            $expected=self::parent($parent);$r['parentReadInventorySha256']=self::digest($expected);
            $statements=self::statements();$r['databaseSha256']=self::database($db);
            $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([self::LOCK]);self::need((int)$q->fetchColumn()===1,'lock_unavailable');$locked=true;
            $fresh=AnyTourHotelStaySchemaReadV1::read($db);self::need($fresh['receipt']['state']==='completed_read_only','schema_preflight');
            $before=$fresh['inventory'];self::need(strlen(self::json($before))<=65536,'schema_preflight');$r['beforeSha256']=self::digest($before);
            self::need($fresh['plan']['state']==='create_only_after_review'&&$fresh['plan']['createTables']===self::TARGETS
                &&self::json($before)===self::json($expected),'schema_preflight');
            self::need(self::privileges($db),'privileges');
            self::need(self::noCollisions($db,$statements),'constraint_collision');
            $capacity=$db->query('SELECT @@innodb_default_row_format,@@innodb_page_size')->fetch(PDO::FETCH_NUM);
            self::need(is_array($capacity)&&strtolower((string)$capacity[0])==='dynamic'&&(int)$capacity[1]>=8192,'ddl_capability');
            $guards=$db->query('SELECT @@session.foreign_key_checks,@@session.unique_checks')->fetch(PDO::FETCH_NUM);
            self::need(is_array($guards)&&(int)$guards[0]===1&&(int)$guards[1]===1,'session_guards');
            self::need(!file_exists($journalPath)&&!is_link($journalPath)&&realpath(dirname($journalPath))===dirname($journalPath),'journal');
            $old=umask(0077);try {$handle=fopen($journalPath,'x+b');} finally {umask($old);}
            self::need(is_resource($handle)&&((fstat($handle)['mode']??0)&0777)===0600,'journal');
            self::journalEvent($handle,['stage'=>'start','operationId'=>self::OPERATION,'migrationSha256'=>$r['migrationSha256'],
                'databaseSha256'=>$r['databaseSha256'],'beforeSha256'=>$r['beforeSha256'],'parentReadInventorySha256'=>$r['parentReadInventorySha256']]);
            foreach (self::TARGETS as $i=>$table) {
                self::need(self::database($db)===$r['databaseSha256'],'database_identity');
                self::journalEvent($handle,['stage'=>'before_create','table'=>$table,'statementSha256'=>hash('sha256',$statements[$i])]);
                $r['attemptedTables'][]=$table;
                try {self::need($db->exec($statements[$i])!==false,'ddl_execution_failed');}
                catch (Throwable $e) {$r['unknownTables']=[$table];$r['ddlOutcomeUnknown']=true;throw new RuntimeException('ddl_execution_failed');}
                $r['createdTables'][]=$table;$r['schemaWrites']=count($r['createdTables']);
                self::journalEvent($handle,['stage'=>'created','table'=>$table,'acknowledgedSchemaWrites'=>$r['schemaWrites']]);
            }
            $r['state']='verification_failed';
            self::need(self::database($db)===$r['databaseSha256'],'database_identity');
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
            $r['state']='installed_empty';
        } catch (Throwable $e) {
            $allowed=['parent_contract','caller_transaction','source_pin','database_identity','lock_unavailable','schema_preflight',
                'privileges','constraint_collision','ddl_capability','session_guards','journal','ddl_execution_failed','postflight','parent_metadata','rows_nonempty'];
            $r['reason']=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'read_failed';
            $r['state']=$r['ddlOutcomeUnknown']?'ddl_unknown':($r['schemaWrites']>0?'verification_failed':'preflight_refused');
            if ($r['attemptedTables']!==[]&&$after===null) try {
                $post=AnyTourHotelStaySchemaReadV1::read($db);
                if ($post['receipt']['state']==='completed_read_only'&&strlen(self::json($post['inventory']))<=65536) {$after=$post['inventory'];$r['afterSha256']=self::digest($after);
                    $r['parentMetadataUnchanged']=$before!==null&&self::json(array_slice($before['tables'],0,2))===self::json(array_slice($after['tables'],0,2));}
            } catch (Throwable $ignored) {}
        } finally {
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
        $operation=(string)getenv('ANYTOUR_OPERATION_ID');$parentPath=(string)getenv('ANYTOUR_SCHEMA_PARENT_RECEIPT_PATH');
        if (!preg_match('/^[a-f0-9]{40}$/D',$source)||!preg_match('/^[a-f0-9]{40}$/D',$control)
            ||$operation!==AnyTourHotelStaySchemaInstallV1::OPERATION||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            ||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)||basename($dir)!==$operation||str_starts_with($dir.'/',$site.'/')
            ||$parentPath!==dirname($dir).'/'.AnyTourHotelStaySchemaInstallV1::PARENT_OPERATION.'/result.json'
            ||!is_file($parentPath)||is_link($parentPath)||filesize($parentPath)>262144) throw new RuntimeException('CLI_SCOPE');
        $parent=json_decode((string)file_get_contents($parentPath),true,512,JSON_THROW_ON_ERROR);
        $protected=$parent['local_stay_schema_read']['protectedBefore']??null;
        if (!is_array($protected)||$protected!==($parent['local_stay_schema_read']['protectedAfter']??null)
            ||array_keys($protected)!==['config.php','data/db-v1.php']) throw new RuntimeException('CLI_PARENT');
        foreach ($protected as $name=>$sha) if (!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)
            ||!is_file($site.'/'.$name)||is_link($site.'/'.$name)||hash_file('sha256',$site.'/'.$name)!==$sha) throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $path=$dir.'/local-stay-schema-install.json';if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $_SERVER['DOCUMENT_ROOT']=$site;require_once __DIR__.'/../../v2/data/db-v1.php';
        $result=AnyTourHotelStaySchemaInstallV1::install(v2_data_db(),$parent,$dir.'/local-stay-schema-install-journal.jsonl');
        foreach ($protected as $name=>$sha) if (hash_file('sha256',$site.'/'.$name)!==$sha) throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $result['receipt']+=['operationId'=>$operation,'sourceSha'=>$source,'controlSha'=>$control];
        $json=AnyTourHotelStaySchemaInstallV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStaySchemaInstallV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);try {$handle=fopen($path,'x+b');} finally {umask($old);}
        if ($handle===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {if (fwrite($handle,$json)!==strlen($json)||!fflush($handle)||!fsync($handle)) throw new RuntimeException('CLI_RECEIPT_WRITE');}
        finally {fclose($handle);}
        echo $json;exit($result['receipt']['state']==='installed_empty'?0:1);
    } catch (Throwable $e) {fwrite(STDERR,"LOCAL_STAY_SCHEMA_INSTALL_FAILED\n");exit(1);}
}
