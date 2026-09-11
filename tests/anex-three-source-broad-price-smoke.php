<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_BROAD_PRICE_LIBRARY_ONLY',true);
require __DIR__.'/../scripts/diagnostics/anex_three_source_broad_price.php';

$checks=0;function broad_check(bool $ok):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('broad_price_check_'.$checks);}
broad_check(ANEX_BROAD_PRICE_EXPERIMENT==='anex_three_source_broad_price_20260911_v2');
broad_check(ANEX_BROAD_PRICE_DATE==='2026-10-05');
$base=['experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'case_id'=>'anex','country'=>'Turkey','date'=>ANEX_BROAD_PRICE_DATE,'nights'=>7,'adults'=>2,'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB'];
broad_check(anex_broad_price_input($base)===$base);
foreach(ANEX_BROAD_PRICE_CASES as $case){$v=$base;$v['case_id']=$case;broad_check(anex_broad_price_input($v)['case_id']===$case);}
foreach([['date','2026-10-04'],['country','Egypt'],['nights',8],['adults',3],['meal_family','bb'],['currency','USD'],['case_id','bad']] as [$key,$value]){$v=$base;$v[$key]=$value;try{anex_broad_price_input($v);broad_check(false);}catch(RuntimeException $e){broad_check($e->getMessage()==='BROAD_PRICE_INVALID_INPUT');}}
foreach(['AI','ALL INCLUSIVE','UAI','AI-WITHOUT ALCOHOL','все включено'] as $meal)broad_check(anex_broad_price_meal($meal)==='ai');
foreach(['BB','HB','RO',''] as $meal)broad_check(anex_broad_price_meal($meal)===null);
broad_check(anex_broad_price_money('100000',true)==='100000');broad_check(anex_broad_price_money('0',false)==='0');broad_check(anex_broad_price_money('0',true)===null);
foreach(['-1','1e5','1,2','1.234'] as $bad)broad_check(anex_broad_price_money($bad,false)===null);
$row=anex_broad_price_offer('tourvisor',1239,1239,'HEDEF RESORT HOTEL',ANEX_BROAD_PRICE_DATE,7,2,0,'AI','Standard Room','DBL','107391','RUB','2500');
broad_check(is_array($row)&&$row['local_hotel_id']===1239&&$row['room_norm']==='standard room'&&$row['placement_norm']==='dbl');
broad_check($row['fuel_charge']==='2500'&&$row['fuel_inclusion_verified']===false&&$row['final_price_verified']===false);
broad_check(anex_broad_price_offer('anex',0,8652,'X',ANEX_BROAD_PRICE_DATE,7,2,0,'AI','Standard','DBL','100000','RUB')===null);
broad_check(anex_broad_price_offer('anex',1239,8652,'X',ANEX_BROAD_PRICE_DATE,7,2,0,'BB','Standard','DBL','100000','RUB')===null);
$initial=['status'=>'blocked','offers'=>[],'supplier_effect'=>'unknown_after_reservation','reused'=>false];$result=['offers'=>[$row],'received_offers'=>1];
$done=anex_broad_price_completed($initial,$result);broad_check($done['status']==='completed'&&$done['offers']===$result['offers']&&$done['supplier_effect']==='read_only_search_completed');
$reuse=anex_broad_price_reused($done);broad_check($reuse['reused']===true&&$reuse['offers']===$result['offers']);
echo 'Broad three-source price guards: '.$checks." checks passed; supplier/SSH/DB=0.\n";
