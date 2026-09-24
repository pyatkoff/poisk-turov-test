<?php
declare(strict_types=1);

/**
 * Safe classifier for the exact search-envelope predicates enforced by
 * AnyTourAndromedaLocalOfferCollectorV1. It returns fixed codes only: no supplier
 * payload, URL, provider-native ID or credential can enter the diagnostic output.
 */
final class AnyTourAndromedaSearchEnvelopeDiagnosticV1
{
    public static function exceptionCode(array $request, $search): ?string
    {
        $predicate = self::invalidPredicate($request, $search);
        return $predicate === null ? null : 'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_' . $predicate;
    }

    public static function invalidPredicate(array $request, $search): ?string
    {
        if (!is_array($search)) return 'NOT_ARRAY';
        if (($search['provider'] ?? null) !== 'andromeda') return 'PROVIDER';
        $ref = $search['search_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/\A[a-f0-9]{64}\z/D', $ref)) return 'SEARCH_REF';
        $pages = $search['pages_count'] ?? null;
        if (!is_int($pages) || $pages < 0) return 'PAGES_COUNT';

        if ($pages === 0) return self::terminalEmptyPredicate($request, $search);

        $status = $search['status'] ?? null;
        if ($status === 'complete') return null;
        if ($status !== 'partial') return 'STATUS';
        return self::drainedPartialPredicate($search);
    }

    private static function terminalEmptyPredicate(array $request, array $search): ?string
    {
        if (($search['page'] ?? null) !== 1) return 'TERMINAL_EMPTY_PAGE';
        if (($search['generation'] ?? null) !== ($request['generation'] ?? null)) return 'TERMINAL_EMPTY_GENERATION';
        if (($search['status'] ?? null) !== 'complete') return 'TERMINAL_EMPTY_STATUS';
        if (($search['hotels'] ?? null) !== []) return 'TERMINAL_EMPTY_HOTELS';
        if (($search['grouped'] ?? null) !== true) return 'TERMINAL_EMPTY_GROUPED';
        if (($search['first_page_only'] ?? null) !== false) return 'TERMINAL_EMPTY_FIRST_PAGE';
        if (($search['external_search_pending'] ?? null) !== false) return 'TERMINAL_EMPTY_EXTERNAL_PENDING';
        if (($search['received_offers'] ?? null) !== 0) return 'TERMINAL_EMPTY_RECEIVED';
        if (($search['mapped_offers'] ?? null) !== 0) return 'TERMINAL_EMPTY_MAPPED';
        return null;
    }

    private static function drainedPartialPredicate(array $search): ?string
    {
        $page = $search['page'] ?? null;
        $pages = $search['pages_count'] ?? null;
        if (!is_int($page) || $page < 1) return 'PARTIAL_PAGE';
        if (!is_int($pages) || $pages <= 1 || $page > $pages) return 'PARTIAL_PAGES_COUNT';
        if (($search['grouped'] ?? null) !== true) return 'PARTIAL_GROUPED';
        if (($search['first_page_only'] ?? null) !== false) return 'PARTIAL_FIRST_PAGE';
        $pending = $search['external_search_pending'] ?? null;
        if ($pending === true) {
            // run_pages() emits this exact shape after one or more valid pages when
            // a later page becomes temporarily unavailable. It is useful evidence,
            // but it is not a complete/publishable supplier cohort.
            if ($page >= $pages) return 'PARTIAL_EXTERNAL_PENDING';
        } elseif ($pending === false) {
            if ($page !== $pages) return 'PARTIAL_NOT_DRAINED';
        } else {
            return 'PARTIAL_EXTERNAL_PENDING';
        }
        $received = $search['received_offers'] ?? null;
        if (!is_int($received) || $received < 0) return 'PARTIAL_RECEIVED';
        $mapped = $search['mapped_offers'] ?? null;
        if (!is_int($mapped) || $mapped < 0) return 'PARTIAL_MAPPED';
        return null;
    }
}
