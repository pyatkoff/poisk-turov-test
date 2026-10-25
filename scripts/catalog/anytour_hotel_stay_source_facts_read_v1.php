<?php
/** Fixed raw source facts and unreviewed hotel-local drafts. No stay lookup, writes or price authority. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/anytour_hotel_stay_offer_candidates_v2.php';

final class AnyTourHotelStaySourceFactsReadV1
{
    public const OPERATION='int-andromeda-local-stay-source-facts-read-20261026-v1';
    public const CONTEXT_OPERATION='int-andromeda-local-stay-install-context-read-20261026-v1';
    public const FAILURE_OPERATION='int-andromeda-local-stay-context-install-20261026-v1';
    public const SOURCE='1d0e529e3fc0666783db1454c0765d3c16d63843';
    public const PARENT_SOURCE='30a7e7013edd7fe70a4700db0aafb2bf22ee05f8';
    public const CONTEXT_CONTROL='af256ad98636480ee1e62c59808bad2a612d6420';
    public const FAILURE_CONTROL='ecc2e42af388a81de965949e1e9e2e9ee9011698';
    public const CONTEXT_SHA256='21ce19017ad4211a67d8f9e053d2ed033cfa3f5350493d0797084eb31867f1b0';
    public const FAILURE_SHA256='0da0b944d7a09b010fbd449fbc6004916c2f1e4d9c7698df850eb5eb65308596';
    public const DATABASE_SHA256='4cd52b85a632855e79a5fafbe4e425d1c3c6a51dba2c995e0a43f976fbc25f1d';
    public const HELPER_SHA256='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4';
    public const CONFIG_SHA256='121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def';
    public const TOUR='25282424201500';
    public const MAX_BYTES=2097152;

    public static function json(mixed $value): string
    {
        $canonical=static function(mixed $item) use (&$canonical): mixed {
            if (!is_array($item)) return $item;
            if (!array_is_list($item)) ksort($item,SORT_STRING);
            foreach ($item as $key=>$part) $item[$key]=$canonical($part);
            return $item;
        };
        return json_encode($canonical($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    private static function need(bool $ok,string $reason): void {if (!$ok) throw new RuntimeException($reason);}
    private static function digest(mixed $value): string {return hash('sha256',self::json($value));}
    private static function lineage(array $context,array $failure,string $expected): array
    {
        $c=$context['local_stay_install_context']??null;$r=$c['data']['receipt']??null;
        $f=$failure['local_stay_context_install']['data']['receipt']??null;
        self::need(preg_match('/^[a-f0-9]{64}$/D',$expected)===1&&is_array($c)&&is_array($r)&&is_array($f)
            &&($context['status']??null)==='complete'&&($context['mode']??null)==='local-stay-install-context-read-v1'
            &&($context['operation_id']??null)===self::CONTEXT_OPERATION&&($context['source_sha']??null)===self::PARENT_SOURCE
            &&($r['source']??null)==='anytour-hotel-stay-install-context-read-v1'&&($r['schemaVersion']??null)===1
            &&($r['operationId']??null)===self::CONTEXT_OPERATION&&($r['controlSha']??null)===self::CONTEXT_CONTROL
            &&($r['sourceSha']??null)===self::PARENT_SOURCE&&($r['state']??null)==='completed_read_only'
            &&($r['databaseSha256']??null)===$expected&&($r['connectionAuthority']??null)==='current_root_config_and_accepted_helper'
            &&($r['helperAccepted']??null)===true&&($r['phpExecuted']??null)===true
            &&($r['oldDatabaseIdentityKnown']??null)===false&&($r['applyAuthorized']??null)===false
            &&($failure['mode']??null)==='local-stay-context-install-v1'&&($failure['status']??null)==='terminal_nonzero_no_replay'
            &&($failure['operation_id']??null)===self::FAILURE_OPERATION&&($failure['source_sha']??null)===self::PARENT_SOURCE
            &&($failure['schema_write_outcome_unknown']??null)===false
            &&($f['source']??null)==='anytour-hotel-stay-context-install-v1'&&($f['schemaVersion']??null)===1
            &&($f['operationId']??null)===self::FAILURE_OPERATION&&($f['controlSha']??null)===self::FAILURE_CONTROL
            &&($f['sourceSha']??null)===self::PARENT_SOURCE&&($f['state']??null)==='preflight_refused'&&($f['reason']??null)==='privileges'
            &&($f['databaseSha256']??null)===$expected&&($f['contextDatabaseSha256']??null)===$expected
            &&($f['contextResultSha256']??null)===self::digest($context)&&($f['ddlOutcomeUnknown']??null)===false
            &&($f['attemptedTables']??null)===[]&&($f['createdTables']??null)===[]&&($f['unknownTables']??null)===[]
            &&array_key_exists('journalSha256',$f)&&$f['journalSha256']===null,'context_contract');
        foreach (['supplierCalls','databaseWrites','schemaWrites','automaticAccepts'] as $key)
            self::need(($r[$key]??null)===0&&($f[$key]??null)===0,'context_contract');
        foreach (['booking_calls','database_writes','lead_calls','mapping_writes','profile_writes','schema_writes','schema_writes_acknowledged','supplier_calls'] as $key)
            self::need(($failure[$key]??null)===0,'context_contract');
        $protected=$r['currentProtectedBefore']??null;
        self::need(is_array($protected)&&array_keys($protected)===['config.php','data/db-v1.php']
            &&is_string($protected['config.php'])&&preg_match('/^[a-f0-9]{64}$/D',$protected['config.php'])===1
            &&$protected['data/db-v1.php']===self::HELPER_SHA256&&$protected===($r['currentProtectedAfter']??null)
            &&$protected===($c['protectedBefore']??null)&&$protected===($c['protectedAfter']??null)
            &&$protected===($f['protectedBefore']??null)&&$protected===($f['protectedAfter']??null),'context_contract');
        return $protected;
    }
    public static function fingerprint(string $site): array
    {
        $out=[];foreach (['config.php','data/db-v1.php'] as $name) {
            $path=$site.'/'.$name;self::need(is_file($path)&&!is_link($path)&&realpath($path)===$path&&filesize($path)>0&&filesize($path)<=2097152,'protected_drift');
            $out[$name]=hash_file('sha256',$path);
        }return $out;
    }
    private static function database(PDO $db): string
    {
        $row=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch(PDO::FETCH_ASSOC);
        self::need(is_array($row)&&is_string($row['selected_database']??null)&&$row['selected_database']!==''&&strlen($row['selected_database'])<=64
            &&is_string($row['server_uuid']??null)&&preg_match('/^[a-f0-9-]{36}$/Di',$row['server_uuid'])===1,'database_identity');
        return self::digest(['database'=>$row['selected_database'],'serverUuid'=>$row['server_uuid']]);
    }
    private static function clearFacts(array &$result): void
    {
        foreach (['anchorEvidence','sourceFacts','currentCohorts','draftConcepts'] as $key) $result[$key]=null;
        foreach (['anchorRetainedRows','anchorExpiredRows','currentValidatedOffers','currentFactRows','returnedCohorts','draftConceptCount',
            'omittedMissingOperatorOffers','omittedMissingStayFactOffers'] as $key) $result['receipt'][$key]=0;
    }
    /** expectedDatabase is an explicit native-fixture argument; runtime CLI always supplies the literal target. */
    public static function read(PDO $db,array $context,array $failure,string $site,string $expectedDatabase=self::DATABASE_SHA256): array
    {
        $result=['receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-stay-source-facts-read-v1','state'=>'read_failed','observedAt'=>time(),
            'tourvisorTourId'=>self::TOUR,'contextOperation'=>self::CONTEXT_OPERATION,'contextResultSha256'=>self::digest($context),
            'failedInstallOperation'=>self::FAILURE_OPERATION,'failedInstallResultSha256'=>self::digest($failure),
            'databaseSha256Before'=>null,'databaseSha256After'=>null,'protectedBefore'=>null,'protectedAfter'=>null,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'automaticAccepts'=>0,
            'importHeld'=>true,'priceAuthority'=>false,'anchorRetainedRows'=>0,'anchorExpiredRows'=>0,'currentValidatedOffers'=>0,
            'currentFactRows'=>0,'returnedCohorts'=>0,'draftConceptCount'=>0,'omittedMissingOperatorOffers'=>0,'omittedMissingStayFactOffers'=>0,
            'missingTables'=>[],'factsSha256'=>null],
            'anchorEvidence'=>null,'sourceFacts'=>null,'currentCohorts'=>null,'draftConcepts'=>null,
            'conceptLookup'=>['state'=>'not_read','reason'=>'source_facts_only','rooms'=>null,'meals'=>null,'mappings'=>null],
            'privilegeProof'=>['state'=>'unavailable','scope'=>'direct_global_or_schema','CREATE'=>null,'REFERENCES'=>null,'SELECT'=>null,'TRIGGER'=>null,'allRequired'=>null]];
        $r=&$result['receipt'];$protected=null;
        try {
            $protected=self::lineage($context,$failure,$expectedDatabase);
            self::need(!$db->inTransaction()&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&$db->getAttribute(PDO::ATTR_ERRMODE)===PDO::ERRMODE_EXCEPTION,'caller_transaction');
            $r['protectedBefore']=self::fingerprint($site);self::need($r['protectedBefore']===$protected,'protected_drift');
            $r['databaseSha256Before']=self::database($db);self::need($r['databaseSha256Before']===$expectedDatabase,'database_identity');
            $producer=new AnyTourHotelStayOfferCandidatesV2($db);$result['privilegeProof']=$producer->directPrivilegeProof();
            $facts=$producer->sourceOnly(self::TOUR);
            foreach (['anchorEvidence','sourceFacts','currentCohorts','draftConcepts'] as $key) $result[$key]=$facts[$key];
            $r['anchorRetainedRows']=$facts['anchorEvidence']['retainedRows'];$r['anchorExpiredRows']=$facts['anchorEvidence']['expiredRows'];
            foreach (['currentValidatedOffers','currentFactRows','omittedMissingOperatorOffers','omittedMissingStayFactOffers'] as $key) $r[$key]=$facts[$key];
            $r['returnedCohorts']=count($facts['currentCohorts']);$r['draftConceptCount']=count($facts['draftConcepts']);$r['state']='completed_read_only';
        } catch (AnyTourHotelStayOfferCandidatesSchemaException $e) {$r['state']='source_schema_missing';$r['missingTables']=$e->missingTables;}
        catch (Throwable $e) {
            $states=['HOTEL_STAY_ANCHOR_MISSING'=>'anchor_missing','HOTEL_STAY_ANCHOR_PAYLOAD_INVALID'=>'anchor_payload_invalid',
                'HOTEL_STAY_ANCHOR_IDENTITY_AMBIGUOUS'=>'anchor_identity_ambiguous','HOTEL_STAY_ANCHOR_IDENTITY_UNAVAILABLE'=>'anchor_identity_unavailable',
                'HOTEL_STAY_ANCHOR_ROW_LIMIT'=>'anchor_row_limit','HOTEL_STAY_ANCHOR_CURRENT_ROW_LIMIT'=>'current_row_limit',
                'HOTEL_STAY_OFFER_CANDIDATE_PAYLOAD_INTEGRITY'=>'current_payload_invalid','HOTEL_STAY_SOURCE_COHORT_LIMIT'=>'cohort_limit',
                'HOTEL_STAY_SOURCE_SIZE_LIMIT'=>'result_size_limit','context_contract'=>'context_contract','database_identity'=>'database_identity',
                'protected_drift'=>'protected_drift','caller_transaction'=>'caller_transaction','HOTEL_STAY_SOURCE_CALLER_TRANSACTION'=>'caller_transaction'];
            $r['state']=$states[$e->getMessage()]??'read_failed';
        } finally {
            if ($r['databaseSha256Before']!==null) try {$r['databaseSha256After']=self::database($db);
                self::need($r['databaseSha256After']===$expectedDatabase,'database_identity');}
            catch (Throwable $ignored) {$r['state']='database_identity';}
            if ($protected!==null) try {$r['protectedAfter']=self::fingerprint($site);self::need($r['protectedAfter']===$protected,'protected_drift');}
            catch (Throwable $ignored) {$r['state']='protected_drift';}
        }
        if ($r['state']!=='completed_read_only') self::clearFacts($result);
        if (strlen(self::json($result))>self::MAX_BYTES-1024) {$r['state']='result_size_limit';self::clearFacts($result);}
        $parts=$result;unset($parts['receipt']);$r['factsSha256']=self::digest($parts);return $result;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__) {
    try {
        if ($argc!==1) throw new RuntimeException('CLI_SCOPE');
        $site=(string)getenv('ANYTOUR_SITE_ROOT');$dir=(string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR');
        $source=(string)getenv('ANYTOUR_OPERATION_RELEASE_SHA');$control=(string)getenv('ANYTOUR_OPERATION_CONTROL_SHA');$op=(string)getenv('ANYTOUR_OPERATION_ID');
        $contextPath=(string)getenv('ANYTOUR_SOURCE_FACTS_CONTEXT_PATH');$failurePath=(string)getenv('ANYTOUR_SOURCE_FACTS_FAILURE_PATH');
        if ($source!==AnyTourHotelStaySourceFactsReadV1::SOURCE||!preg_match('/^[a-f0-9]{40}$/D',$control)||$op!==AnyTourHotelStaySourceFactsReadV1::OPERATION
            ||$site===''||realpath($site)!==$site||basename($site)!=='anytoour.ru'||$dir===''||realpath($dir)!==$dir||is_link($dir)||!is_dir($dir)
            ||basename($dir)!==$op||str_starts_with($dir.'/',$site.'/')
            ||$contextPath!==dirname($dir).'/'.AnyTourHotelStaySourceFactsReadV1::CONTEXT_OPERATION.'/result.json'
            ||$failurePath!==dirname($dir).'/'.AnyTourHotelStaySourceFactsReadV1::FAILURE_OPERATION.'/result.json') throw new RuntimeException('CLI_SCOPE');
        foreach ([$contextPath=>[8528,AnyTourHotelStaySourceFactsReadV1::CONTEXT_SHA256],$failurePath=>[8644,AnyTourHotelStaySourceFactsReadV1::FAILURE_SHA256]] as $path=>$pin)
            if (!is_file($path)||is_link($path)||filesize($path)!==$pin[0]||hash_file('sha256',$path)!==$pin[1]) throw new RuntimeException('CLI_PARENT');
        foreach (['DSN','DB_USER','DB_PASSWORD','DB_HOST','DB_NAME','DB_PORT'] as $key) if (getenv('ANYTOUR_DATA_'.$key)!==false) throw new RuntimeException('CLI_ENV');
        $path=$dir.'/local-stay-source-facts-read.json';if (file_exists($path)||is_link($path)) throw new RuntimeException('CLI_RECEIPT_EXISTS');
        $context=json_decode((string)file_get_contents($contextPath),true,512,JSON_THROW_ON_ERROR);$failure=json_decode((string)file_get_contents($failurePath),true,512,JSON_THROW_ON_ERROR);
        if (AnyTourHotelStaySourceFactsReadV1::fingerprint($site)!==['config.php'=>AnyTourHotelStaySourceFactsReadV1::CONFIG_SHA256,'data/db-v1.php'=>AnyTourHotelStaySourceFactsReadV1::HELPER_SHA256]) throw new RuntimeException('CLI_PRIVATE_DRIFT');
        $helper=__DIR__.'/../../v2/data/db-v1.php';if (!is_file($helper)||is_link($helper)||hash_file('sha256',$helper)!==AnyTourHotelStaySourceFactsReadV1::HELPER_SHA256) throw new RuntimeException('CLI_HELPER');
        $_SERVER['DOCUMENT_ROOT']=$site;require_once $helper;
        $result=AnyTourHotelStaySourceFactsReadV1::read(v2_data_db(),$context,$failure,$site,AnyTourHotelStaySourceFactsReadV1::DATABASE_SHA256);
        $result['receipt']+=['operationId'=>$op,'sourceSha'=>$source,'controlSha'=>$control];$json=AnyTourHotelStaySourceFactsReadV1::json($result)."\n";
        if (strlen($json)>AnyTourHotelStaySourceFactsReadV1::MAX_BYTES) throw new RuntimeException('CLI_RESULT_SIZE');
        $old=umask(0077);try {$handle=fopen($path,'x+b');} finally {umask($old);}if ($handle===false) throw new RuntimeException('CLI_RECEIPT_CREATE');
        try {if (fwrite($handle,$json)!==strlen($json)||!fflush($handle)||!fsync($handle)) throw new RuntimeException('CLI_RECEIPT_WRITE');} finally {fclose($handle);}
        echo $json;exit(in_array($result['receipt']['state'],['read_failed','context_contract','database_identity','protected_drift','caller_transaction'],true)?1:0);
    } catch (Throwable $ignored) {fwrite(STDERR,"LOCAL_STAY_SOURCE_FACTS_READ_FAILED\n");exit(1);}
}
