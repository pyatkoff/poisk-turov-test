<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);
define('ANYTOUR_ANEX_CONCRETE_FUEL_LIBRARY_ONLY', true);
require __DIR__ . '/../scripts/diagnostics/anex_search3_paired_runner.php';
require __DIR__ . '/../scripts/diagnostics/anex_concrete_fuel_binding.php';

$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL:'.$message);++$checks;};
$spec=['experiment_id'=>ANEX_CONCRETE_FUEL_EXPERIMENT,'country'=>'Turkey','date'=>'2026-10-19','nights'=>7,
    'adults'=>2,'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB'];
$assert(anex_concrete_fuel_input($spec)===$spec,'exact new scenario');
$bad=$spec;$bad['date']='2026-10-12';
try{anex_concrete_fuel_input($bad);$assert(false,'old date accepted');}catch(RuntimeException $e){$assert($e->getMessage()==='CONCRETE_FUEL_INVALID_INPUT','old case not replayed');}
$assert(anex_concrete_fuel_ai('AI-WITHOUT ALCOHOL'),'ai family');
$assert(anex_concrete_fuel_decimal(14592.2)==='14592.2'&&anex_concrete_fuel_decimal(-1)===null,'decimal facts');
$rows=[['id'=>3,'currencyISO'=>'RUB'],['id'=>4,'currencyISO'=>'USD']];
$assert(anex_concrete_fuel_dictionary_id($rows,['RUB','RUR'])===3,'unique dictionary');

$offer=['kind'=>'group_minimum','hotel'=>['external_id'=>'25084'],'checkin'=>'2026-10-19','nights'=>7,'adults'=>2,'children'=>0,
    'meal'=>'AI','room'=>'STANDARD ROOM','hotel_place'=>'DBL','price'=>['amount'=>'119448','currency'=>'RUB'],'converted_price'=>null,
    'supplier_tour_program_id'=>'2637','supplier_currency_id'=>'3','availability'=>['hotel'=>'Y']];
$summary=anex_concrete_fuel_offer_summary($offer);
$assert($summary!==null&&$summary['kind']==='group_minimum'&&$summary['price']==='119448','group minimum retained as group');
$offer['kind']='concrete';$offer['price']['amount']='133310';$concrete=anex_concrete_fuel_offer_summary($offer);
$assert($concrete!==null&&$concrete['kind']==='concrete'&&$concrete['supplier_tour_program_id']==='2637','concrete program retained');
$wrong=$offer;$wrong['hotel']['external_id']='1';
$assert(anex_concrete_fuel_offer_summary($wrong)===null,'wrong hotel rejected');

$routes=anex_concrete_fuel_routes(['routes'=>[['date'=>'2026-10-19','from'=>'Moscow','to'=>'Izmir','options'=>[[
    'name'=>'SU123','carrier'=>'Airline','departure'=>['airport_code'=>'SVO','time'=>'10:00'],
    'arrival'=>['airport_code'=>'ADB','time'=>'15:00'],'itinerary_details_available'=>true,
    'classes'=>[['name'=>'economy','availability'=>'Y','baggage'=>'20']]
]]]]]);
$assert(count($routes)===1&&$routes[0]['options'][0]['departure_airport']==='SVO'&&$routes[0]['options'][0]['arrival_airport']==='ADB','flight route preserved');
$assert(!array_key_exists('classes',$routes[0]['options'][0]),'unneeded flight detail stripped');

$tmp=sys_get_temp_dir().'/anex-concrete-fuel-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
try{
    $value=['status'=>'completed','converted'=>14592.2,'zero'=>0.0];
    $bytes=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    anex_concrete_fuel_save($tmp.'/checkpoint.json',$value);
    $assert(file_get_contents($tmp.'/checkpoint.json')===$bytes,'checkpoint exact bytes');
}finally{foreach(scandir($tmp) as $name)if($name!=='.'&&$name!=='..')unlink($tmp.'/'.$name);rmdir($tmp);}

$php=file_get_contents(__DIR__.'/../scripts/diagnostics/anex_concrete_fuel_binding.php');
foreach(['->bron(','bron_ticket','broninit(','->calc(','INSERT INTO anex_hotel_search_mappings','UPDATE anex_hotel_search_mappings'] as $forbidden)$assert(strpos($php,$forbidden)===false,'forbidden '.$forbidden);
$assert(strpos($php,'->expand(')!==false&&strpos($php,'->flights(')!==false,'existing concrete runtime consumers used');
$assert(strpos($php,"'tour_flights_requested'=>false")!==false,'no Tourvisor flight refresh yet');
echo "ANEX concrete fuel binding smoke: {$checks} checks passed; network=0.\n";
