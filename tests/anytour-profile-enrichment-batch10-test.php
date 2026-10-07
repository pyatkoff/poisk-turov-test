<?php
/** Fictional-only crash tests for the unchanged importer in the stock CI MySQL. */
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-profile-enrichment-v1.php';

function b10_need(bool $ok, string $why): void {
    if (!$ok) throw new RuntimeException('BATCH10_TEST:'.$why);
}
function b10_dsn(): string {
    $dsn=(string)getenv('ANYTOUR_PROFILE_ENRICH_TEST_DSN');
    b10_need($dsn==='mysql:host=127.0.0.1;port=3306;dbname=anytour_profile_enrich_fixture;charset=utf8mb4'
        && getenv('ANYTOUR_PROFILE_ENRICH_TEST_PASSWORD')==='fixture-only-password','disposable_database_only');
    return $dsn;
}
function b10_options(): array {
    return [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false];
}

/** Real PDO/MySQL; only the injected failure points are test code. */
class Batch10FaultPDO extends PDO {
    public int $updates=0;
    public bool $committed=false;
    public function __construct(public string $fault) {
        parent::__construct(b10_dsn(),'root','fixture-only-password',b10_options());
        $this->exec('SET SESSION innodb_lock_wait_timeout=3');
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS,[Batch10FaultStatement::class,[$this]]);
    }
    public function stopNow(string $point): never {
        b10_need(function_exists('posix_kill') && defined('SIGKILL'),'sigkill_required');
        echo json_encode(['event'=>$point,'updates'=>$this->updates,'committed'=>$this->committed],JSON_THROW_ON_ERROR)."\n";
        fflush(STDOUT);
        posix_kill(getmypid(),SIGKILL);
        throw new RuntimeException('BATCH10_TEST:kill_did_not_stop');
    }
    public function commit(): bool {
        if($this->fault==='kill_before_commit')$this->stopNow('before_commit');
        $ok=parent::commit();$this->committed=$ok;
        if($this->fault==='kill_after_commit')$this->stopNow('after_commit_before_readback');
        return $ok;
    }
}
class Batch10FaultStatement extends PDOStatement {
    protected function __construct(private Batch10FaultPDO $db) {}
    public function execute(?array $params=null): bool {
        $sql=preg_replace('/\s+/',' ',trim($this->queryString));
        if($this->db->fault==='readback_error' && $this->db->committed
            && str_starts_with($sql,'SELECT profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?')
            && !str_contains($sql,'FOR UPDATE'))throw new RuntimeException('FICTIONAL_READBACK_FAILURE');
        $ok=parent::execute($params);
        if(str_starts_with($sql,'UPDATE anytour_hotels SET profile_json=')){
            ++$this->db->updates;
            if($this->db->fault==='kill_after_update5' && $this->db->updates===5)$this->db->stopNow('after_update5_before_commit');
        }
        return $ok;
    }
}
function b10_child(array $argv): never {
    b10_dsn();
    b10_need(count($argv)===3 && $argv[1]==='--fictional-child'
        && in_array($argv[2],['normal','kill_after_update5','kill_before_commit','kill_after_commit','readback_error'],true),'child_mode');
    $input=stream_get_contents(STDIN,65537);b10_need(is_string($input)&&strlen($input)<=65536,'input_bound');
    $v=json_decode($input,true,128,JSON_THROW_ON_ERROR);$p=$v['plan'];$operation=$v['operation'];
    b10_need(is_string($operation)&&preg_match('/^fixture-batch10-[a-z0-9-]+$/D',$operation)===1
        && $p['limit']===10 && count($p['contentScope'])===10,'fictional_scope');
    foreach($p['contentScope'] as $s)b10_need($s['localHotelId']>=1000000 && $s['localHotelId']<1001000
        && $s['fields']===['description'],'fictional_identity');
    $db=new Batch10FaultPDO($argv[2]);
    try{
        $result=(new AnyTourProfileEnrichmentV1($db))->apply($operation,10,$p['demandThrough'],$p['planSha256'],$p['contentScope'],true);
        echo json_encode(['result'=>$result,'updates'=>$db->updates,'committed'=>$db->committed],JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }catch(Throwable $e){
        echo json_encode(['error'=>$e->getMessage(),'updates'=>$db->updates,'committed'=>$db->committed],JSON_THROW_ON_ERROR)."\n";
        exit(82);
    }
}
function b10_process(string $mode,array $plan,string $operation): array {
    b10_dsn();
    $env=['ANYTOUR_PROFILE_ENRICH_TEST_DSN'=>b10_dsn(),
        'ANYTOUR_PROFILE_ENRICH_TEST_PASSWORD'=>'fixture-only-password','PATH'=>(string)getenv('PATH')];
    $proc=proc_open([PHP_BINARY,__FILE__,'--fictional-child',$mode],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__,$env);
    b10_need(is_resource($proc),'child_start');
    $bytes=json_encode(['plan'=>$plan,'operation'=>$operation],JSON_THROW_ON_ERROR);
    b10_need(strlen($bytes)<=65536 && fwrite($pipes[0],$bytes)===strlen($bytes),'input_write');fclose($pipes[0]);
    stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $out='';$err='';$end=microtime(true)+15;
    do{
        $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);$status=proc_get_status($proc);
        if(!$status['running'])break;
        if(microtime(true)>$end){proc_terminate($proc,9);throw new RuntimeException('BATCH10_TEST:child_timeout');}
        usleep(10000);
    }while(true);
    $out.=stream_get_contents($pipes[1]);$err.=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);
    b10_need($err==='' && strlen($out)<65536,'child_output');
    $value=json_decode(trim($out),true,64,JSON_THROW_ON_ERROR);
    return ['exit'=>$status['exitcode'],'signal'=>$status['signaled']?$status['termsig']:null,'output'=>$value];
}
function b10_rows(PDO $observer,array $scope): array {
    $ids=array_column($scope,'anytourHotelId');$marks=implode(',',array_fill(0,count($ids),'?'));
    $q=$observer->prepare("SELECT id,profile_json,profile_sha256,revision FROM anytour_hotels WHERE id IN ($marks) ORDER BY id");
    $q->execute($ids);$rows=$q->fetchAll();b10_need(count($rows)===10,'ten_fictional_rows');
    foreach($rows as $r)b10_need(hash('sha256',$r['profile_json'])===$r['profile_sha256'],'profile_hash');
    return array_column($rows,null,'id');
}
function b10_provenance(PDO $observer,string $operation,array $scope): array {
    $ids=array_column($scope,'anytourHotelId');$marks=implode(',',array_fill(0,count($ids),'?'));
    $q=$observer->prepare("SELECT anytour_hotel_id,source_json,source_sha256 FROM anytour_hotel_sources
        WHERE namespace='profile_sync:retained_tv_v1' AND anytour_hotel_id IN ($marks) ORDER BY anytour_hotel_id");
    $q->execute($ids);$sources=$q->fetchAll();
    foreach($sources as $r){b10_need(hash('sha256',$r['source_json'])===$r['source_sha256'],'provenance_hash');
        b10_need(json_decode($r['source_json'],true,64,JSON_THROW_ON_ERROR)['operation']===$operation,'provenance_operation');}
    return $sources;
}
function b10_verify_commit(PDO $observer,array $scope,array $wanted,string $operation,int $expected=10): void {
    $rows=b10_rows($observer,$scope);$sources=b10_provenance($observer,$operation,$scope);
    b10_need(count($sources)===$expected,'provenance_count');
    foreach($sources as $source){$proof=json_decode($source['source_json'],true,64,JSON_THROW_ON_ERROR);$own=(int)$source['anytour_hotel_id'];$row=$rows[$own];
        $p=json_decode($row['profile_json'],true,128,JSON_THROW_ON_ERROR);
        b10_need((int)$row['revision']===2 && $p['description']===$wanted[$own]
            && $proof['result_revision']===2 && $proof['result_profile_sha256']===$row['profile_sha256']
            && $proof['fields_updated']===['description'],'committed_profile_and_provenance');}
}
function run_batch10_mysql_tests(PDO $db,callable $make,callable $rawFor,callable $saveRaw): void {
    b10_dsn();b10_need(!$db->inTransaction(),'clean_fixture_connection');
    b10_need(function_exists('posix_kill') && function_exists('proc_open'),'actual_process_crashes_required');
    $source=(string)file_get_contents(__DIR__.'/../v2/data/anytour-profile-enrichment-v1.php');
    $blob=sha1('blob '.strlen($source)."\0".$source);
    b10_need($blob==='99e83233b73aff6415d459cc61464665743b76f7','unchanged_actual_owner');
    $observer=new PDO(b10_dsn(),'root','fixture-only-password',b10_options());
    $observer->exec('SET SESSION innodb_lock_wait_timeout=3');
    $engine=new AnyTourProfileEnrichmentV1($db);$through='2026-09-25T20:00:00Z';$case=0;$results=[];
    $prepare=function()use(&$case,$db,$make,$rawFor,$saveRaw,$engine,$through,$observer):array{
        ++$case;$scope=[];$raws=[];$wanted=[];
        for($i=0;$i<10;++$i){$local=1000000+$case*10+$i;$seed=$rawFor($local);$seed['name']='FICTIONAL batch10 '.$case.' '.$i;
            [$own,$raw]=$make($local,$seed);$raw['common']['description']='FICTIONAL updated '.$case.' '.$i;
            $saveRaw($raw,'2026-09-25 10:00:00');$scope[]=['anytourHotelId'=>$own,'localHotelId'=>$local,'fields'=>['description']];
            $raws[$own]=$raw;$wanted[$own]=$raw['common']['description'];}
        $p=$engine->plan(10,$through,$scope,true);b10_need(count($p['selected'])===10 && $p['writes']===0,'ten_real_owner_deltas');
        return [$p,$scope,b10_rows($observer,$scope),$raws,$wanted,'fixture-batch10-case'.$case];
    };
    // 1. Ordinary commit and 2. duplicate invocation of the exact fictional plan.
    [$p,$scope,$before,$raws,$want,$op]=$prepare();$run=b10_process('normal',$p,$op);
    b10_need($run['exit']===0 && ($run['output']['result']['status']??null)==='committed_verified'
        && $run['output']['result']['profilesUpdated']===10 && $run['output']['result']['fieldsFilled']===10,'normal_batch');
    b10_verify_commit($observer,$scope,$want,$op);$after=b10_rows($observer,$scope);
    $results[]=['scenario'=>'normal','durable_profiles'=>10,'durable_provenance'=>10,'reported_success'=>true];
    $again=b10_process('normal',$p,$op);b10_need($again['exit']===82 && str_contains($again['output']['error']??'','PLAN_DRIFT')
        && $again['output']['updates']===0 && b10_rows($observer,$scope)===$after,'stale_plan_not_reapplied');
    b10_verify_commit($observer,$scope,$want,$op);
    $results[]=['scenario'=>'repeat_original_fictional_plan','additional_updates'=>0,'additional_provenance'=>0,'rejected'=>'PLAN_DRIFT'];
    // 3-4. Actual SIGKILL before commit, including five real SQL updates in flight.
    foreach(['kill_after_update5'=>5,'kill_before_commit'=>10] as $mode=>$updates){
        [$p,$scope,$before,$raws,$want,$op]=$prepare();$run=b10_process($mode,$p,$op);
        b10_need($run['signal']===9 && $run['output']['updates']===$updates && !$run['output']['committed'],'actual_precommit_sigkill');
        b10_need(b10_rows($observer,$scope)===$before && count(b10_provenance($observer,$op,$scope))===0,'rollback_all_ten');
        $results[]=['scenario'=>$mode,'signal'=>9,'sql_updates_before_kill'=>$updates,'durable_profiles'=>0,'durable_provenance'=>0];
    }
    // 5. Commit returned, but the process dies before the importer can read back/report.
    [$p,$scope,$before,$raws,$want,$op]=$prepare();$run=b10_process('kill_after_commit',$p,$op);
    b10_need($run['signal']===9 && $run['output']['committed'] && $run['output']['updates']===10
        && !isset($run['output']['result']),'actual_postcommit_sigkill');b10_verify_commit($observer,$scope,$want,$op);
    $results[]=['scenario'=>'kill_after_commit','signal'=>9,'durable_profiles'=>10,'durable_provenance'=>10,'reported_success'=>false];
    // 6. The real postcommit readback path throws rather than returning false success.
    [$p,$scope,$before,$raws,$want,$op]=$prepare();$run=b10_process('readback_error',$p,$op);
    b10_need($run['exit']===82 && $run['output']['error']==='FICTIONAL_READBACK_FAILURE' && $run['output']['committed'],'postcommit_readback_failed');
    b10_verify_commit($observer,$scope,$want,$op);
    $results[]=['scenario'=>'postcommit_readback_error','durable_profiles'=>10,'durable_provenance'=>10,'reported_success'=>false];
    // 7. A manual change after plan invalidates the WHOLE old batch before any write.
    [$p,$scope,$before,$raws,$want,$op]=$prepare();$own=$scope[4]['anytourHotelId'];
    $manual=json_decode($before[$own]['profile_json'],true,128,JSON_THROW_ON_ERROR);$manual['description']='FICTIONAL MANUAL KEEP';$j=AnyTourProfileEnrichmentV1::json($manual);
    $db->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=?,revision=2 WHERE id=?')->execute([$j,hash('sha256',$j),$own]);
    $manualState=b10_rows($observer,$scope);$run=b10_process('normal',$p,$op);
    b10_need($run['exit']===82 && str_contains($run['output']['error']??'','PLAN_DRIFT') && $run['output']['updates']===0
        && b10_rows($observer,$scope)===$manualState && count(b10_provenance($observer,$op,$scope))===0,'manual_change_preserved');
    $results[]=['scenario'=>'manual_change_after_plan','importer_updates'=>0,'manual_preserved'=>true,'whole_batch_rejected'=>true];
    // 8. Manual content already present at planning is held while the nine valid rows proceed.
    $fresh=$engine->plan(10,$through,$scope,true);b10_need(count($fresh['selected'])===9 && isset($fresh['held'][$own]),'manual_row_held_at_plan');
    $run=b10_process('normal',$fresh,$op);b10_need($run['exit']===0 && $run['output']['result']['profilesUpdated']===9,'nine_independent_rows');
    b10_verify_commit($observer,$scope,$want,$op,9);b10_need(b10_rows($observer,$scope)[$own]===$manualState[$own],'manual_still_unchanged');
    $results[]=['scenario'=>'manual_change_before_new_fictional_plan','durable_profiles'=>9,'durable_provenance'=>9,'manual_preserved'=>true];
    // 9. Changed retained content after preparation also rejects the complete stale batch.
    [$p,$scope,$before,$raws,$want,$op]=$prepare();$raw=$raws[$scope[4]['anytourHotelId']];
    $raw['common']['description']='FICTIONAL SOURCE CHANGED AFTER PLAN';$saveRaw($raw,'2026-09-25 10:00:01');
    $run=b10_process('normal',$p,$op);b10_need($run['exit']===82 && str_contains($run['output']['error']??'','PLAN_DRIFT')
        && $run['output']['updates']===0 && b10_rows($observer,$scope)===$before && count(b10_provenance($observer,$op,$scope))===0,'source_change_rejected');
    $results[]=['scenario'=>'source_change_after_plan','durable_profiles'=>0,'durable_provenance'=>0,'whole_batch_rejected'=>true];
    // 10. An empty new description never deletes the existing description.
    [$p,$scope,$before,$raws,$want,$op]=$prepare();foreach($raws as $raw){$raw['common']['description']='';$saveRaw($raw,'2026-09-25 10:00:01');}
    $empty=$engine->plan(10,$through,$scope,true);b10_need($empty['selected']===[],'empty_source_has_no_patch');
    $run=b10_process('normal',$empty,$op);b10_need($run['exit']===0 && $run['output']['result']['profilesUpdated']===0
        && b10_rows($observer,$scope)===$before && count(b10_provenance($observer,$op,$scope))===0,'empty_source_preserves_all');
    $results[]=['scenario'=>'empty_source','durable_profiles'=>0,'durable_provenance'=>0,'existing_descriptions_preserved'=>10];
    echo 'BATCH10_ACTUAL_MYSQL_REPORT '.json_encode(['status'=>'passed','owner_blob'=>$blob,'pdo_driver'=>$observer->getAttribute(PDO::ATTR_DRIVER_NAME),
        'mysql_version'=>$observer->getAttribute(PDO::ATTR_SERVER_VERSION),'scenarios'=>count($results),'batch_size'=>10,'results'=>$results,
        'production_database_access'=>false,'supplier_http'=>0,'operational_recovery_implemented'=>false],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}
if(PHP_SAPI==='cli' && realpath((string)($argv[0]??''))===__FILE__)b10_child($argv);
