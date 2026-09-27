<?php
declare(strict_types=1);
/** Guarded SAMO31 writer. Including this file never opens a database. */
const S31_OP='hotel-match-samo31-writer-1971-20260927-v71';
const S31_AUDIT_SHA='1f6846947cefe390a9f0fc9fc6663d8cb1a73ca2b766ca57348311d53bc4cb04';
const S31_PAIRS=[167=>'261102',242=>'244469',320=>'405440',856=>'174700',898=>'73024',1299=>'2000123210',1734=>'129812',1735=>'5767',1736=>'298738',1737=>'150070',1739=>'450605',1760=>'207647',1773=>'37255',1781=>'315584',3111=>'141649',4100=>'105904',15819=>'2000028170',22632=>'450630',23214=>'394688',23668=>'2000073524',45140=>'2000034456',51062=>'2000034535',68667=>'10866',4326=>'309768',55945=>'2000037585',56479=>'2000093384',67304=>'2000055490',76753=>'2000087342',108356=>'269426',116886=>'2000103169',121109=>'2000090159'];
function s31_need(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
function s31_json($v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function s31_save(string $p,array $v):string{$raw=s31_json($v)."\n";$f=@fopen($p,'x+b');s31_need($f!==false,'exclusive_record');try{s31_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'record_write');if(function_exists('fsync'))s31_need(fsync($f),'record_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function s31_q(PDO $db,string $sql,array $args=[]):array{$s=$db->prepare($sql);s31_need($s!==false&&$s->execute(array_values($args)),'query');return $s->fetchAll(PDO::FETCH_ASSOC);}
function s31_main(array $argv):int{
 s31_need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','disabled');
 $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 s31_need(is_dir($root)&&!is_link($root)&&is_dir($dir)&&!is_link($dir)&&basename($dir)===S31_OP&&preg_match('/^[0-9a-f]{40}$/D',$head)===1,'runtime_scope');
 $manifest=json_decode(file_get_contents((string)getenv('MATCH_MANIFEST')),true,64,JSON_THROW_ON_ERROR);
 s31_need(($manifest['schema']??'')==='hotel_match_samo31_current_ready_v71'&&($manifest['source_audit_sha256']??'')===S31_AUDIT_SHA,'manifest');
 $pairs=[];foreach($manifest['exact_pairs'] as $p){s31_need(is_array($p)&&count($p)===2,'pair');$pairs[(int)$p[0]]=(string)$p[1];}ksort($pairs,SORT_NUMERIC);$expect=S31_PAIRS;ksort($expect,SORT_NUMERIC);s31_need($pairs===$expect&&count($pairs)===31,'exact_allowlist');
 $reservation=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);s31_need(($reservation['operation']??'')===S31_OP&&($reservation['source_sha']??'')===$head&&($reservation['state']??'')==='reserved_before_db_write','reservation');
 foreach(['execution-started.json','result.json','receipt.json','commit-attempt.json'] as $f)s31_need(!file_exists($dir.'/'.$f),'no_replay');
 s31_save($dir.'/execution-started.json',['operation'=>S31_OP,'source_sha'=>$head,'state'=>'reserved_before_db_access']);
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');s31_need(function_exists('v2_data_db'),'db_factory');$db=v2_data_db();s31_need($db instanceof PDO,'db');
 $attempt=false;$committed=false;$writes=0;$transitions=0;$inserted=0;
 try{
  foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions'] as $t){$e=s31_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);s31_need(count($e)===1&&strtoupper((string)$e[0]['ENGINE'])==='INNODB','transactional');}
  $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');s31_need($db->beginTransaction(),'begin');
  $ids=array_keys($pairs);$cats=array_values($pairs);$ih=implode(',',array_fill(0,count($ids),'?'));$ch=implode(',',array_fill(0,count($cats),'?'));
  $hot=[];foreach(s31_q($db,"SELECT id,name,country_name,is_active FROM catalog_hotels WHERE id IN ($ih) ORDER BY id FOR UPDATE",$ids) as $r)$hot[(int)$r['id']]=$r;
  $live=[];$args=$ids;$args[]=gmdate('Y-m-d H:i:s',time()-30*86400);foreach(s31_q($db,"SELECT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ih) AND last_seen_at>=? ORDER BY hotel_id FOR UPDATE",$args) as $r)$live[(int)$r['hotel_id']]=true;
  $target=[];foreach(s31_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_json FROM andromeda_hotel_identities WHERE (supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ch)) OR local_hotel_id IN ($ih) ORDER BY supplier_namespace,external_hotel_id FOR UPDATE",array_merge($cats,$ids)) as $r){$target[]=$r;}
  $bySource=[];$byLocal=[];foreach($target as $r){if($r['supplier_namespace']==='andromeda_catalog')$bySource[(string)$r['external_hotel_id']][]=$r;$byLocal[(int)$r['local_hotel_id']][]=$r;}
  $manual=[];foreach(s31_q($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ih) ORDER BY catalog_hotel_id FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']]=true;
  $planned=[];
  foreach($pairs as $id=>$cat){
   s31_need(isset($hot[$id])&&(int)$hot[$id]['is_active']===1&&isset($live[$id])&&!isset($manual[$id]),'target_guard_'.$id);
   $src=$bySource[$cat]??[];
   if($src){
    s31_need(count($src)===1&&(int)$src[0]['local_hotel_id']===$id,'source_occupied_'.$id);
    s31_need((string)$src[0]['decision_status']!=='accepted','already_accepted_'.$id);
    $planned[]=['mode'=>'transition','id'=>$id,'cat'=>$cat];
   }else{
    foreach($byLocal[$id]??[] as $r)s31_need($r['supplier_namespace']!=='andromeda_catalog','target_catalog_occupied_'.$id);
    $planned[]=['mode'=>'insert','id'=>$id,'cat'=>$cat];
   }
  }
  s31_need(count($planned)===31,'planned_count');
  s31_save($dir.'/write-plan.json',['operation'=>S31_OP,'source_sha'=>$head,'rows'=>$planned,'state'=>'verified_before_sql']);
  $ins=$db->prepare("INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES ('andromeda_catalog',?,?,'accepted',?,?,?)");
  $upd=$db->prepare("UPDATE andromeda_hotel_identities SET decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id=? AND decision_status<>'accepted'");
  foreach($planned as $p){$ev=s31_json(['operation_id'=>S31_OP,'rule'=>'fresh_current_samo31_v71','source_sha'=>$head,'audit_sha256'=>S31_AUDIT_SHA,'target'=>$hot[$p['id']],'catalog_id'=>$p['cat'],'mode'=>$p['mode'],'provider_http_calls'=>0]);$eh=hash('sha256',$ev);
   if($p['mode']==='insert'){$cs=hash('sha256',s31_json(['catalog_id'=>$p['cat'],'audit_sha256'=>S31_AUDIT_SHA]));s31_need($ins->execute([$p['cat'],$p['id'],$cs,$eh,$ev])&&$ins->rowCount()===1,'insert_'.$p['id']);$inserted++;}
   else{s31_need($upd->execute([$eh,$ev,$p['cat'],$p['id']])&&$upd->rowCount()===1,'transition_'.$p['id']);$transitions++;}
  }
  $writes=$inserted+$transitions;s31_need($writes===31,'write_count');
  $check=s31_q($db,"SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ch) ORDER BY external_hotel_id",$cats);
  $seen=[];foreach($check as $r){$seen[(string)$r['external_hotel_id']]=(int)$r['local_hotel_id'];s31_need($r['decision_status']==='accepted','precommit_status');}foreach($pairs as $id=>$cat)s31_need(($seen[$cat]??null)===$id,'precommit_pair');
  s31_save($dir.'/commit-attempt.json',['operation'=>S31_OP,'source_sha'=>$head,'state'=>'commit_attempt_no_replay','writes'=>31]);$attempt=true;s31_need($db->commit(),'commit');$committed=true;
  $post=s31_q($db,"SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ch) ORDER BY external_hotel_id",$cats);$seen=[];foreach($post as $r){$seen[(string)$r['external_hotel_id']]=(int)$r['local_hotel_id'];s31_need($r['decision_status']==='accepted','post_status');}foreach($pairs as $id=>$cat)s31_need(($seen[$cat]??null)===$id,'post_pair');
  $result=['operation'=>S31_OP,'state'=>'committed_readback_verified','source_sha'=>$head,'inserted'=>$inserted,'pending_transitions'=>$transitions,'database_writes'=>31,'mapping_writes'=>31,'readback_verified'=>true,'provider_http_calls'=>0,'no_replay'=>true,'rows'=>$post];
 }catch(Throwable $e){if(!$attempt&&$db->inTransaction())$db->rollBack();$result=['operation'=>S31_OP,'state'=>$attempt?($committed?'committed_readback_unconfirmed':'commit_outcome_unknown_no_replay'):'rolled_back_no_writes','reason'=>preg_match('/^[a-z0-9_]+$/',$e->getMessage())?$e->getMessage():'writer_failed','source_sha'=>$head,'database_writes'=>$attempt?null:0,'mapping_writes'=>$attempt?null:0,'readback_verified'=>false,'provider_http_calls'=>0,'no_replay'=>true,'rows'=>[]];}
 $sha=s31_save($dir.'/result.json',$result);s31_save($dir.'/receipt.json',['operation'=>S31_OP,'state'=>$result['state'],'source_sha'=>$head,'result_sha256'=>$sha,'readback_verified'=>$result['readback_verified'],'database_writes'=>$result['database_writes'],'mapping_writes'=>$result['mapping_writes'],'provider_http_calls'=>0,'no_replay'=>true]);echo s31_json(array_diff_key($result,['rows'=>true]))."\n";return $result['state']==='committed_readback_verified'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(s31_main($argv));
