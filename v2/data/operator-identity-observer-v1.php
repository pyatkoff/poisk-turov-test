<?php
/** Best-effort persistence of operator/hotel identity evidence from trusted Tourvisor result rows. */
declare(strict_types=1);

function v2_operator_identity_id($value): ?int
{
    if (is_array($value)) $value = $value['id'] ?? null;
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && (int)$id > 0 ? (int)$id : null;
}

function v2_operator_identity_text($value, int $max = 255): string
{
    if (is_array($value)) $value = $value['name'] ?? $value['russianName'] ?? '';
    return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
}

function v2_operator_identity_safe_link($value): ?array
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 2048 || !str_starts_with(strtolower($url), 'https://')) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return null;
    $host = strtolower((string)$parts['host']);
    if (!preg_match('/^[a-z0-9.-]+$/', $host)) return null;
    $query = (string)($parts['query'] ?? '');
    if ($query !== '') {
        parse_str($query, $params);
        foreach (array_keys($params) as $key) {
            $k = strtolower((string)$key);
            if (preg_match('/(?:^|_)(?:token|jwt|secret|password|passwd|auth|authorization|access[_-]?token|api[_-]?key|signature|sig)(?:$|_)/', $k)) return null;
        }
    }
    $path = (string)($parts['path'] ?? '/');
    $safe = 'https://' . $host . ($path !== '' ? $path : '/');
    if (isset($parts['port'])) $safe = 'https://' . $host . ':' . (int)$parts['port'] . ($path !== '' ? $path : '/');
    if ($query !== '') $safe .= '?' . $query;
    return ['url'=>$safe,'host'=>$host,'path'=>$path !== '' ? $path : '/','query'=>$query !== '' ? $query : null];
}

function v2_operator_identity_native_id(?array $link): ?array
{
    if ($link === null || empty($link['query'])) return null;
    parse_str((string)$link['query'], $params);
    $best = null;
    $priority = ['hotelcode'=>10, 'hotellist'=>20, 'hotels'=>30, 'f4'=>40];
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            if (count($value) !== 1) continue;
            $value = reset($value);
        }
        $normalizedKey = strtolower((string)$key);
        if (!isset($priority[$normalizedKey])) continue;
        $candidate = trim((string)$value);
        if ($candidate === '' || !preg_match('/^[0-9]+$/D', $candidate) || (int)$candidate <= 0) continue;
        $type = match ($normalizedKey) {
            'hotelcode' => 'hotelCode',
            'hotellist' => 'HOTELLIST',
            'hotels' => 'hotels',
            'f4' => 'f4',
        };
        if ($best === null || $priority[$normalizedKey] < $best['priority']) {
            $best = ['type'=>$type, 'value'=>$candidate, 'priority'=>$priority[$normalizedKey]];
        }
    }
    if ($best === null) return null;
    unset($best['priority']);
    return $best;
}

function v2_operator_identity_fingerprint(int $hotelId, int $operatorId): string
{
    return hash('sha256', 'tourvisor|' . $hotelId . '|' . $operatorId);
}

function v2_operator_identity_extract(array $hotels, array $context): array
{
    $source = v2_operator_identity_text($context['source'] ?? 'user_search', 40) ?: 'user_search';
    $searchId = v2_operator_identity_id($context['searchId'] ?? null);
    $contextCountryId = v2_operator_identity_id($context['countryId'] ?? null);
    $rows = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) continue;
        $hotelId = v2_operator_identity_id($hotel['id'] ?? null);
        if ($hotelId === null) continue;
        $country = is_array($hotel['country'] ?? null) ? $hotel['country'] : [];
        $region = is_array($hotel['region'] ?? null) ? $hotel['region'] : [];
        $sub = is_array($hotel['subRegion'] ?? null) ? $hotel['subRegion'] : (is_array($hotel['subregion'] ?? null) ? $hotel['subregion'] : []);
        $common = is_array($hotel['common'] ?? null) ? $hotel['common'] : [];
        $countryId = v2_operator_identity_id($country) ?? $contextCountryId;
        if ($countryId === null || ($contextCountryId !== null && $countryId !== $contextCountryId)) continue;
        $hotelName = v2_operator_identity_text($hotel['name'] ?? '', 255);
        $regionId = v2_operator_identity_id($region);
        $subregionId = v2_operator_identity_id($sub);
        $regionName = v2_operator_identity_text($region, 180);
        $subregionName = v2_operator_identity_text($sub, 180);
        $lat = isset($common['latitude']) && is_numeric($common['latitude']) ? (float)$common['latitude'] : null;
        $lon = isset($common['longitude']) && is_numeric($common['longitude']) ? (float)$common['longitude'] : null;
        $byOperator = [];
        foreach (($hotel['tours'] ?? []) as $tour) {
            if (!is_array($tour)) continue;
            $operator = is_array($tour['operator'] ?? null) ? $tour['operator'] : [];
            $operatorId = v2_operator_identity_id($operator);
            if ($operatorId === null) continue;
            $operatorName = v2_operator_identity_text($operator, 180) ?: null;
            $tourId = v2_operator_identity_text($tour['id'] ?? $tour['tourId'] ?? '', 220);
            $link = v2_operator_identity_safe_link($tour['operatorLink'] ?? ($hotel['operatorLink'] ?? null));
            $native = v2_operator_identity_native_id($link);
            $key = (string)$operatorId;
            if (!isset($byOperator[$key])) {
                $byOperator[$key] = [
                    'source'=>$source,'search_id'=>$searchId,'country_id'=>$countryId,'region_id'=>$regionId,'subregion_id'=>$subregionId,
                    'hotel_id'=>$hotelId,'hotel_name'=>$hotelName !== '' ? $hotelName : null,
                    'region_name'=>$regionName !== '' ? $regionName : null,'subregion_name'=>$subregionName !== '' ? $subregionName : null,
                    'latitude'=>$lat,'longitude'=>$lon,'operator_id'=>$operatorId,'operator_name'=>$operatorName,
                    'tour_id'=>$tourId !== '' ? $tourId : null,
                    'operator_link'=>$link['url'] ?? null,'operator_link_host'=>$link['host'] ?? null,
                    'operator_link_path'=>$link['path'] ?? null,'operator_link_query'=>$link['query'] ?? null,
                    'native_id_type'=>$native['type'] ?? null,'native_id_value'=>$native['value'] ?? null,'native_id_conflict'=>0,
                ];
                continue;
            }
            $row =& $byOperator[$key];
            if ($row['operator_name'] === null && $operatorName !== null) $row['operator_name'] = $operatorName;
            if ($row['tour_id'] === null && $tourId !== '') $row['tour_id'] = $tourId;
            if ($native !== null) {
                if ($row['native_id_value'] === null) {
                    $row['native_id_type'] = $native['type'];
                    $row['native_id_value'] = $native['value'];
                } elseif ($row['native_id_type'] !== $native['type'] || $row['native_id_value'] !== $native['value']) {
                    $row['native_id_conflict'] = 1;
                }
            }
            $existingHasNativeLink = $row['operator_link'] !== null && $row['native_id_value'] !== null;
            if ($link !== null && ($row['operator_link'] === null || ($native !== null && !$existingHasNativeLink))) {
                $row['operator_link'] = $link['url'];
                $row['operator_link_host'] = $link['host'];
                $row['operator_link_path'] = $link['path'];
                $row['operator_link_query'] = $link['query'];
            }
            unset($row);
        }
        foreach ($byOperator as $row) $rows[] = $row;
    }
    return $rows;
}

function v2_data_observe_operator_identities(array $hotels, array $context): array
{
    $rows = v2_operator_identity_extract($hotels, $context);
    if ($rows === []) return ['seen'=>0,'written'=>0,'reason'=>'no_operator_identity'];
    try {
        require_once __DIR__ . '/db-v1.php';
        $pdo = v2_data_db();
        $stmt = $pdo->prepare("INSERT INTO tour_operator_identity_observations (
            fingerprint,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,
            hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,
            operator_link,operator_link_host,operator_link_path,operator_link_query,native_id_type,native_id_value,native_id_conflict
        ) VALUES (
            :fingerprint,:seen,:seen,1,:source,:search_id,:country_id,:region_id,:subregion_id,
            :hotel_id,:hotel_name,:region_name,:subregion_name,:latitude,:longitude,:operator_id,:operator_name,:tour_id,
            :operator_link,:operator_link_host,:operator_link_path,:operator_link_query,:native_id_type,:native_id_value,:native_id_conflict
        ) ON DUPLICATE KEY UPDATE
            last_seen_at=VALUES(last_seen_at),observation_count=observation_count+1,source=VALUES(source),
            search_id=COALESCE(VALUES(search_id),search_id),country_id=VALUES(country_id),
            region_id=COALESCE(VALUES(region_id),region_id),subregion_id=COALESCE(VALUES(subregion_id),subregion_id),
            hotel_name=COALESCE(VALUES(hotel_name),hotel_name),region_name=COALESCE(VALUES(region_name),region_name),
            subregion_name=COALESCE(VALUES(subregion_name),subregion_name),latitude=COALESCE(VALUES(latitude),latitude),
            longitude=COALESCE(VALUES(longitude),longitude),operator_name=COALESCE(VALUES(operator_name),operator_name),
            tour_id=COALESCE(VALUES(tour_id),tour_id),
            operator_link=COALESCE(VALUES(operator_link),operator_link),operator_link_host=COALESCE(VALUES(operator_link_host),operator_link_host),
            operator_link_path=COALESCE(VALUES(operator_link_path),operator_link_path),operator_link_query=COALESCE(VALUES(operator_link_query),operator_link_query),
            native_id_conflict=CASE
                WHEN VALUES(native_id_value) IS NULL OR native_id_value IS NULL THEN native_id_conflict
                WHEN native_id_type=VALUES(native_id_type) AND native_id_value=VALUES(native_id_value) THEN native_id_conflict
                ELSE 1 END,
            native_id_type=COALESCE(native_id_type,VALUES(native_id_type)),native_id_value=COALESCE(native_id_value,VALUES(native_id_value))");
        $seenAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $written = 0;
        foreach ($rows as $row) {
            $fingerprint = v2_operator_identity_fingerprint((int)$row['hotel_id'], (int)$row['operator_id']);
            $stmt->execute($row + ['fingerprint'=>$fingerprint,'seen'=>$seenAt]);
            $written++;
        }
        return ['seen'=>count($rows),'written'=>$written];
    } catch (Throwable $e) {
        return ['seen'=>count($rows),'written'=>0,'reason'=>'identity_persistence_unavailable'];
    }
}
