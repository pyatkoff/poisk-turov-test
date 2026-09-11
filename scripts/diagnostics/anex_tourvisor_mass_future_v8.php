<?php
declare(strict_types=1);

/** MATCH #1971 v8: read-only CURRENT-DB + live Tourvisor ANEX future-date evidence collector. */

if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/anex_tourvisor_hotelcode_evidence_collect.php';
require_once __DIR__ . '/tourvisor-client-v1.php';

const MTV8_OPERATION = 'hotel-match-anex-tourvisor-mass-future-1971-20260911-v8';
const MTV8_LEAD_DAYS = [14, 28, 42];
const MTV8_MAX_DETAIL = 250;
const MTV8_MAX_PAGE_GETS = 180;

function mtv8_safe_message(Throwable $e): string
{
    $message = trim((string)$e->getMessage());
    $message = preg_replace('/[^\p{L}\p{N}\s_.:()\/-]+/u', ' ', $message) ?? '';
    return mb_substr(preg_replace('/\s+/u', ' ', $message) ?? '', 0, 180, 'UTF-8');
}

function mtv8_detail_evidence(array $candidate, int $operatorId, int &$detailCalls, int &$pageGets): array
{
    $row = $candidate;
    $tourId = trim((string)($row['tour_id'] ?? ''));
    if ($tourId === '') {
        unset($row['tour_id']);
        $row['evidence_class'] = 'tourvisor_search_match_no_tour';
        return $row;
    }

    $detailCalls++;
    try {
        $detail = v2_data_tv_get('/tours/' . rawurlencode($tourId), ['currency' => 'RUB']);
    } catch (Throwable $e) {
        unset($row['tour_id']);
        $row['evidence_class'] = 'tourvisor_detail_error';
        $row['detail_error'] = mtv8_safe_message($e);
        return $row;
    }

    $link = trim((string)($detail['operatorLink'] ?? ''));
    $hotellist = $link !== '' ? ate_operator_hotellist($link) : null;
    $host = $link !== '' ? strtolower((string)(parse_url($link, PHP_URL_HOST) ?? '')) : '';

    $pageStatus = 'not_fetched';
    $pageHttp = 0;
    $hotelcode = [
        'status' => 'insufficient_evidence',
        'hotel_code' => null,
        'codes' => [],
        'accepted_urls' => [],
        'rejected' => [],
    ];

    if ($link !== '' && ate_allowed_anex_page($link) && $pageGets < MTV8_MAX_PAGE_GETS) {
        usleep(350000);
        $page = ate_fetch_anex_page($link);
        $pageGets++;
        $pageStatus = (string)($page['status'] ?? 'unknown');
        $pageHttp = (int)($page['http_status'] ?? 0);
        if ($pageStatus === 'ok') {
            $hotelcode = ate_page_hotelcode_evidence((string)($page['body'] ?? ''));
        }
    }

    $seedId = (int)$row['anex_hotel_id'];
    $mediaCode = ($hotelcode['status'] ?? '') === 'confirmed'
        ? (int)($hotelcode['hotel_code'] ?? 0)
        : null;

    $row['operator_id'] = $operatorId;
    $row['operator_link_host'] = $host;
    $row['operator_hotellist'] = $hotellist;
    $row['operator_hotellist_matches_seed'] = $hotellist !== null && $hotellist === $seedId;
    $row['page_status'] = $pageStatus;
    $row['page_http_status'] = $pageHttp;
    $row['hotelcode_status'] = (string)($hotelcode['status'] ?? 'invalid_evidence');
    $row['hotel_code'] = $mediaCode;
    $row['media_evidence_count'] = count($hotelcode['accepted_urls'] ?? []);

    if ($hotellist !== null && $hotellist !== $seedId) {
        $row['evidence_class'] = 'operator_hotellist_conflict';
    } elseif ($row['operator_hotellist_matches_seed'] && $mediaCode !== null && $mediaCode === $seedId) {
        $row['evidence_class'] = 'full_operator_link_plus_hotelcode';
    } elseif ($row['operator_hotellist_matches_seed']) {
        $row['evidence_class'] = 'strong_operator_link_no_hotelcode';
    } elseif ($mediaCode !== null && $mediaCode === $seedId) {
        $row['evidence_class'] = 'media_hotelcode_matches_seed_without_hotellist';
    } elseif ($mediaCode !== null && $mediaCode !== $seedId) {
        $row['evidence_class'] = 'media_hotelcode_conflict';
    } else {
        $row['evidence_class'] = 'tourvisor_search_match_without_direct_anex_id';
    }

    unset($row['tour_id']);
    return $row;
}

try {
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');

    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') {
        throw new RuntimeException('server_root_invalid');
    }

    $config = realpath($root . '/config.php');
    $dbHelper = realpath(
        $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php')
    );
    if (
        !$config || !$dbHelper
        || strpos($config, $root . DIRECTORY_SEPARATOR) !== 0
        || strpos($dbHelper, $root . DIRECTORY_SEPARATOR) !== 0
    ) {
        throw new RuntimeException('runtime_helper_invalid');
    }

    ob_start();
    require_once $config;
    require_once $dbHelper;
    ob_end_clean();

    if (!defined('TOURVISOR_ANEX_JWT')) {
        throw new RuntimeException('anex_jwt_constant_missing');
    }
    $token = trim((string)constant('TOURVISOR_ANEX_JWT'));
    if (stripos($token, 'Bearer ') === 0) $token = trim(substr($token, 7));
    if ($token === '' || strlen($token) < 20) {
        throw new RuntimeException('anex_jwt_empty');
    }
    putenv('TOURVISOR_JWT=' . $token);

    $db = v2_data_db();
    $current = ate_current_seeds($db);
    $seeds = is_array($current['seeds'] ?? null) ? $current['seeds'] : [];
    $coverage = is_array($current['coverage'] ?? null) ? $current['coverage'] : [];

    $countryCounts = [];
    foreach ($seeds as $seed) {
        $country = (int)($seed['country_id'] ?? 0);
        if (!isset(ATE_CORE8[$country])) continue;
        $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
    }
    arsort($countryCounts, SORT_NUMERIC);

    $departures = v2_data_tv_get('/departures');
    $departureId = ate_departure_id(is_array($departures) ? $departures : []);
    if (!$departureId) throw new RuntimeException('moscow_departure_missing');

    $today = new DateTimeImmutable('today');
    $searches = [];
    $rawMatches = [];
    $operatorIds = [];
    $searchStarts = 0;
    $statusCalls = 0;
    $resultCalls = 0;
    $catalogCalls = 1;
    $detailCalls = 0;
    $pageGets = 0;

    foreach (array_keys($countryCounts) as $country) {
        $operatorsRaw = v2_data_tv_get('/operators', [
            'departureId' => $departureId,
            'countryId' => $country,
        ]);
        $catalogCalls++;
        $operatorId = ate_operator_id(is_array($operatorsRaw) ? $operatorsRaw : []);
        $operatorIds[(string)$country] = $operatorId;

        $datesRaw = v2_data_tv_get('/tours/dates', [
            'departureId' => $departureId,
            'countryId' => $country,
            'onlyCharter' => false,
        ]);
        $catalogCalls++;
        $dateRows = is_array($datesRaw) ? $datesRaw : [];

        $selected = [];
        foreach (MTV8_LEAD_DAYS as $lead) {
            $date = ate_pick_date($dateRows, $today->modify('+' . $lead . ' days'));
            if ($date !== null && !isset($selected[$date])) {
                $selected[$date] = 'lead' . $lead;
            }
        }
        if ($selected === []) {
            $date = ate_pick_date($dateRows, $today->modify('+7 days'));
            if ($date !== null) $selected[$date] = 'future_fallback';
        }

        if (!$operatorId || $selected === []) {
            $searches[] = [
                'country_id' => $country,
                'seed_count' => (int)$countryCounts[$country],
                'status' => !$operatorId ? 'anex_operator_missing' : 'date_missing',
                'operator_id' => $operatorId,
            ];
            continue;
        }

        foreach ($selected as $date => $dateMode) {
            $payload = [
                'departureId' => $departureId,
                'countryId' => $country,
                'dateFrom' => $date,
                'dateTo' => $date,
                'nightsFrom' => 7,
                'nightsTo' => 9,
                'adults' => 2,
                'operatorIds' => [$operatorId],
                'currency' => 'RUB',
                'onlyCharter' => false,
                'onlyDirect' => false,
            ];

            try {
                $start = v2_data_tv_get('/tours/search', $payload);
                $searchStarts++;
            } catch (Throwable $e) {
                $searches[] = [
                    'country_id' => $country,
                    'seed_count' => (int)$countryCounts[$country],
                    'status' => 'search_start_error',
                    'date' => $date,
                    'date_mode' => $dateMode,
                    'operator_id' => $operatorId,
                    'error' => mtv8_safe_message($e),
                ];
                continue;
            }

            $searchId = ate_search_id($start);
            if (!$searchId) {
                $searches[] = [
                    'country_id' => $country,
                    'seed_count' => (int)$countryCounts[$country],
                    'status' => 'search_id_missing',
                    'date' => $date,
                    'date_mode' => $dateMode,
                    'operator_id' => $operatorId,
                ];
                continue;
            }

            $complete = false;
            $lastStatus = [];
            $statusError = null;
            for ($poll = 0; $poll < 12; $poll++) {
                if ($poll > 0) sleep(2);
                try {
                    $lastStatus = v2_data_tv_get(
                        '/tours/search/' . $searchId . '/status',
                        ['operatorStatus' => false]
                    );
                    $statusCalls++;
                } catch (Throwable $e) {
                    $statusError = mtv8_safe_message($e);
                    break;
                }
                if (ate_search_complete($lastStatus)) {
                    $complete = true;
                    break;
                }
            }

            $rows = [];
            $resultError = null;
            foreach ([1000, 500, 100] as $limit) {
                try {
                    $result = v2_data_tv_get('/tours/search/' . $searchId, ['limit' => $limit]);
                    $resultCalls++;
                    $rows = ate_tv_rows($result);
                    $resultError = null;
                    break;
                } catch (Throwable $e) {
                    $resultError = mtv8_safe_message($e);
                }
            }

            $matched = 0;
            foreach ($rows as $hotel) {
                $match = ate_match_tv_hotel($hotel, $seeds);
                if (!$match) continue;
                $tourId = ate_first_anex_tour_id($hotel, $operatorId);
                if (!$tourId) continue;
                $match['tour_id'] = $tourId;
                $match['operator_id'] = $operatorId;
                $match['search_date'] = $date;
                $match['date_mode'] = $dateMode;
                $rawMatches[] = $match;
                $matched++;
            }

            $searches[] = [
                'country_id' => $country,
                'seed_count' => (int)$countryCounts[$country],
                'status' => 'completed',
                'date' => $date,
                'date_mode' => $dateMode,
                'operator_id' => $operatorId,
                'search_complete' => $complete,
                'progress' => (int)($lastStatus['progress'] ?? 0),
                'status_error' => $statusError,
                'result_hotels' => count($rows),
                'matched_queue_rows' => $matched,
                'result_error' => $resultError,
            ];
        }
    }

    $bySeed = [];
    foreach ($rawMatches as $match) {
        $bySeed[(int)$match['anex_hotel_id']][] = $match;
    }

    $unique = [];
    $ambiguous = [];
    foreach ($bySeed as $anexId => $list) {
        $targets = [];
        foreach ($list as $match) {
            $targets[(int)$match['tv_hotel_id']] = $match;
        }
        if (count($targets) === 1) {
            $candidate = array_values($targets)[0];
            $candidate['search_observation_count'] = count($list);
            $unique[] = $candidate;
        } else {
            $ambiguous[] = [
                'anex_hotel_id' => (int)$anexId,
                'target_ids' => array_map('intval', array_keys($targets)),
                'search_observation_count' => count($list),
            ];
        }
    }

    usort($unique, static function (array $a, array $b): int {
        return (($a['observed'] ? 0 : 1) <=> ($b['observed'] ? 0 : 1))
            ?: (((int)$b['search_count']) <=> ((int)$a['search_count']))
            ?: (((int)$a['anex_hotel_id']) <=> ((int)$b['anex_hotel_id']));
    });

    $evidence = [];
    $confirmed = [];
    $strong = [];
    $conflicts = [];
    $searchOnly = [];

    foreach (array_slice($unique, 0, MTV8_MAX_DETAIL) as $candidate) {
        usleep(200000);
        $row = mtv8_detail_evidence($candidate, (int)$candidate['operator_id'], $detailCalls, $pageGets);
        $evidence[] = $row;
        $class = (string)($row['evidence_class'] ?? '');

        if (in_array($class, ['full_operator_link_plus_hotelcode', 'media_hotelcode_matches_seed_without_hotellist'], true)) {
            $confirmed[] = $row;
        } elseif ($class === 'strong_operator_link_no_hotelcode') {
            $strong[] = $row;
        } elseif (in_array($class, ['operator_hotellist_conflict', 'media_hotelcode_conflict'], true)) {
            $conflicts[] = $row;
        } else {
            $searchOnly[] = $row;
        }
    }

    $out = [
        'status' => 'completed',
        'operation_id' => MTV8_OPERATION,
        'mode' => 'current_db_live_tourvisor_anex_mass_future_dates',
        'token_value_recorded' => false,
        'database_calls' => 1,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'booking_calls' => 0,
        'search_continue_calls' => 0,
        'coverage' => $coverage,
        'queue_count' => count($seeds),
        'queue_by_country' => $countryCounts,
        'operator_ids' => $operatorIds,
        'supplier' => [
            'departure_id' => $departureId,
            'catalog_calls' => $catalogCalls,
            'search_starts' => $searchStarts,
            'status_calls' => $statusCalls,
            'result_calls' => $resultCalls,
            'tour_detail_calls' => $detailCalls,
            'anex_page_gets' => $pageGets,
        ],
        'searches' => $searches,
        'raw_queue_matches' => count($rawMatches),
        'matched_unique' => count($unique),
        'ambiguous_seed_matches' => $ambiguous,
        'evidence_rows' => $evidence,
        'confirmed_full_path' => $confirmed,
        'strong_operator_link' => $strong,
        'conflicts' => $conflicts,
        'search_only' => $searchOnly,
        'guards' => [
            'current_db_read_only' => true,
            'observed_frequency_priority' => true,
            'one_day_search' => true,
            'lead_day_targets' => MTV8_LEAD_DAYS,
            'anex_operator_verified_per_country' => true,
            'search_continue_calls' => 0,
            'database_writes' => 0,
            'mapping_writes' => 0,
            'booking_calls' => 0,
            'historical_operations_replayed' => false,
            'coordinate_conflict_auto_block_m' => 5000,
        ],
    ];

    echo fc_json($out), PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'failed',
        'operation_id' => MTV8_OPERATION,
        'reason' => 'mass_future_probe_failed',
        'message' => mtv8_safe_message($e),
        'token_value_recorded' => false,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'booking_calls' => 0,
        'search_continue_calls' => 0,
        'historical_operations_replayed' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(2);
}
