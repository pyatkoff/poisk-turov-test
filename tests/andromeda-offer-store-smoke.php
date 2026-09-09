<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-offer-store.php';
$n=0;
function ok($v): void { global $n; ++$n; if (!$v) throw new RuntimeException('CHECK_'.$n); }
function denied(callable $f,string $why): void {
    try { $f(); } catch (RuntimeException|InvalidArgumentException $e) { ok($e->getMessage()===$why); return; }
    throw new RuntimeException('EXPECTED_'.$why);
}
$c=['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260918','CHECKIN_END'=>'20260918',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>8,'NIGHTS_TILL'=>8,'CURRENCYINC'=>643];
$r=['id'=>'private-fixture-offer','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>107193,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'18.09.2026','nights'=>'8',
    'hotel'=>'Fixture hotel','operator'=>'Anex Tour','meal'=>'AI','mealKey'=>6,
    'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$p=['PAGE'=>1,'PAGES_COUNT'=>2,'PRICES'=>[$r]];
$s=[]; $a=new AnyTourAndromedaOfferStore($s);
$a->begin('search_a',1,1000);
denied(fn()=>$a->lookup('search_a',1,'x',1001),'SNAPSHOT_MISSING');
$out=$a->capture($p,$c,'search_a',1,1001); $ref=$out['offers'][0]['offer_ref'];
ok($out['status']==='partial' && $out['selection_enabled']===false);
ok(strpos(json_encode($out),'private-fixture-offer')===false);
ok($a->lookup('search_a',1,$ref,1002)['supplier_offer_id']===$r['id']);
ok($a->lookup('search_a',1,$ref,1002)['offer']['local_hotel_id']===null);
ok($a->lookup('search_a',1,$ref,1002)['criteria']===$c);
denied(fn()=>$a->capture($p,$c,'search_a',1,1002),'SNAPSHOT_ALREADY_CAPTURED');
denied(fn()=>$a->lookup('search_b',1,$ref,1002),'STALE_SEARCH');
denied(fn()=>$a->lookup('search_a',2,$ref,1002),'STALE_SEARCH');
denied(fn()=>$a->lookup('search_a',1,'offer_'.str_repeat('0',64),1002),'OFFER_NOT_FOUND');
denied(fn()=>$a->lookup('search_a',1,$ref,999),'EXPIRED_SEARCH');
denied(fn()=>$a->lookup('search_a',1,$ref,1900),'EXPIRED_SEARCH');
// Roundtrip private session serialization, separate request instance.
$copy=json_decode(json_encode($s,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
$b=new AnyTourAndromedaOfferStore($copy);
ok($b->lookup('search_a',1,$ref,1003)['supplier_offer_id']===$r['id']);
$other=[]; $foreign=new AnyTourAndromedaOfferStore($other);
$foreign->begin('search_a',1,1000);
denied(fn()=>$foreign->lookup('search_a',1,$ref,1003),'SNAPSHOT_MISSING');
$a->begin('search_b',2,1100);
ok(strpos(json_encode($s),'private-fixture-offer')===false);
denied(fn()=>$a->lookup('search_a',1,$ref,1101),'STALE_SEARCH');
denied(fn()=>$a->begin('search_a',1,1101),'STALE_GENERATION');
$bad=$p; $bad['PAGE']=2;
denied(fn()=>$a->capture($bad,$c,'search_b',2,1101),'FIRST_PAGE_REQUIRED');
ok($s['snapshot']===null);
$bad=$p; $bad['PRICES']=[['invalid'=>true],$r,$r];
$out=$a->capture($bad,$c,'search_b',2,1102);
ok(count($out['offers'])===1 && count($out['rejected'])===2);
ok($a->lookup('search_b',2,$out['offers'][0]['offer_ref'],1103)['supplier_offer_id']===$r['id']);
$a->begin('search_c',3,1200);
$large=$p; $large['PRICES']=[];
for($i=0;$i<600;$i++){ $v=$r; $v['id']='id_'.$i; $v['hotel']=str_repeat('x',4096); $large['PRICES'][]=$v; }
denied(fn()=>$a->capture($large,$c,'search_c',3,1201),'SNAPSHOT_TOO_LARGE');
ok($s['snapshot']===null && $s['raw_ids']===[]);
echo "Andromeda offer store: $n checks passed\n";
