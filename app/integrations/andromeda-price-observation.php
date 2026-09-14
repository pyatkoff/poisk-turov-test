<?php
declare(strict_types=1);

/**
 * Browser-safe comparison between the price estimate intended for search display and
 * a later supplier-verified Andromeda calc price.
 *
 * This class is pure: no supplier I/O, persistence, thresholds or currency guessing.
 * Completed quote checkpoints already persist the public result, so attaching this
 * observation there creates an append-only evidence corpus without another database.
 */
final class AnyTourAndromedaPriceObservation
{
    public static function build(?array $searchEstimate, array $finalPrice): array
    {
        $final = self::money($finalPrice, false, false, 'ANDROMEDA_PRICE_OBSERVATION_FINAL');
        $base = [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'state' => 'estimate_unavailable',
            'search_price_estimate' => null,
            'final_price' => $final,
            'signed_delta_amount' => null,
            'absolute_delta_amount' => null,
            'relative_delta_bps' => null,
            'final_price_verified' => true,
        ];
        if ($searchEstimate === null) return $base;

        $estimate = self::money($searchEstimate, true, false, 'ANDROMEDA_PRICE_OBSERVATION_ESTIMATE');
        $base['search_price_estimate'] = $estimate;
        if ($estimate['currency'] !== $final['currency']) {
            $base['state'] = 'currency_mismatch';
            return $base;
        }

        $estimateCents = self::cents($estimate['amount']);
        $finalCents = self::cents($final['amount']);
        if ($estimateCents === null || $finalCents === null || $estimateCents < 1) {
            throw new InvalidArgumentException('ANDROMEDA_PRICE_OBSERVATION_AMOUNT');
        }
        $delta = $finalCents - $estimateCents;
        $absolute = abs($delta);
        if ($absolute > intdiv(PHP_INT_MAX - intdiv($estimateCents, 2), 10000)) {
            throw new OverflowException('ANDROMEDA_PRICE_OBSERVATION_OVERFLOW');
        }
        $bps = intdiv(($absolute * 10000) + intdiv($estimateCents, 2), $estimateCents);

        $base['state'] = 'comparable';
        $base['signed_delta_amount'] = self::signedMoney($delta);
        $base['absolute_delta_amount'] = self::unsignedMoney($absolute);
        $base['relative_delta_bps'] = $bps;
        return $base;
    }

    /** Read only a price receipt from the server session, after retained-offer resolution. */
    public static function resolveServed(array $receipts, mixed $reference, array $resolved,
        int $createdAt, int $expiresAt, int $now): ?array
    {
        if (!is_string($reference) || !preg_match('/^listing_[a-f0-9]{64}$/D', $reference)) return null;
        $receipt = $receipts[$reference] ?? null;
        try {
            if (!is_array($receipt) || !self::exactKeys($receipt,
                ['context','local_id','base_price','served_price','basis','issued_at'])
                || !is_array($receipt['context']) || !self::exactKeys($receipt['context'],
                    ['provider','search_ref','generation','page','offer_ref'])
                || !is_int($receipt['issued_at']) || $receipt['issued_at'] < $createdAt
                || $receipt['issued_at'] > $now || $now - $receipt['issued_at'] >= 900
                || $now >= $expiresAt || $createdAt < 1
                || !in_array($receipt['basis'], ['search_base','transport_surcharge_estimate'], true)
                || !is_int($receipt['local_id']) || $receipt['local_id'] < 1
                || $receipt['local_id'] !== ($resolved['offer']['local_hotel_id'] ?? null)
                || !hash_equals($reference, 'listing_' . hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR)))) return null;
            foreach ($receipt['context'] as $key => $value) {
                if (!array_key_exists($key, $resolved['context'] ?? []) || $resolved['context'][$key] !== $value) return null;
            }
            $base = self::money($receipt['base_price'], false, false, 'ANDROMEDA_SERVED_PRICE_INVALID');
            self::money($receipt['served_price'], false, false, 'ANDROMEDA_SERVED_PRICE_INVALID');
            $retained = $resolved['offer']['price'] ?? null;
            if (!is_array($retained)) return null;
            // SelectedOffer retains the normalizer's provenance; the receipt stores money only.
            if (self::exactKeys($retained, ['amount','currency','currency_id','kind','fees','final'])) {
                if (!is_string($retained['currency_id']) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $retained['currency_id'])
                    || $retained['kind'] !== 'offer' || $retained['fees'] !== 'unknown'
                    || $retained['final'] !== false) return null;
                $retained = ['amount'=>$retained['amount'], 'currency'=>$retained['currency']];
            }
            if ($base !== self::money($retained, false, false, 'ANDROMEDA_SERVED_PRICE_INVALID')) return null;
            return $receipt;
        } catch (Throwable $ignored) { return null; }
    }

    /** Separate corpus: the echoed search response, NOT a new estimate during calc. */
    public static function compareServed(?array $receipt, array $quote, int $now): ?array
    {
        if ($receipt === null || ($quote['state'] ?? null) !== 'quote_verified'
            || ($quote['final_price_verified'] ?? null) !== true) return null;
        try {
            $price = self::money($receipt['served_price'], false, false, 'ANDROMEDA_SERVED_PRICE_INVALID');
            if (!is_int($receipt['issued_at']) || $now < $receipt['issued_at']
                || !in_array($receipt['basis'] ?? null, ['search_base','transport_surcharge_estimate'], true)) return null;
            // Reuse the established decimal delta arithmetic, not its estimator provenance.
            $delta = self::build($price + ['source'=>'derived_search_estimate'], $quote['final_price']);
            return [
                'schema_version'=>1, 'provider'=>'andromeda', 'basis'=>'search_api_response',
                'state'=>$delta['state'], 'price_basis'=>$receipt['basis'],
                'served_at'=>$receipt['issued_at'], 'actualized_at'=>$now,
                'served_price'=>$price, 'final_price'=>$delta['final_price'],
                'signed_delta_amount'=>$delta['signed_delta_amount'],
                'absolute_delta_amount'=>$delta['absolute_delta_amount'],
                'relative_delta_bps'=>$delta['relative_delta_bps'],
                'final_price_verified'=>true,
            ];
        } catch (Throwable $ignored) { return null; } // Measurement must not invalidate a completed quote.
    }

    /** Aggregate completed observations without encoding the owner's 80% target as a gate. */
    public static function summarize(array $observations): array
    {
        if ($observations !== [] && array_keys($observations) !== range(0, count($observations) - 1)) {
            throw new InvalidArgumentException('ANDROMEDA_PRICE_OBSERVATION_LIST');
        }
        $counts = [
            'total' => 0,
            'comparable' => 0,
            'estimate_unavailable' => 0,
            'currency_mismatch' => 0,
            'within_100_bps' => 0,
            'within_300_bps' => 0,
            'within_500_bps' => 0,
            'within_1000_bps' => 0,
        ];
        $bps = [];
        foreach ($observations as $observation) {
            self::assertObservation($observation);
            ++$counts['total'];
            $state = $observation['state'];
            if ($state !== 'comparable') {
                ++$counts[$state];
                continue;
            }
            ++$counts['comparable'];
            $value = $observation['relative_delta_bps'];
            $bps[] = $value;
            foreach ([100, 300, 500, 1000] as $limit) {
                if ($value <= $limit) ++$counts['within_'.$limit.'_bps'];
            }
        }
        sort($bps, SORT_NUMERIC);
        $mean = $bps === [] ? null : (int)round(array_sum($bps) / count($bps));
        return [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'counts' => $counts,
            'mean_relative_delta_bps' => $mean,
            'p50_relative_delta_bps' => self::percentile($bps, 0.50),
            'p90_relative_delta_bps' => self::percentile($bps, 0.90),
            'max_relative_delta_bps' => $bps === [] ? null : max($bps),
            'product_accuracy_target_applied_as_runtime_gate' => false,
        ];
    }

    private static function assertObservation(array $value): void
    {
        $expected = [
            'schema_version', 'provider', 'state', 'search_price_estimate', 'final_price',
            'signed_delta_amount', 'absolute_delta_amount', 'relative_delta_bps',
            'final_price_verified',
        ];
        if (!self::exactKeys($value, $expected)
            || ($value['schema_version'] ?? null) !== 1
            || ($value['provider'] ?? null) !== 'andromeda'
            || !in_array($value['state'] ?? null, ['comparable', 'estimate_unavailable', 'currency_mismatch'], true)
            || ($value['final_price_verified'] ?? null) !== true) {
            throw new InvalidArgumentException('ANDROMEDA_PRICE_OBSERVATION_STATE');
        }
        self::money($value['final_price'], false, false, 'ANDROMEDA_PRICE_OBSERVATION_FINAL');
        if ($value['search_price_estimate'] !== null) {
            self::money($value['search_price_estimate'], true, false, 'ANDROMEDA_PRICE_OBSERVATION_ESTIMATE');
        }
        if ($value['state'] === 'comparable') {
            if (!is_string($value['signed_delta_amount'])
                || preg_match('/^-?(?:0|[1-9][0-9]{0,12})\.[0-9]{2}$/D', $value['signed_delta_amount']) !== 1
                || !is_string($value['absolute_delta_amount'])
                || preg_match('/^(?:0|[1-9][0-9]{0,12})\.[0-9]{2}$/D', $value['absolute_delta_amount']) !== 1
                || !is_int($value['relative_delta_bps']) || $value['relative_delta_bps'] < 0) {
                throw new InvalidArgumentException('ANDROMEDA_PRICE_OBSERVATION_DELTA');
            }
        } elseif ($value['signed_delta_amount'] !== null
            || $value['absolute_delta_amount'] !== null
            || $value['relative_delta_bps'] !== null) {
            throw new InvalidArgumentException('ANDROMEDA_PRICE_OBSERVATION_DELTA');
        }
    }

    private static function money(array $value, bool $estimate, bool $allowZero, string $error): array
    {
        $expected = $estimate ? ['amount', 'currency', 'source'] : ['amount', 'currency'];
        if (!self::exactKeys($value, $expected)) throw new InvalidArgumentException($error);
        $amount = $value['amount'] ?? null;
        $currency = $value['currency'] ?? null;
        if (!is_string($amount)
            || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $amount) !== 1
            || (!$allowZero && preg_match('/[1-9]/', $amount) !== 1)
            || !is_string($currency) || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) {
            throw new InvalidArgumentException($error);
        }
        if ($estimate && ($value['source'] ?? null) !== 'derived_search_estimate') {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function cents(string $amount): ?int
    {
        $parts = explode('.', $amount, 2);
        $whole = (int)$parts[0];
        if ($whole > intdiv(PHP_INT_MAX, 100)) return null;
        $fraction = str_pad($parts[1] ?? '', 2, '0');
        return ($whole * 100) + (int)$fraction;
    }

    private static function signedMoney(int $cents): string
    {
        $prefix = $cents < 0 ? '-' : '';
        return $prefix . self::unsignedMoney(abs($cents));
    }

    private static function unsignedMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function percentile(array $values, float $fraction): ?int
    {
        if ($values === []) return null;
        $index = (int)ceil(count($values) * $fraction) - 1;
        return $values[max(0, min(count($values) - 1, $index))];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
