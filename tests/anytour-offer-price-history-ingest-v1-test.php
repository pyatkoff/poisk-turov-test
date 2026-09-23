<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/anytour-offer-snapshot-ingest-v1.php';
require_once __DIR__ . '/../v2/data/anytour-offer-store-read-v2.php';

function history_ingest_check(bool $ok, string $label): void
{
    if (!$ok) {
        fwrite(STDERR, "ANYTOUR_OFFER_PRICE_HISTORY_INGEST_FAILED {$label}\n");
        exit(1);
    }
}

function history_ingest_sql(PDO $db, string $path): void
{
    $sql = file_get_contents($path);
    if (!is_string($sql) || $sql === '') throw new RuntimeException('EMPTY_SQL:' . $path);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $db->exec($statement);
    }
}

function history_ingest_params(): array
{
    return [
        'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-05',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],
        'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],
        'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
        'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
    ];
}

function history_ingest_dto(
    string $salt,
    string $price,
    int $issued,
    string $observedAt
): array {
    return [
        'schema_version'=>1,
        'provider'=>'anex',
        'operator'=>[
            'raw'=>'ANEX',
            'canonical_name'=>'ANEX',
            'canonical_verified'=>true,
            'identity_source'=>'provider_fixed',
            'filter_status'=>'unsupported',
            'cross_provider_equivalence_verified'=>false,
            'supplier_code_exposed'=>false,
        ],
        'local_hotel_id'=>101,
        'identity'=>[
            'search_ref_digest'=>hash('sha256','search:anex:fixed'),
            'offer_ref_digest'=>hash('sha256','offer:anex:'.$salt),
            'provider_hotel_ref_digest'=>hash('sha256','hotel:anex:101'),
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
            'placement'=>['raw'=>'2AD'],
            'availability'=>['hotel'=>['raw'=>'available']],
            'flight_details'=>['state'=>'search_summary_only'],
            'observed_at'=>$observedAt,
        ],
        'money'=>['search_price_with_surcharge'=>['amount'=>$price,'currency'=>'RUB']],
        'quote_state'=>'unknown',
        'final_price_verified'=>false,
        'quote_evidence_digest'=>null,
        'context'=>[
            'generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,
            'current_context_verified'=>true,
        ],
        'selection_state'=>'disabled',
        'booking_enabled'=>false,
        'finalPriceReady'=>true,
        'finalPrice'=>$price,
        'price'=>$price,
        'currency'=>'RUB',
    ];
}

$dsn=trim((string)getenv('ANYTOUR_OFFER_HISTORY_INGEST_TEST_DSN'));
if($dsn===''){
    fwrite(STDERR,"ANYTOUR_OFFER_PRICE_HISTORY_INGEST_FAILED missing_test_dsn\n");
    exit(1);
}
$db=new PDO(
    $dsn,
    (string)getenv('ANYTOUR_OFFER_HISTORY_INGEST_TEST_USER'),
    (string)getenv('ANYTOUR_OFFER_HISTORY_INGEST_TEST_PASSWORD'),
    [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_STRINGIFY_FETCHES=>false,
    ]
);

foreach([
    'anytour_offer_price_observations','anytour_offers','anytour_offer_scope_state',
    'anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources',
    'anytour_hotels','anytour_catalog_control'
] as $table){
    $db->exec('DROP TABLE IF EXISTS '.$table);
}
history_ingest_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
history_ingest_sql($db,__DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');
history_ingest_sql($db,__DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
history_ingest_sql($db,__DIR__.'/../v2/data/migrations/20260923-anytour-offer-price-history-v1.sql');

$when='2026-09-23 10:00:00';
$profile=json_encode(['name'=>'History Hotel'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insertHotel=$db->prepare(
    'INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) '
    .'VALUES(:json,:sha,1,1,:created,:updated)'
);
$insertHotel->execute(['json'=>$profile,'sha'=>hash('sha256',$profile),'created'=>$when,'updated'=>$when]);
$own=(int)$db->lastInsertId();

$source=json_encode(['fixture'=>true],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$sourceSha=hash('sha256',$source);
$insertSource=$db->prepare(
    "INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) "
    ."VALUES(:namespace,:external,:own,:via,:json,:sha,:first_seen,:last_seen)"
);
$insertSource->execute([
    'namespace'=>'legacy_catalog','external'=>'101','own'=>$own,'via'=>'test',
    'json'=>$source,'sha'=>$sourceSha,'first_seen'=>$when,'last_seen'=>$when,
]);
$alias=[
    'accepted_local_hotel_id'=>101,
    'canonical_hotel_id'=>$own,
    'derived_from_namespace'=>'legacy_catalog',
    'derived_from_source_sha256'=>$sourceSha,
    'schema_version'=>1,
];
ksort($alias,SORT_STRING);
$aliasJson=json_encode($alias,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$insertSource->execute([
    'namespace'=>'anytour_local_id','external'=>'101','own'=>$own,'via'=>'canonical_local_alias_v1',
    'json'=>$aliasJson,'sha'=>hash('sha256',$aliasJson),'first_seen'=>$when,'last_seen'=>$when,
]);

$params=history_ingest_params();
$now=new DateTimeImmutable('2026-09-23T10:00:00Z');
$issued=$now->getTimestamp();
$scope=AnyTourSearchScopeV1::fromParams($params)['digest'];

$row=[
    'anytour_hotel_id'=>$own,
    'dto'=>history_ingest_dto('same-offer','240000',$issued,'2026-09-23T09:59:00Z'),
    'expires_at'=>'2026-09-23T10:15:00Z',
];
$first=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$row],$now);
history_ingest_check(($first['selectionAuthority']??null)===false,'selection authority remains false');
history_ingest_check(($first['priceHistory']['installed']??null)===true,'history installed');
history_ingest_check(($first['priceHistory']['written']??-1)===1,'first history observation written');
history_ingest_check(($first['priceHistory']['duplicates']??-1)===0,'first history observation not duplicate');
history_ingest_check((int)$db->query('SELECT COUNT(*) FROM anytour_offer_price_observations')->fetchColumn()===1,'history row count one');

$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now);
history_ingest_check(count($visible['items'])===1&&$visible['items'][0]['price']==='240000','current offer visible');

$replay=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$row],$now->modify('+1 minute'));
history_ingest_check(($replay['priceHistory']['written']??-1)===0,'replay writes no history');
history_ingest_check(($replay['priceHistory']['duplicates']??-1)===1,'replay reported duplicate');
history_ingest_check((int)$db->query('SELECT COUNT(*) FROM anytour_offer_price_observations')->fetchColumn()===1,'replay history row count stable');

$changed=$row;
$changed['dto']=history_ingest_dto('same-offer','195000',$issued+120,'2026-09-23T10:02:00Z');
$changed['expires_at']='2026-09-23T10:17:00Z';
$partial=AnyTourOfferSnapshotIngestV1::mergePartialSnapshot($db,'anex',$params,[$changed],$now->modify('+2 minutes'));
history_ingest_check(($partial['snapshotMode']??'')==='partial_additive','partial mode');
history_ingest_check(($partial['priceHistory']['written']??-1)===1,'changed price appended');
history_ingest_check((int)$db->query('SELECT COUNT(*) FROM anytour_offer_price_observations')->fetchColumn()===2,'two history observations');

$offerRef=hash('sha256','offer:anex:same-offer');
$q=$db->prepare("SELECT display_price FROM anytour_offer_price_observations WHERE provider='anex' AND offer_ref_digest=:offer ORDER BY observed_at");
$q->execute(['offer'=>$offerRef]);
history_ingest_check(array_map('floatval',$q->fetchAll(PDO::FETCH_COLUMN))===[240000.0,195000.0],'exact offer price series');

$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+2 minutes'));
history_ingest_check(count($visible['items'])===1&&$visible['items'][0]['price']==='195000','partial current offer updated');

$db->exec('DROP TABLE anytour_offer_price_observations');
$withoutTable=$changed;
$withoutTable['dto']=history_ingest_dto('same-offer','190000',$issued+180,'2026-09-23T10:03:00Z');
$withoutTable['expires_at']='2026-09-23T10:18:00Z';
$noHistory=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$withoutTable],$now->modify('+3 minutes'));
history_ingest_check(($noHistory['priceHistory']['installed']??null)===false,'absent history table is no-op');
history_ingest_check(($noHistory['priceHistory']['written']??-1)===0,'absent history writes zero');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+3 minutes'));
history_ingest_check(count($visible['items'])===1&&$visible['items'][0]['price']==='190000','offer store unaffected by absent history');

$db->exec('CREATE TABLE anytour_offer_price_observations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
$brokenHistory=$withoutTable;
$brokenHistory['dto']=history_ingest_dto('same-offer','185000',$issued+240,'2026-09-23T10:04:00Z');
$brokenHistory['expires_at']='2026-09-23T10:19:00Z';
$failedHistory=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$brokenHistory],$now->modify('+4 minutes'));
history_ingest_check(array_key_exists('installed',$failedHistory['priceHistory']) && $failedHistory['priceHistory']['installed']===null,'broken history marked unknown');
history_ingest_check(($failedHistory['priceHistory']['error']??'')==='history_write_failed','broken history fail-open receipt');
history_ingest_check(($failedHistory['selectionAuthority']??null)===false,'failed history cannot gain selection authority');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+4 minutes'));
history_ingest_check(count($visible['items'])===1&&$visible['items'][0]['price']==='185000','offer store remains current after history failure');
history_ingest_check(
    (int)$db->query("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='anex' AND status='completed'")->fetchColumn()===5,
    'all current snapshots completed despite history states'
);
history_ingest_check(
    (int)$db->query("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='anex' AND status='aborted'")->fetchColumn()===0,
    'history failure never aborts completed offer refresh'
);

$db->exec('DROP TABLE anytour_offer_price_observations');
history_ingest_sql($db,__DIR__.'/../v2/data/migrations/20260923-anytour-offer-price-history-v1.sql');
$empty=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[],$now->modify('+5 minutes'));
history_ingest_check(($empty['offerCount']??-1)===0,'authoritative empty remains empty');
history_ingest_check(($empty['priceHistory']['inputCount']??-1)===0,'empty snapshot creates no history input');
history_ingest_check(($empty['priceHistory']['written']??-1)===0,'empty snapshot creates no history row');
history_ingest_check((int)$db->query('SELECT COUNT(*) FROM anytour_offer_price_observations')->fetchColumn()===0,'no synthetic history after empty');

$source=(string)file_get_contents(__DIR__.'/../v2/data/anytour-offer-snapshot-ingest-v1.php');
history_ingest_check(
    strpos($source,'AnyTourOfferStoreV1::completeRefresh')<strpos($source,'AnyTourOfferPriceHistoryV1::recordIfInstalled'),
    'history recording occurs after offer refresh completion'
);
history_ingest_check(
    !preg_match('/\b(?:curl_|file_get_contents\s*\(\s*[\'\"]https?:|fsockopen|stream_socket_client)\b/i',$source),
    'no supplier transport'
);

echo "ANYTOUR_OFFER_PRICE_HISTORY_INGEST_OK history=append_only replay=dedup absent=fail_open broken=fail_open selection=unchanged\n";
