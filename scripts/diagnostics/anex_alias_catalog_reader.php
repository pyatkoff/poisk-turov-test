<?php
// Read complete canonical/alias competitors in the AnyTour catalogue. No writes or network calls.
error_reporting(0);
ob_start();

function anex_alias_text($value, $limit = 500)
{
    if (!is_string($value)) return '';
    $value = trim($value);
    if (!preg_match('//u', $value)) return '';
    return preg_match('/^(.{0,' . (int) $limit . '})/us', $value, $m) ? $m[1] : '';
}
function anex_alias_number($value, $limit)
{
    if (!is_int($value) && !is_float($value) && !is_numeric($value)) return null;
    $value = (float) $value;
    return is_finite($value) && abs($value) <= $limit ? $value : null;
}
function anex_alias_param(&$params, $value)
{
    $key = ':p' . count($params);
    $params[$key] = $value;
    return $key;
}
function anex_alias_like($value)
{
    return '%' . strtr($value, array('!' => '!!', '%' => '!%', '_' => '!_')) . '%';
}
function anex_alias_tokens($name)
{
    $generic = array_flip(array('hotel','hotels','resort','resorts','spa','the','and','club',
        'apart','aparthotel','apartments','apartment','suites','suite','отель','отели','резорт',
        'ресорт','спа','клуб','апартаменты','звезды','звёзды','stars','star','all','inclusive'));
    preg_match_all('/[\p{L}\p{N}]+/u', $name, $m);
    $tokens = array();
    foreach ($m[0] as $token) {
        if (!isset($generic[$token]) && preg_match('/^.{3,}$/us', $token) && !preg_match('/^\d+$/', $token)) {
            $tokens[$token] = $token;
        }
    }
    usort($tokens, function ($a, $b) { return strlen($b) - strlen($a); });
    return array_slice($tokens, 0, 3);
}

$pdo = null;
$started = false;
$result = array('status' => 'catalog_unavailable', 'items' => array());
try {
    $raw = file_get_contents('php://stdin', false, null, 0, 65537);
    if (!is_string($raw) || strlen($raw) > 65536) throw new RuntimeException();
    $input = json_decode($raw, true, 16);
    if (!is_array($input) || ($input['mode'] ?? '') !== 'alias_review'
        || !isset($input['queries']) || !is_array($input['queries'])
        || count($input['queries']) < 1 || count($input['queries']) > 2) throw new RuntimeException();
    $queries = array();
    foreach ($input['queries'] as $query) {
        if (!is_array($query) || !isset($query['key']) || !is_int($query['key']) || $query['key'] < 1
            || !isset($query['country_id']) || !is_int($query['country_id']) || $query['country_id'] < 1
            || !isset($query['names']) || !is_array($query['names']) || count($query['names']) > 3) {
            throw new RuntimeException();
        }
        $names = array();
        foreach ($query['names'] as $name) {
            $name = anex_alias_text($name, 300);
            if ($name !== '') $names[] = $name;
        }
        if (!$names) throw new RuntimeException();
        $queries[] = array('key' => $query['key'], 'country_id' => $query['country_id'],
            'names' => array_values(array_unique($names)),
            'latitude' => anex_alias_number($query['latitude'] ?? null, 90),
            'longitude' => anex_alias_number($query['longitude'] ?? null, 180));
    }
    $root = realpath(getcwd());
    if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException();
    $helper = false;
    foreach (array('data/db-v1.php', 'v2/data/db-v1.php') as $relative) {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($candidate !== false && is_file($candidate) && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0) {
            $helper = $candidate; break;
        }
    }
    if ($helper === false) throw new RuntimeException();
    require_once $helper;
    if (!function_exists('v2_data_db') || !function_exists('v2_data_normalize_text')) throw new RuntimeException();
    $pdo = v2_data_db();
    if (!($pdo instanceof PDO)) throw new RuntimeException();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('START TRANSACTION READ ONLY');
    $started = true;
    $hasAliases = (bool) $pdo->query("SHOW TABLES LIKE 'hotel_aliases'")->fetchColumn();
    $hasDetails = (bool) $pdo->query("SHOW TABLES LIKE 'catalog_hotel_details'")->fetchColumn();
    if (!$hasAliases) throw new RuntimeException();
    $latitude = $hasDetails ? 'COALESCE(d.latitude,h.latitude)' : 'h.latitude';
    $longitude = $hasDetails ? 'COALESCE(d.longitude,h.longitude)' : 'h.longitude';
    $address = $hasDetails ? 'd.address' : 'NULL';
    $items = array();
    foreach ($queries as $query) {
        $params = array(); $conditions = array(); $normalized = array();
        foreach ($query['names'] as $name) {
            $value = anex_alias_text(v2_data_normalize_text($name), 300);
            if ($value !== '') $normalized[$value] = $value;
        }
        foreach ($normalized as $name) {
            $exact = anex_alias_param($params, $name);
            $conditions[] = '(h.normalized_name=' . $exact . ' OR h.search_key=' . anex_alias_param($params, $name)
                . ' OR am.normalized_alias=' . anex_alias_param($params, $name) . ')';
            $parts = array();
            foreach (anex_alias_tokens($name) as $token) {
                $like = anex_alias_like($token);
                $parts[] = '(h.normalized_name LIKE ' . anex_alias_param($params, $like) . " ESCAPE '!'"
                    . ' OR h.search_key LIKE ' . anex_alias_param($params, $like) . " ESCAPE '!'"
                    . ' OR am.normalized_alias LIKE ' . anex_alias_param($params, $like) . " ESCAPE '!')";
            }
            if ($parts) $conditions[] = '(' . implode(' AND ', $parts) . ')';
        }
        if ($query['latitude'] !== null && $query['longitude'] !== null) {
            $conditions[] = '(' . $latitude . ' BETWEEN ' . anex_alias_param($params, $query['latitude'] - 0.01)
                . ' AND ' . anex_alias_param($params, $query['latitude'] + 0.01) . ' AND ' . $longitude
                . ' BETWEEN ' . anex_alias_param($params, $query['longitude'] - 0.01)
                . ' AND ' . anex_alias_param($params, $query['longitude'] + 0.01) . ')';
        }
        if (!$conditions) throw new RuntimeException();
        $sql = 'SELECT /*+ MAX_EXECUTION_TIME(3000) */ DISTINCT h.id,h.name,h.country_name,h.region_name,'
            . 'h.subregion_name,h.category,' . $latitude . ' AS latitude,' . $longitude . ' AS longitude,'
            . $address . ' AS address FROM catalog_hotels h '
            . ($hasDetails ? 'LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id ' : '')
            . 'LEFT JOIN hotel_aliases am ON am.hotel_id=h.id WHERE h.is_active=1 AND h.country_id='
            . anex_alias_param($params, $query['country_id']) . ' AND (' . implode(' OR ', $conditions)
            . ') ORDER BY h.id ASC LIMIT 4097';
        $statement = $pdo->prepare($sql); $statement->execute($params);
        $candidates = array();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $candidate = array('id' => (int) $row['id'], 'aliases' => array());
            foreach (array('name','country_name','region_name','subregion_name','category') as $field) {
                $candidate[$field] = anex_alias_text(isset($row[$field]) ? (string) $row[$field] : '', 300);
            }
            $candidate['latitude'] = anex_alias_number($row['latitude'], 90);
            $candidate['longitude'] = anex_alias_number($row['longitude'], 180);
            $candidate['address'] = anex_alias_text(isset($row['address']) ? (string) $row['address'] : '', 500);
            $candidates[$candidate['id']] = $candidate;
        }
        $statement->closeCursor();
        $aliasCount = 0; $aliasesExhausted = true;
        if ($candidates) {
            $aliasParams = array(); $holders = array();
            foreach (array_keys($candidates) as $id) $holders[] = anex_alias_param($aliasParams, $id);
            $aliasSql = 'SELECT /*+ MAX_EXECUTION_TIME(3000) */ hotel_id,alias,normalized_alias,source '
                . 'FROM hotel_aliases WHERE hotel_id IN (' . implode(',', $holders) . ') '
                . 'ORDER BY hotel_id,normalized_alias,alias,source LIMIT 8193';
            $aliasStatement = $pdo->prepare($aliasSql); $aliasStatement->execute($aliasParams);
            while ($row = $aliasStatement->fetch(PDO::FETCH_ASSOC)) {
                $aliasCount++;
                if ($aliasCount > 8192) { $aliasesExhausted = false; break; }
                $id = (int) $row['hotel_id'];
                if (!isset($candidates[$id])) throw new RuntimeException();
                $candidates[$id]['aliases'][] = array('alias' => anex_alias_text((string) $row['alias'], 500),
                    'normalized_alias' => anex_alias_text((string) $row['normalized_alias'], 500),
                    'source' => anex_alias_text((string) $row['source'], 100));
            }
            $aliasStatement->closeCursor();
        }
        $list = array_values($candidates);
        $items[] = array('key' => $query['key'], 'candidates' => $list,
            'candidate_set_complete' => count($list) < 4097, 'fetch_limit' => 4097,
            'alias_set_complete' => $aliasesExhausted, 'alias_fetch_limit' => 8193,
            'alias_rows' => min($aliasCount, 8192),
            'query_scope' => 'active_country_canonical_alias_or_geobox');
    }
    $pdo->exec('ROLLBACK'); $started = false;
    $result = array('status' => 'ok', 'items' => $items);
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $started) { try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored2) {} }
}
while (ob_get_level() > 0) ob_end_clean();
$encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || strlen($encoded) > 4000000) $encoded = '{"status":"catalog_unavailable","items":[]}';
echo $encoded, "\n";
