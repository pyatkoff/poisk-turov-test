<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_single_fingerprint_conflict_v39.php';
function v39t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v39t(V39_OP==='hotel-match-tv-samo-single-fingerprint-conflict-audit-1971-20260925-v39','op');
v39t(v39_core_tokens('WB TRAVEL ANITA MATIATE')===['anita','matiate'],'wb');
$src=['source_name_keys'=>['alegria side'],'source_country_keys'=>['turkey'],'source_place_keys'=>['side']];
$h=['id'=>7,'name'=>'SIDE ALEGRIA HOTEL & SPA ADULTS ONLY 16+','normalized_name'=>'','country_name'=>'Турция','region_name'=>'Сиде','subregion_name'=>''];
$x=v39_side($h,[],$src);
v39t($x['supported']===true,'supported');
$src2=['source_name_keys'=>['beach fame'],'source_country_keys'=>['turkey'],'source_place_keys'=>['kemer']];
$h2=['id'=>8,'name'=>'FAME HOTEL','normalized_name'=>'','country_name'=>'Турция','region_name'=>'Кемер','subregion_name'=>''];
$x2=v39_side($h2,[],$src2);
v39t($x2['supported']===false,'weak_subset');
echo "MATCH_TV_SAMO_SINGLE_FINGERPRINT_CONFLICT_V39_TEST_OK\n";
