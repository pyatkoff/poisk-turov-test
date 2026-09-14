<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/andromeda-raw-catalog-schema.php';

$checks = 0;
function schema_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('schema_check_'.$checks); }

$reply = [
    'CHECKIN_BEG' => ['20260920'],
    'FREIGHTS' => [
        ['id' => 77, 'programKey' => 13, 'markup' => '160.00', 'currency' => 'USD', 'secretNote' => 'must-not-leak'],
        ['id' => 78, 'programKey' => 13, 'markup' => '0', 'currency' => 'USD'],
    ],
    'TRANSPORT_RULES' => [
        'applyFuelSurcharge' => true,
        'nested' => ['routeIndex' => 0, 'amount' => '80'],
    ],
    'odd-key!' => 'private-value',
];
$schema = anytour_andromeda_raw_catalog_schema($reply);
$json = json_encode($schema, JSON_THROW_ON_ERROR);

schema_check(($schema['schema_version'] ?? null) === 1);
schema_check(isset($schema['top_level']['FREIGHTS'], $schema['top_level']['TRANSPORT_RULES'], $schema['top_level']['$other']));
schema_check(($schema['top_level']['FREIGHTS']['type'] ?? null) === 'list');
schema_check(($schema['top_level']['FREIGHTS']['count'] ?? null) === 2);
schema_check(($schema['top_level']['FREIGHTS']['fields']['programKey'] ?? null) === ['int']);
schema_check(($schema['top_level']['FREIGHTS']['fields']['markup'] ?? null) === ['string']);
schema_check(($schema['top_level']['TRANSPORT_RULES']['fields']['applyFuelSurcharge']['type'] ?? null) === 'bool');
schema_check(!str_contains($json, '160.00'));
schema_check(!str_contains($json, '80'));
schema_check(!str_contains($json, 'private-value'));
schema_check(!str_contains($json, 'must-not-leak'));

$nested = anytour_andromeda_raw_catalog_schema(['rows'=>[
    ['freightBeg'=>'private-ref-101','freightEnd'=>'private-ref-102','programInc'=>'private-program'],
    ['freightBeg'=>'private-ref-201','freightEnd'=>'private-ref-202','programInc'=>'private-program-2'],
]]);
$nestedJson = json_encode($nested, JSON_THROW_ON_ERROR);
schema_check(($nested['top_level']['rows']['fields']['freightBeg'] ?? null) === ['string']);
schema_check(($nested['top_level']['rows']['fields']['freightEnd'] ?? null) === ['string']);
schema_check(($nested['top_level']['rows']['fields']['programInc'] ?? null) === ['string']);
schema_check(!str_contains($nestedJson, 'private-ref-101'));
schema_check(!str_contains($nestedJson, 'private-program'));

$requests = [];
$transport = static function (string $url, array $options) use (&$requests): array {
    $requests[] = $url;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (($query['action'] ?? '') === 'login') return ['status'=>200, 'body'=>json_encode(['sid'=>'schemaSession123'], JSON_THROW_ON_ERROR)];
    if (($query['action'] ?? '') === 'all') return ['status'=>200, 'body'=>json_encode([
        'CHECKIN_BEG'=>[], 'TOWNTO'=>[], 'STARS'=>[], 'HOTELS'=>[], 'MEAL'=>[], 'CURRENCY'=>[], 'OPERATORS'=>[],
        'FREIGHTS'=>[['id'=>55,'programKey'=>99,'markup'=>'160','currency'=>'USD']],
    ], JSON_THROW_ON_ERROR)];
    throw new RuntimeException('unexpected_action');
};
$client = new AnyTourAndromedaClient($transport, true);
$client->login('user', 'pass');
$liveSchema = anytour_andromeda_discover_all_schema($client, 1, 3);
schema_check(count($requests) === 2);
schema_check(str_contains($requests[1], 'action=all'));
schema_check(isset($liveSchema['top_level']['FREIGHTS']['fields']['markup']));
schema_check(!str_contains(json_encode($liveSchema, JSON_THROW_ON_ERROR), '160'));

echo 'Andromeda raw catalog schema: '.$checks." checks passed; values/secrets retained=0.\n";
