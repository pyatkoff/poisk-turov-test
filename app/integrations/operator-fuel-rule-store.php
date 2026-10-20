<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/** Private V2 direction-keyed fuel evidence in the existing INT searches directory. */
final class AnyTourOperatorFuelRuleStoreV1
{
    private const PREFIX = 'operator-fuel-rule-v2-';
    private const MAX_BYTES = 262144;
    private const MAX_OBSERVATIONS = 128;

    public static function append(string $directory, array $raw, callable $write): array
    {
        self::assertDirectory($directory);
        $obs = AnyTourOperatorFuelRuleEvidenceV1::observation($raw);
        $digest = AnyTourOperatorFuelRuleEvidenceV1::directionDigest($obs['direction']);
        $path = self::path($directory, $digest);
        $current = self::readEnvelope($path, true);
        $rows = [];
        if ($current !== null) {
            self::assertEnvelope($current, $digest);
            if ($current['direction'] !== $obs['direction']) throw new DomainException('OPERATOR_FUEL_STORE_CONFLICT');
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
        $next = ['version'=>2,'direction_sha256'=>$digest,'direction'=>$obs['direction'],'observations'=>$rows];
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
            $operator = $target['operator'] ?? null;
            if (!is_string($operator)) return null;
            $direction = self::directionForTarget($target, $operator);
            $digest = AnyTourOperatorFuelRuleEvidenceV1::directionDigest($direction);
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
        if ($input !== null) {
            $policy = self::ownerPolicyForDirection($input['direction'] ?? null);
            if ($policy !== null) $input['owner_policy'] = $policy;
            return ['state'=>'operator_fuel','operator_fuel'=>$input];
        }
        $fallback = self::ownerFallbackInput($directory, $target, $now, $exchange);
        return $fallback === null ? null : ['state'=>'operator_fuel','operator_fuel'=>$fallback];
    }

    private static function ownerFallbackInput(string $directory, array $target, int $now, ?array $exchange): ?array
    {
        try {
            self::assertDirectory($directory);
            if ($now < 1 || !is_array($exchange)) return null;
            $operator = $target['operator'] ?? null;
            if (!is_string($operator)) return null;
            $direction = self::directionForTarget($target, $operator);
            $policy = self::ownerPolicyForDirection($direction);
            if ($policy === null) return null;
            $party = self::partyForTarget($target);
            foreach ($party['child_ages'] as $age) if ($age < 2) return null;
            $offer = self::digest($target['offer_ref_digest'] ?? null);
            $fx = self::exchangeForDirection($exchange, $direction, $now);
            return [
                'offer_ref_digest'=>$offer,
                'direction'=>$direction,
                'party'=>$party,
                'observations'=>[],
                'exchange'=>$fx,
                'owner_policy'=>$policy,
            ];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    private static function directionForTarget(array $target, string $operator): array
    {
        if (array_key_exists('direction', $target)) {
            return AnyTourOperatorFuelRuleEvidenceV1::canonicalDirection($operator, $target['direction']);
        }
        if (is_array($target['search_params'] ?? null)) {
            return AnyTourOperatorFuelRuleEvidenceV1::directionFromSearch($operator, $target['search_params']);
        }
        $provider = $target['provider'] ?? null;
        $scope = AnyTourOperatorFuelRuleEvidenceV1::canonicalScope($provider, $operator, $target['scope'] ?? null);
        return AnyTourOperatorFuelRuleEvidenceV1::directionFromScope($operator, $scope);
    }

    private static function ownerPolicyForDirection(mixed $direction): ?array
    {
        if (!is_array($direction)
            || ($direction['operator_family'] ?? null) !== 'fun_and_sun'
            || ($direction['destination'] ?? null) !== 'country:4') return null;
        return [
            'schema_version'=>1,
            'source'=>'owner_policy',
            'policy_date'=>'2026-09-23',
            'operator_family'=>'fun_and_sun',
            'destination'=>'country:4',
            'amount'=>'70.00',
            'currency'=>'EUR',
            'unit'=>'per_person_one_way',
            'base_relation'=>'excluded',
        ];
    }

    private static function partyForTarget(array $target): array
    {
        $value = array_key_exists('party', $target)
            ? $target['party']
            : (is_array($target['scope'] ?? null) ? ($target['scope']['party'] ?? null) : null);
        if (!is_array($value) || count($value) !== 3
            || !is_int($value['adults'] ?? null) || $value['adults'] < 1 || $value['adults'] > 9
            || !is_int($value['children'] ?? null) || $value['children'] < 0 || $value['children'] > 9
            || !is_array($value['child_ages'] ?? null) || !array_is_list($value['child_ages'])
            || count($value['child_ages']) !== $value['children']) throw new InvalidArgumentException('OPERATOR_FUEL_PARTY');
        $ages = $value['child_ages'];
        foreach ($ages as $age) if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('OPERATOR_FUEL_PARTY');
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$value['adults'],'children'=>$value['children'],'child_ages'=>$ages];
    }

    private static function exchangeForDirection(array $value, array $direction, int $now): array
    {
        if (($value['from'] ?? null) !== 'EUR' || ($value['to'] ?? null) !== 'RUB'
            || !is_string($value['rate'] ?? null)
            || preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $value['rate']) !== 1
            || preg_match('/[1-9]/', $value['rate']) !== 1
            || ($value['scope_sha256'] ?? null) !== AnyTourOperatorFuelRuleEvidenceV1::directionDigest($direction)
            || !is_int($value['observed_at'] ?? null) || !is_int($value['expires_at'] ?? null)
            || $value['observed_at'] < 1 || $value['observed_at'] > $now || $value['expires_at'] <= $now) {
            throw new InvalidArgumentException('OPERATOR_FUEL_EXCHANGE');
        }
        return [
            'from'=>'EUR','to'=>'RUB','rate'=>$value['rate'],
            'scope_sha256'=>$value['scope_sha256'],
            'observed_at'=>$value['observed_at'],'expires_at'=>$value['expires_at'],
            'evidence_sha256'=>self::digest($value['evidence_sha256'] ?? null),
        ];
    }

    private static function digest(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('OPERATOR_FUEL_DIGEST');
        }
        return $value;
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
        if ($keys !== ['direction','direction_sha256','observations','version'] || ($value['version']??null)!==2
            || ($value['direction_sha256']??null)!==$digest || !is_array($value['direction']??null)
            || AnyTourOperatorFuelRuleEvidenceV1::directionDigest($value['direction']) !== $digest
            || !is_array($value['observations']??null) || !array_is_list($value['observations'])
            || count($value['observations']) > self::MAX_OBSERVATIONS) throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
        foreach ($value['observations'] as $row) {
            if (!is_array($row) || AnyTourOperatorFuelRuleEvidenceV1::observation($row) !== $row
                || $row['direction'] !== $value['direction']) {
                throw new DomainException('OPERATOR_FUEL_STORE_INVALID');
            }
        }
    }
}
