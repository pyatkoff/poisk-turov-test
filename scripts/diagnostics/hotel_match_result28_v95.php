<?php
declare(strict_types=1);
/** Original acquisition result edges are evidence even without a sidecar. */
require_once __DIR__.'/hotel_match_retained_mass47_v94.php';
const M95_OP='hotel-match-retained-result28-1971-20260928-v95';
const M95_PRIOR_SHA='745da2583afbd911df13c4e8a53757a4f81f994fba2d2f6e549dacc11ec05a50';
const M95_ANEX=[129437=>'42644',141989=>'44101',4029=>'5561',4041=>'5572',4051=>'5623',4087=>'5625',13946=>'5626',184=>'464',130835=>'44936',127476=>'41291',1748=>'11805',1757=>'11806',55653=>'41132',57823=>'41581',59441=>'20470',125098=>'42982',129317=>'43904',119712=>'39850',963=>'35700',1451=>'15080',1049=>'8261',132075=>'37719'];
const M95_SAMO=[420=>['9501',43,'24402'],16944=>['2000034238',25,'211585'],66290=>['2000052048',43,'23147'],67304=>['2000055490',43,'23057'],108356=>['269426',43,'2977'],42903=>['3126',25,'849821']];
function m95_index_doc(array $doc,string $op,string $sha,array &$index):void{
 w76_need(($doc['operation']??null)===$op&&($doc['database_writes']??-1)===0&&($doc['mapping_writes']??-1)===0&&max((int)($doc['provider_calls']??0),(int)($doc['provider_http_calls']??0))>0&&w76_sha($sha),'original_acquisition_authority');
 foreach($doc['edges']??[]as$i=>$e){if(!is_array($e))continue;$id=(int)($e['tv_hotel_id']??0);$to=(int)($e['operator_id']??0);$ns=$e['positive_native_candidates']??[];if($id<1||!in_array($to,[13,25,43],true)||!is_array($ns)||count($ns)!==1)continue;$n=(string)$ns[0];$why=w84_edge_reasons($e);
  if($why){$index['rejected'][$id][$to][$n][]=['operation'=>$op,'json_pointer'=>'/edges/'.$i,'reasons'=>$why,'state'=>$e['state']??null,'namespace'=>$e['namespace']??null,'host'=>$e['operator_link_host']??null,'query_keys'=>$e['query_keys']??[]];continue;}
  $index['by_native'][$to][$n][$id]=true;$index['by_hotel'][$id][$to][$n]=true;
  $e['source_operation']=$op;$e['source_result_sha256']=$sha;$e['source_json_pointer']='/edges/'.$i;$e['source_edge_sha256']=w76_hash($doc['edges'][$i]);$index['edges'][$id][$to][$n][]=$e;
 }
}
function m95_prepare(string $ops):array{
 $prev=w84_read($ops.'/'.M94_OP.'/result.json',M95_PRIOR_SHA);w76_need(($prev['state']??'')==='committed_readback_verified'&&($prev['mapping_writes']??-1)===17&&($prev['readback_verified']??false)===true,'previous_write_receipt');
 $old=[];foreach($prev['held']as$r)if($r['reasons']===['global_tv_native_collision_or_absence','no_verified_raw_tv_edge','pinned_result_raw_edge_provenance_missing'])$old[($r['catalog_id']??null)!==null?'samo|'.$r['local_hotel_id']:'anex|'.$r['local_hotel_id']]=$r;
 w76_need(count($old)===28,'technical_hold_scope');
 $ledger=w84_read($ops.'/hotel-match-request-ledger-1971-20260928-v93/result.json',M94_LEDGER_SHA);$front=[];foreach($ledger['rows']as$r)$front[(int)$r['tv_hotel_id']]=$r;
 $index=['by_native'=>[],'by_hotel'=>[],'edges'=>[],'rejected'=>[]];$inputErrors=[];
 foreach($ledger['operation_summaries']as$r){$s=$r['result']??[];if(!w76_sha($s['sha256']??null)||max((int)($s['provider_calls']??0),(int)($s['provider_http_calls']??0))<1||($s['database_writes']??-1)!==0||($s['mapping_writes']??-1)!==0)continue;
  $op=$r['operation'];w76_need(preg_match('/^hotel-match-[A-Za-z0-9_-]+$/D',$op)===1,'operation_path');$d=w84_read($ops.'/'.$op.'/result.json',$s['sha256'],33554432);m95_index_doc($d,$op,$s['sha256'],$index);
 }
 $v=w84_read($ops.'/'.W84_NATIVE_OP.'/result.json',W84_NATIVE_SHA,67108864);$native=[];$facts=[];$prior=[];foreach($v['native_facts']as$f){$ns=(string)$f['supplier_namespace'];$n=(string)$f['native_id'];$cat=(string)$f['catalog_id'];$native[$ns][$n][$cat]=$f;$facts[$cat][$ns.'|'.$n]=['ns'=>$ns,'native'=>$n];}foreach($v['current_identities']as$r)if($r['supplier_namespace']==='andromeda_catalog')$prior[(string)$r['external_hotel_id']]=$r;
 $out=['captured_edge_count'=>28,'candidates'=>[],'direct'=>[],'held'=>[]];$items=[];foreach(M95_ANEX as$id=>$n)$items[]=['id'=>$id,'op'=>13,'native'=>$n,'cat'=>null];foreach(M95_SAMO as$id=>[$cat,$op,$n])$items[]=['id'=>$id,'op'=>$op,'native'=>$n,'cat'=>$cat];
 foreach($items as$x){$id=$x['id'];$op=$x['op'];$n=$x['native'];$cat=$x['cat'];$why=[];w76_need(isset($old[($cat===null?'anex|':'samo|').$id]),'prior_hold_membership');$rows=$index['edges'][$id][$op][$n]??[];
  if(!isset($front[$id]))$why[]='outside_pinned_frontier';
  if(count($index['by_native'][$op][$n]??[])!==1||count($index['by_hotel'][$id][$op]??[])!==1)$why[]='global_native_collision_or_missing';
  if(!$rows)$why[]='no_verified_original_result_edge';
  if(!in_array($n,array_map('strval',$front[$id]['lanes'][$op]['retained_exact']??[]),true))$why[]='outside_pinned_native_evidence';
  if($cat!==null){$ns=$op===25?'operator_315':'operator_342';if(count($native[$ns][$n]??[])!==1||!isset($native[$ns][$n][$cat]))$why[]='samo_native_collision_or_missing';if(!isset($prior[$cat]))$why[]='canonical_source_not_pinned';}
  if($why){$out['held'][]=['local_hotel_id'=>$id,'catalog_id'=>$cat,'anex_hotel_id'=>$cat===null?$n:null,'reasons'=>$why,'edge_validation'=>$index['rejected'][$id][$op][$n]??[]];continue;}
  $e=$rows[0];if($cat===null){$out['direct'][]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'expected_name'=>$front[$id]['hotel_name'],'proof'=>$e];continue;}
  try{$raw=w84_raw($ops,$native[$ns][$n][$cat],$cat,$ns,$n);}catch(Throwable){$out['held'][]=['local_hotel_id'=>$id,'catalog_id'=>$cat,'reasons'=>['raw_samo_proof_missing']];continue;}
  $out['candidates'][]=['id'=>$id,'catalog_id'=>$cat,'expected_name'=>$front[$id]['hotel_name'],'prior_scan'=>$prior[$cat],'operator_facts'=>array_values($facts[$cat]??[]),'prepare_holds'=>[],'proofs'=>[['namespace'=>$ns,'native_id'=>$n,'tv'=>['kind'=>'independent_tv_audit','row'=>$e],'samo'=>$raw,'samo_catalog_id'=>$cat]]];
 }
 w76_need(count($out['candidates'])+count($out['direct'])+count($out['held'])===28,'complete_scope');return$out;
}
function m95_write(PDO $db,array $p,array $cohort,string $head,string $dir):array{
 require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
 w76_need(!$db->inTransaction()&&($cohort['complete']??false)===true&&$p['captured_edge_count']<=28,'writer_scope');
 $attempt=false;$committed=false;$sql=false;$rolled=false;$planned=[];$anex=[];$held=$p['held'];$already=[];$beforeCensus=null;$beforeSamo=null;
 try{
  foreach(['andromeda_hotel_identities','anex_hotel_search_mappings','catalog_hotels','tour_operator_identity_observations','anex_hotel_decisions','anex_review_pair_exclusions']as$t){$x=w76_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);w76_need(count($x)===1&&strtoupper($x[0]['ENGINE'])==='INNODB','nontransactional');}
  $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');w76_need($db->beginTransaction(),'begin');
  $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE');$before=w76_index($all);
  $maps=w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');$mapBefore=w75_index($maps);
  $dec=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001 FOR UPDATE');$exc=w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001 FOR UPDATE');
  $c=['sources'=>[],'targets'=>[],'operators'=>[],'hotels'=>[],'live'=>[],'manual'=>[],'exclusions'=>[],'manual_target'=>[],'manual_source'=>[],'excluded_source'=>[],'mapping_source'=>[],'mapping_target'=>[],'op5_source'=>[]];
  foreach($all as$r){$ns=$r['supplier_namespace'];$n=(string)$r['external_hotel_id'];if($ns==='andromeda_catalog'){$c['sources'][$n][]=$r;if($r['local_hotel_id']!==null)$c['targets'][(int)$r['local_hotel_id']][]=$r;}else$c['operators'][$ns][$n][]=$r;if($ns==='operator_5')$c['op5_source'][$n][]=$r;}
  foreach($maps as$r){$c['mapping_source'][(string)$r['anex_hotel_id']][]=$r;$c['mapping_target'][(int)$r['catalog_hotel_id']][]=$r;}
  foreach($dec as$r){$c['manual_source'][(string)$r['anex_hotel_id']][]=$r;if($r['catalog_hotel_id']!==null){$c['manual'][(int)$r['catalog_hotel_id']]=true;$c['manual_target'][(int)$r['catalog_hotel_id']]=true;}}
  foreach($exc as$r){$c['excluded_source'][(string)$r['anex_hotel_id']][]=$r;$c['exclusions'][(int)$r['catalog_hotel_id']]=true;}
  $ids=[];foreach($p['candidates']as$e)$ids[$e['id']]=true;foreach($p['direct']as$e)$ids[$e['local_hotel_id']]=true;$ids=array_keys($ids);sort($ids,SORT_NUMERIC);
  if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));foreach(w76_q($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active,latitude,longitude FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids)as$r)$c['hotels'][(int)$r['id']]=$r;
   foreach(w76_q($db,"SELECT hotel_id,last_seen_at FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) ORDER BY hotel_id FOR UPDATE",$ids)as$r)$c['live'][(int)$r['hotel_id']]=true;}
  $c['effective']=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$beforeCensus=w76_census($db);$beforeSamo=w84_samo_census($all,$cohort);
  foreach($p['candidates']as$e){$d=m94_classify($e,$c,$cohort);$item=['local_hotel_id'=>$e['id'],'catalog_id'=>$e['catalog_id']]+$d;if($d['status']==='hold'){$held[]=$item;continue;}if($d['status']==='already'){$already[]=$item;continue;}
   $old=$c['sources'][$e['catalog_id']][0];$history=json_decode($old['evidence_json'],true);$ev=['operation_id'=>M95_OP,'rule'=>'one_exact_same_operator_raw_evidence','source_sha'=>$head,'ledger_result_sha256'=>M94_LEDGER_SHA,'native_result_sha256'=>W84_NATIVE_SHA,'source'=>$history['source'],'target'=>$c['hotels'][$e['id']],'proofs'=>$e['proofs'],'prior_evidence_json'=>$old['evidence_json'],'prior_evidence_sha256'=>$old['evidence_sha256'],'catalog_sha256_preserved'=>$old['catalog_sha256'],'required_exact_operators'=>1,'provider_http_calls'=>0];$raw=w76_json($ev);$key='andromeda_catalog|'.$e['catalog_id'];w76_need(!isset($planned[$key]),'duplicate_plan');$planned[$key]=['id'=>$e['id'],'cat'=>$e['catalog_id'],'name'=>$c['hotels'][$e['id']]['name'],'old'=>$old,'new_json'=>$raw,'new_sha'=>hash('sha256',$raw),'proof_count'=>count($e['proofs'])];
  }
  foreach($p['direct']as$e){$id=$e['local_hotel_id'];$n=$e['anex_hotel_id'];$d=a74_anex($id,$n,$c);if(!empty($c['exclusions'][$id])){$d['status']='hold';$d['reasons'][]='excluded_target_protected';}
   if(isset($e['expected_name'])&&($c['hotels'][$id]['name']??null)!==$e['expected_name']){$d['status']='hold';$d['reasons'][]='target_name_drift';}
   if($d['status']==='already_effective_same'){$already[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'status'=>'already'];continue;}if($d['status']!=='source_missing_needs_identity_proof'||$d['reasons']){$held[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'reasons'=>$d['reasons']];continue;}
   $ev=['operation'=>M95_OP,'source_sha'=>$head,'authority'=>'verified_TV_operator13_HOTELLIST','proof'=>$e['proof'],'current_target'=>$c['hotels'][$id],'provider_http_calls'=>0];$anex[]=['anex_hotel_id'=>$n,'catalog_hotel_id'=>$id,'match_class'=>W75_CLASS,'scope'=>'preview','approval_policy'=>W75_POLICY,'source_row_digest'=>w75_digest($ev),'mapping_digest'=>'','enabled'=>1,'evidence'=>$ev];
  }
  w76_need(count($planned)+count($anex)<=28,'write_cap');$batch=w75_digest(['operation'=>M95_OP,'rows'=>$anex]);foreach($anex as&$a)$a['mapping_digest']=$batch;unset($a);
  w76_save($dir.'/write-plan.json',['operation'=>M95_OP,'source_sha'=>$head,'primary'=>$planned,'direct_anex'=>$anex,'held'=>$held,'already'=>$already,'identities_before_sha256'=>w76_hash($before),'mappings_before_sha256'=>w75_digest($mapBefore),'tv_before'=>$beforeCensus,'samo_before'=>$beforeSamo]);
  if(!$planned&&!$anex){$db->rollBack();return['state'=>'completed_no_new_writes','database_writes'=>0,'mapping_writes'=>0,'primary_samo_writes'=>0,'direct_anex_writes'=>0,'rows'=>[],'direct_rows'=>[],'held'=>$held,'already'=>$already,'readback_verified'=>true,'coverage_before'=>$beforeCensus,'coverage_after'=>$beforeCensus,'samo_before'=>$beforeSamo,'samo_after'=>$beforeSamo];}
  $st=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");foreach($planned as$x){$sql=true;w76_need($st->execute([$x['id'],$x['new_sha'],$x['new_json'],$x['cat'],$x['old']['catalog_sha256'],$x['old']['evidence_sha256']])&&$st->rowCount()===1,'conditional_update');}
  $st=$db->prepare('INSERT INTO anex_hotel_search_mappings ('.W75_COLUMNS.') VALUES (?,?,?,?,?,?,?,?)');foreach($anex as$x){$sql=true;w76_need($st->execute(array_map(fn($k)=>$x[$k],explode(',',W75_COLUMNS)))&&$st->rowCount()===1,'conditional_insert');}
  $verify=function()use($db,$before,$planned,$mapBefore,$anex,$dec,$exc):array{
   $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');$rows=w76_verify($before,$planned,$all);w75_verify($mapBefore,$anex,w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),true);
   w76_need(w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001')===$dec,'manual_changed');w76_need(w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001')===$exc,'exclusions_changed');
   $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($anex as$x)w76_need($registry->resolve('anex_online',$x['anex_hotel_id'],'preview')===$x['catalog_hotel_id'],'registry_mismatch');return[$rows,$all];
  };$verify();w76_save($dir.'/commit-attempt.json',['operation'=>M95_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>count($planned)+count($anex)]);$attempt=true;w76_need($db->commit(),'commit');$committed=true;
  $db->exec('START TRANSACTION READ ONLY');[$rows,$after]=$verify();$afterCensus=w76_census($db);$afterSamo=w84_samo_census($after,$cohort);$db->rollBack();return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'database_writes'=>count($planned)+count($anex),'mapping_writes'=>count($planned)+count($anex),'primary_samo_writes'=>count($planned),'direct_anex_writes'=>count($anex),'rows'=>$rows,'direct_rows'=>array_map(fn($a)=>array_diff_key($a,['evidence'=>true]),$anex),'held'=>$held,'already'=>$already,'readback_verified'=>true,'prior_evidence_preserved'=>true,'unrelated_identities_and_mappings_unchanged'=>true,'coverage_before'=>$beforeCensus,'coverage_after'=>$afterCensus,'samo_before'=>$beforeSamo,'samo_after'=>$afterSamo];
 }catch(Throwable$x){if($db->inTransaction())try{$rolled=$db->rollBack();}catch(Throwable){}$count=$committed?count($planned)+count($anex):(($attempt||($sql&&!$rolled))?null:0);return['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':($rolled?'rolled_back_no_writes':'failed_before_writer')),'reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'writer_failed','error_class'=>get_class($x),'commit_attempted'=>$attempt,'commit_completed'=>$committed,'database_writes'=>$count,'mapping_writes'=>$count,'readback_verified'=>false,'held'=>$held,'already'=>$already,'coverage_before'=>$beforeCensus,'samo_before'=>$beforeSamo];}
}
function m95_main(array $a):int{
 w76_need(($a[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 w76_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===M95_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'scope');
 $rv=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);w76_need(($rv['operation']??null)===M95_OP&&($rv['source_sha']??null)===$head&&($rv['maximum_writes']??0)===28&&($rv['provider_http_calls']??-1)===0,'reservation');
 foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json']as$f)w76_need(!file_exists($dir.'/'.$f),'no_replay');w76_save($dir.'/execution-started.json',['operation'=>M95_OP,'source_sha'=>$head]);
 try{$p=m95_prepare(dirname($dir));$cohort=w84_live_sources($root);w76_save($dir.'/samo-cohort.json',$cohort);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=m95_write(v2_data_db(),$p,$cohort,$head,$dir);}
 catch(Throwable$x){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'prepare_failed','error_class'=>get_class($x),'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
 $out+=['operation'=>M95_OP,'source_sha'=>$head,'provider_http_calls'=>0,'new_operator_ids'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];$sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>M95_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$sha,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);echo w76_json(array_diff_key($out,['rows'=>true,'direct_rows'=>true,'held'=>true,'already'=>true,'samo_before'=>true,'samo_after'=>true]))."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(m95_main($argv));
