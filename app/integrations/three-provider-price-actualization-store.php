<?php
declare(strict_types=1);

require_once __DIR__ . '/three-provider-price-actualization-observation.php';

/**
 * Private append-only store for supplier-free natural actualization observations.
 * Input must already be produced by AnyTourThreeProviderPriceActualizationObservation.
 */
final class AnyTourThreeProviderPriceActualizationStore
{
    public static function append(string $path, array $observation): void
    {
        self::assertObservation($observation);
        $directory = dirname($path);
        if (!is_dir($directory)) throw new RuntimeException('THREE_PROVIDER_ACTUALIZATION_STORE_DIRECTORY');
        $line = json_encode($observation, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $handle = fopen($path, 'ab');
        if ($handle === false) throw new RuntimeException('THREE_PROVIDER_ACTUALIZATION_STORE_OPEN');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('THREE_PROVIDER_ACTUALIZATION_STORE_LOCK');
            $written = fwrite($handle, $line);
            if ($written !== strlen($line) || !fflush($handle)) throw new RuntimeException('THREE_PROVIDER_ACTUALIZATION_STORE_WRITE');
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public static function summarize(string $path, int $maxRows = 10000): array
    {
        if ($maxRows < 1 || $maxRows > 100000) throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_SUMMARY_LIMIT');
        if (!is_file($path)) return ['total' => 0, 'exact' => 0, 'accuracy' => null, 'providers' => []];
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('THREE_PROVIDER_ACTUALIZATION_STORE_OPEN');
        $rows = [];
        try {
            if (!flock($handle, LOCK_SH)) throw new RuntimeException('THREE_PROVIDER_ACTUALIZATION_STORE_LOCK');
            while (($line = fgets($handle)) !== false) {
                $decoded = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
                // Correct only the in-memory summary flag; preserve the append-only source bytes.
                $decoded['exact_match'] = self::assertObservation($decoded, true);
                $rows[] = $decoded;
                if (count($rows) > $maxRows) array_shift($rows);
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $summary = ['total' => 0, 'exact' => 0, 'accuracy' => null, 'providers' => []];
        foreach ($rows as $row) {
            ++$summary['total'];
            if ($row['exact_match']) ++$summary['exact'];
            $provider = $row['provider'];
            $operator = $row['operator'];
            if (!isset($summary['providers'][$provider])) {
                $summary['providers'][$provider] = ['total' => 0, 'exact' => 0, 'accuracy' => null, 'operators' => []];
            }
            ++$summary['providers'][$provider]['total'];
            if ($row['exact_match']) ++$summary['providers'][$provider]['exact'];
            if (!isset($summary['providers'][$provider]['operators'][$operator])) {
                $summary['providers'][$provider]['operators'][$operator] = ['total' => 0, 'exact' => 0, 'accuracy' => null];
            }
            ++$summary['providers'][$provider]['operators'][$operator]['total'];
            if ($row['exact_match']) ++$summary['providers'][$provider]['operators'][$operator]['exact'];
        }

        self::finishAccuracy($summary);
        foreach ($summary['providers'] as &$providerSummary) {
            self::finishAccuracy($providerSummary);
            foreach ($providerSummary['operators'] as &$operatorSummary) self::finishAccuracy($operatorSummary);
            unset($operatorSummary);
            ksort($providerSummary['operators']);
        }
        unset($providerSummary);
        ksort($summary['providers']);
        return $summary;
    }

    private static function finishAccuracy(array &$bucket): void
    {
        $bucket['accuracy'] = $bucket['total'] > 0 ? round($bucket['exact'] / $bucket['total'], 4) : null;
    }

    private static function assertObservation(array $row, bool $readingLegacy = false): bool
    {
        if (($row['schema_version'] ?? null) !== 1
            || !is_string($row['provider'] ?? null) || $row['provider'] === ''
            || !is_string($row['operator'] ?? null) || $row['operator'] === ''
            || !is_int($row['local_hotel_id'] ?? null)
            || !is_bool($row['exact_match'] ?? null)
            || ($row['listing_final_price_ready'] ?? null) !== true
            || ($row['quote_final_price_verified'] ?? null) !== true
            || !is_array($row['listing_price'] ?? null)
            || !is_array($row['verified_quote_price'] ?? null)
            || !is_array($row['tour'] ?? null)
            || !is_array($row['context'] ?? null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_OBSERVATION');
        }
        foreach (['listing_price', 'verified_quote_price'] as $key) {
            if (($row[$key]['currency'] ?? null) !== 'RUB'
                || !is_string($row[$key]['amount'] ?? null)
                || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $row[$key]['amount'])
                || !preg_match('/[1-9]/', $row[$key]['amount'])) {
                throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_OBSERVATION_MONEY');
            }
        }
        $derivedExact = AnyTourThreeProviderPriceActualizationObservation::moneyMatches(
            $row['listing_price'], $row['verified_quote_price']);
        // Old producers accepted false for equal amounts with different decimal spellings.
        // Read that already-valid history, but never accept an inconsistent new append or
        // a forged true flag for genuinely different amounts (including one kopeck).
        $legacyMismatch = $readingLegacy && $derivedExact && $row['exact_match'] === false
            && $row['listing_price'] !== $row['verified_quote_price'];
        if ($row['exact_match'] !== $derivedExact && !$legacyMismatch) {
            throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_EXACT_MATCH');
        }
        $encoded = json_encode($row, JSON_THROW_ON_ERROR);
        foreach (['supplier_offer_id', 'offer_ref', 'search_ref', 'externalOfferId', 'claiminc', 'quote_evidence_digest'] as $forbidden) {
            if (strpos($encoded, $forbidden) !== false) throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_PRIVATE_REF');
        }
        return $derivedExact;
    }
}
