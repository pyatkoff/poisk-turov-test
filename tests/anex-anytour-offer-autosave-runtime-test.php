<?php
declare(strict_types=1);

// Reuse the existing real DTO/money/producer fixtures and their assertions. Only
// the mapping lookup and LOCAL persistence boundary below are test doubles.
require __DIR__ . '/anex-anytour-offer-autosave-test.php';

function runtimeCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('ANEX_RUNTIME_CHECK_FAILED:' . $label);
}

runtimeCheck(!class_exists('AnyTourAnexSearchMappingRegistry', false)
    && !class_exists('AnyTourOfferSnapshotIngestV1', false), 'isolated runtime boundaries');
if (true) {
    final class AnyTourAnexSearchMappingRegistry
    {
        public static function fromPdo(PDO $db): self { return new self(); }
        public function previewResolver(): callable
        {
            return static fn(string $namespace, string $external): ?int =>
                $namespace === 'anex_online' ? (['8101' => 3417, '8102' => 3418][$external] ?? null) : null;
        }
    }
    function v2_data_db(): PDO { return $GLOBALS['runtimeDb']; }
    function anytour_anex_search3_additional_application(array $evidence, array $rawOffer): array
    {
        return ($GLOBALS['runtimeApply'])($evidence, $rawOffer);
    }
    final class AnyTourOfferSnapshotIngestV1
    {
        public static array $snapshots = [];
        public static array $calls = [];
        public static bool $fail = false;
        public static function key(string $provider, array $params): string
        {
            return $provider . ':' . hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        }
        public static function index(array $rows): array
        {
            $out = [];
            foreach ($rows as $row) $out[$row['dto']['identity']['offer_ref_digest']] = $row;
            return $out;
        }
        public static function replaceCompleteSnapshot(PDO $db, string $provider, array $params, array $rows, DateTimeImmutable $at): array
        {
            return self::receive('complete_replace', $provider, $params, $rows, $at);
        }
        public static function mergePartialSnapshot(PDO $db, string $provider, array $params, array $rows, DateTimeImmutable $at): array
        {
            return self::receive('partial_additive', $provider, $params, $rows, $at);
        }
        private static function receive(string $mode, string $provider, array $params, array $rows, DateTimeImmutable $at): array
        {
            self::$calls[] = compact('mode', 'provider', 'params', 'rows', 'at');
            if (self::$fail) throw new RuntimeException('SIMULATED_LOCAL_INGEST_FAILURE');
            $key = self::key($provider, $params);
            $incoming = self::index($rows);
            self::$snapshots[$key] = $mode === 'complete_replace'
                ? $incoming : array_replace(self::$snapshots[$key] ?? [], $incoming);
            return ['provider' => $provider, 'offerCount' => count($rows), 'snapshotMode' => $mode, 'selectionAuthority' => false];
        }
    }
}

$GLOBALS['runtimeDb'] = $db;
$GLOBALS['runtimeApply'] = $apply;
$runtimeNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$runtimeCreated = $runtimeNow->getTimestamp() - 60;
$baseState = $state;
unset($baseState['anytour_offer_autosave']);
$baseState['gateway']['saved_offers']['created_at'] = $runtimeCreated;
$baseState['gateway']['saved_offers']['expires_at'] = $runtimeCreated + 900;
$baseState['gateway']['saved_offers']['offers'][$offerRef]['observed_at'] = $runtimeCreated + 10;

// Produce a genuine prior ready DTO, rather than inventing its price or expiry.
$previousRows = [];
$priorState = $baseState;
$prior = AnyTourAnexOfferAutosaveV1::consume($db, $plan, $priorState, $terminal, $runtimeNow, $apply, $resolver,
    static function (string $provider, array $search, array $rows, DateTimeImmutable $at) use (&$previousRows): array {
        $previousRows = $rows;
        return ['provider' => $provider, 'offerCount' => count($rows), 'selectionAuthority' => false];
    });
runtimeCheck($prior['published'] === true && count($previousRows) === 1, 'real prior ready DTO');
$key = AnyTourOfferSnapshotIngestV1::key('anex', $params);
$previousIndex = AnyTourOfferSnapshotIngestV1::index($previousRows);
$previousRef = array_key_first($previousIndex);
AnyTourOfferSnapshotIngestV1::$snapshots[$key] = $previousIndex;
AnyTourOfferSnapshotIngestV1::$snapshots['unrelated-scope'] = $previousIndex;

$firstRef = 'anex_online:' . str_repeat('b', 64);
$firstOffer = $offer;
$firstOffer['offer_key'] = $firstRef;
$firstOffer['hotel']['external_id'] = '8102';
$firstOffer['hotel']['local_id'] = 3418;
$firstEntry = $baseState['gateway']['saved_offers']['offers'][$offerRef];
$firstEntry['offer'] = $firstOffer;
$runtimeState = $baseState;
$runtimeState['gateway']['saved_offers']['offers'] = [$firstRef => $firstEntry];
$runtimeState['gateway']['search']['offers'] = [['offer_key' => $firstRef, 'kind' => 'concrete', 'hotel_external_id' => '8102']];
$firstPlan = ['offers' => [['offer_ref' => $firstRef, 'local_hotel_id' => 3418, 'context_digest' => $digest]]];

$oldOverride = getenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE');
// require_once of this already-running test keeps the fake LOCAL class isolated
// from any real runtime file in the checkout. No server configuration is loaded.
putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE=' . __FILE__);
try {
    $first = anytour_anex_anytour_offer_autosave_runtime($firstPlan, $runtimeState, $terminal);
    $kept = isset(AnyTourOfferSnapshotIngestV1::$snapshots[$key][$previousRef]);
    echo 'ANEX_RUNTIME_FIRST_BATCH ' . json_encode([
        'mode' => AnyTourOfferSnapshotIngestV1::$calls[0]['mode'] ?? null,
        'prior_preserved' => $kept,
        'visible_fixture_rows' => count(AnyTourOfferSnapshotIngestV1::$snapshots[$key]),
    ], JSON_THROW_ON_ERROR) . "\n";
    runtimeCheck($first['published'] === true && $first['readyOfferCount'] === 1, 'first batch really reaches runtime intake');
    runtimeCheck($kept && count(AnyTourOfferSnapshotIngestV1::$snapshots[$key]) === 2, 'first partial batch preserves prior offer');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$calls[0]['mode'] === 'partial_additive', 'existing additive method selected');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$calls[0]['provider'] === 'anex'
        && AnyTourOfferSnapshotIngestV1::$calls[0]['params'] === $params, 'exact provider and search forwarded');
    $firstRow = AnyTourOfferSnapshotIngestV1::$calls[0]['rows'][0];
    runtimeCheck($firstRow['anytour_hotel_id'] === 502 && $firstRow['dto']['finalPrice'] === '110000'
        && $firstRow['dto']['finalPriceReady'] === true, 'real canonical DTO and protected price unchanged');

    $secondRef = 'anex_online:' . str_repeat('e', 64);
    $secondEntry = $baseState['gateway']['saved_offers']['offers'][$offerRef];
    $secondEntry['offer']['offer_key'] = $secondRef;
    $runtimeState['gateway']['saved_offers']['offers'][$secondRef] = $secondEntry;
    $secondPlan = ['offers' => [['offer_ref' => $secondRef, 'local_hotel_id' => 3417, 'context_digest' => $digest]]];
    $second = anytour_anex_anytour_offer_autosave_runtime($secondPlan, $runtimeState, $terminal);
    runtimeCheck($second['published'] === true && $second['readyOfferCount'] === 2, 'later cumulative batch published');
    runtimeCheck(count(AnyTourOfferSnapshotIngestV1::$snapshots[$key]) === 3, 'prior plus both fresh offers preserved');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$snapshots[$key][$previousRef] === $previousIndex[$previousRef], 'prior DTO and expiry untouched');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$snapshots['unrelated-scope'] === $previousIndex, 'unrelated scope untouched');
    $final = anytour_anex_anytour_offer_autosave_finalize_runtime($runtimeState);
    runtimeCheck($final['published'] === false && $final['reason'] === 'already_published'
        && count(AnyTourOfferSnapshotIngestV1::$calls) === 2, 'finalize does not repeat or replace partial intake');

    $before = AnyTourOfferSnapshotIngestV1::$snapshots;
    $unknownState = $baseState;
    $unknown = anytour_anex_anytour_offer_autosave_runtime($plan, $unknownState, []);
    runtimeCheck($unknown['published'] === false && $unknown['reason'] === 'no_final_price_ready'
        && count(AnyTourOfferSnapshotIngestV1::$calls) === 2, 'unknown fuel never makes an empty destructive intake');
    AnyTourOfferSnapshotIngestV1::$fail = true;
    $failedState = $baseState;
    $failed = anytour_anex_anytour_offer_autosave_runtime($plan, $failedState, $terminal);
    runtimeCheck($failed['published'] === false && $failed['reason'] === 'autosave_failed', 'intake failure remains fail-closed');
    runtimeCheck(count(AnyTourOfferSnapshotIngestV1::$calls) === 3
        && ($failedState['anytour_offer_autosave']['last_published_digest'] ?? null) === null, 'failed intake not marked published or retried');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$snapshots === $before, 'fixture failure leaves earlier results intact');
    runtimeCheck(array_unique(array_column(AnyTourOfferSnapshotIngestV1::$calls, 'mode')) === ['partial_additive'], 'no replacement fallback');
} finally {
    putenv($oldOverride === false ? 'ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE' : 'ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE=' . $oldOverride);
}
echo "ANEX_RUNTIME_PARTIAL_PRESERVATION_OK batches=2 prior=1 final_no_replay=1 unknown=1 failed_intake=1 supplier=0 real_db=0\n";
