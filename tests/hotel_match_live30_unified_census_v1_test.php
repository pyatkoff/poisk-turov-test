<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live30_unified_census_v1.php';

function tneed(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}

$c=hml30u_classify(
    [1=>true,2=>true,3=>true,4=>true,5=>true],
    [1=>true,2=>true,4=>true],
    [1=>true,3=>true,4=>true],
    'left','right'
);
tneed($c['mapped_local_hotels']===5,'mapped');
tneed($c['triple']===2,'triple');
tneed($c['double']===2,'double');
tneed($c['double_split']===['left'=>1,'right'=>1],'split');
tneed($c['single']===1,'single');

$v=hml30u_venn(
    [1=>true,2=>true,4=>true,7=>true],
    [1=>true,3=>true,4=>true,8=>true],
    [1=>true,2=>true,3=>true,9=>true]
);
tneed($v['all3']===1,'all3');
tneed($v['tv_samo_only']===1,'tv_samo');
tneed($v['tv_anex_only']===1,'tv_anex');
tneed($v['samo_anex_only']===1,'samo_anex');
tneed($v['tv_only']===1&&$v['samo_only']===1&&$v['anex_only']===1,'singles');
tneed($v['union_local_hotels']===7,'union');

tneed(hml30u_pick(['last_seen_at'=>true],['observed_at','last_seen_at'])==='last_seen_at','pick');
tneed(hml30u_pick([],['observed_at'])===null,'pick_missing');

echo "MATCH_LIVE30_UNIFIED_CENSUS_V1_TEST_OK\n";
