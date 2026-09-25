<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_anex_missing_samo_router_v36.php';
if(R36_OP!=='hotel-match-live-anex-missing-samo-router-1971-20260925-v36')throw new RuntimeException('op');
r36_req(count(r36_chunks([3,1,1,2],2))===2,'chunks');
echo "LIVE_ANEX_MISSING_SAMO_ROUTER_V36_TEST_OK\n";
