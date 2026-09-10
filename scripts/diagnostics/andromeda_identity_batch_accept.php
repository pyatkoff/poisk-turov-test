<?php
error_reporting(0); ob_start(); $pdo=null;
function norm_text($v){$v=mb_strtolower((string)$v,'UTF-8');$v=str_replace('ё','е',$v);preg_match_all('/[\p{L}\p{N}]+/u',$v,$m);return implode(' ',$m[0]);}
function current_name($v){$v=(string)$v;$v=preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s+[^()]+\)\s*$/ui','',$v);return trim($v);}
function name_key($v){$parts=explode(' ',norm_text(current_name($v)));$parts=array_values(array_filter($parts,fn($x)=>$x!==''&&$x!=='hotel'));sort($parts,SORT_STRING);return implode(' ',$parts);}
try{
 $root=realpath(getcwd()); if(!$root||basename($root)!=='anytoour.ru') throw new RuntimeException('root');
 $req=json_decode(file_get_contents('php://stdin'),true,64,JSON_THROW_ON_ERROR);
 if(($req['operation_id']??'')!=='andromeda-1759-validated-92-v1'||($req['source_report_sha256']??'')!=='16158bb5d4e6ff7189512ead8a0d12227f85a8e9a1cb167fbe38b188d0df7857'||($req['append_only_decisions']??false)!==true||($req['supplier_calls']??-1)!==0||count($req['rows']??[])!==92) throw new RuntimeException('request');
 $wanted=[]; foreach($req['rows'] as $r){$e=(string)$r['external_hotel_id'];$l=(int)$r['local_hotel_id'];$c=(int)$r['country_id'];if(isset($wanted[$e])||$e===''||$l<=0||!in_array($c,[1,4],true))throw new RuntimeException('ids');$wanted[$e]=$r;}
 if(count(array_unique(array_map(fn($r)=>(int)$r['local_hotel_id'],$req['rows'])))!==92) throw new RuntimeException('duplicate_target');
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
 $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->beginTransaction();
 $all=$pdo->query("SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
 $index=[];$preserve=[];foreach($all as $row){$k=$row['supplier_namespace'].':'.$row['external_hotel_id'];$index[$k]=$row;if($row['supplier_namespace']!=='andromeda_catalog'||!isset($wanted[(string)$row['external_hotel_id']]))$preserve[$k]=$row;}
 $hotels=$pdo->query("SELECT id,name,country_id,region_name,subregion_name,is_active FROM catalog_hotels WHERE country_id IN (1,4) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
 $aliases=$pdo->query("SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN (1,4) AND h.is_active=1 ORDER BY a.hotel_id")->fetchAll(PDO::FETCH_ASSOC);
 $hotelBy=[];$nameIndex=[1=>[],4=>[]];foreach($hotels as $h){$id=(int)$h['id'];$hotelBy[$id]=$h;if((int)$h['is_active']===1){$k=name_key($h['name']);if($k!=='')$nameIndex[(int)$h['country_id']][$k][$id]=true;}}
 foreach($aliases as $a){$k=name_key($a['alias']);if($k!=='')$nameIndex[(int)$a['country_id']][$k][(int)$a['hotel_id']]=true;}
 $update=$pdo->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
 $updated=0;$read=[];
 foreach($wanted as $external=>$r){$old=$index['andromeda_catalog:'.$external]??null;if(!$old||$old['decision_status']!=='pending'||$old['local_hotel_id']!==null||$old['evidence_sha256']!==$r['expected_evidence_sha256'])throw new RuntimeException('stale_identity');
  $prior=json_decode($old['evidence_json'],true,64,JSON_THROW_ON_ERROR);$source=$prior['source']??null;if(!is_array($source)||strval($source['id']??'')!==$external||hash('sha256',json_encode($r['source'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false)throw new RuntimeException('source');
  if(norm_text($source['name']??'')!==norm_text($r['source']['name']??'')||norm_text($source['state']??'')!==norm_text($r['source']['state']??'')||norm_text($source['town']??'')!==norm_text($r['source']['town']??''))throw new RuntimeException('source_changed');
  $target=$hotelBy[(int)$r['local_hotel_id']]??null;if(!$target||(int)$target['is_active']!==1||(int)$target['country_id']!==(int)$r['country_id'])throw new RuntimeException('target');
  $expected=$r['expected_local'];if((int)($expected['id']??0)!==(int)$target['id']||norm_text($expected['name']??'')!==norm_text($target['name']??''))throw new RuntimeException('target_changed');
  $keys=array_unique(array_filter([name_key($source['name']??''),name_key($source['lName']??'')]));$candidates=[];foreach($keys as $k)foreach(array_keys($nameIndex[(int)$r['country_id']][$k]??[]) as $id)$candidates[(int)$id]=true;
  if(count($candidates)!==1||!isset($candidates[(int)$r['local_hotel_id']]))throw new RuntimeException('name_not_unique');
  $places=array_filter([norm_text($r['geography']['supplier_town']??''),norm_text($r['geography']['supplier_parent']??'')]);$localPlaces=array_filter([norm_text($target['region_name']??''),norm_text($target['subregion_name']??'')]);if(!array_intersect($places,$localPlaces))throw new RuntimeException('geo_changed');
  $evidence=['prior_evidence'=>$prior,'source'=>'validated_full_catalogue_geography_batch_20260910','source_report_sha256'=>$req['source_report_sha256'],'source_row_sha256'=>$r['source_row_sha256'],'target'=>$r['expected_local'],'geography'=>$r['geography'],'category_difference'=>(bool)$r['category_difference']];
  $ejson=json_encode($evidence,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$ehash=hash('sha256',$ejson);
  $update->execute([(int)$r['local_hotel_id'],$ehash,$ejson,$external,$r['expected_evidence_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('update');$updated++;
 }
 $after=$pdo->query("SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);$remaining=[];foreach($after as $row){$k=$row['supplier_namespace'].':'.$row['external_hotel_id'];if($row['supplier_namespace']==='andromeda_catalog'&&isset($wanted[(string)$row['external_hotel_id']])){$read[]=['external_hotel_id'=>(string)$row['external_hotel_id'],'local_hotel_id'=>(int)$row['local_hotel_id'],'decision_status'=>$row['decision_status'],'evidence_sha256'=>$row['evidence_sha256']];}else{$remaining[$k]=$row;}}
 if($remaining!==$preserve||count($after)!==count($all)||count($read)!==92)throw new RuntimeException('preservation');
 foreach($read as $r)if($r['decision_status']!=='accepted'||(int)$wanted[$r['external_hotel_id']]['local_hotel_id']!==$r['local_hotel_id'])throw new RuntimeException('readback');
 $counts=$pdo->query("SELECT decision_status,COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_KEY_PAIR);
 $pdo->commit();$out=['status'=>'accepted','operation_id'=>$req['operation_id'],'input_count'=>92,'updated'=>$updated,'readback_verified'=>true,'other_identities_unchanged'=>true,'rows'=>$read,'counts'=>$counts,'supplier_calls'=>0];
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','database_transaction_rolled_back'=>true,'supplier_calls'=>0];}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
