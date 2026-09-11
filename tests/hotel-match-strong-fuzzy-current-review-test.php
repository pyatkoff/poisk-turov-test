<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_strong_fuzzy_current_review.php';
function hfsr_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

$p=hfsr_pair('RIXOS PREMIUM CLUB BELEK','RIXOS PREMIUM BELEK','Белек',true);
hfsr_assert($p['critical_ok']===true&&$p['anchor_ok']===true,'compatible anchor/qualifiers');
hfsr_assert($p['safe_strong_fuzzy']===true,'strong ANEX bridge variation accepted');

$p=hfsr_pair('SUNRISE GARDEN RESORT','SUNRISE BEACH GARDEN','Хургада',true);
hfsr_assert($p['critical_ok']===false,'critical qualifier mismatch detected');
hfsr_assert($p['safe_strong_fuzzy']===false,'critical mismatch blocked');

$p=hfsr_pair('DONG DUONG HOTEL','DUONG DONG HOTEL','Хойан',true);
hfsr_assert($p['anchor_ok']===false,'reordered/different anchor blocked');
hfsr_assert($p['safe_strong_fuzzy']===false,'unsafe historical fuzzy shape blocked');

$p=hfsr_pair('ROYAL GRAND SHARM','ROYAL GRAND RESORT SHARM','Шарм-эль-Шейх',false);
hfsr_assert($p['safe_strong_fuzzy']===true,'high-confidence three-token Tourvisor identity accepted');

$p=hfsr_pair('BLUE BAY','BLUE BAY HOTEL','Хургада',false);
hfsr_assert($p['safe_strong_fuzzy']===false,'short Tourvisor-only generic identity blocked');

echo "ok\n";
