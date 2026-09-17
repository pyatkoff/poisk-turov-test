<?php
declare(strict_types=1);

/**
 * Pure MATCH resolver for the owner-directed three-source ANEX lane.
 *
 * Input is already-captured/sanitized offer evidence from:
 *   - direct ANEX
 *   - SAMO/Andromeda with operatorKey=5 (ANEX)
 *   - Tourvisor with operator=ANEX
 *
 * This file performs NO network access and NO DB writes.
 */
final class AnyTourAnexThreeSourceResortStarV1
{
    private const GENERIC = ['hotel'=>true,'ex'=>true];

    public static function norm(mixed $value): string
    {
        if (!is_scalar($value)) return '';
        $text = mb_strtolower(trim((string)$value), 'UTF-8');
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function nameKey(mixed $value): string
    {
        $parts = preg_split('/\s+/u', self::norm($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_filter($parts, static fn(string $p): bool => !isset(self::GENERIC[$p])));
        return implode(' ', $parts);
    }

    public static function isGenericProduct(mixed $value): bool
    {
        $n = self::norm($value);
        return preg_match('/^(?:fortuna|roulette|фортуна|рулетка)(?:\s|$)/u', $n) === 1;
    }

    private static function positiveId(mixed $value): ?string
    {
        if (is_int($value) && $value > 0) return (string)$value;
        if (is_string($value) && preg_match('/\A[1-9][0-9]{0,31}\z/D', $value)) return $value;
        return null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        return $id === false ? null : (int)$id;
    }

    private static function money(mixed $value): ?string
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string)$value;
        if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)) return null;
        return $value;
    }

    private static function cents(string $value): int
    {
        $parts = explode('.', $value, 2);
        return ((int)$parts[0] * 100) + (isset($parts[1]) ? (int)str_pad($parts[1], 2, '0') : 0);
    }

    private static function stars(mixed $value): ?int
    {
        $v = filter_var($value, FILTER_VALIDATE_INT);
        return $v === false || !in_array((int)$v, [3,4,5], true) ? null : (int)$v;
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $e = DateTimeImmutable::getLastErrors();
        if (!$d || ($e !== false && (($e['warning_count'] ?? 0) || ($e['error_count'] ?? 0)))) return null;
        return $d->format('Y-m-d');
    }

    private static function bucket(array $row): ?string
    {
        $resort = self::norm($row['resort'] ?? null);
        $date = self::date($row['date'] ?? null);
        $nights = self::positiveInt($row['nights'] ?? null);
        $adults = self::positiveInt($row['adults'] ?? null);
        $children = filter_var($row['children'] ?? null, FILTER_VALIDATE_INT);
        $stars = self::stars($row['stars'] ?? null);
        if ($resort === '' || $date === null || $nights === null || $adults === null || $children === false || $children < 0 || $stars === null) return null;
        return implode('|', [$resort,$date,$nights,$adults,(int)$children,$stars]);
    }

    private static function offerContext(array $row): ?string
    {
        $meal = self::norm($row['meal'] ?? null);
        $room = self::norm($row['room'] ?? null);
        if ($meal === '' || $room === '') return null;
        return $meal . '|' . $room;
    }

    private static function sanitizeBase(array $row): ?array
    {
        $bucket = self::bucket($row);
        $price = self::money($row['price'] ?? null);
        $currency = strtoupper(trim((string)($row['currency'] ?? '')));
        $name = trim((string)($row['hotel_name'] ?? ''));
        if ($bucket === null || $price === null || $currency !== 'RUB' || $name === '' || self::isGenericProduct($name)) return null;
        return [
            'bucket'=>$bucket,
            'hotel_name'=>$name,
            'name_key'=>self::nameKey($name),
            'context'=>self::offerContext($row),
            'price'=>$price,
            'fuel_charge'=>self::money($row['fuel_charge'] ?? null),
        ];
    }

    private static function groupOffers(array $rows, string $provider): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $base = self::sanitizeBase($row);
            if ($base === null) continue;
            if ($provider === 'anex') {
                $id = self::positiveId($row['anex_hotel_id'] ?? $row['hotel_id'] ?? null);
            } elseif ($provider === 'andromeda') {
                $id = self::positiveId($row['andromeda_hotel_id'] ?? null);
                $op = self::positiveId($row['operator_key'] ?? null);
                if ($op !== '5' || !in_array($row['is_operator_hotel_key'] ?? null, [0,'0',false], true)) continue;
                $base['original_anex_hotel_id'] = self::positiveId($row['original_hotel_id'] ?? $row['original']['hotelKey'] ?? null);
                if ($base['original_anex_hotel_id'] === null) continue;
            } else {
                $id = self::positiveId($row['tv_hotel_id'] ?? $row['hotel_id'] ?? null);
                $op = self::norm($row['operator_name'] ?? '');
                if (!in_array($op, ['anex','anex tour','anextour','анекс','анекс тур'], true)) continue;
                $base['tour_id'] = self::positiveId($row['tour_id'] ?? null);
                $base['operator_link_anex_id'] = self::positiveId($row['operator_link_anex_id'] ?? null);
            }
            if ($id === null) continue;
            $base['provider_hotel_id'] = $id;
            $out[$id][] = $base;
        }
        return $out;
    }

    private static function first(array $offers): array
    {
        return $offers[0] ?? [];
    }

    private static function relation(array $anexOffers, array $tvOffers): array
    {
        $a = [];
        foreach ($anexOffers as $row) if (($row['context'] ?? null) !== null) $a[$row['context']][] = $row;
        $t = [];
        foreach ($tvOffers as $row) if (($row['context'] ?? null) !== null) $t[$row['context']][] = $row;
        $contexts = array_values(array_intersect(array_keys($a), array_keys($t)));
        $exact = 0; $minusFuel = 0; $samples = [];
        foreach ($contexts as $ctx) {
            $matchedExact = false; $matchedFuel = false;
            foreach ($a[$ctx] as $left) foreach ($t[$ctx] as $right) {
                $ap = self::cents($left['price']); $tp = self::cents($right['price']);
                if ($ap === $tp) $matchedExact = true;
                if (($right['fuel_charge'] ?? null) !== null && $tp - self::cents($right['fuel_charge']) === $ap) $matchedFuel = true;
            }
            if ($matchedExact) $exact++;
            if ($matchedFuel) $minusFuel++;
            if (($matchedExact || $matchedFuel) && count($samples) < 5) $samples[] = $ctx;
        }
        return [
            'shared_contexts'=>count($contexts),
            'exact_price_contexts'=>$exact,
            'tv_minus_fuel_contexts'=>$minusFuel,
            'matching_contexts'=>count(array_unique($samples)),
            'sample_contexts'=>$samples,
        ];
    }

    public static function resolve(array $input): array
    {
        if (($input['schema_version'] ?? null) !== 1) throw new InvalidArgumentException('THREE_SOURCE_SCHEMA');
        foreach (['anex','andromeda','tourvisor'] as $key) if (!is_array($input[$key] ?? null)) throw new InvalidArgumentException('THREE_SOURCE_INPUT');

        $anex = self::groupOffers($input['anex'], 'anex');
        $andr = self::groupOffers($input['andromeda'], 'andromeda');
        $tv = self::groupOffers($input['tourvisor'], 'tourvisor');

        $anexByBucket = [];
        foreach ($anex as $id=>$offers) foreach ($offers as $row) $anexByBucket[$row['bucket']][$id] = true;

        $andromedaByAnex = [];
        $andromedaConflicts = [];
        foreach ($andr as $andrId=>$offers) {
            $native = [];
            foreach ($offers as $row) $native[$row['original_anex_hotel_id']] = true;
            if (count($native) !== 1) { $andromedaConflicts[] = ['andromeda_hotel_id'=>$andrId,'reason'=>'multiple_original_anex_ids']; continue; }
            $anexId = (string)array_key_first($native);
            $buckets = [];
            foreach ($offers as $row) $buckets[$row['bucket']] = true;
            $andromedaByAnex[$anexId][$andrId] = ['buckets'=>array_keys($buckets),'name_key'=>self::first($offers)['name_key'] ?? ''];
        }

        $directTv = [];
        $conflicts = $andromedaConflicts;
        $candidates = [];
        $ambiguous = [];

        foreach ($tv as $tvId=>$offers) {
            $directIds = [];
            foreach ($offers as $row) if (($row['operator_link_anex_id'] ?? null) !== null) $directIds[$row['operator_link_anex_id']] = true;
            if (count($directIds) > 1) {
                $conflicts[] = ['tv_hotel_id'=>$tvId,'reason'=>'conflicting_operator_link_anex_ids','anex_ids'=>array_keys($directIds)];
                continue;
            }
            if (count($directIds) === 1) {
                $anexId = (string)array_key_first($directIds);
                $directTv[] = [
                    'tv_hotel_id'=>$tvId,'anex_hotel_id'=>$anexId,
                    'andromeda_hotel_ids'=>array_keys($andromedaByAnex[$anexId] ?? []),
                    'evidence'=>'tourvisor_operator_link_hotellist',
                    'prepared_only'=>true,
                ];
                continue;
            }

            $bucketIds = [];
            foreach ($offers as $row) foreach (array_keys($anexByBucket[$row['bucket']] ?? []) as $anexId) $bucketIds[(string)$anexId] = true;
            $possible = [];
            foreach (array_keys($bucketIds) as $anexIdRaw) {
                $anexId = (string)$anexIdRaw;
                if (!isset($anex[$anexId]) || count($andromedaByAnex[$anexId] ?? []) !== 1) continue;
                $relation = self::relation($anex[$anexId], $offers);
                $nameExact = (self::first($anex[$anexId])['name_key'] ?? '') !== ''
                    && (self::first($anex[$anexId])['name_key'] ?? '') === (self::first($offers)['name_key'] ?? '');
                $priceStrong = (($relation['exact_price_contexts'] ?? 0) + ($relation['tv_minus_fuel_contexts'] ?? 0)) >= 2;
                $priceSome = (($relation['exact_price_contexts'] ?? 0) + ($relation['tv_minus_fuel_contexts'] ?? 0)) >= 1;
                if (!$nameExact && !$priceStrong) continue;
                $possible[] = [
                    'anex_hotel_id'=>$anexId,
                    'andromeda_hotel_id'=>(string)array_key_first($andromedaByAnex[$anexId]),
                    'name_exact'=>$nameExact,
                    'price_relation'=>$relation,
                    'strong'=>$priceStrong || ($nameExact && $priceSome),
                ];
            }
            // Exact normalized hotel identity outranks a price-only candidate inside the
            // same resort/star/date bucket. Price can corroborate identity, but must not
            // make an unrelated same-priced hotel compete with an exact-name candidate.
            if (count($possible) > 1) {
                $exactNamed = array_values(array_filter($possible, static fn(array $p): bool => ($p['name_exact'] ?? false) === true));
                if (count($exactNamed) === 1) $possible = $exactNamed;
            }
            if (count($possible) === 1 && ($possible[0]['strong'] ?? false)) {
                $candidates[] = ['tv_hotel_id'=>$tvId] + $possible[0] + [
                    'evidence'=>'unique_resort_star_date_candidate_with_andromeda_native_anchor',
                    'prepared_only'=>true,
                    'mapping_write_authorized'=>false,
                ];
            } elseif ($possible) {
                $ambiguous[] = ['tv_hotel_id'=>$tvId,'candidate_count'=>count($possible),'candidates'=>$possible];
            }
        }

        $buckets = [];
        foreach (array_keys($anexByBucket) as $bucket) {
            $a = array_map('strval', array_keys($anexByBucket[$bucket]));
            $d = [];
            foreach ($a as $anexId) foreach (($andromedaByAnex[$anexId] ?? []) as $andrId=>$meta) {
                if (in_array($bucket, $meta['buckets'], true)) $d[$andrId] = true;
            }
            $t = [];
            foreach ($tv as $tvId=>$offers) foreach ($offers as $row) if ($row['bucket'] === $bucket) { $t[$tvId] = true; break; }
            $buckets[$bucket] = ['anex_hotels'=>count($a),'andromeda_hotels'=>count($d),'tourvisor_hotels'=>count($t),
                'is_1_1_1'=>count($a)===1 && count($d)===1 && count($t)===1];
        }

        return [
            'schema_version'=>1,
            'status'=>'prepared_read_only',
            'counts'=>[
                'anex_hotels'=>count($anex),'andromeda_hotels'=>count($andr),'tourvisor_hotels'=>count($tv),
                'andromeda_direct_bindings'=>array_sum(array_map('count',$andromedaByAnex)),
                'tourvisor_direct_confirmations'=>count($directTv),
                'unique_price_name_candidates'=>count($candidates),
                'ambiguous_tv_hotels'=>count($ambiguous),
                'conflicts'=>count($conflicts),
            ],
            'direct_andromeda_by_anex'=>$andromedaByAnex,
            'direct_tourvisor'=>$directTv,
            'prepared_candidates'=>$candidates,
            'ambiguous'=>$ambiguous,
            'conflicts'=>$conflicts,
            'buckets'=>$buckets,
            'mapping_writes'=>0,
            'price_only_auto_accept'=>false,
            'fortuna_roulette_excluded'=>true,
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $raw = stream_get_contents(STDIN);
    $input = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new RuntimeException('THREE_SOURCE_INPUT');
    echo json_encode(AnyTourAnexThreeSourceResortStarV1::resolve($input), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";
}
