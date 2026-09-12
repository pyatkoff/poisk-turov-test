<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_ADDITIONAL_PARITY_LIBRARY_ONLY', true);
require __DIR__ . '/../scripts/diagnostics/anex_additional_parity_v3.php';

$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL:'.$message);++$checks;};
$anex=[[
    'provider'=>'anex','local_hotel_id'=>1039,'external_hotel_id'=>'33118','date'=>'2026-10-12','nights'=>7,
    'adults'=>2,'children'=>0,'meal_family'=>'ai','room_norm'=>'standard room','placement_norm'=>'dbl','price'=>'120000','currency'=>'RUB',
    'fuel_charge'=>null,'supplier_tour_program_id'=>'778','supplier_currency_id'=>'3','fuel_inclusion_verified'=>false,'final_price_verified'=>false,
]];
$tv=[[
    'provider'=>'tourvisor','local_hotel_id'=>1039,'external_hotel_id'=>'1039','date'=>'2026-10-12','nights'=>7,
    'adults'=>2,'children'=>0,'meal_family'=>'ai','room_norm'=>'standard room','placement_norm'=>'dbl','price'=>'145000','currency'=>'RUB',
    'fuel_charge'=>'25000','supplier_tour_program_id'=>null,'supplier_currency_id'=>null,'fuel_inclusion_verified'=>false,'final_price_verified'=>false,
]];
$pairs=anex_additional_parity_align($anex,$tv);
$assert(count($pairs)===1,'one aligned pair');
$assert(($pairs[0]['basis']??null)==='same_current_local_hotel_date_party_ai_and_exact_room','alignment basis');
$assert(($pairs[0]['identical_supplier_package_verified']??null)===false,'no package overclaim');
$assert(($pairs[0]['anex']['supplier_tour_program_id']??null)==='778','program retained privately');
$bad=$anex;$bad[0]['supplier_tour_program_id']=null;
$assert(anex_additional_parity_align($bad,$tv)===[],'missing program is unusable');
$bad=$tv;$bad[0]['fuel_charge']=null;
$assert(anex_additional_parity_align($anex,$bad)===[],'missing reported fuel is not zero');
$assert(anex_additional_parity_provider_id('03')===null&&anex_additional_parity_provider_id(3)==='3','opaque id guard');
$assert(anex_additional_parity_ai('AI-WITHOUT ALCOHOL')===true,'ai family');
$assert(anex_additional_parity_money('0',true)==='0'&&anex_additional_parity_money('0')===null,'zero semantics');

echo "ANEX additional parity v3 smoke: {$checks} checks passed; network=0.\n";
