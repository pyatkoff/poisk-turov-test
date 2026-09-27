<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_retained93_primary_write_v84.php';

const V85_OP='hotel-match-retained-candidates-current-audit-1971-20260927-v85';
const V85_PACKET='reports/hotel-match-retained-candidate-packet-20260927.json';
const V85_PACKET_BLOB='757711a6fb7012a18743da1bc2bb4a7a67cb6232';

function v85_need(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function v85_q(PDO $db,string $sql,array $args=[]):array{
  $s=$db->prepare($sql);v85_need($s!==false&&$s->execute(array_values($args)),'query');
  $r=$s->fetchAll(PDO::FETCH_ASSOC);v85_need(count($r)<=50000,'row_limit');return $r;
}
function v85_packet(string $payload):array{
  $p=$payload.'/'.V85_PACKET;v85_need(is_file($p)&&!is_link($p),'packet_missing');
  $raw=file_get_contents($p);v85_need(is_string($raw),'packet_read');
  $j=json_decode($raw,true,128,JSON_THROW_ON_ERROR);v85_need(is_array($j)&&($j['schema']??'')==='retained-match-candidate-packet/1','packet_schema');
  v85_need(count($j['candidate_pairs']??[])===8,'packet_count');return $j;
}
function v85_source_status(array $rows,int $local,string $expectedSha):array{
  if(count($rows)!==1)return ['state'=>count($rows)?'duplicate':'missing','safe'=>false];
  $r=$rows[0];
  if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']===$local)
    return ['state'=>'already_same','safe'=>false,'row'=>$r];
  if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)
    return ['state'=>'accepted_other','safe'=>false,'row'=>$r];
  $ok=($r['decision_status']??'')==='pending'&&$r['local_hotel_id']===null&&w76_evidence_valid($r)
      &&hash_equals((string)$r['evidence_sha256'],$expectedSha)&&w78_auto($r);
  return ['state'=>$ok?'pending_automatic_exact':'pending_or_protected','safe'=>$ok,'row'=>$r];
}
function v85_operator_status(array $rows,int $local):array{
  if(!$rows)return ['state'=>'absent','safe'=>true];
  if(count($rows)!==1)return ['state'=>'duplicate','safe'=>false];
  $r=$rows[0];
  if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']===$local)
    return ['state'=>'accepted_same','safe'=>true,'row'=>$r];
  if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)
    return ['state'=>'accepted_other','safe'=>false,'row'=>$r];
  $ok=w78_auto_operator($r);
  return ['state'=>$ok?'pending_automatic':'pending_or_protected','safe'=>$ok,'row'=>$r];
}
function v85_main(array $argv):int{
  v85_need(($argv[1]??'')==='--execute','disabled');
  $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');$payload=(string)getenv('MATCH_PAYLOAD_ROOT');
  v85_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru','root');
  v85_need(is_dir($dir)&&!is_link($dir)&&basename($dir)===V85_OP,'dir');
  v85_need(is_dir($payload)&&!is_link($payload)&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'payload');
  foreach(['execution-started.json','result.json','receipt.json'] as $f)v85_need(!file_exists($dir.'/'.$f),'no_replay');
  w76_save($dir.'/execution-started.json',['operation'=>V85_OP,'source_sha'=>$head]);

  $packet=v85_packet($payload);
  require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
  $db=v2_data_db();v85_need($db instanceof PDO,'db');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
  try{
    $before=w76_census($db);
    $ident=v85_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');
    $cohort=w84_live_sources($root);$samo=w84_samo_census($ident,$cohort);
    $sources=[];$ops=[];$targets=[];
    foreach($ident as $r){
      $ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];
      if($ns==='andromeda_catalog'){$sources[$ext][]=$r;if($r['local_hotel_id']!==null)$targets[(int)$r['local_hotel_id']][]=$r;}
      else $ops[$ns][$ext][]=$r;
    }
    $maps=v85_q($db,'SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001');
    $mapBySource=[];$mapByTarget=[];foreach($maps as $r){$mapBySource[(string)$r['anex_hotel_id']][]=$r;$mapByTarget[(int)$r['catalog_hotel_id']][]=$r;}
    $dec=v85_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001');
    $manualSource=[];$manualTarget=[];foreach($dec as $r){$manualSource[(string)$r['anex_hotel_id']][]=$r;if($r['catalog_hotel_id']!==null)$manualTarget[(int)$r['catalog_hotel_id']]=true;}
    $exc=v85_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001');
    $excludedSource=[];$excludedTarget=[];foreach($exc as $r){$excludedSource[(string)$r['anex_hotel_id']][]=$r;$excludedTarget[(int)$r['catalog_hotel_id']]=true;}
    $ids=[];foreach($packet['candidate_pairs'] as $p)$ids[(int)$p['local_hotel_id']]=true;$ids=array_keys($ids);
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $hot=[];foreach(v85_q($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as $r)$hot[(int)$r['id']]=$r;
    $live=[];foreach(v85_q($db,"SELECT DISTINCT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)",$ids) as $r)$live[(int)$r['hotel_id']]=true;

    $rows=[];$safeSamo=0;$safeAnex=0;$already=0;
    foreach($packet['candidate_pairs'] as $p){
      $id=(int)$p['local_hotel_id'];$base=['kind'=>$p['kind'],'local_hotel_id'=>$id,'local_name'=>$p['local_name'],'live30'=>isset($live[$id]),'hotel'=>$hot[$id]??null,'safe_to_write_now'=>false,'reasons'=>[]];
      if(!isset($hot[$id])||(int)$hot[$id]['is_active']!==1)$base['reasons'][]='target_inactive_or_missing';
      if(!isset($live[$id]))$base['reasons'][]='outside_tv_live30';
      if(!empty($manualTarget[$id]))$base['reasons'][]='manual_target_protected';
      if(!empty($excludedTarget[$id]))$base['reasons'][]='excluded_target_protected';
      if($p['kind']==='SAMO'){
        $cat=(string)$p['catalog_id'];$proof=$p['proofs'][0]??[];$ns=(string)($proof['namespace']??'');$native=(string)($proof['native_id']??'');
        $ss=v85_source_status($sources[$cat]??[],$id,(string)$p['source_evidence_sha256']);
        $os=v85_operator_status($ops[$ns][$native]??[],$id);
        $other=array_values(array_filter($targets[$id]??[],fn($r)=>(string)$r['external_hotel_id']!==$cat&&($r['decision_status']??'')==='accepted'));
        if($other)$base['reasons'][]='target_other_canonical_accepted';
        if($ss['state']==='already_same'){$base['state']='already_same';$already++;}
        else{
          if(!$ss['safe'])$base['reasons'][]='canonical_source_'.$ss['state'];
          if(!$os['safe'])$base['reasons'][]='operator_'.$os['state'];
          if(!$base['reasons']){$base['safe_to_write_now']=true;$safeSamo++;}
          $base['state']=$base['safe_to_write_now']?'ready_current':'hold_current';
        }
        $base+=['catalog_id'=>$cat,'source_state'=>$ss['state'],'operator_namespace'=>$ns,'operator_native_id'=>$native,'operator_state'=>$os['state']];
      }else{
        $an=(string)$p['anex_online_id'];$same=false;
        foreach($mapBySource[$an]??[] as $r)if((int)$r['catalog_hotel_id']===$id&&(int)$r['enabled']===1)$same=true;
        if($same){$base['state']='already_same';$already++;}
        else{
          if(!empty($mapBySource[$an]))$base['reasons'][]='anex_source_occupied';
          foreach($mapByTarget[$id]??[] as $r)if((string)$r['anex_hotel_id']!==$an&&(int)$r['enabled']===1)$base['reasons'][]='target_has_other_anex_mapping';
          if(!empty($manualSource[$an]))$base['reasons'][]='anex_manual_source_protected';
          if(!empty($excludedSource[$an]))$base['reasons'][]='anex_source_excluded';
          if(!$base['reasons']){$base['safe_to_write_now']=true;$safeAnex++;}
          $base['state']=$base['safe_to_write_now']?'ready_current':'hold_current';
        }
        $base['anex_online_id']=$an;
      }
      $base['reasons']=array_values(array_unique($base['reasons']));$rows[]=$base;
    }
    $after=w76_census($db);v85_need($after===$before,'read_only_census_drift');
    $db->rollBack();
    $out=['operation'=>V85_OP,'state'=>'completed_read_only_current_candidates','source_sha'=>$head,'generated_at_utc'=>gmdate('c'),
      'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'candidate_count'=>count($rows),
      'safe_samo_count'=>$safeSamo,'safe_direct_anex_count'=>$safeAnex,'already_count'=>$already,'rows'=>$rows,
      'coverage_current'=>$before,'samo_live30_current'=>$samo,'safe_total_count'=>$safeSamo+$safeAnex,'no_replay'=>true];
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$out=['operation'=>V85_OP,'state'=>'failed_read_only_current_candidates','source_sha'=>$head,'reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'audit_failed','provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];}
  $sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>V85_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
  echo w76_json($out)."\n";return $out['state']==='completed_read_only_current_candidates'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(v85_main($argv));
