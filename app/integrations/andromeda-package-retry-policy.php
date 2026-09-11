<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-network-transport-failure.php';

/**
 * Source-only fail-closed retry policy for one selected Andromeda broninit.
 *
 * Supplier clarification received 2026-09-11 permits repeating broninit with the
 * SAME claiminc after a network failure. This policy never stores or accepts the raw
 * claiminc; it compares bounded SHA-256 provenance only. Historical v1 `unknown`
 * checkpoints remain non-retryable because their failure class was not preserved.
 */
final class AnyTourAndromedaPackageRetryPolicy
{
    private const MAX_ATTEMPTS = 2;
    private const EVIDENCE_KEYS = [
        'version', 'status', 'failure_class', 'attempt',
        'claiminc_sha256', 'context_sha256', 'operation_sha256',
    ];

    /** Never infer network provenance from an error message string. */
    public static function classify(Throwable $error): string
    {
        return $error instanceof AnyTourAndromedaNetworkTransportFailure
            ? 'network_transport'
            : 'unclassified';
    }

    /**
     * Decide whether exactly one repeat is eligible. The caller supplies the expected
     * selected claiminc/context/operation digests from its current trusted state.
     * No supplier operation is performed here.
     */
    public static function decision(array $evidence, string $expectedClaimincSha256,
        string $expectedContextSha256, string $expectedOperationSha256): array
    {
        $deny = static fn(string $reason): array => [
            'retry_allowed' => false,
            'reason' => $reason,
            'next_attempt' => null,
            'max_attempts' => self::MAX_ATTEMPTS,
        ];
        foreach ([$expectedClaimincSha256, $expectedContextSha256, $expectedOperationSha256] as $digest) {
            if (!self::digest($digest)) return $deny('invalid_expected_provenance');
        }
        $keys = array_keys($evidence);
        sort($keys);
        $expectedKeys = self::EVIDENCE_KEYS;
        sort($expectedKeys);
        if ($keys !== $expectedKeys) return $deny('invalid_evidence_envelope');
        if (($evidence['version'] ?? null) !== 2) return $deny('legacy_or_unclassified');
        if (!is_int($evidence['attempt'] ?? null) || $evidence['attempt'] < 1
            || $evidence['attempt'] > self::MAX_ATTEMPTS) return $deny('invalid_attempt');
        foreach (['claiminc_sha256', 'context_sha256', 'operation_sha256'] as $field) {
            if (!is_string($evidence[$field] ?? null) || !self::digest($evidence[$field])) {
                return $deny('invalid_evidence_provenance');
            }
        }
        if (!hash_equals($expectedClaimincSha256, $evidence['claiminc_sha256'])
            || !hash_equals($expectedContextSha256, $evidence['context_sha256'])
            || !hash_equals($expectedOperationSha256, $evidence['operation_sha256'])) {
            return $deny('provenance_changed');
        }
        if (($evidence['status'] ?? null) !== 'unknown_transport'
            || ($evidence['failure_class'] ?? null) !== 'network_transport') {
            return $deny('outcome_not_retryable');
        }
        if ($evidence['attempt'] !== 1) return $deny('retry_already_consumed');
        return [
            'retry_allowed' => true,
            'reason' => 'supplier_confirmed_network_retry',
            'next_attempt' => 2,
            'max_attempts' => self::MAX_ATTEMPTS,
        ];
    }

    private static function digest(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }
}
