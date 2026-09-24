<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_business_live30_common4_acquire_v3_wave1.php';

if(SLC4A_OP!=='hotel-match-samo-business-live30-common4-acquire-1971-20260924-v3-wave1')throw new RuntimeException('operation');
if(SLC4A_PLAN_OP!=='hotel-match-samo-business-live30-common4-plan-1971-20260924-v2')throw new RuntimeException('plan_operation');
if(SLC4A_CHECKIN_BEG!=='20260927'||SLC4A_CHECKIN_END!=='20261017')throw new RuntimeException('context');
if(SLC4A_OPS!==[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'])throw new RuntimeException('operators');

$ids=[];for($i=0;$i<31;$i++)$ids[]=(string)(900000000000000000+$i);
$chunks=slc4a_chunks($ids);
if(count($chunks)<3)throw new RuntimeException('chunk_count');
$seen=[];
foreach($chunks as $c){
    if(count($c)<1||count($c)>12||strlen(implode(',',$c))>300)throw new RuntimeException('chunk_guard');
    foreach($c as $id){if(isset($seen[$id]))throw new RuntimeException('chunk_duplicate');$seen[$id]=true;}
}
if(count($seen)!==31)throw new RuntimeException('chunk_coverage');

$rows=[
 ['catalog_id'=>'10','stateinc'=>5,'missing_operator_ids'=>[5,315]],
 ['catalog_id'=>'11','stateinc'=>5,'missing_operator_ids'=>[315]],
 ['catalog_id'=>'12','stateinc'=>7,'missing_operator_ids'=>[115]],
];
$groups=slc4a_groups($rows);
if(count($groups)!==3)throw new RuntimeException('groups');
foreach($groups as $g)if(count($g['hotel_ids'])>12||strlen(implode(',',$g['hotel_ids']))>300)throw new RuntimeException('group_guard');

$req=['10'=>true];
$x=slc4a_bridge(['operatorKey'=>5,'hotelKey'=>'10','isOperatorHotelKey'=>'0','original'=>['hotelKey'=>'99','operatorKey'=>5]],5,$req);
if(($x['state']??'')!=='exact_catalog_to_native'||($x['native_id']??'')!=='99')throw new RuntimeException('bridge');

echo "MATCH_SAMO_BUSINESS_LIVE30_COMMON4_ACQUIRE_V3_WAVE1_TEST_OK\n";
