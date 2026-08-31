<?php
/** Build Yandex Direct hotel feed from fresh hot_tours_current rows. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/direct-feed-v1.php';

function direct_feed_arg(array $argv, string $name, string $fallback): string {
    foreach ($argv as $arg) if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name)+3);
    return $fallback;
}

$target = direct_feed_arg($argv, 'output', dirname(__DIR__,2).'/feed/direct_hotels.yml');
$limit = max(1, min(5000, (int)direct_feed_arg($argv, 'limit', '1500')));
$pdo = v2_data_db();
$sql = "SELECT hotel_id,hotel_name,hotel_category,country_id,country_name,region_id,region_name,departure_id,departure_date,nights,price,currency,picture_url,fetched_at,expires_at
          FROM hot_tours_current
         WHERE expires_at > NOW() AND price > 0 AND currency IN ('RUB','RUR')
         ORDER BY hotel_id ASC, price ASC, fetched_at DESC
         LIMIT " . ($limit * 8);
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$xml = v2_direct_feed_render(array_slice(v2_direct_feed_best_by_hotel($rows), 0, $limit));

$dir = dirname($target);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Cannot create feed directory');
$tmp = $target . '.tmp.' . getmypid();
if (file_put_contents($tmp, $xml, LOCK_EX) === false) throw new RuntimeException('Cannot write temporary feed');
if (!rename($tmp, $target)) { @unlink($tmp); throw new RuntimeException('Cannot publish feed atomically'); }

fwrite(STDOUT, 'DIRECT_FEED_BUILD_OK rows='.count($rows).' offers='.substr_count($xml, '<offer ').' output='.$target."\n");
