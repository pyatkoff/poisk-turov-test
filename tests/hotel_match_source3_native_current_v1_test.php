<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_source3_native_current_v1.php';

$checks=0;
function s3t(bool $ok,string $name):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('source3_test:'.$name);}
function s3reject(callable $fn,string $name):void{try{$fn();}catch(RuntimeException){s3t(true,$name);return;}throw new RuntimeException('source3_test:'.$name);}

$fixture=dirname(__DIR__).'/scripts/diagnostics/fixtures/hotel_match_source3_native_current_v1.json';
s3t(hash_file('sha256',$fixture)===S3N_MANIFEST_SHA,'fixture_digest');
$manifest=s3n_read($fixture);$scope=s3n_manifest($manifest);
s3t(array_map('strval',array_keys($scope['rows']))===['163887','2000057636','2000073063'],'fixture_membership');
s3t($scope['target_ids']===[1124,21679,60766],'target_membership');
s3t($scope['request']['checkin_beg']==='20261008'&&$scope['request']['checkin_end']==='20261029','request_dates');

$fact=s3n_fact(['hotelKey'=>'163887','operatorKey'=>5,'isOperatorHotelKey'=>0,'original'=>['hotelKey'=>'8319','operatorKey'=>5]],'163887',5);
s3t($fact===['state'=>'exact_catalog_to_native','native_id'=>'8319'],'exact_fact');
s3t(s3n_fact(['hotelKey'=>'163887','operatorKey'=>342,'original'=>['hotelKey'=>'1','operatorKey'=>342]],'163887',5)['state']==='other_operator','other_operator');
s3t(s3n_fact(['hotelKey'=>'9','operatorKey'=>5,'original'=>['hotelKey'=>'1','operatorKey'=>5]],'163887',5)['state']==='other_catalog','other_catalog');
s3t(s3n_fact(['hotelKey'=>'163887','operatorKey'=>5,'isOperatorHotelKey'=>1,'original'=>['hotelKey'=>'8319','operatorKey'=>5]],'163887',5)['state']==='operator_key_row','operator_key_row');
s3t(s3n_fact(['hotelKey'=>'163887','operatorKey'=>5,'isOperatorHotelKey'=>0,'original'=>['hotelKey'=>'8319','operatorKey'=>342]],'163887',5)['state']==='catalog_only','original_operator_guard');
s3t(s3n_fact(['hotelKey'=>'163887','operatorKey'=>5,'isOperatorHotelKey'=>0,'original'=>[]],'163887',5)['state']==='catalog_only','missing_original_guard');

$sources=[];
foreach($scope['rows'] as $cat=>$row)$sources[$cat][]=array_merge($row,[
    'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$cat,'local_hotel_id'=>null,'decision_status'=>'pending',
    'evidence_json'=>json_encode(['source'=>['id'=>$cat]],JSON_THROW_ON_ERROR),
]);
$hotels=[1124=>['id'=>1124,'country_id'=>'4','is_active'=>1],21679=>['id'=>21679,'country_id'=>'4','is_active'=>1],60766=>['id'=>60766,'country_id'=>'4','is_active'=>1]];
$pre=s3n_preflight($scope,$sources,[],$hotels,[],[]);
s3t(count($pre)===3&&array_unique(array_column($pre,'state'))===['eligible_for_source_evidence'],'all_eligible');
s3t(array_column($pre,'catalog_id')===['163887','2000057636','2000073063'],'preflight_order');

$occupied=[60766=>[['external_hotel_id'=>'other-source']]];
$held=s3n_preflight($scope,$sources,$occupied,$hotels,[1124=>true],[21679=>true]);
$by=[];foreach($held as $row)$by[$row['catalog_id']]=$row;
s3t($by['163887']['holds']===['target_manual_or_exclusion'],'manual_hold_isolated');
s3t($by['2000057636']['holds']===['target_manual_or_exclusion'],'exclusion_hold_isolated');
s3t($by['2000073063']['holds']===['target_occupied'],'occupied_hold_isolated');
s3t(count(array_filter($held,fn($r)=>$r['state']==='eligible_for_source_evidence'))===0,'held_states');

$changed=$sources;$changed['163887'][0]['evidence_sha256']=str_repeat('a',64);
$changed['2000057636'][]=$changed['2000057636'][0];
$changed['2000073063'][0]['evidence_json']='{}';
$badHotels=$hotels;$badHotels[1124]['country_id']='1';$badHotels[21679]['is_active']=0;
$held=s3n_preflight($scope,$changed,[],$badHotels,[],[]);$by=[];foreach($held as $row)$by[$row['catalog_id']]=$row;
s3t(in_array('current_source_revision_differs',$by['163887']['holds'],true),'revision_hold');
s3t(in_array('target_country_changed',$by['163887']['holds'],true),'country_hold');
s3t(in_array('current_source_not_unique',$by['2000057636']['holds'],true),'duplicate_source_hold');
s3t(in_array('target_missing_or_inactive',$by['2000057636']['holds'],true),'inactive_hold');
s3t(in_array('current_source_history_review',$by['2000073063']['holds'],true),'history_hold');
s3t(count(array_filter($held,fn($r)=>$r['safe_to_write_now']!==false))===0,'never_write_ready');

$mut=$manifest;$mut['rows'][0]['catalog_id']='1';s3reject(fn()=>s3n_manifest($mut),'reject_catalog_mutation');
$mut=$manifest;$mut['rows'][1]['operator_id']=5;s3reject(fn()=>s3n_manifest($mut),'reject_operator_mutation');
$mut=$manifest;$mut['request']['page']=2;s3reject(fn()=>s3n_manifest($mut),'reject_page_mutation');
$mut=$manifest;$mut['rows'][2]['target_native_id_for_comparison']='1';s3reject(fn()=>s3n_manifest($mut),'reject_target_native_mutation');

$own=s3n_preflight($scope,$sources,[1124=>[['external_hotel_id'=>'163887']]],$hotels,[],[]);
s3t($own[0]['state']==='eligible_for_source_evidence'&&$own[0]['holds']===[],'same_source_owner_not_occupied');
$foreign=s3n_preflight($scope,$sources,[1124=>[['external_hotel_id'=>'163888']]],$hotels,[],[]);
s3t($foreign[0]['holds']===['target_occupied']&&$foreign[1]['state']==='eligible_for_source_evidence','foreign_owner_hold_isolated');

$base=['state'=>'not_returned_in_context','price_rows'=>1,'native_ids'=>['8319'=>true],
    'target_native_id_for_comparison'=>'8319','matches_target_native'=>false,'safe_to_write_now'=>false];
$final=s3n_finalize_evidence_row($base);
s3t($final['native_ids']===['8319']&&$final['state']==='captured_single_native'&&$final['matches_target_native']===true,'numeric_native_matches_string_target');
$final=s3n_finalize_evidence_row(array_merge($base,['native_ids'=>['24891'=>true]]));
s3t($final['state']==='captured_single_native'&&$final['matches_target_native']===false,'different_native_not_match');
$final=s3n_finalize_evidence_row(array_merge($base,['native_ids'=>['44562'=>true,'804'=>true],'target_native_id_for_comparison'=>'804']));
s3t($final['native_ids']===['804','44562']&&$final['state']==='captured_ambiguous_native'&&$final['matches_target_native']===false,'ambiguous_native_preserves_all_tokens');
$final=s3n_finalize_evidence_row(array_merge($base,['target_native_id_for_comparison'=>null]));
s3t($final['matches_target_native']===false&&$final['safe_to_write_now']===false,'unknown_target_stays_evidence_only');
$final=s3n_finalize_evidence_row(array_merge($base,['state'=>'preflight_hold','native_ids'=>[]]));
s3t($final['state']==='preflight_hold'&&$final['matches_target_native']===false,'preflight_hold_preserved');
$final=s3n_finalize_evidence_row(array_merge($base,['native_ids'=>[]]));
s3t($final['state']==='catalog_only'&&$final['matches_target_native']===false,'catalog_only_preserved');
$final=s3n_finalize_evidence_row(array_merge($base,['price_rows'=>0,'native_ids'=>[]]));
s3t($final['state']==='not_returned_in_context'&&$final['matches_target_native']===false,'absent_price_preserved');

echo 'hotel_match_source3_native_current_v1_test: PASS '.$checks."\n";
