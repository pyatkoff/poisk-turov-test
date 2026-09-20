<?php
declare(strict_types=1);

// Existing actual-CLI fixtures and real autosave/producer contracts. Only supplier
// search, mapping and the final DB intake are doubled; files are disposable.
require __DIR__ . '/andromeda-local-offer-collector-cli-test.php';
require __DIR__ . '/andromeda-anytour-offer-autosave-test.php';
require_once __DIR__ . '/../app/integrations/andromeda-local-offer-collector.php';

foreach (['all', 'non_external_only'] as $zeroMode) foreach ([0, 210] as $zeroSeconds) {
    $zeroDir = temp_searches();
    try {
        $zeroRef = hash('sha256', 'zero-capture-' . $zeroMode . '-' . $zeroSeconds);
        $zeroCreated = time() - 30;
        $zeroAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $zeroRequest = search_request();
        $zeroRows = [];
        foreach ([false, true, null] as $index => $external) {
            $raw = normalized_offer('zero-' . $index);
            $raw['provider'] = 'andromeda';
            $raw['operator_ref'] = '1';
            $raw['transport_context'] = ['freight_external' => $external];
            $zeroRows[] = ['page' => $index === 0 ? 1 : 2, 'offer' => $raw];
        }
        write_state($zeroDir, $zeroRef, $zeroCreated, 1,
            state($zeroRef, 1, 1, 2, $zeroCreated, [$zeroRows[0]['offer']]));
        write_state($zeroDir, $zeroRef, $zeroCreated, 2,
            state($zeroRef, 1, 2, 2, $zeroCreated, array_column(array_slice($zeroRows, 1), 'offer')));
        $zeroIng ests = [];
        [$zeroMapping, $zeroCanonical, , $zeroSave, $zeroIngest] = callbacks($zeroIng ests);
        $zeroPricing = static function(array $state, int $created, array $offer, array $current) use ($zeroRows, $verifiedPricing): ?array {
            if ($offer['offer_ref'] === $zeroRows[0]['offer']['offer_ref']) return $verifiedPricing;
            if ($offer['offer_ref'] === $zeroRows[1]['offer']['offer_ref']) return party_surcharge();
            return null;
        };
        $zeroCounts = ['search' => 0, 'cohort' => 0, 'capture' => 0, 'clock' => 0, 'autosave' => 0];
        $zeroSearch = static function(array $request) use ($zeroRequest, $zeroRef, &$zeroCounts): array {
            aassert($request === $zeroRequest, 'zero capture changed search request');
            ++$zeroCounts['search'];
            return ['provider' => 'andromeda', 'search_ref' => $zeroRef, 'status' => 'partial',
                'page' => 2, 'pages_count' => 2, 'grouped' => true, 'first_page_only' => false,
                'external_search_pending' => false, 'received_offers' => 3, 'mapped_offers' => 3];
        };
        $zeroLoad = static function(string $ref, int $generation) use ($zeroRef, $zeroRows, &$zeroCounts): array {
            aassert($ref === $zeroRef && $generation === 1, 'zero capture changed cohort identity');
            ++$zeroCounts['cohort']; return $zeroRows;
        };
        $zeroCapture = static function(array $selection) use (&$zeroCounts): array {
            ++$zeroCounts['capture']; throw new RuntimeException('ZERO_CAPTURE_MUST_NOT_RUN');
        };
        $zeroClock = static function() use (&$zeroCounts): float {
            ++$zeroCounts['clock']; throw new RuntimeException('ZERO_CAPTURE_CLOCK_MUST_NOT_RUN');
        };
        $zeroAutosave = static function(array $request, string $ref, int $generation) use (
            $zeroRequest, $zeroRef, $zeroDir, $zeroAt, $zeroMapping, $zeroCanonical, $zeroPricing, $zeroSave, $zeroIngest, &$zeroCounts
        ): array {
            aassert($request === $zeroRequest && $ref === $zeroRef && $generation === 1, 'zero capture changed autosave binding');
            ++$zeroCounts['autosave'];
            return AnyTourAndromedaOfferAutosaveV1::consume($request, $zeroDir, $ref, $generation,
                $zeroAt, $zeroMapping, $zeroCanonical, $zeroPricing, $zeroSave, $zeroIngest);
        };
        try {
            $zeroResult = AnyTourAndromedaLocalOfferCollectorV1::collect($zeroRequest, $zeroSearch, $zeroLoad,
                static fn(array $selection, array $offer): bool => true, $zeroCapture, $zeroAutosave,
                0, $zeroMode, $zeroSeconds, $zeroClock);
        } catch (Throwable $error) {
            echo 'ANDROMEDA_ZERO_CAPTURE_REJECTED ' . json_encode(['error' => $error->getMessage(), 'counts' => $zeroCounts]) . "\n";
            throw $error;
        }
        aassert($zeroCounts === ['search' => 1, 'cohort' => 1, 'capture' => 0, 'clock' => 0, 'autosave' => 1], 'zero capture orchestration changed');
        aassert($zeroResult['status'] === 'complete' && $zeroResult['ready_offer_count'] === 1
            && $zeroResult['autosave']['confirmationRequiredOfferCount'] === 2, 'zero capture lost saved price states');
        aassert($zeroResult['surcharge_capture_attempts'] === 0 && $zeroResult['surcharge_ready'] === 0
            && $zeroResult['selection_authority'] === false && $zeroResult['booking_calls'] === 0, 'zero capture gained quote/selection authority');
        aassert(count($zeroIng ests) === 1 && count($zeroIng ests[0]['rows']) === 3, 'zero capture lost complete cohort rows');
        $zeroDtos = array_column($zeroIng ests[0]['rows'], 'dto');
        aassert($zeroDtos[0]['finalPriceReady'] === true && $zeroDtos[0]['finalPrice'] === '199390'
            && $zeroDtos[0]['final_price_verified'] === true, 'saved verified total was not reused');
        assert_confirmation_dto($zeroDtos[1]); assert_confirmation_dto($zeroDtos[2]);
        $zeroAgain = $zeroAutosave($zeroRequest, $zeroRef, 1);
        aassert($zeroAgain['reason'] === 'already_published' && $zeroAgain['readyOfferCount'] === 1
            && count($zeroIng ests) === 1, 'zero capture repeated persistence or invented readiness');
        echo 'ANDROMEDA_ZERO_CAPTURE_MEASURE ' . json_encode(['mode' => $zeroMode, 'seconds' => $zeroSeconds,
            'stored' => 3, 'ready' => 1, 'confirmation' => 2, 'captures' => 0, 'new_repeat_intakes' => 0]) . "\n";

        foreach (['autosave_failed', 'local_ingest_unavailable'] as $reason) {
            $failed = ['published' => false, 'reason' => $reason, 'writeOutcome' => 'unknown'];
            $failedCalls = 0;
            $failedResult = AnyTourAndromedaLocalOfferCollectorV1::collect($zeroRequest, $zeroSearch, $zeroLoad,
                static fn(array $selection, array $offer): bool => true, $zeroCapture,
                static function() use ($failed, &$failedCalls): array { ++$failedCalls; return $failed; }, 0, $zeroMode);
            aassert($failedResult['status'] === 'incomplete' && $failedResult['autosave'] === $failed
                && $failedCalls === 1 && $zeroCounts['capture'] === 0, 'zero capture retried or hid failed persistence');
        }
        $positiveCaptures = 0;
        $defaultResult = AnyTourAndromedaLocalOfferCollectorV1::collect($zeroRequest, $zeroSearch, $zeroLoad,
            static fn(array $selection, array $offer): bool => true,
            static function() use (&$positiveCaptures): array { ++$positiveCaptures; return ['status' => 'captured']; },
            static fn(): array => ['published' => false, 'reason' => 'already_published', 'readyOfferCount' => 1]);
        aassert($positiveCaptures === 2 && $defaultResult['surcharge_capture_attempts'] === 2, 'default capture budget changed');
    } finally { cleanup_dir($zeroDir); }
}

foreach (['all', 'non_external_only'] as $zeroMode) {
    $zeroCli = familyCli(['--max-captures=0', '--capture-mode=' . $zeroMode, '--max-capture-seconds=210', '--child-ages=7,3', '--region=20']);
    familyCheck($zeroCli['code'] === 0, 'actual CLI rejects zero capture: ' . $zeroCli['stderr']);
    $zeroEvents = array_column($zeroCli['trace'], 1, 0);
    familyCheck($zeroEvents['collector']['maxCaptures'] === 0 && $zeroEvents['collector']['mode'] === $zeroMode,
        'actual CLI lost explicit zero budget');
    familyCheck($zeroEvents['search'] === $zeroEvents['autosave'] && $zeroEvents['search']['params']['childs'] === [3,7]
        && $zeroEvents['search']['params']['regionIds'] === ['20'], 'zero budget changed family/destination');
}
foreach (['-1', '301', '0.0', '00', 'x'] as $badBudget) {
    $badCli = familyCli(['--max-captures=' . $badBudget]);
    familyCheck($badCli['code'] !== 0 && !in_array('catalog', array_column($badCli['trace'], 0), true), 'invalid budget reached search');
}
foreach ([-1, 301] as $badBudget) {
    $unexpected = static function(): array { throw new RuntimeException('INVALID_BUDGET_REACHED_CALLBACK'); };
    $refused = false;
    try { AnyTourAndromedaLocalOfferCollectorV1::collect(search_request(), $unexpected, $unexpected, $unexpected, $unexpected, $unexpected, $badBudget); }
    catch (InvalidArgumentException $error) { $refused = $error->getMessage() === 'ANDROMEDA_LOCAL_COLLECTOR_INPUT'; }
    aassert($refused, 'collector invalid capture bound accepted');
}
$partialRefused = false;
try {
    AnyTourAndromedaLocalOfferCollectorV1::collect(search_request(),
        static fn(): array => ['provider' => 'andromeda', 'search_ref' => str_repeat('a',64), 'pages_count' => 2, 'status' => 'pending'],
        $unexpected, $unexpected, $unexpected, $unexpected, 0);
} catch (RuntimeException $error) { $partialRefused = $error->getMessage() === 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH'; }
aassert($partialRefused, 'zero capture bypassed completeness');
echo "ANDROMEDA_ZERO_CAPTURE_OK real_autosave=4 cli=2 invalid_cli=5 invalid_core=2 incomplete_search=1 default_unchanged=1 supplier_http=0 live_db=0\n";
