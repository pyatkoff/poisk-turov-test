<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_single_fingerprint_corroboration_v38.php';

function v38t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v38t(V38_OP==='hotel-match-tv-samo-single-fingerprint-corroboration-1971-20260925-v38','op');
v38t(V38_V37_SHA==='b7ef6082b8d27ad822ddaf69dd86e9249523f859cd61ee1b547c106118ffc55a','sha');
v38t(v38_name_key('The Side Example Hotel & Spa')==='example side','name_key');
v38t(v38_country('Турция')==='turkey'&&v38_country('Turkey')==='turkey','country');
v38t(v38_place_key('Side - центр')==='side','place');
$src=['places'=>['side'=>'Side'],'points'=>[]];
$local=['places'=>['side'=>'Side'],'point'=>null];
v38t(v38_geo($src,$local)['ok']===true,'place_geo');
$src=['places'=>[],'points'=>['x'=>[36.0,30.0]]];
$local=['places'=>[],'point'=>[36.01,30.01]];
v38t(v38_geo($src,$local)['ok']===true,'coord_geo');
$bad=['places'=>[],'points'=>['x'=>[36.0,30.0]]];
$far=['places'=>[],'point'=>[37.0,31.0]];
v38t(v38_geo($bad,$far)['ok']===false,'far_geo');
echo "MATCH_TV_SAMO_SINGLE_FINGERPRINT_CORROBORATION_V38_TEST_OK\n";
