<?php
/** New protected-context-bound metadata admission. The accepted engine is an unbound library component. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$enginePath=__DIR__.'/anytour_hotel_stay_permission_proof_read_v1.php';
if (!is_file($enginePath)||is_link($enginePath)||hash_file('sha256',$enginePath)!=='1e9b8a15f0418e467380bcfe63368e8e4734b76a1deb9d95d6b16245b2cc6e4b') throw new RuntimeException('ENGINE_SOURCE');
require_once $enginePath; // Its CLI guard is false: no historical operation is executed.

final class AnyTourHotelStayContextPermissionReadV1
{
    public const OPERATION='int-andromeda-local-stay-context-permission-read-20261026-v1';
    public const CONTEXT_OPERATION='int-andromeda-local-stay-protected-context-read-20261026-v1';
    public const CONTEXT_CONTROL='fbb814c3460dad4a72eb8ea235801d1b8cc950eb';
    public const CONTEXT_SHA256='49656487d2db323519ce915584001a948408765e72cfa73bfabdb16c81fdfeea';
    public const CONTEXT_FILE_SHA256='a65936a8731590baf63a4fa0657a860a5f583e6e84c2d43d186bbf9e29900315';
    public const FAILED_OPERATION='int-andromeda-local-stay-permission-proof-read-20261026-v1';
    public const FAILED_SHA256='356a4d731d4a9d6f38045ea9c27ed71dcd07e87a525f7af942a1abccfcb2d005';
    public const RAW_OPERATION='int-andromeda-local-stay-source-facts-read-20261026-v1';
    public const RAW_SHA256='0852e6438c911a4c04c772c6d81fff7613b61593bb5613948d460fc60b8a0817';
    public const SOURCE='1d0e529e3fc0666783db1454c0765d3c16d63843';
    public const ENGINE_SHA256='1e9b8a15f0418e467380bcfe63368e8e4734b76a1deb9d95d6b16245b2cc6e4b';
    public const MAX_BYTES=262144;
    public const BASELINE=['config.php'=>'121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def',
        'data/db-v1.php'=>'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'];
    public static function json(mixed $v): string {return AnyTourHotelStayPermissionProofReadV1::json($v);}
    private static function sha(mixed $v): string {return hash('sha256',self::json($v));}
    private static function need(bool $ok): void {if (!$ok) throw new RuntimeException('PARENT_INVALID');}
    private static function digest(mixed $v): bool {return is_string($v)&&preg_match('/^[a-f0-9]{64}$/D',$v)===1;}
    private static function keys(array $a,array $keys): bool {$actual=array_keys($a);sort($actual,SORT_STRING);sort($keys,SORT_STRING);return $actual===$keys;}

    private static function parents(array $context,array $failed,array $raw,string $expected): array
    {
        $box=$context['local_stay_protected_context']??null;$data=$box['data']??null;$r=$data['receipt']??null;$rr=$raw['local_stay_source_facts']['data']['receipt']??null;
        self::need(is_array($box)&&is_array($data)&&is_array($r)&&is_array($rr)&&self::digest($expected)
            &&($context['mode']??null)==='local-stay-protected-context-read-v1'&&($context['status']??null)==='complete'
            &&($context['operation_id']??null)===self::CONTEXT_OPERATION&&($context['source_sha']??null)===self::SOURCE
            &&($r['schemaVersion']??null)===1&&($r['source']??null)==='anytour-hotel-stay-protected-context-read-v1'
            &&($r['operationId']??null)===self::CONTEXT_OPERATION&&($r['sourceSha']??null)===self::SOURCE&&($r['controlSha']??null)===self::CONTEXT_CONTROL
            &&($r['state']??null)==='completed_read_only'&&($r['changedFiles']??null)===[]
            &&($box['fileSha256']??null)===hash('sha256',self::json($data)."\n")
            &&($r['failedPermissionOperation']??null)===self::FAILED_OPERATION&&($r['failedPermissionResultSha256']??null)===self::sha($failed)
            &&($box['failedPermissionResultSha256']??null)===self::sha($failed)
            &&($r['parentOperation']??null)===self::RAW_OPERATION&&($r['parentResultSha256']??null)===self::sha($raw)&&($box['parentResultSha256']??null)===self::sha($raw)
            &&($r['parentFileSha256']??null)===($raw['local_stay_source_facts']['fileSha256']??null)
            &&($failed['mode']??null)==='local-stay-permission-proof-read-v1'&&($failed['operation_id']??null)===self::FAILED_OPERATION
            &&($failed['source_sha']??null)===self::SOURCE&&($failed['status']??null)==='unknown_no_replay'&&($failed['reason']??null)==='local_stay_permission_private_drift'
            &&!array_key_exists('local_stay_permission_proof',$failed)
            &&($raw['mode']??null)==='local-stay-source-facts-read-v1'&&($raw['status']??null)==='complete'&&($raw['operation_id']??null)===self::RAW_OPERATION&&($raw['source_sha']??null)===self::SOURCE
            &&($rr['state']??null)==='anchor_missing'&&($rr['controlSha']??null)===AnyTourHotelStayPermissionProofReadV1::PARENT_CONTROL
            &&($rr['databaseSha256Before']??null)===$expected&&($rr['databaseSha256After']??null)===$expected);
        foreach (['phpExecuted','helperExecuted','databaseConnected','applyAuthorized','ddlAdmission','priceAuthority'] as $key) self::need(($r[$key]??null)===false);
        foreach (['databaseState','accountState','permissionState'] as $key) self::need(($r[$key]??null)==='NOT_MEASURED');
        foreach (['supplierCalls','databaseWrites','schemaWrites','profileWrites','mappingWrites','automaticAccepts','subprocessCalls'] as $key) self::need(($r[$key]??null)===0);
        foreach (['booking_calls','database_writes','lead_calls','mapping_writes','profile_writes','schema_writes','supplier_calls'] as $key) self::need(($failed[$key]??null)===0);
        $baseline=$r['baselineProtected']??null;self::need(is_array($baseline)&&self::keys($baseline,array_keys(self::BASELINE))&&self::digest($baseline['config.php'])&&$baseline['data/db-v1.php']===self::BASELINE['data/db-v1.php']);
        foreach (['protectedBefore','protectedAfter'] as $key) {
            self::need(is_array($data[$key]??null)&&self::keys($data[$key],array_keys(self::BASELINE))&&($rr[$key]??null)===$baseline);
            foreach ($baseline as $name=>$sha) self::need(($data[$key][$name]??null)===['matchesBaseline'=>true,'sha256'=>$sha,'status'=>'hashed']);
        }return $baseline;
    }
    /** Fixed two-file derived observation; no configuration contents or raw errors are returned. */
    public static function observe(string $site,array $baseline): array
    {
        $out=[];foreach (array_keys(self::BASELINE) as $name) {
            $p=$site.'/'.$name;$status='read_failed';$sha=null;$handle=null;
            try {
                clearstatcache(true,$p);$stat=@lstat($p);
                if ($stat===false) $status='missing';
                elseif (is_link($p)) $status='symlink';
                elseif (!is_file($p)||realpath($p)!==$p) $status='not_regular';
                elseif ($stat['size']<1||$stat['size']>2097152) $status='size_limit';
                else {
                    $handle=@fopen($p,'rb');$opened=is_resource($handle)?fstat($handle):false;
                    if (is_array($opened)&&$opened['ino']===$stat['ino']&&$opened['dev']===$stat['dev']) {
                        $hash=hash_init('sha256');$n=hash_update_stream($hash,$handle,2097153);$end=fstat($handle);clearstatcache(true,$p);$last=@lstat($p);
                        if ($n===$stat['size']&&is_array($last)&&is_array($end)&&$last['ino']===$opened['ino']&&$last['dev']===$opened['dev']&&$end['size']===$stat['size']&&$last['size']===$stat['size']&&!is_link($p)) {$sha=hash_final($hash);$status='hashed';}
                    }
                }
            } catch (Throwable $ignored) {} finally {if (is_resource($handle)) fclose($handle);}
            $out[$name]=['status'=>$status,'sha256'=>$sha,'matchesBaseline'=>$status==='hashed'?$sha===$baseline[$name]:null];
        }return $out;
    }
    private static function protectedResult(array &$result,string $site): void
    {
        $p=&$result['protectedContext'];$p['protectedAfter']=self::observe($site,$p['baselineProtected']);$p['changedFiles']=[];$hashed=true;$matches=true;
        foreach (array_keys(self::BASELINE) as $name) foreach (['protectedBefore','protectedAfter'] as $side) {
            $entry=$p[$side][$name];if ($entry['status']!=='hashed') $hashed=false;
            if ($entry['matchesBaseline']!==true) $matches=false;
            if ($entry['status']==='hashed'&&$entry['matchesBaseline']===false&&!in_array($name,$p['changedFiles'],true)) $p['changedFiles'][]=$name;
        }
        $equal=self::json($p['protectedBefore'])===self::json($p['protectedAfter']);$p['stable']=$hashed&&$equal;
        if ($result['receipt']['state']!=='parent_invalid') {
            if (!$equal) $result['receipt']['state']='protected_drift';
            elseif (!$hashed||!$matches) $result['receipt']['state']='protected_refused';
        }
        if (!$p['stable']||!$matches) $result['receipt']['allRequiredProven']=false;
    }
    public static function prepare(array $context,array $failed,array $raw,string $site,string $expectedDatabase=AnyTourHotelStayPermissionProofReadV1::DATABASE_SHA256): array
    {
        $result=['receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-stay-context-permission-read-v1','state'=>'parent_invalid','observedAt'=>time(),
            'contextOperation'=>self::CONTEXT_OPERATION,'contextResultSha256'=>self::sha($context),'contextFileSha256'=>$context['local_stay_protected_context']['fileSha256']??null,
            'failedPermissionOperation'=>self::FAILED_OPERATION,'failedPermissionResultSha256'=>self::sha($failed),'rawOperation'=>self::RAW_OPERATION,'rawResultSha256'=>self::sha($raw),
            'engineSourceSha256'=>self::ENGINE_SHA256,'engineResultSha256'=>null,'engineOperationBound'=>false,'engineCliExecuted'=>false,
            'phpExecuted'=>true,'helperAttempted'=>false,'pdoAttempted'=>false,'engineInvoked'=>false,'databaseBindingKnown'=>false,'accountBindingKnown'=>false,'roleBindingKnown'=>false,
            'oldAccountIdentityKnown'=>false,'allRequiredProven'=>false,'effectiveDenialClaimed'=>false,'ddlAdmission'=>false,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'automaticAccepts'=>0],
            'protectedContext'=>['baselineProtected'=>self::BASELINE,'protectedBefore'=>[],'protectedAfter'=>[],'changedFiles'=>[],'stable'=>false],'engine'=>null];
        try {$baseline=self::parents($context,$failed,$raw,$expectedDatabase);$result['protectedContext']['baselineProtected']=$baseline;$result['receipt']['state']='ready';}
        catch (Throwable $ignored) {}
        $result['protectedContext']['protectedBefore']=self::observe($site,$result['protectedContext']['baselineProtected']);self::protectedResult($result,$site);return $result;
    }
    public static function after(array $result,string $site): array {self::protectedResult($result,$site);return $result;}
    private static function engineValid(array $engine): bool
    {
        if (!self::keys($engine,['receipt','coverage','privileges'])||strlen(self::json($engine))>self::MAX_BYTES-8192) return false;
        $r=$engine['receipt'];$c=$engine['coverage'];$p=$engine['privileges'];
        if (!is_array($r)||!is_array($c)||!is_array($p)||!self::keys($r,['schemaVersion','source','state','observedAt','parentOperation','parentResultSha256','parentFileSha256',
            'databaseSha256Before','databaseSha256After','accountSha256Before','accountSha256After','roleSha256Before','roleSha256After','oldAccountIdentityKnown','protectedBefore','protectedAfter','coverageSha256',
            'supplierCalls','databaseWrites','schemaWrites','profileWrites','mappingWrites','automaticAccepts','allRequiredProven','effectiveDenialClaimed','ddlAdmission'])
            ||$r['schemaVersion']!==1||$r['source']!=='anytour-hotel-stay-permission-proof-read-v1'||!is_int($r['observedAt'])||$r['oldAccountIdentityKnown']!==false||$r['effectiveDenialClaimed']!==false||$r['ddlAdmission']!==false
            ||!in_array($r['state'],['completed_read_only','parent_invalid','caller_transaction','database_identity','protected_drift','binding_drift','metadata_unavailable','metadata_limit','read_failed'],true)
            ||!self::keys($p,AnyTourHotelStayPermissionProofReadV1::PRIVILEGES)||!in_array($c['state']??null,['complete','partial','unavailable'],true)
            ||$r['coverageSha256']!==self::sha(['coverage'=>$c,'privileges'=>$p])) return false;
        if (!self::keys($c,['state','accountEncoding','lowerCaseTableNames','partialRevokes','activeRoles','schemaPatterns','matchingSchemaPatterns','metadataRowsBefore','metadataRowsAfter','metadataSha256Before','metadataSha256After','tableColumnScopes','limits'])
            ||!in_array($c['accountEncoding'],['supported','unsupported','not_observed'],true)||!in_array($c['lowerCaseTableNames'],[null,0,1,2],true)
            ||!in_array($c['partialRevokes'],[null,false,true],true)||!in_array($c['activeRoles'],['none','unexpanded','not_observed'],true)
            ||!in_array($c['schemaPatterns'],['single_literal_match','no_match','ambiguous','unsupported','not_observed'],true)
            ||!is_array($c['limits'])||!self::keys($c['limits'],['rows','bytes'])||$c['limits']['rows']!==512||$c['limits']['bytes']!==131072) return false;
        if ($c['matchingSchemaPatterns']!==null&&(!is_int($c['matchingSchemaPatterns'])||$c['matchingSchemaPatterns']<0||$c['matchingSchemaPatterns']>256)) return false;
        foreach (['Before','After'] as $side) {
            $rows=$c['metadataRows'.$side];$hash=$c['metadataSha256'.$side];
            if (($rows===null)!==($hash===null)||($rows!==null&&(!is_int($rows)||$rows<0||$rows>256||!self::digest($hash)))) return false;
            $files=$r['protected'.$side];if ($files!==null&&(!is_array($files)||!self::keys($files,array_keys(self::BASELINE))||!self::digest($files['config.php'])||!self::digest($files['data/db-v1.php']))) return false;
        }
        if ($c['tableColumnScopes']!==null) {
            if (!is_array($c['tableColumnScopes'])||!array_is_list($c['tableColumnScopes'])||count($c['tableColumnScopes'])!==7) return false;
            foreach ($c['tableColumnScopes'] as $i=>$row) {
                if (!is_array($row)||!self::keys($row,['tableName','observationScope','tablePrivileges','columnPrivileges','columnGrantRows','columnGrants'])||$row['tableName']!==AnyTourHotelStayPermissionProofReadV1::TABLES[$i]
                    ||$row['observationScope']!=='direct_metadata_fixed_columns'||!is_int($row['columnGrantRows'])||$row['columnGrantRows']<0||$row['columnGrantRows']>256) return false;
                foreach (['tablePrivileges','columnPrivileges'] as $field) if (!is_array($row[$field])||$row[$field]!==array_values(array_intersect(AnyTourHotelStayPermissionProofReadV1::PRIVILEGES,$row[$field]))) return false;
                if (!is_array($row['columnGrants'])||!array_is_list($row['columnGrants'])) return false;$names=[];$types=[];$count=0;
                foreach ($row['columnGrants'] as $grant) {
                    if (!is_array($grant)||!self::keys($grant,['columnName','privileges'])||!in_array($grant['columnName'],AnyTourHotelStayPermissionProofReadV1::COLUMNS[$row['tableName']],true)||in_array($grant['columnName'],$names,true)
                        ||!is_array($grant['privileges'])||$grant['privileges']===[]||$grant['privileges']!==array_values(array_intersect(AnyTourHotelStayPermissionProofReadV1::PRIVILEGES,$grant['privileges']))) return false;
                    $names[]=$grant['columnName'];array_push($types,...$grant['privileges']);$count+=count($grant['privileges']);
                }
                if ($names!==array_values(array_intersect(AnyTourHotelStayPermissionProofReadV1::COLUMNS[$row['tableName']],$names))||$count!==$row['columnGrantRows']||$row['columnPrivileges']!==array_values(array_intersect(AnyTourHotelStayPermissionProofReadV1::PRIVILEGES,$types))) return false;
            }
        }
        if ($c['state']==='complete'&&($r['state']!=='completed_read_only'||$c['accountEncoding']!=='supported'||$c['lowerCaseTableNames']!==0||$c['partialRevokes']!==false||$c['activeRoles']!=='none'
            ||!in_array($c['schemaPatterns'],['single_literal_match','no_match'],true)||$c['matchingSchemaPatterns']!==($c['schemaPatterns']==='no_match'?0:1)
            ||$c['metadataSha256Before']===null||$c['metadataSha256Before']!==$c['metadataSha256After']||$c['metadataRowsBefore']!==$c['metadataRowsAfter']||$c['tableColumnScopes']===null)) return false;
        foreach (['supplierCalls','databaseWrites','schemaWrites','profileWrites','mappingWrites','automaticAccepts'] as $key) if ($r[$key]!==0) return false;
        foreach (['databaseSha256Before','databaseSha256After','accountSha256Before','accountSha256After','roleSha256Before','roleSha256After'] as $key) if ($r[$key]!==null&&!self::digest($r[$key])) return false;
        $all=true;foreach ($p as $priv) {
            if (!is_array($priv)||!self::keys($priv,['status','coverage','basis'])||$priv['coverage']!==$c['state']||!in_array($priv['basis'],['none','direct_global','single_literal_schema','direct_global_and_single_literal_schema'],true)
                ||$priv['status']!==($r['state']==='completed_read_only'&&$c['state']==='complete'&&$priv['basis']!=='none'?'proven':'not_proven')) return false;
            if (str_contains($priv['basis'],'single_literal_schema')&&$c['schemaPatterns']!=='single_literal_match') return false;
            if ($c['state']==='unavailable'&&$priv['basis']!=='none') return false;
            if ($priv['status']!=='proven') $all=false;
        }return $r['allRequiredProven']===($all&&$c['state']==='complete');
    }
    /** Supplied PDO is already an attempted connection; the caller did not execute a helper through this pure API. */
    public static function read(PDO $db,array $context,array $failed,array $raw,string $site,string $expectedDatabase=AnyTourHotelStayPermissionProofReadV1::DATABASE_SHA256): array
    {
        $result=self::prepare($context,$failed,$raw,$site,$expectedDatabase);$result['receipt']['pdoAttempted']=true;
        if ($result['receipt']['state']!=='ready') return $result;
        $result['receipt']['engineInvoked']=true;
        try {$engine=AnyTourHotelStayPermissionProofReadV1::read($db,$raw,$site,$expectedDatabase);return self::finish($result,$engine,$raw,$site,$expectedDatabase);}
        catch (Throwable $ignored) {$result['receipt']['state']='engine_held';self::protectedResult($result,$site);return $result;}
    }
    public static function finish(array $result,array $engine,array $raw,string $site,string $expectedDatabase): array
    {
        $r=&$result['receipt'];$r['state']='engine_invalid';$result['engine']=null;$r['engineResultSha256']=null;$r['allRequiredProven']=false;
        foreach (['databaseBindingKnown','accountBindingKnown','roleBindingKnown'] as $key) $r[$key]=false;
        if (self::engineValid($engine)&&$engine['receipt']['parentResultSha256']===self::sha($raw)&&$engine['receipt']['parentOperation']===self::RAW_OPERATION
            &&$engine['receipt']['parentFileSha256']===($raw['local_stay_source_facts']['fileSha256']??null)) {
            $result['engine']=$engine;$r['engineResultSha256']=self::sha($engine);$er=$engine['receipt'];
            $r['databaseBindingKnown']=$er['databaseSha256Before']===$expectedDatabase&&$er['databaseSha256After']===$expectedDatabase;
            foreach (['account','role'] as $kind) $r[$kind.'BindingKnown']=$r['databaseBindingKnown']&&self::digest($er[$kind.'Sha256Before'])&&$er[$kind.'Sha256Before']===$er[$kind.'Sha256After'];
            $protected=$result['protectedContext']['baselineProtected'];
            $r['state']=$er['state']==='completed_read_only'&&$r['databaseBindingKnown']&&$r['accountBindingKnown']&&$r['roleBindingKnown']&&$er['protectedBefore']===$protected&&$er['protectedAfter']===$protected?'completed_read_only':'engine_held';
            $r['allRequiredProven']=$r['state']==='completed_read_only'&&$er['allRequiredProven']===true;
        }
        self::protectedResult($result,$site);if (strlen(self::json($result))>self::MAX_BYTES-1024) {$r['state']='result_size_limit';$r['allRequiredProven']=false;$result['engine']=null;$r['engineResultSha256']=null;}return $result;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');$source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');
        $paths=[(string)getenv('ANYTOUR_CONTEXT_PERMISSION_CONTEXT_PATH')=>[AnyTourHotelStayContextPermissionReadV1::CONTEXT_OPERATION,2930,AnyTourHotelStayContextPermissionReadV1::CONTEXT_SHA256],
            (string)getenv('ANYTOUR_CONTEXT_PERMISSION_FAILURE_PATH')=>[AnyTourHotelStayContextPermissionReadV1::FAILED_OPERATION,573,AnyTourHotelStayContextPermissionReadV1::FAILED_SHA256],
            (string)getenv('ANYTOUR_CONTEXT_PERMISSION_RAW_PATH')=>[AnyTourHotelStayContextPermissionReadV1::RAW_OPERATION,3429,AnyTourHotelStayContextPermissionReadV1::RAW_SHA256]];
        if ($source!==AnyTourHotelStayContextPermissionReadV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)||$op!==AnyTourHotelStayContextPermissionReadV1::OPERATION||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'
            ||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/')||count($paths)!==3) throw new RuntimeException('CLI_SCOPE');
        $parents=[];foreach ($paths as $path=>$pin) {if ($path!==dirname($dir).'/'.$pin[0].'/result.json'||!is_file($path)||is_link($path)||filesize($path)!==$pin[1]||hash_file('sha256',$path)!==$pin[2]) throw new RuntimeException('CLI_PARENT');$parents[]=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);}
        foreach (['DSN','DB_USER','DB_PASSWORD','DB_HOST','DB_NAME','DB_PORT'] as $key) if (getenv('ANYTOUR_DATA_'.$key)!==false) throw new RuntimeException('CLI_ENV');
        $path=$dir.'/local-stay-context-permission-read.json';if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $result=AnyTourHotelStayContextPermissionReadV1::prepare($parents[0],$parents[1],$parents[2],$site);
        if ($result['protectedContext']['baselineProtected']!==AnyTourHotelStayContextPermissionReadV1::BASELINE) throw new RuntimeException('CLI_BASELINE');
        if ($result['receipt']['state']==='ready') {
            $helper=__DIR__.'/../../v2/data/db-v1.php';$result['receipt']['state']='helper_unavailable';
            if (is_file($helper)&&!is_link($helper)&&hash_file('sha256',$helper)===AnyTourHotelStayContextPermissionReadV1::BASELINE['data/db-v1.php']) try {
                $result['receipt']['helperAttempted']=true;$_SERVER['DOCUMENT_ROOT']=$site;require_once $helper;
                $result['receipt']['state']='pdo_unavailable';$result['receipt']['pdoAttempted']=true;$db=v2_data_db();
                $measured=AnyTourHotelStayContextPermissionReadV1::read($db,$parents[0],$parents[1],$parents[2],$site);$measured['receipt']['helperAttempted']=true;$measured['protectedContext']['protectedBefore']=$result['protectedContext']['protectedBefore'];$result=AnyTourHotelStayContextPermissionReadV1::after($measured,$site);
            } catch (Throwable $ignored) {}
        }
        // Re-observe even when helper/PDO acquisition failed; a refusal never loses current file facts.
        if ($result['engine']===null&&$result['receipt']['state']!=='parent_invalid') {
            $result=AnyTourHotelStayContextPermissionReadV1::after($result,$site);
        }
        $result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];$json=AnyTourHotelStayContextPermissionReadV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStayContextPermissionReadV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);try {$h=fopen($path,'x+b');} finally {umask($old);}if ($h===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {if (fwrite($h,$json)!==strlen($json)||!fflush($h)||!fsync($h)) throw new RuntimeException('CLI_RECEIPT_WRITE');} finally {fclose($h);}
        echo $json;exit(in_array($result['receipt']['state'],['completed_read_only','protected_refused','helper_unavailable','pdo_unavailable','engine_held'],true)?0:1);
    } catch (Throwable $ignored) {fwrite(STDERR,"LOCAL_STAY_CONTEXT_PERMISSION_READ_FAILED\n");exit(1);}
}
