<?php
declare(strict_types=1);

define('ANYTOUR_OFFER_PRICE_HISTORY_READ_LIBRARY_ONLY', true);
require_once __DIR__ . '/../v2/data/anytour-offer-price-history-read-v1.php';

function history_read_check(bool $ok,string $label):void
{
    if(!$ok){
        fwrite(STDERR,"ANYTOUR_OFFER_PRICE_HISTORY_READ_FAILED {$label}\n");
        exit(1);
    }
}

function history_read_sql(PDO $db,string $path):void
{
    $sql=file_get_contents($path);
    if(!is_string($sql)||$sql==='')throw new RuntimeException('EMPTY_SQL:'.$path);
    $sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement){
        $statement=trim($statement);
        if($statement!=='')$db->exec($statement);
    }
}

function history_read_params():array
{
    return [
        'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-05',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],
        'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],
        'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
        'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
    ];
}

function history_read_dto(
    string $provider,
    int $providerHotelId,
    string $operator,
    string $offerSalt,
    string $searchSalt,
    string $price,
    string $observedAt
):array{
    return [
        'provider'=>$provider,
        'operator'=>[
            'raw'=>$operator,
            'canonical_name'=>$operator,
            'canonical_verified'=>false,
            'identity_source'=>'test',
            'filter_status'=>'unknown',
            'cross_provider_equivalence_verified'=>false,
            'supplier_code_exposed'=>false,
        ],
        'local_hotel_id'=>$providerHotelId,
        'identity'=>[
            'search_ref_digest'=>hash('sha256',$provider.':search:'.$searchSalt),
            'offer_ref_digest'=>hash('sha256',$provider.':offer:'.$offerSalt),
            'provider_hotel_ref_digest'=>hash('sha256',$provider.':hotel:'.$providerHotelId),
        ],
        'tour'=>[
            'checkin'=>'2026-10-05',
            'nights'=>7,
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            'meal'=>[
                'raw'=>'All Inclusive',
                'family'=>'ai',
                'qualifiers'=>['plus'=>false,'without_alcohol'=>false],
                'family_verified'=>true,
            ],
            'room'=>['raw'=>'STANDARD','normalized'=>'standard'],
            'placement'=>null,
            'availability'=>[],
            'flight_details'=>[],
            'observed_at'=>$observedAt,
        ],
        'price'=>$price,
        'currency'=>'RUB',
        'finalPriceReady'=>true,
        'final_price_verified'=>false,
    ];
}

$dsn=trim((string)getenv('ANYTOUR_OFFER_HISTORY_READ_TEST_DSN'));
if($dsn===''){
    fwrite(STDERR,"ANYTOUR_OFFER_PRICE_HISTORY_READ_FAILED missing_test_dsn\n");
    exit(1);
}
$db=new PDO(
    $dsn,
    (string)getenv('ANYTOUR_OFFER_HISTORY_READ_TEST_USER'),
    (string)getenv('ANYTOUR_OFFER_HISTORY_READ_TEST_PASSWORD'),
    [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_STRINGIFY_FETCHES=>false,
    ]
);
$db->exec('DROP TABLE IF EXISTS anytour_offer_price_observations');
$db->exec('DROP TABLE IF EXISTS anytour_hotels');
$db->exec('CREATE TABLE anytour_hotels (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('INSERT INTO anytour_hotels(id) VALUES (42)');
history_read_sql($db,__DIR__.'/../v2/data/migrations/20260923-anytour-offer-price-history-v1.sql');

$params=history_read_params();
$recorded=new DateTimeImmutable('2026-09-23T12:00:00Z');
$anexOld=history_read_dto('anex',7001,'ANEX','same','old','240000','2026-09-21T10:00:00Z');
$anexCurrent=history_read_dto('anex',7001,'ANEX','same','current','195000','2026-09-23T10:00:00Z');
$andromedaExpensive=history_read_dto('andromeda',315001,'FUN&SUN','other','other','300000','2026-09-21T11:00:00Z');

$write=AnyTourOfferPriceHistoryV1::recordIfInstalled(
    $db,
    $params,
    [
        ['anytour_hotel_id'=>42,'dto'=>$anexOld],
        ['anytour_hotel_id'=>42,'dto'=>$andromedaExpensive],
        ['anytour_hotel_id'=>42,'dto'=>$anexCurrent],
    ],
    $recorded
);
history_read_check(($write['written']??-1)===3,'fixture writes');

$offerRef=hash('sha256','anex:offer:same');
$result=AnyTourOfferPriceHistoryReadV1::read(
    $db,'anex',$offerRef,'195000',30,new DateTimeImmutable('2026-09-23T12:00:00Z')
);
history_read_check(($result['ok']??false)===true,'read ok');
history_read_check(($result['installed']??false)===true,'installed');
history_read_check(($result['historyAvailable']??false)===true,'history available');
history_read_check(($result['currentPriceMatchesLatest']??false)===true,'current price matches');
history_read_check(($result['latestHistoryPrice']??'')==='195000','latest price');
history_read_check(($result['anytourHotelId']??0)===42,'canonical hotel');

$exact=$result['exactOffer']??[];
history_read_check(($exact['comparisonMode']??'')==='exact_offer','exact mode');
history_read_check((float)($exact['referencePrice']??0)===240000.0,'exact reference old same offer');
history_read_check(($exact['historicalDropPercent']??0)===19,'exact drop percent');
history_read_check(($exact['showPromoDrop']??false)===true,'exact drop visible');
history_read_check(($exact['referenceMethod']??'')==='max_observed_price_exact_offer','exact reference method');

$consumer=$result['consumerEquivalent']??[];
history_read_check(($consumer['comparisonMode']??'')==='consumer_equivalent_operator_independent','consumer mode');
history_read_check((float)($consumer['referencePrice']??0)===240000.0,'expensive alternate provider cannot inflate reference');
history_read_check(($consumer['historicalDropPercent']??0)===19,'consumer drop percent');
history_read_check(($consumer['showPromoDrop']??false)===true,'consumer drop visible');
history_read_check(($consumer['referenceMethod']??'')==='max_daily_best_price_consumer_comparable_segment','consumer reference method');

$mismatch=AnyTourOfferPriceHistoryReadV1::read(
    $db,'anex',$offerRef,'190000',30,new DateTimeImmutable('2026-09-23T12:00:00Z')
);
history_read_check(($mismatch['currentPriceMatchesLatest']??true)===false,'mismatch detected');
history_read_check(($mismatch['exactOffer']['showPromoDrop']??true)===false,'exact mismatch suppresses promo');
history_read_check(($mismatch['consumerEquivalent']['showPromoDrop']??true)===false,'consumer mismatch suppresses promo');
history_read_check(($mismatch['exactOffer']['claimSuppressedReason']??'')==='current_price_mismatch','exact suppression reason');
history_read_check(($mismatch['consumerEquivalent']['claimSuppressedReason']??'')==='current_price_mismatch','consumer suppression reason');

$missing=AnyTourOfferPriceHistoryReadV1::read(
    $db,'anex',hash('sha256','anex:offer:missing'),'100000',30,new DateTimeImmutable('2026-09-23T12:00:00Z')
);
history_read_check(($missing['installed']??false)===true&&($missing['historyAvailable']??true)===false,'missing offer is clean empty');

$db->exec('DROP TABLE anytour_offer_price_observations');
$absent=AnyTourOfferPriceHistoryReadV1::read(
    $db,'anex',$offerRef,'195000',30,new DateTimeImmutable('2026-09-23T12:00:00Z')
);
history_read_check(($absent['ok']??false)===true,'absent table read ok');
history_read_check(($absent['installed']??true)===false,'absent table installed false');
history_read_check(($absent['historyAvailable']??true)===false,'absent table history unavailable');

$source=(string)file_get_contents(__DIR__.'/../v2/data/anytour-offer-price-history-read-v1.php');
history_read_check(!preg_match('/\b(?:INSERT|UPDATE|DELETE|ALTER|CREATE|REPLACE)\s+/i',$source),'reader source has no mutations');
history_read_check(!preg_match('/\b(?:curl_|file_get_contents\s*\(\s*[\'\"]https?:|fsockopen|stream_socket_client)\b/i',$source),'reader source has no supplier transport');

echo "ANYTOUR_OFFER_PRICE_HISTORY_READ_OK exact_drop=19 consumer_drop=19 expensive_alt_ignored=300000 mismatch=suppressed absent=clean\n";
