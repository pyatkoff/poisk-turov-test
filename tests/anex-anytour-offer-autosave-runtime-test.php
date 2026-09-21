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
    echo 'ANEX_RUNTIME_FIRST_BATCH ' . json_encode([
        'published' => $first['published'] ?? null,
        'reason' => $first['reason'] ?? null,
        'local_calls' => count(AnyTourOfferSnapshotIngestV1::$calls),
        'prior_preserved' => isset(AnyTourOfferSnapshotIngestV1::$snapshots[$key][$previousRef]),
    ], JSON_THROW_ON_ERROR) . "\n";
    runtimeCheck($first['published'] === false && $first['reason'] === 'staged'
        && $first['readyOfferCount'] === 1, 'first batch stages exact ready count');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$calls === [], 'first bounded batch must not create LOCAL refresh');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$snapshots[$key] === $previousIndex, 'first staged batch keeps prior canonical snapshot untouched');

    $secondRef = 'anex_online:' . str_repeat('e', 64);
    $secondEntry = $baseState['gateway']['saved_offers']['offers'][$offerRef];
    $secondEntry['offer']['offer_key'] = $secondRef;
    $runtimeState['gateway']['saved_offers']['offers'][$secondRef] = $secondEntry;
    $secondPlan = ['offers' => [['offer_ref' => $secondRef, 'local_hotel_id' => 3417, 'context_digest' => $digest]]];
    $second = anytour_anex_anytour_offer_autosave_runtime($secondPlan, $runtimeState, $terminal);
    runtimeCheck($second['published'] === false && $second['reason'] === 'staged'
        && $second['readyOfferCount'] === 2, 'later cumulative batch remains staged');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$calls === []
        && AnyTourOfferSnapshotIngestV1::$snapshots[$key] === $previousIndex, 'second staged batch still has no canonical side effect');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$snapshots['unrelated-scope'] === $previousIndex, 'unrelated scope untouched while staging');

    $final = anytour_anex_anytour_offer_autosave_finalize_runtime($runtimeState);
    runtimeCheck($final['published'] === true && $final['readyOfferCount'] === 2
        && count(AnyTourOfferSnapshotIngestV1::$calls) === 1, 'finalize publishes accumulated union exactly once');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$calls[0]['mode'] === 'complete_replace', 'finalize must create authoritative complete refresh');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$calls[0]['provider'] === 'anex'
        && AnyTourOfferSnapshotIngestV1::$calls[0]['params'] === $params, 'final exact provider and search forwarded');
    runtimeCheck(count(AnyTourOfferSnapshotIngestV1::$snapshots[$key]) === 2
        && !isset(AnyTourOfferSnapshotIngestV1::$snapshots[$key][$previousRef]), 'complete final replaces stale prior scope with exact accumulated union');
    $finalAgain = anytour_anex_anytour_offer_autosave_finalize_runtime($runtimeState);
    runtimeCheck($finalAgain['published'] === false && $finalAgain['reason'] === 'already_published'
        && count(AnyTourOfferSnapshotIngestV1::$calls) === 1, 'identical finalization does not repeat intake');

    $before = AnyTourOfferSnapshotIngestV1::$snapshots;
    $unknownState = $baseState;
    $unknown = anytour_anex_anytour_offer_autosave_runtime($plan, $unknownState, []);
    runtimeCheck($unknown['published'] === false && $unknown['reason'] === 'no_final_price_ready'
        && count(AnyTourOfferSnapshotIngestV1::$calls) === 1, 'unknown fuel never makes an empty destructive intake');

    $failedState = $baseState;
    $staged = anytour_anex_anytour_offer_autosave_runtime($plan, $failedState, $terminal);
    runtimeCheck($staged['reason'] === 'staged' && count(AnyTourOfferSnapshotIngestV1::$calls) === 1, 'failure fixture stages before final intake');
    AnyTourOfferSnapshotIngestV1::$fail = true;
    $failed = anytour_anex_anytour_offer_autosave_finalize_runtime($failedState);
    runtimeCheck($failed['published'] === false && $failed['reason'] === 'autosave_failed', 'final complete intake failure remains fail-closed');
    runtimeCheck(count(AnyTourOfferSnapshotIngestV1::$calls) === 2
        && ($failedState['anytour_offer_autosave']['last_published_digest'] ?? null) === null, 'failed final intake not marked published or retried');
    runtimeCheck(AnyTourOfferSnapshotIngestV1::$snapshots === $before, 'fixture failure leaves earlier results intact');
    runtimeCheck(array_unique(array_column(AnyTourOfferSnapshotIngestV1::$calls, 'mode')) === ['complete_replace'], 'bounded batches never fall back to partial LOCAL intake');
} finally {
    putenv($oldOverride === false ? 'ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE' : 'ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE=' . $oldOverride);
}
echo "ANEX_RUNTIME_FINAL_ONLY_OK staged_batches=2 local_before_final=0 final_complete_replace=1 final_idempotent=1 unknown=1 failed_final=1 supplier=0 real_db=0\n";
