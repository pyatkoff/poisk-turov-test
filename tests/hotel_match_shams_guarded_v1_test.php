<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_shams_guarded_v1.php';
$checks=0;
function shgcheck(bool $ok,string $name): void {global $checks;++$checks;if(!$ok)throw new RuntimeException('shams_guarded_test:'.$name);}

$source=['id'=>'9501','name'=>'SHAMS SAFAGA','state'=>'Египет','town'=>'Шамс Сафага'];
$raw=w76_json(['source'=>$source,'reason'=>'native_review']);
$row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'9501','local_hotel_id'=>null,'decision_status'=>'pending',
    'catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$hotel=['id'=>420,'name'=>'SHAMS SAFAGA','country_id'=>5,'country_name'=>'Египет','region_name'=>'Марса Алам',
    'subregion_name'=>null,'category'=>4,'is_active'=>1,'latitude'=>24.6907006,'longitude'=>35.0835745];
$entry=['id'=>420,'catalog_id'=>'9501','prior'=>array_diff_key($row,['evidence_json'=>true]),'history_sha256'=>w76_hash($source),
    'target'=>$hotel,'operator_facts'=>[['namespace'=>'operator_5','native_id'=>'835'],['namespace'=>'operator_342','native_id'=>'24402']],
    'proofs'=>[['namespace'=>'operator_342','native_id'=>'24402','samo'=>['raw_verified'=>true],'tv'=>[['state'=>'saved_tv_proof_verified']]]],
    'geo_source'=>['town'=>'Марса Алам','townKey'=>'5821','namespace'=>'operator_342','native_id'=>'24402'],
    'geo_receipt_sha256'=>str_repeat('b',64)];
$context=pm1_context([$row]);$context['hotels']=[420=>$hotel];
shgcheck(ng110_classify($entry,$context)['reasons']===['geography_requires_review'],'old_history_still_holds');
shgcheck(shg_classify($entry,$context)===['status'=>'ready','reasons'=>[]],'independent_concrete_geo_admits');

$c=$context;$c['hotels'][420]['region_name']='Хургада';$entryDrift=$entry;$entryDrift['target']=$c['hotels'][420];
shgcheck(shg_classify($entryDrift,$c)['status']==='hold','current_target_geo_drift_holds');
$c=$context;$c['manual'][420]=true;shgcheck(in_array('protected_target',shg_classify($entry,$c)['reasons'],true),'manual_holds');
$c=$context;$c['exclusions'][420]=true;shgcheck(in_array('protected_target',shg_classify($entry,$c)['reasons'],true),'exclusion_holds');
$c=$context;$c['targets'][420][]=['external_hotel_id'=>'other'];shgcheck(in_array('target_catalog_occupied',shg_classify($entry,$c)['reasons'],true),'occupied_holds');
$c=$context;$c['sources']['9501'][0]['decision_status']='accepted';shgcheck(in_array('source_not_pending_null',shg_classify($entry,$c)['reasons'],true),'accepted_not_replaced');
$c=$context;$c['hotels'][420]['is_active']=0;shgcheck(in_array('target_missing_or_inactive',shg_classify($entry,$c)['reasons'],true),'inactive_holds');
$c=$context;$c['hotels'][420]['country_name']='Турция';$entryCountry=$entry;$entryCountry['target']=$c['hotels'][420];
shgcheck(in_array('country_conflict',shg_classify($entryCountry,$c)['reasons'],true),'country_holds');
$c=$context;$h=json_decode($c['sources']['9501'][0]['evidence_json'],true);$h['source']['latitude']=1;$h['source']['longitude']=1;
$r=&$c['sources']['9501'][0];$r['evidence_json']=w76_json($h);$r['evidence_sha256']=hash('sha256',$r['evidence_json']);
$entryCoordinates=$entry;$entryCoordinates['prior']['evidence_sha256']=$r['evidence_sha256'];$entryCoordinates['history_sha256']=w76_hash($h['source']);
shgcheck(in_array('coordinate_conflict_over_5km',shg_classify($entryCoordinates,$c)['reasons'],true),'over_5km_never_removed');
shgcheck(SHG_OP!==(defined('NG110_OP')?NG110_OP:''),'new_operation_not_old_writer');
echo 'SHAMS_GUARDED_PURE_PASS '.$checks."\n";
if(getenv('MATCH_SHAMS_MYSQL_TEST')!=='1')exit(0);

$db=new PDO('mysql:host=127.0.0.1;port=33306;dbname=match_primary_fixture;charset=utf8mb4','root','match-fixture-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
shgcheck($db->query('SELECT DATABASE()')->fetchColumn()==='match_primary_fixture','fixture_database_only');
function shgreset(PDO $db,array $row,array $hotel): void {
    foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
    $db->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace VARCHAR(64) NOT NULL,external_hotel_id VARCHAR(64) NOT NULL,local_hotel_id BIGINT NULL,decision_status VARCHAR(32) NOT NULL,catalog_sha256 CHAR(64) NOT NULL,evidence_sha256 CHAR(64) NOT NULL,evidence_json LONGTEXT NOT NULL,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE catalog_hotels (id BIGINT PRIMARY KEY,name VARCHAR(255),country_id INT,country_name VARCHAR(100),region_name VARCHAR(100),subregion_name VARCHAR(100),category INT,is_active INT,latitude DOUBLE NULL,longitude DOUBLE NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE tour_operator_identity_observations (hotel_id BIGINT PRIMARY KEY,last_seen_at DATETIME) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_search_mappings (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id BIGINT,match_class VARCHAR(64),scope VARCHAR(20),approval_policy VARCHAR(100),enabled INT,source_row_digest CHAR(64),mapping_digest CHAR(64)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_decisions (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id BIGINT NULL,decision_status VARCHAR(30)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INT,catalog_hotel_id BIGINT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
    $db->prepare('INSERT INTO andromeda_hotel_identities VALUES (?,?,?,?,?,?,?)')->execute(array_values($row));
    $db->prepare('INSERT INTO catalog_hotels VALUES (?,?,?,?,?,?,?,?,?,?)')->execute(array_values($hotel));
    $db->prepare('INSERT INTO tour_operator_identity_observations VALUES (?,UTC_TIMESTAMP())')->execute([$hotel['id']]);
}
function shgrun(PDO $db,array $entry): array {
    $dir=sys_get_temp_dir().'/shams-write-'.bin2hex(random_bytes(8));mkdir($dir,0700);
    try{return shg_write($db,[420=>$entry],str_repeat('a',40),$dir);}
    finally{foreach(new DirectoryIterator($dir) as $f)if(!$f->isDot())unlink($f->getPathname());rmdir($dir);}
}
shgreset($db,$row,$hotel);$out=shgrun($db,$entry);
shgcheck($out['state']==='committed_readback_verified'&&$out['mapping_writes']===1&&count($out['rows'])===1,'one_real_commit');
shgcheck($out['effective_resolver_verified']&&$out['prior_evidence_preserved']&&$out['unrelated_identities_unchanged'],'post_commit_guards');
$out=shgrun($db,$entry);shgcheck($out['state']==='completed_no_new_writes'&&$out['mapping_writes']===0,'accepted_not_replaced_mysql');
shgreset($db,$row,$hotel);$other=$row;$other['external_hotel_id']='other';$other['local_hotel_id']=420;$other['decision_status']='accepted';
$db->prepare('INSERT INTO andromeda_hotel_identities VALUES (?,?,?,?,?,?,?)')->execute(array_values($other));
$out=shgrun($db,$entry);shgcheck($out['state']==='completed_no_new_writes'&&$out['mapping_writes']===0
    &&in_array('target_catalog_occupied',$out['held'][0]['reasons'],true),'occupied_target_not_replaced_mysql');
echo 'SHAMS_GUARDED_MYSQL_PASS '.$checks."\n";
