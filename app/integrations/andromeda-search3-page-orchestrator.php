<?php
declare(strict_types=1);

/**
 * Sequential, supplier-agnostic orchestration for already-projected Andromeda pages.
 *
 * The runner owns provider auth/session/checkpoints and pricing. This helper only asks
 * for the next advertised page and merges public projections. It never performs a
 * supplier request itself and never recalculates money.
 */
final class AnyTourAndromedaSearch3PageOrchestrator
{
    private const MAX_PAGES = 1000;

    /** @param callable(array):array $runner */
    public static function run(array $request, callable $runner): array
    {
        $explicit = array_key_exists('page', $request);
        if ($explicit && $request['page'] !== 1) return $runner($request);
        if (($request['action'] ?? null) !== null || isset($request['hotel_scope'])) return $runner($request);

        $pages = [];
        $target = 1;
        for ($page = 1; $page <= $target; ++$page) {
            if ($page > self::MAX_PAGES) throw new RuntimeException('andromeda_pages_exceeded');
            $next = $request;
            $next['page'] = $page;
            $projection = $runner($next);
            self::validatePage($projection, $page, $pages[0] ?? null);
            $pages[] = $projection;

            $advertised = (int)$projection['pages_count'];
            if ($advertised < $page || $advertised > self::MAX_PAGES) {
                throw new RuntimeException('andromeda_pages_invalid');
            }
            $target = max($target, $advertised);
        }

        return self::merge($pages);
    }

    /** @param array<int,array> $pages */
    public static function merge(array $pages): array
    {
        if ($pages === []) throw new InvalidArgumentException('andromeda_pages_empty');
        $first = $pages[0];
        $hotels = [];
        $hotelOrder = [];
        $tourSeen = [];
        $received = 0;
        $mapped = 0;
        $target = 1;

        foreach ($pages as $index => $page) {
            self::validatePage($page, $index + 1, $first);
            $target = max($target, (int)$page['pages_count']);
            $received += (int)($page['received_offers'] ?? 0);
            $mapped += (int)($page['mapped_offers'] ?? 0);

            foreach ($page['hotels'] as $hotel) {
                $localId = $hotel['local_id'] ?? null;
                if (!is_int($localId) || $localId < 1 || !is_array($hotel['tours'] ?? null)) {
                    throw new RuntimeException('andromeda_projection_invalid');
                }
                if (!isset($hotels[$localId])) {
                    $hotels[$localId] = $hotel;
                    $hotels[$localId]['tours'] = [];
                    $hotelOrder[] = $localId;
                } elseif (empty($hotels[$localId]['andromeda_content']) && !empty($hotel['andromeda_content'])) {
                    $hotels[$localId]['andromeda_content'] = $hotel['andromeda_content'];
                }

                foreach ($hotel['tours'] as $tour) {
                    $ref = $tour['offer_ref'] ?? null;
                    if (!is_string($ref) || !preg_match('/^offer_[a-f0-9]{64}$/D', $ref)) {
                        throw new RuntimeException('andromeda_offer_ref_invalid');
                    }
                    if (isset($tourSeen[$ref])) continue;
                    $tourSeen[$ref] = true;
                    $hotels[$localId]['tours'][] = $tour;
                }
            }
        }

        $last = $pages[count($pages) - 1];
        if ((int)$last['page'] !== $target) throw new RuntimeException('andromeda_pages_incomplete');
        $result = $first;
        $result['hotels'] = array_values(array_map(static fn(int $id): array => $hotels[$id], $hotelOrder));
        $result['page'] = $target;
        $result['pages_count'] = $target;
        $result['status'] = $last['status'] ?? $first['status'] ?? 'complete';
        $result['external_search_pending'] = false;
        $result['received_offers'] = $received;
        $result['mapped_offers'] = $mapped;
        $result['selection_enabled'] = false;
        return $result;
    }

    private static function validatePage(array $page, int $expectedPage, ?array $first): void
    {
        if (($page['provider'] ?? null) !== 'andromeda'
            || ($page['page'] ?? null) !== $expectedPage
            || !is_int($page['pages_count'] ?? null)
            || !is_array($page['hotels'] ?? null)
            || !is_int($page['generation'] ?? null)
            || !is_string($page['search_ref'] ?? null)) {
            throw new RuntimeException('andromeda_projection_invalid');
        }
        if ($first !== null && (($page['generation'] ?? null) !== ($first['generation'] ?? null)
            || ($page['search_ref'] ?? null) !== ($first['search_ref'] ?? null))) {
            throw new RuntimeException('andromeda_page_context_mismatch');
        }
    }
}
