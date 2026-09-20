<?php
declare(strict_types=1);

// Actual CLI wiring plus real retained-cohort autosave/producer. Supplier search,
// current mapping and final DB intake are doubles; files are disposable.
require __DIR__ . '/andromeda-local-offer-collector-cli-test.php';
require __DIR__ . '/andromeda-anytour-offer-autosave-test.php';
require_once __DIR__ . '/../app/integrations/andromeda-local-offer-collector.php';

function zeroCaptureCase(string $mode, int $seconds, array $verifiedPricing): void
{
    $dir = temp_searches();
    try {
        $ref = hash('sha256', 'zero-capture-' . $mode . '-' . $seconds);
        $created = time() - 30;
        $at = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $request = search_request();
        $rows = [];
        foreach ([false, true, null] as $index => $external) {
            $raw = normalized_offer('zero-' . $index);
            $raw['provider'] = 'andromeda';
            $raw['operator_ref'] = '1';
            $raw['transport_context'] = ['freight_external' => $external];
            $rows[] = ['page' => $index === 0 ? 1 : 2, 'offer' => $raw];
        }
        write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, [$rows[0]['offer']]));
        write_state($dir, $ref, $created, 2, state($ref, 1, 2, 2, $created, array_column(array_slice($rows, 1), 'offer')));
        $intakes = [];
        [$mapping, $canonical, , $save, $ingest] = callbacks($intakes);
        $pricing = static function(array $state, int $created, array $offer, array $current) use ($rows, $verifiedPricing): ?array {
            if ($offer['offer_ref'] === $rows[0]['offer']['offer_ref']) return $verifiedPricing;
            if ($offer['offer_ref'] === $rows[1]['offer']['offer_ref']) return party_surcharge();
            return null;
        };
        $counts = ['search' => 0, 'cohort' => 0, 'capture' => 0, 'clock' => 0, 'autosave' => 0];
        $search = static function(array $req) use ($request, $ref, &$counts): array {
            aassert($req === $request, 'zero capture changed search request');
            ++$counts['search'];
            return ['provider' => 'andromeda', 'search_ref' => $ref, 'status' => 'partial',
                'page' => 2, 'pages_count' => 2, 'grouped' => true, 'first_page_only' => false,
                'external_search_pending' => false, 'received_offers' => 3, 'mapped_offers' => 3];
        };
        $load = static function(string $value, int $generation) use ($ref, $rows, &$counts): array {
            aassert($value === $ref && $generation === 1, 'zero capture changed cohort identity');
            ++$counts['cohort']; return $rows;
        };
        $allow = static fn(array $selection, array $offer): bool => true;
        $capture = static function(array $selection) use (&$counts): array {
            ++$counts['capture']; throw new RuntimeException('ZERO_CAPTURE_MUST_NOT_RUN');
        };
        $clock = static function() use (&$counts): float {
            ++$counts['clock']; throw new RuntimeException('ZERO_CAPTURE_CLOCK_MUST_NOT_RUN');
        };
        $autosave = static function(array $req, string $value, int $generation) use (
            $request, $ref, $dir, $at, $mapping, $canonical, $pricing, $save, $ingest, &$counts
        ): array {
            aassert($req === $request && $value === $ref && $generation === 1, 'zero capture changed autosave binding');
            ++$counts['autosave'];
            return AnyTourAndromedaOfferAutosaveV1::consume($req, $dir, $value, $generation,
                $at, $mapping, $canonical, $pricing, $save, $ingest);
        };
        try {
            $result = AnyTourAndromedaLocalOfferCollectorV1::collect($request, $search, $load, $allow, $capture, $autosave,
                0, $mode, $seconds, $clock);
        } catch (Throwable $error) {
            echo 'ANDROMEDA_ZERO_CAPTURE_REJECTED ' . json_encode(['error' => $error->getMessage(), 'counts' => $counts]) . "\n";
            throw $error;
        }
        aassert($counts === ['search' => 1, 'cohort' => 1, 'capture' => 0, 'clock' => 0, 'autosave' => 1], 'zero capture orchestration changed');
        aassert($result['status'] === 'complete' && $result['ready_offer_count'] === 1
            && $result['autosave']['confirmationRequiredOfferCount'] === 2, 'zero capture lost saved price states');
        aassert($result['surcharge_capture_attempts'] === 0 && $result['surcharge_ready'] === 0
            && $result['selection_authority'] === false && $result['booking_calls'] === 0, 'zero capture gained authority');
        aassert(count($intakes) === 1 && count($intakes[0]['rows']) === 3, 'zero capture lost cohort rows');
        $dtos = array_column($intakes[0]['rows'], 'dto');
        aassert($dtos[0]['finalPriceReady'] === true && $dtos[0]['finalPrice'] === '199390'
            && $dtos[0]['final_price_verified'] === true, 'saved verified total not reused');
        assert_confirmation_dto($dtos[1]); assert_confirmation_dto($dtos[2]);
        $again = $autosave($request, $ref, 1);
        aassert($again['reason'] === 'already_published' && $again['readyOfferCount'] === 1
            && count($intakes) === 1, 'zero capture repeated persistence or invented readiness');
        echo 'ANDROMEDA_ZERO_CAPTURE_MEASURE ' . json_encode(['mode' => $mode, 'seconds' => $seconds,
            'stored' => 3, 'ready' => 1, 'confirmation' => 2, 'captures' => 0, 'new_repeat_intakes' => 0]) . "\n";
        foreach (['autosave_failed', 'local_ingest_unavailable'] as $reason) {
            $failed = ['published' => false, 'reason' => $reason, 'writeOutcome' => 'unknown'];
            $calls = 0;
            $failure = AnyTourAndromedaLocalOfferCollectorV1::collect($request, $search, $load, $allow, $capture,
                static function() use ($failed, &$calls): array { ++$calls; return $failed; }, 0, $mode);
            aassert($failure['status'] === 'incomplete' && $failure['autosave'] === $failed
                && $calls === 1 && $counts['capture'] === 0, 'zero capture retried or hid failed persistence');
        }
        $positiveCaptures = 0;
        $default = AnyTourAndromedaLocalOfferCollectorV1::collect($request, $search, $load, $allow,
            static function() use (&$positiveCaptures): array { ++$positiveCaptures; return ['status' => 'captured']; },
            static fn(): array => ['published' => false, 'reason' => 'already_published', 'readyOfferCount' => 1]);
        aassert($positiveCaptures === 2 && $default['surcharge_capture_attempts'] === 2, 'default capture budget changed');
    } finally { cleanup_dir($dir); }
}
foreach (['all', 'non_external_only'] as $mode) foreach ([0, 210] as $seconds) zeroCaptureCase($mode, $seconds, $verifiedPricing);
foreach (['all', 'non_external_only'] as $mode) {
    $run = familyCli(['--max-captures=0', '--capture-mode=' . $mode, '--max-capture-seconds=210', '--child-ages=7,3', '--region=20']);
    familyCheck($run['code'] === 0, 'actual CLI rejects zero capture: ' . $run['stderr']);
    $events = array_column($run['trace'], 1, 0);
    familyCheck($events['collector']['maxCaptures'] === 0 && $events['collector']['mode'] === $mode, 'CLI lost zero budget');
    familyCheck($events['search'] === $events['autosave'] && $events['search']['params']['childs'] === [3,7]
        && $events['search']['params']['regionIds'] === ['20'], 'zero budget changed family/destination');
}
foreach (['-1', '301', '0.0', '00', 'x'] as $badBudget) {
    $run = familyCli(['--max-captures=' . $badBudget]);
    familyCheck($run['code'] !== 0 && !in_array('catalog', array_column($run['trace'], 0), true), 'invalid budget reached search');
}
$unexpected = static function(): array { throw new RuntimeException('INVALID_INPUT_REACHED_CALLBACK'); };
foreach ([-1, 301] as $badBudget) {
    $refused = false;
    try { AnyTourAndromedaLocalOfferCollectorV1::collect(search_request(), $unexpected, $unexpected, $unexpected, $unexpected, $unexpected, $badBudget); }
    catch (InvalidArgumentException $error) { $refused = $error->getMessage() === 'ANDROMEDA_LOCAL_COLLECTOR_INPUT'; }
    aassert($refused, 'invalid capture bound accepted');
}
$refused = false;
try {
    AnyTourAndromedaLocalOfferCollectorV1::collect(search_request(),
        static fn(): array => ['provider' => 'andromeda', 'search_ref' => str_repeat('a',64), 'pages_count' => 2, 'status' => 'pending'],
        $unexpected, $unexpected, $unexpected, $unexpected, 0);
} catch (RuntimeException $error) { $refused = $error->getMessage() === 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH'; }
aassert($refused, 'zero capture bypassed completeness');
echo "ANDROMEDA_ZERO_CAPTURE_OK real_autosave=4 cli=2 invalid_cli=5 invalid_core=2 incomplete_search=1 default_unchanged=1 supplier_http=0 live_db=0\n";
