<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/andromeda-selected-tour-readiness.php';

$checks=0;
function ready_check(bool $ok): void { global $checks; ++$checks; if(!$ok)throw new RuntimeException('ready_check_'.$checks); }
function package_fixture($external, bool $price=true): array {
    $value=['status'=>'package_bound_unquoted','identity_verified'=>true,'package_binding_verified'=>true,
        'quote_verified'=>false,'selection_enabled'=>false,'price_status'=>$price?'package_unverified':'unknown',
        'requires_external_flights'=>$external];
    if($price)$value['price']=['amount'=>'123456.70','currency'=>'RUB','status'=>'package_unverified'];
    return $value;
}

$flights=AnyTourAndromedaSelectedTourReadiness::fromPackage(package_fixture(true));
ready_check($flights['state']==='needs_flights'&&$flights['next_supplier_step']==='get_flights');
ready_check($flights['flight_details']['external_lookup_required']===true);
ready_check($flights['package_price']['amount']==='123456.70'&&$flights['package_price_status']==='package_unverified');
ready_check($flights['final_price_verified']===false&&$flights['selection_enabled']===false&&$flights['booking_enabled']===false);

$calc=AnyTourAndromedaSelectedTourReadiness::fromPackage(package_fixture(false));
ready_check($calc['state']==='needs_calc'&&$calc['next_supplier_step']==='calc');
ready_check($calc['flight_details']['external_lookup_required']===false);
ready_check($calc['final_price_verified']===false);

$unknown=AnyTourAndromedaSelectedTourReadiness::fromPackage(package_fixture(null));
ready_check($unknown['state']==='transport_requirement_unknown'&&$unknown['next_supplier_step']===null);
ready_check($unknown['package_price_status']==='package_unverified'&&$unknown['final_price_verified']===false);

$noPrice=AnyTourAndromedaSelectedTourReadiness::fromPackage(package_fixture(false,false));
ready_check($noPrice['state']==='needs_calc'&&$noPrice['package_price']===null&&$noPrice['package_price_status']==='unknown');

$notBound=package_fixture(false);$notBound['status']='package_captured_unquoted';$notBound['private_package']=['secret'=>'must-not-leak'];
$blocked=AnyTourAndromedaSelectedTourReadiness::fromPackage($notBound);
ready_check($blocked['state']==='package_not_ready'&&$blocked['next_supplier_step']===null);
ready_check(!str_contains(json_encode($blocked,JSON_THROW_ON_ERROR),'must-not-leak'));

$badPrice=package_fixture(false);$badPrice['price']=['amount'=>'0','currency'=>'RUB','status'=>'package_unverified'];
$bad=AnyTourAndromedaSelectedTourReadiness::fromPackage($badPrice);
ready_check($bad['state']==='needs_calc'&&$bad['package_price']===null&&$bad['final_price_verified']===false);

echo 'Andromeda selected tour readiness: '.$checks." checks passed; supplier/calc/get_flights/booking=0.\n";
