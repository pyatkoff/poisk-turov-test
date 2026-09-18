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
$insertAlias = $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('anytour_local_id',:legacy,:own,'canonical_local_alias_v1',:json,:sha,:first_seen,:last_seen)");
$owns=[];$aliasFixtures=[];
foreach ([101=>'Alpha Hotel',202=>'Beta Hotel'] as $legacy=>$name) {
    $profile=json_encode(['name'=>$name],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $insertHotel->execute(['json'=>$profile,'sha'=>hash('sha256',$profile),'created'=>$time,'updated'=>$time]);
    $own=(int)$db->lastInsertId();$owns[$legacy]=$own;
    $source=json_encode(['fixture'=>true],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $sourceSha=hash('sha256',$source);
    $insertBridge->execute(['legacy'=>(string)$legacy,'own'=>$own,'json'=>$source,'sha'=>$sourceSha,'first_seen'=>$time,'last_seen'=>$time]);
    $alias=[
        'accepted_local_hotel_id'=>$legacy,
        'canonical_hotel_id'=>$own,
        'derived_from_namespace'=>'legacy_catalog',
        'derived_from_source_sha256'=>$sourceSha,
        'schema_version'=>1,
    ];
    ksort($alias,SORT_STRING);
    $aliasJson=json_encode($alias,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $aliasFixtures[$legacy]=['legacy'=>(string)$legacy,'own'=>$own,'json'=>$aliasJson,'sha'=>hash('sha256',$aliasJson),'first_seen'=>$time,'last_seen'=>$time];
    $insertAlias->execute($aliasFixtures[$legacy]);
}

$now = new DateTimeImmutable('2026-09-17T03:00:00Z');
$issued=$now->getTimestamp();
$params=search_params_snapshot();
$scope=AnyTourSearchScopeV1::fromParams($params)['digest'];
$rowA=['anytour_hotel_id'=>$owns[101],'dto'=>dto_snapshot('anex',101,'a','199390',$issued),'expires_at'=>'2026-09-17T03:15:00Z'];
$rowB=['anytour_hotel_id'=>$owns[202],'dto'=>dto_snapshot('anex',202,'b','205000',$issued),'expires_at'=>'2026-09-17T03:15:00Z'];

$first=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$rowA,$rowB],$now);
check_snapshot($first['scopeDigest']===$scope && $first['offerCount']===2 && $first['hotelCount']===2, 'first-summary');
check_snapshot($first['snapshotMode']==='complete_replace' && $first['carriedForward']===0, 'complete-mode');
check_snapshot($first['selectionAuthority']===false, 'no-selection-authority');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now);
check_snapshot(count($visible['items'])===2, 'first-visible');
check_snapshot(array_column($visible['items'],'price')===['199390','205000'], 'first-prices');

$stored=$db->query("SELECT source_context_expires_at,last_seen_at,expires_at FROM anytour_offers WHERE provider='anex' AND is_active=1 ORDER BY id ASC")->fetchAll();
check_snapshot(count($stored)===2, 'stored-two');
foreach ($stored as $item) {
    check_snapshot($item['source_context_expires_at']==='2026-09-17 03:15:00', 'source-context-preserved');
    check_snapshot($item['last_seen_at']==='2026-09-17 03:00:00', 'listing-seen-at');
    check_snapshot($item['expires_at']==='2026-09-18 03:00:00', 'listing-one-day-expiry');
}
$afterContext=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+20 minutes'));
check_snapshot(count($afterContext['items'])===2, 'visible-after-provider-context-expiry');
foreach ($afterContext['items'] as $item) {
    check_snapshot(($item['offer']['selection_state']??null)==='refresh_required', 'cached-refresh-required');
    check_snapshot(($item['offer']['booking_enabled']??null)===false, 'cached-booking-disabled');
    check_snapshot($item['expiresAt']==='2026-09-18T03:00:00Z', 'cached-one-day-expiry');
}
$beforeListingExpiry=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+23 hours 59 minutes'));
check_snapshot(count($beforeListingExpiry['items'])===2, 'visible-through-one-day-window');
$afterListing=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+24 hours'));
check_snapshot(count($afterListing['items'])===0, 'hidden-at-local-listing-expiry');

// A non-authoritative page that observes only A must not delete unseen B. The carried
// B listing keeps its original LOCAL age/expiry instead of being silently refreshed.
$partialA=$rowA;
$partialA['dto']=dto_snapshot('anex',101,'a','198500',$issued+30);
$partialA['expires_at']='2026-09-17T03:15:30Z';
$partial=AnyTourOfferSnapshotIngestV1::mergePartialSnapshot($db,'anex',$params,[$partialA],$now->modify('+30 seconds'));
check_snapshot($partial['snapshotMode']==='partial_additive', 'partial-mode');
check_snapshot($partial['offerCount']===1 && $partial['carriedForward']===1, 'partial-summary');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+30 seconds'));
check_snapshot(count($visible['items'])===2, 'partial-preserves-unseen');
check_snapshot(array_column($visible['items'],'price')===['198500','205000'], 'partial-prices');
$latest=(string)$db->query("SELECT latest_complete_refresh_token FROM anytour_offer_scope_state WHERE provider='anex' AND scope_sha256=".$db->quote($scope))->fetchColumn();
$carriedQuery=$db->prepare("SELECT last_seen_at,expires_at FROM anytour_offers WHERE provider='anex' AND scope_sha256=:scope AND last_refresh_token=:token AND offer_ref_digest=:offer LIMIT 1");
$carriedQuery->execute(['scope'=>$scope,'token'=>$latest,'offer'=>$rowB['dto']['identity']['offer_ref_digest']]);
$carried=$carriedQuery->fetch();
check_snapshot(is_array($carried), 'partial-carried-row');
check_snapshot($carried['last_seen_at']==='2026-09-17 03:00:00', 'partial-does-not-refresh-unseen-age');
check_snapshot($carried['expires_at']==='2026-09-18 03:00:00', 'partial-does-not-refresh-unseen-expiry');

$beforePartialReject=(int)$db->query('SELECT COUNT(*) FROM anytour_offer_refreshes')->fetchColumn();
$latestBeforeReject=$latest;
expect_snapshot_error(
    fn()=>AnyTourOfferSnapshotIngestV1::mergePartialSnapshot($db,'anex',$params,[],$now->modify('+40 seconds')),
    'ANYTOUR_OFFER_PARTIAL_ROWS','empty-partial-refused'
);
check_snapshot((int)$db->query('SELECT COUNT(*) FROM anytour_offer_refreshes')->fetchColumn()===$beforePartialReject, 'empty-partial-no-refresh');
check_snapshot((string)$db->query("SELECT latest_complete_refresh_token FROM anytour_offer_scope_state WHERE provider='anex' AND scope_sha256=".$db->quote($scope))->fetchColumn()===$latestBeforeReject, 'empty-partial-keeps-complete');

// Carry-forward revalidates current accepted local identity. Removing B's accepted
// AnyTour alias makes it unresolved; a later partial A may not resurrect B from cache.
$db->prepare("DELETE FROM anytour_hotel_sources WHERE namespace='anytour_local_id' AND external_key=?")->execute(['202']);
$partialA2=$rowA;
$partialA2['dto']=dto_snapshot('anex',101,'a','198250',$issued+45);
$partialA2['expires_at']='2026-09-17T03:15:45Z';
$revalidated=AnyTourOfferSnapshotIngestV1::mergePartialSnapshot($db,'anex',$params,[$partialA2],$now->modify('+45 seconds'));
check_snapshot($revalidated['carriedForward']===0, 'partial-unresolved-not-carried');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+45 seconds'));
check_snapshot(count($visible['items'])===1 && $visible['items'][0]['price']==='198250', 'partial-unresolved-hidden');
$insertAlias->execute($aliasFixtures[202]);

$updated=$rowA;$updated['dto']=dto_snapshot('anex',101,'a2','198000',$issued+60);$updated['expires_at']='2026-09-17T03:16:00Z';
$second=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$updated],$now->modify('+1 minute'));
check_snapshot($second['offerCount']===1 && $second['expiredUnseen']===1, 'replace-summary');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+1 minute'));
check_snapshot(count($visible['items'])===1 && $visible['items'][0]['price']==='198000', 'replace-visible');

$good=$updated;$good['dto']=dto_snapshot('anex',101,'partial-good','197000',$issued+120);$good['expires_at']='2026-09-17T03:17:00Z';
$bad=$rowB;$bad['anytour_hotel_id']=$owns[101];$bad['dto']=dto_snapshot('anex',202,'partial-bad','190000',$issued+120);$bad['expires_at']='2026-09-17T03:17:00Z';
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
    fn()=>AnyTourOfferSnapshotIngestV1::mergePartialSnapshot($db,'anex',$params,[$good,$duplicate],$now->modify('+3 minutes')),
    'ANYTOUR_OFFER_SNAPSHOT_DUPLICATE_IDENTITY','duplicate-partial-refused'
);
check_snapshot((int)$db->query('SELECT COUNT(*) FROM anytour_offer_refreshes')->fetchColumn()===$before, 'duplicate-partial-no-refresh');

$tooLong=$good;$tooLong['expires_at']='2026-09-17T09:02:01Z';
expect_snapshot_error(
    fn()=>AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[$tooLong],$now->modify('+2 minutes')),
    'ANYTOUR_OFFER_SNAPSHOT_EXPIRY','producer-expiry-still-bounded'
);
check_snapshot((int)$db->query('SELECT COUNT(*) FROM anytour_offer_refreshes')->fetchColumn()===$before, 'bad-expiry-no-refresh');

// Provider isolation uses another provider-neutral LOCAL source without requiring an
// Andromeda MATCH fixture, which is covered by the dedicated identity-bridge regression.
$other=['anytour_hotel_id'=>$owns[202],'dto'=>dto_snapshot('tourvisor',202,'tv','210000',$issued+180),'expires_at'=>'2026-09-17T03:18:00Z'];
AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'tourvisor',$params,[$other],$now->modify('+3 minutes'));
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+3 minutes'));
$providers=array_count_values(array_column($visible['items'],'provider'));
check_snapshot(count($visible['items'])===2 && ($providers['anex']??0)===1 && ($providers['tourvisor']??0)===1, 'provider-independent');

$db->exec('UPDATE anytour_offer_store_control SET schema_version=1 WHERE singleton_id=1');
expect_snapshot_error(
    fn()=>AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[],$now->modify('+4 minutes')),
    'ANYTOUR_OFFER_SNAPSHOT_SCHEMA_V2_REQUIRED','schema-v2-required'
);
$db->exec('UPDATE anytour_offer_store_control SET schema_version=2 WHERE singleton_id=1');

$empty=AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db,'anex',$params,[],$now->modify('+4 minutes'));
check_snapshot($empty['offerCount']===0 && $empty['hotelCount']===0, 'empty-complete');
$visible=AnyTourOfferStoreReadV2::readScope($db,$scope,$now->modify('+4 minutes'));
check_snapshot(count($visible['items'])===1 && $visible['items'][0]['provider']==='tourvisor', 'empty-provider-isolated');

$source=file_get_contents(__DIR__.'/../v2/data/anytour-offer-snapshot-ingest-v1.php');
check_snapshot(is_string($source) && !preg_match('/\b(?:curl_|file_get_contents\s*\(\s*[\'\"]https?:|fsockopen|stream_socket_client)\b/i',$source), 'no-supplier-transport');

echo "ANYTOUR_OFFER_SNAPSHOT_INGEST_OK complete_replace=2 partial_additive=2 aborted=1 providers=2 schema=2 cached_listing_ttl=86400\n";
