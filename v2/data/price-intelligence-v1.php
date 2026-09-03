<?php
/** Guarded price intelligence from exact comparable daily history. */
declare(strict_types=1);

require_once __DIR__ . '/price-confidence-v1.php';

function v2_price_intelligence_median(array $values): ?float
{
    $clean = array_values(array_filter(array_map('floatval', $values), static fn(float $v): bool => $v > 0));
    if ($clean === []) return null;
    sort($clean, SORT_NUMERIC);
    $count = count($clean);
    $middle = intdiv($count, 2);
    return $count % 2 === 1 ? $clean[$middle] : ($clean[$middle - 1] + $clean[$middle]) / 2;
}

function v2_price_intelligence_summary(array $dailyRows, float $currentPrice, int $minimumDropPercent = 5): array
{
    $minimumDropPercent = max(1, min(50, $minimumDropPercent));
    $series = [];
    $seenDates = [];
    $observationCount = 0;
    $independentSearchCount = 0;

    foreach ($dailyRows as $row) {
        if (!is_array($row)) continue;
        $date = trim((string)($row['price_date'] ?? ''));
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$dt || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $dt->format('Y-m-d') !== $date) {
            return ['ok' => false, 'state' => 'invalid_history', 'reason' => 'invalid_price_date'];
        }
        if (isset($seenDates[$date])) {
            return ['ok' => false, 'state' => 'invalid_history', 'reason' => 'duplicate_exact_segment_day'];
        }

        $min = (float)($row['min_price'] ?? 0);
        $median = (float)($row['median_price'] ?? 0);
        $max = (float)($row['max_price'] ?? 0);
        $count = (int)($row['observation_count'] ?? 0);
        $searchCount = (int)($row['independent_search_count'] ?? 0);
        if (
            $min <= 0 || $median <= 0 || $max <= 0 || $count <= 0
            || $searchCount < 0 || $searchCount > $count
            || $min > $median || $median > $max
        ) {
            return ['ok' => false, 'state' => 'invalid_history', 'reason' => 'invalid_daily_price_aggregate'];
        }

        $seenDates[$date] = true;
        $observationCount += $count;
        $independentSearchCount += $searchCount;
        $series[] = [
            'date' => $date,
            'minPrice' => $min,
            'medianPrice' => $median,
            'maxPrice' => $max,
            'observationCount' => $count,
            'independentSearchCount' => $searchCount,
        ];
    }

    usort($series, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));
    $observedDays = count($series);
    $stage = v2_price_confidence_stage($independentSearchCount, $observedDays);

    if ($series === [] || $currentPrice <= 0) {
        return [
            'ok' => true,
            'state' => $stage,
            'showPromoDrop' => false,
            'showHistoricalDrop' => false,
            'observedDays' => $observedDays,
            'observationCount' => $observationCount,
            'independentSearchCount' => $independentSearchCount,
            'series' => $series,
        ];
    }

    $dailyMins = array_column($series, 'minPrice');
    $dailyMedians = array_column($series, 'medianPrice');
    $dailyMaxes = array_column($series, 'maxPrice');

    // tour_price_daily_exact is keyed by the strict exact-comparable fingerprint:
    // departure, hotel, exact departure date, nights, tourists, meal, room,
    // operator and currency. Its maximum is therefore a real observed price of
    // the same product, not a maximum borrowed from a different tour.
    $referencePrice = max($dailyMaxes);
    $historicalLow = min($dailyMins);
    $medianReference = v2_price_intelligence_median($dailyMedians);
    $referenceDate = null;
    $historicalLowDate = null;
    foreach ($series as $point) {
        if ((float)$point['maxPrice'] === (float)$referencePrice) $referenceDate = $point['date'];
        if ($historicalLowDate === null && (float)$point['minPrice'] === (float)$historicalLow) $historicalLowDate = $point['date'];
    }

    $dropAmount = max(0.0, $referencePrice - $currentPrice);
    $dropPercentRaw = $referencePrice > 0 ? ($dropAmount / $referencePrice) * 100 : 0.0;
    $dropPercent = (int)round($dropPercentRaw);
    $hasMaterialDrop = $referencePrice > $currentPrice && $dropPercent >= $minimumDropPercent;

    // Commercial price-drop claims need evidence, not an arbitrary waiting
    // period. Two exact comparable observations are enough to establish that
    // this same tour was really observed at two prices. Longer history remains
    // useful for stronger analytics (weekly lows, normal-price bands, trends),
    // but it no longer blocks the truthful crossed-out observed price.
    $hasComparableHistory = $observationCount >= 2;
    $showPromoDrop = $hasComparableHistory && $hasMaterialDrop;
    $showHistoricalDrop = $hasComparableHistory && $hasMaterialDrop;

    $goodPrice =
        v2_price_confidence_rank($stage) >= 1
        && $medianReference !== null
        && $currentPrice <= $medianReference * 0.95;

    return [
        'ok' => true,
        'state' => $stage,
        'currentPrice' => $currentPrice,
        'observedDays' => $observedDays,
        'observationCount' => $observationCount,
        'independentSearchCount' => $independentSearchCount,
        'historicalLowPrice' => $historicalLow,
        'historicalLowDate' => $historicalLowDate,
        'medianDailyPrice' => $medianReference,
        'referencePrice' => $referencePrice,
        'referenceDate' => $referenceDate,
        'referenceMethod' => 'max_observed_price_exact_comparable_segment',
        'historicalDropAmount' => $dropAmount,
        'historicalDropPercent' => $dropPercent,
        'showPromoDrop' => $showPromoDrop,
        'showHistoricalDrop' => $showHistoricalDrop,
        'goodPrice' => $goodPrice,
        'historyReady' => $stage === 'history_ready',
        'series' => $series,
    ];
}
