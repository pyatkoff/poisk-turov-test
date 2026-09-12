<?php
declare(strict_types=1);

require_once __DIR__ . '/three-provider-search-coverage.php';
require_once __DIR__ . '/three-provider-search-window.php';
require_once __DIR__ . '/three-provider-meal-family.php';
require_once __DIR__ . '/three-provider-room-placement.php';
require_once __DIR__ . '/three-provider-availability.php';
require_once __DIR__ . '/three-provider-money-facts.php';

/**
 * Provider-neutral P1 observation evidence.
 *
 * This is an immutable source-side envelope for one already observed supplier offer.
 * It performs no supplier I/O, persistence, hotel matching or mapping writes. Opaque
 * supplier hotel IDs remain provider-scoped strings; nullable local identity is only
 * a statement about the caller's current accepted resolver state.
 */
final class AnyTourThreeProviderSearchObservation
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const IDENTITY_STATES = ['current_accepted', 'unmapped'];

    public static function build(array $input): array
    {
        self::exactKeys($input, [
            'provider', 'operator_raw', 'external_hotel_id', 'local_hotel_id',
            'local_identity_state', 'hotel', 'criteria', 'offer', 'money',
            'coverage_evidence', 'lineage', 'observed_at',
        ], 'THREE_PROVIDER_OBSERVATION_KEYS');

        $provider = $input['provider'];
        if (!is_string($provider) || !in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_PROVIDER');
        }
        $operator = self::nullableLabel($input['operator_raw'], 120, 'THREE_PROVIDER_OBSERVATION_OPERATOR');
        $externalHotelId = self::opaqueId($input['external_hotel_id'], 'THREE_PROVIDER_OBSERVATION_EXTERNAL_ID');
        [$localHotelId, $identityState] = self::identity($input['local_hotel_id'], $input['local_identity_state']);

        $hotel = self::hotel($input['hotel']);
        $criteria = self::criteria($input['criteria']);
        $offer = self::offer($provider, $input['offer']);
        $money = self::money($provider, $input['money']);
        $coverage = AnyTourThreeProviderSearchCoverage::fromEvidence($provider, $input['coverage_evidence']);
        if (($coverage['observation_usable'] ?? null) !== true) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_COVERAGE');
        }
        $lineage = self::lineage($input['lineage']);
        $observedAt = self::utcTimestamp($input['observed_at']);

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'operator' => [
                'raw' => $operator,
                'cross_provider_equivalence_verified' => false,
            ],
            'hotel' => [
                'external_id' => $externalHotelId,
                'external_id_scope' => $provider,
                'external_id_universal' => false,
                'local_hotel_id' => $localHotelId,
                'local_identity_state' => $identityState,
                'name' => $hotel['name'],
                'country' => $hotel['country'],
                'geography' => $hotel['geography'],
                'stars_raw' => $hotel['stars_raw'],
                'coordinates' => $hotel['coordinates'],
                'supplier_labels_identity_proof' => false,
                'coordinates_identity_proof' => false,
            ],
            'criteria' => $criteria,
            'offer' => $offer,
            'money' => $money,
            'coverage' => $coverage,
            'lineage' => $lineage,
            'observed_at' => $observedAt,
            'matching_handoff_state' => $localHotelId === null ? 'evidence_only' : 'not_required',
            'mapping_write_allowed' => false,
            'hotel_identity_decision_allowed' => false,
            'package_identity_verified' => false,
            'cross_provider_price_equivalence_verified' => false,
        ];
    }

    private static function identity($localHotelId, $state): array
    {
        if (!is_string($state) || !in_array($state, self::IDENTITY_STATES, true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_IDENTITY');
        }
        if ($state === 'unmapped') {
            if ($localHotelId !== null) throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_IDENTITY');
            return [null, 'unmapped'];
        }
        if (!is_int($localHotelId) || $localHotelId < 1) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_IDENTITY');
        }
        return [$localHotelId, 'current_accepted'];
    }

    private static function hotel(array $hotel): array
    {
        self::exactKeys($hotel, ['name', 'country', 'geography', 'stars_raw', 'coordinates'], 'THREE_PROVIDER_OBSERVATION_HOTEL');
        $name = self::nullableLabel($hotel['name'], 300, 'THREE_PROVIDER_OBSERVATION_HOTEL_NAME');

        if (!is_array($hotel['country'])) throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_COUNTRY');
        self::exactKeys($hotel['country'], ['supplier_id', 'supplier_name'], 'THREE_PROVIDER_OBSERVATION_COUNTRY');
        $country = [
            'supplier_id' => $hotel['country']['supplier_id'] === null
                ? null : self::opaqueId($hotel['country']['supplier_id'], 'THREE_PROVIDER_OBSERVATION_COUNTRY'),
            'supplier_name' => self::nullableLabel($hotel['country']['supplier_name'], 160, 'THREE_PROVIDER_OBSERVATION_COUNTRY'),
            'supplier_id_universal' => false,
        ];

        if (!is_array($hotel['geography'])) throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_GEOGRAPHY');
        self::exactKeys($hotel['geography'], ['region', 'resort', 'subregion'], 'THREE_PROVIDER_OBSERVATION_GEOGRAPHY');
        $geography = [
            'region' => self::nullableLabel($hotel['geography']['region'], 180, 'THREE_PROVIDER_OBSERVATION_GEOGRAPHY'),
            'resort' => self::nullableLabel($hotel['geography']['resort'], 180, 'THREE_PROVIDER_OBSERVATION_GEOGRAPHY'),
            'subregion' => self::nullableLabel($hotel['geography']['subregion'], 180, 'THREE_PROVIDER_OBSERVATION_GEOGRAPHY'),
            'cross_provider_equivalence_verified' => false,
        ];

        $stars = self::nullableLabel($hotel['stars_raw'], 80, 'THREE_PROVIDER_OBSERVATION_STARS');
        $coordinates = self::coordinates($hotel['coordinates']);
        return [
            'name' => $name,
            'country' => $country,
            'geography' => $geography,
            'stars_raw' => $stars,
            'coordinates' => $coordinates,
        ];
    }

    private static function criteria(array $criteria): array
    {
        self::exactKeys($criteria, [
            'window', 'departure_local_id', 'country_local_id', 'meal_label'
        ], 'THREE_PROVIDER_OBSERVATION_CRITERIA');
        if (!is_array($criteria['window'])) throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_CRITERIA');
        $window = AnyTourThreeProviderSearchWindow::fromSearch($criteria['window']);
        $departure = self::positiveInt($criteria['departure_local_id'], 'THREE_PROVIDER_OBSERVATION_DEPARTURE');
        $country = self::positiveInt($criteria['country_local_id'], 'THREE_PROVIDER_OBSERVATION_COUNTRY');
        $meal = AnyTourThreeProviderMealFamily::normalize($criteria['meal_label']);
        return [
            'window' => $window,
            'departure_local_id' => $departure,
            'country_local_id' => $country,
            'meal' => $meal,
            'provider_filter_equivalence_verified' => false,
        ];
    }

    private static function offer(string $provider, array $offer): array
    {
        self::exactKeys($offer, [
            'meal_label', 'room_label', 'placement_label', 'availability'
        ], 'THREE_PROVIDER_OBSERVATION_OFFER');
        if (!is_array($offer['availability'])) throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_OFFER');
        return [
            'meal' => AnyTourThreeProviderMealFamily::normalize($offer['meal_label']),
            'room_placement' => AnyTourThreeProviderRoomPlacement::normalize(
                $provider,
                $offer['room_label'],
                $offer['placement_label']
            ),
            'availability' => AnyTourThreeProviderAvailability::fromSearch($provider, $offer['availability']),
        ];
    }

    private static function money(string $provider, array $money): array
    {
        self::exactKeys($money, [
            'search_price', 'fuel_charge_reported', 'additional_prices_reported'
        ], 'THREE_PROVIDER_OBSERVATION_MONEY');
        if (!is_array($money['search_price'])
            || ($money['fuel_charge_reported'] !== null && !is_array($money['fuel_charge_reported']))
            || !is_array($money['additional_prices_reported'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_MONEY');
        }
        return AnyTourThreeProviderMoneyFacts::fromSearch(
            $provider,
            $money['search_price'],
            $money['fuel_charge_reported'],
            $money['additional_prices_reported']
        );
    }

    private static function lineage(array $lineage): array
    {
        self::exactKeys($lineage, [
            'operation_id', 'scenario_revision', 'criteria_digest', 'source_sha', 'checkpoint_path'
        ], 'THREE_PROVIDER_OBSERVATION_LINEAGE');
        $operation = $lineage['operation_id'];
        if (!is_string($operation) || preg_match('/\A[a-z0-9][a-z0-9._:-]{5,127}\z/D', $operation) !== 1) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_OPERATION');
        }
        $revision = $lineage['scenario_revision'];
        if (!is_int($revision) || $revision < 1 || $revision > 10000) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_REVISION');
        }
        if (!is_string($lineage['criteria_digest']) || preg_match('/\A[a-f0-9]{64}\z/D', $lineage['criteria_digest']) !== 1) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_DIGEST');
        }
        if (!is_string($lineage['source_sha']) || preg_match('/\A[a-f0-9]{40}\z/D', $lineage['source_sha']) !== 1) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_SOURCE');
        }
        $checkpoint = $lineage['checkpoint_path'];
        if (!is_string($checkpoint) || $checkpoint === '' || strlen($checkpoint) > 240
            || str_starts_with($checkpoint, '/') || str_contains($checkpoint, '..')
            || str_contains($checkpoint, '\\') || preg_match('/[\x00-\x1f]/', $checkpoint) === 1) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_CHECKPOINT');
        }
        return [
            'operation_id' => $operation,
            'scenario_revision' => $revision,
            'criteria_digest' => $lineage['criteria_digest'],
            'source_sha' => $lineage['source_sha'],
            'checkpoint_path' => $checkpoint,
            'replay_authority' => false,
        ];
    }

    private static function coordinates($value): ?array
    {
        if ($value === null) return null;
        if (!is_array($value)) throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_COORDINATES');
        self::exactKeys($value, ['lat', 'lon'], 'THREE_PROVIDER_OBSERVATION_COORDINATES');
        foreach (['lat', 'lon'] as $key) {
            if (!is_int($value[$key]) && !is_float($value[$key])) {
                throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_COORDINATES');
            }
            if (!is_finite((float)$value[$key])) throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_COORDINATES');
        }
        $lat = (float)$value['lat'];
        $lon = (float)$value['lon'];
        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_COORDINATES');
        }
        return ['lat' => $lat, 'lon' => $lon];
    }

    private static function utcTimestamp($value): string
    {
        if (!is_string($value)
            || preg_match('/\A20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D', $value) !== 1) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_TIME');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d\\TH:i:s\\Z') !== $value) {
            throw new InvalidArgumentException('THREE_PROVIDER_OBSERVATION_TIME');
        }
        return $value;
    }

    private static function nullableLabel($value, int $max, string $error): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException($error);
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($value === '' || $length > $max || preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function opaqueId($value, string $error): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 128
            || preg_match('/\A[\x21-\x7e]+\z/D', $value) !== 1) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function positiveInt($value, string $error): int
    {
        if (!is_int($value) || $value < 1) throw new InvalidArgumentException($error);
        return $value;
    }

    private static function exactKeys(array $value, array $expected, string $error): void
    {
        if (count($value) !== count($expected)
            || array_diff($expected, array_keys($value)) !== []
            || array_diff(array_keys($value), $expected) !== []) {
            throw new InvalidArgumentException($error);
        }
    }
}
