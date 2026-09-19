<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        throw new InvalidArgumentException('ANDROMEDA_TOURKEY_ARG');
    }
    [$k, $v] = explode('=', substr($arg, 2), 2);
    $args[$k] = $v;
}
$get = static function(string $key) use ($args): string {
    $v = $args[$key] ?? null;
    if (!is_string($v) || $v === '') throw new InvalidArgumentException('ANDROMEDA_TOURKEY_ARG_' . $key);
    return $v;
};
$int = static function(string $value, int $min, int $max): int {
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $value)) throw new InvalidArgumentException('ANDROMEDA_TOURKEY_INT');
    $n = (int)$value;
    if ($n < $min || $n > $max) throw new InvalidArgumentException('ANDROMEDA_TOURKEY_INT');
    return $n;
};
$date = static function(string $value): string {
    if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $value, $m)
        || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        throw new InvalidArgumentException('ANDROMEDA_TOURKEY_DATE');
    }
    return $value;
};
$idText = static function(mixed $value): ?string {
    if (is_int($value)) $value = (string)$value;
    return is_string($value) && preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $value) ? $value : null;
};

$site = realpath($get('site-root'));
$privateConfig = realpath($get('private-config'));
$source = $get('source-sha');
$operation = $get('operation-id');
if ($site === false || basename($site) !== 'anytoour.ru' || $privateConfig === false || !is_file($privateConfig)
    || !preg_match('/\A[a-f0-9]{40}\z/D', $source)
    || !preg_match('/\A[a-z0-9][a-z0-9._-]{20,160}\z/D', $operation)) {
    throw new RuntimeException('ANDROMEDA_TOURKEY_ROOT');
}

$runtime = dirname(__DIR__, 2);
require_once $runtime . '/v2/api-andromeda-search3-preview.php';

$config = require $privateConfig;
if (!is_array($config) || ($config['enabled'] ?? null) !== true) throw new RuntimeException('ANDROMEDA_TOURKEY_CONFIG');

require_once $site . '/config.php';
$dbFile = is_file($site . '/data/db-v1.php') ? $site . '/data/db-v1.php' : $site . '/v2/data/db-v1.php';
require_once $dbFile;
$pdo = v2_data_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$departure = $int($args['departure'] ?? '1', 1, 999999999);
$country = $int($args['country'] ?? '4', 1, 999999999);
$dateFrom = $date($args['date-from'] ?? '2026-09-19');
$dateTo = $date($args['date-to'] ?? $dateFrom);
if ($dateTo < $dateFrom) throw new InvalidArgumentException('ANDROMEDA_TOURKEY_DATE_RANGE');
$nightsFrom = $int($args['nights-from'] ?? '7', 1, 28);
$nightsTo = $int($args['nights-to'] ?? '10', $nightsFrom, 28);
$adults = $int($args['adults'] ?? '2', 1, 6);
$generation = $int($args['generation'] ?? '17171901', 1, 2147483647);

$request = [
    'generation' => $generation,
    'params' => [
        'departureId' => (string)$departure,
        'countryId' => (string)$country,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'nightsFrom' => $nightsFrom,
        'nightsTo' => $nightsTo,
        'adults' => $adults,
        'childs' => [],
        'meal' => '',
        'hotelCategory' => '',
        'hotelRating' => '',
        'hotelTypes' => [],
        'hotelIds' => [],
        'hotelServices' => [],
        'arrivalId' => '',
        'regionIds' => [],
        'subregionIds' => [],
        'operatorIds' => [],
        'priceFrom' => '',
        'priceTo' => '',
        'currency' => 'RUB',
        'onlyCharter' => false,
        'onlyDirect' => false,
    ],
];

$saved = anytour_andromeda_search3_catalog($config, $request);
$saved['excluded_operator_ids'] = $config['excluded_operator_ids'] ?? [];
$criteria = anytour_andromeda_search3_params($request, $pdo, $saved);
$criteria['PAGE'] = 1;
$searchRef = hash('sha256', 'tourkey-inventory-v1|' . $operation . '|' . json_encode($criteria, JSON_THROW_ON_ERROR));

$lookup = $pdo->prepare(
    "SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id AS catalog_hotel_id,"
    . "h.id AS existing_catalog_hotel_id,i.decision_status "
    . "FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id "
    . "WHERE i.decision_status='accepted' AND h.is_active=1 AND h.country_id=? ORDER BY i.external_hotel_id"
);
$lookup->execute([$country]);
$identities = $lookup->fetchAll(PDO::FETCH_ASSOC);
$resolver = AnyTourAndromedaHotelResolver::fromRows(
    $identities,
    hash('sha256', json_encode($identities, JSON_THROW_ON_ERROR))
);
unset($identities);

$monthlyDirectory = dirname((string)$config['catalog_path']);
$lastSupplierStarted = 0.0;
$reserveSupplier = static function() use (&$lastSupplierStarted, $monthlyDirectory): void {
    $wait = 1.05 - (microtime(true) - $lastSupplierStarted);
    if ($wait > 0) usleep((int)ceil($wait * 1000000));
    anytour_andromeda_search3_budget($monthlyDirectory);
    $lastSupplierStarted = microtime(true);
};
$makeClient = static function() use ($reserveSupplier): AnyTourAndromedaClient {
    $transport = new AnyTourAndromedaTransport(true, false);
    $wrapped = static function(string $url, array $options) use ($reserveSupplier, $transport): array {
        $reserveSupplier();
        return $transport($url, $options);
    };
    return new AnyTourAndromedaClient($wrapped, true, false);
};

$client = $makeClient();
$client->ensureLogin((string)$config['username'], (string)$config['password']);
$session = $client->privateSession();
if (!is_string($session['sid'] ?? null) || !is_int($session['expires'] ?? null)) {
    throw new RuntimeException('ANDROMEDA_TOURKEY_SESSION');
}

$ownsOperator = static function(string $raw): bool {
    $value = str_replace(['Ё', 'ё'], 'е', trim($raw));
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    if ($compact === '') return false;
    foreach (['anex','анекс','pegas','пегас','coral','корал','sunmar','санмар'] as $other) {
        if (str_contains($compact, $other)) return false;
    }
    return true;
};

$groups = [];
$pages = 0;
$priceRows = 0;
$normalizedOffers = 0;
$mappedOffers = 0;
$ownedMappedOffers = 0;
$missingTourKey = 0;
$missingProgramKey = 0;
$target = 1;

for ($page = 1; $page <= $target; ++$page) {
    if ($page > AnyTourAndromedaPaginationV1::MAX_PAGES) throw new RuntimeException('ANDROMEDA_TOURKEY_PAGE_BUDGET');
    $pageCriteria = $criteria;
    $pageCriteria['PAGE'] = $page;
    if ($page === 1) {
        $pageClient = $client;
    } else {
        $pageClient = $makeClient();
        $pageClient->restorePrivateSession($session);
    }
    $payload = $pageClient->price($pageCriteria);
    ++$pages;
    $priceRows += count($payload['PRICES'] ?? []);
    if (($payload['PAGES_COUNT'] ?? null) === 0 && ($payload['PRICES'] ?? []) === []) break;

    $normalized = AnyTourAndromedaNormalizer::page($payload, $pageCriteria, $searchRef, $generation);
    $normalized = $resolver->apply($normalized);
    $normalizedOffers += count($normalized['offers'] ?? []);

    foreach (($normalized['offers'] ?? []) as $offer) {
        if (!is_array($offer)) continue;
        if (!is_int($offer['local_hotel_id'] ?? null) || $offer['local_hotel_id'] < 1) continue;
        ++$mappedOffers;
        if (!$ownsOperator((string)($offer['operator'] ?? ''))) continue;
        ++$ownedMappedOffers;

        $ctx = is_array($offer['transport_context'] ?? null) ? $offer['transport_context'] : [];
        $tour = $idText($ctx['tour_ref'] ?? null);
        $program = $idText($ctx['program_ref'] ?? null);
        $spo = $idText($ctx['spo_ref'] ?? null);
        $operator = $idText($offer['operator_ref'] ?? null);
        if ($tour === null) { ++$missingTourKey; continue; }
        if ($program === null) ++$missingProgramKey;
        if ($operator === null) continue;

        $gkey = $operator . "\0" . $tour;
        if (!isset($groups[$gkey])) {
            $groups[$gkey] = [
                'operatorKey' => $operator,
                'tourKey' => $tour,
                'programKeys' => [],
                'spoKeys' => [],
                'checkIns' => [],
                'nights' => [],
                'offer_count' => 0,
                'freight_external_true' => 0,
                'freight_external_false' => 0,
                'freight_external_unknown' => 0,
            ];
        }
        ++$groups[$gkey]['offer_count'];
        if ($program !== null) $groups[$gkey]['programKeys'][$program] = true;
        if ($spo !== null) $groups[$gkey]['spoKeys'][$spo] = true;
        $checkIn = $offer['check_in'] ?? null;
        if (is_string($checkIn) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $checkIn)) $groups[$gkey]['checkIns'][$checkIn] = true;
        $nights = $offer['nights'] ?? null;
        if (is_int($nights) && $nights > 0) $groups[$gkey]['nights'][(string)$nights] = true;
        $freight = $ctx['freight_external'] ?? null;
        if ($freight === true) ++$groups[$gkey]['freight_external_true'];
        elseif ($freight === false) ++$groups[$gkey]['freight_external_false'];
        else ++$groups[$gkey]['freight_external_unknown'];
    }

    $decision = AnyTourAndromedaPaginationV1::nextTarget(
        $page,
        (int)$normalized['pages_count'],
        count($normalized['offers']),
        (string)$normalized['status'],
        count($normalized['rejected']),
        $target
    );
    if (($decision['terminal'] ?? false) === true) break;
    $target = (int)$decision['target'];
}

$outGroups = [];
foreach ($groups as $row) {
    $programKeys = array_keys($row['programKeys']); sort($programKeys, SORT_STRING);
    $spoKeys = array_keys($row['spoKeys']); sort($spoKeys, SORT_STRING);
    $checkIns = array_keys($row['checkIns']); sort($checkIns, SORT_STRING);
    $nights = array_map('intval', array_keys($row['nights'])); sort($nights, SORT_NUMERIC);
    $outGroups[] = [
        'operatorKey' => $row['operatorKey'],
        'tourKey' => $row['tourKey'],
        'programKeys' => $programKeys,
        'spoKey_count' => count($spoKeys),
        'spoKeys' => count($spoKeys) <= 20 ? $spoKeys : array_slice($spoKeys, 0, 20),
        'spoKeys_truncated' => count($spoKeys) > 20,
        'checkIns' => $checkIns,
        'nights' => $nights,
        'offer_count' => $row['offer_count'],
        'freight_external_true' => $row['freight_external_true'],
        'freight_external_false' => $row['freight_external_false'],
        'freight_external_unknown' => $row['freight_external_unknown'],
    ];
}
usort($outGroups, static function(array $a, array $b): int {
    return $b['offer_count'] <=> $a['offer_count']
        ?: strcmp($a['operatorKey'], $b['operatorKey'])
        ?: strcmp($a['tourKey'], $b['tourKey']);
});
if (count($outGroups) > 500) throw new RuntimeException('ANDROMEDA_TOURKEY_GROUP_BUDGET');

$result = [
    'version' => 1,
    'operation' => $operation,
    'source' => $source,
    'status' => 'completed_terminal_no_replay',
    'scope' => [
        'departureId' => $departure,
        'countryId' => $country,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'nightsFrom' => $nightsFrom,
        'nightsTo' => $nightsTo,
        'adult' => $adults,
        'child' => 0,
        'meal' => '',
        'currency' => 'RUB',
    ],
    'price_pages' => $pages,
    'price_rows' => $priceRows,
    'normalized_offers' => $normalizedOffers,
    'mapped_offers' => $mappedOffers,
    'owned_mapped_offers' => $ownedMappedOffers,
    'missing_tourKey' => $missingTourKey,
    'missing_programKey' => $missingProgramKey,
    'distinct_operator_tour_keys' => count($outGroups),
    'tourKey_groups' => $outGroups,
    'package_calls' => 0,
    'get_flights_calls' => 0,
    'changeservice_calls' => 0,
    'calc_calls' => 0,
    'booking_calls' => 0,
    'db_writes' => 0,
    'autosave_calls' => 0,
    'search3_publication' => 0,
    'replay_allowed' => false,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
