<?php
declare(strict_types=1);

/**
 * Provider-neutral availability evidence captured from search responses.
 *
 * Raw supplier markers are retained as bounded labels. Search-time evidence does
 * not assign their business meaning and cannot enable selection or booking.
 */
final class AnyTourThreeProviderAvailability
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];

    public static function fromSearch(string $provider, array $input): array
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_AVAILABILITY_PROVIDER');
        }
        if (!self::exactKeys($input, ['hotel', 'flight_outbound_economy', 'flight_return_economy'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_AVAILABILITY_KEYS');
        }

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'hotel' => self::component($input['hotel']),
            'flight_outbound_economy' => self::component($input['flight_outbound_economy']),
            'flight_return_economy' => self::component($input['flight_return_economy']),
            'offer_availability_verified' => false,
            'selection_eligible' => false,
            'booking_eligible' => false,
        ];
    }

    private static function component(mixed $raw): array
    {
        if ($raw === null) {
            return [
                'raw' => null,
                'canonical_state' => 'unknown',
                'canonical_verified' => false,
                'evidence_state' => 'missing',
            ];
        }
        if (!is_string($raw)) {
            throw new InvalidArgumentException('THREE_PROVIDER_AVAILABILITY_RAW');
        }

        $raw = trim($raw);
        $length = function_exists('mb_strlen') ? mb_strlen($raw, 'UTF-8') : strlen($raw);
        if ($raw === '' || $length > 40
            || preg_match('/\A[\p{L}\p{N}_+ -]{1,40}\z/uD', $raw) !== 1
            || preg_match('/\A[0-9]+\z/D', $raw) === 1) {
            throw new InvalidArgumentException('THREE_PROVIDER_AVAILABILITY_RAW');
        }

        return [
            'raw' => $raw,
            'canonical_state' => 'unknown',
            'canonical_verified' => false,
            'evidence_state' => 'raw_only',
        ];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
