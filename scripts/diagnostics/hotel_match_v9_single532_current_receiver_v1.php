<?php
declare(strict_types=1);
const OP='hotel-match-v9-single532-current-receiver-1971-20260921-v1';
const INPUT_SHA='0995ccbd0c14639a335a644748d6dc5af6b359345328698ea3c0fc1300d88814';
const NS=[25=>'operator_315',43=>'operator_342'];

function need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function q(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute(array_values($p));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function obj($v):array{if(is_array($v))return $v;if(!is_string($v)||trim($v)==='')return[];try{$x=json_decode($v,true,256,JSON_THROW_ON_ERROR);return is_array($x)?$x:[];}catch(Throwable){return[];}}
function writej(string $p,array $x):string{$b=json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$f=fopen($p,'xb');need(is_resource($f),'open');need(fwrite($f,$b)===strlen($b),'write');fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$b);}

if(($argv[1]??'')==='--self-test'){
  $e=['operator_id'=>25,'link_state'=>'captured_single_native','positive_native_candidates'=>[123]];
  need(isset(NS[$e['operator_id']])&&count($e['positive_native_candidates'])===1,'edge');
  echo "V9_SINGLE532_CURRENT_RECEIVER_V1_SELFTEST_OK\n";exit;
}
need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));$inp=realpath((string)getenv('MATCH_INPUT_PATH'));$dir=(string)getenv('MATCH_OPERATION_DIR');
need(is_string($root)&&basename($root)==='anytoour.ru'&&is_string($inp)&&is_dir($dir)&&basename($dir)===OP,'runtime');
need(hash_file('sha256',$inp)===INPUT_SHA,'input_hash');
$rec=json_decode((string)file_get_contents($inp),true,1024,JSON_THROW_ON_ERROR);
need(($rec['operation']??'')==='hotel-match-v9-reconcile-1971-20260921-v1','input_op');
$src=$rec['source_result']??null;need(is_array($src)&&($src['operation']??'')==='hotel-match-remaining-tv-search30-1971-20260921-v9'&&($src['state']??'')==='completed_read_only','source_result');
$targets=[];
foreach(($src['edges']??[]) as $e){
  if(($e['link_state']??'')!=='captured_single_native')continue;
  $op=(int)($e['operator_id']??0);if(!isset(NS[$op]))continue;
  $pos=array_values(array_unique(array_map('intval',(array)($e['positive_native_candidates']??[]))));
  need(count($pos)===1&&$pos[0]>0,'single_native_shape');
  $k=NS[$op].'|'.$pos[0].'|'.(int)$e['tv_hotel_id'];
  $targets[$k]=['tv_hotel_id'=>(int)$e['tv_hotel_id'],'operator_id'=>$op,'supplier_namespace'=>NS[$op],'native_hotel_id'=>(string)$pos[0],'tour_id'=>(string)$e['tour_id'],'search_id'=>(string)$e['search_id'],'batch'=>(int)$e['batch'],'operator_link_sha256'=>$e['operator_link_sha256']??null,'raw_identity_tokens'=>$e['raw_identity_tokens']??[]];
}
need(count($targets)===532,'input532');

require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
  $provider=[];
  foreach(array_chunk(array_values($targets),300) as $chunk){
    foreach($chunk as $t){
      $r=q($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_json,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?',[$t['supplier_namespace'],$t['native_hotel_id']]);
      $provider[$t['supplier_namespace'].'|'.$t['native_hotel_id']]=$r;
    }
  }
  $tv=array_values(array_unique(array_map(fn($x)=>$x['tv_hotel_id'],$targets)));$occ=[];
  foreach(array_chunk($tv,400) as $chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IN ($ph) AND supplier_namespace IN ('operator_315','operator_342')",$chunk) as $r)$occ[(int)$r['local_hotel_id']][]=$r;}
  $bridgeIds=[];$bridgeBy=[];
  foreach($provider as $pk=>$rr)foreach($rr as $r){$e=obj($r['evidence_json']??null);foreach((array)($e['provider_bridges']??[]) as $b){if(!is_array($b))continue;$id=(string)($b['andromeda_hotel_id']??'');if(preg_match('/^[1-9][0-9]{0,18}$/D',$id)){$bridgeIds[$id]=true;$bridgeBy[$pk][$id]=true;}}}
  $anchors=[];
  if($bridgeIds){$ids=array_keys($bridgeIds);foreach(array_chunk($ids,400) as $chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(q($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ph)",$chunk) as $r)$anchors[(string)$r['external_hotel_id']][]=$r;}}
  $db->rollBack();

  $rows=[];$counts=[];$pool=0;$explicitBridge=0;
  foreach($targets as $t){
    $pk=$t['supplier_namespace'].'|'.$t['native_hotel_id'];$rr=$provider[$pk]??[];$state='provider_identity_missing';
    if(count($rr)>1)$state='provider_identity_duplicate';
    elseif(count($rr)===1){
      $r=$rr[0];$st=(string)$r['decision_status'];$local=$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'];
      if($st==='accepted'&&$local===$t['tv_hotel_id'])$state='accepted_same';
      elseif($st==='accepted'&&$local!==null&&$local!==$t['tv_hotel_id'])$state='accepted_other_target_conflict';
      elseif(in_array($st,['conflict','rejected'],true))$state='protected_'.$st;
      elseif($local!==null&&$local!==$t['tv_hotel_id'])$state='pending_other_target';
      else $state='pending_same_or_unassigned';
    }
    $bids=array_keys($bridgeBy[$pk]??[]);$resolved=[];foreach($bids as $id)foreach($anchors[$id]??[] as $a)$resolved[]=['andromeda_hotel_id'=>$id,'anchor'=>$a];
    if($bids)$explicitBridge++;
    $sameAcceptedBridge=false;foreach($resolved as $z)if(($z['anchor']['decision_status']??'')==='accepted'&&(int)($z['anchor']['local_hotel_id']??0)===$t['tv_hotel_id'])$sameAcceptedBridge=true;
    $otherOcc=array_values(array_filter($occ[$t['tv_hotel_id']]??[],fn($x)=>!($x['supplier_namespace']===$t['supplier_namespace']&&(string)$x['external_hotel_id']===$t['native_hotel_id'])));
    $candidate=($state==='pending_same_or_unassigned'&&$sameAcceptedBridge&&!$otherOcc);
    if($candidate)$pool++;
    $counts[$state]=($counts[$state]??0)+1;
    $rows[]=$t+['current_provider_rows'=>array_map(fn($x)=>array_diff_key($x,['evidence_json'=>true]),$rr),'saved_bridge_ids'=>$bids,'resolved_bridge_anchors'=>$resolved,'same_target_accepted_samo_bridge'=>$sameAcceptedBridge,'same_target_other_provider_occupants'=>$otherOcc,'classification'=>$state,'aggregate_pool_candidate'=>$candidate,'safe_to_write_now'=>false];
  }
  ksort($counts);
  $result=['operation'=>OP,'state'=>'completed_read_only','input_single_native_edges'=>532,'classification_counts'=>$counts,'edges_with_explicit_saved_bridge'=>$explicitBridge,'aggregate_pool_candidates'=>$pool,'rows'=>$rows,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
  $sha=writej($dir.'/result.json',$result);writej($dir.'/receipt.json',['operation'=>OP,'state'=>'completed_read_only','result_sha256'=>$sha,'input_single_native_edges'=>532,'aggregate_pool_candidates'=>$pool,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true]);
  echo json_encode(['classification_counts'=>$counts,'edges_with_explicit_saved_bridge'=>$explicitBridge,'aggregate_pool_candidates'=>$pool,'result_sha256'=>$sha],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
