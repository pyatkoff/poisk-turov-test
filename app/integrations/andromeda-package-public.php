<?php
declare(strict_types=1);

/**
 * Browser-safe projection of ONE already captured Andromeda package.
 * No supplier call, booking, price arithmetic, persistence or selected-offer lookup.
 * Use current() when identity authority is required: it calls PackageCapture::read(),
 * which revalidates the retained context/mapping before this projection is exposed.
 *
 * Official schema: https://dokuwiki.samo.ru/doku.php?id=andromeda:claim_struct
 */
final class AnyTourAndromedaPackagePublic
{
    private const MAX_PACKAGE_BYTES = 2097152;
    private const MAX_ROWS = 100;

    private static function base(string $status): array
    {
        return [
            'status' => $status,
            'identity_verified' => false,
            'package_binding_verified' => false,
            'quote_verified' => false,
            'selection_enabled' => false,
            'price_status' => 'unknown',
            'requires_external_flights' => null,
        ];
    }

    private static function plain(mixed $value, int $depth = 0): bool
    {
        if ($depth > 32) return false;
        if (is_array($value)) {
            foreach ($value as $child) if (!self::plain($child, $depth + 1)) return false;
            return true;
        }
        return $value === null || is_scalar($value);
    }

    private static function text(mixed $value, int $maxBytes = 240): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxBytes || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $value)) return null;
        return $value;
    }

    private static function integer(mixed $value, int $min = 0, int $max = 9999): ?int
    {
        if (!(is_int($value) || is_string($value))
            || preg_match('/^(?:0|[1-9][0-9]{0,8})$/D', (string) $value) !== 1) return null;
        $number = (int) $value;
        return $number < $min || $number > $max ? null : $number;
    }

    private static function boolean(mixed $value): ?bool
    {
        if ($value === true || $value === 'true') return true;
        if ($value === false || $value === 'false') return false;
        return null;
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value)
            || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}(?:Z|[+-](?:(?:0[0-9]|1[0-3]):[0-5][0-9]|14:00))?$/D', $value) !== 1) {
            return null;
        }
        $calendar = substr($value, 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $calendar);
        return $date !== false && $date->format('Y-m-d') === $calendar ? $value : null;
    }

    /** Missing collection is an empty list; malformed collection is null. */
    private static function rows(array $parent, string $plural, string $singular): ?array
    {
        if (!array_key_exists($plural, $parent)) return [];
        $groups = $parent[$plural];
        if (!is_array($groups) || !array_is_list($groups) || count($groups) > self::MAX_ROWS) return null;
        $rows = [];
        foreach ($groups as $group) {
            if (!is_array($group) || !array_key_exists($singular, $group)
                || !is_array($group[$singular]) || !array_is_list($group[$singular])) return null;
            foreach ($group[$singular] as $row) {
                if (!is_array($row) || $row === [] || array_is_list($row)
                    || count($rows) >= self::MAX_ROWS) return null;
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private static function place(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 4) return null;
        $out = [];
        foreach ($value as $row) {
            if (!is_array($row) || $row === [] || array_is_list($row)) return null;
            $item = [];
            foreach (['state', 'town', 'port', 'time'] as $field) {
                $text = self::text($row[$field] ?? null, $field === 'time' ? 32 : 160);
                if ($text !== null) $item[$field] = $text;
            }
            $out[] = $item;
        }
        return $out;
    }

    private static function projectHotel(array $row): array
    {
        $out = [];
        foreach (['name', 'star', 'town', 'state', 'room', 'htplace', 'meal', 'title'] as $field) {
            $text = self::text($row[$field] ?? null);
            if ($text !== null) $out[$field === 'htplace' ? 'placement' : $field] = $text;
        }
        foreach (['datebeg' => 'date_from', 'dateend' => 'date_to'] as $field => $public) {
            $date = self::date($row[$field] ?? null);
            if ($date !== null) $out[$public] = $date;
        }
        foreach (['placesCount' => 'places', 'roomCount' => 'rooms'] as $field => $public) {
            $number = self::integer($row[$field] ?? null, 0, 99);
            if ($number !== null) $out[$public] = $number;
        }
        return $out;
    }

    private static function projectTransport(array $row): array
    {
        $out = [];
        foreach (['name', 'type', 'class', 'onlineClass', 'category', 'title'] as $field) {
            $text = self::text($row[$field] ?? null);
            if ($text !== null) $out[$field === 'onlineClass' ? 'travel_class' : $field] = $text;
        }
        foreach (['datebeg' => 'date_from', 'dateend' => 'date_to'] as $field => $public) {
            $date = self::date($row[$field] ?? null);
            if ($date !== null) $out[$public] = $date;
        }
        foreach (['departure', 'arrival'] as $field) {
            if (!array_key_exists($field, $row)) continue;
            $place = self::place($row[$field]);
            if ($place !== null) $out[$field] = $place;
        }
        return $out;
    }

    private static function projectService(array $row): ?array
    {
        $hidden = self::boolean($row['hidden'] ?? false);
        if ($hidden === true) return null;
        if (array_key_exists('hidden', $row) && $hidden === null) return null;
        $out = [];
        foreach (['name', 'type', 'servicetype', 'title'] as $field) {
            $text = self::text($row[$field] ?? null);
            if ($text !== null) $out[$field === 'servicetype' ? 'service_type' : $field] = $text;
        }
        foreach (['datebeg' => 'date_from', 'dateend' => 'date_to'] as $field => $public) {
            $date = self::date($row[$field] ?? null);
            if ($date !== null) $out[$public] = $date;
        }
        $required = self::boolean($row['required'] ?? null);
        if ($required !== null) $out['required'] = $required;
        return $out;
    }

    private static function buyerPrice(array $document): ?array
    {
        $rows = self::rows($document, 'buyerMoneys', 'buyerClaimMoney');
        if ($rows === null || count($rows) !== 1) return null;
        $net = $rows[0]['net'] ?? null;
        $currency = self::text($rows[0]['currency'] ?? null, 8);
        $currencyKey = self::integer($rows[0]['currencyKey'] ?? null, 1, 999999999);
        // Decimal stays a string. No float math, conversion, agency-money fallback or zero price.
        if (!(is_string($net) || is_int($net))
            || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', (string) $net) !== 1
            || preg_match('/[1-9]/', (string) $net) !== 1 || $currency === null || $currencyKey === null
            || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) return null;
        return ['amount' => (string) $net, 'currency' => $currency, 'status' => 'package_unverified'];
    }

    /**
     * Validate and project a private captured record without claiming current selection authority.
     * catalogKey is supplier-private related metadata; SAMO confirms that it omits the TO id/search
     * form carried by the full PRICES[].id/claiminc, so it must never be compared byte-for-byte.
     */
    public static function record(array $record): array
    {
        $result = self::base('not_captured');
        if (($record['version'] ?? null) !== 1 || ($record['status'] ?? null) !== 'captured') return $result;
        foreach (['identity_verified', 'quote_verified', 'selection_enabled'] as $flag) {
            if (($record[$flag] ?? null) !== false) return self::base('checkpoint_invalid');
        }
        $raw = $record['private_package'] ?? null;
        $packageDigest = $record['package_sha256'] ?? null;
        $selectedDigest = $record['supplier_offer_sha256'] ?? null;
        if (!is_array($raw) || !self::plain($raw) || !is_string($packageDigest)
            || !preg_match('/^[a-f0-9]{64}$/D', $packageDigest)
            || !is_string($selectedDigest) || !preg_match('/^[a-f0-9]{64}$/D', $selectedDigest)) {
            return self::base('checkpoint_invalid');
        }
        try { $encoded = json_encode($raw, JSON_THROW_ON_ERROR); }
        catch (Throwable $ignored) { return self::base('checkpoint_invalid'); }
        if (strlen($encoded) > self::MAX_PACKAGE_BYTES
            || !hash_equals($packageDigest, hash('sha256', $encoded))) return self::base('checkpoint_invalid');
        $documents = $raw['claimDocument'] ?? null;
        if (!is_array($documents) || !array_is_list($documents) || count($documents) !== 1
            || !is_array($documents[0]) || $documents[0] === [] || array_is_list($documents[0])) {
            return self::base('claim_document_invalid');
        }
        $document = $documents[0];
        // catalogKey is intentionally validated only as bounded private metadata. It is never
        // emitted and is not a substitute for the full claiminc retained behind selectedDigest.
        if (self::text($document['catalogKey'] ?? null, 4096) === null) {
            return self::base('claim_document_invalid');
        }
        $result = self::base('package_captured_unquoted');
        if (($document['condition'] ?? null) !== 'ccOffer') {
            $result['status'] = 'package_not_temporary';
            return $result;
        }
        $hotels = self::rows($document, 'hotels', 'hotel');
        $transports = self::rows($document, 'transports', 'transport');
        $services = self::rows($document, 'services', 'service');
        if ($hotels === null || $transports === null || $services === null || $hotels === []) {
            $result['status'] = 'composition_invalid';
            return $result;
        }
        $result['trip'] = [];
        foreach (['datebeg' => 'date_from', 'dateend' => 'date_to'] as $field => $public) {
            $date = self::date($document[$field] ?? null);
            if ($date !== null) $result['trip'][$public] = $date;
        }
        foreach (['nights' => ['nights', 1, 365], 'peopleCount' => ['people', 1, 99],
            'adult' => ['adults', 0, 99], 'child' => ['children', 0, 99], 'infant' => ['infants', 0, 99]] as $field => $spec) {
            $number = self::integer($document[$field] ?? null, $spec[1], $spec[2]);
            if ($number !== null) $result['trip'][$spec[0]] = $number;
        }
        $result['hotels'] = array_map([self::class, 'projectHotel'], $hotels);
        $result['transports'] = array_map([self::class, 'projectTransport'], $transports);
        $result['services'] = [];
        foreach ($services as $service) {
            $public = self::projectService($service);
            if ($public !== null) $result['services'][] = $public;
        }
        $external = self::integer($document['freightExternal'] ?? null, 0, 999999999);
        $result['requires_external_flights'] = $external === null ? null : $external > 0;
        $variants = $raw['variants'] ?? null;
        $result['alternatives_available'] = is_array($variants) && array_is_list($variants)
            && $variants !== [] && count($variants) <= self::MAX_ROWS && self::plain($variants);
        $price = self::buyerPrice($document);
        if ($price !== null) {
            $result['price'] = $price;
            $result['price_status'] = 'package_unverified';
        }
        return $result;
    }

    /**
     * Safe authority boundary: PackageCapture::read() rechecks current retained context,
     * mapping, selected full-claiminc digest and package digest. Only that capture provenance,
     * never catalogKey text, may promote a captured package to current selected-package binding.
     * This does NOT verify availability/final price and never enables selection by itself.
     */
    public static function current(AnyTourAndromedaPackageCapture $capture,
        AnyTourAndromedaOfferStore $store, array $context): array
    {
        try { $record = $capture->read($store, $context); }
        catch (Throwable $ignored) { return self::base('current_context_invalid'); }
        $result = self::record($record);
        if ($result['status'] === 'package_captured_unquoted') {
            $result['status'] = 'package_bound_unquoted';
            $result['package_binding_verified'] = true;
            $result['identity_verified'] = true;
        } elseif ($result['status'] === 'composition_invalid') {
            $result['package_binding_verified'] = true;
            $result['identity_verified'] = true;
        } elseif ($result['status'] === 'package_not_temporary') {
            $result['package_binding_verified'] = true;
        }
        return $result;
    }
}
