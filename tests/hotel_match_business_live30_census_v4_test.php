<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_business_live30_census_v4.php';
function t4(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
$c=classify([1=>1,2=>1,3=>1,4=>1],[1=>1,2=>1],[1=>1,3=>1],'left','right');
t4($c['mapped_local_hotels']===4,'mapped');t4($c['triple']===1,'triple');t4($c['double']===2,'double');t4($c['single']===1,'single');
$v=venn([1=>1,2=>1,4=>1],[1=>1,3=>1,4=>1],[1=>1,2=>1,3=>1]);
t4($v['all3']===1,'all3');t4($v['tv_samo_only']===1,'ts');t4($v['tv_anex_only']===1,'ta');t4($v['samo_anex_only']===1,'sa');
echo "BUSINESS_LIVE30_V4_TEST_OK\n";
