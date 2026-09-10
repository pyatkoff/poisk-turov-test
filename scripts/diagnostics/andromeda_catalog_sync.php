<?php
// Confined AnyTour catalog read / append-only Andromeda identity import.
error_reporting(0);
ob_start();
$pdo = null;
try {
    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException();
    require_once $root . (is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
    $pdo = v2_data_db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $raw = file_get_contents('php://stdin', false, null, 0, 4000001);
    if (strlen($raw)>4000000) throw new RuntimeException();
    $request = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if ($request['operation'] === 'export') {
        $pdo->exec('START TRANSACTION READ ONLY');
        $hotels = $pdo->query('SELECT id,name,country_id,country_name,region_name,subregion_name,category FROM catalog_hotels WHERE country_id=1 AND is_active=1 ORDER BY id LIMIT 10001')->fetchAll(PDO::FETCH_ASSOC);
        $aliases = $pdo->query('SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=1 AND h.is_active=1 ORDER BY a.hotel_id,a.alias LIMIT 30001')->fetchAll(PDO::FETCH_ASSOC);
        if (count($hotels)>10000 || count($aliases)>30000) throw new RuntimeException();
        $pdo->exec('ROLLBACK');
        $result = ['status'=>'ok','hotels'=>$hotels,'aliases'=>$aliases,'country_id'=>1,'complete'=>true];
    } elseif ($request['operation'] === 'import') {
        $rows=$request['rows'];
        if (!is_array($rows) || count($rows)!==905 || !preg_match('/^[a-f0-9]{64}$/D',$request['catalog_sha256']??'')) throw new RuntimeException();
        $seen=[];
        foreach ($rows as $r) {
            $id=$r['external_hotel_id']; $target=$r['local_hotel_id'];
            if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,31}$/D',$id) || isset($seen[$id])
                || !in_array($r['decision_status'],['accepted','pending','conflict'],true)
                || ($r['decision_status']==='accepted' ? (!is_int($target)||$target<1||$id==='2000073714') : $target!==null)
                || !is_string($r['evidence_json']) || strlen($r['evidence_json'])>20000) throw new RuntimeException();
            $seen[$id]=true;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS andromeda_hotel_identities (supplier_namespace VARCHAR(40) NOT NULL,external_hotel_id VARCHAR(32) NOT NULL,local_hotel_id INT NULL,decision_status VARCHAR(16) NOT NULL,catalog_sha256 CHAR(64) NOT NULL,evidence_sha256 CHAR(64) NOT NULL,evidence_json MEDIUMTEXT NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(supplier_namespace,external_hotel_id),KEY(local_hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->beginTransaction();
        $existing=$pdo->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        $index=[]; foreach($existing as $r)$index[$r['external_hotel_id']]=$r;
        $lookup=$pdo->prepare('SELECT id,name FROM catalog_hotels WHERE id=? AND country_id=1 AND is_active=1');
        $insert=$pdo->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('andromeda_catalog',?,?,?,?,?,?)");
        $inserted=0;
        foreach($rows as $r){
            $sha=hash('sha256',$r['evidence_json']);
            if(isset($index[$r['external_hotel_id']])){
                $old=$index[$r['external_hotel_id']];
                if($old['evidence_sha256']!==$sha || $old['decision_status']!==$r['decision_status'] || (string)$old['local_hotel_id']!==(string)$r['local_hotel_id']) throw new RuntimeException();
                continue;
            }
            if($r['local_hotel_id']!==null){
                $lookup->execute([$r['local_hotel_id']]); $hotel=$lookup->fetch(PDO::FETCH_ASSOC);
                $evidence=json_decode($r['evidence_json'],true,32,JSON_THROW_ON_ERROR);
                if(!$hotel || $hotel['name']!==$evidence['target_name']) throw new RuntimeException();
            }
            $insert->execute([$r['external_hotel_id'],$r['local_hotel_id'],$r['decision_status'],$request['catalog_sha256'],$sha,$r['evidence_json']]);++$inserted;
        }
        $counts=$pdo->query("SELECT decision_status,COUNT(*) AS total FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_KEY_PAIR);
        $pdo->commit();
        $readback=$pdo->query("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        $result=['status'=>'imported','inserted'=>$inserted,'preserved'=>count($existing),'counts'=>$counts,'rows'=>$readback];
    } else throw new RuntimeException();
} catch(Throwable $error) {
    if($pdo instanceof PDO && $pdo->inTransaction())$pdo->rollBack();
    $result=['status'=>'failed'];
}
while(ob_get_level())ob_end_clean();
$out=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
echo strlen($out)<=4000000?$out:'{"status":"failed"}';
