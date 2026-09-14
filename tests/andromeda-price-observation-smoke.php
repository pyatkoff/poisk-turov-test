<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/andromeda-price-observation.php';

$checks = 0;
function observation_check(bool $ok, string $name): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('ANDROMEDA_PRICE_OBSERVATION_TEST_' . $name);
}

$estimate = ['amount' => '133486.80', 'currency' => 'RUB', 'source' => 'derived_search_estimate'];
$observation = AnyTourAndromedaPriceObservation::build(
    $estimate,
    ['amount' => '135643', 'currency' => 'RUB']
);
observation_check($observation['state'] === 'comparable', 'state');
observation_check($observation['signed_delta_amount'] === '2156.20', 'signed_delta');
observation_check($observation['absolute_delta_amount'] === '2156.20', 'absolute_delta');
observation_check($observation['relative_delta_bps'] === 162, 'relative_bps');
observation_check($observation['search_price_estimate'] === $estimate, 'estimate_preserved');
observation_check($observation['final_price_verified'] === true, 'final_verified');

$below = AnyTourAndromedaPriceObservation::build(
    ['amount' => '140000', 'currency' => 'RUB', 'source' => 'derived_search_estimate'],
    ['amount' => '135643', 'currency' => 'RUB']
);
observation_check($below['signed_delta_amount'] === '-4357.00', 'negative_delta');
observation_check($below['absolute_delta_amount'] === '4357.00', 'negative_absolute');

$missing = AnyTourAndromedaPriceObservation::build(null, ['amount' => '135643', 'currency' => 'RUB']);
observation_check($missing['state'] === 'estimate_unavailable', 'missing');
observation_check($missing['relative_delta_bps'] === null, 'missing_no_delta');

$mismatch = AnyTourAndromedaPriceObservation::build(
    ['amount' => '1510', 'currency' => 'USD', 'source' => 'derived_search_estimate'],
    ['amount' => '135643', 'currency' => 'RUB']
);
observation_check($mismatch['state'] === 'currency_mismatch', 'currency_mismatch');
observation_check($mismatch['absolute_delta_amount'] === null, 'currency_no_delta');

$summary = AnyTourAndromedaPriceObservation::summarize([$observation, $below, $missing, $mismatch]);
observation_check($summary['counts']['total'] === 4, 'summary_total');
observation_check($summary['counts']['comparable'] === 2, 'summary_comparable');
observation_check($summary['counts']['estimate_unavailable'] === 1, 'summary_missing');
observation_check($summary['counts']['currency_mismatch'] === 1, 'summary_currency');
observation_check($summary['counts']['within_300_bps'] === 1, 'summary_3pct');
observation_check($summary['counts']['within_500_bps'] === 2, 'summary_5pct');
observation_check($summary['p50_relative_delta_bps'] === 162, 'summary_p50');
observation_check($summary['p90_relative_delta_bps'] === 311, 'summary_p90');
observation_check($summary['product_accuracy_target_applied_as_runtime_gate'] === false, 'no_runtime_gate');

foreach ([
    fn() => AnyTourAndromedaPriceObservation::build(
        ['amount'=>'133486.80','currency'=>'RUB','source'=>'guessed'],
        ['amount'=>'135643','currency'=>'RUB']
    ),
    fn() => AnyTourAndromedaPriceObservation::build(
        ['amount'=>'133486.801','currency'=>'RUB','source'=>'derived_search_estimate'],
        ['amount'=>'135643','currency'=>'RUB']
    ),
    fn() => AnyTourAndromedaPriceObservation::build(
        ['amount'=>'133486.80','currency'=>'RUB','source'=>'derived_search_estimate'],
        ['amount'=>'0','currency'=>'RUB']
    ),
] as $bad) {
    try {
        $bad();
        observation_check(false, 'reject');
    } catch (InvalidArgumentException $e) {
        observation_check(true, 'reject');
    }
}

echo 'Andromeda price observation: ' . $checks . " checks passed; supplier/DB=0.\n";
