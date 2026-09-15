<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';
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
$payload = [
    'data' => [[
        'tour'=>778,
        'currency'=>3,
        'dateBeg'=>'2026-10-18T00:00:00',
        'nights'=>14,
        'price_adult'=>100,
        'price_chd'=>100,
        'cashrate'=>104.12,
        'price_converted_adult'=>10412,
        'price_converted_chd'=>10412,
    ]],
    'totalCount'=>1,
];
$fact = anytour_anex_additional_specimen_fact($payload, $criteria);
additional_check($fact['state'] === 'observed');
additional_check($fact['total_count'] === 1 && $fact['retained_row_count'] === 1 && $fact['context_verified'] === true);
$row = $fact['rows'][0];
additional_check($row['tour'] === 778 && $row['currency'] === 3 && $row['date_beg'] === '2026-10-18' && $row['nights'] === 14);
additional_check($row['price_adult'] === '100' && $row['price_child'] === '100' && $row['cashrate'] === '104.12');
additional_check($row['price_converted_adult'] === '10412' && $row['price_converted_child'] === '10412');
additional_check($fact['money_semantics']['additional_prices_source'] === 'AdditionalPricesDaily');
additional_check($fact['money_semantics']['supplier_program_context'] === 'authoritative_program_778_existing_evidence');
additional_check($fact['money_semantics']['fuel_equivalence_verified'] === false);
additional_check($fact['money_semantics']['arithmetic_applied'] === false);
additional_check($fact['money_semantics']['empty_means_zero'] === false);

$empty = anytour_anex_additional_specimen_fact(['data'=>[], 'totalCount'=>0], $criteria);
additional_check($empty['state'] === 'empty_unknown' && $empty['retained_row_count'] === 0 && $empty['money_semantics']['empty_means_zero'] === false);

foreach ([
    'tour' => 779,
    'currency' => 4,
    'dateBeg' => '2026-10-19T00:00:00',
    'nights' => 10,
] as $field => $value) {
    $mismatch = $payload;
    $mismatch['data'][0][$field] = $value;
    additional_reject(static fn() => anytour_anex_additional_specimen_fact($mismatch, $criteria), 'ANEX_ADDITIONAL_SPECIMEN_CONTEXT');
}

$midday = $payload;
$midday['data'][0]['dateBeg'] = '2026-10-18T12:00:00';
additional_reject(static fn() => anytour_anex_additional_specimen_fact($midday, $criteria), 'ANEX_ADDITIONAL_SPECIMEN_CONTEXT');
additional_reject(static fn() => anytour_anex_additional_specimen_fact(['data'=>[['tour'=>778]],'totalCount'=>1], $criteria), 'ANEX_ADDITIONAL_SPECIMEN_CONTEXT');
additional_reject(static fn() => anytour_anex_additional_specimen_fact(['data'=>'bad','totalCount'=>0], $criteria), 'ANEX_ADDITIONAL_SPECIMEN_RESPONSE');
additional_reject(static fn() => anytour_anex_additional_specimen_fact(['data'=>[],'totalCount'=>-1], $criteria), 'ANEX_ADDITIONAL_SPECIMEN_RESPONSE');

additional_check(anytour_anex_additional_specimen_decimal('0') === '0');
additional_check(anytour_anex_additional_specimen_decimal('10412') === '10412');
additional_check(anytour_anex_additional_specimen_decimal('-1') === null);
additional_check(anytour_anex_additional_specimen_decimal('1e3') === null);
additional_check(anytour_anex_additional_specimen_context_date('2026-10-18') === '2026-10-18');
additional_check(anytour_anex_additional_specimen_context_date('2026-10-18T00:00:00') === '2026-10-18');
additional_check(anytour_anex_additional_specimen_context_date('2026-10-18T12:00:00') === null);

echo "ANEX AdditionalPricesDaily program778 14n v12 evidence guards: PASS\n";
