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
        'https://cdn.example.test/hotel-1.jpg',
        'http://unsafe.example.test/hotel-2.jpg',
        'https://cdn.example.test/hotel-1.jpg',
        'https://cdn.example.test/hotel-3.jpg',
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

echo "ANYTOUR_HOTEL_DETAILS_SMOKE_OK hotel=65108 images=2 description=1\n";
