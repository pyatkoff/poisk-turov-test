<?php
declare(strict_types=1);

/**
 * Pure projection of the Hotels_DETAILS method payload (without its envelope).
 * Schema: https://dokuwiki.samo.ru/doku.php?id=onlinest:api:hotels:details
 * No I/O, HTML, transport credentials, unknown fields or automatic identity links.
 * Text limits are UTF-8 bytes; collection traversal and retained rows are bounded.
 * Photo URLs are references only: this helper does not download or resolve them.
 */
function anytour_anex_hotel_content(array $data, int $expectedId, string $secret = ''): array
{
    if ($expectedId < 1 || $expectedId > 99999999) {
        throw new InvalidArgumentException('ANEX_CONTENT_INVALID_ID');
    }
    $result = ['status' => 'empty', 'hotel_id' => $expectedId, 'content' => [],
        'availability' => [], 'source_sha256' => null];
    if ($data === []) return $result;
    if (anytour_anex_content_id($data['id'] ?? null) !== $expectedId) {
        $result['status'] = 'id_mismatch';
        return $result;
    }

    $content = ['id' => $expectedId];
    foreach (['name' => 400, 'address' => 1200, 'description' => 16000,
        'location' => 8000, 'transfer' => 8000, 'note' => 8000] as $field => $limit) {
        $content[$field] = anytour_anex_content_text($data[$field] ?? null, $limit, $secret);
    }
    foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
        $value = $data[$field] ?? null;
        $content[$field] = null;
        if ((is_int($value) || is_float($value) || (is_string($value)
            && preg_match('/^-?[0-9]{1,3}(?:\.[0-9]{1,12})?$/D', $value)))
            && is_finite((float)$value) && abs((float)$value) <= $limit) {
            $content[$field] = (float)$value;
        }
    }
    $content['attributes'] = anytour_anex_content_attributes($data['attributes'] ?? null, 80, $secret);
    $content['rooms'] = [];
    if (is_array($data['rooms'] ?? null)) {
        $seen = 0;
        foreach ($data['rooms'] as $room) {
            if (++$seen > 60 || count($content['rooms']) >= 30) break;
            if (!is_array($room)) continue;
            $id = anytour_anex_content_id($room['id'] ?? null);
            $name = anytour_anex_content_text($room['name'] ?? null, 400, $secret);
            if ($id === null || $name === '') continue;
            $content['rooms'][] = ['id' => $id, 'name' => $name,
                'description' => anytour_anex_content_text($room['description'] ?? null, 2400, $secret),
                'attributes' => anytour_anex_content_attributes($room['attributes'] ?? null, 30, $secret)];
        }
    }
    $content['photos'] = [];
    if (is_array($data['photos'] ?? null)) {
        $seen = 0;
        $urls = [];
        foreach ($data['photos'] as $photo) {
            if (++$seen > 200 || count($content['photos']) >= 40) break;
            if (!is_array($photo)) continue;
            $url = anytour_anex_content_photo_url($photo['url'] ?? null, $secret);
            if ($url === '' || isset($urls[$url])) continue;
            $urls[$url] = true;
            $content['photos'][] = ['url' => $url,
                'note' => anytour_anex_content_text($photo['note'] ?? null, 600, $secret)];
        }
    }
    foreach ($content as $field => $value) {
        if ($field === 'id') continue;
        $raw = $data[$field] ?? null;
        $result['availability'][$field] = !array_key_exists($field, $data) ? 'missing'
            : (($value !== '' && $value !== null && $value !== []) ? 'present'
                : (($raw === null || $raw === '' || $raw === []
                    || (is_string($raw) && trim($raw) === '')) ? 'empty' : 'filtered'));
    }
    $result['status'] = 'ok';
    $result['content'] = $content;
    $result['source_sha256'] = hash('sha256', json_encode($content,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    return $result;
}

function anytour_anex_content_id($value): ?int
{
    if (!is_int($value) && !is_string($value)) return null;
    return preg_match('/^[1-9][0-9]{0,7}$/D', (string)$value) ? (int)$value : null;
}

/** Repeated encodings and a credential echo fail closed, without logging values. */
function anytour_anex_content_decoded(string $value, string $secret): ?string
{
    for ($round = 0; $round < 8; ++$round) {
        if ($secret !== '' && strpos($value, $secret) !== false) return null;
        $decoded = rawurldecode(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($decoded === $value) return $value;
        $value = $decoded;
    }
    return null;
}

function anytour_anex_content_text($value, int $limit, string $secret): string
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) return '';
    $value = (string)$value;
    if (strlen($value) > 65536 || !preg_match('//u', $value)) return '';
    $decoded = anytour_anex_content_decoded($value, $secret);
    if ($decoded === null || preg_match('/(?:oauth_token|access_token|api[_-]?key|authorization|password|client_secret)\s*[=:]/i', $decoded)) return '';
    // Decode before stripping: entity-encoded markup is not allowed to survive.
    $value = preg_replace('~<(script|style|iframe|object|svg|math|template)\b[^>]*>.*?(?:</\1\s*>|$)~is', '', $decoded);
    $value = preg_replace('~</?(?:p|div|br|li|ul|ol|h[1-6]|table|tr|td)\b[^>]*>~i', ' ', $value ?? '');
    $value = strip_tags($value ?? '');
    $value = preg_replace('~\b(?:https?|ftp)://[^\s<>]+~i', '', $value);
    $value = str_replace(['<', '>'], '', $value ?? '');
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value);
    $value = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value ?? '') ?? '');
    // Tags/whitespace can split a credential in the original supplier text.
    if (anytour_anex_content_decoded($value, $secret) === null
        || preg_match('/(?:oauth_token|access_token|api[_-]?key|authorization|password|client_secret)\s*[=:]/i', $value)) return '';
    $value = substr($value, 0, $limit);
    while ($value !== '' && !preg_match('//u', $value)) $value = substr($value, 0, -1);
    return trim($value);
}

function anytour_anex_content_photo_url($value, string $secret): string
{
    if (!is_string($value) || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7F<>\\\\]/', $value)) return '';
    $decoded = anytour_anex_content_decoded($value, $secret);
    if ($decoded === null || preg_match('/[\x00-\x20\x7F<>\\\\]/', $decoded)
        || preg_match('/(?:oauth|token|authorization|auth|credential|password|secret|session|signature|api[_-]?key|[?&]sig|[?&]key)(?:[\/=:_-]|$)/i', $decoded)
        || preg_match('/[?&][^=&]{0,80}(?:auth|token|credential|password|secret|session|signature|key)[^=&]{0,80}=/i', $decoded)) return '';
    $parts = parse_url($value);
    if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        || (isset($parts['port']) && $parts['port'] !== 443)) return '';
    $host = strtolower($parts['host'] ?? '');
    // Named public hosts only: excludes IP literals and alternate numeric formats.
    if (strlen($host) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
        || preg_match('/(?:^|\.)(?:localhost|local|internal|invalid|test|example|home|lan)$/D', $host)
        || preg_match('/(?:^|\.)(?:nip\.io|sslip\.io|localtest\.me)$/D', $host)) return '';
    return filter_var($value, FILTER_VALIDATE_URL) === false ? '' : $value;
}

/** Numeric type is supplier metadata; values stay text, including explicit "0". */
function anytour_anex_content_attributes($rows, int $limit, string $secret): array
{
    $result = [];
    if (!is_array($rows)) return $result;
    $seen = 0;
    foreach ($rows as $row) {
        if (++$seen > $limit * 2 || count($result) >= $limit) break;
        if (!is_array($row)) continue;
        $name = anytour_anex_content_text($row['name'] ?? null, 200, $secret);
        $type = $row['type'] ?? null;
        if ($name === '' || (!is_int($type) && !is_string($type))
            || !preg_match('/^[0-9]{1,2}$/D', (string)$type)) continue;
        $item = ['name' => $name, 'type' => (int)$type,
            'value' => anytour_anex_content_text($row['value'] ?? null, 400, $secret)];
        foreach (['id', 'groupKey'] as $key) {
            $id = anytour_anex_content_id($row[$key] ?? null);
            if ($id !== null) $item[$key] = $id;
        }
        if (array_key_exists('group', $row)) $item['group'] = anytour_anex_content_text($row['group'], 160, $secret);
        $result[] = $item;
    }
    return $result;
}
