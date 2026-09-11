<?php

declare(strict_types=1);

/**
 * Conservative source-only normalization for meal labels.
 *
 * This class deliberately separates meal family from qualifiers. It does not
 * assert cross-provider or package equivalence.
 */
final class AnyTourThreeProviderMeal
{
    private const MAX_LABEL_LENGTH = 120;

    /** @var array<string, list<string>> */
    private const FAMILY_ALIASES = [
        'uai' => [
            'uai',
            'ultra all inclusive',
            'ультра все включено',
            'ультра всё включено',
        ],
        'ai' => [
            'ai',
            'all inclusive',
            'все включено',
            'всё включено',
        ],
        'fb' => [
            'fb',
            'full board',
            'полный пансион',
        ],
        'hb' => [
            'hb',
            'half board',
            'полупансион',
        ],
        'bb' => [
            'bb',
            'bed breakfast',
            'bed and breakfast',
            'завтрак',
        ],
        'ro' => [
            'ro',
            'room only',
            'без питания',
        ],
    ];

    /**
     * @return array{
     *   raw:string,
     *   normalized:string,
     *   family:?string,
     *   qualifiers:list<string>,
     *   canonical_key:?string,
     *   classification_status:string,
     *   cross_provider_equivalence_verified:bool,
     *   package_equivalence_verified:bool
     * }
     */
    public static function fromLabel(mixed $value): array
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Meal label must be a string.');
        }

        $raw = trim($value);
        if ($raw === '') {
            throw new InvalidArgumentException('Meal label must not be empty.');
        }

        if (self::length($raw) > self::MAX_LABEL_LENGTH) {
            throw new InvalidArgumentException('Meal label is too long.');
        }

        if (preg_match('/^\d+$/', $raw) === 1) {
            throw new InvalidArgumentException('Raw numeric supplier meal ids are not canonical labels.');
        }

        $normalized = self::normalize($raw);
        $qualifiers = [];

        if (preg_match('/(?:^| )(?:without alcohol|без алкоголя)(?: |$)/u', $normalized) === 1) {
            $qualifiers[] = 'without_alcohol';
        }

        if (
            str_contains($raw, '+')
            || preg_match('/(?:^| )plus(?: |$)/u', $normalized) === 1
        ) {
            $qualifiers[] = 'plus';
        }

        $base = preg_replace(
            '/(?:^| )(?:without alcohol|без алкоголя|plus)(?: |$)/u',
            ' ',
            $normalized
        );
        $base = self::normalize((string) $base);

        $family = self::familyFor($base);
        $canonicalKey = $family;

        if ($family !== null && $qualifiers !== []) {
            $canonicalKey .= ':' . implode('+', $qualifiers);
        }

        return [
            'raw' => $raw,
            'normalized' => $normalized,
            'family' => $family,
            'qualifiers' => $qualifiers,
            'canonical_key' => $canonicalKey,
            'classification_status' => $family === null ? 'unknown' : 'verified',
            'cross_provider_equivalence_verified' => false,
            'package_equivalence_verified' => false,
        ];
    }

    private static function familyFor(string $base): ?string
    {
        foreach (self::FAMILY_ALIASES as $family => $aliases) {
            if (in_array($base, $aliases, true)) {
                return $family;
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        $value = self::lower($value);
        $value = str_replace(['&', '+'], [' and ', ' '], $value);
        $value = preg_replace('/[^a-zа-яё0-9]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        return trim((string) $value);
    }

    private static function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtr(strtolower($value), [
            'А' => 'а', 'Б' => 'б', 'В' => 'в', 'Г' => 'г', 'Д' => 'д',
            'Е' => 'е', 'Ё' => 'ё', 'Ж' => 'ж', 'З' => 'з', 'И' => 'и',
            'Й' => 'й', 'К' => 'к', 'Л' => 'л', 'М' => 'м', 'Н' => 'н',
            'О' => 'о', 'П' => 'п', 'Р' => 'р', 'С' => 'с', 'Т' => 'т',
            'У' => 'у', 'Ф' => 'ф', 'Х' => 'х', 'Ц' => 'ц', 'Ч' => 'ч',
            'Ш' => 'ш', 'Щ' => 'щ', 'Ъ' => 'ъ', 'Ы' => 'ы', 'Ь' => 'ь',
            'Э' => 'э', 'Ю' => 'ю', 'Я' => 'я',
        ]);
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
