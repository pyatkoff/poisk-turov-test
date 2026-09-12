<?php
declare(strict_types=1);

/**
 * Provider-neutral P1 search coverage semantics.
 *
 * This class does not perform supplier searches. It only describes how much of
 * one already observed provider result set is proven to have been retained.
 * Result counts remain useful observations even when coverage is bounded, but
 * they must not be promoted to exhaustive cross-provider evidence.
 */
final class AnyTourThreeProviderSearchCoverage
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const MAX_PAGE = 1000;
    private const MAX_COUNT = 1000000;

    public static function fromEvidence(string $provider, ?array $evidence): array
    {
        self::assertProvider($provider);

        if ($evidence === null) {
            return self::result(
                $provider,
                'unknown',
                false,
                null,
                null,
                null,
                [],
                'no_coverage_evidence'
            );
        }

        if ($provider === 'anex') return self::fromAnex($evidence);
        if ($provider === 'andromeda') return self::fromAndromeda($evidence);
        return self::fromTourvisor($evidence);
    }

    private static function fromAnex(array $evidence): array
    {
        self::exactKeys($evidence, ['price_page', 'received_rows']);
        if ($evidence['price_page'] !== 1) {
            throw new InvalidArgumentException('direct ANEX coverage is only verified for current PRICEPAGE=1 source');
        }
        $count = self::countValue($evidence['received_rows'], 'received_rows');

        // Current AnyTourAnexSearch fixes PRICEPAGE=1 and has no verified total
        // page/continuation contract. Therefore even 300 rows are a bounded
        // observation, not proof that the supplier result set is exhausted.
        return self::result(
            'anex',
            'bounded',
            false,
            $count,
            1,
            null,
            [1],
            'pricepage_1_without_total_pagination_proof'
        );
    }

    private static function fromAndromeda(array $evidence): array
    {
        self::exactKeys($evidence, [
            'page', 'pages_count', 'loaded_pages', 'received_offers', 'same_search_context'
        ]);
        $page = self::pageValue($evidence['page'], 'page');
        $pages = self::pageValue($evidence['pages_count'], 'pages_count');
        if ($page > $pages) throw new InvalidArgumentException('page exceeds pages_count');
        if ($evidence['same_search_context'] !== true) {
            throw new InvalidArgumentException('mixed Andromeda search context cannot prove coverage');
        }
        if (!is_array($evidence['loaded_pages'])) throw new InvalidArgumentException('invalid loaded_pages');
        $loaded = [];
        foreach ($evidence['loaded_pages'] as $value) {
            $number = self::pageValue($value, 'loaded_page');
            if ($number > $pages || isset($loaded[$number])) {
                throw new InvalidArgumentException('invalid loaded_pages');
            }
            $loaded[$number] = true;
        }
        if (!isset($loaded[$page])) throw new InvalidArgumentException('current page is not retained');
        $loadedPages = array_keys($loaded);
        sort($loadedPages, SORT_NUMERIC);
        $expected = range(1, $pages);
        $complete = $loadedPages === $expected;
        $count = self::countValue($evidence['received_offers'], 'received_offers');

        return self::result(
            'andromeda',
            $complete ? 'complete' : 'partial',
            $complete,
            $count,
            $page,
            $pages,
            $loadedPages,
            $complete ? 'all_advertised_pages_retained' : 'advertised_pages_not_fully_retained'
        );
    }

    private static function fromTourvisor(array $evidence): array
    {
        self::exactKeys($evidence, [
            'search_status', 'results_fetch_limit', 'continuation_rounds',
            'no_growth_after_continue', 'unique_groups'
        ]);
        if (!in_array($evidence['search_status'], ['complete', 'incomplete'], true)) {
            throw new InvalidArgumentException('invalid Tourvisor search_status');
        }
        if (!is_int($evidence['results_fetch_limit']) || $evidence['results_fetch_limit'] < 1
            || $evidence['results_fetch_limit'] > 10000) {
            throw new InvalidArgumentException('invalid Tourvisor results_fetch_limit');
        }
        if (!is_int($evidence['continuation_rounds']) || $evidence['continuation_rounds'] < 0
            || $evidence['continuation_rounds'] > 100) {
            throw new InvalidArgumentException('invalid Tourvisor continuation_rounds');
        }
        if (!is_bool($evidence['no_growth_after_continue'])) {
            throw new InvalidArgumentException('invalid Tourvisor continuation evidence');
        }
        if ($evidence['no_growth_after_continue'] && $evidence['continuation_rounds'] < 1) {
            throw new InvalidArgumentException('no-growth requires an explicit continuation round');
        }
        $count = self::countValue($evidence['unique_groups'], 'unique_groups');
        $complete = $evidence['search_status'] === 'complete'
            && $evidence['continuation_rounds'] >= 1
            && $evidence['no_growth_after_continue'] === true;
        $state = $complete
            ? 'complete'
            : ($evidence['search_status'] === 'incomplete' ? 'partial' : 'bounded');
        $method = $complete
            ? 'search_complete_and_continue_no_growth'
            : ($evidence['search_status'] === 'incomplete'
                ? 'supplier_search_not_complete'
                : 'search_complete_without_continuation_exhaustion');

        return self::result(
            'tourvisor',
            $state,
            $complete,
            $count,
            null,
            null,
            [],
            $method
        );
    }

    private static function result(
        string $provider,
        string $state,
        bool $exhaustive,
        ?int $observedCount,
        ?int $page,
        ?int $pagesCount,
        array $loadedPages,
        string $completionMethod
    ): array {
        return [
            'schema_version' => 1,
            'provider' => $provider,
            'coverage_state' => $state,
            'counts_exhaustive' => $exhaustive,
            'observed_count' => $observedCount,
            'page' => $page,
            'pages_count' => $pagesCount,
            'loaded_pages' => $loadedPages,
            'completion_method' => $completionMethod,
            'observation_scope' => $state === 'complete' ? 'exhaustive' : ($state === 'unknown' ? 'unknown' : 'bounded_subset'),
            'observation_usable' => $state !== 'unknown',
            'cross_provider_count_comparability_verified' => false,
            'hotel_identity_proof_from_coverage' => false,
            'package_identity_proof_from_coverage' => false,
            'price_equivalence_proof_from_coverage' => false,
            'current_source_audit' => self::sourceAudit($provider),
        ];
    }

    private static function sourceAudit(string $provider): array
    {
        if ($provider === 'anex') {
            return [
                'current_behavior' => 'PRICEPAGE=1',
                'exhaustion_proof' => 'not_implemented_in_current_search_source',
            ];
        }
        if ($provider === 'andromeda') {
            return [
                'current_behavior' => 'page_and_pages_count_exposed',
                'exhaustion_proof' => 'requires_contiguous_retained_pages_1_to_pages_count',
            ];
        }
        return [
            'current_behavior' => 'status_complete_does_not_prove_result_exhaustion',
            'exhaustion_proof' => 'requires_explicit_continue_no_growth_evidence',
        ];
    }

    private static function exactKeys(array $value, array $expected): void
    {
        if (count($value) !== count($expected)
            || array_diff($expected, array_keys($value)) !== []
            || array_diff(array_keys($value), $expected) !== []) {
            throw new InvalidArgumentException('invalid coverage evidence keys');
        }
    }

    private static function pageValue($value, string $label): int
    {
        if (!is_int($value) || $value < 1 || $value > self::MAX_PAGE) {
            throw new InvalidArgumentException('invalid '.$label);
        }
        return $value;
    }

    private static function countValue($value, string $label): int
    {
        if (!is_int($value) || $value < 0 || $value > self::MAX_COUNT) {
            throw new InvalidArgumentException('invalid '.$label);
        }
        return $value;
    }

    private static function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('unknown provider');
        }
    }
}
