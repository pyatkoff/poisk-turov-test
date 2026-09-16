<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/data/anytour-offer-store-v1.php';

function check(bool $value, string $label): void
{
    if (!$value) throw new RuntimeException('CHECK_FAILED:' . $label);
}

function expect_error(callable $fn, string $needle, string $label): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        check(str_contains($e->getMessage(), $needle), $label . ':wrong_error:' . $e->getMessage());
        return;
    }
    throw new RuntimeException('CHECK_FAILED:' . $label . ':no_error');
}

function fixture_dto(string $provider, int $legacyHotelId, string $salt, string $price = '199390'): array
{
    $now = 1789581600;
    $operatorRaw = match ($provider) {
        'anex' => 'ANEX',
        'andromeda' => 'FUN&SUN',
        default => 'Pegas Touristik',
    };
    $operatorVerified = $provider === 'anex';
    return [
        'schema_version' => 1,
        'provider' => $provider,
        'operator' => [
            'raw' => $operatorRaw,
            'canonical_name' => $operatorVerified ? 'ANEX' : null,
            'canonical_verified' => $operatorVerified,
            'identity_source' => $operatorVerified ? 'provider_fixed' : 'raw_label_only',
            'filter_status' => $provider === 'tourvisor' ? 'verified' : 'unsupported',
            'cross_provider_equivalence_verified' => false,
            'supplier_code_exposed' => false,
        ],
        'local_hotel_id' => $legacyHotelId,
        'identity' => [
            'search_ref_digest' => hash('sha256', 'search:' . $provider . ':' . $salt),
            'offer_ref_digest' => hash('sha256', 'offer:' . $provider . ':' . $salt),
            'provider_hotel_ref_digest' => hash('sha256', 'hotel:' . $provider . ':' . $salt),
        ],
        'tour' => [
            'checkin' => '2026-10-05',
            'nights' => 7,
            'party' => ['adults' => 2, 'children' => 1, 'child_ages' => [7]],
            'meal' => ['raw' => 'AI', 'family' => 'AI', 'qualifiers' => ['plus' => false, 'without_alcohol' => false], 'family_verified' => true],
            'room' => ['raw' => 'STANDARD ROOM'],
            'placement' => ['raw' => '2AD+1CHD'],
            'availability' => ['hotel' => ['raw' => 'available']],
            'flight_details' => ['state' => 'search_summary_only'],
            'observed_at' => '2026-09-16T18:00:00Z',
        ],
        'money' => [
            'search_price' => ['amount' => '185125', 'currency' => 'RUB'],
            'search_price_with_surcharge' => ['amount' => $price, 'currency' => 'RUB'],
        ],
        'quote_state' => 'unknown',
        'final_price_verified' => false,
        'quote_evidence_digest' => null,
        'context' => [
            'generation' => 1,
            'page' => 1,
            'issued_at' => $now,
            'expires_at' => $now + 900,
            'current_context_verified' => true,
        ],
        'selection_state' => 'disabled',
        'booking_enabled' => false,
        'finalPriceReady' => true,
        'finalPrice' => $price,
        'price' => $price,
        'currency' => 'RUB',
    ];
}

function private_call(string $method, mixed ...$args): mixed
{
    $m = new ReflectionMethod(AnyTourOfferStoreV1::class, $method);
    return $m->invoke(null, ...$args);
}

$tv = fixture_dto('tourvisor', 101, 'tv-1', '199390');
$anex = fixture_dto('anex', 101, 'anex-1', '196500');
$andromeda = fixture_dto('andromeda', 101, 'andromeda-1', '195000');
foreach ([$tv, $anex, $andromeda] as $dto) {
    $validated = private_call('validateDto', $dto);
    check($validated['price'] === $dto['price'], 'pure-price-' . $dto['provider']);
    $listing = private_call('listingProjection', $dto);
    check($listing['provider'] === $dto['provider'], 'pure-provider-' . $dto['provider']);
    check($listing['listingPriceReady'] === true && $listing['listingPrice'] === $dto['price'], 'pure-listing-price-' . $dto['provider']);
    check($listing['selection_state'] === 'refresh_required' && $listing['booking_enabled'] === false, 'pure-no-stale-selection-' . $dto['provider']);
    check(!array_key_exists('context', $listing) && !array_key_exists('local_hotel_id', $listing), 'pure-context-stripped-' . $dto['provider']);
}
$bad = $tv; $bad['finalPriceReady'] = false;
expect_error(fn() => private_call('validateDto', $bad), 'ANYTOUR_OFFER_READINESS', 'pure-ready-failclosed');
$bad = $tv; $bad['identity']['offer_ref_digest'] = 'abc';
expect_error(fn() => private_call('validateDto', $bad), 'ANYTOUR_OFFER_IDENTITY', 'pure-identity-failclosed');
$bad = $tv; $bad['price'] = '199391';
expect_error(fn() => private_call('validateDto', $bad), 'ANYTOUR_OFFER_PRICE_MISMATCH', 'pure-price-mismatch');
$bad = $tv; $bad['provider'] = 'samo';
expect_error(fn() => private_call('validateDto', $bad), 'ANYTOUR_OFFER_PROVIDER', 'pure-provider-namespace');
$bad = $tv; $bad['operator']['supplier_code_exposed'] = true;
expect_error(fn() => private_call('validateDto', $bad), 'ANYTOUR_OFFER_OPERATOR', 'pure-private-operator-code');
echo "ANYTOUR_OFFER_STORE_PURE_OK checks=20 providers=3\n";

$requireSql = in_array('--require-sql', $argv, true);
$dsn = trim((string)getenv('ANYTOUR_OFFER_TEST_DSN'));
if ($dsn === '') {
    if ($requireSql) throw new RuntimeException('ANYTOUR_OFFER_SQL_REQUIRED');
    echo "ANYTOUR_OFFER_STORE_SQL_NOT_RUN reason=no_test_dsn\n";
    exit(0);
}
check(in_array('mysql', PDO::getAvailableDrivers(), true), 'pdo_mysql-required');
$pdo = new PDO($dsn, (string)getenv('ANYTOUR_OFFER_TEST_USER'), (string)getenv('ANYTOUR_OFFER_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
]);

function exec_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if (!is_string($sql) || $sql === '') throw new RuntimeException('EMPTY_SQL:' . $path);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $pdo->exec($statement);
    }
}

foreach (['anytour_offers','anytour_offer_scope_state','anytour_offer_refreshes','anytour_offer_store_control','anytour_hotel_sources','anytour_hotels','anytour_catalog_control'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS ' . $table);
}
exec_sql_file($pdo, __DIR__ . '/../v2/data/migrations/20260916-anytour-canonical-catalog.sql');
exec_sql_file($pdo, __DIR__ . '/../v2/data/migrations/20260916-anytour-offer-store.sql');

$nowSql = '2026-09-16 18:00:00';
$profile = json_encode(['name' => 'Fixture Hotel'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$insertHotel = $pdo->prepare('INSERT INTO anytour_hotels (profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES (:json,:sha,1,1,:at,:at)');
$insertHotel->execute(['json'=>$profile,'sha'=>hash('sha256',$profile),'at'=>$nowSql]);
$own1 = (int)$pdo->lastInsertId();
$profile2 = json_encode(['name' => 'Other Hotel'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$insertHotel->execute(['json'=>$profile2,'sha'=>hash('sha256',$profile2),'at'=>$nowSql]);
$own2 = (int)$pdo->lastInsertId();
$source = json_encode(['fixture'=>true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$bridge = $pdo->prepare("INSERT INTO anytour_hotel_sources (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES ('legacy_catalog',:legacy,:own,'test',:json,:sha,:at,:at)");
$bridge->execute(['legacy'=>'101','own'=>$own1,'json'=>$source,'sha'=>hash('sha256',$source),'at'=>$nowSql]);
$bridge->execute(['legacy'=>'202','own'=>$own2,'json'=>$source,'sha'=>hash('sha256',$source),'at'=>$nowSql]);

$at = new DateTimeImmutable('2026-09-16T18:00:00Z');
$expires = $at->modify('+30 minutes');
$scope = hash('sha256', 'MOW|EG|2026-10-05|2026-10-05|7|7|2|7');

$tvRefresh = AnyTourOfferStoreV1::beginRefresh($pdo, 'tourvisor', $scope, $at);
AnyTourOfferStoreV1::upsertReadyOffer($pdo, $tvRefresh, $own1, $tv, $expires, $at);
$tv2 = fixture_dto('tourvisor', 101, 'tv-2', '205000');
AnyTourOfferStoreV1::upsertReadyOffer($pdo, $tvRefresh, $own1, $tv2, $expires, $at);
check(AnyTourOfferStoreV1::completeRefresh($pdo, $tvRefresh, $at)['expiredUnseen'] === 0, 'initial-tv-complete');

$anexRefresh = AnyTourOfferStoreV1::beginRefresh($pdo, 'anex', $scope, $at);
AnyTourOfferStoreV1::upsertReadyOffer($pdo, $anexRefresh, $own1, $anex, $expires, $at);
AnyTourOfferStoreV1::completeRefresh($pdo, $anexRefresh, $at);
$andRefresh = AnyTourOfferStoreV1::beginRefresh($pdo, 'andromeda', $scope, $at);
AnyTourOfferStoreV1::upsertReadyOffer($pdo, $andRefresh, $own1, $andromeda, $expires, $at);
AnyTourOfferStoreV1::completeRefresh($pdo, $andRefresh, $at);

$read = AnyTourOfferStoreV1::readScope($pdo, $scope, $at);
check(count($read['items']) === 4, 'three-provider-count');
$providers = array_count_values(array_column($read['items'], 'provider'));
check(($providers['tourvisor'] ?? 0) === 2 && ($providers['anex'] ?? 0) === 1 && ($providers['andromeda'] ?? 0) === 1, 'three-provider-coexistence');
check($read['items'][0]['price'] === '195000', 'price-sort');
foreach ($read['items'] as $item) {
    check($item['anytourHotelId'] === $own1 && $item['legacyHotelId'] === 101, 'own-id-bridge');
    check(($item['offer']['selection_state'] ?? null) === 'refresh_required', 'read-never-replays-selection');
    check(!array_key_exists('context', $item['offer']), 'read-no-expired-context');
}

$tvRefresh2 = AnyTourOfferStoreV1::beginRefresh($pdo, 'tourvisor', $scope, $at->modify('+1 minute'));
$tvUpdated = fixture_dto('tourvisor', 101, 'tv-1', '198000');
AnyTourOfferStoreV1::upsertReadyOffer($pdo, $tvRefresh2, $own1, $tvUpdated, $expires, $at->modify('+1 minute'));
$complete = AnyTourOfferStoreV1::completeRefresh($pdo, $tvRefresh2, $at->modify('+1 minute'));
check($complete['expiredUnseen'] === 1, 'successful-refresh-expires-unseen-own-provider');
$read = AnyTourOfferStoreV1::readScope($pdo, $scope, $at->modify('+2 minutes'));
$providers = array_count_values(array_column($read['items'], 'provider'));
check(count($read['items']) === 3 && ($providers['tourvisor'] ?? 0) === 1 && ($providers['anex'] ?? 0) === 1 && ($providers['andromeda'] ?? 0) === 1, 'provider-isolated-refresh');
$tvRows = array_values(array_filter($read['items'], fn($x) => $x['provider'] === 'tourvisor'));
check(count($tvRows) === 1 && $tvRows[0]['price'] === '198000', 'same-identity-updated');
check((int)$pdo->query("SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND scope_sha256=" . $pdo->quote($scope))->fetchColumn() === 2, 'upsert-not-third-row');

$abortToken = AnyTourOfferStoreV1::beginRefresh($pdo, 'anex', $scope, $at->modify('+3 minutes'));
AnyTourOfferStoreV1::abortRefresh($pdo, $abortToken, $at->modify('+3 minutes'));
$read = AnyTourOfferStoreV1::readScope($pdo, $scope, $at->modify('+4 minutes'));
check(count(array_filter($read['items'], fn($x) => $x['provider'] === 'anex')) === 1, 'aborted-refresh-preserves-prior');

$busyScope = hash('sha256', 'busy-scope');
$busy = AnyTourOfferStoreV1::beginRefresh($pdo, 'andromeda', $busyScope, $at, 60);
expect_error(fn() => AnyTourOfferStoreV1::beginRefresh($pdo, 'andromeda', $busyScope, $at->modify('+10 seconds'), 60), 'ANYTOUR_OFFER_REFRESH_BUSY', 'busy-refresh');
$pdo->prepare("UPDATE anytour_offer_refreshes SET lease_expires_at='2026-09-16 17:59:00' WHERE refresh_token=:token")->execute(['token'=>$busy]);
$replacement = AnyTourOfferStoreV1::beginRefresh($pdo, 'andromeda', $busyScope, $at->modify('+1 minute'), 60);
check($pdo->query("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE status='abandoned'")->fetchColumn() >= 1, 'stale-refresh-abandoned');
AnyTourOfferStoreV1::abortRefresh($pdo, $replacement, $at->modify('+1 minute'));

$mapScope = hash('sha256', 'mapping-scope');
$mapRefresh = AnyTourOfferStoreV1::beginRefresh($pdo, 'tourvisor', $mapScope, $at);
$wrongHotel = fixture_dto('tourvisor', 202, 'wrong-own', '180000');
expect_error(fn() => AnyTourOfferStoreV1::upsertReadyOffer($pdo, $mapRefresh, $own1, $wrongHotel, $expires, $at), 'ANYTOUR_OFFER_HOTEL_BRIDGE', 'explicit-own-bridge');
AnyTourOfferStoreV1::abortRefresh($pdo, $mapRefresh, $at);

$providerScope = hash('sha256', 'provider-scope');
$providerRefresh = AnyTourOfferStoreV1::beginRefresh($pdo, 'tourvisor', $providerScope, $at);
expect_error(fn() => AnyTourOfferStoreV1::upsertReadyOffer($pdo, $providerRefresh, $own1, $anex, $expires, $at), 'ANYTOUR_OFFER_PROVIDER_SCOPE', 'provider-scope-isolation');
AnyTourOfferStoreV1::abortRefresh($pdo, $providerRefresh, $at);

$pdo->beginTransaction();
expect_error(fn() => AnyTourOfferStoreV1::beginRefresh($pdo, 'tourvisor', hash('sha256','tx'), $at), 'ANYTOUR_OFFER_CALLER_TRANSACTION', 'caller-transaction-rejected');
$pdo->rollBack();

$expiryScope = hash('sha256', 'expiry-scope');
$expiryRefresh = AnyTourOfferStoreV1::beginRefresh($pdo, 'tourvisor', $expiryScope, $at);
expect_error(fn() => AnyTourOfferStoreV1::upsertReadyOffer($pdo, $expiryRefresh, $own1, $tv, $at->modify('+7 hours'), $at), 'ANYTOUR_OFFER_EXPIRY', 'expiry-cap');
AnyTourOfferStoreV1::upsertReadyOffer($pdo, $expiryRefresh, $own1, $tv, $at->modify('+5 minutes'), $at);
AnyTourOfferStoreV1::completeRefresh($pdo, $expiryRefresh, $at);
check(count(AnyTourOfferStoreV1::readScope($pdo, $expiryScope, $at->modify('+6 minutes'))['items']) === 0, 'expired-hidden');

$pdo->exec("UPDATE anytour_offers SET payload_json='{}' WHERE provider='anex' AND scope_sha256=" . $pdo->quote($scope));
expect_error(fn() => AnyTourOfferStoreV1::readScope($pdo, $scope, $at->modify('+5 minutes')), 'ANYTOUR_OFFER_PAYLOAD_INTEGRITY', 'payload-integrity');

echo "ANYTOUR_OFFER_STORE_SQL_OK providers=3 own_hotels=2 initial_offers=4 current_offers=3\n";
