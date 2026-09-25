<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_business_live30_post_anex_writer_census_v48.php';
function v48t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v48t(V48_OP==='hotel-match-business-live30-post-anex-writer-census-1971-20260926-v48','op');
v48t(V48_V47_OP==='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260925-v47','v47');
v48t(V48_V47_DIGEST==='5de4e12e61862cf42c9004965e0174f067ba7d665583ee96b5e3edc78260ab03','digest');
v48t(V48_EXPECTED===17,'expected');
v48t(V48_BASELINE===['tv_live30_total'=>4399,'full_triple_total'=>2678,'tv_samo_missing_anex'=>761,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726],'baseline');
v48t(v48_bucket(true,true)==='full_triple','full');
v48t(v48_bucket(true,false)==='tv_samo_missing_anex','missing_anex');
v48t(v48_bucket(false,true)==='tv_anex_missing_samo','missing_samo');
v48t(v48_bucket(false,false)==='tv_only_missing_both','missing_both');
echo "MATCH_POST_ANEX_WRITER_CENSUS_V48_TEST_OK\n";
