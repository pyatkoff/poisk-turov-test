<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_fingerprint_correction_readiness_v40.php';
function v40t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v40t(V40_OP==='hotel-match-tv-samo-fingerprint-correction-readiness-1971-20260925-v40b','op');
v40t(V40_V37_SHA==='b7ef6082b8d27ad822ddaf69dd86e9249523f859cd61ee1b547c106118ffc55a','v37');
v40t(v40_core('AKRA FETHIYE THE RESIDENCE TUI BLUE SENSATORI ADULTS ONLY 16+')===['akra','blue','fethiye','residence','sensatori','tui'],'core');
v40t(v40_jaccard(['a','b'],['a','b'])===1.0,'jac');
$o=[];v40_direct_walk(['operator_342'=>['native_id'=>'777']],null,$o);v40t(isset($o['operator_342']['777']),'direct');
$tv=v40_tv_native([['operator_id'=>25,'operator_link_query'=>'hotelCode=123','last_seen_at'=>'2026-09-25']]);v40t(($tv['operator_315'][0]??null)==='123','tv_native');
echo "MATCH_TV_SAMO_FINGERPRINT_CORRECTION_READINESS_V40_TEST_OK\n";
