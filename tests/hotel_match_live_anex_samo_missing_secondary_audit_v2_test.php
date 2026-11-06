<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_anex_samo_missing_secondary_audit_v2.php';
function v2t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v2t(HMAMS_OP==='hotel-match-live-anex-samo-missing-secondary-audit-1971-20260926-v2','op');
v2t(hmams_operator_namespace(18)==='bgoperator','bg');
v2t(hmams_operator_namespace(25)==='operator_315','fs');
v2t(hmams_operator_namespace(43)==='operator_342','int');
$bg=hmams_parse_link(['operator_id'=>18,'operator_link'=>'https://www.bgoperator.ru/x?F4=12345','operator_link_host'=>'www.bgoperator.ru','operator_link_query'=>'F4=12345']);
v2t($bg['state']==='single_native_candidate'&&array_map('strval',$bg['candidates'])===['12345'],'bg_link');
$fs=hmams_parse_link(['operator_id'=>25,'operator_link'=>'https://example.test/x?hotelCode=4567','operator_link_host'=>'example.test','operator_link_query'=>'hotelCode=4567']);
v2t($fs['state']==='single_native_candidate'&&array_map('strval',$fs['candidates'])===['4567'],'fs_link');
$secret=hmams_parse_link(['operator_id'=>43,'operator_link'=>'https://example.test/x?session=abc&hotelId=4567','operator_link_host'=>'example.test','operator_link_query'=>'session=abc&hotelId=4567']);
v2t($secret['state']==='secret_bearing_link','secret');
echo "MATCH_LIVE_ANEX_SAMO_MISSING_SECONDARY_AUDIT_V2_TEST_OK\n";
