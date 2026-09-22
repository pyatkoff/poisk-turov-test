<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live_anex_samo_missing_secondary_audit_v1.php';
function t_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
t_need(hmams_operator_namespace(18)==='bgoperator','bg');
t_need(hmams_operator_namespace(25)==='operator_315','fs');
t_need(hmams_operator_namespace(43)==='operator_342','intourist');
$x=hmams_parse_link(['operator_id'=>18,'operator_link'=>'https://bgoperator.ru/t?F4=987','operator_link_host'=>'bgoperator.ru','operator_link_query'=>'F4=987']);
t_need($x['state']==='single_native_candidate'&&$x['candidates']===['987'],'f4');
$x=hmams_parse_link(['operator_id'=>25,'operator_link'=>'https://operator.test/t?hotel_id=654','operator_link_host'=>'operator.test','operator_link_query'=>'hotel_id=654']);
t_need($x['state']==='single_native_candidate'&&$x['candidates']===['654'],'hotel');
$x=hmams_parse_link(['operator_id'=>43,'operator_link'=>'https://operator.test/t?hotelId=1&hotelCode=2','operator_link_host'=>'operator.test','operator_link_query'=>'hotelId=1&hotelCode=2']);
t_need($x['state']==='ambiguous_native_candidates','ambiguous');
$x=hmams_parse_link(['operator_id'=>43,'operator_link'=>'https://operator.test/t?page=12','operator_link_host'=>'operator.test','operator_link_query'=>'page=12']);
t_need($x['state']==='numeric_nonhotel_only','nonhotel');
echo "MATCH_LIVE_ANEX_SAMO_MISSING_SECONDARY_AUDIT_V1_TEST_OK\n";
