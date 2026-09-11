<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_global_coordinate_nearest_rescue.php';
function hmgcn_assert(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
[$a,$b]=hmgcn_cell(36.713018,31.563078);hmgcn_assert(is_int($a)&&is_int($b),'grid cell');
$exact=hmgcr_pair('APERION BEACH HOTEL','APERION BEACH',[],[]);$r=hmgcn_route($exact,20.0,true);hmgcn_assert(($r['rank']??0)===3,'exact coordinate route');
$two=hmgcr_pair('Marina Express Aviator Phuket Airport','Sugar Marina Express Aviator Phuket Airport',[],[]);$r=hmgcn_route($two,100.0,true);hmgcn_assert($r!==null&&in_array($r['rank'],[2,3],true),'two-token coordinate route');
$single=hmgcr_pair('Ascot Hotel','ASCOT KRABI',[],[]);$r=hmgcn_route($single,25.0,true);hmgcn_assert($r!==null,'single ultra-tight place route');
hmgcn_assert(hmgcn_route($single,70.0,true)===null,'single >50m blocked');
$zero=hmgcr_pair('Completely Different','Unrelated Property',[],[]);hmgcn_assert(hmgcn_route($zero,1.0,true)===null,'zero-token coordinate-only blocked');
$qual=hmgcr_pair('Royal Garden','Royal Beach',[],[]);hmgcn_assert(($qual['critical_ok']??true)===false,'qualifier mismatch blocked');
hmgcn_assert(hmgcn_route($exact,6000.0,true)===null,'>5km blocked');
fwrite(STDOUT,"hotel-match-global-coordinate-nearest-rescue-test: ok\n");
