<?php
/** Bounded direct metadata evidence only. Never grants privileges, admits DDL, or claims effective denial. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}

final class AnyTourHotelStayPermissionProofReadV1
{
    public const OPERATION='int-andromeda-local-stay-permission-proof-read-20261026-v1';
    public const PARENT_OPERATION='int-andromeda-local-stay-source-facts-read-20261026-v1';
    public const SOURCE='1d0e529e3fc0666783db1454c0765d3c16d63843';
    public const PARENT_CONTROL='436cf6f1bd85f062dd7b8ff42292f80f8232a4b5';
    public const PARENT_SHA256='0852e6438c911a4c04c772c6d81fff7613b61593bb5613948d460fc60b8a0817';
    public const PARENT_FILE_SHA256='1b26f11a7fbca04f7ce9ffcaeb9c41a849948a98301284a6c4ca3ccf9e79dea4';
    public const DATABASE_SHA256='4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d';
    public const CONFIG_SHA256='121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def';
    public const HELPER_SHA256='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4';
    public const MAX_BYTES=262144;
    public const PRIVILEGES=['CREATE','REFERENCES','SELECT','TRIGGER'];
    public const TABLES=['anytour_hotels','anytour_hotel_sources','anytour_offers','anytour_offer_scope_state',
        'anytour_hotel_room_concepts_v2','anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2'];
    public const COLUMNS=[
        'anytour_hotels'=>['id','is_active','profile_json','profile_sha256'],
        'anytour_hotel_sources'=>['namespace','external_key','anytour_hotel_id','acquired_via','source_json','source_sha256'],
        'anytour_offers'=>['id','anytour_hotel_id','legacy_hotel_id','provider','provider_hotel_ref_digest','offer_ref_digest','search_ref_digest','identity_sha256','payload_json','payload_sha256','last_seen_at','expires_at','is_active','scope_sha256','last_refresh_token'],
        'anytour_offer_scope_state'=>['provider','scope_sha256','latest_complete_refresh_token'],
        'anytour_hotel_room_concepts_v2'=>['id','anytour_hotel_id','local_key','name_ru','facts_json','revision','is_active'],
        'anytour_hotel_meal_concepts_v2'=>['id','anytour_hotel_id','local_key','name_ru','facts_json','revision','is_active'],
        'anytour_hotel_stay_mappings_v2'=>['namespace','external_hotel_key','operator_key','kind','key_kind','external_key','anytour_hotel_id','room_concept_id','meal_concept_id','state']];
    public const BINDING_SQL='SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid,CURRENT_USER() AS account,CURRENT_ROLE() AS roles,@@lower_case_table_names AS lower_case_table_names,@@global.partial_revokes AS partial_revokes';

    public static function json(mixed $value): string
    {
        $canon=static function(mixed $v) use (&$canon): mixed {
            if (!is_array($v)) return $v;if (!array_is_list($v)) ksort($v,SORT_STRING);
            foreach ($v as $k=>$part) $v[$k]=$canon($part);return $v;
        };
        return json_encode($canon($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    private static function sha(mixed $value): string {return hash('sha256',self::json($value));}
    private static function need(bool $ok,string $reason): void {if (!$ok) throw new RuntimeException($reason);}
    public static function fingerprint(string $site): array
    {
        $out=[];foreach (['config.php','data/db-v1.php'] as $name) {
            $p=$site.'/'.$name;self::need(is_file($p)&&!is_link($p)&&realpath($p)===$p&&filesize($p)>0&&filesize($p)<=2097152,'protected_drift');$out[$name]=hash_file('sha256',$p);
        }return $out;
    }
    private static function parent(array $parent,string $expected): array
    {
        $p=$parent['local_stay_source_facts']??null;$d=$p['data']??null;$r=$d['receipt']??null;
        self::need(is_array($p)&&is_array($d)&&is_array($r)&&preg_match('/^[a-f0-9]{64}$/D',$expected)===1
            &&($parent['mode']??null)==='local-stay-source-facts-read-v1'&&($parent['status']??null)==='complete'
            &&($parent['operation_id']??null)===self::PARENT_OPERATION&&($parent['source_sha']??null)===self::SOURCE
            &&($r['schemaVersion']??null)===1&&($r['source']??null)==='anytour-hotel-stay-source-facts-read-v1'
            &&($r['operationId']??null)===self::PARENT_OPERATION&&($r['sourceSha']??null)===self::SOURCE
            &&($r['controlSha']??null)===self::PARENT_CONTROL&&($r['state']??null)==='anchor_missing'
            &&($r['databaseSha256Before']??null)===$expected&&($r['databaseSha256After']??null)===$expected
            &&($d['privilegeProof']??null)===['CREATE'=>false,'REFERENCES'=>false,'SELECT'=>false,'TRIGGER'=>false,'allRequired'=>false,'scope'=>'direct_global_or_schema','state'=>'direct_metadata_observed'],'parent_invalid');
        foreach (['anchorEvidence','sourceFacts','currentCohorts','draftConcepts'] as $key) self::need(array_key_exists($key,$d)&&$d[$key]===null,'parent_invalid');
        foreach (['supplierCalls','databaseWrites','schemaWrites','profileWrites','mappingWrites','automaticAccepts'] as $key) self::need(($r[$key]??null)===0,'parent_invalid');
        $protected=$r['protectedBefore']??null;
        self::need(is_array($protected)&&array_keys($protected)===['config.php','data/db-v1.php']
            &&is_string($protected['config.php'])&&preg_match('/^[a-f0-9]{64}$/D',$protected['config.php'])===1
            &&$protected['data/db-v1.php']===self::HELPER_SHA256&&$protected===($r['protectedAfter']??null)
            &&$protected===($p['protectedBefore']??null)&&$protected===($p['protectedAfter']??null),'parent_invalid');
        return $protected;
    }
    private static function binding(PDO $db): array
    {
        $r=$db->query(self::BINDING_SQL)->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($r)&&is_string($r['selected_database']??null)&&$r['selected_database']!==''&&strlen($r['selected_database'])<=64
            &&is_string($r['server_uuid']??null)&&preg_match('/^[a-f0-9-]{36}$/Di',$r['server_uuid'])===1
            &&is_string($r['account']??null)&&strlen($r['account'])<=288&&$r['account']!==''
            &&is_string($r['roles']??null)&&strlen($r['roles'])<=16384&&$r['roles']!=='','database_identity');
        self::need(in_array((string)($r['lower_case_table_names']??''),['0','1','2'],true)
            &&in_array((string)($r['partial_revokes']??''),['0','1'],true),'metadata_unavailable');
        return $r;
    }
    private static function database(array $b): string {return self::sha(['database'=>$b['selected_database'],'serverUuid'=>$b['server_uuid']]);}
    private static function grantee(string $account): ?string
    {
        $at=strrpos($account,'@');if ($at===false) return null;$user=substr($account,0,$at);$host=substr($account,$at+1);
        if (preg_match('/^[a-zA-Z0-9_.$@:-]{1,32}$/D',$user)!==1||preg_match('/^[a-zA-Z0-9_.:%-]{1,255}$/D',$host)!==1) return null;
        return "'".$user."'@'".$host."'";
    }
    /** Recognize only MySQL schema-pattern escapes; wildcard matches are held, never grant proof. */
    private static function pattern(string $pattern,string $database): ?array
    {
        if ($pattern===''||strlen($pattern)>128||preg_match('/^[\x20-\x7e]+$/D',$pattern)!==1) return null;
        $regex='';$wild=false;
        for ($i=0;$i<strlen($pattern);++$i) {
            $c=$pattern[$i];
            if ($c==='\\') {if (++$i>=strlen($pattern)||!in_array($pattern[$i],['_','%','\\'],true)) return null;$regex.=preg_quote($pattern[$i],'~');}
            elseif ($c==='%'||$c==='_') {$wild=true;$regex.=$c==='%'?'.*':'.';}
            else $regex.=preg_quote($c,'~');
        }
        $matched=preg_match('~^'.$regex.'$~D',$database);if ($matched===false) return null;
        return ['matches'=>$matched===1,'literal'=>!$wild];
    }
    /** No grant text is returned. Max256 rows/64KiB per snapshot, at most512 rows/128KiB overall. */
    private static function snapshot(PDO $db,string $grantee): array
    {
        $out=['global'=>[],'schema'=>[],'table'=>[],'column'=>[]];$count=0;
        foreach (['global','schema','table','column'] as $kind) {
            $fields=match($kind){'global'=>'PRIVILEGE_TYPE','schema'=>'TABLE_SCHEMA,PRIVILEGE_TYPE','column'=>'TABLE_NAME,COLUMN_NAME,PRIVILEGE_TYPE',default=>'TABLE_NAME,PRIVILEGE_TYPE'};
            $table=match($kind){'global'=>'USER_PRIVILEGES','schema'=>'SCHEMA_PRIVILEGES','table'=>'TABLE_PRIVILEGES','column'=>'COLUMN_PRIVILEGES'};
            $sql='SELECT '.$fields.' FROM information_schema.'.$table.' WHERE BINARY GRANTEE=BINARY ? AND PRIVILEGE_TYPE IN (\'CREATE\',\'REFERENCES\',\'SELECT\',\'TRIGGER\')';$params=[$grantee];
            if (in_array($kind,['table','column'],true)) {$sql.=' AND BINARY TABLE_SCHEMA=BINARY DATABASE() AND TABLE_NAME IN (?,?,?,?,?,?,?)';$params=array_merge($params,self::TABLES);}
            if ($kind==='column') {
                $pairs=[];foreach (self::COLUMNS as $name=>$columns) {$pairs[]='(BINARY TABLE_NAME=BINARY ? AND COLUMN_NAME IN ('.implode(',',array_fill(0,count($columns),'?')).'))';$params[]=$name;array_push($params,...$columns);}
                $sql.=' AND ('.implode(' OR ',$pairs).')';
            }
            $sql.=' ORDER BY '.$fields.' LIMIT '.(256-$count);$q=$db->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
            self::need(is_array($rows)&&count($rows)<256-$count,'metadata_limit');$count+=count($rows);
            foreach ($rows as $r) {
                self::need(is_array($r)&&in_array($r['PRIVILEGE_TYPE']??null,self::PRIVILEGES,true),'metadata_unavailable');
                if ($kind==='schema') self::need(is_string($r['TABLE_SCHEMA']??null)&&strlen($r['TABLE_SCHEMA'])<=128,'metadata_unavailable');
                if (in_array($kind,['table','column'],true)) self::need(in_array($r['TABLE_NAME']??null,self::TABLES,true),'metadata_unavailable');
                if ($kind==='column') self::need(in_array($r['COLUMN_NAME']??null,self::COLUMNS[$r['TABLE_NAME']],true),'metadata_unavailable');
            }$out[$kind]=$rows;self::need(strlen(self::json($out))<=65536,'metadata_limit');
        }return ['rows'=>$count,'data'=>$out];
    }
    private static function coverage(array $binding,array $snapshot,array $coverage,array $privileges): array
    {
        $c=$coverage;$d=$snapshot['data'];$safe=self::grantee($binding['account'])!==null;
        $c['accountEncoding']=$safe?'supported':'unsupported';$c['lowerCaseTableNames']=(int)$binding['lower_case_table_names'];
        $c['partialRevokes']=(string)$binding['partial_revokes']==='1';$c['activeRoles']=$binding['roles']==='NONE'?'none':'unexpanded';
        $patterns=[];$unsupported=false;
        foreach ($d['schema'] as $row) {
            $p=self::pattern($row['TABLE_SCHEMA'],$binding['selected_database']);
            if ($p===null) {$unsupported=true;continue;}
            if ($p['matches']) {$patterns[$row['TABLE_SCHEMA']]=$p['literal'];if (!$p['literal']) $unsupported=true;}
        }
        $c['matchingSchemaPatterns']=count($patterns);
        $c['schemaPatterns']=count($patterns)>1?'ambiguous':($unsupported?'unsupported':(count($patterns)===1?'single_literal_match':'no_match'));
        $c['state']=$safe&&preg_match('/^[A-Za-z0-9_.$-]{1,64}$/D',$binding['selected_database'])===1
            &&$c['lowerCaseTableNames']===0&&!$c['partialRevokes']&&$c['activeRoles']==='none'
            &&!$unsupported&&count($patterns)<=1?'complete':'partial';
        $c['tableColumnScopes']=[];
        foreach (self::TABLES as $name) {
            $t=[];$col=[];$n=0;
            foreach ($d['table'] as $row) if ($row['TABLE_NAME']===$name) $t[]=$row['PRIVILEGE_TYPE'];
            foreach ($d['column'] as $row) if ($row['TABLE_NAME']===$name) {$col[]=$row['PRIVILEGE_TYPE'];++$n;}
            $grants=[];foreach (self::COLUMNS[$name] as $column) {
                $types=[];foreach ($d['column'] as $row) if ($row['TABLE_NAME']===$name&&$row['COLUMN_NAME']===$column) $types[]=$row['PRIVILEGE_TYPE'];
                if ($types!==[]) $grants[]=['columnName'=>$column,'privileges'=>array_values(array_intersect(self::PRIVILEGES,$types))];
            }
            $c['tableColumnScopes'][]=['tableName'=>$name,'observationScope'=>'direct_metadata_fixed_columns','tablePrivileges'=>array_values(array_intersect(self::PRIVILEGES,$t)),
                'columnPrivileges'=>array_values(array_intersect(self::PRIVILEGES,$col)),'columnGrantRows'=>$n,'columnGrants'=>$grants];
        }
        foreach (self::PRIVILEGES as $key) {
            $global=in_array($key,array_column($d['global'],'PRIVILEGE_TYPE'),true);$schema=false;
            if (count($patterns)===1&&!$unsupported) foreach ($d['schema'] as $row) if ($row['TABLE_SCHEMA']===array_key_first($patterns)&&$row['PRIVILEGE_TYPE']===$key) $schema=true;
            $basis=$global?($schema?'direct_global_and_single_literal_schema':'direct_global'):($schema?'single_literal_schema':'none');
            $privileges[$key]=['status'=>$c['state']==='complete'&&$basis!=='none'?'proven':'not_proven','coverage'=>$c['state'],'basis'=>$basis];
        }return [$c,$privileges];
    }
    /** Fixture expectedDatabase has no runtime CLI/env override. This output never admits a CREATE. */
    public static function read(PDO $db,array $parent,string $site,string $expectedDatabase=self::DATABASE_SHA256): array
    {
        $r=['schemaVersion'=>1,'source'=>'anytour-hotel-stay-permission-proof-read-v1','state'=>'read_failed','observedAt'=>time(),
            'parentOperation'=>self::PARENT_OPERATION,'parentResultSha256'=>self::sha($parent),'parentFileSha256'=>$parent['local_stay_source_facts']['fileSha256']??null,
            'databaseSha256Before'=>null,'databaseSha256After'=>null,'accountSha256Before'=>null,'accountSha256After'=>null,'roleSha256Before'=>null,'roleSha256After'=>null,
            'oldAccountIdentityKnown'=>false,'protectedBefore'=>null,'protectedAfter'=>null,'coverageSha256'=>null,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'automaticAccepts'=>0,
            'allRequiredProven'=>false,'effectiveDenialClaimed'=>false,'ddlAdmission'=>false];
        $c=['state'=>'unavailable','accountEncoding'=>'not_observed','lowerCaseTableNames'=>null,'partialRevokes'=>null,'activeRoles'=>'not_observed',
            'schemaPatterns'=>'not_observed','matchingSchemaPatterns'=>null,'metadataRowsBefore'=>null,'metadataRowsAfter'=>null,
            'metadataSha256Before'=>null,'metadataSha256After'=>null,'tableColumnScopes'=>null,'limits'=>['rows'=>512,'bytes'=>131072]];
        $p=[];foreach (self::PRIVILEGES as $key) $p[$key]=['status'=>'not_proven','coverage'=>'unavailable','basis'=>'none'];
        $protected=null;$before=null;$owned=false;
        try {
            $protected=self::parent($parent,$expectedDatabase);
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            $r['protectedBefore']=self::fingerprint($site);self::need($r['protectedBefore']===$protected,'protected_drift');
            $before=self::binding($db);$r['databaseSha256Before']=self::database($before);$r['accountSha256Before']=self::sha(['account'=>$before['account']]);$r['roleSha256Before']=self::sha(['roles'=>$before['roles']]);
            self::need($r['databaseSha256Before']===$expectedDatabase,'database_identity');
            $c['accountEncoding']=self::grantee($before['account'])===null?'unsupported':'supported';$c['lowerCaseTableNames']=(int)$before['lower_case_table_names'];
            $c['partialRevokes']=(string)$before['partial_revokes']==='1';$c['activeRoles']=$before['roles']==='NONE'?'none':'unexpanded';
            if ($c['accountEncoding']==='unsupported') {$c['state']='partial';foreach ($p as &$priv) $priv['coverage']='partial';unset($priv);$r['state']='completed_read_only';}
            else {
                $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();$owned=true;
                $a=self::snapshot($db,(string)self::grantee($before['account']));$c['metadataRowsBefore']=$a['rows'];$c['metadataSha256Before']=self::sha($a['data']);
                [$c,$p]=self::coverage($before,$a,$c,$p);
                $b=self::snapshot($db,(string)self::grantee($before['account']));$c['metadataRowsAfter']=$b['rows'];$c['metadataSha256After']=self::sha($b['data']);
                self::need($c['metadataSha256Before']===$c['metadataSha256After'],'binding_drift');$db->commit();$owned=false;$r['state']='completed_read_only';
            }
        } catch (Throwable $e) {
            if ($owned&&$db->inTransaction()) $db->rollBack();$owned=false;
            $allowed=['parent_invalid','caller_transaction','database_identity','protected_drift','binding_drift','metadata_limit','metadata_unavailable'];
            $r['state']=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'metadata_unavailable';$c['state']='unavailable';
        } finally {
            if ($before!==null) try {
                $after=self::binding($db);$r['databaseSha256After']=self::database($after);$r['accountSha256After']=self::sha(['account'=>$after['account']]);$r['roleSha256After']=self::sha(['roles'=>$after['roles']]);
                self::need(self::json($before)===self::json($after),'binding_drift');
            } catch (Throwable $ignored) {$r['state']='binding_drift';$c['state']='unavailable';}
            if ($protected!==null) try {$r['protectedAfter']=self::fingerprint($site);self::need($r['protectedAfter']===$protected,'protected_drift');}
            catch (Throwable $ignored) {$r['state']='protected_drift';$c['state']='unavailable';}
        }
        if ($r['state']!=='completed_read_only') foreach ($p as &$priv) {$priv['status']='not_proven';$priv['coverage']='unavailable';$priv['basis']='none';}unset($priv);
        $r['allRequiredProven']=$c['state']==='complete'&&array_column($p,'status')===array_fill(0,4,'proven');
        $r['coverageSha256']=self::sha(['coverage'=>$c,'privileges'=>$p]);$result=['receipt'=>$r,'coverage'=>$c,'privileges'=>$p];
        self::need(strlen(self::json($result))<self::MAX_BYTES-1024,'result_size');return $result;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');$source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');
        $control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');$parentPath=(string)getenv('ANYTOUR_PERMISSION_PROOF_PARENT_PATH');
        if ($source!==AnyTourHotelStayPermissionProofReadV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)||$op!==AnyTourHotelStayPermissionProofReadV1::OPERATION
            ||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)
            ||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/')||$parentPath!==dirname($dir).'/'.AnyTourHotelStayPermissionProofReadV1::PARENT_OPERATION.'/result.json'
            ||!is_file($parentPath)||is_link($parentPath)||filesize($parentPath)!==3429||hash_file('sha256',$parentPath)!==AnyTourHotelStayPermissionProofReadV1::PARENT_SHA256) throw new RuntimeException('CLI_SCOPE');
        foreach (['DSN','DB_USER','DB_PASSWORD','DB_HOST','DB_NAME','DB_PORT'] as $key) if (getenv('ANYTOUR_DATA_'.$key)!==false) throw new RuntimeException('CLI_ENV');
        $path=$dir.'/local-stay-permission-proof-read.json';if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $parent=json_decode((string)file_get_contents($parentPath),true,512,JSON_THROW_ON_ERROR);
        if (($parent['local_stay_source_facts']['fileSha256']??null)!==AnyTourHotelStayPermissionProofReadV1::PARENT_FILE_SHA256
            ||AnyTourHotelStayPermissionProofReadV1::fingerprint($site)!==['config.php'=>AnyTourHotelStayPermissionProofReadV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelStayPermissionProofReadV1::HELPER_SHA256]) throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $helper=__DIR__.'/../../v2/data/db-v1.php';if (!is_file($helper)||is_link($helper)||hash_file('sha256',$helper)!==AnyTourHotelStayPermissionProofReadV1::HELPER_SHA256) throw new RuntimeException('CLI_HELPER');
        $_SERVER['DOCUMENT_ROOT']=$site;require_once $helper;
        $result=AnyTourHotelStayPermissionProofReadV1::read(v2_data_db(),$parent,$site,AnyTourHotelStayPermissionProofReadV1::DATABASE_SHA256);
        $result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];$json=AnyTourHotelStayPermissionProofReadV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStayPermissionProofReadV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);try {$handle=fopen($path,'x+b');} finally {umask($old);}if ($handle===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {if (fwrite($handle,$json)!==strlen($json)||!fflush($handle)||!fsync($handle)) throw new RuntimeException('CLI_RECEIPT_WRITE');} finally {fclose($handle);}
        echo $json;exit(in_array($result['receipt']['state'],['completed_read_only','metadata_unavailable','metadata_limit'],true)?0:1);
    } catch (Throwable $ignored) {fwrite(STDERR,"LOCAL_STAY_PERMISSION_PROOF_READ_FAILED\n");exit(1);}
}
