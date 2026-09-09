<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__ . '/../app/integrations/anex-normalizer.php';
$includeOutput = ob_get_clean();
$checks = 0;
function search_mapping_check(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $name);
    ++$checks;
}
function search_mapping_row(int $id, int $target, string $class = 'exact'): array
{
    return ['anex_hotel_id' => $id, 'catalog_hotel_id' => $target,
        'existing_catalog_hotel_id' => $target, 'match_class' => $class,
        'approval_policy' => 'owner_exact_and_strong_20260908', 'enabled' => 1, 'scope' => 'preview'];
}
function search_mapping_decision(int $id, string $status, ?int $target): array
{
    return ['anex_hotel_id' => $id, 'decision_status' => $status,
        'catalog_hotel_id' => $target, 'existing_catalog_hotel_id' => $target];
}
function search_mapping_exclusion(int $id, int $target): array
{
    return ['anex_hotel_id' => $id, 'catalog_hotel_id' => $target];
}

search_mapping_check($includeOutput === '', 'include has no output or runtime work');
$rows = [];
for ($id = 1; $id <= 12; ++$id) $rows[] = search_mapping_row($id, 100 + $id, $id === 2 ? 'strong_candidate' : 'exact');
$rows[] = search_mapping_row(14, 114, 'review');
$rows[] = array_replace(search_mapping_row(15, 115), ['scope' => 'production']);
$rows[] = array_replace(search_mapping_row(16, 116), ['scope' => null]);
$rows[2]['enabled'] = 0;
$rows[3]['approval_policy'] = 'unapproved_candidate';
$rows[4]['existing_catalog_hotel_id'] = null;
$decisions = [search_mapping_decision(6, 'accepted', 206),
    search_mapping_decision(7, 'rejected', 207),
    search_mapping_decision(8, 'needs_review', 208),
    search_mapping_decision(9, 'unknown', 209),
    search_mapping_decision(10, 'accepted', 210),
    search_mapping_decision(11, 'accepted', null),
    search_mapping_decision(13, 'accepted', 213)];
$decisions[4]['existing_catalog_hotel_id'] = null;
$registry = AnyTourAnexSearchMappingRegistry::fromRows($rows, $decisions);
search_mapping_check($registry->count() === 5, 'only policy-approved and manual accepted targets resolve');
search_mapping_check($registry->resolve('anex_online', 1, 'preview') === 101, 'exact accepted by owner policy');
search_mapping_check($registry->resolve('anex_xml', '2', 'preview') === 102, 'strong accepted by owner policy');
search_mapping_check($registry->resolve('anex_online', 6, 'preview') === 206, 'manual target overrides policy target');
search_mapping_check($registry->resolve('anex_online', 13, 'preview') === 213, 'valid manual acceptance without automated row');
foreach ([3, 4, 5, 7, 8, 9, 10, 11, 14, 15, 16] as $id) {
    search_mapping_check($registry->resolve('anex_online', $id, 'preview') === null,
        'disabled/unapproved/missing/rejected/review/unknown/class/scope identity stays blocked ' . $id);
}
foreach (['tourvisor_api', 'tourvisor', 'andromeda', 'anex', ''] as $provider) {
    search_mapping_check($registry->resolve($provider, 1, 'preview') === null, 'provider namespace fails closed');
}
search_mapping_check($registry->resolve('anex_online', 1) === null, 'default production scope disabled');
search_mapping_check($registry->resolve('anex_online', 1, 'production') === null, 'explicit production scope disabled');
search_mapping_check($registry->resolve('anex_online', 1, 'test') === null, 'unknown scope disabled');
foreach (['01', '+1', '1 ', '1e0', '', '-1', 1.0, true, null, '100000000'] as $bad) {
    search_mapping_check($registry->resolve('anex_online', $bad, 'preview') === null, 'invalid external ID fails closed');
}
search_mapping_check($registry->resolve('anex_online', 999, 'preview') === null, 'unmapped supplier ID is not a local fallback');
$resolver = $registry->previewResolver();
search_mapping_check($resolver('anex_online', '2') === 102, 'normalizer resolver contract');
search_mapping_check($resolver('anex_xml', 1) === 101, 'XML and Online share accepted identity');
search_mapping_check($resolver('tourvisor_api', 1) === null, 'resolver preserves provider boundary');
$normalized = anytour_anex_normalize_prices(['prices' => [[
    'id' => 'fixture-owner-approved', 'hotelKey' => 2, 'hotel' => 'Fixture Hotel',
    'checkIn' => '20260914', 'checkOut' => '20260921', 'nights' => 7,
    'adult' => 2, 'child' => 0, 'packetType' => 0, 'price' => '1000.00',
    'currency' => 'USD', 'grouped' => false, 'bron' => false,
]]], ['checkin_begin' => '20260914', 'checkin_end' => '20260916',
    'nights_from' => 7, 'nights_till' => 10, 'adults' => 2, 'children' => 0], $resolver);
search_mapping_check($normalized['offers'][0]['hotel']['local_id'] === 102
    && $normalized['offers'][0]['hotel']['mapping_status'] === 'resolved'
    && $normalized['offers'][0]['supplier_booking_flag'] === false,
    'ordinary Online price normalization uses DB identity without a booking flag requirement');

$pairRegistry = AnyTourAnexSearchMappingRegistry::fromRows([
    search_mapping_row(21, 121), search_mapping_row(22, 122, 'strong_candidate'),
    search_mapping_row(23, 123), search_mapping_row(24, 124), search_mapping_row(25, 125),
    search_mapping_row(26, 126), search_mapping_row(27, 127), search_mapping_row(29, 121),
], [
    search_mapping_decision(23, 'accepted', 223), search_mapping_decision(24, 'accepted', 224),
    search_mapping_decision(26, 'needs_review', 226), search_mapping_decision(28, 'accepted', 228),
], [
    search_mapping_exclusion(21, 121), search_mapping_exclusion(22, 122),
    search_mapping_exclusion(23, 223), search_mapping_exclusion(24, 124),
    search_mapping_exclusion(25, 225), search_mapping_exclusion(25, 325),
    search_mapping_exclusion(26, 226), search_mapping_exclusion(28, 228),
]);
search_mapping_check($pairRegistry->count() === 4, 'only permitted exact pairs remain available');
foreach ([21, 22, 23, 26, 28] as $id) {
    search_mapping_check($pairRegistry->resolve('anex_online', $id, 'preview') === null,
        'rejected exact/strong/manual pair or existing review decision stays blocked ' . $id);
}
search_mapping_check($pairRegistry->resolve('anex_online', 23, 'preview') === null,
    'excluded manual target never falls back to a different automated target');
search_mapping_check($pairRegistry->resolve('anex_online', 24, 'preview') === 224,
    'manual acceptance of an alternative target survives exclusion of the old pair');
search_mapping_check($pairRegistry->resolve('anex_online', 25, 'preview') === 125,
    'several excluded alternatives do not block an allowed target for the same ANEX hotel');
search_mapping_check($pairRegistry->resolve('anex_online', 27, 'preview') === 127,
    'unrelated existing mapping remains available');
search_mapping_check($pairRegistry->resolve('anex_online', 29, 'preview') === 121,
    'pair exclusion does not become a global catalog hotel exclusion');
search_mapping_check($pairRegistry->resolve('anex_xml', 21, 'preview') === null
    && ($pairRegistry->previewResolver())('anex_online', '21') === null,
    'pair exclusion applies to both accepted namespaces and the normalizer resolver');
$duplicateExclusions = AnyTourAnexSearchMappingRegistry::fromRows([search_mapping_row(1, 101)], [], [
    search_mapping_exclusion(1, 101), ['anex_hotel_id' => '1', 'catalog_hotel_id' => '101'],
]);
search_mapping_check($duplicateExclusions->count() === 0,
    'repeated exclusion of the same exact pair is idempotent for numeric SQL strings and integers');

foreach ([[search_mapping_row(1, 101), search_mapping_row(1, 102)],
    [array_replace(search_mapping_row(1, 101), ['anex_hotel_id' => '01'])],
    array_fill(0, 50001, search_mapping_row(1, 101))] as $invalidRows) {
    try {
        AnyTourAnexSearchMappingRegistry::fromRows($invalidRows);
        throw new RuntimeException('FAILED: invalid registry rows accepted');
    } catch (UnexpectedValueException $error) {
        search_mapping_check($error->getMessage() === 'anex_search_mapping_registry_invalid',
            'duplicate/malformed/oversized rows rejected');
    }
}
try {
    AnyTourAnexSearchMappingRegistry::fromRows([], [search_mapping_decision(1, 'accepted', 1),
        search_mapping_decision(1, 'rejected', null)]);
    throw new RuntimeException('FAILED: duplicate decisions accepted');
} catch (UnexpectedValueException $error) {
    search_mapping_check(true, 'duplicate manual decisions cannot become order-dependent');
}

$invalidExclusions = [
    [null], [[]],
    array_fill(0, 50001, search_mapping_exclusion(1, 101)),
];
foreach (['01', '+1', '1 ', '1e0', '', '-1', 1.0, true, null, '100000000'] as $bad) {
    $invalidExclusions[] = [array_replace(search_mapping_exclusion(1, 101), ['anex_hotel_id' => $bad])];
}
foreach (['0101', '+101', '101 ', '1e2', '', '-1', 101.0, true, null, '2147483648', '10000000000'] as $bad) {
    $invalidExclusions[] = [array_replace(search_mapping_exclusion(1, 101), ['catalog_hotel_id' => $bad])];
}
foreach ($invalidExclusions as $exclusions) {
    try {
        AnyTourAnexSearchMappingRegistry::fromRows([search_mapping_row(1, 101)], [], $exclusions);
        throw new RuntimeException('FAILED: invalid pair exclusions accepted');
    } catch (UnexpectedValueException $error) {
        search_mapping_check($error->getMessage() === 'anex_search_mapping_registry_invalid',
            'malformed/oversized pair exclusions fail closed');
    }
}
unset($invalidExclusions);

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE catalog_hotels (id INTEGER PRIMARY KEY)');
    $pdo->exec('CREATE TABLE anex_hotel_search_mappings (anex_hotel_id INTEGER PRIMARY KEY,'
        . 'catalog_hotel_id INTEGER,match_class TEXT,approval_policy TEXT,enabled INTEGER,scope TEXT)');
    $pdo->exec('CREATE TABLE anex_hotel_decisions (anex_hotel_id INTEGER PRIMARY KEY,'
        . 'catalog_hotel_id INTEGER,decision_status TEXT)');
    $pdo->exec('INSERT INTO catalog_hotels (id) VALUES (101),(102),(103),(106),(107),(108),(109),(110),(111),(114),(115),(116),(206),(213)');
    $insert = $pdo->prepare('INSERT INTO anex_hotel_search_mappings VALUES (?,?,?,?,?,?)');
    foreach ($rows as $row) $insert->execute([$row['anex_hotel_id'], $row['catalog_hotel_id'],
        $row['match_class'], $row['approval_policy'], $row['enabled'], $row['scope']]);
    $insert = $pdo->prepare('INSERT INTO anex_hotel_decisions VALUES (?,?,?)');
    foreach ($decisions as $row) $insert->execute([$row['anex_hotel_id'], $row['catalog_hotel_id'], $row['decision_status']]);
    $fromDb = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    search_mapping_check($fromDb->count() === 4,
        'legacy database without exclusions table still joins valid targets and manual acceptance');
    search_mapping_check($fromDb->resolve('anex_online', 1, 'preview') === 101
        && $fromDb->resolve('anex_online', 2, 'preview') === 102, 'SQL reads exact and strong identities');
    search_mapping_check($fromDb->resolve('anex_online', 6, 'preview') === 206
        && $fromDb->resolve('anex_online', 13, 'preview') === 213, 'SQL reads independent manual overrides');
    search_mapping_check((int) $pdo->query('SELECT COUNT(*) FROM anex_hotel_decisions')->fetchColumn() === 7,
        'registry never writes manual decisions');

    $pdo->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INTEGER NOT NULL,'
        . 'catalog_hotel_id INTEGER NOT NULL,PRIMARY KEY (anex_hotel_id,catalog_hotel_id))');
    $pdo->exec('INSERT INTO anex_review_pair_exclusions VALUES (1,101),(2,999),(2,998),(6,206)');
    $storedDecisions = $pdo->query('SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
    $storedExclusions = $pdo->query('SELECT * FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id')
        ->fetchAll(PDO::FETCH_ASSOC);
    $fromDbWithExclusions = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    search_mapping_check($fromDbWithExclusions->count() === 2
        && $fromDbWithExclusions->resolve('anex_online', 1, 'preview') === null
        && $fromDbWithExclusions->resolve('anex_online', 6, 'preview') === null,
        'SQL exclusions suppress policy and historical manual acceptance of rejected pairs');
    search_mapping_check($fromDbWithExclusions->resolve('anex_online', 2, 'preview') === 102
        && $fromDbWithExclusions->resolve('anex_online', 13, 'preview') === 213,
        'SQL exclusions preserve allowed alternatives and unrelated manual decisions');
    search_mapping_check($pdo->query('SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC)
        === $storedDecisions
        && $pdo->query('SELECT * FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id')
            ->fetchAll(PDO::FETCH_ASSOC) === $storedExclusions,
        'registry reads never modify exclusions or previous manual decisions');

    $pdo->exec('DROP TABLE anex_review_pair_exclusions');
    $pdo->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INTEGER PRIMARY KEY)');
    $failedClosed = false;
    try {
        AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    } catch (Throwable $error) {
        $failedClosed = true;
    }
    search_mapping_check($failedClosed, 'existing exclusions table with missing columns cannot silently enable mappings');

    $pdo->exec('DROP TABLE anex_review_pair_exclusions');
    $pdo->exec('CREATE VIEW anex_review_pair_exclusions AS'
        . ' SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions_v2');
    $failedClosed = false;
    try {
        AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    } catch (Throwable $error) {
        $failedClosed = true;
    }
    search_mapping_check($failedClosed,
        'missing dependency during exclusion reads is not mistaken for an absent optional exclusions table');
} else {
    echo "ANEX search mapping registry: SQLite integration unavailable\n";
}
echo 'ANEX search mapping registry: ' . $checks . " checks passed\n";
