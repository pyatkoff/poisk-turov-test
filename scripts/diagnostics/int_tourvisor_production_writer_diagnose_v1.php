<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') throw new RuntimeException('DIAG_CLI_ONLY');
$siteRoot = isset($argv[1]) ? realpath((string)$argv[1]) : false;
$ledger = isset($argv[2]) ? realpath((string)$argv[2]) : false;
if (!is_string($siteRoot) || $siteRoot === '' || !is_file($siteRoot . '/config.php')) throw new RuntimeException('DIAG_SITE_ROOT');
if (!is_string($ledger) || $ledger === '' || !is_file($ledger . '/search-results.json')) throw new RuntimeException('DIAG_LEDGER');

$sourceRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $siteRoot;
require_once $sourceRoot . '/app/integrations/tourvisor-anytour-offer-autosave.php';

$scope = [
    'departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-10-12','dateTo'=>'2026-10-12',
    'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'',
    'hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>null,
    'regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>null,'priceTo'=>null,
    'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
];
$results = json_decode((string)file_get_contents($ledger . '/search-results.json'), true, 64, JSON_THROW_ON_ERROR);
$start = json_decode((string)file_get_contents($ledger . '/search-start.json'), true, 32, JSON_THROW_ON_ERROR);
$before = is_file($ledger . '/before.json') ? json_decode((string)file_get_contents($ledger . '/before.json'), true, 16, JSON_THROW_ON_ERROR) : null;
$after = is_file($ledger . '/after.json') ? json_decode((string)file_get_contents($ledger . '/after.json'), true, 16, JSON_THROW_ON_ERROR) : null;
if (!is_array($results) || !array_is_list($results)) throw new RuntimeException('DIAG_RESULTS');
$searchId = (int)($start['searchId'] ?? $start['data']['searchId'] ?? 0);
if ($searchId < 1) throw new RuntimeException('DIAG_SEARCH_ID');

$reflect = static function(string $name): ReflectionMethod {
    $m = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, $name);
    return $m;
};
$owned = $reflect('ownedOperatorFamily');
$entryFromTour = $reflect('entryFromTour');
$firstText = $reflect('firstText');
$opaqueId = $reflect('opaqueId');
$date = $reflect('date');
$positiveInt = $reflect('positiveInt');
$moneyAmount = $reflect('moneyAmount');
$text = $reflect('text');

$totalTours = 0;
$routed = ['pegas'=>0,'coral'=>0,'sunmar'=>0];
$unknownOperator = 0;
$legacyIds = [];
$routedRows = [];
foreach ($results as $hotel) {
    if (!is_array($hotel)) continue;
    $legacy = filter_var($hotel['id'] ?? null, FILTER_VALIDATE_INT);
    $tours = $hotel['tours'] ?? null;
    if ($legacy === false || (int)$legacy < 1 || !is_array($tours) || !array_is_list($tours)) continue;
    foreach ($tours as $tour) {
        if (!is_array($tour)) continue;
        ++$totalTours;
        $operatorRaw = (string)$firstText->invoke(null, $tour, ['operatorName','operator']);
        $family = $owned->invoke(null, $operatorRaw);
        if (!is_string($family)) { ++$unknownOperator; continue; }
        ++$routed[$family];
        $legacyIds[(int)$legacy] = (int)$legacy;
        $routedRows[] = ['legacy'=>(int)$legacy, 'tour'=>$tour, 'family'=>$family];
    }
}

$db = v2_data_db();
$db->exec('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $catalog = new AnyTourCanonicalCatalog($db);
    $targets = $legacyIds === [] ? [] : $catalog->legacyTargets(array_values($legacyIds));
    $q = static function(PDO $db, string $sql): int {
        $v = $db->query($sql)->fetchColumn();
        return is_numeric($v) ? (int)$v : 0;
    };
    $dbNow = [
        'completed_refreshes'=>$q($db, "SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='completed'"),
        'running_refreshes'=>$q($db, "SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='running'"),
        'aborted_refreshes'=>$q($db, "SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='aborted'"),
        'active_offers'=>$q($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1"),
        'ready_rub'=>$q($db, "SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1 AND final_price_ready=1 AND currency='RUB'"),
        'latest_scopes'=>$q($db, "SELECT COUNT(*) FROM anytour_offer_scope_state WHERE provider='tourvisor' AND latest_complete_refresh_token IS NOT NULL"),
    ];
    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$observedAt = $now->format('Y-m-d\TH:i:s\Z');
$entries = [];
$entryNull = 0;
$entryReasons = [];
$compiledMapped = 0;
$compiledUnmapped = 0;
$dtoReady = 0;
$dtoNotReady = 0;
$dtoException = 0;
$fuel = ['present_positive'=>0,'present_zero'=>0,'missing_or_invalid'=>0];

foreach ($routedRows as $row) {
    $tour = $row['tour'];
    $legacy = $row['legacy'];
    $own = $targets[$legacy] ?? null;
    $reason = null;
    $tourId = $opaqueId->invoke(null, $tour['id'] ?? $tour['tourId'] ?? null, 220);
    $checkin = $date->invoke(null, $tour['date'] ?? $tour['checkin'] ?? null);
    $nights = $positiveInt->invoke(null, $tour['nights'] ?? null);
    $price = $moneyAmount->invoke(null, $tour['price'] ?? null, false);
    $currency = strtoupper((string)$text->invoke(null, $tour['currency'] ?? 'RUB', 8));
    $meal = (string)$firstText->invoke(null, $tour, ['mealName','mealType','meal','pansion']);
    $room = (string)$firstText->invoke(null, $tour, ['roomType','roomName','room']);
    if ($tourId === null) $reason = 'tour_id';
    elseif ($checkin === null) $reason = 'checkin';
    elseif ($nights === null || $nights > 30) $reason = 'nights';
    elseif ($price === null) $reason = 'price';
    elseif ($currency !== 'RUB') $reason = 'currency';
    elseif ($meal === '') $reason = 'meal';
    elseif ($room === '') $reason = 'room';

    if (array_key_exists('fuelCharge', $tour)) {
        $fa = $moneyAmount->invoke(null, $tour['fuelCharge'], true);
        if ($fa === '0') ++$fuel['present_zero'];
        elseif (is_string($fa)) ++$fuel['present_positive'];
        else ++$fuel['missing_or_invalid'];
    } else ++$fuel['missing_or_invalid'];

    $entry = $entryFromTour->invoke(
        null, $searchId, $legacy, is_int($own) ? $own : null, $tour,
        2, 0, [], $observedAt, $now
    );
    if (!is_array($entry)) {
        ++$entryNull;
        $reason ??= 'normalizer_or_contract';
        $entryReasons[$reason] = ($entryReasons[$reason] ?? 0) + 1;
        continue;
    }
    if (is_int($own)) ++$compiledMapped; else ++$compiledUnmapped;
    $entries[] = $entry;
    try {
        $dto = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
            $entry['offer'], $entry['retained'], $entry['current'], $now->getTimestamp(), $entry['priced_money']
        );
        if (($dto['finalPriceReady'] ?? null) === true) ++$dtoReady; else ++$dtoNotReady;
    } catch (Throwable $e) {
        ++$dtoException;
    }
}
ksort($entryReasons);

$producer = null;
if ($entries !== []) {
    try {
        $producer = AnyTourIntOfferSnapshotProducerV1::produce(
            'tourvisor', $scope,
            ['complete'=>true,'authoritative_empty'=>false,'offers'=>$entries],
            $now,
            static function(string $provider, array $params, array $rows, DateTimeImmutable $at): array {
                return ['dryRun'=>true,'provider'=>$provider,'rowCount'=>count($rows)];
            }
        );
    } catch (Throwable $e) {
        $producer = ['published'=>false,'reason'=>'exception','exception_class'=>get_class($e),'exception_code'=>$e->getMessage()];
    }
}

$out = [
    'schema_version'=>1,
    'operation'=>'int-tourvisor-production-writer-diagnose-20260917-v1',
    'status'=>'completed_read_only',
    'release_sha'=>'f61eb807193ba7eb7d692661f56f62c6f051ca7c',
    'sealed_v4_search_id'=>$searchId,
    'sealed_v4_before'=>$before,
    'sealed_v4_after'=>$after,
    'current_db'=>$dbNow,
    'result_hotels'=>count($results),
    'total_tours'=>$totalTours,
    'routed_offers'=>$routed,
    'routed_offer_count'=>array_sum($routed),
    'unowned_or_unknown_operator_count'=>$unknownOperator,
    'routed_legacy_hotels'=>count($legacyIds),
    'mapped_routed_hotels'=>count($targets),
    'unmapped_routed_hotels'=>count($legacyIds)-count($targets),
    'entry_compiled'=>count($entries),
    'entry_null'=>$entryNull,
    'entry_null_reasons'=>$entryReasons,
    'compiled_mapped'=>$compiledMapped,
    'compiled_unmapped'=>$compiledUnmapped,
    'fuel'=>$fuel,
    'dto_ready'=>$dtoReady,
    'dto_not_ready'=>$dtoNotReady,
    'dto_exception'=>$dtoException,
    'producer_dry_run'=>$producer,
    'supplier_calls'=>0,
    'db_writes'=>0,
    'public_writes'=>0,
];
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
