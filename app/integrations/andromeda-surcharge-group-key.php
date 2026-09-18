<?php
declare(strict_types=1);

final class AndromedaSurchargeGroupKey
{
    private const PREFIX = 'andromeda-surcharge-v2:';
    private const ID_PATTERN = '/^[A-Za-z0-9_-]{1,128}$/';
    private const CURRENCY_PATTERN = '/^[A-Z]{3}$/';

    /**
     * Build a fail-closed cache/group key for external-freight surcharge evidence.
     *
     * Money experiment v1 proved SPO-invariance for the observed supplier contract,
     * so spo_ref is deliberately outside the key. Nights/tour/route/date/party are
     * retained until isolated evidence proves that they can be removed safely.
     * Hotel/room/meal/offer/search-price fields are presentation/package variants,
     * not transport-group discriminators.
     *
     * @param array<string,mixed> $offer Normalized Andromeda PRICE offer.
     * @param array<string,mixed> $request Public search request or its params block.
     */
    public static function build(array $offer, array $request): ?string
    {
        if (($offer['provider'] ?? null) !== 'andromeda') {
            return null;
        }

        $operator = self::id($offer['operator_ref'] ?? null);
        $transport = $offer['transport_context'] ?? null;
        $price = $offer['price'] ?? null;
        if ($operator === null || !is_array($transport) || !is_array($price)
            || ($transport['freight_external'] ?? null) !== true) {
            return null;
        }

        // Program identity is mandatory for reusable surcharge evidence.
        $program = self::id($transport['program_ref'] ?? null);
        if ($program === null) {
            return null;
        }

        $tour = null;
        if (array_key_exists('tour_ref', $transport)
            && $transport['tour_ref'] !== null && $transport['tour_ref'] !== '') {
            $tour = self::id($transport['tour_ref']);
            if ($tour === null) {
                return null;
            }
        }

        $scope = is_array($request['params'] ?? null) ? $request['params'] : $request;
        $departure = self::positiveInt($scope['departureId'] ?? $scope['departure_id'] ?? null);
        $country = self::positiveInt($scope['countryId'] ?? $scope['country_id'] ?? null);
        $date = self::date($offer['check_in'] ?? null);
        $nights = self::positiveInt($offer['nights'] ?? null);
        $adults = self::positiveInt($offer['adults'] ?? null);
        $children = self::nonNegativeInt($offer['children'] ?? null);
        $currency = self::currency($price['currency'] ?? null);

        if ($departure === null || $country === null || $date === null || $nights === null
            || $adults === null || $children === null || $currency === null) {
            return null;
        }

        $canonical = [
            'operator' => $operator,
            'program' => $program,
            'tour' => $tour,
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
