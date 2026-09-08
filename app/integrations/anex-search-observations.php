<?php
declare(strict_types=1);

/** Aggregate hotel sightings from successful preview searches, without extra API calls. */
final class AnyTourAnexSearchObservations
{
    public static function install(PDO $pdo): void
    {
        if ($pdo->inTransaction()) throw new RuntimeException('ANEX_OBSERVATION_TRANSACTION');
        $pdo->exec('CREATE TABLE IF NOT EXISTS anex_search_hotel_observations ('
            . 'anex_hotel_id INT UNSIGNED NOT NULL PRIMARY KEY, hotel_name VARCHAR(300) NOT NULL,'
            . 'country_id INT UNSIGNED NOT NULL, anex_country_id INT UNSIGNED NOT NULL,'
            . 'last_catalog_hotel_id BIGINT UNSIGNED NULL, first_seen_utc DATETIME NOT NULL,'
            . 'last_seen_utc DATETIME NOT NULL, search_count BIGINT UNSIGNED NOT NULL DEFAULT 1,'
            . 'last_checkin_from DATE NOT NULL, last_checkin_to DATE NOT NULL,'
            . 'last_source_sha CHAR(40) NULL, INDEX observed_priority(search_count,last_seen_utc)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $fields = $pdo->query('SHOW COLUMNS FROM anex_search_hotel_observations')->fetchAll(PDO::FETCH_COLUMN);
        if ($fields !== ['anex_hotel_id','hotel_name','country_id','anex_country_id','last_catalog_hotel_id',
            'first_seen_utc','last_seen_utc','search_count','last_checkin_from','last_checkin_to','last_source_sha']) {
            throw new RuntimeException('ANEX_OBSERVATION_SCHEMA_MISMATCH');
        }
    }

    public static function rows(array $offers, array $context): array
    {
        foreach (['country_id','anex_country_id'] as $key) {
            if (!is_int($context[$key] ?? null) || $context[$key] < 1) throw new InvalidArgumentException('ANEX_OBSERVATION_CONTEXT');
        }
        foreach (['checkin_from','checkin_to'] as $key) {
            $date = $context[$key] ?? null;
            if (!is_string($date) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $date)
                || (new DateTimeImmutable($date))->format('Y-m-d') !== $date) throw new InvalidArgumentException('ANEX_OBSERVATION_CONTEXT');
        }
        $rows = [];
        foreach (array_slice($offers, 0, 300) as $offer) {
            $hotel = $offer['hotel'] ?? [];
            $id = $hotel['external_id'] ?? null;
            if ((!is_int($id) && !is_string($id)) || !preg_match('/\A[1-9][0-9]{0,7}\z/D', (string)$id)) continue;
            if (isset($rows[(int)$id])) continue;
            $name = $hotel['name'] ?? '';
            if (!is_string($name) || strlen($name) > 1200 || preg_match('/[\x00-\x1f<>]|https?:\/\/|oauth_token/i', $name)
                || (defined('ANEX_API_TOKEN') && ANEX_API_TOKEN !== '' && strpos($name, ANEX_API_TOKEN) !== false)) $name = '';
            if (function_exists('mb_substr')) $name = mb_substr($name, 0, 300, 'UTF-8');
            elseif (strlen($name) > 300) $name = '';
            $local = ($hotel['mapping_status'] ?? '') === 'resolved' && is_int($hotel['local_id'] ?? null)
                && $hotel['local_id'] > 0 ? $hotel['local_id'] : null;
            $rows[(int)$id] = ['anex_hotel_id' => (int)$id, 'hotel_name' => $name,
                'country_id' => $context['country_id'], 'anex_country_id' => $context['anex_country_id'],
                'last_catalog_hotel_id' => $local, 'last_checkin_from' => $context['checkin_from'],
                'last_checkin_to' => $context['checkin_to']];
        }
        return array_values($rows);
    }

    public static function record(PDO $pdo, array $offers, array $context): array
    {
        $rows = self::rows($offers, $context);
        if (!$rows) return ['status' => 'empty', 'unique_hotels' => 0];
        if ($pdo->inTransaction()) throw new RuntimeException('ANEX_OBSERVATION_TRANSACTION');
        usort($rows, static function ($a, $b) { return $a['anex_hotel_id'] <=> $b['anex_hotel_id']; });
        $sha = defined('ANEX_PREVIEW_SOURCE_SHA') && preg_match('/\A[0-9a-f]{40}\z/D', ANEX_PREVIEW_SOURCE_SHA)
            ? ANEX_PREVIEW_SOURCE_SHA : null;
        $values = [];
        foreach ($rows as $row) foreach (array_merge(array_values($row), [$sha]) as $value) $values[] = $value;
        // One atomic upsert per response; duplicate tour offers never inflate search_count.
        $sql = 'INSERT INTO anex_search_hotel_observations (anex_hotel_id,hotel_name,country_id,anex_country_id,'
            . 'last_catalog_hotel_id,last_checkin_from,last_checkin_to,last_source_sha,first_seen_utc,last_seen_utc,search_count) VALUES '
            . implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)'))
            . ' ON DUPLICATE KEY UPDATE hotel_name=IF(VALUES(hotel_name)=\'\',hotel_name,VALUES(hotel_name)),'
            . 'country_id=VALUES(country_id),anex_country_id=VALUES(anex_country_id),'
            . 'last_catalog_hotel_id=VALUES(last_catalog_hotel_id),last_checkin_from=VALUES(last_checkin_from),'
            . 'last_checkin_to=VALUES(last_checkin_to),last_source_sha=VALUES(last_source_sha),'
            . 'last_seen_utc=UTC_TIMESTAMP(),search_count=search_count+1';
        if (!$pdo->prepare($sql)->execute($values)) throw new RuntimeException('ANEX_OBSERVATION_WRITE_FAILED');
        return ['status' => 'stored', 'unique_hotels' => count($rows)];
    }

    public static function classify(array $rows, callable $resolver, array $manualIds, int $limit = 1000): array
    {
        $counts = ['observed' => 0, 'mapped' => 0, 'pending' => 0, 'manual_review' => 0];
        $pending = $manual = [];
        foreach ($rows as $row) {
            $id = (int)$row['anex_hotel_id'];
            $counts['observed']++;
            // Resolve now, not from last_catalog_hotel_id: new links and manual blocks take effect immediately.
            $local = $resolver('anex_online', $id);
            if ($local !== null) { $counts['mapped']++; continue; }
            $row['catalog_hotel_id'] = null;
            $row['status'] = isset($manualIds[$id]) ? 'manual_review' : 'pending';
            $counts[$row['status']]++;
            if ($row['status'] === 'manual_review') { if (count($manual) < $limit) $manual[] = $row; }
            elseif (count($pending) < $limit) $pending[] = $row;
        }
        return ['scope' => 'preview', 'source' => 'successful_initial_search', 'first_page_only' => true,
            'counts' => $counts, 'queue_limit' => $limit, 'pending' => $pending, 'manual_review' => $manual,
            'truncated' => $counts['pending'] > count($pending) || $counts['manual_review'] > count($manual)];
    }

    public static function snapshot(PDO $pdo): array
    {
        $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        $manual = [];
        foreach ($pdo->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN) as $id) $manual[(int)$id] = true;
        $rows = $pdo->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id LIMIT 50001')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 50000) throw new RuntimeException('ANEX_OBSERVATION_EXPORT_LIMIT');
        return self::classify($rows, $registry->previewResolver(), $manual)
            + ['generated_at_utc' => gmdate('c'), 'source_sha' => defined('ANEX_PREVIEW_SOURCE_SHA') ? ANEX_PREVIEW_SOURCE_SHA : null];
    }
}
