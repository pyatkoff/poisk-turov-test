<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_live30_common4_plan_v1.php';
if(SLC4P_NS_OPS!==['operator_5'=>5,'operator_115'=>115,'operator_315'=>315,'operator_342'=>342])throw new RuntimeException('operators');
if(slc4p_time_col(['created_at','observed_at_utc'])!=='observed_at_utc')throw new RuntimeException('time');
echo "MATCH_SAMO_LIVE30_COMMON4_PLAN_V1_TEST_OK\n";
