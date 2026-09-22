<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_anchor_anex_bridge_v1.php';
function t_need($ok,$why){if(!$ok)throw new RuntimeException($why);}
$ids=[];hmsab_collect_ids(['anex_bridges'=>[['anex_id'=>123]],'prior'=>['source'=>['operatorKey'=>5,'hotelKey'=>'456']]],$ids);
t_need(array_keys($ids)===[123,456],'collect');
$ids=[];hmsab_collect_ids(['source'=>['operatorKey'=>315,'hotelKey'=>'456']],$ids);t_need($ids===[],'op_guard');
$ids=[];hmsab_collect_ids(['source'=>['operator_key'=>'5','original'=>['hotelKey'=>'789']]],$ids);t_need(array_keys($ids)===[789],'nested_original');
echo "MATCH_LIVE_SAMO_ANCHOR_ANEX_BRIDGE_V1_TEST_OK\n";
