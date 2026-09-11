<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/anex_strong_bridge_current_review.php';
function asbr_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$p=asbr_pair('RIXOS PREMIUM CLUB BELEK','RIXOS PREMIUM BELEK','Белек',true);asbr_assert($p['safe_strong_bridge']===true,'Andromeda bridge permits strong two-token identity');
$p=asbr_pair('SUNRISE GARDEN HOTEL','SUNRISE BEACH GARDEN','Хургада',true);asbr_assert($p['critical_ok']===false&&$p['safe_strong_bridge']===false,'critical qualifier mismatch blocked');
$p=asbr_pair('DONG DUONG HOTEL','DUONG DONG HOTEL','Хойан',true);asbr_assert($p['anchor_ok']===false&&$p['safe_strong_bridge']===false,'historical reorder mismatch blocked');
$p=asbr_pair('ROYAL GRAND PALACE SHARM','ROYAL GRAND PALACE RESORT SHARM','Шарм-эль-Шейх',false);asbr_assert($p['safe_strong_bridge']===true,'three significant TV tokens survive generic words');
$p=asbr_pair('BLUE BAY','BLUE BAY HOTEL','Хургада',false);asbr_assert($p['safe_strong_bridge']===false,'short TV-only identity blocked');
echo "ok\n";
