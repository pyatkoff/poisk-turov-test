<?php
declare(strict_types=1);

/**
 * Provider-neutral retained-offer guard for the future Search handoff.
 *
 * Pure source-only boundary: no supplier, database, mapping, pricing, UI, lead,
 * or reservation side effects. Selection remains disabled until the owning runtime
 * explicitly wires a verified current context.
 */
final class AnyTourThreeProviderOfferContext
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const IDENTITY_KEYS = [
        'search_ref_digest',
        'offer_ref_digest',
        'provider_hotel_ref_digest',
    ];

    public static function retain(array $offer, int $generation, int $page, int $now, int $ttl = 900): array
    {
        self::validatePositive($generation, 2147483647, 'THREE_PROVIDER_CONTEXT_GENERATION');
        self::validatePositive($page, 10000, 'THREE_PROVIDER_CONTEXT_PAGE');
        self::validatePositive($now, 2147483647, 'THREE_PROVIDER_CONTEXT_TIME');
        if ($ttl < 60 || $ttl > 900) {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_TTL');
        }

        if (($offer['schema_version'] ?? null) !== 1
            || ($offer['selection_state'] ?? null) !== 'disabled') {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_OFFER');
        }
        $provider = self::provider($offer['provider'] ?? null);
        $operator = self::operator($provider, $offer['operator'] ?? null);
        $localHotelId = $offer['local_hotel_id'] ?? null;
        self::validatePositive($localHotelId, 999999999, 'THREE_PROVIDER_CONTEXT_LOCAL_ID');
        $identity = self::identity($offer['identity'] ?? null);

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'operator' => $operator,
            'local_hotel_id' => $localHotelId,
            'identity' => $identity,
            'generation' => $generation,
            'page' => $page,
            'issued_at' => $now,
            'expires_at' => $now + $ttl,
            'current_context_verified' => false,
            'selection_state' => 'disabled',
        ];
    }

    public static function validate(array $retained, array $current, int $now): array
    {
        self::retained($retained);
        self::validatePositive($now, 2147483647, 'THREE_PROVIDER_CONTEXT_TIME');
        self::current($current);

        if ($now >= $retained['expires_at']) {
            return self::result('expired', false);
        }

        foreach (['provider', 'operator', 'local_hotel_id', 'generation', 'page'] as $key) {
            if ($retained[$key] !== $current[$key]) {
                return self::result('mismatch', false);
            }
        }
        foreach (self::IDENTITY_KEYS as $key) {
            if ($retained['identity'][$key] !== $current['identity'][$key]) {
                return self::result('mismatch', false);
            }
        }

        return self::result('current', true);
    }

    private static function retained(array $value): void
    {
        $keys = [
            'schema_version', 'provider', 'operator', 'local_hotel_id', 'identity',
            'generation', 'page', 'issued_at', 'expires_at',
            'current_context_verified', 'selection_state',
        ];
        if (!self::exactKeys($value, $keys)
            || $value['schema_version'] !== 1
            || $value['current_context_verified'] !== false
            || $value['selection_state'] !== 'disabled') {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_RETAINED');
        }
        $provider = self::provider($value['provider']);
        self::operator($provider, $value['operator']);
        self::validatePositive($value['local_hotel_id'], 999999999, 'THREE_PROVIDER_CONTEXT_LOCAL_ID');
        self::identity($value['identity']);
        self::validatePositive($value['generation'], 2147483647, 'THREE_PROVIDER_CONTEXT_GENERATION');
        self::validatePositive($value['page'], 10000, 'THREE_PROVIDER_CONTEXT_PAGE');
        self::validatePositive($value['issued_at'], 2147483647, 'THREE_PROVIDER_CONTEXT_TIME');
        self::validatePositive($value['expires_at'], 2147483647, 'THREE_PROVIDER_CONTEXT_TIME');
        $ttl = $value['expires_at'] - $value['issued_at'];
        if ($ttl < 60 || $ttl > 900) {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_TTL');
        }
    }

    private static function current(array $value): void
    {
        $keys = ['provider', 'operator', 'local_hotel_id', 'identity', 'generation', 'page'];
        if (!self::exactKeys($value, $keys)) {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_CURRENT');
        }
        $provider = self::provider($value['provider']);
        self::operator($provider, $value['operator']);
        self::validatePositive($value['local_hotel_id'], 999999999, 'THREE_PROVIDER_CONTEXT_LOCAL_ID');
        self::identity($value['identity']);
        self::validatePositive($value['generation'], 2147483647, 'THREE_PROVIDER_CONTEXT_GENERATION');
        self::validatePositive($value['page'], 10000, 'THREE_PROVIDER_CONTEXT_PAGE');
    }

    private static function provider($value): string
    {
        if (!is_string($value) || !in_array($value, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_PROVIDER');
        }
        return $value;
    }

    private static function operator(string $provider, $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_OPERATOR');
        }
        $checked = AnyTourThreeProviderOperator::fromSearch($provider, $value['raw'] ?? null);
        if ($checked !== $value) throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_OPERATOR');
        return $checked;
    }

    private static function identity($value): array
    {
        if (!is_array($value) || !self::exactKeys($value, self::IDENTITY_KEYS)) {
            throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_IDENTITY');
        }
        foreach (self::IDENTITY_KEYS as $key) {
            if (!is_string($value[$key]) || !preg_match('/\A[a-f0-9]{64}\z/D', $value[$key])) {
                throw new InvalidArgumentException('THREE_PROVIDER_CONTEXT_IDENTITY');
            }
        }
        return $value;
    }

    private static function validatePositive($value, int $max, string $error): void
    {
        if (!is_int($value) || $value < 1 || $value > $max) {
            throw new InvalidArgumentException($error);
        }
    }

    private static function result(string $status, bool $verified): array
    {
        return [
            'status' => $status,
            'current_context_verified' => $verified,
            'selection_state' => 'disabled',
        ];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
