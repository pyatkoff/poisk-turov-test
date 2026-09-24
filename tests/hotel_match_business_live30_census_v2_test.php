<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_business_live30_census_v2.php';
function t(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
$c=classify([1=>1,2=>1,3=>1,4=>1],[1=>1,2=>1],[1=>1,3=>1],'left','right');
t($c['mapped_local_hotels']===4,'mapped');t($c['triple']===1,'triple');t($c['double']===2,'double');t($c['single']===1,'single');
t($c['double_split']===['left'=>1,'right'=>1],'split');
$v=venn([1=>1,2=>1,4=>1],[1=>1,3=>1,4=>1],[1=>1,2=>1,3=>1]);
t($v['all3']===1,'all3');t($v['tv_samo_only']===1,'ts');t($v['tv_anex_only']===1,'ta');t($v['samo_anex_only']===1,'sa');
echo "BUSINESS_LIVE30_V2_TEST_OK\n";