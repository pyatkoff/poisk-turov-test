<?php
declare(strict_types=1);
// Append-only MATCH receiver. No supplier requests, no changes to existing identities.
const OP = 'hotel-match-v9-provider328-write-1971-20260921-v1';
const MIN_WRITE = 100;
const NS = [25 => 'operator_315', 43 => 'operator_342'];
const INPUT_HASHES = [
 'v9.json'=>'0995ccbd0c14639a335a644748d6dc5af6b359345328698ea3c0fc1300d88814',
 'reserved178.json'=>'8cb977214acf20bd93226103d752119d0615bb49692aa95191ede30bec4a0d1d',
 'chunk1.json'=>'488b3b9c28cb6f6fd429a6a1925e86217b90022d9372b142ba6e3981c77bc351',
 'chunk2.json'=>'3e5893d0e8580f3060cd1e8d947b1fa10e99d1e1f6a2398a6dc8617121ac1ba2',
 'chunk3.json'=>'26c42c8910a396deb1359cd29998a827d165fb7c16329e862edbfcd6bad8cf44',
 'chunk4.json'=>'6ddedb9545a2715f34f7994f201e35336665cf0ee3c3a0500bd77949c1de2d70',
 'chunk5.json'=>'68f8ac7bee97072a2fc3bb375e333f210242225dde3c87521cfc35e84d53cca0',
 'chunk6.json'=>'f7d9ede056a41c9c8e32f3237e505b4cc203101477c4d812ca8cfb194ac7c9a4',
 'chunk7.json'=>'7961ed6cf57a6740f70c4236481624b07f98cc056fc90c407966ec12ad7e747f',
];
function need(bool $ok,string $code):void { if (!$ok) throw new RuntimeException($code); }
function canon(mixed $v):string { if (is_array($v)) { if (!array_is_list($v)) ksort($v,SORT_STRING); foreach ($v as &$x) $x=json_decode(canon($x),true,512,JSON_THROW_ON_ERROR); unset($x); } return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function digest(array $v):string { return hash('sha256',canon($v)); }
function loadj(string $p):array { $v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR); need(is_array($v),'json'); return $v; }
function savej(string $p,array $v):string { $b=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; $f=fopen($p,'xb'); need(is_resource($f),'exclusive_output'); need(fwrite($f,$b)===strlen($b)&&fflush($f),'write'); if(function_exists('fsync')) need(fsync($f),'fsync'); fclose($f); need(hash_file('sha256',$p)===hash('sha256',$b),'disk_readback'); return hash('sha256',$b); }
function keyOf(array $r):string { return $r['supplier_namespace'].'|'.($r['native_hotel_id']??$r['external_hotel_id']); }
function pairOf(array $r):string { return $r['supplier_namespace'].'|'.($r['tv_hotel_id']??$r['local_hotel_id']); }
function seeds(string $dir):array {
 $docs=[]; foreach(INPUT_HASHES as $n=>$h) { need(is_file($dir.'/'.$n)&&!is_link($dir.'/'.$n)&&hash_file('sha256',$dir.'/'.$n)===$h,'input_hash'); $docs[$n]=loadj($dir.'/'.$n); }
 $v=$docs['v9.json']; need($v['state']==='completed_read_only_reconciliation'&&count($v['source_result']['edges'])===1456,'v9');
 $reserved=$docs['reserved178.json']; need($reserved['state']==='completed_read_only'&&$reserved['current_core_safe']===178&&count($reserved['rows'])===178,'reserved178');
 $rk=[];$rp=[];foreach($reserved['rows'] as $r) {$rk[keyOf($r)]=true;$rp[pairOf($r)]=true;} need(count($rk)===178,'reserved_keys');
 $sources=[];$nativeTargets=[];foreach($v['source_result']['edges'] as $e) {
  $o=(int)$e['operator_id'];if(!isset(NS[$o]))continue;
  need(hash('sha256',$e['operator_link'])===$e['operator_link_sha256'],'raw_link_hash');
  foreach($e['positive_native_candidates'] as $id) $nativeTargets[NS[$o].'|'.$id][(int)$e['tv_hotel_id']]=true;
  if($e['link_state']==='captured_single_native') {need(count($e['positive_native_candidates'])===1,'single');$k=NS[$o].'|'.$e['positive_native_candidates'][0].'|'.$e['tv_hotel_id'];need(!isset($sources[$k]),'duplicate_edge');$sources[$k]=$e;}
 }
 $out=[];$seen=[];$hotelIds=[];$eligible=0;$excluded=0;$pairs=[];
 for($i=1;$i<=7;$i++) {
  $d=$docs['chunk'.$i.'.json'];need($d['state']==='completed_read_only','terminal_input');
  foreach($d['selected_hotel_ids'] as $id){need(!isset($hotelIds[$id]),'prior_overlap');$hotelIds[$id]=true;}
  foreach($d['rows'] as $r){$key=keyOf($r);$sk=$key.'|'.$r['tv_hotel_id'];need(!isset($seen[$sk]),'edge_overlap');$seen[$sk]=true;
   $anchors=array_values(array_filter($r['target_occupants'],fn($a)=>$a['supplier_namespace']==='andromeda_catalog'&&$a['decision_status']==='accepted'));
   if($r['provider_classification']!=='provider_missing'||count($anchors)!==1)continue;$eligible++;
   if(isset($rk[$key])||isset($rp[pairOf($r)])){$excluded++;continue;}
   need(isset($sources[$sk])&&array_keys($nativeTargets[$key])===[(int)$r['tv_hotel_id']],'source_global_conflict');
   $e=$sources[$sk];foreach(['operator_id','tour_id','batch','operator_link_sha256'] as $f)need((string)$e[$f]===(string)$r[$f],'provenance');
   need(!isset($pairs[pairOf($r)]),'target_namespace_duplicate');$pairs[pairOf($r)]=true;
   $out[]=['supplier_namespace'=>$r['supplier_namespace'],'native_hotel_id'=>(string)$r['native_hotel_id'],'tv_hotel_id'=>(int)$r['tv_hotel_id'],'expected_target'=>$r['target'],'expected_anchor_id'=>(string)$anchors[0]['external_hotel_id'],'source'=>$e];
  }
 }
 need(count($hotelIds)===432&&count($seen)===532&&$eligible===506&&$excluded===178&&count($out)===328,'cohort');usort($out,fn($a,$b)=>strcmp(keyOf($a),keyOf($b)));return $out;
}
function query(PDO $db,string $sql,array $params=[]):array { $s=$db->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function factsEqual(array $a,array $b):bool { foreach(['id','name','country_name','region_name','subregion_name','category','is_active'] as $k)if((string)($a[$k]??'')!==(string)($b[$k]??''))return false;return true; }
function reason(array $s,array $byKey,array $byTarget,array $hot,array $manual):?string {
 $tv=$s['tv_hotel_id'];$ns=$s['supplier_namespace'];$t=$hot[$tv]??null;$aa=$byTarget['andromeda_catalog|'.$tv]??[];
 if(isset($byKey[keyOf($s)]))return 'provider_source_present';
 if(!$t||(int)$t['is_active']!==1)return 'target_missing_or_inactive';
 if(in_array(trim((string)$t['country_name']),['Россия','Абхазия','Russia','Russian Federation','Abkhazia'],true))return 'excluded_country';
 if(!factsEqual($t,$s['expected_target']))return 'target_facts_changed';
 if(isset($manual[$tv]))return 'manual_target_protected';
 if(isset($byTarget[$ns.'|'.$tv]))return 'provider_target_occupied';
 if(count($aa)!==1)return 'canonical_anchor_not_unique';
 $a=$aa[0];if((string)$a['external_hotel_id']!==$s['expected_anchor_id'])return 'canonical_anchor_changed';
 if(!preg_match('/^[0-9a-f]{64}$/D',$a['catalog_sha256'])||!preg_match('/^[0-9a-f]{64}$/D',$a['evidence_sha256']))return 'anchor_hash_invalid';
 if(hash('sha256',$a['evidence_json'])!==$a['evidence_sha256'])return 'anchor_evidence_hash_invalid';
 return null;
}
function indexed(array $rows):array { $keys=[];$targets=[];foreach($rows as $r){$k=keyOf($r);need(!isset($keys[$k]),'duplicate_identity');$keys[$k]=$r;if($r['decision_status']==='accepted'&&$r['local_hotel_id']!==null)$targets[pairOf($r)][]=$r;}return [$keys,$targets]; }
function writeMappings(PDO $db,array $seeds,string $dir,string $sourceSha):array {
 $base=['operation'=>OP,'source_sha'=>$sourceSha,'input_candidates'=>count($seeds),'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0];$commitAttempt=false;$committed=false;$inserted=[];
 try {
  $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=15');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
  $tables=array_map('current',query($db,'SHOW TABLES'));$guards=array_values(array_filter($tables,fn($t)=>preg_match('/(?:andromeda|hotel).*(?:decision|review|exclusion|hold)|(?:decision|review|exclusion|hold).*(?:andromeda|hotel)/i',$t)));sort($guards);need($guards===['anex_hotel_decisions'],'guard_table_drift');
  $all=query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json,created_at FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE');[$byKey,$byTarget]=indexed($all);
  $ids=array_values(array_unique(array_column($seeds,'tv_hotel_id')));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));$hot=[];$manual=[];
  foreach(query($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids) as $r)$hot[(int)$r['id']]=$r;
  $decisions=query($db,'SELECT anex_hotel_id,decision_status,catalog_hotel_id,decided_by,decision_note,decided_at,updated_at FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE');foreach($decisions as $r)if($r['catalog_hotel_id']!==null)$manual[(int)$r['catalog_hotel_id']]=true;
  $safe=[];$holds=[];$counts=[];$expected=[];
  foreach($seeds as $s){$why=reason($s,$byKey,$byTarget,$hot,$manual);if($why!==null){$holds[]=['key'=>keyOf($s),'tv_hotel_id'=>$s['tv_hotel_id'],'reason'=>$why];$counts[$why]=($counts[$why]??0)+1;continue;}
   $a=$byTarget['andromeda_catalog|'.$s['tv_hotel_id']][0];$safe[]=$s;$expected[keyOf($s)]=['anchor'=>$a,'target'=>$hot[$s['tv_hotel_id']]];
  }
  ksort($counts);$before=[];foreach($byKey as $k=>$r)$before[$k]=digest($r);
  savej($dir.'/capture.json',$base+['state'=>'current_locked_capture','safe_count'=>count($safe),'holds'=>$holds,'anchor_target_rows'=>$expected,'identity_rows_before'=>count($all),'identity_rows_hash'=>digest($before),'manual_rows_hash'=>digest($decisions),'guard_tables'=>$guards]);
  savej($dir.'/plan.json',$base+['state'=>count($safe)>=MIN_WRITE?'ready_to_insert':'below_threshold','minimum'=>MIN_WRITE,'safe_keys'=>array_map('keyOf',$safe),'safe_count'=>count($safe),'hold_counts'=>$counts]);
  if(count($safe)<MIN_WRITE){$db->rollBack();return $base+['state'=>'completed_no_write_below_threshold','mapping_writes'=>0,'database_writes'=>0,'current_safe'=>count($safe),'hold_counts'=>$counts,'holds'=>$holds,'readback_verified'=>false];}
  $sql="INSERT INTO andromeda_hotel_identities (supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,'accepted',?,?,?)";$st=$db->prepare($sql);
  foreach($safe as $s){$k=keyOf($s);$a=$expected[$k]['anchor'];$evidence=['operation_id'=>OP,'rule'=>'direct_saved_tourvisor_operator_link_and_independent_current_samo_target','source_sha'=>$sourceSha,'input_hashes'=>INPUT_HASHES,'tv_hotel_id'=>$s['tv_hotel_id'],'supplier_namespace'=>$s['supplier_namespace'],'external_hotel_id'=>$s['native_hotel_id'],'source'=>$s['source'],'canonical_samo_anchor'=>$a,'provider_bridges'=>[['andromeda_hotel_id'=>(string)$a['external_hotel_id'],'local_hotel_id'=>$s['tv_hotel_id'],'evidence_sha256'=>$a['evidence_sha256']]],'target'=>$expected[$k]['target'],'identity_separation'=>'native_operator_id_is_not_samo_id'];$ej=canon($evidence);$eh=hash('sha256',$ej);
   $st->execute([$s['supplier_namespace'],$s['native_hotel_id'],$s['tv_hotel_id'],$a['catalog_sha256'],$eh,$ej]);need($st->rowCount()===1,'insert_count');$inserted[$k]=['supplier_namespace'=>$s['supplier_namespace'],'external_hotel_id'=>$s['native_hotel_id'],'local_hotel_id'=>$s['tv_hotel_id'],'decision_status'=>'accepted','catalog_sha256'=>$a['catalog_sha256'],'evidence_sha256'=>$eh,'evidence_json'=>$ej];
  }
  $after=query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json,created_at FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');[$afterKeys]=indexed($after);need(count($afterKeys)===count($before)+count($safe),'delta_count');
  foreach($before as $k=>$h)need(isset($afterKeys[$k])&&digest($afterKeys[$k])===$h,'existing_identity_changed');foreach($inserted as $k=>$x)foreach($x as $field=>$value)need((string)$afterKeys[$k][$field]===(string)$value,'insert_readback');
  savej($dir.'/pre-commit.json',$base+['state'=>'verified_before_commit','planned_writes'=>count($safe),'old_rows_preserved'=>count($before),'inserted_keys'=>array_keys($inserted),'hold_counts'=>$counts]);
  savej($dir.'/commit-attempt.json',$base+['state'=>'commit_attempt_no_replay','planned_writes'=>count($safe)]);$commitAttempt=true;$db->commit();$committed=true;
  $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
  $post=query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json,created_at FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');[$postKeys,$postTargets]=indexed($post);
  $postHot=[];foreach(query($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$postHot[(int)$r['id']]=$r;
  $resolverRows=[];$written=[];foreach($inserted as $k=>$x){need(isset($postKeys[$k]),'post_missing');$r=$postKeys[$k];foreach($x as $field=>$value)need((string)$r[$field]===(string)$value,'post_row');$aa=$postTargets['andromeda_catalog|'.$x['local_hotel_id']]??[];need(count($aa)===1&&digest($aa[0])===digest($expected[$k]['anchor']),'post_anchor');need(isset($postHot[$x['local_hotel_id']])&&factsEqual($postHot[$x['local_hotel_id']],$expected[$k]['target']),'post_target');
   $resolverRows[]=['supplier_namespace'=>$x['supplier_namespace'],'external_hotel_id'=>$x['external_hotel_id'],'decision_status'=>'accepted','catalog_hotel_id'=>$x['local_hotel_id'],'existing_catalog_hotel_id'=>$postHot[$x['local_hotel_id']]['id']];$written[]=['supplier_namespace'=>$x['supplier_namespace'],'external_hotel_id'=>$x['external_hotel_id'],'local_hotel_id'=>$x['local_hotel_id'],'canonical_samo_id'=>(string)$aa[0]['external_hotel_id'],'evidence_sha256'=>$x['evidence_sha256']];
  }
  $resolver=AnyTourAndromedaHotelResolver::fromRows($resolverRows,digest($resolverRows));$offers=[];foreach($written as $r)$offers[]=['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>$r['external_hotel_id']];$page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>$offers]);foreach($page['offers'] as $i=>$offer)need($offer['local_hotel_id']===$written[$i]['local_hotel_id'],'effective_resolver');$db->rollBack();
  return $base+['state'=>'committed_verified','current_safe'=>count($safe),'mapping_writes'=>count($safe),'database_writes'=>count($safe),'post_commit_verified'=>count($written),'resolver_verified'=>count($written),'readback_verified'=>true,'old_rows_preserved'=>count($before),'hold_counts'=>$counts,'holds'=>$holds,'written_rows'=>$written,'written_unique_hotels'=>count(array_unique(array_column($written,'local_hotel_id')))];
 }catch(Throwable $e){$rolledBack=false;try{if($db->inTransaction())$rolledBack=$db->rollBack();}catch(Throwable){}$state=$committed?'post_commit_verification_failed_no_replay':($commitAttempt?'commit_unknown_no_replay':'rolled_back_no_write');savej($dir.'/failure.json',$base+['state'=>$state,'commit_attempted'=>$commitAttempt,'committed'=>$committed,'rollback_returned'=>$rolledBack,'attempted_inserts'=>count($inserted),'mapping_writes'=>$commitAttempt?null:0,'database_writes'=>$commitAttempt?null:0,'error_class'=>get_class($e)]);throw $e;}
}
function selfTest():void { need(canon(['b'=>1,'a'=>2])==='{"a":2,"b":1}','canonical_json');$s=['supplier_namespace'=>'operator_315','native_hotel_id'=>'1','tv_hotel_id'=>9,'expected_target'=>['id'=>9,'name'=>'A','is_active'=>1,'country_name'=>'Турция'],'expected_anchor_id'=>'7'];$h=[9=>$s['expected_target']];$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>9,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_json'=>'{}','evidence_sha256'=>hash('sha256','{}')];$t=['andromeda_catalog|9'=>[$a]];need(reason($s,[],$t,$h,[])===null,'safe');need(reason($s,['operator_315|1'=>[]],$t,$h,[])==='provider_source_present','occupied');need(reason($s,[],$t,$h,[9=>true])==='manual_target_protected','manual');need(reason($s,[],['andromeda_catalog|9'=>[$a,$a]],$h,[])==='canonical_anchor_not_unique','ambiguous');echo "PROVIDER328_SELFTEST_OK\n"; }
if(defined('MATCH_UNIT_TEST'))return;
need(PHP_SAPI==='cli','cli_only');$mode=$argv[1]??'';if($mode==='--self-test'){selfTest();exit;}need(in_array($mode,['--plan','--execute'],true),'disabled');$input=realpath((string)getenv('MATCH_INPUT_DIR'));need(is_string($input),'input_directory');$seeds=seeds($input);
if($mode==='--plan'){echo json_encode(['operation'=>OP,'candidates'=>count($seeds),'unique_hotels'=>count(array_unique(array_column($seeds,'tv_hotel_id'))),'keys'=>array_map('keyOf',$seeds),'supplier_calls'=>0,'database_writes'=>0],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit;}
$root=realpath((string)getenv('ANYTOUR_ROOT'));$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$sha=(string)getenv('MATCH_SOURCE_SHA');need(is_string($root)&&basename($root)==='anytoour.ru'&&is_string($dir)&&basename($dir)===OP&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'scope');$res=loadj($input.'/reservation.json');need($res['operation']===OP&&$res['source_sha']===$sha&&$res['script_sha256']===hash_file('sha256',__FILE__)&&$res['state']==='reserved_before_db','reservation');
savej($dir.'/started.json',['operation'=>OP,'state'=>'started_no_replay','source_sha'=>$sha]);require_once __DIR__.'/andromeda-hotel-resolver.php';require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
try{$result=writeMappings(v2_data_db(),$seeds,$dir,$sha);$h=savej($dir.'/result.json',$result);savej($dir.'/receipt.json',['operation'=>OP,'source_sha'=>$sha,'state'=>$result['state'],'result_sha256'=>$h,'mapping_writes'=>$result['mapping_writes'],'database_writes'=>$result['database_writes'],'readback_verified'=>$result['readback_verified'],'provider_calls'=>0,'no_replay'=>true]);echo json_encode(['state'=>$result['state'],'mapping_writes'=>$result['mapping_writes'],'hold_counts'=>$result['hold_counts'],'result_sha256'=>$h],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable){fwrite(STDERR,"MATCH_OPERATION_FAILED_NO_REPLAY\n");exit(1);}
