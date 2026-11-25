<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_business_live30_post_anex_writer_census_v62.php';
function v62t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v62t(V62_OP==='hotel-match-business-live30-post-anex-writer-census-1971-20260926-v62','op');
v62t(V62_V61_OP==='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260926-v61','v61');
v62t(V62_V61_DIGEST==='5853696fad966012d929c3adbb35a32dff7956aa4bced3808ab4279170817b22','digest');
v62t(V62_EXPECTED===2,'expected');
v62t(V62_BASELINE===['tv_live30_total'=>4399,'full_triple_total'=>2709,'tv_samo_missing_anex'=>730,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726],'baseline');
v62t(v62_bucket(true,true)==='full_triple','full');
v62t(v62_bucket(true,false)==='tv_samo_missing_anex','missing_anex');
v62t(v62_bucket(false,true)==='tv_anex_missing_samo','missing_samo');
v62t(v62_bucket(false,false)==='tv_only_missing_both','missing_both');
echo "MATCH_POST_ANEX_WRITER_CENSUS_V62_TEST_OK\n";
