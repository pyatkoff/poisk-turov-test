<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

$context=[
    'checkin_begin'=>'2026-10-12','checkin_end'=>'2026-10-12',
    'nights_from'=>7,'nights_till'=>7,'adults'=>2,'children'=>0,'child_ages'=>[],
];
$base=[
    'id'=>'CATCLAIM-1','hotelKey'=>25084,'hotel'=>'Fixture Hotel',
    'checkIn'=>'20261012','checkOut'=>'20261019','nights'=>7,'adult'=>2,'child'=>0,
    'grouped'=>false,'price'=>'100000','currency'=>'RUB','packetType'=>0,
    'meal'=>'AI','room'=>'Standard Room','htPlace'=>'DBL',
    'tourKey'=>778,'currencyKey'=>3,'hotelAvailability'=>'YYYY',
    'freights'=>['econom'=>['in'=>'Y','out'=>'Y']],
    'bron'=>true,
];
$resolver=static function(string $provider,$external):?int{
    return $provider==='anex_online' && (string)$external==='25084' ? 21753 : null;
};

$regular=anytour_anex_normalizer_offer($base+['freightExternal'=>'Y'],$context,$resolver,[]);
$charter=anytour_anex_normalizer_offer(array_replace($base,['id'=>'CATCLAIM-2','freightExternal'=>'N']),$context,$resolver,[]);
$unknown=anytour_anex_normalizer_offer(array_replace($base,['id'=>'CATCLAIM-3','freightExternal'=>'1']),$context,$resolver,[]);
if(!is_array($regular)||$regular['flight_type']!=='regular')throw new RuntimeException('REGULAR_HANDOFF');
if(!is_array($charter)||$charter['flight_type']!=='charter')throw new RuntimeException('CHARTER_HANDOFF');
if(!is_array($unknown)||$unknown['flight_type']!==null)throw new RuntimeException('UNKNOWN_MUST_NOT_GUESS');

$metadata=[21753=>[
    'id'=>21753,'name'=>'Fixture Hotel','country_id'=>4,'country_name'=>'Turkey',
    'region_id'=>1,'region_name'=>'Side','subregion_id'=>1,'subregion_name'=>'',
    'category'=>4,'rating'=>4.5,'primary_image_url'=>null,'description'=>null,'address'=>null,
]];
$params=[
    'countryId'=>4,'hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],
    'hotelRating'=>'','hotelCategory'=>'','meal'=>'','priceFrom'=>'','priceTo'=>'',
];
$projected=anytour_anex_search3_project([$regular,$charter,$unknown],$metadata,$params,'a234567890abcdef1234567890abcdef');
if(count($projected)!==1||count($projected[0]['tours'])!==3)throw new RuntimeException('PROJECT_COUNT');
$types=array_column($projected[0]['tours'],'flight_type');
if($types!==['regular','charter',null])throw new RuntimeException('PROJECT_TYPES');

echo "ANEX_FLIGHT_TYPE_HANDOFF_OK regular=Y charter=N unknown=null projected=3\n";
