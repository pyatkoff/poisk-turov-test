<?php
declare(strict_types=1);

final class AndromedaSurchargeGroupKey
{
    private const PREFIX = 'andromeda-surcharge-v1:';
    private const ID_PATTERN = '/^[A-Za-z0-9_-]{1,128}$/';
    private const CURRENCY_PATTERN = '/^[A-Z]{3}$/' ;

    /**
     * Build a fail-closed cache/group key for transport surcharge evidence.
     *
     * The key deliberately excludes hotel/room/meal/offer/search-price fields:
     * surcharge evidence may be reused only for the same supplier transport
     * identity and exact route/date/nights/party/currency context.
     *
     * @param array<string,mixed> $offer Normalized Andromeda PRICE offer.
     * @param array<string,mixed> $request Public search context.
     */
    public static function build(array $offer, array $request): ?string
    {
        $operator = self::id($offer['operator_ref'] ?? null);
        $program = self::optionalId($offer['program_ref'] ?? null);
        $tour = self::optionalId($offer['tour_ref'] ?? null);
        $spo = self::optionalId($offer['spo_ref'] ?? null);

        if ($operator === null || ($program === null && $tour === null && $spo === null)) {
            return null;
        }

        $departure = self::positiveInt($request['departureId'] ?? $request['departure_id'] ?? null);
        $country = self::positiveInt($request['countryId'] ?? $request['country_id'] ?? null);
        $date = self::date($offer['check_in'] ?? null);
        $nights = self::positiveInt($offer['nights'] ?? null);
        $adults = self::positiveInt($offer['adults'] ?? null);
        $children = self::nonNegativeInt($offer['children'] ?? null);
        $currency = self::currency($offer['currency'] ?? null);

        if ($departure === null || $country === null || $date === null || $nights === null
            || $adults === null || $children === null || $currency === null) {
            return null;
        }

        $canonical = [
            'operator' => $operator,
            'program' => $program,
            'tour' => $tour,
            'spo' => $spo,
            'departure' => $departure,
            'country' => $country,
            'date' => $date,
            'nights' => $nights,
            'adults' => $adults,
            'children' => $children,
            'currency' => $currency,
        ];

        return self::PREFIX . hash('sha256', json_encode(
            $canonical,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    private static function id(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value !== '' && preg_match(self::ID_PATTERN, $value) === 1 ? $value : null;
    }

    private static function optionalId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return self::id($value);
    }

    private static function positiveInt(mixed $value): ?int
    {
        $value = self::integer($value);
        return $value !== null && $value > 0 ? $value : null;
    }

    private static function nonNegativeInt(mixed $value): ?int
    {
        $value = self::integer($value);
        return $value !== null && $value >= 0 ? $value : null;
    }

    private static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        return null;
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year) ? $value : null;
    }

    private static function currency(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtoupper(trim($value));
        return preg_match(self::CURRENCY_PATTERN, $value) === 1 ? $value : null;
    }
}
