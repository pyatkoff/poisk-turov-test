<?php
// Read all active AnyTour catalogue hotels once for an in-memory comparison.
// No schema or row is changed and no configuration detail leaves the process.
error_reporting(0);
ob_start();
$pdo = null;
$transaction = false;
$result = array('status' => 'catalog_unavailable', 'hotels' => array());
try {
    $input = json_decode((string)file_get_contents('php://stdin', false, null, 0, 1025), true);
    if (!is_array($input) || $input !== array('limit' => 100000)) throw new RuntimeException();
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
    $sql = 'SELECT /*+ MAX_EXECUTION_TIME(60000) */ id,name,normalized_name,'
        . 'country_name,region_name,subregion_name '
        . 'FROM catalog_hotels WHERE is_active=1 ORDER BY id LIMIT 100001';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 100000) throw new RuntimeException();
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
    $result = array('status' => 'ok', 'hotels' => $hotels);
} catch (Throwable $ignored) {
    if ($pdo instanceof PDO && $transaction) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $rollbackIgnored) {}
    }
}
while (ob_get_level() > 0) ob_end_clean();
$encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || strlen($encoded) > 67108864) $encoded = '{"status":"catalog_unavailable","hotels":[]}';
echo $encoded, "\n";
