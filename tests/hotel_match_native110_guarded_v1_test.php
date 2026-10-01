<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_native110_guarded_v1.php';
$checks=0;
function ngcheck(bool $ok,string $name): void {global $checks;++$checks;if(!$ok)throw new RuntimeException('guarded_test:'.$name);}
function ngfixture(): array {
    $entries=[];$rows=[];$hotels=[];
    foreach(NG110_PAIRS as $id=>$cat){
        $source=['id'=>$cat,'name'=>'Different display alias','state'=>'Fixture country','town'=>'Fixture town','starKey'=>114];
        $raw=w76_json(['source'=>$source,'reason'=>'native_review','candidate_ids'=>[]]);
        $row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$cat,'local_hotel_id'=>null,
            'decision_status'=>'pending','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
        $rows[]=$row;$hotel=['id'=>$id,'name'=>'Verified independent target','country_id'=>99,'country_name'=>'Fixture country',
            'region_name'=>'Fixture town','subregion_name'=>'Fixture suburb','category'=>4,'is_active'=>1,'latitude'=>null,'longitude'=>null];
        $hotels[$id]=$hotel;$entries[$id]=['id'=>$id,'catalog_id'=>$cat,'prior'=>array_diff_key($row,['evidence_json'=>true]),
            'history_sha256'=>w76_hash($source),'target'=>$hotel,'operator_facts'=>[['namespace'=>'operator_315','native_id'=>(string)($id+100)]],
            'proofs'=>[['namespace'=>'operator_315','native_id'=>(string)($id+100),'samo'=>['raw_verified'=>true],'tv'=>[['state'=>'saved_tv_proof_verified']]]]];
    }
    $context=pm1_context($rows);$context['hotels']=$hotels;return [$entries,$rows,$hotels,$context];
}
[$entries,$rows,$hotels,$context]=ngfixture();
foreach($entries as $id=>$entry)ngcheck(ng110_classify($entry,$context)===['status'=>'ready','reasons'=>[]],'proven_native_policy_'.$id);
ngcheck(isset($entries[28529]),'new_ketenci_is_in_mass_intake');
$mutations=[
    'source_accepted'=>fn(&$e,&$c)=>$c['sources']['3126'][0]['decision_status']='accepted',
    'source_conflict'=>fn(&$e,&$c)=>$c['sources']['3126'][0]['decision_status']='conflict',
    'source_drift'=>fn(&$e,&$c)=>$e['prior']['evidence_sha256']=str_repeat('b',64),
    'source_history_drift'=>fn(&$e,&$c)=>$e['history_sha256']=str_repeat('b',64),
    'target_inactive'=>fn(&$e,&$c)=>$c['hotels'][42903]['is_active']=0,
    'target_drift'=>fn(&$e,&$c)=>$c['hotels'][42903]['country_name']='Different country',
    'manual'=>fn(&$e,&$c)=>$c['manual'][42903]=true,
    'exclusion'=>fn(&$e,&$c)=>$c['exclusions'][42903]=true,
    'occupied'=>fn(&$e,&$c)=>$c['targets'][42903][]=['external_hotel_id'=>'other'],
    'proof_missing'=>fn(&$e,&$c)=>$e['proofs']=[],
    'geography_unknown'=>function(&$e,&$c){$c['hotels'][42903]['region_name']='Other';$e['target']=$c['hotels'][42903];},
    'source_manual'=>function(&$e,&$c){$r=&$c['sources']['3126'][0];$h=json_decode($r['evidence_json'],true);$h['manual']=true;$r['evidence_json']=w76_json($h);$r['evidence_sha256']=hash('sha256',$r['evidence_json']);$e['prior']['evidence_sha256']=$r['evidence_sha256'];},
    'operator_other'=>function(&$e,&$c){$r=$c['sources']['3126'][0];$r['local_hotel_id']=999;$r['decision_status']='accepted';$c['operators']['operator_315']['43003']=[$r];},
    'operator_protected'=>function(&$e,&$c){$r=$c['sources']['3126'][0];$r['decision_status']='rejected';$c['operators']['operator_315']['43003']=[$r];},
    'operator_multiple'=>function(&$e,&$c){$r=$c['sources']['3126'][0];$c['operators']['operator_315']['43003']=[$r,$r];},
];
foreach($mutations as $name=>$mutate){$e=$entries[42903];$c=$context;$mutate($e,$c);ngcheck(ng110_classify($e,$c)['status']==='hold',$name);}
ngcheck(ng110_classify(['catalog_id'=>NC110_PROTECTED,'id'=>144804],[])['reasons']===['protected_source'],'protected_short_circuit');
$e=$entries[42903];$c=$context;$h=json_decode($c['sources']['3126'][0]['evidence_json'],true);
$h['source']['latitude']=1;$h['source']['longitude']=1;$r=&$c['sources']['3126'][0];$r['evidence_json']=w76_json($h);$r['evidence_sha256']=hash('sha256',$r['evidence_json']);
$e['prior']['evidence_sha256']=$r['evidence_sha256'];$e['history_sha256']=w76_hash($h['source']);
$c['hotels'][42903]['latitude']=5;$c['hotels'][42903]['longitude']=5;$e['target']=$c['hotels'][42903];
ngcheck(in_array('coordinate_conflict_over_5km',ng110_classify($e,$c)['reasons'],true),'coordinate_conflict_cannot_be_overridden_by_town');
echo 'NATIVE110_GUARDED_PURE_PASS '.$checks."\n";
if(getenv('MATCH_NATIVE110_MYSQL_TEST')!=='1')exit(0);
class NGFaultPDO extends PDO {
    public string $fault='';public bool $didCommit=false;
    public function commit(): bool {
        if($this->fault==='commit_before')throw new RuntimeException('fixture_commit_before');
        $out=parent::commit();$this->didCommit=true;
        if($this->fault==='commit_after')throw new RuntimeException('fixture_commit_after');return $out;
    }
    public function exec(string $sql): int|false {
        if($this->fault==='readback'&&$this->didCommit&&$sql==='START TRANSACTION READ ONLY')throw new RuntimeException('fixture_readback');
        return parent::exec($sql);
    }
}
$db=new NGFaultPDO('mysql:host=127.0.0.1;port=33306;dbname=match_primary_fixture;charset=utf8mb4','root','match-fixture-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
ngcheck($db->query('SELECT DATABASE()')->fetchColumn()==='match_primary_fixture','fixture_database_only');
function ngreset(PDO $db,array $rows,array $hotels): void {
    $db->exec('DROP TRIGGER IF EXISTS reject_second');
    foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
    $db->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace VARCHAR(64) NOT NULL,external_hotel_id VARCHAR(64) NOT NULL,local_hotel_id BIGINT NULL,decision_status VARCHAR(32) NOT NULL,catalog_sha256 CHAR(64) NOT NULL,evidence_sha256 CHAR(64) NOT NULL,evidence_json LONGTEXT NOT NULL,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE catalog_hotels (id BIGINT PRIMARY KEY,name VARCHAR(255),country_id INT,country_name VARCHAR(100),region_name VARCHAR(100),subregion_name VARCHAR(100),category INT,is_active INT,latitude DOUBLE NULL,longitude DOUBLE NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE tour_operator_identity_observations (hotel_id BIGINT PRIMARY KEY,last_seen_at DATETIME) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_search_mappings (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id BIGINT,match_class VARCHAR(64),scope VARCHAR(20),approval_policy VARCHAR(100),enabled INT,source_row_digest CHAR(64),mapping_digest CHAR(64)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_decisions (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id BIGINT NULL,decision_status VARCHAR(30)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INT,catalog_hotel_id BIGINT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
    foreach($rows as $r)$db->prepare('INSERT INTO andromeda_hotel_identities VALUES (?,?,?,?,?,?,?)')->execute(array_values($r));
    foreach($hotels as $h){$db->prepare('INSERT INTO catalog_hotels VALUES (?,?,?,?,?,?,?,?,?,?)')->execute(array_values($h));$db->prepare('INSERT INTO tour_operator_identity_observations VALUES (?,UTC_TIMESTAMP())')->execute([$h['id']]);}
    $db->exec("INSERT INTO anex_hotel_search_mappings VALUES (1001,42903,'exact','preview','owner_exact_and_strong_20260908',1,REPEAT('a',64),REPEAT('b',64))");
}
function ng_run(NGFaultPDO $db,array $entries): array {
    $dir=sys_get_temp_dir().'/native110-write-'.bin2hex(random_bytes(8));mkdir($dir,0700);
    $out=ng110_write($db,$entries,str_repeat('a',40),$dir);$out['checkpoint_present']=is_file($dir.'/pre-commit.json')&&is_file($dir.'/commit-attempt.json');
    foreach(new DirectoryIterator($dir) as $f)if(!$f->isDot())unlink($f->getPathname());rmdir($dir);return $out;
}
ngreset($db,$rows,$hotels);$out=ng_run($db,$entries);
ngcheck($out['state']==='committed_readback_verified'&&$out['mapping_writes']===4&&count($out['rows'])===4,'four_real_commits');
ngcheck($out['effective_resolver_verified']&&$out['checkpoint_present']&&$out['new_full_triples']===1,'durable_checkpoint_resolver_and_triple');
$out=ng_run($db,$entries);ngcheck($out['state']==='completed_no_new_writes'&&$out['mapping_writes']===0&&count($out['held'])===4,'accepted_not_replaced');
ngreset($db,$rows,$hotels);$db->exec("INSERT INTO anex_hotel_decisions VALUES (9999,16944,'rejected')");$out=ng_run($db,$entries);
ngcheck($out['mapping_writes']===3&&count($out['held'])===1&&$out['held'][0]['catalog_id']==='2000034238','one_hold_does_not_block_three');
ngreset($db,$rows,$hotels);$db->exec("CREATE TRIGGER reject_second BEFORE UPDATE ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.local_hotel_id=16944 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture_reject_second'; END IF; END");
$out=ng_run($db,$entries);ngcheck($out['state']==='rolled_back_no_writes'&&$out['mapping_writes']===0,'transaction_atomic_rollback');
ngcheck((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted'")->fetchColumn()===0,'rollback_preserves_all_sources');
foreach(['commit_before','commit_after','readback'] as $fault){
    ngreset($db,$rows,$hotels);$db->fault=$fault;$db->didCommit=false;$out=ng_run($db,$entries);$db->fault='';
    ngcheck(!$out['readback_verified']&&$out['checkpoint_present'],'unknown_or_unverified_not_done_'.$fault);
    if($fault==='readback')ngcheck($out['state']==='committed_readback_unconfirmed'&&$out['mapping_writes']===4,'committed_not_falsely_verified');
    else ngcheck($out['state']==='commit_outcome_unknown_no_replay'&&$out['mapping_writes']===null,'commit_unknown_not_zero_'.$fault);
    ngcheck((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted'")->fetchColumn()===($fault==='commit_before'?0:4),'independent_outcome_'.$fault);
}
echo 'NATIVE110_GUARDED_MYSQL_PASS '.$checks."\n";
