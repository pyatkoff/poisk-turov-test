<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_samo_live30_post39_coverage_v1.php';

function tneed(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}

$locals=[1=>true,2=>true,3=>true,4=>true,5=>true];
$lanes=[
    1=>[],
    2=>['operator_5'=>true],
    3=>['operator_115'=>true,'operator_315'=>true],
    4=>['operator_5'=>true,'operator_115'=>true,'operator_342'=>true],
    5=>array_fill_keys(HMSP39_LANES,true),
];
$s=hmsp39_summary($locals,$lanes,[1=>true,3=>true,5=>true],[1=>['a'=>true],2=>['b'=>true],5=>['c'=>true]]);
tneed($s['mapped_local_hotels']===5,'mapped');
tneed($s['common4_lanes_by_count']===['0'=>1,'1'=>1,'2'=>1,'3'=>1,'4'=>1],'distribution');
tneed($s['common4_lane_present']===['operator_5'=>3,'operator_115'=>3,'operator_315'=>2,'operator_342'=>2],'present');
tneed($s['common4_lane_missing']===['operator_5'=>2,'operator_115'=>2,'operator_315'=>3,'operator_342'=>3],'missing');
tneed($s['with_tv_live30']===3,'tv');
tneed($s['with_direct_anex']===3,'anex');
tneed($s['full_triple_tv_samo_direct_anex']===2,'triple');
tneed($s['common4_all4']===1,'all4');

echo "MATCH_SAMO_POST39_COVERAGE_V1_TEST_OK\n";
