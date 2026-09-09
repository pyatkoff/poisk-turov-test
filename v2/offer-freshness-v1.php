<?php
/** Display-date policy for cached offers; never an assertion of bookability. */
declare(strict_types=1);

function v2_offer_business_date(?DateTimeImmutable $now = null): string
{
    // Match the existing SEO price calendar/seasonal inventory business clock.
    // This is not a per-airport flight timezone or a same-day sales cutoff.
    $timezone = new DateTimeZone('Europe/Moscow');
    return ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone)->format('Y-m-d');
}

/** Dates advertised by the public hot-tour handoff, aligned to the offer business clock. */
function v2_offer_hot_search_window(?DateTimeImmutable $now = null): array
{
    $timezone = new DateTimeZone('Europe/Moscow');
    $today = new DateTimeImmutable(v2_offer_business_date($now), $timezone);
    $from = $today->modify('+1 day');
    return [$from->format('Y-m-d'), $from->modify('+14 days')->format('Y-m-d')];
}

function v2_offer_departure_is_current(string $departureDate, string $businessDate): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $departureDate)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $departureDate));
    return checkdate($month, $day, $year) && $departureDate >= $businessDate;
}
