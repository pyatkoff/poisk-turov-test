<?php
declare(strict_types=1);

const C4V27_OP='hotel-match-common4-retained-context-v26-recovery-1971-20260925-v27b';
const C4V27_TARGET='hotel-match-common4-retained-context-acquire-1971-20260925-v26';
const C4V27_TARGET_RESULT_SHA='036b64868f17ca2b9f087e998c1064f3f6b1d312c7683bef4768e5f6338844';
const C4V27_CONTEXTS=[
 ['ordinal'=>1,'operator_id'=>315,'stateinc'=>5,'beg'=>'20260929','end'=>'20261005','nights'=>7,'meal'=>null],
 ['ordinal'=>2,'operator_id'=>315,'stateinc'=>5,'beg'=>'20261011','end'=>'20261011','nights'=>7,'meal'=>null],
 ['ordinal'=>3,'operator_id'=>315,'stateinc'=>5,'beg'=>'20261016','end'=>'20261016','nights'=>7,'meal'=>null],
 ['ordinal'=>4,'operator_id'=>5,'stateinc'=>3,'beg'=>'20261005','end'=>'20261005','nights'=>7,'meal'=>null],
 ['ordinal'=>5,'operator_id'=>5,'stateinc'=>3,'beg'=>'20261006','end'=>'20261006','nights'=>7,'meal'=>null],
 ['ordinal'=>6,'operator_id'=>5,'stateinc'=>5,'beg'=>'20261011','end'=>'20261011','nights'=>7,'meal'=>null],
 ['ordinal'=>7,'operator_id'=>5,'stateinc'=>5,'beg'=>'20261012','end'=>'20261012','nights'=>7,'meal'=>'5,7'],
 ['ordinal'=>8,'operator_id'=>5,'stateinc'=>5,'beg'=>'20261025','end'=>'20261025','nights'=>7,'meal'=>'5,7'],
];

function c4v27_need(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function c4v27_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function c4v27_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,256,JSON_THROW_ON_ERROR);c4v27_need(is_array($v),'json_shape');return$v;}
function c4v27_save(string $p,array $v):string{$raw=c4v27_json($v)."\n";$f=@fopen($p,'x+b');c4v27_need($f!==false,'exclusive_create');try{c4v27_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))c4v27_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function c4v27_key(array $c):string{return (string)$c['stateinc'].'|'.(string)$c['operator_id'].'|'.(string)$c['nights'];}

function c4v27_recover(string $target):array{
  c4v27_need(is_dir($target)&&!is_link($target),'target_dir');
  $resultPath=$target.'/result.json';$receiptPath=$target.'/receipt.json';
  c4v27_need(is_file($resultPath)&&is_file($receiptPath),'target_terminal_files');
  $serverResultSha=hash_file('sha256',$resultPath);
  $tr=c4v27_load($resultPath);$tq=c4v27_load($receiptPath);
  c4v27_need(($tr['operation']??'')===C4V27_TARGET&&($tr['state']??'')==='terminal_failed_no_replay'&&($tr['reason']??'')==='exclusive_create','target_terminal_state');
  c4v27_need(($tq['result_sha256']??'')===$serverResultSha&&($tq['provider_accessed']??false)===true&&($tq['no_replay']??false)===true,'target_receipt');

  $http=[];foreach(glob($target.'/http-*-reserved.json')?:[] as $p){
    if(!preg_match('/http-([0-9]{4})-reserved\.json$/D',$p,$m))continue;$v=c4v27_load($p);
    $http[]=['call'=>(int)$m[1],'action'=>(string)($v['action']??''),'sha256'=>hash_file('sha256',$p)];
  }
  usort($http,fn($a,$b)=>$a['call']<=>$b['call']);
  foreach($http as $i=>$x)c4v27_need($x['call']===$i+1,'http_sequence');

  $reservations=[];foreach(glob($target.'/context-*-reserved.json')?:[] as $p){
    if(!preg_match('/context-([0-9]+)-([0-9]+)-([0-9]+)-page-([0-9]+)-reserved\.json$/D',$p,$m))continue;
    $key=$m[1].'|'.$m[2].'|'.$m[3];$reservations[$key][(int)$m[4]]=['sha256'=>hash_file('sha256',$p)];
  }
  $evidence=[];$edir=$target.'/evidence-private';
  if(is_dir($edir)&&!is_link($edir))foreach(glob($edir.'/context-*.json')?:[] as $p){
    if(!preg_match('/context-([0-9]+)-([0-9]+)-([0-9]+)-page-([0-9]+)\.json$/D',$p,$m))continue;
    $key=$m[1].'|'.$m[2].'|'.$m[3];$page=(int)$m[4];$v=c4v27_load($p);
    $prices=$v['PRICES']??null;c4v27_need(is_array($prices),'evidence_prices');
    $evidence[$key][$page]=['page'=>(int)($v['PAGE']??-1),'pages_count'=>(int)($v['PAGES_COUNT']??-1),'price_rows'=>count($prices),'sha256'=>hash_file('sha256',$p),'size'=>filesize($p)];
  }
  foreach($reservations as &$pp)ksort($pp,SORT_NUMERIC);unset($pp);foreach($evidence as &$pp)ksort($pp,SORT_NUMERIC);unset($pp);

  $first=C4V27_CONTEXTS[0];$firstKey=c4v27_key($first);$ev=$evidence[$firstKey]??[];$rv=$reservations[$firstKey]??[];
  $pcs=[];foreach($ev as $page=>$x){c4v27_need($x['page']===$page,'evidence_page_echo');c4v27_need($x['pages_count']>=0&&$x['pages_count']<=1000,'evidence_pages_count');$pcs[$x['pages_count']]=true;}
  c4v27_need(count($pcs)<=1,'evidence_pages_count_drift');$advertised=$pcs===[]?null:(int)array_key_first($pcs);
  $maxEvidence=$ev===[]?0:max(array_keys($ev));$maxReserved=$rv===[]?0:max(array_keys($rv));
  $full=false;if($advertised!==null){$needed=$advertised===0?[]:range(1,$advertised);$full=array_keys($ev)===$needed&&array_keys($rv)===$needed;}

  $statuses=[];$seenKey=[];
  foreach(C4V27_CONTEXTS as $c){
    $key=c4v27_key($c);$ord=(int)$c['ordinal'];$st='not_started';
    if(!isset($seenKey[$key])){
      if($ord===1)$st=$full?'consumed_complete':($ev!==[]?'consumed_partial_hold':'not_started');
      elseif(isset($reservations[$key])||isset($evidence[$key]))$st='unexpected_files_hold';
      $seenKey[$key]=$ord;
    }else{
      if($key===$firstKey&&$ord===2&&($tr['reason']??'')==='exclusive_create')$st='collision_before_http';
      else $st='not_started';
    }
    $statuses[]=$c+['legacy_file_key'=>$key,'recovery_status'=>$st];
  }
  $consumed=array_values(array_filter($statuses,fn($x)=>str_starts_with($x['recovery_status'],'consumed_')));
  $unconsumed=array_values(array_filter($statuses,fn($x)=>in_array($x['recovery_status'],['collision_before_http','not_started'],true)));

  return[
    'operation'=>C4V27_OP,'state'=>'completed_supplier_free_v26_recovery','target_operation'=>C4V27_TARGET,'target_server_result_sha256'=>$serverResultSha,'archived_artifact_result_sha256'=>C4V27_TARGET_RESULT_SHA,
    'target_terminal_reason'=>'exclusive_create','provider_accessed_in_target'=>true,
    'http_reservation_count'=>count($http),'http_actions'=>array_count_values(array_column($http,'action')),
    'legacy_context_reservation_file_count'=>array_sum(array_map('count',$reservations)),
    'legacy_evidence_file_count'=>array_sum(array_map('count',$evidence)),
    'first_context'=>['ordinal'=>1,'legacy_file_key'=>$firstKey,'advertised_pages'=>$advertised,'max_reserved_page'=>$maxReserved,'max_evidence_page'=>$maxEvidence,'complete_proven'=>$full,'evidence_page_summaries'=>array_values($ev)],
    'context_statuses'=>$statuses,'consumed_context_count'=>count($consumed),'unconsumed_context_count'=>count($unconsumed),
    'consumed_ordinals'=>array_values(array_map(fn($x)=>$x['ordinal'],$consumed)),'unconsumed_ordinals'=>array_values(array_map(fn($x)=>$x['ordinal'],$unconsumed)),
    'provider_http_calls'=>0,'ssh_target_mutations'=>0,'database_writes'=>0,'mapping_writes'=>0,'raw_provider_payload_exported'=>false,'safe_to_write_now'=>false
  ];
}
function c4v27_self_test():void{c4v27_need(c4v27_key(C4V27_CONTEXTS[0])==='5|315|7','first_key');c4v27_need(c4v27_key(C4V27_CONTEXTS[1])==='5|315|7','collision_key');c4v27_need(c4v27_key(C4V27_CONTEXTS[3])==='3|5|7','op5_key');}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
 if(in_array('--self-test',$argv??[],true)){c4v27_self_test();echo"MATCH_COMMON4_V26_RECOVERY_V27_SELFTEST_OK\n";exit;}
 c4v27_need(($argv[1]??'')==='--execute','disabled');$dir=(string)getenv('MATCH_RECOVERY_OPERATION_DIR');$target=(string)getenv('MATCH_TARGET_OPERATION_DIR');
 c4v27_need(is_dir($dir)&&basename($dir)===C4V27_OP&&is_dir($target)&&basename($target)===C4V27_TARGET,'runtime_scope');
 $res=c4v27_load($dir.'/reservation.json');c4v27_need(($res['operation']??'')===C4V27_OP&&($res['state']??'')==='reserved_before_read_only_recovery','reservation');
 try{$r=c4v27_recover($target);$h=c4v27_save($dir.'/result.json',$r);c4v27_save($dir.'/receipt.json',['operation'=>C4V27_OP,'state'=>$r['state'],'result_sha256'=>$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true]);echo c4v27_json(['state'=>$r['state'],'http_reservation_count'=>$r['http_reservation_count'],'first_context'=>$r['first_context'],'consumed_ordinals'=>$r['consumed_ordinals'],'unconsumed_ordinals'=>$r['unconsumed_ordinals']])."\n";}
 catch(Throwable$e){$f=['operation'=>C4V27_OP,'state'=>'failed_supplier_free_v26_recovery','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160)),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=c4v27_save($dir.'/result.json',$f);c4v27_save($dir.'/receipt.json',['operation'=>C4V27_OP,'state'=>$f['state'],'result_sha256'=>$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
