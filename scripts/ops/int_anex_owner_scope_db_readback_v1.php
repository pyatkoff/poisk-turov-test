<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 2) { fwrite(STDERR, "CLI only\n"); exit(2); }
$site = realpath($argv[1]);
$runtime = realpath(dirname(__DIR__, 2));
if ($site === false || $runtime === false || basename($site) !== 'anytoour.ru') throw new RuntimeException('ANEX_SCOPE_READBACK_ROOT');
$_SERVER['DOCUMENT_ROOT'] = $site;
$data = $runtime . '/v2/data';
foreach (['db-v1.php','anytour-search-scope-v1.php','anytour-offer-scope-index-v1.php'] as $file) {
    $path = $data . '/' . $file;
    if (!is_file($path) || is_link($path)) throw new RuntimeException('ANEX_SCOPE_READBACK_RUNTIME');
    require_once $path;
}

$scope = AnyTourSearchScopeV1::fromParams([
    'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-09-18','dateTo'=>'2026-09-24',
    'nightsFrom'=>7,'nightsTo'=>10,'adults'=>2,'childs'=>[],
    'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],
    'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
    'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
]);
$db = v2_data_db();
$db->exec('SET SESSION TRANSACTION READ ONLY');
$db->exec('START TRANSACTION READ ONLY');
try {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $compatible = AnyTourOfferScopeIndexV1::compatibleDigests($db, $scope, $now, 64);
    $digests = $compatible;
    $digests[] = $scope['digest'];
    $digests = array_values(array_unique($digests));

    $scopeMeta = [];
    if ($digests !== []) {
        $ph = implode(',', array_fill(0, count($digests), '?'));
        $q = $db->prepare("SELECT scope_sha256,params_json FROM anytour_offer_scopes WHERE scope_sha256 IN ($ph)");
        $q->execute($digests);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $params = json_decode((string)$row['params_json'], true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($params)) throw new RuntimeException('ANEX_SCOPE_READBACK_META');
            $scopeMeta[(string)$row['scope_sha256']] = [
                'dateFrom'=>(string)$params['dateFrom'],'dateTo'=>(string)$params['dateTo'],
                'nightsFrom'=>(int)$params['nightsFrom'],'nightsTo'=>(int)$params['nightsTo'],
            ];
        }
    }

    $countsByProvider = [];
    $countsByNight = ['7'=>0,'8'=>0,'9'=>0,'10'=>0];
    $countsByState = [];
    $ready = 0; $verified = 0; $hotels = []; $rows = 0; $scopeRows = [];
    if ($digests !== []) {
        $ph = implode(',', array_fill(0, count($digests), '?'));
        $sql = "SELECT o.scope_sha256,o.provider,o.anytour_hotel_id,o.checkin,o.nights,o.final_price_ready,o.final_price_verified,o.payload_json "
             . "FROM anytour_offers o JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256 "
             . "WHERE o.scope_sha256 IN ($ph) AND o.last_refresh_token=s.latest_complete_refresh_token "
             . "AND s.latest_complete_refresh_token IS NOT NULL AND o.is_active=1 AND o.expires_at>CURRENT_TIMESTAMP "
             . "AND o.checkin BETWEEN '2026-09-18' AND '2026-09-24' AND o.nights BETWEEN 7 AND 10";
        $q = $db->prepare($sql); $q->execute($digests);
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $provider=(string)$row['provider']; $digest=(string)$row['scope_sha256']; $night=(string)(int)$row['nights'];
            $countsByProvider[$provider]=($countsByProvider[$provider]??0)+1;
            if (isset($countsByNight[$night])) $countsByNight[$night]++;
            $scopeRows[$digest]=($scopeRows[$digest]??0)+1;
            $hotels[(string)(int)$row['anytour_hotel_id']]=true;
            $ready += (int)$row['final_price_ready'] === 1 ? 1 : 0;
            $verified += (int)$row['final_price_verified'] === 1 ? 1 : 0;
            $payload=json_decode((string)$row['payload_json'],true,64,JSON_THROW_ON_ERROR);
            $state=is_array($payload)&&is_string($payload['listingPriceState']??null)?$payload['listingPriceState']:'missing';
            $countsByState[$state]=($countsByState[$state]??0)+1;
            $rows++;
        }
    }
    ksort($countsByProvider); ksort($countsByState); ksort($scopeRows);
    $scopeProof=[];
    foreach ($compatible as $digest) {
        $meta=$scopeMeta[$digest]??null;
        $scopeProof[]=['digest'=>substr($digest,0,12),'dateFrom'=>$meta['dateFrom']??null,'dateTo'=>$meta['dateTo']??null,'nightsFrom'=>$meta['nightsFrom']??null,'nightsTo'=>$meta['nightsTo']??null,'visibleRows'=>$scopeRows[$digest]??0];
    }
    $db->rollBack();
    echo json_encode([
        'status'=>'completed_read_only','provider'=>'anex','currentScopeDigest'=>substr($scope['digest'],0,12),
        'compatibleDigestCount'=>count($compatible),'compatibleScopes'=>$scopeProof,
        'providerOfferCounts'=>$countsByProvider,'hotelCount'=>count($hotels),'visibleRows'=>$rows,
        'nightCounts'=>$countsByNight,'finalPriceReadyCount'=>$ready,'finalPriceVerifiedCount'=>$verified,
        'listingPriceStateCounts'=>$countsByState,
        'supplier_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,
        'search3_publication'=>0,'production_webroot_writes'=>0,'replay_allowed'=>false
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
