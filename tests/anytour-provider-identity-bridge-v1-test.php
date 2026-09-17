<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/anytour-provider-identity-bridge-v1.php';
require_once __DIR__ . '/../v2/data/anytour-offer-store-v1.php';
require_once __DIR__ . '/../v2/data/anytour-offer-store-read-v2.php';

function bridge_check(bool $value, string $label): void
{
    if (!$value) throw new RuntimeException('CHECK_FAILED:' . $label);
}

function bridge_expect(callable $fn, string $needle, string $label): void
{
    try { $fn(); }
    catch (Throwable $error) {
        bridge_check(str_contains($error->getMessage(), $needle), $label . ':wrong:' . $error->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:' . $label . ':no_error');
}

function bridge_sql(PDO $db, string $path): void
{
    $sql = file_get_contents($path);
    if (!is_string($sql) || $sql === '') throw new RuntimeException('EMPTY_SQL');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $db->exec($statement);
    }
}

function bridge_dto(int $legacyId, string $providerHotelDigest, int $issued): array
{
    return [
        'schema_version'=>1,
        'provider'=>'andromeda',
        'operator'=>[
            'raw'=>'FUN&SUN','canonical_name'=>null,'canonical_verified'=>false,
            'identity_source'=>'raw_label_only','filter_status'=>'unsupported',
            'cross_provider_equivalence_verified'=>false,'supplier_code_exposed'=>false,
        ],
        'local_hotel_id'=>$legacyId,
        'identity'=>[
            'search_ref_digest'=>hash('sha256','direct-search'),
            'offer_ref_digest'=>hash('sha256','direct-offer'),
            'provider_hotel_ref_digest'=>$providerHotelDigest,
        ],
        'tour'=>[
            'checkin'=>'2026-10-05','nights'=>7,
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            'meal'=>['raw'=>'AI'],'room'=>['raw'=>'STANDARD'],'placement'=>['raw'=>'2AD'],
            'availability'=>['hotel'=>['raw'=>'available']],
            'flight_details'=>['state'=>'search_summary_only'],
            'observed_at'=>gmdate('Y-m-d\TH:i:s\Z',$issued),
        ],
        'money'=>['search_price_with_surcharge'=>['amount'=>'199390','currency'=>'RUB']],
        'quote_state'=>'unknown','final_price_verified'=>false,'quote_evidence_digest'=>null,
        'context'=>['generation'=>1,'page'=>1,'issued_at'=>$issued,'expires_at'=>$issued+900,'current_context_verified'=>true],
        'selection_state'=>'disabled','booking_enabled'=>false,
        'finalPriceReady'=>true,'finalPrice'=>'199390','price'=>'199390','currency'=>'RUB',
    ];
}

$dsn = trim((string)getenv('ANYTOUR_PROVIDER_BRIDGE_TEST_DSN'));
if ($dsn === '') {
    bridge_check(
        AnyTourProviderIdentityBridgeV1::providerRefDigest('andromeda_catalog:7001') === hash('sha256', 'andromeda_catalog:7001'),
        'pure-digest'
    );
    bridge_expect(
        fn() => AnyTourProviderIdentityBridgeV1::providerRefDigest("bad\nref"),
        'ANYTOUR_PROVIDER_BRIDGE_REF',
        'pure-control-ref'
    );
    echo "ANYTOUR_PROVIDER_IDENTITY_BRIDGE_PURE_OK\n";
    exit(0);
}

$db = new PDO($dsn, (string)getenv('ANYTOUR_PROVIDER_BRIDGE_TEST_USER'), (string)getenv('ANYTOUR_PROVIDER_BRIDGE_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
]);
foreach (['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','andromeda_hotel_identities','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table) {
    $db->exec('DROP TABLE IF EXISTS ' . $table);
}
bridge_sql($db, __DIR__ . '/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
bridge_sql($db, __DIR__ . '/../v2/data/migrations/20260916-anytour-offer-store.sql');
bridge_sql($db, __DIR__ . '/../v2/data/migrations/20260917-anytour-offer-store-v2.sql');
$db->exec("CREATE TABLE andromeda_hotel_identities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    supplier_namespace VARCHAR(64) NOT NULL,
    external_hotel_id VARCHAR(120) NOT NULL,
    local_hotel_id BIGINT UNSIGNED NULL,
    decision_status VARCHAR(32) NOT NULL,
    KEY ix_external (external_hotel_id),
    KEY ix_tuple (supplier_namespace,external_hotel_id,decision_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$at = '2026-09-17 14:45:00';
$insertHotel = $db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(:json,:sha,1,1,:created,:updated)');
$insertLegacy = $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',:legacy,:own,'fixture',:json,:sha,:first_seen,:last_seen)");
$owns = [];
foreach ([101=>'Direct Alpha',202=>'Legacy Beta'] as $legacy => $name) {
    $profile = json_encode(['name'=>$name], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $insertHotel->execute(['json'=>$profile,'sha'=>hash('sha256',$profile),'created'=>$at,'updated'=>$at]);
    $own = (int)$db->lastInsertId(); $owns[$legacy] = $own;
    $source = json_encode(['fixture'=>true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $insertLegacy->execute(['legacy'=>(string)$legacy,'own'=>$own,'json'=>$source,'sha'=>hash('sha256',$source),'first_seen'=>$at,'last_seen'=>$at]);
}
$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status) VALUES('andromeda_catalog','7001',101,'accepted')")->execute();

$now = new DateTimeImmutable('2026-09-17T14:45:00Z');
$receipt = AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($db, [
    ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7001'],
], $now);
bridge_check($receipt['created'] === 1 && $receipt['materialized'] === 1 && $receipt['verified'] === 1, 'materialize-accepted');
bridge_check($receipt['mapping_writes'] === 0 && $receipt['supplier_calls'] === 0, 'ownership-receipt');

$digest = hash('sha256', 'andromeda_catalog:7001');
$sourceRow = $db->prepare("SELECT anytour_hotel_id,acquired_via FROM anytour_hotel_sources WHERE namespace='provider_ref_digest:andromeda' AND external_key=?");
$sourceRow->execute([$digest]); $saved = $sourceRow->fetch(PDO::FETCH_ASSOC);
bridge_check(is_array($saved) && (int)$saved['anytour_hotel_id'] === $owns[101] && $saved['acquired_via'] === 'match_accepted_bridge', 'direct-row');

// Prove runtime no longer needs legacy_catalog once the accepted provider bridge exists.
$db->prepare("DELETE FROM anytour_hotel_sources WHERE namespace='legacy_catalog' AND external_key='101'")->execute();
$directOffer = [[
    'provider'=>'andromeda','provider_hotel_ref_digest'=>$digest,
    'legacy_hotel_id'=>101,'anytour_hotel_id'=>$owns[101],
]];
bridge_check(count(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $directOffer)) === 1, 'direct-without-legacy');
bridge_check(AnyTourProviderIdentityBridgeV1::allowsOffer($db, 'andromeda', $digest, 101, $owns[101]), 'direct-allows');

// Integration: store + v2 visibility both use the direct accepted identity after the legacy link is gone.
$scope = hash('sha256','direct-provider-scope');
$token = AnyTourOfferStoreV1::beginRefresh($db,'andromeda',$scope,$now);
$dto = bridge_dto(101,$digest,$now->getTimestamp());
AnyTourOfferStoreV1::upsertReadyOffer($db,$token,$owns[101],$dto,$now->modify('+30 minutes'),$now);
AnyTourOfferStoreV1::completeRefresh($db,$token,$now);
$visible = AnyTourOfferStoreReadV2::readScope($db,$scope,$now);
bridge_check(count($visible['items']) === 1 && $visible['items'][0]['anytourHotelId'] === $owns[101], 'store-read-direct-without-legacy');
bridge_check($visible['items'][0]['provider'] === 'andromeda' && $visible['items'][0]['price'] === '199390', 'store-read-exact-offer');

// A current MATCH rejection/reassignment invalidates the materialized bridge and the already-saved offer may not fall back.
$db->exec("UPDATE andromeda_hotel_identities SET decision_status='pending' WHERE external_hotel_id='7001'");
bridge_check(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $directOffer) === [], 'pending-fail-closed');
bridge_check(AnyTourOfferStoreReadV2::readScope($db,$scope,$now)['items'] === [], 'pending-hidden-from-store');
$db->exec("UPDATE andromeda_hotel_identities SET decision_status='accepted',local_hotel_id=202 WHERE external_hotel_id='7001'");
bridge_check(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $directOffer) === [], 'reassigned-fail-closed');
bridge_check(AnyTourOfferStoreReadV2::readScope($db,$scope,$now)['items'] === [], 'reassigned-hidden-from-store');
bridge_expect(
    fn() => AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($db, [
        ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7001'],
    ], $now->modify('+1 minute')),
    'ANYTOUR_PROVIDER_BRIDGE_TARGET_CONFLICT',
    'reassignment-needs-explicit-reconcile'
);

// A not-yet-materialized provider identity may use the bounded compatibility path,
// but only while that exact provider ref is CURRENT accepted to the claimed local ID.
$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status) VALUES('andromeda_catalog','7999',202,'accepted')")->execute();
$legacyDigest = hash('sha256', 'andromeda_catalog:7999');
$legacyOffer = [[
    'provider'=>'andromeda','provider_hotel_ref_digest'=>$legacyDigest,
    'legacy_hotel_id'=>202,'anytour_hotel_id'=>$owns[202],
]];
bridge_check(count(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $legacyOffer)) === 1, 'accepted-progressive-fallback');

// A supplier hotel with the same legacy target but no exact accepted identity must fail closed.
$unacceptedDigest = hash('sha256', 'andromeda_catalog:7888');
$unacceptedOffer = [[
    'provider'=>'andromeda','provider_hotel_ref_digest'=>$unacceptedDigest,
    'legacy_hotel_id'=>202,'anytour_hotel_id'=>$owns[202],
]];
bridge_check(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $unacceptedOffer) === [], 'unaccepted-fallback-blocked');
bridge_check(!AnyTourProviderIdentityBridgeV1::allowsOffer($db, 'andromeda', $unacceptedDigest, 202, $owns[202]), 'unaccepted-admission-blocked');

// Non-Andromeda providers keep their existing legacy compatibility behavior.
$anexOffer = [[
    'provider'=>'anex','provider_hotel_ref_digest'=>hash('sha256', 'anex:fixture'),
    'legacy_hotel_id'=>202,'anytour_hotel_id'=>$owns[202],
]];
bridge_check(count(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $anexOffer)) === 1, 'anex-legacy-unchanged');

// Unresolved accepted identity is never invented.
$missing = AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($db, [
    ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'9999'],
], $now->modify('+2 minutes'));
bridge_check($missing['materialized'] === 0 && $missing['unresolved'] === 1, 'unresolved-no-guess');

echo "ANYTOUR_PROVIDER_IDENTITY_BRIDGE_OK direct_without_legacy=1 store_read=1 stale_fail_closed=1 fallback_acceptance=1 unresolved=1\n";
