<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_BROAD_PRICE_LIBRARY_ONLY',true);
require __DIR__.'/../scripts/diagnostics/anex_three_source_broad_price.php';
$checks=0;function broad_check(bool $ok):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('broad_price_check_'.$checks);}
broad_check(ANEX_BROAD_PRICE_EXPERIMENT==='anex_green_gold_program_20260912_v9');
broad_check(ANEX_BROAD_PRICE_CASES===['anex']);
broad_check(ANEX_BROAD_PRICE_DATE==='2026-10-05'&&ANEX_BROAD_PRICE_NIGHTS===7);
broad_check(ANEX_BROAD_PRICE_TARGET_LOCAL_HOTEL===21753&&ANEX_BROAD_PRICE_TARGET_ANEX_HOTEL==='25084');
$base=['experiment_id'=>ANEX_BROAD_PRICE_EXPERIMENT,'case_id'=>'anex','country'=>'Turkey','date'=>ANEX_BROAD_PRICE_DATE,'nights'=>7,'adults'=>2,'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB'];
broad_check(anex_broad_price_input($base)===$base);
foreach([['case_id','tourvisor'],['date','2026-10-06'],['nights',8],['meal_family','bb']] as [$k,$v]){$bad=$base;$bad[$k]=$v;try{anex_broad_price_input($bad);broad_check(false);}catch(RuntimeException $e){broad_check($e->getMessage()==='BROAD_PRICE_INVALID_INPUT');}}
broad_check(anex_broad_price_provider_id(778)==='778'&&anex_broad_price_provider_id('3')==='3'&&anex_broad_price_provider_id('03')===null);
$raw=anex_broad_price_raw_programs(['prices'=>[['id'=>'a','tourKey'=>778,'currencyKey'=>3],['id'=>'b','tourKey'=>'3867','currencyKey'=>'3']]]);
broad_check($raw===['a'=>['tour'=>'778','currency'=>'3'],'b'=>['tour'=>'3867','currency'=>'3']]);
$row=anex_broad_price_offer('anex',21753,'25084','GREEN GOLD','2026-10-05',7,2,0,'AI','Standard Room','DBL','119952','RUB');
broad_check(is_array($row)&&$row['price']==='119952'&&$row['fuel_charge']===null&&$row['meal_key']==='ai');
broad_check(anex_broad_price_offer('tourvisor',21753,'25084','GREEN GOLD','2026-10-05',7,2,0,'AI','Standard','DBL','1','RUB')===null);
broad_check(anex_broad_price_offer('anex',21753,'999','GREEN GOLD','2026-10-05',7,2,0,'AI','Standard','DBL','1','RUB')===null);
$done=anex_broad_price_completed(['status'=>'blocked','offers'=>[],'supplier_effect'=>'unknown_after_reservation'],['offers'=>[$row],'program_pairs'=>[['tour'=>'900','currency'=>'3']]]);
broad_check($done['status']==='completed'&&$done['supplier_effect']==='read_only_search_completed');
broad_check(anex_broad_price_reused($done)['reused']===true);
echo 'Green Gold v9 direct-ANEX guards: '.$checks." checks passed; supplier/SSH/DB=0.\n";
