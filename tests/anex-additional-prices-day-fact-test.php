<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-day-fact.php';

function apd_day_obs(int $nights, string $adult = '100', string $child = '100'): array
{
    return [
        'context_verified' => true,
        'tour' => 778,
        'currency' => 3,
        'date_beg' => '2026-10-18',
        'nights' => $nights,
        'price_adult' => $adult,
        'price_child' => $child,
        'cashrate' => '104.12',
        'price_converted_adult' => '10412',
        'price_converted_child' => '10412',
    ];
}

$collapsed = anytour_anex_apd_day_fact([apd_day_obs(14), apd_day_obs(7), apd_day_obs(10)]);
assert($collapsed['state'] === 'observed');
assert($collapsed['identity'] === ['tour' => 778, 'date_beg' => '2026-10-18', 'currency' => 3]);
assert($collapsed['observed_nights'] === [7, 10, 14]);
assert($collapsed['money']['price_adult'] === '100');
assert($collapsed['money']['cashrate'] === '104.12');
assert($collapsed['source'] === 'AdditionalPricesDaily');
assert($collapsed['fuel_equivalence_verified'] === false);
assert($collapsed['final_price_verified'] === false);
assert($collapsed['arithmetic_applied'] === false);

$conflict = anytour_anex_apd_day_fact([apd_day_obs(7), apd_day_obs(10, '120')]);
assert($conflict['state'] === 'conflict');
assert($conflict['reason'] === 'money_differs_by_nights');
assert($conflict['observed_nights'] === [7, 10]);

$unknown = apd_day_obs(7);
$unknown['price_adult'] = null;
$unknown = anytour_anex_apd_day_fact([$unknown]);
assert($unknown === ['state' => 'unknown', 'reason' => 'money_unknown']);

$unverified = apd_day_obs(7);
$unverified['context_verified'] = false;
assert(anytour_anex_apd_day_fact([$unverified]) === ['state' => 'unknown', 'reason' => 'unverified_context']);

$otherDay = apd_day_obs(10);
$otherDay['date_beg'] = '2026-10-19';
$identityConflict = anytour_anex_apd_day_fact([apd_day_obs(7), $otherDay]);
assert($identityConflict['state'] === 'conflict');
assert($identityConflict['reason'] === 'day_identity_mismatch');

echo "ANEX APD day fact: PASS\n";
