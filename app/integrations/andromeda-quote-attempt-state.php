<?php
declare(strict_types=1);

/**
 * Pure durable-state contract for one selected Andromeda quote operation.
 * Runtime persistence/locking is owned by the existing private Search3 gateway.
 * No supplier/private identity is retained here: only provenance digests and the
 * already browser-safe completed result may be persisted.
 */
final class AnyTourAndromedaQuoteAttemptState
{
    private const KEYS = ['version', 'status', 'context_sha256', 'operation_sha256', 'result'];

    public static function reserve(string $contextSha256, string $operationSha256): array
    {
        self::assertDigest($contextSha256);
        self::assertDigest($operationSha256);
        return [
            'version' => 1,
            'status' => 'reserved',
            'context_sha256' => $contextSha256,
            'operation_sha256' => $operationSha256,
            'result' => null,
        ];
    }

    public static function completed(array $reserved, array $result): array
    {
        self::assertReserved($reserved);
        self::assertPublicResult($result);
        $next = $reserved;
        $next['status'] = 'completed';
        $next['result'] = $result;
        return $next;
    }

    public static function unknown(array $reserved): array
    {
        self::assertReserved($reserved);
        $next = $reserved;
        $next['status'] = 'unknown';
        return $next;
    }

    /**
     * A completed quote may be reused locally only for the exact current context.
     * Reserved/unknown operations are sealed and can never authorize another call.
     */
    public static function replay(array $state, string $contextSha256, string $operationSha256): array
    {
        self::assertState($state);
        self::assertDigest($contextSha256);
        self::assertDigest($operationSha256);
        if (!hash_equals($state['context_sha256'], $contextSha256)
            || !hash_equals($state['operation_sha256'], $operationSha256)
            || $state['status'] !== 'completed' || !is_array($state['result'])) {
            throw new RuntimeException('ANDROMEDA_QUOTE_REPLAY_REFUSED');
        }
        self::assertPublicResult($state['result']);
        return $state['result'];
    }

    private static function assertReserved(array $state): void
    {
        self::assertState($state);
        if ($state['status'] !== 'reserved' || $state['result'] !== null) {
            throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        }
    }

    private static function assertState(array $state): void
    {
        $keys = array_keys($state);
        $expected = self::KEYS;
        sort($keys);
        sort($expected);
        if ($keys !== $expected || ($state['version'] ?? null) !== 1
            || !in_array($state['status'] ?? null, ['reserved', 'unknown', 'completed'], true)
            || !is_string($state['context_sha256'] ?? null)
            || !is_string($state['operation_sha256'] ?? null)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        }
        self::assertDigest($state['context_sha256']);
        self::assertDigest($state['operation_sha256']);
        if ($state['status'] === 'completed') {
            if (!is_array($state['result'])) throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        } elseif ($state['result'] !== null) {
            throw new RuntimeException('ANDROMEDA_QUOTE_ATTEMPT_INVALID');
        }
    }

    private static function assertPublicResult(array $result): void
    {
        if (($result['provider'] ?? null) !== 'andromeda'
            || ($result['selection_enabled'] ?? null) !== true
            || ($result['booking_enabled'] ?? null) !== false
            || !in_array($result['state'] ?? null, ['quote_verified', 'flight_selection_required'], true)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }
        if ($result['state'] === 'quote_verified') {
            if (($result['quote_state'] ?? null) !== 'verified'
                || ($result['final_price_verified'] ?? null) !== true
                || !is_array($result['final_price'] ?? null)) {
                throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
            }
        } elseif (($result['quote_state'] ?? null) !== 'unverified'
            || ($result['final_price_verified'] ?? null) !== false
            || ($result['final_price'] ?? null) !== null) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }
        self::assertNoPrivateKeys($result);
    }

    private static function assertNoPrivateKeys(array $value): void
    {
        $private = ['supplier_offer_id', 'supplier_hotel_id', 'claiminc', 'claimdocument', 'sid', 'uid', 'catalogkey'];
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string)$key), $private, true)) {
                throw new RuntimeException('ANDROMEDA_QUOTE_PRIVATE_STATE');
            }
            if (is_array($item)) self::assertNoPrivateKeys($item);
        }
    }

    private static function assertDigest(string $value): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RuntimeException('ANDROMEDA_QUOTE_PROVENANCE_INVALID');
        }
    }
}
