<?php
declare(strict_types=1);

/** MATCH #1971: large read-only direct ANEX ↔ Andromeda evidence sweep. */
const AAP1_OPERATION = 'hotel-match-anex-andromeda-core8-pair-scale-1971-20260911-v1';
const AAP1_ANDROMEDA_OPERATOR = '5';
const AAP1_PAGE_CAP = 100;
const AAP1_HOTEL_CAP = 5000;
const AAP1_PAIR_CAP = 5000;

function aap1_text($value, int $max = 180): string
{
    if (!is_string($value)) return '';
    $value = trim((string) (preg_replace('/\s+/u', ' ', $value) ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function aap1_norm(string $value, bool $dropGeneric = false): string
{
    $value = str_replace(['Ё', 'ё'], ['Е', 'е'], $value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = (string) (preg_replace('/\bex\.?\s*/iu', ' ', $value) ?? $value);
    $value = (string) (preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value);
    $parts = array_values(array_filter(preg_split('/\s+/u', trim($value)) ?: [], static fn($x) => $x !== ''));
    if ($dropGeneric) {
        $parts = array_values(array_filter($parts, static fn($x) => !in_array($x, ['hotel', 'resort', 'spa'], true)));
    }
    return implode(' ', $parts);
}

function aap1_aliases(string $name): array
{
    $raw = [$name, (string) (preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name) ?? $name)];
    if (preg_match_all('/\((?:EX\.?\s*)?([^)]{2,100})\)/iu', $name, $matches)) {
        foreach ($matches[1] as $former) $raw[] = trim((string) $former);
    }
    $out = [];
    foreach ($raw as $candidate) {
        foreach ([false, true] as $dropGeneric) {
            $key = aap1_norm((string) $candidate, $dropGeneric);
            if ($key !== '') $out[$key] = true;
        }
    }
    return array_keys($out);
}

function aap1_unique_pairs(array $anex, array $andromeda): array
{
    $aIndex = [];
    $dIndex = [];
    foreach ($anex as $key => $hotel) foreach ($hotel['aliases'] as $alias) $aIndex[$alias][] = $key;
    foreach ($andromeda as $key => $hotel) foreach ($hotel['aliases'] as $alias) $dIndex[$alias][] = $key;
    $aliases = array_intersect(array_keys($aIndex), array_keys($dIndex));
    sort($aliases, SORT_STRING);
    $seen = [];
    $rows = [];
    foreach ($aliases as $alias) {
        $a = array_values(array_unique($aIndex[$alias] ?? []));
        $d = array_values(array_unique($dIndex[$alias] ?? []));
        if (count($a) !== 1 || count($d) !== 1) continue;
        $ak = $a[0];
        $dk = $d[0];
        $dedupe = $ak . '|' . $dk;
        if (isset($seen[$dedupe])) continue;
        $seen[$dedupe] = true;
        $rows[] = [
            'alias_key' => $alias,
            'anex' => [
                'id' => $anex[$ak]['id'],
                'name' => $anex[$ak]['name'],
                'local_id' => $anex[$ak]['local_id'],
            ],
            'andromeda' => [
                'id' => $andromeda[$dk]['id'],
                'namespace' => $andromeda[$dk]['namespace'],
                'name' => $andromeda[$dk]['name'],
            ],
        ];
        if (count($rows) >= AAP1_PAIR_CAP) break;
    }
    return $rows;
}

function aap1_fail(string $stage, string $reason, array $extra = []): void
{
    echo 'AAP1_JSON:' . json_encode(array_merge([
        'status' => 'failed',
        'stage' => $stage,
        'reason' => $reason,
        'operation_id' => AAP1_OPERATION,
        'tourvisor_calls' => 0,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'raw_provider_bodies_recorded' => false,
        'token_values_recorded' => false,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(2);
}

try {
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');
    $root = realpath(getcwd());
    if (!$root || basename($root) !== 'anytoour.ru') aap1_fail('bootstrap', 'server_root_invalid');
    $preview = $root . '/_preview/search3-anex-candidate';
    $andromedaApi = $preview . '/api-andromeda-search3-preview.php';
    if (!is_file($andromedaApi)) aap1_fail('bootstrap', 'andromeda_runtime_missing');
    require_once $andromedaApi;
    $anexPrivate = $preview . '/.anex-private.php';
    if (is_file($anexPrivate)) require_once $anexPrivate;
    $andromedaPrivate = $preview . '/.andromeda-private.php';
    if (!is_file($andromedaPrivate)) aap1_fail('bootstrap', 'andromeda_private_missing');
    $andromedaConfig = require $andromedaPrivate;
    if (!is_array($andromedaConfig)) aap1_fail('bootstrap', 'andromeda_config_invalid');
    require_once $root . '/config.php';
    $dbHelper = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
    require_once $dbHelper;
    $app = is_file($preview . '/app/integrations/anex-search.php') ? $preview . '/app/integrations' : $root . '/app/integrations';
    foreach (['anex-search', 'anex-search-mapping-registry'] as $file) require_once $app . '/' . $file . '.php';

    $pdo = v2_data_db();
    $anexToken = trim((string) getenv('ANEX_API_TOKEN'));
    if ($anexToken === '' && defined('ANEX_API_TOKEN')) $anexToken = trim((string) ANEX_API_TOKEN);
    if ($anexToken === '') aap1_fail('anex', 'credential_missing');
    $resolver = AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();
    $dictionaryCache = [];

    $budgetDir = dirname((string) $andromedaConfig['catalog_path']);
    $transport = new AnyTourAndromedaTransport(true);
    $makeAndromeda = static function () use ($transport, $budgetDir) {
        return new AnyTourAndromedaClient(static function ($url, $options) use ($transport, $budgetDir) {
            anytour_andromeda_search3_budget($budgetDir);
            return $transport($url, $options);
        }, true);
    };
    $login = $makeAndromeda();
    $login->login((string) $andromedaConfig['username'], (string) $andromedaConfig['password']);
    $privateSession = $login->privateSession();
    if (!$privateSession) aap1_fail('andromeda', 'private_session_missing');

    $criteriaMatrix = [
        [1, 'Egypt', '2026-11-27', 8], [1, 'Egypt', '2027-01-08', 9],
        [4, 'Turkey', '2026-12-11', 8], [4, 'Turkey', '2027-01-15', 7],
        [2, 'Thailand', '2026-11-27', 7], [2, 'Thailand', '2027-01-08', 8],
        [9, 'UAE', '2026-11-27', 7], [9, 'UAE', '2027-01-08', 8],
        [16, 'Vietnam', '2026-12-11', 8], [16, 'Vietnam', '2027-01-15', 7],
        [12, 'Sri Lanka', '2026-12-11', 8], [12, 'Sri Lanka', '2027-01-15', 7],
        [8, 'Maldives', '2026-11-27', 7], [8, 'Maldives', '2027-01-08', 8],
    ];
    $lookup = $pdo->prepare('SELECT d.name AS departure_name,c.name AS country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');
    $sliceRows = [];
    $pairAggregate = [];
    $calls = ['anex_requests' => 0, 'anex_slices' => 0, 'andromeda_login' => 1, 'andromeda_price_pages' => 0];

    foreach ($criteriaMatrix as [$countryId, $countryLabel, $date, $nights]) {
        $criteria = [
            'departureId' => 1, 'countryId' => $countryId, 'dateFrom' => $date, 'dateTo' => $date,
            'nightsFrom' => $nights, 'nightsTo' => $nights, 'adults' => 2, 'childs' => [], 'currency' => 'RUB',
            'meal' => '', 'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [], 'arrivalId' => '',
            'operatorIds' => [], 'hotelServices' => [], 'hotelTypes' => [], 'onlyDirect' => false, 'onlyCharter' => false,
            'hotelCategory' => '', 'hotelRating' => '', 'priceFrom' => '', 'priceTo' => '',
        ];
        $sliceKey = $countryId . ':' . $date . ':' . $nights;
        $slice = [
            'key' => $sliceKey, 'country_id' => $countryId, 'country' => $countryLabel,
            'date' => $date, 'nights' => $nights, 'state' => 'checked',
            'anex_hotels' => 0, 'andromeda_hotels' => 0, 'andromeda_pages' => 0, 'unique_pairs' => 0,
        ];
        try {
            // Fresh client per slice: preserves the global token-hash rate slot while avoiding the intentional
            // 12-request per-object cap truncating a mass matrix.
            $anexClient = new AnyTourAnexClient($anexToken);
            $beforeRequests = $anexClient->requestsMade();
            $core = anytour_anex_search3_core($criteria);
            $lookup->execute([1, $countryId]);
            $names = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$names) throw new RuntimeException('local_dictionary_names_missing');
            $core['supplier_namespace'] = 'anex_online';
            $core['departure_id'] = anytour_anex_search3_dictionary_id(
                anytour_anex_search3_dictionary($anexClient, 'SearchTour_TOWNFROMS', [], $dictionaryCache),
                [$names['departure_name']]
            );
            $core['destination_id'] = anytour_anex_search3_dictionary_id(
                anytour_anex_search3_dictionary($anexClient, 'SearchTour_STATES', ['TOWNFROMINC' => $core['departure_id']], $dictionaryCache),
                [$names['country_name']]
            );
            $dated = [
                'TOWNFROMINC' => $core['departure_id'], 'STATEINC' => $core['destination_id'],
                'CHECKIN_BEG' => str_replace('-', '', $core['checkin_begin']),
                'CHECKIN_END' => str_replace('-', '', $core['checkin_end']), 'ADULT' => 2, 'CHILD' => 0,
            ];
            $core['currency_id'] = anytour_anex_search3_dictionary_id(
                anytour_anex_search3_dictionary($anexClient, 'SearchTour_CURRENCIES', $dated, $dictionaryCache),
                ['RUB', 'RUR', 'Рубль', 'Рубли', 'Руб']
            );
            $anexResult = anytour_anex_search3_prices($anexClient, $resolver, $core);
            $calls['anex_requests'] += $anexClient->requestsMade() - $beforeRequests;
            $calls['anex_slices']++;
            $anex = [];
            foreach ($anexResult['offers'] as $offer) {
                $hotel = $offer['hotel'] ?? [];
                $id = (string) ($hotel['external_id'] ?? '');
                $name = aap1_text($hotel['name'] ?? '');
                if ($id === '' || $name === '' || isset($anex[$id])) continue;
                $anex[$id] = [
                    'id' => $id, 'name' => $name,
                    'local_id' => is_int($hotel['local_id'] ?? null) ? $hotel['local_id'] : null,
                    'aliases' => aap1_aliases($name),
                ];
                if (count($anex) > AAP1_HOTEL_CAP) throw new RuntimeException('anex_hotel_cap');
            }
            $slice['anex_hotels'] = count($anex);

            $request = ['generation' => 1, 'page' => 1, 'params' => $criteria, 'andromeda_operator_ids' => [AAP1_ANDROMEDA_OPERATOR]];
            $saved = anytour_andromeda_search3_catalog($andromedaConfig, $request);
            $saved['excluded_operator_ids'] = $andromedaConfig['excluded_operator_ids'] ?? [];
            $baseParams = anytour_andromeda_search3_params($request, $pdo, $saved);
            $andromeda = [];
            $page = 1;
            $pages = 1;
            do {
                if ($page > AAP1_PAGE_CAP) throw new RuntimeException('andromeda_page_cap');
                $client = $makeAndromeda();
                $client->restorePrivateSession($privateSession);
                $params = $baseParams;
                $params['PAGE'] = $page;
                $raw = $client->price($params);
                $calls['andromeda_price_pages']++;
                $projection = AnyTourAndromedaNormalizer::page($raw, $params, 'aap1', 1);
                $pages = (int) $projection['pages_count'];
                foreach ($projection['offers'] as $offer) {
                    $id = (string) ($offer['external_hotel_id'] ?? '');
                    $name = aap1_text($offer['hotel'] ?? '');
                    $namespace = aap1_text($offer['supplier_namespace'] ?? '', 80);
                    if ($id === '' || $name === '') continue;
                    $key = $namespace . ':' . $id;
                    if (!isset($andromeda[$key])) {
                        $andromeda[$key] = [
                            'id' => $id, 'namespace' => $namespace, 'name' => $name,
                            'aliases' => aap1_aliases($name),
                        ];
                    }
                    if (count($andromeda) > AAP1_HOTEL_CAP) throw new RuntimeException('andromeda_hotel_cap');
                }
                $page++;
            } while ($page <= $pages);
            $slice['andromeda_hotels'] = count($andromeda);
            $slice['andromeda_pages'] = $pages;
            $pairs = aap1_unique_pairs($anex, $andromeda);
            $slice['unique_pairs'] = count($pairs);
            foreach ($pairs as $pair) {
                $pairKey = $pair['anex']['id'] . '|' . $pair['andromeda']['namespace'] . ':' . $pair['andromeda']['id'];
                if (!isset($pairAggregate[$pairKey])) {
                    $pairAggregate[$pairKey] = $pair + ['pair_key' => $pairKey, 'occurrences' => 0, 'criteria' => []];
                }
                $pairAggregate[$pairKey]['occurrences']++;
                $pairAggregate[$pairKey]['criteria'][] = ['country_id' => $countryId, 'date' => $date, 'nights' => $nights];
            }
        } catch (Throwable $error) {
            $slice['state'] = 'supplier_error';
            $slice['safe_error'] = aap1_text($error->getMessage(), 100);
        }
        $sliceRows[] = $slice;
        usleep(2000000);
    }

    $pairs = array_values($pairAggregate);
    usort($pairs, static function ($a, $b) {
        $cmp = ((int) $b['occurrences']) <=> ((int) $a['occurrences']);
        if ($cmp !== 0) return $cmp;
        $aLocal = is_int($a['anex']['local_id']) && $a['anex']['local_id'] > 0 ? 0 : 1;
        $bLocal = is_int($b['anex']['local_id']) && $b['anex']['local_id'] > 0 ? 0 : 1;
        return ($aLocal <=> $bLocal) ?: strcmp($a['pair_key'], $b['pair_key']);
    });
    $withLocal = 0;
    $recurrent = 0;
    foreach ($pairs as $pair) {
        if (is_int($pair['anex']['local_id']) && $pair['anex']['local_id'] > 0) $withLocal++;
        if ((int) $pair['occurrences'] >= 2) $recurrent++;
    }

    echo 'AAP1_JSON:' . json_encode([
        'status' => 'completed',
        'operation_id' => AAP1_OPERATION,
        'rule' => 'exact unique supplier pair evidence only; HOTEL/RESORT/SPA generic variants removed, former EX aliases allowed; no mapping from evidence alone',
        'criteria_count' => count($criteriaMatrix),
        'slices' => $sliceRows,
        'unique_pair_count' => count($pairs),
        'recurrent_pair_count' => $recurrent,
        'pairs_with_existing_anex_local_id' => $withLocal,
        'pairs' => $pairs,
        'provider_calls' => $calls,
        'tourvisor_calls' => 0,
        'database_writes' => 0,
        'mapping_writes' => 0,
        'booking_calls' => 0,
        'lead_calls' => 0,
        'raw_provider_bodies_recorded' => false,
        'token_values_recorded' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    aap1_fail('runtime', 'provider_exception', [
        'exception_class' => get_class($error),
        'safe_message' => aap1_text($error->getMessage(), 120),
    ]);
}
