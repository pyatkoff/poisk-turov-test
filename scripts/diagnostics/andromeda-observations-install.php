<?php
error_reporting(0);ob_start();$pdo=null;$lock=null;$written=[];$backups=[];$phase='setup';
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException();
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
    $request=json_decode(file_get_contents('php://stdin'),true,16,JSON_THROW_ON_ERROR);
    if(!preg_match('/^[a-f0-9]{40}$/D',$request['source_sha']??'')
        ||($request['expected_endpoint_sha256']??'')!=='fa968565ffe3831beba0aaefacf2f2c7085a6c6934bcb329eb81dbf4cf15409e'
        ||array_keys($request['files']??[])!==['app/integrations/andromeda-hotel-observations.php','api-andromeda-search3-preview.php'])throw new RuntimeException();
    $target=$root.'/_preview/search3-anex-candidate';$private=dirname($root,2).'/.anytoour-andromeda';
    $lock=fopen($private.'/unresolved-observations.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
    if(is_link($target.'/api-andromeda-search3-preview.php')||hash_file('sha256',$target.'/api-andromeda-search3-preview.php')!==$request['expected_endpoint_sha256'])throw new RuntimeException();
    $module=$target.'/app/integrations/andromeda-hotel-observations.php';if(file_exists($module)||is_link($module))throw new RuntimeException();
    $files=[];foreach($request['files'] as $path=>$encoded){$bytes=base64_decode($encoded,true);if($bytes===false||strlen($bytes)>200000)throw new RuntimeException();$files[$path]=$bytes;}
    if(hash('sha256',$files['app/integrations/andromeda-hotel-observations.php'])!==$request['module_sha256']
        ||hash('sha256',$files['api-andromeda-search3-preview.php'])!==$request['endpoint_sha256'])throw new RuntimeException();
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $hashRows=static function(PDO $pdo,string $sql):array{$ctx=hash_init('sha256');$count=0;$q=$pdo->query($sql);while($row=$q->fetch(PDO::FETCH_NUM)){hash_update($ctx,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");++$count;}return ['count'=>$count,'sha256'=>hash_final($ctx)];};
    $before=['identities'=>$hashRows($pdo,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id'),
        'catalog'=>$hashRows($pdo,'SELECT id,name,country_id,is_active FROM catalog_hotels ORDER BY id')];
    $phase='publish';$release=$private.'/unresolved-observations-'.$request['source_sha'];
    if(file_exists($release)||!mkdir($release,0700))throw new RuntimeException();
    foreach($files as $path=>$bytes){
        $destination=$target.'/'.$path;$directory=dirname($destination);if(!is_dir($directory)&&!mkdir($directory,0755,true))throw new RuntimeException();
        $backups[$path]=is_file($destination)?file_get_contents($destination):null;
        $staged=$release.'/'.str_replace('/','__',$path);if(file_put_contents($staged,$bytes)!==strlen($bytes)||!chmod($staged,0644)||!rename($staged,$destination))throw new RuntimeException();
        $written[]=$path;if(hash_file('sha256',$destination)!==hash('sha256',$bytes))throw new RuntimeException();
    }
    $phase='schema';require_once $module;AnyTourAndromedaHotelObservations::install($pdo);
    $existing=(int)$pdo->query('SELECT COUNT(*) FROM andromeda_search_hotel_observations')->fetchColumn();
    if($existing!==0)throw new RuntimeException();
    $phase='backfill';$config=require $target.'/.andromeda-private.php';
    $catalogPaths=[$config['catalog_path']];foreach(glob(dirname($config['catalog_path']).'/countries/*.json')?:[] as $path)$catalogPaths[]=$path;
    $countries=[];foreach($catalogPaths as $path){$saved=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);$state=(string)($saved['all']['params']['STATEINC']??'');if($state===''||isset($countries[$state]))throw new RuntimeException();$countries[$state]=$saved;}
    $accepted=[];$q=$pdo->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted'");
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$accepted[$row['supplier_namespace'].':'.$row['external_hotel_id']]=(int)$row['local_hotel_id'];
    $pages=0;$unique=0;$inserted=0;$mappedFiltered=0;$skipped=0;
    $paths=glob(dirname($config['catalog_path']).'/searches/*.json')?:[];if(count($paths)>500)throw new RuntimeException();
    foreach($paths as $path){
        if(is_link($path)||filesize($path)>3000000||!preg_match('/-[1-9][0-9]*\.json$/D',$path)){++$skipped;continue;}
        $state=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
        $page=$state['store']['snapshot']??null;$criteria=$state['criteria']??null;
        if(!is_array($page)||!is_array($criteria)||!in_array($state['status']??null,['complete','partial'],true)){++$skipped;continue;}
        $country=$countries[(string)($criteria['STATEINC']??'')]??null;if(!$country){++$skipped;continue;}
        foreach($page['offers'] as &$offer){$key=($offer['supplier_namespace']??'').':'.($offer['external_hotel_id']??'');if(isset($accepted[$key])&&($offer['local_hotel_id']??null)===null){$offer['local_hotel_id']=$accepted[$key];++$mappedFiltered;}}
        unset($offer);$country['observed_at_utc']=gmdate('Y-m-d H:i:s',(int)($state['store']['created_at']??time()));
        $result=AnyTourAndromedaHotelObservations::record($pdo,$page,$country);++$pages;$unique+=$result['unique_hotels'];$inserted+=$result['inserted'];
    }
    $after=['identities'=>$hashRows($pdo,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id'),
        'catalog'=>$hashRows($pdo,'SELECT id,name,country_id,is_active FROM catalog_hotels ORDER BY id')];
    if($after!==$before)throw new RuntimeException();
    $rows=(int)$pdo->query('SELECT COUNT(*) FROM andromeda_search_hotel_observations')->fetchColumn();
    $resolved=(int)$pdo->query("SELECT COUNT(*) FROM andromeda_search_hotel_observations o JOIN andromeda_hotel_identities i ON i.supplier_namespace=o.supplier_namespace AND i.external_hotel_id=o.external_hotel_id AND i.decision_status='accepted'")->fetchColumn();
    if($rows!==$inserted||$resolved!==0)throw new RuntimeException();
    $result=['status'=>'installed','source_sha'=>$request['source_sha'],'table'=>'andromeda_search_hotel_observations',
        'saved_pages_scanned'=>$pages,'unresolved_hotel_events'=>$rows,'duplicate_events'=>$unique-$inserted,
        'currently_mapped_filtered'=>$mappedFiltered,'files_skipped'=>$skipped,'readback_verified'=>true,
        'identities_preserved'=>$before['identities'],'catalog_preserved'=>$before['catalog'],
        'supplier_calls'=>0,'mapping_writes'=>0,'catalog_writes'=>0,'production_changed'=>false];
    file_put_contents($release.'/manifest.json',json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
} catch(Throwable $e) {
    foreach(array_reverse($written) as $path){$destination=$target.'/'.$path;if($backups[$path]===null)@unlink($destination);else @file_put_contents($destination,$backups[$path]);}
    $result=['status'=>'failed','phase'=>$phase,'retry'=>false,'rollback_attempted'=>count($written)>0];
}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
