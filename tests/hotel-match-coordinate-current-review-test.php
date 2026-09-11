<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_coordinate_current_review.php';
function hccr_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

$n=hccr_name_evidence(['APERION BEACH HOTEL'],['APERION BEACH'],'Сиде');
hccr_assert($n['critical_ok']===true,'matching beach qualifier retained');
hccr_assert(hccr_candidate_rule($n,120,400)!==null,'near matching identity accepted');

$n=hccr_name_evidence(['SUNRISE GARDEN'],['SUNRISE BEACH GARDEN'],'Хургада');
hccr_assert($n['critical_ok']===false,'critical beach/garden difference detected');
hccr_assert(hccr_candidate_rule($n,10,500)===null,'critical qualifier mismatch blocks even very close coordinates');

$n=['strict'=>true,'broad'=>true,'critical_ok'=>true,'anchor_ok'=>true,'shared'=>2,'score'=>1.0];
hccr_assert(hccr_candidate_rule($n,999,100)==='coordinate_strict_name_direct_geo','strict name within 1km safe');
hccr_assert(hccr_candidate_rule($n,1001,100)===null,'strict name beyond 1km not auto-safe');
hccr_assert(hccr_candidate_rule($n,100,74)===null,'nearest target margin under 75m blocked');

$n=['strict'=>false,'broad'=>false,'critical_ok'=>true,'anchor_ok'=>true,'shared'=>2,'score'=>0.70];
hccr_assert(hccr_candidate_rule($n,150,200)==='coordinate_fuzzy_name_direct_geo','strong fuzzy with tight coordinates safe');
hccr_assert(hccr_candidate_rule($n,201,300)===null,'fuzzy beyond 200m blocked');

echo "ok\n";
