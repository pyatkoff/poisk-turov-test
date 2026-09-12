<?php
declare(strict_types=1);
$path=__DIR__.'/../scripts/diagnostics/anex_additional_prices_specimen.php';
$source=file_get_contents($path);
if(!is_string($source)||!str_starts_with($source,'<?php'))throw new RuntimeException('specimen source');
$source=substr($source,5);
$needle="if(!defined('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY')){";
if(substr_count($source,$needle)!==1)throw new RuntimeException('specimen guard');
$source=str_replace($needle,'if(false){',$source);
eval($source);
function additional_check(bool $ok): void { if (!$ok) throw new RuntimeException('additional regression'); }
function additional_reject(callable $call, string $expected): void { try{$call();}catch(RuntimeException $e){additional_check($e->getMessage()===$expected);return;}throw new RuntimeException('expected rejection'); }
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_OPERATION==='anex-additional-green-gold-20260912-v7');
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_TOUR===2637&&ANEX_ADDITIONAL_GREEN_GOLD_DATE==='2026-10-05');
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_NIGHTS===7&&ANEX_ADDITIONAL_GREEN_GOLD_CURRENCY===3);
$criteria=['tour'=>2637,'dateBeg'=>'2026-10-05','nights'=>7,'currency'=>3,'page'=>1,'pageSize'=>10];
additional_check(anytour_anex_additional_query($criteria)==='page=1&pageSize=10&tour=2637&dateBeg=2026-10-05&nights=7&currency=3');
$bad=$criteria;$bad['tour']=778;additional_reject(static fn()=>anytour_anex_additional_query($bad),'ANEX_ADDITIONAL_CRITERIA');
$payload=['data'=>[ ['cashrate'=>104.23,'currency'=>3,'dateBeg'=>'2026-09-20T00:00:00','nights'=>7,'price_adult'=>120,'price_chd'=>120,'price_converted_adult'=>12507.6,'price_converted_chd'=>12507.6,'tour'=>778] ],'totalCount'=>1,'totalPages'=>1];
$result=anytour_anex_additional_sanitize_payload($payload);
additional_check($result['total_count']===1&&$result['total_pages']===1&&$result['retained_row_count']===1&&$result['truncated']===false);
additional_check($result['contract']==='additional_prices_daily_envelope_v4_observed');
additional_check($result['unit_semantics']==='passenger_category_rate_fields_observed_application_rule_unknown');
$row=$result['rows'][0];
additional_check($row['tour']==='778'&&$row['currency']==='3'&&$row['date_beg']==='2026-09-20T00:00:00'&&$row['nights']===7);
additional_check($row['price_adult']==='120'&&$row['price_child']==='120'&&$row['cashrate']==='104.23');
additional_check($row['price_converted_adult']==='12507.6'&&$row['price_converted_child']==='12507.6');
additional_check(anytour_anex_additional_sanitize_payload(json_encode($payload,JSON_THROW_ON_ERROR))===$result);
additional_reject(static fn()=>anytour_anex_additional_sanitize_payload([['price_adult'=>120]]),'ANEX_ADDITIONAL_RESPONSE');
additional_reject(static fn()=>anytour_anex_additional_sanitize_payload(['data'=>[],'totalCount'=>1,'totalPages'=>1,'extra'=>true]),'ANEX_ADDITIONAL_RESPONSE');
additional_reject(static fn()=>anytour_anex_additional_sanitize_payload(['data'=>[['tour'=>0]],'totalCount'=>1,'totalPages'=>1]),'ANEX_ADDITIONAL_RESPONSE');
additional_check(anytour_anex_additional_decimal('0')==='0'&&anytour_anex_additional_decimal('12507.6')==='12507.6');
additional_check(anytour_anex_additional_decimal('-1')===null&&anytour_anex_additional_decimal('1e3')===null);
additional_check(anytour_anex_additional_provider_id(3)==='3'&&anytour_anex_additional_provider_id('03')===null);
additional_check(anytour_anex_additional_text('EUR',24)==='EUR'&&anytour_anex_additional_text('<x>',24)===null);
echo "ANEX AdditionalPricesDaily proven envelope guards: PASS\n";
