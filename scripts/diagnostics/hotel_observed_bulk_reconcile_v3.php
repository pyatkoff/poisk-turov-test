<?php
declare(strict_types=1);

const HB3_OPERATION = 'hotel-observed-bulk-1759-20260911-v3';
const HB3_POLICY = 'owner_exact_and_strong_20260908';

function hb3_json($value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function hb3_hash($value): string { return hash('sha256', is_string($value) ? $value : hb3_json($value)); }
function hb3_norm($value): string {
    $value = mb_strtolower(trim((string)$value), 'UTF-8');
    $value = strtr($value, ['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    preg_match_all('/[\p{L}\p{N}]+/u', $value, $matches);
    return implode(' ', $matches[0]);
}
function hb3_tokens($value, bool $broad = false): array {
    $value = preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s+[^()]+\)\s*$/ui', ' ', (string)$value);
    $generic = ['hotel'=>true,'hotels'=>true,'отель'=>true,'отели'=>true];
    if ($broad) $generic += ['resort'=>true,'resorts'=>true,'spa'=>true,'the'=>true,'and'=>true,'club'=>true,'apart'=>true,'aparthotel'=>true,'apartments'=>true,'apartment'=>true,'suite'=>true,'suites'=>true,'резорт'=>true,'ресорт'=>true,'спа'=>true,'клуб'=>true,'апартаменты'=>true];
    return array_values(array_filter(explode(' ', hb3_norm($value)), static fn($token) => $token !== '' && !isset($generic[$token])));
}
function hb3_strict_key($value): string { return implode(' ', hb3_tokens($value, false)); }
function hb3_similarity($left, $right): array {
    $a = array_fill_keys(array_unique(hb3_tokens($left, true)), true);
    $b = array_fill_keys(array_unique(hb3_tokens($right, true)), true);
    $intersection = count(array_intersect_key($a, $b));
    $union = count($a + $b);
    return [$union ? $intersection / $union : 0.0, $intersection];
}
function hb3_country_allowed($name): bool {
    return !in_array(hb3_norm($name), ['russia','россия','abkhazia','абхазия'], true);
}
function hb3_write_once(string $path, array $value): void {
    $raw = hb3_json($value);
    $file = @fopen($path, 'x');
    if (!$file) throw new RuntimeException('operation_already_reserved');
    @chmod($path, 0600);
    try {
        if (fwrite($file, $raw) !== strlen($raw) || !fflush($file)) throw new RuntimeException('receipt_write');
        if (function_exists('fsync') && !fsync($file)) throw new RuntimeException('receipt_sync');
    } finally { fclose($file); }
}
function hb3_require_tables(PDO $pdo): void {
    $required = ['catalog_hotels','hotel_aliases','anex_hotels','anex_search_hotel_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_search_hotel_observations','andromeda_hotel_identities'];
    $marks = implode(',', array_fill(0, count($required), '?'));
    $query = $pdo->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");
    $query->execute($required);
    $tables = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($required as $table) if (strtoupper((string)($tables[$table] ?? '')) !== 'INNODB') throw new RuntimeException('required_transactional_table_missing');
}
function hb3_coverage(PDO $pdo): array {
    $anex = [];
    $sql = "SELECT m.catalog_hotel_id FROM anex_hotel_search_mappings m JOIN catalog_hotels h ON h.id=m.catalog_hotel_id LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='" . HB3_POLICY . "' AND m.match_class IN ('exact','strong_candidate') AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)";
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) $anex[(int)$id] = true;
    $sql = "SELECT d.catalog_hotel_id FROM anex_hotel_decisions d JOIN catalog_hotels h ON h.id=d.catalog_hotel_id WHERE d.decision_status='accepted' AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)";
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) $anex[(int)$id] = true;
    $andromeda = [];
    foreach ($pdo->query("SELECT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id) $andromeda[(int)$id] = true;
    $triple = count(array_intersect_key($anex, $andromeda));
    $policyLinks = (int)$pdo->query("SELECT COUNT(*) FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='" . HB3_POLICY . "' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)")->fetchColumn();
    $manualAccepted = (int)$pdo->query("SELECT COUNT(*) FROM anex_hotel_decisions d JOIN catalog_hotels h ON h.id=d.catalog_hotel_id WHERE d.decision_status='accepted' AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)")->fetchColumn();
    return ['anex_links'=>$policyLinks+$manualAccepted,'anex_unique_local'=>count($anex),'andromeda_unique_local'=>count($andromeda),'all_three'=>$triple,'anex_tv_only'=>count($anex)-$triple,'andromeda_tv_only'=>count($andromeda)-$triple,'exactly_two'=>count($anex)+count($andromeda)-2*$triple];
}
function hb3_catalog(PDO $pdo, array $countries): array {
    $countries = array_values(array_unique(array_map('intval', $countries)));
    sort($countries, SORT_NUMERIC);
    if (!$countries || count($countries) > 100) throw new RuntimeException('country_scope');
    $marks = implode(',', array_fill(0, count($countries), '?'));
    $query = $pdo->prepare("SELECT id,country_id,country_name,name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE country_id IN ($marks) AND is_active=1 ORDER BY country_id,id FOR UPDATE");
    $query->execute($countries);
    $hotels = []; $names = []; $strict = []; $places = []; $countryNames = []; $hotelCount = 0;
    while ($hotel = $query->fetch(PDO::FETCH_ASSOC)) {
        if (++$hotelCount > 100000) throw new RuntimeException('hotel_scope_limit');
        $id = (int)$hotel['id']; $country = (int)$hotel['country_id'];
        $hotels[$id] = $hotel; $names[$id] = [$hotel['name']]; $countryNames[$country] = $hotel['country_name'];
        $key = hb3_strict_key($hotel['name']); if ($key !== '') $strict[$country][$key][$id] = true;
        foreach ([$hotel['region_name'],$hotel['subregion_name']] as $place) { $place = hb3_norm($place); if ($place !== '') $places[$country][$place][$id] = true; }
    }
    $query = $pdo->prepare("SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN ($marks) AND h.is_active=1 ORDER BY h.country_id,a.hotel_id,a.id FOR UPDATE");
    $query->execute($countries); $aliasCount = 0;
    while ($alias = $query->fetch(PDO::FETCH_ASSOC)) {
        if (++$aliasCount > 200000) throw new RuntimeException('alias_scope_limit');
        $id = (int)$alias['hotel_id']; $country = (int)$alias['country_id'];
        $names[$id][] = $alias['alias']; $key = hb3_strict_key($alias['alias']); if ($key !== '') $strict[$country][$key][$id] = true;
    }
    return [$hotels,$names,$strict,$places,$countryNames,['countries'=>count($countries),'hotels'=>$hotelCount,'aliases'=>$aliasCount]];
}
function hb3_reconcile(PDO $pdo, string $operation): array {
    hb3_require_tables($pdo);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=15');
    $before = hb3_coverage($pdo); $committed = false; $writes = 0;
    try {
        $pdo->beginTransaction();
        $anexObservations = $pdo->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $andromedaObservations = $pdo->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        if (count($anexObservations) > 50000 || count($andromedaObservations) > 50000) throw new RuntimeException('observation_scope_limit');
        $pending = [];
        foreach ($pdo->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC) as $row) $pending[(string)$row['external_hotel_id']] = $row;
        $manual = array_fill_keys(array_map('intval', $pdo->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)), true);
        $existing = array_fill_keys(array_map('intval', $pdo->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)), true);
        $excluded = [];
        foreach ($pdo->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $row) $excluded[(int)$row['anex_hotel_id']][(int)$row['catalog_hotel_id']] = true;
        $staged = array_fill_keys(array_map('intval', $pdo->query('SELECT anex_hotel_id FROM anex_hotels ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN)), true);
        $countries = [];
        foreach ($anexObservations as $row) $countries[(int)$row['country_id']] = true;
        foreach ($andromedaObservations as $row) $countries[(int)$row['country_id']] = true;
        if (!$countries) { $pdo->rollBack(); return ['status'=>'empty','operation_id'=>$operation,'supplier_calls'=>0,'database_writes'=>0,'before'=>$before,'after'=>$before]; }
        [$hotels,$names,$strict,$places,$countryNames,$catalogScope] = hb3_catalog($pdo, array_keys($countries));

        $insert = $pdo->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $mappingDigest = hb3_hash([$operation,'strict_observation_only_v3']);
        $anex = ['observed'=>count($anexObservations),'eligible_no_staging'=>0,'accepted'=>0,'manual'=>0,'existing'=>0,'has_staging'=>0,'blocked_country'=>0,'short_name'=>0,'not_unique'=>0,'pair_excluded'=>0];
        $anexRows = [];
        foreach ($anexObservations as $observation) {
            $id = (int)$observation['anex_hotel_id'];
            if (isset($manual[$id])) { $anex['manual']++; continue; }
            if (isset($existing[$id])) { $anex['existing']++; continue; }
            if (isset($staged[$id])) { $anex['has_staging']++; continue; }
            $country = (int)$observation['country_id'];
            if (!hb3_country_allowed($countryNames[$country] ?? '')) { $anex['blocked_country']++; continue; }
            $anex['eligible_no_staging']++;
            $tokens = hb3_tokens($observation['hotel_name'], false);
            if (count($tokens) < 2) { $anex['short_name']++; continue; }
            $key = implode(' ', $tokens);
            $targets = array_map('intval', array_keys($strict[$country][$key] ?? []));
            if (count($targets) !== 1) { $anex['not_unique']++; continue; }
            $target = $targets[0];
            if (isset($excluded[$id][$target])) { $anex['pair_excluded']++; continue; }
            $evidence = ['operation_id'=>$operation,'provider'=>'anex','rule'=>'strict_observation_unique_current_country_v3','anex_hotel_id'=>$id,'hotel_name'=>$observation['hotel_name'],'country_id'=>$country,'search_count'=>(int)$observation['search_count'],'last_seen_utc'=>$observation['last_seen_utc'],'strict_key'=>$key,'target'=>$target];
            $digest = hb3_hash($evidence);
            $insert->execute([$id,$target,HB3_POLICY,$digest,$mappingDigest]);
            $anexRows[$id] = [$target,$digest]; $anex['accepted']++; $writes++;
        }

        $latest = [];
        foreach ($andromedaObservations as $observation) { $id = (string)$observation['external_hotel_id']; if (!isset($latest[$id])) $latest[$id] = $observation; }
        $update = $pdo->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
        $andromeda = ['unique_observed'=>count($latest),'pending_observed'=>0,'accepted'=>0,'not_pending'=>0,'blocked_country'=>0,'region_missing'=>0,'no_candidate'=>0,'weak_similarity'=>0,'ambiguous_margin'=>0,'category_conflict'=>0];
        $andromedaRows = [];
        foreach ($latest as $external => $observation) {
            $old = $pending[$external] ?? null;
            if (!$old) { $andromeda['not_pending']++; continue; }
            $andromeda['pending_observed']++;
            $country = (int)$observation['country_id'];
            if (!hb3_country_allowed($countryNames[$country] ?? '')) { $andromeda['blocked_country']++; continue; }
            $place = hb3_norm($observation['region_name']);
            if ($place === '') { $andromeda['region_missing']++; continue; }
            $candidateIds = array_map('intval', array_keys($places[$country][$place] ?? []));
            if (!$candidateIds) { $andromeda['no_candidate']++; continue; }
            $best = [0.0,0,null]; $second = 0.0;
            foreach ($candidateIds as $candidateId) {
                $score = 0.0; $intersection = 0;
                foreach ($names[$candidateId] as $localName) {
                    [$currentScore,$currentIntersection] = hb3_similarity($observation['hotel_name'], $localName);
                    if ($currentScore > $score) { $score = $currentScore; $intersection = $currentIntersection; }
                }
                if ($score > $best[0]) { $second = $best[0]; $best = [$score,$intersection,$candidateId]; }
                elseif ($score > $second) $second = $score;
            }
            if ($best[2] === null || $best[0] < 0.80 || $best[1] < 2) { $andromeda['weak_similarity']++; continue; }
            if ($best[0] - $second < 0.15) { $andromeda['ambiguous_margin']++; continue; }
            $target = (int)$best[2]; $hotel = $hotels[$target];
            $sourceCategory = $observation['category'] === null ? null : (int)$observation['category'];
            $targetCategory = $hotel['category'] === null ? null : (int)$hotel['category'];
            if ($sourceCategory && $targetCategory && $sourceCategory !== $targetCategory) { $andromeda['category_conflict']++; continue; }
            $prior = json_decode((string)$old['evidence_json'], true, 64, JSON_THROW_ON_ERROR);
            $evidence = ['prior_evidence'=>$prior,'promotion'=>['operation_id'=>$operation,'source'=>'live_search_observation_stage3','rule'=>'fuzzy_name_exact_region_margin','observation_sha256'=>$observation['observation_sha256'],'observed_at_utc'=>$observation['observed_at_utc'],'hotel_name'=>$observation['hotel_name'],'country_id'=>$country,'region_name'=>$observation['region_name'],'target'=>$target,'score'=>round($best[0],6),'shared_tokens'=>$best[1],'runner_up_score'=>round($second,6),'margin'=>round($best[0]-$second,6),'source_category'=>$sourceCategory,'target_category'=>$targetCategory]];
            $evidenceJson = hb3_json($evidence); $evidenceHash = hash('sha256', $evidenceJson);
            $update->execute([$target,$evidenceHash,$evidenceJson,$external,$old['evidence_sha256']]);
            if ($update->rowCount() !== 1) throw new RuntimeException('andromeda_concurrent_change');
            $andromedaRows[$external] = [$target,$evidenceHash]; $andromeda['accepted']++; $writes++;
        }
        $pdo->commit(); $committed = true;

        $query = $pdo->prepare("SELECT catalog_hotel_id,source_row_digest,enabled,match_class FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND scope='preview' AND approval_policy=?");
        foreach ($anexRows as $id => $expected) {
            $query->execute([$id,HB3_POLICY]); $actual = $query->fetch(PDO::FETCH_ASSOC);
            if (!$actual || (int)$actual['catalog_hotel_id'] !== $expected[0] || $actual['source_row_digest'] !== $expected[1] || (int)$actual['enabled'] !== 1 || $actual['match_class'] !== 'strong_candidate') throw new RuntimeException('anex_readback');
        }
        $query = $pdo->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
        foreach ($andromedaRows as $id => $expected) {
            $query->execute([$id]); $actual = $query->fetch(PDO::FETCH_ASSOC);
            if (!$actual || (int)$actual['local_hotel_id'] !== $expected[0] || $actual['decision_status'] !== 'accepted' || $actual['evidence_sha256'] !== $expected[1]) throw new RuntimeException('andromeda_readback');
        }
        $after = hb3_coverage($pdo);
        return ['status'=>'completed','operation_id'=>$operation,'rules'=>['anex'=>'strict_observation_unique_current_country','andromeda'=>'fuzzy_name_exact_region_margin'],'catalog_scope'=>$catalogScope,'anex'=>$anex,'andromeda'=>$andromeda,'accepted_total'=>$anex['accepted']+$andromeda['accepted'],'database_writes'=>$writes,'readback_verified'=>true,'supplier_calls'=>0,'before'=>$before,'after'=>$after];
    } catch (Throwable $error) {
        if (!$committed && $pdo->inTransaction()) $pdo->rollBack();
        return ['status'=>$committed?'committed_readback_failed':'failed_rolled_back','operation_id'=>$operation,'database_writes'=>$committed?$writes:0,'readback_verified'=>false,'supplier_calls'=>0,'reason'=>in_array($error->getMessage(),['required_transactional_table_missing','country_scope','hotel_scope_limit','alias_scope_limit','observation_scope_limit'],true)?$error->getMessage():'runtime_failure'];
    }
}
function hb3_main(): array {
    $raw = file_get_contents('php://stdin', false, null, 0, 4097);
    $request = json_decode((string)$raw, true, 16, JSON_THROW_ON_ERROR);
    if (($request['operation_id'] ?? '') !== HB3_OPERATION || !in_array($request['phase'] ?? '', ['apply','receipt'], true)) throw new RuntimeException('request_scope');
    $root = realpath(getcwd()); if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('root');
    $config = require $root . '/_preview/search3-anex-candidate/.andromeda-private.php';
    $private = realpath(dirname($config['catalog_path'])); if (!$private || strpos($private, $root . DIRECTORY_SEPARATOR) === 0) throw new RuntimeException('private_state');
    $directory = $private . '/' . HB3_OPERATION;
    if ($request['phase'] === 'receipt') {
        if (is_file($directory . '/result.json')) return json_decode((string)file_get_contents($directory . '/result.json'), true, 64, JSON_THROW_ON_ERROR);
        return ['status'=>is_file($directory . '/reservation.json')?'reserved_or_unknown':'not_started','operation_id'=>HB3_OPERATION,'supplier_calls'=>0];
    }
    if (is_dir($directory) || !mkdir($directory, 0700)) throw new RuntimeException('operation_already_reserved');
    hb3_write_once($directory . '/reservation.json', ['operation_id'=>HB3_OPERATION,'state'=>'reserved_before_database','rules'=>['anex'=>'strict_observation_unique_current_country','andromeda'=>'fuzzy_name_exact_region_margin'],'supplier_calls'=>0]);
    $helper = realpath($root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php'));
    if (!$helper || strpos($helper, $root . DIRECTORY_SEPARATOR) !== 0) throw new RuntimeException('db_helper');
    require_once $helper; $pdo = v2_data_db(); $result = hb3_reconcile($pdo, HB3_OPERATION); hb3_write_once($directory . '/result.json', $result); return $result;
}
if (!defined('HB3_LIBRARY_ONLY')) {
    error_reporting(0); ob_start();
    try { $output = hb3_main(); } catch (Throwable $error) { $output = ['status'=>'failed','operation_id'=>HB3_OPERATION,'reason'=>in_array($error->getMessage(),['operation_already_reserved'],true)?$error->getMessage():'runtime_failure','supplier_calls'=>0]; }
    while (ob_get_level()) ob_end_clean(); echo hb3_json($output), "\n"; exit(in_array($output['status'] ?? '', ['completed','empty'], true) ? 0 : 1);
}
