<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/** Private fuel evidence in the existing INT searches evidence directory. */
final class AnyTourOperatorFuelRuleStoreV1
{
    private const PREFIX = 'operator-fuel-rule-v1-';
    private const MAX_BYTES = 131072;
    private const MAX_OBSERVATIONS = 64;

    public static function append(string $directory, array $raw, callable $write): array
    {
        self::assertDirectory($directory);
        $obs = AnyTourOperatorFuelRuleEvidenceV1::observation($raw);
        $digest = AnyTourOperatorFuelRuleEvidenceV1::scopeDigest($obs['scope']);
        $path = self::path($directory, $digest);
        $current = self::readEnvelope($path, true);
        $rows = [];
        if ($current !== null) {
            self::assertEnvelope($current, $digest);
            $rows = $current['observations'];
        }
        $byEvidence = [];
        foreach ($rows as $row) $byEvidence[$row['evidence_sha256']] = $row;
        if (isset($byEvidence[$obs['evidence_sha256']])) {
            if ($byEvidence[$obs['evidence_sha256']] !== $obs) throw new DomainException('OPERATOR_FUEL_STORE_CONFLICT');
            return ['status'=>'unchanged','written'=>false,'path'=>$path,'observationCount'=>count($rows)];
        }
        $rows[] = $obs;
        usort($rows, static fn(array $a,array $b): int => [$a['observed_at'],$a['evidence_sha256']] <=> [$b['observed_at'],$b['evidence_sha256']]);
        if (count($rows) > self::MAX_OBSERVATIONS) $rows = array_slice($rows, -self::MAX_OBSERVATIONS);
        $next = ['version'=>1,'scope_sha256'=>$digest,'scope'=>$obs['scope'],'observations'=>$rows];
        $encoded = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_BYTES) throw new DomainException('OPERATOR_FUEL_STORE_SIZE');
        if ($write($path, $next) !== true) throw new RuntimeException('OPERATOR_FUEL_STORE_WRITE');
        $read = self::readEnvelope($path, false);
        self::assertEnvelope($read, $digest);
        if ($read !== $next) throw new RuntimeException('OPERATOR_FUEL_STORE_READBACK');
        return ['status'=>$current===null?'created':'appended','written'=>true,'path'=>$path,'observationCount'=>count($rows)];
    }

    public static function inputForTarget(string $directory, array $target, int $now, ?array $exchange = null): ?array
    {
        try {
            self::assertDirectory($directory);
            $provider = $target['provider'] ?? null;
            $operator = $target['operator'] ?? null;
            $scopeRaw = $target['scope'] ?? null;
            if (!is_array($scopeRaw)) return null;
            $scope = AnyTourOperatorFuelRuleEvidenceV1::canonicalScope($provider, $operator, $scopeRaw);
            $digest = AnyTourOperatorFuelRuleEvidenceV1::scopeDigest($scope);
            $envelope = self::readEnvelope(self::path($directory, $digest), true);
            if ($envelope === null) return null;
            self::assertEnvelope($envelope, $digest);
            return AnyTourOperatorFuelRuleEvidenceV1::confirmedInput($target, $envelope['observations'], $now, $exchange);
        } catch (Throwable $ignored) {
            return null;
        }
    }

    public static function pricingEnvelopeForTarget(string $directory, array $target, int $now, ?array $exchange = null): ?array
    {
        $input = self::inputForTarget($directory, $target, $now, $exchange);
        return $input === null ? null : ['state'=>'operator_fuel','operator_fuel'=>$input];
    }

    private static function path(string $directory, string $digest): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) throw new InvalidArgumentException('OPERATOR_FUEL_STORE_DIGEST');
        return rtrim($directory, '/') . '/' . self::PREFIX . $digest . '.json';
    }

    private static function assertDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory) || basename(rtrim($directory,'/')) !== 'searches') {
            throw new InvalidArgumentException('OPERATOR_FUEL_STORE_ROOT');
        }
    }

    private static function readEnvelope(string $path, bool $optional): ?array
    {
        if (is_link($path)) throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
        if (!file_exists($path)) return $optional ? null : throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
        if (!is_file($path) || filesize($path) < 2 || filesize($path) > self::MAX_BYTES) throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
        $value = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
    }

    private static function assertEnvelope(array $value, string $digest): void
    {
        $keys = array_keys($value); sort($keys);
        if ($keys !== ['observations','scope','scope_sha256','version'] || ($value['version']??null)!==1
            || ($value['scope_sha256']??null)!==$digest || !is_array($value['scope']??null)
            || AnyTourOperatorFuelRuleEvidenceV1::scopeDigest($value['scope']) !== $digest
            || !is_array($value['observations']??null) || !array_is_list($value['observations'])
            || count($value['observations']) > self::MAX_OBSERVATIONS) throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
        foreach ($value['observations'] as $row) {
            if (!is_array($row) || AnyTourOperatorFuelRuleEvidenceV1::observation($row) !== $row || $row['scope'] !== $value['scope']) {
                throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
            }
        }
    }
}
