<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
date_default_timezone_set('UTC');
$root = getenv('HOME') . '/www/anytoour.ru/data';
try {
    require $root . '/db-v1.php';
    $db = v2_data_db();
    $version = (string)$db->query('SELECT VERSION()')->fetchColumn();
    $db->exec(stripos($version, 'mariadb') === false
        ? 'SET SESSION MAX_EXECUTION_TIME=5000' : 'SET SESSION max_statement_time=5');
    $db->exec('START TRANSACTION READ ONLY');
    $latest = $db->query('SELECT MAX(observed_at) FROM tour_price_observations')->fetchColumn();
    if (!is_string($latest) || !strtotime($latest)) throw new RuntimeException('OBSERVATION_TIMESTAMP');
    $history = $db->prepare('SELECT MAX(price_date) AS latest, COUNT(*) AS points
        FROM tour_price_daily_exact WHERE segment_fingerprint=? AND price_date >= UTC_DATE() - INTERVAL 30 DAY');
    $snapshots = $db->query("SELECT observed_at,expires_at,offers_json
        FROM seo_offer_snapshots WHERE page_type='country' AND offer_count>0
        AND observed_at>=NOW()-INTERVAL 72 HOUR AND expires_at>NOW()
        ORDER BY observed_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($snapshots as $snapshot) {
        $offers = json_decode($snapshot['offers_json'], true, 32, JSON_THROW_ON_ERROR);
        foreach ($offers as $offer) {
            $segment = $offer['segmentFingerprint'] ?? '';
            $price = (float)($offer['price'] ?? 0);
            if (!preg_match('/\A[a-f0-9]{64}\z/D', $segment) || $price < 1) continue;
            $history->execute([$segment]);
            $rows = $history->fetch();
            if ((int)$rows['points'] < 1) continue;
            $db->exec('ROLLBACK');
            echo json_encode([
                'query'=>['segment'=>$segment, 'currentPrice'=>$price, 'days'=>31],
                'history_latest'=>$rows['latest'],
                'history_points'=>(int)$rows['points'],
                'observation_latest'=>$latest,
                'seo_observed'=>$snapshot['observed_at'],
                'seo_expires'=>$snapshot['expires_at'],
            ], JSON_THROW_ON_ERROR) . PHP_EOL;
            exit;
        }
    }
    throw new RuntimeException('NO_FRESH_MATCHED_SEGMENT');
} catch (Throwable $error) {
    $reason = $error instanceof PDOException ? 'DATABASE' : $error->getMessage();
    fwrite(STDERR, 'PRICE_FRESHNESS_DB_FAILED ' . preg_replace('/[^A-Z0-9_]/', '', $reason) . PHP_EOL);
    exit(4);
}
