<?php
declare(strict_types=1);

/**
 * Provider-neutral reusable operator fuel evidence.
 *
 * V2 separates the reusable identity (canonical operator family + direction) from
 * exact flight/provider/party facts. Exact scope is retained as provenance only.
 * It must never narrow a direction rule by date, nights, hotel, room, meal,
 * program/SPO, carrier or flight number.
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

    /** Normalize one source-bound fuel observation. */
    public static function observation(array $row): array
    {
        $provider = $row['provider'] ?? null;
        if (!in_array($provider, ['tourvisor', 'andromeda'], true)) throw new InvalidArgumentException('OPERATOR_FUEL_PROVIDER');
        $operatorRaw = $row['operator_raw'] ?? ($row['operator'] ?? null);
        $family = self::operatorFamily($operatorRaw);
        if ($family === null || !in_array($family, self::TARGET_OPERATORS, true)) throw new InvalidArgumentException('OPERATOR_FUEL_OPERATOR');

        $scope = self::canonicalScope($provider, $operatorRaw, $row['scope'] ?? null);
        $direction = array_key_exists('direction', $row)
            ? self::canonicalDirection($operatorRaw, $row['direction'])
            : self::directionFromScope($operatorRaw, $scope);
        if ($direction['operator_family'] !== $family) throw new InvalidArgumentException('OPERATOR_FUEL_DIRECTION');

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
            'schema_version' => 2,
            'provider' => $provider,
            'operator_family' => $family,
            'operator_raw' => trim((string)$operatorRaw),
            'direction' => $direction,
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
            // Retained as source provenance only. V2 does not use this period as a
            // rule discriminator or target-date gate.
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
        ];
    }

    /**
     * Compile one reusable direction-rate input for the existing price handoff.
     *
     * Two independent observations still corroborate a rate, but they may be from
     * different flights/dates/hotels/night counts. Exact flight equality is no longer
     * required. A party-roundtrip total is never divided into a per-person rate and
     * is therefore reusable only for the same party composition. Infant-containing
     * party totals are not generalized because infant pricing is a separate rule.
     */
    public static function confirmedInput(array $target, array $rows, int $now, ?array $exchange = null): ?array
    {
        if ($now < 1) throw new InvalidArgumentException('OPERATOR_FUEL_NOW');
        $operator = $target['operator'] ?? null;
        $family = self::operatorFamily($operator);
        if ($family === null) return null;
        $direction = self::targetDirection($target, $operator);
        $party = self::targetParty($target);
        if (self::hasInfant($party)) return null;
        $targetRelation = $target['base_relation'] ?? null;
        if (!in_array($targetRelation, ['included', 'excluded'], true)) {
            throw new InvalidArgumentException('OPERATOR_FUEL_TARGET_RELATION');
        }
        $offerDigest = self::digest($target['offer_ref_digest'] ?? null, 'OPERATOR_FUEL_TARGET_OFFER');

        // Owner contract: a minority exception must not block the general direction rule.
        // Group only the reusable rate fact (native amount+currency+unit). Source PRICE
        // inclusion remains provenance; the target provider supplies its own relation.
        $groups = [];
        foreach ($rows as $raw) {
            if (!is_array($raw)) continue;
            try { $obs = self::observation($raw); } catch (InvalidArgumentException $ignored) { continue; }
            if ($obs['operator_family'] !== $family || $obs['direction'] !== $direction) continue;
            if ($obs['unit'] !== 'party_roundtrip'
                || $obs['base_includes_other_required_charges'] !== true) continue;
            if ($obs['observed_at'] > $now || $obs['expires_at'] <= $now) continue;
            $sourceParty = $obs['scope']['party'];
            if ($sourceParty !== $party || self::hasInfant($sourceParty)) continue;
            $fact = $obs['amount'] . '|' . $obs['currency'] . '|party_roundtrip';
            $groups[$fact] ??= ['rows'=>[], 'offers'=>[], 'evidence'=>[]];
            $groups[$fact]['rows'][] = $obs;
            $groups[$fact]['offers'][$obs['offer_ref_digest']] = true;
            $groups[$fact]['evidence'][$obs['evidence_sha256']] = true;
        }
        if ($groups === []) return null;

        $ranked = [];
        foreach ($groups as $fact=>$group) {
            $support = min(count($group['offers']), count($group['evidence']));
            if ($support < 2) continue;
            $ranked[] = ['fact'=>$fact, 'support'=>$support, 'group'=>$group];
        }
        if ($ranked === []) return null;
        usort($ranked, static function(array $a,array $b):int {
            $bySupport = $b['support'] <=> $a['support'];
            return $bySupport !== 0 ? $bySupport : strcmp($a['fact'], $b['fact']);
        });
        if (isset($ranked[1]) && $ranked[1]['support'] === $ranked[0]['support']) return null;
        $winner = $ranked[0]['group'];
        $winnerFact = $ranked[0]['fact'];
        $conflicting = 0;
        foreach ($groups as $fact=>$group) if ($fact !== $winnerFact) $conflicting += count($group['rows']);

        $inputObs = [];
        foreach ($winner['rows'] as $obs) {
            $inputObs[] = [
                'direction' => $obs['direction'],
                'provider' => $obs['provider'],
                'provenance_scope' => $obs['scope'],
                'party' => $obs['scope']['party'],
                'kind' => 'fuel',
                'unit' => 'party_roundtrip',
                'base_includes_other_required_charges' => true,
                'source_base_relation' => $obs['base_relation'],
                'offer_ref_digest' => $obs['offer_ref_digest'],
                'evidence_sha256' => $obs['evidence_sha256'],
                'observed_at' => $obs['observed_at'],
                'expires_at' => $obs['expires_at'],
                'evidence_valid_from' => $obs['valid_from'],
                'evidence_valid_to' => $obs['valid_to'],
                'amount' => $obs['amount'],
                'currency' => $obs['currency'],
            ];
        }
        return [
            'offer_ref_digest' => $offerDigest,
            'direction' => $direction,
            'party' => $party,
            'base_relation' => $targetRelation,
            'observations' => $inputObs,
            'winning_support' => $ranked[0]['support'],
            'conflicting_observation_count' => $conflicting,
            'exchange' => $exchange,
        ];
    }

    public static function directionDigest(array $direction): string
    {
        return self::hash($direction);
    }

    /** Backward-compatible helper retained for callers that hash provenance. */
    public static function scopeDigest(array $scope): string
    {
        return self::hash($scope);
    }

    public static function canonicalDirection(mixed $operator, mixed $value): array
    {
        $family = self::operatorFamily($operator);
        if ($family === null || !is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('OPERATOR_FUEL_DIRECTION');
        }
        $keys = array_keys($value); sort($keys);
        if ($keys === ['destination','market']) {
            return [
                'operator_family' => $family,
                'market' => self::normalizeMarket($value['market']),
                'destination' => self::label($value['destination']),
            ];
        }
        if ($keys === ['destination','market','operator_family']) {
            if (($value['operator_family'] ?? null) !== $family) throw new InvalidArgumentException('OPERATOR_FUEL_DIRECTION');
            return [
                'operator_family' => $family,
                'market' => self::normalizeMarket($value['market']),
                'destination' => self::label($value['destination']),
            ];
        }
        throw new InvalidArgumentException('OPERATOR_FUEL_DIRECTION');
    }

    public static function directionFromSearch(mixed $operator, array $params): array
    {
        $departure = $params['departureId'] ?? null;
        $country = $params['countryId'] ?? null;
        foreach ([&$departure, &$country] as &$value) {
            if (is_int($value) && $value > 0) $value = (string)$value;
            if (!is_string($value) || preg_match('/\A[1-9][0-9]{0,12}\z/D', $value) !== 1) {
                throw new InvalidArgumentException('OPERATOR_FUEL_DIRECTION');
            }
        }
        unset($value);
        return self::canonicalDirection($operator, [
            'market' => 'departure:' . $departure,
            'destination' => 'country:' . $country,
        ]);
    }

    public static function directionFromScope(mixed $operator, array $scope): array
    {
        $market = $scope['market'] ?? null;
        $outbound = $scope['outbound'] ?? null;
        $return = $scope['return'] ?? null;
        if (!is_array($outbound) || !is_array($return)
            || ($outbound['destination'] ?? null) !== ($return['origin'] ?? null)
            || ($outbound['origin'] ?? null) !== ($return['destination'] ?? null)) {
            throw new InvalidArgumentException('OPERATOR_FUEL_DIRECTION');
        }
        return self::canonicalDirection($operator, [
            'market' => $market,
            'destination' => $outbound['destination'],
        ]);
    }

    /** Exact source scope retained only as provenance. */
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

    private static function targetDirection(array $target, mixed $operator): array
    {
        if (array_key_exists('direction', $target)) return self::canonicalDirection($operator, $target['direction']);
        if (is_array($target['search_params'] ?? null)) return self::directionFromSearch($operator, $target['search_params']);
        $provider = $target['provider'] ?? null;
        $scope = self::canonicalScope($provider, $operator, $target['scope'] ?? null);
        return self::directionFromScope($operator, $scope);
    }

    private static function targetParty(array $target): array
    {
        if (array_key_exists('party', $target)) return self::party($target['party']);
        $scope = $target['scope'] ?? null;
        if (!is_array($scope) || !array_key_exists('party', $scope)) throw new InvalidArgumentException('OPERATOR_FUEL_PARTY');
        return self::party($scope['party']);
    }

    private static function hasInfant(array $party): bool
    {
        foreach ($party['child_ages'] as $age) if ($age < 2) return true;
        return false;
    }

    private static function normalizeMarket(mixed $value): string
    {
        $market = self::label($value);
        if (preg_match('/\A(?:tourvisor|andromeda):(.+)\z/iD', $market, $m) === 1) {
            $market = self::label($m[1]);
        }
        return $market;
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
