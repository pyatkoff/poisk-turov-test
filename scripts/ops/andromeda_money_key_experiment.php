<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) throw new InvalidArgumentException('ANDROMEDA_MONEY_EXPERIMENT_ARG');
    [$k, $v] = explode('=', substr($arg, 2), 2);
    $args[$k] = $v;
}
$get = static function(string $key) use ($args): string {
    $v = $args[$key] ?? null;
    if (!is_string($v) || $v === '') throw new InvalidArgumentException('ANDROMEDA_MONEY_EXPERIMENT_ARG_' . $key);
    return $v;
};
$int = static function(string $value, int $min, int $max): int {
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $value)) throw new InvalidArgumentException('ANDROMEDA_MONEY_EXPERIMENT_INT');
    $n = (int)$value;
    if ($n < $min || $n > $max) throw new InvalidArgumentException('ANDROMEDA_MONEY_EXPERIMENT_INT');
    return $n;
};
$date = static function(string $value): string {
    if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $value, $m)
        || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) throw new InvalidArgumentException('ANDROMEDA_MONEY_EXPERIMENT_DATE');
    return $value;
};

$site = realpath($get('site-root'));
$privateConfig = realpath($get('private-config'));
$source = $get('source-sha');
$operation = $get('operation-id');
$checkpoint = $get('checkpoint');
if ($site === false || basename($site) !== 'anytoour.ru' || $privateConfig === false || !is_file($privateConfig)
    || !preg_match('/\A[a-f0-9]{40}\z/D', $source)
    || !preg_match('/\A[a-z0-9][a-z0-9._-]{20,160}\z/D', $operation)
    || !str_starts_with($checkpoint, rtrim((string)getenv('HOME'), '/') . '/.anytour-ops/')) {
    throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_ROOT');
}

$runtime = dirname(__DIR__, 2);
require_once $runtime . '/v2/api-andromeda-search3-preview.php';
require_once $runtime . '/app/integrations/andromeda-operator-config.php';
require_once $runtime . '/app/integrations/andromeda-claim-actions.php';

$config = require $privateConfig;
if (!is_array($config) || ($config['enabled'] ?? null) !== true) throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_CONFIG');
require_once $site . '/config.php';
$dbFile = is_file($site . '/data/db-v1.php') ? $site . '/data/db-v1.php' : $site . '/v2/data/db-v1.php';
require_once $dbFile;
$pdo = v2_data_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$departure = $int($args['departure'] ?? '1', 1, 999999999);
$country = $int($args['country'] ?? '4', 1, 999999999);
$checkIn = $date($args['date'] ?? '2026-11-01');
$nightsFrom = $int($args['nights-from'] ?? '7', 1, 28);
$nightsTo = $int($args['nights-to'] ?? '10', $nightsFrom, 28);
$adults = $int($args['adults'] ?? '2', 1, 6);
$meal = $args['meal'] ?? '7';
$generation = $int($args['generation'] ?? '17171844', 1, 2147483647);
$maxSpecimens = $int($args['max-specimens'] ?? '12', 1, 12);

$request = [
    'generation' => $generation,
    'params' => [
        'departureId' => (string)$departure,
        'countryId' => (string)$country,
        'dateFrom' => $checkIn,
        'dateTo' => $checkIn,
        'nightsFrom' => $nightsFrom,
        'nightsTo' => $nightsTo,
        'adults' => $adults,
        'childs' => [],
        'meal' => $meal,
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
$searchRef = hash('sha256', 'money-key-v1|' . $operation . '|' . json_encode($criteria, JSON_THROW_ON_ERROR));

$lookup = $pdo->prepare("SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id,i.decision_status FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status='accepted' AND h.is_active=1 AND h.country_id=? ORDER BY i.external_hotel_id");
$lookup->execute([$country]);
$identities = $lookup->fetchAll(PDO::FETCH_ASSOC);
$resolver = AnyTourAndromedaHotelResolver::fromRows($identities, hash('sha256', json_encode($identities, JSON_THROW_ON_ERROR)));
unset($identities);

$monthlyDirectory = dirname((string)$config['catalog_path']);
$lastSupplierStarted = 0.0;
$reserveSupplier = static function() use (&$lastSupplierStarted, $monthlyDirectory): void {
    $wait = 1.05 - (microtime(true) - $lastSupplierStarted);
    if ($wait > 0) usleep((int)ceil($wait * 1000000));
    anytour_andromeda_search3_budget($monthlyDirectory);
    $lastSupplierStarted = microtime(true);
};

$makeClient = static function(bool $allowPrice, bool $allowPackage, ?string $operatorLogin = null, ?string $operatorPassword = null) use ($reserveSupplier): AnyTourAndromedaClient {
    $transport = new AnyTourAndromedaTransport($allowPrice, $allowPackage);
    $wrapped = static function(string $url, array $options) use ($reserveSupplier, $transport): array {
        $reserveSupplier();
        return $transport($url, $options);
    };
    return new AnyTourAndromedaClient($wrapped, true, $allowPackage, $operatorLogin, $operatorPassword);
};

$client = $makeClient(true, false);
$client->ensureLogin((string)$config['username'], (string)$config['password']);
$session = $client->privateSession();
if (!is_string($session['sid'] ?? null) || !is_int($session['expires'] ?? null)) throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_SESSION');

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
$idText = static function(mixed $value): ?string {
    if (is_int($value)) $value = (string)$value;
    return is_string($value) && preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $value) ? $value : null;
};

$candidates = [];
$pages = 0;
$priceRows = 0;
$mappedRows = 0;
$target = 1;
$page = 1;
while ($page <= $target) {
    $pageCriteria = $criteria;
    $pageCriteria['PAGE'] = $page;
    if ($page === 1) {
        $pageClient = $client;
    } else {
        $pageClient = $makeClient(true, false);
        $pageClient->restorePrivateSession($session);
    }
    $payload = $pageClient->price($pageCriteria);
    ++$pages;
    $priceRows += count($payload['PRICES']);

    if (($payload['PAGES_COUNT'] ?? null) === 0 && ($payload['PRICES'] ?? []) === []) break;
    $normalized = AnyTourAndromedaNormalizer::page($payload, $pageCriteria, $searchRef, $generation);
    $normalized = $resolver->apply($normalized);
    $byRef = [];
    foreach ($payload['PRICES'] as $raw) {
        if (!is_array($raw)) continue;
        $rawId = $raw['id'] ?? null;
        $operatorKey = $idText($raw['operatorKey'] ?? null);
        if ((!is_string($rawId) && !is_int($rawId)) || $operatorKey === null) continue;
        $rawId = (string)$rawId;
        if ($rawId === '' || strlen($rawId) > 2048) continue;
        $offerRef = 'offer_' . hash('sha256', json_encode([$searchRef, $generation, $operatorKey, $rawId], JSON_THROW_ON_ERROR));
        $byRef[$offerRef] = $rawId;
    }
    foreach ($normalized['offers'] as $offer) {
        if (!is_array($offer) || !is_int($offer['local_hotel_id'] ?? null) || $offer['local_hotel_id'] < 1) continue;
        ++$mappedRows;
        if (!$ownsOperator((string)($offer['operator'] ?? ''))) continue;
        $ctx = is_array($offer['transport_context'] ?? null) ? $offer['transport_context'] : [];
        if (($ctx['freight_external'] ?? null) !== true) continue;
        $program = $idText($ctx['program_ref'] ?? null);
        if ($program === null) continue;
        $offerRef = $offer['offer_ref'] ?? null;
        if (!is_string($offerRef) || !isset($byRef[$offerRef])) continue;
        $tour = $idText($ctx['tour_ref'] ?? null);
        $spo = $idText($ctx['spo_ref'] ?? null);
        $operator = $idText($offer['operator_ref'] ?? null);
        if ($operator === null) continue;
        $candidates[] = [
            'offer_ref' => $offerRef,
            'supplier_offer_id' => $byRef[$offerRef],
            'identity' => [
                'operatorKey' => $operator,
                'programKey' => $program,
                'tourKey' => $tour,
                'spoKey' => $spo,
                'checkIn' => (string)$offer['check_in'],
                'nights' => (int)$offer['nights'],
                'adult' => (int)$offer['adults'],
                'child' => (int)$offer['children'],
                'freightExternal' => true,
            ],
            'features' => [
                'hotel' => (int)$offer['local_hotel_id'],
                'room' => hash('sha256', (string)($offer['room_raw'] ?? $offer['room'] ?? '')),
                'meal' => hash('sha256', (string)($offer['meal']['raw_label'] ?? $offer['meal']['label'] ?? '')),
            ],
        ];
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
    ++$page;
    if ($page > AnyTourAndromedaPaginationV1::MAX_PAGES) throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_PAGE_BUDGET');
}

usort($candidates, static fn(array $a, array $b): int => strcmp(hash('sha256', $a['offer_ref']), hash('sha256', $b['offer_ref'])));

$selected = [];
$selectedBasis = [];
$add = static function(array $candidate, string $basis) use (&$selected, &$selectedBasis, $maxSpecimens): void {
    if (count($selected) >= $maxSpecimens) return;
    $ref = $candidate['offer_ref'];
    if (!isset($selected[$ref])) $selected[$ref] = $candidate;
    $selectedBasis[$ref] ??= [];
    if (!in_array($basis, $selectedBasis[$ref], true)) $selectedBasis[$ref][] = $basis;
};
$groupKey = static function(array $identity, array $fields): string {
    $v = [];
    foreach ($fields as $field) $v[] = $identity[$field] ?? null;
    return hash('sha256', json_encode($v, JSON_THROW_ON_ERROR));
};
$pickVariation = static function(array $controls, string $vary, string $basis, int $perGroup = 4) use ($candidates, $groupKey, $add, &$selected, $maxSpecimens): void {
    $groups = [];
    foreach ($candidates as $candidate) {
        $key = $groupKey($candidate['identity'], $controls);
        $value = json_encode($candidate['identity'][$vary] ?? null, JSON_THROW_ON_ERROR);
        $groups[$key][$value][] = $candidate;
    }
    $ranked = [];
    foreach ($groups as $key => $values) {
        if (count($values) < 2) continue;
        $ranked[] = ['key' => $key, 'values' => $values, 'count' => count($values)];
    }
    usort($ranked, static fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['key'], $b['key']));
    foreach ($ranked as $group) {
        if (count($selected) >= $maxSpecimens) break;
        $used = 0;
        ksort($group['values'], SORT_STRING);
        foreach ($group['values'] as $rows) {
            if ($used >= $perGroup || count($selected) >= $maxSpecimens) break;
            $add($rows[0], $basis);
            ++$used;
        }
    }
};

// Prefer isolated comparisons, then a broader same-program/date cross-night matrix.
$pickVariation(['operatorKey','programKey','tourKey','spoKey','checkIn','adult','child'], 'nights', 'isolated_nights');
$pickVariation(['operatorKey','programKey','spoKey','checkIn','nights','adult','child'], 'tourKey', 'isolated_tour');
$pickVariation(['operatorKey','programKey','tourKey','checkIn','nights','adult','child'], 'spoKey', 'isolated_spo');
$pickVariation(['operatorKey','programKey','checkIn','adult','child'], 'nights', 'program_date_nights');
if (count($selected) < $maxSpecimens) {
    $seenPrograms = [];
    foreach ($candidates as $candidate) {
        if (count($selected) >= $maxSpecimens) break;
        $key = $candidate['identity']['operatorKey'] . ':' . $candidate['identity']['programKey'];
        if (isset($seenPrograms[$key])) continue;
        $seenPrograms[$key] = true;
        $add($candidate, 'program_coverage');
    }
}
if ($selected === []) throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_NO_SPECIMENS');

$featureDiversity = ['hotel' => [], 'room' => [], 'meal' => []];
foreach ($selected as $candidate) {
    foreach ($featureDiversity as $field => $_) $featureDiversity[$field][(string)$candidate['features'][$field]] = true;
}
$featureDiversity = array_map('count', $featureDiversity);

[$operatorLogin, $operatorPassword] = anytour_andromeda_operator_credentials_from_config($config);
$canonicalMoney = static function(mixed $value): ?string {
    if (is_int($value)) $value = (string)$value;
    if (is_float($value) && is_finite($value)) $value = number_format($value, 2, '.', '');
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)) return null;
    if (str_contains($value, '.')) {
        $value = rtrim(rtrim($value, '0'), '.');
        if ($value === '') $value = '0';
    }
    return $value;
};
$extractMarkup = static function(array $claim) use ($canonicalMoney): array {
    $facts = [];
    foreach (($claim['variants'] ?? []) as $variant) {
        if (!is_array($variant)) continue;
        foreach (($variant['transports'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
            foreach ($block['transport'] as $transport) {
                if (!is_array($transport) || ($transport['type'] ?? null) !== 'ttAvia') continue;
                foreach (($transport['details'] ?? []) as $detailBlock) {
                    if (!is_array($detailBlock) || !is_array($detailBlock['detail'] ?? null)) continue;
                    foreach ($detailBlock['detail'] as $detail) {
                        if (!is_array($detail)) continue;
                        $amount = $canonicalMoney($detail['markup'] ?? null);
                        $currency = $detail['currency'] ?? null;
                        if ($amount === null || !is_string($currency) || !preg_match('/\A[A-Z0-9_]{2,8}\z/D', $currency)) continue;
                        $facts[$currency . "\0" . $amount] = ['amount' => $amount, 'currency' => $currency];
                    }
                }
            }
        }
    }
    ksort($facts, SORT_STRING);
    if (count($facts) > 128) throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_MARKUP_BUDGET');
    return array_values($facts);
};
$safeCode = static function(Throwable $error): string {
    $message = $error->getMessage();
    return is_string($message) && preg_match('/\A[A-Z0-9_:-]{1,96}\z/D', $message)
        ? $message : 'ANDROMEDA_MONEY_EXPERIMENT_FAILURE';
};
$saveCheckpoint = static function(array $state) use ($checkpoint): void {
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (strlen($json) > 262144) throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_CHECKPOINT_BUDGET');
    $tmp = $checkpoint . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json) || !rename($tmp, $checkpoint)) {
        throw new RuntimeException('ANDROMEDA_MONEY_EXPERIMENT_CHECKPOINT');
    }
};

$results = [];
$progress = [
    'version' => 1,
    'operation' => $operation,
    'source' => $source,
    'status' => 'running',
    'scope' => ['departureId'=>$departure,'countryId'=>$country,'checkIn'=>$checkIn,'nightsFrom'=>$nightsFrom,'nightsTo'=>$nightsTo,'adult'=>$adults,'child'=>0,'meal'=>$meal,'currency'=>'RUB'],
    'price_pages' => $pages,
    'price_rows' => $priceRows,
    'mapped_rows' => $mappedRows,
    'external_program_candidates' => count($candidates),
    'selected_count' => count($selected),
    'selected_feature_diversity_counts' => $featureDiversity,
    'specimens' => [],
    'calc_calls' => 0,
    'changeservice_calls' => 0,
    'booking_calls' => 0,
    'db_writes' => 0,
    'autosave_calls' => 0,
    'search3_publication' => 0,
    'replay_allowed' => false,
];
$saveCheckpoint($progress);

foreach ($selected as $ref => $candidate) {
    $identity = $candidate['identity'];
    $digest = hash('sha256', $operation . '|' . $ref);
    $entry = [
        'specimen_digest' => $digest,
        'selection_basis' => $selectedBasis[$ref] ?? [],
        'price_identity' => $identity,
        'status' => 'reserved_terminal_no_replay',
        'markup' => [],
    ];
    $results[] = $entry;
    $progress['specimens'] = $results;
    $saveCheckpoint($progress); // durable terminal reservation before supplier access
    $idx = array_key_last($results);
    try {
        $packageClient = $makeClient(false, true, $operatorLogin, $operatorPassword);
        $packageClient->restorePrivateSession($session);
        $package = $packageClient->package($candidate['supplier_offer_id']);
        $doc = is_array($package['claimDocument'] ?? null) && array_keys($package['claimDocument']) === [0]
            && is_array($package['claimDocument'][0]) ? $package['claimDocument'][0] : null;
        if (!is_array($doc)) throw new RuntimeException('ANDROMEDA_CLAIM_SHAPE_INVALID');
        $freight = $doc['freightExternal'] ?? null;
        if ((string)$freight === '0') {
            $results[$idx]['status'] = 'package_non_external_terminal_no_replay';
            unset($package, $doc);
            $progress['specimens'] = $results;
            $saveCheckpoint($progress);
            continue;
        }
        $actions = new AnyTourAndromedaClaimActions($session['sid'], $reserveSupplier);
        $flights = $actions->getFlights($package);
        $facts = $extractMarkup($flights);
        $results[$idx]['status'] = $facts === [] ? 'completed_no_markup' : 'completed';
        $results[$idx]['markup'] = $facts;
        unset($package, $doc, $flights, $actions);
    } catch (Throwable $error) {
        $results[$idx]['status'] = 'failed_terminal_no_replay';
        $results[$idx]['failure_class'] = $safeCode($error);
    }
    $progress['specimens'] = $results;
    $saveCheckpoint($progress);
}

$usable = array_values(array_filter($results, static fn(array $row): bool => ($row['status'] ?? null) === 'completed' && ($row['markup'] ?? []) !== []));
$signature = static fn(array $row): string => hash('sha256', json_encode($row['markup'], JSON_THROW_ON_ERROR));
$compare = static function(string $vary, array $controls) use ($usable, $signature): array {
    $same = 0; $different = 0; $pairs = 0;
    $n = count($usable);
    for ($i = 0; $i < $n; ++$i) {
        for ($j = $i + 1; $j < $n; ++$j) {
            $a = $usable[$i]['price_identity']; $b = $usable[$j]['price_identity'];
            if (($a[$vary] ?? null) === ($b[$vary] ?? null)) continue;
            $controlled = true;
            foreach ($controls as $field) if (($a[$field] ?? null) !== ($b[$field] ?? null)) { $controlled = false; break; }
            if (!$controlled) continue;
            ++$pairs;
            if ($signature($usable[$i]) === $signature($usable[$j])) ++$same; else ++$different;
        }
    }
    $decision = $different > 0 ? 'partition_observed_keep'
        : ($same > 0 ? 'invariant_in_observed_pairs_drop_supported' : 'not_isolated_keep');
    return ['isolated_pairs'=>$pairs,'same_markup_set_pairs'=>$same,'different_markup_set_pairs'=>$different,'decision'=>$decision];
};

$comparisons = [
    'nights' => $compare('nights', ['operatorKey','programKey','tourKey','spoKey','checkIn','adult','child']),
    'tourKey' => $compare('tourKey', ['operatorKey','programKey','spoKey','checkIn','nights','adult','child']),
    'spoKey' => $compare('spoKey', ['operatorKey','programKey','tourKey','checkIn','nights','adult','child']),
];
$recommended = ['operatorKey','programKey','departureId','countryId','checkIn','adult','child','currency'];
foreach (['nights','tourKey','spoKey'] as $field) {
    if (($comparisons[$field]['decision'] ?? '') !== 'invariant_in_observed_pairs_drop_supported') $recommended[] = $field;
}

$progress['status'] = 'completed_terminal_no_replay';
$progress['usable_specimens'] = count($usable);
$progress['comparisons'] = $comparisons;
$progress['untested_context_policy'] = [
    'departureId' => 'keep_not_varied',
    'countryId' => 'keep_not_varied',
    'checkIn' => 'keep_single_date_experiment',
    'adult' => 'keep_fixed_party_experiment',
    'child' => 'keep_fixed_party_experiment',
    'currency' => 'keep_fixed_currency_experiment',
    'operatorKey' => 'keep_supplier_operator_identity',
    'programKey' => 'keep_transport_program_identity',
];
$progress['recommended_group_key_fields_after_this_evidence'] = $recommended;
$progress['specimens'] = $results;
$saveCheckpoint($progress);
echo json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
