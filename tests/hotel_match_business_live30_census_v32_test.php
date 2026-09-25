<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_business_live30_census_v32.php';
function t32(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
$c=classify([1=>1,2=>1,3=>1,4=>1],[1=>1,2=>1],[1=>1,3=>1],'left','right');
t32($c['mapped_local_hotels']===4,'mapped');t32($c['triple']===1,'triple');t32($c['double']===2,'double');t32($c['single']===1,'single');
$v=venn([1=>1,2=>1,4=>1],[1=>1,3=>1,4=>1],[1=>1,2=>1,3=>1]);
t32($v['all3']===1,'all3');t32($v['tv_samo_only']===1,'ts');t32($v['tv_anex_only']===1,'ta');t32($v['samo_anex_only']===1,'sa');
echo "BUSINESS_LIVE30_V32_TEST_OK\n";
