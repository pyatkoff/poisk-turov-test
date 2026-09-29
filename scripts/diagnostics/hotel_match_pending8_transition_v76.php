<?php
declare(strict_types=1);
/** Exact retained evidence, conditional pending/null transition, no supplier client. */
const W76_OP='hotel-match-pending8-transition-1971-20260927-v76b';
const W76_PAIRS=[4326=>'309768',55945=>'2000037585',56479=>'2000093384',67304=>'2000055490',76753=>'2000087342',108356=>'269426',116886=>'2000103169',121109=>'2000090159'];
const W76_PINS=[
 'v65.json'=>'42829f8f7a7988f3f033bfd8e377758b537ccb95c3ace9a09d953191bfe17810',
 'tv-r1.json'=>'4cd23630e97bb31b81bb7e2980a85e51145fff862346fbee51d0e1a6e85bad24',
 'current97.json'=>'00a1ffe4177da1441f8c3d823a22c8ca7c1ac72b285f192389c6521413e902cc',
 'context.json'=>'292c8f40d5bc9164e638246fdafa7b5f7113646c1c1597edee9a89b9c1a62851',
];
function w76_need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function w76_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function w76_canon(mixed $v):mixed{if(!is_array($v))return$v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as$k=>$x)$v[$k]=w76_canon($x);return$v;}
function w76_hash(mixed $v):string{return hash('sha256',w76_json(w76_canon($v)));}
function w76_save(string $path,array $v):string{$b=w76_json($v)."\n";$f=@fopen($path,'x+b');w76_need($f!==false,'exclusive_record');try{w76_need(fwrite($f,$b)===strlen($b)&&fflush($f),'durable_record');if(function_exists('fsync'))w76_need(fsync($f),'fsync');}finally{fclose($f);}return hash('sha256',$b);}
function w76_q(PDO $db,string $sql,array $args=[]):array{$s=$db->prepare($sql);w76_need($s!==false&&$s->execute(array_values($args)),'query');$r=$s->fetchAll(PDO::FETCH_ASSOC);w76_need(count($r)<=50000,'row_limit');return$r;}
function w76_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[a-f0-9]{64}$/D',$v)===1;}
function w76_text(mixed $v):string{return trim(preg_replace('/\s+/u',' ',strtr((string)$v,['ё'=>'е','Ё'=>'Е'])));}
function w76_target(array $r):array{$out=[];foreach(['id','name','country_id','country_name','region_name','subregion_name','category','is_active','latitude','longitude'] as$k)$out[$k]=($r[$k]??null)===null?null:(string)$r[$k];return$out;}
function w76_prepare(string $dir):array{
 $docs=[];foreach(W76_PINS as$name=>$hash){$p=$dir.'/'.$name;w76_need(is_file($p)&&!is_link($p)&&filesize($p)<=33554432,'input_file');$raw=file_get_contents($p);w76_need(is_string($raw)&&hash_equals($hash,hash('sha256',$raw)),'input_hash');$docs[$name]=json_decode($raw,true,256,JSON_THROW_ON_ERROR);}
 $v=$docs['v65.json'];$tv=$docs['tv-r1.json'];$audit=$docs['current97.json'];$context=$docs['context.json']['context'];
 w76_need($v['state']==='completed_read_only'&&count($v['dossiers'])===175&&$audit['state']==='completed_read_only_current'&&count($audit['samo_rows'])===15,'input_scope');
 $ti=[];$si=[];$dossiers=[];$rows=[];$old=[];$hot=[];
 foreach($tv['rows'] as$r){$ns=$r['supplier_namespace']??'';if(!in_array($ns,['operator_315','operator_342'],true))continue;$n=(string)$r['external_hotel_id'];$id=(int)$r['tv_hotel_id'];$ti[$ns][$n][$id][]=$r;}
 foreach($v['dossiers'] as$d){$dossiers[(int)$d['local_hotel_id']]=$d;foreach($d['candidates'] as$c)foreach(['operator_315','operator_342']as$ns){$lane=$c['lanes'][$ns]??[];if(($lane['state']??'')==='single_native'&&count($lane['native_ids']??[])===1)$si[$ns][(string)$lane['native_ids'][0]][(string)$c['catalog_id']]=true;}}
 foreach($audit['samo_rows']as$r)$old[(int)$r['local_hotel_id']]=$r;
 foreach($context['rows']as$r)$hot[(int)$r['tv_hotel_id']]=$r['hotel'];
 $history=[];foreach($context['selected_and_unaccepted_hotel_evidence']as$r)if($r['identity']['supplier_namespace']==='andromeda_catalog')$history[(string)$r['identity']['external_hotel_id']]=$r;
 foreach(W76_PAIRS as$id=>$cat){
  $a=$old[$id]??[];w76_need(($a['catalog_id']??'')===$cat&&$a['status']==='pending_unassigned_needs_identity_review'&&$a['reasons']===[]&&count($a['current_source'])===1,'audit_pair');
  $prior=$a['current_source'][0];w76_need($prior['local_hotel_id']===null&&$prior['decision_status']==='pending'&&$prior['evidence_valid']===true,'prior_pending');
  $d=$dossiers[$id];$cc=array_values(array_filter($d['candidates'],fn($c)=>(string)$c['catalog_id']===$cat));w76_need(count($cc)===1,'candidate_unique');$c=$cc[0];$proof=[];
  foreach(['operator_315'=>25,'operator_342'=>43]as$ns=>$op){$lane=$c['lanes'][$ns];if($lane['state']!=='single_native'||count($lane['native_ids'])!==1)continue;$n=(string)$lane['native_ids'][0];$targets=$ti[$ns][$n]??[];$cats=$si[$ns][$n]??[];
   if(count($targets)!==1||!isset($targets[$id])||count($cats)!==1||!isset($cats[$cat]))continue;
   foreach($targets[$id]as$t){w76_need((int)$t['operator_id']===$op&&$t['kind']==='identity','tv_operator_binding');foreach(['source_result_sha256','operator_link_sha256','search_id_sha256','tour_id_sha256']as$k)w76_need(w76_sha($t[$k]??null),'tv_proof_digest');
    w76_need($t['operator_link_host']===($op===25?'b2b.fstravel.com':'searchtour.intourist.ru'),'tv_host');
    $proof[]=['namespace'=>$ns,'native_id'=>$n,'tv_audit_row'=>$t,'samo_lane'=>$lane,'samo_catalog_id'=>$cat];break;
   }
  }
  $h=$history[$cat]??null;w76_need(is_array($h)&&$h['raw_evidence_sha256']===$prior['evidence_sha256']&&$h['identity']['catalog_sha256']===$prior['catalog_sha256'],'history_binding');
  $rows[$id]=['id'=>$id,'catalog_id'=>$cat,'prior'=>$prior,'target'=>w76_target($hot[$id]),'history'=>$h['hotel_evidence'],'candidate'=>$c,'dossier'=>$d,'proofs'=>$proof,'direct_anex_ids'=>$a['direct_anex_ids']];
 }
 w76_need(array_keys($rows)===array_keys(W76_PAIRS),'complete_scope');return$rows;
}
function w76_evidence_valid(array $row):bool{$raw=$row['evidence_json']??null;return is_string($raw)&&w76_sha($row['evidence_sha256']??null)&&hash_equals($row['evidence_sha256'],hash('sha256',$raw))&&is_array(json_decode($raw,true));}
/** m948_safe exports a filtered, reordered projection; full raw SHA remains mandatory. */
function w76_source_projection_matches(mixed $raw,mixed $projection):bool{
 if(!is_array($raw)||!is_array($projection)||!$projection)return false;
 foreach($projection as$key=>$expected){
  if(!array_key_exists($key,$raw)||w76_canon($raw[$key])!==w76_canon($expected))return false;
 }
 return true;
}
/** Return hold reasons; proof existence alone never overrides a current conflict. */
function w76_classify(array $e,array $c):array{
 $id=$e['id'];$cat=$e['catalog_id'];$reasons=[];$sources=$c['sources'][$cat]??[];
 if(!$e['proofs'])$reasons[]='no_independent_same_operator_proof';
 if(count($sources)!==1)$reasons[]='source_missing_or_duplicate';
 $row=count($sources)===1?$sources[0]:null;
 if($row&&$row['decision_status']==='accepted'&&$row['local_hotel_id']!==null&&(int)$row['local_hotel_id']===$id&&w76_evidence_valid($row))return['status'=>'already_accepted_same','reasons'=>[]];
 if($row){
  if($row['decision_status']!=='pending'||$row['local_hotel_id']!==null)$reasons[]='source_not_pending_null';
  if(!w76_evidence_valid($row))$reasons[]='prior_evidence_invalid';
  if($row['evidence_sha256']!==$e['prior']['evidence_sha256']||$row['catalog_sha256']!==$e['prior']['catalog_sha256'])$reasons[]='prior_evidence_changed';
  $ev=json_decode((string)$row['evidence_json'],true);if(!is_array($ev))$ev=[];
  if(array_diff(array_keys($ev),['source','reason','operation_id','candidate_ids','geography','target_name']))$reasons[]='unknown_prior_decision_fields';
  if(($ev['reason']??'')!=='no_unique_name'||(isset($ev['operation_id'])&&$ev['operation_id']!=='andromeda-1759-country6-20260910-v1'))$reasons[]='manual_or_nonautomatic_origin';
  if(!w76_source_projection_matches($ev['source']??null,$e['history']['source']??null))$reasons[]='prior_source_projection_changed';
  $candidates=$ev['candidate_ids']??[];if(!is_array($candidates))$reasons[]='invalid_candidate_history';elseif($candidates&&!in_array((string)$id,array_map('strval',$candidates),true))$reasons[]='historical_candidate_other_target';
 }
 foreach($c['targets'][$id]??[]as$r)if((string)$r['external_hotel_id']!==$cat)$reasons[]='target_catalog_occupied';
 $h=$c['hotels'][$id]??null;
 if(!$h||(int)$h['is_active']!==1)$reasons[]='target_inactive_or_missing';
 elseif(w76_target($h)!==$e['target'])$reasons[]='target_facts_changed';
 if(empty($c['live'][$id]))$reasons[]='outside_tv_live30';
 if(!empty($c['manual'][$id]))$reasons[]='manual_target_protected';
 if(!empty($c['exclusions'][$id]))$reasons[]='pair_exclusion_protected';
 $ids=array_map('strval',array_keys($c['effective']['by_local'][$id]??[]));sort($ids,SORT_STRING);$expected=$e['direct_anex_ids'];sort($expected,SORT_STRING);
 if(!$ids||$ids!==$expected)$reasons[]='direct_anex_anchor_changed';
 $source=$e['history']['source'];
 if($h){
  if(w76_text($source['state']??'')!==w76_text($h['country_name']??'')||preg_match('/^(?:россия|абхазия|russia|abkhazia)$/ui',(string)$h['country_name']))$reasons[]='country_conflict';
  if((int)($source['starKey']??0)!==(int)($h['category']??0))$reasons[]='category_conflict';
  $town=w76_text($source['town']??'');if($town===''||!in_array($town,[w76_text($h['region_name']??''),w76_text($h['subregion_name']??'')],true))$reasons[]='geography_requires_review';
 }
 foreach($e['candidate']['lanes']as$ns=>$lane){
  if($lane['state']!=='single_native'||count($lane['native_ids'])!==1)continue;$n=(string)$lane['native_ids'][0];
  foreach($c['operators'][$ns][$n]??[]as$r){if(!w76_evidence_valid($r))$reasons[]='operator_evidence_invalid';if($r['decision_status']==='accepted'&&$r['local_hotel_id']!==null&&(int)$r['local_hotel_id']!==$id)$reasons[]=$ns.'_other_target';elseif($r['decision_status']!=='accepted')$reasons[]=$ns.'_protected';}
 }
 $reasons=array_values(array_unique($reasons));return['status'=>$reasons?'hold':'ready_pending_transition','reasons'=>$reasons];
}
function w76_index(array $rows):array{$out=[];foreach($rows as$r){$k=$r['supplier_namespace'].'|'.$r['external_hotel_id'];w76_need(!isset($out[$k]),'duplicate_identity');$out[$k]=$r;}return$out;}
function w76_verify(array $before,array $planned,array $rows):array{
 $after=w76_index($rows);w76_need(count($after)===count($before),'identity_count_changed');$read=[];
 foreach($before as$key=>$row){w76_need(isset($after[$key]),'identity_lost');$now=$after[$key];if(!isset($planned[$key])){w76_need(w76_hash($now)===w76_hash($row),'unrelated_identity_changed');continue;}
  $p=$planned[$key];foreach($row as$field=>$value)if(!in_array($field,['local_hotel_id','decision_status','evidence_sha256','evidence_json','updated_at'],true))w76_need((string)($now[$field]??'')===(string)$value,'original_field_changed');
  w76_need((int)$now['local_hotel_id']===$p['id']&&$now['decision_status']==='accepted'&&$now['evidence_json']===$p['new_json']&&$now['evidence_sha256']===$p['new_sha']&&w76_evidence_valid($now),'changed_row_mismatch');
  $ev=json_decode($now['evidence_json'],true);w76_need($ev['prior_evidence_json']===$row['evidence_json']&&$ev['prior_evidence_sha256']===$row['evidence_sha256'],'history_not_preserved');
  $read[]=['local_hotel_id'=>$p['id'],'catalog_id'=>(string)$now['external_hotel_id'],'name'=>$p['name'],'catalog_sha256'=>$now['catalog_sha256'],'evidence_sha256'=>$now['evidence_sha256'],'prior_evidence_sha256'=>$row['evidence_sha256'],'proof_operator_count'=>$p['proof_count']];
 }return$read;
}
function w76_census(PDO $db):array{
 require_once __DIR__.'/hotel_match_anex_effective_coverage.php';$a=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$s=[];
 foreach(w76_q($db,"SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")as$r)$s[(int)$r['local_hotel_id']]=true;
 $live=w76_q($db,"SELECT DISTINCT h.id FROM catalog_hotels h JOIN tour_operator_identity_observations o ON o.hotel_id=h.id WHERE h.is_active=1 AND o.last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND h.country_name NOT IN ('Россия','Абхазия','Russia','Abkhazia','Russian Federation')");
 $n=['tv_total'=>count($live),'full_triple'=>0,'samo_only'=>0,'anex_only'=>0,'neither'=>0];foreach($live as$r){$id=(int)$r['id'];$sa=isset($s[$id]);$an=!empty($a['by_local'][$id]);$n[$sa?($an?'full_triple':'samo_only'):($an?'anex_only':'neither')]++;}return$n;
}
function w76_execute(PDO $db,array $entries,string $head,string $dir):array{
 require_once __DIR__.'/hotel_match_anex_effective_coverage.php';w76_need(array_keys($entries)===array_keys(W76_PAIRS)&&!$db->inTransaction(),'writer_scope');
 $attempt=false;$committed=false;$planned=[];$held=[];$already=[];$before=[];$coverageBefore=null;
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 try{
  foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions']as$t){$r=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);w76_need(count($r)===1&&strtoupper($r[0]['ENGINE'])==='INNODB','nontransactional_table');}
  $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');w76_need($db->beginTransaction(),'begin');
  $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');$before=w76_index($all);$c=['sources'=>[],'targets'=>[],'operators'=>[],'hotels'=>[],'manual'=>[],'exclusions'=>[],'live'=>[]];
  foreach($all as$r){$ns=$r['supplier_namespace'];$n=(string)$r['external_hotel_id'];if($ns==='andromeda_catalog'){$c['sources'][$n][]=$r;if($r['local_hotel_id']!==null)$c['targets'][(int)$r['local_hotel_id']][]=$r;}else$c['operators'][$ns][$n][]=$r;}
  $ids=array_keys($entries);$ph=implode(',',array_fill(0,count($ids),'?'));$natives=[];foreach($entries as$e)foreach($e['direct_anex_ids']as$n)$natives[$n]=true;$natives=array_keys($natives);$nh=implode(',',array_fill(0,count($natives),'?'));
  foreach(w76_q($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids)as$r)$c['hotels'][(int)$r['id']]=$r;
  foreach(w76_q($db,"SELECT hotel_id,last_seen_at FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) ORDER BY hotel_id FOR UPDATE",$ids)as$r)$c['live'][(int)$r['hotel_id']]=true;
  foreach(w76_q($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) OR anex_hotel_id IN ($nh) FOR UPDATE",array_merge($ids,$natives))as$r){foreach($entries as$id=>$e)if((int)($r['catalog_hotel_id']??0)===$id||in_array((string)$r['anex_hotel_id'],$e['direct_anex_ids'],true))$c['manual'][$id]=true;}
  foreach(w76_q($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph) OR anex_hotel_id IN ($nh) FOR UPDATE",array_merge($ids,$natives))as$r){foreach($entries as$id=>$e)if((int)($r['catalog_hotel_id']??0)===$id||in_array((string)$r['anex_hotel_id'],$e['direct_anex_ids'],true))$c['exclusions'][$id]=true;}
  w76_q($db,"SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE catalog_hotel_id IN ($ph) OR anex_hotel_id IN ($nh) FOR UPDATE",array_merge($ids,$natives));$c['effective']=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$coverageBefore=w76_census($db);
  foreach($entries as$id=>$e){$decision=w76_classify($e,$c);$item=['local_hotel_id'=>$id,'catalog_id'=>$e['catalog_id'],'name'=>$e['target']['name']]+$decision;if($decision['status']==='hold'){$held[]=$item;continue;}if($decision['status']==='already_accepted_same'){$already[]=$item;continue;}
   $old=$c['sources'][$e['catalog_id']][0];$ev=['operation_id'=>W76_OP,'rule'=>'exact_same_operator_pending_null_transition','source_sha'=>$head,'inputs'=>W76_PINS,'catalog_sha256_preserved'=>$old['catalog_sha256'],'prior_evidence_sha256'=>$old['evidence_sha256'],'prior_evidence_json'=>$old['evidence_json'],'source'=>$e['history']['source'],'target'=>$c['hotels'][$id],'proofs'=>$e['proofs'],'direct_anex_ids'=>$e['direct_anex_ids'],'required_exact_operators'=>1,'provider_http_calls'=>0];$raw=w76_json($ev);$key='andromeda_catalog|'.$e['catalog_id'];$planned[$key]=['id'=>$id,'cat'=>$e['catalog_id'],'name'=>$e['target']['name'],'old'=>$old,'new_json'=>$raw,'new_sha'=>hash('sha256',$raw),'proof_count'=>count($e['proofs'])];
  }
  w76_save($dir.'/write-plan.json',['operation'=>W76_OP,'source_sha'=>$head,'planned'=>$planned,'held'=>$held,'already'=>$already,'before_identity_sha256'=>w76_hash($before)]);
  if(!$planned){$db->rollBack();return['state'=>'completed_no_new_writes','transitioned'=>0,'rows'=>[],'held'=>$held,'already'=>$already,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true,'prior_evidence_preserved'=>true,'unrelated_identities_unchanged'=>true,'coverage_before'=>$coverageBefore,'coverage_after'=>$coverageBefore];}
  $st=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");
  foreach($planned as$p){w76_need($st->execute([$p['id'],$p['new_sha'],$p['new_json'],$p['cat'],$p['old']['catalog_sha256'],$p['old']['evidence_sha256']])&&$st->rowCount()===1,'conditional_update_failed');}
  w76_verify($before,$planned,w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));
  w76_save($dir.'/pre-commit.json',['operation'=>W76_OP,'planned_count'=>count($planned),'planned_sha256'=>w76_hash($planned),'held'=>$held]);w76_save($dir.'/commit-attempt.json',['operation'=>W76_OP,'planned_count'=>count($planned),'state'=>'commit_attempt_no_replay']);
  $attempt=true;w76_need($db->commit(),'commit_false');$committed=true;
  $db->exec('START TRANSACTION READ ONLY');$read=w76_verify($before,$planned,w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));$coverageAfter=w76_census($db);$db->rollBack();
  return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'transitioned'=>count($read),'rows'=>$read,'held'=>$held,'already'=>$already,'database_writes'=>count($read),'mapping_writes'=>count($read),'readback_verified'=>true,'prior_evidence_preserved'=>true,'unrelated_identities_unchanged'=>true,'coverage_before'=>$coverageBefore,'coverage_after'=>$coverageAfter];
 }catch(Throwable $e){if($db->inTransaction())try{$db->rollBack();}catch(Throwable){}return['state'=>$attempt?($committed?'committed_readback_unconfirmed':'commit_outcome_unknown_no_replay'):'rolled_back_no_writes','reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'writer_failed','commit_attempted'=>$attempt,'commit_completed'=>$committed,'database_writes'=>$committed?count($planned):($attempt?null:0),'mapping_writes'=>$committed?count($planned):($attempt?null:0),'readback_verified'=>false,'rows'=>[],'held'=>$held,'already'=>$already];}
}
function w76_main(array $args):int{
 w76_need(PHP_SAPI==='cli'&&($args[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 w76_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===W76_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'runtime_scope');
 $res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);w76_need(($res['operation']??'')===W76_OP&&($res['source_sha']??'')===$head&&($res['state']??'')==='reserved_before_db_write'&&($res['maximum_writes']??0)===8,'reservation');foreach(['execution-started.json','write-plan.json','result.json','receipt.json','commit-attempt.json']as$f)w76_need(!file_exists($dir.'/'.$f),'terminal_no_replay');
 $entries=w76_prepare(dirname(__DIR__,2).'/input');w76_save($dir.'/execution-started.json',['operation'=>W76_OP,'source_sha'=>$head]);
 try{$boot=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');w76_need(is_file($boot)&&!is_link($boot),'bootstrap');require_once $boot;w76_need(function_exists('v2_data_db'),'db_factory');$out=w76_execute(v2_data_db(),$entries,$head,$dir);}catch(Throwable $e){$out=['state'=>'failed_before_writer','reason'=>'bootstrap_or_input_failure','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false,'rows'=>[]];}
 $out+=['operation'=>W76_OP,'source_sha'=>$head,'provider_http_calls'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];$sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>W76_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$sha,'result_file_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'database_readback_verified'=>$out['readback_verified'],'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'provider_http_calls'=>0,'no_replay'=>true]);echo w76_json(array_diff_key($out,['rows'=>true]))."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(w76_main($argv));
