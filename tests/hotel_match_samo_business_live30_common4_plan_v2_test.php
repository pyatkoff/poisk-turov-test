<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_samo_business_live30_common4_plan_v2.php';
function tt(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
$x=sblc4_canonical_catalog(
    [['andromeda_catalog','10'],['operator_115','b'],['operator_315','c'],['operator_342','x']],
    ['operator_115|b'=>[7=>true],'operator_315|c'=>[8=>true],'operator_342|x'=>[9=>true]],
    [7=>['10'=>true],8=>['20'=>true],9=>['30'=>true,'31'=>true]]
);
tt(count($x['catalog'])===2,'catalog_count');
tt(isset($x['catalog']['10'])&&isset($x['catalog']['20']),'catalog_ids');
tt($x['operator_resolved']===2,'resolved');
tt($x['operator_collision']===1,'collision');
tt(count($x['operator_unresolved'])===0,'unresolved');
tt($x['operator_resolved']+$x['operator_collision']===3,'operator_accounting');
echo "MATCH_SAMO_BUSINESS_LIVE30_COMMON4_PLAN_V2_TEST_OK\n";
