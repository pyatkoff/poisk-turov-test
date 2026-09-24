<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/andromeda-search-envelope-diagnostic.php';

function ok_diag(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

$generation = 17171801;
$complete = [
    'provider'=>'andromeda',
    'search_ref'=>str_repeat('a', 64),
    'pages_count'=>3,
    'status'=>'complete',
];
ok_diag(AnyTourAndromedaSearchEnvelopeDiagnosticV1::failureCode($complete, $generation) === null, 'complete accepted for authoritative collector');

$partial = [
    'provider'=>'andromeda',
    'search_ref'=>str_repeat('b', 64),
    'pages_count'=>3,
    'page'=>3,
    'status'=>'partial',
    'grouped'=>true,
    'first_page_only'=>false,
    'external_search_pending'=>false,
    'received_offers'=>1372,
    'mapped_offers'=>1273,
];
ok_diag(AnyTourAndromedaSearchEnvelopeDiagnosticV1::failureCode($partial, $generation) === null, 'drained partial accepted for authoritative collector');

$empty = [
    'provider'=>'andromeda',
    'search_ref'=>str_repeat('c', 64),
    'generation'=>$generation,
    'pages_count'=>0,
    'page'=>1,
    'status'=>'complete',
    'hotels'=>[],
    'grouped'=>true,
    'first_page_only'=>false,
    'external_search_pending'=>false,
    'received_offers'=>0,
    'mapped_offers'=>0,
];
ok_diag(AnyTourAndromedaSearchEnvelopeDiagnosticV1::failureCode($empty, $generation) === null, 'terminal empty accepted for authoritative collector');

$cases = [
    [null, 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_SHAPE'],
    [array_replace($complete, ['provider'=>'other']), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PROVIDER'],
    [array_replace($complete, ['search_ref'=>'private-native-id']), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_REF'],
    [array_replace($complete, ['pages_count'=>'3']), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PAGES'],
    [array_replace($complete, ['status'=>'pending']), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_STATUS'],
    [array_replace($partial, ['page'=>2]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_PAGE'],
    [array_replace($partial, ['grouped'=>false]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_GROUPED'],
    [array_replace($partial, ['first_page_only'=>true]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_FIRST_PAGE'],
    [array_replace($partial, ['external_search_pending'=>true]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_PENDING'],
    [array_replace($partial, ['received_offers'=>'1372']), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_COUNTS'],
    [array_replace($empty, ['page'=>2]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_PAGE'],
    [array_replace($empty, ['generation'=>$generation+1]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_GENERATION'],
    [array_replace($empty, ['status'=>'partial']), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_STATUS'],
    [array_replace($empty, ['hotels'=>[['id'=>'must-not-export']]]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_HOTELS'],
    [array_replace($empty, ['grouped'=>false]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_GROUPED'],
    [array_replace($empty, ['first_page_only'=>true]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_FIRST_PAGE'],
    [array_replace($empty, ['external_search_pending'=>true]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_PENDING'],
    [array_replace($empty, ['mapped_offers'=>1]), 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_COUNTS'],
];
foreach ($cases as [$input, $expected]) {
    $actual = AnyTourAndromedaSearchEnvelopeDiagnosticV1::failureCode($input, $generation);
    ok_diag($actual === $expected, 'expected '.$expected.' got '.var_export($actual, true));
    ok_diag((bool)preg_match('/\AANDROMEDA_LOCAL_COLLECTOR_SEARCH_[A-Z_]+\z/D', $actual), 'fixed safe code only');
    ok_diag(!str_contains($actual, 'private') && !str_contains($actual, 'must-not-export'), 'no supplier/private values in code');
}

echo "andromeda search envelope diagnostic tests: OK\n";
