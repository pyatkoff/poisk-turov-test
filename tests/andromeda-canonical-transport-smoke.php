<?php
declare(strict_types=1);

$root=$argv[1]??dirname(__DIR__);
require_once $root.'/app/integrations/andromeda-normalizer.php';

$criteria=[
    'TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260915','CHECKIN_END'=>'20260915',
    'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,
];
$row=[
    'id'=>'opaque-offer-1','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>36700,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'15.09.2026','nights'=>'7',
    'hotel'=>'ARES CITY (EX. KAMI HOTEL)','operator'=>'FUN&SUN','meal'=>'Bed & Breakfast','mealKey'=>'1','andrMealKey'=>1,
    'room'=>'standard','htplace'=>'DBL','adult'=>'2','child'=>'0',
    'tour'=>'Moscow Antalya','tourKey'=>'4983','program'=>'ANEX Light','programKey'=>'1552',
    'spo'=>'AYT-TEST-MOW','spoKey'=>'45143187','freightExternal'=>'N','departureTimes'=>'00:15, 06:50',
];
$page=AnyTourAndromedaNormalizer::page(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]],$criteria,'canonical_transport_v1',1);
if(count($page['offers'])!==1||$page['rejected']!==[])throw new RuntimeException('offer rejected');
$offer=$page['offers'][0];
if(($offer['meal']['canonical_key']??null)!=='bb'||($offer['meal']['label']??null)!=='BB · Завтрак')throw new RuntimeException('meal not canonical');
if(($offer['meal']['raw_label']??null)!=='Bed & Breakfast')throw new RuntimeException('meal raw evidence lost');
if(($offer['room']??null)!=='Standard'||($offer['room_raw']??null)!=='standard'||($offer['room_normalized']??null)!=='standard')throw new RuntimeException('room not canonical');
if(($offer['placement']??null)!=='Dbl'||($offer['placement_raw']??null)!=='DBL')throw new RuntimeException('placement evidence lost');
$t=$offer['transport_context']??[];
if(($t['tour_ref']??null)!=='4983'||($t['program_ref']??null)!=='1552'||($t['spo_ref']??null)!=='45143187')throw new RuntimeException('PRICE transport refs lost');
if(($t['tour_label']??null)!=='Moscow Antalya'||($t['program_label']??null)!=='ANEX Light'||($t['departure_times_reported']??null)!=='00:15, 06:50')throw new RuntimeException('PRICE transport labels lost');
if(($t['freight_external']??null)!==false||($t['surcharge_status']??null)!=='unknown'||($t['surcharge']??'not-null')!==null||($t['arithmetic_applied']??null)!==false)throw new RuntimeException('unverified surcharge became arithmetic');

$bb=AnyTourThreeProviderMealFamily::normalize('BB - Только завтрак');
$english=AnyTourThreeProviderMealFamily::normalize('Bed & Breakfast');
if($bb['canonical_key']!=='bb'||$english['canonical_key']!=='bb'||$bb['display_label']!==$english['display_label'])throw new RuntimeException('BB synonyms diverged');

$standardA=AnyTourThreeProviderRoomPlacement::normalize('tourvisor','Standard',null);
$standardB=AnyTourThreeProviderRoomPlacement::normalize('andromeda','standard',null);
if($standardA['room']['normalized']!==$standardB['room']['normalized']||$standardA['room']['display_label']!==$standardB['room']['display_label'])throw new RuntimeException('room case diverged');

echo "Andromeda canonical labels and retained PRICE transport refs passed\n";
