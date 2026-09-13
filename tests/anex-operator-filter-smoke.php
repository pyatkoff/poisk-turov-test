<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function operator_check(bool $passed, string $label): void
{
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
}

function operator_reject(callable $callback, string $label): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        operator_check($error->getMessage() === 'ANEX_FILTER_UNSUPPORTED' || $error->getMessage() === 'ANEX_INVALID_SEARCH', $label . ' error');
        return;
    }
    throw new RuntimeException('FAIL: ' . $label);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE tour_operator_identity_observations (id INTEGER PRIMARY KEY AUTOINCREMENT, operator_id INTEGER NOT NULL, operator_name TEXT, last_seen_at TEXT NOT NULL)');
$insert = $pdo->prepare('INSERT INTO tour_operator_identity_observations(operator_id,operator_name,last_seen_at) VALUES(?,?,?)');
$insert->execute([101, 'Анекс Тур', '2026-09-13 10:00:00']);
$insert->execute([102, 'FUN&SUN (RU)', '2026-09-13 10:00:00']);
$insert->execute([103, 'Анекс Тур', '2026-09-13 10:00:00']);
$insert->execute([103, 'FUN&SUN', '2026-09-13 09:00:00']);

operator_check(anytour_anex_search3_operator_scope($pdo, []) === 'all', 'empty selection keeps direct ANEX enabled');
operator_check(anytour_anex_search3_operator_scope($pdo, ['101']) === 'include', 'ANEX selection keeps direct ANEX enabled');
operator_check(anytour_anex_search3_operator_scope($pdo, ['101', '102']) === 'include', 'mixed selection containing ANEX keeps direct ANEX enabled');
operator_check(anytour_anex_search3_operator_scope($pdo, ['102']) === 'exclude', 'foreign-only selection excludes direct ANEX');
operator_reject(static fn() => anytour_anex_search3_operator_scope($pdo, ['999']), 'unknown Tourvisor operator fails closed');
operator_reject(static fn() => anytour_anex_search3_operator_scope($pdo, ['103']), 'ambiguous observed operator identity fails closed');
operator_reject(static fn() => anytour_anex_search3_operator_scope($pdo, ['0']), 'invalid operator ID rejected');

$date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$request = ['generation' => 7, 'params' => [
    'departureId' => 1, 'countryId' => 2, 'dateFrom' => $date, 'dateTo' => $date,
    'nightsFrom' => 7, 'nightsTo' => 7, 'adults' => 2, 'childs' => [], 'meal' => '',
    'operatorIds' => ['102'], 'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [], 'currency' => 'RUB',
]];
$cache = [];
$diagnostics = null;
$state = ['old_search' => true];
$result = anytour_anex_search3_run($request, $pdo, null, $cache, $diagnostics, null, $state, 'exclude');
operator_check($result['provider'] === 'anex' && $result['generation'] === 7, 'excluded provider returns normal provider slice');
operator_check($result['hotels'] === [] && $result['external_search_pending'] === false, 'excluded provider returns an empty settled slice');
operator_check($state === [], 'foreign-only replacement invalidates stale retained ANEX context');
operator_check($cache === [], 'foreign-only selection performs no supplier dictionary request');

echo "ANEX operator routing: PASS\n";
