<?php
declare(strict_types=1);
function sqlprobe_need(bool $ok):void {if(!$ok)throw new RuntimeException('sql_probe_closed');}
function sqlprobe_identity(string $path,string $target):array {
 clearstatcache(true,$path);$GLOBALS['sql_diag']+=[];$GLOBALS['sql_diag']['identity_target']=$target;$GLOBALS['sql_diag']['identity_exists']=is_file($path);$GLOBALS['sql_diag']['identity_is_link']=is_link($path);$GLOBALS['sql_diag']['identity_realpath_equal']=realpath($path)===$path;sqlprobe_need(!is_link($path)&&is_file($path)&&realpath($path)===$path);$s=lstat($path);sqlprobe_need(is_array($s));
 return array_intersect_key($s,array_flip(['dev','ino','mode','uid','gid','size','mtime','ctime']));
}
function sqlprobe_core(string $dbPath,string $configPath,string $dbSha):array {
 $GLOBALS['sql_diag']=['sql_phase'=>'path','sql_kind'=>'guard'];$level=ob_get_level();ob_start();set_error_handler(static function(int $severity,string $message,string $file,int $line){$GLOBALS['sql_diag']['sql_kind']='config_warning';$GLOBALS['sql_diag']['child_warning_kind']=preg_match('/Constant .+ already defined/i',$message)?'constant_already_defined':(str_contains($message,'Undefined array key')?'undefined_array_key':(($severity&E_DEPRECATED)||($severity&E_USER_DEPRECATED)?'deprecated':'other'));$GLOBALS['sql_diag']['child_warning_file']=in_array(basename($file),['config.php','db-v1.php'],true)?basename($file):'other';$GLOBALS['sql_diag']['child_warning_line']=$line;$GLOBALS['sql_diag']['child_warning_severity']=$severity;throw new RuntimeException('sql_probe_warning');});
 try {
  // Canonical config must exist. The DOCUMENT_ROOT fallback is never admitted.
  sqlprobe_need(dirname(dirname($dbPath)).'/config.php'===$configPath);
  $GLOBALS['sql_diag']['sql_phase']='identity';$dbIdentity=sqlprobe_identity($dbPath,'db');$configIdentity=sqlprobe_identity($configPath,'config');
  $GLOBALS['sql_diag']['sql_phase']='db_pin';sqlprobe_need(hash_file('sha256',$dbPath)===$dbSha);
  sqlprobe_need(!function_exists('v2_data_db')&&!in_array($configPath,get_included_files(),true));
  $GLOBALS['sql_diag']['sql_phase']='include_db';require $dbPath;sqlprobe_need(function_exists('v2_data_db'));
  $GLOBALS['sql_diag']['sql_phase']='connect_config';$db=v2_data_db();sqlprobe_need($db instanceof PDO);
  $GLOBALS['sql_diag']['sql_phase']='query';$q=$db->query("SELECT IS_USED_LOCK('anytour_top500_monthly_v1')");sqlprobe_need($q instanceof PDOStatement);
  $GLOBALS['sql_diag']['sql_phase']='owner';$owner=$q->fetchColumn();if($owner!==null)$GLOBALS['sql_diag']['sql_kind']='lock_busy';sqlprobe_need($owner===null);
  $GLOBALS['sql_diag']['sql_phase']='config_included';sqlprobe_need(in_array($configPath,get_included_files(),true));
  $GLOBALS['sql_diag']['sql_phase']='identity_after';sqlprobe_need(sqlprobe_identity($dbPath,'db')===$dbIdentity&&sqlprobe_identity($configPath,'config')===$configIdentity&&hash_file('sha256',$dbPath)===$dbSha);
  $GLOBALS['sql_diag']['sql_phase']='output';sqlprobe_need(ob_get_contents()==='');
  return ['schema'=>'price1619-isolated-sql-v1','ok'=>true,'sql_active_collectors'=>0,'consumer_db_sha256'=>$dbSha,'config_identity'=>$configIdentity,'observed_at_epoch'=>time()];
 }catch(Throwable $e){if($e instanceof PDOException){$GLOBALS['sql_diag']['sql_kind']=in_array('mysql',PDO::getAvailableDrivers(),true)?'pdo':'missing_driver';$state=substr((string)$e->getCode(),0,2);$GLOBALS['sql_diag']['pdo_state_class']=in_array($state,['08','28','42','HY','IM'],true)?$state:'other';$code=$e->errorInfo[1]??null;if(is_int($code)&&in_array($code,[1045,1049,2002,2006,2013],true))$GLOBALS['sql_diag']['pdo_driver_code']=$code;}elseif($e instanceof Error)$GLOBALS['sql_diag']['sql_kind']='error';throw new RuntimeException('sql_probe_failed_closed');}
 finally{restore_error_handler();while(ob_get_level()>$level)ob_end_clean();}
}
