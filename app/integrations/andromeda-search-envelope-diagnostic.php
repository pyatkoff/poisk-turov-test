<?php
declare(strict_types=1);

/**
 * Safe diagnostic mirror for the fail-closed search-envelope gate owned by
 * AnyTourAndromedaLocalOfferCollectorV1.
 *
 * This class never authorizes a search result. A null return only means the
 * envelope is shaped closely enough to be handed to the authoritative
 * collector, which repeats its own validation before cohort load/autosave.
 * Returned codes contain no supplier payload, native ids, URLs or auth data.
 */
final class AnyTourAndromedaSearchEnvelopeDiagnosticV1
{
    public static function failureCode(mixed $search, mixed $generation): ?string
    {
        if (!is_array($search)) return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_SHAPE';
        if (($search['provider'] ?? null) !== 'andromeda') {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PROVIDER';
        }
        $ref = $search['search_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/\A[a-f0-9]{64}\z/D', $ref)) {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_REF';
        }
        $pages = $search['pages_count'] ?? null;
        if (!is_int($pages) || $pages < 0) {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PAGES';
        }

        $status = $search['status'] ?? null;
        if ($pages === 0) {
            if (($search['page'] ?? null) !== 1) {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_PAGE';
            }
            if (!is_int($generation) || ($search['generation'] ?? null) !== $generation) {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_GENERATION';
            }
            if ($status !== 'complete') {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_STATUS';
            }
            if (($search['hotels'] ?? null) !== []) {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_HOTELS';
            }
            if (($search['grouped'] ?? null) !== true) {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_GROUPED';
            }
            if (($search['first_page_only'] ?? null) !== false) {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_FIRST_PAGE';
            }
            if (($search['external_search_pending'] ?? null) !== false) {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_PENDING';
            }
            if (($search['received_offers'] ?? null) !== 0
                || ($search['mapped_offers'] ?? null) !== 0) {
                return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_EMPTY_COUNTS';
            }
            return null;
        }

        if ($status === 'complete') return null;
        if ($status !== 'partial') {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_STATUS';
        }
        if ($pages <= 1 || !is_int($search['page'] ?? null)
            || $search['page'] !== $pages) {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_PAGE';
        }
        if (($search['grouped'] ?? null) !== true) {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_GROUPED';
        }
        if (($search['first_page_only'] ?? null) !== false) {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_FIRST_PAGE';
        }
        if (($search['external_search_pending'] ?? null) !== false) {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_PENDING';
        }
        if (!is_int($search['received_offers'] ?? null)
            || $search['received_offers'] < 0
            || !is_int($search['mapped_offers'] ?? null)
            || $search['mapped_offers'] < 0) {
            return 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_COUNTS';
        }
        return null;
    }
}
