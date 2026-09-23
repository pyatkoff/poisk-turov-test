<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live30_common4_continuation_plan_v8.php';
function c8ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$p=hmc4c8_partition([1,2,3,5,8],[1,2,4,6]);c8ok($p['attempted_still_current']===[1,2],'still');c8ok($p['attempted_resolved']===[4,6],'resolved');c8ok($p['never_attempted_current']===[3,5,8],'never');
c8ok(hmc4c8_id_digest([3,1,2])===hmc4c8_id_digest([2,3,1]),'set_digest_order');
c8ok(hmc4c8_sequence_digest([3,1,2])!==hmc4c8_sequence_digest([2,3,1]),'sequence_digest_order');
c8ok(count(HMC4C8_CHILDREN)===6&&array_sum(array_column(HMC4C8_CHILDREN,'count'))===450,'children450');
echo "MATCH_COMMON4_CONTINUATION_PLAN_V8_TEST_OK\n";
