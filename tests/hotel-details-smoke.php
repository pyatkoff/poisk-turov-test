<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/hotel-details-v1.php';

$payload = [[
    'id' => 65108,
    'name' => 'The Westin Maldives Miriandhoo Resort',
    'category' => 5,
    'rating' => 4.8,
    'type' => 1,
    'country' => ['id'=>8,'name'=>'Мальдивы'],
    'region' => ['id'=>123,'name'=>'Baa Atoll'],
    'subRegion' => ['id'=>456,'name'=>'Miriandhoo'],
    'common' => [
        'description' => 'Описание отеля',
        'address' => 'Baa Atoll',
        'latitude' => 5.123,
        'longitude' => 73.456,
    ],
    'images' => [
        '//cdn.example.test/hotel-1.jpg',
        'http://unsafe.example.test/hotel-2.jpg',
        'https://cdn.example.test/hotel-1.jpg',
        '//cdn.example.test/hotel-3.jpg',
    ],
    'infrastructure' => ['beach'=>'Песчаный','territory'=>'Бассейн'],
    'meals' => ['description'=>'Завтрак','list'=>'BB'],
    'services' => ['free'=>'Wi-Fi'],
    'roomTypes' => 'Deluxe, Villa',
]];

$hotel = v2_hotel_detail_object($payload);
if ($hotel === null || (int)$hotel['id'] !== 65108) throw new RuntimeException('hotel identity normalization failed');
$detail = v2_hotel_detail_normalized($hotel);
if ($detail['hotel_id'] !== 65108 || $detail['country_id'] !== 8 || $detail['region_id'] !== 123) throw new RuntimeException('hotel geo normalization failed');
if ($detail['description'] !== 'Описание отеля') throw new RuntimeException('hotel description missing');
if ($detail['primary_image_url'] !== 'https://cdn.example.test/hotel-1.jpg') throw new RuntimeException('primary image selection failed');
if (count($detail['images']) !== 2) throw new RuntimeException('hotel image safety/dedupe failed');
if (!str_contains((string)$detail['services_json'], 'Wi-Fi')) throw new RuntimeException('hotel services persistence failed');
if ($detail['room_types'] !== 'Deluxe, Villa') throw new RuntimeException('hotel room types missing');
if (v2_hotel_detail_https_url('javascript:alert(1)') !== null) throw new RuntimeException('unsafe hotel image URL accepted');
if (v2_hotel_detail_https_url('//cdn.example.test/hotel.jpg') !== 'https://cdn.example.test/hotel.jpg') throw new RuntimeException('Tourvisor scheme-relative hotel image lost');

foreach ([
    '///cdn.example.test/hotel.jpg',
    '//user:secret@cdn.example.test/hotel.jpg',
    'https://user@cdn.example.test/hotel.jpg',
    "//cdn.example.test/line\nbreak.jpg",
    "\t//cdn.example.test/hotel.jpg",
    '//cdn.example.test\\@other.example.test/hotel.jpg',
    'https://cdn.example.test/with space.jpg',
    '//', '/hotel.jpg', 'http://cdn.example.test/hotel.jpg',
    ['url'=>'https://cdn.example.test/hotel.jpg'], null,
] as $unsafe) {
    if (v2_hotel_detail_https_url($unsafe) !== null) throw new RuntimeException('unsafe or malformed hotel image accepted');
}
$limited = v2_hotel_detail_images(['images'=>array_map(
    static fn(int $i): string => '//cdn.example.test/hotel-' . $i . '.jpg', range(1, 101)
)]);
if (count($limited) !== 100 || $limited[99] !== 'https://cdn.example.test/hotel-100.jpg') throw new RuntimeException('normalized hotel image limit failed');
if (v2_hotel_detail_https_url('//cdn.example.test/' . str_repeat('a', 2026)) !== null) throw new RuntimeException('normalized image URL length limit failed');

echo "ANYTOUR_HOTEL_DETAILS_SMOKE_OK hotel=65108 images=2 description=1\n";
