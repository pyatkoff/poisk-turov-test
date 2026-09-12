<?php
declare(strict_types=1);

require __DIR__ . '/three-provider-quote-envelope-test.php';

$factsChecks = 0;
function quote_facts_check(bool $ok): void
{
    global $factsChecks;
    ++$factsChecks;
    if (!$ok) throw new RuntimeException('quote_facts_check_'.$factsChecks);
}
function quote_facts_reject(callable $case): void
{
    try {
        $case();
        quote_facts_check(false);
    } catch (InvalidArgumentException $error) {
        quote_facts_check(true);
    }
}

[$factsOffer, $factsRetained, $factsCurrent] = quote_env_setup();
$factsQuote = quote_env_quote();
$factsQuote['fuel_surcharges_reported'] = [
    ['amount' => '80', 'currency' => 'USD', 'route_index' => '0', 'source' => 'andromeda_claim_service'],
    ['amount' => '80', 'currency' => 'USD', 'route_index' => '1', 'source' => 'andromeda_claim_service'],
];
$factsQuote['operator_currency_rates_reported'] = [
    ['currency' => 'USD', 'rate' => '1', 'is_claim_currency' => true, 'source' => 'andromeda_claim_money', 'arithmetic_applied' => false],
    ['currency' => 'RUB', 'rate' => '89.83', 'is_claim_currency' => false, 'source' => 'andromeda_claim_money', 'arithmetic_applied' => false],
];
$factsQuote['calc_money_facts_reported'] = [
    ['currency' => 'USD', 'gross_amount' => '1510', 'net_amount' => '1402', 'commissionable_amount' => '1349.79', 'commission_amount' => '108', 'source' => 'andromeda_calc_money', 'arithmetic_applied' => false],
    ['currency' => 'RUB', 'gross_amount' => '135643', 'net_amount' => '125942', 'commissionable_amount' => '121251.64', 'commission_amount' => '9702', 'source' => 'andromeda_calc_money', 'arithmetic_applied' => false],
];
$factsQuote['flights'][0]['transport_markup_reported'] = [
    'amount' => '25', 'currency' => 'USD', 'source' => 'andromeda_transport_detail', 'aggregation' => 'unknown',
];
$factsQuote['flights'][1]['transport_markup_reported'] = [
    'amount' => '35', 'currency' => 'USD', 'source' => 'andromeda_transport_detail', 'aggregation' => 'unknown',
];

$factsOut = AnyTourThreeProviderQuoteEnvelope::verified(
    $factsOffer, $factsRetained, $factsCurrent, $factsQuote, 1789161300
);
quote_facts_check($factsOut['money']['fuel_surcharges_reported'] === $factsQuote['fuel_surcharges_reported']);
quote_facts_check($factsOut['money']['operator_currency_rates_reported'] === $factsQuote['operator_currency_rates_reported']);
quote_facts_check($factsOut['money']['calc_money_facts_reported'] === $factsQuote['calc_money_facts_reported']);
quote_facts_check($factsOut['money']['transport_markups_reported'] === [
    ['direction' => '0', 'amount' => '25', 'currency' => 'USD', 'source' => 'andromeda_transport_detail', 'aggregation' => 'unknown'],
    ['direction' => '1', 'amount' => '35', 'currency' => 'USD', 'source' => 'andromeda_transport_detail', 'aggregation' => 'unknown'],
]);
quote_facts_check($factsOut['money']['arithmetic_applied'] === false
    && $factsOut['money']['search_price_fuel_relation'] === 'unknown');
quote_facts_check(!isset($factsOut['money']['fuel_total'])
    && !isset($factsOut['money']['surcharge_total'])
    && !isset($factsOut['money']['commission_rate'])
    && !isset($factsOut['money']['derived_price']));

$changedFact = $factsQuote;
$changedFact['fuel_surcharges_reported'][0]['amount'] = '81';
$changedOut = AnyTourThreeProviderQuoteEnvelope::verified(
    $factsOffer, $factsRetained, $factsCurrent, $changedFact, 1789161300
);
quote_facts_check($changedOut['quote_evidence_digest'] !== $factsOut['quote_evidence_digest']);

// The reported block is all-or-none: partial migration cannot silently lose facts.
foreach (['fuel_surcharges_reported', 'operator_currency_rates_reported', 'calc_money_facts_reported'] as $missing) {
    quote_facts_reject(function () use ($factsOffer, $factsRetained, $factsCurrent, $factsQuote, $missing): void {
        $x = $factsQuote;
        unset($x[$missing]);
        AnyTourThreeProviderQuoteEnvelope::verified($factsOffer, $factsRetained, $factsCurrent, $x, 1789161300);
    });
}

// Exact nested shapes/sources remain fail-closed; no arbitrary reported data crosses the boundary.
quote_facts_reject(function () use ($factsOffer, $factsRetained, $factsCurrent, $factsQuote): void {
    $x = $factsQuote;
    $x['fuel_surcharges_reported'][0]['total'] = '160';
    AnyTourThreeProviderQuoteEnvelope::verified($factsOffer, $factsRetained, $factsCurrent, $x, 1789161300);
});
quote_facts_reject(function () use ($factsOffer, $factsRetained, $factsCurrent, $factsQuote): void {
    $x = $factsQuote;
    $x['operator_currency_rates_reported'][0]['arithmetic_applied'] = true;
    AnyTourThreeProviderQuoteEnvelope::verified($factsOffer, $factsRetained, $factsCurrent, $x, 1789161300);
});
quote_facts_reject(function () use ($factsOffer, $factsRetained, $factsCurrent, $factsQuote): void {
    $x = $factsQuote;
    $x['calc_money_facts_reported'][0]['commission_rate'] = '8';
    AnyTourThreeProviderQuoteEnvelope::verified($factsOffer, $factsRetained, $factsCurrent, $x, 1789161300);
});
quote_facts_reject(function () use ($factsOffer, $factsRetained, $factsCurrent, $factsQuote): void {
    $x = $factsQuote;
    $x['flights'][0]['transport_markup_reported']['aggregation'] = 'sum';
    AnyTourThreeProviderQuoteEnvelope::verified($factsOffer, $factsRetained, $factsCurrent, $x, 1789161300);
});

$json = json_encode($factsOut, JSON_THROW_ON_ERROR);
quote_facts_check(strpos($json, 'PRIVATE-FLIGHT') === false);
quote_facts_check(!isset($factsOut['flights']) && !isset($factsOut['final_price']));

echo 'Three-provider quote reported facts: '.$factsChecks." checks passed; arithmetic/supplier/DB/booking=0.\n";
