<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_anex_strong_fuzzy_v1.php';
if(hmsaf_score('Movenpick Soma Bay','Movenpick Soma Bay Hotel')<0.99) throw new RuntimeException('score');
if(hmsaf_qualifiers('Alpha North Wing')===hmsaf_qualifiers('Alpha South Wing')) throw new RuntimeException('qualifier');
echo "MATCH_STRONG_FUZZY_TEST_OK\n";
