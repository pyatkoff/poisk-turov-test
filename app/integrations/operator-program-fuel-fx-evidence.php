<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-program-fuel-registry.php';
require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Read-only FX evidence selector for validated INT supplier evidence.
 *
 * This helper deliberately does not expose or infer any fuel rate. It reuses either
 * fresh EUR->RUB exchange evidence already retained on validated Andromeda program
 * observations or a direction-scoped FX-only receipt derived from terminal probes.
 * Neither source can create a fuel amount, relation or final verification.
 */
final class AnyTourOperatorProgramFuelFxEvidenceV1
{
    private const PREFIX = 'operator-program-fuel-v1-';
    private const DIRECTION_PREFIX = 'operator-direction-fx-v1-';
    private const MAX_BYTES = 131072;
    private const MAX_FILES = 2048;
    private const MAX_OBSERVATIONS = 32;

    public static function latestForDirection(
        string $directory,
        string $operator,
        array $direction,
        int $now
    ): ?array {
        try {
            self::assertDirectory($directory);
            if ($now < 1) return null;
            $canonicalDirection = AnyTourOperatorFuelRuleEvidenceV1::canonicalDirection($operator, $direction);
            $family = $canonicalDirection['operator_family'] ?? null;
            if (!is_string($family)) return null;

            $paths = glob(rtrim($directory, '/') . '/' . self::PREFIX . '*.json');
            if (!is_array($paths) || count($paths) > self::MAX_FILES) return null;
            sort($paths, SORT_STRING);

            $fresh = [];
            $latestObserved = 0;
            foreach ($paths as $path) {
                $envelope = self::readEnvelope($path);
                $key = $envelope['key'];
                if (($key['operator_family'] ?? null) !== $family) continue;

                foreach ($envelope['observations'] as $row) {
                    if (!is_array($row)) throw new DomainException('PROGRAM_FUEL_FX_STORE_INVALID');
                    $obs = AnyTourOperatorProgramFuelRegistryV1::observation($row);
                    if ($obs !== $row || $obs['key'] !== $key) {
                        throw new DomainException('PROGRAM_FUEL_FX_STORE_INVALID');
                    }
                    // Supplier-retained FX only. An operator reference may establish a
                    // fuel tariff, but it must not manufacture a currency conversion.
                    if (($obs['source'] ?? null) !== 'andromeda_get_flights') continue;
                    $fx = $obs['exchange'] ?? null;
                    if (!is_array($fx)
                        || ($fx['from'] ?? null) !== 'EUR'
                        || ($fx['to'] ?? null) !== 'RUB'
                        || !is_int($fx['observed_at'] ?? null)
                        || !is_int($fx['expires_at'] ?? null)
                        || $fx['observed_at'] > $now
                        || $fx['expires_at'] <= $now) continue;

                    $seenAt = $fx['observed_at'];
                    if ($seenAt > $latestObserved) {
                        $latestObserved = $seenAt;
                        $fresh = [$fx];
                    } elseif ($seenAt === $latestObserved) {
                        $fresh[] = $fx;
                    }
                }
            }

            $directionFx = self::readDirectionFx($directory, $canonicalDirection, $now);
            if ($directionFx !== null) {
                $seenAt = $directionFx['observed_at'];
                if ($seenAt > $latestObserved) {
                    $latestObserved = $seenAt;
                    $fresh = [$directionFx];
                } elseif ($seenAt === $latestObserved) {
                    $fresh[] = $directionFx;
                }
            }
            if ($fresh === []) return null;

            $rates = [];
            foreach ($fresh as $candidate) $rates[$candidate['rate']] = true;
            // Same-time disagreement is ambiguous and must not be resolved by order.
            if (count($rates) !== 1) return null;

            usort($fresh, static function(array $a, array $b): int {
                $expiry = $b['expires_at'] <=> $a['expires_at'];
                return $expiry !== 0 ? $expiry : strcmp($a['evidence_sha256'], $b['evidence_sha256']);
            });
            $picked = $fresh[0];
            return [
                'from' => 'EUR',
                'to' => 'RUB',
                'rate' => $picked['rate'],
                'scope_sha256' => AnyTourOperatorFuelRuleEvidenceV1::directionDigest($canonicalDirection),
                'observed_at' => $picked['observed_at'],
                'expires_at' => $picked['expires_at'],
                'evidence_sha256' => $picked['evidence_sha256'],
            ];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    private static function readEnvelope(string $path): array
    {
        if (is_link($path) || !is_file($path)) throw new DomainException('PROGRAM_FUEL_FX_STORE_INVALID');
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > self::MAX_BYTES) {
            throw new DomainException('PROGRAM_FUEL_FX_STORE_INVALID');
        }
        $value = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value) || array_is_list($value)) throw new DomainException('PROGRAM_FUEL_FX_STORE_INVALID');
        $keys = array_keys($value); sort($keys, SORT_STRING);
        if ($keys !== ['key','key_sha256','observations','version']
            || ($value['version'] ?? null) !== 1
            || !is_array($value['key'] ?? null) || array_is_list($value['key'])
            || !is_string($value['key_sha256'] ?? null)
            || !hash_equals(AnyTourOperatorFuelRuleEvidenceV1::hash($value['key']), $value['key_sha256'])
            || !is_array($value['observations'] ?? null) || !array_is_list($value['observations'])
            || count($value['observations']) > self::MAX_OBSERVATIONS) {
            throw new DomainException('PROGRAM_FUEL_FX_STORE_INVALID');
        }
        return $value;
    }

    private static function readDirectionFx(string $directory, array $direction, int $now): ?array
    {
        $digest = AnyTourOperatorFuelRuleEvidenceV1::directionDigest($direction);
        $path = rtrim($directory, '/') . '/' . self::DIRECTION_PREFIX . $digest . '.json';
        if (!file_exists($path)) return null;
        if (is_link($path) || !is_file($path)) throw new DomainException('PROGRAM_FUEL_FX_DIRECTION_INVALID');
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > self::MAX_BYTES) {
            throw new DomainException('PROGRAM_FUEL_FX_DIRECTION_INVALID');
        }
        $value = json_decode((string)file_get_contents($path), true, 24, JSON_THROW_ON_ERROR);
        if (!is_array($value) || array_is_list($value)) throw new DomainException('PROGRAM_FUEL_FX_DIRECTION_INVALID');
        $keys = array_keys($value); sort($keys, SORT_STRING);
        if ($keys !== [
                'direction','direction_sha256','evidence_sha256','expires_at','from',
                'independent_probe_count','observed_at','probe_result_sha256','rate',
                'source','to','version'
            ]
            || ($value['version'] ?? null) !== 1
            || ($value['source'] ?? null) !== 'terminal_program_fuel_probes'
            || ($value['from'] ?? null) !== 'EUR' || ($value['to'] ?? null) !== 'RUB'
            || ($value['direction'] ?? null) !== $direction
            || ($value['direction_sha256'] ?? null) !== $digest
            || !is_string($value['rate'] ?? null)
            || preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $value['rate']) !== 1
            || (float)$value['rate'] <= 0
            || !is_int($value['observed_at'] ?? null) || $value['observed_at'] < 1
            || !is_int($value['expires_at'] ?? null) || $value['expires_at'] <= $value['observed_at']
            || ($value['independent_probe_count'] ?? null) !== 2
            || !is_string($value['evidence_sha256'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', $value['evidence_sha256']) !== 1
            || !is_array($value['probe_result_sha256'] ?? null) || !array_is_list($value['probe_result_sha256'])
            || count($value['probe_result_sha256']) !== 2
            || count(array_unique($value['probe_result_sha256'])) !== 2) {
            throw new DomainException('PROGRAM_FUEL_FX_DIRECTION_INVALID');
        }
        foreach ($value['probe_result_sha256'] as $sha) {
            if (!is_string($sha) || preg_match('/\A[a-f0-9]{64}\z/D', $sha) !== 1) {
                throw new DomainException('PROGRAM_FUEL_FX_DIRECTION_INVALID');
            }
        }
        if ($value['observed_at'] > $now || $value['expires_at'] <= $now) return null;
        return [
            'from'=>'EUR','to'=>'RUB','rate'=>$value['rate'],
            'observed_at'=>$value['observed_at'],'expires_at'=>$value['expires_at'],
            'evidence_sha256'=>$value['evidence_sha256'],
        ];
    }

    private static function assertDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory) || basename(rtrim($directory, '/')) !== 'searches') {
            throw new InvalidArgumentException('PROGRAM_FUEL_FX_STORE_ROOT');
        }
    }
}
