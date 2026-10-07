<?php
declare(strict_types=1);
require_once __DIR__ . '/anex-package-quote-client.php';

function anytour_anex_quote_rows($value): array
{
    if (!is_array($value) || $value === []) return [];
    return array_keys($value) === range(0, count($value) - 1) ? $value : [$value];
}

function anytour_anex_quote_text($value): string
{
    if (!is_string($value) && !is_int($value)) return '';
    return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
}

/** A parser CATCLAIM is not assumed to share identity with B2B: prove the returned package. */
function anytour_anex_quote_identity_date($value): ?string
{
    if (!is_string($value)) return null;
    if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/D', $value, $parts) !== 1
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) return null;
    return $parts[1] . '-' . $parts[2] . '-' . $parts[3];
}

/** Only explicit supplier age classes; no names, birth dates or inferred zero counts. */
function anytour_anex_quote_participant_counts(array $doc): ?array
{
    $people = anytour_anex_quote_rows($doc['peoples']['people'] ?? null);
    if ($people === [] || count($people) > 17) return null;
    $counts = ['adults' => 0, 'children' => 0];
    $keys = [];
    foreach ($people as $person) {
        if (!is_array($person)) return null;
        $classes = [];
        foreach (['age', 'human'] as $field) {
            $value = $person[$field] ?? null;
            if (in_array($value, ['ADL', 'CHD', 'INF'], true)) $classes[$value] = true;
        }
        if (count($classes) !== 1 || isset($classes['INF'])) return null;
        if (isset($person['key'])) {
            if (!is_string($person['key']) && !is_int($person['key'])) return null;
            $key = (string) $person['key'];
            if ($key === '' || isset($keys[$key])) return null;
            $keys[$key] = true;
        }
        ++$counts[isset($classes['ADL']) ? 'adults' : 'children'];
    }
    return $counts;
}

/** Diagnostic classifications never substitute for the strict identity checks. */
function anytour_anex_quote_identity(array $response, array $offer, ?array &$mismatches = null): array
{
    $mismatches = [];
    $doc = $response['bron']['claim']['claimDocument'] ?? null;
    if (!is_array($doc)) {
        $mismatches['document'] = 'missing';
        throw new RuntimeException('ANEX_QUOTE_IDENTITY_UNCONFIRMED');
    }
    foreach (['datebeg' => 'checkin', 'dateend' => 'checkout', 'nights' => 'nights'] as $from => $to) {
        if (!isset($doc[$from], $offer[$to]) || (string) $doc[$from] !== (string) $offer[$to]) {
            $mismatches[$to] = isset($doc[$from], $offer[$to]) ? 'mismatch' : 'missing';
            if (in_array($to, ['checkin', 'checkout'], true)) {
                $actualDate = anytour_anex_quote_identity_date($doc[$from] ?? null);
                $expectedDate = anytour_anex_quote_identity_date($offer[$to] ?? null);
                if ($actualDate !== null && $actualDate === $expectedDate) $mismatches[$to] = 'format';
            }
        }
    }
    // The public ANEX client derives party from peoples.people. Some matching
    // documents omit the summary counters; every participant must then be explicit.
    $party = isset($doc['adult'], $doc['child']) ? null : anytour_anex_quote_participant_counts($doc);
    foreach (['adult' => 'adults', 'child' => 'children'] as $from => $to) {
        $actual = $doc[$from] ?? ($party[$to] ?? null);
        if ($actual === null || !isset($offer[$to])) $mismatches[$to] = 'missing';
        elseif ((string) $actual !== (string) $offer[$to]
            || ($party !== null && (string) $actual !== (string) $party[$to])) $mismatches[$to] = 'mismatch';
    }
    // Nonzero infants have no verified traveller contract in the search normalizer.
    // Matching adult/child counters must not hide an explicit supplier infant.
    if (array_key_exists('infant', $doc)) {
        $infants = $doc['infant'];
        if ((!is_string($infants) && !is_int($infants))
            || preg_match('/\A[0-9]+\z/D', (string) $infants) !== 1) $mismatches['infants'] = 'format';
        elseif ((string) $infants !== '0') $mismatches['infants'] = 'mismatch';
    }
    foreach (anytour_anex_quote_rows($doc['peoples']['people'] ?? null) as $person) {
        if (is_array($person)
            && (($person['age'] ?? null) === 'INF' || ($person['human'] ?? null) === 'INF')) {
            $mismatches['infants'] = 'mismatch';
        }
    }
    $hotels = anytour_anex_quote_rows($doc['hotels']['hotel'] ?? null);
    if (count($hotels) !== 1 || !is_array($hotels[0])
        || (string) ($hotels[0]['key'] ?? '') !== (string) ($offer['hotel']['external_id'] ?? '')) {
        $mismatches['hotel'] = count($hotels) !== 1 ? 'shape' : 'mismatch';
    }
    $hotel = is_array($hotels[0] ?? null) ? $hotels[0] : [];
    foreach (['room' => 'room', 'meal' => 'meal', 'htplace' => 'hotel_place'] as $from => $to) {
        $expected = anytour_anex_quote_text($offer[$to] ?? null);
        if ($expected === '' || anytour_anex_quote_text($hotel[$from] ?? null) !== $expected) {
            $mismatches[$to] = $expected === '' || !isset($hotel[$from]) ? 'missing' : 'mismatch';
        }
    }
    if (!empty($offer['external_room_id']) && (string) ($hotel['roomKey'] ?? '') !== $offer['external_room_id']) {
        $mismatches['room_id'] = isset($hotel['roomKey']) ? 'mismatch' : 'missing';
    }
    if ($mismatches !== []) throw new RuntimeException('ANEX_QUOTE_IDENTITY_UNCONFIRMED');
    return $doc;
}

function anytour_anex_quote_transport_map(array $rows): array
{
    if (count($rows) !== 2) throw new RuntimeException('ANEX_QUOTE_TRANSPORT_UNCONFIRMED');
    $map = [];
    foreach ($rows as $row) {
        if (!is_array($row)) throw new RuntimeException('ANEX_QUOTE_TRANSPORT_UNCONFIRMED');
        $route = (string) ($row['routeIndex'] ?? ''); $uid = $row['uid'] ?? null;
        if (!preg_match('/\A[0-9]{1,3}\z/D', $route) || !is_string($uid) || $uid === ''
            || strlen($uid) > 2048 || preg_match('/[\x00-\x20\x7f]/', $uid) || isset($map[$route])) {
            throw new RuntimeException('ANEX_QUOTE_TRANSPORT_UNCONFIRMED');
        }
        $map[$route] = $uid;
    }
    ksort($map, SORT_NUMERIC);
    return $map;
}

/** Projection contains display facts, never supplier UIDs, raw response or personal fields. */
function anytour_anex_quote_leg(array $row): array
{
    $details = anytour_anex_quote_rows($row['details']['detail'] ?? null);
    $segments = [];
    foreach (array_slice($details ?: [$row], 0, 8) as $segment) {
        if (!is_array($segment)) continue;
        $parts = [];
        foreach (['full_airline', 'name', 'flight_number', 'datebeg', 'dateend', 'depart_time', 'arrival_time', 'departureAirportName', 'arrivalAirportName'] as $field) {
            $text = anytour_anex_quote_text($segment[$field] ?? null);
            if ($text !== '' && strlen($text) <= 240) $parts[] = $text;
        }
        foreach (['departure', 'arrival'] as $direction) {
            foreach (['port', 'time'] as $field) {
                $text = anytour_anex_quote_text($segment[$direction][$field] ?? null);
                if ($text !== '' && strlen($text) <= 240) $parts[] = $text;
            }
        }
        if ($parts) $segments[] = implode(' · ', array_unique($parts));
    }
    if (!$segments) throw new RuntimeException('ANEX_QUOTE_TRANSPORT_UNCONFIRMED');
    return ['label' => implode(' / ', $segments)];
}

/** GDS variants remain supplier pairs; charter directions may be independently combined. */
function anytour_anex_quote_choices(array $response, string $sessionId): array
{
    $doc = $response['bron']['claim']['claimDocument'];
    $packet = anytour_anex_quote_rows($doc['transports']['transport'] ?? null);
    $packetMap = anytour_anex_quote_transport_map($packet);
    $routes = array_keys($packetMap);
    $variants = anytour_anex_quote_rows($response['bron']['claim']['variants']['transports']['transport'] ?? null);
    $pairs = [$packet];
    if ((string) ($doc['freightExternal'] ?? '') === '0') {
        $directions = [$routes[0] => [], $routes[1] => []];
        foreach (array_merge($packet, $variants) as $row) {
            $route = $row['routeIndex'] ?? null;
            if (isset($directions[$route]) && count($directions[$route]) < 6) $directions[$route][] = $row;
        }
        foreach ($directions[$routes[0]] as $out) foreach ($directions[$routes[1]] as $back) $pairs[] = [$out, $back];
    } else {
        // This grouping is explicit in the official _formatBookingTransport client.
        foreach (array_chunk(array_slice($variants, 0, 78), 2) as $pair) if (count($pair) === 2) $pairs[] = $pair;
    }
    $choices = [];
    foreach ($pairs as $pair) {
        try {
            $map = anytour_anex_quote_transport_map($pair);
            if (array_keys($map) !== $routes) continue;
            usort($pair, static fn(array $a, array $b): int => (int) $a['routeIndex'] <=> (int) $b['routeIndex']);
            $legs = array_map('anytour_anex_quote_leg', $pair);
        } catch (RuntimeException $ignored) { continue; }
        $ref = 'anex_quote:' . hash('sha256', $sessionId . "\0" . json_encode($map, JSON_THROW_ON_ERROR));
        $choices[$ref] = ['selection' => $map, 'public' => ['choice_ref' => $ref, 'legs' => $legs, 'current' => $map === $packetMap]];
    }
    if (!$choices) throw new RuntimeException('ANEX_QUOTE_TRANSPORT_UNCONFIRMED');
    return $choices;
}

/** Use the supplier's gross RUB money row verbatim; no net, FX, APD or fuel arithmetic. */
function anytour_anex_quote_price(array $doc): array
{
    $rub = [];
    foreach (anytour_anex_quote_rows($doc['moneys']['money'] ?? null) as $money) {
        if (!is_array($money) || strtoupper((string) ($money['currency'] ?? '')) !== 'RUB') continue;
        $price = $money['price'] ?? null;
        if ((!is_string($price) && !is_int($price) && !is_float($price))
            || !preg_match('/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/D', (string) $price) || (float) $price <= 0) {
            throw new RuntimeException('ANEX_QUOTE_PRICE_UNCONFIRMED');
        }
        $rub[] = (string) $price;
    }
    if (count($rub) !== 1) throw new RuntimeException('ANEX_QUOTE_PRICE_UNCONFIRMED');
    return ['amount' => $rub[0], 'currency' => 'RUB', 'basis' => 'supplier_gross_package'];
}

/** Called only after the existing gateway has validated live search/offer/local-hotel identity. */
function anytour_anex_quote_run(array $request, array &$state, array $offer, array $known,
    array $savedEntry, callable $factory, callable $checkpoint, int $now): array
{
    $key = $request['offer_ref'];
    $base = ['status' => 'quote_unavailable', 'final_price_verified' => false, 'selection_state' => 'disabled'];
    if (($offer['kind'] ?? null) !== 'concrete') return $base;
    $attempt = $state['package_quotes'][$key] ?? null;
    if ($attempt !== null && (!is_array($attempt) || ($attempt['expires_at'] ?? 0) <= $now)) {
        return array_replace($base, ['status' => 'quote_expired']);
    }
    if ($request['action'] === 'quote_start') {
        if (is_array($attempt)) return $attempt['public'] ?? array_replace($base, ['status' => 'quote_unknown']);
        $currency = $savedEntry['supplier_currency_id'] ?? null;
        $claim = $known['supplier_offer_id'] ?? null;
        if (!is_string($currency) || !is_string($claim) || $currency === '' || $claim === '') return $base;
        // Client-owned numeric calculation id, separate from a real booked claim.
        $id = (string) random_int(1000000000000, 9999999999999);
        $state['package_quotes'][$key] = ['id' => $id, 'expires_at' => min($now + 600, $state['gateway']['saved_offers']['expires_at']), 'stages' => []];
        $attempt =& $state['package_quotes'][$key];
        $stages = ['start', 'transports'];
    } else {
        $ref = $request['choice_ref'] ?? null;
        if (!is_array($attempt) || !is_string($ref) || !isset($attempt['choices'][$ref])) return $base;
        if (isset($attempt['chosen'])) {
            if ($attempt['chosen'] !== $ref) return array_replace($base, ['status' => 'quote_selection_locked']);
            return $attempt['public'] ?? array_replace($base, ['status' => 'quote_unknown']);
        }
        if (($attempt['stages']['transports'] ?? null) !== 'complete') return $base;
        $attempt =& $state['package_quotes'][$key];
        $attempt['chosen'] = $ref;
        unset($attempt['public']);
        $stages = ['SetTransport', 'calcfull'];
    }
    $client = null;
    $identityMismatches = [];
    try {
        foreach ($stages as $stage) {
            // Durable reservation precedes EACH supplier request. A crash cannot grant a replay.
            $attempt['stages'][$stage] = 'unknown';
            $checkpoint($state);
            if ($client === null) $client = $factory();
            if (!$client instanceof AnyTourAnexPackageQuoteClient) throw new RuntimeException('ANEX_QUOTE_CLIENT_UNAVAILABLE');
            if ($stage === 'start') $response = $client->start($claim, $attempt['id'], $currency);
            elseif ($stage === 'transports') $response = $client->transports($attempt['id']);
            elseif ($stage === 'SetTransport') $response = $client->select($attempt['id'], $attempt['choices'][$ref]['selection']);
            else $response = $client->calculate($attempt['id']);
            $doc = anytour_anex_quote_identity($response, $offer, $identityMismatches);
            if (in_array($stage, ['SetTransport', 'calcfull'], true)
                && anytour_anex_quote_transport_map(anytour_anex_quote_rows($doc['transports']['transport'] ?? null)) !== $attempt['choices'][$ref]['selection']) {
                throw new RuntimeException('ANEX_QUOTE_TRANSPORT_UNCONFIRMED');
            }
            $attempt['stages'][$stage] = 'complete';
            $attempt['diagnostics'][$stage] = $client->lastRequestDiagnostics();
            if ($stage === 'transports') {
                $attempt['choices'] = anytour_anex_quote_choices($response, $attempt['id']);
                $attempt['public'] = array_replace($base, ['status' => 'quote_choices',
                    'choices' => array_values(array_column($attempt['choices'], 'public'))]);
            } elseif ($stage === 'calcfull') {
                $price = anytour_anex_quote_price($doc);
                $attempt['public'] = ['status' => 'quote_verified', 'final_price_verified' => true,
                    'selection_state' => 'preview_only', 'price' => $price, 'choice' => $attempt['choices'][$ref]['public'],
                    'verified_at' => $now, 'expires_at' => $attempt['expires_at']];
            }
            $checkpoint($state);
        }
    } catch (Throwable $error) {
        $code = $error->getMessage();
        $allowed = ['ANEX_QUOTE_IDENTITY_UNCONFIRMED', 'ANEX_QUOTE_TRANSPORT_UNCONFIRMED', 'ANEX_QUOTE_PRICE_UNCONFIRMED',
            'ANEX_QUOTE_SUPPLIER_REJECTED', 'ANEX_QUOTE_HTTP_ERROR', 'ANEX_QUOTE_TRANSPORT_ERROR', 'ANEX_QUOTE_INVALID_RESPONSE',
            'ANEX_QUOTE_CLIENT_UNAVAILABLE', 'ANEX_QUOTE_RATE_LIMIT'];
        $attempt['public'] = array_replace($base, ['status' => 'quote_failed', 'reason' => in_array($code, $allowed, true) ? $code : 'ANEX_QUOTE_UNKNOWN']);
        if (in_array($stage, ['start', 'transports', 'SetTransport', 'calcfull'], true)) {
            $attempt['public']['failure_stage'] = $stage;
        }
        if ($code === 'ANEX_QUOTE_IDENTITY_UNCONFIRMED' && $identityMismatches !== []) {
            $attempt['public']['identity_mismatches'] = $identityMismatches;
        }
        if ($client instanceof AnyTourAnexPackageQuoteClient) $attempt['diagnostics'][$stage] = $client->lastRequestDiagnostics();
        $httpStatus = $attempt['diagnostics'][$stage]['http_status'] ?? null;
        if ($code === 'ANEX_QUOTE_HTTP_ERROR' && is_int($httpStatus) && $httpStatus >= 100 && $httpStatus <= 599) {
            $attempt['public']['supplier_http_status'] = $httpStatus;
        }
        // If checkpoint itself fails, leave the durable reservation UNKNOWN; never send a success.
        $checkpoint($state);
    }
    return $attempt['public'] ?? array_replace($base, ['status' => 'quote_unknown']);
}
