<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_direct_details_coordinate_accept.php';

function hmaddca_test_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
function hmaddca_test_throws(callable $fn,string $message): void { try{$fn();}catch(Throwable $e){return;}throw new RuntimeException($message); }

$prepared=[];
for($i=1;$i<=35;$i++)$prepared[]=['anex_hotel_id'=>1000+$i,'target_local_hotel_id'=>2000+$i,'live'=>true];
$review=[
    'status'=>'completed',
    'operation_id'=>HMADDCA_SOURCE_OPERATION,
    'database_writes'=>0,
    'mapping_writes'=>0,
    'supplier_calls'=>0,
    'historical_operations_replayed'=>false,
    'direct_result_sha256'=>HMADDCR_DIRECT_RESULT_SHA256,
    'direct_plan_sha256'=>HMADDCR_DIRECT_PLAN_SHA256,
    'stats'=>['prepared'=>35,'prepared_live'=>35],
    'prepared'=>$prepared,
];
$rows=hmaddca_source_rows($review);
hmaddca_test_assert(count($rows)===35,'source rows count');
hmaddca_test_assert(isset($rows[1001])&&(int)$rows[1001]['target_local_hotel_id']===2001,'source rows indexed');
$bad=$review;$bad['operation_id']='old-op';hmaddca_test_throws(static fn()=>hmaddca_source_rows($bad),'operation tamper must block');
$bad=$review;$bad['prepared'][0]['live']=false;hmaddca_test_throws(static fn()=>hmaddca_source_rows($bad),'non-live source must block');
$bad=$review;array_pop($bad['prepared']);hmaddca_test_throws(static fn()=>hmaddca_source_rows($bad),'source count tamper must block');

hmaddca_test_assert(!hmaddca_two_token_identity_anchor(
    ['source_tokens'=>['mira','beach','bodrum'],'target_tokens'=>['amilla','beach','bodrum']],
    ['source_region'=>'Bodrum','source_town'=>'','target_region'=>'Bodrum','target_subregion'=>'']
),'place plus beach is not an identity anchor');
hmaddca_test_assert(!hmaddca_two_token_identity_anchor(
    ['source_tokens'=>['v','grand','sahl','hasheesh'],'target_tokens'=>['srnty','sahl','hasheesh']],
    ['source_region'=>'Sahl Hasheesh','source_town'=>'','target_region'=>'Sahl Hasheesh','target_subregion'=>'']
),'geography-only overlap must block');
hmaddca_test_assert(hmaddca_two_token_identity_anchor(
    ['source_tokens'=>['costa','centro','bodrum'],'target_tokens'=>['costa','bodrum','city']],
    ['source_region'=>'Bodrum','source_town'=>'','target_region'=>'Bodrum','target_subregion'=>'']
),'brand token must preserve Costa match');
hmaddca_test_assert(hmaddca_two_token_identity_anchor(
    ['source_tokens'=>['titanic','palace','hurghada'],'target_tokens'=>['titanic','palace']],
    ['source_region'=>'Hurghada','source_town'=>'','target_region'=>'Hurghada','target_subregion'=>'']
),'distinctive Titanic token must preserve match');

echo "ok\n";
