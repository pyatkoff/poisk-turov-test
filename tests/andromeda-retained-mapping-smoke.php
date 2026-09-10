<?php
declare(strict_types=1);
require $argv[1] . '/v2/api-andromeda-search3-preview.php';

// Disposable SQLite + retained normalized fixtures only; no live DB or transport.
function verify(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
}
final class MappingFixturePDO extends PDO {
    public int $mappingReads = 0;
    public bool $failMapping = false;
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_contains($query, 'FROM andromeda_hotel_identities')) {
            ++$this->mappingReads;
            if ($this->failMapping) throw new PDOException('fixture_mapping_read_failed');
        }
        return parent::prepare($query, $options);
    }
}
$pdo = new MappingFixturePDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE catalog_hotels (id INTEGER, country_id INTEGER, is_active INTEGER,
    name TEXT, country_name TEXT, region_id INTEGER, region_name TEXT, subregion_id INTEGER,
    subregion_name TEXT, category INTEGER, rating REAL, primary_image_url TEXT)');
$pdo->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace TEXT, external_hotel_id TEXT,
    local_hotel_id INTEGER, decision_status TEXT)');
$pdo->exec("INSERT INTO catalog_hotels VALUES
    (900,4,1,'Retained fixture','Turkey',1,'Region',1,'Subregion',5,4.5,NULL),
    (901,4,1,'Different fixture','Turkey',1,'Region',1,'Subregion',5,4.5,NULL)");
$pdo->exec("INSERT INTO andromeda_hotel_identities VALUES
    ('andromeda_catalog','3414',900,'accepted'),('andromeda_catalog','3415',900,'accepted')");
$criteria = ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>643,'PAGE'=>1];
$row = ['id'=>'private-fixture-a','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>83080,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'22.09.2026','nights'=>'7',
    'hotel'=>'Fixture','operator'=>'Operator A','meal'=>'RO','mealKey'=>1,
    'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$other = array_replace($row, ['id'=>'private-fixture-b','hotelKey'=>3415,'operatorKey'=>6,'operator'=>'Operator B']);
$identities = [];
foreach (['3414','3415'] as $id) $identities[] = ['supplier_namespace'=>'andromeda_catalog',
    'external_hotel_id'=>$id,'decision_status'=>'accepted','catalog_hotel_id'=>'900','existing_catalog_hotel_id'=>'900'];
$resolver = AnyTourAndromedaHotelResolver::fromRows($identities, str_repeat('c',64));
$state = [];
$store = new AnyTourAndromedaOfferStore($state,true);
$ref = str_repeat('a',64);
$store->begin($ref,1,1000);
$page = $store->capture(['PAGE'=>1,'PAGES_COUNT'=>3,'PRICES'=>[$row,$other]],$criteria,$ref,1,1001,$resolver);
$before = json_encode([$page,$state], JSON_THROW_ON_ERROR);
$request = ['generation'=>1,'params'=>['countryId'=>4,'dateFrom'=>'2026-09-22','dateTo'=>'2026-09-22']];
$read = static function(?array $params = null) use ($pdo,$request,$page): array {
    $r = $request;
    if ($params !== null) $r['params'] = array_replace($r['params'],$params);
    $pdo->mappingReads = 0;
    $result = anytour_andromeda_search3_project($r,$pdo,$page);
    verify($pdo->mappingReads === 1, 'one_batch_read_not_one_per_offer');
    verify($result['received_offers'] === 2 && $result['pages_count'] === 3, 'retention_counts_preserved');
    return $result;
};
$initial = $read();
verify(count($initial['hotels']) === 1 && count($initial['hotels'][0]['tours']) === 2, 'accepted_retained_offers_visible');
$pdo->exec("UPDATE andromeda_hotel_identities SET decision_status='pending' WHERE external_hotel_id='3414'");
$revoked = $read();
verify($revoked['mapped_offers'] === 1 && count($revoked['hotels'][0]['tours']) === 1, 'revoked_offer_removed_from_listing');
verify($revoked['hotels'][0]['tours'][0]['offer_ref'] === $page['offers'][1]['offer_ref'], 'equal_price_does_not_rebind_revoked_context');
verify($revoked['hotels'][0]['tours'][0]['operator'] === $page['offers'][1]['operator'], 'remaining_operator_preserved');
verify(!anytour_andromeda_search3_mapping_allows($pdo,4,$page['offers'][0]), 'detail_and_listing_agree');
verify(anytour_andromeda_search3_mapping_allows($pdo,4,$page['offers'][1]), 'unaffected_detail_retained');
verify($read() === $revoked, 'repeat_read_stable');
verify($read(['hotelIds'=>[901]])['hotels'] === [], 'local_filter_remains_local');
$pdo->exec("UPDATE andromeda_hotel_identities SET decision_status='accepted',local_hotel_id=901 WHERE external_hotel_id='3414'");
$reassigned = $read();
verify($reassigned['mapped_offers'] === 1 && $reassigned['hotels'][0]['local_id'] === 900, 'no_silent_reassignment');
$pdo->exec("UPDATE andromeda_hotel_identities SET local_hotel_id=900 WHERE external_hotel_id='3414'");
$pdo->exec("INSERT INTO andromeda_hotel_identities VALUES ('andromeda_catalog','3414',901,'accepted')");
verify($read()['mapped_offers'] === 1, 'duplicate_target_outside_visible_set_rejected');
$pdo->exec("DELETE FROM andromeda_hotel_identities WHERE local_hotel_id=901");
$pdo->exec("INSERT INTO andromeda_hotel_identities VALUES ('other_namespace','3414',901,'accepted')");
verify($read()['mapped_offers'] === 2, 'namespace_collision_not_identity');
$pdo->exec('UPDATE catalog_hotels SET is_active=0 WHERE id=900');
verify($read()['hotels'] === [], 'inactive_hotel_removed');
$pdo->exec('UPDATE catalog_hotels SET is_active=1,country_id=1 WHERE id=900');
verify($read()['hotels'] === [], 'wrong_country_removed');
$pdo->exec('UPDATE catalog_hotels SET country_id=4 WHERE id=900');
verify($read() === $initial, 'restored_mapping_reuses_original_context');
$many = $page;
$many['offers'] = [];
for ($i=0;$i<40;$i++) $many['offers'][] = array_replace($page['offers'][0], ['offer_ref'=>'offer_'.hash('sha256',(string)$i)]);
$pdo->mappingReads = 0;
$manyResult = anytour_andromeda_search3_project($request,$pdo,$many);
verify($pdo->mappingReads === 1 && $manyResult['mapped_offers'] === 40, 'batch_cost_independent_of_offer_count');
$empty = $page;
$empty['offers'] = [];
$pdo->mappingReads = 0;
verify(anytour_andromeda_search3_project($request,$pdo,$empty)['hotels'] === [] && $pdo->mappingReads === 0, 'empty_page_no_mapping_query');
$pdo->failMapping = true;
try {
    $read();
    throw new LogicException('mapping_failure_must_not_publish_cached_identity');
} catch (PDOException $expected) {}
verify(json_encode([$page,$state], JSON_THROW_ON_ERROR) === $before, 'private_snapshot_and_observations_not_mutated');
echo "Retained mapping: accepted/revoked/reassigned/duplicate/namespace/country/active/equal-price/filters/retention/batch/fail-closed passed offline.\n";
