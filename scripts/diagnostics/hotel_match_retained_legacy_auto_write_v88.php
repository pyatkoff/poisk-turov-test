<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_retained_candidates_write_v86.php';

const V88_OP='hotel-match-retained-legacy-auto-write-1971-20260927-v88';
const V88_V87_OP='hotel-match-retained-provenance-audit-1971-20260927-v87';
const V88_V87_SHA='e86b37f65b94e26ff3c1b57915b051f5a066a3fd03ba314ae3f8bad423f451e6';
const V88_PAIRS=[57552=>'2000040084',65714=>'2000072730'];

function v88_need(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
function v88_legacy_auto(array $r,array $hotel,string $cat):bool{
  if(($r['decision_status']??'')!=='pending'||$r['local_hotel_id']!==null||!w76_evidence_valid($r))return false;
  $e=json_decode((string)$r['evidence_json'],true);if(!is_array($e))return false;
  $allowed=['candidate_ids','geography','operation_id','reason','source','target_name'];
  if(array_diff(array_keys($e),$allowed)||array_diff($allowed,array_keys($e)))return false;
  if(($e['candidate_ids']??null)!==[]||!array_key_exists('target_name',$e)||$e['target_name']!==null||($e['reason']??'')!=='no_unique_name')return false;
  if(($e['operation_id']??'')!=='andromeda-1759-country6-20260910-v1')return false;
  if(($e['geography']['status']??'')!=='unknown'||count($e['geography']??[])!==1)return false;
  $s=$e['source']??null;if(!is_array($s)||(string)($s['id']??'')!==$cat)return false;
  if(w76_text($s['state']??'')!==w76_text($hotel['country_name']??''))return false;
  if((int)($s['starKey']??0)!==(int)($hotel['category']??0))return false;
  return true;
}
function v88_prepare(string $ops,string $payload):array{
  $packet=v85_packet($payload);
  $v87=v86_load($ops.'/'.V88_V87_OP.'/result.json',V88_V87_SHA);
  v88_need(($v87['state']??'')==='completed_read_only_provenance'&&count($v87['rows']??[])===2,'v87_state');
  $audit=[];foreach($v87['rows'] as $r)$audit[(int)$r['local_hotel_id']]=$r;
  $out=[];
  foreach($packet['candidate_pairs'] as $p){
    $id=(int)$p['local_hotel_id'];if(!isset(V88_PAIRS[$id])||$p['kind']!=='SAMO')continue;
    v88_need(V88_PAIRS[$id]===(string)$p['catalog_id'],'scope_pair');
    $a=$audit[$id]??null;v88_need(is_array($a)&&($a['source_row']['decision_status']??'')==='pending'&&array_key_exists('local_hotel_id',$a['source_row'])&&$a['source_row']['local_hotel_id']===null,'audit_pending');
    $pr=$p['proofs'][0];$ns=(string)$pr['namespace'];$native=(string)$pr['native_id'];
    $edge=v86_tv_edge($ops,$packet,$pr['tv_proofs'][0],$id,$ns,$native);
    $raw=v86_samo_raw($ops,$p,(string)$p['catalog_id'],$ns,$native);
    $out[$id]=['id'=>$id,'catalog_id'=>(string)$p['catalog_id'],'source_evidence_sha256'=>(string)$p['source_evidence_sha256'],
      'operator_namespace'=>$ns,'operator_native_id'=>$native,'tv_edge'=>$edge,'samo_raw'=>$raw];
  }
  v88_need(count($out)===2,'scope_count');return$out;
}
function v88_write(PDO $db,array $entries,string $head,string $dir,array $cohort):array{
  v88_need(!$db->inTransaction(),'tx_open');$attempt=false;$committed=false;$planned=[];$held=[];$same=[];
  try{
    foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_decisions','anex_review_pair_exclusions'] as $t){
      $e=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);v88_need(count($e)===1&&strtoupper($e[0]['ENGINE'])==='INNODB','engine');
    }
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');v88_need($db->beginTransaction(),'begin');
    $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');$before=w76_index($all);
    $sources=[];$targets=[];$ops=[];foreach($all as $r){$ns=(string)$r['supplier_namespace'];$n=(string)$r['external_hotel_id'];if($ns==='andromeda_catalog'){$sources[$n][]=$r;if($r['local_hotel_id']!==null)$targets[(int)$r['local_hotel_id']][]=$r;}else$ops[$ns][$n][]=$r;}
    $ids=array_keys($entries);$ph=implode(',',array_fill(0,count($ids),'?'));
    $hot=[];foreach(w76_q($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$hot[(int)$r['id']]=$r;
    $live=[];foreach(w76_q($db,"SELECT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) ORDER BY hotel_id FOR UPDATE",$ids) as $r)$live[(int)$r['hotel_id']]=true;
    $manual=[];foreach(w76_q($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) FOR UPDATE",$ids) as $r)$manual[(int)$r['catalog_hotel_id']]=true;
    $excluded=[];foreach(w76_q($db,"SELECT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph) FOR UPDATE",$ids) as $r)$excluded[(int)$r['catalog_hotel_id']]=true;
    $beforeC=w76_census($db);$beforeS=w84_samo_census($all,$cohort);
    foreach($entries as $id=>$e){
      $cat=$e['catalog_id'];$rows=$sources[$cat]??[];$why=[];$r=count($rows)===1?$rows[0]:null;
      if(!$r)$why[]='source_missing_or_duplicate';
      elseif(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']===$id){$same[]=['local_hotel_id'=>$id,'catalog_id'=>$cat];continue;}
      else{
        if(!hash_equals((string)($r['evidence_sha256']??''),$e['source_evidence_sha256']))$why[]='source_evidence_drift';
        if(!v88_legacy_auto($r,$hot[$id]??[],$cat))$why[]='legacy_auto_predicate_failed';
      }
      foreach($targets[$id]??[] as $x)if((string)$x['external_hotel_id']!==$cat&&($x['decision_status']??'')==='accepted')$why[]='target_other_canonical';
      if(!isset($hot[$id])||(int)$hot[$id]['is_active']!==1)$why[]='inactive_target';if(!isset($live[$id]))$why[]='outside_tv_live30';
      if(isset($manual[$id])||isset($excluded[$id]))$why[]='protected_target';
      foreach($ops[$e['operator_namespace']][$e['operator_native_id']]??[] as $or){
        if(($or['decision_status']??'')==='accepted'&&$or['local_hotel_id']!==null&&(int)$or['local_hotel_id']!==$id)$why[]='operator_other_target';
        elseif(($or['decision_status']??'')!=='accepted'&&!w78_auto_operator($or))$why[]='operator_protected';
      }
      $why=array_values(array_unique($why));if($why){$held[]=['local_hotel_id'=>$id,'catalog_id'=>$cat,'reasons'=>$why];continue;}
      $ev=['operation'=>V88_OP,'source_sha'=>$head,'rule'=>'exact_same_operator_over_legacy_no_unique_name','target'=>$hot[$id],
        'operator_namespace'=>$e['operator_namespace'],'operator_native_id'=>$e['operator_native_id'],'tv_proof'=>$e['tv_edge'],'samo_raw_proof'=>$e['samo_raw'],
        'prior_evidence_json'=>$r['evidence_json'],'prior_evidence_sha256'=>$r['evidence_sha256'],'provider_http_calls'=>0];
      $json=w76_json($ev);$planned['andromeda_catalog|'.$cat]=['id'=>$id,'cat'=>$cat,'name'=>$hot[$id]['name'],'old'=>$r,'new_json'=>$json,'new_sha'=>hash('sha256',$json),'proof_count'=>1];
    }
    w76_save($dir.'/write-plan.json',['operation'=>V88_OP,'source_sha'=>$head,'planned'=>$planned,'held'=>$held,'already'=>$same,'coverage_before'=>$beforeC,'samo_before'=>$beforeS]);
    if(!$planned){$db->rollBack();return['state'=>'completed_no_new_writes','database_writes'=>0,'mapping_writes'=>0,'primary_samo_writes'=>0,'held'=>$held,'already'=>$same,'coverage_before'=>$beforeC,'coverage_after'=>$beforeC,'samo_before'=>$beforeS,'samo_after'=>$beforeS,'readback_verified'=>true];}
    $st=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");
    foreach($planned as $x)v88_need($st->execute([$x['id'],$x['new_sha'],$x['new_json'],$x['cat'],$x['old']['catalog_sha256'],$x['old']['evidence_sha256']])&&$st->rowCount()===1,'conditional_update');
    w76_verify($before,$planned,w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));
    w76_save($dir.'/commit-attempt.json',['operation'=>V88_OP,'source_sha'=>$head,'planned_writes'=>count($planned),'state'=>'commit_attempt_no_replay']);$attempt=true;v88_need($db->commit(),'commit');$committed=true;
    $db->exec('START TRANSACTION READ ONLY');$afterAll=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');w76_verify($before,$planned,$afterAll);
    $afterC=w76_census($db);$afterS=w84_samo_census($afterAll,$cohort);$db->rollBack();
    return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'database_writes'=>count($planned),'mapping_writes'=>count($planned),'primary_samo_writes'=>count($planned),'held'=>$held,'already'=>$same,'coverage_before'=>$beforeC,'coverage_after'=>$afterC,'samo_before'=>$beforeS,'samo_after'=>$afterS,'readback_verified'=>true];
  }catch(Throwable $e){if($db->inTransaction())try{$db->rollBack();}catch(Throwable){}return['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':'rolled_back_no_writes'),'reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'writer_failed','database_writes'=>$attempt?null:0,'mapping_writes'=>$attempt?null:0,'readback_verified'=>false];}
}
function v88_main(array $argv):int{
  if(($argv[1]??'')==='--self-test'){
    $r=['decision_status'=>'pending','local_hotel_id'=>null,'evidence_json'=>w76_json(['candidate_ids'=>[],'geography'=>['status'=>'unknown'],'operation_id'=>'andromeda-1759-country6-20260910-v1','reason'=>'no_unique_name','source'=>['id'=>9,'state'=>'X','starKey'=>4],'target_name'=>null])];$r['evidence_sha256']=hash('sha256',$r['evidence_json']);
    v88_need(v88_legacy_auto($r,['country_name'=>'X','category'=>4],'9'),'legacy_positive');echo "V88_SELF_TEST_OK\n";return 0;
  }
  v88_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');$payload=(string)getenv('MATCH_PAYLOAD_ROOT');
  v88_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===V88_OP&&is_dir($payload)&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'scope');
  foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json'] as $f)v88_need(!file_exists($dir.'/'.$f),'no_replay');w76_save($dir.'/execution-started.json',['operation'=>V88_OP,'source_sha'=>$head]);
  try{$entries=v88_prepare(dirname($dir),$payload);$cohort=w84_live_sources($root);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=v88_write(v2_data_db(),$entries,$head,$dir,$cohort);}
  catch(Throwable $e){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'prepare_failed','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
  $out+=['operation'=>V88_OP,'source_sha'=>$head,'provider_http_calls'=>0,'new_operator_ids'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];$sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>V88_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$sha,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);echo w76_json($out)."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(v88_main($argv));
