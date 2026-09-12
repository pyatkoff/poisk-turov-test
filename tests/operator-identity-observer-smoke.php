<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/operator-identity-observer-v1.php';

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$hotels = [[
    'id'=>21477,'name'=>'MOVENPICK SOMA BAY',
    'country'=>['id'=>1,'name'=>'Египет'],'region'=>['id'=>2,'name'=>'Хургада'],'subRegion'=>['id'=>3,'name'=>'Сома Бей'],
    'common'=>['latitude'=>26.85,'longitude'=>33.99],
    'tours'=>[
        ['id'=>'tour-1','operator'=>['id'=>10,'name'=>'ANEX'],'operatorLink'=>'https://online.anextour.ru/search?hotelCode=5844&foo=bar'],
        ['id'=>'tour-2','operator'=>['id'=>11,'name'=>'FUN&SUN'],'operatorLink'=>'https://fstravel.com/hotel/abc'],
    ],
]];
$rows = v2_operator_identity_extract($hotels,['searchId'=>99,'countryId'=>1,'source'=>'user_search']);
check(count($rows)===2,'two operators');
check($rows[0]['hotel_id']===21477 && $rows[0]['operator_id']===10,'ids');
check($rows[0]['hotel_name']==='MOVENPICK SOMA BAY','hotel name');
check($rows[0]['operator_link_host']==='online.anextour.ru','host');
check($rows[0]['latitude']===26.85 && $rows[0]['longitude']===33.99,'coords');
check(str_contains($rows[0]['operator_link'],'hotelCode=5844'),'identity query retained');

check(v2_operator_identity_safe_link('http://example.com/x')===null,'http rejected');
check(v2_operator_identity_safe_link('https://user:pass@example.com/x')===null,'credentials rejected');
check(v2_operator_identity_safe_link('https://example.com/x?access_token=abc')===null,'secret query rejected');
check(v2_operator_identity_safe_link('https://example.com/x?hotelCode=123')!==null,'hotel code allowed');

$badCountry = v2_operator_identity_extract($hotels,['searchId'=>99,'countryId'=>9]);
check($badCountry===[],'country mismatch rejected');

$missingLink = $hotels;
unset($missingLink[0]['tours'][0]['operatorLink'],$missingLink[0]['tours'][1]['operatorLink']);
check(v2_operator_identity_extract($missingLink,['searchId'=>99,'countryId'=>1])===[],'missing links ignored');

$migration = file_get_contents(__DIR__.'/../v2/data/migrations/20260913-tour-operator-identity.sql');
check(is_string($migration) && str_contains($migration,'CREATE TABLE IF NOT EXISTS tour_operator_identity_observations'),'migration present');
$endpoint = file_get_contents(__DIR__.'/../v2/data/observe-search-v1.php');
check(is_string($endpoint) && str_contains($endpoint,"require_once __DIR__.'/operator-identity-observer-v1.php'"),'production observer wired');
check(str_contains($endpoint,'v2_data_observe_operator_identities($rows, $context)'),'identity capture invoked');
check(substr_count($endpoint,"v2_data_tv_get('/tours/search/'")===1,'no extra Tourvisor result request');

echo "operator_identity_observer_smoke: PASS\n";
