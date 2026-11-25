<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_retained_context_acquire_v26b.php';
c4r26_self_test();
if(count(C4R26_CONTEXTS)!==7||C4R26_HTTP_CAP!==1000)throw new RuntimeException('context_contract');
$ids=array_map(fn($x)=>(int)$x['context_id'],C4R26_CONTEXTS);
if($ids!==[2,3,4,5,6,7,8])throw new RuntimeException('context_ids');
$keys=[];foreach(C4R26_CONTEXTS as $c){$k=c4r26_file_key($c,1);if(isset($keys[$k]))throw new RuntimeException('file_key_collision');$keys[$k]=true;}
echo "MATCH_COMMON4_RETAINED_CONTEXT_ACQUIRE_V26B_TEST_OK\n";
