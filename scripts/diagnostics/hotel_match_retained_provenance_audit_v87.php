<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_retained_candidates_current_audit_v85.php';
const V87_OP='hotel-match-retained-provenance-audit-1971-20260927-v87';
const V87_PAIRS=[57552=>'2000040084',65714=>'2000072730'];
function v87_need(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
function v87_main(array $argv):int{
  v87_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
  v87_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===V87_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'scope');
  foreach(['execution-started.json','result.json','receipt.json'] as $f)v87_need(!file_exists($dir.'/'.$f),'no_replay');w76_save($dir.'/execution-started.json',['operation'=>V87_OP,'source_sha'=>$head]);
  require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
  try{
    $rows=[];
    foreach(V87_PAIRS as $id=>$cat){
      $s=v85_q($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$cat]);
      $t=v85_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE local_hotel_id=? ORDER BY supplier_namespace,external_hotel_id",[$id]);
      $h=v85_q($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id=?",[$id]);
      $manual=v85_q($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE catalog_hotel_id=?",[$id]);
      $exc=v85_q($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id=?",[$id]);
      $src=$s[0]??null;$ev=$src?json_decode((string)$src['evidence_json'],true):null;
      $rows[]=['local_hotel_id'=>$id,'catalog_id'=>$cat,'hotel'=>$h[0]??null,'source_row'=>$src?[
        'local_hotel_id'=>$src['local_hotel_id'],'decision_status'=>$src['decision_status'],'catalog_sha256'=>$src['catalog_sha256'],'evidence_sha256'=>$src['evidence_sha256'],
        'evidence_valid'=>w76_evidence_valid($src),'w78_auto'=>w78_auto($src),'evidence'=>$ev]:null,
        'target_identities'=>$t,'manual'=>$manual,'exclusions'=>$exc];
    }
    $db->rollBack();$out=['operation'=>V87_OP,'state'=>'completed_read_only_provenance','source_sha'=>$head,'rows'=>$rows,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$out=['operation'=>V87_OP,'state'=>'failed_read_only_provenance','source_sha'=>$head,'reason'=>'audit_failed','provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];}
  $sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>V87_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo w76_json($out)."\n";return $out['state']==='completed_read_only_provenance'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(v87_main($argv));
