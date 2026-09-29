<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_primary_candidate_v1.php';

$checks=0;
function check(bool $ok,string $label): void {global $checks;$checks++;if(!$ok)throw new RuntimeException('test_failed:'.$label);}
function rejects(callable $fn,string $label): void {$raised=false;try{$fn();}catch(Throwable $e){$raised=true;}check($raised,$label);}
function fixture(): array {
    $entries=[];$identities=[];$hotels=[];
    foreach(PM1_PAIRS as $id=>$spec){
        $name='Fixture Hotel '.$id;
        $source=['id'=>$spec['catalog'],'name'=>$name,'lName'=>$name,'state'=>'Fixture country','town'=>'Fixture town','starKey'=>4];
        $json=w76_json(['source'=>$source,'reason'=>'no_unique_name','candidate_ids'=>[],'target_name'=>null]);
        $row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$spec['catalog'],'local_hotel_id'=>null,
            'decision_status'=>'pending','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$json),'evidence_json'=>$json];
        $identities[]=$row;
        $hotels[$id]=['id'=>$id,'name'=>$name,'country_id'=>99,'country_name'=>'Fixture country','region_name'=>'Fixture town',
            'subregion_name'=>'Fixture suburb','category'=>4,'is_active'=>1,'latitude'=>null,'longitude'=>null];
        $entries[$id]=['id'=>$id,'catalog_id'=>$spec['catalog'],'namespace'=>$spec['namespace'],'native_id'=>$spec['native'],
            'prepare_holds'=>[],'prior'=>$row,'expected_name'=>$name,'expected_country'=>'Fixture country',
            'observed_at'=>time()-60,'operator_facts'=>[['ns'=>$spec['namespace'],'native'=>$spec['native']]],
            'proofs'=>[['namespace'=>$spec['namespace'],'native_id'=>$spec['native'],
                'tv'=>['kind'=>'independent_tv_audit','row'=>[]],'samo'=>[],'samo_catalog_id'=>$spec['catalog']]]];
    }
    $context=pm1_context($identities);$context['hotels']=$hotels;
    return [$entries,$identities,$hotels,$context];
}
function scratch(): string {$dir=sys_get_temp_dir().'/match-primary-tests-'.bin2hex(random_bytes(8));mkdir($dir,0700);return $dir;}
function erase(string $dir): void {foreach(new DirectoryIterator($dir) as $f){if($f->isDot())continue;$p=$f->getPathname();if($f->isDir()&&!$f->isLink())erase($p);else unlink($p);}rmdir($dir);}

check(function_exists('mb_strtolower'),'real_mbstring_required');
[$entries,$identities,$hotels,$context]=fixture();
pm1_scope($entries);check(true,'exact_three_scope');
foreach(['int-andromeda-match-primary-samo3-20260929-v1'] as $op)check(pm1_operation($op),'operation_valid');
foreach(['hotel-match-old-v84','int-anex-match-primary-samo3-20260929-v1','int-andromeda-match-primary-../escape-v1'] as $op)check(!pm1_operation($op),'operation_rejected');
foreach(['extra','protected','wrong_operator','wrong_target'] as $change){
    $bad=$entries;
    if($change==='extra')$bad[100]=$entries[420];
    if($change==='protected')$bad[420]['catalog_id']='2000086118';
    if($change==='wrong_operator')$bad[420]['namespace']='operator_315';
    if($change==='wrong_target')$bad[420]['id']=421;
    rejects(fn()=>pm1_scope($bad),'scope_'.$change);
}
$spec=PM1_PAIRS[420];
$edge=['tv_hotel_id'=>420,'operator_id'=>43,'state'=>'detail_identity_verified','link_state'=>'captured_single_native',
    'namespace'=>'operator_342','positive_native_candidates'=>['24402'],'operator_link_sha256'=>str_repeat('b',64),
    'tour_id_sha256'=>str_repeat('c',64),'search_id_sha256'=>str_repeat('d',64),'operator_link_host'=>'searchtour.intourist.ru'];
check(pm1_tv_edge($edge,420,$spec),'valid_tv_edge');
foreach(['operator_id'=>25,'namespace'=>'operator_315','tv_hotel_id'=>421,'operator_link_host'=>'example.invalid',
    'state'=>'unknown','link_state'=>'ambiguous','positive_native_candidates'=>['24402','9'],'tour_id_sha256'=>'bad'] as $key=>$value){
    $bad=$edge;$bad[$key]=$value;check(!pm1_tv_edge($bad,420,$spec),'edge_'.$key);
}
$raw=['hotelKey'=>'9501','operatorKey'=>'342','original'=>['hotelKey'=>'24402','operatorKey'=>'342']];
check(w78_fact($raw,'9501','operator_342','24402'),'raw_native_valid');
foreach(['hotelKey'=>'24402','operatorKey'=>'315','isOperatorHotelKey'=>true] as $key=>$value){$bad=$raw;$bad[$key]=$value;check(!w78_fact($bad,'9501','operator_342','24402'),'raw_'.$key);}
check(w78_ptr(['rows'=>[$raw]],'/rows/0')===$raw,'json_pointer');
rejects(fn()=>w78_ptr(['rows'=>[$raw]],'/rows/1'),'pointer_missing');
foreach($entries as $id=>$entry)check(pm1_classify($entry,$context)===['status'=>'ready','reasons'=>[]],'baseline_ready_'.$id);
$mutations=[
    'source_drift'=>function(&$e,&$c){$e['prior']['evidence_sha256']=str_repeat('f',64);},
    'target_catalog_occupied'=>function(&$e,&$c){$c['targets'][420][]=['external_hotel_id'=>'different'];},
    'target_inactive'=>function(&$e,&$c){$c['hotels'][420]['is_active']=0;},
    'target_drift'=>function(&$e,&$c){$c['hotels'][420]['name']='Another hotel';},
    'protected_target'=>function(&$e,&$c){$c['manual'][420]=true;},
    'exclusion'=>function(&$e,&$c){$c['exclusions'][420]=true;},
    'outside_samo_live30'=>function(&$e,&$c){$e['observed_at']=time()-31*86400;},
    'future_observation'=>function(&$e,&$c){$e['observed_at']=time()+3600;},
    'geography_requires_review'=>function(&$e,&$c){$c['hotels'][420]['region_name']='Other town';},
    'operator_other_target'=>function(&$e,&$c){$r=$c['sources']['9501'][0];$r['supplier_namespace']='operator_342';$r['external_hotel_id']='24402';$r['decision_status']='accepted';$r['local_hotel_id']=999;$c['operators']['operator_342']['24402']=[$r];},
    'protected_operator'=>function(&$e,&$c){$r=$c['sources']['9501'][0];$r['decision_status']='rejected';$c['operators']['operator_342']['24402']=[$r];},
    'no_proof'=>function(&$e,&$c){$e['proofs']=[];},
    'retained_hold'=>function(&$e,&$c){$e['prepare_holds']=['retained_conflict'];},
];
foreach($mutations as $label=>$mutate){$e=$entries[420];$c=$context;$mutate($e,$c);check(pm1_classify($e,$c)['status']==='hold','current_'.$label);}
$c=$context;$c['sources']['9501'][0]['decision_status']='accepted';$c['sources']['9501'][0]['local_hotel_id']=420;
check(pm1_classify($entries[420],$c)['status']==='already','existing_acceptance_not_new');
check(pm1_geography(['town'=>'X'],['region_name'=>'X','subregion_name'=>''])===[],'exact_town');
check(pm1_geography(['town'=>'X','latitude'=>1,'longitude'=>1],['region_name'=>'X','latitude'=>5,'longitude'=>5])===['coordinate_conflict_over_5km'],'coordinate_conflict_precedence');
check(pm1_geography(['latitude'=>0,'longitude'=>0],['latitude'=>0,'longitude'=>0])===['geography_requires_review'],'missing_coordinates_not_evidence');
check(pm1_geography(['latitude'=>45,'longitude'=>30],['latitude'=>45.001,'longitude'=>30])===[],'near_coordinates');
$dir=scratch();mkdir($dir.'/fixture');$bytes='{"state":"completed","operation":"fixture","database_writes":0,"mapping_writes":0}';
file_put_contents($dir.'/fixture/result.json',$bytes);$sha=hash('sha256',$bytes);
file_put_contents($dir.'/fixture/receipt.json',w76_json(['state'=>'completed','result_sha256'=>$sha]));
check(pm1_terminal($dir,'fixture',$sha,['completed'])['operation']==='fixture','terminal_hash_binding');
rejects(fn()=>pm1_read($dir,'fixture/result.json',str_repeat('0',64)),'tamper_digest');
rejects(fn()=>pm1_read($dir,'../fixture/result.json'),'path_escape');
rejects(fn()=>pm1_read($dir,'fixture/result.json',null,2),'size_cap');
symlink($dir.'/fixture/result.json',$dir.'/fixture/link.json');rejects(fn()=>pm1_read($dir,'fixture/link.json'),'symlink');
rejects(fn()=>pm1_terminal($dir,'fixture',$sha,['unknown']),'terminal_state');
erase($dir);
echo 'PRIMARY_PURE_PASS '.$checks."\n";

if(getenv('MATCH_PRIMARY_MYSQL_TEST')!=='1')exit(0);
check(extension_loaded('pdo_mysql'),'real_mysql_driver_required');
const FIXTURE_DSN='mysql:host=127.0.0.1;port=33306;dbname=match_primary_fixture;charset=utf8mb4';
class FaultPDO extends PDO {
    public string $fault='';
    public bool $didCommit=false;
    public function commit(): bool {
        if($this->fault==='commit_before')throw new RuntimeException('simulated_commit_transport');
        $result=parent::commit();$this->didCommit=true;
        if($this->fault==='commit_after')throw new RuntimeException('simulated_commit_response_loss');
        return $result;
    }
    public function exec(string $statement): int|false {
        if($this->fault==='readback'&&$this->didCommit&&$statement==='START TRANSACTION READ ONLY')throw new RuntimeException('simulated_readback_failure');
        return parent::exec($statement);
    }
}
$db=new FaultPDO(FIXTURE_DSN,'root','match-fixture-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
check($db->query('SELECT DATABASE()')->fetchColumn()==='match_primary_fixture','dedicated_fixture_database');
function reset_db(PDO $db,array $rows,array $hotels): void {
    $db->exec('DROP TRIGGER IF EXISTS reject_second');
    foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
    $db->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace VARCHAR(64) NOT NULL,external_hotel_id VARCHAR(64) NOT NULL,local_hotel_id BIGINT NULL,decision_status VARCHAR(32) NOT NULL,catalog_sha256 CHAR(64) NOT NULL,evidence_sha256 CHAR(64) NOT NULL,evidence_json LONGTEXT NOT NULL,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE catalog_hotels (id BIGINT PRIMARY KEY,name VARCHAR(255),country_id INT,country_name VARCHAR(100),region_name VARCHAR(100),subregion_name VARCHAR(100),category INT,is_active INT,latitude DOUBLE NULL,longitude DOUBLE NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE tour_operator_identity_observations (hotel_id BIGINT PRIMARY KEY,last_seen_at DATETIME) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_search_mappings (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id BIGINT,match_class VARCHAR(64),scope VARCHAR(20),approval_policy VARCHAR(100),enabled INT,source_row_digest CHAR(64),mapping_digest CHAR(64)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_decisions (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id BIGINT NULL,decision_status VARCHAR(30)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INT,catalog_hotel_id BIGINT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
    foreach($rows as $row)$db->prepare('INSERT INTO andromeda_hotel_identities VALUES (?,?,?,?,?,?,?)')->execute(array_values($row));
    foreach($hotels as $hotel){$db->prepare('INSERT INTO catalog_hotels VALUES (?,?,?,?,?,?,?,?,?,?)')->execute(array_values($hotel));$db->prepare('INSERT INTO tour_operator_identity_observations VALUES (?,UTC_TIMESTAMP())')->execute([$hotel['id']]);}
    $db->exec("INSERT INTO anex_hotel_search_mappings VALUES (1001,42903,'exact','preview','owner_exact_and_strong_20260908',1,REPEAT('a',64),REPEAT('b',64))");
}
function run_write(FaultPDO $db,array $entries,string $suffix): array {
    $dir=scratch();$out=pm1_write($db,$entries,'int-andromeda-match-primary-fixture-'.$suffix.'-v1',str_repeat('a',40),$dir);
    $out['_commit_marker']=file_exists($dir.'/commit-attempt.json');erase($dir);return $out;
}
reset_db($db,$identities,$hotels);
$out=run_write($db,$entries,'success');
check($out['state']==='committed_readback_verified'&&$out['mapping_writes']===3,'three_committed');
check($out['effective_resolver_verified']===true&&count($out['rows'])===3,'actual_resolver_readback');
check($out['new_full_triples']===1&&$out['new_full_triple_targets']===[42903],'attributable_triple');
check($out['current_candidates_evaluated']===3&&$out['_commit_marker'],'count_and_durable_marker');
$out=run_write($db,$entries,'already');
check($out['mapping_writes']===0&&count($out['already'])===3,'no_duplicate_writes');
reset_db($db,$identities,$hotels);$db->exec("INSERT INTO anex_hotel_decisions VALUES (9999,16944,'rejected')");
$out=run_write($db,$entries,'mixed');
check($out['mapping_writes']===2&&count($out['held'])===1&&$out['held'][0]['local_hotel_id']===16944,'hold_does_not_block_independent');
check($db->query("SELECT decision_status FROM andromeda_hotel_identities WHERE external_hotel_id='2000034238'")->fetchColumn()==='pending','protected_row_preserved');
reset_db($db,$identities,$hotels);
$db->exec("CREATE TRIGGER reject_second BEFORE UPDATE ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.local_hotel_id=16944 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture_reject_second'; END IF; END");
$out=run_write($db,$entries,'rollback');
check($out['state']==='rolled_back_no_writes'&&$out['mapping_writes']===0,'whole_transaction_rollback');
check((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted'")->fetchColumn()===0,'first_update_rolled_back');
foreach(['commit_before','commit_after','readback'] as $fault){
    reset_db($db,$identities,$hotels);$db->fault=$fault;$db->didCommit=false;
    $out=run_write($db,$entries,str_replace('_','-',$fault));$db->fault='';
    if($fault==='readback')check($out['state']==='committed_readback_unconfirmed'&&$out['mapping_writes']===3&&!$out['readback_verified'],'committed_not_falsely_verified');
    else check($out['state']==='commit_outcome_unknown_no_replay'&&$out['mapping_writes']===null&&!$out['readback_verified'],'commit_unknown_not_zero_'.$fault);
    $actual=(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted'")->fetchColumn();
    check($actual===($fault==='commit_before'?0:3),'independent_fixture_outcome_'.$fault);
}
echo 'PRIMARY_MYSQL_PASS '.$checks."\n";
