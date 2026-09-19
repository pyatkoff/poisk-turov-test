<?php
declare(strict_types=1);
require __DIR__ . '/../scripts/diagnostics/hotel_match_common4_operator_binding_v1.php';

function hm_common4_test(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

$tv = ['operators'=>[
    ['id'=>13,'name'=>'ANEX'],
    ['id'=>51,'name'=>'FUN&SUN'],
    ['id'=>72,'name'=>'Библио Глобус'],
    ['id'=>88,'name'=>'Интурист'],
    ['id'=>999,'name'=>'Pegas'],
]];
$samo = ['OPERATORS'=>[
    ['operatorKey'=>'A17','lName'=>'Анекс Тур'],
    ['operatorKey'=>'F4','lName'=>'FUN SUN'],
    ['operatorKey'=>'BG9','lName'=>'Biblio-Globus'],
    ['operatorKey'=>'I8','lName'=>'NTK Intourist'],
]];

$r = hm_common4_resolve_pair($tv, $samo);
hm_common4_test($r['tourvisor']['anex']['provider_operator_id']==='13', 'tv anex');
hm_common4_test($r['tourvisor']['funsun']['provider_operator_id']==='51', 'tv funsun');
hm_common4_test($r['samo']['biblio_globus']['provider_operator_id']==='BG9', 'samo biblio');
hm_common4_test($r['samo']['intourist']['provider_operator_name']==='NTK Intourist', 'raw name');
hm_common4_test($r['tourvisor']['anex']['namespace']==='tourvisor' && $r['samo']['anex']['namespace']==='samo', 'namespaces');

$one = hm_common4_resolve($tv, 'tourvisor', ['biblio_globus']);
hm_common4_test(array_keys($one)===['biblio_globus'], 'subset');

$ambiguous = $tv;
$ambiguous['operators'][] = ['id'=>73,'name'=>'BiblioGlobus'];
try {
    hm_common4_resolve($ambiguous, 'tourvisor');
    throw new RuntimeException('ambiguous accepted');
} catch (RuntimeException $e) {
    hm_common4_test($e->getMessage()==='operator_binding_biblio_globus_count_2', 'ambiguous');
}

$missing = $tv;
$missing['operators'] = array_values(array_filter($missing['operators'], fn(array $x): bool => $x['id']!==88));
try {
    hm_common4_resolve($missing, 'tourvisor');
    throw new RuntimeException('missing accepted');
} catch (RuntimeException $e) {
    hm_common4_test($e->getMessage()==='operator_binding_intourist_count_0', 'missing');
}

$bad = $tv;
$bad['operators'][0]['id'] = 'ANEX';
try {
    hm_common4_resolve($bad, 'tourvisor');
    throw new RuntimeException('bad tv id accepted');
} catch (RuntimeException $e) {
    hm_common4_test($e->getMessage()==='operator_binding_anex_count_0', 'tv numeric only');
}

$collision = ['operators'=>[
    ['id'=>13,'name'=>'ANEX'],
    ['id'=>13,'name'=>'FUN&SUN'],
    ['id'=>72,'name'=>'Библио Глобус'],
    ['id'=>88,'name'=>'Интурист'],
]];
try {
    hm_common4_resolve($collision, 'tourvisor');
    throw new RuntimeException('collision accepted');
} catch (RuntimeException $e) {
    hm_common4_test($e->getMessage()==='operator_id_collision:13', 'id collision');
}

hm_common4_test(hm_common4_normalize_name(' FUN&SUN ')==='fun and sun', 'ampersand');
echo "hotel-match-common4-operator-binding-v1: PASS\n";
