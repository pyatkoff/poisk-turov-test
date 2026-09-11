<?php
declare(strict_types=1);

/**
 * Provider-neutral search window and party facts.
 *
 * This contract validates only AnyTour's canonical input. It deliberately does
 * not claim that supplier date spans, night spans, or returned packages are
 * equivalent across Tourvisor, direct ANEX, and Andromeda.
 */
final class AnyTourThreeProviderSearchWindow
{
    public static function fromSearch(array $input): array
    {
        $keys = ['date_from', 'date_to', 'nights_from', 'nights_to', 'adults', 'child_ages'];
        if (!self::exactKeys($input, $keys)) {
            throw new InvalidArgumentException('THREE_PROVIDER_WINDOW_KEYS');
        }

        $dateFrom = self::date($input['date_from']);
        $dateTo = self::date($input['date_to']);
        if ($dateTo < $dateFrom) {
            throw new InvalidArgumentException('THREE_PROVIDER_WINDOW_DATE_ORDER');
        }

        $nightsFrom = self::integer($input['nights_from'], 1, 30, 'THREE_PROVIDER_WINDOW_NIGHTS');
        $nightsTo = self::integer($input['nights_to'], 1, 30, 'THREE_PROVIDER_WINDOW_NIGHTS');
        if ($nightsTo < $nightsFrom) {
            throw new InvalidArgumentException('THREE_PROVIDER_WINDOW_NIGHT_ORDER');
        }

        $adults = self::integer($input['adults'], 1, 9, 'THREE_PROVIDER_WINDOW_ADULTS');
        $ages = $input['child_ages'];
        if (!is_array($ages)
            || ($ages !== [] && array_keys($ages) !== range(0, count($ages) - 1))
            || count($ages) > 9) {
            throw new InvalidArgumentException('THREE_PROVIDER_WINDOW_CHILDREN');
        }
        foreach ($ages as $age) {
            self::integer($age, 0, 17, 'THREE_PROVIDER_WINDOW_CHILDREN');
        }

        return [
            'schema_version' => 1,
            'dates' => [
                'from' => $dateFrom->format('Y-m-d'),
                'to' => $dateTo->format('Y-m-d'),
                'inclusive_days' => (int)$dateFrom->diff($dateTo)->days + 1,
            ],
            'nights' => ['from' => $nightsFrom, 'to' => $nightsTo],
            'party' => [
                'adults' => $adults,
                'children' => count($ages),
                'child_ages' => $ages,
            ],
            'statuses' => [
                'canonical_input_shape' => 'verified',
                'ordered_child_ages' => 'verified',
                'supplier_date_span_support' => 'unknown',
                'supplier_night_span_support' => 'unknown',
                'cross_provider_result_equivalence' => 'unknown',
            ],
        ];
    }

    private static function date($value): DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/\A20[0-9]{2}-[0-9]{2}-[0-9]{2}\z/D', $value)) {
            throw new InvalidArgumentException('THREE_PROVIDER_WINDOW_DATE');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('THREE_PROVIDER_WINDOW_DATE');
        }
        return $date;
    }

    private static function integer($value, int $min, int $max, string $error): int
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
