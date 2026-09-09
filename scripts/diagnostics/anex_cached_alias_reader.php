<?php
// Read aliases only for pinned, already-complete catalog candidate IDs. No writes or network calls.
error_reporting(0);
ob_start();

function anex_cached_alias_text($value, $limit)
{
    if (!is_string($value)) return '';
    $value = trim($value);
    if (!preg_match('//u', $value)) return '';
    return preg_match('/^(.{0,' . (int) $limit . '})/us', $value, $m) ? $m[1] : '';
}

$pdo = null;
$started = false;
$result = array('status' => 'catalog_unavailable', 'items' => array());
try {
    $raw = file_get_contents('php://stdin', false, null, 0, 65537);
    if (!is_string($raw) || strlen($raw) > 65536) throw new RuntimeException();
    $input = json_decode($raw, true, 16);
    if (!is_array($input) || ($input['mode'] ?? '') !== 'cached_alias_review'
        || !isset($input['queries']) || !is_array($input['queries'])
        || count($input['queries']) !== 1) throw new RuntimeException();
    $queries = array();
    foreach ($input['queries'] as $query) {
        if (!is_array($query) || !isset($query['key']) || !is_int($query['key']) || $query['key'] < 1
            || !isset($query['candidate_ids']) || !is_array($query['candidate_ids'])
            || count($query['candidate_ids']) < 1 || count($query['candidate_ids']) > 4097) {
            throw new RuntimeException();
        }
        $ids = array();
        foreach ($query['candidate_ids'] as $id) {
            if (!is_int($id) || $id < 1 || $id > 2147483647 || isset($ids[$id])) {
                throw new RuntimeException();
            }
            $ids[$id] = $id;
        }
        $queries[] = array('key' => $query['key'], 'candidate_ids' => array_values($ids));
    }
    $root = realpath(getcwd());
    if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException();
    $helper = false;
    foreach (array('data/db-v1.php', 'v2/data/db-v1.php') as $relative) {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($candidate !== false && is_file($candidate)
            && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0) {
            $helper = $candidate;
            break;
        }
    }
    if ($helper === false) throw new RuntimeException();
    require_once $helper;
    if (!function_exists('v2_data_db')) throw new RuntimeException();
    $pdo = v2_data_db();
    if (!($pdo instanceof PDO)) throw new RuntimeException();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('START TRANSACTION READ ONLY');
    if (!(bool) $pdo->query("SHOW TABLES LIKE 'hotel_aliases'")->fetchColumn()) {
        throw new RuntimeException();
    }
    $items = array();
    foreach ($queries as $query) {
        $holders = implode(',', array_fill(0, count($query['candidate_ids']), '?'));
        $sql = 'SELECT /*+ MAX_EXECUTION_TIME(3000) */ hotel_id,alias,normalized_alias,source '
            . 'FROM hotel_aliases WHERE hotel_id IN (' . $holders . ') '
            . 'ORDER BY hotel_id,normalized_alias,alias,source LIMIT 8193';
        $statement = $pdo->prepare($sql);
        $statement->execute($query['candidate_ids']);
        $rows = array();
        $exhausted = true;
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (count($rows) >= 8192) {
                $exhausted = false;
                break;
            }
            $id = (int) $row['hotel_id'];
            if (!in_array($id, $query['candidate_ids'], true)) throw new RuntimeException();
            $alias = anex_cached_alias_text((string) $row['alias'], 500);
            $normalized = anex_cached_alias_text((string) $row['normalized_alias'], 500);
            $source = anex_cached_alias_text((string) $row['source'], 100);
            if ($alias === '' || $normalized === '' || $source === '') throw new RuntimeException();
            $rows[] = array('hotel_id' => $id, 'alias' => $alias,
                'normalized_alias' => $normalized, 'source' => $source);
        }
        $statement->closeCursor();
        $items[] = array('key' => $query['key'], 'aliases' => $rows,
            'alias_set_complete' => $exhausted, 'alias_fetch_limit' => 8193,
            'alias_rows' => count($rows), 'candidate_count' => count($query['candidate_ids']),
            'query_scope' => 'pinned_complete_candidate_ids_aliases');
    }
    $pdo->exec('ROLLBACK');
    $started = false;
    $result = array('status' => 'ok', 'items' => $items);
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $started) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored2) {}
    }
}
while (ob_get_level() > 0) ob_end_clean();
$encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || strlen($encoded) > 4000000) {
    $encoded = '{"status":"catalog_unavailable","items":[]}';
}
echo $encoded, "\n";
