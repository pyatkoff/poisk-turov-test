<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_v16_bgoperator_writer_v17.php';

$dsn=getenv('MATCH_V17_TEST_DSN');
if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=match_v17;charset=utf8mb4')throw new RuntimeException('isolated_mysql_required');
$db=new PDO($dsn,'root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

function v17ok(bool $yes,string $m):void{if(!$yes)throw new RuntimeException($m);}
function v17manifest():array{
    $rows=[];
    for($i=1;$i<=HM17_EXPECTED_ROWS;$i++){
        $tv=1000+$i;$ext=(string)(100000000000+$i);
        $rows[]=[
            'supplier_namespace'=>'bgoperator','external_hotel_id'=>$ext,'tv_hotel_id'=>$tv,
            'operator'=>'biblio','operator_id'=>18,'batch'=>1,'search_id'=>'s1','tour_id'=>'t'.$i,
            'operator_link_sha256'=>hash('sha256','link'.$i),
            'target'=>['id'=>$tv,'name'=>'HOTEL '.$i,'country_id'=>4,'country_name'=>'Турция',
                'region_id'=>20,'region_name'=>'Сиде','subregion_id'=>200,'subregion_name'=>'Сиде',
                'category'=>'5','is_active'=>1],
        ];
    }
    return $rows;
}
function v17seed(PDO $db):void{
    $db->exec('DELETE FROM andromeda_hotel_identities');$db->exec('DELETE FROM anex_hotel_decisions');$db->exec('DELETE FROM catalog_hotels');
    $h=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,?,?,?,?,?,1)');
    $a=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES ('andromeda_catalog',?,?,'accepted',?,?,?)");
    for($i=1;$i<=HM17_EXPECTED_ROWS;$i++){
        $tv=1000+$i;$h->execute([$tv,'HOTEL '.$i,4,'Турция',20,'Сиде',200,'Сиде','5']);
        $ej=hm17_json(['source'=>'canonical','i'=>$i]);$a->execute([(string)(900000+$i),$tv,hash('sha256','catalog'.$i),hash('sha256',$ej),$ej]);
    }
    $db->exec("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES ('other_provider','777',1001,'accepted',REPEAT('a',64),REPEAT('b',64),'{}')");
}
$tables=['anex_hotel_decisions','andromeda_hotel_identities','catalog_hotels'];
try{
    foreach($tables as $t)$db->exec("DROP TABLE IF EXISTS $t");
    $db->exec("CREATE TABLE catalog_hotels(
      id INT PRIMARY KEY,name VARCHAR(500),country_id INT,country_name VARCHAR(100),region_id INT NULL,region_name VARCHAR(200),
      subregion_id INT NULL,subregion_name VARCHAR(200) NULL,category VARCHAR(20),is_active TINYINT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE andromeda_hotel_identities(
      supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(16),
      catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json MEDIUMTEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY(supplier_namespace,external_hotel_id), KEY(local_hotel_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE anex_hotel_decisions(anex_hotel_id BIGINT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(32)) ENGINE=InnoDB");

    $manifest=v17manifest();v17seed($db);
    $result=hm17_write($db,$manifest,str_repeat('c',40),null);
    v17ok($result['state']==='committed_verified'&&$result['inserted']===HM17_EXPECTED_ROWS&&$result['readback_verified'],'commit336');
    v17ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='bgoperator'")->fetchColumn()===HM17_EXPECTED_ROWS,'inserted_count');
    v17ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchColumn()===HM17_EXPECTED_ROWS,'anchors_preserved');

    $replay=false;try{hm17_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$replay=str_starts_with($e->getMessage(),'rolled_back_no_write:source_key_now_present');}
    v17ok($replay,'replay_blocked');

    v17seed($db);$db->exec("UPDATE catalog_hotels SET name='DRIFT' WHERE id=1001");
    $drift=false;try{hm17_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$drift=str_contains($e->getMessage(),'target_facts_drift');}
    v17ok($drift&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='bgoperator'")->fetchColumn()===0,'drift_rolls_back');

    v17seed($db);$last=(string)(100000000000+HM17_EXPECTED_ROWS);
    $db->exec("CREATE TRIGGER reject_v17_last BEFORE INSERT ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.supplier_namespace='bgoperator' AND NEW.external_hotel_id='$last' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late failure'; END IF; END");
    $late=false;try{hm17_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$late=str_starts_with($e->getMessage(),'rolled_back_no_write:');}
    v17ok($late&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='bgoperator'")->fetchColumn()===0,'late_failure_atomic');
    $db->exec('DROP TRIGGER reject_v17_last');

    v17seed($db);$db->exec("INSERT INTO anex_hotel_decisions VALUES(1,1001,'rejected')");
    $manual=false;try{hm17_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$manual=str_contains($e->getMessage(),'manual_target_protected');}
    v17ok($manual&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='bgoperator'")->fetchColumn()===0,'manual_guard');

    echo json_encode(['status'=>'passed','rows'=>HM17_EXPECTED_ROWS,'supplier_calls'=>0,'production_database_writes'=>0],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if($db->inTransaction())$db->rollBack();
    foreach($tables as $t)$db->exec("DROP TABLE IF EXISTS $t");
}
