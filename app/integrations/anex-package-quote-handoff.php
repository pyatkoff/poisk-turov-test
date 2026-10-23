<?php
declare(strict_types=1);
require_once __DIR__ . '/anex-package-quote-runtime.php';

/** Keep already-hydrated labels in the existing session; no new lookup or store. */
function anytour_anex_handoff_catalog(array $metadata, int $country): array
{
    $out = [];
    foreach ($metadata as $id => $row) {
        if (!is_int($id) || $id < 1 || $id > 2147483647 || !is_array($row)
            || (!is_int($row['id'] ?? null) && !is_string($row['id'] ?? null))
            || (string) $row['id'] !== (string) $id
            || (!is_int($row['country_id'] ?? null) && !is_string($row['country_id'] ?? null))
            || (string) $row['country_id'] !== (string) $country) continue;
        $out[$id] = ['uiId' => $id, 'namespace' => 'search3-ui',
            'name' => $row['name'] ?? null, 'resort' => $row['region_name'] ?? ''];
    }
    return $out;
}

function anytour_anex_handoff_text($value, int $max, bool $required = false): bool
{
    return is_string($value) && preg_match('//u', $value) === 1
        && preg_match('/[\x00-\x1f\x7f]/u', $value) === 0
        && preg_match_all('/./us', $value) <= $max && (!$required || trim($value) !== '');
}

function anytour_anex_handoff_held(string $field, string $owner = 'INT'): array
{
    return ['status' => 'held', 'missing' => [['field' => $field, 'owner' => $owner]]];
}

/** Internal adapter only: no public action, signer, HTTP, supplier or browser observation input. */
function anytour_anex_handoff_freeze(array $request, array &$state, callable $checkpoint, int $now, ?callable $clock = null): array
{
    try {
        $clock = $clock ?? static fn(): int => time();
        $current = $clock();
        if (!is_int($current) || $current < $now || $current > 8640000000000) return anytour_anex_handoff_held('record.clock');
        return anytour_anex_handoff_freeze_retained($request, $state, $checkpoint, $current, $clock);
    }
    catch (Throwable $ignored) { return anytour_anex_handoff_held('record.invalid_retained'); }
}

/** Caller holds the canonical session lock. Cache is inside the existing max-three quote pairs. */
function anytour_anex_handoff_freeze_retained(array $request, array &$state, callable $checkpoint, int $now, callable $clock): array
{
    $keys = ['generation', 'search_ref', 'offer_ref', 'local_hotel_id', 'choice_ref'];
    if (count($request) !== count($keys) || array_diff($keys, array_keys($request))
        || !is_int($request['generation'] ?? null) || $request['generation'] < 1 || $request['generation'] > 2147483647
        || !is_string($request['search_ref'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/D', $request['search_ref'])
        || !is_string($request['offer_ref'] ?? null) || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $request['offer_ref'])
        || !is_string($request['choice_ref'] ?? null) || !preg_match('/\Aanex_quote:[a-f0-9]{64}\z/D', $request['choice_ref'])
        || !is_int($request['local_hotel_id'] ?? null)) return anytour_anex_handoff_held('selection.pins', 'SEARCH');
    $saved = $state['gateway']['saved_offers'] ?? [];
    if ($now < 1 || !is_int($saved['created_at'] ?? null) || !is_int($saved['expires_at'] ?? null)
        || $saved['expires_at'] !== $saved['created_at'] + 900 || $now < $saved['created_at']
        || $now >= $saved['expires_at'] || ($state['generation'] ?? null) !== $request['generation']
        || ($saved['search_ref'] ?? null) !== $request['search_ref']) return anytour_anex_handoff_held('search.current_context');
    $key = $request['offer_ref']; $ref = $request['choice_ref'];
    $entry = $saved['offers'][$key] ?? [];
    $offer = $entry['offer'] ?? [];
    $known = array_column($state['gateway']['search']['offers'] ?? [], null, 'offer_key')[$key] ?? [];
    $id = $offer['hotel']['local_id'] ?? null;
    if (($offer['provider'] ?? null) !== 'anex' || ($offer['supplier_namespace'] ?? null) !== 'anex_online'
        || ($offer['offer_key'] ?? null) !== $key || ($offer['kind'] ?? null) !== 'concrete'
        || ($known['kind'] ?? null) !== 'concrete' || ($known['hotel_external_id'] ?? null) !== ($offer['hotel']['external_id'] ?? null)
        || !is_string($known['supplier_offer_id'] ?? null) || $known['supplier_offer_id'] === ''
        || ($offer['hotel']['mapping_status'] ?? null) !== 'resolved'
        || !is_int($id) || $id !== $request['local_hotel_id']) return anytour_anex_handoff_held('offer.retained_identity');
    $attempt = $state['package_quotes'][$key] ?? null;
    $currency = $state['gateway']['search']['context']['currency_id'] ?? null;
    $quoteEntry = $entry;
    $quoteEntry['supplier_currency_id'] = is_int($currency) || is_string($currency) ? (string) $currency : null;
    if (!is_array($attempt) || !anytour_anex_quote_reprice_ready($attempt)
        || ($attempt['context_digest'] ?? null) !== anytour_anex_quote_context_digest($request, $state, $offer, $known, $quoteEntry)
        || $now >= $attempt['expires_at'] || !isset($attempt['pairs'][$ref]['public'])
        || !isset($attempt['choices'][$ref]['selection'])) return anytour_anex_handoff_held('quote.complete_exact_pair');
    $selection = $attempt['choices'][$ref]['selection'];
    if ('anex_quote:' . hash('sha256', ($attempt['id'] ?? '') . "\0" . json_encode($selection, JSON_THROW_ON_ERROR)) !== $ref)
        return anytour_anex_handoff_held('flights.retained_pair_binding');
    $public = $attempt['pairs'][$ref]['public'];
    if ($public['verified_at'] > $now || $public['expires_at'] > $saved['expires_at']) return anytour_anex_handoff_held('quote.observed_deadline');
    $context = $state['handoff_context'] ?? [];
    $hotel = $context['hotels'][$id] ?? [];
    if (($context['version'] ?? null) !== 1 || !anytour_anex_handoff_text($context['origin'] ?? null, 180, true))
        return anytour_anex_handoff_held('trip.origin');
    $country = $context['countryUiId'] ?? null;
    if (!is_string($country) || !preg_match('/\A[1-9][0-9]{0,8}\z/D', $country)
        || $country !== (string) ($state['params']['countryId'] ?? '')) return anytour_anex_handoff_held('trip.countryUiId');
    if (!is_array($hotel) || count($hotel) !== 4 || array_diff(array_keys($hotel), ['uiId', 'namespace', 'name', 'resort'])
        || ($hotel['uiId'] ?? null) !== $id || ($hotel['namespace'] ?? null) !== 'search3-ui'
        || !anytour_anex_handoff_text($hotel['name'] ?? null, 191, true)
        || !anytour_anex_handoff_text($hotel['resort'] ?? null, 300)) return anytour_anex_handoff_held('hotel.catalog_labels');
    $ages = $state['gateway']['search']['context']['child_ages'] ?? [];
    if (!is_array($ages) || !array_is_list($ages) || count($ages) > 3
        || count($ages) !== ($offer['children'] ?? null)
        || !is_int($offer['adults'] ?? null) || $offer['adults'] < 1 || $offer['adults'] > 6) return anytour_anex_handoff_held('trip.party');
    foreach ($ages as $age) if (!is_int($age) || $age < 0 || $age > 17) return anytour_anex_handoff_held('trip.childAges');
    $day = static fn($v): bool => is_string($v) && preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $v, $m)
        && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    if (!$day($offer['checkin'] ?? null) || !is_int($offer['nights'] ?? null) || $offer['nights'] < 1 || $offer['nights'] > 365)
        return anytour_anex_handoff_held('trip.departure_nights');
    $end = $offer['checkout'] ?? null;
    if ($end !== null && (!$day($end) || $end <= $offer['checkin'])) return anytour_anex_handoff_held('trip.stayEnd');
    foreach (['room' => 500, 'meal' => 300, 'hotel_place' => 500] as $field => $limit)
        if (!anytour_anex_handoff_text($offer[$field] ?? '', $limit, $field !== 'hotel_place'))
            return anytour_anex_handoff_held('stay.' . $field);
    $legs = $public['choice']['legs'] ?? null;
    if (!is_array($legs) || !array_is_list($legs) || count($legs) !== 2) return anytour_anex_handoff_held('flights.selected_labels');
    foreach ($legs as $leg) if (!is_array($leg) || array_keys($leg) !== ['label']
        || !anytour_anex_handoff_text($leg['label'], 1000, true)) return anytour_anex_handoff_held('flights.selected_labels');
    $price = $public['price'];
    if (!is_string($price['amount']) || !preg_match('/\A(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?\z/D', $price['amount'])
        || $price['currency'] !== 'RUB') return anytour_anex_handoff_held('price.receiver_bounds');
    $terms = ['source' => 'anex', 'hotel' => $hotel,
        'trip' => ['origin' => $context['origin'], 'countryUiId' => $country, 'departure' => $offer['checkin'],
            'nights' => $offer['nights'], 'stayEnd' => $end, 'stayEndBasis' => $end === null ? 'unknown' : 'shown-stay-end',
            'adults' => $offer['adults'], 'childAges' => $ages],
        'stay' => ['room' => $offer['room'], 'meal' => $offer['meal'], 'placement' => $offer['hotel_place'] ?? '', 'operator' => ''],
        'flights' => ['state' => 'selected', 'outward' => [$legs[0]], 'inward' => [$legs[1]]],
        'price' => ['amount' => $price['amount'], 'currency' => 'RUB', 'state' => 'checked',
            'description' => 'Supplier gross for the retained selected pair; observed conditions.',
            'expiresAt' => $public['expires_at'], 'basis' => 'selected-pair',
            'finalPriceVerified' => false, 'authority' => 'browser-observation-only']];
    $digest = hash('sha256', json_encode([$attempt['context_digest'], $attempt['choices'][$ref]['selection'],
        $public['verified_at'], $terms], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    $old = $attempt['pairs'][$ref]['handoff_record'] ?? null;
    if ($old !== null) {
        if (!is_array($old) || ($old['digest'] ?? null) !== $digest || !is_string($old['json'] ?? null) || strlen($old['json']) > 24576)
            return anytour_anex_handoff_held('record.immutable_terms');
        $record = json_decode($old['json'], true);
        $captured = $record['observation']['capturedAt'] ?? null;
        if (!is_int($captured) || $captured < 1 || $captured > $now * 1000
            || !is_string($record['binding']['record_id'] ?? null)
            || !preg_match('/\Aanex:[a-f0-9]{32}\z/D', $record['binding']['record_id'])
            || ($record['binding']['revision'] ?? null) !== 1
            || $record !== ['binding' => $record['binding'], 'observation' =>
                ['schema' => 1, 'kind' => 'search3_browser_observation', 'capturedAt' => $captured] + $terms]
            || array_keys($record['binding']) !== ['record_id', 'revision']
            || hash('sha256', $old['json']) !== ($old['json_sha256'] ?? null)) return anytour_anex_handoff_held('record.integrity');
        return ['status' => 'ready', 'record' => $record, 'record_sha256' => $old['json_sha256']];
    }
    $record = ['binding' => ['record_id' => 'anex:' . bin2hex(random_bytes(16)), 'revision' => 1],
        'observation' => ['schema' => 1, 'kind' => 'search3_browser_observation', 'capturedAt' => $now * 1000] + $terms];
    $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($json) > 24576) return anytour_anex_handoff_held('record.receiver_size');
    $frozen = ['digest' => $digest, 'json' => $json, 'json_sha256' => hash('sha256', $json)];
    $state['package_quotes'][$key]['pairs'][$ref]['handoff_record'] = $frozen;
    $expected = $state;
    try {
        $checkpoint($state);
        if ($state !== $expected) return anytour_anex_handoff_held('record.checkpoint_changed');
    } catch (Throwable $ignored) {
        // Do not overwrite a concurrently selected foreign context after a session-lock transition.
        if ($state === $expected) $state['package_quotes'][$key]['pairs'][$ref]['handoff_record'] = ['status' => 'unknown'];
        return anytour_anex_handoff_held('record.checkpoint_unknown');
    }
    $after = $clock();
    if (!is_int($after) || $after < $now || $after >= $public['expires_at'] || $after >= $saved['expires_at'])
        return anytour_anex_handoff_held('record.checkpoint_deadline');
    return ['status' => 'ready', 'record' => $record, 'record_sha256' => $frozen['json_sha256']];
}
