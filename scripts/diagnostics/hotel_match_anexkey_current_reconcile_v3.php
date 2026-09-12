<?php
declare(strict_types=1);
const OP='hotel-match-anexkey-current-reconcile-1971-20260912-v3';
const POLICY='owner_exact_operator_key_20260912_v3';
function j($v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function h($v):string{return hash('sha256',j($v));}
function save_once(string $path,array $data):void{$f=fopen($path,'x');if(!$f)throw new RuntimeException('receipt_exists');chmod($path,0600);$raw=j($data)."\n";fwrite($f,$raw);fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);if(file_get_contents($path)!==$raw)throw new RuntimeException('receipt_readback');}
$plan=[
['bucket'=>'safe_missing_anex_side','anex_id'=>'1102','andromeda_id'=>'2000024112','local_id'=>124,'country'=>'egypt','recurrence'=>10],
['bucket'=>'safe_missing_anex_side','anex_id'=>'7212','andromeda_id'=>'35534','local_id'=>38343,'country'=>'turkey','recurrence'=>7],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32552','andromeda_id'=>'2000030484','local_id'=>113689,'country'=>'turkey','recurrence'=>7],
['bucket'=>'safe_missing_anex_side','anex_id'=>'39184','andromeda_id'=>'114597','local_id'=>53991,'country'=>'turkey','recurrence'=>7],
['bucket'=>'safe_missing_anex_side','anex_id'=>'43791','andromeda_id'=>'871043','local_id'=>122044,'country'=>'turkey','recurrence'=>7],
['bucket'=>'safe_missing_anex_side','anex_id'=>'8417','andromeda_id'=>'163877','local_id'=>17884,'country'=>'turkey','recurrence'=>6],
['bucket'=>'safe_missing_anex_side','anex_id'=>'9649','andromeda_id'=>'96645','local_id'=>42978,'country'=>'turkey','recurrence'=>6],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32551','andromeda_id'=>'2000030405','local_id'=>46774,'country'=>'turkey','recurrence'=>6],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'45394','andromeda_id'=>'2000045633','local_id'=>20536,'country'=>'turkey','recurrence'=>6],
['bucket'=>'safe_missing_anex_side','anex_id'=>'104506','andromeda_id'=>'2000104189','local_id'=>137592,'country'=>'turkey','recurrence'=>6],
['bucket'=>'safe_missing_anex_side','anex_id'=>'7551','andromeda_id'=>'2000109083','local_id'=>16510,'country'=>'turkey','recurrence'=>5],
['bucket'=>'safe_missing_anex_side','anex_id'=>'8367','andromeda_id'=>'2000025289','local_id'=>17669,'country'=>'turkey','recurrence'=>5],
['bucket'=>'safe_missing_anex_side','anex_id'=>'26183','andromeda_id'=>'2000065261','local_id'=>137418,'country'=>'turkey','recurrence'=>5],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'38282','andromeda_id'=>'992808','local_id'=>64149,'country'=>'turkey','recurrence'=>5],
['bucket'=>'safe_missing_anex_side','anex_id'=>'63381','andromeda_id'=>'2000075547','local_id'=>133990,'country'=>'turkey','recurrence'=>5],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32706','andromeda_id'=>'74761','local_id'=>17503,'country'=>'turkey','recurrence'=>4],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'75807','andromeda_id'=>'1176579','local_id'=>139644,'country'=>'turkey','recurrence'=>4],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'80989','andromeda_id'=>'2000079648','local_id'=>135003,'country'=>'turkey','recurrence'=>4],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32731','andromeda_id'=>'2000049133','local_id'=>65057,'country'=>'turkey','recurrence'=>3],
['bucket'=>'safe_missing_anex_side','anex_id'=>'56577','andromeda_id'=>'2000017948','local_id'=>126053,'country'=>'turkey','recurrence'=>3],
['bucket'=>'safe_missing_anex_side','anex_id'=>'58875','andromeda_id'=>'2000057810','local_id'=>131107,'country'=>'turkey','recurrence'=>3],
['bucket'=>'safe_missing_anex_side','anex_id'=>'133239','andromeda_id'=>'2000133239','local_id'=>33101,'country'=>'egypt','recurrence'=>3],
['bucket'=>'safe_missing_anex_side','anex_id'=>'86531','andromeda_id'=>'2000050387','local_id'=>132155,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'105055','andromeda_id'=>'2000051996','local_id'=>141815,'country'=>'egypt','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'6410','andromeda_id'=>'235056','local_id'=>2706,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'9997','andromeda_id'=>'40287','local_id'=>12069,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'10324','andromeda_id'=>'2000005607','local_id'=>19560,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'13482','andromeda_id'=>'2000017627','local_id'=>4514,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'24645','andromeda_id'=>'2000026455','local_id'=>14536,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32566','andromeda_id'=>'2000032666','local_id'=>46585,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32982','andromeda_id'=>'205760','local_id'=>65490,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'44534','andromeda_id'=>'2000130416','local_id'=>78432,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'45591','andromeda_id'=>'220157','local_id'=>21327,'country'=>'turkey','recurrence'=>2],
['bucket'=>'safe_missing_anex_side','anex_id'=>'518','andromeda_id'=>'81090','local_id'=>19382,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'1327','andromeda_id'=>'1282','local_id'=>127,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'1978','andromeda_id'=>'737902','local_id'=>38502,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'5200','andromeda_id'=>'516728','local_id'=>21477,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'5834','andromeda_id'=>'2000007154','local_id'=>2770,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32599','andromeda_id'=>'2000032800','local_id'=>46679,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'32704','andromeda_id'=>'2000033843','local_id'=>46840,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'38179','andromeda_id'=>'2000043432','local_id'=>64123,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'39916','andromeda_id'=>'2000045158','local_id'=>67672,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'57161','andromeda_id'=>'2000058759','local_id'=>135907,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'71571','andromeda_id'=>'2000016402','local_id'=>126050,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'96408','andromeda_id'=>'2000024275','local_id'=>137062,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'103264','andromeda_id'=>'2000058513','local_id'=>135730,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'118996','andromeda_id'=>'2000118996','local_id'=>127015,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'139637','andromeda_id'=>'779343','local_id'=>137915,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'139900','andromeda_id'=>'2000149165','local_id'=>150137,'country'=>'turkey','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'140403','andromeda_id'=>'2000083496','local_id'=>135711,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_anex_side','anex_id'=>'140675','andromeda_id'=>'2000171047','local_id'=>151402,'country'=>'egypt','recurrence'=>1],
['bucket'=>'safe_missing_andromeda_side','anex_id'=>'140734','andromeda_id'=>'2000171132','local_id'=>151460,'country'=>'egypt','recurrence'=>1]
];
if(in_array('--self-test',$_SERVER['argv']??[],true)){
 if(count($plan)!==51)exit(2);
 $targets=[];$pairs=[];
 foreach($plan as $r){$provider=$r['bucket']==='safe_missing_anex_side'?'anex':'andromeda';$pk=$provider==='anex'?$r['anex_id']:$r['andromeda_id'];$k=$provider.':'.$pk;if(isset($targets[$k]))exit(3);$targets[$k]=1;$pair=$r['andromeda_id'].':'.$r['anex_id'].':'.$r['local_id'];if(isset($pairs[$pair]))exit(4);$pairs[$pair]=1;}
 echo "MATCH anexkey reconcile v3 self-test PASS rows=51\n";exit;
}
error_reporting(0);ob_start();$db=null;$dir=null;$commitAttempted=false;$committed=false;$written=[];$skipped=[];
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');$home=realpath((string)getenv('HOME'));if(!$home)throw new RuntimeException('home');$base=$home.'/.anytoour-match/operations';if(!is_dir($base)&&!mkdir($base,0700,true))throw new RuntimeException('receipt_root');$dir=$base.'/'.OP;if(file_exists($dir)||!mkdir($dir,0700)){$dir=null;throw new RuntimeException('prior_operation_no_replay');}
 save_once($dir.'/reservation.json',['operation_id'=>OP,'status'=>'reserved_before_db_access','planned'=>count($plan),'source'=>'datesweep-v2-run34697225669-artifact10299441040-current-reconcile','excluded_andromeda_keys'=>['2000025459','13802','2000041100','67774'],'excluded_anex_ids'=>['44411'],'no_replay'=>true]);
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
 $ins=$db->prepare('INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,?,?,?,?,?,1)');
 $upd=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_json=?,evidence_sha256=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id IS NULL AND decision_status='pending' AND evidence_sha256=?");
 foreach($plan as$r){$aid=(string)$r['anex_id'];$did=(string)$r['andromeda_id'];$lid=(int)$r['local_id'];$q=$db->prepare('SELECT id,is_active FROM catalog_hotels WHERE id=? FOR UPDATE');$q->execute([$lid]);$hotel=$q->fetch(PDO::FETCH_ASSOC);if(!$hotel||(int)$hotel['is_active']!==1){$skipped[]=$r+['reason'=>'target_inactive'];continue;}$q=$db->prepare('SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? LIMIT 1 FOR UPDATE');$q->execute([$aid]);$manual=(bool)$q->fetchColumn();$q=$db->prepare('SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? LIMIT 1 FOR UPDATE');$q->execute([$aid]);$excluded=(bool)$q->fetchColumn();if($manual||$excluded){$skipped[]=$r+['reason'=>'manual_or_exclusion'];continue;}$q=$db->prepare('SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? ORDER BY catalog_hotel_id FOR UPDATE');$q->execute([$aid]);$maps=$q->fetchAll(PDO::FETCH_ASSOC);$q=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_json,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");$q->execute([$did]);$and=$q->fetch(PDO::FETCH_ASSOC);
  if($r['bucket']==='safe_missing_anex_side'){if($maps){$exact=count($maps)===1&&(int)$maps[0]['enabled']===1&&(int)$maps[0]['catalog_hotel_id']===$lid;$skipped[]=$r+['reason'=>$exact?'already_exact':'anex_now_mapped_other'];continue;}if(!$and||$and['decision_status']!=='accepted'||(int)$and['local_hotel_id']!==$lid){$skipped[]=$r+['reason'=>'andromeda_anchor_changed'];continue;}$proof=['operation_id'=>OP,'rule'=>'andromeda_original_hotelkey_current_anchor_reconcile_v3']+$r;$sd=h($proof);$md=h(['anex_hotel_id'=>(int)$aid,'catalog_hotel_id'=>$lid,'scope'=>'preview','approval_policy'=>POLICY,'source_row_digest'=>$sd]);$ins->execute([(int)$aid,$lid,'exact_operator_key','preview',POLICY,$sd,$md]);if($ins->rowCount()!==1)throw new RuntimeException('anex_insert_count');$written[]=$r+['mapping_digest'=>$md,'source_row_digest'=>$sd];}
  else{if(count($maps)!==1||(int)$maps[0]['enabled']!==1||(int)$maps[0]['catalog_hotel_id']!==$lid){$skipped[]=$r+['reason'=>'anex_anchor_changed'];continue;}if(!$and){$skipped[]=$r+['reason'=>'andromeda_missing'];continue;}if($and['decision_status']==='accepted'&&(int)$and['local_hotel_id']===$lid){$skipped[]=$r+['reason'=>'already_exact'];continue;}if($and['decision_status']!=='pending'||$and['local_hotel_id']!==null){$skipped[]=$r+['reason'=>'andromeda_now_protected'];continue;}$prior=json_decode((string)$and['evidence_json'],true);$proof=['operation_id'=>OP,'rule'=>'andromeda_original_hotelkey_current_anchor_reconcile_v3']+$r;$ej=j(['prior_evidence'=>$prior,'proof'=>$proof]);$eh=hash('sha256',$ej);$upd->execute([$lid,$ej,$eh,$did,$and['evidence_sha256']]);if($upd->rowCount()!==1)throw new RuntimeException('andromeda_update_count');$written[]=$r+['evidence_sha256'=>$eh];}}
 save_once($dir.'/precommit-intent.json',['operation_id'=>OP,'written'=>$written,'skipped'=>$skipped,'no_replay'=>true]);$commitAttempted=true;$db->commit();$committed=true;$db->exec('START TRANSACTION READ ONLY');foreach($written as$w){if($w['bucket']==='safe_missing_anex_side'){$q=$db->prepare('SELECT catalog_hotel_id,enabled,mapping_digest,source_row_digest FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');$q->execute([$w['anex_id']]);$x=$q->fetchAll(PDO::FETCH_ASSOC);if(count($x)!==1||(int)$x[0]['catalog_hotel_id']!==(int)$w['local_id']||(int)$x[0]['enabled']!==1||$x[0]['mapping_digest']!==$w['mapping_digest'])throw new RuntimeException('readback_anex');}else{$q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");$q->execute([$w['andromeda_id']]);$x=$q->fetch(PDO::FETCH_ASSOC);if(!$x||(int)$x['local_hotel_id']!==(int)$w['local_id']||$x['decision_status']!=='accepted'||$x['evidence_sha256']!==$w['evidence_sha256'])throw new RuntimeException('readback_andromeda');}}$db->exec('ROLLBACK');$counts=['anex'=>0,'andromeda'=>0];foreach($written as$w)++$counts[$w['bucket']==='safe_missing_anex_side'?'anex':'andromeda'];$reasons=[];foreach($skipped as$s){$k=$s['reason'];$reasons[$k]=($reasons[$k]??0)+1;}ksort($reasons);$out=['status'=>'accepted','operation_id'=>OP,'planned'=>count($plan),'written_count'=>count($written),'provider_counts'=>$counts,'skipped_count'=>count($skipped),'skip_reasons'=>$reasons,'skipped'=>$skipped,'readback_verified'=>true,'database_writes'=>count($written),'mapping_writes'=>count($written),'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];save_once($dir.'/result.json',$out);
}catch(Throwable$e){try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable$ignored){}$out=['status'=>$commitAttempted?'commit_outcome_requires_readback':'rolled_back_or_not_started','operation_id'=>OP,'safe_message'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'guard_failure','commit_attempted'=>$commitAttempted,'commit_confirmed'=>$committed,'database_writes'=>$commitAttempted?null:0,'mapping_writes'=>$commitAttempted?null:0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];if($dir&&is_dir($dir)&&!file_exists($dir.'/failure.json')){try{save_once($dir.'/failure.json',$out);}catch(Throwable$ignored){}}}
while(ob_get_level())ob_end_clean();echo 'MATCH_ANEXKEY_RECONCILE_V3:'.j($out).PHP_EOL;exit(($out['status']??'')==='accepted'?0:2);
