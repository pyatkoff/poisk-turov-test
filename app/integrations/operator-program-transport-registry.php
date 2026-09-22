<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Private passive registry: Andromeda program/tour reference -> stable transport facts.
 *
 * It never performs supplier I/O. It only learns from already returned quote/getFlights
 * projections. FUN&SUN and Intourist are intentionally symmetric. Missing/ambiguous
 * transport never blocks the existing operator+departure+destination fuel fallback.
 */
final class AnyTourOperatorProgramTransportRegistryV1
{
    private const PREFIX = 'operator-program-transport-v1-';
    private const MAX_BYTES = 131072;
    private const MAX_OBSERVATIONS = 32;
    private const TTL_SECONDS = 2592000; // 30 days; refresh on later real observations.

    public static function captureFromQuoteResult(
        string $directory,
        array $offer,
        array $searchParams,
        array $quoteResult,
        int $now,
        callable $write
    ): array {
        self::assertDirectory($directory);
        if ($now < 1) throw new InvalidArgumentException('PROGRAM_TRANSPORT_TIME');

        $identity = self::identity($offer);
        if ($identity === null) return self::receipt(false, 'identity_missing');
        $family = self::operatorFamily($offer['operator'] ?? null);
        if ($family === null) return self::receipt(false, 'operator_not_target');
        $scope = self::scope($searchParams);
        $transport = self::transport($quoteResult['flights'] ?? null);
        if ($transport === null) return self::receipt(false, 'transport_ambiguous');

        $observation = [
            'schema_version' => 1,
            'operator_family' => $family,
            'scope' => $scope,
            'identity' => $identity,
            'transport' => $transport,
            'source' => 'andromeda_quote_flights',
            'observed_at' => $now,
            'expires_at' => $now + self::TTL_SECONDS,
            'evidence_sha256' => self::hash([
                'flights' => $quoteResult['flights'],
                'identity' => $identity,
                'scope' => $scope,
                'operator_family' => $family,
            ]),
        ];

        $digest = self::keyDigest($family, $scope, $identity);
        $path = self::path($directory, $digest);
        $lockPath = $path . '.lock';
        if (is_link($lockPath)) throw new RuntimeException('PROGRAM_TRANSPORT_LOCK_INVALID');
        $lock = fopen($lockPath, 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('PROGRAM_TRANSPORT_LOCK_FAILED');
        try {
            $current = self::readEnvelope($path, true);
            $rows = [];
            if ($current !== null) {
                self::assertEnvelope($current, $digest);
                $rows = $current['observations'];
            }

            foreach ($rows as $row) {
                if (($row['evidence_sha256'] ?? null) === $observation['evidence_sha256']) {
                    if ($row !== $observation) throw new DomainException('PROGRAM_TRANSPORT_CONFLICT');
                    $resolved = self::resolveRows($rows, $now);
                    return self::receipt(true, 'unchanged', $resolved, $path, count($rows));
                }
            }

            $rows[] = $observation;
            usort($rows, static fn(array $a, array $b): int =>
                [$a['observed_at'], $a['evidence_sha256']] <=> [$b['observed_at'], $b['evidence_sha256']]
            );
            if (count($rows) > self::MAX_OBSERVATIONS) {
                $rows = array_slice($rows, -self::MAX_OBSERVATIONS);
            }
            $resolved = self::resolveRows($rows, $now);
            $next = [
                'version' => 1,
                'key_sha256' => $digest,
                'operator_family' => $family,
                'scope' => $scope,
                'identity' => $identity,
                'state' => $resolved === null ? 'conflict' : 'resolved',
                'resolved_transport' => $resolved,
                'observations' => $rows,
            ];
            $encoded = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($encoded) > self::MAX_BYTES) throw new DomainException('PROGRAM_TRANSPORT_SIZE');
            if ($write($path, $next) !== true) throw new RuntimeException('PROGRAM_TRANSPORT_WRITE');
            $read = self::readEnvelope($path, false);
            self::assertEnvelope($read, $digest);
            if ($read !== $next) throw new RuntimeException('PROGRAM_TRANSPORT_READBACK');
            return self::receipt(true, $current === null ? 'created' : 'appended', $resolved, $path, count($rows));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Read a usable learned override. Null means: keep the old direction fallback.
     */
    public static function lookup(
        string $directory,
        array $offer,
        array $searchParams,
        int $now
    ): ?array {
        try {
            self::assertDirectory($directory);
            if ($now < 1) return null;
            $identity = self::identity($offer);
            $family = self::operatorFamily($offer['operator'] ?? null);
            if ($identity === null || $family === null) return null;
            $scope = self::scope($searchParams);
            $digest = self::keyDigest($family, $scope, $identity);
            $envelope = self::readEnvelope(self::path($directory, $digest), true);
            if ($envelope === null) return null;
            self::assertEnvelope($envelope, $digest);
            $resolved = self::resolveRows($envelope['observations'], $now);
            if ($resolved === null) return null;
            $expires = PHP_INT_MAX;
            $evidence = [];
            foreach ($envelope['observations'] as $row) {
                if (($row['observed_at'] ?? PHP_INT_MAX) > $now || ($row['expires_at'] ?? 0) <= $now) continue;
                $expires = min($expires, $row['expires_at']);
                $evidence[$row['evidence_sha256']] = true;
            }
            return [
                'schema_version' => 1,
                'operator_family' => $family,
                'scope' => $scope,
                'identity' => $identity,
                'transport' => $resolved,
                'evidence_count' => count($evidence),
                'expires_at' => $expires,
                'source' => 'learned_program_transport',
            ];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    private static function operatorFamily(mixed $raw): ?string
    {
        $family = AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($raw);
        return in_array($family, ['fun_and_sun', 'intourist'], true) ? $family : null;
    }

    /**
     * programKey is primary because it is reusable across sibling offers.
     * tourKey is a fallback when a program reference is absent.
     */
    private static function identity(array $offer): ?array
    {
        $context = $offer['transport_context'] ?? null;
        if (!is_array($context) || array_is_list($context)) return null;
        foreach ([['program_ref','program'], ['tour_ref','tour']] as [$field, $kind]) {
            $value = $context[$field] ?? null;
            if (is_int($value) && $value > 0) $value = (string)$value;
            if (is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $value) === 1) {
                return ['kind' => $kind, 'ref' => $value];
            }
        }
        return null;
    }

    private static function scope(array $params): array
    {
        $departure = $params['departureId'] ?? null;
        $country = $params['countryId'] ?? null;
        if (is_int($departure) && $departure > 0) $departure = (string)$departure;
        if (is_int($country) && $country > 0) $country = (string)$country;
        if (!is_string($departure) || preg_match('/\A[1-9][0-9]{0,12}\z/D', $departure) !== 1
            || !is_string($country) || preg_match('/\A[1-9][0-9]{0,12}\z/D', $country) !== 1) {
            throw new InvalidArgumentException('PROGRAM_TRANSPORT_SCOPE');
        }
        return ['departure_id' => $departure, 'country_id' => $country];
    }

    /**
     * Resolve a stable carrier per direction. A flight number is retained only when
     * every returned option in that direction agrees. Multiple carriers => ambiguous.
     */
    private static function transport(mixed $flights): ?array
    {
        if (!is_array($flights) || !array_is_list($flights) || $flights === []) return null;
        $rows = ['0' => [], '1' => []];
        foreach ($flights as $flight) {
            if (!is_array($flight)) continue;
            $direction = (string)($flight['direction'] ?? '');
            if (!isset($rows[$direction])) continue;
            $details = $flight['flight_details'] ?? null;
            if (!is_array($details) || array_is_list($details)) continue;
            $carrier = self::firstLabel([
                $details['airline_code'] ?? null,
                $details['airline_name'] ?? null,
            ]);
            if ($carrier === null) continue;
            $rows[$direction][] = [
                'carrier' => $carrier,
                'carrier_name' => self::labelOrNull($details['airline_name'] ?? null, 120),
                'flight_number' => self::labelOrNull($details['flight_number'] ?? null, 32),
                'origin' => self::labelOrNull($details['departure_airport_code'] ?? null, 8),
                'destination' => self::labelOrNull($details['arrival_airport_code'] ?? null, 8),
            ];
        }
        $out = [];
        foreach (['0' => 'outbound', '1' => 'return'] as $direction => $name) {
            if ($rows[$direction] === []) return null;
            $carriers = [];
            foreach ($rows[$direction] as $row) $carriers[$row['carrier']] = true;
            if (count($carriers) !== 1) return null;
            $carrier = array_key_first($carriers);
            $out[$name] = [
                'carrier' => $carrier,
                'carrier_name' => self::singleValue($rows[$direction], 'carrier_name'),
                'flight_number' => self::singleValue($rows[$direction], 'flight_number'),
                'origin' => self::singleValue($rows[$direction], 'origin'),
                'destination' => self::singleValue($rows[$direction], 'destination'),
            ];
        }
        return $out;
    }

    private static function resolveRows(array $rows, int $now): ?array
    {
        $active = array_values(array_filter($rows, static fn(array $row): bool =>
            is_int($row['observed_at'] ?? null) && is_int($row['expires_at'] ?? null)
            && $row['observed_at'] <= $now && $row['expires_at'] > $now
        ));
        if ($active === []) return null;

        $resolved = [];
        foreach (['outbound','return'] as $direction) {
            $carriers = [];
            foreach ($active as $row) {
                $carrier = $row['transport'][$direction]['carrier'] ?? null;
                if (!is_string($carrier) || $carrier === '') return null;
                $carriers[$carrier] = true;
            }
            if (count($carriers) !== 1) return null;
            $carrier = array_key_first($carriers);
            $resolved[$direction] = [
                'carrier' => $carrier,
                'carrier_name' => self::sameAcross($active, $direction, 'carrier_name'),
                'flight_number' => self::sameAcross($active, $direction, 'flight_number'),
                'origin' => self::sameAcross($active, $direction, 'origin'),
                'destination' => self::sameAcross($active, $direction, 'destination'),
            ];
        }
        return $resolved;
    }

    private static function sameAcross(array $rows, string $direction, string $field): ?string
    {
        $values = [];
        foreach ($rows as $row) {
            $value = $row['transport'][$direction][$field] ?? null;
            if ($value !== null) $values[$value] = true;
        }
        return count($values) === 1 ? array_key_first($values) : null;
    }

    private static function singleValue(array $rows, string $field): ?string
    {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$field] ?? null;
            if ($value !== null) $values[$value] = true;
        }
        return count($values) === 1 ? array_key_first($values) : null;
    }

    private static function firstLabel(array $values): ?string
    {
        foreach ($values as $value) {
            $label = self::labelOrNull($value, 120);
            if ($label !== null) return $label;
        }
        return null;
    }

    private static function labelOrNull(mixed $value, int $max): ?string
    {
        if (!is_string($value) || $value === '' || trim($value) !== $value || strlen($value) > $max
            || preg_match('/[\x00-\x1F\x7F]/', $value)) return null;
        return $value;
    }

    private static function keyDigest(string $family, array $scope, array $identity): string
    {
        return self::hash(['operator_family'=>$family,'scope'=>$scope,'identity'=>$identity]);
    }

    private static function path(string $directory, string $digest): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new InvalidArgumentException('PROGRAM_TRANSPORT_DIGEST');
        }
        return rtrim($directory, '/') . '/' . self::PREFIX . $digest . '.json';
    }

    private static function assertDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory) || basename(rtrim($directory, '/')) !== 'searches') {
            throw new InvalidArgumentException('PROGRAM_TRANSPORT_ROOT');
        }
    }

    private static function readEnvelope(string $path, bool $optional): ?array
    {
        if (is_link($path)) throw new DomainException('PROGRAM_TRANSPORT_INVALID');
        if (!file_exists($path)) return $optional ? null : throw new DomainException('PROGRAM_TRANSPORT_INVALID');
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > self::MAX_BYTES) throw new DomainException('PROGRAM_TRANSPORT_INVALID');
        $value = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : throw new DomainException('PROGRAM_TRANSPORT_INVALID');
    }

    private static function assertEnvelope(array $value, string $digest): void
    {
        if (($value['version'] ?? null) !== 1 || ($value['key_sha256'] ?? null) !== $digest
            || !in_array($value['operator_family'] ?? null, ['fun_and_sun','intourist'], true)
            || !is_array($value['scope'] ?? null) || !is_array($value['identity'] ?? null)
            || !in_array($value['state'] ?? null, ['resolved','conflict'], true)
            || !is_array($value['observations'] ?? null) || !array_is_list($value['observations'])
            || count($value['observations']) > self::MAX_OBSERVATIONS) {
            throw new DomainException('PROGRAM_TRANSPORT_INVALID');
        }
        foreach ($value['observations'] as $row) {
            if (!is_array($row) || ($row['schema_version'] ?? null) !== 1
                || ($row['operator_family'] ?? null) !== $value['operator_family']
                || ($row['scope'] ?? null) !== $value['scope']
                || ($row['identity'] ?? null) !== $value['identity']
                || !is_array($row['transport'] ?? null)
                || !is_string($row['evidence_sha256'] ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/D', $row['evidence_sha256']) !== 1
                || !is_int($row['observed_at'] ?? null) || !is_int($row['expires_at'] ?? null)
                || $row['expires_at'] <= $row['observed_at']) {
                throw new DomainException('PROGRAM_TRANSPORT_INVALID');
            }
        }
    }

    private static function hash(mixed $value): string
    {
        $canonical = static function(mixed $v) use (&$canonical): mixed {
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v, SORT_STRING);
            foreach ($v as $k => $item) $v[$k] = $canonical($item);
            return $v;
        };
        return hash('sha256', json_encode(
            $canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    private static function receipt(bool $captured, string $status, ?array $transport = null,
        ?string $path = null, int $count = 0): array
    {
        return [
            'captured' => $captured,
            'status' => $status,
            'usable_override' => $transport !== null,
            'transport' => $transport,
            'path' => $path,
            'observationCount' => $count,
            'fallback' => 'operator_departure_destination',
        ];
    }
}
