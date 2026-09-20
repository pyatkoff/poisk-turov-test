<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-selected-quote.php';

$checks = 0;
$fuel = new ReflectionMethod(AnyTourAndromedaSelectedQuote::class, 'fuelSurcharges');
$fuel->setAccessible(true);

$claim = [
    'claimDocument' => [[
        'services' => [[
            'service' => [
                [
                    'servicetype' => '5',
                    'servicecategoryName' => 'Топливный сбор',
                    'price' => '170',
                    'currencyAlias' => 'EUR',
                    'routeIndex' => '0',
                    'required' => 'true',
                    'packet' => 'false',
                ],
                [
                    'servicetype' => 9,
                    'servicecategoryName' => 'Топливный сбор',
                    'price' => '160',
                    'currencyAlias' => 'EUR',
                    'routeIndex' => '1',
                    'required' => 'true',
                    'packet' => 'false',
                ],
                [
                    'servicetype' => '8',
                    'servicecategoryName' => 'Не топливо',
                    'price' => '999',
                    'currencyAlias' => 'EUR',
                    'routeIndex' => '0',
                ],
                [
                    'servicetype' => '5',
                    'servicecategoryName' => 'Топливный сбор',
                    'price' => 'bad',
                    'currencyAlias' => 'EUR',
                    'routeIndex' => '0',
                ],
            ],
        ]],
    ]],
];

$actual = $fuel->invoke(null, $claim);
$expected = [
    [
        'amount' => '170',
        'currency' => 'EUR',
        'route_index' => '0',
        'source' => 'andromeda_claim_service',
        'service_type' => '5',
        'required_reported' => true,
        'packet_reported' => false,
    ],
    [
        'amount' => '160',
        'currency' => 'EUR',
        'route_index' => '1',
        'source' => 'andromeda_claim_service',
        'service_type' => '9',
        'required_reported' => true,
        'packet_reported' => false,
    ],
];
if ($actual !== $expected) throw new RuntimeException('observed service applicability was not preserved');
++$checks;

// Optional applicability evidence is all-or-none and literal. Absence or malformed
// supplier flags must not invent a unit/package interpretation.
$withoutFlags = $claim;
unset($withoutFlags['claimDocument'][0]['services'][0]['service'][0]['required']);
unset($withoutFlags['claimDocument'][0]['services'][0]['service'][0]['packet']);
$withoutActual = $fuel->invoke(null, $withoutFlags);
if (array_key_exists('required_reported', $withoutActual[0]) || array_key_exists('packet_reported', $withoutActual[0])) {
    throw new RuntimeException('missing applicability was invented');
}
++$checks;

$malformedFlags = $claim;
$malformedFlags['claimDocument'][0]['services'][0]['service'][0]['required'] = 'maybe';
$malformedActual = $fuel->invoke(null, $malformedFlags);
if (array_key_exists('required_reported', $malformedActual[0]) || array_key_exists('packet_reported', $malformedActual[0])) {
    throw new RuntimeException('malformed applicability was admitted');
}
++$checks;

$legacy = [
    'claimDocument' => [[
        'services' => [[
            'service' => [[
                'servicetype' => '8',
                'servicecategoryName' => 'Топливный сбор',
                'price' => '80',
                'currencyAlias' => 'USD',
                'routeIndex' => '0',
            ]],
        ]],
    ]],
];
$legacyActual = $fuel->invoke(null, $legacy);
if ($legacyActual !== [[
    'amount' => '80',
    'currency' => 'USD',
    'route_index' => '0',
    'source' => 'andromeda_claim_service',
]]) throw new RuntimeException('legacy type-8 public shape changed');
++$checks;

$encoded = json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
foreach (['fuel_total', 'surcharge_total', 'finalPriceReady', 'derived_price', 'unit_scope', 'price_includes_fuel'] as $forbidden) {
    if (str_contains($encoded, $forbidden)) throw new RuntimeException('arithmetic or inferred semantics leaked into evidence');
}
++$checks;

echo "ANDROMEDA_FUEL_SERVICE_CATEGORY_OK checks={$checks}\n";