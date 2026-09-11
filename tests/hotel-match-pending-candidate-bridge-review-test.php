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

pcbr_assert(pcbr_strict_pair(['Pickalbatros Palace Resort'],['PICKALBATROS PALACE RESORT HURGHADA'],'Хургада')!==null,'coarse region suffix accepted');
pcbr_assert(pcbr_strict_pair(['Baramee Resortel Phuket'],['BARAMEE RESORTEL'],'Пхукет')!==null,'coarse region source suffix accepted');
pcbr_assert(pcbr_strict_pair(['DoubleTree by Hilton Antalya City Center'],['DOUBLETREE BY HILTON ANTALYA CITY CENTRE'],'Анталья')!==null,'orthography accepted');
pcbr_assert(pcbr_strict_pair(['Pickalbatros Aqua Park Resort Hurghada'],['GRAVITY HOTEL & AQUA PARK HURGHADA'],'Хургада')===null,'different brand blocked');
pcbr_assert(pcbr_strict_pair(['Prince Palace Bangkok'],['BANGKOK PALACE HOTEL'],'Бангкок')===null,'token order identity blocked');
pcbr_assert(pcbr_strict_pair(['Hilton Dubai Jumeirah'],['HILTON DUBAI PALM JUMEIRAH'],'Дубай')===null,'significant locality qualifier blocked');
pcbr_assert(pcbr_strict_pair(['SUN BEACH HOTEL'],['SUN GARDEN HOTEL'],'Сиде')===null,'beach garden mismatch blocked');

echo "ok\n";
