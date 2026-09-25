<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_retained_context_acquire_v26.php';
c4r26_self_test();
if(count(C4R26_CONTEXTS)!==8||C4R26_HTTP_CAP!==1000)throw new RuntimeException('context_contract');
$ops=[];foreach(C4R26_CONTEXTS as $c)$ops[$c['operator_id']]=true;
if(array_keys($ops)!==[315,5]&&array_keys($ops)!==[5,315])throw new RuntimeException('operators');
echo "MATCH_COMMON4_RETAINED_CONTEXT_ACQUIRE_V26_TEST_OK\n";
