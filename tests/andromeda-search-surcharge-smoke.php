<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/andromeda-search-surcharge.php';

function fail_surcharge(string $message): void { fwrite(STDERR, "ANDROMEDA_SEARCH_SURCHARGE_FAIL:$message\n"); exit(1); }
function same_surcharge(mixed $actual, mixed $expected, string $message): void { if ($actual !== $expected) fail_surcharge($message . ':' . json_encode($actual)); }

$claim = [
    'claimDocument' => [[
        'moneys' => [[ 'money' => [
            ['currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true'],
            ['currency'=>'RUB','rate'=>'89.83','isClaimCurrency'=>'false'],
        ]]],
    ]],
    'variants' => [[
        'transports' => [[ 'transport' => [
            ['type'=>'ttAvia','direction'=>'0','details'=>[[ 'detail'=>[['markup'=>'160','currency'=>'USD']] ]]],
            ['type'=>'ttAvia','direction'=>'1','details'=>[[ 'detail'=>[['markup'=>'160','currency'=>'USD']] ]]],
        ]]],
    ]],
];
$estimate = AnyTourAndromedaSearchSurcharge::estimate($claim, ['amount'=>'119114','currency'=>'RUB']);
same_surcharge($estimate['state'], 'estimated', 'state');
same_surcharge($estimate['transport_markup_reported']['amount'], '160', 'raw_markup');
same_surcharge($estimate['transport_markup_reported']['aggregation'], 'single_distinct_party_markup', 'dedup_routes');
same_surcharge($estimate['party_surcharge'], [
    'amount'=>'14372.80','currency'=>'RUB','source'=>'andromeda_get_flights_transport_converted'
], 'converted_party_surcharge');
same_surcharge($estimate['search_price_with_surcharge'], [
    'amount'=>'133486.80','currency'=>'RUB','source'=>'derived_search_estimate'
], 'display_estimate');
same_surcharge($estimate['final_price_verified'], false, 'not_final');
same_surcharge($estimate['arithmetic_applied'], true, 'arithmetic');

// Different markups mean the selected flight affects money; do not guess or sum them.
$ambiguous = $claim;
$ambiguous['variants'][0]['transports'][0]['transport'][1]['details'][0]['detail'][0]['markup'] = '180';
$unknown = AnyTourAndromedaSearchSurcharge::estimate($ambiguous, ['amount'=>'119114','currency'=>'RUB']);
same_surcharge($unknown['state'], 'unknown', 'different_flight_markup_unknown');
same_surcharge($unknown['party_surcharge'], null, 'different_flight_no_surcharge');
same_surcharge($unknown['search_price_with_surcharge'], null, 'different_flight_no_total');
same_surcharge($unknown['arithmetic_applied'], false, 'different_flight_no_arithmetic');

// Missing target conversion rate is unknown, never zero.
$noRate = $claim;
$noRate['claimDocument'][0]['moneys'][0]['money'] = [['currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true']];
$unknown = AnyTourAndromedaSearchSurcharge::estimate($noRate, ['amount'=>'119114','currency'=>'RUB']);
same_surcharge($unknown['state'], 'unknown', 'missing_rate_unknown');
same_surcharge($unknown['party_surcharge'], null, 'missing_rate_not_zero');

echo "ANDROMEDA_SEARCH_SURCHARGE_OK\n";
