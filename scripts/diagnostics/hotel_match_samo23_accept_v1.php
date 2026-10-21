<?php
declare(strict_types=1);
const OP='hotel-match-samo23-accept-1971-20260927-v1';
const AUDIT_SHA='1f6846947cefe390a9f0fc9fc6663d8cb1a73ca2b766ca57348311d53bc4cb04';
const PAIRS=[167=>'261102',242=>'244469',320=>'405440',856=>'174700',898=>'73024',1299=>'2000123210',1734=>'129812',1735=>'5767',1736=>'298738',1737=>'150070',1739=>'450605',1760=>'207647',1773=>'37255',1781=>'315584',3111=>'141649',4100=>'105904',15819=>'2000028170',22632=>'450630',23214=>'394688',23668=>'2000073524',45140=>'2000034456',51062=>'2000034535',68667=>'10866'];
function need($x,$m){if(!$x)throw new RuntimeException($m);}
function main($argv){
 need(($argv[1]??'')==='--execute','disabled');$root=getenv('ANYTOUR_ROOT');$dir=getenv('MATCH_OPERATION_DIR');$audit=getenv('MATCH_AUDIT_RESULT');
 need(is_dir($root)&&is_dir($dir)&&is_file($audit)&&hash_file('sha256',$audit)===AUDIT_SHA,'scope');$a=json_decode(file_get_contents($audit),true,128,JSON_THROW_ON_ERROR);need($a['state']==='completed_read_only_current_audit'&&$a['input_count']===37,'audit_state');
 $ready=[];foreach($a['rows'] as $r)if($r['status']==='ready_source_missing')$ready[(int)$r['tv_hotel_id']]=(string)$r['catalog_id'];ksort($ready);need($ready===PAIRS,'audit_pairs');
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$planned=[];$phase='guards';
 try{$cut=gmdate('Y-m-d H:i:s',time()-30*86400);
  foreach(PAIRS as $id=>$cat){
   $q=$db->prepare('SELECT id,name,is_active FROM catalog_hotels WHERE id=? FOR UPDATE');$q->execute([$id]);$h=$q->fetch(PDO::FETCH_ASSOC);need($h&&(int)$h['is_active']===1,'target_inactive');
   $q=$db->prepare("SELECT local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE");$q->execute([$cat]);need($q->fetchAll(PDO::FETCH_ASSOC)===[],'source_not_missing');
   $q=$db->prepare("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND local_hotel_id=? AND decision_status='accepted' FOR UPDATE");$q->execute([$id]);need($q->fetchAll(PDO::FETCH_COLUMN)===[],'target_catalog_occupied');
   $q=$db->prepare('SELECT COUNT(*) FROM tour_operator_identity_observations WHERE hotel_id=? AND last_seen_at>=?');$q->execute([$id,$cut]);need((int)$q->fetchColumn()>0,'not_live30');
   $q=$db->prepare("SELECT COUNT(*) FROM anex_hotel_decisions WHERE catalog_hotel_id=? AND decision_status<>'accepted'");$q->execute([$id]);need((int)$q->fetchColumn()===0,'manual_anex_protection');
   $q=$db->prepare("SELECT COUNT(*) FROM anex_hotel_decisions d WHERE d.catalog_hotel_id=? AND d.decision_status='accepted' AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)");$q->execute([$id]);$dec=(int)$q->fetchColumn();
   $q=$db->prepare("SELECT COUNT(*) FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.catalog_hotel_id=? AND m.enabled=1 AND m.scope='preview' AND m.approval_policy='owner_exact_and_strong_20260908' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)");$q->execute([$id]);need($dec+(int)$q->fetchColumn()>0,'direct_anex_missing');
   $ev=json_encode(['operation_id'=>OP,'rule'=>'v65_strict_multi_lane_current_guarded_v1','prior_current_audit_sha256'=>AUDIT_SHA,'local_hotel_id'=>$id,'andromeda_catalog_id'=>$cat,'provider_http_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$planned[]=[$id,$cat,$ev,hash('sha256',$ev)];
  }
  need(count($planned)===23,'planned_count');file_put_contents($dir.'/pre-commit.json',json_encode(['operation'=>OP,'state'=>'verified_before_insert','count'=>23],JSON_PRETTY_PRINT)."\n");$phase='insert';
  $st=$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('andromeda_catalog',?,?,'accepted',?,?,?)");foreach($planned as [$id,$cat,$ev,$eh]){$st->execute([$cat,$id,AUDIT_SHA,$eh,$ev]);need($st->rowCount()===1,'insert_count');}
  file_put_contents($dir.'/commit-attempt.json',json_encode(['operation'=>OP,'state'=>'commit_attempt_no_replay','count'=>23])."\n");$phase='commit';need($db->commit(),'commit_false');$phase='readback';$rows=[];
  $q=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");foreach($planned as [$id,$cat,$ev,$eh]){$q->execute([$cat]);$x=$q->fetchAll(PDO::FETCH_ASSOC);need(count($x)===1&&(int)$x[0]['local_hotel_id']===$id&&$x[0]['decision_status']==='accepted'&&hash('sha256',$x[0]['evidence_json'])===$x[0]['evidence_sha256'],'readback');$rows[]=['tv_hotel_id'=>$id,'catalog_id'=>$cat];}
  $res=['operation'=>OP,'state'=>'committed_verified','inserted'=>23,'database_writes'=>23,'mapping_writes'=>23,'provider_http_calls'=>0,'readback_verified'=>true,'rows'=>$rows];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$res=['operation'=>OP,'state'=>'rolled_back_no_write','phase'=>$phase,'reason'=>preg_match('/^[a-z0-9_]+$/',$e->getMessage())?$e->getMessage():'guard_failed','database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0,'readback_verified'=>false];}
 file_put_contents($dir.'/result.json',json_encode($res,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");echo json_encode($res,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";return $res['state']==='committed_verified'?0:2;}
if(PHP_SAPI==='cli')exit(main($argv));
