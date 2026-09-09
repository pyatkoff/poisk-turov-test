<?php
/** Pure helpers for AnyTour departure-date low-price calendars. */
declare(strict_types=1);

function v2_price_calendar_date(string $raw): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $raw) {
        throw new InvalidArgumentException('calendar date must use a real YYYY-MM-DD date');
    }
    return $date;
}

function v2_price_calendar_build(array $rows, string $dateFrom, string $dateTo): array
{
    $from = v2_price_calendar_date($dateFrom);
    $to = v2_price_calendar_date($dateTo);
    if ($to < $from) throw new InvalidArgumentException('calendar dateTo must not precede dateFrom');
    $span = (int)$from->diff($to)->format('%a') + 1;
    if ($span > 31) throw new InvalidArgumentException('calendar range must not exceed 31 days');

    $byDate = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $date = trim((string)($row['departure_date'] ?? ''));
        $dt = v2_price_calendar_date($date);
        if ($dt < $from || $dt > $to) continue;

        $minPrice = (float)($row['min_price'] ?? 0);
        $hotelCount = (int)($row['hotel_count'] ?? 0);
        $searchCount = (int)($row['independent_search_count'] ?? 0);
        $latestObservedAt = trim((string)($row['latest_observed_at'] ?? ''));
        if ($minPrice <= 0 || $hotelCount <= 0 || $searchCount <= 0 || $latestObservedAt === '') {
            throw new InvalidArgumentException('invalid observed calendar aggregate');
        }
        if (isset($byDate[$date])) throw new InvalidArgumentException('duplicate calendar date aggregate');
        $byDate[$date] = [
            'date' => $date,
            'observed' => true,
            'minPrice' => $minPrice,
            'hotelCount' => $hotelCount,
            'independentSearchCount' => $searchCount,
            'latestObservedAt' => $latestObservedAt,
            'best' => false,
        ];
    }

    $series = [];
    $observedPrices = [];
    for ($cursor = $from; $cursor <= $to; $cursor = $cursor->modify('+1 day')) {
        $date = $cursor->format('Y-m-d');
        if (isset($byDate[$date])) {
            $series[] = $byDate[$date];
            $observedPrices[$date] = (float)$byDate[$date]['minPrice'];
        } else {
            $series[] = [
                'date' => $date,
                'observed' => false,
                'minPrice' => null,
                'hotelCount' => null,
                'independentSearchCount' => null,
                'latestObservedAt' => null,
                'best' => false,
            ];
        }
    }

    $bestDate = null;
    $bestPrice = null;
    if (count($observedPrices) >= 2) {
        $bestPrice = min($observedPrices);
        foreach ($observedPrices as $date => $price) {
            if ($price === $bestPrice) {
                $bestDate = $date;
                break;
            }
        }
        foreach ($series as &$point) {
            if (($point['date'] ?? null) === $bestDate) $point['best'] = true;
        }
        unset($point);
    }

    return [
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'days' => $span,
        'observedDays' => count($observedPrices),
        'missingDays' => $span - count($observedPrices),
        'bestDate' => $bestDate,
        'bestPrice' => $bestPrice,
        'series' => $series,
        'missing_semantics' => 'unknown_not_zero',
    ];
}
