<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-tourvisor-fuel-resolver.php';

function base_offer(string $provider, string $price): array {
    return [
        'provider'=>$provider,'local_hotel_id'=>21753,'date'=>'2026-10-12','nights'=>7,'adults'=>2,'children'=>0,
        'meal_family'=>'ai','room_norm'=>'standard room','placement_norm'=>'dbl','currency'=>'RUB','price'=>$price,
    ];
}
function tv_offer(string $total, string $fuel): array {
    return base_offer('tourvisor',$total)+['fuel_charge'=>$fuel];
}
function child_offer(string $provider, string $price, array $ages): array {
    $row=base_offer($provider,$price);
    $row['children']=count($ages);
    $row['child_ages']=$ages;
    return $row;
}
function check(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); }

$r=anytour_anex_tv_fuel_resolve(base_offer('anex','119448'),[tv_offer('140294','20846'),tv_offer('162494','29184')]);
check($r['status']==='resolved_evidence','2637_status');
check($r['fuel_charge']==='20846','2637_fuel');
check($r['total_price_candidate']==='140294','2637_total');
check($r['runtime_arithmetic_authorized']===false,'2637_not_authorized');

$r=anytour_anex_tv_fuel_resolve(base_offer('anex','122366'),[tv_offer('143212','20846')]);
check($r['fuel_charge']==='20846'&&$r['total_price_candidate']==='143212','2637_second');

$r=anytour_anex_tv_fuel_resolve(base_offer('anex','133310'),[tv_offer('162494','29184')]);
check($r['fuel_charge']==='29184'&&$r['total_price_candidate']==='162494','1797_first');

$r=anytour_anex_tv_fuel_resolve(base_offer('anex','136541'),[tv_offer('165725','29184')]);
check($r['fuel_charge']==='29184'&&$r['total_price_candidate']==='165725','1797_second');

$r=anytour_anex_tv_fuel_resolve(base_offer('anex','119448'),[tv_offer('140294','20846'),tv_offer('150000','30552')]);
check($r['status']==='unresolved'&&$r['reason']==='ambiguous_fuel','ambiguity_fail_closed');

$wrongRoom=tv_offer('140294','20846');$wrongRoom['room_norm']='family room';
$r=anytour_anex_tv_fuel_resolve(base_offer('anex','119448'),[$wrongRoom]);
check($r['status']==='unresolved'&&$r['reason']==='no_exact_arithmetic_match','room_guard');

$wrongPlacement=tv_offer('140294','20846');$wrongPlacement['placement_norm']='trpl';
$r=anytour_anex_tv_fuel_resolve(base_offer('anex','119448'),[$wrongPlacement]);
check($r['status']==='unresolved','placement_guard');

$r=anytour_anex_tv_fuel_resolve(base_offer('anex','119448'),[tv_offer('140295','20846')]);
check($r['status']==='unresolved','arithmetic_guard');

$anexChild=child_offer('anex','119448',[4,11]);
$tvChild=child_offer('tourvisor','140294',[11,4])+['fuel_charge'=>'20846'];
$r=anytour_anex_tv_fuel_resolve($anexChild,[$tvChild]);
check($r['status']==='resolved_evidence'&&$r['fuel_charge']==='20846','child_ages_same_multiset');

$wrongAges=child_offer('tourvisor','140294',[5,11])+['fuel_charge'=>'20846'];
$r=anytour_anex_tv_fuel_resolve($anexChild,[$wrongAges]);
check($r['status']==='unresolved'&&$r['reason']==='no_exact_arithmetic_match','child_ages_guard');

$missingAges=$tvChild;unset($missingAges['child_ages']);
$r=anytour_anex_tv_fuel_resolve($anexChild,[$missingAges]);
check($r['status']==='unresolved','child_ages_missing_guard');

$badDirect=$anexChild;$badDirect['child_ages']=[4];
$r=anytour_anex_tv_fuel_resolve($badDirect,[$tvChild]);
check($r['status']==='unresolved'&&$r['reason']==='invalid_direct_offer','child_ages_count_guard');

$badDirect=$anexChild;$badDirect['child_ages']=[4,18];
$r=anytour_anex_tv_fuel_resolve($badDirect,[$tvChild]);
check($r['status']==='unresolved'&&$r['reason']==='invalid_direct_offer','child_ages_range_guard');

$badDirect=$anexChild;$badDirect['child_ages']=[4,11.0];
$r=anytour_anex_tv_fuel_resolve($badDirect,[$tvChild]);
check($r['status']==='unresolved'&&$r['reason']==='invalid_direct_offer','child_ages_type_guard');

check(anytour_anex_tv_fuel_cents('20846')===2084600,'cents_integer');
check(anytour_anex_tv_fuel_cents('20846.4')===2084640,'cents_decimal');
check(anytour_anex_tv_fuel_format(2084640)==='20846.40','format_decimal');

echo "ANEX Tourvisor fuel resolver smoke: PASS\n";
