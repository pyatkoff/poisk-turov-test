<?php
declare(strict_types=1);
const OP='hotel-match-anexkey-current-accept-1971-20260912-v1';
const POLICY='owner_exact_operator_key_20260912';
function j($v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function h($v):string{return hash('sha256',j($v));}
function save_once(string $path,array $data):void{$f=fopen($path,'x');if(!$f)throw new RuntimeException('receipt_exists');chmod($path,0600);$raw=j($data)."\n";fwrite($f,$raw);fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);if(file_get_contents($path)!==$raw)throw new RuntimeException('receipt_readback');}
$plan=[
['bucket'=>'safe_missing_anex_side','anex_id'=>'1102','andromeda_id'=>'2000024112','local_id'=>124,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'1351','andromeda_id'=>'2000041404','local_id'=>206,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'762','andromeda_id'=>'536','local_id'=>288,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'770','andromeda_id'=>'5428','local_id'=>297,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'832','andromeda_id'=>'2000043265','local_id'=>325,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'37037','andromeda_id'=>'2000067402','local_id'=>417,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'537','andromeda_id'=>'530023','local_id'=>515,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'1783','andromeda_id'=>'2000071056','local_id'=>1960,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'34426','andromeda_id'=>'2000084254','local_id'=>75345,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'32610','andromeda_id'=>'2000035006','local_id'=>17528,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32772','andromeda_id'=>'2000060795','local_id'=>17617,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32421','andromeda_id'=>'321591','local_id'=>28678,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'4293','andromeda_id'=>'357404','local_id'=>109,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'1640','andromeda_id'=>'105369','local_id'=>136,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'4516','andromeda_id'=>'32167','local_id'=>154,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'510','andromeda_id'=>'873','local_id'=>224,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'43477','andromeda_id'=>'2000113581','local_id'=>143789,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32593','andromeda_id'=>'2000041100','local_id'=>17457,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'32556','andromeda_id'=>'2000021300','local_id'=>21811,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'35688','andromeda_id'=>'2000021972','local_id'=>53527,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'41882','andromeda_id'=>'2000087867','local_id'=>59105,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'35835','andromeda_id'=>'2000064695','local_id'=>70807,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'39641','andromeda_id'=>'2000098372','local_id'=>123757,'country'=>'turkey','recurrence'=>1],
];
if(in_array('--self-test',$_SERVER['argv']??[],true)){if(count($plan)!==23)exit(2);$d=[];$a=[];foreach($plan as$r){if(isset($d[$r['andromeda_id']])||isset($a[$r['anex_id']]))exit(3);$d[$r['andromeda_id']]=1;$a[$r['anex_id']]=1;}echo "MATCH anexkey accept self-test PASS rows=23\n";exit;}
error_reporting(0);ob_start();$db=null;$dir=null;$commitAttempted=false;$committed=false;$written=[];$skipped=[];
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
 $home=realpath((string)getenv('HOME'));if(!$home)throw new RuntimeException('home');$base=$home.'/.anytoour-match/operations';if(!is_dir($base)&&!mkdir($base,0700,true))throw new RuntimeException('receipt_root');$dir=$base.'/'.OP;if(file_exists($dir)||!mkdir($dir,0700)){$dir=null;throw new RuntimeException('prior_operation_no_replay');}
 save_once($dir.'/reservation.json',['operation_id'=>OP,'status'=>'reserved_before_db_access','planned'=>count($plan),'no_replay'=>true]);
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
 $ins=$db->prepare('INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,?,?,?,?,?,1)');
 $upd=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_json=?,evidence_sha256=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id IS NULL AND decision_status='pending' AND evidence_sha256=?");
 foreach($plan as$r){$aid=(string)$r['anex_id'];$did=(string)$r['andromeda_id'];$lid=(int)$r['local_id'];
  $q=$db->prepare('SELECT id,country_name,is_active FROM catalog_hotels WHERE id=? FOR UPDATE');$q->execute([$lid]);$hotel=$q->fetch(PDO::FETCH_ASSOC);if(!$hotel||(int)$hotel['is_active']!==1){$skipped[]=$r+['reason'=>'target_inactive'];continue;}
  $q=$db->prepare('SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 1 FOR UPDATE');$q->execute([$aid]);$manual=(bool)$q->fetchColumn();$q=$db->prepare('SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? LIMIT 1 FOR UPDATE');$q->execute([$aid]);$excluded=(bool)$q->fetchColumn();if($manual||$excluded){$skipped[]=$r+['reason'=>'manual_or_exclusion'];continue;}
  $q=$db->prepare('SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? ORDER BY catalog_hotel_id FOR UPDATE');$q->execute([$aid]);$maps=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_json,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");$q->execute([$did]);$and=$q->fetch(PDO::FETCH_ASSOC);
  if($r['bucket']==='safe_missing_anex_side'){
   if($maps){$skipped[]=$r+['reason'=>'anex_now_mapped'];continue;}if(!$and||$and['decision_status']!=='accepted'||(int)$and['local_hotel_id']!==$lid){$skipped[]=$r+['reason'=>'andromeda_anchor_changed'];continue;}
   $proof=['operation_id'=>OP,'rule'=>'andromeda_original_hotelkey_plus_direct_anex_exact_current_anchor','anex_id'=>$aid,'andromeda_id'=>$did,'local_id'=>$lid,'country'=>$r['country'],'recurrence'=>$r['recurrence']];$sd=h($proof);$md=h(['anex_hotel_id'=>(int)$aid,'catalog_hotel_id'=>$lid,'scope'=>'preview','approval_policy'=>POLICY,'source_row_digest'=>$sd]);$ins->execute([(int)$aid,$lid,'exact_operator_key','preview',POLICY,$sd,$md]);if($ins->rowCount()!==1)throw new RuntimeException('anex_insert_count');$written[]=$r+['mapping_digest'=>$md,'source_row_digest'=>$sd];
  }else{
   if(count($maps)!==1||(int)$maps[0]['enabled']!==1||(int)$maps[0]['catalog_hotel_id']!==$lid){$skipped[]=$r+['reason'=>'anex_anchor_changed'];continue;}if(!$and||$and['decision_status']!=='pending'||$and['local_hotel_id']!==null){$skipped[]=$r+['reason'=>'andromeda_now_protected'];continue;}
   $prior=json_decode((string)$and['evidence_json'],true);$proof=['operation_id'=>OP,'rule'=>'andromeda_original_hotelkey_plus_direct_anex_exact_current_anchor','anex_id'=>$aid,'andromeda_id'=>$did,'local_id'=>$lid,'country'=>$r['country'],'recurrence'=>$r['recurrence']];$ej=j(['prior_evidence'=>$prior,'proof'=>$proof]);$eh=hash('sha256',$ej);$upd->execute([$lid,$ej,$eh,$did,$and['evidence_sha256']]);if($upd->rowCount()!==1)throw new RuntimeException('andromeda_update_count');$written[]=$r+['evidence_sha256'=>$eh];
  }
 }
 save_once($dir.'/precommit-intent.json',['operation_id'=>OP,'written'=>$written,'skipped'=>$skipped,'no_replay'=>true]);$commitAttempted=true;$db->commit();$committed=true;
 $db->exec('START TRANSACTION READ ONLY');foreach($written as$w){if($w['bucket']==='safe_missing_anex_side'){$q=$db->prepare('SELECT catalog_hotel_id,enabled,mapping_digest,source_row_digest FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');$q->execute([$w['anex_id']]);$x=$q->fetchAll(PDO::FETCH_ASSOC);if(count($x)!==1||(int)$x[0]['catalog_hotel_id']!==(int)$w['local_id']||(int)$x[0]['enabled']!==1||$x[0]['mapping_digest']!==$w['mapping_digest']||$x[0]['source_row_digest']!==$w['source_row_digest'])throw new RuntimeException('readback_anex');}else{$q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");$q->execute([$w['andromeda_id']]);$x=$q->fetch(PDO::FETCH_ASSOC);if(!$x||(int)$x['local_hotel_id']!==(int)$w['local_id']||$x['decision_status']!=='accepted'||$x['evidence_sha256']!==$w['evidence_sha256'])throw new RuntimeException('readback_andromeda');}}$db->exec('ROLLBACK');
 $counts=['anex'=>0,'andromeda'=>0];foreach($written as$w)++$counts[$w['bucket']==='safe_missing_anex_side'?'anex':'andromeda'];$out=['status'=>'accepted','operation_id'=>OP,'planned'=>count($plan),'written_count'=>count($written),'provider_counts'=>$counts,'skipped_count'=>count($skipped),'skipped'=>$skipped,'readback_verified'=>true,'database_writes'=>count($written),'mapping_writes'=>count($written),'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];save_once($dir.'/result.json',$out);
}catch(Throwable$e){try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable$ignored){}$out=['status'=>$commitAttempted?'commit_outcome_requires_readback':'rolled_back_or_not_started','operation_id'=>OP,'safe_message'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'guard_failure','commit_attempted'=>$commitAttempted,'commit_confirmed'=>$committed,'database_writes'=>$commitAttempted?null:0,'mapping_writes'=>$commitAttempted?null:0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];if($dir&&is_dir($dir)&&!file_exists($dir.'/failure.json')){try{save_once($dir.'/failure.json',$out);}catch(Throwable$ignored){}}}
while(ob_get_level())ob_end_clean();echo 'MATCH_ANEXKEY_ACCEPT:'.j($out).PHP_EOL;exit($out['status']==='accepted'?0:2);
