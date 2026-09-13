<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_anex_bridge_fuzzy_review.php';
function habfr_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

$p=habfr_pair_policy('RIXOS PREMIUM BELEK','RIXOS PREMIUM CLUB BELEK','Белек');
habfr_assert($p['critical_ok']===true,'critical tokens compatible');
habfr_assert($p['anchor_ok']===true,'same anchor retained');
habfr_assert($p['one_sided']===true,'one-sided extension recognized');
habfr_assert($p['subsequence']===true,'ordered containment recognized');
habfr_assert($p['safe_containment']===true,'single non-critical extension may be safe evidence');

$p=habfr_pair_policy('SUNRISE GARDEN','SUNRISE BEACH GARDEN','Хургада');
habfr_assert($p['critical_ok']===false,'beach/garden qualifier mismatch blocked');
habfr_assert($p['safe_containment']===false,'critical mismatch cannot be safe');

$p=habfr_pair_policy('BLUE LAGOON PALACE','BLUE BAY PALACE','Хургада');
habfr_assert($p['anchor_ok']===true,'anchor alone is insufficient');
habfr_assert($p['one_sided']===false,'two-sided token swap detected');
habfr_assert($p['safe_containment']===false,'two-sided token swap blocked');

$p=habfr_pair_policy('ROYAL GRAND','GRAND ROYAL CLUB','Хургада');
habfr_assert($p['anchor_ok']===false,'reordered brand/property anchor blocked');
habfr_assert($p['safe_containment']===false,'token reorder not promoted');

$p=habfr_best_policy(['RIXOS PREMIUM BELEK'],['RIXOS PREMIUM CLUB BELEK'],'Белек');
habfr_assert($p['safe_rank']===2,'best policy ranks safe containment first');
$p=habfr_best_policy(['SUNRISE GARDEN'],['SUNRISE BEACH GARDEN'],'Хургада');
habfr_assert($p['safe_rank']===0,'critical mismatch remains unranked');

echo "ok\n";
