<?php
declare(strict_types=1);

/** Pure summary of existing browser-safe served_price_observation facts. */
final class AnyTourAndromedaServedPriceAccuracy
{
    public static function summarize(array $observations): array
    {
        if ($observations !== [] && array_keys($observations) !== range(0, count($observations) - 1)) {
            throw new InvalidArgumentException('ANDROMEDA_SERVED_ACCURACY_LIST');
        }
        $counts = [
            'total' => 0, 'comparable' => 0, 'currency_mismatch' => 0,
            'exact' => 0, 'within_100_bps' => 0, 'within_300_bps' => 0,
            'within_500_bps' => 0, 'within_1000_bps' => 0,
        ];
        $bps = [];
        foreach ($observations as $row) {
            self::assertObservation($row);
            ++$counts['total'];
            if ($row['state'] !== 'comparable') {
                ++$counts['currency_mismatch'];
                continue;
            }
            ++$counts['comparable'];
            $value = $row['relative_delta_bps'];
            $bps[] = $value;
            if ($value === 0) ++$counts['exact'];
            foreach ([100, 300, 500, 1000] as $limit) {
                if ($value <= $limit) ++$counts['within_' . $limit . '_bps'];
            }
        }
        sort($bps, SORT_NUMERIC);
        return [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'population' => 'natural_customer_actualizations',
            'counts' => $counts,
            'exact_accuracy' => $counts['comparable'] > 0
                ? round($counts['exact'] / $counts['comparable'], 4) : null,
            'within_100_bps_accuracy' => self::ratio($counts['within_100_bps'], $counts['comparable']),
            'within_300_bps_accuracy' => self::ratio($counts['within_300_bps'], $counts['comparable']),
            'within_500_bps_accuracy' => self::ratio($counts['within_500_bps'], $counts['comparable']),
            'within_1000_bps_accuracy' => self::ratio($counts['within_1000_bps'], $counts['comparable']),
            'mean_relative_delta_bps' => $bps === [] ? null : (int)round(array_sum($bps) / count($bps)),
            'p50_relative_delta_bps' => self::percentile($bps, 0.50),
            'p90_relative_delta_bps' => self::percentile($bps, 0.90),
            'max_relative_delta_bps' => $bps === [] ? null : max($bps),
            'runtime_gate_applied' => false,
        ];
    }

    private static function assertObservation(array $row): void
    {
        $keys = [
            'schema_version','provider','basis','state','price_basis','served_at','actualized_at',
            'served_price','final_price','signed_delta_amount','absolute_delta_amount',
            'relative_delta_bps','final_price_verified',
        ];
        $actual = array_keys($row); sort($actual); sort($keys);
        if ($actual !== $keys || ($row['schema_version'] ?? null) !== 1
            || ($row['provider'] ?? null) !== 'andromeda'
            || ($row['basis'] ?? null) !== 'search_api_response'
            || !in_array($row['state'] ?? null, ['comparable','currency_mismatch'], true)
            || !in_array($row['price_basis'] ?? null, ['search_base','transport_surcharge_estimate'], true)
            || !is_int($row['served_at'] ?? null) || !is_int($row['actualized_at'] ?? null)
            || $row['actualized_at'] < $row['served_at']
            || ($row['final_price_verified'] ?? null) !== true) {
            throw new InvalidArgumentException('ANDROMEDA_SERVED_ACCURACY_OBSERVATION');
        }
        self::money($row['served_price']); self::money($row['final_price']);
        if ($row['state'] === 'comparable') {
            if (!is_int($row['relative_delta_bps']) || $row['relative_delta_bps'] < 0
                || !is_string($row['signed_delta_amount'])
                || !is_string($row['absolute_delta_amount'])) {
                throw new InvalidArgumentException('ANDROMEDA_SERVED_ACCURACY_DELTA');
            }
        } elseif ($row['relative_delta_bps'] !== null
            || $row['signed_delta_amount'] !== null || $row['absolute_delta_amount'] !== null) {
            throw new InvalidArgumentException('ANDROMEDA_SERVED_ACCURACY_DELTA');
        }
    }

    private static function money(array $money): void
    {
        if (array_keys($money) !== ['amount','currency']
            || !is_string($money['amount'])
            || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $money['amount']) !== 1
            || preg_match('/[1-9]/', $money['amount']) !== 1
            || !is_string($money['currency'])
            || preg_match('/^[A-Z0-9_]{2,8}$/D', $money['currency']) !== 1) {
            throw new InvalidArgumentException('ANDROMEDA_SERVED_ACCURACY_MONEY');
        }
    }

    private static function ratio(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total, 4) : null;
    }

    private static function percentile(array $values, float $fraction): ?int
    {
        if ($values === []) return null;
        $index = (int)ceil(count($values) * $fraction) - 1;
        return $values[max(0, min(count($values) - 1, $index))];
    }
}
