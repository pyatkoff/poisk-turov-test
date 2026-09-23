<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_anex_coverage_v2.php';
if(HMTSAC_OP!=='hotel-match-tv-samo-anex-coverage-1971-20260924-v3')throw new RuntimeException('operation');
$facts=[1=>['name'=>'A','country_name'=>'Turkey','region_name'=>'R','subregion_name'=>'S','category'=>'5'],2=>['name'=>'B','country_name'=>'Egypt','region_name'=>'H','subregion_name'=>'','category'=>'4']];
$x=hmtsac_samo_bucket([1,2],[1=>true],[1=>['a'=>true]],$facts);
if($x['counts']['samo_local_total']!==2||$x['counts']['full_triple']!==1||$x['counts']['only_samo']!==1)throw new RuntimeException('bucket');
echo "MATCH_TV_SAMO_ANEX_COVERAGE_V2_TEST_OK\n";
