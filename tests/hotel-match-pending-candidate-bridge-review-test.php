<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_pending_candidate_bridge_review.php';
function pcbr_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

$x=pcbr_compat(['APERION BEACH HOTEL'],['APERION BEACH (EX. SEA PARADISE)']);
pcbr_assert($x['shared']>=2,'former alias tokens');

$x=pcbr_compat(['SUN BEACH HOTEL'],['SUN GARDEN HOTEL']);
pcbr_assert($x['broad']===false,'beach garden significant');

$x=pcbr_compat(['NORTH STAR RESORT'],['SOUTH STAR RESORT']);
pcbr_assert($x['broad']===false,'north south significant');

$x=pcbr_compat(['THE TOWER PLAZA HOTEL'],['THE TOWER PLAZA HOTEL DUBAI (EX. MILLENNIUM PLAZA)']);
pcbr_assert($x['shared']>=2 && $x['score']>=0.6,'strong alias overlap');

echo "ok\n";
