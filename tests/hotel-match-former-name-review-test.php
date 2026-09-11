<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_former_name_review.php';
function hmfn_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$segments=hmfn_former_segments('OCCIDENTAL EDEN BERUWALA (EX. EDEN RESORT & SPA)');
hmfn_assert(in_array('EDEN RESORT & SPA',$segments,true),'parenthesized EX segment');
$segments=hmfn_former_segments('CASA BLUE BEACH RESORT EX. MAGIC TULIP');
hmfn_assert(in_array('MAGIC TULIP',$segments,true),'inline EX segment');
hmfn_assert(hmfn_key('Double Tree By Hilton')==='double tree by hilton','identity normalization');
hmfn_assert(hmfn_key('Eden Resort & Spa')==='eden','generic resort/spa ignored');
hmfn_assert(hmfn_critical('Sunset Beach Hotel')===['beach'],'critical qualifier preserved');
echo "hotel-match-former-name-review-test: ok\n";
