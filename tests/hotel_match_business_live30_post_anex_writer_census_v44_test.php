<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_business_live30_post_anex_writer_census_v44.php';
function v44t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v44t(V44_OP==='hotel-match-business-live30-post-anex-writer-census-1971-20260925-v44','op');
v44t(V44_V43_OP==='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260925-v43','v43');
v44t(V44_V43_DIGEST==='86acbd0baa8fd5df55a3e0814764cf4ef3f04a29047139698208d3ba5ff1f856','digest');
v44t(V44_BASELINE===['tv_live30_total'=>4399,'full_triple_total'=>2662,'tv_samo_missing_anex'=>777,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726],'baseline');
v44t(v44_bucket(true,true)==='full_triple','full');
v44t(v44_bucket(true,false)==='tv_samo_missing_anex','missing_anex');
v44t(v44_bucket(false,true)==='tv_anex_missing_samo','missing_samo');
v44t(v44_bucket(false,false)==='tv_only_missing_both','missing_both');
echo "MATCH_POST_ANEX_WRITER_CENSUS_V44_TEST_OK\n";
