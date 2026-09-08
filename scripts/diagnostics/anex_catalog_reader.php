<?php
// Run only in the AnyTour document root, with a small JSON request on stdin.
// This diagnostic performs no file writes, network requests or database writes.
error_reporting(0);
ob_start();

function anex_catalog_text($value, $limit = 300)
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (!preg_match('//u', $value)) {
        return '';
    }
    if (preg_match('/^(.{0,' . (int) $limit . '})/us', $value, $match)) {
        return $match[1];
    }
    return '';
}

function anex_catalog_number($value, $limit)
{
    if (!is_int($value) && !is_float($value) && !is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && abs($number) <= $limit ? $number : null;
}

function anex_catalog_param(&$params, $value)
{
    $name = ':p' . count($params);
    $params[$name] = $value;
    return $name;
}

function anex_catalog_like($value)
{
    // An explicit escape character avoids dependence on SQL backslash modes.
    return '%' . strtr($value, array('!' => '!!', '%' => '!%', '_' => '!_')) . '%';
}

function anex_catalog_tokens($name)
{
    $generic = array_flip(array(
        'hotel', 'hotels', 'resort', 'resorts', 'spa', 'the', 'and', 'club',
        'apart', 'aparthotel', 'apartments', 'apartment', 'suites', 'suite',
        'отель', 'отели', 'резорт', 'ресорт', 'спа', 'клуб', 'апартаменты',
        'звезды', 'звёзды', 'stars', 'star', 'all', 'inclusive'
    ));
    preg_match_all('/[\p{L}\p{N}]+/u', $name, $matches);
    $tokens = array();
    foreach ($matches[0] as $token) {
        if (!isset($generic[$token]) && preg_match('/^.{3,}$/us', $token)
            && !preg_match('/^\d+$/', $token)) {
            $tokens[$token] = $token;
        }
    }
    // Require the distinctive words together; a lone generic word is no match.
    usort($tokens, function ($a, $b) { return strlen($b) - strlen($a); });
    return array_slice($tokens, 0, 3);
}

$pdo = null;
$transactionStarted = false;
$result = array('status' => 'catalog_unavailable', 'items' => array());
try {
    $raw = file_get_contents('php://stdin', false, null, 0, 65537);
    if (!is_string($raw) || strlen($raw) > 65536) {
        throw new RuntimeException();
    }
    $input = json_decode($raw, true, 16);
    if (!is_array($input) || !isset($input['queries']) || !is_array($input['queries'])
        || count($input['queries']) < 1 || count($input['queries']) > 10) {
        throw new RuntimeException();
    }
    $candidateLimit = isset($input['candidate_limit']) && in_array($input['candidate_limit'], array(64, 256), true) ? $input['candidate_limit'] : 8;
    $queries = array();
    foreach ($input['queries'] as $query) {
        if (!is_array($query) || !isset($query['key']) || !is_int($query['key'])
            || $query['key'] < 1 || !isset($query['names']) || !is_array($query['names'])
            || count($query['names']) > 3) {
            throw new RuntimeException();
        }
        $names = array();
        if (isset($query['country_id']) && (!is_int($query['country_id']) || $query['country_id'] < 1)) {
            throw new RuntimeException();
        }
        foreach ($query['names'] as $name) {
            $name = anex_catalog_text($name);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        $queries[] = array(
            'key' => $query['key'], 'names' => array_values(array_unique($names)),
            'country_id' => isset($query['country_id']) ? $query['country_id'] : null,
            'latitude' => anex_catalog_number(isset($query['latitude']) ? $query['latitude'] : null, 90),
            'longitude' => anex_catalog_number(isset($query['longitude']) ? $query['longitude'] : null, 180)
        );
    }

    $root = realpath(getcwd());
    if ($root === false || basename($root) !== 'anytoour.ru') {
        throw new RuntimeException();
    }
    $helper = false;
    foreach (array('data/db-v1.php', 'v2/data/db-v1.php') as $relative) {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($candidate !== false && is_file($candidate)
            && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0) {
            $helper = $candidate;
            break;
        }
    }
    if ($helper === false) {
        throw new RuntimeException();
    }
    require_once $helper;
    if (!function_exists('v2_data_db') || !function_exists('v2_data_normalize_text')) {
        throw new RuntimeException();
    }
    $pdo = v2_data_db();
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException();
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('START TRANSACTION READ ONLY');
    $transactionStarted = true;
    $tables = $pdo->query("SHOW TABLES LIKE 'catalog_hotel_details'")->fetchAll(PDO::FETCH_COLUMN);
    $hasDetails = in_array('catalog_hotel_details', $tables, true);
    $latitude = $hasDetails ? 'COALESCE(d.latitude, h.latitude)' : 'h.latitude';
    $longitude = $hasDetails ? 'COALESCE(d.longitude, h.longitude)' : 'h.longitude';
    $address = $hasDetails ? 'd.address' : 'NULL';
    $items = array();
    foreach ($queries as $query) {
        $params = array();
        $conditions = array();
        $exactOrder = array();
        $tokenOrder = array();
        $normalizedNames = array();
        foreach ($query['names'] as $name) {
            $normalized = anex_catalog_text(v2_data_normalize_text($name));
            if ($normalized !== '') {
                $normalizedNames[$normalized] = $normalized;
            }
        }
        foreach ($normalizedNames as $name) {
            $conditions[] = '(h.normalized_name = ' . anex_catalog_param($params, $name)
                . ' OR h.search_key = ' . anex_catalog_param($params, $name) . ')';
            $exactOrder[] = 'h.normalized_name = ' . anex_catalog_param($params, $name);
            $tokens = anex_catalog_tokens($name);
            $tokenConditions = array();
            $tokenOrderConditions = array();
            foreach ($tokens as $token) {
                $pattern = anex_catalog_like($token);
                $tokenConditions[] = '(h.normalized_name LIKE ' . anex_catalog_param($params, $pattern)
                    . " ESCAPE '!' OR h.search_key LIKE " . anex_catalog_param($params, $pattern) . " ESCAPE '!')";
                $tokenOrderConditions[] = '(h.normalized_name LIKE ' . anex_catalog_param($params, $pattern)
                    . " ESCAPE '!' OR h.search_key LIKE " . anex_catalog_param($params, $pattern) . " ESCAPE '!')";
            }
            if ($tokenConditions) {
                $conditions[] = '(' . implode(' AND ', $tokenConditions) . ')';
                $tokenOrder[] = '(' . implode(' AND ', $tokenOrderConditions) . ')';
            }
        }
        if ($query['latitude'] !== null && $query['longitude'] !== null) {
            $conditions[] = '(' . $latitude . ' BETWEEN '
                . anex_catalog_param($params, $query['latitude'] - 0.01) . ' AND '
                . anex_catalog_param($params, $query['latitude'] + 0.01) . ' AND '
                . $longitude . ' BETWEEN '
                . anex_catalog_param($params, $query['longitude'] - 0.01) . ' AND '
                . anex_catalog_param($params, $query['longitude'] + 0.01) . ')';
        }
        $candidates = array();
        if ($conditions) {
            $sql = 'SELECT /*+ MAX_EXECUTION_TIME(3000) */ h.id, h.name, h.country_name, '
                . 'h.region_name, h.subregion_name, h.category, ' . $latitude . ' AS latitude, '
                . $longitude . ' AS longitude, ' . $address . ' AS address FROM catalog_hotels h '
                . ($hasDetails ? 'LEFT JOIN catalog_hotel_details d ON d.hotel_id = h.id ' : '')
                . 'WHERE h.is_active = 1 '
                . ($query['country_id'] !== null ? 'AND h.country_id = ' . anex_catalog_param($params, $query['country_id']) . ' ' : '')
                . 'AND (' . implode(' OR ', $conditions) . ') ORDER BY '
                . ($exactOrder ? 'CASE WHEN (' . implode(' OR ', $exactOrder) . ') THEN 0 '
                    . ($tokenOrder ? 'WHEN (' . implode(' OR ', $tokenOrder) . ') THEN 1 ' : '') . 'ELSE 2 END, ' : '')
                . 'h.is_active DESC, h.id ASC LIMIT ' . $candidateLimit;
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $candidate = array('id' => (int) $row['id']);
                foreach (array('name', 'country_name', 'region_name', 'subregion_name', 'category') as $field) {
                    $candidate[$field] = anex_catalog_text(isset($row[$field]) ? (string) $row[$field] : '');
                }
                $candidate['latitude'] = anex_catalog_number($row['latitude'], 90);
                $candidate['longitude'] = anex_catalog_number($row['longitude'], 180);
                $candidate['address'] = anex_catalog_text(isset($row['address']) ? (string) $row['address'] : '', 500);
                $candidates[] = $candidate;
            }
            $statement->closeCursor();
        }
        $items[] = array('key' => $query['key'], 'candidates' => $candidates);
    }
    $pdo->exec('ROLLBACK');
    $transactionStarted = false;
    $result = array('status' => 'ok', 'items' => $items);
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $transactionStarted) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $rollbackIgnored) {
            // Errors and connection details never leave this diagnostic.
        }
    }
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
$encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || strlen($encoded) > 307200) {
    $encoded = '{"status":"catalog_unavailable","items":[]}';
}
echo $encoded, "\n";
