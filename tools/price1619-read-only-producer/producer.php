<?php
declare(strict_types=1);
function git4294(array $args):string{
 $p=proc_open(array_merge(['git'], $args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($p))throw new RuntimeException('git_read_failed');fclose($pipes[0]);$out=stream_get_contents($pipes[1],2000001);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($p)!==0||strlen($out)>2000000)throw new RuntimeException('git_read_failed');return trim($out);
}
function producer_process_diagnostic():array {
 $out=['ps_probe'=>'failed'];$p=proc_open(['ps','-eo','pid=,etimes=,args='],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($p))return $out;fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$raw='';$err='';$deadline=microtime(true)+5;$exit=null;$bad=false;
 try{while(true){$raw.=stream_get_contents($pipes[1],2097153);$err.=stream_get_contents($pipes[2],4097);if(strlen($raw)>2097152||strlen($err)>4096||microtime(true)>$deadline){$bad=true;proc_terminate($p,9);break;}$status=proc_get_status($p);if(!$status['running']){$exit=$status['exitcode'];$raw.=stream_get_contents($pipes[1],2097153);$err.=stream_get_contents($pipes[2],4097);break;}usleep(10000);}}
 finally{fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($p);}
 if($bad||($exit??$closed)!==0||strlen($raw)>2097152||$err!=='')return $out;
 $out=['ps_probe'=>'observed','collector_count'=>0,'monitor_count'=>0,'timer_count'=>0,'timer_elapsed_max'=>0,'recover_count'=>0,'collector_elapsed_max'=>0,'monitor_elapsed_max'=>0,'recover_elapsed_max'=>0];
 foreach(explode("\n",trim($raw)) as $line){if(!preg_match('/\A\s*([0-9]+)\s+([0-9]+)\s+(.+)\z/D',$line,$m))return ['ps_probe'=>'failed'];if((int)$m[1]===getmypid())continue;
  // Only actual PHP command lines, never shell/SSH text containing a script name.
  if(!preg_match('~\A(?:\S*/)?php[0-9.]*\s~',$m[3]))continue;
  foreach(['timer'=>'hotel-price-timer-v1\.php','collector'=>'(?:collect-anytour|top500-daily-collector)[A-Za-z0-9_.-]*\.php','monitor'=>'(?:published-hotels-monitor|monitor-price-hook|monitor-price-after-collection)[A-Za-z0-9_.-]*\.php','recover'=>'recover\.php'] as $key=>$pattern)if(preg_match('~(?:^|[ /])'.$pattern.'(?:\s|$)~',$m[3])){$out[$key.'_count']++;$out[$key.'_elapsed_max']=max($out[$key.'_elapsed_max'],min((int)$m[2],2147483647));}
 }
 return $out;
}
function producer_timer_projection(array $v):array {
 $out=[];
 foreach(['started_at','finished_at'] as $k)if(is_string($v[$k]??null)&&preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2})\z/D',$v[$k]))$out[$k]=$v[$k];
 if(in_array($v['state']??null,['busy','recent','collected','partial','failed'],true))$out['state']=$v['state'];
 if(is_int($v['exit']??null)&&$v['exit']>=-1&&$v['exit']<=255)$out['exit']=$v['exit'];
 foreach(['processed','logical_processed','observations_written','failed','partial','fresh_targets','pending_targets'] as $k)if(is_int($v['result'][$k]??null)&&$v['result'][$k]>=0&&$v['result'][$k]<=2147483647)$out['result'][$k]=$v['result'][$k];
 return $out;
}
function producer_timer_diagnostic():array {
 $out=[];$dir='/var/www/anytoour/data/hotel-price-monitor';
 foreach(['last-check','last-collection','last-error'] as $name){$path=$dir.'/'.$name.'.json';$f=null;
  try{clearstatcache(true,$path);if(!file_exists($path)){$out[$name]=['read_status'=>'missing'];continue;}if(is_link($path)||!is_file($path)||realpath($path)!==$path)throw new RuntimeException('closed');
   $f=fopen($path,'rb');if(!is_resource($f)||!flock($f,LOCK_SH|LOCK_NB))throw new RuntimeException('closed');$before=fstat($f);$raw=stream_get_contents($f,65537);$after=fstat($f);clearstatcache(true,$path);$named=lstat($path);
   if(strlen($raw)>65536||!feof($f))throw new RuntimeException('closed');foreach(['dev','ino','size','mtime','ctime'] as $k)if($before[$k]!==$after[$k]||$before[$k]!==$named[$k])throw new RuntimeException('closed');$v=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($v))throw new RuntimeException('closed');$out[$name]=['read_status'=>'observed']+producer_timer_projection($v);
  }catch(Throwable $e){$out[$name]=['read_status'=>'unavailable'];}finally{if(is_resource($f)){flock($f,LOCK_UN);fclose($f);}}
 }
 return $out;
}
function producer_safe_error(Throwable $error):string {
 if($error instanceof PDOException)return 'producer_failed_closed';
 $allowed=['git_read_failed', 'producer_runtime_warning', 'producer_args', 'producer_identity', 'producer_config_hash', 'producer_closed', 'runtime_dirty', 'runtime_changed', 'path_changed', 'busy', 'file_replaced', 'incomplete', 'file_changed', 'preimage_changed', 'pending_changed', 'missing', 'native_evidence_changed', 'process_read_failed', 'process_read_incomplete', 'process_line_unknown', 'native_collector_busy', 'isolated_sql_unbound', 'isolated_sql_not_proven_idle', 'isolated_sql_snapshot_too_late', 'isolated_sql_env_shape', 'isolated_sql_unavailable', 'consumer_db_source_changed', 'consumer_db_function_missing', 'consumer_db_unavailable', 'consumer_config_missing', 'canonical_collector_sql_lock_busy', 'consumer_path_changed', 'consumer_changed', 'staged_bundle_changed', 'journal_identity_changed'];
 return in_array($error->getMessage(),$allowed,true)?$error->getMessage():'producer_failed_closed';
}
function producer_warning_metadata(string $phase,int $severity,string $file,int $line,string $message=''):array {
 $phases=['bootstrap','runtime','app_config','global_read','child_read','registry','local_feeds','evidence','processes','consumer_db','consumer_config','consumer_sql','consumer_code','bundle','final_identity','output'];
 $files=['producer.php','config.php','db-v1.php','ChannelPostRuntime.php','ChannelPostRegistryService.php','ConversationDb.php'];
 $name=basename($file);
 $kind=($severity===E_DEPRECATED||$severity===E_USER_DEPRECATED)?'deprecated':
  (preg_match('/\AConstant .+ already defined\z/sD',$message)===1?'constant_already_defined':
   (str_starts_with($message,'Undefined array key ')?'undefined_array_key':'other'));
 return ['warning_phase'=>in_array($phase,$phases,true)?$phase:'unknown',
  'warning_severity'=>$severity,'warning_file'=>in_array($name,$files,true)?$name:'other',
  'warning_line'=>$line,'warning_kind'=>$kind];
}
$initialBuffer=ob_get_level();$handles=[];
$warning=null;$phase='bootstrap';
set_error_handler(static function(int $severity,string $message,string $file,int $line)use(&$warning,&$phase){
 $warning=producer_warning_metadata($phase,$severity,$file,$line,$message);throw new RuntimeException('producer_runtime_warning');
});
try {
function must4294($v,string $m):void{if(!$v)throw new RuntimeException($m);}
must4294(count($argv)===5,'producer_args');
[$self,$configFile,$runId,$head,$configSha]=$argv;
must4294(ctype_digit($runId)&&preg_match('/\A[a-f0-9]{40}\z/D',$head)===1,'producer_identity');
must4294(!is_link($configFile)&&is_file($configFile)&&hash_file('sha256',$configFile)===$configSha,'producer_config_hash');
$c=json_decode(file_get_contents($configFile),true,512,JSON_THROW_ON_ERROR);
must4294(($c['producer_enabled']??null)===true,'producer_closed');

// Immutable pure caller; capture original collector environment before any app bootstrap.
$caller=__DIR__.'/subprocess.php';
must4294(!is_link($caller)&&is_file($caller)&&hash_file('sha256',$caller)==='158deacfe49eec015a159caf899c5ad0354121b97855a15525fb419c9bdc35aa','isolated_sql_unbound');
require $caller;
$originalConsumerEnv=isolated_consumer_env_snapshot();
// Positive native idle proof runs read-only before registry/admission/recovery operations.
$processDiagnostic=producer_process_diagnostic();$processDiagnostic['timer_receipts']=producer_timer_diagnostic();
$phase='consumer_sql';
$sqlProof=isolated_consumer_sql_probe(__DIR__.'/probe-worker.php','9346c494e38cda72f231b5410a7795672f7642c2262c9da8bb33d04f8524156f',$originalConsumerEnv);
unset($originalConsumerEnv);
must4294(($sqlProof['consumer_db_sha256']??null)===($c['consumer_db_sha256']??null),'consumer_db_source_changed');
$sqlActive=$sqlProof['sql_active_collectors'];
$dbFile='/var/www/anytoour/data/www/anytoour.ru/data/db-v1.php';

$phase='runtime';
$root='/var/www/anytoour/data/www/app.anytoour.ru'; chdir($root);
if(git4294(['status','--porcelain','--untracked-files=no'])!=='')throw new Exception('runtime_dirty');
if(git4294(['rev-parse','HEAD'])!=='bd8e248f7138ea75cb9fda2dcf44beb631c633a5')throw new Exception('runtime_changed');
$phase='app_config';
ob_start();require $root.'/config.php';require_once $root.'/services/ChannelPostRuntime.php';ob_end_clean();
$dir='/var/www/anytoour/data/channel-post-operations';
$op='commercial-monitor-price-h4294-f72863a3310b60862a907f040008fd46c82e7e044485488e9eece8adb1237e24';
$identities=[];$handles=[];$out=['operation'=>$op,'read_only'=>true];
foreach(['global'=>[$dir.'/monitor-price-after-collection-v1.jsonl','2f45a4f2d5c964d7cda1d7264931b14acf96239dc5586b5fad7bc19b73f78de1'],'child'=>[$dir.'/'.$op.'/receipt.jsonl','86946ce11d552eb55f9362d2342fa96097cc8f8e32df92a90706cc36ffad986b']] as $key=>[$path,$sha]){
 $phase=$key==='global'?'global_read':'child_read';
 if(is_link($path)||realpath($path)!==$path)throw new Exception('path_changed');
 $f=fopen($path,'rb');if(!$f||!flock($f,LOCK_SH|LOCK_NB))throw new Exception('busy');$handles[]=$f;
 $before=fstat($f);$identities[]=[$f,$path,$before];$named=lstat($path);if($before['ino']!==$named['ino']||$before['dev']!==$named['dev'])throw new Exception('file_replaced');
 $h=hash_init('sha256');$last='';$n=0;while(($line=fgets($f,4194305))!==false){if(!str_ends_with($line,"\n"))throw new Exception('incomplete');hash_update($h,$line);$last=$line;$n++;}
 clearstatcache(true,$path);$after=fstat($f);$named=lstat($path);foreach(['ino','dev','size','mtime','ctime'] as $field)if($before[$field]!==$after[$field]||$before[$field]!==$named[$field])throw new Exception('file_changed');
 if(hash_final($h)!==$sha)throw new Exception('preimage_changed');
 $out[$key]=['sha256'=>$sha,'bytes'=>ftell($f),'lines'=>$n,'last_sha256'=>hash('sha256',$last),'last'=>json_decode($last,true,64,JSON_THROW_ON_ERROR)];
}
if(($out['global']['last']['pending']['operation']??null)!==$op)throw new Exception('pending_changed');
$phase='registry';
$pdo=ConversationDb::connection();$r=new ChannelPostRegistryService($pdo);$out['publications']=[];
foreach($out['child']['last']['posts'] as $entry){
 $id=$entry['plan']['publication_id'];$p=$r->publication($id);if(!$p)throw new Exception('missing');
 $event=$p['snapshot']['metadata']['source_event_id']??'';
 $q=$pdo->prepare('SELECT version_id FROM post_observations WHERE publication_id=? AND source_event_key=?');$q->execute([$id,hash('sha256',$event)]);
 $observation=$q->fetch(PDO::FETCH_ASSOC);
 $q=$pdo->prepare('SELECT * FROM post_versions WHERE id=? AND publication_id=?');$q->execute([$entry['plan']['old_version_id'],$id]);
 $out['publications'][]=['publication'=>$p,'observation'=>$observation,'previous_version'=>$q->fetch(PDO::FETCH_ASSOC)];
}
$phase='local_feeds';
foreach(['telegram','max','all'] as $platform)$out['feeds'][$platform]=hash_file('sha256',$root.'/feed/hotel-posts-'.$platform.'.xml');
$phase='evidence';
$g=$out['global']['last'];$out['global']['last']=['schema'=>$g['schema']??null,'pending'=>$g['pending']??null,'nightly_cycle'=>$g['nightly_cycle']??null,'holds'=>$g['holds']??null,'completed_count'=>count($g['completed']??[]),'completed_sha256'=>hash('sha256',json_encode($g['completed']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
$raw=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);

must4294(hash('sha256',$raw)==='91dd4ec54aa101685efb8cee61eaf9b8d3c7cf5a07f225af52baeda523055666','native_evidence_changed');
// Fail closed on incomplete process visibility and non-idle SQL, not just name matching.
$phase='processes';
$p=proc_open(['ps','-eo','pid=,args='],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
must4294(is_resource($p),'process_read_failed');fclose($pipes[0]);$processes=stream_get_contents($pipes[1],2097153);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
must4294(proc_close($p)===0&&strlen($processes)<=2097152,'process_read_incomplete');
$active=0;foreach(explode("\n",trim($processes)) as $line){
 must4294(preg_match('/\A\s*([0-9]+)\s+(.+)\z/D',$line,$m)===1,'process_line_unknown');
 if((int)$m[1]===getmypid())continue;
 if(preg_match('/(?:collect-anytour|top500-daily-collector|published-hotels-monitor|monitor-price-hook|monitor-price-after-collection|recover\.php)/i',$m[2]))$active++;
}
must4294($active===0,'native_collector_busy');
// Consumer SQL was proven in isolated PHP before app/config; never load its config here.
$phase='consumer_code';
$consumer='/var/www/anytoour/data/www/anytoour.ru/data/';$hashes=[];
foreach(['monitor-price-hook.php'=>'canonical_hook_sha256','published-hotels-monitor-v1.php'=>'cohort_sha256','top500-daily-collector-v1.php'=>'collector_sha256'] as $file=>$key){
 must4294(!is_link($consumer.$file)&&is_file($consumer.$file),'consumer_path_changed');$hashes[$key]=hash_file('sha256',$consumer.$file);must4294($hashes[$key]===($c[$key]??null),'consumer_changed');}
$phase='bundle';
must4294(hash_file('sha256',__DIR__.'/staged-bundle.php')===$c['staged_bundle_sha256'],'staged_bundle_changed');
$phase='final_identity';
foreach($identities as [$f,$path,$before]){clearstatcache(true,$path);$after=fstat($f);$named=lstat($path);foreach(['ino','dev','size','mtime','ctime'] as $field)must4294($before[$field]===$after[$field]&&$before[$field]===$named[$field],'journal_identity_changed');}
$phase='output';
$producer=['consumer_db_sha256'=>hash_file('sha256',$dbFile),'run_id'=>(int)$runId,'head_sha'=>$head,'runtime_sha'=>'bd8e248f7138ea75cb9fda2dcf44beb631c633a5','observed_at_epoch'=>$sqlProof['observed_at_epoch'],'operation'=>$op,'global_pin'=>array_intersect_key($out['global'],array_flip(['sha256','bytes','lines'])),'child_pin'=>array_intersect_key($out['child'],array_flip(['sha256','bytes','lines'])),'feed_shas'=>$out['feeds'],'staged_bundle_sha256'=>$c['staged_bundle_sha256'],'cohort_sha256'=>$hashes['cohort_sha256'],'collector_sha256'=>$hashes['collector_sha256'],'native_active_collectors'=>$active,'sql_active_collectors'=>$sqlActive,'unknown_collectors'=>false];
$producer+=$processDiagnostic;
echo json_encode(['native_gzip_base64'=>base64_encode(gzencode($raw,9)),'native_sha256'=>hash('sha256',$raw),'producer'=>$producer],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
foreach(array_reverse($handles) as $f){flock($f,LOCK_UN);fclose($f);}

} catch(Throwable $error) {
 while(ob_get_level()>$initialBuffer)ob_end_clean();
 foreach(array_reverse($handles) as $handle)if(is_resource($handle)){flock($handle,LOCK_UN);fclose($handle);}
 $safePhase=producer_warning_metadata($phase,0,'',0)['warning_phase'];
 $kind=$error instanceof PDOException?'pdo':($error instanceof Error?'error':($error instanceof Exception?'exception':'other'));
 $failure=['ok'=>false,'error'=>producer_safe_error($error),'read_only'=>true,'producer_phase'=>$safePhase,'throwable_kind'=>$kind];
 if(isset($processDiagnostic))$failure+=$processDiagnostic;
 if(isset($GLOBALS['sql_meta']))$failure+=$GLOBALS['sql_meta'];
 if(isset($GLOBALS['sql_transport'])&&in_array($GLOBALS['sql_transport'],['env','spawn','capture','timeout_or_bound','stderr','child_exit','parse'],true))$failure['sql_transport']=$GLOBALS['sql_transport'];
 if(isset($GLOBALS['sql_diag']))$failure+=array_intersect_key($GLOBALS['sql_diag'],array_flip(['sql_phase','sql_kind']));
 if($failure['error']==='producer_runtime_warning'&&is_array($warning))$failure+=$warning;
 echo json_encode($failure),"\n";exit(2);
}
