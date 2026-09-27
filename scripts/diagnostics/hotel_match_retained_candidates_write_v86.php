<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_retained93_primary_write_v84.php';
require_once __DIR__.'/hotel_match_retained_candidates_current_audit_v85.php';

const V86_OP='hotel-match-retained-candidates-write-1971-20260927-v86';
const V86_V85_OP='hotel-match-retained-candidates-current-audit-1971-20260927-v85';
const V86_V85_SHA='5e22f67283712ba305d8b0f18d0b15fa3a100fb954e5a4a774ebb6012a65f48d';
const V86_READY_SAMO=[1477=>'379202',51548=>'2000062239',63524=>'304393'];
const V86_READY_ANEX=[1124=>'8319',1477=>'8418',1597=>'15117'];

function v86_need(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
function v86_load(string $p,string $sha,int $cap=33554432):array{
  v86_need(is_file($p)&&!is_link($p)&&filesize($p)<=$cap,'input_file');
  $raw=file_get_contents($p);v86_need(is_string($raw)&&hash_equals($sha,hash('sha256',$raw)),'input_hash');
  $j=json_decode($raw,true,128,JSON_THROW_ON_ERROR);v86_need(is_array($j),'input_json');return $j;
}
function v86_ptr(array $x,string $p):array{return w78_ptr($x,$p);}
function v86_tv_edge(string $ops,array $packet,array $proof,int $id,string $ns,string $native):array{
  $alias=(string)$proof['input'];$map=[
    'v80'=>['hotel-match-current100-common4-acquire-1971-20260927-v80',$packet['input_receipts']['v80']['result_sha256']],
    'v82'=>['hotel-match-current100-dedicated-account-acquire-1971-20260927-v82',$packet['input_receipts']['v82']['result_sha256']],
    'tv'=>['hotel-match-mass948-retained-files-1971-20260927-v1',$packet['input_receipts']['tv']['result_sha256']],
  ];
  v86_need(isset($map[$alias]),'tv_alias');[$op,$sha]=$map[$alias];
  $j=v86_load($ops.'/'.$op.'/result.json',$sha,67108864);$r=v86_ptr($j,(string)$proof['json_pointer']);
  v86_need((int)($r['tv_hotel_id']??0)===$id,'tv_id');
  if($alias==='tv'){
    v86_need((string)($r['supplier_namespace']??'')===$ns&&(string)($r['external_hotel_id']??'')===$native,'tv_native');
    foreach(['operator_link_sha256','search_id_sha256','tour_id_sha256'] as $k)v86_need(hash_equals((string)$proof[$k],(string)($r[$k]??'')),'tv_digest');
  }else{
    v86_need((string)($r['positive_native_candidates'][0]??'')===$native,'tv_native');
    foreach(['operator_link_sha256','search_id_sha256','tour_id_sha256'] as $k)v86_need(hash_equals((string)$proof[$k],(string)($r[$k]??'')),'tv_digest');
  }
  return $r;
}
function v86_samo_raw(string $ops,array $p,string $cat,string $ns,string $native):array{
  foreach($p['proofs'][0]['samo_raw_references']??[] as $ref){
    $sf=(string)$ref['source_file'];
    v86_need(preg_match('~^operations/(hotel-match-[A-Za-z0-9_-]+)/(.+\.json)$~D',$sf,$m)===1,'raw_path');
    $base=realpath($ops);$path=$ops.'/'.$m[1].'/'.$m[2];$real=realpath($path);
    if($base===false||$real===false||!str_starts_with($real,$base.'/'))continue;
    $j=v86_load($real,(string)$ref['sha256'],16777216);$row=v86_ptr($j,(string)$ref['json_pointer']);
    if(w78_fact($row,$cat,$ns,$native))return $ref;
  }
  throw new RuntimeException('raw_proof');
}
function v86_prepare(string $ops,string $payload):array{
  $packet=v85_packet($payload);
  $v85=v86_load($ops.'/'.V86_V85_OP.'/result.json',V86_V85_SHA);
  v86_need(($v85['state']??'')==='completed_read_only_current_candidates'&&($v85['safe_total_count']??0)===6,'v85_state');
  $current=[];foreach($v85['rows'] as $r)$current[$r['kind'].'|'.$r['local_hotel_id']]=$r;
  $samo=[];$anex=[];
  foreach($packet['candidate_pairs'] as $p){
    $id=(int)$p['local_hotel_id'];$key=$p['kind'].'|'.$id;
    if(($current[$key]['safe_to_write_now']??false)!==true)continue;
    if($p['kind']==='SAMO'){
      v86_need(isset(V86_READY_SAMO[$id])&&V86_READY_SAMO[$id]===(string)$p['catalog_id'],'samo_scope');
      $pr=$p['proofs'][0];$ns=(string)$pr['namespace'];$native=(string)$pr['native_id'];
      $edge=v86_tv_edge($ops,$packet,$pr['tv_proofs'][0],$id,$ns,$native);
      $raw=v86_samo_raw($ops,$p,(string)$p['catalog_id'],$ns,$native);
      $samo[$id]=['id'=>$id,'catalog_id'=>(string)$p['catalog_id'],'source_evidence_sha256'=>(string)$p['source_evidence_sha256'],
        'proofs'=>[['namespace'=>$ns,'native_id'=>$native,'tv'=>['kind'=>'independent_tv_audit','row'=>$edge],'samo'=>$raw,'samo_catalog_id'=>(string)$p['catalog_id']]],
        'operator_facts'=>[['ns'=>$ns,'native'=>$native]]];
    }else{
      v86_need(isset(V86_READY_ANEX[$id])&&V86_READY_ANEX[$id]===(string)$p['anex_online_id'],'anex_scope');
      $edge=v86_tv_edge($ops,$packet,$p['proofs'][0],$id,'anex',(string)$p['anex_online_id']);
      v86_need((int)($edge['operator_id']??0)===13&&w84_edge_reasons($edge)===[],'anex_proof');
      $anex[$id]=['id'=>$id,'native'=>(string)$p['anex_online_id'],'proof'=>$edge];
    }
  }
  v86_need(count($samo)===3&&count($anex)===3,'ready_count');return[$packet,$samo,$anex];
}
function v86_write(PDO $db,array $samo,array $anex,string $head,string $dir,array $cohort):array{
  require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
  v86_need(!$db->inTransaction(),'tx_open');
  foreach(['andromeda_hotel_identities','anex_hotel_search_mappings','catalog_hotels','tour_operator_identity_observations','anex_hotel_decisions','anex_review_pair_exclusions'] as $t){
    $e=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);v86_need(count($e)===1&&strtoupper($e[0]['ENGINE'])==='INNODB','engine');
  }
  $attempt=false;$committed=false;$planned=[];$plannedAnex=[];$held=[];$same=[];
  try{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');v86_need($db->beginTransaction(),'begin');
    $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');$before=w76_index($all);
    $maps=w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');$mapBefore=w75_index($maps);
    $dec=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');
    $exc=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001 FOR UPDATE');
    $sources=[];$targets=[];$ops=[];foreach($all as $r){$ns=(string)$r['supplier_namespace'];$n=(string)$r['external_hotel_id'];if($ns==='andromeda_catalog'){$sources[$n][]=$r;if($r['local_hotel_id']!==null)$targets[(int)$r['local_hotel_id']][]=$r;}else$ops[$ns][$n][]=$r;}
    $ids=array_values(array_unique(array_merge(array_keys($samo),array_keys($anex))));$ph=implode(',',array_fill(0,count($ids),'?'));
    $hot=[];foreach(w76_q($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$hot[(int)$r['id']]=$r;
    $live=[];foreach(w76_q($db,"SELECT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) ORDER BY hotel_id FOR UPDATE",$ids) as $r)$live[(int)$r['hotel_id']]=true;
    $manualTarget=[];$manualSource=[];foreach($dec as $r){$manualSource[(string)$r['anex_hotel_id']][]=$r;if($r['catalog_hotel_id']!==null)$manualTarget[(int)$r['catalog_hotel_id']]=true;}
    $excludedTarget=[];$excludedSource=[];foreach($exc as $r){$excludedSource[(string)$r['anex_hotel_id']][]=$r;$excludedTarget[(int)$r['catalog_hotel_id']]=true;}
    $mappingSource=[];$mappingTarget=[];foreach($maps as $r){$mappingSource[(string)$r['anex_hotel_id']][]=$r;$mappingTarget[(int)$r['catalog_hotel_id']][]=$r;}
    $effective=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
    $beforeC=w76_census($db);$beforeS=w84_samo_census($all,$cohort);

    foreach($samo as $id=>$e){
      $cat=$e['catalog_id'];$rows=$sources[$cat]??[];$why=[];
      if(count($rows)!==1)$why[]='source_missing_or_duplicate';$r=count($rows)===1?$rows[0]:null;
      if($r){
        if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']===$id){$same[]=['kind'=>'SAMO','local_hotel_id'=>$id,'catalog_id'=>$cat];continue;}
        if(($r['decision_status']??'')!=='pending'||$r['local_hotel_id']!==null||!w78_auto($r))$why[]='source_not_automatic_pending';
        if(!hash_equals((string)$r['evidence_sha256'],$e['source_evidence_sha256']))$why[]='source_evidence_drift';
      }
      foreach($targets[$id]??[] as $x)if((string)$x['external_hotel_id']!==$cat&&($x['decision_status']??'')==='accepted')$why[]='target_other_canonical';
      if(!isset($live[$id]))$why[]='outside_tv_live30';if(!isset($hot[$id])||(int)$hot[$id]['is_active']!==1)$why[]='inactive_target';
      if(!empty($manualTarget[$id])||!empty($excludedTarget[$id]))$why[]='protected_target';
      foreach($e['operator_facts'] as $f)foreach($ops[$f['ns']][$f['native']]??[] as $or){
        if(($or['decision_status']??'')==='accepted'&&$or['local_hotel_id']!==null&&(int)$or['local_hotel_id']!==$id)$why[]='operator_other_target';
        elseif(($or['decision_status']??'')!=='accepted'&&!w78_auto_operator($or))$why[]='operator_protected';
      }
      $why=array_values(array_unique($why));if($why){$held[]=['kind'=>'SAMO','local_hotel_id'=>$id,'catalog_id'=>$cat,'reasons'=>$why];continue;}
      $old=$r;$history=$old['evidence_json'];$ev=['operation'=>V86_OP,'source_sha'=>$head,'rule'=>'one_exact_same_operator_raw_evidence','proofs'=>$e['proofs'],'target'=>$hot[$id],'prior_evidence_json'=>$history,'prior_evidence_sha256'=>$old['evidence_sha256'],'provider_http_calls'=>0];
      $json=w76_json($ev);$planned['andromeda_catalog|'.$cat]=['id'=>$id,'cat'=>$cat,'name'=>$hot[$id]['name'],'old'=>$old,'new_json'=>$json,'new_sha'=>hash('sha256',$json),'proof_count'=>count($e['proofs'])];
    }

    $c=['hotels'=>$hot,'live'=>$live,'mapping_source'=>$mappingSource,'mapping_target'=>$mappingTarget,'manual_source'=>$manualSource,'manual_target'=>[],'excluded_source'=>$excludedSource,'op5_source'=>[],'effective'=>$effective];
    foreach($manualTarget as $k=>$v)$c['manual_target'][$k]=[['x'=>1]];
    foreach($all as $r)if($r['supplier_namespace']==='operator_5')$c['op5_source'][(string)$r['external_hotel_id']][]=$r;
    foreach($anex as $id=>$e){
      $n=$e['native'];$d=a74_anex($id,$n,$c);
      if(!empty($excludedTarget[$id])){$d['reasons'][]='excluded_target_protected';}
      if($d['status']==='already_effective_same'){$same[]=['kind'=>'direct_ANEX','local_hotel_id'=>$id,'anex_hotel_id'=>$n];continue;}
      if($d['status']!=='source_missing_needs_identity_proof'||$d['reasons']){$held[]=['kind'=>'direct_ANEX','local_hotel_id'=>$id,'anex_hotel_id'=>$n,'reasons'=>array_values(array_unique($d['reasons']))];continue;}
      $ev=['operation'=>V86_OP,'source_sha'=>$head,'authority'=>'verified_TV_operator13_HOTELLIST','proof'=>$e['proof'],'current_target'=>$hot[$id],'provider_http_calls'=>0];
      $plannedAnex[]=['anex_hotel_id'=>$n,'catalog_hotel_id'=>$id,'match_class'=>W75_CLASS,'scope'=>'preview','approval_policy'=>W75_POLICY,'source_row_digest'=>w75_digest($ev),'mapping_digest'=>'','enabled'=>1,'evidence'=>$ev];
    }
    $digest=w75_digest(['operation'=>V86_OP,'rows'=>array_map(fn($p)=>[$p['anex_hotel_id'],$p['catalog_hotel_id'],$p['source_row_digest']],$plannedAnex)]);
    foreach($plannedAnex as &$p)$p['mapping_digest']=$digest;unset($p);
    w76_save($dir.'/write-plan.json',['operation'=>V86_OP,'source_sha'=>$head,'samo'=>$planned,'direct_anex'=>$plannedAnex,'held'=>$held,'already'=>$same,'coverage_before'=>$beforeC,'samo_before'=>$beforeS]);
    if(!$planned&&!$plannedAnex){$db->rollBack();return['state'=>'completed_no_new_writes','database_writes'=>0,'mapping_writes'=>0,'primary_samo_writes'=>0,'direct_anex_writes'=>0,'held'=>$held,'already'=>$same,'coverage_before'=>$beforeC,'coverage_after'=>$beforeC,'samo_before'=>$beforeS,'samo_after'=>$beforeS,'readback_verified'=>true];}
    $st=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");
    foreach($planned as $x)v86_need($st->execute([$x['id'],$x['new_sha'],$x['new_json'],$x['cat'],$x['old']['catalog_sha256'],$x['old']['evidence_sha256']])&&$st->rowCount()===1,'samo_update');
    $ins=$db->prepare('INSERT INTO anex_hotel_search_mappings ('.W75_COLUMNS.') VALUES (?,?,?,?,?,?,?,?)');
    foreach($plannedAnex as $x)v86_need($ins->execute(array_map(fn($k)=>$x[$k],explode(',',W75_COLUMNS)))&&$ins->rowCount()===1,'anex_insert');
    w76_verify($before,$planned,w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));
    w75_verify($mapBefore,$plannedAnex,w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),true);
    w76_save($dir.'/commit-attempt.json',['operation'=>V86_OP,'source_sha'=>$head,'planned_writes'=>count($planned)+count($plannedAnex),'state'=>'commit_attempt_no_replay']);$attempt=true;v86_need($db->commit(),'commit');$committed=true;

    $db->exec('START TRANSACTION READ ONLY');$afterAll=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');w76_verify($before,$planned,$afterAll);
    w75_verify($mapBefore,$plannedAnex,w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),false);
    $reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($plannedAnex as $x)v86_need($reg->resolve('anex_online',$x['anex_hotel_id'],'preview')===$x['catalog_hotel_id'],'registry_readback');
    $afterC=w76_census($db);$afterS=w84_samo_census($afterAll,$cohort);$db->rollBack();
    return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'database_writes'=>count($planned)+count($plannedAnex),'mapping_writes'=>count($planned)+count($plannedAnex),'primary_samo_writes'=>count($planned),'direct_anex_writes'=>count($plannedAnex),'held'=>$held,'already'=>$same,'coverage_before'=>$beforeC,'coverage_after'=>$afterC,'samo_before'=>$beforeS,'samo_after'=>$afterS,'readback_verified'=>true];
  }catch(Throwable $e){if($db->inTransaction())try{$db->rollBack();}catch(Throwable){}return['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':'rolled_back_no_writes'),'reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'writer_failed','database_writes'=>$attempt?null:0,'mapping_writes'=>$attempt?null:0,'readback_verified'=>false];}
}
function v86_main(array $argv):int{
  v86_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');$payload=(string)getenv('MATCH_PAYLOAD_ROOT');
  v86_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===V86_OP&&is_dir($payload)&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'scope');
  foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json'] as $f)v86_need(!file_exists($dir.'/'.$f),'no_replay');
  w76_save($dir.'/execution-started.json',['operation'=>V86_OP,'source_sha'=>$head]);$ops=dirname($dir);
  try{[$packet,$samo,$anex]=v86_prepare($ops,$payload);$cohort=w84_live_sources($root);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=v86_write(v2_data_db(),$samo,$anex,$head,$dir,$cohort);}
  catch(Throwable $e){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'prepare_failed','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
  $out+=['operation'=>V86_OP,'source_sha'=>$head,'provider_http_calls'=>0,'new_operator_ids'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];
  $sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>V86_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$sha,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);
  echo w76_json($out)."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(v86_main($argv));
