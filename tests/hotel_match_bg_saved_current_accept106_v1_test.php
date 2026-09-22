<?php
declare(strict_types=1);
// Offline, no sockets: exercise the exact saved cohort and the real append-only
// transaction against synthetic CURRENT row projections.
define('MATCH_UNIT_TEST', true);
require getenv('MATCH_SCRIPT_PATH') ?: __DIR__.'/../scripts/diagnostics/hotel_match_bg_saved_current_accept106_v1.php';
require getenv('MATCH_RESOLVER_PATH') ?: __DIR__.'/../app/integrations/andromeda-hotel-resolver.php';
final class FixturePDO extends PDO {
    public array $identities; public array $hotels; public array $manual;
    public string $fault; public string $dir; public int $inserts=0; public int $commits=0;
    public bool $tx=false; public bool $serial=false; public bool $readOnly=false;
    private array $backup=[];
    public function __construct(array $i,array $h,array $m,string $fault,string $dir) {
        $this->identities=$i; $this->hotels=$h; $this->manual=$m; $this->fault=$fault; $this->dir=$dir;
    }
    public function setAttribute(int $attribute,mixed $value):bool { return true; }
    public function prepare(string $query,array $options=[]):PDOStatement|false { return new FixtureStatement($this,$query); }
    public function exec(string $statement):int|false {
        if($statement==='SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'){$this->serial=true;return 0;}
        if($statement==='SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'||$statement==='SET SESSION innodb_lock_wait_timeout=15')return 0;
        if($statement==='START TRANSACTION READ ONLY'){$this->beginTransaction();$this->readOnly=true;return 0;}
        throw new RuntimeException('fixture_denied_exec:'.$statement);
    }
    public function beginTransaction():bool { need(!$this->tx,'fixture_nested_tx');$this->backup=$this->identities;$this->tx=true;$this->readOnly=false;return true; }
    public function inTransaction():bool { return $this->tx; }
    public function commit():bool {
        need($this->tx&&$this->serial&&!$this->readOnly,'fixture_commit_scope');
        need(is_file($this->dir.'/pre-commit.json')&&is_file($this->dir.'/commit-attempt.json'),'fixture_missing_commit_checkpoint');
        $this->tx=false;$this->commits++;
        if($this->fault==='commit_unknown')throw new RuntimeException('fixture_lost_commit_reply');
        if($this->fault==='commit_false')return false;
        return true;
    }
    public function rollBack():bool { need($this->tx,'fixture_no_tx');$this->identities=$this->backup;$this->tx=false;return true; }
    public function run(string $sql,array $params):array {
        need($this->tx,'fixture_query_outside_tx');
        if($sql==='SHOW TABLES')return array_map(fn($t)=>['table'=>$t],array_merge(['catalog_hotels','andromeda_hotel_identities','anex_hotel_decisions'],$this->fault==='unknown_guard'?['hotel_exclusions']:[]));
        if(str_starts_with($sql,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json,created_at FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id')) {
            $r=$this->identities;usort($r,fn($a,$b)=>strcmp(keyOf($a),keyOf($b)));
            if($this->fault==='post_corrupt'&&$this->commits>0)foreach($r as &$row)if(str_contains($row['evidence_json'],OP)){$row['local_hotel_id']=999999;break;}unset($row);
            return $r;
        }
        if(str_starts_with($sql,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ('))return array_values(array_filter($this->hotels,fn($h)=>in_array($h['id'],$params,true)));
        if(str_starts_with($sql,'SELECT anex_hotel_id,decision_status,catalog_hotel_id,decided_by,decision_note,decided_at,updated_at FROM anex_hotel_decisions ORDER BY anex_hotel_id'))return $this->manual;
        if($sql==="INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)") {
            need($this->serial&&!$this->readOnly&&is_file($this->dir.'/capture.json')&&is_file($this->dir.'/plan.json'),'fixture_insert_guard');
            if($this->fault==='insert_error'&&$this->inserts===4)throw new RuntimeException('fixture_insert_failure');
            [$ns,$native,$tv,$catalog,$sha,$body]=$params;
            foreach($this->identities as $r)need(keyOf($r)!==$ns.'|'.$native,'fixture_unique_key');
            $e=json_decode($body,true);need(hash('sha256',$body)===$sha&&$e['operation_id']===OP,'fixture_evidence');
            need($ns==='bgoperator'&&$e['identity_separation']==='bgoperator_f4_is_raw_and_is_not_operator_115_or_samo_id'&&$e['prefix_transform_applied']===false,'fixture_namespace');
            $this->identities[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$native,'local_hotel_id'=>$tv,'decision_status'=>'accepted','catalog_sha256'=>$catalog,'evidence_sha256'=>$sha,'evidence_json'=>$body,'created_at'=>'2026-09-21 17:00:00'];
            $this->inserts++;return [];
        }
        throw new RuntimeException('fixture_sql_denied:'.$sql);
    }
}
final class FixtureStatement extends PDOStatement {
    private FixturePDO $db;private string $sql;private array $rows=[];
    public function __construct(FixturePDO $db,string $sql){$this->db=$db;$this->sql=$sql;}
    public function execute(?array $params=null):bool {$this->rows=$this->db->run($this->sql,$params??[]);return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array {return $this->rows;}
    public function rowCount():int {return str_starts_with($this->sql,'INSERT')?1:count($this->rows);}
}
$input=$argv[1]??'';$s=seeds($input);need(count($s)===106,'real_cohort_count');
$sourceKinds=array_count_values(array_map(fn($x)=>$x['source']['evidence_kind'],$s));ksort($sourceKinds);
need($sourceKinds===['alias_delta'=>72,'independent_common4'=>16,'source_catalog'=>18],'real_cohort_split');
$identities=[];$hotels=[];$manual=[];$seen=[];$pairs=[];
foreach($s as &$seed){
    need($seed['supplier_namespace']==='bgoperator'&&preg_match('/^102[0-9]+$/D',$seed['native_hotel_id'])===1,'raw_bg_identity');
    need(!isset($seen[$seed['native_hotel_id']])&&!isset($pairs[$seed['tv_hotel_id']]),'cohort_unique');$seen[$seed['native_hotel_id']]=true;$pairs[$seed['tv_hotel_id']]=true;
    $ej=canon(['fixture_catalog_id'=>$seed['expected_anchor']['external_hotel_id']]);$eh=hash('sha256',$ej);
    $seed['expected_anchor']['evidence_sha256']=$eh;$seed['expected_anchor']['evidence_json_sha256']=$eh;
    $identities[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$seed['expected_anchor']['external_hotel_id'],'local_hotel_id'=>$seed['tv_hotel_id'],'decision_status'=>'accepted','catalog_sha256'=>$seed['expected_anchor']['catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej,'created_at'=>'2026-09-22 13:00:00'];
    $t=$seed['expected_target'];$hotels[]=['id'=>(int)$t['id'],'name'=>(string)$t['name'],'country_id'=>(int)$t['country_id'],'country_name'=>(string)$t['country_name'],'region_name'=>(string)($t['region_name']??''),'subregion_name'=>(string)($t['subregion_name']??''),'category'=>$t['category'],'is_active'=>(int)$t['is_active']];
    $seed['expected_target']=end($hotels);
}
unset($seed);
[$keys,$targets,$occupied]=indexed($identities);$hot=array_column($hotels,null,'id');$manualSet=[];$good=0;
foreach($s as $seed){need(reason($seed,$keys,$targets,$occupied,$hot,$manualSet)===null,'actual_full_predicate');$good++;}
$root=sys_get_temp_dir().'/match-bg-saved-accept106-'.bin2hex(random_bytes(6));mkdir($root,0700);$results=[];
foreach(['success','manual','source_rejected','target_occupied','canonical_multi','anchor_changed','below_threshold','insert_error','commit_unknown','commit_false','post_corrupt','unknown_guard'] as $case) {
    $i=$identities;$h=$hotels;$m=$manual;$expectedCount=106;
    if($case==='manual'){$m[]=['catalog_hotel_id'=>$s[0]['tv_hotel_id']];$expectedCount-=count(array_filter($s,fn($x)=>$x['tv_hotel_id']===$s[0]['tv_hotel_id']));}
    if($case==='source_rejected'||$case==='below_threshold'){
        $n=$case==='below_threshold'?9:1;$expectedCount-= $n;
        foreach(array_slice($s,0,$n) as $x)$i[]=['supplier_namespace'=>$x['supplier_namespace'],'external_hotel_id'=>$x['native_hotel_id'],'local_hotel_id'=>null,'decision_status'=>'rejected','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256','{}'),'evidence_json'=>'{}','created_at'=>'2026-09-21 17:00:00'];
    }
    if($case==='target_occupied'){$x=$s[0];$i[]=['supplier_namespace'=>'bgoperator','external_hotel_id'=>'199999999999','local_hotel_id'=>$x['tv_hotel_id'],'decision_status'=>'conflict','catalog_sha256'=>str_repeat('b',64),'evidence_sha256'=>hash('sha256','{}'),'evidence_json'=>'{}','created_at'=>'2026-09-22 13:00:00'];$expectedCount--;}
    if($case==='canonical_multi'){$x=$s[0];$i[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'999999999999','local_hotel_id'=>$x['tv_hotel_id'],'decision_status'=>'accepted','catalog_sha256'=>str_repeat('c',64),'evidence_sha256'=>hash('sha256','{}'),'evidence_json'=>'{}','created_at'=>'2026-09-22 13:00:00'];$expectedCount--;}
    if($case==='anchor_changed'){
        foreach($i as &$r)if($r['supplier_namespace']==='andromeda_catalog'&&$r['local_hotel_id']===$s[0]['tv_hotel_id']){$r['evidence_json'].=' ';$r['evidence_sha256']=hash('sha256',$r['evidence_json']);}unset($r);
        $expectedCount-=count(array_filter($s,fn($x)=>$x['tv_hotel_id']===$s[0]['tv_hotel_id']));
    }
    $dir=$root.'/'.$case;mkdir($dir);$db=new FixturePDO($i,$h,$m,$case,$dir);$before=rh($i);$thrown=false;$r=null;
    try{$r=writeMappings($db,$s,$dir,str_repeat('a',40));}catch(Throwable $e){$thrown=true;}
    if(in_array($case,['insert_error','commit_unknown','commit_false','post_corrupt','unknown_guard'],true)){
        need($thrown,'fault_not_raised:'.$case);$f=loadj($dir.'/failure.json');
        $state=in_array($case,['commit_unknown','commit_false'],true)?'commit_unknown_no_replay':($case==='post_corrupt'?'post_commit_verification_failed_no_replay':'rolled_back_no_write');
        need($f['state']===$state,'fault_state:'.$case);
        if($state==='rolled_back_no_write')need(rh($db->identities)===$before&&$f['mapping_writes']===0,'rollback_preserves');
        else need($f['mapping_writes']===null,'uncertainty_not_zero');
        $results[$case]=$f['state'];continue;
    }
    need(!$thrown&&is_array($r),'unexpected_test_exception:'.$case);
    if($case==='below_threshold'){need($r['state']==='completed_no_write_below_threshold'&&$r['current_safe']===97&&$db->inserts===0&&rh($db->identities)===$before,'below_threshold_no_writes');}
    else {need($r['state']==='committed_verified'&&$r['mapping_writes']===$expectedCount&&$r['post_commit_verified']===$expectedCount&&$r['resolver_verified']===$expectedCount&&$r['old_rows_preserved']===count($i),'success_count:'.$case);}
    $shaResult=savej($dir.'/result.json',$r);
    savej($dir.'/receipt.json',['operation'=>OP,'source_sha'=>str_repeat('a',40),'state'=>$r['state'],'result_sha256'=>$shaResult,'mapping_writes'=>$r['mapping_writes'],'database_writes'=>$r['database_writes'],'readback_verified'=>$r['readback_verified'],'provider_calls'=>0,'no_replay'=>true]);
    $results[$case]=['state'=>$r['state'],'writes'=>$r['mapping_writes'],'safe'=>$r['current_safe']];
}
$marker=$root.'/started.json';savej($marker,['operation'=>OP]);$blocked=false;try{@savej($marker,['operation'=>OP]);}catch(RuntimeException){$blocked=true;}need($blocked,'exclusive_no_replay');
$out=['state'=>'offline_tests_passed','candidate_count'=>count($s),'full_predicate_matches'=>$good,'source_kinds'=>$sourceKinds,'raw_bg_namespace_only'=>true,'operator_115_writes'=>0,'transaction_cases'=>$results,'exclusive_no_replay'=>true,'fixture_only'=>true,'live_database_writes'=>0,'fixture_output'=>$root];
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";

