<?php
declare(strict_types=1);
const R4294_OPERATION='commercial-monitor-price-h4294-f72863a3310b60862a907f040008fd46c82e7e044485488e9eece8adb1237e24';
function r4294_need(bool $ok,string $reason):void {if(!$ok)throw new RuntimeException($reason);}
// JSON object order is not semantic. Only this exact pin schema is normalized.
function r4294_canonical_pin($pin):array {
 r4294_need(is_array($pin),'journal_pin_shape');$keys=array_keys($pin);sort($keys,SORT_STRING);
 r4294_need($keys===['bytes','lines','sha256']&&is_string($pin['sha256'])
  &&preg_match('/\A[a-f0-9]{64}\z/D',$pin['sha256'])===1
  &&is_int($pin['bytes'])&&$pin['bytes']>=0&&is_int($pin['lines'])&&$pin['lines']>=0,'journal_pin_shape');
 return ['sha256'=>$pin['sha256'],'bytes'=>$pin['bytes'],'lines'=>$pin['lines']];
}
function r4294_pin_equal($left,$right):bool {return r4294_canonical_pin($left)===r4294_canonical_pin($right);}
function r4294_load_definitions(string $bundle,string $sha):void {
 r4294_need(preg_match('/\A[a-f0-9]{64}\z/D',$sha)===1&&hash_file('sha256',$bundle)===$sha,'staged_bundle_changed');
 $source=file_get_contents($bundle);$marker="\n\$inspect=getenv('ANYTOUR_MONITOR_HOOK_INSPECT')";
 r4294_need(substr_count($source,$marker)===1&&str_starts_with($source,"<?php\ndeclare(strict_types=1);"),'definitions_boundary_invalid');
 $prefix=explode($marker,$source,2)[0];eval(substr($prefix,5));
}
function r4294_project(array $child,array $tg,int $now):array {
 r4294_need(($child['operation']??null)===R4294_OPERATION&&!isset($child['complete'])&&count($child['posts']??[])===2,'child_initial_state_changed');
 r4294_need($tg['publication_id']===1815&&$tg['version_id']===9092&&$tg['platform']==='telegram'&&$tg['state']==='sent','tg_target_not_confirmed');
 $key='telegram:commercial-hotel-4294-v1';$max='max:commercial-hotel-4294-v1';
 r4294_need(($child['posts'][$max]['state']??null)==='confirmed'&&($child['posts'][$max]['receipt']['version_id']??null)===9091
  &&($child['posts'][$key]['state']??null)==='provider_response_recorded'&&!isset($child['posts'][$key]['receipt']),'child_pair_shape_changed');
 $plan=$child['posts'][$key]['plan'];$response=$child['posts'][$key]['provider_response'];
 r4294_need(($response['http']??null)===200&&($response['data']['ok']??null)===true&&!empty($response['data']['result']),'original_response_not_successful');
 $message=$response['data']['result'];$snapshot=ChannelPostIncomingAdapter::telegramSnapshot($message);
 r4294_need(is_array($snapshot)&&snapshotMatches($tg,$snapshot,$plan)&&$tg['snapshot']['raw_body']===$message
  &&$tg['start_payload']===$plan['token'],'original_response_content_changed');
 $computed=monitorEditorPriceLink($plan['text'],'telegram',ChannelPostButtonTransport::buttonUrl($tg));
 r4294_need($computed===$plan['price_link']&&monitorEditorPriceLinkVerified('telegram',$message,$plan['text'],$computed),'original_price_link_not_verified');
 $receipt=safeResult($tg,$plan);$receipt['price_link_verified']=true;$receipt['price_link_url']=$plan['price_link']['url'];
 $confirmed=$child;$confirmed['posts'][$key]['state']='confirmed';$confirmed['posts'][$key]['receipt']=$receipt;
 $complete=$confirmed;$complete['complete']=true;
 $binding=$complete['feed_binding'];$binding['operation']=$complete['operation'];$binding['manifest_hash']=$complete['manifest_hash'];
 // Exact canonical expiry/price/text/receipt validation BEFORE any database write.
 monitor_price_confirmed_journal($complete,$binding,$now);
 r4294_need($complete['posts'][$max]===$child['posts'][$max],'max_receipt_changed');
 return ['confirmed'=>$confirmed,'complete'=>$complete,'binding'=>$binding,'receipt'=>$receipt];
}
function r4294_global_before(array $state,array $evidence):void {
 r4294_need(($state['schema']??null)===1&&is_array($state['completed']??null)
  &&($state['pending']??null)===$evidence['global']['last']['pending']
  &&($state['pending']['operation']??null)===R4294_OPERATION&&($state['pending']['phase']??null)==='post_started'
  &&($state['nightly_cycle']??null)===$evidence['global']['last']['nightly_cycle']
  &&($state['holds']??null)===$evidence['global']['last']['holds'],'global_state_changed');
}
function r4294_global_after(array $before,array $after):void {
 r4294_need(array_key_exists('pending',$after)&&$after['pending']===null&&($after['completed'][4294]['operation']??null)===R4294_OPERATION,'pending_not_closed');
 unset($before['pending'],$after['pending'],$before['completed'][4294],$after['completed'][4294]);
 r4294_need($before===$after,'unrelated_global_history_changed');
}
function r4294_feed_unchanged_except_price(string $before,string $after,array $project,string $name,int $now):void {
 $update=monitor_price_confirmed_journal($project['complete'],$project['binding'],$now);
 $expected=monitor_price_render_feed($before,[$update],$name,$now);
 $normalize=static function(string $xml):string {
  $result=preg_replace('/<yml_catalog date="[^"<>]+">/','<yml_catalog date="CHECKED">',$xml,1,$count);
  r4294_need($count===1&&is_string($result),'feed_date_invalid');return $result;
 };
 r4294_need($normalize($expected)===$normalize($after),'feed_non_target_or_price_changed');
}

function r4294_named_identity($f,string $path,array $original):void {
 clearstatcache(true,$path);$named=lstat($path);$opened=fstat($f);
 r4294_need(!is_link($path)&&is_array($named)&&is_array($opened)&&realpath($path)===$path,'journal_named_identity_changed');
 foreach(['dev','ino'] as $key)r4294_need($opened[$key]===$original[$key]&&$named[$key]===$original[$key],'journal_named_identity_changed');
}

function r4294_admission(array $admission,array $config):void {
 // These fields are sealed by the exact admission file hash verified by recover.php.
 // Carry the original authenticated route time; never substitute admission/producer time.
 $routeAt=$admission['route_observed_at_epoch']??null;
 r4294_need(is_int($routeAt)&&$routeAt<=time()&&time()-$routeAt<=300
  &&preg_match('/\A[a-f0-9]{64}\z/D',$admission['route_attestation_sha256']??'')===1,'route_proof_stale_or_unbound');
 r4294_need(is_array($config['price_window_claims']??null)&&count($config['price_window_claims'])>=1,'price_window_claim_unbound');
 foreach($config['price_window_claims'] as $claim)r4294_need(is_array($claim)&&is_int($claim['claim_id']??null)&&$claim['claim_id']>0
  &&is_int($claim['ack_id']??null)&&$claim['ack_id']>0&&preg_match('/\A[a-f0-9]{64}\z/D',$claim['claim_body_sha256']??'')===1
  &&preg_match('/\A[a-f0-9]{64}\z/D',$claim['ack_body_sha256']??'')===1,'price_window_claim_unbound');
 $release=$config['technical_release_proof']??[];$producer=$config['producer_proof']??[];
 r4294_need(is_array($release)&&is_array($producer)&&($release['application_sha']??null)===($config['runtime_sha']??null)
  &&preg_match('/\A[a-f0-9]{64}\z/D',$release['receipt_sha256']??'')===1&&is_int($release['deploy_run_id']??null)&&$release['deploy_run_id']>0
  &&is_int($producer['run_id']??null)&&$producer['run_id']>0&&is_int($producer['observed_at_epoch']??null)
  &&$producer['observed_at_epoch']<=time()&&time()-$producer['observed_at_epoch']<=300
  &&preg_match('/\A[a-f0-9]{64}\z/D',$producer['receipt_sha256']??'')===1,'technical_release_or_producer_unbound');
 r4294_need(($admission['operation']??null)===R4294_OPERATION&&($admission['runtime_sha']??null)===$config['runtime_sha']
  &&($admission['staged_bundle_sha256']??null)===$config['staged_bundle_sha256']
  &&r4294_pin_equal($admission['global_pin']??null,$config['global_pin'])&&r4294_pin_equal($admission['child_pin']??null,$config['child_pin'])
  &&($admission['feed_shas']??null)===$config['feed_shas']&&($admission['no_active_collectors']??null)===true
  &&($admission['route_idle']??null)===true&&($admission['price_window_owned']??null)===true
  &&is_array($config['price_window_claims']??null)&&count($config['price_window_claims'])>=1
  &&($admission['price_window_claims']??null)===$config['price_window_claims']
  &&is_array($config['technical_release_proof']??null)&&($admission['technical_release_proof']??null)===$config['technical_release_proof']
  &&($admission['technical_release_accepted']??null)===true&&($admission['technical_publisher_free']??null)===true
  &&is_array($config['producer_proof']??null)&&($admission['producer_proof']??null)===$config['producer_proof']
  &&is_int($admission['observed_at_epoch']??null)&&$admission['observed_at_epoch']<=time()
  &&time()-$admission['observed_at_epoch']<=300,'fresh_admission_invalid');
}
