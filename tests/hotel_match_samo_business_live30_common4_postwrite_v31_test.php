<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_samo_business_live30_common4_postwrite_v31.php';
$x=sblc4_canonical_catalog([['andromeda_catalog','10'],['operator_115','z']],[ 'operator_115|z'=>[7=>true]],[7=>['10'=>true]]);
if(count($x['catalog'])!==1||!isset($x['catalog']['10'])||$x['operator_resolved']!==1)throw new RuntimeException('canonical');
echo "MATCH_SAMO_BUSINESS_LIVE30_COMMON4_POSTWRITE_V31_TEST_OK\n";
