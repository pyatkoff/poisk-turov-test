<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live234_andromeda_context_acquire_v65.php';
function t65(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
t65(V65_OP==='hotel-match-live234-andromeda-context-acquire-1971-20260926-v65','op');
t65(V65_SRC_OP==='hotel-match-live-anex-missing-samo-router-refresh-1971-20260926-v36r','src');
t65(V65_SRC_SHA==='cd9b6f18c049af27501b40d5490536f31d9b5fa595f3d4d7864a4a7f04587a58','sha');
t65(V65_DAY==='2026-09-26','day');
t65(V65_HTTP_CAP===1600&&V65_PRICE_CAP===1400&&V65_PAGE_CAP===5,'caps');
t65(V65_OPS===[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'],'ops');
$p=['1'=>['catalog_hotel_id'=>'1','names'=>['ARES CITY (EX. KAMI HOTEL)'],'town_key'=>'7','town_labels'=>['Кемер'],'star_key'=>'3']];
$r=hms34_candidates(['name'=>'ARES CITY (EX. KAMI HOTEL)','region_name'=>'Кемер','subregion_name'=>''],[],$p);
t65($r['mode']==='exact'&&(string)array_key_first($r['candidates'])==='1','candidate');
$f=hms34_price_fact(['hotelKey'=>1,'operatorKey'=>342,'original'=>['hotelKey'=>565,'operatorKey'=>342]],'342');
t65(($f['native_operator_hotel_id']??'')==='565','fact');
$chunks=v65_chunks(array_map('strval',range(1,25)));
t65(count($chunks)===3&&max(array_map('count',$chunks))<=12,'chunks');
t65(v65_excluded('Россия')&&!v65_excluded('Турция'),'country');
echo "MATCH_V65_TEST_OK\n";
