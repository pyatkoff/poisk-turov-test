<?php
error_reporting(0);ob_start();$pdo=null;$lock=null;$phase='setup';
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException();
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
 $preview=$root.'/_preview/search3-anex-candidate';
 require_once $preview.'/api-andromeda-search3-preview.php';
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
 $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $config=require $preview.'/.andromeda-private.php';$private=dirname($config['catalog_path']);
 $stage=$private.'/turkey-catalog-v1';
 $request=json_decode(file_get_contents('php://stdin'),true,32,JSON_THROW_ON_ERROR);
 $local=$pdo->query("SELECT id,name FROM catalog_countries WHERE name='Турция' AND is_active=1")->fetchAll(PDO::FETCH_ASSOC);
 if(count($local)!==1)throw new RuntimeException();$country=(int)$local[0]['id'];
 $lock=fopen($private.'/turkey-catalog.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
 if($request['operation']==='capture'){
  $phase='capture_reservation';if(file_exists($stage)||!mkdir($stage,0700))throw new RuntimeException();
  anytour_andromeda_search3_save($stage.'/reservation.json',['state'=>'inflight','country_id'=>$country]);
  $saved=json_decode(file_get_contents($config['catalog_path']),true,32,JSON_THROW_ON_ERROR);
  $stateId=anytour_anex_search3_dictionary_id($saved['state']['payload']['STATE'],['Турция']);
  $departure=(int)$saved['all']['params']['TOWNFROMINC'];
  $client=new AnyTourAndromedaClient(static function($url,$options)use($private){
   anytour_andromeda_search3_budget($private);return (new AnyTourAndromedaTransport(true))($url,$options);
  },true);
  $phase='supplier_catalog';$client->login($config['username'],$config['password']);
  $params=['TOWNFROMINC'=>$departure,'STATEINC'=>$stateId];$payload=$client->catalog('all',$params);
  $bytes=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  foreach([$config['username'],$config['password'],rawurlencode($config['username']),rawurlencode($config['password'])] as $secret)if($secret!==''&&strpos($bytes,$secret)!==false)throw new RuntimeException();
  $saved['all']=['action'=>'all','params'=>$params,'payload'=>$payload];
  $saved['local_country_id']=$country;$saved['local_country_name']=$local[0]['name'];
  anytour_andromeda_search3_save($stage.'/catalog.json',$saved);
  $phase='local_catalog';$pdo->exec('START TRANSACTION READ ONLY');
  $h=$pdo->prepare('SELECT id,name,country_id,country_name,region_name,subregion_name,category FROM catalog_hotels WHERE country_id=? AND is_active=1 ORDER BY id LIMIT 20001');
  $h->execute([$country]);$hotels=$h->fetchAll(PDO::FETCH_ASSOC);
  $q=$pdo->prepare('SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=? AND h.is_active=1 ORDER BY a.hotel_id,a.alias LIMIT 50001');
  $q->execute([$country]);$aliases=$q->fetchAll(PDO::FETCH_ASSOC);$pdo->exec('ROLLBACK');
  if(count($hotels)>20000||count($aliases)>50000)throw new RuntimeException();
  $result=['status'=>'captured','country_id'=>$country,'supplier_country_id'=>$stateId,'catalog_sha256'=>hash_file('sha256',$stage.'/catalog.json'),
   'catalog'=>$saved['all'],'local'=>['complete'=>true,'country_id'=>$country,'hotels'=>$hotels,'aliases'=>$aliases],'supplier_calls'=>2];
  anytour_andromeda_search3_save($stage.'/capture-result.json',['status'=>'captured','catalog_sha256'=>$result['catalog_sha256'],'hotels'=>count($payload['HOTELS'])]);
 }elseif($request['operation']==='import'){
  $phase='import_reservation';if(!is_file($stage.'/capture-result.json')||is_file($stage.'/import-reservation.json'))throw new RuntimeException();
  $saved=json_decode(file_get_contents($stage.'/catalog.json'),true,32,JSON_THROW_ON_ERROR);
  if(hash_file('sha256',$stage.'/catalog.json')!==$request['catalog_sha256']||$saved['local_country_id']!==$country)throw new RuntimeException();
  $catalog=[];foreach($saved['all']['payload']['HOTELS'] as $h)$catalog[(string)$h['id']]=$h;
  $rows=$request['rows'];if(count($rows)!==count($catalog))throw new RuntimeException();
  $seen=[];foreach($rows as $r){
   $id=$r['external_hotel_id'];$e=json_decode($r['evidence_json'],true,32,JSON_THROW_ON_ERROR);
   if(!is_string($id)||!isset($catalog[$id])||isset($seen[$id])||$e['source']!=$catalog[$id]||!in_array($r['decision_status'],['accepted','pending','conflict'],true))throw new RuntimeException();
   if($r['decision_status']==='accepted' ? (!is_int($r['local_hotel_id'])||$r['local_hotel_id']<1||(string)$catalog[$id]['stateKey']!==(string)$saved['all']['params']['STATEINC']) : $r['local_hotel_id']!==null)throw new RuntimeException();
   $seen[$id]=true;
  }
  anytour_andromeda_search3_save($stage.'/import-reservation.json',['state'=>'inflight','request_sha256'=>hash('sha256',json_encode($request))]);
  $phase='import';$pdo->beginTransaction();
  $before=$pdo->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
  $existing=[];foreach($before as $r)$existing[$r['supplier_namespace'].':'.$r['external_hotel_id']]=$r;
  $lookup=$pdo->prepare('SELECT name FROM catalog_hotels WHERE id=? AND country_id=? AND is_active=1');
  $insert=$pdo->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('andromeda_catalog',?,?,?,?,?,?)");
  $counts=['accepted'=>0,'pending'=>0,'conflict'=>0];$skipped=[];$inserted=0;
  foreach($rows as $r){
   if(isset($existing['andromeda_catalog:'.$r['external_hotel_id']])){$skipped[]=$r['external_hotel_id'];continue;}
   if($r['local_hotel_id']!==null){$lookup->execute([$r['local_hotel_id'],$country]);$name=$lookup->fetchColumn();$e=json_decode($r['evidence_json'],true);if(!$name||$name!==$e['target_name'])throw new RuntimeException();}
   $insert->execute([$r['external_hotel_id'],$r['local_hotel_id'],$r['decision_status'],$request['catalog_sha256'],hash('sha256',$r['evidence_json']),$r['evidence_json']]);++$inserted;++$counts[$r['decision_status']];
  }
  $after=$pdo->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
  $afterIndex=[];foreach($after as $r)$afterIndex[$r['supplier_namespace'].':'.$r['external_hotel_id']]=$r;
  foreach($existing as $key=>$old)if($afterIndex[$key]!==$old)throw new RuntimeException();
  foreach($rows as $r){$key='andromeda_catalog:'.$r['external_hotel_id'];if(isset($existing[$key]))continue;$a=$afterIndex[$key];if((string)$a['local_hotel_id']!==(string)$r['local_hotel_id']||$a['decision_status']!==$r['decision_status']||$a['evidence_sha256']!==hash('sha256',$r['evidence_json']))throw new RuntimeException();}
  $pdo->commit();$phase='catalog_activation';
  $dir=$private.'/countries';if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException();
  if(file_exists($dir.'/'.$country.'.json'))throw new RuntimeException();
  anytour_andromeda_search3_save($dir.'/'.$country.'.json',$saved);
  $result=['status'=>'imported','country_id'=>$country,'supplier_country_id'=>$saved['all']['params']['STATEINC'],
   'inserted'=>$inserted,'counts'=>$counts,'existing_preserved'=>count($before),'existing_id_collisions'=>$skipped,
   'catalog_hotels'=>count($catalog),'catalog_sha256'=>hash_file('sha256',$dir.'/'.$country.'.json'),'readback_verified'=>true,'supplier_calls'=>0];
  anytour_andromeda_search3_save($stage.'/import-result.json',$result);
 }else throw new RuntimeException();
}catch(Throwable $e){
 if($pdo instanceof PDO && $pdo->inTransaction())$pdo->rollBack();
 $result=['status'=>'failed','phase'=>$phase,'retry'=>false];
}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
