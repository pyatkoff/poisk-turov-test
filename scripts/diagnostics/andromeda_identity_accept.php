<?php
error_reporting(0);ob_start();$pdo=null;
function andromeda_current_name($value){
 $value=mb_strtolower($value,'UTF-8');$value=preg_split('/\(\s*(?:[eе][xх][.\s:-]*|быв[.\s:-]*)/u',$value)[0];
 $value=str_replace(['&','ё'],[' and ','е'],$value);preg_match_all('/[\p{L}\p{N}]+/u',$value,$m);
 return implode(' ',array_values(array_diff($m[0],['hotel','hotels','resort','resorts','spa','the','and','отель'])));
}
try{
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
 $request=json_decode(file_get_contents('php://stdin'),true,32,JSON_THROW_ON_ERROR);
 $allowed=['416247'=>9365,'2000042763'=>447];$actual=[];
 foreach($request['rows']??[] as $r){if(isset($actual[$r['external_hotel_id']]))throw new RuntimeException();$actual[$r['external_hotel_id']]=$r['local_hotel_id'];}
 if($actual!==$allowed)throw new RuntimeException();
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
 require_once $root.'/_preview/search3-anex-candidate/app/integrations/anex-search-mapping-registry.php';
 $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->beginTransaction();
 $all=$pdo->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
 $index=[];$preserve=[];foreach($all as $row){$key=$row['supplier_namespace'].':'.$row['external_hotel_id'];$index[$key]=$row;if($row['supplier_namespace']!=='andromeda_catalog'||!isset($allowed[$row['external_hotel_id']]))$preserve[$key]=$row;}
 $locals=$pdo->query('SELECT id,name,country_id FROM catalog_hotels WHERE is_active=1 AND country_id=1 ORDER BY id LIMIT 10001')->fetchAll(PDO::FETCH_ASSOC);
 $aliases=$pdo->query('SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=1 AND h.is_active=1 LIMIT 30001')->fetchAll(PDO::FETCH_ASSOC);
 if(count($locals)>10000||count($aliases)>30000)throw new RuntimeException();
 $nameIndex=[];foreach($locals as $h)$nameIndex[andromeda_current_name($h['name'])][(int)$h['id']]=true;
 foreach($aliases as $h)$nameIndex[andromeda_current_name($h['alias'])][(int)$h['hotel_id']]=true;
 $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$updated=0;
 $update=$pdo->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
 foreach($request['rows'] as $r){
  $old=$index['andromeda_catalog:'.$r['external_hotel_id']]??null;$newHash=hash('sha256',$r['evidence_json']);
  if($old&&$old['decision_status']==='accepted'&&(int)$old['local_hotel_id']===$r['local_hotel_id']&&$old['evidence_sha256']===$newHash)continue;
  if(!$old||$old['decision_status']!=='pending'||$old['local_hotel_id']!==null||$old['evidence_sha256']!==$r['previous_evidence_sha256'])throw new RuntimeException();
  $prior=json_decode($old['evidence_json'],true,32,JSON_THROW_ON_ERROR);$evidence=json_decode($r['evidence_json'],true,32,JSON_THROW_ON_ERROR);
  if(($prior['source']['stateKey']??null)!==3||($evidence['prior_evidence']??null)!==$prior)throw new RuntimeException();
  $candidates=array_keys($nameIndex[andromeda_current_name($evidence['observed_hotel'])]??[]);
  if(count($candidates)!==1||$candidates[0]!==$r['local_hotel_id'])throw new RuntimeException();
  if($r['external_hotel_id']==='416247'&&$registry->resolve('anex_online',5196,'preview')!==9365)throw new RuntimeException();
  $update->execute([$r['local_hotel_id'],$newHash,$r['evidence_json'],$r['external_hotel_id'],$r['previous_evidence_sha256']]);if($update->rowCount()!==1)throw new RuntimeException();++$updated;
 }
 $after=$pdo->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id')->fetchAll(PDO::FETCH_ASSOC);$remaining=[];$readback=[];
 foreach($after as $row){$key=$row['supplier_namespace'].':'.$row['external_hotel_id'];if($row['supplier_namespace']==='andromeda_catalog'&&isset($allowed[$row['external_hotel_id']]))$readback[]=['external_hotel_id'=>$row['external_hotel_id'],'local_hotel_id'=>$row['local_hotel_id'],'decision_status'=>$row['decision_status'],'evidence_sha256'=>$row['evidence_sha256']];else $remaining[$key]=$row;}
 if($remaining!==$preserve||count($after)!==count($all))throw new RuntimeException();
 $counts=$pdo->query("SELECT decision_status,COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_KEY_PAIR);
 $pdo->commit();$out=['status'=>'accepted','updated'=>$updated,'rows'=>$readback,'counts'=>$counts,'other_identities_unchanged'=>true,'supplier_calls'=>0];
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','database_transaction_rolled_back'=>true];}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
