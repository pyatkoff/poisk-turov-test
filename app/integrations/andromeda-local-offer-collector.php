<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-surcharge-group-key.php';

/**
 * INT-owned orchestration for a complete Andromeda cohort followed by a bounded
 * number of retained package/get_flights surcharge captures and canonical autosave.
 * Supplier transport, DB writes and mapping validation stay in injected owners.
 * An explicit zero capture budget keeps search and retained-pricing autosave only.
 */
final class AnyTourAndromedaLocalOfferCollectorV1
{
    public static function collect(
        array $request,
        callable $searchComplete,
        callable $loadCohort,
        callable $candidateAllowed,
        callable $captureSurcharge,
        callable $autosave,
        int $maxCaptures = 2,
        string $captureMode = 'all',
        int $maxCaptureSeconds = 0,
        ?callable $clock = null
    ): array {
        if (!is_int($request['generation'] ?? null) || $request['generation'] < 1
            || !is_array($request['params'] ?? null)
            || $maxCaptures < 0 || $maxCaptures > 300
            || $maxCaptureSeconds < 0 || $maxCaptureSeconds > 240
            || !in_array($captureMode, ['all','non_external_only'], true)) {
            throw new InvalidArgumentException('ANDROMEDA_LOCAL_COLLECTOR_INPUT');
        }

        $search = $searchComplete($request);
        $status = is_array($search) ? ($search['status'] ?? null) : null;
        // The page normalizer deliberately labels every standalone multi-page data page
        // `partial`: one page cannot prove that its predecessors were drained. The grouped
        // page orchestrator does provide that proof after it consumes the exact advertised
        // range, but its legacy aggregate currently preserves the last page's `partial`
        // label. Accept only that narrow aggregate shape; arbitrary partial/one-page/search-
        // pending results remain fail-closed. Canonical autosave independently re-reads the
        // retained cohort and rejects unsafe supplier rows before any publication.
        $drainedPartial = is_array($search)
            && $status === 'partial'
            && is_int($search['page'] ?? null)
            && is_int($search['pages_count'] ?? null)
            && $search['pages_count'] > 1
            && $search['page'] === $search['pages_count']
            && ($search['grouped'] ?? null) === true
            && ($search['first_page_only'] ?? null) === false
            && ($search['external_search_pending'] ?? null) === false
            && is_int($search['received_offers'] ?? null)
            && $search['received_offers'] >= 0
            && is_int($search['mapped_offers'] ?? null)
            && $search['mapped_offers'] >= 0;
        // The complete first empty page is returned unchanged by run_pages with
        // pages_count=0. Admit only that exact projection, not an arbitrary zero.
        // The retained cohort and canonical autosave independently validate it.
        $terminalEmpty = is_array($search)
            && ($search['pages_count'] ?? null) === 0
            && ($search['page'] ?? null) === 1
            && ($search['generation'] ?? null) === $request['generation']
            && $status === 'complete'
            && ($search['hotels'] ?? null) === []
            && ($search['grouped'] ?? null) === true
            && ($search['first_page_only'] ?? null) === false
            && ($search['external_search_pending'] ?? null) === false
            && ($search['received_offers'] ?? null) === 0
            && ($search['mapped_offers'] ?? null) === 0;
        if (!is_array($search)
            || ($search['provider'] ?? null) !== 'andromeda'
            || !is_string($search['search_ref'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $search['search_ref'])
            || !is_int($search['pages_count'] ?? null)
            || $search['pages_count'] < 0
            || ($search['pages_count'] === 0 && !$terminalEmpty)
            || ($status !== 'complete' && !$drainedPartial)) {
            throw new RuntimeException('ANDROMEDA_LOCAL_COLLECTOR_SEARCH');
        }

        $rows = $loadCohort($search['search_ref'], $request['generation']);
        if (!is_array($rows) || !array_is_list($rows) || ($terminalEmpty && $rows !== [])) {
            throw new RuntimeException('ANDROMEDA_LOCAL_COLLECTOR_COHORT');
        }

        $eligible = [];
        $owned = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row['page'] ?? null) || !is_array($row['offer'] ?? null)) continue;
            $offer = $row['offer'];
            if (!self::ownsOperator((string)($offer['operator'] ?? ''))) continue;
            ++$owned;

            $offerRef = $offer['offer_ref'] ?? null;
            $local = $offer['local_hotel_id'] ?? null;
            $operatorRef = $offer['operator_ref'] ?? null;
            if (!is_string($offerRef) || !preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offerRef)
                || !is_int($local) || $local < 1
                || (!is_string($operatorRef) && !is_int($operatorRef))
                || (string)$operatorRef === '') {
                continue;
            }
            $selection = [
                'provider' => 'andromeda',
                'search_ref' => $search['search_ref'],
                'generation' => $request['generation'],
                'page' => $row['page'],
                'offer_ref' => $offerRef,
                'hotel_scope' => null,
                'operator_ref' => (string)$operatorRef,
                'local_id' => $local,
            ];
            if ($candidateAllowed($selection, $offer) !== true) continue;
            $key = $offerRef . ':' . $local;
            $freightExternal = self::freightExternal($offer);
            $eligible[$key] = [
                'selection' => $selection,
                'freight_external' => $freightExternal,
                'transport_group' => self::captureGroup(
                    $offer, $request, (string)$operatorRef, $key, $freightExternal
                ),
            ];
        }

        // get_flights is only meaningful when the supplier reports external freight.
        // The search row is not authority to skip any mapped candidate, but it is useful
        // for spending the deliberately small capture budget: true first, then unknown,
        // then explicit false. External-freight rows use the evidence-backed strict
        // surcharge key, so route/date/nights/party/currency/tour differences cannot be
        // collapsed merely because operator/program match. Ungroupable external rows are
        // fail-closed into unique-offer buckets. Historical unknown/non-external ordering
        // stays unchanged until its separate pricing contract is replaced.
        $captureQueue = [];
        $priorities = $captureMode === 'non_external_only' ? [false] : [true, null, false];
        foreach ($priorities as $priority) {
            $groups = [];
            foreach ($eligible as $key => $candidate) {
                if ($candidate['freight_external'] !== $priority) continue;
                $group = $candidate['transport_group'];
                if (isset($groups[$group])) continue;
                $groups[$group] = true;
                $captureQueue[$key] = $candidate['selection'];
            }
            foreach ($eligible as $key => $candidate) {
                if ($candidate['freight_external'] !== $priority || isset($captureQueue[$key])) continue;
                $captureQueue[$key] = $candidate['selection'];
            }
        }

        $attempted = 0;
        $surchargeReady = 0;
        $captured = [];
        $readClock = null;
        $captureStartedAt = null;
        $timeBudgetExhausted = false;
        if ($maxCaptures > 0 && $maxCaptureSeconds > 0) {
            $clock ??= static fn(): float => microtime(true);
            $readClock = static function() use ($clock): float {
                $value = $clock();
                if ((!is_int($value) && !is_float($value))
                    || !is_finite((float)$value) || (float)$value < 0) {
                    throw new RuntimeException('ANDROMEDA_LOCAL_COLLECTOR_CLOCK');
                }
                return (float)$value;
            };
            $captureStartedAt = $readClock();
        }
        foreach ($captureQueue as $key => $selection) {
            if ($attempted >= $maxCaptures) break;
            if ($captureStartedAt !== null && $attempted > 0) {
                $elapsed = $readClock() - $captureStartedAt;
                if ($elapsed < 0) throw new RuntimeException('ANDROMEDA_LOCAL_COLLECTOR_CLOCK');
                if ($elapsed >= $maxCaptureSeconds) {
                    $timeBudgetExhausted = true;
                    break;
                }
            }
            ++$attempted;
            try {
                $receipt = $captureSurcharge($selection);
            } catch (RuntimeException $error) {
                // The package runtime durably seals an attempted supplier call before it
                // reports this outcome. It is terminal for this one offer, not for the
                // disjoint candidates still inside the caller's bounded capture budget.
                // Do not broaden this allowlist: invariant/checkpoint/programming failures
                // must still abort the cohort so we never turn an unknown write state into
                // permission to continue.
                if ($error->getMessage() !== 'ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN') throw $error;
                $captured[$key] = 'failed_terminal_no_replay';
                continue;
            }
            if (!is_array($receipt) || ($receipt['status'] ?? null) !== 'captured') {
                $captured[$key] = 'failed';
                continue;
            }
            $surcharge = $receipt['surcharge'] ?? null;
            if (is_array($surcharge) && ($surcharge['status'] ?? null) === 'complete'
                && (is_array($surcharge['fact'] ?? null)
                    || ($surcharge['final_price_verified'] ?? null) === true)) {
                ++$surchargeReady;
                $captured[$key] = ($surcharge['final_price_verified'] ?? null) === true
                    ? 'verified' : 'ready';
            } else {
                $captured[$key] = 'not_ready';
            }
        }

        $save = $autosave($request, $search['search_ref'], $request['generation']);
        if (!is_array($save)) throw new RuntimeException('ANDROMEDA_LOCAL_COLLECTOR_AUTOSAVE');
        // A collector run is complete only when the canonical offer snapshot was
        // actually published, or when the autosave owner proves this exact generation
        // was already published. Preserve every other receipt verbatim: an autosave
        // failure can represent an unknown DB/write outcome and must never become replay
        // authority just because search/capture work itself completed.
        $autosaveComplete = ($save['published'] ?? null) === true
            || (($save['published'] ?? null) === false
                && ($save['reason'] ?? null) === 'already_published');
        $confirmationRequiredCount = $save['confirmationRequiredOfferCount'] ?? null;
        if (!is_int($confirmationRequiredCount) || $confirmationRequiredCount < 0) {
            $confirmationRequiredCount = null;
        }

        return [
            'source' => 'andromeda-local-offer-collector-v1',
            'status' => $autosaveComplete ? 'complete' : 'incomplete',
            'pages' => $search['pages_count'],
            'received_offers' => count($rows),
            'owned_operator_offers' => $owned,
            'eligible_offers' => count($eligible),
            'capture_mode' => $captureMode,
            'capture_queue_offers' => count($captureQueue),
            'capture_time_budget_seconds' => $maxCaptureSeconds > 0 ? $maxCaptureSeconds : null,
            'capture_time_budget_exhausted' => $timeBudgetExhausted,
            'surcharge_capture_attempts' => $attempted,
            'surcharge_ready' => $surchargeReady,
            'autosave_published' => ($save['published'] ?? false) === true,
            'autosave_reason' => $save['reason'] ?? null,
            'ready_offer_count' => (int)($save['readyOfferCount'] ?? 0),
            'confirmation_required_offer_count' => $confirmationRequiredCount,
            'autosave' => $save,
            'selection_authority' => false,
            'booking_calls' => 0,
        ];
    }

    public static function ownsOperator(string $raw): bool
    {
        $value = str_replace(['Ё', 'ё'], 'е', trim($raw));
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
        if ($compact === '') return false;
        foreach (['anex','анекс','pegas','пегас','coral','корал','sunmar','санмар'] as $other) {
            if (str_contains($compact, $other)) return false;
        }
        return true;
    }

    private static function freightExternal(array $offer): ?bool
    {
        $value = $offer['transport_context']['freight_external'] ?? null;
        return is_bool($value) ? $value : null;
    }

    private static function captureGroup(
        array $offer,
        array $request,
        string $operatorRef,
        string $fallback,
        ?bool $freightExternal
    ): string {
        if ($freightExternal === true) {
            $strict = AndromedaSurchargeGroupKey::build($offer, $request);
            return $strict ?? 'offer:' . $fallback;
        }
        return self::legacyTransportGroup($offer, $operatorRef, $fallback);
    }

    private static function legacyTransportGroup(array $offer, string $operatorRef, string $fallback): string
    {
        $context = is_array($offer['transport_context'] ?? null) ? $offer['transport_context'] : [];
        $program = $context['program_ref'] ?? null;
        $tour = $context['tour_ref'] ?? null;
        $program = (is_string($program) || is_int($program)) && (string)$program !== '' ? (string)$program : '';
        $tour = (is_string($tour) || is_int($tour)) && (string)$tour !== '' ? (string)$tour : '';
        if ($program === '' && $tour === '') return 'offer:' . $fallback;
        return hash('sha256', json_encode([$operatorRef, $program, $tour], JSON_THROW_ON_ERROR));
    }
}
