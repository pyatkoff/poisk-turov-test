<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_live30_common4_acquire_v1.php';
if(SLC4A_OPS!==[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'])throw new RuntimeException('operators');
$req=['10'=>true];
$x=slc4a_bridge(['operatorKey'=>5,'hotelKey'=>'10','isOperatorHotelKey'=>'0','original'=>['hotelKey'=>'99','operatorKey'=>5]],5,$req);
if(($x['state']??'')!=='exact_catalog_to_native'||($x['native_id']??'')!=='99')throw new RuntimeException('bridge');
echo "MATCH_SAMO_LIVE30_COMMON4_ACQUIRE_V1_TEST_OK\n";
