<?php
declare(strict_types=1);
/** MATCH #1971 Turkey-B wrapper over pinned Egypt v2 acquisition runtime; only uncaptured Turkey-A locals. */
$base=__DIR__.'/hotel_match_tv_multiop_targeted_v2.php';
$src=file_get_contents($base);if($src===false)throw new RuntimeException('v2_source_missing');
$oldOp='hotel-match-tv-multiop-targeted-1971-20260916-v2-egypt';
$newOp='hotel-match-tv-multiop-targeted-1971-20260916-v1-turkey-b';
if(substr_count($src,$oldOp)!==1)throw new RuntimeException('v2_operation_marker_changed');$src=str_replace($oldOp,$newOp,$src);
if(substr_count($src,'2026-11-15')<2)throw new RuntimeException('v2_date_marker_changed');$src=str_replace('2026-11-15','2026-11-19',$src);
$oldTargets='const HMM2_TARGETS=[19226=>14140,37048=>97122,1361=>125,28396=>293,36906=>21636,780=>464,1771=>190,1655=>157782,30600=>129,37012=>83106,44562=>159,1772=>191,28563=>73342,30599=>159159,4131=>488,1328=>128,990=>162145,1769=>186,43850=>151784,45166=>148539,45249=>127294,45259=>143442,45330=>130621,29557=>73343];';
$newTargets='const HMM2_TARGETS=[23894=>1600,45225=>153743,44573=>59085,43984=>99257,16605=>115500,32745=>85422,37885=>111046,30424=>43550,45226=>163543,32742=>132803,29036=>132803,34804=>81578,39389=>1557,43077=>1558,44139=>67042,8550=>1283,11241=>1255,12143=>1004,12901=>1474,15046=>1217,21696=>1070,29332=>60388];';
if(substr_count($src,$oldTargets)!==1)throw new RuntimeException('v2_targets_marker_changed');$src=str_replace($oldTargets,$newTargets,$src);
$oldPoll="function h2_poll(int\$sid):array{sleep(8);\$s=h2_call('search_status','/tours/search/'.\$sid.'/status',['operatorStatus'=>false]);if(!h2_complete(\$s)){sleep(12);\$s=h2_call('search_status','/tours/search/'.\$sid.'/status',['operatorStatus'=>false]);}return\$s;}";
$newPoll="function h2_poll(int\$sid):array{\$s=[];foreach([8,12,15,15,15] as \$wait){sleep(\$wait);\$s=h2_call('search_status','/tours/search/'.\$sid.'/status',['operatorStatus'=>false]);if(h2_complete(\$s))return\$s;}return\$s;}";
if(substr_count($src,$oldPoll)!==1)throw new RuntimeException('v2_poll_marker_changed');$src=str_replace($oldPoll,$newPoll,$src);
$oldFocus='$focus=[];$sourceByLocal=[];foreach(HMM2_TARGETS as$aid=>$local){if(isset($mapped[$aid])||isset($manual[$aid])||isset($ex[$aid][$local])||!isset($active[$local]))continue;$focus[$aid]=$local;$sourceByLocal[$local]=$aid;}';
$newFocus='$focus=[];$sourceByLocal=[];foreach(HMM2_TARGETS as$aid=>$local){if(isset($mapped[$aid])||isset($manual[$aid])||isset($ex[$aid][$local])||!isset($active[$local]))continue;$focus[$local]=$local;$sourceByLocal[$local][]=$aid;}';
if(substr_count($src,$oldFocus)!==1)throw new RuntimeException('v2_focus_marker_changed');$src=str_replace($oldFocus,$newFocus,$src);
if(substr_count($src,'country_id=1 AND is_active=1')!==1)throw new RuntimeException('v2_country_sql_marker_changed');$src=str_replace('country_id=1 AND is_active=1','country_id=4 AND is_active=1',$src);
if(substr_count($src,"h2_find(\$countries,['егип','egypt'])")!==1)throw new RuntimeException('v2_country_find_marker_changed');$src=str_replace("h2_find(\$countries,['егип','egypt'])","h2_find(\$countries,['турц','turkey'])",$src);
$src=str_replace('egypt_not_found','turkey_not_found',$src);
if(substr_count($src,"'country'=>'Egypt'")!==1)throw new RuntimeException('v2_country_contract_marker_changed');$src=str_replace("'country'=>'Egypt'","'country'=>'Turkey'",$src);
$oldCheckpoint="\$search=h2_call('search_create','/tours/search',\$params);\$sid=h2_search_id(\$search);if(\$sid===null)throw new RuntimeException('search_id_not_found');\$status=h2_poll(\$sid);";
$newCheckpoint="\$search=h2_call('search_create','/tours/search',\$params);\$sid=h2_search_id(\$search);if(\$sid===null)throw new RuntimeException('search_id_not_found');h2_write(\$dir.'/search-checkpoint.json',['operation_id'=>\$op,'source_sha'=>\$sha,'state'=>'search_created_before_poll','search_id'=>\$sid,'date'=>HMM2_DATE,'current_input_count'=>count(\$focus),'source_identity_count'=>array_sum(array_map('count',\$sourceByLocal)),'tourvisor_calls_at_checkpoint'=>\$calls,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'no_replay'=>true]);\$status=h2_poll(\$sid);";
if(substr_count($src,$oldCheckpoint)!==1)throw new RuntimeException('v2_checkpoint_marker_changed');$src=str_replace($oldCheckpoint,$newCheckpoint,$src);
if(substr_count($src,"'source_anex_id'=>\$sourceByLocal[\$tv]??null")!==1)throw new RuntimeException('v2_source_output_marker_changed');$src=str_replace("'source_anex_id'=>\$sourceByLocal[\$tv]??null","'source_anex_ids'=>\$sourceByLocal[\$tv]??[]",$src);
$oldPairs="'current_focus_pairs'=>array_map(static fn(\$aid,\$local)=>['anex_hotel_id'=>\$aid,'local_hotel_id'=>\$local],array_keys(\$focus),array_values(\$focus))";
$newPairs="'current_focus_pairs'=>array_map(static fn(\$local,\$aids)=>['local_hotel_id'=>\$local,'anex_hotel_ids'=>\$aids],array_keys(\$sourceByLocal),array_values(\$sourceByLocal))";
if(substr_count($src,$oldPairs)!==1)throw new RuntimeException('v2_focus_output_marker_changed');$src=str_replace($oldPairs,$newPairs,$src);
if(str_contains($src,$oldOp)||str_contains($src,'2026-11-15')||str_contains($src,"['егип','egypt']")||substr_count($src,'search_created_before_poll')!==1||substr_count($src,'foreach([8,12,15,15,15] as $wait)')!==1)throw new RuntimeException('turkey_b_patch_incomplete');
if(in_array('--emit',$argv??[],true)){echo $src;exit(0);}
$tmp=sys_get_temp_dir().'/hotel-match-tv-multiop-turkey-b-'.getmypid().'.php';if(file_put_contents($tmp,$src,LOCK_EX)!==strlen($src))throw new RuntimeException('temp_write_failed');try{require $tmp;}finally{@unlink($tmp);}