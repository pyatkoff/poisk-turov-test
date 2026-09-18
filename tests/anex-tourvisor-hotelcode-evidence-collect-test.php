<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/anex_tourvisor_hotelcode_evidence_collect.php';
function ate_assert(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}

ate_assert(ate_allowed_anex_page('https://agent.anextour.ru/search/tour?HOTELLIST=8121'),'allow operator');
ate_assert(!ate_allowed_anex_page('http://agent.anextour.ru/search/tour?HOTELLIST=8121'),'https only');
ate_assert(!ate_allowed_anex_page('https://evil.example/?next=anextour.ru'),'host allowlist');
ate_assert(ate_operator_hotellist('https://agent.anextour.ru/search/tour?HOTELLIST=8121')===8121,'HOTELLIST parse');
ate_assert(ate_operator_hotellist('https://agent.anextour.ru/search/tour?HOTELLIST=x')===null,'HOTELLIST strict');
$html='<img src="https://files.anextour.ru/hotel/egypt/hotel/x/o417822?hotelCode=5844&amp;size=lg">';
$ev=ate_page_hotelcode_evidence($html);ate_assert(($ev['status']??'')==='confirmed','page hotelCode confirmed');ate_assert((int)$ev['hotel_code']===5844,'page code');
$conf=ate_page_hotelcode_evidence('<img src="https://files.anextour.ru/hotel/a?hotelCode=11"><img src="https://files.anextour.ru/hotel/b?hotelCode=12">');ate_assert(($conf['status']??'')==='conflicting_codes','page conflict');

$seeds=[
 ['anex_hotel_id'=>8121,'country_id'=>4,'observed'=>true,'search_count'=>9,'source_names'=>['APERION BEACH HOTEL'],'source_places'=>['Side'],'latitude'=>36.713018,'longitude'=>31.563078],
 ['anex_hotel_id'=>99,'country_id'=>4,'observed'=>true,'search_count'=>1,'source_names'=>['OTHER HOTEL'],'source_places'=>[],'latitude'=>null,'longitude'=>null],
];
$row=['id'=>6319,'name'=>'APERION BEACH','country'=>['id'=>4],'region'=>['name'=>'Side'],'latitude'=>36.7130180,'longitude'=>31.5630780,'tours'=>[['id'=>'tour-1','operator'=>['id'=>7]]]];
$m=ate_match_tv_hotel($row,$seeds);ate_assert(is_array($m),'generic name geo match');ate_assert($m['anex_hotel_id']===8121,'correct seed');ate_assert($m['tv_hotel_id']===6319,'tv id');ate_assert(($m['distance_m']??999)<1,'coordinate precision');
ate_assert(ate_first_anex_tour_id($row,7)==='tour-1','tour id');ate_assert(ate_first_anex_tour_id($row,8)===null,'operator guard');

$far=$row;$far['latitude']=40.0;$far['longitude']=30.0;ate_assert(ate_match_tv_hotel($far,$seeds)===null,'5km guard');
ate_assert(ate_departure_id([['id'=>1,'name'=>'Москва']])===1,'moscow departure');
ate_assert(ate_operator_id([['id'=>22,'name'=>'ANEX']])===22,'anex operator');
ate_assert(ate_pick_date(['2026-09-10','2026-09-12'],new DateTimeImmutable('2026-09-11'))==='2026-09-12','future date');

$src=file_get_contents(__DIR__.'/../scripts/diagnostics/anex_tourvisor_hotelcode_evidence_collect.php');
ate_assert(is_string($src)&&str_contains($src,'START TRANSACTION READ ONLY'),'current DB read only');
ate_assert(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\b\s+/i',$src),'no DML');
ate_assert(str_contains($src,"ATE_OPERATION = 'hotel-match-anex-tourvisor-hotelcode-evidence-1971-20260911-v1'"),'immutable op');
echo "anex-tourvisor-hotelcode-evidence-collect-test: ok\n";
