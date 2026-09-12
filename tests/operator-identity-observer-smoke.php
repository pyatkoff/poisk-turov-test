<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/operator-identity-observer-v1.php';

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$hotels = [[
    'id'=>21477,'name'=>'MOVENPICK SOMA BAY',
    'country'=>['id'=>1,'name'=>'Египет'],'region'=>['id'=>2,'name'=>'Хургада'],'subRegion'=>['id'=>3,'name'=>'Сома Бей'],
    // Canonical /tours/search shape exposes coordinates at hotel top level.
    'latitude'=>26.85,'longitude'=>33.99,
    'tours'=>[
        ['id'=>'tour-1','operator'=>['id'=>10,'name'=>'ANEX'],'operatorLink'=>'https://online.anextour.ru/search?hotelCode=5844&foo=bar'],
        ['id'=>'tour-2','operator'=>['id'=>11,'name'=>'FUN&SUN']],
    ],
]];
$rows = v2_operator_identity_extract($hotels,['searchId'=>99,'countryId'=>1,'source'=>'user_search']);
check(count($rows)===2,'two operators retained');
check($rows[0]['hotel_id']===21477 && $rows[0]['operator_id']===10,'ids');
check($rows[0]['hotel_name']==='MOVENPICK SOMA BAY','hotel name');
check($rows[0]['operator_link_host']==='online.anextour.ru','host');
check($rows[0]['latitude']===26.85 && $rows[0]['longitude']===33.99,'top-level coords');
check(str_contains((string)$rows[0]['operator_link'],'hotelCode=5844'),'identity query retained');
check($rows[1]['operator_link']===null && $rows[1]['operator_link_host']===null,'linkless canonical row retained');

check(v2_operator_identity_safe_link('http://example.com/x')===null,'http rejected');
check(v2_operator_identity_safe_link('https://user:pass@example.com/x')===null,'credentials rejected');
check(v2_operator_identity_safe_link('https://example.com/x?access_token=abc')===null,'secret query rejected');
check(v2_operator_identity_safe_link('https://example.com/x?hotelCode=123')!==null,'hotel code allowed');

$badCountry = v2_operator_identity_extract($hotels,['searchId'=>99,'countryId'=>9]);
check($badCountry===[],'country mismatch rejected');

$unsafe = $hotels;
$unsafe[0]['tours'][0]['operatorLink']='https://example.com/x?access_token=abc';
$unsafeRows=v2_operator_identity_extract($unsafe,['searchId'=>99,'countryId'=>1]);
check(count($unsafeRows)===2 && $unsafeRows[0]['operator_link']===null,'unsafe link dropped without losing base identity');

$legacy = $hotels;
unset($legacy[0]['latitude'],$legacy[0]['longitude']);
$legacy[0]['common']=['latitude'=>26.851,'longitude'=>33.991];
$legacyRows=v2_operator_identity_extract($legacy,['searchId'=>99,'countryId'=>1]);
check($legacyRows[0]['latitude']===26.851 && $legacyRows[0]['longitude']===33.991,'legacy coord fallback');

$migration = file_get_contents(__DIR__.'/../v2/data/migrations/20260913-tour-operator-identity.sql');
check(is_string($migration) && str_contains($migration,'CREATE TABLE IF NOT EXISTS tour_operator_identity_observations'),'migration present');
check(str_contains($migration,'operator_link VARCHAR(2048) DEFAULT NULL'),'operator link nullable');
check(str_contains($migration,'operator_link_host VARCHAR(255) DEFAULT NULL'),'operator host nullable');
$observer = file_get_contents(__DIR__.'/../v2/data/operator-identity-observer-v1.php');
check(is_string($observer) && str_contains($observer,"implode('|', [$row['hotel_id'],$row['operator_id'],$row['tour_id']])"),'stable link-independent fingerprint');
check(str_contains($observer,'operator_link=COALESCE(VALUES(operator_link),operator_link)'),'later link enrichment');
$endpoint = file_get_contents(__DIR__.'/../v2/data/observe-search-v1.php');
check(is_string($endpoint) && str_contains($endpoint,"require_once __DIR__.'/operator-identity-observer-v1.php'"),'production observer wired');
check(str_contains($endpoint,'v2_data_observe_operator_identities($rows, $context)'),'identity capture invoked');
check(substr_count($endpoint,"v2_data_tv_get('/tours/search/'")===1,'no extra Tourvisor result request');

echo "operator_identity_observer_smoke: PASS\n";
