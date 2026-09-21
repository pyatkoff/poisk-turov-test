<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/stored-provider-offer-context.php';

// Fictional retained pages and CURRENT-reader shaped rows, no network or database.
$checks = 0;
function check(bool $ok, string $name): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($name); }
function rejects(callable $fn, string $code): void {
    try { $fn(); } catch (RuntimeException $e) { check($e->getMessage() === 'STORED_PROVIDER_' . $code, 'Wrong refusal: ' . $e->getMessage()); return; }
    throw new RuntimeException('Expected refusal: ' . $code);
}
$now = 1800000000;
$proofCalls = [];
$allows = static function ($provider, $digest, $legacy, $own) use (&$proofCalls): bool {
    $proofCalls[] = [$provider, $digest, $legacy, $own];
    return $legacy === 101 && $own === 4234;
};
function fixture(string $provider, int $now): array {
    $ref = str_repeat('a', $provider === 'anex' ? 32 : 64);
    $keys = $provider === 'anex' ? ['anex_online:' . str_repeat('b', 64), 'anex_online:' . str_repeat('c', 64)]
        : ['offer_' . str_repeat('b', 64), 'offer_' . str_repeat('c', 64)];
    $external = $provider === 'anex' ? 'anex_online:500' : 'operator_315:500';
    $room = 'DELUXE SEA VIEW';
    $party = ['adults' => 2, 'children' => 2, 'child_ages' => [0, 17]];
    $tour = ['checkin' => '2027-01-25', 'nights' => 7, 'party' => $party,
        'meal' => ['raw' => 'AI'], 'room' => ['raw' => $room], 'placement' => ['raw' => 'DBL+2CHD']];
    $row = ['anytourHotelId' => 4234, 'legacyHotelId' => 101, 'provider' => $provider,
        'sourceScopeDigest' => str_repeat('e', 64), 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $now + 86400),
        'price' => '133500.50', 'currency' => 'RUB', 'offer' => [
            'schema_version' => 1, 'provider' => $provider, 'selection_state' => 'refresh_required', 'booking_enabled' => false,
            'identity' => ['search_ref_digest' => hash('sha256', $ref), 'offer_ref_digest' => hash('sha256', $keys[1]),
                'provider_hotel_ref_digest' => hash('sha256', $external)],
            'tour' => $tour, 'listingPriceState' => 'search_price_confirmation_required',
            'listingPrice' => '133500.50', 'finalPriceVerified' => false]];
    if ($provider === 'andromeda') {
        $offers = [];
        foreach ($keys as $i => $key) $offers[] = ['provider' => 'andromeda', 'search_ref' => $ref, 'generation' => 7,
            'offer_ref' => $key, 'supplier_namespace' => 'operator_315', 'external_hotel_id' => '500', 'local_hotel_id' => 101,
            'operator_ref' => '315', 'check_in' => $tour['checkin'], 'nights' => 7, 'adults' => 2, 'children' => 2,
            'room_raw' => $i === 0 ? 'STANDARD' : $room, 'placement_raw' => 'DBL+2CHD', 'meal' => ['raw_label' => 'AI'],
            'price' => ['amount' => $i === 0 ? '99000' : '133500.50']];
        $state = ['version' => 1, 'search_ref' => $ref, 'generation' => 7, 'created_at' => $now - 100,
            'expires_at' => $now + 800, 'snapshot' => ['page' => 2, 'offers' => $offers],
            'criteria' => ['PAGE' => 2, 'ADULT' => 2, 'CHILD' => 2, 'AGES' => '0,17', 'HOTELS' => '500'],
            'raw_ids' => [$keys[0] => 'private-package-A', $keys[1] => 'private-package-B']];
    } else {
        $entries = []; $known = [];
        foreach ($keys as $i => $key) {
            $offer = ['provider' => 'anex', 'supplier_namespace' => 'anex_online', 'offer_key' => $key, 'kind' => 'concrete',
                'hotel' => ['external_id' => '500', 'local_id' => 101], 'checkin' => $tour['checkin'], 'nights' => 7,
                'adults' => 2, 'children' => 2, 'meal' => 'AI', 'room' => $i === 0 ? 'STANDARD' : $room, 'hotel_place' => 'DBL+2CHD'];
            $entries[$key] = ['observed_at' => $now - 50, 'offer' => $offer];
            $known[] = ['offer_key' => $key, 'kind' => 'concrete', 'hotel_external_id' => '500'];
        }
        $state = ['expires_at' => $now + 800, 'search' => ['offers' => $known], 'saved_offers' => [
            'search_ref' => $ref, 'created_at' => $now - 100, 'expires_at' => $now + 800,
            'search' => ['adults' => 2, 'children' => 2, 'child_ages' => [0, 17]], 'offers' => $entries]];
    }
    return [$row, $state, $keys];
}
foreach (['anex', 'andromeda'] as $provider) {
    [$row, $state, $keys] = fixture($provider, $now);
    $run = static fn(array $r, array $s, ?callable $proof = null, ?int $time = null) =>
        AnyTourStoredProviderOfferContext::$provider($r, $s, $proof ?? $allows, $time ?? $now);
    $before = serialize([$row, $state]);
    $context = $run($row, $state);
    check(($context['offer_ref'] ?? $context['offer_key']) === $keys[1], 'Must find second exact offer, not the cheaper same hotel');
    check(serialize([$row, $state]) === $before, 'Read-only: original rows/snapshot/prices must be unchanged');
    check(!str_contains(json_encode($context), 'private-package-'), 'Private supplier IDs must not enter context output');
    check(!isset($context['price']) && !isset($context['finalPriceVerified']) && !isset($context['selection_enabled']), 'Lookup never fabricates quote/readiness');
    check(end($proofCalls)[2] === 101 && end($proofCalls)[3] === 4234, 'Canonical proof keeps legacy and own namespaces distinct');
    $bad = $row; $bad['provider'] = 'tourvisor'; rejects(fn() => $run($bad, $state), 'ROW');
    $bad = $row; $bad['offer']['provider'] = 'wrong'; rejects(fn() => $run($bad, $state), 'ROW');
    $bad = $row; $bad['offer']['booking_enabled'] = true; rejects(fn() => $run($bad, $state), 'ROW');
    $bad = $row; $bad['offer']['selection_state'] = 'enabled'; rejects(fn() => $run($bad, $state), 'ROW');
    $bad = $row; $bad['offer']['identity']['offer_ref_digest'] = '../not-an-id'; rejects(fn() => $run($bad, $state), 'IDENTITY');
    $bad = $row; $bad['sourceScopeDigest'] = 'broken'; rejects(fn() => $run($bad, $state), 'IDENTITY');
    foreach (['search_ref_digest', 'provider_hotel_ref_digest'] as $key) {
        $bad = $row; $bad['offer']['identity'][$key] = str_repeat('f', 64); rejects(fn() => $run($bad, $state), 'IDENTITY_MISMATCH');
    }
    $bad = $row; $bad['offer']['identity']['offer_ref_digest'] = str_repeat('f', 64); rejects(fn() => $run($bad, $state), 'NOT_FOUND');
    $bad = $row; $bad['anytourHotelId'] = 101; rejects(fn() => $run($bad, $state), 'CANONICAL_IDENTITY');
    rejects(fn() => $run($row, $state, static fn() => false), 'CANONICAL_IDENTITY');
    rejects(fn() => $run($row, $state, static fn() => 1), 'CANONICAL_IDENTITY');
    rejects(fn() => $run($row, $state, static function () { throw new RuntimeException('private diagnostic'); }), 'CANONICAL_IDENTITY');
    $bad = $row; $bad['legacyHotelId'] = 102;
    rejects(fn() => $run($bad, $state, static fn() => true), 'HOTEL_MISMATCH');
    foreach (['checkin' => '2027-01-26', 'nights' => 8] as $key => $value) {
        $bad = $row; $bad['offer']['tour'][$key] = $value; rejects(fn() => $run($bad, $state), 'TRIP_MISMATCH');
    }
    foreach (['adults' => 3, 'children' => 1, 'child_ages' => [1, 17]] as $key => $value) {
        $bad = $row; $bad['offer']['tour']['party'][$key] = $value; rejects(fn() => $run($bad, $state), 'TRIP_MISMATCH');
    }
    foreach (['room' => 'STANDARD', 'meal' => 'HB', 'placement' => 'DBL'] as $key => $value) {
        $bad = $row; $bad['offer']['tour'][$key]['raw'] = $value; rejects(fn() => $run($bad, $state), 'TRIP_MISMATCH');
    }
    $reordered = $row; $reordered['offer']['tour']['party']['child_ages'] = [17, 0];
    check($run($reordered, $state) === $context, 'Age order may differ but ages 0/17 are preserved');
    $bad = $row; $bad['expiresAt'] = gmdate('Y-m-d\TH:i:s\Z', $now); rejects(fn() => $run($bad, $state), 'EXPIRED');
    rejects(fn() => $run($row, $state, null, $now + 800), 'EXPIRED');
    rejects(fn() => $run($row, $state, null, $now - 101), 'EXPIRED');
    $bad = $state;
    if ($provider === 'andromeda') {
        $bad['expires_at']++; rejects(fn() => $run($row, $bad), 'SNAPSHOT');
        $bad = $state; $bad['snapshot']['offers'][] = $bad['snapshot']['offers'][1]; rejects(fn() => $run($row, $bad), 'AMBIGUOUS');
        $bad = $state; $bad['snapshot']['page'] = 1; rejects(fn() => $run($row, $bad), 'SNAPSHOT');
        $bad = $state; $bad['snapshot']['offers'][1]['generation']++; rejects(fn() => $run($row, $bad), 'SNAPSHOT');
        $bad = $state; unset($bad['raw_ids'][$keys[1]]); rejects(fn() => $run($row, $bad), 'NATIVE_REFERENCE_MISSING');
        $bad = $state; $bad['criteria']['AGES'] = '0,18'; rejects(fn() => $run($row, $bad), 'TRIP_MISMATCH');
        $bad = $state; $bad['criteria']['AGES'] = '0,0'; rejects(fn() => $run($row, $bad), 'TRIP_MISMATCH');
        check($context['page'] === 2 && $context['generation'] === 7 && $context['operator_ref'] === '315', 'Original page/generation/operator retained');
    } else {
        $bad['saved_offers']['expires_at']++; rejects(fn() => $run($row, $bad), 'SNAPSHOT');
        $bad = $state; $bad['saved_offers']['offers'][$keys[1]]['offer']['kind'] = 'group_minimum'; rejects(fn() => $run($row, $bad), 'CONCRETE_REQUIRED');
        $bad = $state; $bad['search']['offers'][] = $bad['search']['offers'][1]; rejects(fn() => $run($row, $bad), 'AMBIGUOUS');
        $bad = $state; $bad['saved_offers']['offers'][$keys[1]]['observed_at'] = $now + 1; rejects(fn() => $run($row, $bad), 'SNAPSHOT');
        $bad = $state; $bad['expires_at'] = $now; rejects(fn() => $run($row, $bad), 'EXPIRED');
        check($context['action'] === 'offer' && $context['local_hotel_id'] === 101, 'Unchanged native saved-offer request');
    }
    // A historical verified price cannot revive an expired native context.
    $priced = $row; $priced['offer']['listingPriceState'] = 'final_verified'; $priced['offer']['finalPriceVerified'] = true;
    rejects(fn() => $run($priced, $state, null, $now + 800), 'EXPIRED');
}
if (in_array('--native', $argv, true)) {
    // CI exercises the real existing resolvers, not mock provider classes.
    require_once __DIR__ . '/../app/integrations/andromeda-selected-offer.php';
    require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
    [$row, $state, $keys] = fixture('andromeda', $now);
    $original = serialize($state);
    $mappingCalls = 0;
    $mapping = static function (array $offer) use (&$mappingCalls): bool {
        ++$mappingCalls;
        return $offer['local_hotel_id'] === 101 && $offer['supplier_namespace'] === 'operator_315';
    };
    $resolved = AnyTourStoredProviderOfferContext::resolveAndromeda($row, $state, $allows, $mapping, $now);
    check($resolved['supplier_offer_id'] === 'private-package-B', 'Existing SAMO resolver must return exactly the second native package');
    check($resolved['context']['offer_ref'] === $keys[1], 'Existing native context kept unchanged');
    check($mappingCalls === 1, 'Current native mapping check remains mandatory');
    check(serialize($state) === $original, 'Native SAMO resolution cannot change the retained store');
    try {
        AnyTourStoredProviderOfferContext::resolveAndromeda($row, $state, $allows, static fn() => false, $now);
        throw new LogicException('Current SAMO mapping was bypassed');
    } catch (RuntimeException $e) { check(true, 'Current SAMO mapping refuses changed identity'); }

    [$row, $session, $keys] = fixture('anex', $now);
    $session['saved_offers']['search'] += ['checkin_begin' => '2027-01-25', 'checkin_end' => '2027-01-25', 'nights_from' => 7, 'nights_till' => 7];
    foreach ($session['saved_offers']['offers'] as &$entry) $entry['offer'] += [
        'final_price_verified' => false, 'infants' => 0, 'price' => ['amount' => '133500.50', 'currency' => 'RUB'],
        'availability' => ['hotel' => null, 'flight_outbound_economy' => null, 'flight_return_economy' => null]];
    unset($entry);
    $clientCalls = 0; $registryCalls = 0;
    $client = static function () use (&$clientCalls) { ++$clientCalls; throw new LogicException('Supplier access forbidden'); };
    $registry = static function (string $namespace, string $external) use (&$registryCalls): ?int {
        ++$registryCalls; return $namespace === 'anex_online' && $external === '500' ? 101 : null;
    };
    $gateway = new AnyTourAnexPreviewGateway($client, $registry, [], static fn() => $now);
    $original = serialize($session);
    $resolved = AnyTourStoredProviderOfferContext::resolveAnex($row, $session, $gateway, $allows, $now);
    check($resolved['context']['offer_key'] === $keys[1] && $resolved['resolved']['status'] === 'current', 'Existing ANEX saved read resolves the exact requested row');
    check($resolved['resolved']['offer']['identity'] === $row['offer']['identity'], 'Native ANEX DTO roundtrips all three LOCAL digests');
    check($resolved['resolved']['selection_state'] === 'disabled', 'No quote or booking authority fabricated');
    check($registryCalls === 1 && $clientCalls === 0, 'Current ANEX registry called; token-bearing client never created');
    check(serialize($session) === $original, 'Native ANEX read cannot renew background session lifetime');
    $changed = new AnyTourAnexPreviewGateway($client, static fn() => 102, [], static fn() => $now);
    rejects(fn() => AnyTourStoredProviderOfferContext::resolveAnex($row, $session, $changed, $allows, $now), 'NATIVE_UNAVAILABLE');
    check($clientCalls === 0, 'Failures never start supplier work');
}
echo "Stored native context: $checks checks passed; supplier/DB/lead calls=0; public selection remains disabled.\n";
