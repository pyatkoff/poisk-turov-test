<?php
declare(strict_types=1);

// Reuse the complete-cohort regression suite and its synthetic page fixtures.
// No HTTP clients, live database, credentials or operator requests are used.
require_once __DIR__ . '/andromeda-anytour-offer-autosave-test.php';

$partialChecks = 0;
function pa_assert(bool $condition, string $message): void {
    global $partialChecks;
    ++$partialChecks;
    if (!$condition) throw new RuntimeException('received-page: ' . $message);
}
function pa_consume(string $dir, string $ref, int $created, int $page, array $cb, ?array $request = null): array {
    $request ??= search_request();
    $request['page'] = $page;
    return AnyTourAndromedaOfferAutosaveV1::consume(
        $request, $dir, $ref, 1, new DateTimeImmutable('@' . ($created + 30)),
        $cb[0], $cb[1], $cb[2], $cb[3], $cb[4], $page
    );
}

$dir = temp_searches();
$ref = hash('sha256', 'received-page-no-auto-continue');
$created = (new DateTimeImmutable('2026-09-29T00:00:00Z'))->getTimestamp();
$one = normalized_offer('partial-one');
$two = normalized_offer('partial-two', 'Intourist', 102, '200', '220000');
$ingests = [];
$cb = callbacks($ingests);
try {
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 4, $created, [$one]));
    $first = pa_consume($dir, $ref, $created, 1, $cb);
    pa_assert(($first['published'] ?? false) === true, 'page 1/4 must persist before page 2 exists');
    pa_assert(count($ingests) === 1 && count($ingests[0]['rows']) === 1, 'only received row persisted');
    pa_assert(($first['snapshotMode'] ?? null) === 'partial_additive', 'receipt must not claim supplier completeness');
    assert_confirmation_dto($ingests[0]['rows'][0]['dto']);
    pa_assert(!is_file($dir . '/' . $ref . '-' . $created . '-2.json'), 'no next-page acquisition');
    $again = pa_consume($dir, $ref, $created, 1, $cb);
    pa_assert(($again['reason'] ?? '') === 'already_published' && count($ingests) === 1, 'repeat page must not ingest twice');

    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 4, $created + 5, [$two]));
    $second = pa_consume($dir, $ref, $created, 2, $cb);
    pa_assert(($second['published'] ?? false) === true && count($ingests) === 2, 'received page 2 saves without pages 3/4');
    pa_assert(count($ingests[1]['rows']) === 1 && $ingests[1]['rows'][0]['anytour_hotel_id'] === 1002, 'page 2 must not re-publish or refresh page 1');
    assert_confirmation_dto($ingests[1]['rows'][0]['dto'], '220000');
    pa_consume($dir, $ref, $created, 1, $cb);
    pa_consume($dir, $ref, $created, 2, $cb);
    pa_assert(count($ingests) === 2, 'separate page checkpoints survive interleaved rereads');

    $missing = pa_consume($dir, $ref, $created, 3, $cb);
    pa_assert(($missing['published'] ?? false) === false && count($ingests) === 2, 'missing next page cannot clear saved data');
    $emptyState = state($ref, 1, 3, 0, $created + 10, []);
    $emptyState['status'] = 'complete';
    write_state($dir, $ref, $created, 3, $emptyState);
    $empty = pa_consume($dir, $ref, $created, 3, $cb);
    pa_assert(($empty['published'] ?? false) === false && count($ingests) === 2, 'terminal empty partial is not authoritative empty');

    $bad = state($ref, 2, 2, 4, $created + 5, [$two]);
    write_state($dir, $ref, $created, 2, $bad);
    try { pa_consume($dir, $ref, $created, 2, $cb); throw new LogicException('accepted wrong generation'); }
    catch (DomainException $expected) { pa_assert(count($ingests) === 2, 'generation mismatch must not ingest'); }
    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 4, $created + 5, [$two]));

    // A content/pricing change invalidates only this page checkpoint; neither an old
    // full-cohort checkpoint nor another page may suppress newly observed facts.
    $changed = $one; $changed['price']['amount'] = '185225';
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 4, $created, [$changed]));
    $update = pa_consume($dir, $ref, $created, 1, $cb);
    pa_assert(($update['published'] ?? false) === true && count($ingests) === 3, 'changed page must persist once');
    assert_confirmation_dto($ingests[2]['rows'][0]['dto'], '185225');
    pa_consume($dir, $ref, $created, 1, $cb);
    pa_assert(count($ingests) === 3, 'updated page remains idempotent');

    // The old complete-cohort API remains strict even when received pages were saved.
    $complete = AnyTourAndromedaOfferAutosaveV1::consume(
        search_request(), $dir, $ref, 1, new DateTimeImmutable('@' . ($created + 30)), ...$cb
    );
    pa_assert(($complete['published'] ?? false) === false, 'partial path cannot manufacture complete-cohort authority');
} finally { cleanup_dir($dir); }

foreach (['unmapped', 'excluded', 'empty', 'failed_ingest'] as $case) {
    $dir = temp_searches(); $ref = hash('sha256', 'partial-' . $case); $ingests = [];
    $offer = normalized_offer($case, $case === 'excluded' ? 'Anex Tour' : 'FUN&SUN');
    $offers = $case === 'empty' ? [] : [$offer];
    $cb = callbacks($ingests, null, $case !== 'unmapped');
    if ($case === 'failed_ingest') $cb[4] = static function() { throw new RuntimeException('synthetic_ingest_failure'); };
    try {
        write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, $offers));
        if ($case === 'failed_ingest') {
            try { pa_consume($dir, $ref, $created, 1, $cb); throw new LogicException('ingest failure swallowed'); }
            catch (RuntimeException $expected) { pa_assert($expected->getMessage() === 'synthetic_ingest_failure', 'ingest error retained'); }
            pa_assert(count(glob($dir . '/*anytour-offer-autosave*.json')) === 0, 'failed ingest must not write success checkpoint');
        } else {
            $result = pa_consume($dir, $ref, $created, 1, $cb);
            pa_assert(($result['published'] ?? false) === false && $ingests === [], $case . ' must not clear prior cohort');
        }
    } finally { cleanup_dir($dir); }
}
echo 'Received-page autosave: ' . $partialChecks . " checks passed; supplier HTTP 0, live DB 0\n";
