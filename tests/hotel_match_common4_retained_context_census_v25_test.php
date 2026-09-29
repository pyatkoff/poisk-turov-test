<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_retained_context_census_v25.php';
mrc25_self_test();
if(MRC25_OP!=='hotel-match-common4-retained-context-census-1971-20260925-v25')throw new RuntimeException('operation');
echo "MATCH_COMMON4_RETAINED_CONTEXT_CENSUS_V25_TEST_OK\n";
