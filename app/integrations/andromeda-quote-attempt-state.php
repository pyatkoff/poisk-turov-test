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
        if (($result['schema_version'] ?? null) !== 1
            || ($result['provider'] ?? null) !== 'andromeda'
            || ($result['selection_enabled'] ?? null) !== true
            || ($result['booking_enabled'] ?? null) !== false
            || !is_int($result['local_id'] ?? null) || $result['local_id'] < 1
            || !in_array($result['state'] ?? null, ['quote_verified', 'flight_selection_required'], true)
            || !is_bool($result['flight_selection_required'] ?? null)
            || !is_array($result['flights'] ?? null)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }

        // Search/package/final are distinct supplier facts. Validate shape only;
        // never infer equality, add fees, convert currencies or replace one with another.
        self::assertMoney($result['search_price'] ?? null, false);
        if (($result['package_price'] ?? null) !== null) {
            self::assertMoney($result['package_price'], false);
        }

        if ($result['state'] === 'quote_verified') {
            if (($result['quote_state'] ?? null) !== 'verified'
                || ($result['final_price_verified'] ?? null) !== true
                || $result['flight_selection_required'] !== false) {
                throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
            }
            self::assertMoney($result['final_price'] ?? null, false);
        } elseif (($result['quote_state'] ?? null) !== 'unverified'
            || ($result['final_price_verified'] ?? null) !== false
            || ($result['final_price'] ?? null) !== null
            || $result['flight_selection_required'] !== true) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }

        if ($result['flights'] !== []
            && array_keys($result['flights']) !== range(0, count($result['flights']) - 1)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_RESULT_INVALID');
        }
        self::assertNoPrivateKeys($result);
    }

    private static function assertMoney(mixed $value, bool $allowZero): void
    {
        if (!is_array($value)) {
            throw new RuntimeException('ANDROMEDA_QUOTE_MONEY_INVALID');
        }
        $keys = array_keys($value);
        sort($keys);
        if ($keys !== ['amount', 'currency']) {
            throw new RuntimeException('ANDROMEDA_QUOTE_MONEY_INVALID');
        }
        $amount = $value['amount'];
        $currency = $value['currency'];
        if (!is_string($amount)
            || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $amount) !== 1
            || (!$allowZero && preg_match('/[1-9]/', $amount) !== 1)
            || !is_string($currency)
            || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) {
            throw new RuntimeException('ANDROMEDA_QUOTE_MONEY_INVALID');
        }
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
