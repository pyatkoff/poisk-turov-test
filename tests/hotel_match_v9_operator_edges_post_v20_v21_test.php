<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_v9_operator_edges_post_v20_v21.php';
function v21ok(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$x=hm21_remaining(['rows'=>[
 ['status'=>'current_missing_edge','supplier_namespace'=>'operator_342','external_hotel_id'=>'9','tv_hotel_id'=>99,'operator'=>'intourist','operator_id'=>43,'operator_link_sha256'=>str_repeat('a',64),'canonical_anchor_count'=>0,'catalog_hotel'=>['id'=>99]],
 ['status'=>'source_occupied','supplier_namespace'=>'bgoperator','external_hotel_id'=>'2','tv_hotel_id'=>3],
]]);
v21ok(count($x)===1&&$x[0]['supplier_namespace']==='operator_342'&&$x[0]['tv_hotel_id']===99,'remaining_row');
echo "MATCH_V9_OPERATOR_EDGES_POST_V20_V21_TEST_OK\n";
