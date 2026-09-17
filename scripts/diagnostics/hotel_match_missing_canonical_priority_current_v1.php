<?php
declare(strict_types=1);

/**
 * MATCH missing AnyTour canonical backlog v1.
 *
 * READ ONLY. Recomputes accepted provider -> Tourvisor hotel targets that do not
 * yet have LOCAL's explicit legacy_catalog -> AnyTour canonical bridge, and ranks
 * only seedable/sellable targets by real AnyTour search demand.
 *
 * This file does not create AnyTour hotels and does not accept provider mappings.
 */

const MCP_SCHEMA = 'match-missing-canonical-priority-v1';
const MCP_MAX_ROWS = 20000;

function mcp_require(bool $ok, string $code): void {
    if (!$ok) throw new RuntimeException($code);
}

function mcp_positive_int(mixed $value, string $field): int {
    if (is_int($value) && $value > 0) return $value;
    $text = trim((string)$value);
    mcp_require($text !== '' && ctype_digit($text) && (int)$text > 0, 'invalid_' . $field);
    return (int)$text;
}

function mcp_nonnegative_int(mixed $value, string $field): int {
    if ($value === null || $value === '') return 0;
    $text = trim((string)$value);
    mcp_require($text !== '' && ctype_digit($text), 'invalid_' . $field);
    return (int)$text;
}

function mcp_text(mixed $value, int $max = 255): ?string {
    if ($value === null) return null;
    $text = trim((string)$value);
    if ($text === '') return null;
    $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    return $text === '' ? null : mb_substr($text, 0, $max, 'UTF-8');
}

function mcp_country_key(?string $country): string {
    if ($country === null) return '';
    $text = mb_strtolower($country, 'UTF-8');
    $text = str_replace(['ё'], ['е'], $text);
    $text = preg_replace('/[^\p{L}]+/u', ' ', $text) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function mcp_is_non_sellable_country(?string $country): bool {
    $key = mcp_country_key($country);
    return in_array($key, ['россия','russia','russian federation','абхазия','abkhazia'], true);
}

function mcp_tier(array $row): string {
    if (($row['status'] ?? '') !== 'ready_for_local_seed') return 'hold';
    if (($row['observations_24h'] ?? 0) > 0) return 'live_24h';
    if (($row['observations_7d'] ?? 0) > 0) return 'live_7d';
    if (($row['observations_total'] ?? 0) > 0) return 'observed_historical';
    return 'provider_only';
}

function mcp_tier_rank(string $tier): int {
    return match ($tier) {
        'live_24h' => 0,
        'live_7d' => 1,
        'observed_historical' => 2,
        'provider_only' => 3,
        default => 9,
    };
}

/** @return array<string,mixed> */
function mcp_normalize_row(array $row): array {
    $tvId = mcp_positive_int($row['tv_hotel_id'] ?? null, 'tv_hotel_id');
    $acceptedSources = mcp_positive_int($row['accepted_source_count'] ?? null, 'accepted_source_count');
    $acceptedProviders = mcp_positive_int($row['accepted_provider_count'] ?? null, 'accepted_provider_count');
    $catalogPresent = (int)($row['catalog_present'] ?? 0) === 1;
    $catalogActive = $catalogPresent && (int)($row['catalog_active'] ?? 0) === 1;
    $countryName = mcp_text($row['country_name'] ?? null, 160);

    if (!$catalogPresent) $status = 'hold_catalog_missing';
    elseif (!$catalogActive) $status = 'hold_catalog_inactive';
    elseif (mcp_is_non_sellable_country($countryName)) $status = 'hold_non_sellable_country';
    else $status = 'ready_for_local_seed';

    $providers = [];
    $rawProviders = mcp_text($row['provider_namespaces'] ?? null, 1000);
    if ($rawProviders !== null) {
        foreach (explode(',', $rawProviders) as $provider) {
            $provider = trim($provider);
            if ($provider !== '') $providers[$provider] = true;
        }
    }
    $providers = array_keys($providers);
    sort($providers, SORT_STRING);
    mcp_require(count($providers) === $acceptedProviders, 'provider_namespace_count_mismatch');

    $normalized = [
        'tv_hotel_id' => $tvId,
        'status' => $status,
        'hotel_name' => mcp_text($row['hotel_name'] ?? null, 255),
        'country_id' => $row['country_id'] === null || $row['country_id'] === '' ? null : (int)$row['country_id'],
        'country_name' => $countryName,
        'region_id' => $row['region_id'] === null || $row['region_id'] === '' ? null : (int)$row['region_id'],
        'region_name' => mcp_text($row['region_name'] ?? null, 180),
        'subregion_id' => $row['subregion_id'] === null || $row['subregion_id'] === '' ? null : (int)$row['subregion_id'],
        'subregion_name' => mcp_text($row['subregion_name'] ?? null, 180),
        'category' => $row['category'] === null || $row['category'] === '' ? null : (int)$row['category'],
        'latitude' => $row['latitude'] === null || $row['latitude'] === '' ? null : (float)$row['latitude'],
        'longitude' => $row['longitude'] === null || $row['longitude'] === '' ? null : (float)$row['longitude'],
        'accepted_source_count' => $acceptedSources,
        'accepted_provider_count' => $acceptedProviders,
        'provider_namespaces' => $providers,
        'observations_24h' => mcp_nonnegative_int($row['observations_24h'] ?? 0, 'observations_24h'),
        'observations_7d' => mcp_nonnegative_int($row['observations_7d'] ?? 0, 'observations_7d'),
        'observations_total' => mcp_nonnegative_int($row['observations_total'] ?? 0, 'observations_total'),
        'searches_24h' => mcp_nonnegative_int($row['searches_24h'] ?? 0, 'searches_24h'),
        'searches_7d' => mcp_nonnegative_int($row['searches_7d'] ?? 0, 'searches_7d'),
        'searches_total' => mcp_nonnegative_int($row['searches_total'] ?? 0, 'searches_total'),
        'last_observed_at' => mcp_text($row['last_observed_at'] ?? null, 64),
    ];
    $normalized['priority_tier'] = mcp_tier($normalized);
    return $normalized;
}

function mcp_ready_compare(array $a, array $b): int {
    $tier = mcp_tier_rank($a['priority_tier']) <=> mcp_tier_rank($b['priority_tier']);
    if ($tier !== 0) return $tier;
    foreach (['observations_24h','searches_24h','observations_7d','searches_7d','accepted_provider_count','accepted_source_count','observations_total','searches_total'] as $field) {
        $cmp = $b[$field] <=> $a[$field];
        if ($cmp !== 0) return $cmp;
    }
    $lastCmp = strcmp((string)($b['last_observed_at'] ?? ''), (string)($a['last_observed_at'] ?? ''));
    if ($lastCmp !== 0) return $lastCmp;
    return $a['tv_hotel_id'] <=> $b['tv_hotel_id'];
}

/** @return array{ready_rows:list<array<string,mixed>>,held_rows:list<array<string,mixed>>,census:array<string,mixed>} */
function mcp_build(array $rows): array {
    mcp_require(array_is_list($rows) && count($rows) <= MCP_MAX_ROWS, 'row_budget');
    $ready = [];
    $held = [];
    $seen = [];
    $statusCounts = [];
    $tierCounts = [];
    foreach ($rows as $raw) {
        mcp_require(is_array($raw), 'row_shape');
        $row = mcp_normalize_row($raw);
        $tv = $row['tv_hotel_id'];
        mcp_require(!isset($seen[$tv]), 'duplicate_tv_target');
        $seen[$tv] = true;
        $statusCounts[$row['status']] = ($statusCounts[$row['status']] ?? 0) + 1;
        $tierCounts[$row['priority_tier']] = ($tierCounts[$row['priority_tier']] ?? 0) + 1;
        if ($row['status'] === 'ready_for_local_seed') $ready[] = $row;
        else $held[] = $row;
    }
    usort($ready, 'mcp_ready_compare');
    usort($held, static fn(array $a, array $b): int => [$a['status'],$a['tv_hotel_id']] <=> [$b['status'],$b['tv_hotel_id']]);
    ksort($statusCounts); ksort($tierCounts);
    return [
        'ready_rows' => $ready,
        'held_rows' => $held,
        'census' => [
            'schema' => MCP_SCHEMA,
            'missing_canonical_targets' => count($rows),
            'ready_for_local_seed' => count($ready),
            'held' => count($held),
            'by_status' => $statusCounts,
            'by_priority_tier' => $tierCounts,
        ],
    ];
}

function mcp_query(PDO $db): array {
    $required = ['andromeda_hotel_identities','anex_hotel_search_mappings','anytour_hotel_sources','tour_price_observations','catalog_hotels'];
    $marks = implode(',', array_fill(0, count($required), '?'));
    $stmt = $db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");
    $stmt->execute($required);
    $engines = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($required as $table) mcp_require(($engines[$table] ?? null) === 'InnoDB', 'table_contract_' . $table);

    $sql = <<<'SQL'
WITH accepted_provider AS (
    SELECT CAST(local_hotel_id AS UNSIGNED) AS tv_hotel_id,
           supplier_namespace AS provider_namespace,
           external_hotel_id AS provider_hotel_id
    FROM andromeda_hotel_identities
    WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL AND local_hotel_id > 0
    UNION ALL
    SELECT CAST(catalog_hotel_id AS UNSIGNED) AS tv_hotel_id,
           'operator_5' AS provider_namespace,
           CAST(anex_hotel_id AS CHAR) AS provider_hotel_id
    FROM anex_hotel_search_mappings
    WHERE enabled=1 AND catalog_hotel_id > 0 AND anex_hotel_id > 0
), provider_targets AS (
    SELECT tv_hotel_id,
           COUNT(DISTINCT CONCAT(provider_namespace, CHAR(31), provider_hotel_id)) AS accepted_source_count,
           COUNT(DISTINCT provider_namespace) AS accepted_provider_count,
           GROUP_CONCAT(DISTINCT provider_namespace ORDER BY provider_namespace SEPARATOR ',') AS provider_namespaces
    FROM accepted_provider
    GROUP BY tv_hotel_id
), demand AS (
    SELECT hotel_id AS tv_hotel_id,
           COUNT(*) AS observations_total,
           SUM(observed_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR) AS observations_24h,
           SUM(observed_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY) AS observations_7d,
           COUNT(DISTINCT search_id) AS searches_total,
           COUNT(DISTINCT CASE WHEN observed_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR THEN search_id END) AS searches_24h,
           COUNT(DISTINCT CASE WHEN observed_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY THEN search_id END) AS searches_7d,
           MAX(observed_at) AS last_observed_at
    FROM tour_price_observations
    WHERE hotel_id > 0
    GROUP BY hotel_id
)
SELECT p.tv_hotel_id,p.accepted_source_count,p.accepted_provider_count,p.provider_namespaces,
       IF(h.id IS NULL,0,1) AS catalog_present,
       h.is_active AS catalog_active,h.name AS hotel_name,h.country_id,h.country_name,
       h.region_id,h.region_name,h.subregion_id,h.subregion_name,h.category,h.latitude,h.longitude,
       COALESCE(d.observations_24h,0) AS observations_24h,
       COALESCE(d.observations_7d,0) AS observations_7d,
       COALESCE(d.observations_total,0) AS observations_total,
       COALESCE(d.searches_24h,0) AS searches_24h,
       COALESCE(d.searches_7d,0) AS searches_7d,
       COALESCE(d.searches_total,0) AS searches_total,
       d.last_observed_at
FROM provider_targets p
LEFT JOIN anytour_hotel_sources s
  ON s.namespace='legacy_catalog' AND s.external_key=CAST(p.tv_hotel_id AS CHAR)
LEFT JOIN catalog_hotels h ON h.id=p.tv_hotel_id
LEFT JOIN demand d ON d.tv_hotel_id=p.tv_hotel_id
WHERE s.anytour_hotel_id IS NULL
ORDER BY p.tv_hotel_id
SQL;
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    mcp_require(count($rows) <= MCP_MAX_ROWS, 'row_budget');
    return $rows;
}

function mcp_write_new(string $path, string $bytes): string {
    $f = @fopen($path, 'x+b');
    mcp_require(is_resource($f), 'exclusive_output');
    mcp_require(fwrite($f, $bytes) === strlen($bytes) && fflush($f), 'output_write');
    if (function_exists('fsync')) mcp_require(fsync($f), 'output_sync');
    rewind($f); mcp_require(stream_get_contents($f) === $bytes, 'output_readback'); fclose($f);
    return hash('sha256', $bytes);
}

function mcp_json(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . "\n";
}

function mcp_self_test(): void {
    $base = [
        'tv_hotel_id'=>'10','accepted_source_count'=>'2','accepted_provider_count'=>'2','provider_namespaces'=>'operator_315,operator_5',
        'catalog_present'=>'1','catalog_active'=>'1','hotel_name'=>'A','country_id'=>'4','country_name'=>'Turkey','region_id'=>'1','region_name'=>'Antalya',
        'subregion_id'=>null,'subregion_name'=>null,'category'=>'5','latitude'=>'36.1','longitude'=>'30.1',
        'observations_24h'=>'5','observations_7d'=>'20','observations_total'=>'100','searches_24h'=>'2','searches_7d'=>'4','searches_total'=>'10','last_observed_at'=>'2026-09-17 12:00:00'
    ];
    $b=$base; $b['tv_hotel_id']='11'; $b['observations_24h']='0'; $b['observations_7d']='10';
    $c=$base; $c['tv_hotel_id']='12'; $c['country_name']='Russia';
    $out=mcp_build([$b,$c,$base]);
    mcp_require(count($out['ready_rows'])===2 && $out['ready_rows'][0]['tv_hotel_id']===10, 'self_rank');
    mcp_require(($out['census']['by_status']['hold_non_sellable_country'] ?? 0)===1, 'self_country_hold');
    mcp_require($out['ready_rows'][1]['priority_tier']==='live_7d', 'self_tier');
}

if (in_array('--self-test', $argv ?? [], true)) { mcp_self_test(); echo "missing canonical priority v1 self-test PASS\n"; exit(0); }
if (defined('MCP_LIBRARY_ONLY') && MCP_LIBRARY_ONLY === true) return;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$op=trim((string)getenv('MATCH_OPERATION_ID')); $sha=trim((string)getenv('MATCH_SOURCE_SHA'));
mcp_require((bool)preg_match('/^hotel-match-missing-canonical-priority-1971-[0-9]{8}-v[0-9]+$/D',$op),'operation_id');
mcp_require((bool)preg_match('/^[a-f0-9]{40}$/D',$sha),'source_sha');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.$op;
mcp_require(is_dir($dir),'operation_dir_missing');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
mcp_require(($res['operation_id']??'')===$op && ($res['source_sha']??'')===$sha && ($res['state']??'')==='reserved_before_db_access','reservation_contract');
$result=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];
$db=null;
try {
    $root=realpath(getcwd()); mcp_require(is_string($root)&&basename($root)==='anytoour.ru','runtime_root');
    $dbFile=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; require_once $dbFile;
    $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $built=mcp_build(mcp_query($db)); $db->exec('ROLLBACK');
    $payload=['schema'=>MCP_SCHEMA,'generated_at_utc'=>gmdate('c'),'census'=>$built['census'],'ready_rows'=>$built['ready_rows'],'held_rows'=>$built['held_rows']];
    $payloadBytes=mcp_json($payload); $payloadSha=mcp_write_new($dir.'/priority-backlog.json',$payloadBytes);
    $result['state']='completed_read_only'; $result['census']=$built['census']; $result['payload_sha256']=$payloadSha; $result['transaction']='REPEATABLE READ / READ ONLY';
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    $result['error_code']=preg_match('/^[a-zA-Z0-9_\-]{2,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';
}
$resultBytes=mcp_json($result); $resultSha=mcp_write_new($dir.'/result.json',$resultBytes);
$receipt=['operation_id'=>$op,'source_sha'=>$sha,'state'=>$result['state'],'result_sha256'=>$resultSha,'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$resultSha,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0];
mcp_write_new($dir.'/receipt.json',mcp_json($receipt));
echo mcp_json(['state'=>$result['state'],'census'=>$result['census']??null,'payload_sha256'=>$result['payload_sha256']??null,'result_sha256'=>$resultSha]);
exit($result['state']==='completed_read_only'?0:2);
