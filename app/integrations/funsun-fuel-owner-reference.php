<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Owner-provided FUN&SUN fuel reference captured 2026-09-23.
 *
 * This is a pure reference/resolver. It performs no supplier I/O, storage writes,
 * FX conversion, DTO mutation or final-price verification. Exact transport facts
 * resolved here are intended to sit above program/direction fallbacks.
 */
final class AnyTourFunSunFuelOwnerReferenceV1
{
    private const REFERENCE_DATE = '2026-09-23';

    public static function leg(
        string $operator,
        string $destination,
        string $flight,
        string $travelDate,
        ?string $carrier = null
    ): ?array {
        if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) !== 'fun_and_sun') return null;
        $destination = self::destination($destination);
        $flight = self::flight($flight);
        $date = self::date($travelDate);
        $weekday = (int)$date->format('N');
        $carrierFamily = self::carrier($carrier);

        foreach (self::rules() as $rule) {
            if ($rule['destination'] !== $destination) continue;
            if (isset($rule['flight']) && $rule['flight'] !== $flight) continue;
            if (isset($rule['flight_set']) && !in_array($flight, $rule['flight_set'], true)) continue;
            if (isset($rule['prefix']) && !str_starts_with($flight, $rule['prefix'])) continue;
            if (isset($rule['carrier']) && $rule['carrier'] !== $carrierFamily) continue;
            if ($rule['from'] !== null && $travelDate < $rule['from']) continue;
            if ($rule['to'] !== null && $travelDate > $rule['to']) continue;
            if ($rule['weekdays'] !== null && !in_array($weekday, $rule['weekdays'], true)) continue;

            return [
                'schema_version' => 1,
                'kind' => 'funsun_fuel_reference_leg',
                'operator_family' => 'fun_and_sun',
                'source' => 'owner_reference',
                'reference_date' => self::REFERENCE_DATE,
                'destination' => $destination,
                'flight_number' => $flight,
                'travel_date' => $travelDate,
                'iso_weekday' => $weekday,
                'amount' => $rule['amount'],
                'currency' => $rule['currency'],
                'unit' => 'per_person_one_way',
                'base_relation' => 'excluded',
                'valid_from' => $rule['from'],
                'valid_to' => $rule['to'],
            ];
        }
        return null;
    }

    public static function roundTrip(
        string $operator,
        string $destination,
        string $outboundFlight,
        string $outboundDate,
        string $returnFlight,
        string $returnDate,
        array $party,
        ?string $outboundCarrier = null,
        ?string $returnCarrier = null
    ): ?array {
        $outbound = self::leg($operator, $destination, $outboundFlight, $outboundDate, $outboundCarrier);
        $return = self::leg($operator, $destination, $returnFlight, $returnDate, $returnCarrier);
        if ($outbound === null || $return === null || $outbound['currency'] !== $return['currency']) return null;

        $party = self::party($party);
        $eligible = $party['adults'];
        $infants = 0;
        foreach ($party['child_ages'] as $age) {
            if ($age < 2) ++$infants;
            else ++$eligible;
        }
        if ($eligible < 1) return null;

        $perPassenger = self::cents($outbound['amount']) + self::cents($return['amount']);
        if ($perPassenger > intdiv(PHP_INT_MAX, $eligible)) return null;
        $partyTotal = $perPassenger * $eligible;

        return [
            'schema_version' => 1,
            'kind' => 'funsun_fuel_reference_roundtrip',
            'operator_family' => 'fun_and_sun',
            'source' => 'owner_reference',
            'reference_date' => self::REFERENCE_DATE,
            'destination' => self::destination($destination),
            'currency' => $outbound['currency'],
            'unit' => 'per_person_route_sum',
            'base_relation' => 'excluded',
            'eligible_passenger_count' => $eligible,
            'infant_count' => $infants,
            'per_eligible_passenger_roundtrip_amount' => self::money($perPassenger),
            'party_fuel_native_total' => self::money($partyTotal),
            'legs' => ['outbound'=>$outbound, 'return'=>$return],
            'infant_reference' => self::infantReference($destination),
        ];
    }

    public static function infantReference(string $destination): ?array
    {
        $destination = self::destination($destination);
        $rule = match ($destination) {
            'TR-AYT', 'TR-DLM', 'TR-BJV' => ['amount'=>'35.00','currency'=>'EUR','unit'=>'per_infant_unspecified'],
            'EG-SSH', 'EG-HRG' => ['amount'=>'70.00','currency'=>'USD','unit'=>'per_infant_one_way'],
            'TH', 'VN' => ['amount'=>'100.00','currency'=>'USD','unit'=>'per_infant_unspecified'],
            default => null,
        };
        if ($rule === null) return null;
        return [
            'schema_version'=>1,
            'kind'=>'infant_boarding_reference',
            'operator_family'=>'fun_and_sun',
            'source'=>'owner_reference',
            'reference_date'=>self::REFERENCE_DATE,
            'destination'=>$destination,
            'amount'=>$rule['amount'],
            'currency'=>$rule['currency'],
            'unit'=>$rule['unit'],
            'separate_from_fuel'=>true,
        ];
    }

    private static function rules(): array
    {
        return [
            // Turkey — Antalya.
            self::r('TR-AYT','ZF3001','2026-09-19','2026-10-03',[6],'80.00','EUR'),
            self::r('TR-AYT','ZF3001','2026-09-19','2026-10-03',[1,2,3,4,5,7],'70.00','EUR'),
            self::r('TR-AYT','ZF3001','2026-10-04','2026-10-31',null,'70.00','EUR'),
            self::r('TR-AYT','ZF3002','2026-09-26','2026-10-31',[2],'80.00','EUR'),
            self::r('TR-AYT','ZF3002','2026-09-26','2026-10-31',[1,3,4,5,6,7],'70.00','EUR'),
            self::r('TR-AYT','ZF3003','2026-09-19','2026-10-31',[1],'70.00','EUR'),
            self::r('TR-AYT','ZF3003','2026-09-19','2026-10-31',[2,3,4,5,6,7],'80.00','EUR'),
            self::r('TR-AYT','ZF3004','2026-09-26','2026-10-30',null,'70.00','EUR'),
            self::r('TR-AYT','ZF3005','2026-09-19','2026-10-31',[1,2,4,5,7],'70.00','EUR'),
            self::r('TR-AYT','ZF3005','2026-09-19','2026-10-31',[3,6],'80.00','EUR'),
            self::r('TR-AYT','ZF3006','2026-09-26','2026-10-30',null,'70.00','EUR'),
            self::r('TR-AYT','ZF3007','2026-09-19','2026-10-30',null,'70.00','EUR'),
            self::r('TR-AYT','ZF3008','2026-09-26','2026-10-30',null,'80.00','EUR'),
            self::r('TR-AYT','ZF3009','2026-09-22','2026-10-30',null,'70.00','EUR'),
            self::r('TR-AYT','ZF3010','2026-09-10','2026-10-30',null,'80.00','EUR'),
            self::r('TR-AYT','ZF3013','2026-04-01','2026-10-30',null,'60.00','EUR'),
            self::r('TR-AYT','ZF3014','2026-09-26','2026-10-30',null,'70.00','EUR'),

            self::r('TR-AYT','S73739','2026-04-01','2027-03-29',null,'150.00','EUR'),
            self::r('TR-AYT','S73740','2026-04-01','2027-03-29',null,'150.00','EUR'),
            self::r('TR-AYT','S73745','2026-09-19','2027-03-29',null,'150.00','EUR'),
            self::r('TR-AYT','S73746','2026-09-26','2027-03-29',null,'150.00','EUR'),
            self::r('TR-AYT','S73747','2026-09-27','2027-03-29',null,'130.00','EUR'),
            self::r('TR-AYT','S73748','2026-09-10','2027-03-29',null,'150.00','EUR'),

            self::r('TR-AYT','PC1577','2026-09-19','2027-03-28',null,'110.00','EUR'),
            self::r('TR-AYT','PC1576','2026-09-26','2027-03-28',null,'110.00','EUR'),
            self::r('TR-AYT','PC1581','2026-09-19','2027-03-28',null,'110.00','EUR'),
            self::r('TR-AYT','PC1580','2026-09-26','2027-03-28',null,'110.00','EUR'),

            self::r('TR-AYT','U61555','2026-09-10','2026-11-09',null,'80.00','EUR'),
            self::r('TR-AYT','U61556','2026-09-26','2026-11-08',[1,2,3,4,6,7],'100.00','EUR'),
            self::r('TR-AYT','U61556','2026-09-26','2026-11-08',[5],'80.00','EUR'),
            self::r('TR-AYT','U63555','2026-09-19','2027-10-30',[1,2,4,5,6],'80.00','EUR'),
            self::r('TR-AYT','U63555','2026-09-19','2027-10-30',[3,7],'70.00','EUR'),
            self::r('TR-AYT','U63556','2026-09-26','2027-10-30',[1,2,3,5,6,7],'70.00','EUR'),
            self::r('TR-AYT','U63556','2026-09-26','2027-10-30',[4],'80.00','EUR'),
            self::r('TR-AYT','U63557','2026-09-19','2027-10-30',null,'80.00','EUR'),
            self::r('TR-AYT','U63558','2026-09-10','2026-11-08',null,'80.00','EUR'),
            self::r('TR-AYT','U63558','2026-11-09','2027-10-30',null,'70.00','EUR'),
            self::r('TR-AYT','U63559','2026-09-19','2026-11-06',[1],'70.00','EUR'),
            self::r('TR-AYT','U63559','2026-09-19','2026-11-06',[2,3,4,5,6,7],'80.00','EUR'),
            self::r('TR-AYT','U63560','2026-09-26','2026-11-08',null,'80.00','EUR'),
            self::r('TR-AYT','U63571','2026-09-04','2026-11-12',null,'80.00','EUR'),
            self::r('TR-AYT','U63572','2026-09-26','2026-12-31',null,'70.00','EUR'),
            self::r('TR-AYT','U63572','2027-01-01','2027-12-31',null,'80.00','EUR'),

            self::many('TR-AYT',['XC9301','XC9305','XC9315','XC9111','XC9113','XC9115','XC9117'],'2026-09-19','2027-10-25','110.00','EUR'),
            self::many('TR-AYT',['XC9302','XC9306','XC9316','XC9112','XC9114','XC9116','XC9118'],'2026-09-26','2027-10-25','110.00','EUR'),

            self::r('TR-AYT','Y77101','2026-09-04','2026-10-31',null,'100.00','EUR'),
            self::r('TR-AYT','Y77102','2026-09-10','2026-10-31',null,'100.00','EUR'),
            self::r('TR-AYT','TK3173','2026-09-19','2026-10-31',null,'105.00','EUR'),
            self::r('TR-AYT','TK3083','2026-09-19','2026-10-24',null,'85.00','EUR'),
            self::r('TR-AYT','TK3172','2026-09-26','2026-10-31',null,'105.00','EUR'),
            self::r('TR-AYT','TK3082','2026-09-26','2026-10-24',null,'85.00','EUR'),

            // Turkey — Dalaman.
            self::r('TR-DLM','PC1459','2026-09-17','2026-10-20',null,'80.00','EUR'),
            self::r('TR-DLM','PC1458','2026-09-24','2026-10-20',null,'80.00','EUR'),
            self::r('TR-DLM','ZF411','2026-09-17','2026-10-30',null,'70.00','EUR'),

            // Turkey — Bodrum.
            self::r('TR-BJV','PC1457','2026-09-17','2026-10-20',null,'80.00','EUR'),
            self::r('TR-BJV','PC1456','2026-09-24','2026-10-20',null,'80.00','EUR'),
            self::r('TR-BJV','TK3123','2026-09-17','2026-10-31',[1,4],'125.00','EUR'),
            self::r('TR-BJV','TK3123','2026-09-17','2026-10-31',[2,3,5,6,7],'105.00','EUR'),
            self::r('TR-BJV','TK3122','2026-09-24','2026-10-31',[1,4],'125.00','EUR'),
            self::r('TR-BJV','TK3122','2026-09-24','2026-10-31',[2,3,5,6,7],'105.00','EUR'),
            self::r('TR-BJV','TK3027','2026-09-17','2026-10-31',null,'70.00','EUR'),
            self::r('TR-BJV','TK3026','2026-09-24','2026-10-31',null,'70.00','EUR'),

            // Egypt.
            self::r('EG-SSH','MS728','2026-05-18','2027-02-28',null,'50.00','USD'),
            self::r('EG-SSH','MS727','2026-05-18','2027-02-28',null,'50.00','USD'),
            self::r('EG-HRG','MS728','2026-05-18','2027-02-28',null,'50.00','USD'),
            self::r('EG-HRG','MS727','2026-05-18','2027-02-28',null,'50.00','USD'),

            // Thailand / Vietnam. Owner reference did not provide validity dates.
            self::prefix('TH','WZ','20.00','USD'),
            self::carrierRule('TH','azur','20.00','USD'),
            self::prefix('TH','ZF','20.00','USD'),
            self::prefix('VN','WZ','20.00','USD'),
            self::carrierRule('VN','azur','20.00','USD'),
            self::prefix('VN','ZF','20.00','USD'),
            self::carrierRule('VN','vietjet','20.00','USD'),
        ];
    }

    private static function r(
        string $destination,
        string $flight,
        ?string $from,
        ?string $to,
        ?array $weekdays,
        string $amount,
        string $currency
    ): array {
        return compact('destination','flight','from','to','weekdays','amount','currency');
    }

    private static function many(
        string $destination,
        array $flights,
        string $from,
        string $to,
        string $amount,
        string $currency
    ): array {
        return [
            'destination'=>$destination,
            'flight_set'=>$flights,
            'from'=>$from,'to'=>$to,'weekdays'=>null,'amount'=>$amount,'currency'=>$currency,
        ];
    }

    private static function prefix(string $destination, string $prefix, string $amount, string $currency): array
    {
        return ['destination'=>$destination,'prefix'=>$prefix,'from'=>null,'to'=>null,'weekdays'=>null,'amount'=>$amount,'currency'=>$currency];
    }

    private static function carrierRule(string $destination, string $carrier, string $amount, string $currency): array
    {
        return ['destination'=>$destination,'carrier'=>$carrier,'from'=>null,'to'=>null,'weekdays'=>null,'amount'=>$amount,'currency'=>$currency];
    }

    private static function destination(string $value): string
    {
        $value = strtoupper(trim($value));
        if (!in_array($value, ['TR-AYT','TR-DLM','TR-BJV','EG-SSH','EG-HRG','TH','VN'], true)) {
            throw new InvalidArgumentException('FUNSUN_FUEL_DESTINATION');
        }
        return $value;
    }

    private static function flight(string $value): string
    {
        $value = strtr(trim($value), ['Р'=>'P','р'=>'P','С'=>'C','с'=>'C']);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]/', '', $value);
        if (!is_string($value) || $value === '' || preg_match('/\A[A-Z0-9]{2,12}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('FUNSUN_FUEL_FLIGHT');
        }
        return $value;
    }

    private static function carrier(?string $value): ?string
    {
        if ($value === null || trim($value) === '') return null;
        $normalized = strtolower(trim($value));
        if (str_contains($normalized, 'azur')) return 'azur';
        if (str_contains($normalized, 'vietjet')) return 'vietjet';
        if (str_contains($normalized, 'red wings')) return 'red_wings';
        return null;
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('FUNSUN_FUEL_DATE');
        }
        return $date;
    }

    private static function party(array $party): array
    {
        if (!is_int($party['adults'] ?? null) || $party['adults'] < 1 || $party['adults'] > 9
            || !is_int($party['children'] ?? null) || $party['children'] < 0 || $party['children'] > 9
            || !is_array($party['child_ages'] ?? null) || !array_is_list($party['child_ages'])
            || count($party['child_ages']) !== $party['children']) {
            throw new InvalidArgumentException('FUNSUN_FUEL_PARTY');
        }
        $ages = $party['child_ages'];
        foreach ($ages as $age) {
            if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('FUNSUN_FUEL_PARTY');
        }
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$party['adults'],'children'=>$party['children'],'child_ages'=>$ages];
    }

    private static function cents(string $amount): int
    {
        if (preg_match('/\A(?:0|[1-9][0-9]{0,6})\.([0-9]{2})\z/D', $amount, $m) !== 1) {
            throw new InvalidArgumentException('FUNSUN_FUEL_MONEY');
        }
        [$whole] = explode('.', $amount, 2);
        return (int)$whole * 100 + (int)$m[1];
    }

    private static function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
