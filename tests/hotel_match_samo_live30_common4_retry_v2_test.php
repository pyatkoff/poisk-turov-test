<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_live30_common4_retry_v2.php';
$f=[['v1_batch'=>7,'v1_reason'=>'ANDROMEDA_INVALID_PARAMS','stateinc'=>4,'operator_id'=>115,'hotel_ids'=>array_map('strval',range(20000001,20000030))]];
$g=slc4r2_subgroups($f);
if(count($g)!==3)throw new RuntimeException('count');
foreach($g as $x){if(count($x['hotel_ids'])>12||$x['hotels_bytes']>300)throw new RuntimeException('bounds');}
echo "MATCH_SAMO_LIVE30_COMMON4_RETRY_V2_TEST_OK\n";
