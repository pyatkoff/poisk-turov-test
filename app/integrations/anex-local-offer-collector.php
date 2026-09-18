<?php
declare(strict_types=1);

/**
 * INT-owned orchestration for one bounded direct-ANEX refresh into local persistence.
 * Supplier transport, APD arithmetic, mapping and AnyTour offer-store writes remain
 * delegated to the already-tested callbacks supplied by the runtime/CLI.
 */
final class AnyTourAnexLocalOfferCollectorV1
{
    public static function collect(
        array $searchRequest,
        array &$state,
        callable $search,
        callable $expand,
        callable $recordPrograms,
        callable $additionalBatch,
        int $maxExpands = 2,
        int $maxBatchItems = 6
    ): array {
        if (($searchRequest['action'] ?? null) !== 'search'
            || !is_int($searchRequest['generation'] ?? null)
            || !is_array($searchRequest['params'] ?? null)
            || $maxExpands < 0 || $maxExpands > 10
            || $maxBatchItems < 1 || $maxBatchItems > 20) {
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
        }

        $items = array_slice(array_values($charters), 0, $maxBatchItems);
        $batch = null;
        if ($items !== []) {
            $batch = $additionalBatch([
                'action' => 'additional_prices_batch',
                'generation' => $searchRequest['generation'],
                'search_ref' => $searchData['search_ref'],
                'items' => $items,
            ], $state);
            if (!is_array($batch) || ($batch['status'] ?? null) !== 'additional_prices_batch') {
                throw new RuntimeException('ANEX_LOCAL_COLLECTOR_APD');
            }
        }

        $ready = 0;
        $complete = 0;
        $retryable = 0;
        foreach (($batch['offers'] ?? []) as $offer) {
            if (!is_array($offer)) continue;
            if (($offer['status'] ?? null) === 'additional_prices') ++$complete;
            if (($offer['finalPriceReady'] ?? null) === true) ++$ready;
            if (($offer['retryable'] ?? null) === true) ++$retryable;
        }

        return [
            'source' => 'anex-local-offer-collector-v1',
            'status' => 'complete',
            'search_hotels' => count($searchData['hotels']),
            'grouped_candidates' => count($grouped),
            'expand_calls' => $expanded,
            'charter_concrete_candidates' => count($charters),
            'regular_concrete_candidates' => count($regular),
            'apd_batch_items' => count($items),
            'apd_complete_offers' => $complete,
            'final_price_ready_offers' => $ready,
            'retryable_offers' => $retryable,
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
