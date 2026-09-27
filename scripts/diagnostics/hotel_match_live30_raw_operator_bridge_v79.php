<?php
declare(strict_types=1);
/** Retained operator identities only. No provider client, primary mapping or direct ANEX writes. */
const O79_OP='hotel-match-live30-raw-operator-bridge-1971-20260927-v79';
const O79_V77='d40fbe2e0240a5df194ac838376a3425a8f4e80f2426a757560fe369011107f7';
const O79_V78='12ec6b462a7628e96791ce65b4e9eca0d32c17a1ed4997a62ea11786675fe414';
const O79_SCOPE='8d178f71dddbd3b4b01bda3f27d3ad01542e54becc07f0ae8d79536130b7bb76';
const O79_NS=['operator_5','operator_115','operator_315','operator_342'];
function o79_need(bool $b,string $r):void{if(!$b)throw new RuntimeException($r);}
function o79_json(mixed $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function o79_save(string $p,array $x):string{$s=o79_json($x)."\n";$f=@fopen($p,'x+b');o79_need($f!==false,'exclusive_record');try{o79_need(fwrite($f,$s)===strlen($s)&&fflush($f),'durable_record');if(function_exists('fsync'))o79_need(fsync($f),'sync_record');}finally{fclose($f);}return hash('sha256',$s);}
function o79_read(string $p,string $sha,int $limit=67108864):array{o79_need(is_file($p)&&!is_link($p)&&filesize($p)<=$limit,'proof_file');$s=file_get_contents($p);o79_need(is_string($s)&&hash_equals($sha,hash('sha256',$s)),'proof_digest');$x=json_decode($s,true,128,JSON_THROW_ON_ERROR);o79_need(is_array($x),'proof_json');return $x;}
function o79_index(array $rows):array{$out=[];foreach($rows as$r){$k=$r['supplier_namespace'].'|'.$r['external_hotel_id'];o79_need(!isset($out[$k]),'duplicate_identity');$out[$k]=$r;}return$out;}
function o79_valid(array $r):bool{return is_string($r['evidence_json']??null)&&is_string($r['evidence_sha256']??null)&&hash_equals($r['evidence_sha256'],hash('sha256',$r['evidence_json']))&&is_array(json_decode($r['evidence_json'],true));}
function o79_pin(array $r):array{$x=[];foreach(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256']as$k)$x[$k]=($r[$k]??null)===null?null:(string)$r[$k];return$x;}
function o79_auto(array $r):bool{
 if($r['decision_status']!=='pending'||$r['local_hotel_id']!==null||!o79_valid($r))return false;
 $e=json_decode($r['evidence_json'],true);$keys=['country_id','source','operation_id','schema','source_operation_id','source_result_sha256','catalog_reference_kind','decision','provider_bridges'];
 if(array_diff(array_keys($e),$keys)||!is_array($e['decision']??null)||array_diff(array_keys($e['decision']),['state','local_hotel_id','country_id','anchors','reason'])||($e['provider_bridges']??[])!==[])return false;
 return($e['schema']??'')==='operator-original-price-bridge/1'&&($e['operation_id']??'')==='hotel-match-operator-original-store-1971-20260915-v2'&&($e['source_operation_id']??'')==='hotel-match-operator-original-batch-1971-20260915-v2'&&($e['source_result_sha256']??'')==='f379062abd43c36e6feb792f8e0313466ddd579a1959cfe814e90b863a43968e'&&($e['catalog_reference_kind']??'')==='price_capture'&&is_array($e['decision']??null)&&array_key_exists('local_hotel_id',$e['decision'])&&$e['decision']['local_hotel_id']===null&&($e['decision']['state']??'')==='pending'&&($e['decision']['anchors']??[])===[]&&($e['decision']['reason']??'')==='no_anchor'&&(string)($e['source']['id']??'')===(string)$r['external_hotel_id']&&'operator_'.(string)($e['source']['operator_key']??'')===$r['supplier_namespace'];
}
function o79_fact(array $r,string $cat,string $ns,string $native):bool{
 $o=$r['original']??null;$op=substr($ns,9);
 return in_array($ns,O79_NS,true)&&is_array($o)&&(string)($r['hotelKey']??'')===$cat&&(string)($r['operatorKey']??($o['operatorKey']??''))===$op&&(!isset($o['operatorKey'])||(string)$o['operatorKey']===$op)&&(string)($o['hotelKey']??'')===$native&&!in_array($r['isOperatorHotelKey']??false,[true,1,'1','true'],true);
}
function o79_pointer(array $x,string $p):array{o79_need(str_starts_with($p,'/'),'pointer');foreach(explode('/',substr($p,1))as$k){$k=strtr($k,['~1'=>'/','~0'=>'~']);o79_need(is_array($x)&&array_key_exists($k,$x),'pointer_missing');$x=$x[$k];}o79_need(is_array($x),'pointer_row');return$x;}
function o79_prepare(string $ops):array{
 $v=o79_read($ops.'/hotel-match-live30-retained-native-union-1971-20260927-v77/result.json',O79_V77);
 $last=o79_read($ops.'/hotel-match-raw-native-pending11-1971-20260927-v78c/result.json',O79_V78);
 o79_need($v['state']==='completed_retained_native_scan'&&$last['state']==='committed_readback_verified'&&$last['database_writes']===11&&$last['readback_verified']===true,'predecessor_state');
 $idx=o79_index($v['current_identities']);foreach($last['rows']as$r){$k='andromeda_catalog|'.$r['catalog_id'];o79_need(isset($idx[$k]),'predecessor_anchor');$idx[$k]['local_hotel_id']=$r['local_hotel_id'];$idx[$k]['decision_status']='accepted';$idx[$k]['evidence_sha256']=$r['evidence_sha256'];}
 $live=[];foreach($v['source_frontier']as$s)$live[(string)$s['catalog_id']]=$s;
 $native=[];foreach($v['native_facts']as$f)$native[$f['supplier_namespace'].'|'.$f['native_id']][(string)$f['catalog_id']]=$f;
 $entries=[];$scope=[];
 foreach($native as$key=>$cats){if(count($cats)!==1)continue;$cat=(string)array_key_first($cats);$f=$cats[$cat];$a=$idx['andromeda_catalog|'.$cat]??null;$old=$idx[$key]??null;
  if(!isset($live[$cat])||!$a||$a['decision_status']!=='accepted'||$a['local_hotel_id']===null||($old&&$old['decision_status']==='accepted'))continue;
  $ns=$f['supplier_namespace'];$n=(string)$f['native_id'];$id=(int)$a['local_hotel_id'];o79_need(in_array($ns,O79_NS,true)&&$id>0&&preg_match('/^[1-9][0-9]{0,31}$/D',$n)===1,'candidate_scope');
  $e=['key'=>$key,'ns'=>$ns,'native'=>$n,'catalog_id'=>$cat,'id'=>$id,'anchor'=>o79_pin($a),'prior'=>$old?o79_pin($old):null,'holds'=>[],'proof'=>null];
  try{$proof=null;foreach($f['evidence']as$p)if(preg_match('~^operations/(hotel-match-[a-zA-Z0-9_-]+)/((?:evidence-private/[a-zA-Z0-9_.-]+|raw-[a-zA-Z0-9_.-]+)\.json)$~D',$p['source_file'],$m)){$path=$ops.'/'.$m[1].'/'.$m[2];o79_need(str_starts_with((string)realpath($path),realpath($ops).'/'),'raw_path');$raw=o79_read($path,$p['sha256'],16777216);$row=o79_pointer($raw,$p['json_pointer']);o79_need(o79_fact($row,$cat,$ns,$n),'raw_identity');$proof=$p;break;}o79_need($proof!==null,'no_raw_proof');
   $recent=array_filter($live[$cat]['contexts'],fn($c)=>($c['created_at']??0)>=time()-30*86400);o79_need($recent!==[],'expired_live_scope');$e['proof']=$proof;$context=array_values($recent)[0];$e['live_proof']=['file'=>$context['file'],'sha256'=>$context['file_sha256'],'created_at'=>$context['created_at'],'json_pointer'=>$context['json_pointer']];
  }catch(Throwable$x){$e['holds'][]=preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'raw_validation_failed';}
  $entries[$key]=$e;$scope[]=$key.'|'.$cat.'|'.$id;
 }
 sort($scope,SORT_STRING);o79_need(count($entries)===310&&hash('sha256',implode("\n",$scope)."\n")===O79_SCOPE,'scope_digest');ksort($entries,SORT_STRING);return$entries;
}
function o79_classify(array $e,array $idx,array $hot,array $manual,array $excluded):array{
 $why=$e['holds'];$key=$e['key'];$old=$idx[$key]??null;$a=$idx['andromeda_catalog|'.$e['catalog_id']]??null;$h=$hot[$e['id']]??null;
 if($old&&$old['decision_status']==='accepted'&&$old['local_hotel_id']!==null&&(int)$old['local_hotel_id']===$e['id']&&o79_valid($old))return['status'=>'already','reasons'=>[]];
 if(!$a||o79_pin($a)!==$e['anchor']||!o79_valid($a))$why[]='canonical_anchor_drift';
 if(!$h||(int)$h['is_active']!==1||preg_match('/^(?:Россия|Абхазия|Russia|Abkhazia|Russian Federation)$/iu',(string)($h['country_name']??'')))$why[]='inactive_or_excluded_country';
 if(isset($manual[$e['id']])||isset($excluded[$e['id']]))$why[]='manual_target_protected';
 if($old){if($e['prior']===null||o79_pin($old)!==$e['prior'])$why[]='operator_source_drift';if(!o79_auto($old))$why[]='operator_not_automatic_pending';}
 elseif($e['prior']!==null)$why[]='operator_source_missing';
 if(!$e['proof'])$why[]='no_raw_proof';
 return['status'=>$why?'hold':($old?'update':'insert'),'reasons'=>array_values(array_unique($why))];
}
function o79_q(PDO $db,string $sql,array $args=[]):array{$s=$db->prepare($sql);o79_need($s&&$s->execute(array_values($args)),'query');$r=$s->fetchAll(PDO::FETCH_ASSOC);o79_need(count($r)<=50000,'row_limit');return$r;}
function o79_verify(array $before,array $plan,array $rows):array{
 $after=o79_index($rows);$inserted=count(array_filter($plan,fn($p)=>$p['old']===null));o79_need(count($after)===count($before)+$inserted,'identity_count_changed');
 foreach($before as$k=>$old){o79_need(isset($after[$k]),'identity_lost');if(!isset($plan[$k]))o79_need($after[$k]===$old,'unrelated_identity_changed');else foreach($old as$f=>$v)if(!in_array($f,['local_hotel_id','decision_status','evidence_sha256','evidence_json','updated_at'],true))o79_need($after[$k][$f]===$v,'old_field_changed');}
 $out=[];foreach($plan as$k=>$p){$r=$after[$k]??[];o79_need(($r['supplier_namespace']??'')===$p['ns']&&(string)($r['external_hotel_id']??'')===$p['native']&&(int)($r['local_hotel_id']??0)===$p['id']&&($r['decision_status']??'')==='accepted'&&($r['catalog_sha256']??'')===$p['catalog_sha256']&&($r['evidence_json']??'')===$p['json']&&($r['evidence_sha256']??'')===$p['sha']&&o79_valid($r),'changed_row_mismatch');
  $ev=json_decode($r['evidence_json'],true);o79_need(($ev['prior_evidence_json']??null)===($p['old']['evidence_json']??null)&&($ev['prior_evidence_sha256']??null)===($p['old']['evidence_sha256']??null),'prior_evidence_lost');
  $out[]=['supplier_namespace'=>$p['ns'],'external_hotel_id'=>$p['native'],'local_hotel_id'=>$p['id'],'catalog_id'=>$p['cat'],'name'=>$p['name'],'change'=>$p['old']===null?'insert':'pending_transition','evidence_sha256'=>$p['sha']];
 }return$out;
}
function o79_execute(PDO $db,array $entries,string $head,string $dir):array{
 o79_need(count($entries)>0&&count($entries)<=310&&!$db->inTransaction(),'writer_scope');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$plan=[];$held=[];$already=[];$attempt=false;$committed=false;
 try{foreach(['andromeda_hotel_identities','catalog_hotels','anex_hotel_decisions','anex_review_pair_exclusions']as$t){$r=o79_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);o79_need(count($r)===1&&strtoupper($r[0]['ENGINE'])==='INNODB','nontransactional');}
  $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$before=o79_index(o79_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001 FOR UPDATE'));
  $ids=array_values(array_unique(array_column($entries,'id')));$ph=implode(',',array_fill(0,count($ids),'?'));$hot=[];$manual=[];$excluded=[];foreach(o79_q($db,"SELECT id,name,country_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id FOR UPDATE",$ids)as$r)$hot[(int)$r['id']]=$r;
  foreach(o79_q($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) FOR UPDATE",$ids)as$r)$manual[(int)$r['catalog_hotel_id']]=true;
  foreach(o79_q($db,"SELECT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph) FOR UPDATE",$ids)as$r)$excluded[(int)$r['catalog_hotel_id']]=true;
  foreach($entries as$k=>$e){o79_need($k===$e['key']&&$k===$e['ns'].'|'.$e['native']&&in_array($e['ns'],O79_NS,true),'operator_namespace');$d=o79_classify($e,$before,$hot,$manual,$excluded);$item=['supplier_namespace'=>$e['ns'],'external_hotel_id'=>$e['native'],'local_hotel_id'=>$e['id'],'catalog_id'=>$e['catalog_id']]+$d;
   if($d['status']==='hold'){$held[]=$item;continue;}if($d['status']==='already'){$already[]=$item;continue;}$old=$before[$k]??null;$a=$before['andromeda_catalog|'.$e['catalog_id']];$catalogSha=$old['catalog_sha256']??$a['catalog_sha256'];
   $ev=['schema'=>'retained-operator-native-canonical-bridge/1','operation_id'=>O79_OP,'source_sha'=>$head,'source_result_sha256'=>O79_V77,'prior_primary_write_result_sha256'=>O79_V78,'source'=>['id'=>$e['native'],'operator_key'=>substr($e['ns'],9),'catalog_id'=>$e['catalog_id']],'canonical_anchor'=>o79_pin($a),'target'=>$hot[$e['id']],'raw_proof'=>$e['proof'],'live30_proof'=>$e['live_proof']??[],'prior_evidence_json'=>$old['evidence_json']??null,'prior_evidence_sha256'=>$old['evidence_sha256']??null,'provider_http_calls'=>0,'direct_anex_mapping'=>false];
   $json=o79_json($ev);$plan[$k]=['ns'=>$e['ns'],'native'=>$e['native'],'cat'=>$e['catalog_id'],'id'=>$e['id'],'name'=>$hot[$e['id']]['name'],'old'=>$old,'catalog_sha256'=>$catalogSha,'json'=>$json,'sha'=>hash('sha256',$json)];
  }
  o79_save($dir.'/write-plan.json',['operation'=>O79_OP,'planned'=>$plan,'held'=>$held,'already'=>$already]);
  if(!$plan){$db->rollBack();return['state'=>'completed_no_new_writes','database_writes'=>0,'mapping_writes'=>0,'rows'=>[],'held'=>$held,'already'=>$already,'readback_verified'=>true,'inserted'=>0,'updated'=>0];}
  $ins=$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES(?,?,?,'accepted',?,?,?)");
  $upd=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace=? AND external_hotel_id=? AND local_hotel_id IS NULL AND decision_status='pending' AND catalog_sha256=? AND evidence_sha256=?");
  foreach($plan as$p){if($p['old']===null)o79_need($ins->execute([$p['ns'],$p['native'],$p['id'],$p['catalog_sha256'],$p['sha'],$p['json']])&&$ins->rowCount()===1,'conditional_insert');else o79_need($upd->execute([$p['id'],$p['sha'],$p['json'],$p['ns'],$p['native'],$p['old']['catalog_sha256'],$p['old']['evidence_sha256']])&&$upd->rowCount()===1,'conditional_update');}
  o79_verify($before,$plan,o79_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));
  o79_save($dir.'/commit-attempt.json',['operation'=>O79_OP,'planned_count'=>count($plan),'no_replay'=>true]);$attempt=true;o79_need($db->commit(),'commit');$committed=true;
  $db->exec('START TRANSACTION READ ONLY');$read=o79_verify($before,$plan,o79_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001'));$db->rollBack();$inserted=count(array_filter($read,fn($r)=>$r['change']==='insert'));
  return['state'=>'committed_readback_verified','commit_attempted'=>true,'commit_completed'=>true,'database_writes'=>count($read),'mapping_writes'=>count($read),'inserted'=>$inserted,'updated'=>count($read)-$inserted,'local_hotels'=>count(array_unique(array_column($read,'local_hotel_id'))),'rows'=>$read,'held'=>$held,'already'=>$already,'readback_verified'=>true,'prior_evidence_preserved'=>true,'unrelated_identities_unchanged'=>true,'primary_identities_unchanged'=>true];
 }catch(Throwable$x){if($db->inTransaction())try{$db->rollBack();}catch(Throwable){}return['state'=>$attempt?($committed?'committed_readback_unconfirmed':'commit_outcome_unknown_no_replay'):'rolled_back_no_writes','reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'writer_failed','error_class'=>get_class($x),'commit_attempted'=>$attempt,'commit_completed'=>$committed,'database_writes'=>$committed?count($plan):($attempt?null:0),'mapping_writes'=>$committed?count($plan):($attempt?null:0),'readback_verified'=>false,'rows'=>[],'held'=>$held,'already'=>$already];}
}
function o79_main(array $argv):int{
 o79_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 o79_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===O79_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'scope');$rv=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);o79_need($rv['operation']===O79_OP&&$rv['source_sha']===$head&&$rv['maximum_writes']===310,'reservation');foreach(['execution-started.json','write-plan.json','commit-attempt.json','result.json','receipt.json']as$f)o79_need(!file_exists($dir.'/'.$f),'no_replay');o79_save($dir.'/execution-started.json',['operation'=>O79_OP,'source_sha'=>$head]);
 try{$entries=o79_prepare(dirname($dir));require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=o79_execute(v2_data_db(),$entries,$head,$dir);}catch(Throwable$x){$out=['state'=>'failed_before_writer','reason'=>preg_match('/^[a-z_]+$/D',$x->getMessage())?$x->getMessage():'prepare_failure','database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>false,'rows'=>[]];}
 $out+=['operation'=>O79_OP,'source_sha'=>$head,'scope_sha256'=>O79_SCOPE,'provider_http_calls'=>0,'direct_anex_mapping_writes'=>0,'new_primary_links'=>0,'new_full_triples'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];$sha=o79_save($dir.'/result.json',$out);o79_save($dir.'/receipt.json',['operation'=>O79_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$sha,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'database_readback_verified'=>$out['readback_verified'],'provider_http_calls'=>0,'no_replay'=>true]);echo o79_json(array_diff_key($out,['rows'=>true,'held'=>true,'already'=>true]))."\n";return in_array($out['state'],['committed_readback_verified','completed_no_new_writes'],true)?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(o79_main($argv));
