<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_THREE_PRICE_LIBRARY_ONLY', true);
require __DIR__ . '/../scripts/diagnostics/anex_search3_three_source_price.php';

$checks=0;
function three_check(bool $ok): void { global $checks; ++$checks; if(!$ok)throw new RuntimeException('three_price_check_'.$checks); }

$base=['experiment_id'=>ANEX_THREE_PRICE_EXPERIMENT,'case_id'=>'anex','country'=>'Turkey','date'=>'2026-09-20','nights'=>7,
    'adults'=>2,'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB'];
three_check(anex_three_price_input($base)===$base);
foreach(['anex','andromeda','tourvisor'] as $case){$v=$base;$v['case_id']=$case;three_check(anex_three_price_input($v)['case_id']===$case);}
foreach([
    ['date','2026-09-21'],['nights',8],['adults',3],['meal_family','bb'],['currency','USD'],['country','Egypt'],['case_id','other']
] as [$key,$value]){$v=$base;$v[$key]=$value;try{anex_three_price_input($v);three_check(false);}catch(RuntimeException $e){three_check($e->getMessage()==='THREE_PRICE_INVALID_INPUT');}}
$v=$base;$v['extra']=1;try{anex_three_price_input($v);three_check(false);}catch(RuntimeException $e){three_check(true);}

foreach(['AI','All Inclusive','UAI','Ультра все включено','все включено без алкоголя'] as $meal)three_check(anex_three_price_meal($meal)==='ai');
foreach(['BB','HB','RO',''] as $meal)three_check(anex_three_price_meal($meal)===null);
three_check(anex_three_price_decimal('123456.78')==='123456.78');
foreach(['0','-1','1e5','1,2','1.234',NAN] as $bad)three_check(anex_three_price_decimal($bad)===null);

$row=anex_three_price_offer('tourvisor',6319,6319,'2026-09-20',7,2,0,'AI','STANDARD ROOM','DBL','123456.00','RUB',2500);
three_check(is_array($row)&&$row['room_norm']==='standard room'&&$row['placement_norm']==='dbl');
three_check($row['fuel_charge']==='2500'&&$row['fuel_inclusion_verified']===false&&$row['final_price_verified']===false);
three_check(anex_three_price_offer('anex',6319,8121,'2026-09-20',7,2,0,'BB','STANDARD','DBL','100000','RUB')===null);
three_check(anex_three_price_offer('andromeda',6319,'8121','2026-09-20',7,2,0,'AI','STANDARD','DBL','100000','USD')===null);

$initial=['schema_version'=>1,'status'=>'blocked','offers'=>[],'supplier_effect'=>'unknown_after_reservation','reused'=>false,'case_id'=>'anex'];
$subject=['local_hotel_id'=>6319,'anex_hotel_id'=>8121,'andromeda_hotel_id'=>'9001'];
$result=['offers'=>[['provider'=>'anex','price'=>'100000']],'requests'=>7,'source_price_semantics'=>'search_price_unverified'];
$completed=anex_three_price_completed_result($initial,$subject,$result,false);
three_check($completed['status']==='completed');
three_check($completed['supplier_effect']==='read_only_search_completed');
three_check($completed['offers']===$result['offers']);
three_check($completed['subject']===$subject);
three_check($completed['details']===['requests'=>7,'source_price_semantics'=>'search_price_unverified']);
three_check($completed['reused']===false);
$reused=anex_three_price_reused_result($completed);
three_check($reused['status']==='completed'&&$reused['reused']===true);
three_check($reused['offers']===$result['offers']&&$reused['supplier_effect']==='read_only_search_completed');

echo 'Three-source ANEX price runner guards: '.$checks." checks passed; supplier/SSH/DB=0.\n";