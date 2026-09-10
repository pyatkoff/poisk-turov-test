<?php
// Existing SQL writer, actual 25-row manifest and complete saved country competition.
$dsn=getenv('ANEX_LINK_TEST_DSN');if($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_link_test;charset=utf8mb4')throw new RuntimeException('isolated_only');
$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$directory=getenv('ANEX_SAVED_DIR');$fixture=json_decode(file_get_contents($directory.'/strong-fixture.json'),true,64,JSON_THROW_ON_ERROR);$protocol=file_get_contents($directory.'/strong-protocol.ndjson');$d=$fixture['delta'];$checks=0;$temporary=[];
function check($x,$m){global $checks;if(!$x)throw new RuntimeException($m);++$checks;}
function invoke($success=true,$reuse=null,$wire=null){global $temporary,$protocol,$checks;
    if($reuse===null){$dir=sys_get_temp_dir().'/anex-strong-'.bin2hex(random_bytes(8));$temporary[]=$dir;$root=$dir.'/anytoour.ru';mkdir($root.'/data',0700,true);mkdir($root.'/_preview/search3-anex-candidate',0700,true);mkdir($dir.'/private',0700);
        file_put_contents($root.'/data/db-v1.php','<?php function v2_data_db(){return new PDO(getenv("ANEX_LINK_TEST_DSN"),"root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}');
        file_put_contents($root.'/_preview/search3-anex-candidate/.andromeda-private.php','<?php return ["catalog_path"=>'.var_export($dir.'/private/catalog.json',true).'];');
    }else{$root=$reuse;$dir=dirname($root);}
    $registry=file_get_contents(__DIR__.'/../app/integrations/anex-search-mapping-registry.php');$writer=file_get_contents(__DIR__.'/../scripts/diagnostics/anex_search_mapping_writer.php');
    $source=substr($registry,5)."\n".substr($writer,5);$pipes=[];$p=proc_open([PHP_BINARY,'-r',$source],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$root);
    fwrite($pipes[0],$wire??$protocol);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
    check($err==='','no secret/diagnostic leakage');check($exit===($success?0:1),'expected writer exit:'.$out);$r=json_decode($out,true,64,JSON_THROW_ON_ERROR);return [$r,$root];
}
function reset_rows(){global $db;$db->exec('DELETE FROM anex_hotel_search_mappings WHERE anex_hotel_id<>70000');$db->exec('DELETE FROM anex_hotel_decisions WHERE anex_hotel_id<>7777');$db->exec('DELETE FROM anex_review_pair_exclusions');}
$tables=['hotel_aliases','andromeda_hotel_identities','anex_review_pair_exclusions','anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_hotels','catalog_hotels'];
try{
    foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
    $db->exec('CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name VARCHAR(255),country_id INT,is_active INT,latitude DECIMAL(12,8) NULL,longitude DECIMAL(12,8) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE hotel_aliases(hotel_id INT,alias VARCHAR(255),KEY(hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE anex_hotels(id INT PRIMARY KEY) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,decision_status VARCHAR(32),catalog_hotel_id INT) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_search_hotel_observations(anex_hotel_id INT PRIMARY KEY) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(32),scope VARCHAR(16),approval_policy VARCHAR(64),source_row_digest CHAR(64),mapping_digest CHAR(64),enabled INT) ENGINE=InnoDB');
    $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT,decision_status VARCHAR(16)) ENGINE=InnoDB');
    $db->beginTransaction();$q=$db->prepare('INSERT INTO catalog_hotels VALUES(?,?,?,1,NULL,NULL)');$alias=$db->prepare('INSERT INTO hotel_aliases VALUES(?,?)');$hotels=0;
    foreach($d['saved_strong_guards']['countries'] as $c=>$_){$local=$fixture['locals'][(string)$c];foreach($local['hotels'] as $h){$q->execute([$h['id'],$h['name'],$c]);++$hotels;}foreach($local['aliases'] as $a)$alias->execute([$a['hotel_id'],$a['alias']]);}
    $q=$db->prepare('INSERT INTO anex_hotels VALUES(?)');for($i=1;$i<=8362;++$i)$q->execute([$i]);
    $q=$db->prepare('UPDATE catalog_hotels SET latitude=?,longitude=? WHERE id=?');
    foreach($d['saved_strong_guards']['targets'] as $g)$q->execute([$g['target_latitude'],$g['target_longitude'],$g['catalog_hotel_id']]);
    $db->exec("INSERT INTO catalog_hotels VALUES(999999,'Preserved hotel',999,1,NULL,NULL)");
    $db->exec("INSERT INTO anex_hotel_search_mappings VALUES(70000,999999,'exact','preview','owner_exact_and_strong_20260908',REPEAT('a',64),REPEAT('b',64),1)");
    $db->exec("INSERT INTO anex_hotel_decisions VALUES(7777,'accepted',999999)");
    $first=$d['rows'][0];$last=$d['rows'][count($d['rows'])-1];
    $db->exec("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','123',".$first['catalog_hotel_id'].",'accepted')");$db->commit();
    $preserved=$db->query('SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=70000')->fetch(PDO::FETCH_ASSOC);
    [$r,$root]=invoke();check($r['inserted']===25&&$r['updated']===0&&$r['readback_verified'],'25 real inserts/readback');check(count($r['link_readback'])===25,'all pairs');check($r['live_coverage']['all_three']===1,'actual intersection');
    invoke(false,$root);check((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===26,'same operation not replayed');
    check($preserved===$db->query('SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=70000')->fetch(PDO::FETCH_ASSOC),'old row preserved');
    reset_rows();$target=$first['catalog_hotel_id'];$original=$db->query('SELECT * FROM catalog_hotels WHERE id='.$target)->fetch(PDO::FETCH_ASSOC);
    foreach(['is_active=0','country_id=999',"name='Changed'",'latitude=0'] as $change){$db->exec('UPDATE catalog_hotels SET '.$change.' WHERE id='.$target);invoke(false);check((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===1,'current guard zero writes');$q=$db->prepare('UPDATE catalog_hotels SET name=?,country_id=?,is_active=?,latitude=?,longitude=? WHERE id=?');$q->execute([$original['name'],$original['country_id'],1,$original['latitude'],$original['longitude'],$target]);}
    $db->exec("INSERT INTO hotel_aliases VALUES(".$target.",'new competing alias')");invoke(false);$db->exec("DELETE FROM hotel_aliases WHERE alias='new competing alias'");
    $db->exec('INSERT INTO anex_hotel_decisions VALUES('.$first['anex_hotel_id'].",'rejected',".$target.')');invoke(false);reset_rows();
    $db->exec('INSERT INTO anex_review_pair_exclusions VALUES('.$first['anex_hotel_id'].','.$target.')');invoke(false);reset_rows();
    $db->exec('CREATE TRIGGER fail_last BEFORE INSERT ON anex_hotel_search_mappings FOR EACH ROW BEGIN IF NEW.anex_hotel_id='.$last['anex_hotel_id']." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic'; END IF; END");invoke(false);check((int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()===1,'late SQL rolls back prior24');$db->exec('DROP TRIGGER fail_last');
    echo json_encode(['status'=>'passed','checks'=>$checks,'actual_rows'=>25,'full_country_hotels'=>$hotels,'application_database_writes'=>0,'supplier_calls'=>0]),"\n";
}finally{
    if($db->inTransaction())$db->rollBack();foreach($tables as $t)$db->exec('DROP TABLE IF EXISTS '.$t);
    foreach($temporary as $dir){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($dir);}
}
