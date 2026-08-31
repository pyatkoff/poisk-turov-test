<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/direct-feed-v1.php';

$rows = [
    [
        'hotel_id'=>106,'hotel_name'=>'ALADDIN BEACH RESORT','hotel_category'=>4,
        'country_id'=>1,'country_name'=>'Египет','region_id'=>7,'departure_id'=>1,
        'departure_date'=>'2026-12-17','nights'=>7,'price'=>126260,'picture_url'=>'https://example.com/hotel.jpg',
    ],
    [
        'hotel_id'=>106,'hotel_name'=>'ALADDIN BEACH RESORT','hotel_category'=>4,
        'country_id'=>1,'country_name'=>'Египет','region_id'=>7,'departure_id'=>1,
        'departure_date'=>'2026-12-18','nights'=>8,'price'=>129000,
    ],
    [
        'hotel_id'=>220,'hotel_name'=>'TEST HOTEL','hotel_category'=>5,
        'country_id'=>4,'country_name'=>'Турция','region_id'=>42,'departure_id'=>1,
        'departure_date'=>'2026-09-20','nights'=>10,'price'=>155500,
    ],
];

$xml = v2_direct_feed_render($rows, '2026-08-31 22:30');
$fail = static function (string $message): void { fwrite(STDERR, $message."\n"); exit(1); };

if (v2_direct_offer_id(106) !== 'hotel_106') $fail('offer id contract failed');
if (substr_count($xml, '<offer ') !== 2) $fail('one offer per hotel expected');
if (!str_contains($xml, '<offer id="hotel_106" available="true">')) $fail('hotel_106 missing');
if (!str_contains($xml, '<offer id="hotel_220" available="true">')) $fail('hotel_220 missing');
if (!str_contains($xml, '<price>126260</price>')) $fail('lowest hotel price not selected');
if (!str_contains($xml, 'hotel=106')) $fail('hotel landing parameter missing');
if (!str_contains($xml, 'country=1')) $fail('country landing parameter missing');
if (!str_contains($xml, 'dateFrom=2026-12-17')) $fail('departure date landing parameter missing');
if (str_contains($xml, '<oldprice>')) $fail('synthetic oldprice must not be emitted');
if (!str_contains($xml, '<currencyId>RUB</currencyId>')) $fail('currency missing');

fwrite(STDOUT, "DIRECT_OFFER_FEED_CONTRACT_OK offers=2 id=hotel_<TourvisorHotelId>\n");
