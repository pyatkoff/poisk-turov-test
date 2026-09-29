<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_common4_fingerprint_join_v37.php';

function v37t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}

v37t(V37_FRONTIER_TOTAL===1737,'frontier_total');
v37t(V37_DIRECT===['operator_315'=>'operator_315','operator_342'=>'operator_342'],'direct_namespaces');
v37t(V37_SUPPORT===['operator_115'=>'bgoperator','operator_5'=>'anex'],'support_namespaces');

$tv=[];$samo=[];$shape=['operation_dirs_examined'=>0,'result_files_parsed'=>0,'result_files_skipped_size'=>0,'tv_edges_seen'=>0,'samo_edges_seen'=>0];
$sample=[
    'edges'=>[
        ['tv_hotel_id'=>44,'namespace'=>'operator_315','link_state'=>'captured_single_native','positive_native_candidates'=>[700]],
        ['catalog_id'=>'9000','namespace'=>'operator_315','state'=>'captured_single_native','positive_native_candidates'=>[700]],
        ['tv_hotel_id'=>44,'namespace'=>'bgoperator','link_state'=>'captured_single_native','positive_native_candidates'=>[810]],
        ['catalog_id'=>'9000','namespace'=>'operator_115','state'=>'captured_single_native','positive_native_candidates'=>[810]],
    ],
];
v37_walk($sample,'hotel-match-test-acquire',str_repeat('a',64),$tv,$samo,$shape);
v37t(isset($tv['44|operator_315|700']),'tv_direct_projection');
v37t(isset($samo['9000|operator_315|700']),'samo_direct_projection');
v37t(isset($tv['44|bgoperator|810'])&&isset($samo['9000|operator_115|810']),'support_projection');

$idx=v37_indexes(['tv_edges'=>$tv,'samo_edges'=>$samo],[44=>'tv_only_missing_both']);
$m=v37_match_one('9000','operator_315','operator_315',$idx);
v37t($m['state']==='exact_unique'&&$m['target']===44,'exact_direct_join');

$current=['active'=>[44=>['id'=>44]],'bySource'=>[],'byTarget'=>[],'manual'=>[]];
$r=v37_classify_source('9000',$idx,[44=>'tv_only_missing_both'],$current);
v37t($r['status']==='single_direct_plus_support','one_direct_not_strong');

$tv['44|operator_342|701']=['entity'=>'44','namespace'=>'operator_342','native_id'=>'701','evidence'=>[]];
$samo['9000|operator_342|701']=['entity'=>'9000','namespace'=>'operator_342','native_id'=>'701','evidence'=>[]];
$idx=v37_indexes(['tv_edges'=>$tv,'samo_edges'=>$samo],[44=>'tv_only_missing_both']);
$r=v37_classify_source('9000',$idx,[44=>'tv_only_missing_both'],$current);
v37t($r['status']==='strong_two_direct_current_missing','two_direct_strong');
v37t($r['support_operator_count']===1,'support_not_counted_direct');

$current['byTarget'][44]=[['external_hotel_id'=>'other','local_hotel_id'=>44,'decision_status'=>'accepted']];
$r=v37_classify_source('9000',$idx,[44=>'tv_only_missing_both'],$current);
v37t($r['status']==='hold_target_samo_occupied','current_target_guard');

echo "MATCH_TV_SAMO_COMMON4_FINGERPRINT_JOIN_V37_TEST_OK\n";
