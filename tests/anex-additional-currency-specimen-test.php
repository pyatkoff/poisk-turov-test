<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_ADDITIONAL_SPECIMEN_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/anex_additional_prices_specimen.php';

function currency_check(bool $ok): void { if (!$ok) throw new RuntimeException('currency regression'); }
function currency_reject(callable $call, string $expected): void {
    try { $call(); } catch (RuntimeException $e) { currency_check($e->getMessage() === $expected); return; }
    throw new RuntimeException('expected rejection');
}
final class CurrencyFixtureClient {
    public array $calls = [];
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function request(string $action, array $params): array {
        $this->calls[] = [$action, $params];
        return $this->rows;
    }
}
$client = new CurrencyFixtureClient([
    ['id' => 1, 'name' => 'Unrelated'],
    ['id' => '3', 'name' => 'Fixture currency', 'alias' => 'ZZZ', 'private' => 'must not escape'],
]);
$result = anytour_anex_additional_currency_evidence($client, 2, 4);
currency_check($client->calls === [['SearchTour_CURRENCIES', [
    'TOWNFROMINC' => 2, 'STATEINC' => 4, 'CHECKIN_BEG' => '20260920', 'CHECKIN_END' => '20260920',
]]]);
currency_check($result['status'] === 'completed' && $result['currency_reported'] === [
    'id' => 3, 'name' => 'Fixture currency', 'nameAlt' => null, 'alias' => 'ZZZ', 'currencyISO' => null,
]);
currency_check($result['additional_currency_namespace_verified'] === false
    && $result['converted_currency'] === null && $result['included_in_search_price'] === 'unknown'
    && $result['fuel_equivalence_verified'] === false && $result['arithmetic_applied'] === false);
foreach ([[], [['id' => 3]], [['id' => 3, 'name' => '<script>bad</script>']]] as $rows) {
    currency_check(anytour_anex_additional_currency_evidence(new CurrencyFixtureClient($rows), 2, 4)['status'] === 'blocked');
}
currency_reject(static fn () => anytour_anex_additional_currency_evidence(new CurrencyFixtureClient([
    ['id' => 3, 'name' => 'A'], ['id' => '3', 'name' => 'B'],
]), 2, 4), 'ANEX_CURRENCY_AMBIGUOUS');
currency_reject(static fn () => anytour_anex_additional_currency_evidence(new CurrencyFixtureClient(['currencies' => []]), 2, 4), 'ANEX_CURRENCY_DICTIONARY');
currency_reject(static fn () => anytour_anex_additional_currency_evidence($client, 0, 4), 'ANEX_CURRENCY_CONTEXT');
currency_check(count($client->calls) === 1);
// The completed AdditionalPricesDaily operation cannot be executed through this runner.
currency_reject(static fn () => anytour_anex_additional_specimen_run([
    'operation_id' => 'anex-additional-prices-specimen-20260912-v4',
    'date' => '2026-09-20', 'nights' => 7, 'tour' => 778, 'currency' => 3,
]), 'ANEX_CURRENCY_INPUT');
echo "ANEX additional currency evidence: PASS\n";
