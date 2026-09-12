<?php
declare(strict_types=1);

require_once __DIR__ . '/anex_hotelcode_evidence.php';

/**
 * MATCH offline batch stage, NOT a collector, resolver or acceptance writer.
 * Reuses completed census bytes and saved selected-hotel captures only.
 *
 * Capture: anex_hotel_id, local_hotel_id, country_id, operator=ANEX,
 * operator_filter=ANEX, date_from=date_to (ISO), operator_url, card_url,
 * operator_html (contains the card link), card_html, media_urls (optional).
 * The exporter must bind the Tourvisor selected hotel to operator_url. This
 * analyser does not authenticate an exporter or acquire/replay any search.
 * Consistent URL codes are evidence only; names, qualifiers, country, geography,
 * coordinates, current manual/exclusion/conflict and mapping guards remain
 * mandatory in a separate CURRENT acceptance transaction.
 *
 * CLI: php THIS.php census.json expected-census-sha256 [saved-captures.json]
 * Writes JSON to stdout only. No DB/network/workflow/credentials access.
 */
const HMLO_CORE8 = [1, 2, 4, 8, 9, 10, 12, 16];

function hmlo_id(mixed $value): ?int
{
    if (!(is_int($value) || is_string($value))
        || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$value)) return null;
    return (int)$value;
}

function hmlo_json(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/** Allow only public HTTPS origins, not credentials, fragments or redirect URLs. */
function hmlo_url(string $url, array $hosts): ?array
{
    if ($url !== trim($url) || strlen($url) > 4096
        || preg_match('/[\x00-\x20\x7F<>\\\\]/', $url)) return null;
    $p = parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https'
        || !in_array(strtolower($p['host'] ?? ''), $hosts, true)
        || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])
        || (isset($p['port']) && $p['port'] !== 443)) return null;
    foreach (explode('&', $p['query'] ?? '') as $part) {
        $key = rawurldecode(explode('=', $part, 2)[0]);
        if (preg_match('/oauth|token|authorization|auth|credential|password|secret|session|signature|api[_-]?key/i', $key)) return null;
    }
    return $p;
}

function hmlo_operator_code(string $url): ?int
{
    $p = hmlo_url($url, ['agent.anextour.ru']);
    if ($p === null || ($p['path'] ?? '') !== '/search/tour') return null;
    $codes = [];
    foreach (explode('&', $p['query'] ?? '') as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
        $key = rawurldecode($key);
        if (stripos($key, 'HOTELLIST') === false) continue;
        if (strcasecmp($key, 'HOTELLIST') !== 0) return null;
        $id = hmlo_id(rawurldecode($value));
        if ($id === null) return null;
        $codes[] = $id;
    }
    return count($codes) === 1 ? $codes[0] : null;
}

/** Decode literal HTML/JSON URL escaping, never execute scripts or infer IDs. */
function hmlo_urls(string $document): array
{
    if (strlen($document) > 2 * 1024 * 1024) throw new InvalidArgumentException('DOCUMENT_TOO_LARGE');
    $text = html_entity_decode(str_replace(['\\/', '\\u0026', '\\u003d', '\\u003D'], ['/', '&', '=', '='], $document), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    preg_match_all('~https://[^\s"\'<>\\\\]+~u', $text, $m);
    return array_values(array_unique($m[0]));
}

function hmlo_capture(array $capture, array $target, array $locals, array $excluded): array
{
    $id = hmlo_id($capture['anex_hotel_id'] ?? null);
    $local = hmlo_id($capture['local_hotel_id'] ?? null);
    $result = ['capture_sha256' => hash('sha256', hmlo_json($capture)),
        'anex_hotel_id' => $id, 'local_hotel_id' => $local,
        'status' => 'invalid_capture', 'not_write_authority' => true];
    if ($id !== $target['anex_hotel_id'] || $local === null || !isset($locals[$local])) return $result;
    if (isset($excluded[$id][$local])) { $result['status'] = 'pair_excluded'; return $result; }
    if (($target['product_identity_review'] ?? false) === true) {
        $result['status'] = 'non_single_hotel_label'; return $result;
    }
    if (count($target['country_ids']) !== 1
        || hmlo_id($capture['country_id'] ?? null) !== $target['country_ids'][0]
        || (int)$locals[$local]['country_id'] !== $target['country_ids'][0]) {
        $result['status'] = 'country_conflict'; return $result;
    }
    if (($capture['operator'] ?? null) !== 'ANEX' || ($capture['operator_filter'] ?? null) !== 'ANEX') {
        $result['status'] = 'not_anex_only'; return $result;
    }
    $date = $capture['date_from'] ?? null;
    if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m)
        || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || $date !== ($capture['date_to'] ?? null)) {
        $result['status'] = 'not_one_day'; return $result;
    }
    foreach (['operator_url', 'card_url', 'operator_html', 'card_html'] as $key) {
        if (!isset($capture[$key]) || !is_string($capture[$key])) return $result;
    }
    $code = hmlo_operator_code($capture['operator_url']);
    if ($code === null) { $result['status'] = 'invalid_operator_url'; return $result; }
    if ($code !== $id) { $result['status'] = 'operator_code_mismatch'; return $result; }
    $card = hmlo_url($capture['card_url'], ['anextour.ru', 'www.anextour.ru', 'files.anextour.ru']);
    if ($card === null || !str_starts_with($card['path'] ?? '', '/hotel/')) {
        $result['status'] = 'invalid_card_url'; return $result;
    }
    if (!in_array($capture['card_url'], hmlo_urls($capture['operator_html']), true)) {
        $result['status'] = 'card_not_linked_from_operator'; return $result;
    }
    $media = $capture['media_urls'] ?? [];
    if (!is_array($media) || !array_is_list($media)) return $result;
    $cardUrls = hmlo_urls($capture['card_html']);
    foreach ($media as $url) {
        if (!is_string($url) || !in_array($url, $cardUrls, true)) {
            $result['status'] = 'media_not_in_card'; return $result;
        }
    }
    // Include every observed hotelCode URL, not only a caller-chosen agreeable subset.
    foreach ($cardUrls as $url) {
        if (stripos($url, 'hotelCode') !== false || stripos($url, 'files.anextour.ru/hotel/') !== false) $media[] = $url;
    }
    if (strtolower($card['host']) === 'files.anextour.ru') $media[] = $capture['card_url'];
    $media = array_values(array_unique($media));
    sort($media, SORT_STRING);
    $parsed = anytour_anex_hotelcode_evidence($media);
    if ($parsed['status'] !== 'confirmed') { $result['status'] = $parsed['status']; return $result; }
    if ($parsed['hotel_code'] !== $id) { $result['status'] = 'card_code_mismatch'; return $result; }
    // No name/coordinate/price/rank acceptance is implied by matching URL identifiers.
    $result['status'] = 'consistent_code_requires_identity_recheck';
    $result['hotel_code'] = $id;
    $result['confirmed_urls'] = $parsed['confirmed_urls'];
    $result['operator_url_sha256'] = hash('sha256', $capture['operator_url']);
    $result['operator_document_sha256'] = hash('sha256', $capture['operator_html']);
    $result['card_document_sha256'] = hash('sha256', $capture['card_html']);
    $result['required_rechecks'] = ['current_source_and_local', 'name_alias_qualifiers', 'country_geography_coordinates', 'manual_conflict_exclusions', 'existing_mappings', 'same_transaction_acceptance'];
    return $result;
}

function hmlo_build(array $census, string $sourceHash, array $captures = []): array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $sourceHash) || ($census['status'] ?? '') !== 'completed'
        || ($census['no_replay'] ?? null) !== true) throw new InvalidArgumentException('INVALID_CENSUS');
    foreach (['anex_observations', 'anex_mappings', 'anex_decisions', 'anex_exclusions', 'local', 'anex'] as $key) {
        if (!isset($census[$key]) || !is_array($census[$key]) || !array_is_list($census[$key])) throw new InvalidArgumentException('CENSUS_SECTION_' . $key);
    }
    $protected = $excluded = $locals = $staging = $targets = [];
    foreach (array_merge($census['anex_mappings'], $census['anex_decisions']) as $r) {
        $id = hmlo_id($r['anex_hotel_id'] ?? null);
        if ($id !== null) $protected[$id] = true; // Disabled rows/negative/manual decisions protected too.
    }
    foreach ($census['anex_exclusions'] as $r) {
        $id = hmlo_id($r['anex_hotel_id'] ?? null);
        $local = hmlo_id($r['catalog_hotel_id'] ?? $r['local_hotel_id'] ?? null);
        if ($id === null || $local === null) throw new InvalidArgumentException('INVALID_PAIR_EXCLUSION');
        $excluded[$id][$local] = true;
    }
    foreach ($census['local'] as $r) {
        $id = hmlo_id($r['id'] ?? null);
        if ($id !== null && in_array((int)$r['country_id'], HMLO_CORE8, true) && (int)($r['is_active'] ?? 0) === 1) $locals[$id] = $r;
    }
    foreach ($census['anex'] as $r) $staging[(int)$r['anex_hotel_id']] = $r;
    foreach ($census['anex_observations'] as $r) {
        $id = hmlo_id($r['anex_hotel_id'] ?? null);
        $country = hmlo_id($r['country_id'] ?? null);
        if ($id === null || !in_array($country, HMLO_CORE8, true) || isset($protected[$id])) continue;
        if (!isset($targets[$id])) $targets[$id] = ['anex_hotel_id' => $id, 'country_ids' => [], 'names' => [], 'search_count' => 0, 'last_seen_utc' => '', 'staging_present' => isset($staging[$id]), 'product_identity_review' => false];
        $t = &$targets[$id];
        $t['country_ids'][$country] = $country;
        $name = trim((string)($r['hotel_name'] ?? ''));
        if ($name !== '') $t['names'][$name] = $name;
        $t['search_count'] += max(0, (int)($r['search_count'] ?? 0));
        $t['last_seen_utc'] = max($t['last_seen_utc'], (string)($r['last_seen_utc'] ?? ''));
        if (preg_match('/^(?:fortuna\b|roulette\b|тур\s*["«])/iu', $name)) $t['product_identity_review'] = true;
        unset($t);
    }
    foreach ($targets as &$t) {
        $t['country_ids'] = array_values($t['country_ids']); sort($t['country_ids'], SORT_NUMERIC);
        $t['names'] = array_values($t['names']); sort($t['names'], SORT_STRING);
        $t['next_evidence'] = $t['product_identity_review'] ? 'product_identity_not_single_hotel' : 'saved_tourvisor_anex_operator_card_hotelcode';
        $t['captures'] = [];
    }
    unset($t);
    $rejected = 0;
    if (!array_is_list($captures) || count($captures) > 20000) throw new InvalidArgumentException('INVALID_CAPTURE_BATCH');
    foreach ($captures as $capture) {
        if (!is_array($capture)) throw new InvalidArgumentException('INVALID_CAPTURE_ROW');
        $id = hmlo_id($capture['anex_hotel_id'] ?? null);
        if ($id === null || !isset($targets[$id])) { $rejected++; continue; }
        $targets[$id]['captures'][] = hmlo_capture($capture, $targets[$id], $locals, $excluded);
    }
    foreach ($targets as &$t) {
        usort($t['captures'], static fn($a, $b) => strcmp($a['capture_sha256'], $b['capture_sha256']));
        $localCandidates = [];
        foreach ($t['captures'] as $c) if ($c['status'] === 'consistent_code_requires_identity_recheck') $localCandidates[$c['local_hotel_id']] = true;
        $t['competing_local_targets'] = count($localCandidates) > 1;
        // Even consistent captures NEVER become accepted in this offline stage.
        $t['not_write_authority'] = true;
    }
    unset($t);
    $rows = array_values($targets);
    usort($rows, static fn($a, $b) => ($b['search_count'] <=> $a['search_count']) ?: strcmp($b['last_seen_utc'], $a['last_seen_utc']) ?: ($a['anex_hotel_id'] <=> $b['anex_hotel_id']));
    $product = array_values(array_filter($rows, static fn($r) => $r['product_identity_review']));
    return ['schema' => 'hotel-match-live-operator-evidence/1', 'status' => 'prepared_only',
        'source_operation_id' => $census['operation_id'] ?? null, 'source_census_sha256' => $sourceHash,
        'historical_operations_replayed' => false, 'not_write_authority' => true,
        'database_writes' => 0, 'mapping_writes' => 0, 'supplier_calls' => 0, 'tourvisor_calls' => 0,
        'observations_examined' => count($census['anex_observations']), 'live_unresolved_ids' => count($rows),
        'observed_occurrences' => array_sum(array_column($rows, 'search_count')),
        'absent_from_staging' => count(array_filter($rows, static fn($r) => !$r['staging_present'])),
        'product_review_ids' => count($product), 'product_review_occurrences' => array_sum(array_column($product, 'search_count')),
        'operator_evidence_queue_ids' => count($rows) - count($product), 'out_of_scope_captures' => $rejected,
        'queue_sha256' => hash('sha256', hmlo_json($rows)), 'rows' => $rows];
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        if ($argc < 3 || $argc > 4) throw new InvalidArgumentException('usage: census.json expected-sha256 [saved-captures.json]');
        foreach (array_filter([$argv[1], $argv[3] ?? null]) as $path) {
            if (!is_file($path) || filesize($path) > 64 * 1024 * 1024) throw new InvalidArgumentException('INPUT_FILE_SIZE');
        }
        $raw = file_get_contents($argv[1]);
        if (!is_string($raw) || !hash_equals($argv[2], hash('sha256', $raw))) throw new InvalidArgumentException('CENSUS_HASH_MISMATCH');
        $captures = isset($argv[3]) ? json_decode(file_get_contents($argv[3]), true, 64, JSON_THROW_ON_ERROR) : [];
        echo hmlo_json(hmlo_build(json_decode($raw, true, 64, JSON_THROW_ON_ERROR), $argv[2], $captures)), "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n"); exit(1);
    }
}
