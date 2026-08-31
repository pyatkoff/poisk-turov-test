<?php
/** Shared confidence gates for AnyTour price intelligence. */
declare(strict_types=1);

function v2_price_confidence_stage(int $independentSearches, int $observedDays): string
{
    if ($independentSearches >= 30 && $observedDays >= 7) return 'history_ready';
    if ($independentSearches >= 15 && $observedDays >= 3) return 'guarded_delta_ready';
    if ($independentSearches >= 5 && $observedDays >= 2) return 'good_price_only';
    return 'collect_more';
}

function v2_price_confidence_rank(string $stage): int
{
    return match ($stage) {
        'history_ready' => 3,
        'guarded_delta_ready' => 2,
        'good_price_only' => 1,
        default => 0,
    };
}
