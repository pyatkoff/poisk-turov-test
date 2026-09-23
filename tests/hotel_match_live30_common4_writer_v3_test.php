<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live30_common4_writer_v3.php';

$dsn=getenv('MATCH_C4W_TEST_DSN');
if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=match_c4w;charset=utf8mb4')throw new RuntimeException('isolated_mysql_required');
$db=new PDO($dsn,'root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function c4wok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function c4wtarget(int $tv):array{return ['id'=>$tv,'name'=>'HOTEL '.$tv,'country_id'=>4,'country_name'=>'Турция','region_id'=>20,'region_name'=>'Сиде','subregion_id'=>200,'subregion_name'=>'Сиде','category'=>'5','is_active'=>1];}
function c4wanchor(int $tv):array{
    $ej=hmc4w_json(['source'=>'canonical','tv'=>$tv]);return ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(700000+$tv),
        'local_hotel_id'=>$tv,'decision_status'=>'accepted','catalog_sha256'=>hash('sha256','catalog-'.$tv),'evidence_sha256'=>hash('sha256',$ej),'evidence_json'=>$ej];
}
function c4wmanifest():array{
    $rows=[];
    for($i=1;$i<=HMC4W_EXPECTED_ROWS;$i++){
        $tv=1000+$i;$ns=$i<=100?'bgoperator':($i<=140?'operator_315':'operator_342');$op=HMC4W_ALLOWED_NS[$ns];$a=c4wanchor($tv);
        $rows[]=['supplier_namespace'=>$ns,'external_hotel_id'=>(string)(800000000+$i),'tv_hotel_id'=>$tv,'operator_id'=>$op,
            'source_operation'=>'hotel-match-live30-common4-acquire-1971-20260923-o0-n100-v1','source_result_sha256'=>str_repeat('a',64),
            'batch'=>1,'search_id_sha256'=>str_repeat('b',64),'tour_id_sha256'=>str_repeat('c',64),'operator_link_sha256'=>str_repeat('d',64),
            'target'=>c4wtarget($tv),'unanimous_catalog_sha256'=>$a['catalog_sha256'],'anchors'=>hmc4w_anchor_list([$a])];
    }
    return $rows;
}
function c4waudit():array{
    $rows=[];foreach(c4wmanifest() as $m)$rows[]=$m+['writer_ready'=>true,'status'=>'current_missing_edge','anchor_state'=>'canonical_anchor_ok','safe_to_write_now'=>false,'catalog_hotel'=>$m['target']];
    return ['operation'=>HMC4W_AUDIT_OP,'state'=>'completed_read_only_current_audit','writer_ready_count'=>HMC4W_EXPECTED_ROWS,
        'input_single_native_count'=>283,'input_namespace_counts'=>['bgoperator'=>205,'operator_315'=>50,'operator_342'=>28],'rows'=>$rows];
}
function c4wseed(PDO $db):void{
    $db->exec('DELETE FROM andromeda_hotel_identities');$db->exec('DELETE FROM anex_hotel_decisions');$db->exec('DELETE FROM catalog_hotels');
    $h=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,?,?,?,?,?,1)');$a=$db->prepare('INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,?,?,?,?)');
    for($i=1;$i<=HMC4W_EXPECTED_ROWS;$i++){$tv=1000+$i;$t=c4wtarget($tv);$h->execute([$t['id'],$t['name'],$t['country_id'],$t['country_name'],$t['region_id'],$t['region_name'],$t['subregion_id'],$t['subregion_name'],$t['category']]);$x=c4wanchor($tv);$a->execute([$x['supplier_namespace'],$x['external_hotel_id'],$x['local_hotel_id'],$x['decision_status'],$x['catalog_sha256'],$x['evidence_sha256'],$x['evidence_json']]);}
    $a->execute(['other_provider','777',1001,'accepted',str_repeat('e',64),str_repeat('f',64),'{}']);
}
$tables=['anex_hotel_decisions','andromeda_hotel_identities','catalog_hotels'];
try{
    foreach($tables as $t)$db->exec("DROP TABLE IF EXISTS $t");
    $db->exec("CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name VARCHAR(500),country_id INT,country_name VARCHAR(100),region_id INT NULL,region_name VARCHAR(200),subregion_id INT NULL,subregion_name VARCHAR(200) NULL,category VARCHAR(20),is_active TINYINT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(supplier_namespace,external_hotel_id),KEY(local_hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE anex_hotel_decisions(anex_hotel_id BIGINT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(32)) ENGINE=InnoDB");

    $manifest=hmc4w_manifest(c4waudit());c4wok(count($manifest)===172,'manifest172');c4wseed($db);
    $r=hmc4w_write($db,$manifest,str_repeat('1',40),null);
    c4wok($r['state']==='committed_verified'&&$r['inserted']===172&&array_sum($r['inserted_by_namespace'])===172&&$r['readback_verified']===true,'commit172');
    c4wok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===172,'insert_count');

    $replay=false;try{hmc4w_write($db,$manifest,str_repeat('1',40),null);}catch(RuntimeException $e){$replay=str_contains($e->getMessage(),'source_key_now_present');}c4wok($replay,'replay_blocked');

    c4wseed($db);$db->exec("UPDATE catalog_hotels SET name='DRIFT' WHERE id=1001");
    $drift=false;try{hmc4w_write($db,$manifest,str_repeat('1',40),null);}catch(RuntimeException $e){$drift=str_contains($e->getMessage(),'target_facts_drift');}
    c4wok($drift&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'target_drift_rollback');

    c4wseed($db);$db->exec("UPDATE andromeda_hotel_identities SET evidence_sha256=REPEAT('f',64) WHERE supplier_namespace='andromeda_catalog' AND local_hotel_id=1001");
    $anchor=false;try{hmc4w_write($db,$manifest,str_repeat('1',40),null);}catch(RuntimeException $e){$anchor=str_contains($e->getMessage(),'anchor_evidence_hash_drift');}
    c4wok($anchor&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'anchor_drift_rollback');

    c4wseed($db);$db->exec("INSERT INTO anex_hotel_decisions VALUES(1,1001,'rejected')");
    $manual=false;try{hmc4w_write($db,$manifest,str_repeat('1',40),null);}catch(RuntimeException $e){$manual=str_contains($e->getMessage(),'manual_target_protected');}
    c4wok($manual&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'manual_rollback');

    c4wseed($db);$last=(string)(800000000+HMC4W_EXPECTED_ROWS);
    $db->exec("CREATE TRIGGER reject_c4w_last BEFORE INSERT ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.external_hotel_id='$last' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late failure'; END IF; END");
    $late=false;try{hmc4w_write($db,$manifest,str_repeat('1',40),null);}catch(RuntimeException $e){$late=str_starts_with($e->getMessage(),'rolled_back_no_write:');}
    c4wok($late&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'late_failure_atomic');$db->exec('DROP TRIGGER reject_c4w_last');

    echo json_encode(['status'=>'passed','rows'=>172,'supplier_calls'=>0,'production_database_writes'=>0],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if($db->inTransaction())$db->rollBack();foreach($tables as $t)$db->exec("DROP TABLE IF EXISTS $t");
}
