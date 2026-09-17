<?php
declare(strict_types=1);

/**
 * MATCH-only pure evidence resolver.
 *
 * Builds:
 *  - provider hotel operator fingerprints across common operators;
 *  - conservative TV<->SAMO hotel candidates;
 *  - hotel-local room correspondence candidates inside one hotel pair only.
 *
 * No network, DB or mapping writes.
 */

function hmf_text(mixed $v, int $max = 512): string {
    return is_scalar($v) ? mb_substr(trim((string)$v), 0, $max, 'UTF-8') : '';
}

function hmf_norm(string $v): string {
    $v = mb_strtolower(trim($v), 'UTF-8');
    $v = str_replace(['&', '+'], ' ', $v);
    $v = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $v) ?? $v;
    return trim(preg_replace('/\\s+/u', ' ', $v) ?? $v);
}

function hmf_hotel_key(string $v): string {
    $tokens = preg_split('/\\s+/u', hmf_norm($v), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $drop = ['hotel'=>1,'resort'=>1,'spa'=>1,'отель'=>1,'гостиница'=>1];
    $keep = [];
    foreach ($tokens as $t) if (!isset($drop[$t])) $keep[] = $t;
    return implode(' ', $keep);
}

function hmf_room_key(string $v): string {
    $tokens = preg_split('/\\s+/u', hmf_norm($v), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $drop = ['room'=>1,'rooms'=>1,'номер'=>1,'номера'=>1,'комната'=>1];
    $keep = [];
    foreach ($tokens as $t) if (!isset($drop[$t])) $keep[] = $t;
    return implode(' ', $keep);
}

function hmf_operator_family(string $v): ?string {
    $n = hmf_norm($v);
    if ($n === '') return null;
    if (preg_match('/(?:^| )(?:anex|anextour|анекс)(?: |$)/u', $n)) return 'anex';
    if (preg_match('/(?:^| )(?:biblio globus|biblio globus|библио глобус)(?: |$)/u', $n)) return 'biblio';
    if (preg_match('/(?:^| )(?:fun sun|фан сан)(?: |$)/u', $n)) return 'funsun';
    if (preg_match('/(?:^| )(?:intourist|интурист)(?: |$)/u', $n)) return 'intourist';
    return null;
}

function hmf_generic_product(string $v): bool {
    return preg_match('/^(?:fortuna|roulette|фортуна|рулетка)(?: |$)/u', hmf_norm($v)) === 1;
}

function hmf_url(mixed $v): ?string {
    $s = hmf_text($v, 2048);
    if ($s === '') return null;
    if (str_starts_with($s, '//')) $s = 'https:' . $s;
    $p = parse_url($s);
    if (!is_array($p) || !in_array(strtolower((string)($p['scheme'] ?? '')), ['https','http'], true)
        || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return null;
    if (isset($p['query'])) {
        parse_str((string)$p['query'], $q);
        foreach (array_keys($q) as $k) {
            if (preg_match('/token|auth|pass|secret|session|sid|cookie|signature|api[_-]?key/i', (string)$k)) return null;
        }
    }
    return $s;
}

function hmf_positive_int(mixed $v): ?int {
    $n = filter_var($v, FILTER_VALIDATE_INT);
    return $n !== false && (int)$n > 0 ? (int)$n : null;
}

function hmf_offer_context(array $row): ?array {
    $family = hmf_operator_family(hmf_text($row['operator_name'] ?? $row['operator'] ?? ''));
    $date = hmf_text($row['date'] ?? $row['checkin'] ?? '', 20);
    $nights = hmf_positive_int($row['nights'] ?? null);
    $adults = hmf_positive_int($row['adults'] ?? null);
    $children = isset($row['children']) && is_numeric($row['children']) ? max(0, (int)$row['children']) : 0;
    if ($family === null || $date === '' || $nights === null || $adults === null) return null;
    return [
        'operator' => $family,
        'date' => $date,
        'nights' => $nights,
        'adults' => $adults,
        'children' => $children,
    ];
}

function hmf_context_key(array $ctx): string {
    return implode('|', [
        $ctx['operator'],
        $ctx['date'],
        (string)$ctx['nights'],
        (string)$ctx['adults'],
        (string)$ctx['children'],
    ]);
}

function hmf_normalize_offer(array $row, string $provider): ?array {
    $hotelId = hmf_text($row['hotel_id'] ?? $row['hotelKey'] ?? '', 80);
    $hotelName = hmf_text($row['hotel_name'] ?? $row['hotel'] ?? '');
    $ctx = hmf_offer_context($row);
    if ($hotelId === '' || $hotelName === '' || $ctx === null || hmf_generic_product($hotelName)) return null;

    $roomRaw = hmf_text($row['room_raw'] ?? $row['room'] ?? $row['room_name'] ?? '', 300);
    $mealRaw = hmf_text($row['meal_raw'] ?? $row['meal'] ?? $row['meal_name'] ?? '', 200);
    $price = null;
    foreach (['price','price_rub','priceRub','amount','cost'] as $k) {
        if (isset($row[$k]) && is_numeric($row[$k]) && (float)$row[$k] > 0) {
            $price = (float)$row[$k];
            break;
        }
    }
    $nativeAnex = hmf_text($row['native_anex_hotel_id'] ?? $row['original_hotel_key'] ?? '', 80);
    if ($nativeAnex !== '' && !preg_match('/^[A-Za-z0-9_.-]{1,80}$/D', $nativeAnex)) $nativeAnex = '';

    $urls = [];
    foreach (['hotel_url','operator_link','hotel_link','url'] as $k) {
        if (($u = hmf_url($row[$k] ?? null)) !== null) $urls[$u] = true;
    }

    return [
        'provider' => $provider,
        'hotel_id' => $hotelId,
        'hotel_name' => $hotelName,
        'hotel_key' => hmf_hotel_key($hotelName),
        'operator' => $ctx['operator'],
        'date' => $ctx['date'],
        'nights' => $ctx['nights'],
        'adults' => $ctx['adults'],
        'children' => $ctx['children'],
        'context_key' => hmf_context_key($ctx),
        'room_raw' => $roomRaw,
        'room_key' => hmf_room_key($roomRaw),
        'meal_raw' => $mealRaw,
        'price' => $price,
        'native_anex_hotel_id' => $nativeAnex !== '' ? $nativeAnex : null,
        'urls' => array_keys($urls),
    ];
}

function hmf_group_hotels(array $rows, string $provider): array {
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row) || ($o = hmf_normalize_offer($row, $provider)) === null) continue;
        $id = $o['hotel_id'];
        if (!isset($out[$id])) {
            $out[$id] = [
                'provider' => $provider,
                'hotel_id' => $id,
                'hotel_name' => $o['hotel_name'],
                'hotel_key' => $o['hotel_key'],
                'operator_fingerprint' => [],
                'native_anex_hotel_ids' => [],
                'urls' => [],
                'offers' => [],
            ];
        }
        $out[$id]['operator_fingerprint'][$o['operator']] = ($out[$id]['operator_fingerprint'][$o['operator']] ?? 0) + 1;
        if ($o['native_anex_hotel_id'] !== null) $out[$id]['native_anex_hotel_ids'][$o['native_anex_hotel_id']] = true;
        foreach ($o['urls'] as $u) $out[$id]['urls'][$u] = true;
        $out[$id]['offers'][] = $o;
    }
    foreach ($out as &$h) {
        ksort($h['operator_fingerprint']);
        $h['native_anex_hotel_ids'] = array_map('strval', array_keys($h['native_anex_hotel_ids']));
        sort($h['native_anex_hotel_ids'], SORT_STRING);
        $h['urls'] = array_keys($h['urls']);
        sort($h['urls'], SORT_STRING);
    }
    unset($h);
    return $out;
}

function hmf_fingerprint_overlap(array $a, array $b): array {
    $ops = array_values(array_intersect(array_keys($a), array_keys($b)));
    sort($ops, SORT_STRING);
    return [
        'operators' => $ops,
        'count' => count($ops),
        'contains_anex' => in_array('anex', $ops, true),
        'contains_biblio' => in_array('biblio', $ops, true),
        'contains_funsun' => in_array('funsun', $ops, true),
        'contains_intourist' => in_array('intourist', $ops, true),
    ];
}

function hmf_price_gap(?float $a, ?float $b): ?array {
    if ($a === null || $b === null || $a <= 0 || $b <= 0) return null;
    $abs = abs($a - $b);
    return ['absolute' => $abs, 'relative' => $abs / max($a, $b)];
}

function hmf_room_candidates(array $tvOffers, array $samoOffers, array $anexOffers = []): array {
    $index = static function(array $offers): array {
        $out = [];
        foreach ($offers as $o) {
            if (!is_array($o) || ($o['room_key'] ?? '') === '' || ($o['context_key'] ?? '') === '') continue;
            $out[$o['context_key']][$o['room_key']][] = $o;
        }
        return $out;
    };
    $tv = $index($tvOffers);
    $sa = $index($samoOffers);
    $ax = $index($anexOffers);
    $agg = [];

    foreach ($tv as $ctx => $tvRooms) {
        if (!isset($sa[$ctx])) continue;
        foreach ($tvRooms as $roomKey => $tvRows) {
            if (!isset($sa[$ctx][$roomKey])) continue;
            $saRows = $sa[$ctx][$roomKey];
            $key = $roomKey;
            if (!isset($agg[$key])) {
                $agg[$key] = [
                    'room_key' => $roomKey,
                    'tv_rooms' => [],
                    'samo_rooms' => [],
                    'anex_rooms' => [],
                    'operators' => [],
                    'contexts' => [],
                    'meal_pairs' => [],
                    'price_gaps' => [],
                    'evidence_count' => 0,
                    'safe_to_write_now' => false,
                ];
            }
            foreach ($tvRows as $t) foreach ($saRows as $s) {
                $agg[$key]['tv_rooms'][$t['room_raw']] = true;
                $agg[$key]['samo_rooms'][$s['room_raw']] = true;
                $agg[$key]['operators'][$t['operator']] = true;
                $agg[$key]['contexts'][$ctx] = true;
                $mealPair = hmf_norm($t['meal_raw']) . ' ↔ ' . hmf_norm($s['meal_raw']);
                $agg[$key]['meal_pairs'][$mealPair] = true;
                if (($g = hmf_price_gap($t['price'], $s['price'])) !== null) $agg[$key]['price_gaps'][] = $g;
                $agg[$key]['evidence_count']++;
            }
            if (isset($ax[$ctx][$roomKey])) {
                foreach ($ax[$ctx][$roomKey] as $a) {
                    if ($a['room_raw'] !== '') $agg[$key]['anex_rooms'][$a['room_raw']] = true;
                }
            }
        }
    }

    $out = [];
    foreach ($agg as $c) {
        foreach (['tv_rooms','samo_rooms','anex_rooms','operators','contexts','meal_pairs'] as $k) {
            $c[$k] = array_keys($c[$k]);
            sort($c[$k], SORT_STRING);
        }
        $c['operator_count'] = count($c['operators']);
        $c['context_count'] = count($c['contexts']);
        $c['evidence_class'] = $c['operator_count'] >= 2 || $c['context_count'] >= 2
            ? 'repeated_same_hotel_context'
            : 'single_context_exact_room_key';
        $out[] = $c;
    }
    usort($out, static fn(array $a, array $b): int =>
        [$b['operator_count'],$b['context_count'],$b['evidence_count'],$a['room_key']]
        <=>
        [$a['operator_count'],$a['context_count'],$a['evidence_count'],$b['room_key']]
    );
    return $out;
}

function hmf_resolve(array $tvRows, array $samoRows, array $anexRows = []): array {
    $tv = hmf_group_hotels($tvRows, 'tourvisor');
    $sa = hmf_group_hotels($samoRows, 'andromeda');
    $ax = hmf_group_hotels($anexRows, 'anex');

    $anexByNative = [];
    foreach ($ax as $h) foreach ($h['native_anex_hotel_ids'] as $native) $anexByNative[$native][] = $h;

    $candidates = [];
    foreach ($tv as $tvh) foreach ($sa as $sah) {
        $nameExact = $tvh['hotel_key'] !== '' && $tvh['hotel_key'] === $sah['hotel_key'];
        $nativeOverlap = array_values(array_intersect($tvh['native_anex_hotel_ids'], $sah['native_anex_hotel_ids']));
        $fp = hmf_fingerprint_overlap($tvh['operator_fingerprint'], $sah['operator_fingerprint']);
        if (!$nameExact && !$nativeOverlap) continue;

        $directAnexOffers = [];
        foreach ($nativeOverlap as $native) {
            foreach ($anexByNative[$native] ?? [] as $h) foreach ($h['offers'] as $o) $directAnexOffers[] = $o;
        }

        $candidates[] = [
            'tv_hotel_id' => $tvh['hotel_id'],
            'samo_hotel_id' => $sah['hotel_id'],
            'tv_name' => $tvh['hotel_name'],
            'samo_name' => $sah['hotel_name'],
            'name_exact' => $nameExact,
            'operator_overlap' => $fp,
            'tv_operator_fingerprint' => $tvh['operator_fingerprint'],
            'samo_operator_fingerprint' => $sah['operator_fingerprint'],
            'native_anex_overlap' => $nativeOverlap,
            'tv_urls' => $tvh['urls'],
            'samo_urls' => $sah['urls'],
            'room_candidates' => hmf_room_candidates($tvh['offers'], $sah['offers'], $directAnexOffers),
            'hotel_evidence_class' => $nativeOverlap
                ? 'direct_anex_anchor_plus_operator_fingerprint'
                : 'exact_hotel_name_plus_operator_fingerprint',
            'safe_to_write_now' => false,
        ];
    }

    usort($candidates, static fn(array $a, array $b): int =>
        [count($b['native_anex_overlap']),$b['operator_overlap']['count'],$a['tv_hotel_id'],$a['samo_hotel_id']]
        <=>
        [count($a['native_anex_overlap']),$a['operator_overlap']['count'],$b['tv_hotel_id'],$b['samo_hotel_id']]
    );

    return [
        'schema' => 'anytour.match.operator_fingerprint_room_evidence.v1',
        'tourvisor_hotels' => count($tv),
        'samo_hotels' => count($sa),
        'direct_anex_hotels' => count($ax),
        'hotel_candidates' => $candidates,
        'hotel_candidate_count' => count($candidates),
        'room_candidate_count' => array_sum(array_map(static fn(array $x): int => count($x['room_candidates']), $candidates)),
        'database_writes' => 0,
        'mapping_writes' => 0,
        'supplier_calls' => 0,
    ];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? '') !== '--self-test') {
        fwrite(STDERR, "OFFLINE_ONLY\n");
        exit(2);
    }
    if (hmf_operator_family('FUN&SUN') !== 'funsun') throw new RuntimeException('operator');
    if (hmf_room_key('Deluxe Sea View Room') !== 'deluxe sea view') throw new RuntimeException('room');
    if (!hmf_generic_product('Fortuna Antalya 5*')) throw new RuntimeException('generic');
    echo "MATCH_OPERATOR_FINGERPRINT_ROOM_EVIDENCE_SELFTEST_OK\n";
}
