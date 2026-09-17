<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_biblio_resort_star_v1.php';

function need(bool $v,string $m): void { if(!$v) throw new RuntimeException($m); }

need(hm_identity_key('Rixos Downtown Antalya Hotel & SPA')==='rixos downtown antalya','identity_key');
need(hm_generic('FORTUNA ANTALYA 5*'),'fortuna');
need(!hm_generic('Hotel Fortuna Beach'),'fortuna_brand');
$rows=[['id'=>10,'name'=>'3*'],['id'=>20,'name'=>'4 stars'],['id'=>30,'name'=>'5 звезд']];
need(hm_star_id($rows,3)===10 && hm_star_id($rows,4)===20 && hm_star_id($rows,5)===30,'stars');
$dates=hm_dates(['dates'=>[['date'=>'2026-10-05'],['value'=>'20261006'],['name'=>'05.10.2026']]]);
need($dates===['2026-10-05','2026-10-06'],'dates');
need(hm_continue_stop(0,10) && hm_continue_stop(2,0) && !hm_continue_stop(2,1),'continue');
$tv=[
  101=>['identity_key'=>'alpha beach','name'=>'Alpha Beach Hotel','min_price'=>100000.0,'first_tour_id'=>'tv1'],
  102=>['identity_key'=>'duplicate','name'=>'Duplicate','min_price'=>90000.0,'first_tour_id'=>'tv2'],
  103=>['identity_key'=>'duplicate','name'=>'Duplicate Resort','min_price'=>91000.0,'first_tour_id'=>'tv3'],
];
$samo=[
 '9001'=>['identity_key'=>'alpha beach','name'=>'Alpha Beach','price'=>101000.0,'native_operator_hotel_id'=>'5001','urls'=>['https://example.test/hotel/5001']],
 '9002'=>['identity_key'=>'duplicate','name'=>'Duplicate','price'=>92000.0,'native_operator_hotel_id'=>'5002','urls'=>[]],
];
$m=hm_match($tv,$samo);
need(count($m)===1 && $m[0]['tv_hotel_id']===101 && $m[0]['samo_hotel_id']==='9001','unique_match');
need(($m[0]['price_gap']['relative']??1)<0.02,'price_gap');
need(hm_url('https://user:pass@example.test/x')===null,'url_credentials');
need(hm_url('https://example.test/hotel?session=secret')===null,'url_query_secret');
$merged=hm_tv_merge_rows(
  [['id'=>1,'name'=>'A','tours'=>[['id'=>'t1','price'=>100]]]],
  [['id'=>1,'name'=>'A','tours'=>[['id'=>'t2','price'=>110]]],['id'=>2,'name'=>'B','tours'=>[['id'=>'t3','price'=>120]]]]
);
$sig=hm_tv_signatures($merged);
need($sig['hotels']===2 && $sig['tours']===3,'continue_union');

echo "MATCH_TV_SAMO_BIBLIO_TEST_OK\n";
