<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/anex_search3_paired_runner.php';
function paired_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
}
function paired_rejected(callable $call, string $code): void {
    try { $call(); throw new LogicException('Unexpected acceptance'); }
    catch (RuntimeException $e) { paired_check($e->getMessage()===$code, $code); }
}
$input = ['experiment_id'=>'one_day_anex_20260908','case_id'=>'tv_day','date'=>'2026-09-16','nights'=>7,'adults'=>2,'currency'=>'RUB'];
paired_check(anex_paired_input($input)===$input,'exact experiment accepted');
foreach (['date'=>'2026-09-17','nights'=>'7','adults'=>3,'currency'=>'USD','case_id'=>'anex_week','experiment_id'=>'new'] as $key=>$value) {
    paired_rejected(static function () use ($input,$key,$value) { anex_paired_input(array_replace($input,[$key=>$value])); },'PAIRED_INVALID_INPUT');
}
paired_rejected(static function () use ($input) { anex_paired_input($input+['operator_id'=>1]); },'PAIRED_INVALID_INPUT');
paired_check(anex_paired_operator([['id'=>90,'name'=>' Anex-Tour '],['id'=>91,'name'=>'NOT ANEX']])['id']===90,'exact normalized dictionary identity');
paired_rejected(static function () { anex_paired_operator([['id'=>90,'name'=>'ANEX'],['id'=>91,'name'=>'ANEX TOUR']]); },'PAIRED_TV_OPERATOR_NOT_UNIQUE');
paired_rejected(static function () { anex_paired_operator([['id'=>90,'name'=>'ANEX Premium']]); },'PAIRED_TV_OPERATOR_NOT_UNIQUE');
$secret = 'fixture-secret-token';
foreach ([$secret,rawurlencode($secret),htmlspecialchars($secret),'https://supplier.invalid/'.$secret,'Bearer opaque','0x'.str_repeat('a',64)] as $value) {
    paired_check(anex_paired_text($value,[$secret])===null,'secret or opaque link removed');
}
paired_check(anex_paired_text(['russianName'=>'Питание'],[])==='Питание','named object projected');
paired_check(anex_paired_date('16.09.2026')==='2026-09-16' && anex_paired_date('2026-02-30')===null,'dates validated');
paired_check(anex_paired_price('123.45')==='123.45' && anex_paired_price('123.450')===null && anex_paired_price('0')===null,'money keeps decimal string');
$criteria = ['dateFrom'=>'2026-09-16','dateTo'=>'2026-09-16','countryId'=>4];
$operator = ['id'=>90,'name'=>'ANEX TOUR'];
$tour = ['id'=>'opaque-tour-must-not-escape','date'=>'16.09.2026','nights'=>7,'price'=>'120000.25',
    'meal'=>['name'=>'AI'],'roomType'=>['name'=>'Standard'],'operator'=>['id'=>90,'name'=>'ANEX TOUR']];
$groups = [['id'=>34,'name'=>'Hotel A','country'=>['id'=>4],'category'=>5,'tours'=>[$tour,$tour,
    array_replace($tour,['date'=>'17.09.2026']),array_replace($tour,['operator'=>['id'=>91,'name'=>'ANEX TOUR']]),
    array_replace($tour,['operator'=>null]),array_replace($tour,['nights'=>8])]]];
$summary=[]; $offers = anex_paired_tv_offers($groups,$criteria,$operator,[$secret],$summary);
paired_check(count($offers)===1 && $summary['raw_tours']===6 && $summary['rejected_tours']===4,'one-day/operator/party bounds and duplicate identity');
paired_check($summary['operator_unverified_tours']===2,'operator mismatch never converted to requested identity');
paired_check($offers[0]['hotel_id']===34 && $offers[0]['local_hotel_id']===null,'DB proof required for local identity');
paired_check($offers[0]['price']==='120000.25' && $offers[0]['currency_source']==='request','price precision and currency evidence');
paired_check($offers[0]['final_price_verified']===false && $offers[0]['fuel_inclusion_verified']===false,'no final-price/fuel claim');
paired_check(strpos(json_encode($offers),'opaque-tour-must-not-escape')===false,'supplier tour ID omitted');
$groups[0]['tours']=[array_replace($tour,['operator'=>'ANEX TOUR'])];
$offers=anex_paired_tv_offers($groups,$criteria,$operator,[],$summary);
paired_check($offers[0]['operator_identity_evidence']==='exact_response_name','missing numeric operator requires exact name');
$groups[0]['country']['id']=99;
paired_check(anex_paired_tv_offers($groups,$criteria,$operator,[],$summary)===[],'different destination rejected');
$metrics=anex_paired_metrics(['searchId'=>345,'searchKey'=>'opaque','progress'=>100,'status'=>'complete','hotelsCount'=>150,'url'=>'https://example.invalid'],[]);
paired_check($metrics===['progress'=>100,'status'=>'complete','hotelsCount'=>150],'status/totals retained without handles');
echo "ANEX paired runner smoke: OK\n";
