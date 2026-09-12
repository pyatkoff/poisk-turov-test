<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/three-provider-anex-offer.php';
require_once __DIR__ . '/../app/integrations/three-provider-offer-context.php';

// Synthetic identifiers/amounts, based on the existing normalizer fixture shape.
// This is adapter integration coverage, not new live supplier evidence.
set_error_handler(static function ($severity, $message) { throw new RuntimeException($message); });
$checks = 0;
function bridge_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('bridge_check_' . $checks); }
function bridge_page(array $rowChanges = [], array $searchChanges = []): array
{
    $row = array_replace([
        'id' => 'fixture-private-offer-1', 'hotelKey' => 30160, 'hotel' => 'Fixture Hotel',
        'star' => '3*', 'meal' => 'BB', 'room' => 'Standard-Room', 'htPlace' => 'DBL / 2 ADL',
        'checkIn' => '20260914', 'checkOut' => '20260921', 'nights' => 7,
        'adult' => 2, 'child' => 0, 'packetType' => 0, 'price' => '790.00', 'currency' => 'EUR',
        'convertedPrice' => '84 617.00 RUB', 'grouped' => 0, 'bron' => 1,
        'hotelAvailability' => 'YYYY', 'freights' => ['econom' => ['in' => 'Y', 'out' => 'R']],
    ], $rowChanges);
    $search = array_replace([
        'checkin_begin' => '20260914', 'checkin_end' => '20260916',
        'nights_from' => 7, 'nights_till' => 10, 'adults' => 2, 'children' => 0, 'child_ages' => [],
        'departure_id' => 1, 'destination_id' => 2, 'currency_id' => 3,
    ], $searchChanges);
    return anytour_anex_normalize_prices(['SearchTour_PRICES' => ['prices' => [$row]]], $search,
        static function (string $namespace, string $id): ?int { return $namespace === 'anex_online' && $id === '30160' ? 40430 : null; });
}
function bridge_identity(?int $id = 40430): array
{
    return ['supplier_namespace' => 'anex_online', 'external_id' => '30160', 'local_id' => $id];
}
function bridge_offer(array $page, ?array $identity = null, int $index = 0, array $additionalPricesReported = []): array
{
    return AnyTourThreeProviderAnexOffer::fromPage($page, $index, $identity ?? bridge_identity(),
        'fixture-private-search', '2026-09-11T20:00:00Z', $additionalPricesReported);
}
function bridge_reject(callable $call, string $code, string $class = InvalidArgumentException::class): void
{
    try { $call(); } catch (Throwable $error) {
        bridge_check(get_class($error) === $class && $error->getMessage() === $code);
        return;
    }
    bridge_check(false);
}

$page = bridge_page();
$before = $page;
$dto = bridge_offer($page);
bridge_check($page === $before);
bridge_check($dto['provider'] === 'anex' && $dto['local_hotel_id'] === 40430);
bridge_check($dto['operator']['raw'] === null && $dto['operator']['canonical_verified'] === false);
bridge_check($dto['money']['search_price'] === ['amount' => '790.00', 'currency' => 'EUR', 'source' => 'anex_search']);
bridge_check($dto['money']['fuel_charge_reported'] === null && $dto['money']['additional_prices_reported'] === []);
bridge_check($dto['money']['quote_price'] === null && $dto['money']['package_buyer_price'] === null);
bridge_check($dto['money']['arithmetic_applied'] === false && $dto['money']['search_price_fuel_relation'] === 'unknown');

// Canonical additional-price fixture only: it does not assign semantics to the live v4 currency id.
$verifiedAdditionalFixture = [
    ['kind' => 'air_adult', 'amount' => '120', 'currency' => 'USD', 'source' => 'anex_additional'],
    ['kind' => 'air_child', 'amount' => '120', 'currency' => 'USD', 'source' => 'anex_additional'],
];
$withAdditional = bridge_offer($page, null, 0, $verifiedAdditionalFixture);
bridge_check($withAdditional['money']['additional_prices_reported'] === $verifiedAdditionalFixture);
bridge_check($withAdditional['money']['search_price'] === $dto['money']['search_price']);
bridge_check($withAdditional['money']['fuel_charge_reported'] === null
    && $withAdditional['money']['arithmetic_applied'] === false
    && $withAdditional['money']['search_price_fuel_relation'] === 'unknown');
bridge_reject(static fn () => bridge_offer($page, null, 0, [
    ['kind' => 'air_adult', 'amount' => '120', 'currency' => 'USD', 'source' => 'anex_search'],
]), 'THREE_PROVIDER_MONEY_SOURCE');

bridge_check($dto['meal']['raw'] === 'BB' && $dto['meal']['family'] === 'bb');
bridge_check($dto['room']['raw'] === 'Standard-Room' && $dto['room']['normalized'] === 'standard room');
bridge_check($dto['placement']['raw'] === 'DBL / 2 ADL' && $dto['placement']['normalized'] === 'dbl 2 adl');
bridge_check($dto['room']['comparison_scope'] === 'display_label_only');
bridge_check($dto['availability']['hotel']['raw'] === 'YYYY' && $dto['availability']['hotel']['canonical_state'] === 'unknown');
bridge_check($dto['availability']['flight_outbound_economy']['raw'] === 'Y'
    && $dto['availability']['flight_return_economy']['raw'] === 'R');
bridge_check($dto['availability']['selection_eligible'] === false && $dto['availability']['booking_eligible'] === false);
bridge_check($dto['flight_details']['details_state'] === 'not_loaded' && $dto['flight_details']['automatic_fetch_allowed'] === false);
bridge_check($dto['selection_state'] === 'disabled' && $dto['quote_state'] === 'unknown' && $dto['final_price_verified'] === false);
bridge_check($dto['observed_at'] === '2026-09-11T20:00:00Z');
bridge_check($dto['identity']['offer_ref_digest'] === hash('sha256', $page['offers'][0]['offer_key']));
bridge_check($dto['identity']['provider_hotel_ref_digest'] === hash('sha256', 'anex_online:30160'));
bridge_check($dto['identity']['search_ref_digest'] === hash('sha256', 'fixture-private-search'));

$extra = $page;
$extra['offers'][0]['claiminc'] = 'fixture-private-claim';
$extra['offers'][0]['url'] = 'https://private.invalid/fixture';
$extra['offers'][0]['operator'] = 'Not proven by this source';
$extra['offers'][0]['quote_price'] = ['amount' => '1', 'currency' => 'RUB'];
bridge_check(bridge_offer($extra) === $dto);
$json = json_encode($dto, JSON_THROW_ON_ERROR);
foreach (['supplier_offer_id', 'fixture-private-', 'claiminc', 'private.invalid', 'converted_price', 'supplier_booking_flag'] as $private) {
    bridge_check(strpos($json, $private) === false);
}

// An old accepted snapshot never overrides CURRENT null or a changed local target.
bridge_check(bridge_offer($page, bridge_identity(null))['local_hotel_id'] === null);
bridge_check(bridge_offer($page, bridge_identity(50505))['local_hotel_id'] === 50505);
$unmappedSnapshot = $page;
$unmappedSnapshot['offers'][0]['hotel']['local_id'] = null;
$unmappedSnapshot['offers'][0]['hotel']['mapping_status'] = 'unmapped';
bridge_check(bridge_offer($unmappedSnapshot)['local_hotel_id'] === 40430);

$variants = [
    ['meal' => 'AI without alcohol'], ['meal' => 'HB+'], ['meal' => 'UAI'],
    ['meal' => 'Premium Concept'], ['meal' => '7'], ['htPlace' => null],
    ['price' => '999999999999.99', 'currency' => 'RUB'],
    ['hotelAvailability' => null, 'freights' => null],
];
foreach ($variants as $change) {
    $p = bridge_page($change);
    $v = bridge_offer($p);
    bridge_check($v['money']['search_price']['amount'] === $p['offers'][0]['price']['amount']);
    bridge_check($v['money']['search_price']['currency'] === $p['offers'][0]['price']['currency']);
    bridge_check($v['placement'] === null || $v['placement']['raw'] === $p['offers'][0]['hotel_place']);
    bridge_check($v['meal']['family'] === AnyTourThreeProviderMealFamily::normalize($p['offers'][0]['meal'])['family']);
    bridge_check($v['meal']['qualifiers'] === AnyTourThreeProviderMealFamily::normalize($p['offers'][0]['meal'])['qualifiers']);
}
$childPage = bridge_page(['child' => 2], ['children' => 2, 'child_ages' => [7, 11]]);
bridge_check(bridge_offer($childPage)['party'] === ['adults' => 2, 'children' => 2, 'child_ages' => [7, 11]]);

bridge_reject(static fn () => bridge_offer(bridge_page(['grouped' => 1])),
    'THREE_PROVIDER_ANEX_CONCRETE_REQUIRED', DomainException::class);
foreach ([-1, 1, 300] as $index) bridge_reject(static fn () => bridge_offer($page, null, $index), 'THREE_PROVIDER_ANEX_PAGE');
foreach (['schema_version' => '1', 'provider' => 'tourvisor', 'supplier_namespace' => 'anex_xml', 'search' => null,
    'offers' => ['bad-key' => $page['offers'][0]]] as $key => $bad) {
    $p = $page; $p[$key] = $bad;
    bridge_reject(static fn () => bridge_offer($p), 'THREE_PROVIDER_ANEX_PAGE');
}
$tooMany = $page; $tooMany['offers'] = array_fill(0, 301, $page['offers'][0]);
bridge_reject(static fn () => bridge_offer($tooMany), 'THREE_PROVIDER_ANEX_PAGE');
foreach (['supplier_namespace' => 'andromeda_catalog', 'external_id' => '999', 'extra' => true] as $key => $bad) {
    $identity = bridge_identity(); $identity[$key] = $bad;
    bridge_reject(static fn () => bridge_offer($page, $identity), 'THREE_PROVIDER_ANEX_IDENTITY');
}
bridge_reject(static fn () => bridge_offer($page, bridge_identity(0)), 'THREE_PROVIDER_OFFER_LOCAL_ID');
foreach (['provider' => 'andromeda', 'kind' => 'invented', 'final_price_verified' => true, 'price' => null, 'availability' => null] as $key => $bad) {
    $p = $page; $p['offers'][0][$key] = $bad;
    bridge_reject(static fn () => bridge_offer($p), 'THREE_PROVIDER_ANEX_OFFER');
}
foreach (['adults' => 3, 'children' => 1, 'nights' => 11, 'checkin' => '2026-09-17', 'infants' => 1] as $key => $bad) {
    $p = $page; $p['offers'][0][$key] = $bad;
    bridge_reject(static fn () => bridge_offer($p), 'THREE_PROVIDER_ANEX_SEARCH_CONTEXT');
}
$p = $page; $p['offers'][0]['offer_key'] = 'fixture-private-unhashed';
bridge_reject(static fn () => bridge_offer($p), 'THREE_PROVIDER_ANEX_REFERENCE');
$p = $page; $p['offers'][0]['hotel']['external_id'] = '030160';
bridge_reject(static fn () => bridge_offer($p), 'THREE_PROVIDER_ANEX_IDENTITY');
$p = $page; unset($p['offers'][0]['hotel_place']);
bridge_reject(static fn () => bridge_offer($p), 'THREE_PROVIDER_ANEX_OFFER');
bridge_reject(static fn () => bridge_offer(bridge_page(['room' => null])), 'THREE_PROVIDER_ROOM_LABEL');
bridge_reject(static fn () => bridge_offer(bridge_page(['meal' => null])), 'THREE_PROVIDER_MEAL_LABEL');
$p = $page; $p['offers'][0]['price']['amount'] = 790.0;
bridge_reject(static fn () => bridge_offer($p), 'THREE_PROVIDER_MONEY_AMOUNT');
$p = $page; $p['search']['child_ages'] = [7];
bridge_reject(static fn () => bridge_offer($p), 'invalid_anex_search_context');
bridge_reject(static fn () => AnyTourThreeProviderAnexOffer::fromPage($page, 0, bridge_identity(), '', '2026-09-11T20:00:00Z'),
    'THREE_PROVIDER_OFFER_SEARCH_REF');
bridge_reject(static fn () => AnyTourThreeProviderAnexOffer::fromPage($page, 0, bridge_identity(), 's', '2026-02-30T20:00:00Z'),
    'THREE_PROVIDER_OFFER_TIMESTAMP');

// Normalizer -> bridge -> retained context: A cannot validate as B or a new search.
$two = $page;
$two['offers'][] = bridge_page(['id' => 'fixture-private-offer-2', 'price' => '800.00'])['offers'][0];
$b = bridge_offer($two, null, 1);
bridge_check($b['identity']['offer_ref_digest'] !== $dto['identity']['offer_ref_digest']);
$now = 1789156800;
$retained = AnyTourThreeProviderOfferContext::retain($dto, 7, 1, $now);
$current = array_intersect_key($retained, array_flip(['provider', 'operator', 'local_hotel_id', 'identity', 'generation', 'page']));
bridge_check(AnyTourThreeProviderOfferContext::validate($retained, $current, $now)['status'] === 'current');
foreach ([['identity' => $b['identity']], ['local_hotel_id' => 50505], ['generation' => 8], ['page' => 2],
    ['operator' => AnyTourThreeProviderOperator::fromSearch('anex', 'ANEX')]] as $change) {
    bridge_check(AnyTourThreeProviderOfferContext::validate($retained, array_replace($current, $change), $now)['status'] === 'mismatch');
}
$forgedOperator = $current;
$forgedOperator['operator']['canonical_verified'] = true;
bridge_reject(static fn () => AnyTourThreeProviderOfferContext::validate($retained, $forgedOperator, $now),
    'THREE_PROVIDER_CONTEXT_OPERATOR');
$fresh = AnyTourThreeProviderAnexOffer::fromPage($page, 0, bridge_identity(), 'new-search', '2026-09-11T20:00:00Z');
bridge_check(AnyTourThreeProviderOfferContext::validate($retained, array_replace($current, ['identity' => $fresh['identity']]), $now)['status'] === 'mismatch');
bridge_check(AnyTourThreeProviderOfferContext::validate($retained, $current, $now + 900)['status'] === 'expired');
bridge_check(AnyTourThreeProviderOfferContext::validate($retained, $current, $now)['selection_state'] === 'disabled');
restore_error_handler();
echo 'Three-provider ANEX bridge: ' . $checks . " checks passed; live/DB/mapping/selection=0.\n";
