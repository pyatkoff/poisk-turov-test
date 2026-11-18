<?php
declare(strict_types=1);
const SQL_PROBE_ENABLED=true;
ini_set('display_errors','0');ini_set('log_errors','0');
$done=false;$level=ob_get_level();ob_start();
register_shutdown_function(static function()use(&$done,$level){if(!$done){while(ob_get_level()>$level)ob_end_clean();echo '{"ok":false,"error":"sql_probe_failed_closed"}\n';}});
try {
 if(!SQL_PROBE_ENABLED||PHP_SAPI!=='cli'||count($argv)!==1)throw new RuntimeException('closed');
 if(is_link(__DIR__.'/probe-core.php')||hash_file('sha256',__DIR__.'/probe-core.php')!=='605abf560512d8c7feb2ccb9854c185c42e701730b3d34141328843733289735')throw new RuntimeException('closed');
 require __DIR__.'/probe-core.php';
 $r=sqlprobe_core('/var/www/anytoour/data/www/anytoour.ru/data/db-v1.php','/var/www/anytoour/data/www/anytoour.ru/config.php','ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4');
 $raw=json_encode($r,JSON_THROW_ON_ERROR);if(strlen($raw)>2048||ob_get_contents()!=='')throw new RuntimeException('closed');
 while(ob_get_level()>$level)ob_end_clean();$done=true;echo $raw,"\n";exit(0);
}catch(Throwable $e){while(ob_get_level()>$level)ob_end_clean();$done=true;echo '{"ok":false,"error":"sql_probe_failed_closed"}',"\n";exit(2);}
