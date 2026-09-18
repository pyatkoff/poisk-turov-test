<?php
/**
 * LOCAL-owned persistence boundary for already-normalized provider snapshots.
 *
 * No supplier I/O, provider translation, hotel matching, or price arithmetic lives here.
 * The caller must supply Search3 params plus DTOs that already satisfy the existing
 * AnyTourOfferStoreV1 offer contract.
 */
declare(strict_types=1);

require_once __DIR__ . '/anytour-search-scope-v1.php';
require_once __DIR__ . '/anytour-offer-scope-index-v1.php';
require_once __DIR__ . '/anytour-offer-store-v1.php';
require_once __DIR__ . '/anytour-provider-identity-bridge-v1.php';

final class AnyTourOfferSnapshotIngestV1
{
    private const MAX_OFFERS = 5000;
    private const LOCAL_LISTING_TTL_SECONDS = 86400;
    private const MAX_PRODUCER_EXPIRY_SECONDS = 21600;
    private const ROW_KEYS = ['anytour_hotel_id', 'dto', 'expires_at'];

    /**
     * Replace one provider's completed snapshot for the exact canonical Search3 scope.
     * Empty rows are authoritative here and therefore intentionally clear only this
     * provider+scope cohort.
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
        return self::persistSnapshot($db, $provider, $scope, $prepared, $now, false);
    }

    /**
     * Merge a non-authoritative partial provider page/batch into the current completed
     * provider+scope cohort. Offers not observed in this batch remain visible only for
     * their existing LOCAL listing TTL; the partial batch never refreshes their age.
     *
     * An empty partial batch is rejected before a refresh starts so it can never be
     * mistaken for an authoritative empty snapshot.
     */
    public static function mergePartialSnapshot(
        PDO $db,
        string $provider,
        array $searchParams,
        array $rows,
        DateTimeImmutable $now
    ): array {
        self::requireSchemaV2($db);
        if (!array_is_list($rows) || $rows === [] || count($rows) > self::MAX_OFFERS) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PARTIAL_ROWS');
        }

        $scope = AnyTourSearchScopeV1::fromParams($searchParams);
        $prepared = self::prepareRows($provider, $rows, $now);
        return self::persistSnapshot($db, $provider, $scope, $prepared, $now, true);
    }

    private static function persistSnapshot(
        PDO $db,
        string $provider,
        array $scope,
        array $prepared,
        DateTimeImmutable $now,
        bool $partial
    ): array {
        // Scope metadata is additive and has no selection authority. During a rolling
        // deployment the index may not yet exist; exact-scope persistence remains safe.
        $scopeIndexed = AnyTourOfferScopeIndexV1::recordIfInstalled($db, $scope, $now);
        $token = AnyTourOfferStoreV1::beginRefresh($db, $provider, $scope['digest'], $now);
        $written = [];
        $freshOfferRefs = [];
        $carriedForward = 0;

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
                $freshOfferRefs[(string)$row['dto']['identity']['offer_ref_digest']] = true;
            }
            if ($partial) {
                $carriedForward = self::carryForwardLatestComplete(
                    $db,
                    $provider,
                    $scope['digest'],
                    $token,
                    $freshOfferRefs,
                    count($written),
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
            'scopeIndexed' => $scopeIndexed,
            'snapshotMode' => $partial ? 'partial_additive' : 'complete_replace',
            'refreshTokenDigest' => hash('sha256', $token),
            'offerCount' => count($written),
            'hotelCount' => count($hotels),
            'carriedForward' => $carriedForward,
            'expiredUnseen' => (int)($complete['expiredUnseen'] ?? 0),
            'selectionAuthority' => false,
        ];
    }

    /**
     * Copy only offers that were actually visible in the previous completed provider
     * cohort, are still unexpired, still have an accepted exact local identity, and
     * were not superseded by this partial batch. The previous completed rows remain
     * untouched while the new refresh is running, preserving v2 atomic visibility.
     *
     * Partial refreshes are additive, not an eviction mechanism. If the complete set
     * of fresh physical rows plus valid unseen carry rows would exceed MAX_OFFERS, the
     * new refresh aborts before any carry copy. The previous completed cohort therefore
     * stays authoritative instead of silently losing unseen offers or overflowing the
     * documented per-provider bound used by the bounded three-provider reader.
     */
    private static function carryForwardLatestComplete(
        PDO $db,
        string $provider,
        string $scopeDigest,
        string $token,
        array $freshOfferRefs,
        int $freshRowCount,
        DateTimeImmutable $now
    ): int {
        if ($freshRowCount < 1 || $freshRowCount > self::MAX_OFFERS) {
            throw new LogicException('ANYTOUR_OFFER_PARTIAL_FRESH_COUNT');
        }

        $state = $db->prepare(
            'SELECT active_refresh_token,latest_complete_refresh_token '
            . 'FROM anytour_offer_scope_state WHERE provider=:provider AND scope_sha256=:scope LIMIT 1'
        );
        $state->execute(['provider'=>$provider, 'scope'=>$scopeDigest]);
        $scopeState = $state->fetch(PDO::FETCH_ASSOC);
        if (!is_array($scopeState) || ($scopeState['active_refresh_token'] ?? null) !== $token) {
            throw new DomainException('ANYTOUR_OFFER_PARTIAL_REFRESH_NOT_ACTIVE');
        }
        $previous = $scopeState['latest_complete_refresh_token'] ?? null;
        if ($previous === null || $previous === '') return 0;
        if (!is_string($previous) || !preg_match('/\A[a-f0-9]{64}\z/D', $previous)) {
            throw new RuntimeException('ANYTOUR_OFFER_PARTIAL_PREVIOUS_REFRESH');
        }

        $nowSql = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $query = $db->prepare(
            'SELECT id,anytour_hotel_id,legacy_hotel_id,provider,provider_hotel_ref_digest,offer_ref_digest,'
            . 'payload_json,payload_sha256,last_seen_at,expires_at '
            . 'FROM anytour_offers WHERE provider=:provider AND scope_sha256=:scope '
            . 'AND last_refresh_token=:previous AND is_active=1 AND expires_at>:now '
            . 'ORDER BY last_seen_at DESC,id DESC LIMIT ' . (self::MAX_OFFERS + 1)
        );
        $query->execute([
            'provider'=>$provider,
            'scope'=>$scopeDigest,
            'previous'=>$previous,
            'now'=>$nowSql,
        ]);
        $candidates = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($candidates === []) return 0;
        // An oversized prior physical cohort already violates the bounded reader's
        // per-provider assumption. Do not truncate it into a seemingly valid partial.
        if (count($candidates) > self::MAX_OFFERS) {
            throw new DomainException('ANYTOUR_OFFER_PARTIAL_CAPACITY');
        }

        // Revalidate accepted provider->local identity at carry time. Rejected, pending,
        // reassigned, or otherwise unresolved supplier hotels are not copied forward.
        $valid = AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $candidates);
        $seenOfferRefs = [];
        $carryRows = [];
        foreach ($valid as $row) {
            $offerRef = $row['offer_ref_digest'] ?? null;
            if (!is_string($offerRef) || !preg_match('/\A[a-f0-9]{64}\z/D', $offerRef)) {
                throw new RuntimeException('ANYTOUR_OFFER_PARTIAL_IDENTITY_INTEGRITY');
            }
            // read-v2 exposes one row per provider+offer_ref; keep exactly that visible
            // winner and let an incoming copy supersede it when this batch observed it.
            if (isset($seenOfferRefs[$offerRef])) continue;
            $seenOfferRefs[$offerRef] = true;
            if (isset($freshOfferRefs[$offerRef])) continue;

            $payload = (string)($row['payload_json'] ?? '');
            $payloadSha = (string)($row['payload_sha256'] ?? '');
            if (!preg_match('/\A[a-f0-9]{64}\z/D', $payloadSha)
                || !hash_equals($payloadSha, hash('sha256', $payload))) {
                throw new RuntimeException('ANYTOUR_OFFER_PAYLOAD_INTEGRITY');
            }
            $carryRows[] = $row;
        }

        if ($freshRowCount + count($carryRows) > self::MAX_OFFERS) {
            throw new DomainException('ANYTOUR_OFFER_PARTIAL_CAPACITY');
        }
        if ($carryRows === []) return 0;

        $copy = $db->prepare(
            'INSERT INTO anytour_offers('
            . 'anytour_hotel_id,legacy_hotel_id,provider,scope_sha256,search_ref_digest,offer_ref_digest,'
            . 'provider_hotel_ref_digest,identity_sha256,operator_json,operator_sha256,checkin,nights,adults,children,'
            . 'child_ages_json,party_sha256,meal_json,room_json,placement_json,display_price,currency,final_price_ready,'
            . 'final_price_verified,payload_json,payload_sha256,observed_at,source_context_expires_at,last_refresh_token,'
            . 'last_seen_at,expires_at,is_active) '
            . 'SELECT old.anytour_hotel_id,old.legacy_hotel_id,old.provider,old.scope_sha256,old.search_ref_digest,'
            . 'old.offer_ref_digest,old.provider_hotel_ref_digest,old.identity_sha256,old.operator_json,old.operator_sha256,'
            . 'old.checkin,old.nights,old.adults,old.children,old.child_ages_json,old.party_sha256,old.meal_json,old.room_json,'
            . 'old.placement_json,old.display_price,old.currency,old.final_price_ready,old.final_price_verified,old.payload_json,'
            . 'old.payload_sha256,old.observed_at,old.source_context_expires_at,:token,old.last_seen_at,old.expires_at,1 '
            . 'FROM anytour_offers old WHERE old.id=:id AND old.provider=:provider AND old.scope_sha256=:scope '
            . 'AND old.last_refresh_token=:previous AND old.is_active=1 AND old.expires_at>:now LIMIT 1'
        );

        $carried = 0;
        foreach ($carryRows as $row) {
            $copy->execute([
                'token'=>$token,
                'id'=>(int)$row['id'],
                'provider'=>$provider,
                'scope'=>$scopeDigest,
                'previous'=>$previous,
                'now'=>$nowSql,
            ]);
            if ($copy->rowCount() !== 1) {
                throw new RuntimeException('ANYTOUR_OFFER_PARTIAL_CARRY');
            }
            ++$carried;
        }
        return $carried;
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
            $offerRef = is_array($identity) ? ($identity['offer_ref_digest'] ?? null) : null;
            if (!is_array($identity) || !is_string($offerRef)
                || !preg_match('/\A[a-f0-9]{64}\z/D', $offerRef)) {
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
