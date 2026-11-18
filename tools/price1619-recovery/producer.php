<?php
declare(strict_types=1);
function git4294(array $args):string{
 $p=proc_open(array_merge(['git'], $args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($p))throw new RuntimeException('git_read_failed');fclose($pipes[0]);$out=stream_get_contents($pipes[1],2000001);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($p)!==0||strlen($out)>2000000)throw new RuntimeException('git_read_failed');return trim($out);
}
$initialBuffer=ob_get_level();$handles=[];
set_error_handler(static function(){throw new RuntimeException('producer_runtime_warning');});
try {
function must4294($v,string $m):void{if(!$v)throw new RuntimeException($m);}
must4294(count($argv)===5,'producer_args');
[$self,$configFile,$runId,$head,$configSha]=$argv;
must4294(ctype_digit($runId)&&preg_match('/\A[a-f0-9]{40}\z/D',$head)===1,'producer_identity');
must4294(!is_link($configFile)&&is_file($configFile)&&hash_file('sha256',$configFile)===$configSha,'producer_config_hash');
$c=json_decode(file_get_contents($configFile),true,512,JSON_THROW_ON_ERROR);
must4294(($c['producer_enabled']??null)===true,'producer_closed');

$root='/var/www/anytoour/data/www/app.anytoour.ru'; chdir($root);
if(git4294(['status','--porcelain','--untracked-files=no'])!=='')throw new Exception('runtime_dirty');
if(git4294(['rev-parse','HEAD'])!=='92bd28bea3f8e00bffb154b267104640fd53ce17')throw new Exception('runtime_changed');
ob_start();require $root.'/config.php';require_once $root.'/services/ChannelPostRuntime.php';ob_end_clean();
$dir='/var/www/anytoour/data/channel-post-operations';
$op='commercial-monitor-price-h4294-f72863a3310b60862a907f040008fd46c82e7e044485488e9eece8adb1237e24';
$identities=[];$handles=[];$out=['operation'=>$op,'read_only'=>true];
foreach(['global'=>[$dir.'/monitor-price-after-collection-v1.jsonl','2f45a4f2d5c964d7cda1d7264931b14acf96239dc5586b5fad7bc19b73f78de1'],'child'=>[$dir.'/'.$op.'/receipt.jsonl','86946ce11d552eb55f9362d2342fa96097cc8f8e32df92a90706cc36ffad986b']] as $key=>[$path,$sha]){
 if(is_link($path)||realpath($path)!==$path)throw new Exception('path_changed');
 $f=fopen($path,'rb');if(!$f||!flock($f,LOCK_SH|LOCK_NB))throw new Exception('busy');$handles[]=$f;
 $before=fstat($f);$identities[]=[$f,$path,$before];$named=lstat($path);if($before['ino']!==$named['ino']||$before['dev']!==$named['dev'])throw new Exception('file_replaced');
 $h=hash_init('sha256');$last='';$n=0;while(($line=fgets($f,4194305))!==false){if(!str_ends_with($line,"\n"))throw new Exception('incomplete');hash_update($h,$line);$last=$line;$n++;}
 clearstatcache(true,$path);$after=fstat($f);$named=lstat($path);foreach(['ino','dev','size','mtime','ctime'] as $field)if($before[$field]!==$after[$field]||$before[$field]!==$named[$field])throw new Exception('file_changed');
 if(hash_final($h)!==$sha)throw new Exception('preimage_changed');
 $out[$key]=['sha256'=>$sha,'bytes'=>ftell($f),'lines'=>$n,'last_sha256'=>hash('sha256',$last),'last'=>json_decode($last,true,64,JSON_THROW_ON_ERROR)];
}
if(($out['global']['last']['pending']['operation']??null)!==$op)throw new Exception('pending_changed');
$pdo=ConversationDb::connection();$r=new ChannelPostRegistryService($pdo);$out['publications']=[];
foreach($out['child']['last']['posts'] as $entry){
 $id=$entry['plan']['publication_id'];$p=$r->publication($id);if(!$p)throw new Exception('missing');
 $event=$p['snapshot']['metadata']['source_event_id']??'';
 $q=$pdo->prepare('SELECT version_id FROM post_observations WHERE publication_id=? AND source_event_key=?');$q->execute([$id,hash('sha256',$event)]);
 $observation=$q->fetch(PDO::FETCH_ASSOC);
 $q=$pdo->prepare('SELECT * FROM post_versions WHERE id=? AND publication_id=?');$q->execute([$entry['plan']['old_version_id'],$id]);
 $out['publications'][]=['publication'=>$p,'observation'=>$observation,'previous_version'=>$q->fetch(PDO::FETCH_ASSOC)];
}
foreach(['telegram','max','all'] as $platform)$out['feeds'][$platform]=hash_file('sha256',$root.'/feed/hotel-posts-'.$platform.'.xml');
$g=$out['global']['last'];$out['global']['last']=['schema'=>$g['schema']??null,'pending'=>$g['pending']??null,'nightly_cycle'=>$g['nightly_cycle']??null,'holds'=>$g['holds']??null,'completed_count'=>count($g['completed']??[]),'completed_sha256'=>hash('sha256',json_encode($g['completed']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
$raw=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);

must4294(hash('sha256',$raw)==='91dd4ec54aa101685efb8cee61eaf9b8d3c7cf5a07f225af52baeda523055666','native_evidence_changed');
// Fail closed on incomplete process visibility and non-idle SQL, not just name matching.
$p=proc_open(['ps','-eo','pid=,args='],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
must4294(is_resource($p),'process_read_failed');fclose($pipes[0]);$processes=stream_get_contents($pipes[1],2097153);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
must4294(proc_close($p)===0&&strlen($processes)<=2097152,'process_read_incomplete');
$active=0;foreach(explode("\n",trim($processes)) as $line){
 must4294(preg_match('/\A\s*([0-9]+)\s+(.+)\z/D',$line,$m)===1,'process_line_unknown');
 if((int)$m[1]===getmypid())continue;
 if(preg_match('/(?:collect-anytour|top500-daily-collector|published-hotels-monitor|monitor-price-hook|monitor-price-after-collection|recover\.php)/i',$m[2]))$active++;
}
must4294($active===0,'native_collector_busy');
// The collector requires __DIR__.'/db-v1.php' at its deployed /data path.
$dbFile='/var/www/anytoour/data/www/anytoour.ru/data/db-v1.php';
must4294(!is_link($dbFile)&&is_file($dbFile)&&hash_file('sha256',$dbFile)===($c['consumer_db_sha256']??null),'consumer_db_source_changed');
ob_start();try {require_once $dbFile;must4294(function_exists('v2_data_db'),'consumer_db_function_missing');
$collectorDb=v2_data_db();must4294($collectorDb instanceof PDO,'consumer_db_unavailable');
$lockOwner=$collectorDb->query("SELECT IS_USED_LOCK('anytour_top500_monthly_v1')")->fetchColumn();
must4294($lockOwner===null,'canonical_collector_sql_lock_busy');$sqlActive=0;}finally{ob_end_clean();}
$consumer='/var/www/anytoour/data/www/anytoour.ru/data/';$hashes=[];
foreach(['monitor-price-hook.php'=>'canonical_hook_sha256','published-hotels-monitor-v1.php'=>'cohort_sha256','top500-daily-collector-v1.php'=>'collector_sha256'] as $file=>$key){
 must4294(!is_link($consumer.$file)&&is_file($consumer.$file),'consumer_path_changed');$hashes[$key]=hash_file('sha256',$consumer.$file);must4294($hashes[$key]===($c[$key]??null),'consumer_changed');}
must4294(hash_file('sha256',__DIR__.'/staged-bundle.php')===$c['staged_bundle_sha256'],'staged_bundle_changed');
foreach($identities as [$f,$path,$before]){clearstatcache(true,$path);$after=fstat($f);$named=lstat($path);foreach(['ino','dev','size','mtime','ctime'] as $field)must4294($before[$field]===$after[$field]&&$before[$field]===$named[$field],'journal_identity_changed');}
$producer=['run_id'=>(int)$runId,'head_sha'=>$head,'runtime_sha'=>'92bd28bea3f8e00bffb154b267104640fd53ce17','observed_at_epoch'=>time(),'operation'=>$op,'global_pin'=>array_intersect_key($out['global'],array_flip(['sha256','bytes','lines'])),'child_pin'=>array_intersect_key($out['child'],array_flip(['sha256','bytes','lines'])),'feed_shas'=>$out['feeds'],'staged_bundle_sha256'=>$c['staged_bundle_sha256'],'cohort_sha256'=>$hashes['cohort_sha256'],'collector_sha256'=>$hashes['collector_sha256'],'native_active_collectors'=>$active,'sql_active_collectors'=>$sqlActive,'unknown_collectors'=>false];
echo json_encode(['native_gzip_base64'=>base64_encode(gzencode($raw,9)),'native_sha256'=>hash('sha256',$raw),'producer'=>$producer],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
foreach(array_reverse($handles) as $f){flock($f,LOCK_UN);fclose($f);}

} catch(Throwable $error) {
 while(ob_get_level()>$initialBuffer)ob_end_clean();
 foreach(array_reverse($handles) as $handle)if(is_resource($handle)){flock($handle,LOCK_UN);fclose($handle);}
 echo json_encode(['ok'=>false,'error'=>'producer_failed_closed','read_only'=>true]),"\n";exit(2);
}
