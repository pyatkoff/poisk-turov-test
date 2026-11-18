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
 $childEnv=isolated_consumer_child_env($originalConsumerEnv);
 if($timeoutSeconds<1||$timeoutSeconds>20||!preg_match('/\A[a-f0-9]{64}\z/D',$expectedSha)||is_link($worker)||!is_file($worker)||hash_file('sha256',$worker)!==$expectedSha)throw new RuntimeException('isolated_sql_unbound');
 // PHP proc_open omits empty env values. env KEY= restores only empty slots; no secret values enter argv.
 $command=['/usr/bin/env'];foreach(SQL_PROBE_ENV_KEYS as $key)if($originalConsumerEnv[$key]==='')$command[]=$key.'=';
 $command=array_merge($command,[PHP_BINARY,'-d','display_errors=0','-d','log_errors=0','-d','auto_prepend_file=','-d','auto_append_file=',$worker]);
 $p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$childEnv);
 if(!is_resource($p))throw new RuntimeException('isolated_sql_unavailable');fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$out='';$err='';$deadline=microtime(true)+$timeoutSeconds;$failed=false;$exit=null;
 try {
  while(true){$out.=stream_get_contents($pipes[1],4097);$err.=stream_get_contents($pipes[2],4097);if(strlen($out)>4096||strlen($err)>4096||microtime(true)>$deadline){$failed=true;proc_terminate($p,9);break;}$status=proc_get_status($p);if(!$status['running']){$exit=$status['exitcode'];$out.=stream_get_contents($pipes[1],4097);$err.=stream_get_contents($pipes[2],4097);break;}usleep(10000);}
 }finally{fclose($pipes[1]);fclose($pipes[2]);$closed=proc_close($p);}
 if($failed||($exit??$closed)!==0||strlen($out)>4096||$err!=='')throw new RuntimeException('isolated_sql_not_proven_idle');
 try{$r=json_decode($out,true,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('isolated_sql_not_proven_idle');}
 if(!is_array($r)||($r['schema']??null)!=='price1619-isolated-sql-v1'||($r['ok']??null)!==true||($r['sql_active_collectors']??null)!==0||($r['consumer_db_sha256']??null)!=='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'||!is_array($r['config_identity']??null)||!is_int($r['observed_at_epoch']??null)||$r['observed_at_epoch']>time()||time()-$r['observed_at_epoch']>20)throw new RuntimeException('isolated_sql_not_proven_idle');
 return $r;
}
