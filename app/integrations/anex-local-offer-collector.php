<?php
declare(strict_types=1);

/**
 * INT-owned orchestration for one bounded direct-ANEX refresh into local persistence.
 * Supplier transport, APD arithmetic, mapping and AnyTour offer-store writes remain
 * delegated to the already-tested callbacks supplied by the runtime/CLI.
 */
final class AnyTourAnexLocalOfferCollectorV1
{
    /**
     * Split one user-visible direct-ANEX date intent into exact-day supplier windows.
     * The owner contract is at most 21 inclusive days. Live supplier evidence shows
     * broad windows can fail while later exact days remain valid, so a bad date must
     * not prevent collection of independently valid dates. Invalid/reversed/too-wide
     * ranges still fail before I/O.
     *
     * @return list<array{from:string,to:string}>
     */
    public static function dateWindows(string $from, string $to): array
    {
        $parse = static function (string $value): DateTimeImmutable {
            if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $value, $m)
                || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_DATE_RANGE');
            }
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        };
        $start = $parse($from);
        $end = $parse($to);
        if ($end < $start) throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_DATE_RANGE');
        $inclusiveDays = (int)$start->diff($end)->days + 1;
        if ($inclusiveDays < 1 || $inclusiveDays > 21) {
            throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_DATE_RANGE');
        }

        $windows = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            $day = $cursor->format('Y-m-d');
            $windows[] = ['from' => $day, 'to' => $day];
        }
        return $windows;
    }

    /**
     * Run supplier-safe windows sequentially. Persistence/invariant failures still
     * fail-stop. In a multi-window range only bounded supplier transport/result
     * errors are isolated to their exact window so later independent windows can
     * still complete. A failed supplier window never becomes an empty-success fact.
     *
     * @param callable(array,int,array):array $collectWindow
     */
    public static function collectRange(
        array $searchRequest,
        string $from,
        string $to,
        callable $collectWindow
    ): array {
        if (($searchRequest['action'] ?? null) !== 'search'
            || !is_int($searchRequest['generation'] ?? null)
            || !is_array($searchRequest['params'] ?? null)) {
            throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_INPUT');
        }
        $windows = self::dateWindows($from, $to);
        $receipts = [];
        $completed = 0;
        $status = 'complete';
        $multiWindow = count($windows) > 1;
        foreach ($windows as $index => $window) {
            $request = $searchRequest;
            $request['params']['dateFrom'] = $window['from'];
            $request['params']['dateTo'] = $window['to'];
            $supplierError = false;
            try {
                $result = $collectWindow($request, $index, $window);
            } catch (RuntimeException $error) {
                if (!$multiWindow || !in_array($error->getMessage(), ['ANEX_SUPPLIER_ERROR', 'ANEX_HTTP_ERROR'], true)) {
                    throw $error;
                }
                $supplierError = true;
                $result = [
                    'source' => 'anex-local-offer-collector-range-v1',
                    'status' => 'supplier_error',
                    'error_code' => $error->getMessage(),
                    'requested_date_range' => $window,
                    'selection_authority' => false,
                ];
            }
            if (!is_array($result) || !is_string($result['status'] ?? null)) {
                throw new RuntimeException('ANEX_LOCAL_COLLECTOR_RANGE_RESULT');
            }
            $receipts[] = ['date_range' => $window, 'result' => $result];
            if ($result['status'] !== 'complete') {
                $status = 'incomplete';
                if ($supplierError) continue;
                break;
            }
            ++$completed;
        }
        return [
            'source' => 'anex-local-offer-collector-range-v1',
            'status' => $status,
            'requested_date_range' => ['from' => $from, 'to' => $to],
            'window_count' => count($windows),
            'windows_completed' => $completed,
            'windows' => $receipts,
            'selection_authority' => false,
        ];
    }

    public static function collect(
        array $searchRequest,
        array &$state,
        callable $search,
        callable $expand,
        callable $recordPrograms,
        callable $additionalBatch,
        int $maxExpands = 600,
        int $maxBatchItems = 600
    ): array {
        if (($searchRequest['action'] ?? null) !== 'search'
            || !is_int($searchRequest['generation'] ?? null)
            || !is_array($searchRequest['params'] ?? null)
            || $maxExpands < 0 || $maxExpands > 600
            || $maxBatchItems < 1 || $maxBatchItems > 600) {
            throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_INPUT');
        }

        $searchData = $search($searchRequest, $state);
        if (!is_array($searchData) || ($searchData['provider'] ?? null) !== 'anex'
            || !is_string($searchData['search_ref'] ?? null)
            || !is_array($searchData['hotels'] ?? null)) {
            throw new RuntimeException('ANEX_LOCAL_COLLECTOR_SEARCH');
        }
        $recordPrograms($state);

        $grouped = [];
        $charters = [];
        $regular = [];
        self::collectOffers($searchData['hotels'], $grouped, $charters, $regular);

        $processed = 0;
        $ready = 0;
        $complete = 0;
        $retryable = 0;
        $batchCalls = 0;
        // Persist full batches as soon as their concrete offers are discovered.
        // Keep the final short tail until normal completion: batching, item order
        // and budgets stay unchanged, and failures never trigger a retry/flush.
        $flush = static function (bool $finish) use (
            &$charters, &$state, $searchRequest, $searchData, $additionalBatch, $maxBatchItems,
            &$processed, &$ready, &$complete, &$retryable, &$batchCalls
        ): void {
            $items = array_slice(array_values($charters), $processed, $maxBatchItems - $processed);
            foreach (array_chunk($items, 6) as $chunk) {
                if (!$finish && count($chunk) < 6) break;
                $batch = $additionalBatch([
                    'action' => 'additional_prices_batch',
                    'generation' => $searchRequest['generation'],
                    'search_ref' => $searchData['search_ref'],
                    'items' => $chunk,
                ], $state);
                ++$batchCalls;
                if (!is_array($batch) || ($batch['status'] ?? null) !== 'additional_prices_batch'
                    || !is_array($batch['offers'] ?? null)) {
                    throw new RuntimeException('ANEX_LOCAL_COLLECTOR_APD');
                }
                foreach ($batch['offers'] as $offer) {
                    if (!is_array($offer)) continue;
                    if (($offer['status'] ?? null) === 'additional_prices') ++$complete;
                    if (($offer['finalPriceReady'] ?? null) === true) ++$ready;
                    if (($offer['retryable'] ?? null) === true) ++$retryable;
                }
                $processed += count($chunk);
            }
        };
        $flush(false);

        $expanded = 0;
        foreach ($grouped as $group) {
            if ($expanded >= $maxExpands || count($charters) >= $maxBatchItems) break;
            $reply = $expand([
                'action' => 'expand',
                'generation' => $searchRequest['generation'],
                'search_ref' => $searchData['search_ref'],
                'offer_ref' => $group['offer_ref'],
                'local_hotel_id' => $group['local_hotel_id'],
            ], $state);
            ++$expanded;
            $recordPrograms($state);
            if (!is_array($reply) || ($reply['status'] ?? null) !== 'expanded') continue;
            $ignoredGrouped = [];
            self::collectOffers($reply['hotels'] ?? [], $ignoredGrouped, $charters, $regular);
            $flush(false);
        }

        $flush(true);

        $groupedDrained = $expanded >= count($grouped);
        $concreteDrained = count($charters) <= $maxBatchItems;
        $discoveredSetDrained = $groupedDrained && $concreteDrained;
        return [
            'source' => 'anex-local-offer-collector-v1',
            // A bounded invocation may safely preserve batches it already completed,
            // but it must not claim the scope itself was completed until every grouped
            // candidate and discovered concrete charter fit inside the caller's bounds.
            'status' => $discoveredSetDrained ? 'complete' : 'incomplete',
            'search_hotels' => count($searchData['hotels']),
            'grouped_candidates' => count($grouped),
            'expand_calls' => $expanded,
            'charter_concrete_candidates' => count($charters),
            'regular_concrete_candidates' => count($regular),
            'apd_batch_items' => $processed,
            'apd_batch_calls' => $batchCalls,
            'apd_complete_offers' => $complete,
            'final_price_ready_offers' => $ready,
            'retryable_offers' => $retryable,
            'grouped_drained' => $groupedDrained,
            'concrete_drained' => $concreteDrained,
            'discovered_set_drained' => $discoveredSetDrained,
            'selection_authority' => false,
        ];
    }

    private static function collectOffers(array $hotels, array &$grouped, array &$charters, array &$regular): void
    {
        foreach ($hotels as $hotel) {
            if (!is_array($hotel) || !is_int($hotel['local_id'] ?? null) || !is_array($hotel['tours'] ?? null)) continue;
            $local = $hotel['local_id'];
            foreach ($hotel['tours'] as $tour) {
                if (!is_array($tour) || !is_string($tour['offer_ref'] ?? null)) continue;
                $pair = ['offer_ref' => $tour['offer_ref'], 'local_hotel_id' => $local];
                $key = $tour['offer_ref'] . ':' . $local;
                if (($tour['kind'] ?? null) === 'group_minimum') {
                    $grouped[$key] = $pair;
                    continue;
                }
                if (($tour['kind'] ?? null) !== 'concrete') continue;
                if (($tour['flight_type'] ?? null) === 'charter') $charters[$key] = $pair;
                elseif (($tour['flight_type'] ?? null) === 'regular') $regular[$key] = $pair;
            }
        }
    }
}
