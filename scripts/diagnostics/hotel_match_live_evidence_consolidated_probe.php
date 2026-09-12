<?php
declare(strict_types=1);
error_reporting(0);
const OP='hotel-match-live-evidence-consolidated-probe-1971-20260912-v1';
$candidates=[
 ['p'=>'anex','id'=>'797','local'=>501],['p'=>'anex','id'=>'6928','local'=>38859],
 ['p'=>'anex','id'=>'9444','local'=>272],['p'=>'anex','id'=>'20855','local'=>1048],['p'=>'anex','id'=>'9280','local'=>1056],['p'=>'anex','id'=>'15924','local'=>1092],['p'=>'anex','id'=>'24653','local'=>346],['p'=>'anex','id'=>'8643','local'=>1440],['p'=>'anex','id'=>'5441','local'=>772],['p'=>'anex','id'=>'15001','local'=>1459],['p'=>'anex','id'=>'9809','local'=>1067],['p'=>'anex','id'=>'17730','local'=>1170],
 ['p'=>'anex','id'=>'1078','local'=>2443],['p'=>'anex','id'=>'19557','local'=>75560],['p'=>'anex','id'=>'1287','local'=>70872],
 ['p'=>'andromeda','id'=>'2000036330','local'=>1208],['p'=>'anex','id'=>'32809','local'=>71266]
];
try{
 $root=realpath(getcwd()); if(!$root||basename($root)!=='anytoour.ru') throw new RuntimeException('root');
 $dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; require_once $dbHelper;
 $pdo=v2_data_db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->exec('START TRANSACTION READ ONLY');
 $tables=['anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities']; $schema=[];
 foreach($tables as $t){$q=$pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$t]);$schema[$t]=$q->fetchAll(PDO::FETCH_ASSOC);}
 $rows=[];
 foreach($candidates as $c){$r=$c;
   if($c['p']==='anex'){
    $q=$pdo->prepare('SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=? LIMIT 20');$q->execute([$c['id']]);$r['mappings']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 20');$q->execute([$c['id']]);$r['decisions']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare('SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? LIMIT 50');$q->execute([$c['id']]);$r['exclusions']=$q->fetchAll(PDO::FETCH_ASSOC);
   } else {
    $q=$pdo->prepare("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? LIMIT 5");$q->execute([$c['id']]);$r['identity']=$q->fetchAll(PDO::FETCH_ASSOC);
   }
   $q=$pdo->prepare('SELECT id,name,country_id,region_name,subregion_name,is_active FROM catalog_hotels WHERE id=?');$q->execute([$c['local']]);$r['target']=$q->fetch(PDO::FETCH_ASSOC)?:null; $rows[]=$r;
 }
 $pdo->exec('ROLLBACK'); $out=['status'=>'completed','operation_id'=>OP,'schema'=>$schema,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','operation_id'=>OP,'safe_message'=>substr($e->getMessage(),0,180),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];}
echo 'MATCH_CONSOLIDATED_PROBE_JSON:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL; exit(($out['status']??'')==='completed'?0:2);
