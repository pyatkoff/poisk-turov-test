<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_samo_business_live30_common4_recover_calibrate_v3.php';

$req=['2001'=>true];
$x=sbrc3_bridge(['operatorKey'=>315,'hotelKey'=>'2001','isOperatorHotelKey'=>0,'original'=>['hotel'=>'Hotel','hotelKey'=>'8123','tourKey'=>44]],315,$req);
if($x!==['state'=>'exact_catalog_to_native','catalog_id'=>'2001','native_id'=>'8123'])throw new RuntimeException('real_shape_bridge');
$x=sbrc3_bridge(['operatorKey'=>315,'hotelKey'=>'2001','isOperatorHotelKey'=>0,'original'=>['hotel'=>'Hotel','tourKey'=>44]],315,$req);
if(($x['state']??'')!=='catalog_only')throw new RuntimeException('missing_native_hold');
$x=sbrc3_bridge(['operatorKey'=>342,'hotelKey'=>'2001','isOperatorHotelKey'=>0,'original'=>['hotelKey'=>'8123']],315,$req);
if(($x['state']??'')!=='other_operator')throw new RuntimeException('operator_mismatch');
echo "MATCH_SAMO_BUSINESS_LIVE30_RECOVER_CALIBRATE_V3_TEST_OK\n";
