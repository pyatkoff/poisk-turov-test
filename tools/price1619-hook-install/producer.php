<?php
declare(strict_types=1);
require __DIR__.'/guards.php';
$handles=[];$identities=[];$level=ob_get_level();set_error_handler(static function(){throw new RuntimeException('producer_runtime_warning');});
try{
 $hiOriginalConsumerEnv=hi_sql_bootstrap_snapshot();
 must(count($argv)===5,'producer_arguments');[$self,$configFile,$run,$head,$sha]=$argv;
 [$raw]=readfile_exact($configFile,2000000);must(hash('sha256',$raw)===$sha,'config_changed');$c=json_decode($raw,true,128,JSON_THROW_ON_ERROR);
 must(($c['producer_enabled']??null)===true&&ctype_digit($run)&&preg_match('/\A[a-f0-9]{40}\z/D',$head)===1,'producer_closed');
 [$bundle]=readfile_exact(__DIR__.'/candidate.php',2000000);must(hash('sha256',$bundle)===HI_NEW,'candidate_changed');
 $marker="\n\$inspect=getenv('ANYTOUR_MONITOR_HOOK_INSPECT')";must(substr_count($bundle,$marker)===1,'definitions_boundary');eval(substr(explode($marker,$bundle,2)[0],5));
 $app='/var/www/anytoour/data/www/app.anytoour.ru';$data='/var/www/anytoour/data/www/anytoour.ru/data';$private='/var/www/anytoour/data/channel-post-operations';
 must(chdir($app)&&hook_runtime()===$c['runtime_sha']&&$c['runtime_sha']==='bd8e248f7138ea75cb9fda2dcf44beb631c633a5','runtime_changed');
 $out=['operation'=>HI_OPERATION,'runtime_sha'=>$c['runtime_sha'],'run_id'=>(int)$run,'head_sha'=>$head,'candidate_hook_sha256'=>HI_NEW];
 foreach(['journal_pin'=>$private.'/monitor-price-after-collection-v1.jsonl','child_pin'=>$private.'/'.HI_RECOVERED.'/receipt.jsonl'] as $key=>$path){
  must(!is_link($path)&&realpath($path)===$path,'journal_path');$f=fopen($path,'rb');must(is_resource($f)&&flock($f,LOCK_SH|LOCK_NB),'journal_busy');$handles[]=$f;$before=fstat($f);must($before['size']>0&&$before['size']<=($key==='journal_pin'?1073741824:2000000),'journal_bound');
  if($key==='journal_pin'){$h=monitor_hook_read_history($f);must($h['trailing_blank_lines']===0,'journal_trailing');hi_state($h['state']);$pin=$h['pin'];$out['pending']=null;$out['completed4294']=true;}
  else{$bytes=stream_get_contents($f,2000001);must(strlen($bytes)<=2000000&&feof($f),'child_bound');$child=monitor_hook_parse_journal($bytes,HI_RECOVERED);must(($child['complete']??null)===true,'child_incomplete');$pin=['sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes),'lines'=>substr_count($bytes,"\n")];}
  clearstatcache(true,$path);$after=fstat($f);$named=lstat($path);foreach(['dev','ino','size','mtime','ctime'] as $field)must($before[$field]===$after[$field]&&$before[$field]===$named[$field],'journal_identity_changed');
  must(hi_pin_equal($pin,$c[$key]),'postrecovery_pin_changed');$out[$key]=$pin;$identities[]=[$f,$path,$before];
 }
 foreach(['published-hotels-monitor-v1.php'=>'cohort_sha256','top500-daily-collector-v1.php'=>'collector_sha256','monitor-price-hook.php'=>'old_hook_sha256'] as $name=>$key){[$bytes]=readfile_exact($data.'/'.$name,2000000);must(hash('sha256',$bytes)===$c[$key],'consumer_changed');$out[$key]=$c[$key];}
 $out['feed_shas']=[];$out['feed_counts']=[];
 foreach(['telegram','max','all'] as $name){[$bytes]=readfile_exact($app.'/feed/hotel-posts-'.$name.'.xml',4000000);$hash=hash('sha256',$bytes);must($hash===$c['feed_shas'][$name]&&hash('sha256',hook_get('https://app.anytoour.ru/feed/hotel-posts-'.$name.'.xml'))===$hash,'feed_changed');$count=substr_count($bytes,'<offer id="');must($count===$c['feed_counts'][$name],'feed_count_changed');$out['feed_shas'][$name]=$hash;$out['feed_counts'][$name]=$count;}
 $out['native_active_collectors']=active_collectors();must($out['native_active_collectors']===0,'native_collector_busy');
 $sqlProof=hi_consumer_sql_idle();
 $out['sql_active_collectors']=0;$out['unknown_collectors']=false;
 [$recoveryRaw]=readfile_exact(__DIR__.'/recovery.private.json',2000000);must(hash('sha256',$recoveryRaw)===$c['recovery_receipt_sha256'],'recovery_receipt_changed');$r=json_decode($recoveryRaw,true,128,JSON_THROW_ON_ERROR);
 must(($r['ok']??null)===true&&($r['operation']??null)===HI_RECOVERED&&($r['phase']??null)==='completed'&&array_key_exists('pending',$r)&&$r['pending']===null,'recovery_not_completed');
 must(hi_pin_equal($r['global_pin'],$out['journal_pin'])&&hi_pin_equal($r['child_pin'],$out['child_pin'])&&hi_feed_map($r['feed_shas'])===hi_feed_map($out['feed_shas']),'recovery_receipt_pins_changed');$out['recovery_receipt_sha256']=$c['recovery_receipt_sha256'];
 foreach($identities as [$f,$path,$before]){clearstatcache(true,$path);$after=fstat($f);$named=lstat($path);foreach(['dev','ino','size','mtime','ctime'] as $field)must($before[$field]===$after[$field]&&$before[$field]===$named[$field],'journal_changed');}
 $out['observed_at_epoch']=$sqlProof['observed_at_epoch'];echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable $e){while(ob_get_level()>$level)ob_end_clean();echo '{"ok":false,"error":"producer_failed_closed"}',"\n";exit(2);}
finally{foreach(array_reverse($handles) as $f)if(is_resource($f)){flock($f,LOCK_UN);fclose($f);}}
