<?php

declare(strict_types=1);

/**
 * MATCH #1971 — mass read-only ANEX operator-key ↔ Andromeda key evidence.
 *
 * This is evidence only. It never writes mappings and never calls Tourvisor or
 * direct ANEX. A provider pair is high-confidence only when the raw Andromeda
 * offer contains both key namespaces and its image identity independently
 * corroborates operator/anex/andromeda IDs.
 */

const HM_BULK_COUNTRY = '__MATCH_COUNTRY__';
const HM_BULK_OPERATION = '__MATCH_OPERATION__';
const HM_BULK_DATES = [
    '2026-09-30', '2026-10-14', '2026-10-28', '2026-11-11',
    '2026-11-25', '2026-12-09', '2027-01-13', '2027-02-10',
];
const HM_BULK_NIGHTS = [5, 7, 10, 14];
const HM_BULK_PAGE_CAP = 80;
const HM_BULK_PAIR_CAP = 12000;

function hmb_text($value, int $max = 240): string
{
    if (!is_scalar($value)) return '';
    $s = trim((string)(preg_replace('/\s+/u', ' ', (string)$value) ?? ''));
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}

function hmb_country_key($value): ?string
{
    $s = mb_strtolower(trim((string)$value), 'UTF-8');
    $s = str_replace(['ё', '_', '-'], ['е', ' ', ' '], $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $map = [
        'egypt' => ['египет', 'egypt'],
        'turkey' => ['турция', 'turkey', 'türkiye', 'turkiye'],
        'thailand' => ['таиланд', 'thailand'],
        'uae' => ['оаэ', 'объединенные арабские эмираты', 'united arab emirates', 'uae'],
        'vietnam' => ['вьетнам', 'vietnam', 'viet nam'],
        'srilanka' => ['шри ланка', 'sri lanka'],
        'maldives' => ['мальдивы', 'maldives'],
        'cuba' => ['куба', 'cuba'],
    ];
    foreach ($map as $key => $aliases) if (in_array($s, $aliases, true)) return $key;
    return null;
}

function hmb_collect_offer_rows($node, array &$rows): void
{
    if (!is_array($node)) return;
    if (isset($node['operatorKey'], $node['hotelKey']) && is_array($node['original'] ?? null) && isset($node['original']['hotelKey'])) {
        $rows[] = $node;
    }
    foreach ($node as $value) if (is_array($value)) hmb_collect_offer_rows($value, $rows);
}

function hmb_image_identity(string $url): ?array
{
    if ($url === '') return null;
    if (!preg_match('~/([0-9]+)\.([0-9]+)\.([0-9]+)\.(?:jpe?g|png|webp)(?:\?|$)~i', $url, $m)) return null;
    return ['operator' => (int)$m[1], 'anex' => (int)$m[2], 'andromeda' => (int)$m[3]];
}

function hmb_select_in_chunks(PDO $db, string $sqlTemplate, array $ids, int $chunk = 800): array
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn($v) => $v !== '')));
    if (!$ids) return [];
    $out = [];
    foreach (array_chunk($ids, $chunk) as $part) {
        $sql = str_replace('__IN__', implode(',', array_fill(0, count($part), '?')), $sqlTemplate);
        $q = $db->prepare($sql);
        $q->execute($part);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $out[] = $row;
    }
    return $out;
}

function hmb_emit(array $payload, int $exit = 0): never
{
    $base = [
        'operation_id' => HM_BULK_OPERATION,
        'country' => HM_BULK_COUNTRY,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'tourvisor_calls' => 0,
        'direct_anex_calls' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'no_replay' => true,
        'acceptance_authority' => false,
    ];
    echo 'MATCH_OPERATOR_KEY_BULK_JSON:' . json_encode(array_merge($base, $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($exit);
}

function hmb_main(): never
{
    $validCountries = ['egypt', 'turkey', 'thailand', 'uae', 'vietnam', 'srilanka', 'maldives', 'cuba'];
    if (PHP_SAPI !== 'cli' || !in_array(HM_BULK_COUNTRY, $validCountries, true) || !str_starts_with(HM_BULK_OPERATION, 'hotel-match-andromeda-operator-key-bulk-1971-20260914-v5-')) {
        hmb_emit(['status' => 'failed', 'stage' => 'bootstrap', 'reason' => 'unarmed'], 2);
    }

    try {
        $root = realpath(getcwd());
        if (!$root || basename($root) !== 'anytoour.ru') hmb_emit(['status' => 'failed', 'stage' => 'bootstrap', 'reason' => 'root'], 2);
        $preview = $root . '/_preview/search3-anex-candidate';
        $runtime = $preview . '/api-andromeda-search3-preview.php';
        if (!is_file($runtime)) hmb_emit(['status' => 'failed', 'stage' => 'bootstrap', 'reason' => 'andromeda_runtime_missing'], 2);
        require_once $runtime;

        $private = $preview . '/.andromeda-private.php';
        if (!is_file($private)) hmb_emit(['status' => 'failed', 'stage' => 'bootstrap', 'reason' => 'andromeda_private_missing'], 2);
        $cfg = require $private;
        if (!is_array($cfg)) hmb_emit(['status' => 'failed', 'stage' => 'bootstrap', 'reason' => 'andromeda_config_invalid'], 2);
        require_once $root . '/config.php';
        require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        $db = v2_data_db();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $countries = $db->query('SELECT id,name FROM catalog_countries WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC);
        $countryRow = null;
        foreach ($countries as $row) if (hmb_country_key($row['name'] ?? '') === HM_BULK_COUNTRY) { $countryRow = $row; break; }
        if (!$countryRow) hmb_emit(['status' => 'failed', 'stage' => 'bootstrap', 'reason' => 'country_not_found'], 2);
        $countryId = (int)$countryRow['id'];
        $countryName = (string)$countryRow['name'];

        $budgetDir = dirname((string)($cfg['catalog_path'] ?? ''));
        $transport = new AnyTourAndromedaTransport(true);
        $supplierCalls = 0;
        $client = new AnyTourAndromedaClient(static function ($url, $opts) use ($transport, $budgetDir, &$supplierCalls) {
            if ($budgetDir !== '') anytour_andromeda_search3_budget($budgetDir);
            $supplierCalls++;
            return $transport($url, $opts);
        }, true);
        $client->login((string)($cfg['username'] ?? ''), (string)($cfg['password'] ?? ''));
        $session = $client->privateSession();
        if (!$session) hmb_emit(['status' => 'failed', 'stage' => 'andromeda', 'reason' => 'session', 'supplier_calls' => $supplierCalls], 2);

        $pairs = [];
        $sliceResults = [];
        $rawOperatorRows = 0;
        $verifiedObservations = 0;
        $quarantine = 0;
        $pricePages = 0;
        $sliceErrors = 0;

        foreach (HM_BULK_DATES as $date) {
            foreach (HM_BULK_NIGHTS as $nights) {
                $criteria = [
                    'departureId' => 1, 'countryId' => $countryId,
                    'dateFrom' => $date, 'dateTo' => $date,
                    'nightsFrom' => $nights, 'nightsTo' => $nights,
                    'adults' => 2, 'childs' => [], 'currency' => 'RUB',
                    'meal' => '', 'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [],
                    'arrivalId' => '', 'operatorIds' => [], 'hotelServices' => [], 'hotelTypes' => [],
                    'onlyDirect' => false, 'onlyCharter' => false, 'hotelCategory' => '', 'hotelRating' => '',
                    'priceFrom' => '', 'priceTo' => '',
                ];
                $request = ['generation' => 1, 'page' => 1, 'params' => $criteria, 'andromeda_operator_ids' => ['5']];
                try {
                    $saved = anytour_andromeda_search3_catalog($cfg, $request);
                    $saved['excluded_operator_ids'] = $cfg['excluded_operator_ids'] ?? [];
                    $base = anytour_andromeda_search3_params($request, $db, $saved);
                    $page = 1;
                    $pages = 1;
                    $sliceVerified = 0;
                    do {
                        if ($page > HM_BULK_PAGE_CAP) throw new RuntimeException('page_cap');
                        $params = $base;
                        $params['PAGE'] = $page;
                        if ($page > 1) $client->restorePrivateSession($session);
                        $raw = $client->price($params);
                        $pricePages++;
                        $projected = AnyTourAndromedaNormalizer::page($raw, $params, 'match-operator-key-bulk', 1);
                        $pages = max(1, (int)($projected['pages_count'] ?? 1));
                        $rows = [];
                        hmb_collect_offer_rows($raw, $rows);
                        foreach ($rows as $row) {
                            if ((int)($row['operatorKey'] ?? 0) !== 5) continue;
                            $rawOperatorRows++;
                            $aid = (int)($row['original']['hotelKey'] ?? 0);
                            $did = (int)($row['hotelKey'] ?? 0);
                            if ($aid <= 0 || $did <= 0 || (int)($row['isOperatorHotelKey'] ?? 0) === 1) { $quarantine++; continue; }
                            $img = hmb_image_identity(hmb_text($row['hotelImage'] ?? '', 700));
                            if ($img === null || $img['operator'] !== 5 || $img['anex'] !== $aid || $img['andromeda'] !== $did) { $quarantine++; continue; }
                            $verifiedObservations++;
                            $sliceVerified++;
                            $key = $did . '|' . $aid;
                            if (!isset($pairs[$key])) {
                                $pairs[$key] = [
                                    'andromeda_id' => (string)$did,
                                    'anex_id' => (string)$aid,
                                    'names' => [], 'towns' => [], 'stars' => [], 'observations' => [],
                                ];
                            }
                            $name = hmb_text($row['hotel'] ?? '');
                            $town = hmb_text($row['town'] ?? '');
                            $star = hmb_text($row['star'] ?? '');
                            if ($name !== '') $pairs[$key]['names'][$name] = true;
                            if ($town !== '') $pairs[$key]['towns'][$town] = true;
                            if ($star !== '') $pairs[$key]['stars'][$star] = true;
                            $obsKey = $date . '|n' . $nights;
                            $pairs[$key]['observations'][$obsKey] = ['date' => $date, 'nights' => $nights];
                            if (count($pairs) > HM_BULK_PAIR_CAP) throw new RuntimeException('pair_cap');
                        }
                        $page++;
                    } while ($page <= $pages);
                    $sliceResults[] = ['date' => $date, 'nights' => $nights, 'status' => 'completed', 'pages' => $pages, 'verified_observations' => $sliceVerified];
                } catch (Throwable $e) {
                    $sliceErrors++;
                    $reason = preg_match('/^[a-z0-9_ -]{1,120}$/i', $e->getMessage()) ? $e->getMessage() : get_class($e);
                    $sliceResults[] = ['date' => $date, 'nights' => $nights, 'status' => 'failed', 'reason' => hmb_text($reason, 120)];
                }
            }
        }

        foreach ($pairs as &$pair) {
            $pair['names'] = array_keys($pair['names']); sort($pair['names'], SORT_STRING);
            $pair['towns'] = array_keys($pair['towns']); sort($pair['towns'], SORT_STRING);
            $pair['stars'] = array_keys($pair['stars']); sort($pair['stars'], SORT_STRING);
            $pair['observations'] = array_values($pair['observations']);
            $pair['recurrence'] = count($pair['observations']);
            $pair['distinct_dates'] = count(array_unique(array_column($pair['observations'], 'date')));
            $pair['distinct_nights'] = count(array_unique(array_column($pair['observations'], 'nights')));
        }
        unset($pair);

        $byDid = []; $byAid = [];
        foreach ($pairs as $pair) {
            $byDid[$pair['andromeda_id']][$pair['anex_id']] = true;
            $byAid[$pair['anex_id']][$pair['andromeda_id']] = true;
        }
        $confDid = []; $confAid = [];
        foreach ($byDid as $id => $ids) if (count($ids) > 1) $confDid[$id] = array_keys($ids);
        foreach ($byAid as $id => $ids) if (count($ids) > 1) $confAid[$id] = array_keys($ids);

        $aids = array_values(array_unique(array_column($pairs, 'anex_id')));
        $dids = array_values(array_unique(array_column($pairs, 'andromeda_id')));
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION READ ONLY');
        $maps = hmb_select_in_chunks($db, 'SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id IN (__IN__) ORDER BY anex_hotel_id,catalog_hotel_id', $aids);
        $decisions = hmb_select_in_chunks($db, 'SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id IN (__IN__)', $aids);
        $exclusions = hmb_select_in_chunks($db, 'SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN (__IN__)', $aids);
        $andromeda = hmb_select_in_chunks($db, "SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN (__IN__)", $dids);
        $db->exec('ROLLBACK');

        $mapsByAid = [];
        foreach ($maps as $row) $mapsByAid[(string)$row['anex_hotel_id']][] = $row;
        $protectedAid = [];
        foreach (array_merge($decisions, $exclusions) as $row) $protectedAid[(string)$row['anex_hotel_id']] = true;
        $andByDid = [];
        foreach ($andromeda as $row) $andByDid[(string)$row['external_hotel_id']] = $row;

        $counts = [];
        $rows = [];
        foreach ($pairs as $pair) {
            $aid = $pair['anex_id']; $did = $pair['andromeda_id'];
            $allMaps = $mapsByAid[$aid] ?? [];
            $enabled = array_values(array_filter($allMaps, static fn($r) => (int)$r['enabled'] === 1));
            $dr = $andByDid[$did] ?? null;
            $bucket = 'provider_pair_unanchored';
            $anchor = null;
            if (isset($confDid[$did]) || isset($confAid[$aid])) {
                $bucket = 'provider_pair_conflict';
            } elseif (isset($protectedAid[$aid])) {
                $bucket = 'protected_manual_or_exclusion';
            } elseif (count($enabled) > 1) {
                $bucket = 'current_anex_multiple_enabled';
            } elseif (count($allMaps) > 0 && count($enabled) === 0) {
                $bucket = 'existing_anex_mapping_hold';
            } elseif ($dr !== null && (string)$dr['decision_status'] === 'conflict') {
                $bucket = 'current_andromeda_conflict';
            } else {
                $anexLocal = count($enabled) === 1 ? (int)$enabled[0]['catalog_hotel_id'] : null;
                $andLocal = ($dr !== null && $dr['local_hotel_id'] !== null) ? (int)$dr['local_hotel_id'] : null;
                $andStatus = $dr !== null ? (string)$dr['decision_status'] : '';
                if ($anexLocal !== null && $andStatus === 'accepted' && $andLocal !== null && $anexLocal === $andLocal) {
                    $bucket = 'existing_exact_triple'; $anchor = $anexLocal;
                } elseif ($anexLocal !== null && $andStatus === 'accepted' && $andLocal !== null && $anexLocal !== $andLocal) {
                    $bucket = 'current_target_mismatch';
                } elseif ($anexLocal !== null && ($dr === null || ($andStatus === 'pending' && $andLocal === null))) {
                    $bucket = 'candidate_missing_andromeda_side'; $anchor = $anexLocal;
                } elseif ($anexLocal === null && count($allMaps) === 0 && $andStatus === 'accepted' && $andLocal !== null) {
                    $bucket = 'candidate_missing_anex_side'; $anchor = $andLocal;
                } elseif ($dr === null) {
                    $bucket = 'andromeda_identity_missing';
                }
            }
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
            $pair['bucket'] = $bucket;
            $pair['anchor_local_id'] = $anchor;
            $pair['current_anex_rows'] = $allMaps;
            $pair['current_andromeda'] = $dr;
            $rows[] = $pair;
        }
        ksort($counts);
        usort($rows, static fn($a, $b) => [$a['bucket'], -$a['recurrence'], (int)$a['anex_id'], (int)$a['andromeda_id']] <=> [$b['bucket'], -$b['recurrence'], (int)$b['anex_id'], (int)$b['andromeda_id']]);

        hmb_emit([
            'status' => 'completed',
            'country_id' => $countryId,
            'country_name' => $countryName,
            'dates' => HM_BULK_DATES,
            'nights' => HM_BULK_NIGHTS,
            'planned_slices' => count(HM_BULK_DATES) * count(HM_BULK_NIGHTS),
            'completed_slices' => count(array_filter($sliceResults, static fn($r) => $r['status'] === 'completed')),
            'failed_slices' => $sliceErrors,
            'slice_results' => $sliceResults,
            'supplier_calls' => $supplierCalls,
            'price_pages' => $pricePages,
            'raw_operator_rows' => $rawOperatorRows,
            'verified_observations' => $verifiedObservations,
            'verified_pairs' => count($pairs),
            'quarantine_observations' => $quarantine,
            'provider_conflict_andromeda' => count($confDid),
            'provider_conflict_anex' => count($confAid),
            'current_missing_third_candidates' => ($counts['candidate_missing_andromeda_side'] ?? 0) + ($counts['candidate_missing_anex_side'] ?? 0),
            'counts' => $counts,
            'rows' => $rows,
        ]);
    } catch (Throwable $e) {
        $reason = preg_match('/^[a-z0-9_ .:-]{1,160}$/i', $e->getMessage()) ? $e->getMessage() : get_class($e);
        hmb_emit(['status' => 'failed', 'stage' => 'runtime', 'reason' => hmb_text($reason, 160)], 2);
    }
}

if (getenv('MATCH_OPERATOR_BULK_EXEC') === '1' || realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    hmb_main();
}
