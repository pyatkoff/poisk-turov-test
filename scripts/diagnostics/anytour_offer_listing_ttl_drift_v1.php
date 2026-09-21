<?php
declare(strict_types=1);

final class AnyTourOfferListingTtlDriftV1
{
    public static function sourceContract(string $producerSource, string $ingestSource, string $helperSource): array
    {
        $listing = self::constInt($ingestSource, 'LOCAL_LISTING_TTL_SECONDS');
        $producerMax = self::constInt($ingestSource, 'MAX_PRODUCER_EXPIRY_SECONDS');
        $context = self::constInt($helperSource, 'CONTEXT_TTL');
        $passesDtoExpiry = str_contains($producerSource, "\$dto['context']['expires_at']")
            && str_contains($producerSource, "'expires_at' => gmdate")
            && str_contains($producerSource, '$expires');
        $ingestSeparatesListing = str_contains($ingestSource, '$listingExpires')
            && str_contains($ingestSource, "'expires_at' => \$listingExpires");
        if ($listing === null || $producerMax === null || $context === null || !$passesDtoExpiry || !$ingestSeparatesListing) {
            throw new DomainException('ANYTOUR_LISTING_TTL_SOURCE_CONTRACT');
        }
        if ($listing <= $context || $producerMax < $context) {
            throw new DomainException('ANYTOUR_LISTING_TTL_SOURCE_CONTRACT');
        }
        return [
            'listing_ttl_seconds' => $listing,
            'producer_expiry_max_seconds' => $producerMax,
            'source_context_ttl_seconds' => $context,
            'producer_expiry_source' => 'dto_context',
            'ingest_rewrites_listing_expiry' => true,
        ];
    }

    public static function classify(array $contract, array $snapshot): array
    {
        foreach (['listing_ttl_seconds','producer_expiry_max_seconds','source_context_ttl_seconds'] as $key) {
            if (!is_int($contract[$key] ?? null) || $contract[$key] < 1) {
                throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_CONTRACT');
            }
        }
        if (($contract['producer_expiry_source'] ?? null) !== 'dto_context'
            || ($contract['ingest_rewrites_listing_expiry'] ?? null) !== true) {
            throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_CONTRACT');
        }
        $observations = $snapshot['observations'] ?? null;
        if (!is_array($observations) || !array_is_list($observations) || $observations === []) {
            throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_SNAPSHOT');
        }
        $expectedHash = self::sha($snapshot['expected_ingest_sha256'] ?? null);
        $installedHash = self::sha($snapshot['installed_ingest_sha256'] ?? null);
        $hashRelation = $expectedHash === null || $installedHash === null
            ? 'unknown'
            : (hash_equals($expectedHash, $installedHash) ? 'match' : 'mismatch');

        $counts = [];
        $rows = [];
        foreach ($observations as $index => $row) {
            if (!is_array($row)) throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_OBSERVATION');
            $provider = $row['provider'] ?? null;
            if (!is_string($provider) || preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $provider) !== 1) {
                throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_OBSERVATION');
            }
            $lastSeen = self::utc($row['last_seen_at'] ?? null);
            $expires = self::utc($row['expires_at'] ?? null);
            $sourceExpires = self::utc($row['source_context_expires_at'] ?? null);
            $listingTtl = $expires - $lastSeen;
            $sourceTtl = $sourceExpires - $lastSeen;
            if ($listingTtl <= 0 || $sourceTtl <= 0) {
                throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_OBSERVATION');
            }

            $state = 'contract_consistent';
            if ($sourceTtl > $contract['source_context_ttl_seconds']) {
                $state = 'source_context_ttl_mismatch';
            } elseif ($listingTtl !== $contract['listing_ttl_seconds']) {
                if ($listingTtl === $sourceTtl) {
                    $state = $hashRelation === 'mismatch'
                        ? 'installed_ingest_drift'
                        : ($hashRelation === 'match' ? 'bypass_writer_or_path' : 'installed_drift_or_bypass');
                } else {
                    $state = 'listing_ttl_mismatch';
                }
            }
            $counts[$state] = ($counts[$state] ?? 0) + 1;
            $rows[] = [
                'index' => $index,
                'provider' => $provider,
                'listing_ttl_seconds' => $listingTtl,
                'source_context_ttl_seconds' => $sourceTtl,
                'state' => $state,
            ];
        }
        ksort($counts);
        return [
            'schema_version' => 1,
            'source' => 'anytour-offer-listing-ttl-drift-v1',
            'expected' => $contract,
            'ingest_hash_relation' => $hashRelation,
            'counts' => $counts,
            'observations' => $rows,
            'writes' => 0,
            'supplier_calls' => 0,
        ];
    }

    private static function constInt(string $source, string $name): ?int
    {
        $pattern = '/private\\s+const\\s+' . preg_quote($name, '/') . '\\s*=\\s*([0-9]+)\\s*;/';
        return preg_match($pattern, $source, $m) === 1 ? (int)$m[1] : null;
    }

    private static function utc(mixed $value): int
    {
        if (!is_string($value)) throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_OBSERVATION');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\\TH:i:s\\Z') !== $value) {
            throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_OBSERVATION');
        }
        return $date->getTimestamp();
    }

    private static function sha(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_SNAPSHOT');
        }
        return $value;
    }
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $options = getopt('', ['producer:', 'ingest:', 'helper:', 'snapshot:']);
    foreach (['producer','ingest','helper','snapshot'] as $key) {
        if (!is_string($options[$key] ?? null) || $options[$key] === '') {
            fwrite(STDERR, "missing --{$key}\n"); exit(2);
        }
    }
    try {
        $producer = file_get_contents($options['producer']);
        $ingest = file_get_contents($options['ingest']);
        $helper = file_get_contents($options['helper']);
        $snapshotRaw = file_get_contents($options['snapshot']);
        if (!is_string($producer) || !is_string($ingest) || !is_string($helper) || !is_string($snapshotRaw)) {
            throw new RuntimeException('ANYTOUR_LISTING_TTL_READ');
        }
        $snapshot = json_decode($snapshotRaw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) throw new InvalidArgumentException('ANYTOUR_LISTING_TTL_SNAPSHOT');
        echo json_encode(AnyTourOfferListingTtlDriftV1::classify(
            AnyTourOfferListingTtlDriftV1::sourceContract($producer, $ingest, $helper),
            $snapshot
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        fwrite(STDERR, preg_replace('/[^A-Z0-9_:-]+/i', '_', $error->getMessage()) . "\n");
        exit(1);
    }
}
