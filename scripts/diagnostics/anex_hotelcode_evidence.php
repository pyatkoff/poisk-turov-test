<?php
declare(strict_types=1);

/**
 * Pure MATCH helper for ANEX hotelCode evidence.
 *
 * The code is accepted only from public ANEX hotel/media URLs. The path slug is
 * never treated as identity. No I/O, DB, network, supplier calls or credentials.
 */
function anytour_anex_hotelcode_url(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F<>\\\\]/', $url)) {
        return ['status' => 'invalid_url'];
    }
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string)($parts['host'] ?? '')) !== 'files.anextour.ru'
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        || (isset($parts['port']) && (int)$parts['port'] !== 443)
        || !str_starts_with((string)($parts['path'] ?? ''), '/hotel/')) {
        return ['status' => 'invalid_url'];
    }

    $query = (string)($parts['query'] ?? '');
    $codes = [];
    foreach (explode('&', $query) as $pair) {
        if ($pair === '') continue;
        [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
        $key = rawurldecode($rawKey);
        $value = rawurldecode($rawValue);
        if (preg_match('/(?:oauth|token|authorization|auth|credential|password|secret|session|signature|api[_-]?key)/i', $key)) {
            return ['status' => 'invalid_url'];
        }
        if (strcasecmp($key, 'hotelCode') !== 0) continue;
        if (!preg_match('/^[1-9][0-9]{0,7}$/D', $value)) return ['status' => 'invalid_code'];
        $codes[] = (int)$value;
    }
    if ($codes === []) return ['status' => 'no_code'];
    if (count($codes) !== 1) return ['status' => 'ambiguous_code'];
    return ['status' => 'confirmed', 'hotel_code' => $codes[0], 'source' => 'anex_public_hotel_url'];
}

/**
 * Require one consistent positive hotelCode across all supplied URL evidence.
 * Mixed/non-ANEX URLs fail closed instead of being silently discarded.
 */
function anytour_anex_hotelcode_evidence(array $urls): array
{
    if ($urls === [] || count($urls) > 40) {
        return ['status' => 'insufficient_evidence', 'hotel_code' => null, 'confirmed_urls' => 0];
    }
    $codes = [];
    $confirmed = 0;
    foreach ($urls as $url) {
        if (!is_string($url)) {
            return ['status' => 'invalid_evidence', 'hotel_code' => null, 'confirmed_urls' => $confirmed];
        }
        $parsed = anytour_anex_hotelcode_url($url);
        if ($parsed['status'] === 'no_code') continue;
        if ($parsed['status'] !== 'confirmed') {
            return ['status' => 'invalid_evidence', 'hotel_code' => null, 'confirmed_urls' => $confirmed];
        }
        $confirmed++;
        $codes[(int)$parsed['hotel_code']] = true;
        if (count($codes) > 1) {
            return ['status' => 'conflicting_codes', 'hotel_code' => null, 'confirmed_urls' => $confirmed];
        }
    }
    if ($confirmed === 0 || count($codes) !== 1) {
        return ['status' => 'insufficient_evidence', 'hotel_code' => null, 'confirmed_urls' => $confirmed];
    }
    return [
        'status' => 'confirmed',
        'hotel_code' => (int)array_key_first($codes),
        'confirmed_urls' => $confirmed,
        'identity_source' => 'anex_hotelCode_query',
    ];
}
