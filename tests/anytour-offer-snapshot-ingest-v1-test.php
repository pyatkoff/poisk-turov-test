<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/anytour-offer-snapshot-ingest-v1.php';
require_once __DIR__ . '/../v2/data/anytour-offer-store-read-v2.php';

function check_snapshot(bool $value, string $label): void
{
    if (!$value) throw new RuntimeException('CHECK_FAILED:' . $label);
}

function expect_snapshot_error(callable $fn, string $needle, string $label): void
{
    try { $fn(); } catch (Throwable $e) {
        check_snapshot(str_contains($e->getMessage(), $needle), $label . ':wrong:' . $e->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:' . $label . ':no_error');
}

function run_sql_file(PDO $db, string $path): void
{
    $sql = file_get_contents($path);
    if (!is_string($sql) || $sql === '') throw new RuntimeException('EMPTY_SQL:' . $path);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $db->exec($statement);
    }
}

function search_params_snapshot(): array
{
    return [
        'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-05',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],
        'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],
        'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
        'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
    ];
}

function dto_snapshot(string $provider, int $legacyId, string $salt, string $price, int $issued): array
{
    return [
        'schema_version'=>1,
        'provider'=>$provider,
        'operator'=>[
            'raw'=>$provider === 'anex' ? 'ANEX' : 'FUN&SUN',
            'canonical_name'=>$provider === 'anex' ? 'ANEX' : null,
            'canonical_verified'=>$provider === 'anex',
            'identity_source'=>$provider === 'anex' ? 'provider_fixed' : 'raw_label_only',
            'filter_status'=>'unsupported',
            'cross_provider_equivalence_verified'=>false,
            'supplier_code_exposed'=>false,
        ],
        'local_hotel_id'=>$legacyId,
        'identity'=>[
            'search_ref_digest'=>hash('sha256','search:'.$provider.':'.$salt),
            'offer_ref_digest'=>hash('sha256','offer:'.$provider.':'.$salt),
            'provider_hotel_ref_digest'=>hash('sha256','hotel:'.$provider.':'.$salt),
        ],
        'tour'=>[
            'checkin'=>'2026-10-05','nights'=>7,
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            'meal'=>['raw'=>'AI'],'room'=>['raw'=>'STANDARD'],'placement'=>['raw'=>'2AD'],
            'availability'=>['hotel'=>['raw'=>'available']],
            'flight_details'=>['state'=>'search_summary_only'],
            'observed_at'=>gmdate('Y-m-d\\TH:i:s\\Z',$issued),
        ],
        'money'=>['search_price_with_surcharge'=>['amount'=>$price,'currency'=>'RUB']],
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'context'=>['generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,'current_context_verified'=>true],
        'selection_state'=>'disabled','booking_enabled'=>false,
        'finalPriceReady'=>true,'finalPrice'=>$price,'price'=>$price,'currency'=>'RUB',
    ];
}

$dsn = trim((string)getenv('ANYTOUR_OFFER_TEST_DSN'));
if ($dsn === '') {
    echo "ANYTOUR_OFFER_SNAPSHOT_SQL_NOT_RUN reason=no_test_dsn\n";
    exit(0);
}
check_snapshot(in_array('mysql', PDO::getAvailableDrivers(), true), 'pdo_mysql');
$db = new PDO($dsn, (string)getenv('ANYTOUR_OFFER_TEST_USER'), (string)getenv('ANYTOUR_OFFER_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false,
]);

foreach (['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table) {
    $db->exec('DROP TABLE IF EXISTS '.$table);
}
run_sql_file($db, __DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
run_sql_file($db, __DIR__.'/../v2/data/migrations/20260916-anytour-offer-store.sql');
run_sql_file($db, __DIR__.'/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
check_snapshot((int)$db->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn() === 2, 'schema-v2');

$time = '2026-09-17 03:00:00';
$insertHotel = $db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(:json,:sha,1,1,:created,:updated)');
$insertBridge = $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',:legacy,:own,'test',:json,:sha,:first_seen,:last_seen)");
$owns=[];
foreach ([101=>'Alpha Hotel',202=>'Beta Hotel'] as $legacy=>$name) {
    $profile=json_encode(['name'=>$name],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $insertHotel->execute(['json'=>$profile,'sha'=>hash('sha256',$profile),'created'=>$time,'updated'=>$time]);
    $own=(int)$db->lastInsertId();$owns[$legacy]=$own;
    $source=json_encode(['fixture'=>true],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $insertBridge->execute(['legacy'=>(string)$legacy,'own'=>$own,'json'=>$source,'sha'=>hash('sha256',$source),'first_seen'=>$time,'last_seen'=>$time]);
}

$now = new DateTimeImmutable('2026-09-17T03:00:00Z');
$issued=$now->getTimestamp();
$params=search_params_snapshot();
$scope=AnyTourSearchScopeV1::fromParams($params)['digest'];
$rowA=['anytour_hotel_id'=>$owns[101],'dto'=>dto_snapshot('anex',101,'a','199390',$issued),'expires_at'=>'2026-09-17T03:30:00Z'];
$rowB=['anytour_hotel_id'=>$owns[202],'dto'=>dto_snapshot('anex',202,'b','205000',$issued),'expires_at'=>'2026-09-17T03:30:00Z'];

$first=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$rowA,$rowB],$now);
check_snapshot($first['scopeDigest']===$scope && $first['offerCount']===2 && $first['hotelCount']===2, 'first-summary');
check_snapshot($first['selectionAuthority']===false, 'no-selection-authority');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now);
check_snapshot(count($visible['items'])===2, 'first-visible');
check_snapshot(array_column($visible['items'],'price')===['199390','205000'], 'first-prices');

$updated=$rowA;$updated['dto']=dto_snapshot('anex',101,'a2','198000',$issued+60);$updated['expires_at']='2026-09-17T03:31:00Z';
$second=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$updated],$now->modify('+1 minute'));
check_snapshot($second['offerCount']===1 && $second['expiredUnseen']===2, 'replace-summary');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+1 minute'));
check_snapshot(count($visible['items'])===1 && $visible['items'][0]['price']==='198000', 'replace-visible');

$good=$updated;$good['dto']=dto_snapshot('anex',101,'partial-good','197000',$issued+120);$good['expires_at']='2026-09-17T03:32:00Z';
$bad=$rowB;$bad['anytour_hotel_id']=$owns[101];$bad['dto']=dto_snapshot('anex',202,'partial-bad','190000',$issued+120);$bad['expires_at']='2026-09-17T03:32:00Z';
expect_snapshot_error(
    fn()=>AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$good,$bad],$now->modify('+2 minutes')),
    'ANYTOUR_OFFER_HOTEL_BRIDGE','bridge-failclosed'
);
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+2 minutes'));
check_snapshot(count($visible['items'])===1 && $visible['items'][0]['price']==='198000', 'aborted-keeps-complete');
check_snapshot((int)$db->query("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE status='aborted'")->fetchColumn()===1, 'aborted-recorded');
check_snapshot((int)$db->query("SELECT COUNT(*) FROM anytour_offers WHERE display_price=197000")->fetchColumn()===1, 'partial-row-retained-invisible');

$before=(int)$db->query('SELECT COUNT(*) FROM anytour_offer_refreshes')->fetchColumn();
$duplicate=$good;$duplicate['anytour_hotel_id']=$owns[202];
expect_snapshot_error(
    fn()=>AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$good,$duplicate],$now->modify('+3 minutes')),
    'ANYTOUR_OFFER_SNAPSHOT_DUPLICATE_IDENTITY','duplicate-refused'
);
check_snapshot((int)$db->query('SELECT COUNT(*) FROM anytour_offer_refreshes')->fetchColumn()===$before, 'duplicate-no-refresh');

$and=['anytour_hotel_id'=>$owns[202],'dto'=>dto_snapshot('andromeda',202,'and','210000',$issued+180),'expires_at'=>'2026-09-17T03:33:00Z'];
AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'andromeda',$params,[$and],$now->modify('+3 minutes'));
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+3 minutes'));
$providers=array_count_values(array_column($visible['items'],'provider'));
check_snapshot(count($visible['items'])===2 && ($providers['anex']??0)===1 && ($providers['andromeda']??0)===1, 'provider-independent');

$db->exec('UPDATE anytour_offer_store_control SET schema_version=1 WHERE singleton_id=1');
expect_snapshot_error(
    fn()=>AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[],$now->modify('+4 minutes')),
    'ANYTOUR_OFFER_SNAPSHOT_SCHEMA_V2_REQUIRED','schema-v2-required'
);
$db->exec('UPDATE anytour_offer_store_control SET schema_version=2 WHERE singleton_id=1');

$empty=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[],$now->modify('+4 minutes'));
check_snapshot($empty['offerCount']===0 && $empty['hotelCount']===0, 'empty-complete');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+4 minutes'));
check_snapshot(count($visible['items'])===1 && $visible['items'][0]['provider']==='andromeda', 'empty-provider-isolated');

$source=file_get_contents(__DIR__.'/../v2/data/anytour-offer-snapshot-ingest-v1.php');
check_snapshot(is_string($source) && !preg_match('/\b(?:curl_|file_get_contents\s*\(\s*[\'\"]https?:|fsockopen|stream_socket_client)\b/i',$source), 'no-supplier-transport');

echo "ANYTOUR_OFFER_SNAPSHOT_INGEST_OK complete=2 aborted=1 providers=2 schema=2\n";
