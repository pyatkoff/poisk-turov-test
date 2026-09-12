<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/anex_additional_prices_specimen.php';
function additional_check(bool $ok): void { if (!$ok) throw new RuntimeException('additional regression'); }
function additional_reject(callable $call, string $expected): void { try{$call();}catch(RuntimeException $e){additional_check($e->getMessage()===$expected);return;}throw new RuntimeException('expected rejection'); }
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_OPERATION==='anex-additional-green-gold-20260912-v7');
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_TOUR===2637&&ANEX_ADDITIONAL_GREEN_GOLD_DATE==='2026-10-05');
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_NIGHTS===7&&ANEX_ADDITIONAL_GREEN_GOLD_CURRENCY===3);
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_MAX_BYTES===262144);
additional_check(ANEX_ADDITIONAL_GREEN_GOLD_ENDPOINT==='https://api.anextour.ru/b2b/AdditionalPricesDaily');
$criteria=['tour'=>2637,'dateBeg'=>'2026-10-05','nights'=>7,'currency'=>3,'page'=>1,'pageSize'=>10];
additional_check(anytour_anex_additional_query($criteria)==='page=1&pageSize=10&tour=2637&dateBeg=2026-10-05&nights=7&currency=3');
$bad=$criteria;$bad['tour']=778;additional_reject(static fn()=>anytour_anex_additional_query($bad),'ANEX_ADDITIONAL_CRITERIA');
$payload=[['additionalPriceAmount'=>'250','keyAlias'=>'EUR','keyName'=>'Euro','pagesCount'=>121,'programId'=>'2637','programName'=>'Program','packetDateBeg'=>'2026-10-05','packetDateEnd'=>'2026-10-12','tourNights'=>7,'hotelId'=>null,'hotelName'=>null,'partnerPriceAmount'=>'119448','partnerPriceCurrency'=>'RUB','privateToken'=>'must not escape']];
$result=anytour_anex_additional_sanitize_payload($payload);additional_check($result['entry_count']===1&&$result['retained_entry_count']===1&&!$result['truncated']);
additional_check($result['reported_additional_price_currencies']===['EUR']&&$result['reported_pages_counts']===[121]);
$row=$result['rows'][0];additional_check($row['additional_price_amount']==='250'&&$row['additional_price_currency_alias']==='EUR');
additional_check($row['program_id']==='2637'&&$row['tour_nights']==='7'&&$row['partner_price_amount']==='119448'&&$row['partner_price_currency']==='RUB');
additional_check(!array_key_exists('privateToken',$row));additional_check(anytour_anex_additional_sanitize_payload(json_encode($payload,JSON_THROW_ON_ERROR))===$result);
additional_reject(static fn()=>anytour_anex_additional_sanitize_payload(['rows'=>[]]),'ANEX_ADDITIONAL_RESPONSE');
additional_reject(static fn()=>anytour_anex_additional_sanitize_payload('not-json'),'ANEX_ADDITIONAL_RESPONSE');
additional_check(anytour_anex_additional_decimal('0')==='0'&&anytour_anex_additional_decimal('12.50')==='12.50');
additional_check(anytour_anex_additional_decimal('-1')===null&&anytour_anex_additional_decimal('1e3')===null);
additional_check(anytour_anex_additional_label('EUR',24)==='EUR'&&anytour_anex_additional_label('<x>',24)===null);
echo "ANEX Green Gold AdditionalPricesDaily v7 guards: PASS\n";
