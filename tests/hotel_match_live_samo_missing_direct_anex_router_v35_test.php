<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_missing_direct_anex_router_v35.php';
if(R35_OP!=='hotel-match-live-samo-missing-direct-anex-router-1971-20260925-v35')throw new RuntimeException('op');
r35_req(count(r35_chunks([3,1,1,2],2))===2,'chunks');
echo "LIVE_SAMO_MISSING_DIRECT_ANEX_ROUTER_V35_TEST_OK\n";
