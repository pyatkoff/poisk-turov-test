<?php
declare(strict_types=1);

/**
 * Canonical, provider-neutral evidence for a reusable tour-operator fuel rule.
 *
 * This class performs no supplier I/O and no persistence. It accepts only already
 * retained, source-bound facts. The first arithmetic-capable unit is an explicit
 * party roundtrip total. Per-leg/per-person facts may be retained by upstream code,
 * but are never converted here without an explicit compatible unit.
 */
final class AnyTourOperatorFuelRuleEvidenceV1
{
    private const TARGET_OPERATORS = ['biblio_globus', 'fun_and_sun', 'intourist'];

    public static function operatorFamily(mixed $raw): ?string
    {
        if (!is_string($raw)) return null;
        $text = str_replace(['Ё', 'ё'], 'е', trim($raw));
        if ($text === '' || strlen($text) > 160 || preg_match('/[\x00-\x1F\x7F]/u', $text)) return null;
        if (preg_match('/интурист/iu', $text) === 1 || preg_match('/intourist/i', $text) === 1) return 'intourist';
        if (preg_match('/фан[^\p{L}\p{N}]*с[аa]н/iu', $text) === 1 || preg_match('/fun[^a-z0-9]*&?[^a-z0-9]*sun/i', $text) === 1) return 'fun_and_sun';
        if (preg_match('/библио[^\p{L}\p{N}]*глобус/iu', $text) === 1 || preg_match('/biblio[^a-z0-9]*globus/i', $text) === 1) return 'biblio_globus';
        return null;
    }

    /** Normalize one already-saved explicit fuel observation. */
    public static function observation(array $row): array
    {
        $provider = $row['provider'] ?? null;
        if (!in_array($provider, ['tourvisor', 'andromeda'], true)) throw new InvalidArgumentException('OPERATOR_FUEL_PROVIDER');
        $operatorRaw = $row['operator_raw'] ?? ($row['operator'] ?? null);
        $family = self::operatorFamily($operatorRaw);
        if ($family === null || !in_array($family, self::TARGET_OPERATORS, true)) throw new InvalidArgumentException('OPERATOR_FUEL_OPERATOR');
        $scope = self::canonicalScope($provider, $operatorRaw, $row['scope'] ?? null);
        $unit = $row['unit'] ?? null;
        if (!in_array($unit, ['party_roundtrip', 'per_person_one_way', 'route_reported_unknown', 'unknown'], true)) {
            throw new InvalidArgumentException('OPERATOR_FUEL_UNIT');
        }
        $relation = $row['base_relation'] ?? null;
        if (!in_array($relation, ['included', 'excluded', 'unknown'], true)) throw new InvalidArgumentException('OPERATOR_FUEL_RELATION');
        $amount = self::money($row['amount'] ?? null);
        $currency = self::currency($row['currency'] ?? null);
        $offer = self::digest($row['offer_ref_digest'] ?? null, 'OPERATOR_FUEL_OFFER_DIGEST');
        $evidence = self::digest($row['evidence_sha256'] ?? null, 'OPERATOR_FUEL_EVIDENCE_DIGEST');
        $source = $row['source'] ?? null;
        if (!is_string($source) || !in_array($source, [
            'tourvisor_search_fuel', 'tourvisor_flights_fuel', 'andromeda_claim_service', 'operator_official_rule'
        ], true)) throw new InvalidArgumentException('OPERATOR_FUEL_SOURCE');
        $observed = self::positiveInt($row['observed_at'] ?? null, 'OPERATOR_FUEL_TIME');
        $expires = self::positiveInt($row['expires_at'] ?? null, 'OPERATOR_FUEL_TIME');
        if ($expires <= $observed) throw new InvalidArgumentException('OPERATOR_FUEL_TIME');
        $validFrom = self::date($row['valid_from'] ?? null);
        $validTo = self::date($row['valid_to'] ?? null);
        if ($validFrom > $validTo) throw new InvalidArgumentException('OPERATOR_FUEL_PERIOD');
        $other = $row['base_includes_other_required_charges'] ?? null;
        if (!is_bool($other)) throw new InvalidArgumentException('OPERATOR_FUEL_REQUIRED_CHARGES');
        $sourceResponse = self::digest($row['source_response_sha256'] ?? null, 'OPERATOR_FUEL_SOURCE_DIGEST');
        return [
            'schema_version' => 1,
            'provider' => $provider,
            'operator_family' => $family,
            'operator_raw' => trim((string)$operatorRaw),
            'scope' => $scope,
            'kind' => 'fuel',
            'unit' => $unit,
            'amount' => $amount,
            'currency' => $currency,
            'base_relation' => $relation,
            'base_includes_other_required_charges' => $other,
            'offer_ref_digest' => $offer,
            'evidence_sha256' => $evidence,
            'source_response_sha256' => $sourceResponse,
            'source' => $source,
            'observed_at' => $observed,
            'expires_at' => $expires,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
        ];
    }

    /**
     * Build the exact input already consumed by AnyTourThreeProviderFuelEvidenceV1.
     * Two independent offers + two evidence digests must agree. Nights/hotel/room/meal
     * are intentionally absent from the rule identity.
     */
    public static function confirmedInput(array $target, array $rows, int $now, ?array $exchange = null): ?array
    {
        if ($now < 1) throw new InvalidArgumentException('OPERATOR_FUEL_NOW');
        $provider = $target['provider'] ?? null;
        $operator = $target['operator'] ?? null;
        $scope = self::canonicalScope($provider, $operator, $target['scope'] ?? null);
        $family = self::operatorFamily($operator);
        if ($family === null) return null;
        $offerDigest = self::digest($target['offer_ref_digest'] ?? null, 'OPERATOR_FUEL_TARGET_OFFER');
        $dates = $target['flight_dates'] ?? null;
        if (!is_array($dates) || !array_is_list($dates) || count($dates) !== 2) throw new InvalidArgumentException('OPERATOR_FUEL_TARGET_DATES');
        $dates = [self::date($dates[0]), self::date($dates[1])];
        if ($dates[0] > $dates[1]) throw new InvalidArgumentException('OPERATOR_FUEL_TARGET_DATES');

        $compatible = [];
        $facts = [];
        $offers = [];
        $evidence = [];
        foreach ($rows as $raw) {
            if (!is_array($raw)) continue;
            try { $obs = self::observation($raw); } catch (InvalidArgumentException $ignored) { continue; }
            if ($obs['provider'] !== $provider || $obs['operator_family'] !== $family || $obs['scope'] !== $scope) continue;
            if ($obs['unit'] !== 'party_roundtrip' || !in_array($obs['base_relation'], ['included', 'excluded'], true)
                || $obs['base_includes_other_required_charges'] !== true) continue;
            if ($obs['observed_at'] > $now || $obs['expires_at'] <= $now) continue;
            if ($dates[0] < $obs['valid_from'] || $dates[1] > $obs['valid_to']) continue;
            $fact = $obs['amount'] . '|' . $obs['currency'] . '|' . $obs['base_relation'];
            $facts[$fact] = true;
            if (count($facts) > 1) return null;
            $offers[$obs['offer_ref_digest']] = true;
            $evidence[$obs['evidence_sha256']] = true;
            $compatible[] = $obs;
        }
        if (count($offers) < 2 || count($evidence) < 2 || $compatible === []) return null;

        $inputObs = [];
        foreach ($compatible as $obs) {
            $inputObs[] = [
                'scope' => $obs['scope'], 'kind' => 'fuel', 'unit' => 'party_roundtrip',
                'base_includes_other_required_charges' => true, 'base_relation' => $obs['base_relation'],
                'offer_ref_digest' => $obs['offer_ref_digest'], 'evidence_sha256' => $obs['evidence_sha256'],
                'observed_at' => $obs['observed_at'], 'expires_at' => $obs['expires_at'],
                'valid_from' => $obs['valid_from'], 'valid_to' => $obs['valid_to'],
                'amount' => $obs['amount'], 'currency' => $obs['currency'],
            ];
        }
        return [
            'offer_ref_digest' => $offerDigest,
            'scope' => $scope,
            'flight_dates' => $dates,
            'observations' => $inputObs,
            'exchange' => $exchange,
        ];
    }

    public static function scopeDigest(array $scope): string
    {
        return self::hash($scope);
    }

    public static function canonicalScope(mixed $provider, mixed $operator, mixed $value): array
    {
        if (!in_array($provider, ['tourvisor', 'andromeda'], true) || !is_string($operator) || trim($operator) === '') {
            throw new InvalidArgumentException('OPERATOR_FUEL_SCOPE');
        }
        if (!is_array($value)) throw new InvalidArgumentException('OPERATOR_FUEL_SCOPE');
        $operatorHash = self::hash([
            'raw' => trim($operator),
            'canonical_name' => null,
            'canonical_verified' => false,
            'identity_source' => 'raw_label_only',
            'filter_status' => $provider === 'tourvisor' ? 'verified' : 'unsupported',
            'cross_provider_equivalence_verified' => false,
            'supplier_code_exposed' => false,
        ]);
        $rawKeys = ['market','outbound','return','party'];
        $canonicalKeys = ['provider','operator_sha256','market','outbound','return','party'];
        $keys = array_keys($value); sort($keys);
        $rawSorted = $rawKeys; sort($rawSorted);
        $canonicalSorted = $canonicalKeys; sort($canonicalSorted);
        if ($keys === $canonicalSorted) {
            if (($value['provider'] ?? null) !== $provider || ($value['operator_sha256'] ?? null) !== $operatorHash) {
                throw new InvalidArgumentException('OPERATOR_FUEL_SCOPE');
            }
        } elseif ($keys !== $rawSorted) {
            throw new InvalidArgumentException('OPERATOR_FUEL_SCOPE');
        }
        return [
            'provider' => $provider,
            'operator_sha256' => $operatorHash,
            'market' => self::label($value['market']),
            'outbound' => self::leg($value['outbound']),
            'return' => self::leg($value['return']),
            'party' => self::party($value['party']),
        ];
    }

    public static function hash(mixed $value): string
    {
        $canonical = static function(mixed $v) use (&$canonical): mixed {
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v, SORT_STRING);
            foreach ($v as $k => $item) $v[$k] = $canonical($item);
            return $v;
        };
        return hash('sha256', json_encode($canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function leg(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 4 || array_diff(['origin','destination','carrier','flight'], array_keys($value)) !== []) {
            throw new InvalidArgumentException('OPERATOR_FUEL_LEG');
        }
        return [
            'origin' => self::label($value['origin']), 'destination' => self::label($value['destination']),
            'carrier' => self::label($value['carrier']), 'flight' => self::label($value['flight']),
        ];
    }

    private static function party(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 3 || !is_int($value['adults'] ?? null)
            || !is_int($value['children'] ?? null) || !is_array($value['child_ages'] ?? null)
            || !array_is_list($value['child_ages']) || count($value['child_ages']) !== $value['children']
            || $value['adults'] < 1 || $value['adults'] > 9 || $value['children'] < 0 || $value['children'] > 9) {
            throw new InvalidArgumentException('OPERATOR_FUEL_PARTY');
        }
        $ages = $value['child_ages'];
        foreach ($ages as $age) if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('OPERATOR_FUEL_PARTY');
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$value['adults'],'children'=>$value['children'],'child_ages'=>$ages];
    }

    private static function label(mixed $value): string
    {
        if (!is_string($value) || $value === '' || trim($value) !== $value || strlen($value) > 80
            || preg_match('/[\x00-\x1F\x7F*]/', $value)) throw new InvalidArgumentException('OPERATOR_FUEL_LABEL');
        return $value;
    }

    private static function money(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)) {
            throw new InvalidArgumentException('OPERATOR_FUEL_MONEY');
        }
        return $value;
    }

    private static function currency(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[A-Z]{3}\z/D', $value)) throw new InvalidArgumentException('OPERATOR_FUEL_CURRENCY');
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

    private static function date(mixed $value): string
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC')) : false;
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('OPERATOR_FUEL_DATE');
        return $value;
    }
}
