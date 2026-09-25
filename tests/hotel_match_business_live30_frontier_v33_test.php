<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_business_live30_frontier_v33.php';
v33_self_test();
if(V33_OP!=='hotel-match-business-live30-frontier-plan-1971-20260925-v33')throw new RuntimeException('op');
echo "MATCH_BUSINESS_LIVE30_FRONTIER_V33_TEST_OK\n";
