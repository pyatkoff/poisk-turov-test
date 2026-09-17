<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/anytour-provider-identity-bridge-v1.php';

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
foreach (['andromeda_hotel_identities','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table) {
    $db->exec('DROP TABLE IF EXISTS ' . $table);
}
bridge_sql($db, __DIR__ . '/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
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
$insertHotel = $db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(:json,:sha,1,1,:at,:at)');
$insertLegacy = $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',:legacy,:own,'fixture',:json,:sha,:at,:at)");
$owns = [];
foreach ([101=>'Direct Alpha',202=>'Legacy Beta'] as $legacy => $name) {
    $profile = json_encode(['name'=>$name], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $insertHotel->execute(['json'=>$profile,'sha'=>hash('sha256',$profile),'at'=>$at]);
    $own = (int)$db->lastInsertId(); $owns[$legacy] = $own;
    $source = json_encode(['fixture'=>true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $insertLegacy->execute(['legacy'=>(string)$legacy,'own'=>$own,'json'=>$source,'sha'=>hash('sha256',$source),'at'=>$at]);
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

// A current MATCH rejection/reassignment invalidates the materialized bridge and may not fall back.
$db->exec("UPDATE andromeda_hotel_identities SET decision_status='pending' WHERE external_hotel_id='7001'");
bridge_check(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $directOffer) === [], 'pending-fail-closed');
$db->exec("UPDATE andromeda_hotel_identities SET decision_status='accepted',local_hotel_id=202 WHERE external_hotel_id='7001'");
bridge_check(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $directOffer) === [], 'reassigned-fail-closed');
bridge_expect(
    fn() => AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($db, [
        ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7001'],
    ], $now->modify('+1 minute')),
    'ANYTOUR_PROVIDER_BRIDGE_TARGET_CONFLICT',
    'reassignment-needs-explicit-reconcile'
);

// A not-yet-materialized provider identity retains the bounded migration fallback only.
$legacyDigest = hash('sha256', 'andromeda_catalog:7999');
$legacyOffer = [[
    'provider'=>'andromeda','provider_hotel_ref_digest'=>$legacyDigest,
    'legacy_hotel_id'=>202,'anytour_hotel_id'=>$owns[202],
]];
bridge_check(count(AnyTourProviderIdentityBridgeV1::filterOfferRows($db, $legacyOffer)) === 1, 'progressive-legacy-fallback');

// Unresolved accepted identity is never invented.
$missing = AnyTourProviderIdentityBridgeV1::materializeAcceptedAndromeda($db, [
    ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'9999'],
], $now->modify('+2 minutes'));
bridge_check($missing['materialized'] === 0 && $missing['unresolved'] === 1, 'unresolved-no-guess');

echo "ANYTOUR_PROVIDER_IDENTITY_BRIDGE_OK direct_without_legacy=1 stale_fail_closed=1 unresolved=1\n";
