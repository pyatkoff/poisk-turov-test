<?php
declare(strict_types=1);
/**
 * MATCH #1971 v3: byte-pinned correction over v2.
 * Only changes immutable operation/date, bounded readiness polling, and durable searchId checkpoint.
 */
$base=__DIR__.'/hotel_match_tv_multiop_targeted_v2.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v2_source_missing');
$oldOp='hotel-match-tv-multiop-targeted-1971-20260916-v2-egypt';
$newOp='hotel-match-tv-multiop-targeted-1971-20260916-v3-egypt';
if(substr_count($src,$oldOp)<2)throw new RuntimeException('v2_operation_marker_changed');
$src=str_replace($oldOp,$newOp,$src);
if(substr_count($src,'2026-11-15')<2)throw new RuntimeException('v2_date_marker_changed');
$src=str_replace('2026-11-15','2026-11-16',$src);
$oldPoll="function h2_poll(int\$sid):array{sleep(8);\$s=h2_call('search_status','/tours/search/'.\$sid.'/status',['operatorStatus'=>false]);if(!h2_complete(\$s)){sleep(12);\$s=h2_call('search_status','/tours/search/'.\$sid.'/status',['operatorStatus'=>false]);}return\$s;}";
$newPoll="function h2_poll(int\$sid):array{\$s=[];foreach([8,12,15,15,15] as \$wait){sleep(\$wait);\$s=h2_call('search_status','/tours/search/'.\$sid.'/status',['operatorStatus'=>false]);if(h2_complete(\$s))return\$s;}return\$s;}";
if(substr_count($src,$oldPoll)!==1)throw new RuntimeException('v2_poll_marker_changed');
$src=str_replace($oldPoll,$newPoll,$src);
$oldCheckpoint="\$search=h2_call('search_create','/tours/search',\$params);\$sid=h2_search_id(\$search);if(\$sid===null)throw new RuntimeException('search_id_not_found');\$status=h2_poll(\$sid);";
$newCheckpoint="\$search=h2_call('search_create','/tours/search',\$params);\$sid=h2_search_id(\$search);if(\$sid===null)throw new RuntimeException('search_id_not_found');h2_write(\$dir.'/search-checkpoint.json',['operation_id'=>\$op,'source_sha'=>\$sha,'state'=>'search_created_before_poll','search_id'=>\$sid,'date'=>HMM2_DATE,'current_input_count'=>count(\$focus),'tourvisor_calls_at_checkpoint'=>\$calls,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'no_replay'=>true]);\$status=h2_poll(\$sid);";
if(substr_count($src,$oldCheckpoint)!==1)throw new RuntimeException('v2_checkpoint_marker_changed');
$src=str_replace($oldCheckpoint,$newCheckpoint,$src);
if(str_contains($src,$oldOp)||str_contains($src,'2026-11-15')||substr_count($src,"search_created_before_poll")!==1)throw new RuntimeException('v3_patch_incomplete');
$tmp=sys_get_temp_dir().'/hotel-match-tv-multiop-targeted-v3-'.getmypid().'.php';
if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('v3_temp_write_failed');
try{require $tmp;}finally{@unlink($tmp);}
