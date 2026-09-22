<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_v9_operator_edges_postwrite_v18.php';
function v18ok(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$x=hm18_residual(['rows'=>[
 ['status'=>'current_missing_edge','supplier_namespace'=>'bgoperator','canonical_anchor_count'=>2,'catalog_hotel'=>['country_name'=>'Таиланд','region_name'=>'Пхукет','subregion_name'=>'Патонг']],
 ['status'=>'current_missing_edge','supplier_namespace'=>'operator_315','canonical_anchor_count'=>0,'catalog_hotel'=>['country_name'=>'Египет','region_name'=>'Хургада','subregion_name'=>'']],
 ['status'=>'resolved_same','supplier_namespace'=>'bgoperator','canonical_anchor_count'=>1,'catalog_hotel'=>[]],
]]);
v18ok($x['current_missing_count']===2,'missing_count');
v18ok($x['by_namespace']===['bgoperator'=>1,'operator_315'=>1],'namespace_counts');
v18ok($x['by_anchor_count']===['0'=>1,'2'=>1],'anchor_counts');
echo "MATCH_V9_OPERATOR_EDGES_POSTWRITE_V18_TEST_OK\n";
