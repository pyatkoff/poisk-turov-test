<?php
/**
 * LOCAL-owned persistence boundary for one already-normalized provider snapshot.
 *
 * No supplier I/O, provider translation, hotel matching, or price arithmetic lives here.
 * The caller must supply Search3 params plus DTOs that already satisfy the existing
 * AnyTourOfferStoreV1 final-price-ready contract.
 */
declare(strict_types=1);

require_once __DIR__ . '/anytour-search-scope-v1.php';
require_once __DIR__ . '/anytour-offer-store-v1.php';

final class AnyTourOfferSnapshotIngestV1
{
    private const MAX_OFFERS = 5000;
    private const LOCAL_LISTING_TTL_SECONDS = 86400;
    private const MAX_PRODUCER_EXPIRY_SECONDS = 21600;
    private const ROW_KEYS = ['anytour_hotel_id', 'dto', 'expires_at'];

    /**
     * Replace one provider's completed snapshot for the exact canonical Search3 scope.
     *
     * Schema v2 is mandatory because it keeps running/aborted rows physically separate
     * from the previous completed refresh. A failed snapshot is aborted and therefore
     * remains invisible to AnyTourOfferStoreReadV2.
     */
    public static function replaceCompleteSnapshot(
        PDO $db,
        string $provider,
        array $searchParams,
        array $rows,
        DateTimeImmutable $now
    ): array {
        self::requireSchemaV2($db);
        if (!array_is_list($rows) || count($rows) > self::MAX_OFFERS) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_SNAPSHOT_ROWS');
        }

        $scope = AnyTourSearchScopeV1::fromParams($searchParams);
        $prepared = self::prepareRows($provider, $rows, $now);
        $token = AnyTourOfferStoreV1::beginRefresh($db, $provider, $scope['digest'], $now);
        $written = [];

        try {
            foreach ($prepared as $row) {
                $written[] = AnyTourOfferStoreV1::upsertReadyOffer(
                    $db,
                    $token,
                    $row['anytour_hotel_id'],
                    $row['dto'],
                    $row['expires_at'],
                    $now
                );
            }
            $complete = AnyTourOfferStoreV1::completeRefresh($db, $token, $now);
        } catch (Throwable $error) {
            try {
                AnyTourOfferStoreV1::abortRefresh($db, $token, $now);
            } catch (Throwable $abortError) {
                throw new RuntimeException(
                    'ANYTOUR_OFFER_SNAPSHOT_ABORT_FAILED:' . $abortError->getMessage(),
                    0,
                    $error
                );
            }
            throw $error;
        }

        $hotels = [];
        foreach ($written as $item) {
            $hotels[(int)$item['anytourHotelId']] = true;
        }

        return [
            'source' => 'anytour-offer-snapshot-ingest-v1',
            'provider' => $provider,
            'scopeVersion' => $scope['version'],
            'scopeDigest' => $scope['digest'],
            'refreshTokenDigest' => hash('sha256', $token),
            'offerCount' => count($written),
            'hotelCount' => count($hotels),
            'expiredUnseen' => (int)($complete['expiredUnseen'] ?? 0),
            'selectionAuthority' => false,
        ];
    }

    private static function requireSchemaV2(PDO $db): void
    {
        if ($db->inTransaction()) {
            throw new LogicException('ANYTOUR_OFFER_SNAPSHOT_CALLER_TRANSACTION');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $value = $db->query(
            'SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1 LIMIT 1'
        )->fetchColumn();
        if ((int)$value !== 2 || (string)$value !== '2') {
            throw new DomainException('ANYTOUR_OFFER_SNAPSHOT_SCHEMA_V2_REQUIRED');
        }
    }

    private static function prepareRows(string $provider, array $rows, DateTimeImmutable $now): array
    {
        $prepared = [];
        $identities = [];
        $listingExpires = $now->setTimestamp($now->getTimestamp() + self::LOCAL_LISTING_TTL_SECONDS);
        foreach ($rows as $row) {
            if (!is_array($row) || !self::exactKeys($row, self::ROW_KEYS)) {
                throw new InvalidArgumentException('ANYTOUR_OFFER_SNAPSHOT_ROW');
            }
            $ownId = $row['anytour_hotel_id'] ?? null;
            $dto = $row['dto'] ?? null;
            if (!is_int($ownId) || $ownId < 1 || !is_array($dto) || ($dto['provider'] ?? null) !== $provider) {
                throw new InvalidArgumentException('ANYTOUR_OFFER_SNAPSHOT_ROW');
            }
            $identity = $dto['identity'] ?? null;
            if (!is_array($identity)) {
                throw new InvalidArgumentException('ANYTOUR_OFFER_SNAPSHOT_IDENTITY');
            }
            $identityJson = json_encode(
                self::canonical(['provider' => $provider, 'identity' => $identity]),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $identityDigest = hash('sha256', $identityJson);
            if (isset($identities[$identityDigest])) {
                throw new InvalidArgumentException('ANYTOUR_OFFER_SNAPSHOT_DUPLICATE_IDENTITY');
            }
            $identities[$identityDigest] = true;

            // The producer expiry is still a required freshness assertion for accepting
            // this snapshot. It is not the lifetime of the cached LOCAL listing: supplier
            // context remains inside the DTO and selection is always refresh-required.
            $producerExpires = self::utc($row['expires_at'] ?? null);
            $producerSeconds = $producerExpires->getTimestamp() - $now->getTimestamp();
            if ($producerSeconds <= 0 || $producerSeconds > self::MAX_PRODUCER_EXPIRY_SECONDS) {
                throw new InvalidArgumentException('ANYTOUR_OFFER_SNAPSHOT_EXPIRY');
            }
            $prepared[] = [
                'anytour_hotel_id' => $ownId,
                'dto' => $dto,
                'expires_at' => $listingExpires,
            ];
        }
        return $prepared;
    }

    private static function utc(mixed $value): DateTimeImmutable
    {
        $date = is_string($value)
            ? DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new DateTimeZone('UTC'))
            : false;
        if (!$date || $date->format('Y-m-d\\TH:i:s\\Z') !== $value) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_SNAPSHOT_EXPIRY');
        }
        return $date;
    }

    private static function exactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        $expected = $keys;
        sort($expected, SORT_STRING);
        return $actual === $expected;
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class, 'canonical'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonical($item);
        return $value;
    }
}