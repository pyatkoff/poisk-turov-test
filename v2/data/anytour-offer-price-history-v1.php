<?php
/**
 * Append-only canonical offer price history preparation/writer.
 *
 * This module has no supplier I/O and does not alter current-offer authority.
 * Runtime wiring is intentionally separate from the storage contract.
 */
declare(strict_types=1);

require_once __DIR__ . '/anytour-search-scope-v1.php';

final class AnyTourOfferPriceHistoryV1
{
    private const PROVIDERS = ['tourvisor' => true, 'anex' => true, 'andromeda' => true];
    private const MAX_ROWS = 5000;

    public static function installed(PDO $db): bool
    {
        if ($db->inTransaction()) throw new LogicException('ANYTOUR_OFFER_PRICE_HISTORY_CALLER_TRANSACTION');
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return false;
        $stmt = $db->query(
            "SELECT 1 FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name='anytour_offer_price_observations'
             LIMIT 1"
        );
        return $stmt->fetchColumn() !== false;
    }

    public static function consumerSegmentDigest(array $searchParams, int $anytourHotelId, array $dto): string
    {
        if ($anytourHotelId < 1) throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_HOTEL');
        $scope = AnyTourSearchScopeV1::fromParams($searchParams);
        $provider = self::provider($dto['provider'] ?? null);
        $tour = self::tour($dto['tour'] ?? null);
        $currency = self::currency($dto['currency'] ?? null);
        // Validate provider even though it is intentionally excluded from the digest.
        if ($provider === '') throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_PROVIDER');

        $meal = self::mealIdentity($tour['meal']);
        $room = self::roomIdentity($tour['room']);
        $ages = $tour['party']['child_ages'];
        sort($ages, SORT_NUMERIC);

        $identity = [
            'version' => 1,
            'departure_id' => (int)$scope['params']['departureId'],
            'country_id' => (int)$scope['params']['countryId'],
            'anytour_hotel_id' => $anytourHotelId,
            'checkin' => $tour['checkin'],
            'nights' => $tour['nights'],
            'adults' => $tour['party']['adults'],
            'children' => $tour['party']['children'],
            'child_ages' => $ages,
            'meal' => $meal,
            'room' => $room,
            'currency' => $currency,
        ];
        return hash('sha256', self::json($identity));
    }

    public static function prepareObservation(
        array $searchParams,
        int $anytourHotelId,
        array $dto,
        DateTimeImmutable $recordedAt
    ): array {
        if ($anytourHotelId < 1) throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_HOTEL');
        $scope = AnyTourSearchScopeV1::fromParams($searchParams);
        $provider = self::provider($dto['provider'] ?? null);
        $providerLocalHotelId = self::positiveInt($dto['local_hotel_id'] ?? null, 'ANYTOUR_OFFER_PRICE_HISTORY_LOCAL_HOTEL');
        $identity = $dto['identity'] ?? null;
        if (!is_array($identity)) throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_IDENTITY');
        $searchRef = self::digest($identity['search_ref_digest'] ?? null, 'ANYTOUR_OFFER_PRICE_HISTORY_SEARCH_REF');
        $offerRef = self::digest($identity['offer_ref_digest'] ?? null, 'ANYTOUR_OFFER_PRICE_HISTORY_OFFER_REF');
        $providerHotelRef = self::digest($identity['provider_hotel_ref_digest'] ?? null, 'ANYTOUR_OFFER_PRICE_HISTORY_PROVIDER_HOTEL_REF');

        $tour = self::tour($dto['tour'] ?? null);
        $operator = $dto['operator'] ?? null;
        if (!is_array($operator)) throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_OPERATOR');
        $operatorJson = self::json($operator);
        $mealJson = self::json($tour['meal']);
        $roomJson = self::json($tour['room']);

        $price = self::money($dto['price'] ?? null);
        $currency = self::currency($dto['currency'] ?? null);
        $ready = $dto['finalPriceReady'] ?? null;
        $verified = $dto['final_price_verified'] ?? null;
        if (!is_bool($ready) || !is_bool($verified) || ($verified && !$ready)) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_READINESS');
        }

        $observedAt = self::utc($tour['observed_at'] ?? null, 'ANYTOUR_OFFER_PRICE_HISTORY_OBSERVED');
        $consumer = self::consumerSegmentDigest($searchParams, $anytourHotelId, $dto);
        $recordedSql = $recordedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $agesJson = self::json($tour['party']['child_ages']);

        $observation = [
            'provider' => $provider,
            'anytour_hotel_id' => $anytourHotelId,
            'provider_local_hotel_id' => $providerLocalHotelId,
            'scope_sha256' => $scope['digest'],
            'search_ref_digest' => $searchRef,
            'offer_ref_digest' => $offerRef,
            'provider_hotel_ref_digest' => $providerHotelRef,
            'consumer_segment_sha256' => $consumer,
            'departure_id' => (int)$scope['params']['departureId'],
            'country_id' => (int)$scope['params']['countryId'],
            'checkin' => $tour['checkin'],
            'nights' => $tour['nights'],
            'adults' => $tour['party']['adults'],
            'children' => $tour['party']['children'],
            'child_ages_json' => $agesJson,
            'operator_json' => $operatorJson,
            'operator_sha256' => hash('sha256', $operatorJson),
            'meal_json' => $mealJson,
            'meal_sha256' => hash('sha256', $mealJson),
            'room_json' => $roomJson,
            'room_sha256' => hash('sha256', $roomJson),
            'display_price' => $price,
            'currency' => $currency,
            'final_price_ready' => $ready ? 1 : 0,
            'final_price_verified' => $verified ? 1 : 0,
            'observed_at' => $observedAt->format('Y-m-d H:i:s'),
            'recorded_at' => $recordedSql,
        ];
        $observation['observation_sha256'] = hash('sha256', self::json([
            'provider' => $provider,
            'anytour_hotel_id' => $anytourHotelId,
            'offer_ref_digest' => $offerRef,
            'consumer_segment_sha256' => $consumer,
            'display_price' => $price,
            'currency' => $currency,
            'final_price_ready' => $observation['final_price_ready'],
            'final_price_verified' => $observation['final_price_verified'],
            'observed_at' => $observation['observed_at'],
        ]));

        return $observation;
    }

    public static function recordIfInstalled(
        PDO $db,
        array $searchParams,
        array $rows,
        DateTimeImmutable $recordedAt
    ): array {
        if (!array_is_list($rows) || count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_ROWS');
        }
        if (!self::installed($db)) {
            return [
                'source' => 'anytour-offer-price-history-v1',
                'installed' => false,
                'inputCount' => count($rows),
                'written' => 0,
                'duplicates' => 0,
            ];
        }

        $prepared = [];
        foreach ($rows as $row) {
            if (!is_array($row)
                || !is_int($row['anytour_hotel_id'] ?? null)
                || !is_array($row['dto'] ?? null)) {
                throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_ROW');
            }
            $prepared[] = self::prepareObservation(
                $searchParams,
                $row['anytour_hotel_id'],
                $row['dto'],
                $recordedAt
            );
        }

        $sql = "INSERT IGNORE INTO anytour_offer_price_observations (
            observation_sha256,provider,anytour_hotel_id,provider_local_hotel_id,scope_sha256,
            search_ref_digest,offer_ref_digest,provider_hotel_ref_digest,consumer_segment_sha256,
            departure_id,country_id,checkin,nights,adults,children,child_ages_json,
            operator_json,operator_sha256,meal_json,meal_sha256,room_json,room_sha256,
            display_price,currency,final_price_ready,final_price_verified,observed_at,recorded_at
        ) VALUES (
            :observation_sha256,:provider,:anytour_hotel_id,:provider_local_hotel_id,:scope_sha256,
            :search_ref_digest,:offer_ref_digest,:provider_hotel_ref_digest,:consumer_segment_sha256,
            :departure_id,:country_id,:checkin,:nights,:adults,:children,:child_ages_json,
            :operator_json,:operator_sha256,:meal_json,:meal_sha256,:room_json,:room_sha256,
            :display_price,:currency,:final_price_ready,:final_price_verified,:observed_at,:recorded_at
        )";
        $stmt = $db->prepare($sql);
        $written = 0;
        try {
            $db->beginTransaction();
            foreach ($prepared as $observation) {
                $stmt->execute($observation);
                if ($stmt->rowCount() === 1) ++$written;
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        return [
            'source' => 'anytour-offer-price-history-v1',
            'installed' => true,
            'inputCount' => count($prepared),
            'written' => $written,
            'duplicates' => count($prepared) - $written,
        ];
    }

    private static function tour(mixed $tour): array
    {
        if (!is_array($tour)) throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_TOUR');
        $checkin = $tour['checkin'] ?? null;
        $date = is_string($checkin)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $checkin, new DateTimeZone('UTC'))
            : false;
        if (!$date || $date->format('Y-m-d') !== $checkin) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_CHECKIN');
        }
        $nights = $tour['nights'] ?? null;
        if (!is_int($nights) || $nights < 1 || $nights > 60) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_NIGHTS');
        }
        $party = $tour['party'] ?? null;
        if (!is_array($party)
            || !is_int($party['adults'] ?? null) || $party['adults'] < 1 || $party['adults'] > 9
            || !is_int($party['children'] ?? null) || $party['children'] < 0 || $party['children'] > 9
            || !is_array($party['child_ages'] ?? null)
            || count($party['child_ages']) !== $party['children']) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_PARTY');
        }
        foreach ($party['child_ages'] as $age) {
            if (!is_int($age) || $age < 0 || $age > 17) {
                throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_PARTY');
            }
        }
        if (!is_array($tour['meal'] ?? null) || !is_array($tour['room'] ?? null)) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_STAY');
        }
        self::mealIdentity($tour['meal']);
        self::roomIdentity($tour['room']);
        self::utc($tour['observed_at'] ?? null, 'ANYTOUR_OFFER_PRICE_HISTORY_OBSERVED');
        return $tour;
    }

    private static function mealIdentity(array $meal): array
    {
        $raw = trim((string)($meal['raw'] ?? ''));
        $family = $meal['family'] ?? null;
        $verified = $meal['family_verified'] ?? null;
        $qualifiers = $meal['qualifiers'] ?? null;
        if ($raw === '' || !is_bool($verified) || !is_array($qualifiers)
            || !is_bool($qualifiers['plus'] ?? null)
            || !is_bool($qualifiers['without_alcohol'] ?? null)
            || ($family !== null && !is_string($family))
            || ($verified && ($family === null || trim($family) === ''))
            || (!$verified && $family !== null)) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_MEAL');
        }
        return [
            'family' => $verified ? strtolower(trim((string)$family)) : null,
            'raw' => $verified ? null : mb_strtolower($raw, 'UTF-8'),
            'plus' => $qualifiers['plus'],
            'without_alcohol' => $qualifiers['without_alcohol'],
        ];
    }

    private static function roomIdentity(array $room): array
    {
        $raw = trim((string)($room['raw'] ?? ''));
        $normalized = trim((string)($room['normalized'] ?? ''));
        if ($raw === '' || $normalized === '') {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_ROOM');
        }
        return ['normalized' => mb_strtolower($normalized, 'UTF-8')];
    }

    private static function provider(mixed $value): string
    {
        if (!is_string($value) || !isset(self::PROVIDERS[$value])) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_PROVIDER');
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $error): int
    {
        if (!is_int($value) || $value < 1) throw new InvalidArgumentException($error);
        return $value;
    }

    private static function digest(mixed $value, string $error): string
    {
        if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/D', $value)) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function money(mixed $value): string
    {
        if (!is_string($value)
            || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)
            || !preg_match('/[1-9]/', $value)) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_PRICE');
        }
        $parts = explode('.', $value, 2);
        $fraction = rtrim($parts[1] ?? '', '0');
        return $parts[0] . ($fraction === '' ? '' : '.' . $fraction);
    }

    private static function currency(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[A-Z]{3}\z/D', $value)) {
            throw new InvalidArgumentException('ANYTOUR_OFFER_PRICE_HISTORY_CURRENCY');
        }
        return $value;
    }

    private static function utc(mixed $value, string $error): DateTimeImmutable
    {
        $date = is_string($value)
            ? DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new DateTimeZone('UTC'))
            : false;
        if (!$date || $date->format('Y-m-d\\TH:i:s\\Z') !== $value) {
            throw new InvalidArgumentException($error);
        }
        return $date;
    }

    private static function json(mixed $value): string
    {
        return json_encode(
            self::canonical($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
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
