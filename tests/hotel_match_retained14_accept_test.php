<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_retained14_accept.php';
$root=$argv[1]??'';$mode=$argv[2]??'pure';$checks=0;
function ck(bool $ok,string $why):void {global $checks;v66_need($ok,'test_'.$why);$checks++;}
function rejects(callable $fn,string $why):void {try{$fn();}catch(Throwable){ck(true,$why);return;}ck(false,$why);}
$raw=[];foreach(['source','plan','audit','audit_receipt'] as $name)$raw[$name]=(string)file_get_contents($root.'/'.$name.'.json');
$scope=mra_inputs(...array_values($raw));
ck(count($scope['selected'])===14&&count($scope['excluded'])===15,'real_scope');
ck(array_keys($scope['selected'])===array_keys(MRA_PAIRS),'whitelist_order');
foreach($scope['selected'] as $id=>$entry){ck(MRA_PAIRS[$id]===v66_id($entry['candidate']['catalog_id']),'pair_'.$id);ck(count($entry['retained_proofs'])>=1&&$entry['historical_review_reasons']===[],'proof_'.$id);}
foreach($scope['excluded'] as $id=>$cat)ck(!isset(MRA_PAIRS[$id]),'exclude_'.$id);
foreach(array_keys($raw) as $name){$bad=$raw;$bad[$name].=' ';rejects(fn()=>mra_inputs(...array_values($bad)),'tamper_'.$name);}
$bad=$raw;$a=json_decode($bad['audit'],true,128,JSON_THROW_ON_ERROR);$a['rows'][0]['andromeda_catalog_id']='99999';$bad['audit']=v66_json($a);rejects(fn()=>mra_inputs(...array_values($bad)),'substitute_id');
ck(!str_contains(file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_retained14_accept.php'),'ON DUPLICATE KEY'),'no_upsert');
if($mode==='pure'){echo v66_json(['state'=>'pure_tests_passed','checks'=>$checks,'application_database_writes'=>0,'provider_http_calls'=>0])."\n";exit;}
ck($mode==='mysql','mysql_mode');
$dsn=(string)getenv('MATCH_TEST_DSN');ck($dsn==='mysql:host=127.0.0.1;port=3306;dbname=match_retained14_test;charset=utf8mb4','isolated_dsn');
$db=new PDO($dsn,'root','match_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tables=['andromeda_hotel_identities','tour_operator_identity_observations','anex_hotel_decisions','anex_review_pair_exclusions','anex_hotel_search_mappings','catalog_hotels'];
function rowCountM(PDO $db):int{return (int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ('".implode("','",MRA_PAIRS)."')")->fetchColumn();}
function seedM(PDO $db,array $scope):void {
    global $tables;
    foreach($tables as $t)$db->exec('DELETE FROM '.$t);
    $h=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,1,?,1,?,1,?,?,1)');
    $map=$db->prepare("INSERT INTO anex_hotel_search_mappings VALUES(?,?,'exact','owner_exact_and_strong_20260908',1,'preview')");
    $live=$db->prepare('INSERT INTO tour_operator_identity_observations VALUES(?,?)');
    foreach($scope['selected'] as $id=>$e){$h->execute([$id,$e['dossier']['hotel_name'],'test country','test region','test resort','5']);$live->execute([$id,gmdate('Y-m-d H:i:s')]);foreach($e['dossier']['direct_anex_ids'] as $native)$map->execute([(int)$native,$id]);}
    $ev=v66_json(['test'=>'protected_sentinel']);$q=$db->prepare('INSERT INTO andromeda_hotel_identities VALUES(?,?,?,?,?,?,?)');
    $q->execute(['other_provider','999',null,'rejected',V66_SOURCE_SHA,hash('sha256',$ev),$ev]);
    foreach($scope['excluded'] as $id=>$cat)$q->execute(['andromeda_catalog',$cat,null,'conflict',V66_SOURCE_SHA,hash('sha256',$ev),$ev]);
}
function runM(PDO $db,array $scope):array {
    $dir=sys_get_temp_dir().'/match-accept-test-'.bin2hex(random_bytes(8));mkdir($dir,0700);
    try{return mra_write($db,$scope,str_repeat('c',40),$dir);}finally{foreach(glob($dir.'/*')?:[] as $p)unlink($p);rmdir($dir);}
}
function protectedM(PDO $db,array $scope):array {
    $all=v66_query($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');
    return mra_hashes(array_values(array_filter($all,fn($r)=>$r['supplier_namespace']!=='andromeda_catalog'||!in_array($r['external_hotel_id'],MRA_PAIRS,true))));
}
class CommitUnknownM extends PDO {
    public function commit():bool {parent::commit();throw new PDOException('simulated_transport_loss');}
}
class PostReadFailM extends PDO {
    private bool $done=false;
    public function commit():bool {$ok=parent::commit();$this->done=true;return $ok;}
    public function prepare(string $query,array $options=[]):PDOStatement|false {if($this->done)throw new PDOException('simulated_readback_loss');return parent::prepare($query,$options);}
}
try {
    foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
    $db->exec('CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name VARCHAR(500),country_id INT,country_name VARCHAR(100),region_id INT,region_name VARCHAR(100),subregion_id INT,subregion_name VARCHAR(100),category VARCHAR(20),is_active INT) ENGINE=InnoDB CHARACTER SET utf8mb4');
    $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,PRIMARY KEY(supplier_namespace,external_hotel_id),KEY(local_hotel_id)) ENGINE=InnoDB CHARACTER SET utf8mb4');
    $db->exec('CREATE TABLE tour_operator_identity_observations(hotel_id INT PRIMARY KEY,last_seen_at DATETIME) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,decision_status VARCHAR(16),catalog_hotel_id INT,KEY(catalog_hotel_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(32),approval_policy VARCHAR(64),enabled INT,scope VARCHAR(16)) ENGINE=InnoDB');
    seedM($db,$scope);$preserved=protectedM($db,$scope);$r=runM($db,$scope);
    ck($r['state']==='committed_verified'&&$r['database_writes']===14&&$r['inserted']===14,'real_insert14_'.v66_json($r));
    ck($r['readback_verified']===true&&count($r['rows'])===14&&rowCountM($db)===14,'postcommit14');
    ck(protectedM($db,$scope)===$preserved,'all15_hold_plus_other_preserved');
    foreach($r['rows'] as $row)ck($row['runtime_resolves']===true&&MRA_PAIRS[$row['local_hotel_id']]===$row['andromeda_catalog_id'],'runtime_'.$row['local_hotel_id']);
    $again=runM($db,$scope);ck($again['state']==='committed_verified'&&$again['inserted']===0&&$again['already_resolved_count']===14,'same_rows_not_overwritten');
    $id=array_key_first(MRA_PAIRS);$cat=MRA_PAIRS[$id];$entry=$scope['selected'][$id];$native=(int)$entry['dossier']['direct_anex_ids'][0];
    $cases=[
        'inactive'=>"UPDATE catalog_hotels SET is_active=0 WHERE id=$id",
        'name_drift'=>"UPDATE catalog_hotels SET name='changed identity' WHERE id=$id",
        'excluded_country'=>"UPDATE catalog_hotels SET country_name='Россия' WHERE id=$id",
        'not_live'=>"UPDATE tour_operator_identity_observations SET last_seen_at='2000-01-01' WHERE hotel_id=$id",
        'manual'=>"INSERT INTO anex_hotel_decisions VALUES($native,'accepted',$id)",
        'anex_exclusion'=>"INSERT INTO anex_review_pair_exclusions VALUES($native,$id)",
        'source_pending'=>"INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','$cat',NULL,'pending',REPEAT('a',64),REPEAT('b',64),'{}')",
        'target_occupied'=>"INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','777777',$id,'accepted',REPEAT('a',64),SHA2('{\"test\":1}',256),'{\"test\":1}')",
        'operator_conflict'=>"INSERT INTO andromeda_hotel_identities VALUES('operator_315','303225',11748,'accepted',REPEAT('a',64),SHA2('{\"test\":1}',256),'{\"test\":1}')",
        'operator_protected'=>"INSERT INTO andromeda_hotel_identities VALUES('operator_315','303225',NULL,'rejected',REPEAT('a',64),REPEAT('b',64),'{}')",
        'operator_bad_evidence'=>"INSERT INTO andromeda_hotel_identities VALUES('operator_315','303225',$id,'accepted',REPEAT('a',64),REPEAT('b',64),'{}')",
    ];
    foreach($cases as $name=>$sql){seedM($db,$scope);$db->exec($sql);$pre=protectedM($db,$scope);$out=runM($db,$scope);ck($out['state']==='committed_verified'&&$out['inserted']===13&&$out['current_hold_count']===1,'guard_'.$name.'_'.v66_json($out));ck(protectedM($db,$scope)===$pre,'preservation_'.$name);}
    seedM($db,$scope);
    $db->exec("CREATE TRIGGER match_fail_last BEFORE INSERT ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.external_hotel_id='2000106853' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='late_failure'; END IF; END");
    $pre=protectedM($db,$scope);$failed=runM($db,$scope);
    ck($failed['state']==='rolled_back_no_write'&&$failed['database_writes']===0&&rowCountM($db)===0,'late_failure_atomic_rollback');ck(protectedM($db,$scope)===$pre,'late_failure_preservation');$db->exec('DROP TRIGGER match_fail_last');
    seedM($db,$scope);$db->exec('ALTER TABLE andromeda_hotel_identities ENGINE=MyISAM');$failed=runM($db,$scope);
    ck($failed['state']==='rolled_back_no_write'&&$failed['reason']==='nontransactional_table'&&rowCountM($db)===0,'nontransactional_refused');$db->exec('ALTER TABLE andromeda_hotel_identities ENGINE=InnoDB');
    seedM($db,$scope);$fault=new CommitUnknownM($dsn,'root','match_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$unknown=runM($fault,$scope);$fault=null;
    ck($unknown['state']==='commit_unknown_no_replay'&&$unknown['database_writes']===null&&$unknown['no_replay']===true&&rowCountM($db)===14,'ambiguous_commit_never_report_zero');
    seedM($db,$scope);$fault=new PostReadFailM($dsn,'root','match_test_only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$failed=runM($fault,$scope);$fault=null;
    ck($failed['state']==='committed_readback_failed_no_replay'&&$failed['database_writes']===14&&$failed['readback_verified']===false&&rowCountM($db)===14,'postcommit_failure_honest');
    echo v66_json(['state'=>'mysql_tests_passed','checks'=>$checks,'real_rows'=>14,'current_conflict_cases'=>count($cases),'application_database_writes'=>0,'provider_http_calls'=>0])."\n";
}finally{if($db->inTransaction())$db->rollBack();foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);}
