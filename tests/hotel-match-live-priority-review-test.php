<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live_priority_review.php';
function mlp_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

$g=mlp_pair_guard('Pickalbatros Aqua Park Resort Hurghada','GRAVITY HOTEL & AQUA PARK HURGHADA','Хургада');
mlp_assert($g['anchor_ok']===false,'different brand anchor blocked');
$g=mlp_pair_guard('METT Hotel & Beach Resort Bodrum','BODRUM BEACH RESORT','Бодрум');
mlp_assert($g['anchor_ok']===false,'mett to generic bodrum blocked');
$g=mlp_pair_guard('Aspen Hotel Istanbul','WYNDHAM ISTANBUL OLD CITY','Стамбул');
mlp_assert($g['anchor_ok']===false,'aspen to wyndham blocked');
$g=mlp_pair_guard('DoubleTree by Hilton Istanbul Sirkeci','DOUBLE TREE BY HILTON ISTANBUL TOPKAPI','Стамбул');
mlp_assert($g['anchor_ok']===true,'same brand anchor retained');
mlp_assert($g['dangerous_swap']===true,'different property locality swap blocked');
$g=mlp_pair_guard('SUN BEACH HOTEL','SUN GARDEN HOTEL','Сиде');
mlp_assert($g['critical_ok']===false,'beach garden critical mismatch blocked');
$g=mlp_pair_guard('NORTH STAR RESORT','SOUTH STAR RESORT','Кемер');
mlp_assert($g['critical_ok']===false,'north south critical mismatch blocked');

$p=mlp_best_pair(['Viva BLUE Resort & Diving Sports'],['VIVA BLUE RESORT AND DIVING SPORT'],'Хургада');
mlp_assert($p['strict_ordered']!==null,'known normalized strict identity retained');
$p=mlp_best_pair(['Sea Sun Sand Resort & SPA'],['SUN SEA SAND HOTEL'],'Пхукет');
mlp_assert($p['strict_ordered']===null,'token reorder not promoted to strict');
$p=mlp_best_pair(['Hilton Dubai Jumeirah'],['HILTON DUBAI PALM JUMEIRAH'],'Дубай');
mlp_assert($p['strict_ordered']===null,'significant extra locality not strict');

$one=['id'=>1,'pair'=>['strict_ordered'=>['ok'=>true]]];$none=['id'=>2,'pair'=>['strict_ordered'=>null]];$two=['id'=>3,'pair'=>['strict_ordered'=>['ok'=>true]]];
mlp_assert(mlp_unique_strict_candidate([$one,$none])['id']===1,'single strict target selected');
mlp_assert(mlp_unique_strict_candidate([$one,$two])===null,'multiple strict targets remain ambiguous');
mlp_assert(mlp_unique_strict_candidate([$none])===null,'no strict target stays unresolved');

echo "ok\n";
