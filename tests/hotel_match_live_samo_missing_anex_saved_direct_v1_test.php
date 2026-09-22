<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_missing_anex_saved_direct_v1.php';

function t_need($ok,$why){if(!$ok)throw new RuntimeException($why);}

$r=['operator_name'=>'ANEX','operator_link'=>'https://agent.anextour.ru/search?hotellist=8121','operator_link_host'=>'agent.anextour.ru','operator_link_query'=>'hotellist=8121'];
$x=hmsma_direct_id($r);
t_need(($x['status']??'')==='direct_id'&&($x['anex_hotel_id']??'')==='8121','single');

$r['operator_link']='https://agent.anextour.ru/search?hotelcode=8121';$r['operator_link_query']='hotelcode=8121';
$x=hmsma_direct_id($r);t_need(($x['anex_hotel_id']??'')==='8121','hotelcode');

$r['operator_link']='https://agent.anextour.ru/search?hotellist=8121,8122';$r['operator_link_query']='hotellist=8121,8122';
t_need(hmsma_direct_id($r)['status']==='ambiguous_direct_id','multi');

$r['operator_name']='Other';$r['operator_link']='https://example.com/?hotellist=8121';$r['operator_link_host']='example.com';$r['operator_link_query']='hotellist=8121';
t_need(hmsma_direct_id($r)['status']==='not_anex','operator_guard');

$r=['operator_name'=>'ANEX','operator_link'=>'http://agent.anextour.ru/search?hotellist=8121','operator_link_host'=>'agent.anextour.ru','operator_link_query'=>'hotellist=8121'];
t_need(hmsma_direct_id($r)['status']==='invalid_link','https_guard');

echo "MATCH_LIVE_SAMO_MISSING_ANEX_SAVED_DIRECT_V1_TEST_OK\n";
