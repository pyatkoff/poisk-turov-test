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

// Different markups without a complete required roundtrip remain unknown.
$ambiguous = $claim;
$ambiguous['variants'][0]['transports'][0]['transport'][1]['details'][0]['detail'][0]['markup'] = '180';
$unknown = AnyTourAndromedaSearchSurcharge::estimate($ambiguous, ['amount'=>'119114','currency'=>'RUB']);
same_surcharge($unknown['state'], 'unknown', 'different_flight_markup_unknown');
same_surcharge($unknown['party_surcharge'], null, 'different_flight_no_surcharge');
same_surcharge($unknown['search_price_with_surcharge'], null, 'different_flight_no_total');
same_surcharge($unknown['arithmetic_applied'], false, 'different_flight_no_arithmetic');

// Choice-dependent transport can be resolved privately to the cheapest safely
// comparable option per required direction. This is only a selection strategy;
// supplier changeservice+calc must still establish the final customer price.
$choice = $claim;
$choice['groups'] = [[ 'group' => [
    ['id'=>'g0','required'=>'true','oneItem'=>'true'],
    ['id'=>'g1','required'=>'true','oneItem'=>'true'],
] ]];
$choice['variants'] = [[
    'transports' => [[ 'transport' => [
        ['type'=>'ttAvia','direction'=>'0','groupId'=>'g0','uid'=>'out-expensive',
            'details'=>[[ 'detail'=>[['markup'=>'200','currency'=>'USD']] ]]],
        ['type'=>'ttAvia','direction'=>'0','groupId'=>'g0','uid'=>'out-cheap',
            'details'=>[[ 'detail'=>[['markup'=>'100','currency'=>'USD']] ]]],
        ['type'=>'ttAvia','direction'=>'1','groupId'=>'g1','uid'=>'ret-expensive',
            'details'=>[[ 'detail'=>[['markup'=>'150','currency'=>'USD']] ]]],
        ['type'=>'ttAvia','direction'=>'1','groupId'=>'g1','uid'=>'ret-cheap',
            'details'=>[[ 'detail'=>[['markup'=>'50','currency'=>'USD']] ]]],
    ]]],
]];
$selected = AnyTourAndromedaSearchSurcharge::cheapestRequiredFlightSelection(
    $choice, ['amount'=>'119114','currency'=>'RUB']
);
same_surcharge(is_array($selected), true, 'cheapest_selection_available');
same_surcharge($selected['candidate_counts'], ['0'=>2,'1'=>2], 'cheapest_selection_counts');
same_surcharge($selected['target_currency'], 'RUB', 'cheapest_selection_currency');
same_surcharge($selected['selected'][0]['uid'], 'out-cheap', 'cheapest_outbound');
same_surcharge($selected['selected'][1]['uid'], 'ret-cheap', 'cheapest_return');

// An option with internally contradictory markup is not safely comparable.
$unsafeChoice = $choice;
$unsafeChoice['variants'][0]['transports'][0]['transport'][1]['details'][0]['detail'][] =
    ['markup'=>'101','currency'=>'USD'];
$unsafeChoice['variants'][0]['transports'][0]['transport'] =
    [$unsafeChoice['variants'][0]['transports'][0]['transport'][1],
     $unsafeChoice['variants'][0]['transports'][0]['transport'][2]];
same_surcharge(
    AnyTourAndromedaSearchSurcharge::cheapestRequiredFlightSelection(
        $unsafeChoice, ['amount'=>'119114','currency'=>'RUB']
    ),
    null,
    'unsafe_direction_fails_closed'
);

// Missing target conversion rate is unknown, never zero.
$noRate = $claim;
$noRate['claimDocument'][0]['moneys'][0]['money'] = [['currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true']];
$unknown = AnyTourAndromedaSearchSurcharge::estimate($noRate, ['amount'=>'119114','currency'=>'RUB']);
same_surcharge($unknown['state'], 'unknown', 'missing_rate_unknown');
same_surcharge($unknown['party_surcharge'], null, 'missing_rate_not_zero');

// JSON numbers and decimal strings can report the same party markup on two routes.
$price = ['amount'=>'119114','currency'=>'RUB'];
$withMarkups = static function(array $values) use ($claim): array {
    $copy = $claim;
    foreach ($values as $i => $value) {
        $copy['variants'][0]['transports'][0]['transport'][$i]['details'][0]['detail'][0]['markup'] = $value;
    }
    return $copy;
};
foreach ([160, 160.0, '160', '160.0', '160.00'] as $outbound) {
    foreach ([160, 160.0, '160', '160.0', '160.00'] as $inbound) {
        $copy = $withMarkups([$outbound, $inbound]); $before = $copy;
        $actual = AnyTourAndromedaSearchSurcharge::estimate($copy, $price);
        same_surcharge($actual['state'], 'estimated', 'equivalent_markup');
        same_surcharge($actual['party_surcharge'], $estimate['party_surcharge'], 'equivalent_markup_once');
        same_surcharge($actual['search_price_with_surcharge'], $estimate['search_price_with_surcharge'], 'equivalent_markup_total');
        same_surcharge($actual['transport_markup_reported']['amount'], (string)$outbound, 'first_reported_markup_preserved');
        same_surcharge($copy, $before, 'supplier_claim_unchanged');
        same_surcharge($actual['final_price_verified'], false, 'estimate_still_not_final');
    }
}
$zero = AnyTourAndromedaSearchSurcharge::estimate($withMarkups(['0.00', 0]), $price);
same_surcharge($zero['state'], 'estimated', 'equivalent_explicit_zero');
same_surcharge($zero['party_surcharge']['amount'], '0.00', 'explicit_zero_preserved');
same_surcharge($zero['search_price_with_surcharge']['amount'], '119114.00', 'zero_total');
foreach ([null, true, false, '', 'invalid'] as $missing) {
    $actual = AnyTourAndromedaSearchSurcharge::estimate($withMarkups([$missing, $missing]), $price);
    same_surcharge($actual['state'], 'unknown', 'invalid_not_explicit_zero');
    same_surcharge($actual['party_surcharge'], null, 'invalid_has_no_surcharge');
}
foreach ([['160', '160.01'], ['0.00', '0.01']] as $values) {
    $actual = AnyTourAndromedaSearchSurcharge::estimate($withMarkups($values), $price);
    same_surcharge($actual['state'], 'unknown', 'different_cents_stay_ambiguous');
}
$differentCurrency = $claim;
$differentCurrency['variants'][0]['transports'][0]['transport'][1]['details'][0]['detail'][0]['currency'] = 'RUB';
same_surcharge(AnyTourAndromedaSearchSurcharge::estimate($differentCurrency, $price)['state'], 'unknown', 'currencies_not_deduped');

// Equal rate values are not contradictory evidence; keep the first reported representation.
$equalRates = $claim;
$equalRates['claimDocument'][0]['moneys'][] = ['money'=>[
    ['currency'=>'USD','rate'=>'1.000000','isClaimCurrency'=>'true'],
    ['currency'=>'RUB','rate'=>'89.830000','isClaimCurrency'=>'false'],
]];
foreach ([false, true] as $reverse) {
    $copy = $equalRates;
    if ($reverse) $copy['claimDocument'][0]['moneys'] = array_reverse($copy['claimDocument'][0]['moneys']);
    $before = $copy; $actual = AnyTourAndromedaSearchSurcharge::estimate($copy, $price);
    same_surcharge($actual['search_price_with_surcharge'], $estimate['search_price_with_surcharge'], 'equivalent_rates_total');
    same_surcharge($actual['operator_currency_rates_reported'][0]['rate'], $reverse ? '1.000000' : '1', 'first_raw_rate');
    same_surcharge($copy, $before, 'rate_claim_unchanged');
}
// Genuine conflict remains unknown, including a later repeat of an otherwise valid rate.
foreach (['rate'=>'89.830001', 'isClaimCurrency'=>'true'] as $field => $value) {
    $copy = $equalRates;
    $copy['claimDocument'][0]['moneys'][1]['money'][1][$field] = $value;
    $copy['claimDocument'][0]['moneys'][] = $claim['claimDocument'][0]['moneys'][0];
    $actual = AnyTourAndromedaSearchSurcharge::estimate($copy, $price);
    same_surcharge($actual['state'], 'unknown', 'rate_conflict_not_restored');
    same_surcharge($actual['party_surcharge'], null, 'conflicting_rate_no_total');
}

// Owner-approved minimum-estimate fallback: the FULL claim has one required one-item
// group per direction and both groups expose the same selectable party-markup set.
// Explicit supplier zero is a real minimum; it is never replaced by the next positive value.
$minimum = $claim;
$minimum['groups'] = [[ 'group' => [
    ['id'=>'go','required'=>'true','oneItem'=>'true'],
    ['id'=>'gr','required'=>'true','oneItem'=>'true'],
] ]];
$mk = static fn(string $direction, string $group, string $uid, mixed $amount, string $currency='RUB'): array => [
    'type'=>'ttAvia','direction'=>$direction,'groupId'=>$group,'uid'=>$uid,
    'details'=>[[ 'detail'=>[['markup'=>$amount,'currency'=>$currency]] ]],
];
$minimum['variants'] = [[ 'transports' => [[ 'transport' => [
    $mk('0','go','o-2121','2121'), $mk('0','go','o-0','0.00'), $mk('0','go','o-10','10'),
    $mk('1','gr','r-10','10'), $mk('1','gr','r-2121','2121'), $mk('1','gr','r-0',0),
] ]] ]];
$minimumEstimate = AnyTourAndromedaSearchSurcharge::estimate($minimum, ['amount'=>'185125','currency'=>'RUB']);
same_surcharge($minimumEstimate['state'], 'estimated', 'minimum_complete_roundtrip_estimated');
same_surcharge($minimumEstimate['transport_markup_reported']['aggregation'], 'minimum_complete_required_roundtrip_markup', 'minimum_aggregation');
same_surcharge($minimumEstimate['party_surcharge']['amount'], '0.00', 'minimum_explicit_zero');
same_surcharge($minimumEstimate['search_price_with_surcharge']['amount'], '185125.00', 'minimum_zero_total');
same_surcharge($minimumEstimate['final_price_verified'], false, 'minimum_never_final');

// Numeric ordering, not lexical ordering, and FX conversion happen before comparing.
$numeric = $minimum;
$numeric['variants'][0]['transports'][0]['transport'] = [
    $mk('0','go','o-10','10'), $mk('0','go','o-2','2'),
    $mk('1','gr','r-2','2'), $mk('1','gr','r-10','10'),
];
$numericEstimate = AnyTourAndromedaSearchSurcharge::estimate($numeric, ['amount'=>'100','currency'=>'RUB']);
same_surcharge($numericEstimate['party_surcharge']['amount'], '2.00', 'minimum_numeric_not_lexical');

$fx = $minimum;
$fx['variants'][0]['transports'][0]['transport'] = [
    $mk('0','go','o-usd','1','USD'), $mk('0','go','o-rub','80','RUB'),
    $mk('1','gr','r-rub','80','RUB'), $mk('1','gr','r-usd','1','USD'),
];
$fxEstimate = AnyTourAndromedaSearchSurcharge::estimate($fx, ['amount'=>'100','currency'=>'RUB']);
same_surcharge($fxEstimate['party_surcharge']['amount'], '80.00', 'minimum_fx_before_compare');
same_surcharge($fxEstimate['search_price_with_surcharge']['amount'], '180.00', 'minimum_fx_total');

// Order and duplicate rows do not change the chosen minimum.
$permuted = $minimum;
$rows = $permuted['variants'][0]['transports'][0]['transport'];
$permuted['variants'][0]['transports'][0]['transport'] = array_merge(array_reverse($rows), [$rows[1], $rows[5]]);
same_surcharge(
    AnyTourAndromedaSearchSurcharge::estimate($permuted, ['amount'=>'185125','currency'=>'RUB'])['party_surcharge']['amount'],
    '0.00',
    'minimum_order_duplicates_stable'
);

// Any unreadable eligible option, missing required direction, non-oneItem required
// flight group, cross-variant shape or no common party markup keeps search UNKNOWN.
$unsafeMinimums = [];
$bad = $minimum;
$bad['variants'][0]['transports'][0]['transport'][0]['details'][0]['detail'][0]['markup'] = null;
$unsafeMinimums['invalid_markup'] = $bad;
$bad = $minimum;
$bad['variants'][0]['transports'][0]['transport'] = array_values(array_filter(
    $bad['variants'][0]['transports'][0]['transport'],
    static fn(array $row): bool => $row['direction'] === '0'
));
$unsafeMinimums['missing_return'] = $bad;
$bad = $minimum;
$bad['groups'][0]['group'][1]['oneItem'] = 'false';
$unsafeMinimums['not_one_item'] = $bad;
$bad = $minimum;
$bad['variants'][] = $bad['variants'][0];
$unsafeMinimums['multiple_variants'] = $bad;
$bad = $minimum;
foreach ($bad['variants'][0]['transports'][0]['transport'] as &$row) {
    if ($row['direction'] === '1') $row['details'][0]['detail'][0]['markup'] = '9999';
}
unset($row);
$unsafeMinimums['no_common_markup'] = $bad;
$bad = $minimum;
$bad['claimDocument'][0]['moneys'][0]['money'] = [['currency'=>'USD','rate'=>'1','isClaimCurrency'=>'true']];
$bad['variants'][0]['transports'][0]['transport'] = [
    $mk('0','go','o-usd','1','USD'), $mk('1','gr','r-usd','1','USD'),
    $mk('0','go','o-rub','80','RUB'), $mk('1','gr','r-rub','80','RUB'),
];
$unsafeMinimums['unknown_fx'] = $bad;
foreach ($unsafeMinimums as $name => $bad) {
    $actual = AnyTourAndromedaSearchSurcharge::estimate($bad, ['amount'=>'185125','currency'=>'RUB']);
    same_surcharge($actual['state'], 'unknown', 'minimum_'.$name.'_unknown');
    same_surcharge($actual['party_surcharge'], null, 'minimum_'.$name.'_no_surcharge');
    same_surcharge($actual['final_price_verified'], false, 'minimum_'.$name.'_never_final');
}

echo "ANDROMEDA_SEARCH_SURCHARGE_OK\n";
