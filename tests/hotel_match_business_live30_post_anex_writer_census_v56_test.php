<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_business_live30_post_anex_writer_census_v56.php';
function v56t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v56t(V56_OP==='hotel-match-business-live30-post-anex-writer-census-1971-20260926-v56b','op');
v56t(V56_V51_OP==='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260926-v55','v47');
v56t(V56_V51_DIGEST==='79dfa2123f09d9ed0156b29251a06907598037634fc6fd094c8d99581434665c','digest');
v56t(V56_EXPECTED===2,'expected');
v56t(V56_BASELINE===['tv_live30_total'=>4399,'full_triple_total'=>2707,'tv_samo_missing_anex'=>732,'tv_anex_missing_samo'=>234,'tv_only_missing_both'=>726],'baseline');
v56t(v56_bucket(true,true)==='full_triple','full');
v56t(v56_bucket(true,false)==='tv_samo_missing_anex','missing_anex');
v56t(v56_bucket(false,true)==='tv_anex_missing_samo','missing_samo');
v56t(v56_bucket(false,false)==='tv_only_missing_both','missing_both');
echo "MATCH_POST_ANEX_WRITER_CENSUS_V56_TEST_OK\n";
