<?php
declare(strict_types=1);

/**
 * INT-owned orchestration for a complete Andromeda cohort followed by a bounded
 * number of retained package/get_flights surcharge captures and canonical autosave.
 * Supplier transport, DB writes and mapping validation stay in injected owners.
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
        string $captureMode = 'all'
    ): array {
        if (!is_int($request['generation'] ?? null) || $request['generation'] < 1
            || !is_array($request['params'] ?? null)
            || $maxCaptures < 1 || $maxCaptures > 300
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
        if (!is_array($search)
            || ($search['provider'] ?? null) !== 'andromeda'
            || !is_string($search['search_ref'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $search['search_ref'])
            || !is_int($search['pages_count'] ?? null)
            || $search['pages_count'] < 1
            || ($status !== 'complete' && !$drainedPartial)) {
            throw new RuntimeException('ANDROMEDA_LOCAL_COLLECTOR_SEARCH');
        }

        $rows = $loadCohort($search['search_ref'], $request['generation']);
        if (!is_array($rows) || !array_is_list($rows)) {
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
            $eligible[$key] = [
                'selection' => $selection,
                'freight_external' => self::freightExternal($offer),
                'transport_group' => self::transportGroup($offer, (string)$operatorRef, $key),
            ];
        }

        // get_flights is only meaningful when the supplier reports external freight.
        // The search row is not authority to skip any mapped candidate, but it is useful
        // for spending the deliberately small capture budget: true first, then unknown,
        // then explicit false. Within each bucket, first sample distinct transport
        // program/tour groups; only then spend budget on another offer from a group that
        // was already attempted. This prevents a cheap hotel-order cluster from consuming
        // the whole bounded supplier budget for one flight program.
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
        foreach ($captureQueue as $key => $selection) {
            if ($attempted >= $maxCaptures) break;
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

        return [
            'source' => 'andromeda-local-offer-collector-v1',
            'status' => 'complete',
            'pages' => $search['pages_count'],
            'received_offers' => count($rows),
            'owned_operator_offers' => $owned,
            'eligible_offers' => count($eligible),
            'capture_mode' => $captureMode,
            'capture_queue_offers' => count($captureQueue),
            'surcharge_capture_attempts' => $attempted,
            'surcharge_ready' => $surchargeReady,
            'autosave_published' => ($save['published'] ?? false) === true,
            'autosave_reason' => $save['reason'] ?? null,
            'ready_offer_count' => (int)($save['readyOfferCount'] ?? 0),
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

    private static function transportGroup(array $offer, string $operatorRef, string $fallback): string
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
