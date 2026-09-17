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
        ['id'=>'tour-2','operator'=>['id'=>11,'name'=>'FUN&SUN'],'operatorLink'=>'https://fstravel.com/hotel/abc?hotels=30752'],
        ['id'=>'tour-3','operator'=>['id'=>11,'name'=>'FUN&SUN']],
    ],
]];
$rows = v2_operator_identity_extract($hotels,['searchId'=>99,'countryId'=>1,'source'=>'user_search']);
check(count($rows)===2,'one row per hotel/operator, not per tour');
check($rows[0]['hotel_id']===21477 && $rows[0]['operator_id']===10,'ids');
check($rows[0]['hotel_name']==='MOVENPICK SOMA BAY','hotel name');
check($rows[0]['operator_link_host']==='online.anextour.ru','host');
check($rows[0]['latitude']===26.85 && $rows[0]['longitude']===33.99,'coords');
check($rows[0]['native_id_type']==='hotelCode' && $rows[0]['native_id_value']==='5844','ANEX hotelCode evidence');
check($rows[1]['native_id_type']==='hotels' && $rows[1]['native_id_value']==='30752','single hotels evidence');
check($rows[1]['native_id_conflict']===0,'no false native conflict');

check(v2_operator_identity_safe_link('http://example.com/x')===null,'http rejected');
check(v2_operator_identity_safe_link('https://user:pass@example.com/x')===null,'credentials rejected');
check(v2_operator_identity_safe_link('https://example.com/x?access_token=abc')===null,'secret query rejected');
check(v2_operator_identity_safe_link('https://example.com/x?hotelCode=123')!==null,'hotel code allowed');
check(v2_operator_identity_native_id(v2_operator_identity_safe_link('https://example.com/x?HOTELLIST=8121'))===['type'=>'HOTELLIST','value'=>'8121'],'HOTELLIST extracted');
check(v2_operator_identity_native_id(v2_operator_identity_safe_link('https://example.com/x?f4=102610157319'))===['type'=>'f4','value'=>'102610157319'],'raw f4 retained');
check(v2_operator_identity_native_id(v2_operator_identity_safe_link('https://example.com/x?hotels=1,2'))===null,'multi-hotel query not treated as identity');

$badCountry = v2_operator_identity_extract($hotels,['searchId'=>99,'countryId'=>9]);
check($badCountry===[],'country mismatch rejected');

$missingLink = $hotels;
foreach ($missingLink[0]['tours'] as &$tour) unset($tour['operatorLink']);
unset($tour);
$linklessRows = v2_operator_identity_extract($missingLink,['searchId'=>99,'countryId'=>1]);
check(count($linklessRows)===2,'linkless identities retained');
check($linklessRows[0]['operator_link']===null && $linklessRows[0]['native_id_value']===null,'linkless row has nullable enrichment');
check(v2_operator_identity_fingerprint(21477,10)===v2_operator_identity_fingerprint(21477,10),'stable fingerprint');
check(v2_operator_identity_fingerprint(21477,10)!==v2_operator_identity_fingerprint(21477,11),'operator separates fingerprint');

$conflict = $hotels;
$conflict[0]['tours'][]=['id'=>'tour-4','operator'=>['id'=>11,'name'=>'FUN&SUN'],'operatorLink'=>'https://fstravel.com/hotel/abc?hotels=99999'];
$conflictRows = v2_operator_identity_extract($conflict,['searchId'=>100,'countryId'=>1]);
$fun = array_values(array_filter($conflictRows, static fn(array $row): bool => $row['operator_id']===11));
check(count($fun)===1 && $fun[0]['native_id_conflict']===1,'conflicting native ids retained as conflict, not authority');

echo "operator_identity_observer_smoke: PASS\n";
