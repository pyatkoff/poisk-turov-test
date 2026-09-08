<?php
declare(strict_types=1);

/** One broad initial search against the deployed isolated preview; no DB writes. */
$report = ['schema_version' => 1, 'ok' => false, 'status' => 'ANEX_SEARCH3_PROBE_ERROR',
    'scope' => 'preview', 'first_page_only' => true, 'mapping_coverage_observed' => false];
$pdo = null;
try {
    $home = (string) getenv('HOME');
    $root = realpath($home . '/www/anytoour.ru');
    $preview = realpath($home . '/www/anytoour.ru/_preview/search3-anex-candidate');
    if (!$root || !$preview || realpath((string) getcwd()) !== $preview
        || $preview !== $root . '/_preview/search3-anex-candidate') throw new RuntimeException('ANEX_INVALID_RUNTIME');
    require $home . '/.anytoour-anex/search3-preview.php';
    if (!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED !== true
        || !defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN) === '') {
        throw new RuntimeException('ANEX_PREVIEW_CONFIGURATION_REQUIRED');
    }
    $manifest = json_decode((string) file_get_contents($preview . '/anex-preview-manifest.json'), true);
    if (!defined('ANEX_PREVIEW_SOURCE_SHA') || !preg_match('/\A[a-f0-9]{40}\z/D', (string) ANEX_PREVIEW_SOURCE_SHA)
        || ($manifest['source_sha'] ?? null) !== ANEX_PREVIEW_SOURCE_SHA) throw new RuntimeException('ANEX_DEPLOYMENT_MISMATCH');
    $report['source_sha'] = ANEX_PREVIEW_SOURCE_SHA;
    require_once $preview . '/app/integrations/anex-search.php';
    require_once $preview . '/app/integrations/anex-search-mapping-registry.php';
    // Loading the endpoint as a module does not enter its HTTP handler.
    $_SERVER['SCRIPT_FILENAME'] = '';
    require_once $preview . '/api-anex-search3-preview.php';
    $helper = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
    require_once $helper;
    $pdo = v2_data_db();
    if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('ANEX_DATABASE_UNAVAILABLE');
    }
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $counts = $pdo->prepare('SELECT COUNT(*) AS total,COUNT(DISTINCT m.catalog_hotel_id) AS unique_catalog_hotels,'
        . " SUM(m.match_class='exact') AS exact,SUM(m.match_class='strong_candidate') AS strong"
        . ' FROM anex_hotel_search_mappings m INNER JOIN catalog_hotels h ON h.id=m.catalog_hotel_id'
        . " WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy=? AND m.match_class IN ('exact','strong_candidate')");
    $counts->execute(['owner_exact_and_strong_20260908']);
    $report['local_mappings'] = array_map('intval', $counts->fetch(PDO::FETCH_ASSOC));
    $report['local_mappings']['effective_after_manual_decisions'] = AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->count();
    $lookup = $pdo->prepare('SELECT d.id AS departure_id,d.name AS departure_name,c.id AS country_id,c.name AS country_name'
        . ' FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1'
        . ' AND d.name IN (?,?) AND c.name IN (?,?) LIMIT 2');
    $lookup->execute(['Москва', 'Moscow', 'Египет', 'Egypt']);
    $local = $lookup->fetchAll(PDO::FETCH_ASSOC);
    if (count($local) !== 1) throw new RuntimeException('ANEX_DESTINATION_UNSUPPORTED');
    $local = $local[0];
    $client = new AnyTourAnexClient(ANEX_API_TOKEN);
    $cache = [];
    $departure = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_TOWNFROMS', [], $cache), [$local['departure_name']]);
    $country = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_STATES', ['TOWNFROMINC' => $departure], $cache), [$local['country_name']]);
    $party = ['TOWNFROMINC' => $departure, 'STATEINC' => $country, 'ADULT' => 2, 'CHILD' => 0];
    $calendar = anytour_anex_search3_dictionary($client, 'SearchTour_CHECKIN', $party, $cache);
    $timezone = new DateTimeZone('Europe/Moscow');
    $start = DateTimeImmutable::createFromFormat('!Ymd', (string) ($calendar['start'] ?? ''), $timezone);
    if (!$start || $start->format('Ymd') !== (string) ($calendar['start'] ?? '')
        || !is_string($calendar['valid'] ?? null)) throw new RuntimeException('ANEX_INVALID_DICTIONARY');
    $today = new DateTimeImmutable('today', $timezone);
    $dates = [];
    foreach (str_split(substr($calendar['valid'], 0, 366)) as $offset => $available) {
        $date = $start->modify('+' . $offset . ' days');
        if (strpos('1235', $available) !== false && $date >= $today->modify('+2 days') && $date <= $today->modify('+60 days')) {
            $dates[] = ['date' => $date, 'priority' => [strpos('235', $available) === false, $date < $today->modify('+7 days'), $date->format('Ymd')]];
        }
    }
    usort($dates, static function ($a, $b) { return $a['priority'] <=> $b['priority']; });
    if (!$dates) throw new RuntimeException('ANEX_NO_AVAILABLE_DATES');
    $checkin = $dates[0]['date']->format('Ymd');
    // Same key order as the endpoint allows it to reuse all prerequisite dictionaries.
    $dated = ['TOWNFROMINC' => $departure, 'STATEINC' => $country, 'CHECKIN_BEG' => $checkin,
        'CHECKIN_END' => $checkin, 'ADULT' => 2, 'CHILD' => 0];
    $currency = anytour_anex_search3_dictionary_id(
        anytour_anex_search3_dictionary($client, 'SearchTour_CURRENCIES', $dated, $cache), ['RUB', 'RUR', 'Рубль', 'Рубли', 'Руб']);
    $nightData = anytour_anex_search3_dictionary($client, 'SearchTour_NIGHTS', $dated + ['CURRENCY' => $currency], $cache);
    $nightRows = $nightData['places'] ?? $nightData['nights'] ?? $nightData;
    $nights = [];
    foreach (is_array($nightRows) ? $nightRows : [] as $row) {
        $value = is_array($row) ? ($row['id'] ?? $row['nights'] ?? null) : $row;
        if ((is_int($value) || is_string($value)) && preg_match('/\A[0-9]{1,2}\z/D', (string) $value)
            && (int) $value >= 3 && (int) $value <= 14) $nights[] = (int) $value;
    }
    usort($nights, static function ($a, $b) { return [abs($a - 7), $a] <=> [abs($b - 7), $b]; });
    if (!$nights) throw new RuntimeException('ANEX_NO_AVAILABLE_NIGHTS');
    $params = ['departureId' => (int) $local['departure_id'], 'countryId' => (int) $local['country_id'],
        'dateFrom' => $dates[0]['date']->format('Y-m-d'), 'dateTo' => $dates[0]['date']->format('Y-m-d'),
        'nightsFrom' => $nights[0], 'nightsTo' => $nights[0], 'adults' => 2, 'childs' => [], 'currency' => 'RUB'];
    $report['criteria'] = $params + ['anex_departure_id' => $departure, 'anex_country_id' => $country,
        'anex_currency_id' => $currency, 'hotel_filter' => false];
    $diagnostics = [];
    $result = anytour_anex_search3_run(['generation' => 1, 'params' => $params], $pdo, $client, $cache, $diagnostics);
    foreach (['supplier_offers', 'mapped_offers', 'rejected_count', 'samples', 'unmapped_hotel_ids'] as $field) {
        if (!array_key_exists($field, $diagnostics)) throw new RuntimeException('ANEX_DIAGNOSTICS_UNAVAILABLE');
        $report[$field] = $diagnostics[$field];
    }
    $report['projected_hotels'] = count($result['hotels']);
    $report['projected_tours'] = array_sum(array_map(static function ($hotel) { return count($hotel['tours']); }, $result['hotels']));
    $report['external_search_pending'] = $result['external_search_pending'];
    $report['supplier_requests'] = $client->requestsMade();
    $report['mapping_coverage_observed'] = $report['mapped_offers'] > 0 && $report['projected_hotels'] > 0;
    $report['status'] = $report['supplier_offers'] === 0 ? 'empty' : ($report['mapping_coverage_observed'] ? 'ok' : 'no_mapped_offers');
    $report['ok'] = true;
    // Reproduce the actual initial form: Moscow -> Turkey, +1..+14 days, 7..10 nights.
    // One additional sequential search, retaining the successful Egypt evidence on failure.
    $lookup->execute(['Москва', 'Moscow', 'Турция', 'Turkey']);
    $defaultLocal = $lookup->fetchAll(PDO::FETCH_ASSOC);
    $defaultProbe = ['ok' => false, 'status' => 'ANEX_DESTINATION_UNSUPPORTED'];
    if (count($defaultLocal) === 1) {
        $defaultParams = $params;
        $defaultParams['countryId'] = (int) $defaultLocal[0]['country_id'];
        $defaultParams['dateFrom'] = $today->modify('+1 day')->format('Y-m-d');
        $defaultParams['dateTo'] = $today->modify('+14 days')->format('Y-m-d');
        $defaultParams['nightsFrom'] = 7;
        $defaultParams['nightsTo'] = 10;
        $defaultProbe['criteria'] = $defaultParams;
        $started = microtime(true);
        try {
            $defaultDiagnostics = [];
            $defaultResult = anytour_anex_search3_run(['generation' => 2, 'params' => $defaultParams], $pdo, $client, $cache, $defaultDiagnostics);
            $defaultProbe['ok'] = true;
            $defaultProbe['status'] = 'ok';
            $defaultProbe['projected_hotels'] = count($defaultResult['hotels']);
            $defaultProbe['supplier_offers'] = $defaultDiagnostics['supplier_offers'];
        } catch (Throwable $error) {
            $code = $error->getMessage();
            $defaultProbe['status'] = preg_match('/\AANEX_[A-Z_]{1,70}\z/D', $code) ? $code : 'ANEX_SEARCH3_PROBE_ERROR';
        }
        $defaultProbe['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);
        $defaultProbe['last_request'] = $client->lastRequestDiagnostics();
    }
    $report['default_form_probe'] = $defaultProbe;
    if (!$defaultProbe['ok'] && count($defaultLocal) === 1) {
        // One control isolates whether the default date interval causes the rejection.
        $defaultParams['dateFrom'] = $params['dateFrom'];
        $defaultParams['dateTo'] = $params['dateTo'];
        $control = ['ok' => false, 'criteria' => $defaultParams];
        try {
            $controlResult = anytour_anex_search3_run(['generation' => 3, 'params' => $defaultParams], $pdo, $client, $cache);
            $control['ok'] = true;
            $control['projected_hotels'] = count($controlResult['hotels']);
        } catch (Throwable $error) {
            $code = $error->getMessage();
            $control['status'] = preg_match('/\AANEX_[A-Z_]{1,70}\z/D', $code) ? $code : 'ANEX_SEARCH3_PROBE_ERROR';
        }
        $control['last_request'] = $client->lastRequestDiagnostics();
        $report['single_date_control'] = $control;
    }
    $report['supplier_requests_total'] = $client->requestsMade();
} catch (Throwable $error) {
    $code = $error->getMessage();
    $report['status'] = preg_match('/\AANEX_[A-Z_]{1,70}\z/D', $code) ? $code : 'ANEX_SEARCH3_PROBE_ERROR';
} finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
}
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
exit($report['ok'] ? 0 : 1);
