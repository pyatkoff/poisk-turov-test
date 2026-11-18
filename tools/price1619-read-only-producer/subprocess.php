<?php
declare(strict_types=1);
const SQL_PROBE_ENV_KEYS=['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'];
// MUST be called at the native entrypoint before app/config, hook runtime or consumer bootstrap.
function isolated_consumer_env_snapshot():array {
 if(defined('APP_CONFIG_LOADED')||function_exists('v2_data_db'))throw new RuntimeException('isolated_sql_snapshot_too_late');
 foreach(get_included_files() as $file)if(basename($file)==='config.php')throw new RuntimeException('isolated_sql_snapshot_too_late');
 $out=[];foreach(SQL_PROBE_ENV_KEYS as $key)$out[$key]=getenv($key);return $out;
}
function isolated_consumer_child_env(array $original):array {
 $keys=array_keys($original);sort($keys);$expected=SQL_PROBE_ENV_KEYS;sort($expected);
 if($keys!==$expected)throw new RuntimeException('isolated_sql_env_shape');
 $env=['PATH'=>'/usr/bin:/bin','LANG'=>'C.UTF-8'];
 foreach(SQL_PROBE_ENV_KEYS as $key){$value=$original[$key];if($value!==false&&!is_string($value))throw new RuntimeException('isolated_sql_env_shape');if(is_string($value)){if(str_contains($value,"\0"))throw new RuntimeException('isolated_sql_env_shape');$env[$key]=$value;}}
 return $env;
}
// Caller must bind the reviewed immutable worker, including its core hash.
function isolated_consumer_sql_probe(string $worker,string $expectedSha,array $originalConsumerEnv,int $timeoutSeconds=15):array {
 $GLOBALS['sql_meta']=['parent_php_version_id'=>PHP_VERSION_ID,'parent_mysql_available'=>class_exists('PDO')&&in_array('mysql',PDO::getAvailableDrivers(),true)];$GLOBALS['sql_transport']='env';$childEnv=isolated_consumer_child_env($originalConsumerEnv);
 if($timeoutSeconds<1||$timeoutSeconds>20||!preg_match('/\A[a-f0-9]{64}\z/D',$expectedSha)||is_link($worker)||!is_file($worker)||hash_file('sha256',$worker)!==$expectedSha)throw new RuntimeException('isolated_sql_unbound');
 // PHP proc_open omits empty env values. env KEY= restores only empty slots; no secret values enter argv.
 $command=['/usr/bin/env'];foreach(SQL_PROBE_ENV_KEYS as $key)if($originalConsumerEnv[$key]==='')$command[]=$key.'=';
 $command=array_merge($command,[PHP_BINARY,'-d','display_errors=0','-d','log_errors=0','-d','auto_prepend_file=','-d','auto_append_file=',$worker]);
 $GLOBALS['sql_transport']='spawn';$p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$childEnv);
 if(!is_resource($p))throw new RuntimeException('isolated_sql_unavailable');fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$out='';$err='';$deadline=microtime(true)+$timeoutSeconds;$failed=false;$exit=null;$timedOut=false;$bounded=false;
 try {
  $GLOBALS['sql_transport']='capture';
  while(true){$out.=stream_get_contents($pipes[1],4097);$err.=stream_get_contents($pipes[2],4097);if(strlen($out)>4096||strlen($err)>4096||microtime(true)>$deadline){$timedOut=microtime(true)>$deadline;$bounded=strlen($out)>4096||strlen($err)>4096;$failed=true;proc_terminate($p,9);break;}$status=proc_get_status($p);if(!$status['running']){$exit=$status['exitcode'];$out.=stream_get_contents($pipes[1],4097);$err.=stream_get_contents($pipes[2],4097);break;}usleep(10000);}
 }finally{fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($p);}
 $GLOBALS['sql_meta']+=['transport_exitcode'=>$exit??$closed,'transport_timeout'=>$timedOut,'transport_output_bounds'=>$bounded,'transport_stderr_nonempty'=>$err!=='','transport_stderr_sha256'=>hash('sha256',$err)];
 $GLOBALS['sql_transport']=$failed?'timeout_or_bound':($err!==''?'stderr':(($exit??$closed)!==0?'child_exit':'parse'));
 $diag=json_decode($out,true);
 if(is_array($diag)&&($diag['ok']??null)===false&&($diag['error']??null)==='sql_probe_failed_closed'
  &&in_array($diag['sql_phase']??null,['path','identity','db_pin','include_db','connect_config','query','owner','config_included','identity_after','output','worker_guard'],true)
  &&in_array($diag['sql_kind']??null,['guard','config_warning','pdo','error','loader','missing_driver','lock_busy','shape'],true))$GLOBALS['sql_diag']=array_intersect_key($diag,array_flip(['sql_phase','sql_kind']));
 if(is_array($diag)&&in_array($diag['identity_target']??null,['db','config'],true)){
  $GLOBALS['sql_meta']['identity_target']=$diag['identity_target'];
  foreach(['identity_exists','identity_is_link','identity_realpath_equal'] as $k)if(is_bool($diag[$k]??null))$GLOBALS['sql_meta'][$k]=$diag[$k];
 }
 if(is_array($diag)){
  foreach(['child_warning_kind'=>['constant_already_defined','undefined_array_key','deprecated','other'],'child_warning_file'=>['config.php','db-v1.php','other'],'pdo_state_class'=>['08','28','42','HY','IM','other']] as $k=>$values)if(in_array($diag[$k]??null,$values,true))$GLOBALS['sql_meta'][$k]=$diag[$k];
  foreach(['child_warning_line','child_warning_severity'] as $k)if(is_int($diag[$k]??null)&&$diag[$k]>=0&&$diag[$k]<=1000000)$GLOBALS['sql_meta'][$k]=$diag[$k];
  if(is_int($diag['pdo_driver_code']??null)&&in_array($diag['pdo_driver_code'],[1045,1049,2002,2006,2013],true))$GLOBALS['sql_meta']['pdo_driver_code']=$diag['pdo_driver_code'];
 }
 if(is_array($diag)){
  foreach(['owner_class'=>['null','int_positive','numeric_string_positive','zero','false','other'],'owner_probe'=>['not_positive','connection','processlist','observed','failed'],'owner_probe_error'=>['pdo','error','exception'],'owner_command'=>['Sleep','Query','Execute','Connect','Daemon','Binlog Dump','Killed','other']] as $k=>$values)if(in_array($diag[$k]??null,$values,true))$GLOBALS['sql_meta'][$k]=$diag[$k];
  foreach(['owner_equals_connection_id','owner_visible'] as $k)if(is_bool($diag[$k]??null))$GLOBALS['sql_meta'][$k]=$diag[$k];
  if(is_int($diag['owner_seconds']??null)&&$diag['owner_seconds']>=0&&$diag['owner_seconds']<=2147483647)$GLOBALS['sql_meta']['owner_seconds']=$diag['owner_seconds'];
 }
 if(is_array($diag)&&is_int($diag['child_php_version_id']??null)&&is_bool($diag['child_mysql_available']??null))$GLOBALS['sql_meta']+=array_intersect_key($diag,array_flip(['child_php_version_id','child_mysql_available']));
 if($failed||($exit??$closed)!==0||strlen($out)>4096||$err!=='')throw new RuntimeException('isolated_sql_not_proven_idle');
 try{$r=json_decode($out,true,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('isolated_sql_not_proven_idle');}
 if(!is_array($r)||($r['schema']??null)!=='price1619-isolated-sql-v1'||($r['ok']??null)!==true||($r['sql_active_collectors']??null)!==0||($r['consumer_db_sha256']??null)!=='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'||!is_array($r['config_identity']??null)||!is_int($r['observed_at_epoch']??null)||$r['observed_at_epoch']>time()||time()-$r['observed_at_epoch']>20)throw new RuntimeException('isolated_sql_not_proven_idle');
 return $r;
}
