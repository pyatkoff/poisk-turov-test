<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live30_common4_historical_hold_current_v20.php';
$edge=['supplier_namespace'=>'operator_315','external_hotel_id'=>'10','tv_hotel_id'=>7,'operator_id'=>25,'safe_to_write_now'=>false];
$cat=[7=>['id'=>7,'is_active'=>1,'country_name'=>'Турция']];
$raw='{"ok":true}';$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'99','local_hotel_id'=>7,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$x=hmc4c_classify($edge,$cat,[],[],[7=>[$anchor]],[],['operator_315|10'=>[7=>true]],['operator_315|7'=>['10'=>true]]);
if($x['status']!=='current_missing_edge'||$x['writer_ready']!==true)throw new RuntimeException('ready');
echo "MATCH_COMMON4_HISTORICAL_HOLD_CURRENT_V20_TEST_OK\n";
