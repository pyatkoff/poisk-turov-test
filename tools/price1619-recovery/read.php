<?php
declare(strict_types=1);
$root='/var/www/anytoour/data/www/app.anytoour.ru'; chdir($root);
if(trim((string)shell_exec('git status --porcelain --untracked-files=no'))!=='')throw new Exception('runtime_dirty');
if(trim((string)shell_exec('git rev-parse HEAD'))!=='92bd28bea3f8e00bffb154b267104640fd53ce17')throw new Exception('runtime_changed');
ob_start();require $root.'/config.php';require_once $root.'/services/ChannelPostRuntime.php';ob_end_clean();
$dir='/var/www/anytoour/data/channel-post-operations';
$op='commercial-monitor-price-h4294-f72863a3310b60862a907f040008fd46c82e7e044485488e9eece8adb1237e24';
$handles=[];$out=['operation'=>$op,'read_only'=>true];
foreach(['global'=>[$dir.'/monitor-price-after-collection-v1.jsonl','2f45a4f2d5c964d7cda1d7264931b14acf96239dc5586b5fad7bc19b73f78de1'],'child'=>[$dir.'/'.$op.'/receipt.jsonl','86946ce11d552eb55f9362d2342fa96097cc8f8e32df92a90706cc36ffad986b']] as $key=>[$path,$sha]){
 if(is_link($path)||realpath($path)!==$path)throw new Exception('path_changed');
 $f=fopen($path,'rb');if(!$f||!flock($f,LOCK_SH|LOCK_NB))throw new Exception('busy');$handles[]=$f;
 $before=fstat($f);$named=lstat($path);if($before['ino']!==$named['ino']||$before['dev']!==$named['dev'])throw new Exception('file_replaced');
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
echo 'READ4294_SHA256='.hash('sha256',$raw),"\n",'READ4294_BASE64='.base64_encode(gzencode($raw,9)),"\n";
foreach(array_reverse($handles) as $f){flock($f,LOCK_UN);fclose($f);}
