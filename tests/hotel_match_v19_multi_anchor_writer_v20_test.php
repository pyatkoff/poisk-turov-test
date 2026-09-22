<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_v19_multi_anchor_writer_v20.php';

$dsn=getenv('MATCH_V20_TEST_DSN');
if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=match_v20;charset=utf8mb4')throw new RuntimeException('isolated_mysql_required');
$db=new PDO($dsn,'root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

function v20ok(bool $yes,string $m):void{if(!$yes)throw new RuntimeException($m);}
function v20target(int $tv):array{
    return ['id'=>$tv,'name'=>'HOTEL '.$tv,'country_id'=>4,'country_name'=>'Турция',
        'region_id'=>20,'region_name'=>'Сиде','subregion_id'=>200,'subregion_name'=>'Сиде',
        'category'=>'5','is_active'=>1];
}
function v20anchor_rows(int $tv):array{
    $count=$tv<=1004?3:2;$catalog=hash('sha256','catalog'.$tv);$out=[];
    for($j=1;$j<=$count;$j++){
        $evidence=hm17_json(['source'=>['id'=>(string)(700000+$tv*10+$j),'name'=>'ANCHOR '.$tv.' '.$j,'state'=>'TR'],'tv'=>$tv,'n'=>$j]);
        $out[]=[
            'supplier_namespace'=>'andromeda_catalog',
            'external_hotel_id'=>(string)(700000+$tv*10+$j),
            'local_hotel_id'=>$tv,
            'decision_status'=>'accepted',
            'catalog_sha256'=>$catalog,
            'evidence_sha256'=>hash('sha256',$evidence),
            'evidence_json'=>$evidence,
            'source_projection'=>[['id'=>(string)(700000+$tv*10+$j),'name'=>'ANCHOR '.$tv.' '.$j,'state'=>'TR']],
        ];
    }
    return $out;
}
function v20manifest():array{
    $rows=[];
    for($i=1;$i<=51;$i++){
        $tv=1000+$i;$aa=v20anchor_rows($tv);
        $rows[]=['supplier_namespace'=>'bgoperator','external_hotel_id'=>(string)(810000000+$i),'tv_hotel_id'=>$tv,
            'target'=>v20target($tv),'anchor_count'=>count($aa),'unanimous_catalog_sha256'=>$aa[0]['catalog_sha256'],
            'anchors'=>array_map(fn($a)=>array_diff_key($a,['evidence_json'=>true]),$aa)];
    }
    for($i=1;$i<=10;$i++){
        $tv=1051+$i;$aa=v20anchor_rows($tv);
        $rows[]=['supplier_namespace'=>'operator_315','external_hotel_id'=>(string)(910000000+$i),'tv_hotel_id'=>$tv,
            'target'=>v20target($tv),'anchor_count'=>count($aa),'unanimous_catalog_sha256'=>$aa[0]['catalog_sha256'],
            'anchors'=>array_map(fn($a)=>array_diff_key($a,['evidence_json'=>true]),$aa)];
    }
    for($i=1;$i<=12;$i++){
        $tv=1000+$i;$aa=v20anchor_rows($tv);
        $rows[]=['supplier_namespace'=>'operator_342','external_hotel_id'=>(string)(920000000+$i),'tv_hotel_id'=>$tv,
            'target'=>v20target($tv),'anchor_count'=>count($aa),'unanimous_catalog_sha256'=>$aa[0]['catalog_sha256'],
            'anchors'=>array_map(fn($a)=>array_diff_key($a,['evidence_json'=>true]),$aa)];
    }
    return $rows;
}
function v20seed(PDO $db):void{
    $db->exec('DELETE FROM andromeda_hotel_identities');$db->exec('DELETE FROM anex_hotel_decisions');$db->exec('DELETE FROM catalog_hotels');
    $h=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,?,?,?,?,?,?,1)');
    $a=$db->prepare('INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,?,?,?,?)');
    for($tv=1001;$tv<=1061;$tv++){
        $t=v20target($tv);$h->execute([$t['id'],$t['name'],$t['country_id'],$t['country_name'],$t['region_id'],$t['region_name'],$t['subregion_id'],$t['subregion_name'],$t['category']]);
        foreach(v20anchor_rows($tv) as $r)$a->execute([$r['supplier_namespace'],$r['external_hotel_id'],$r['local_hotel_id'],$r['decision_status'],$r['catalog_sha256'],$r['evidence_sha256'],$r['evidence_json']]);
    }
    $a->execute(['other_provider','777',1001,'accepted',str_repeat('a',64),str_repeat('b',64),'{}']);
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
      PRIMARY KEY(supplier_namespace,external_hotel_id),KEY(local_hotel_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE anex_hotel_decisions(anex_hotel_id BIGINT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(32)) ENGINE=InnoDB");

    $manifest=v20manifest();
    v20ok(count($manifest)===73&&count(array_unique(array_column($manifest,'tv_hotel_id')))===61,'fixture_counts');
    v20seed($db);
    $r=hm20_write($db,$manifest,str_repeat('c',40),null);
    v20ok($r['state']==='committed_verified'&&$r['inserted']===73&&$r['readback_verified']===true,'commit73');
    v20ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='bgoperator'")->fetchColumn()===51,'bg51');
    v20ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='operator_315'")->fetchColumn()===10,'funsun10');
    v20ok((int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='operator_342'")->fetchColumn()===12,'intourist12');

    $replay=false;try{hm20_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$replay=str_contains($e->getMessage(),'source_key_now_present');}
    v20ok($replay,'replay_blocked');

    v20seed($db);$db->exec("UPDATE catalog_hotels SET name='DRIFT' WHERE id=1001");
    $drift=false;try{hm20_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$drift=str_contains($e->getMessage(),'target_facts_drift');}
    v20ok($drift&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'target_drift_rolls_back');

    v20seed($db);$db->exec("UPDATE andromeda_hotel_identities SET evidence_sha256=REPEAT('f',64) WHERE supplier_namespace='andromeda_catalog' AND local_hotel_id=1001 LIMIT 1");
    $anchorDrift=false;try{hm20_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$anchorDrift=str_contains($e->getMessage(),'anchor_evidence_hash_drift');}
    v20ok($anchorDrift&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'anchor_drift_rolls_back');

    v20seed($db);$last='920000012';
    $db->exec("CREATE TRIGGER reject_v20_last BEFORE INSERT ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.supplier_namespace='operator_342' AND NEW.external_hotel_id='$last' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late failure'; END IF; END");
    $late=false;try{hm20_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$late=str_starts_with($e->getMessage(),'rolled_back_no_write:');}
    v20ok($late&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'late_failure_atomic');
    $db->exec('DROP TRIGGER reject_v20_last');

    v20seed($db);$db->exec("INSERT INTO anex_hotel_decisions VALUES(1,1001,'rejected')");
    $manual=false;try{hm20_write($db,$manifest,str_repeat('c',40),null);}catch(RuntimeException $e){$manual=str_contains($e->getMessage(),'manual_target_protected');}
    v20ok($manual&&(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace IN ('bgoperator','operator_315','operator_342')")->fetchColumn()===0,'manual_guard');

    echo json_encode(['status'=>'passed','rows'=>73,'unique_targets'=>61,'supplier_calls'=>0,'production_database_writes'=>0],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if($db->inTransaction())$db->rollBack();
    foreach($tables as $t)$db->exec("DROP TABLE IF EXISTS $t");
}
