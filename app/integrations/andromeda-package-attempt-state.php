<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-claiminc-contract.php';
require_once __DIR__ . '/andromeda-package-retry-policy.php';

/**
 * Pure durable-state contract for one selected Andromeda package refresh.
 * It performs no network/filesystem/database operation and stores only digests.
 * Runtime persistence/locking remains the caller's responsibility.
 */
final class AnyTourAndromedaPackageAttemptState
{
    private const KEYS = [
        'version', 'status', 'failure_class', 'attempt',
        'claiminc_sha256', 'context_sha256', 'operation_sha256',
    ];

    public static function reserveFirst(string $claiminc, string $contextSha256,
        string $operationSha256): array
    {
        $claiminc = AnyTourAndromedaClaimincContract::retained($claiminc);
        self::assertDigest($contextSha256);
        self::assertDigest($operationSha256);
        return [
            'version' => 2,
            'status' => 'reserved',
            'failure_class' => 'none',
            'attempt' => 1,
            'claiminc_sha256' => hash('sha256', $claiminc),
            'context_sha256' => $contextSha256,
            'operation_sha256' => $operationSha256,
        ];
    }

    /** Convert one reserved attempt into bounded failure evidence. */
    public static function failed(array $reserved, Throwable $error): array
    {
        self::assertReserved($reserved);
        $failure = AnyTourAndromedaPackageRetryPolicy::classify($error);
        $next = $reserved;
        if ($failure === 'network_transport') {
            $next['status'] = 'unknown_transport';
            $next['failure_class'] = 'network_transport';
        } else {
            $next['status'] = 'unknown_unclassified';
            $next['failure_class'] = 'unclassified';
        }
        return $next;
    }

    /** Mark a reserved attempt complete without adding package/private data. */
    public static function succeeded(array $reserved): array
    {
        self::assertReserved($reserved);
        $next = $reserved;
        $next['status'] = 'completed';
        return $next;
    }

    /**
     * Create the only permitted repeat reservation. The full claiminc is accepted only
     * to prove it hashes to the original private identity; it is never retained here.
     */
    public static function reserveRetry(array $evidence, string $claiminc,
        string $contextSha256, string $operationSha256): array
    {
        $claiminc = AnyTourAndromedaClaimincContract::retained($claiminc);
        self::assertDigest($contextSha256);
        self::assertDigest($operationSha256);
        $claimincSha256 = hash('sha256', $claiminc);
        $decision = AnyTourAndromedaPackageRetryPolicy::decision(
            $evidence, $claimincSha256, $contextSha256, $operationSha256);
        if (($decision['retry_allowed'] ?? false) !== true || ($decision['next_attempt'] ?? null) !== 2) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_RETRY_REFUSED');
        }
        return [
            'version' => 2,
            'status' => 'reserved',
            'failure_class' => 'none',
            'attempt' => 2,
            'claiminc_sha256' => $claimincSha256,
            'context_sha256' => $contextSha256,
            'operation_sha256' => $operationSha256,
        ];
    }

    private static function assertReserved(array $state): void
    {
        $keys = array_keys($state);
        $expected = self::KEYS;
        sort($keys);
        sort($expected);
        if ($keys !== $expected || ($state['version'] ?? null) !== 2
            || ($state['status'] ?? null) !== 'reserved'
            || ($state['failure_class'] ?? null) !== 'none'
            || !is_int($state['attempt'] ?? null)
            || !in_array($state['attempt'], [1, 2], true)) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_ATTEMPT_INVALID');
        }
        foreach (['claiminc_sha256','context_sha256','operation_sha256'] as $field) {
            if (!is_string($state[$field] ?? null)) {
                throw new RuntimeException('ANDROMEDA_PACKAGE_ATTEMPT_INVALID');
            }
            self::assertDigest($state[$field]);
        }
    }

    private static function assertDigest(string $value): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_PROVENANCE_INVALID');
        }
    }
}
