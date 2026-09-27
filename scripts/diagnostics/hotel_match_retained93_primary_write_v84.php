<?php
declare(strict_types=1);
/** Foreground retained-only MATCH. No supplier clients, schema changes or replay. */
require_once __DIR__.'/hotel_match_raw_native_pending11_v78.php';
require_once __DIR__.'/hotel_match_retained82_writer_v75.php';
const W84_OP='hotel-match-retained93-primary-write-1971-20260927-v84';
const W84_INPUTS=[
 'hotel-match-current100-common4-acquire-1971-20260927-v80'=>['04afbb2bf6aff100a426e0bc97457e31c64abda2bdc9707b1335ce3cf37024a2',24],
 'hotel-match-current100-common4-acquire-1971-20260927-v81'=>['48682ee3bd28499a1696da90b9f72c77d5569e705d579800547af63b470c8b5f',26],
 'hotel-match-current100-dedicated-account-acquire-1971-20260927-v82'=>['dfa4527126e25840b79aa56787503051041ae27a0444ec4e028781b14bf14898',43],
];
const W84_NATIVE_OP='hotel-match-live30-retained-native-union-1971-20260927-v77';
const W84_NATIVE_SHA='d40fbe2e0240a5df194ac838376a3425a8f4e80f2426a757560fe369011107f7';
function w84_read(string $path,string $sha,int $cap=16777216):array {
 w76_need(w76_sha($sha)&&is_file($path)&&!is_link($path)&&filesize($path)<=$cap,'retained_file');
 $b=file_get_contents($path);w76_need(is_string($b)&&hash_equals($sha,hash('sha256',$b)),'retained_digest');
 $j=json_decode($b,true,128,JSON_THROW_ON_ERROR);w76_need(is_array($j),'retained_json');return $j;
}
function w84_edge_reasons(array $e):array {
 $why=[];$op=(int)($e['operator_id']??0);$id=(int)($e['tv_hotel_id']??0);$ns=[25=>'operator_315',43=>'operator_342'];
 if(($e['state']??'')!=='detail_identity_verified'||($e['link_state']??'')!=='captured_single_native'||!in_array($op,[13,18,25,43],true)||$id<1)$why[]='edge_identity_invalid';
 $n=$e['positive_native_candidates']??null;
 if(!is_array($n)||count($n)!==1||preg_match('/^[1-9][0-9]{0,19}$/D',(string)($n[0]??''))!==1)$why[]='native_shape';
 foreach(['search_id_sha256','tour_id_sha256','operator_link_sha256']as$k)if(!w76_sha($e[$k]??null))$why[]='edge_provenance';
 $host=strtolower((string)($e['operator_link_host']??''));
 if($op===13&&(!in_array($host,['agent.anextour.ru','online.anextour.ru','anextour.ru'],true)||!in_array('hotellist',array_map('strtolower',$e['query_keys']??[]),true)))$why[]='anex_hotellist_identity';
 if(isset($ns[$op])&&(($e['namespace']??'')!==$ns[$op]||$host!==($op===25?'b2b.fstravel.com':'searchtour.intourist.ru')))$why[]='operator_namespace_or_host';
 if($op===18)$why[]='biblio_f4_requires_dictionary_not_operator115';
 return array_values(array_unique($why));
}
function w84_raw(string $ops,array $fact,string $cat,string $ns,string $native):array {
 foreach($fact['evidence']??[]as$p){
  if(!is_array($p)||!preg_match('~^operations/(hotel-match-[a-zA-Z0-9_-]+)/((?:evidence-private/[a-zA-Z0-9_.-]+|raw-[a-zA-Z0-9_.-]+)\.json)$~D',(string)($p['source_file']??''),$m))continue;
  $path=$ops.'/'.$m[1].'/'.$m[2];$r=realpath($path);$base=realpath($ops);
  if($base===false||$r===false||!str_starts_with($r,$base.'/'))continue;
  try{$raw=w84_read($path,(string)($p['sha256']??''));$row=w78_ptr($raw,(string)($p['json_pointer']??''));if(w78_fact($row,$cat,$ns,$native))return$p;}catch(Throwable){}
 }
 throw new RuntimeException('verified_raw_native_proof_missing');
}
/** Bind exact same-operator IDs, keeping ambiguous rows out of unrelated work. */
function w84_bind(array $edges,array $v77,string $ops):array {
 $native=[];$catFacts=[];$prior=[];$tv=[];$lanes=[];
 foreach($v77['native_facts']??[]as$f){$ns=(string)($f['supplier_namespace']??'');$n=(string)($f['native_id']??'');$cat=(string)($f['catalog_id']??'');if($ns===''||$n===''||$cat==='')continue;$native[$ns][$n][$cat]=$f;$catFacts[$cat][$ns.'|'.$n]=['ns'=>$ns,'native'=>$n];}
 foreach($v77['current_identities']??[]as$r)if(($r['supplier_namespace']??'')==='andromeda_catalog')$prior[(string)$r['external_hotel_id']]=$r;
 foreach($edges as$e){$id=(int)($e['tv_hotel_id']??0);$op=(int)($e['operator_id']??0);$n=(string)($e['positive_native_candidates'][0]??'');$tv[$op][$n][$id]=true;$lanes[$id][$op][$n]=true;}
 $entries=[];$direct=[];$held=[];
 foreach($edges as$e){$id=(int)$e['tv_hotel_id'];$op=(int)$e['operator_id'];$n=(string)($e['positive_native_candidates'][0]??'');$why=w84_edge_reasons($e);$cat=null;
  if(count($tv[$op][$n])!==1||count($lanes[$id][$op])!==1)$why[]='retained_tv_native_conflict';
  if($op!==13&&$op!==18){$ns=$op===25?'operator_315':'operator_342';$cats=$native[$ns][$n]??[];if(count($cats)!==1)$why[]=count($cats)?'samo_native_ambiguous':'no_samo_native_fact';else$cat=(string)array_key_first($cats);}
  if($why){$held[]=['local_hotel_id'=>$id,'operator_id'=>$op,'native_id'=>$n,'catalog_id'=>$cat,'reasons'=>array_values(array_unique($why))];continue;}
  if($op===13){$direct[$id.'|'.$n]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'proof'=>$e];continue;}
  try{$proof=w84_raw($ops,$native[$ns][$n][$cat],$cat,$ns,$n);}catch(Throwable){$held[]=['local_hotel_id'=>$id,'operator_id'=>$op,'native_id'=>$n,'catalog_id'=>$cat,'reasons'=>['verified_raw_native_proof_missing']];continue;}
  $key=$id.'|'.$cat;
  if(!isset($entries[$key]))$entries[$key]=['id'=>$id,'catalog_id'=>$cat,'prepare_holds'=>[],'proofs'=>[],'operator_facts'=>array_values($catFacts[$cat]??[]),'prior_scan'=>$prior[$cat]??null];
  $entries[$key]['proofs'][]=['namespace'=>$ns,'native_id'=>$n,'tv'=>['kind'=>'independent_tv_audit','row'=>$e],'samo'=>$proof,'samo_catalog_id'=>$cat];
 }
 $byId=[];$byCat=[];foreach($entries as$e){$byId[$e['id']][$e['catalog_id']]=true;$byCat[$e['catalog_id']][$e['id']]=true;}
 foreach($entries as&$e)if(count($byId[$e['id']])!==1||count($byCat[$e['catalog_id']])!==1)$e['prepare_holds'][]='batch_primary_collision';unset($e);
 return['captured_edge_count'=>count($edges),'candidates'=>array_values($entries),'direct'=>array_values($direct),'held'=>$held];
}
function w84_prepare(string $ops):array {
 $edges=[];
 foreach(W84_INPUTS as$op=>[$sha,$expected]){
  $r=w84_read($ops.'/'.$op.'/result.json',$sha);w76_need(($r['operation']??'')===$op&&($r['state']??'')==='completed_read_only'&&($r['database_writes']??-1)===0&&($r['mapping_writes']??-1)===0,'acquisition_receipt');$n=0;
  foreach($r['edges']??[]as$i=>$e){if(!is_array($e)||($e['link_state']??'')!=='captured_single_native')continue;$e['_source_operation']=$op;$e['_source_result_sha256']=$sha;$e['_json_pointer']='/edges/'.$i;$edges[]=$e;$n++;}
  w76_need($n===$expected,'captured_count');
 }
 w76_need(count($edges)===93,'retained93_scope');$v=w84_read($ops.'/'.W84_NATIVE_OP.'/result.json',W84_NATIVE_SHA,67108864);
 w76_need(($v['state']??'')==='completed_retained_native_scan','native_scan_receipt');return w84_bind($edges,$v,$ops);
}
/** Fresh supplier-free SAMO live30 cohort; partial scans cannot authorize writes. */
function w84_live_sources(string $root):array {
 $config=$root.'/_preview/search3-anex-candidate/.andromeda-private.php';w76_need(is_file($config)&&!is_link($config),'samo_config');$cfg=require$config;
 w76_need(is_array($cfg)&&is_string($cfg['catalog_path']??null),'samo_catalog_path');$dir=dirname($cfg['catalog_path']).'/searches';w76_need(is_dir($dir)&&!is_link($dir),'samo_searches');
 $ids=[];$files=0;$bytes=0;$bad=0;$pages=0;$cut=time()-30*86400;$start=gmdate('c');
 foreach(new DirectoryIterator($dir)as$f){if($f->isDot()||!$f->isFile()||$f->isLink()||!str_ends_with($f->getFilename(),'.json'))continue;$size=$f->getSize();w76_need($size>0&&$size<=16777216,'samo_page_size');$files++;$bytes+=$size;w76_need($files<=25000&&$bytes<=1073741824,'samo_scan_budget');
  try{$j=json_decode((string)file_get_contents($f->getPathname()),true,64,JSON_THROW_ON_ERROR);}catch(Throwable){$bad++;continue;}
  $store=$j['store']??[];$snap=$store['snapshot']??[];if(($snap['provider']??'')!=='andromeda'||!is_int($store['created_at']??null)||$store['created_at']<$cut||!is_array($snap['offers']??null))continue;$pages++;
  foreach($snap['offers']as$o){if(!is_array($o)||($o['supplier_namespace']??'')!=='andromeda_catalog')continue;$id=(string)($o['external_hotel_id']??'');if(preg_match('/^[1-9][0-9]{0,31}$/D',$id))$ids[$id]=max($ids[$id]??0,$store['created_at']);}
 }
 w76_need($bad===0,'samo_scan_invalid_json');ksort($ids,SORT_NATURAL);return['source_ids'=>$ids,'files'=>$files,'bytes'=>$bytes,'live30_pages'=>$pages,'invalid_json'=>$bad,'cohort_started_at_utc'=>$start,'cohort_completed_at_utc'=>gmdate('c'),'cutoff_epoch'=>$cut,'complete'=>true];
}
function w84_samo_census(array $rows,array $cohort):array {
 $accepted=[];foreach($rows as$r)if($r['supplier_namespace']==='andromeda_catalog'&&$r['decision_status']==='accepted'&&$r['local_hotel_id']!==null)$accepted[(string)$r['external_hotel_id']]=true;
 $left=[];$mapped=0;foreach($cohort['source_ids']as$id=>$seen){if(isset($accepted[(string)$id]))$mapped++;else$left[]=(string)$id;}
 return['live30_sources'=>count($cohort['source_ids']),'accepted_primary_sources'=>$mapped,'unresolved_primary_sources'=>count($left),'unresolved_source_ids'=>$left,'cohort_sha256'=>w76_hash($cohort['source_ids']),'cohort_completed_at_utc'=>$cohort['cohort_completed_at_utc'],'complete'=>$cohort['complete']];
}
function w84_classify(array $e,array $c,array $cohort):array {
 $id=$e['id'];$cat=$e['catalog_id'];$rows=$c['sources'][$cat]??[];$row=count($rows)===1?$rows[0]:null;
 if(!$row||!w76_evidence_valid($row))return['status'=>'hold','reasons'=>['canonical_source_missing_or_invalid']];
 $history=json_decode($row['evidence_json'],true);$e['prior']=$e['prior_scan']??$row;$e['history']=$history;$e['target']=w78_target($c['hotels'][$id]??[]);$e['live_samo']=!empty($c['live'][$id])||isset($cohort['source_ids'][$cat]);
 if(($row['decision_status']??'')==='pending'&&(string)($history['source']['id']??'')!==$cat)$e['prepare_holds'][]='canonical_source_id_conflict';
 // The two live30 frontiers are a union, not a requirement to be live in both.
 if(!$e['live_samo'])$e['prepare_holds'][]='outside_both_live30_frontiers';
 return w78_classify($e,$c);
}
/** SERIALIZABLE, conditional pending/null updates and preview-only ANEX inserts. */
function w84_write(PDO $db,array $p,array $cohort,string $head,string $dir):array {
 require_once __DIR__.'/hotel_match_anex_effective_coverage.php';
 w76_need(!$db->inTransaction()&&($cohort['complete']??false)===true&&$p['captured_edge_count']<=93,'writer_scope');
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
  foreach($p['candidates']as$e){$d=w84_classify($e,$c,$cohort);$item=['local_hotel_id'=>$e['id'],'catalog_id'=>$e['catalog_id']]+$d;if($d['status']==='hold'){$held[]=$item;continue;}if($d['status']==='already'){$already[]=$item;continue;}
   $old=$c['sources'][$e['catalog_id']][0];$history=json_decode($old['evidence_json'],true);$ev=['operation_id'=>W84_OP,'rule'=>'one_exact_same_operator_raw_evidence','source_sha'=>$head,'inputs'=>W84_INPUTS,'native_result_sha256'=>W84_NATIVE_SHA,'source'=>$history['source'],'target'=>$c['hotels'][$e['id']],'proofs'=>$e['proofs'],'prior_evidence_json'=>$old['evidence_json'],'prior_evidence_sha256'=>$old['evidence_sha256'],'catalog_sha256_preserved'=>$old['catalog_sha256'],'required_exact_operators'=>1,'provider_http_calls'=>0];$raw=w76_json($ev);$key='andromeda_catalog|'.$e['catalog_id'];w76_need(!isset($planned[$key]),'duplicate_plan');$planned[$key]=['id'=>$e['id'],'cat'=>$e['catalog_id'],'name'=>$c['hotels'][$e['id']]['name'],'old'=>$old,'new_json'=>$raw,'new_sha'=>hash('sha256',$raw),'proof_count'=>count($e['proofs'])];
  }
  foreach($p['direct']as$e){$id=$e['local_hotel_id'];$n=$e['anex_hotel_id'];$d=a74_anex($id,$n,$c);if(!empty($c['exclusions'][$id])){$d['status']='hold';$d['reasons'][]='excluded_target_protected';}
   if($d['status']==='already_effective_same'){$already[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'status'=>'already'];continue;}if($d['status']!=='source_missing_needs_identity_proof'||$d['reasons']){$held[]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'reasons'=>$d['reasons']];continue;}
   $ev=['operation'=>W84_OP,'source_sha'=>$head,'authority'=>'verified_TV_operator13_HOTELLIST','proof'=>$e['proof'],'current_target'=>$c['hotels'][$id],'provider_http_calls'=>0];$anex[]=['anex_hotel_id'=>$n,'catalog_hotel_id'=>$id,'match_class'=>W75_CLASS,'scope'=>'preview','approval_policy'=>W75_POLICY,'source_row_digest'=>w75_digest($ev),'mapping_digest'=>'','enabled'=>1,'evidence'=>$ev];
  }
  w76_need(count($planned)+count($anex)<=101,'write_cap');$batch=w75_digest(['operation'=>W84_OP,'rows'=>$anex]);foreach($anex as&$a)$a['mapping_digest']=$batch;unset($a);
  w76_save($dir.'/write-plan.json',['operation'=>W84_OP,'source_sha'=>$head,'primary'=>$planned,'direct_anex'=>$anex,'held'=>$held,'already'=>$already,'identities_before_sha256'=>w76_hash($before),'mappings_before_sha256'=>w75_digest($mapBefore),'tv_before'=>$beforeCensus,'samo_before'=>$beforeSamo]);
  if(!$planned&&!$anex){$db->rollBack();return['state'=>'completed_no_new_writes','database_writes'=>0,'mapping_writes'=>0,'primary_samo_writes'=>0,'direct_anex_writes'=>0,'rows'=>[],'direct_rows'=>[],'held'=>$held,'already'=>$already,'readback_verified'=>true,'coverage_before'=>$beforeCensus,'coverage_after'=>$beforeCensus,'samo_before'=>$beforeSamo,'samo_after'=>$beforeSamo];}
  $st=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND catalog_sha256=? AND evidence_sha256=?");foreach($planned as$x){$sql=true;w76_need($st->execute([$x['id'],$x['new_sha'],$x['new_json'],$x['cat'],$x['old']['catalog_sha256'],$x['old']['evidence_sha256']])&&$st->rowCount()===1,'conditional_update');}
  $st=$db->prepare('INSERT INTO anex_hotel_search_mappings ('.W75_COLUMNS.') VALUES (?,?,?,?,?,?,?,?)');foreach($anex as$x){$sql=true;w76_need($st->execute(array_map(fn($k)=>$x[$k],explode(',',W75_COLUMNS)))&&$st->rowCount()===1,'conditional_insert');}
  $verify=function()use($db,$before,$planned,$mapBefore,$anex,$dec,$exc):array{
   $all=w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');$rows=w76_verify($before,$planned,$all);w75_verify($mapBefore,$anex,w76_q($db,'SELECT '.W75_COLUMNS.' FROM anex_hotel_search_mappings ORDER BY anex_hotel_id LIMIT 50001'),true);
   w76_need(w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001')===$dec,'manual_changed');w76_need(w76_q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001')===$exc,'exclusions_changed');
   $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);foreach($anex as$x)w76_need($registry->resolve('anex_online',$x['anex_hotel_id'],'preview')===$x['catalog_hotel_id'],'registry_mismatch');return[$rows,$all];
  };$verify();w76_save($dir.'/commit-attempt.json',['operation'=>W84_OP,'state'=>'commit_attempt_no_replay','planned_writes'=>count($planned)+count($anex)]);$attempt=true;w76_need($db->commit(),'commit');$committed=true;
  $db->exec('START TRANSACTION READ ONLY');[$rows,$after]=$verify();$afterCensus=w76_census($db);$afterSamo=w84_samo_census($after,$cohort);$db->rollBack();return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'database_writes'=>count($planned)+count($anex),'mapping_writes'=>count($planned)+count($anex),'primary_samo_writes'=>count($planned),'direct_anex_writes'=>count($anex),'rows'=>$rows,'direct_rows'=>array_map(fn($a)=>array_diff_key($a,['evidence'=>true]),$anex),'held'=>$held,'already'=>$already,'readback_verified'=>true,'prior_evidence_preserved'=>true,'unrelated_identities_and_mappings_unchanged'=>true,'coverage_before'=>$beforeCensus,'coverage_after'=>$afterCensus,'samo_before'=>$beforeSamo,'samo_after'=>$afterSamo];
 }catch(Throwable$x){if($db->inTransaction())try{$rolled=$db->rollBack();}catch(Throwable){}$count=$committed?count($planned)+count($anex):(($attempt||($sql&&!$rolled))?null:0);return['state'=>$committed?'committed_readback_unconfirmed':($attempt?'commit_outcome_unknown_no_replay':($rolled?'rolled_back_no_writes':'failed_before_writer')),'reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'writer_failed','error_class'=>get_class($x),'commit_attempted'=>$attempt,'commit_completed'=>$committed,'database_writes'=>$count,'mapping_writes'=>$count,'readback_verified'=>false,'held'=>$held,'already'=>$already,'coverage_before'=>$beforeCensus,'samo_before'=>$beforeSamo];}
}
function w84_main(array $argv):int {
 w76_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 w76_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===W84_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'scope');$rv=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);w76_need(($rv['operation']??'')===W84_OP&&($rv['source_sha']??'')===$head&&($rv['maximum_writes']??0)===101&&($rv['provider_http_calls']??-1)===0,'reservation');
 foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json']as$f)w76_need(!file_exists($dir.'/'.$f),'no_replay');w76_save($dir.'/execution-started.json',['operation'=>W84_OP,'source_sha'=>$head]);
 try{$p=w84_prepare(dirname($dir));$cohort=w84_live_sources($root);w76_save($dir.'/samo-cohort.json',$cohort);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=w84_write(v2_data_db(),$p,$cohort,$head,$dir);$out['retained_captured_edges']=$p['captured_edge_count'];}
 catch(Throwable$x){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'prepare_failed','error_class'=>get_class($x),'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false];}
 $out+=['operation'=>W84_OP,'source_sha'=>$head,'provider_http_calls'=>0,'new_operator_ids'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];$sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>W84_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$sha,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);echo w76_json(array_intersect_key($out,array_flip(['operation','state','reason','database_writes','mapping_writes','primary_samo_writes','direct_anex_writes','coverage_before','coverage_after','readback_verified'])))."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(w84_main($argv));
