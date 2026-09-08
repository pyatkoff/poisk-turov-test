<?php
// Read one bounded page of active AnyTour catalogue hotels for comparison.
// No schema or row is changed and no configuration detail leaves the process.
error_reporting(0);
ob_start();
$pdo = null;
$transaction = false;
$result = array('status' => 'catalog_unavailable', 'hotels' => array());
try {
    $input = json_decode((string)file_get_contents('php://stdin', false, null, 0, 1025), true);
    if (!is_array($input) || !array_key_exists('after_id', $input)
        || !array_key_exists('limit', $input) || count($input) !== 2
        || !is_int($input['after_id']) || $input['after_id'] < 0
        || $input['after_id'] > 2147483647 || $input['limit'] !== 5000) {
        throw new RuntimeException();
    }
    $root = realpath(getcwd());
    if ($root === false || basename($root) !== 'anytoour.ru') throw new RuntimeException();
    $helper = false;
    foreach (array('data/db-v1.php', 'v2/data/db-v1.php') as $relative) {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if ($candidate !== false && is_file($candidate) && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0) {
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
    $transaction = true;
    $sql = 'SELECT /*+ MAX_EXECUTION_TIME(30000) */ id,name,normalized_name,'
        . 'country_name,region_name,subregion_name '
        . 'FROM catalog_hotels WHERE is_active=1 AND id > :after_id ORDER BY id LIMIT 5001';
    $statement = $pdo->prepare($sql);
    $statement->bindValue(':after_id', $input['after_id'], PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > 5000;
    if ($hasMore) array_pop($rows);
    $hotels = array();
    foreach ($rows as $row) {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        if ($id < 1) continue;
        $item = array('id' => $id);
        foreach (array('name','normalized_name','country_name','region_name','subregion_name') as $key) {
            $value = isset($row[$key]) ? trim((string)$row[$key]) : '';
            $item[$key] = preg_match('//u', $value) && strlen($value) <= 1024 ? $value : '';
        }
        $hotels[] = $item;
    }
    $pdo->exec('ROLLBACK');
    $transaction = false;
    $nextAfterId = count($hotels) ? $hotels[count($hotels) - 1]['id'] : $input['after_id'];
    $result = array('status' => 'ok', 'hotels' => $hotels,
        'has_more' => $hasMore, 'next_after_id' => $nextAfterId);
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $transaction) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $rollbackIgnored) {}
    }
}
while (ob_get_level() > 0) ob_end_clean();
$encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || strlen($encoded) > 67108864) $encoded = '{"status":"catalog_unavailable","hotels":[]}';
echo $encoded, "\n";
