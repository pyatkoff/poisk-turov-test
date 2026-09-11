<?php
declare(strict_types=1);

/**
 * Canonical meal-family normalizer shared by provider adapters.
 *
 * Only human-readable labels are accepted here. Supplier numeric IDs are deliberately
 * excluded because the same number must never be assumed to mean the same meal in
 * Tourvisor, direct ANEX and Andromeda.
 */
final class AnyTourThreeProviderMealFamily
{
    public static function normalize($raw): array
    {
        if (!is_string($raw)) {
            throw new InvalidArgumentException('THREE_PROVIDER_MEAL_LABEL');
        }
        $raw = trim($raw);
        if ($raw === '' || self::length($raw) > 120) {
            throw new InvalidArgumentException('THREE_PROVIDER_MEAL_LABEL');
        }

        $label = self::key($raw);
        $family = null;
        $plus = false;
        $withoutAlcohol = false;

        $exact = [
            'ro' => 'ro', 'room only' => 'ro', 'no meal' => 'ro', 'without meal' => 'ro',
            'без питания' => 'ro', 'без еды' => 'ro',
            'bb' => 'bb', 'bed breakfast' => 'bb', 'bed and breakfast' => 'bb',
            'breakfast' => 'bb', 'завтрак' => 'bb', 'только завтрак' => 'bb',
            'hb' => 'hb', 'half board' => 'hb', 'полупансион' => 'hb',
            'fb' => 'fb', 'full board' => 'fb', 'полный пансион' => 'fb',
            'ai' => 'ai', 'all inclusive' => 'ai', 'все включено' => 'ai', 'всё включено' => 'ai',
            'uai' => 'uai', 'ultra all inclusive' => 'uai', 'ultra ai' => 'uai',
            'ультра все включено' => 'uai', 'ультра всё включено' => 'uai',
        ];
        if (isset($exact[$label])) {
            $family = $exact[$label];
        } elseif (in_array($label, [
            'hb plus', 'half board plus', 'полупансион плюс'
        ], true)) {
            $family = 'hb'; $plus = true;
        } elseif (in_array($label, [
            'fb plus', 'full board plus', 'полный пансион плюс'
        ], true)) {
            $family = 'fb'; $plus = true;
        } elseif (in_array($label, [
            'ai plus', 'all inclusive plus', 'все включено плюс', 'всё включено плюс'
        ], true)) {
            $family = 'ai'; $plus = true;
        } elseif (in_array($label, [
            'ai without alcohol', 'all inclusive without alcohol', 'all inclusive no alcohol',
            'все включено без алкоголя', 'всё включено без алкоголя'
        ], true)) {
            $family = 'ai'; $withoutAlcohol = true;
        } elseif (in_array($label, [
            'uai without alcohol', 'ultra all inclusive without alcohol',
            'ультра все включено без алкоголя', 'ультра всё включено без алкоголя'
        ], true)) {
            $family = 'uai'; $withoutAlcohol = true;
        }

        $canonicalKey = $family;
        if ($canonicalKey !== null && $withoutAlcohol) {
            $canonicalKey .= ':without_alcohol';
        }
        if ($canonicalKey !== null && $plus) {
            $canonicalKey .= ':plus';
        }

        return [
            'raw' => $raw,
            'normalized_label' => $label,
            'family' => $family,
            'qualifiers' => [
                'plus' => $plus,
                'without_alcohol' => $withoutAlcohol,
            ],
            'canonical_key' => $canonicalKey,
            'classification_status' => $family === null ? 'unknown' : 'verified',
            'family_verified' => $family !== null,
            'cross_provider_equivalence_verified' => false,
            'package_equivalence_verified' => false,
        ];
    }

    private static function key(string $value): string
    {
        $value = str_replace(['Ё', 'ё'], 'е', $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/\+/', ' plus ', $value);
        $value = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
