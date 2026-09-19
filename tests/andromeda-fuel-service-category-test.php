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
    ],
    [
        'amount' => '160',
        'currency' => 'EUR',
        'route_index' => '1',
        'source' => 'andromeda_claim_service',
        'service_type' => '9',
    ],
];
if ($actual !== $expected) throw new RuntimeException('observed service types were not preserved');
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
foreach (['fuel_total', 'surcharge_total', 'finalPriceReady', 'derived_price'] as $forbidden) {
    if (str_contains($encoded, $forbidden)) throw new RuntimeException('arithmetic leaked into evidence');
}
++$checks;

echo "ANDROMEDA_FUEL_SERVICE_CATEGORY_OK checks={$checks}\n";
