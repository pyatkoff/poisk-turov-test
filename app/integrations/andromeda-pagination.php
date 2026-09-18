<?php
declare(strict_types=1);

/**
 * Shared Andromeda/SAMO pagination facts.
 *
 * Real retained supplier evidence shows a search may advertise a larger PAGES_COUNT
 * on data pages and later terminate with an empty page whose PAGES_COUNT is 0.
 * That exact empty/complete shape is EOF, not a shrinking-page error.
 */
final class AnyTourAndromedaPaginationV1
{
    public const MAX_PAGES = 1000;

    public static function isTerminalEmpty(
        int $page,
        int $pagesCount,
        int $itemCount,
        string $status,
        int $rejectedCount = 0
    ): bool {
        return $page >= 1
            && $pagesCount === 0
            && $itemCount === 0
            && $rejectedCount === 0
            && $status === 'complete';
    }

    public static function nextTarget(
        int $page,
        int $pagesCount,
        int $itemCount,
        string $status,
        int $rejectedCount,
        int $currentTarget
    ): array {
        if ($page < 1 || $page > self::MAX_PAGES || $currentTarget < 0 || $currentTarget > self::MAX_PAGES
            || $pagesCount < 0 || $pagesCount > self::MAX_PAGES || $itemCount < 0 || $rejectedCount < 0) {
            throw new RuntimeException('andromeda_pages_invalid');
        }
        if (self::isTerminalEmpty($page, $pagesCount, $itemCount, $status, $rejectedCount)) {
            return ['terminal' => true, 'target' => max(0, $page - 1)];
        }
        if ($pagesCount < $page) {
            throw new RuntimeException('andromeda_pages_invalid');
        }
        return ['terminal' => false, 'target' => max($currentTarget, $pagesCount)];
    }
}
