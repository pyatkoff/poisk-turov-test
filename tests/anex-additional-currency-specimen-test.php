<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';
require_once __DIR__ . '/../app/integrations/anex-additional-prices-day-fact.php';
define('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/anex_additional_prices_specimen.php';

function additional_check(bool $ok): void
{
    if (!$ok) throw new RuntimeException('additional regression');
}
function additional_reject(callable $call, string $expected): void
{
    try { $call(); } catch (RuntimeException $e) {
        additional_check($e->getMessage() === $expected);
        return;
    }
    throw new RuntimeException('expected rejection');
}

additional_check(ANEX_ADDITIONAL_SPECIMEN_OPERATION === 'anex-additional-prices-778-14n-20260915-v12');
additional_check(ANEX_ADDITIONAL_SPECIMEN_TOUR === 778);
additional_check(ANEX_ADDITIONAL_SPECIMEN_DATE === '2026-10-18');
additional_check(ANEX_ADDITIONAL_SPECIMEN_NIGHTS === [14]);
additional_check(ANEX_ADDITIONAL_SPECIMEN_CURRENCY === 3);

$criteria = ['page'=>1,'pageSize'=>10,'tour'=>778,'dateBeg'=>'2026-10-18','nights'=>14,'currency'=>3];
$payload = ['data' => [[
    'tour'=>778,'currency'=>3,'dateBeg'=>'2026-10-18T00:00:00','nights'=>14,
    'price_adult'=>100,'price_chd'=>100,'cashrate'=>104.12,
    'price_converted_adult'=>10412,'price_converted_chd'=>10412,
]], 'totalCount'=>1];
$fact = anytour_anex_additional_specimen_fact($payload, $criteria);
additional_check($fact['state'] === 'observed');
additional_check($fact['total_count'] === 1 && $fact['retained_row_count'] === 1 && $fact['context_verified'] === true);
$row = $fact['rows'][0];
additional_check($row['tour'] === 778 && $row['currency'] === 3 && $row['date_beg'] === '2026-10-18' && $row['nights'] === 14);
additional_check($row['price_adult'] === '100' && $row['price_child'] === '100' && $row['cashrate'] === '104.12');
additional_check($row['price_converted_adult'] === '10412' && $row['price_converted_child'] === '10412');
additional_check($fact['money_semantics']['additional_prices_source'] === 'AdditionalPricesDaily');
additional_check($fact['money_semantics']['fuel_equivalence_verified'] === false);
additional_check($fact['money_semantics']['arithmetic_applied'] === false);
additional_check($fact['money_semantics']['empty_means_zero'] === false);

$empty = anytour_anex_additional_specimen_fact(['data'=>[], 'totalCount'=>0], $criteria);
additional_check($empty['state'] === 'empty_unknown' && $empty['retained_row_count'] === 0);
foreach (['tour'=>779,'currency'=>4,'dateBeg'=>'2026-10-19T00:00:00','nights'=>10] as $field=>$value) {
    $mismatch=$payload; $mismatch['data'][0][$field]=$value;
    additional_reject(static fn()=>anytour_anex_additional_specimen_fact($mismatch,$criteria),'ANEX_ADDITIONAL_SPECIMEN_CONTEXT');
}

$dayObs = static function (int $nights, string $adult='100'): array {
    return ['context_verified'=>true,'tour'=>778,'currency'=>3,'date_beg'=>'2026-10-18','nights'=>$nights,
        'price_adult'=>$adult,'price_child'=>'100','cashrate'=>'104.12',
        'price_converted_adult'=>'10412','price_converted_child'=>'10412'];
};
$day = anytour_anex_apd_day_fact([$dayObs(14),$dayObs(7),$dayObs(10)]);
additional_check($day['state']==='observed');
additional_check($day['identity']===['tour'=>778,'date_beg'=>'2026-10-18','currency'=>3]);
additional_check($day['observed_nights']===[7,10,14]);
additional_check($day['money']['price_adult']==='100' && $day['money']['cashrate']==='104.12');
additional_check($day['source']==='AdditionalPricesDaily' && $day['fuel_equivalence_verified']===false && $day['arithmetic_applied']===false);
$dayConflict=anytour_anex_apd_day_fact([$dayObs(7),$dayObs(10,'120')]);
additional_check($dayConflict['state']==='conflict' && $dayConflict['reason']==='money_differs_by_nights');
$dayUnknown=$dayObs(7); $dayUnknown['price_adult']=null;
additional_check(anytour_anex_apd_day_fact([$dayUnknown])===['state'=>'unknown','reason'=>'money_unknown']);

additional_check(anytour_anex_additional_specimen_decimal('0') === '0');
additional_check(anytour_anex_additional_specimen_decimal('10412') === '10412');
additional_check(anytour_anex_additional_specimen_decimal('-1') === null);
additional_check(anytour_anex_additional_specimen_context_date('2026-10-18T00:00:00') === '2026-10-18');
additional_check(anytour_anex_additional_specimen_context_date('2026-10-18T12:00:00') === null);

echo "ANEX AdditionalPricesDaily v12 + day fact guards: PASS\n";
