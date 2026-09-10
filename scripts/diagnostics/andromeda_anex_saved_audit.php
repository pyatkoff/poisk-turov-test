<?php
// Export only hotel identity/content evidence; never sessions, raw offer IDs or credentials.
error_reporting(0);ob_start();$pdo=null;
try {
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
 $target=$root.'/_preview/search3-anex-candidate';$private=dirname($root,2).'/.anytoour-andromeda';
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
 require_once $target.'/app/integrations/anex-search-mapping-registry.php';
 $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('START TRANSACTION READ ONLY');
 $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
 $hotels=$pdo->query('SELECT id,name,country_id,region_name,category FROM catalog_hotels WHERE country_id=4 AND is_active=1 ORDER BY id LIMIT 20001')->fetchAll(PDO::FETCH_ASSOC);
 $aliases=$pdo->query('SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=4 AND h.is_active=1 ORDER BY a.hotel_id,a.alias LIMIT 50001')->fetchAll(PDO::FETCH_ASSOC);
 if(count($hotels)>20000||count($aliases)>50000)throw new RuntimeException();
 $identities=$pdo->query('SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 20001')->fetchAll(PDO::FETCH_ASSOC);
 $byId=[];foreach($identities as $i)$byId[$i['supplier_namespace'].':'.$i['external_hotel_id']]=$i;
 $files=glob($private.'/searches/*.json')?:[];if(count($files)>2000)throw new RuntimeException();
 $rows=[];$pages=0;$anexOffers=0;
 foreach($files as $file){
  if(!preg_match('/-[1-9][0-9]*\.json$/D',$file)||filesize($file)>2200000)continue;
  $state=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
  if(!in_array($state['status']??null,['complete','partial'],true)||($state['criteria']['STATEINC']??null)!==5)continue;
  ++$pages;
  foreach($state['store']['snapshot']['offers']??[] as $o){
   if(($o['operator_ref']??null)!=='5')continue;++$anexOffers;
   $key=$o['supplier_namespace'].':'.$o['external_hotel_id'];$content=$o['hotel_content']??[];
   $image=$content['image_url']??null;$anex=null;
   if(is_string($image)&&preg_match('~^https://gateway\.samo\.ru/web/data/hotel/[0-9]+x[0-9]+/5\.([1-9][0-9]*)\.([1-9][0-9]*)\.(?:jpg|jpeg|png)$~D',$image,$m)
      &&$m[2]===$o['external_hotel_id']&&$o['supplier_namespace']==='andromeda_catalog')$anex=(int)$m[1];
   elseif($o['supplier_namespace']==='operator_5'&&ctype_digit($o['external_hotel_id']))$anex=(int)$o['external_hotel_id'];
   $identity=$byId[$key]??null;if(($identity['decision_status']??null)==='accepted')continue;
   $row=['supplier_namespace'=>$o['supplier_namespace'],'external_hotel_id'=>$o['external_hotel_id'],'hotel'=>$o['hotel'],
     'image_url'=>$image,'hotel_url'=>$content['hotel_url']??null,'region'=>$content['region']??null,
     'anex_candidate_id'=>$anex,'anex_accepted_local_id'=>$anex?$registry->resolve('anex_online',$anex,'preview'):null,
     'identity'=>$identity];
   $rowKey=$key.':'.hash('sha256',json_encode([$row['hotel'],$image]));$rows[$rowKey]=$row;
  }
 }
 $pdo->exec('ROLLBACK');
 $csp=[];foreach([$root.'/.htaccess',$target.'/.htaccess',$target.'/search-page-v2.php'] as $p)if(is_file($p))foreach(file($p) as $line)if(stripos($line,'content-security-policy')!==false||stripos($line,'img-src')!==false)$csp[]=trim($line);
 $out=['status'=>'ok','operator_filter'=>'5','supplier_calls'=>0,'database_writes'=>0,'saved_pages_read'=>$pages,'anex_offer_rows'=>$anexOffers,'rows'=>array_values($rows),'hotels'=>$hotels,'aliases'=>$aliases,'csp_source_lines'=>$csp];
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','reason'=>'saved_identity_export_failed'];}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
