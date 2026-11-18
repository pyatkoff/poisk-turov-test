<?php
declare(strict_types=1);
const RJ_CANDIDATE_SHA='483f9f1cd853fdf1808523e72d466e2acd0866ec6ae35de049ada38b5cc128f9';
const RJ_PREFIX_SHA='831edf679b6b32668be954f4f9af47487161f878a912e30e279de4b9519042f1';
const RJ_JOURNAL='/var/www/anytoour/data/channel-post-operations/monitor-price-after-collection-v1.jsonl';
function rj_need(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);}
function rj_stat(array|false $s):array {
 rj_need(is_array($s),'journal_stat_failed');$r=[];
 foreach(['dev','ino','mode','nlink','uid','gid','size','mtime','ctime'] as $k)$r[$k]=$s[$k];
 return $r;
}
function rj_path(string $path,int $cap):array {
 clearstatcache(true,$path);$s=lstat($path);
 rj_need(is_array($s)&&($s['mode']&0170000)===0100000&&!is_link($path)&&realpath($path)===$path,'journal_path_invalid');
 rj_need($s['size']>0&&$s['size']<=$cap,'journal_size_invalid');return rj_stat($s);
}
function rj_load(string $candidate):void {
 $pre=rj_path($candidate,726483);$raw=file_get_contents($candidate);
 rj_need(is_string($raw)&&strlen($raw)===726483&&hash('sha256',$raw)===RJ_CANDIDATE_SHA&&rj_path($candidate,726483)===$pre,'candidate_pin_invalid');
 $marker="\$inspect=getenv('ANYTOUR_MONITOR_HOOK_INSPECT')";
 rj_need(substr_count($raw,$marker)===1,'candidate_prefix_invalid');$prefix=substr($raw,0,strpos($raw,$marker));
 rj_need(strlen($prefix)===709854&&hash('sha256',$prefix)===RJ_PREFIX_SHA&&str_starts_with($prefix,"<?php\n"),'candidate_prefix_invalid');
 // Exact immutable definitions prefix only. The hook main is never evaluated.
 eval(substr($prefix,6));
}
function rj_canonical(mixed $v):mixed {
 if(is_array($v)){if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=rj_canonical($x);unset($x);}
 return $v;
}
function rj_json(array $v):string {return json_encode(rj_canonical($v),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function rj_expected(string $path,string $sha):array {
 rj_need(preg_match('/\\A[a-f0-9]{64}\\z/D',$sha)===1,'expected_cycle_sha_invalid');
 $pre=rj_path($path,1000000);$raw=file_get_contents($path);
 rj_need(is_string($raw)&&rj_path($path,1000000)===$pre,'expected_cycle_file_changed');
 $cycle=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
 rj_need(is_array($cycle)&&hash_equals($sha,hash('sha256',rj_json($cycle))),'expected_cycle_pin_changed');return $cycle;
}
function rj_terminal(array $cycle):void {
 rj_need(($cycle['collector_run_id']??null)==='44236272559'&&($cycle['state']??null)==='preflight_blocked'
  &&monitor_hook_preflight_cycle_valid($cycle),'journal_cycle_invalid');
 $b=$cycle['preflight_block'];$t=$b['terminal'];
 rj_need($b['tourvisor_hotel_id']===36451
  &&$b['operation']==='commercial-monitor-price-h36451-6b83a32f7a19a587de7557b48b9162d194882971d3fb3f8be522ec7c7c3ae01d'
  &&$t['state']==='abandoned_before_write'&&$t['reason']==='channel_preflight_blocked_before_write'
  &&($t['channel_preflight']['write_attempts']??null)===0
  &&($b['child_failure']['channel_preflight']['write_attempts']??null)===0,'journal_preflight_identity_changed');
}
function rj_read(string $path,int $prefixBytes,string $prefixSha,array $expectedCycle):array {
 rj_need($prefixBytes>0&&$prefixBytes<=1073741824,'prefix_size_invalid');
 rj_need(preg_match('/\A[a-f0-9]{64}\z/D',$prefixSha)===1,'prefix_sha_invalid');
 $pre=rj_path($path,1073741824);$f=fopen($path,'rb');rj_need(is_resource($f),'journal_open_failed');
 try {
  rj_need(flock($f,LOCK_SH),'journal_lock_failed');
  rj_need(rj_stat(fstat($f))===$pre&&rj_path($path,1073741824)===$pre,'journal_identity_changed');
  rj_need($prefixBytes<=$pre['size'],'prefix_size_invalid');
  $h=hash_init('sha256');$left=$prefixBytes;$last='';$carry='';
  while($left>0){$chunk=fread($f,min(1048576,$left));rj_need(is_string($chunk)&&$chunk!=='','prefix_read_failed');$left-=strlen($chunk);hash_update($h,$chunk);$carry.=$chunk;
   while(($pos=strpos($carry,"\n"))!==false){$last=substr($carry,0,$pos);$carry=substr($carry,$pos+1);}
  }
  rj_need($carry===''&&$last!==''&&hash_equals($prefixSha,hash_final($h)),'prefix_changed');
  $baseline=json_decode($last,true,64,JSON_THROW_ON_ERROR);unset($carry,$last);
  rj_need(is_array($baseline)&&array_key_exists('pending',$baseline)&&$baseline['pending']===null,'prefix_pending_invalid');
  $history=monitor_hook_read_history($f);
  rj_need(rj_stat(fstat($f))===$pre&&rj_path($path,1073741824)===$pre&&$history['pin']['bytes']===$pre['size'],'journal_changed');
  rj_need($history['trailing_blank_lines']===0,'journal_trailing_blank');
  $s=$history['state'];rj_need(is_array($s)&&array_key_exists('pending',$s)&&$s['pending']===null,'journal_pending_invalid');
  $cycle=$s['nightly_cycle']??null;
  rj_need(is_array($cycle),'journal_cycle_invalid');rj_terminal($cycle);
  $sha=hash('sha256',rj_json($cycle));
  rj_need(rj_json($cycle)===rj_json($expectedCycle),'journal_cycle_changed');
  rj_need(($s['last_preflight_block']??null)===$cycle['preflight_block'],'journal_last_preflight_changed');
  rj_need(is_array($s['completed']??null),'journal_completed_invalid');
  return ['ok'=>true,'mode'=>'read_only_current_journal','counter_scope'=>'this_verifier_only','prefix_verified'=>true,'pin'=>$history['pin'],'pending'=>null,'cycle_state'=>$cycle['state'],'cycle_collector_run_id'=>$cycle['collector_run_id'],'cycle_sha256'=>$sha,'latest_cycle'=>$cycle,'preflight_write_attempts'=>0,'supplier_calls'=>0,'post_writes'=>0,'feed_writes'=>0,'site_writes'=>0,'completed_count'=>count($s['completed']),'writes'=>0];
 } finally {flock($f,LOCK_UN);fclose($f);}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
 try {rj_need(PHP_SAPI==='cli'&&count($argv)===6&&preg_match('/\A[1-9][0-9]{0,9}\z/D',$argv[2])===1,'arguments_invalid');rj_need($argv[2]==='606390308'&&$argv[3]==='d02f2206df26e6dd75b55347ef95d0cf5717be93227be7e3142c38daadb62041','trusted_prefix_pin_changed');rj_load($argv[1]);$expected=rj_expected($argv[4],$argv[5]);$out=rj_read(RJ_JOURNAL,(int)$argv[2],$argv[3],$expected);}
 catch(Throwable $e){$out=['ok'=>false,'error'=>preg_match('/\A[a-z0-9_]+\z/D',$e->getMessage())?$e->getMessage():'journal_verification_failed','writes'=>0];}
 echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";exit($out['ok']?0:2);
}
