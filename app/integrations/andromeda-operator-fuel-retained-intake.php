<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';
require_once __DIR__ . '/operator-fuel-rule-store.php';
require_once __DIR__ . '/andromeda-search-surcharge.php';

/**
 * Saved-only SAMO/Andromeda fuel intake.
 *
 * It consumes an already retained get_flights/package response and emits the same
 * operator-fuel observation used by #3448. No supplier I/O and no second store.
 * Unknown unit/inclusion is retained as evidence but cannot become an arithmetic rule.
 */
final class AnyTourAndromedaOperatorFuelRetainedIntakeV1
{
    public static function observation(array $retained): array
    {
        $offer = self::map($retained['offer'] ?? null, 'ANDROMEDA_FUEL_OFFER');
        if (($offer['provider'] ?? null) !== 'andromeda') throw new InvalidArgumentException('ANDROMEDA_FUEL_PROVIDER');
        $operator = self::label($offer['operator'] ?? null, 'ANDROMEDA_FUEL_OPERATOR');
        if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) === null) {
            throw new InvalidArgumentException('ANDROMEDA_FUEL_OPERATOR');
        }
        $offerRef = $offer['offer_ref'] ?? null;
        if (!is_string($offerRef) || preg_match('/^offer_[a-f0-9]{64}$/D', $offerRef) !== 1) {
            throw new InvalidArgumentException('ANDROMEDA_FUEL_OFFER_REF');
        }

        $party = self::party($retained['party'] ?? null);
        if (($offer['adults'] ?? null) !== $party['adults'] || ($offer['children'] ?? null) !== $party['children']) {
            throw new InvalidArgumentException('ANDROMEDA_FUEL_PARTY');
        }

        $claim = self::map($retained['claim'] ?? null, 'ANDROMEDA_FUEL_CLAIM');
        $doc = self::document($claim);
        $fuel = self::fuelRows($doc);
        $legs = self::exactFlightPair($claim);

        $currency = $fuel['currency'];
        $sum = self::addMoney($fuel['outbound']['amount'], $fuel['return']['amount']);
        $unit = self::unit($fuel['outbound']['unit'], $fuel['return']['unit'], $fuel['outbound']['amount'], $fuel['return']['amount']);
        // A per-person one-way observation stores the native rate for ONE leg.
        // The two equal route rows corroborate that rate; multiplying by party and
        // directions belongs to the target pricing handoff, not retained evidence.
        $amount = $unit === 'per_person_one_way' ? $fuel['outbound']['amount'] : $sum;
        $relation = self::relation($fuel['outbound']['included'], $fuel['return']['included']);
        $otherRequiredClear = self::otherRequiredChargesClear($doc);

        $observedAt = self::positiveInt($retained['observed_at'] ?? null, 'ANDROMEDA_FUEL_TIME');
        $expiresAt = self::positiveInt($retained['expires_at'] ?? null, 'ANDROMEDA_FUEL_TIME');
        if ($expiresAt <= $observedAt) throw new InvalidArgumentException('ANDROMEDA_FUEL_TIME');
        $validFrom = self::date($retained['valid_from'] ?? null, 'ANDROMEDA_FUEL_PERIOD');
        $validTo = self::date($retained['valid_to'] ?? null, 'ANDROMEDA_FUEL_PERIOD');
        if ($validFrom > $validTo) throw new InvalidArgumentException('ANDROMEDA_FUEL_PERIOD');
        $responseDigest = self::digest($retained['source_response_sha256'] ?? null, 'ANDROMEDA_FUEL_SOURCE_DIGEST');
        $exchange = null;
        if ($currency !== 'RUB') {
            $rate = AnyTourAndromedaSearchSurcharge::directExchangeRate($claim, $currency, 'RUB');
            if ($rate !== null) {
                $fxExpires = min($expiresAt, $observedAt + 86400);
                if ($fxExpires > $observedAt) {
                    $exchange = [
                        'from'=>$currency,
                        'to'=>'RUB',
                        'rate'=>$rate,
                        'source'=>'andromeda_claim_money',
                        'observed_at'=>$observedAt,
                        'expires_at'=>$fxExpires,
                        'evidence_sha256'=>AnyTourOperatorFuelRuleEvidenceV1::hash([
                            'source_response_sha256'=>$responseDigest,
                            'from'=>$currency,
                            'to'=>'RUB',
                            'rate'=>$rate,
                            'source'=>'andromeda_claim_money',
                        ]),
                    ];
                }
            }
        }

        $raw = [
            'provider' => 'andromeda',
            'operator' => $operator,
            'scope' => [
                'market' => self::label($retained['market'] ?? null, 'ANDROMEDA_FUEL_MARKET'),
                'outbound' => $legs['0'],
                'return' => $legs['1'],
                'party' => $party,
            ],
            'unit' => $unit,
            'base_relation' => $relation,
            'amount' => $amount,
            'currency' => $currency,
            'source' => 'andromeda_claim_service',
            'observed_at' => $observedAt,
            'expires_at' => $expiresAt,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'base_includes_other_required_charges' => $otherRequiredClear,
            'offer_ref_digest' => AnyTourOperatorFuelRuleEvidenceV1::hash([
                'provider'=>'andromeda',
                'offer_ref'=>$offerRef,
                'operator'=>$operator,
                'party'=>$party,
            ]),
            'source_response_sha256' => $responseDigest,
        ];
        if (array_key_exists('direction', $retained)) {
            // When the saved search owner knows canonical Search3 origin/destination
            // identity, use it so retained evidence and mass autosave share one key.
            $raw['direction'] = $retained['direction'];
        }
        if ($exchange !== null) $raw['exchange'] = $exchange;
        $raw['evidence_sha256'] = AnyTourOperatorFuelRuleEvidenceV1::hash([
            'source_response_sha256'=>$responseDigest,
            'offer_ref'=>$offerRef,
            'fuel'=>$fuel,
            'legs'=>$legs,
            'unit'=>$unit,
            'base_relation'=>$relation,
            'other_required_clear'=>$otherRequiredClear,
            'direction'=>$raw['direction'] ?? null,
            'exchange'=>$exchange,
        ]);
        return AnyTourOperatorFuelRuleEvidenceV1::observation($raw);
    }

    public static function append(string $directory, array $retained, callable $write): array
    {
        return AnyTourOperatorFuelRuleStoreV1::append($directory, self::observation($retained), $write);
    }

    private static function document(array $claim): array
    {
        $docs = $claim['claimDocument'] ?? null;
        if (!is_array($docs) || array_keys($docs) !== [0] || !is_array($docs[0])) {
            throw new InvalidArgumentException('ANDROMEDA_FUEL_CLAIM');
        }
        return $docs[0];
    }

    private static function fuelRows(array $doc): array
    {
        $byRoute = [];
        foreach (($doc['services'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['service'] ?? null)) continue;
            foreach ($block['service'] as $row) {
                if (!is_array($row) || (string)($row['servicecategoryName'] ?? '') !== 'Топливный сбор') continue;
                $route = (string)($row['routeIndex'] ?? '');
                if (!in_array($route, ['0','1'], true) || isset($byRoute[$route])) {
                    throw new DomainException('ANDROMEDA_FUEL_ROUTE_AMBIGUOUS');
                }
                $amount = self::money($row['price'] ?? null, 'ANDROMEDA_FUEL_AMOUNT');
                $currency = self::currency($row['currencyAlias'] ?? null);
                $required = self::bool($row['required'] ?? null);
                $packet = self::bool($row['packet'] ?? null);
                $included = self::explicitIncluded($row);
                $unit = self::explicitUnit($row);
                $byRoute[$route] = [
                    'amount'=>$amount,
                    'currency'=>$currency,
                    'required'=>$required,
                    'packet'=>$packet,
                    'included'=>$included,
                    'unit'=>$unit,
                ];
            }
        }
        if (array_keys($byRoute) !== [0,1] && array_keys($byRoute) !== ['0','1']) {
            ksort($byRoute, SORT_STRING);
        }
        if (array_keys($byRoute) !== [0,1] && array_keys($byRoute) !== ['0','1']) {
            throw new DomainException('ANDROMEDA_FUEL_ROUTE_PAIR');
        }
        if ($byRoute['0']['currency'] !== $byRoute['1']['currency']) {
            throw new DomainException('ANDROMEDA_FUEL_CURRENCY_CONFLICT');
        }
        return [
            'currency'=>$byRoute['0']['currency'],
            'outbound'=>$byRoute['0'],
            'return'=>$byRoute['1'],
        ];
    }

    private static function exactFlightPair(array $claim): array
    {
        $required = [];
        foreach (($claim['groups'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['group'] ?? null)) continue;
            foreach ($block['group'] as $group) {
                if (!is_array($group)) continue;
                if (self::bool($group['required'] ?? null) === true && self::bool($group['oneItem'] ?? null) === true) {
                    $id = (string)($group['id'] ?? '');
                    if ($id !== '') $required[$id] = true;
                }
            }
        }
        $items = ['0'=>[], '1'=>[]];
        foreach (($claim['variants'] ?? []) as $variant) {
            if (!is_array($variant)) continue;
            foreach (($variant['transports'] ?? []) as $block) {
                if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
                foreach ($block['transport'] as $item) {
                    if (!is_array($item) || ($item['type'] ?? null) !== 'ttAvia') continue;
                    $direction = (string)($item['direction'] ?? '');
                    $groupId = (string)($item['groupId'] ?? '');
                    if (!isset($items[$direction]) || !isset($required[$groupId])) continue;
                    $uid = $item['uid'] ?? null;
                    if (!is_string($uid) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $uid) !== 1) {
                        throw new InvalidArgumentException('ANDROMEDA_FUEL_FLIGHT');
                    }
                    $items[$direction][$uid] = $item;
                }
            }
        }
        if (count($items['0']) !== 1 || count($items['1']) !== 1) {
            throw new DomainException('ANDROMEDA_FUEL_FLIGHT_AMBIGUOUS');
        }
        return [
            '0'=>self::leg(array_values($items['0'])[0]),
            '1'=>self::leg(array_values($items['1'])[0]),
        ];
    }

    private static function leg(array $item): array
    {
        $facts = [
            'flight'=>[], 'carrier'=>[], 'origin'=>[], 'destination'=>[],
        ];
        foreach (($item['details'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['detail'] ?? null)) continue;
            foreach ($block['detail'] as $detail) {
                if (!is_array($detail)) continue;
                foreach (['flight_number'] as $key) {
                    $v = self::safeLabel($detail[$key] ?? null);
                    if ($v !== null) $facts['flight'][$v] = true;
                }
                foreach (['marketing_airline','airline'] as $key) {
                    $v = self::safeLabel($detail[$key] ?? null);
                    if ($v !== null) $facts['carrier'][$v] = true;
                }
                $v = self::safeLabel($detail['departureAirportCode'] ?? null);
                if ($v !== null) $facts['origin'][$v] = true;
                $v = self::safeLabel($detail['arrivalAirportCode'] ?? null);
                if ($v !== null) $facts['destination'][$v] = true;
            }
        }
        $departure = self::firstPoint($item['departure'] ?? null);
        $arrival = self::firstPoint($item['arrival'] ?? null);
        if ($facts['origin'] === [] && $departure !== null) $facts['origin'][$departure] = true;
        if ($facts['destination'] === [] && $arrival !== null) $facts['destination'][$arrival] = true;

        foreach ($facts as $kind=>$values) {
            if (count($values) !== 1) throw new DomainException('ANDROMEDA_FUEL_FLIGHT_' . strtoupper($kind));
        }
        return [
            'origin'=>array_key_first($facts['origin']),
            'destination'=>array_key_first($facts['destination']),
            'carrier'=>array_key_first($facts['carrier']),
            'flight'=>array_key_first($facts['flight']),
        ];
    }

    private static function firstPoint(mixed $rows): ?string
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1 || !is_array($rows[0])) return null;
        return self::safeLabel($rows[0]['port'] ?? null);
    }

    private static function explicitIncluded(array $row): ?bool
    {
        $values = [];
        foreach (['included','priceIncluded','includedInPrice','isIncluded'] as $key) {
            if (!array_key_exists($key, $row)) continue;
            $v = self::bool($row[$key]);
            if ($v === null) continue;
            $values[$v ? '1':'0'] = $v;
        }
        return count($values) === 1 ? array_values($values)[0] : null;
    }

    private static function explicitUnit(array $row): ?string
    {
        foreach (['unit','priceUnit','chargeUnit','per'] as $key) {
            $v = $row[$key] ?? null;
            if (!is_string($v)) continue;
            $v = strtolower(trim($v));
            if ($v !== '') return $v;
        }
        return null;
    }

    private static function unit(?string $a, ?string $b, string $amountA, string $amountB): string
    {
        if ($a !== null && $a === $b) {
            if (in_array($a, ['party','package','claim','booking','tour'], true)) return 'party_roundtrip';
            if (in_array($a, ['person','passenger','tourist','pax'], true) && $amountA === $amountB) {
                return 'per_person_one_way';
            }
        }
        return 'route_reported_unknown';
    }

    private static function relation(?bool $a, ?bool $b): string
    {
        if ($a === true && $b === true) return 'included';
        if ($a === false && $b === false) return 'excluded';
        return 'unknown';
    }

    private static function otherRequiredChargesClear(array $doc): bool
    {
        foreach (($doc['services'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['service'] ?? null)) return false;
            foreach ($block['service'] as $row) {
                if (!is_array($row)) return false;
                if ((string)($row['servicecategoryName'] ?? '') === 'Топливный сбор') continue;
                $amount = self::optionalMoney($row['price'] ?? null);
                if ($amount === null) return false;
                if ((float)$amount <= 0.0) continue;
                $required = self::bool($row['required'] ?? null);
                $packet = self::bool($row['packet'] ?? null);
                if ($required === null || $packet === null) return false;
                if ($required === true && $packet === false) return false;
            }
        }
        return true;
    }

    private static function addMoney(string $a, string $b): string
    {
        [$au,$as] = self::units($a); [$bu,$bs] = self::units($b);
        $scale = max($as,$bs);
        $av = $au * (10 ** ($scale-$as)); $bv = $bu * (10 ** ($scale-$bs));
        if ($av > PHP_INT_MAX - $bv) throw new InvalidArgumentException('ANDROMEDA_FUEL_AMOUNT');
        $sum = $av + $bv;
        if ($scale === 0) return (string)$sum;
        $factor = 10 ** $scale;
        return intdiv($sum,$factor) . '.' . str_pad((string)($sum%$factor),$scale,'0',STR_PAD_LEFT);
    }

    private static function units(string $value): array
    {
        $parts = explode('.', $value, 2);
        $scale = strlen($parts[1] ?? '');
        $factor = 10 ** $scale;
        return [((int)$parts[0] * $factor) + (int)($parts[1] ?? '0'), $scale];
    }

    private static function optionalMoney(mixed $value): ?string
    {
        try { return self::money($value, 'ANDROMEDA_FUEL_AMOUNT'); }
        catch (InvalidArgumentException) { return null; }
    }

    private static function money(mixed $value, string $reason): string
    {
        if (is_int($value) && $value >= 0) return (string)$value;
        if (is_float($value) && is_finite($value) && $value >= 0 && abs($value-round($value,2)) < 0.0000001) {
            return rtrim(rtrim(number_format($value,2,'.',''),'0'),'.');
        }
        if (!is_string($value) || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$value)!==1) {
            throw new InvalidArgumentException($reason);
        }
        return $value;
    }

    private static function currency(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[A-Z]{3}$/D',$value)!==1) throw new InvalidArgumentException('ANDROMEDA_FUEL_CURRENCY');
        return $value;
    }

    private static function party(mixed $value): array
    {
        if (!is_array($value) || !is_int($value['adults']??null) || !is_int($value['children']??null)
            || !is_array($value['child_ages']??null) || !array_is_list($value['child_ages'])
            || count($value['child_ages']) !== $value['children'] || $value['adults']<1 || $value['adults']>9
            || $value['children']<0 || $value['children']>9) throw new InvalidArgumentException('ANDROMEDA_FUEL_PARTY');
        $ages=$value['child_ages'];
        foreach($ages as $age) if(!is_int($age)||$age<0||$age>17) throw new InvalidArgumentException('ANDROMEDA_FUEL_PARTY');
        sort($ages,SORT_NUMERIC);
        return ['adults'=>$value['adults'],'children'=>$value['children'],'child_ages'=>$ages];
    }

    private static function bool(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if ($value === 'true' || $value === '1' || $value === 1) return true;
        if ($value === 'false' || $value === '0' || $value === 0) return false;
        return null;
    }

    private static function map(mixed $value,string $reason): array
    {
        if(!is_array($value)||array_is_list($value)) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function label(mixed $value,string $reason): string
    {
        if(!is_string($value)||$value===''||trim($value)!==$value||strlen($value)>80
            ||preg_match('/[\x00-\x1F\x7F*]/',$value)) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function safeLabel(mixed $value): ?string
    {
        try { return self::label($value,'ANDROMEDA_FUEL_FLIGHT'); }
        catch (InvalidArgumentException) { return null; }
    }

    private static function date(mixed $value,string $reason): string
    {
        $d=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC')):false;
        if(!$d||$d->format('Y-m-d')!==$value) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function positiveInt(mixed $value,string $reason): int
    {
        if(!is_int($value)||$value<1) throw new InvalidArgumentException($reason);
        return $value;
    }

    private static function digest(mixed $value,string $reason): string
    {
        if(!is_string($value)||preg_match('/^[a-f0-9]{64}$/D',$value)!==1) throw new InvalidArgumentException($reason);
        return $value;
    }
}