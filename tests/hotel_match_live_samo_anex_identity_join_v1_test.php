<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_anex_identity_join_v1.php';
if(hmsaj_name_key('Example Resort & Spa')!=='example') throw new RuntimeException('name');
if(hmsaj_country('ОАЭ')!=='uae'||hmsaj_country('United Arab Emirates')!=='uae') throw new RuntimeException('country');
$p=hmsaj_place_keys(['Нячанг - центр','Nha Trang']); if(!$p) throw new RuntimeException('place');
$s=[];hmsaj_sources(['x'=>['source'=>['name'=>'ABC','lName'=>'ABC Hotel','town'=>'Side']]],$s);if(count($s)!==1) throw new RuntimeException('source');
echo "MATCH_LIVE_SAMO_ANEX_IDENTITY_JOIN_V1_TEST_OK\n";
