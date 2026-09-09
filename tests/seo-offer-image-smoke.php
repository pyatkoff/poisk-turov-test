<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/v2/seo-offer-snapshot-v1.php';

$offer=[
    'hotelName'=>'Rixos Test Hotel',
    'hotelImage'=>'//cdn.example.com/hotel/test.jpg',
    'price'=>123456,
    'currency'=>'RUB',
];
$url=v2_seo_offer_image_url($offer);
if($url!=='https://cdn.example.com/hotel/test.jpg')exit(1);
$markup=v2_seo_offer_image_markup($offer);
if(!str_contains($markup,'class="sp-offer-media"'))exit(2);
if(!str_contains($markup,'loading="lazy"'))exit(3);
if(!str_contains($markup,'alt="Rixos Test Hotel"'))exit(4);
if(!str_contains(v2_seo_offer_price_markup($offer),'sp-offer-media'))exit(5);
if(v2_seo_offer_image_url(['hotelImage'=>'javascript:alert(1)'])!==null)exit(6);
if(v2_seo_offer_image_url(['hotelImage'=>''])!==null)exit(7);

$observer=(string)file_get_contents(dirname(__DIR__).'/v2/data/price-observer-v1.php');
$builder=(string)file_get_contents(dirname(__DIR__).'/v2/data/build-seo-offer-snapshots-v1.php');
$migration=(string)file_get_contents(dirname(__DIR__).'/v2/data/migrate-hotel-media-v1.php');
if(!str_contains($observer,"['picturelink']"))exit(8);
if(!str_contains($observer,'primary_image_url'))exit(9);
if(!str_contains($builder,"'hotelImage'"))exit(10);
if(!str_contains($builder,'h.primary_image_url AS hotel_image_url'))exit(11);
if(!str_contains($migration,'ADD COLUMN primary_image_url'))exit(12);

echo "SEO_OFFER_IMAGE_OK source=search_result storage=hotel_id snapshot=1 render=1\n";
