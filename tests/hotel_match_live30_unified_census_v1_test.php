<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live30_unified_census_v1.php';

function tneed(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}

$tv=hmluc_tv_summary(
    [1=>true,2=>true,3=>true,4=>true],
    [1=>['s1'=>true],2=>['s2'=>true]],
    [1=>['a1'=>true],3=>['a3'=>true]]
);
tneed($tv['source_live30_total']===4,'tv_total');
tneed([$tv['triple'],$tv['double_samo'],$tv['double_anex'],$tv['single']] === [1,1,1,1],'tv_buckets');

$samo=hmluc_samo_summary(
    ['s1'=>true,'s2'=>true,'s3'=>true,'s4'=>true],
    ['andromeda_catalog|s1'=>[1=>true],'andromeda_catalog|s2'=>[2=>true],'andromeda_catalog|s4'=>[3=>true,4=>true]],
    [1=>true,2=>true,3=>true,4=>true],
    [1=>true],
    [1=>['a'=>true],2=>['b'=>true]]
);
tneed($samo['source_live30_total']===4,'samo_total');
tneed($samo['mapped_source_ids']===2&&$samo['mapped_local_hotels']===2,'samo_mapped');
tneed($samo['unresolved_source_ids']===1&&$samo['collision_source_ids']===1,'samo_unresolved');
tneed($samo['triple']===1&&$samo['double_anex']===1&&$samo['double_tv']===0&&$samo['single']===0,'samo_buckets');

$anex=hmluc_anex_summary(
    ['10'=>true,'20'=>true,'30'=>true],
    [10=>1,20=>2],
    [1=>true,2=>true],
    [1=>true],
    [1=>['s'=>true],2=>['s'=>true]]
);
tneed($anex['source_live30_total']===3&&$anex['mapped_source_ids']===2&&$anex['unresolved_source_ids']===1,'anex_counts');
tneed($anex['triple']===1&&$anex['double_samo']===1&&$anex['double_tv']===0&&$anex['single']===0,'anex_buckets');

$o=hmluc_overlap([1=>true,2=>true,5=>true],[2=>true,3=>true,5=>true],[2=>true,4=>true,5=>true]);
tneed($o['union_mapped_local_hotels']===5,'overlap_union');
tneed($o['buckets']===['tv_only'=>1,'samo_only'=>1,'anex_only'=>1,'tv_samo'=>0,'tv_anex'=>0,'samo_anex'=>0,'all_three_live30'=>2],'overlap_buckets');

echo "MATCH_LIVE30_UNIFIED_CENSUS_V1_TEST_OK\n";
