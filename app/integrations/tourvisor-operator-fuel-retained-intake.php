<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Converts already retained Tourvisor tour + flights payloads into the existing
 * operator-fuel evidence contract. This class performs no HTTP or persistence.
 */
final class AnyTourTourvisorOperatorFuelRetainedIntakeV1
{
    /**
     * Compile an already-retained normalized Tourvisor search row together with a
     * retained/default flights response. This avoids a redundant /tours/{id} HTTP
     * request when the search response already carried the same operator/date/party/
     * currency/fuel facts. Exact flight scope still comes only from /flights.
     */
    public static function observationFromSearchRow(array $retained): array
    {
        $row = self::map($retained['search_row'] ?? null, 'TV_FUEL_SEARCH_ROW');
        $tourId = self::identifier($retained['tour_id'] ?? null, 'TV_FUEL_TOUR_ID');
        if (self::identifier($row['tour_id'] ?? null, 'TV_FUEL_TOUR_ID') !== $tourId) {
            throw new InvalidArgumentException('TV_FUEL_TOUR_ID');
        }
        $party = self::party($retained['party'] ?? null);
        if (($row['adults'] ?? null) !== $party['adults'] || ($row['children'] ?? null) !== $party['children']) {
            throw new InvalidArgumentException('TV_FUEL_PARTY');
        }
        $operator = self::label($row['operator_name'] ?? null, 'TV_FUEL_OPERATOR');
        if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) === null) {
            throw new InvalidArgumentException('TV_FUEL_OPERATOR');
        }
        $fuel = self::money($row['fuel_charge'] ?? null);
        if ((float)$fuel <= 0.0) throw new InvalidArgumentException('TV_FUEL_AMOUNT');
        $currency = self::currency($row['currency'] ?? null);
        $date = self::date($row['date'] ?? null, 'TV_FUEL_DATE');
        $searchDigest = self::digest($retained['search_response_sha256'] ?? null, 'TV_FUEL_SOURCE_DIGEST');

        $copy = $retained;
        unset($copy['search_row'], $copy['search_response_sha256']);
        $copy['tour'] = [
            'id' => $tourId,
            'adults' => $party['adults'],
            'childs' => $party['children'],
            'currency' => $currency,
            'date' => $date,
            'fuelCharge' => $fuel,
            'operator' => ['name' => $operator],
        ];
        // observation() needs one source digest paired with the flights digest. The
        // digest is provenance only; here it intentionally identifies the search
        // response instead of pretending that a /tours/{id} response was fetched.
        $copy['tour_response_sha256'] = $searchDigest;
        return self::observation($copy);
    }

    public static function observation(array $retained): array
    {
        $tour = self::map($retained['tour'] ?? null, 'TV_FUEL_TOUR');
        $response = self::map($retained['flights'] ?? null, 'TV_FUEL_FLIGHTS');
        $tourId = self::identifier($retained['tour_id'] ?? null, 'TV_FUEL_TOUR_ID');
        if (self::identifier($tour['id'] ?? null, 'TV_FUEL_TOUR_ID') !== $tourId) {
            throw new InvalidArgumentException('TV_FUEL_TOUR_ID');
        }

        $operator = self::operator($tour['operator'] ?? null);
        if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) === null) {
            throw new InvalidArgumentException('TV_FUEL_OPERATOR');
        }
        $party = self::party($retained['party'] ?? null);
        if (($tour['adults'] ?? null) !== $party['adults'] || ($tour['childs'] ?? null) !== $party['children']) {
            throw new InvalidArgumentException('TV_FUEL_PARTY');
        }

        if (isset($response['error']) && $response['error'] !== null && $response['error'] !== []) {
            throw new InvalidArgumentException('TV_FUEL_RESPONSE_ERROR');
        }
        $combinations = $response['flights'] ?? null;
        if (!is_array($combinations) || !array_is_list($combinations)) {
            throw new InvalidArgumentException('TV_FUEL_COMBINATIONS');
        }
        $defaults = array_values(array_filter(
            $combinations,
            static fn(mixed $row): bool => is_array($row) && ($row['isDefault'] ?? null) === true
        ));
        if (count($defaults) !== 1) throw new InvalidArgumentException('TV_FUEL_DEFAULT');
        $selected = $defaults[0];

        $forward = self::singleSegment($selected['forward'] ?? null, 'TV_FUEL_FORWARD');
        $backward = self::singleSegment($selected['backward'] ?? null, 'TV_FUEL_BACKWARD');
        $dateForward = self::date($selected['dateForward'] ?? null, 'TV_FUEL_DATE');
        $dateBackward = self::date($selected['dateBackward'] ?? null, 'TV_FUEL_DATE');
        if (self::date($tour['date'] ?? null, 'TV_FUEL_DATE') !== $dateForward
            || self::date($forward['departure']['date'] ?? null, 'TV_FUEL_DATE') !== $dateForward
            || self::date($backward['departure']['date'] ?? null, 'TV_FUEL_DATE') !== $dateBackward
            || $dateForward > $dateBackward) {
            throw new InvalidArgumentException('TV_FUEL_DATE');
        }

        $fuel = self::map($selected['fuelCharge'] ?? null, 'TV_FUEL_AMOUNT');
        $amount = self::money($fuel['value'] ?? null);
        if ((float)$amount <= 0.0) throw new InvalidArgumentException('TV_FUEL_AMOUNT');
        $currency = self::currency($fuel['currency'] ?? null);

        /* A positive tour-level value is a second statement about the same party
         * amount. Null/zero means the search/detail response did not expose it;
         * the newer flights actualization remains the source of this observation. */
        if (array_key_exists('fuelCharge', $tour) && $tour['fuelCharge'] !== null) {
            $tourFuel = self::money($tour['fuelCharge']);
            if ((float)$tourFuel > 0.0 && $tourFuel !== $amount) {
                throw new DomainException('TV_FUEL_AMOUNT_CONFLICT');
            }
        }
        if (self::currency($tour['currency'] ?? null) !== $currency) {
            throw new DomainException('TV_FUEL_CURRENCY_CONFLICT');
        }

        $info = self::map($response['info'] ?? null, 'TV_FUEL_INFO');
        $flags = self::map($info['flags'] ?? null, 'TV_FUEL_FLAGS');
        foreach (['noFlight','noInsurance','noMeal','noTransfer'] as $flag) {
            if (($flags[$flag] ?? null) !== false) throw new InvalidArgumentException('TV_FUEL_REQUIRED_CHARGES');
        }
        if (($info['surcharges'] ?? null) !== []) throw new InvalidArgumentException('TV_FUEL_REQUIRED_CHARGES');

        $tourDigest = self::digest($retained['tour_response_sha256'] ?? null, 'TV_FUEL_SOURCE_DIGEST');
        $flightsDigest = self::digest($retained['flights_response_sha256'] ?? null, 'TV_FUEL_SOURCE_DIGEST');
        $observedAt = self::positiveInt($retained['observed_at'] ?? null, 'TV_FUEL_TIME');
        $expiresAt = self::positiveInt($retained['expires_at'] ?? null, 'TV_FUEL_TIME');
        if ($expiresAt <= $observedAt) throw new InvalidArgumentException('TV_FUEL_TIME');

        $raw = [
            'provider' => 'tourvisor',
            'operator' => $operator,
            'scope' => [
                'market' => self::label($retained['market'] ?? null, 'TV_FUEL_MARKET'),
                'outbound' => self::leg($forward),
                'return' => self::leg($backward),
                'party' => $party,
            ],
            'unit' => 'party_roundtrip',
            'base_relation' => 'included',
            'amount' => $amount,
            'currency' => $currency,
            'source' => 'tourvisor_flights_fuel',
            'observed_at' => $observedAt,
            'expires_at' => $expiresAt,
            'valid_from' => self::date($retained['valid_from'] ?? null, 'TV_FUEL_PERIOD'),
            'valid_to' => self::date($retained['valid_to'] ?? null, 'TV_FUEL_PERIOD'),
            'base_includes_other_required_charges' => true,
            'offer_ref_digest' => AnyTourOperatorFuelRuleEvidenceV1::hash([
                'provider'=>'tourvisor', 'tour_id'=>$tourId, 'operator'=>$operator,
                'market'=>$retained['market'] ?? null, 'party'=>$party,
            ]),
            'source_response_sha256' => AnyTourOperatorFuelRuleEvidenceV1::hash([
                'tour'=>$tourDigest, 'flights'=>$flightsDigest,
            ]),
        ];
        $raw['evidence_sha256'] = AnyTourOperatorFuelRuleEvidenceV1::hash([
            'source_response_sha256'=>$raw['source_response_sha256'],
            'tour_id'=>$tourId,
            'selected_flight'=>AnyTourOperatorFuelRuleEvidenceV1::hash($selected),
            'amount'=>$amount,
            'currency'=>$currency,
        ]);

        return AnyTourOperatorFuelRuleEvidenceV1::observation($raw);
    }

    private static function singleSegment(mixed $value, string $reason): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) !== 1 || !is_array($value[0])) {
            throw new InvalidArgumentException($reason);
        }
        return $value[0];
    }

    private static function leg(array $segment): array
    {
        $departure = self::map($segment['departure'] ?? null, 'TV_FUEL_LEG');
        $arrival = self::map($segment['arrival'] ?? null, 'TV_FUEL_LEG');
        $company = self::map($segment['company'] ?? null, 'TV_FUEL_LEG');
        return [
            'origin' => self::code($departure['port'] ?? null, 'TV_FUEL_PORT'),
            'destination' => self::code($arrival['port'] ?? null, 'TV_FUEL_PORT'),
            'carrier' => self::firstLabel([$company['id'] ?? null, $company['name'] ?? null], 'TV_FUEL_CARRIER'),
            'flight' => self::label($segment['number'] ?? null, 'TV_FUEL_FLIGHT'),
        ];
    }

    private static function code(mixed $port, string $reason): string
    {
        $port = self::map($port, $reason);
        return self::firstLabel([$port['shortName'] ?? null, $port['id'] ?? null], $reason);
    }

    private static function operator(mixed $value): string
    {
        $value = self::map($value, 'TV_FUEL_OPERATOR');
        $labels = [$value['russianName'] ?? null, $value['name'] ?? null, $value['fullName'] ?? null];
        $families = [];
        $chosen = null;
        foreach ($labels as $label) {
            if (!is_string($label) || trim($label) === '') continue;
            $label = self::label($label, 'TV_FUEL_OPERATOR');
            $family = AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($label);
            if ($family !== null) {
                $families[$family] = true;
                $chosen ??= $label;
            }
        }
        if ($chosen === null || count($families) !== 1) throw new InvalidArgumentException('TV_FUEL_OPERATOR');
        return $chosen;
    }

    private static function party(mixed $value): array
    {
        $value = self::map($value, 'TV_FUEL_PARTY');
        $adults = $value['adults'] ?? null;
        $children = $value['children'] ?? null;
        $ages = $value['child_ages'] ?? null;
        if (!is_int($adults) || !is_int($children) || !is_array($ages) || !array_is_list($ages)
            || count($ages) !== $children || $adults < 1 || $children < 0) {
            throw new InvalidArgumentException('TV_FUEL_PARTY');
        }
        foreach ($ages as $age) if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('TV_FUEL_PARTY');
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$adults,'children'=>$children,'child_ages'=>$ages];
    }

    private static function map(mixed $value, string $reason): array
    {
        if (!is_array($value) || array_is_list($value)) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function firstLabel(array $values, string $reason): string
    {
        foreach ($values as $value) if (is_string($value) && trim($value) !== '') return self::label($value, $reason);
        throw new InvalidArgumentException($reason);
    }

    private static function label(mixed $value, string $reason): string
    {
        if (!is_string($value) || trim($value) !== $value || $value === '' || strlen($value) > 80
            || preg_match('/[\x00-\x1F\x7F*]/', $value)) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function identifier(mixed $value, string $reason): string
    {
        if (is_int($value) && $value > 0) return (string)$value;
        if (!is_string($value) || preg_match('/\A[1-9][0-9]{0,31}\z/D', $value) !== 1) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function money(mixed $value): string
    {
        if (is_int($value) && $value >= 0) return (string)$value;
        if (is_float($value) && is_finite($value) && $value >= 0 && abs($value - round($value, 2)) < 0.0000001) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value) === 1) return $value;
        throw new InvalidArgumentException('TV_FUEL_AMOUNT');
    }

    private static function currency(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[A-Z]{3}\z/D', $value) !== 1) throw new InvalidArgumentException('TV_FUEL_CURRENCY');
        return $value;
    }

    private static function date(mixed $value, string $reason): string
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC')) : false;
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function digest(mixed $value, string $reason): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function positiveInt(mixed $value, string $reason): int
    {
        if (!is_int($value) || $value < 1) throw new InvalidArgumentException($reason);
        return $value;
    }
}
