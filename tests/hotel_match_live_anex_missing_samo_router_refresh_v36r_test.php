<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_anex_missing_samo_router_refresh_v36r.php';
function t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
t(V36R_OP==='hotel-match-live-anex-missing-samo-router-refresh-1971-20260926-v36r','op');
t(V36R_SOURCE_OP==='hotel-match-live-anex-missing-samo-router-1971-20260925-v36','source');
t(V36R_SOURCE_RESULT_SHA==='b6d90db7baf96a07a927502ed7605799b6fdfd5af90c9ffc1b8dc0a05942d080','sha');
t(V36R_EXPECTED_INPUT===234,'input');
t(V36R_EXPECTED_DAY==='2026-09-26','day');
t(v36r_excluded('Россия'),'ru');
t(v36r_excluded('Abkhazia'),'abkhazia');
t(!v36r_excluded('Turkey'),'turkey');
t(v36r_ctx_key(['departure_id'=>1,'country_id'=>4,'departure_date'=>'2026-10-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''])==='1|4|2026-10-01|7|2|0|','ctx');
echo "MATCH_V36R_TEST_OK\n";
