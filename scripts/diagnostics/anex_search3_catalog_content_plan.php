<?php
declare(strict_types=1);

/** A new cohort requires an explicit checked-in plan; a code rerun never advances it. */
function anytour_anex_content_plan($plan): array
{
    if (!is_array($plan) || ($plan['schema_version'] ?? null) !== 1
        || !is_string($plan['batch_key'] ?? null) || !is_string($plan['photos_batch_key'] ?? null)
        || !preg_match('/^owner_content_review_[0-9]{8}_[0-9]{2}$/D', $plan['batch_key'])
        || $plan['photos_batch_key'] !== str_replace('owner_content_review_', 'owner_content_review_photos_', $plan['batch_key'])
        || !is_int($plan['limit'] ?? null) || $plan['limit'] < 1 || $plan['limit'] > 25
        || ($plan['priority'] ?? null) !== 'unresolved_verified_frequency') {
        throw new InvalidArgumentException('ANEX_CONTENT_INVALID_PLAN');
    }
    return $plan;
}

/** Existing reservations of every status, plus the completed pilot, are ineligible. */
function anytour_anex_content_choose(array $rows, array $verifiedIds, int $limit): array
{
    if ($limit < 1 || $limit > 25 || count($rows) > 50000) throw new InvalidArgumentException('ANEX_CONTENT_INVALID_QUEUE');
    $verified = array_fill_keys($verifiedIds, true);
    $excluded = array_fill_keys([8121, 16193, 16229, 16330, 17097], true);
    $eligible = [];
    foreach ($rows as $row) {
        $id = anytour_anex_content_id($row['anex_hotel_id'] ?? null);
        if ($id === null || isset($excluded[$id]) || isset($row['content_hotel_id'])) continue;
        $eligible[$id] = ['id' => $id, 'unresolved' => empty($row['mapped_hotel_id']),
            'verified' => isset($verified[$id]), 'search_count' => max(0, (int)($row['search_count'] ?? 0)),
            'last_seen' => (string)($row['last_seen_utc'] ?? '')];
    }
    usort($eligible, static function ($a, $b) {
        return ($b['unresolved'] <=> $a['unresolved']) ?: ($b['verified'] <=> $a['verified'])
            ?: ($b['search_count'] <=> $a['search_count']) ?: strcmp($b['last_seen'], $a['last_seen'])
            ?: ($a['id'] <=> $b['id']);
    });
    return array_column(array_slice($eligible, 0, $limit), 'id');
}

/** Revalidate a saved source before updating it; JSON can decode integral floats as integers. */
function anytour_anex_content_saved_payload(array $row, int $expectedId): array
{
    if (!is_string($row['payload_json'] ?? null) || strlen($row['payload_json']) > 1500000) {
        throw new InvalidArgumentException('ANEX_CONTENT_INVALID_SAVED_PAYLOAD');
    }
    $payload = json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['hotel_id'] ?? null) !== $expectedId
        || !is_array($payload['content'] ?? null) || !is_array($payload['availability'] ?? null)
        || !array_key_exists('source_sha256', $payload) || !array_key_exists('content_sha256', $row)) {
        throw new InvalidArgumentException('ANEX_CONTENT_INVALID_SAVED_PAYLOAD');
    }
    if (($payload['status'] ?? null) === 'empty' && ($row['status'] ?? null) === 'empty') {
        if ($payload['content'] !== [] || $payload['source_sha256'] !== null || $row['content_sha256'] !== null) {
            throw new InvalidArgumentException('ANEX_CONTENT_INVALID_SAVED_PAYLOAD');
        }
        return $payload;
    }
    if (($payload['status'] ?? null) !== 'ok' || ($row['status'] ?? null) !== 'ready'
        || ($payload['content']['id'] ?? null) !== $expectedId) {
        throw new InvalidArgumentException('ANEX_CONTENT_INVALID_SAVED_PAYLOAD');
    }
    foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
        if (!array_key_exists($field, $payload['content'])) throw new InvalidArgumentException('ANEX_CONTENT_INVALID_SAVED_PAYLOAD');
        $value = $payload['content'][$field];
        if ($value === null) continue;
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || abs((float)$value) > $limit) {
            throw new InvalidArgumentException('ANEX_CONTENT_INVALID_SAVED_PAYLOAD');
        }
        $payload['content'][$field] = (float)$value;
    }
    $digest = hash('sha256', json_encode($payload['content'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    if (!is_string($row['content_sha256']) || !hash_equals($digest, $row['content_sha256'])
        || !is_string($payload['source_sha256']) || !hash_equals($digest, $payload['source_sha256'])) {
        throw new InvalidArgumentException('ANEX_CONTENT_INVALID_SAVED_PAYLOAD');
    }
    return $payload;
}

/** Empty Photos responses do not erase the supplier's own DETAILS photographs. */
function anytour_anex_content_merge_photos(array $payload, array $photo): array
{
    if (($payload['status'] ?? null) === 'empty' && ($payload['content'] ?? []) === []
        && ($photo['content']['photos'] ?? []) === []) return $payload;
    if (($payload['content'] ?? []) === [] && ($photo['content']['photos'] ?? []) !== []) {
        $payload['content'] = $photo['content'];
        $payload['availability'] = $photo['availability'];
        $payload['status'] = 'ok';
    }
    $photos = [];
    foreach (array_merge($payload['content']['photos'] ?? [], $photo['content']['photos'] ?? []) as $item) {
        if (count($photos) >= 40) break;
        if (is_array($item) && is_string($item['url'] ?? null) && !isset($photos[$item['url']])) $photos[$item['url']] = $item;
    }
    $payload['content']['photos'] = array_values($photos);
    $payload['availability']['photos'] = $photos ? 'present' : ($photo['availability']['photos'] ?? 'missing');
    return $payload;
}
