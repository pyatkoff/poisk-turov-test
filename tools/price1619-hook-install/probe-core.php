<?php
declare(strict_types=1);
function sqlprobe_need(bool $ok):void {if(!$ok)throw new RuntimeException('sql_probe_closed');}
function sqlprobe_identity(string $path):array {
 clearstatcache(true,$path);sqlprobe_need(!is_link($path)&&is_file($path)&&realpath($path)===$path);$s=lstat($path);sqlprobe_need(is_array($s));
 return array_intersect_key($s,array_flip(['dev','ino','mode','uid','gid','size','mtime','ctime']));
}
function sqlprobe_core(string $dbPath,string $configPath,string $dbSha):array {
 $level=ob_get_level();ob_start();set_error_handler(static function(){throw new RuntimeException('sql_probe_warning');});
 try {
  // Canonical config must exist. The DOCUMENT_ROOT fallback is never admitted.
  sqlprobe_need(dirname(dirname($dbPath)).'/config.php'===$configPath);
  $dbIdentity=sqlprobe_identity($dbPath);$configIdentity=sqlprobe_identity($configPath);
  sqlprobe_need(hash_file('sha256',$dbPath)===$dbSha);
  sqlprobe_need(!function_exists('v2_data_db')&&!in_array($configPath,get_included_files(),true));
  require $dbPath;sqlprobe_need(function_exists('v2_data_db'));
  $db=v2_data_db();sqlprobe_need($db instanceof PDO);
  $q=$db->query("SELECT IS_USED_LOCK('anytour_top500_monthly_v1')");sqlprobe_need($q instanceof PDOStatement);
  $owner=$q->fetchColumn();sqlprobe_need($owner===null);
  sqlprobe_need(in_array($configPath,get_included_files(),true));
  sqlprobe_need(sqlprobe_identity($dbPath)===$dbIdentity&&sqlprobe_identity($configPath)===$configIdentity&&hash_file('sha256',$dbPath)===$dbSha);
  sqlprobe_need(ob_get_contents()==='');
  return ['schema'=>'price1619-isolated-sql-v1','ok'=>true,'sql_active_collectors'=>0,'consumer_db_sha256'=>$dbSha,'config_identity'=>$configIdentity,'observed_at_epoch'=>time()];
 }catch(Throwable $e){throw new RuntimeException('sql_probe_failed_closed');}
 finally{restore_error_handler();while(ob_get_level()>$level)ob_end_clean();}
}
