<?php
declare(strict_types=1);

/**
 * MATCH #1971: mass Tourvisor ANEX-only -> operatorLink -> public ANEX card -> hotelCode evidence.
 * Read-only: supplier/card GETs + CURRENT DB readback, never writes mappings or business data.
 */
const HM_COUNTRY = '__MATCH_COUNTRY__';
const HM_DATE = '__MATCH_DATE__';
const HM_OPERATION = '__MATCH_OPERATION__';
const HM_NIGHTS = 7;
const HM_TV_CONTINUE_CAP = 4;
const HM_STATUS_POLL_CAP = 40;
const HM_RESULT_LIMIT = 10000;
const HM_CARD_CAP = 450;
const HM_BODY_CAP = 2500000;
const HM_MARKER = 'MATCH_TV_ANEX_HOTELCODE_JSON:';

function hm_fail(string $stage, string $reason, array $extra = []): void {
    echo HM_MARKER . json_encode(array_merge([
        'status' => 'failed',
        'stage' => $stage,
        'reason' => $reason,
        'operation_id' => HM_OPERATION,
        'country' => HM_COUNTRY,
        'date' => HM_DATE,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'raw_provider_bodies_recorded' => false,
        'token_values_recorded' => false,
        'no_replay' => true,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(2);
}

function hm_text($value, int $max = 500): string {
    if (!is_scalar($value)) return '';
    $s = trim((string)(preg_replace('/\s+/u', ' ', (string)$value) ?? ''));
    if ($s === '') return '';
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

function hm_country_key($value): ?string {
    $s = function_exists('mb_strtolower') ? mb_strtolower(trim((string)$value), 'UTF-8') : strtolower(trim((string)$value));
    $s = str_replace(['ё', '_', '-'], ['е', ' ', ' '], $s);
    $s = (string)(preg_replace('/\s+/u', ' ', $s) ?? $s);
    $map = [
        'egypt' => ['египет', 'egypt'],
        'turkey' => ['турция', 'turkey', 'türkiye', 'turkiye'],
        'thailand' => ['таиланд', 'thailand'],
        'uae' => ['оаэ', 'объединенные арабские эмираты', 'united arab emirates', 'uae'],
        'vietnam' => ['вьетнам', 'vietnam', 'viet nam'],
        'srilanka' => ['шри ланка', 'sri lanka'],
        'maldives' => ['мальдивы', 'maldives'],
        'cuba' => ['куба', 'cuba'],
    ];
    foreach ($map as $key => $aliases) if (in_array($s, $aliases, true)) return $key;
    return null;
}

function hm_norm_tokens($value): array {
    $s = (string)$value;
    $s = str_replace(['Ё', 'ё', '&'], ['Е', 'е', ' and '], $s);
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = (string)(preg_replace('/\bex\.?\s*/iu', ' ', $s) ?? $s);
    $s = (string)(preg_replace('/\b(?:formerly|former|бывш(?:ий|ая|ее)|ex name)\b/iu', ' ', $s) ?? $s);
    preg_match_all('/[\p{L}\p{N}]+/u', $s, $m);
    $drop = array_fill_keys(['hotel','hotels','resort','resorts','spa','the','and','by','otel','отель','отели','гостиница'], true);
    $out = [];
    foreach ($m[0] as $token) {
        if ($token === '' || isset($drop[$token])) continue;
        $out[$token] = true;
    }
    $tokens = array_keys($out);
    sort($tokens, SORT_STRING);
    return $tokens;
}

function hm_semantic(array $tvTokens, array $cardTokens): array {
    if (!$tvTokens || !$cardTokens) return ['state' => 'unknown', 'overlap' => 0, 'ratio' => null];
    $common = array_values(array_intersect($tvTokens, $cardTokens));
    $den = max(1, min(count($tvTokens), count($cardTokens)));
    $ratio = count($common) / $den;
    $exact = $tvTokens === $cardTokens;
    $strong = $exact || (count($common) >= 2 && $ratio >= 0.80) || (count($tvTokens) === 1 && count($common) === 1);
    return ['state' => $strong ? 'corroborated' : 'non_corroborating', 'overlap' => count($common), 'ratio' => round($ratio, 4)];
}

function hm_scalar(array $row, array $keys): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && is_scalar($row[$key])) {
            $v = hm_text($row[$key]);
            if ($v !== '') return $v;
        }
    }
    return '';
}

function hm_nested_name($value): string {
    if (is_scalar($value)) return hm_text($value);
    if (!is_array($value)) return '';
    foreach (['name','title','label'] as $key) if (isset($value[$key]) && is_scalar($value[$key])) return hm_text($value[$key]);
    return '';
}

function hm_num($value): ?float {
    if (!is_scalar($value) || trim((string)$value) === '' || !is_numeric((string)$value)) return null;
    return (float)$value;
}

function hm_coords_from_hotel(array $hotel): array {
    $sources = [$hotel];
    foreach (['common','location','geo','coordinates'] as $key) if (is_array($hotel[$key] ?? null)) $sources[] = $hotel[$key];
    foreach ($sources as $src) {
        $lat = hm_num($src['latitude'] ?? $src['lat'] ?? null);
        $lon = hm_num($src['longitude'] ?? $src['lng'] ?? $src['lon'] ?? null);
        if ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180) return [$lat, $lon];
    }
    return [null, null];
}

function hm_operator_link_allowed(string $url): bool {
    if ($url === '' || strlen($url) > 2048) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') return false;
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '' || !preg_match('/(^|\.)anextour\.ru$/D', $host)) return false;
    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    foreach (array_keys($query) as $key) {
        if (preg_match('/(?:token|jwt|auth|apikey|api_key|password|secret|session|sid)/i', (string)$key)) return false;
    }
    return true;
}

function hm_walk_tv($node, array &$rows): void {
    if (!is_array($node)) return;
    $link = '';
    foreach (['operatorLink','operator_link','operatorUrl','operator_url'] as $key) {
        if (isset($node[$key]) && is_scalar($node[$key])) { $link = hm_text($node[$key], 2048); break; }
    }
    if ($link !== '' && is_array($node['hotel'] ?? null)) {
        $hotel = $node['hotel'];
        $tvIdRaw = $hotel['id'] ?? $hotel['hotelId'] ?? $hotel['hotel_id'] ?? null;
        $tvId = filter_var($tvIdRaw, FILTER_VALIDATE_INT);
        $name = hm_nested_name($hotel['name'] ?? null);
        if ($name === '') $name = hm_scalar($hotel, ['title','hotelName']);
        if ($tvId !== false && (int)$tvId > 0 && $name !== '') {
            $country = hm_nested_name($hotel['country'] ?? null);
            $region = hm_nested_name($hotel['region'] ?? null);
            $subregion = hm_nested_name($hotel['subRegion'] ?? $hotel['subregion'] ?? null);
            [$lat, $lon] = hm_coords_from_hotel($hotel);
            $rows[] = [
                'tour_id' => hm_scalar($node, ['id','tourId','tour_id']),
                'operator' => hm_nested_name($node['operator'] ?? null),
                'operator_link' => $link,
                'tv_hotel_id' => (int)$tvId,
                'tv_hotel_name' => $name,
                'tv_country' => $country,
                'tv_region' => $region,
                'tv_subregion' => $subregion,
                'tv_latitude' => $lat,
                'tv_longitude' => $lon,
            ];
        }
    }
    foreach ($node as $child) if (is_array($child)) hm_walk_tv($child, $rows);
}

function hm_complete(array $data): bool {
    if ((int)($data['progress'] ?? 0) >= 100) return true;
    $s = strtolower(trim((string)($data['status'] ?? '')));
    if (in_array($s, ['complete','completed','done','ready'], true)) return true;
    foreach ($data as $value) if (is_array($value) && hm_complete($value)) return true;
    return false;
}

function hm_poll_tv(int $searchId): int {
    for ($i = 1; $i <= HM_STATUS_POLL_CAP; $i++) {
        if ($i > 1) usleep(5000000);
        if (hm_complete(v2_data_tv_get('/tours/search/' . $searchId . '/status', ['operatorStatus' => false]))) return $i;
    }
    throw new RuntimeException('tourvisor_search_not_complete');
}

function hm_fetch_tv(int $searchId): array {
    $limits = [HM_RESULT_LIMIT, 5000, 2000, 1000, 500, 250, 100];
    $last = null;
    foreach ($limits as $limit) {
        try { return ['limit' => $limit, 'payload' => v2_data_tv_get('/tours/search/' . $searchId, ['limit' => $limit])]; }
        catch (Throwable $e) { $last = $e; }
    }
    throw new RuntimeException('tourvisor_results_unavailable', 0, $last);
}

function hm_card_fetch(string $url): array {
    if (!hm_operator_link_allowed($url)) return ['status' => 'rejected', 'reason' => 'operator_link_not_allowed'];
    $body = '';
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'AnyTour-MATCH-evidence/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/json;q=0.8,*/*;q=0.5'],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (stripos($line, 'location:') === 0) $headers[] = trim(substr($line, 9));
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
            $remaining = HM_BODY_CAP - strlen($body);
            if ($remaining > 0) $body .= substr($chunk, 0, $remaining);
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effective = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($ok === false || $errno !== 0) return ['status' => 'failed', 'reason' => 'curl_' . $errno, 'detail' => hm_text($error, 120), 'http' => $http];
    if ($http < 200 || $http >= 400) return ['status' => 'failed', 'reason' => 'http_' . $http, 'http' => $http];
    if ($effective !== '' && !hm_operator_link_allowed($effective)) return ['status' => 'rejected', 'reason' => 'redirect_outside_anex', 'http' => $http];
    return ['status' => 'completed', 'http' => $http, 'effective_url' => $effective !== '' ? $effective : $url, 'body' => $body, 'redirects' => $headers];
}

function hm_extract_hotel_codes(array $sources): array {
    $codes = [];
    foreach ($sources as $source) {
        if (!is_string($source) || $source === '') continue;
        $decoded = html_entity_decode(rawurldecode($source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $patterns = [
            '/[?&]hotelCode=(\d{1,12})(?:[&#"\'\s]|$)/i',
            '/["\']hotelCode["\']\s*[:=]\s*["\']?(\d{1,12})/i',
            '/\bhotelCode\s*[=:]\s*["\']?(\d{1,12})/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $decoded, $m)) foreach ($m[1] as $code) if ((int)$code > 0) $codes[(string)(int)$code] = true;
        }
    }
    $out = array_keys($codes);
    sort($out, SORT_NATURAL);
    return $out;
}

function hm_card_title(string $body): string {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/isu', $body, $m)) return hm_text(strip_tags(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')), 300);
    foreach (['hotelName','hotel_name','name'] as $key) {
        $quoted = preg_quote($key, '/');
        if (preg_match('/["\']' . $quoted . '["\']\s*:\s*["\']([^"\']{2,200})["\']/isu', $body, $m)) return hm_text(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 300);
    }
    return '';
}

function hm_card_coords(string $body): array {
    $patterns = [
        '/["\']latitude["\']\s*:\s*["\']?(-?\d{1,2}(?:\.\d+)?)["\']?\s*,\s*["\']longitude["\']\s*:\s*["\']?(-?\d{1,3}(?:\.\d+)?)/i',
        '/["\']lat["\']\s*:\s*["\']?(-?\d{1,2}(?:\.\d+)?)["\']?\s*,\s*["\'](?:lng|lon)["\']\s*:\s*["\']?(-?\d{1,3}(?:\.\d+)?)/i',
    ];
    foreach ($patterns as $pattern) if (preg_match($pattern, $body, $m)) {
        $lat = (float)$m[1]; $lon = (float)$m[2];
        if (abs($lat) <= 90 && abs($lon) <= 180 && ($lat != 0.0 || $lon != 0.0)) return [$lat, $lon];
    }
    return [null, null];
}

function hm_haversine(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float {
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return null;
    $r = 6371.0088;
    $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1); $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
}

function hm_select_in(PDO $db, string $sql, array $ids): array {
    $ids = array_values(array_unique(array_filter($ids, static fn($v) => (string)$v !== '')));
    if (!$ids) return [];
    $stmt = $db->prepare(str_replace('__IN__', implode(',', array_fill(0, count($ids), '?')), $sql));
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if (PHP_SAPI !== 'cli') hm_fail('bootstrap', 'cli_only');
    if (!in_array(HM_COUNTRY, ['egypt','turkey','thailand','uae','vietnam','srilanka','maldives','cuba'], true)) hm_fail('bootstrap', 'country_unarmed');
    if (!preg_match('/^20\d\d-\d\d-\d\d$/D', HM_DATE)) hm_fail('bootstrap', 'date_unarmed');
    if (!str_starts_with(HM_OPERATION, 'hotel-match-tourvisor-anex-hotelcode-mass-1971-')) hm_fail('bootstrap', 'operation_unarmed');

    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') hm_fail('bootstrap', 'server_root_invalid');
    require_once $root . '/config.php';
    $dbFile = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
    $tvFile = is_file($root . '/data/tourvisor-client-v1.php') ? $root . '/data/tourvisor-client-v1.php' : $root . '/v2/data/tourvisor-client-v1.php';
    if (!is_file($dbFile) || !is_file($tvFile)) hm_fail('bootstrap', 'runtime_dependency_missing');
    require_once $dbFile;
    require_once $tvFile;
    $db = v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $countries = $db->query('SELECT id,name FROM catalog_countries WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC);
    $countryRow = null;
    foreach ($countries as $row) if (hm_country_key($row['name'] ?? '') === HM_COUNTRY) { $countryRow = $row; break; }
    if (!$countryRow) hm_fail('bootstrap', 'country_not_found');
    $countryId = (int)$countryRow['id'];
    $countryName = (string)$countryRow['name'];

    if (!defined('TOURVISOR_ANEX_JWT')) hm_fail('tourvisor', 'anex_account_credential_missing');
    $tvToken = trim((string)constant('TOURVISOR_ANEX_JWT'));
    if (stripos($tvToken, 'Bearer ') === 0) $tvToken = trim(substr($tvToken, 7));
    if ($tvToken === '') hm_fail('tourvisor', 'anex_account_credential_empty');
    putenv('TOURVISOR_JWT=' . $tvToken);

    $start = v2_data_tv_get('/tours/search', [
        'departureId' => 1,
        'countryId' => $countryId,
        'dateFrom' => HM_DATE,
        'dateTo' => HM_DATE,
        'nightsFrom' => HM_NIGHTS,
        'nightsTo' => HM_NIGHTS,
        'adults' => 2,
        'currency' => 'RUB',
        'onlyCharter' => false,
        'onlyDirect' => false,
    ]);
    $searchId = (int)($start['searchId'] ?? $start['id'] ?? 0);
    if ($searchId <= 0) hm_fail('tourvisor', 'search_id_missing');

    $statusPolls = hm_poll_tv($searchId);
    $seen = [];
    $resultRounds = [];
    $continueCalls = 0;
    $stopReason = 'continue_cap';
    for ($round = 0; $round <= HM_TV_CONTINUE_CAP; $round++) {
        $fetched = hm_fetch_tv($searchId);
        $rawRows = [];
        hm_walk_tv($fetched['payload'], $rawRows);
        $before = count($seen);
        foreach ($rawRows as $row) {
            if (!hm_operator_link_allowed($row['operator_link'])) continue;
            $key = $row['tv_hotel_id'] . '|' . $row['operator_link'];
            if (!isset($seen[$key])) $seen[$key] = $row;
        }
        $resultRounds[] = ['round' => $round, 'limit' => $fetched['limit'], 'raw_rows' => count($rawRows), 'unique_operator_links' => count($seen)];
        if ($round >= HM_TV_CONTINUE_CAP) break;
        if ($round > 0 && count($seen) === $before) { $stopReason = 'no_growth'; break; }
        usleep(15000000);
        try {
            v2_data_tv_get('/tours/search/' . $searchId . '/continue');
            $continueCalls++;
            $statusPolls += hm_poll_tv($searchId);
        } catch (Throwable $e) {
            $stopReason = 'continue_unavailable_after_complete';
            break;
        }
    }

    $tvRows = array_values($seen);
    usort($tvRows, static fn($a, $b) => [$a['tv_hotel_id'], $a['operator_link']] <=> [$b['tv_hotel_id'], $b['operator_link']]);
    if (count($tvRows) > HM_CARD_CAP) $tvRows = array_slice($tvRows, 0, HM_CARD_CAP);

    $evidence = [];
    $cardCalls = 0;
    foreach ($tvRows as $row) {
        $cardCalls++;
        $card = hm_card_fetch($row['operator_link']);
        $codes = [];
        $title = '';
        $cardLat = null; $cardLon = null;
        $effective = '';
        if (($card['status'] ?? '') === 'completed') {
            $effective = hm_text($card['effective_url'] ?? '', 2048);
            $body = (string)($card['body'] ?? '');
            $codes = hm_extract_hotel_codes([$row['operator_link'], $effective, $body]);
            $title = hm_card_title($body);
            [$cardLat, $cardLon] = hm_card_coords($body);
        } else {
            $codes = hm_extract_hotel_codes([$row['operator_link']]);
        }
        $pathText = '';
        $parts = parse_url($effective !== '' ? $effective : $row['operator_link']);
        if (is_array($parts)) $pathText = str_replace(['-','_','/'], ' ', (string)($parts['path'] ?? ''));
        $semantic = hm_semantic(hm_norm_tokens($row['tv_hotel_name']), hm_norm_tokens($title . ' ' . $pathText));
        $row['card_status'] = $card['status'] ?? 'unknown';
        $row['card_reason'] = $card['reason'] ?? null;
        $row['card_http'] = $card['http'] ?? null;
        $row['effective_url'] = $effective;
        $row['hotel_codes'] = $codes;
        $row['card_title'] = $title;
        $row['card_latitude'] = $cardLat;
        $row['card_longitude'] = $cardLon;
        $row['semantic'] = $semantic;
        $evidence[] = $row;
        usleep(150000);
    }

    $tvIds = array_values(array_unique(array_map(static fn($r) => (string)$r['tv_hotel_id'], $evidence)));
    $codes = [];
    foreach ($evidence as $r) foreach ($r['hotel_codes'] as $code) $codes[$code] = true;
    $codeIds = array_keys($codes);

    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    $catalog = hm_select_in($db, 'SELECT id,is_active,country_id,country_name,region_name,subregion_name,name,category,rating,latitude,longitude FROM catalog_hotels WHERE id IN (__IN__)', $tvIds);
    $maps = hm_select_in($db, 'SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN (__IN__) ORDER BY anex_hotel_id,catalog_hotel_id', $codeIds);
    $decisions = hm_select_in($db, 'SELECT DISTINCT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN (__IN__)', $codeIds);
    $exclusions = hm_select_in($db, 'SELECT DISTINCT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN (__IN__)', $codeIds);
    $occupancy = hm_select_in($db, 'SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE catalog_hotel_id IN (__IN__) AND enabled=1 ORDER BY catalog_hotel_id,anex_hotel_id', $tvIds);
    $db->exec('ROLLBACK');

    $catalogBy = [];
    foreach ($catalog as $r) $catalogBy[(string)$r['id']] = $r;
    $mapsBy = [];
    foreach ($maps as $r) $mapsBy[(string)$r['anex_hotel_id']][] = $r;
    $protected = [];
    foreach (array_merge($decisions, $exclusions) as $r) $protected[(string)$r['anex_hotel_id']] = true;
    $occupiedBy = [];
    foreach ($occupancy as $r) $occupiedBy[(string)$r['catalog_hotel_id']][] = $r;

    $rows = [];
    $counts = [];
    foreach ($evidence as $r) {
        $bucket = 'needs_more_evidence';
        $reasons = [];
        $tvId = (string)$r['tv_hotel_id'];
        $catalogRow = $catalogBy[$tvId] ?? null;
        $uniqueCode = count($r['hotel_codes']) === 1 ? (string)$r['hotel_codes'][0] : null;
        $distanceTvCard = hm_haversine($r['tv_latitude'], $r['tv_longitude'], $r['card_latitude'], $r['card_longitude']);
        $distanceLocalCard = $catalogRow ? hm_haversine(hm_num($catalogRow['latitude'] ?? null), hm_num($catalogRow['longitude'] ?? null), $r['card_latitude'], $r['card_longitude']) : null;
        $distanceTvLocal = $catalogRow ? hm_haversine($r['tv_latitude'], $r['tv_longitude'], hm_num($catalogRow['latitude'] ?? null), hm_num($catalogRow['longitude'] ?? null)) : null;

        if (($r['card_status'] ?? '') !== 'completed' && !$uniqueCode) {
            $bucket = 'card_fetch_failed'; $reasons[] = (string)($r['card_reason'] ?? 'unknown');
        } elseif (count($r['hotel_codes']) === 0) {
            $bucket = 'hotelcode_missing';
        } elseif (count($r['hotel_codes']) > 1) {
            $bucket = 'hotelcode_conflict';
        } elseif (!$catalogRow || (int)$catalogRow['is_active'] !== 1) {
            $bucket = 'catalog_target_missing_or_inactive';
        } elseif ((int)$catalogRow['country_id'] !== $countryId || hm_country_key($catalogRow['country_name'] ?? '') !== HM_COUNTRY) {
            $bucket = 'country_conflict';
        } elseif (($r['tv_country'] ?? '') !== '' && hm_country_key($r['tv_country']) !== null && hm_country_key($r['tv_country']) !== HM_COUNTRY) {
            $bucket = 'tourvisor_country_conflict';
        } elseif (($distanceTvCard !== null && $distanceTvCard > 5.0) || ($distanceLocalCard !== null && $distanceLocalCard > 5.0) || ($distanceTvLocal !== null && $distanceTvLocal > 5.0)) {
            $bucket = 'coordinate_conflict_gt5km';
        } elseif ($uniqueCode !== null && isset($protected[$uniqueCode])) {
            $bucket = 'protected_manual_or_exclusion';
        } else {
            $current = $uniqueCode !== null ? ($mapsBy[$uniqueCode] ?? []) : [];
            if ($current) {
                $same = array_values(array_filter($current, static fn($m) => (string)$m['catalog_hotel_id'] === $tvId && (int)$m['enabled'] === 1));
                if (count($same) === 1 && count($current) === 1) $bucket = 'existing_mapping';
                else $bucket = 'current_anex_mapping_conflict';
            } else {
                $other = array_values(array_filter($occupiedBy[$tvId] ?? [], static fn($m) => (string)$m['anex_hotel_id'] !== (string)$uniqueCode));
                if ($other) $bucket = 'local_target_occupied';
                else {
                    // The Tourvisor ANEX-only operatorLink -> ANEX card -> unique hotelCode chain is the primary independent identity proof.
                    // Name/path/title comparison is a corroboration/guard signal; lack of token overlap across scripts is not treated as a contradiction.
                    $bucket = 'safe_tourvisor_anex_hotelcode';
                    if (($r['semantic']['state'] ?? '') !== 'corroborated') $reasons[] = 'semantic_not_independently_corroborated';
                }
            }
        }

        $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
        $rows[] = [
            'bucket' => $bucket,
            'reason' => $reasons,
            'anex_hotel_id' => $uniqueCode,
            'tv_hotel_id' => $tvId,
            'tv_hotel_name' => $r['tv_hotel_name'],
            'local_hotel_name' => $catalogRow['name'] ?? null,
            'country' => HM_COUNTRY,
            'region' => $r['tv_region'],
            'subregion' => $r['tv_subregion'],
            'operator_link' => $r['operator_link'],
            'effective_url' => $r['effective_url'],
            'card_title' => $r['card_title'],
            'semantic' => $r['semantic'],
            'coordinates' => [
                'tourvisor' => [$r['tv_latitude'], $r['tv_longitude']],
                'catalog' => [$catalogRow !== null ? hm_num($catalogRow['latitude'] ?? null) : null, $catalogRow !== null ? hm_num($catalogRow['longitude'] ?? null) : null],
                'anex_card' => [$r['card_latitude'], $r['card_longitude']],
                'distance_tv_card_km' => $distanceTvCard !== null ? round($distanceTvCard, 3) : null,
                'distance_catalog_card_km' => $distanceLocalCard !== null ? round($distanceLocalCard, 3) : null,
                'distance_tv_catalog_km' => $distanceTvLocal !== null ? round($distanceTvLocal, 3) : null,
            ],
            'tour_id' => $r['tour_id'],
            'card_http' => $r['card_http'],
        ];
    }
    ksort($counts);
    usort($rows, static fn($a, $b) => [$a['bucket'], (int)($a['anex_hotel_id'] ?? PHP_INT_MAX), (int)$a['tv_hotel_id']] <=> [$b['bucket'], (int)($b['anex_hotel_id'] ?? PHP_INT_MAX), (int)$b['tv_hotel_id']]);

    $safePairs = [];
    foreach ($rows as $r) if ($r['bucket'] === 'safe_tourvisor_anex_hotelcode') $safePairs[$r['anex_hotel_id'] . '|' . $r['tv_hotel_id']] = true;

    echo HM_MARKER . json_encode([
        'status' => 'completed',
        'operation_id' => HM_OPERATION,
        'country' => HM_COUNTRY,
        'country_id' => $countryId,
        'country_name' => $countryName,
        'date' => HM_DATE,
        'nights' => HM_NIGHTS,
        'search_id_recorded' => false,
        'tourvisor' => [
            'account_scope' => 'ANEX-only',
            'operatorIds' => 'omitted',
            'status_polls' => $statusPolls,
            'continue_calls' => $continueCalls,
            'stop_reason' => $stopReason,
            'rounds' => $resultRounds,
            'operator_link_rows' => count($seen),
            'card_probe_cap' => HM_CARD_CAP,
        ],
        'card_calls' => $cardCalls,
        'counts' => $counts,
        'distinct_safe_pairs' => count($safePairs),
        'rows' => $rows,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'raw_provider_bodies_recorded' => false,
        'token_values_recorded' => false,
        'no_replay' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    hm_fail('runtime', preg_replace('/[^a-zA-Z0-9_.:-]+/', '_', hm_text($e->getMessage(), 120)) ?: 'exception');
}
