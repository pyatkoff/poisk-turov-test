<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_retained_mass47_v94.php';
const M98_OP='hotel-match-newcontext-anex3-write-1971-20260928-v98';
const M98_ACQ_OP='hotel-match-newcontext-samoonly200-acquire-1971-20260928-v97';
const M98_ACQ_SHA='25dbbb4d2c693e52c3dd367300b762da5e8379195649aaf84edb087b29fb3bb6';
const M98_PLAN_OP='hotel-match-newcontext-samoonly200-plan-1971-20260928-v96';
const M98_PLAN_SHA='4d4e0f8ea69c71d07a773f9063565f5ba51620c2290584647956928bfe5736c1';
function m98_need(bool$b,string$m):void{if(!$b)throw new RuntimeException($m);}
function m98_prepare(string$ops):array{
 $a=w84_read($ops.'/'.M98_ACQ_OP.'/result.json',M98_ACQ_SHA,16777216);$p=w84_read($ops.'/'.M98_PLAN_OP.'/result.json',M98_PLAN_SHA,16777216);
 m98_need(($a['state']??'')==='completed_read_only'&&($a['provider_calls']??-1)===70&&($a['scope_count']??0)===200&&($a['database_writes']??-1)===0&&($a['mapping_writes']??-1)===0,'acq');
 m98_need(($p['state']??'')==='completed_read_only_newcontext_plan'&&($p['scope_count']??0)===200&&($p['provider_http_calls']??-1)===0,'plan');
 $names=[];foreach($p['rows']as$r)$names[(int)$r['tv_hotel_id']]=$r['hotel_name'];
 $byN=[];$byH=[];$out=[];
 foreach($a['edges']as$i=>$e){if((int)($e['operator_id']??0)!==13||($e['namespace']??'')!=='anex'||($e['state']??'')!=='detail_identity_verified'||($e['link_state']??'')!=='captured_single_native')continue;
  m98_need(w84_edge_reasons($e)===[]&&count($e['positive_native_candidates']??[])===1,'edge');$id=(int)$e['tv_hotel_id'];$n=(string)$e['positive_native_candidates'][0];m98_need(isset($names[$id]),'name');
  $byN[$n][$id]=true;$byH[$id][$n]=true;$e['source_operation']=M98_ACQ_OP;$e['source_result_sha256']=M98_ACQ_SHA;$e['source_json_pointer']='/edges/'.$i;$e['source_edge_sha256']=w76_hash($a['edges'][$i]);
  $out[$id]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'expected_name'=>$names[$id],'proof'=>$e];
 }
 foreach($out as$id=>$x)m98_need(count($byN[$x['anex_hotel_id']])===1&&count($byH[$id])===1,'unique');
 m98_need(count($out)===3&&isset($out[1070],$out[3469],$out[55797]),'scope3');return$out;
}
function m98_write(PDO$db,array$entries,string$head,string$dir):array{
 require_once __DIR__.'/hotel_match_anex_effective_coverage.php';m98_need(!$db->inTransaction()&&count($entries)===3,'scope');
 $attempt=false;$committed=false;$sql=false;$rolled=false;$planned=[];$held=[];$already=[];$beforeC=null;
 try{
  foreach(['anex_hotel_search_mappings','catalog_hotels','tour_operator_identity_observations','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities']as$t){$x=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);m98_need(count($x)===1&&strtoupper($x[0]['ENGINE'])==='INNODB','engine');}
  $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');m98_need($db->beginTransaction(),'begin');
  $maps=w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');$before=w75_index($maps);
  $dec=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');$exc=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001 FOR UPDATE');
  $ids=array_keys($entries);$ph=implode(',',array_fill(0,count($ids),'?'));$hot=[];$live=[];
  foreach(w76_q($db,"SELECT id,name,country_id,country_name,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids)as$r)$hot[(int)$r['id']]=$r;
  foreach(w76_q($db,"SELECT hotel_id FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) ORDER BY hotel_id FOR UPDATE",$ids)as$r)$live[(int)$r['hotel_id']]=true;
  $c=['hotels'=>$hot,'live'=>$live,'mapping_source'=>[],'mapping_target'=>[],'manual_source'=>[],'manual_target'=>[],'excluded_source'=>[],'op5_source'=>[],'effective'=>AnyTourMatchAnexEffectiveCoverage::fromPdo($db)];
  foreach($maps as$r){$c['mapping_source'][(string)$r['anex_hotel_id']][]=$r;$c['mapping_target'][(int)$r['catalog_hotel_id']][]=$r;}
  foreach($dec as$r){$c['manual_source'][(string)$r['anex_hotel_id']][]=$r;if($r['catalog_hotel_id']!==null)$c['manual_target'][(int)$r['catalog_hotel_id']][]=$r;}
  foreach($exc as$r)$c['excluded_source'][(string)$r['anex_hotel_id']][]=$r;
  foreach(w76_q($db,"SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='operator_5' ORDER BY external_hotel_id LIMIT 50001 FOR UPDATE")as$r)$c['op5_source'][(string)$r['external_hotel_id']][]=$r;
  $beforeC=w76_census($db);
  foreach($entries as$id=>$e){$n=$e['anex_hotel_id'];$d=a74_anex($id,$n,$c);$reasons=$d['reasons'];
   if(!isset($hot[$id])||(int)$hot[$id]['is_active']!==1||$hot[$id]['name']!==$e['expected_name'])$reasons[]='target_drift';
   if(!isset($live[$id]))$reasons[]='outside_tv_live30';
   if($d['status']==='already_effective_same'){$already[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n];continue;}
   if($d['status']!=='source_missing_needs_identity_proof'||$reasons){$held[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'reasons'=>array_values(array_unique($reasons))];continue;}
   $ev=['operation'=>M98_OP,'source_sha'=>$head,'authority'=>'verified_TV_operator13_HOTELLIST_new_context','proof'=>$e['proof'],'current_target'=>$hot[$id],'provider_http_calls'=>0];
   $planned[]=['anex_hotel_id'=>$n,'catalog_hotel_id'=>$id,'match_class'=>W75_CLASS,'scope'=>'preview','approval_policy'=>W75_POLICY,'source_row_digest'=>w75_digest($ev),'mapping_digest'=>'','enabled'=>1,'evidence'=>$ev];
  }
  $batch=w75_digest(['operation'=>M98_OP,'rows'=>$planned]);foreach($planned as&$x)$x['mapping_digest']=$batch;unset($x);
  w76_save($dir.'/write-plan.json',['operation'=>M98_OP,'source_sha'=>$head,'planned'=>$planned,'held'=>$held,'already'=>$already,'coverage_before'=>$beforeC]);
  if(!$planned){$db->rollBack();return['state'=>'completed_no_new_writes','database_writes'=>0,'mapping_writes'=>0,'direct_anex_writes'=>0,'held'=>$held,'already'=>$already,'coverage_before'=>$beforeC,'coverage_after'=>$beforeC,'readback_verified'=>true];}
  $st=$db->prepare('INSERT INTO anex_hotel_search_mappings ('.W75_COLUMNS.') VALUES (?,?,?,?,?,?,?,?)');foreach($planned as$x){$sql=true;m98_need($st->execute(array_map(fn($k)=>$x[$k],explode(',',W75_COLUMNS)))&&$st->rowCount()===1,'insert');}
  w75_verify($before,$planned,w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),true);
  m98_need(w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001')===$dec,'manual_changed');m98_need(w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001')===$exc,'exclusion_changed');
  w76_save($dir.'/commit-attempt.json',['operation'=>M98_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>count($planned)]);$attempt=true;m98_need($db->commit(),'commit');$committed=true;
  $db->exec('START TRANSACTION READ ONLY');w75_verify($before,$planned,w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),false);$reg=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($planned as$x)m98_need($reg->resolve('anex_online',$x['anex_hotel_id'],'preview')===$x['catalog_hotel_id'],'registry');$afterC=w76_census($db);$db->rollBack();
  return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'database_writes'=>count($planned),'mapping_writes'=>count($planned),'direct_anex_writes'=>count($planned),'held'=>$held,'already'=>$already,'coverage_before'=>$beforeC,'coverage_after'=>$afterC,'readback_verified'=>true];
 }catch(Throwable$x){if($db->inTransaction())try{$rolled=$db->rollBack();}catch(Throwable){}$n=$committed?count($planned):(($attempt||($sql&&!$rolled))?null:0);return['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':($rolled?'rolled_back_no_writes':'failed_before_writer')),'reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'writer_failed','database_writes'=>$n,'mapping_writes'=>$n,'readback_verified'=>false,'coverage_before'=>$beforeC];}
}
function m98_main(array$a):int{
 m98_need(($a[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');m98_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===M98_OP&&preg_match('/^[a-f0-9]{40}$/D',$head),'scope');
 $rv=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);m98_need(($rv['operation']??'')===M98_OP&&($rv['source_sha']??'')===$head&&($rv['maximum_writes']??0)===3&&($rv['provider_http_calls']??-1)===0,'reservation');foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json']as$f)m98_need(!file_exists($dir.'/'.$f),'no_replay');w76_save($dir.'/execution-started.json',['operation'=>M98_OP,'source_sha'=>$head]);
 try{$e=m98_prepare(dirname($dir));require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=m98_write(v2_data_db(),$e,$head,$dir);}catch(Throwable$x){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'prepare_failed','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
 $out+=['operation'=>M98_OP,'source_sha'=>$head,'provider_http_calls'=>0,'new_operator_ids'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];$sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>M98_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);echo w76_json($out)."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(m98_main($argv));
