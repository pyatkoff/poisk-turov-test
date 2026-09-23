<?php
/** Consumer-facing price intelligence across equivalent offers, independent of operator. */
declare(strict_types=1);

require_once __DIR__ . '/price-intelligence-v1.php';

/**
 * Collapse strict operator-specific daily rows into one daily "best available equivalent"
 * price. Independent-search evidence is deliberately conservative: take the maximum
 * provider/operator-specific count for the day instead of summing counts that may refer
 * to the same underlying search.
 */
function v2_price_consumer_daily_best_rows(array $strictRows): array
{
    $days = [];
    foreach ($strictRows as $row) {
        if (!is_array($row)) continue;
        $date = trim((string)($row['price_date'] ?? ''));
        $price = (float)($row['min_price'] ?? 0);
        $observations = (int)($row['observation_count'] ?? 0);
        $searches = (int)($row['independent_search_count'] ?? 0);
        $operator = (int)($row['operator_id'] ?? 0);
        if ($date === '' || $price <= 0 || $observations <= 0 || $searches < 0 || $searches > $observations) {
            throw new InvalidArgumentException('invalid consumer price history row');
        }

        if (!isset($days[$date])) {
            $days[$date] = [
                'price_date' => $date,
                'best_price' => $price,
                'observation_count' => 0,
                'independent_search_count' => 0,
                'operators' => [],
            ];
        }
        $days[$date]['best_price'] = min((float)$days[$date]['best_price'], $price);
        $days[$date]['observation_count'] += $observations;
        $days[$date]['independent_search_count'] = max((int)$days[$date]['independent_search_count'], $searches);
        if ($operator > 0) $days[$date]['operators'][$operator] = true;
    }

    ksort($days, SORT_STRING);
    $rows = [];
    foreach ($days as $day) {
        $best = (float)$day['best_price'];
        $observations = (int)$day['observation_count'];
        $searches = min($observations, (int)$day['independent_search_count']);
        $rows[] = [
            'price_date' => (string)$day['price_date'],
            // Existing price intelligence can be reused safely when all three values
            // describe the same consumer metric: cheapest equivalent offer that day.
            'min_price' => $best,
            'median_price' => $best,
            'max_price' => $best,
            'observation_count' => $observations,
            'independent_search_count' => $searches,
            'operator_count' => count($day['operators']),
        ];
    }
    return $rows;
}

function v2_price_consumer_intelligence_summary(
    array $strictRows,
    float $currentPrice,
    int $minimumDropPercent = 5
): array {
    $daily = v2_price_consumer_daily_best_rows($strictRows);
    $summary = v2_price_intelligence_summary($daily, $currentPrice, $minimumDropPercent);

    $operatorsByDate = [];
    foreach ($daily as $row) $operatorsByDate[(string)$row['price_date']] = (int)$row['operator_count'];
    if (is_array($summary['series'] ?? null)) {
        foreach ($summary['series'] as &$point) {
            $point['operatorCount'] = $operatorsByDate[(string)($point['date'] ?? '')] ?? 0;
        }
        unset($point);
    }

    $summary['comparisonMode'] = 'consumer_equivalent_operator_independent';
    $summary['hotelIdentity'] = 'tourvisor_legacy';
    if (($summary['ok'] ?? false) === true && ($summary['series'] ?? []) !== []) {
        $summary['referenceMethod'] = 'max_daily_best_price_consumer_comparable_segment';
    }
    return $summary;
}
