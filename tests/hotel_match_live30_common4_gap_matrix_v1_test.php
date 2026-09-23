<?php
declare(strict_types=1);
$src=dirname(__DIR__).'/scripts/diagnostics/hotel_match_live30_common4_gap_matrix_v1.php';
if(!is_file($src))throw new RuntimeException('missing_source');
$s=(string)file_get_contents($src);
foreach([
    "13=>['key'=>'anex'","18=>['key'=>'biblio'","25=>['key'=>'funsun'","43=>['key'=>'intourist'",
    "anextour.ru","hotelcode","hotellist","bgoperator.ru","f4",
    "'accepted_current'","'saved_exact_unaccepted'","'saved_exact_source_collision'",
    "'saved_ambiguous_native'","'observed_no_usable_native'","'no_retained_operator_result'",
    "'live30_non_triple_total'","'all_four_exact_count'","'gap_bucket_counts'",
    "'provider_http_calls'=>0","'database_writes'=>0","'mapping_writes'=>0",
] as $needle)if(!str_contains(strtolower($s),strtolower($needle)))throw new RuntimeException('missing_'.$needle);
if(str_contains($s,'/tours/search'))throw new RuntimeException('provider_search_forbidden');
if(str_contains($s,'INSERT INTO ')||str_contains($s,'UPDATE ')||str_contains($s,'DELETE FROM '))throw new RuntimeException('db_write_forbidden');
passthru('php '.escapeshellarg($src).' --self-test',$code);
if($code!==0)throw new RuntimeException('selftest_failed');
echo "MATCH_LIVE30_COMMON4_GAP_MATRIX_V1_TEST_OK\n";
