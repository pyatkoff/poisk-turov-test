<?php
declare(strict_types=1);

/**
 * Provider-neutral room/placement label boundary.
 *
 * It only normalizes display text. It never treats equal labels as package identity
 * evidence and never fills a missing placement from the room label.
 */
final class AnyTourThreeProviderRoomPlacement
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];

    public static function normalize(string $provider, $room, $placement): array
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_ROOM_PROVIDER');
        }

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'room' => self::label($room, 'THREE_PROVIDER_ROOM_LABEL'),
            'placement' => $placement === null
                ? null
                : self::label($placement, 'THREE_PROVIDER_PLACEMENT_LABEL'),
            'room_placement_separate' => true,
            'cross_provider_equivalence_verified' => false,
            'package_identity_verified' => false,
        ];
    }

    private static function label($value, string $error): array
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($error);
        }
        $raw = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($raw, 'UTF-8') : strlen($raw);
        if ($raw === '' || $length > 180 || preg_match('/[\p{Cc}\p{Cf}]/u', $raw)
            || preg_match('/\A[0-9]+\z/D', $raw)) {
            throw new InvalidArgumentException($error);
        }

        $normalized = str_replace(['Ё', 'ё'], 'е', $raw);
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);
        $normalized = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized));
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));
        if ($normalized === '') {
            throw new InvalidArgumentException($error);
        }

        return [
            'raw' => $raw,
            'normalized' => $normalized,
            'comparison_scope' => 'display_label_only',
        ];
    }
}
