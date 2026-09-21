<?php
declare(strict_types=1);

/**
 * Recover an exact retained native reference from a CURRENT LOCAL store row.
 *
 * Server-only: $row is an item from AnyTourOfferStoreReadV2, never a client body.
 * The caller must read the current row for the authorized trip/scope, retain the
 * provider's existing lock and enforce session/CSRF before any quote operation.
 * This performs no I/O, extends no TTL and grants no price/booking authority.
 */
final class AnyTourStoredProviderOfferContext
{
    private const DIGESTS = ['search_ref_digest', 'offer_ref_digest', 'provider_hotel_ref_digest'];
    private const MAX_OFFERS = 5000;

    /** Return PRIVATE native package context; never JSON-encode this result for a browser. */
    public static function resolveAndromeda(array $row, array $state, callable $canonicalAllows,
        callable $mappingAllows, int $now): array
    {
        $context = self::andromeda($row, $state, $canonicalAllows, $now);
        require_once __DIR__ . '/andromeda-selected-offer.php';
        $store = new AnyTourAndromedaOfferStore($state, true);
        return AnyTourAndromedaSelectedOffer::resolve($store, $context, $mappingAllows, $now);
    }

    /** Reuse the existing saved read, with the caller's current registry and without a client call. */
    public static function resolveAnex(array $row, array $session, AnyTourAnexPreviewGateway $gateway,
        callable $canonicalAllows, int $now): array
    {
        $request = self::anex($row, $session, $canonicalAllows, $now);
        // handle() maintains session counters/expiry even for reads. Work on this
        // local value copy: never renew the retained background source or its TTL.
        $resolved = $gateway->handle($request, $session);
        if (($resolved['status'] ?? null) !== 'current') self::fail('NATIVE_UNAVAILABLE');
        return ['context' => $request, 'resolved' => $resolved];
    }

    public static function andromeda(array $row, array $state, callable $canonicalAllows, int $now): array
    {
        $identity = self::row($row, 'andromeda', $canonicalAllows, $now);
        if (($state['version'] ?? null) !== 1) self::fail('SNAPSHOT');
        self::lifetime($state, $now);
        $ref = $state['search_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/\A[a-f0-9]{64}\z/D', $ref)
            || !is_int($state['generation'] ?? null) || $state['generation'] < 1) self::fail('SNAPSHOT');
        self::sameDigest($identity['search_ref_digest'], $ref);
        $page = $state['snapshot'] ?? null;
        if (!is_array($page) || !is_int($page['page'] ?? null) || $page['page'] < 1
            || $page['page'] > 1000 || !is_array($state['criteria'] ?? null)
            || ($state['criteria']['PAGE'] ?? 1) !== $page['page']) self::fail('SNAPSHOT');
        $offer = self::find($page['offers'] ?? null, 'offer_ref', $identity['offer_ref_digest']);
        if (($offer['provider'] ?? null) !== 'andromeda' || ($offer['search_ref'] ?? null) !== $ref
            || ($offer['generation'] ?? null) !== $state['generation']
            || !is_string($offer['offer_ref'] ?? null)
            || !preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $offer['offer_ref'])
            || !is_string($offer['supplier_namespace'] ?? null)
            || !preg_match('/\A(?:andromeda_catalog|operator_[1-9][0-9]*)\z/D', $offer['supplier_namespace'])
            || !is_string($offer['operator_ref'] ?? null) || $offer['operator_ref'] === '') self::fail('SNAPSHOT');
        $external = self::nativeId($offer['external_hotel_id'] ?? null);
        self::sameDigest($identity['provider_hotel_ref_digest'], $offer['supplier_namespace'] . ':' . $external);
        if (($offer['local_hotel_id'] ?? null) !== $row['legacyHotelId']) self::fail('HOTEL_MISMATCH');
        $raw = $state['raw_ids'][$offer['offer_ref']] ?? null;
        if (!is_string($raw) || $raw === '') self::fail('NATIVE_REFERENCE_MISSING');
        $criteria = $state['criteria'];
        $ages = self::andromedaAges($criteria, $offer['children'] ?? null);
        self::tour($row, $offer['check_in'] ?? null, $offer['nights'] ?? null,
            $offer['adults'] ?? null, $offer['children'] ?? null, $ages,
            $offer['meal']['raw_label'] ?? null, $offer['room_raw'] ?? null, $offer['placement_raw'] ?? null);
        if ((string)($criteria['ADULT'] ?? '') !== (string)$offer['adults']
            || (string)($criteria['CHILD'] ?? '') !== (string)$offer['children']) self::fail('TRIP_MISMATCH');
        return ['provider' => 'andromeda', 'search_ref' => $ref, 'generation' => $state['generation'],
            'page' => $page['page'], 'offer_ref' => $offer['offer_ref'],
            'hotel_scope' => $criteria['HOTELS'] ?? null, 'operator_ref' => $offer['operator_ref']];
    }

    public static function anex(array $row, array $session, callable $canonicalAllows, int $now): array
    {
        $identity = self::row($row, 'anex', $canonicalAllows, $now);
        $saved = $session['saved_offers'] ?? null;
        if (!is_array($saved) || !is_array($session['search'] ?? null)) self::fail('SNAPSHOT');
        self::lifetime($saved, $now);
        if (!is_int($session['expires_at'] ?? null) || $session['expires_at'] <= $now) self::fail('EXPIRED');
        $ref = $saved['search_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/\A[a-f0-9]{32}\z/D', $ref)) self::fail('SNAPSHOT');
        self::sameDigest($identity['search_ref_digest'], $ref);
        $entries = $saved['offers'] ?? null;
        if (!is_array($entries) || count($entries) > self::MAX_OFFERS) self::fail('SNAPSHOT');
        $offers = [];
        foreach ($entries as $key => $entry) {
            if (!is_array($entry) || !is_array($entry['offer'] ?? null)
                || ($entry['offer']['offer_key'] ?? null) !== $key) self::fail('SNAPSHOT');
            $offers[] = $entry['offer'];
        }
        $offer = self::find($offers, 'offer_key', $identity['offer_ref_digest']);
        if (($offer['provider'] ?? null) !== 'anex' || ($offer['supplier_namespace'] ?? null) !== 'anex_online'
            || !is_string($offer['offer_key'] ?? null)
            || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $offer['offer_key'])) self::fail('SNAPSHOT');
        if (($offer['kind'] ?? null) !== 'concrete') self::fail('CONCRETE_REQUIRED');
        $external = self::nativeId($offer['hotel']['external_id'] ?? null);
        self::sameDigest($identity['provider_hotel_ref_digest'], 'anex_online:' . $external);
        if (($offer['hotel']['local_id'] ?? null) !== $row['legacyHotelId']) self::fail('HOTEL_MISMATCH');
        $known = self::find($session['search']['offers'] ?? null, 'offer_key', $identity['offer_ref_digest']);
        if (($known['hotel_external_id'] ?? null) !== $external || ($known['kind'] ?? null) !== 'concrete') self::fail('SNAPSHOT');
        $entry = $entries[$offer['offer_key']];
        if (!is_int($entry['observed_at'] ?? null) || $entry['observed_at'] < $saved['created_at']
            || $entry['observed_at'] > $now) self::fail('SNAPSHOT');
        $ages = $saved['search']['child_ages'] ?? null;
        self::tour($row, $offer['checkin'] ?? null, $offer['nights'] ?? null,
            $offer['adults'] ?? null, $offer['children'] ?? null, $ages,
            $offer['meal'] ?? null, $offer['room'] ?? null, $offer['hotel_place'] ?? null);
        if (($saved['search']['adults'] ?? null) !== $offer['adults']
            || ($saved['search']['children'] ?? null) !== $offer['children']) self::fail('TRIP_MISMATCH');
        return ['action' => 'offer', 'search_ref' => $ref,
            'offer_key' => $offer['offer_key'], 'local_hotel_id' => $row['legacyHotelId']];
    }

    private static function row(array $row, string $provider, callable $allows, int $now): array
    {
        $listing = $row['offer'] ?? null;
        if ($now < 1 || ($row['provider'] ?? null) !== $provider
            || !is_int($row['anytourHotelId'] ?? null) || $row['anytourHotelId'] < 1
            || !is_int($row['legacyHotelId'] ?? null) || $row['legacyHotelId'] < 1
            || !is_array($listing) || ($listing['schema_version'] ?? null) !== 1
            || ($listing['provider'] ?? null) !== $provider
            || ($listing['selection_state'] ?? null) !== 'refresh_required'
            || ($listing['booking_enabled'] ?? null) !== false) self::fail('ROW');
        self::digest($row['sourceScopeDigest'] ?? null);
        $expires = $row['expiresAt'] ?? null;
        $time = is_string($expires) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $expires, new DateTimeZone('UTC')) : false;
        if (!$time || $time->format('Y-m-d\TH:i:s\Z') !== $expires) self::fail('ROW');
        if ($time->getTimestamp() <= $now) self::fail('EXPIRED');
        $id = $listing['identity'] ?? null;
        if (!is_array($id) || count($id) !== 3 || array_diff(self::DIGESTS, array_keys($id))) self::fail('IDENTITY');
        foreach ($id as $digest) self::digest($digest);
        try { $ok = $allows($provider, $id['provider_hotel_ref_digest'], $row['legacyHotelId'], $row['anytourHotelId']); }
        catch (Throwable $ignored) { $ok = false; }
        if ($ok !== true) self::fail('CANONICAL_IDENTITY');
        return $id;
    }

    private static function tour(array $row, mixed $date, mixed $nights, mixed $adults, mixed $children,
        mixed $ages, mixed $meal, mixed $room, mixed $placement): void
    {
        $tour = $row['offer']['tour'] ?? null;
        $party = is_array($tour) ? ($tour['party'] ?? null) : null;
        if (!is_array($tour) || !is_array($party) || !is_string($date)
            || !is_int($nights) || $nights < 1 || !is_int($adults) || $adults < 1
            || !is_int($children) || $children < 0
            || ($tour['checkin'] ?? null) !== $date || ($tour['nights'] ?? null) !== $nights
            || ($party['adults'] ?? null) !== $adults || ($party['children'] ?? null) !== $children
            || self::ages($party['child_ages'] ?? null, $children) !== self::ages($ages, $children)
            || self::label($tour['meal']['raw'] ?? null) !== self::label($meal)
            || self::label($tour['room']['raw'] ?? null) !== self::label($room)
            || self::label($tour['placement']['raw'] ?? null, true) !== self::label($placement, true)) self::fail('TRIP_MISMATCH');
    }

    private static function andromedaAges(array $criteria, mixed $children): array
    {
        $raw = $criteria['AGES'] ?? '';
        if (!is_string($raw)) self::fail('TRIP_MISMATCH');
        if ($raw === '' && $children === 0) return [];
        if (!preg_match('/\A(?:0|[1-9][0-9]?)(?:,(?:0|[1-9][0-9]?))*\z/D', $raw)) self::fail('TRIP_MISMATCH');
        return array_map('intval', explode(',', $raw));
    }
    private static function ages(mixed $ages, int $children): array
    {
        if (!is_array($ages) || !array_is_list($ages) || count($ages) !== $children) self::fail('TRIP_MISMATCH');
        foreach ($ages as $age) if (!is_int($age) || $age < 0 || $age > 17) self::fail('TRIP_MISMATCH');
        sort($ages, SORT_NUMERIC);
        return $ages;
    }
    private static function label(mixed $value, bool $nullable = false): string
    {
        if ($nullable && $value === null) return '';
        if (!is_string($value) || (!$nullable && trim($value) === '')) self::fail('TRIP_MISMATCH');
        return trim($value);
    }
    private static function find(mixed $offers, string $key, string $digest): array
    {
        if (!is_array($offers) || count($offers) > self::MAX_OFFERS) self::fail('SNAPSHOT');
        $match = null;
        foreach ($offers as $offer) {
            if (!is_array($offer) || !is_string($offer[$key] ?? null)) self::fail('SNAPSHOT');
            if (!hash_equals($digest, hash('sha256', $offer[$key]))) continue;
            if ($match !== null) self::fail('AMBIGUOUS');
            $match = $offer;
        }
        if ($match === null) self::fail('NOT_FOUND');
        return $match;
    }
    private static function lifetime(array $state, int $now): void
    {
        if (!is_int($state['created_at'] ?? null) || !is_int($state['expires_at'] ?? null)
            || $state['expires_at'] !== $state['created_at'] + 900) self::fail('SNAPSHOT');
        if ($now < $state['created_at'] || $now >= $state['expires_at']) self::fail('EXPIRED');
    }
    private static function digest(mixed $value): void
    {
        if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/D', $value)) self::fail('IDENTITY');
    }
    private static function nativeId(mixed $value): string
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[1-9][0-9]*\z/D', (string)$value)) self::fail('SNAPSHOT');
        return (string)$value;
    }
    private static function sameDigest(string $digest, string $raw): void
    {
        if (!hash_equals($digest, hash('sha256', $raw))) self::fail('IDENTITY_MISMATCH');
    }
    private static function fail(string $reason): never { throw new RuntimeException('STORED_PROVIDER_' . $reason); }
}
