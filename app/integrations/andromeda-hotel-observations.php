<?php
declare(strict_types=1);

/**
 * Append-only evidence for Andromeda hotels that do not yet have an accepted local_id.
 *
 * This store never resolves identities and never changes catalog or mapping tables. The
 * public search may fail open when the table is unavailable; a missing observation must
 * not hide an already mapped offer or make the supplier search fail.
 */
final class AnyTourAndromedaHotelObservations
{
    private const MAX_OFFERS = 2000;

    public static function install(PDO $pdo): void
    {
        if ($pdo->inTransaction()) throw new RuntimeException('ANDROMEDA_OBSERVATION_TRANSACTION');
        $pdo->exec('CREATE TABLE IF NOT EXISTS andromeda_search_hotel_observations ('
            . 'observation_sha256 CHAR(64) NOT NULL PRIMARY KEY,'
            . 'search_evidence_sha256 CHAR(64) NOT NULL,'
            . 'supplier_namespace VARCHAR(160) NOT NULL,'
            . 'external_hotel_id VARCHAR(128) NOT NULL,'
            . 'hotel_name VARCHAR(300) NOT NULL,'
            . 'operator_refs_json TEXT NOT NULL,'
            . 'operator_names_json TEXT NOT NULL,'
            . 'country_id INT UNSIGNED NOT NULL,'
            . 'country_name VARCHAR(160) NOT NULL,'
            . 'region_name VARCHAR(180) NOT NULL,'
            . 'category TINYINT UNSIGNED NULL,'
            . 'description_text MEDIUMTEXT NULL,'
            . 'image_url VARCHAR(2048) NULL,'
            . 'hotel_url VARCHAR(2048) NULL,'
            . 'content_sha256 CHAR(64) NOT NULL,'
            . 'observed_at_utc DATETIME NOT NULL,'
            . 'KEY unresolved_hotel (supplier_namespace,external_hotel_id,observed_at_utc),'
            . 'KEY unresolved_country (country_id,observed_at_utc)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
        $fields = $pdo->query('SHOW COLUMNS FROM andromeda_search_hotel_observations')->fetchAll(PDO::FETCH_COLUMN);
        $expected = ['observation_sha256','search_evidence_sha256','supplier_namespace','external_hotel_id',
            'hotel_name','operator_refs_json','operator_names_json','country_id','country_name','region_name',
            'category','description_text','image_url','hotel_url','content_sha256','observed_at_utc'];
        if ($fields !== $expected) throw new RuntimeException('ANDROMEDA_OBSERVATION_SCHEMA_MISMATCH');
    }

    public static function rows(array $page, array $country): array
    {
        if (($page['provider'] ?? null) !== 'andromeda'
            || !is_string($page['search_ref'] ?? null)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $page['search_ref'])
            || !is_int($page['generation'] ?? null) || $page['generation'] < 1
            || !is_int($page['page'] ?? null) || $page['page'] < 1
            || !is_array($page['offers'] ?? null) || count($page['offers']) > self::MAX_OFFERS) {
            throw new InvalidArgumentException('ANDROMEDA_OBSERVATION_PAGE');
        }
        $countryId = self::positiveInt($country['local_country_id'] ?? null);
        $countryName = self::text($country['local_country_name'] ?? '', 160);
        if ($countryId === null || $countryName === '') throw new InvalidArgumentException('ANDROMEDA_OBSERVATION_COUNTRY');
        $event = hash('sha256', json_encode(['andromeda',$page['search_ref'],$page['generation'],$page['page']], JSON_THROW_ON_ERROR));
        $grouped = [];
        foreach ($page['offers'] as $offer) {
            if (!is_array($offer) || ($offer['provider'] ?? null) !== 'andromeda')
                throw new InvalidArgumentException('ANDROMEDA_OBSERVATION_OFFER');
            // Accepted identities belong in the customer result and never in this queue.
            if (($offer['local_hotel_id'] ?? null) !== null) continue;
            $namespace = self::identifier($offer['supplier_namespace'] ?? null, 160);
            $external = self::identifier($offer['external_hotel_id'] ?? null, 128);
            $key = $namespace.':'.$external;
            $content = is_array($offer['hotel_content'] ?? null) ? $offer['hotel_content'] : [];
            if (!isset($grouped[$key])) {
                $category = self::positiveInt($content['category'] ?? null);
                if ($category !== null && $category > 5) $category = null;
                $grouped[$key] = [
                    'observation_sha256' => hash('sha256', $event."\0".$namespace."\0".$external),
                    'search_evidence_sha256' => $event,
                    'supplier_namespace' => $namespace,
                    'external_hotel_id' => $external,
                    'hotel_name' => self::text($offer['hotel'] ?? '', 300),
                    'operator_refs' => [],
                    'operator_names' => [],
                    'country_id' => $countryId,
                    'country_name' => $countryName,
                    'region_name' => self::text($content['region'] ?? '', 180),
                    'category' => $category,
                    // The price response currently contains no full description. Keep the
                    // nullable field ready for documented content when it is actually present.
                    'description_text' => self::nullableText($content['description'] ?? null, 65535),
                    'image_url' => self::publicUrl($content['image_url'] ?? null),
                    'hotel_url' => self::publicUrl($content['hotel_url'] ?? null),
                ];
            }
            $operatorRef = self::identifier($offer['operator_ref'] ?? null, 128);
            $operatorName = self::text($offer['operator'] ?? '', 300);
            $grouped[$key]['operator_refs'][$operatorRef] = true;
            if ($operatorName !== '') $grouped[$key]['operator_names'][$operatorName] = true;
        }
        $rows = [];
        foreach ($grouped as $row) {
            $row['operator_refs'] = array_map('strval', array_keys($row['operator_refs']));
            $row['operator_names'] = array_keys($row['operator_names']);
            sort($row['operator_refs'], SORT_STRING);
            sort($row['operator_names'], SORT_STRING);
            $content = [$row['hotel_name'],$row['country_id'],$row['country_name'],$row['region_name'],$row['category'],
                $row['description_text'],$row['image_url'],$row['hotel_url'],$row['operator_refs'],$row['operator_names']];
            $row['content_sha256'] = hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            $rows[] = $row;
        }
        usort($rows, static fn(array $a, array $b): int => [$a['supplier_namespace'],$a['external_hotel_id']] <=> [$b['supplier_namespace'],$b['external_hotel_id']]);
        return $rows;
    }

    public static function record(PDO $pdo, array $page, array $country): array
    {
        $rows = self::rows($page, $country);
        if (!$rows) return ['status'=>'empty','unique_hotels'=>0,'inserted'=>0];
        if ($pdo->inTransaction()) throw new RuntimeException('ANDROMEDA_OBSERVATION_TRANSACTION');
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $prefix = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $sql = $prefix.' INTO andromeda_search_hotel_observations ('
            . 'observation_sha256,search_evidence_sha256,supplier_namespace,external_hotel_id,hotel_name,'
            . 'operator_refs_json,operator_names_json,country_id,country_name,region_name,category,description_text,'
            . 'image_url,hotel_url,content_sha256,observed_at_utc) VALUES '
            . implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
        $values = [];
        $now = $country['observed_at_utc'] ?? gmdate('Y-m-d H:i:s');
        if (!is_string($now) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $now))
            throw new InvalidArgumentException('ANDROMEDA_OBSERVATION_TIME');
        foreach ($rows as $row) {
            array_push($values, $row['observation_sha256'], $row['search_evidence_sha256'], $row['supplier_namespace'],
                $row['external_hotel_id'], $row['hotel_name'], json_encode($row['operator_refs'], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                json_encode($row['operator_names'], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR), $row['country_id'],
                $row['country_name'], $row['region_name'], $row['category'], $row['description_text'], $row['image_url'],
                $row['hotel_url'], $row['content_sha256'], $now);
        }
        $statement = $pdo->prepare($sql);
        if (!$statement->execute($values)) throw new RuntimeException('ANDROMEDA_OBSERVATION_WRITE_FAILED');
        return ['status'=>'stored','unique_hotels'=>count($rows),'inserted'=>$statement->rowCount()];
    }

    private static function identifier($value, int $limit): string
    {
        if (is_int($value)) $value = (string)$value;
        if (!is_string($value) || strlen($value) > $limit || !preg_match('/^[A-Za-z0-9_-]+$/D', $value))
            throw new InvalidArgumentException('ANDROMEDA_OBSERVATION_IDENTIFIER');
        return $value;
    }

    private static function positiveInt($value): ?int
    {
        if (is_int($value)) return $value > 0 && $value <= 2147483647 ? $value : null;
        return is_string($value) && preg_match('/^[1-9][0-9]{0,9}$/D', $value) && (float)$value <= 2147483647 ? (int)$value : null;
    }

    private static function text($value, int $limit): string
    {
        if (!is_string($value) || !preg_match('//u', $value) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value)) return '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : (strlen($value) <= $limit ? $value : '');
    }

    private static function nullableText($value, int $limit): ?string
    {
        $text = self::text($value, $limit);
        return $text === '' ? null : $text;
    }

    private static function publicUrl($value): ?string
    {
        if (!is_string($value) || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value)) return null;
        $url = parse_url($value);
        $host = strtolower($url['host'] ?? '');
        if (!$url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['port'])
            || !preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}$/D', $host)
            || preg_match('/\.(?:local|localhost|internal|lan)$/D', $host)
            || preg_match('/(?:^|&)(?:sid|token|password|auth|apikey|secret|session)=/i', $url['query'] ?? '')) return null;
        return $value;
    }
}
