<?php
declare(strict_types=1);

// Reuse real ANEX contracts, producer and fixtures. Only the final intake is a
// callback; canonical lookups execute against an isolated native SQLite database.
require __DIR__ . '/anex-anytour-offer-autosave-test.php';

function lookupCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('ANEX_AUTOSAVE_LOOKUP_TEST:' . $label);
}
final class AnexLookupCountingPdo extends PDO
{
    public int $lookups = 0;
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FROM anytour_hotel_sources s JOIN anytour_hotels h')) ++$this->lookups;
        return parent::prepare($query, $options);
    }
}
$lookupDb = new AnexLookupCountingPdo('sqlite::memory:');
$lookupDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$lookupDb->exec('CREATE TABLE anytour_hotels (id INTEGER PRIMARY KEY, is_active INTEGER NOT NULL)');
$lookupDb->exec('CREATE TABLE anytour_hotel_sources (anytour_hotel_id INTEGER NOT NULL, namespace TEXT NOT NULL, external_key TEXT NOT NULL)');
$lookupDb->exec('INSERT INTO anytour_hotels VALUES(501,1),(502,1),(503,1)');
$lookupDb->exec("INSERT INTO anytour_hotel_sources VALUES(501,'legacy_catalog','3417'),(502,'legacy_catalog','3418')");
$lookupRows = [];
$lookupIngest = static function (string $provider, array $params, array $rows, DateTimeImmutable $at) use (&$lookupRows): array {
    $lookupRows[] = $rows;
    return ['provider' => $provider, 'offerCount' => count($rows), 'selectionAuthority' => false];
};
$lookupState = $state;
unset($lookupState['anytour_offer_autosave']);
$lookupState['gateway']['saved_offers']['offers'] = [];
$lookupState['gateway']['search']['offers'] = [];
$lookupTemplate = $state['gateway']['saved_offers']['offers'][$offerRef];
$lookupPlan = ['offers' => []];
for ($i = 1; $i <= 60; ++$i) {
    $ref = 'anex_online:' . hash('sha256', 'lookup-bulk-' . $i);
    $entry = $lookupTemplate;
    $entry['offer']['offer_key'] = $ref;
    $lookupState['gateway']['saved_offers']['offers'][$ref] = $entry;
    $lookupState['gateway']['search']['offers'][] = ['offer_key' => $ref, 'kind' => 'concrete', 'hotel_external_id' => '8101'];
    $lookupPlan['offers'][] = ['offer_ref' => $ref, 'local_hotel_id' => 3417, 'context_digest' => $digest];
    if ($i % 6 !== 0) continue;
    $receipt = AnyTourAnexOfferAutosaveV1::consume($lookupDb, $lookupPlan, $lookupState, $terminal, $now, $apply, $resolver, $lookupIngest);
    lookupCheck($receipt['published'] === true && $receipt['readyOfferCount'] === $i, 'cumulative publication unchanged');
    $lookupPlan = ['offers' => []];
}
$lookupRowCount = array_sum(array_map('count', $lookupRows));
$lookupDtoHash = hash('sha256', json_encode($lookupRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'ANEX_AUTOSAVE_LOOKUP_MEASURE ' . json_encode([
    'offers' => 60, 'batches' => 10, 'canonical_selects' => $lookupDb->lookups,
    'producer_rows' => $lookupRowCount, 'dto_sha256' => $lookupDtoHash,
], JSON_THROW_ON_ERROR) . "\n";
lookupCheck($lookupRowCount === 330 && count($lookupRows) === 10, 'no change to intake batching or row count');
foreach ($lookupRows as $rows) foreach ($rows as $row) {
    lookupCheck($row['anytour_hotel_id'] === 501 && $row['dto']['finalPrice'] === '110000'
        && $row['dto']['finalPriceReady'] === true, 'canonical identity and protected price unchanged');
}
lookupCheck($lookupDb->lookups === 10, 'one canonical lookup per hotel per consume, not per offer');

// Reusing a search state must never reuse a canonical answer across invocations.
$lookupDb->lookups = 0;
$again = AnyTourAnexOfferAutosaveV1::consume($lookupDb, ['offers' => []], $lookupState, [], $now, $apply, $resolver, $lookupIngest);
lookupCheck($again['reason'] === 'already_published' && $lookupDb->lookups === 1 && count($lookupRows) === 10,
    'idempotent finalizer still reads the current bridge once');
$lookupDb->exec("DELETE FROM anytour_hotel_sources WHERE external_key='3417'");
$lookupDb->lookups = 0;
$missing = AnyTourAnexOfferAutosaveV1::consume($lookupDb, ['offers' => []], $lookupState, [], $now, $apply, $resolver, $lookupIngest);
lookupCheck($missing['published'] === false && $missing['reason'] === 'canonical_bridge_changed'
    && $lookupDb->lookups === 1 && count($lookupRows) === 10, 'removed bridge not cached across calls');
$lookupDb->exec("INSERT INTO anytour_hotel_sources VALUES(503,'legacy_catalog','3417')");
$lookupDb->lookups = 0;
$changed = AnyTourAnexOfferAutosaveV1::consume($lookupDb, ['offers' => []], $lookupState, [], $now, $apply, $resolver, $lookupIngest);
lookupCheck($changed['published'] === true && $lookupDb->lookups === 1 && count($lookupRows) === 11,
    'changed canonical target is resolved on the next invocation');
foreach ($lookupRows[10] as $row) lookupCheck($row['anytour_hotel_id'] === 503, 'no stale canonical target');
$lookupDb->exec("INSERT INTO anytour_hotel_sources VALUES(501,'legacy_catalog','3417')");
$lookupDb->lookups = 0;
$ambiguous = AnyTourAnexOfferAutosaveV1::consume($lookupDb, ['offers' => []], $lookupState, [], $now, $apply, $resolver, $lookupIngest);
lookupCheck($ambiguous['reason'] === 'canonical_bridge_changed' && $lookupDb->lookups === 1 && count($lookupRows) === 11,
    'ambiguous canonical target remains fail closed');
$lookupDb->exec("DELETE FROM anytour_hotel_sources WHERE external_key='3417'");
$lookupDb->exec("INSERT INTO anytour_hotel_sources VALUES(501,'legacy_catalog','3417')");
$lookupDb->exec('UPDATE anytour_hotels SET is_active=0 WHERE id=501');
$lookupDb->lookups = 0;
$inactive = AnyTourAnexOfferAutosaveV1::consume($lookupDb, ['offers' => []], $lookupState, [], $now, $apply, $resolver, $lookupIngest);
lookupCheck($inactive['reason'] === 'canonical_bridge_changed' && $lookupDb->lookups === 1, 'inactive canonical target rejected');

// Negative answers are memoized only inside this invocation too.
$negative = $lookupState;
unset($negative['anytour_offer_autosave']);
$negativePlan = ['offers' => []];
foreach (array_slice(array_keys($negative['gateway']['saved_offers']['offers']), 0, 6) as $ref) {
    $negativePlan['offers'][] = ['offer_ref' => $ref, 'local_hotel_id' => 3417, 'context_digest' => $digest];
}
$lookupDb->lookups = 0;
$noBridge = AnyTourAnexOfferAutosaveV1::consume($lookupDb, $negativePlan, $negative, $terminal, $now, $apply, $resolver, $lookupIngest);
lookupCheck($noBridge['reason'] === 'no_final_price_ready' && $lookupDb->lookups === 1, 'null answers do not cause repeated SQL');
$lookupDb->exec('UPDATE anytour_hotels SET is_active=1 WHERE id=501');
$lookupDb->lookups = 0;
$restored = AnyTourAnexOfferAutosaveV1::consume($lookupDb, $negativePlan, $negative, $terminal, $now, $apply, $resolver, $lookupIngest);
lookupCheck($restored['published'] === true && $restored['readyOfferCount'] === 6 && $lookupDb->lookups === 1,
    'negative answer expires before the next invocation');

// Charter and regular processing share the same invocation-local lookup owner.
$mixedLookup = $negative;
unset($mixedLookup['anytour_offer_autosave']);
foreach (range(1, 6) as $i) {
    $ref = 'anex_online:' . hash('sha256', 'lookup-regular-' . $i);
    $entry = $lookupTemplate;
    $entry['offer'] = $regularOffer;
    $entry['offer']['offer_key'] = $ref;
    $mixedLookup['gateway']['saved_offers']['offers'][$ref] = $entry;
}
$lookupDb->lookups = 0;
$mixReceipt = AnyTourAnexOfferAutosaveV1::consume($lookupDb, $negativePlan, $mixedLookup, $terminal, $now, $apply, $mixedResolver, $lookupIngest);
lookupCheck($mixReceipt['published'] === true && $mixReceipt['readyOfferCount'] === 6
    && $mixReceipt['confirmationRequiredOfferCount'] === 6 && $lookupDb->lookups === 2, 'mixed two-hotel snapshot uses two lookups');
echo "ANEX_AUTOSAVE_LOOKUP_OK offers=60 batches=10 selects=10 invalidation=5 negative=1 mixed=1 supplier=0 live_db=0\n";
