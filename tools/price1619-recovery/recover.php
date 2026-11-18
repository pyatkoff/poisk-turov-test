<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/pure.php';require __DIR__.'/safe-result.php';require __DIR__.'/editor-pure-proof.php';
const RECOVERY_ENABLED=true;
const R4294_ROOT='/var/www/anytoour/data/www/app.anytoour.ru';
const R4294_PRIVATE='/var/www/anytoour/data/channel-post-operations';
function r4294_json(string $path,int $limit=4000000):array {
 r4294_need(!is_link($path)&&is_file($path)&&filesize($path)<=$limit,'input_file_invalid');
 return json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
}
function r4294_pin($f,int $limit):array {
 r4294_need(rewind($f),'journal_rewind_failed');$h=hash_init('sha256');$bytes=0;$lines=0;$last=null;
 while(($line=fgets($f,4000000))!==false){$bytes+=strlen($line);r4294_need($bytes<=$limit&&str_ends_with($line,"\n"),'journal_bound_or_partial');hash_update($h,$line);$lines++;$last=$line;}
 r4294_need(feof($f)&&$last!==null,'journal_read_failed');return ['sha256'=>hash_final($h),'bytes'=>$bytes,'lines'=>$lines];
}
function r4294_lock(string $path,int $mode){
 r4294_need(!is_link($path)&&realpath($path)===$path,'journal_path_changed');$f=fopen($path,'r+b');r4294_need(is_resource($f)&&flock($f,$mode|LOCK_NB),'journal_busy');
 $s=fstat($f);$n=lstat($path);r4294_need($s['dev']===$n['dev']&&$s['ino']===$n['ino'],'journal_identity_changed');return $f;
}
function r4294_prefix($f,array $pin):void {
 rewind($f);$h=hash_init('sha256');$left=$pin['bytes'];while($left>0){$s=fread($f,min($left,1048576));r4294_need(is_string($s)&&$s!=='','prefix_read_failed');hash_update($h,$s);$left-=strlen($s);}r4294_need(hash_final($h)===$pin['sha256'],'original_prefix_changed');
}
function r4294_feeds(array $pins):array {
 $out=[];foreach(['telegram','max','all'] as $key){$name='hotel-posts-'.$key.'.xml';$path=R4294_ROOT.'/feed/'.$name;
  $raw=hook_read($path,4000000);$public=hook_get('https://app.anytoour.ru/feed/'.$name);
  r4294_need(hash('sha256',$raw)===$pins[$key]&&hash('sha256',$public)===$pins[$key],'feed_preimage_changed');$out[$key]=$raw;
 }return $out;
}
$out=['ok'=>false,'operation'=>R4294_OPERATION,'phase'=>'closed','provider_calls'=>0,'provider_writes'=>0,'database_confirmation_attempted'=>false,'child_append_attempted'=>false,'feed_finish_attempted'=>false];$global=null;$child=null;
try {
 r4294_need(RECOVERY_ENABLED&&count($argv)===6,'recovery_closed');
 [$self,$configPath,$evidencePath,$bundlePath,$admissionPath,$configSha]=$argv;
 r4294_need(hash_file('sha256',$configPath)===$configSha,'config_hash_changed');$c=r4294_json($configPath);
 r4294_need(($c['enabled']??null)===true&&$c['operation']===R4294_OPERATION&&preg_match('/\A[a-f0-9]{40}\z/D',$c['runtime_sha'])===1,'config_closed');
 foreach(['staged_bundle_sha256','admission_sha256','evidence_sha256','canonical_hook_sha256','cohort_sha256','collector_sha256'] as $key)r4294_need(preg_match('/\A[a-f0-9]{64}\z/D',$c[$key]??'')===1,'config_unbound');
 r4294_need($c['evidence_sha256']==='91dd4ec54aa101685efb8cee61eaf9b8d3c7cf5a07f225af52baeda523055666'&&hash_file('sha256',$evidencePath)===$c['evidence_sha256'],'original_evidence_changed');
 $e=r4294_json($evidencePath);r4294_need($e['operation']===R4294_OPERATION,'operation_changed');
 r4294_need(hash_file('sha256',$admissionPath)===$c['admission_sha256'],'admission_changed');$admission=r4294_json($admissionPath);r4294_admission($admission,$c);
 r4294_load_definitions($bundlePath,$c['staged_bundle_sha256']);
 r4294_need(realpath(R4294_ROOT)===R4294_ROOT&&chdir(R4294_ROOT)&&hook_runtime()===$c['runtime_sha'],'runtime_changed');
 $consumer='/var/www/anytoour/data/www/anytoour.ru/data/';
 foreach(['monitor-price-hook.php'=>'canonical_hook_sha256','published-hotels-monitor-v1.php'=>'cohort_sha256','top500-daily-collector-v1.php'=>'collector_sha256'] as $name=>$key)r4294_need(hash_file('sha256',$consumer.$name)===$c[$key],'consumer_code_changed');
 $globalPath=R4294_PRIVATE.'/monitor-price-after-collection-v1.jsonl';$childPath=R4294_PRIVATE.'/'.R4294_OPERATION.'/receipt.jsonl';
 $global=r4294_lock($globalPath,LOCK_EX);$child=r4294_lock($childPath,LOCK_EX);$globalIdentity=fstat($global);$childIdentity=fstat($child);$out['phase']='locked';
 r4294_need(r4294_pin_equal(r4294_pin($global,700000000),$c['global_pin'])&&r4294_pin_equal(r4294_pin($child,2000000),$c['child_pin']),'original_journal_changed');
 $history=monitor_hook_read_history($global);r4294_need(r4294_pin_equal($history['pin'],$c['global_pin'])&&$history['trailing_blank_lines']===0,'global_history_changed');$state=$history['state'];$before=$state;r4294_global_before($state,$e);
 rewind($child);$raw=stream_get_contents($child);$original=monitor_hook_parse_journal($raw,R4294_OPERATION);r4294_need($original===$e['child']['last'],'original_child_changed');
 ob_start();try{require R4294_ROOT.'/config.php';require_once R4294_ROOT.'/services/ChannelPostReconciliationService.php';require_once R4294_ROOT.'/services/ChannelPostEntryService.php';}finally{ob_end_clean();}
 $db=ConversationDb::connection();$registry=new ChannelPostRegistryService($db);$publications=[];
 foreach($e['publications'] as $entry){$p=$entry['publication'];$current=$registry->publication($p['publication_id']);r4294_need($current===$p,'publication_changed');$publications[$p['platform']]=$current;}
 r4294_need($publications['max']['state']==='sent'&&$publications['max']['version_id']===9091,'max_changed');
 $key='telegram:commercial-hotel-4294-v1';$entry=$original['posts'][$key];$tg=$publications['telegram'];
 $evidence=['operation'=>R4294_OPERATION,'original_receipt_sha256'=>$c['child_pin']['sha256'],'provider_response'=>$entry['provider_response'],'plan'=>$entry['plan']];
 $identity=['project_key'=>$tg['project_key'],'platform'=>'telegram','bot_key'=>$tg['bot_key']];
 $service=new ChannelPostReconciliationService($db,static function(){throw new RuntimeException('provider_call_forbidden');});
 $preview=$service->confirmTelegramOriginalEdit($identity,1815,9092,$tg['channel']['config_version'],$evidence);
 r4294_need(($preview['ok']??null)===true&&$preview['state']==='preview','api_preview_rejected');
 $projectedTg=$tg;$projectedTg['state']='sent';$projected=r4294_project($original,$projectedTg,time());
 $feedBefore=r4294_feeds($c['feed_shas']);
 // Precompute all three exact price-only feed projections, still before DB.
 foreach($feedBefore as $name=>$xml)monitor_price_render_feed($xml,[monitor_price_confirmed_journal($projected['complete'],$projected['binding'],time())],'hotel-posts-'.$name.'.xml',time());
 r4294_admission($admission,$c);r4294_need(hook_runtime()===$c['runtime_sha'],'runtime_changed');
 $projected=r4294_project($original,$projectedTg,time()); // expiry immediately before intent/DB
 $marker=R4294_PRIVATE.'/'.R4294_OPERATION.'/recovery1619-intent.json';$intent=fopen($marker,'x');r4294_need(is_resource($intent),'recovery_already_attempted');
 $intentBody=json_encode(['operation'=>R4294_OPERATION,'evidence_sha256'=>$c['evidence_sha256'],'confirmation_digest'=>$preview['confirmation_digest'],'at'=>gmdate('c')],JSON_THROW_ON_ERROR)."\n";
 r4294_need(fwrite($intent,$intentBody)===strlen($intentBody)&&fflush($intent)&&fsync($intent),'intent_write_unknown');fclose($intent);
 $out['phase']='database_confirmation';$out['database_confirmation_attempted']=true;
 $confirmed=$service->confirmTelegramOriginalEdit($identity,1815,9092,$tg['channel']['config_version'],$evidence,$preview['confirmation_digest'],true);
 r4294_need(($confirmed['ok']??null)===true&&$confirmed['state']==='sent','database_confirmation_unknown_stop');
 $actual=$registry->publication(1815);r4294_need($actual['state']==='sent'&&$actual['version_id']===9092&&$actual['snapshot']===$tg['snapshot']&&$actual['start_payload']===$tg['start_payload'],'database_readback_changed');
 r4294_need($registry->publication(1825)===$publications['max'],'max_changed');
 $resolved=ChannelPostEntryService::resolve('telegram',$actual['start_payload'],$registry);
 r4294_need(is_array($resolved)&&($resolved['version_id']??null)===9092&&($resolved['snapshot']['text']??null)===$actual['snapshot']['text']&&($resolved['source_key']??null)===$actual['source_key'],'token_current_version_not_resolved');
 $actualProjected=r4294_project($original,$actual,time());r4294_need($actualProjected===$projected,'receipt_projection_changed');
 $record=fopen(R4294_PRIVATE.'/'.R4294_OPERATION.'/recovery1619-confirmed.json','x');r4294_need(is_resource($record),'confirmation_receipt_exists');
 $recordBody=json_encode(['api_result'=>$confirmed,'registry_receipt'=>$actual['receipt'],'at'=>gmdate('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
 r4294_need(fwrite($record,$recordBody)===strlen($recordBody)&&fflush($record)&&fsync($record),'confirmation_receipt_write_unknown');fclose($record);
 $out['confirmation']=$confirmed;$out['phase']='child_confirmation_append';$out['child_append_attempted']=true;
 hook_append($child,$projected['confirmed']);hook_append($child,$projected['complete']);
 r4294_prefix($child,$c['child_pin']);rewind($child);$childRaw=stream_get_contents($child);r4294_need(monitor_hook_parse_journal($childRaw,R4294_OPERATION)===$projected['complete'],'child_readback_failed');
 $out['child_pin']=r4294_pin($child,2000000);$out['receipt']=$projected['receipt'];
 r4294_need(flock($child,LOCK_SH|LOCK_NB),'child_lock_downgrade_failed');
 // flock conversion may release EX momentarily. Rebind the exact completed file
 // under SH before any feed writer can consume it.
 r4294_named_identity($child,$childPath,$childIdentity);
 r4294_need(r4294_pin($child,2000000)===$out['child_pin'],'child_changed_during_lock_conversion');
 rewind($child);r4294_need(monitor_hook_parse_journal(stream_get_contents($child),R4294_OPERATION)===$projected['complete'],'child_terminal_changed_during_lock_conversion');
 r4294_need($registry->publication(1825)===$publications['max']&&$registry->publication(1815)===$actual&&hook_runtime()===$c['runtime_sha'],'pre_feed_state_changed');
 r4294_feeds($c['feed_shas']);r4294_admission($admission,$c);r4294_named_identity($child,$childPath,$childIdentity);r4294_named_identity($global,$globalPath,$globalIdentity);$out['phase']='pending_feed_finish';$out['feed_finish_attempted']=true;
 r4294_need(hook_finish_feed($state,$global,$c['runtime_sha'],$out)==='feed_completed','pending_feed_not_completed');
 r4294_global_after($before,$state);r4294_prefix($global,$c['global_pin']);$afterHistory=monitor_hook_read_history($global);r4294_need($afterHistory['state']===$state,'global_readback_changed');
 r4294_named_identity($global,$globalPath,$globalIdentity);
 $out['global_pin']=$afterHistory['pin'];$out['pending']=null;$out['feed_shas']=[];
 foreach(['telegram','max','all'] as $name){$raw=hook_read(R4294_ROOT.'/feed/hotel-posts-'.$name.'.xml',4000000);$public=hook_get('https://app.anytoour.ru/feed/hotel-posts-'.$name.'.xml');r4294_need(hash('sha256',$raw)===hash('sha256',$public),'final_public_feed_changed');r4294_feed_unchanged_except_price($feedBefore[$name],$raw,$projected,'hotel-posts-'.$name.'.xml',time());$out['feed_shas'][$name]=hash('sha256',$raw);}
 r4294_need($registry->publication(1825)===$publications['max']&&$registry->publication(1815)===$actual,'final_registry_changed');
 $out['ok']=true;$out['phase']='completed';$out['new_content_edits']=0;$out['canonical_hook_installed']=false;
} catch(Throwable $ex){$out['error']=preg_match('/\A[a-z][a-z0-9_]{1,100}\z/D',$ex->getMessage())?$ex->getMessage():'recovery_failure';$out['state']=$out['database_confirmation_attempted']||$out['child_append_attempted']||$out['feed_finish_attempted']?'unknown_requires_original_readback':'stopped_before_database_write';}
finally{foreach([$child,$global] as $f)if(is_resource($f)){flock($f,LOCK_UN);fclose($f);}}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";exit($out['ok']?0:2);
