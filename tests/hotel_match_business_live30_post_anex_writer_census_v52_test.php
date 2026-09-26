<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_business_live30_post_anex_writer_census_v52.php';
function v52t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v52t(V52_OP==='hotel-match-business-live30-post-anex-writer-census-1971-20260926-v52','op');
v52t(V52_V51_OP==='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260926-v51','v47');
v52t(V52_V51_DIGEST==='1fb6c78eb31808f9c0d466317c5c247740be4d4ca91d7f0f4488eaf2c89f1f41','digest');
v52t(V52_EXPECTED===12,'expected');
v52t(V52_BASELINE===['tv_live30_total'=>4399,'full_triple_total'=>2695,'tv_samo_missing_anex'=>744,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726],'baseline');
v52t(v52_bucket(true,true)==='full_triple','full');
v52t(v52_bucket(true,false)==='tv_samo_missing_anex','missing_anex');
v52t(v52_bucket(false,true)==='tv_anex_missing_samo','missing_samo');
v52t(v52_bucket(false,false)==='tv_only_missing_both','missing_both');
echo "MATCH_POST_ANEX_WRITER_CENSUS_V52_TEST_OK\n";
