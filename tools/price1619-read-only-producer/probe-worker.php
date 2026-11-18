<?php
declare(strict_types=1);
const SQL_PROBE_ENABLED=true;
ini_set('display_errors','0');ini_set('log_errors','0');
$done=false;$level=ob_get_level();ob_start();
register_shutdown_function(static function()use(&$done,$level){if(!$done){while(ob_get_level()>$level)ob_end_clean();echo '{"ok":false,"error":"sql_probe_failed_closed"}\n';}});
try {
 if(!SQL_PROBE_ENABLED||PHP_SAPI!=='cli'||count($argv)!==1)throw new RuntimeException('closed');
 if(is_link(__DIR__.'/probe-core.php')||hash_file('sha256',__DIR__.'/probe-core.php')!=='6c3009c938be161ef1d80b5ff7a0071f95c4dc0c6391f39f7d39f05d85d322af')throw new RuntimeException('closed');
 require __DIR__.'/probe-core.php';
 $r=sqlprobe_core('/var/www/anytoour/data/www/anytoour.ru/data/db-v1.php','/var/www/anytoour/data/www/anytoour.ru/config.php','ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4');
 $raw=json_encode($r,JSON_THROW_ON_ERROR);if(strlen($raw)>2048||ob_get_contents()!=='')throw new RuntimeException('closed');
 while(ob_get_level()>$level)ob_end_clean();$done=true;echo $raw,"\n";exit(0);
}catch(Throwable $e){while(ob_get_level()>$level)ob_end_clean();$done=true;echo json_encode(['ok'=>false,'error'=>'sql_probe_failed_closed','child_php_version_id'=>PHP_VERSION_ID,'child_mysql_available'=>class_exists('PDO')&&in_array('mysql',PDO::getAvailableDrivers(),true)]+($GLOBALS['sql_diag']??['sql_phase'=>'worker_guard','sql_kind'=>'loader'])),"\n";exit(2);}
