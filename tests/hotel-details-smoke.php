<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/hotel-details-v1.php';
require_once __DIR__ . '/../v2/data/tourvisor-client-v1.php';

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
if (($detail['generic_product'] ?? true) !== false) throw new RuntimeException('concrete hotel misclassified as generic product');
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

foreach (['Fortuna Kusadasi','FORTUNA MARMARIS','Roulette Phuket','Фортуна Кемер','Рулетка 5*'] as $genericName) {
    if (!v2_hotel_detail_is_generic_product_name($genericName)) throw new RuntimeException('generic accommodation product not classified');
}
if (v2_hotel_detail_is_generic_product_name('Hotel Fortuna Beach')) throw new RuntimeException('concrete hotel name falsely classified by non-leading token');
$generic = v2_hotel_detail_normalized([
    'id'=>42576,'name'=>'FORTUNA MARMARIS','country'=>['id'=>4,'name'=>'Турция'],
    'common'=>['latitude'=>0,'longitude'=>0],
]);
if (($generic['generic_product'] ?? false) !== true || $generic['latitude'] !== null || $generic['longitude'] !== null) {
    throw new RuntimeException('generic accommodation product retained hotel coordinates');
}

putenv('TOURVISOR_HTTP_MAX_ATTEMPTS');
if (v2_data_tv_max_attempts() !== 4) throw new RuntimeException('historical Tourvisor retry default changed');
putenv('TOURVISOR_HTTP_MAX_ATTEMPTS=1');
if (v2_data_tv_max_attempts() !== 1) throw new RuntimeException('Tourvisor request budget retry cap not applied');
putenv('TOURVISOR_HTTP_MAX_ATTEMPTS=5');
try {
    v2_data_tv_max_attempts();
    throw new RuntimeException('invalid Tourvisor retry cap accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'invalid Tourvisor retry cap accepted') throw $e;
}
putenv('TOURVISOR_HTTP_MAX_ATTEMPTS');
if (v2_data_tv_http_attempt_count() !== 0) throw new RuntimeException('Tourvisor HTTP attempt counter mutated without a request');

echo "ANYTOUR_HOTEL_DETAILS_SMOKE_OK hotel=65108 images=2 description=1 fortuna_guard=1 quota_guard=1\n";