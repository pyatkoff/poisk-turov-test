<?php
declare(strict_types=1);

const C4R_OP='hotel-match-common4-link149-current-reconcile-1971-20260919-v1';
const C4R_EVIDENCE_SHA='e49986e7ab22664cc0973723c9d2c6abb8022d25a7b018eb4410f7702e5dc6a7';
const C4R_INPUT_RESULT_SHA='63f64b9d8c6eb2ac28fc7a1102432ea76eda97bb40e4efcf3d8d2bbc2651a5e4';
const C4R_MAX_ROWS=250000;
const C4R_ROUTES=[
  13=>['operator'=>'anex','ns'=>'operator_5'],
  25=>['operator'=>'funsun','ns'=>'operator_315'],
  18=>['operator'=>'biblio_globus','ns'=>'operator_115'],
  43=>['operator'=>'intourist','ns'=>'operator_342'],
];
function c4r_req(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function c4r_json(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function c4r_write(string $p,array $x):string{$raw=c4r_json($x);$f=@fopen($p,'x+b');c4r_req(is_resource($f),'exclusive_output');try{c4r_req(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))c4r_req(fsync($f),'output_sync');rewind($f);c4r_req(stream_get_contents($f)===$raw,'output_readback');}finally{fclose($f);}return hash('sha256',$raw);}
function c4r_read(string $p,int $max=8_000_000):array{c4r_req(!is_link($p)&&realpath($p)===$p&&is_file($p),'input_path');$raw=file_get_contents($p);c4r_req(is_string($raw)&&strlen($raw)<=$max,'input_read');$x=json_decode($raw,true,128,JSON_THROW_ON_ERROR);c4r_req(is_array($x),'input_shape');return$x;}
function c4r_q(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute(array_values($args));$rows=$q->fetchAll(PDO::FETCH_ASSOC)?:[];c4r_req(count($rows)<=C4R_MAX_ROWS,'row_budget');return$rows;}
function c4r_placeholders(int $n):string{c4r_req($n>0&&$n<=5000,'placeholder_count');return implode(',',array_fill(0,$n,'?'));}
function c4r_validate_evidence(array $e):array{
  c4r_req(($e['schema']??'')==='hotel-match-common4-link149-evidence/1','evidence_schema');
  c4r_req(($e['source_result_sha256']??'')===C4R_INPUT_RESULT_SHA,'input_result_sha');
  c4r_req((int)($e['source_artifact_id']??0)===10577689146,'input_artifact');
  $edges=$e['edges']??null;c4r_req(is_array($edges)&&count($edges)===149&&(int)($e['captured_edges']??0)===149,'edge_count');
  $seen=[];$opCount=[];
  foreach($edges as$i=>$x){c4r_req(is_array($x),'edge_shape');$idx=(int)($x['index']??0);$tv=(int)($x['tv_hotel_id']??0);$op=(int)($x['operator_id']??0);c4r_req($idx>0&&$tv>0&&isset(C4R_ROUTES[$op]),'edge_identity');
    c4r_req(($x['operator']??'')===C4R_ROUTES[$op]['operator']&&($x['target_supplier_namespace']??'')===C4R_ROUTES[$op]['ns'],'edge_route');
    c4r_req(preg_match('/^[0-9a-f]{64}$/D',(string)($x['operator_link_sha256']??''))===1&&preg_match('/^[0-9a-f]{64}$/D',(string)($x['response_sha256']??''))===1,'edge_digest');
    $key=$idx.':'.$op.':'.$tv;c4r_req(!isset($seen[$key]),'edge_duplicate');$seen[$key]=true;$opCount[$op]=($opCount[$op]??0)+1;
    $cand=$x['candidate_external_hotel_ids']??null;c4r_req(is_array($cand)&&count($cand)===(int)($x['candidate_count']??-1),'candidate_count');$ids=[];foreach($cand as$v){$n=(int)$v;c4r_req($n>0&&!isset($ids[$n]),'candidate_id');$ids[$n]=true;}
    if($op===13)c4r_req(count($cand)===1&&($x['token_semantics']??'')==='anex_hotellist','anex_anchor_shape');
    if($op===18)c4r_req(count($cand)===0&&($x['token_semantics']??'')==='biblio_link_no_proven_hotel_id_semantics','biblio_semantics');
    if($op===25||$op===43)c4r_req(count($cand)>=1&&count($cand)<=3&&($x['token_semantics']??'')==='operator_hotels_token','operator_token_shape');
  }
  ksort($opCount);c4r_req($opCount===[13=>49,18=>50,25=>25,43=>25],'operator_counts');return$edges;
}
function c4r_classify(array $edge,array $local,array $snap):array{
  $op=(int)$edge['operator_id'];$cand=array_values(array_map('intval',$edge['candidate_external_hotel_ids']));$reasons=[];$facts=[];$class='';
  if((int)($local['is_active']??0)!==1)$reasons[]='target_local_inactive';
  if(count($cand)===0){$class='unproven_link_semantics';$reasons[]='no_proven_operator_hotel_id_semantics';}
  elseif(count($cand)>1){$class='ambiguous_multi_token';$reasons[]='multiple_operator_hotel_candidates';}
  else{
    $id=$cand[0];
    if($op===13){
      $maps=$snap['anex_maps_by_native'][$id]??[];$reverse=$snap['anex_maps_by_local'][(int)$edge['tv_hotel_id']]??[];$dec=$snap['anex_decisions_by_native'][$id]??[];$exc=$snap['anex_exclusions'][$id.':'.(int)$edge['tv_hotel_id']]??false;$native=$snap['anex_native'][$id]??null;
      $facts=['candidate_native'=>$native,'candidate_current_mappings'=>$maps,'target_current_anex_mappings'=>$reverse,'candidate_decisions'=>$dec,'pair_excluded'=>$exc];
      $same=false;$elsewhere=false;foreach($maps as$m){if((int)$m['catalog_hotel_id']===(int)$edge['tv_hotel_id'])$same=true;else$elsewhere=true;}
      $occupied=false;foreach($reverse as$m)if((int)$m['anex_hotel_id']!==$id)$occupied=true;
      if($same){$class='already_accepted_same_pair';}
      else{
        if($elsewhere)$reasons[]='candidate_accepted_other_target';if($occupied)$reasons[]='target_occupied_other_anex';if($exc)$reasons[]='pair_excluded';if(!$native)$reasons[]='native_catalog_missing';
        foreach($dec as$d){$st=strtolower((string)($d['decision_status']??''));if($st!==''&&!in_array($st,['pending','candidate'],true))$reasons[]='existing_decision_'.$st;}
        if($reasons)$class='hold_current_guard';else{$class='single_candidate_current_review';$reasons[]='needs_full_name_country_geo_acceptance_guards';}
      }
    }else{
      $ns=(string)$edge['target_supplier_namespace'];$rows=$snap['provider_by_external'][$ns.':'.$id]??[];$target=$snap['provider_by_local'][$ns.':'.(int)$edge['tv_hotel_id']]??[];
      $facts=['candidate_provider_rows'=>$rows,'target_provider_rows'=>$target];$same=false;$elsewhere=false;$pendingSame=false;
      foreach($rows as$r){$st=strtolower((string)$r['decision_status']);$lh=(int)($r['local_hotel_id']??0);if($st==='accepted'&&$lh===(int)$edge['tv_hotel_id'])$same=true;elseif($st==='accepted'&&$lh>0)$elsewhere=true;elseif($lh===(int)$edge['tv_hotel_id'])$pendingSame=true;}
      $occupied=false;foreach($target as$r){if(strtolower((string)$r['decision_status'])==='accepted'&&(string)$r['external_hotel_id']!==(string)$id)$occupied=true;}
      if($same){$class='already_accepted_same_pair';}
      else{
        if($elsewhere)$reasons[]='candidate_accepted_other_target';if($occupied)$reasons[]='target_occupied_other_external';if($pendingSame)$reasons[]='existing_nonaccepted_same_pair';
        if($reasons)$class='hold_current_guard';else{$class='single_candidate_current_review';$reasons[]='needs_full_name_country_geo_acceptance_guards';}
      }
    }
  }
  return ['index'=>(int)$edge['index'],'tv_hotel_id'=>(int)$edge['tv_hotel_id'],'hotel_name'=>(string)$edge['hotel_name'],'operator'=>(string)$edge['operator'],'operator_id'=>$op,'target_supplier_namespace'=>(string)$edge['target_supplier_namespace'],'tour_id'=>(string)$edge['tour_id'],'operator_link_sha256'=>(string)$edge['operator_link_sha256'],'response_sha256'=>(string)$edge['response_sha256'],'candidate_external_hotel_ids'=>$cand,'candidate_count'=>count($cand),'current_local'=>$local,'classification'=>$class,'reasons'=>array_values(array_unique($reasons)),'current_facts'=>$facts,'safe_to_write_now'=>false];
}
function c4r_selftest():void{
  $fixture=['schema'=>'hotel-match-common4-link149-evidence/1','source_result_sha256'=>C4R_INPUT_RESULT_SHA,'source_artifact_id'=>10577689146,'captured_edges'=>149,'edges'=>[]];$idx=1;
  foreach([[13,49],[18,50],[25,25],[43,25]] as[$op,$n])for($j=0;$j<$n;$j++){$route=C4R_ROUTES[$op];$cand=$op===18?[]:[$idx+1000];$fixture['edges'][]=['index'=>$idx,'tv_hotel_id'=>$idx+5000,'operator_id'=>$op,'operator'=>$route['operator'],'target_supplier_namespace'=>$route['ns'],'operator_link_sha256'=>str_repeat('a',64),'response_sha256'=>str_repeat('b',64),'candidate_external_hotel_ids'=>$cand,'candidate_count'=>count($cand),'token_semantics'=>$op===13?'anex_hotellist':($op===18?'biblio_link_no_proven_hotel_id_semantics':'operator_hotels_token'),'hotel_name'=>'H','tour_id'=>'T'];$idx++;}
  c4r_req(count(c4r_validate_evidence($fixture))===149,'fixture_validation');
  $snap=['anex_maps_by_native'=>[],'anex_maps_by_local'=>[],'anex_decisions_by_native'=>[],'anex_exclusions'=>[],'anex_native'=>[1001=>['anex_hotel_id'=>1001]],'provider_by_external'=>[],'provider_by_local'=>[]];
  $e=$fixture['edges'][0];$e['candidate_external_hotel_ids']=[1001];$e['candidate_count']=1;$d=c4r_classify($e,['id'=>$e['tv_hotel_id'],'is_active'=>1],$snap);c4r_req($d['classification']==='single_candidate_current_review'&&!$d['safe_to_write_now'],'single_review');
  $snap['anex_maps_by_native'][1001]=[['anex_hotel_id'=>1001,'catalog_hotel_id'=>$e['tv_hotel_id']]];$d=c4r_classify($e,['id'=>$e['tv_hotel_id'],'is_active'=>1],$snap);c4r_req($d['classification']==='already_accepted_same_pair','same_pair');
  $f=$fixture['edges'][99];$f['candidate_external_hotel_ids']=[7,8];$f['candidate_count']=2;$d=c4r_classify($f,['id'=>$f['tv_hotel_id'],'is_active'=>1],$snap);c4r_req($d['classification']==='ambiguous_multi_token','multi_hold');
  $b=$fixture['edges'][49];$d=c4r_classify($b,['id'=>$b['tv_hotel_id'],'is_active'=>1],$snap);c4r_req($d['classification']==='unproven_link_semantics','biblio_hold');
  echo "hotel-match-common4-link149-current-reconcile-v1: PASS\n";
}
function c4r_main():void{
  c4r_req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===C4R_OP,'operation_guard');$sha=(string)getenv('MATCH_SOURCE_SHA');c4r_req(preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'source_sha');
  $home=rtrim((string)getenv('HOME'),'/');$dir=$home.'/.anytoour-match/operations/'.C4R_OP;$res=c4r_read(realpath($dir.'/reservation.json')?:'');c4r_req(($res['operation_id']??'')===C4R_OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_access','reservation_guard');
  $ep=realpath($dir.'/evidence.json');c4r_req(is_string($ep)&&hash_file('sha256',$ep)===C4R_EVIDENCE_SHA,'evidence_digest');$ev=c4r_read($ep);$edges=c4r_validate_evidence($ev);
  $root=realpath(getcwd());c4r_req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $out=['operation_id'=>C4R_OP,'source_sha'=>$sha,'input_evidence_sha256'=>C4R_EVIDENCE_SHA,'input_result_sha256'=>C4R_INPUT_RESULT_SHA,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0];
  try{
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach(['catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','anex_hotels','andromeda_hotel_identities']as$t){$r=c4r_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);c4r_req(count($r)===1&&strtoupper((string)$r[0]['ENGINE'])==='INNODB','table_contract_'.$t);}
    $tvIds=array_values(array_unique(array_map(fn($e)=>(int)$e['tv_hotel_id'],$edges)));sort($tvIds,SORT_NUMERIC);$ph=c4r_placeholders(count($tvIds));$locals=c4r_q($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$tvIds);$localBy=[];foreach($locals as$r)$localBy[(int)$r['id']]=$r;c4r_req(count($localBy)===count($tvIds),'missing_local_target');
    $anexIds=[];$providerKeys=[];foreach($edges as$e)foreach($e['candidate_external_hotel_ids'] as$v){$id=(int)$v;if((int)$e['operator_id']===13)$anexIds[$id]=true;elseif((int)$e['operator_id']!==18)$providerKeys[(string)$e['target_supplier_namespace']][$id]=true;}
    $snap=['anex_maps_by_native'=>[],'anex_maps_by_local'=>[],'anex_decisions_by_native'=>[],'anex_exclusions'=>[],'anex_native'=>[],'provider_by_external'=>[],'provider_by_local'=>[]];
    if($anexIds){$ids=array_keys($anexIds);sort($ids,SORT_NUMERIC);$aPh=c4r_placeholders(count($ids));$maps=c4r_q($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($aPh) OR catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id",array_merge($ids,$tvIds));foreach($maps as$r){$snap['anex_maps_by_native'][(int)$r['anex_hotel_id']][]=$r;$snap['anex_maps_by_local'][(int)$r['catalog_hotel_id']][]=$r;}
      $dec=c4r_q($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($aPh) ORDER BY anex_hotel_id,catalog_hotel_id",$ids);foreach($dec as$r)$snap['anex_decisions_by_native'][(int)$r['anex_hotel_id']][]=$r;
      $exc=c4r_q($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($aPh) AND catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id,catalog_hotel_id",array_merge($ids,$tvIds));foreach($exc as$r)$snap['anex_exclusions'][(int)$r['anex_hotel_id'].':'.(int)$r['catalog_hotel_id']]=true;
      $nat=c4r_q($db,"SELECT anex_hotel_id,api_name,api_country,source_fingerprint FROM anex_hotels WHERE anex_hotel_id IN ($aPh) ORDER BY anex_hotel_id",$ids);foreach($nat as$r)$snap['anex_native'][(int)$r['anex_hotel_id']]=$r;
    }
    $namespaces=array_keys($providerKeys);if($namespaces){$nPh=c4r_placeholders(count($namespaces));$externalIds=array_merge(...array_map('array_keys',$providerKeys));$ePh=c4r_placeholders(count($externalIds));$provider=c4r_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace IN ($nPh) AND (local_hotel_id IN ($ph) OR external_hotel_id IN ($ePh)) ORDER BY supplier_namespace,external_hotel_id,local_hotel_id",array_merge($namespaces,$tvIds,$externalIds));foreach($provider as$r){$ns=(string)$r['supplier_namespace'];$snap['provider_by_external'][$ns.':'.(string)$r['external_hotel_id']][]=$r;if((int)($r['local_hotel_id']??0)>0)$snap['provider_by_local'][$ns.':'.(int)$r['local_hotel_id']][]=$r;}}
    $dossiers=[];$counts=[];$by=[];foreach($edges as$e){$d=c4r_classify($e,$localBy[(int)$e['tv_hotel_id']],$snap);$dossiers[]=$d;$c=$d['classification'];$counts[$c]=($counts[$c]??0)+1;$op=$d['operator'];$by[$op]??=[];$by[$op][$c]=($by[$op][$c]??0)+1;}
    ksort($counts);foreach($by as&$v)ksort($v);unset($v);ksort($by);$db->commit();$out+=['state'=>'completed_read_only','read_at_utc'=>gmdate('c'),'input_edge_count'=>count($edges),'classification_counts'=>$counts,'by_operator'=>$by,'dossiers'=>$dossiers];
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$m=$e->getMessage();$out['reason']=preg_match('/^[A-Za-z0-9_:.-]+$/D',$m)?$m:'sanitized_error';}
  $hash=c4r_write($dir.'/result.json',$out);c4r_write($dir.'/receipt.json',['operation_id'=>C4R_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$hash,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'no_replay'=>true]);echo json_encode(['state'=>$out['state'],'classification_counts'=>$out['classification_counts']??null,'by_operator'=>$out['by_operator']??null,'result_sha256'=>$hash],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit($out['state']==='completed_read_only'?0:2);
}
if(($argv[1]??'')==='--self-test'){c4r_selftest();exit(0);}if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)c4r_main();
