<?php
declare(strict_types=1);

/**
 * Provider-neutral Search3 offer boundary.
 *
 * This is a pure source-only contract. It accepts the facts already returned by a
 * provider adapter, keeps provider/operator separate, and never infers identity,
 * performs money arithmetic, requests a supplier, or enables selection.
 */
final class AnyTourThreeProviderOfferContract
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const MEAL_FAMILIES = ['ro', 'bb', 'hb', 'fb', 'ai', 'uai'];

    public static function fromSearch(array $input): array
    {
        $expected = [
            'provider', 'operator', 'local_hotel_id', 'provider_hotel_ref', 'search_ref', 'offer_ref',
            'checkin', 'nights', 'adults', 'children', 'child_ages', 'meal', 'room', 'placement',
            'availability', 'search_price', 'fuel_charge_reported', 'additional_prices_reported',
            'observed_at',
        ];
        if (!self::exactKeys($input, $expected)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_KEYS');
        }

        $provider = $input['provider'];
        if (!is_string($provider) || !in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_PROVIDER');
        }

        $operator = self::text($input['operator'], 120, 'THREE_PROVIDER_OFFER_OPERATOR');
        $providerHotelRef = self::opaqueRef($input['provider_hotel_ref'], 'THREE_PROVIDER_OFFER_HOTEL_REF');
        $searchRef = self::opaqueRef($input['search_ref'], 'THREE_PROVIDER_OFFER_SEARCH_REF');
        $offerRef = self::opaqueRef($input['offer_ref'], 'THREE_PROVIDER_OFFER_REF');

        $local = $input['local_hotel_id'];
        if ($local !== null && (!is_int($local) || $local < 1 || $local > 999999999)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_LOCAL_ID');
        }

        $checkin = $input['checkin'];
        if (!is_string($checkin)
            || !preg_match('/\A20[0-9]{2}-[0-9]{2}-[0-9]{2}\z/D', $checkin)
            || !self::validDate($checkin)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_DATE');
        }

        foreach (['nights' => [1, 30], 'adults' => [1, 9], 'children' => [0, 9]] as $key => [$min, $max]) {
            if (!is_int($input[$key]) || $input[$key] < $min || $input[$key] > $max) {
                throw new InvalidArgumentException('THREE_PROVIDER_OFFER_PARTY');
            }
        }
        $ages = $input['child_ages'];
        if (!is_array($ages) || ($ages !== [] && array_keys($ages) !== range(0, count($ages) - 1))
            || count($ages) !== $input['children']) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_AGES');
        }
        foreach ($ages as $age) {
            if (!is_int($age) || $age < 0 || $age > 17) {
                throw new InvalidArgumentException('THREE_PROVIDER_OFFER_AGES');
            }
        }

        $meal = self::meal($input['meal']);
        $room = self::labelPair($input['room'], 'THREE_PROVIDER_OFFER_ROOM');
        $placement = $input['placement'] === null
            ? null
            : self::labelPair($input['placement'], 'THREE_PROVIDER_OFFER_PLACEMENT');

        if (!is_array($input['availability'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_AVAILABILITY');
        }
        $availability = AnyTourThreeProviderAvailability::fromSearch($provider, $input['availability']);
        $flight = AnyTourThreeProviderFlightDetails::forSearch($provider);
        $observedAt = $input['observed_at'];
        if (!is_string($observedAt)
            || !preg_match('/\A20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D', $observedAt)) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_TIMESTAMP');
        }

        $money = AnyTourThreeProviderMoneyFacts::fromSearch(
            $provider,
            $input['search_price'],
            $input['fuel_charge_reported'],
            $input['additional_prices_reported']
        );

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'operator' => $operator,
            'local_hotel_id' => $local,
            'identity' => [
                'search_ref_digest' => hash('sha256', $searchRef),
                'offer_ref_digest' => hash('sha256', $offerRef),
                'provider_hotel_ref_digest' => hash('sha256', $providerHotelRef),
            ],
            'checkin' => $checkin,
            'nights' => $input['nights'],
            'party' => [
                'adults' => $input['adults'],
                'children' => $input['children'],
                'child_ages' => $ages,
            ],
            'meal' => $meal,
            'room' => $room,
            'placement' => $placement,
            'availability' => $availability,
            'flight_details' => $flight,
            'money' => $money,
            'observed_at' => $observedAt,
            'quote_state' => 'unknown',
            'final_price_verified' => false,
            'selection_state' => 'disabled',
        ];
    }

    private static function meal($value): array
    {
        if (!is_array($value) || !self::exactKeys($value, ['raw', 'family', 'qualifiers'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_MEAL');
        }
        $raw = self::text($value['raw'], 120, 'THREE_PROVIDER_OFFER_MEAL');
        $family = $value['family'];
        if ($family !== null && (!is_string($family) || !in_array($family, self::MEAL_FAMILIES, true))) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_MEAL');
        }
        if (!is_array($value['qualifiers']) || !self::exactKeys($value['qualifiers'], ['plus', 'without_alcohol'])
            || !is_bool($value['qualifiers']['plus']) || !is_bool($value['qualifiers']['without_alcohol'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_OFFER_MEAL');
        }
        return [
            'raw' => $raw,
            'family' => $family,
            'qualifiers' => $value['qualifiers'],
            'family_verified' => $family !== null,
        ];
    }

    private static function labelPair($value, string $error): array
    {
        if (!is_array($value) || !self::exactKeys($value, ['raw', 'normalized'])
            || !is_string($value['raw']) || !is_string($value['normalized'])) {
            throw new InvalidArgumentException($error);
        }
        $raw = self::text($value['raw'], 180, $error);
        $normalized = self::text($value['normalized'], 180, $error);
        if ($normalized === '') {
            throw new InvalidArgumentException($error);
        }
        return ['raw' => $raw, 'normalized' => $normalized];
    }

    private static function opaqueRef($value, string $error): string
    {
        return self::text($value, 240, $error);
    }

    private static function text($value, int $max, string $error): string
    {
        if (!is_string($value) || trim($value) === ''
            || (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > $max) {
            throw new InvalidArgumentException($error);
        }
        return trim($value);
    }

    private static function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
