<?php
declare(strict_types=1);

/**
 * Pure, server-side SearchTour_PRICES boundary (PHP 7.4+).
 *
 * Including this file performs no I/O. The returned supplier_offer_id is an
 * internal opaque CATCLAIM reference, not a browser/lead identifier. A caller
 * must explicitly project public fields before exposing any result.
 *
 * Amounts remain decimal strings. Search-time availability and bron never
 * establish a final quote. Unreviewed hotel mappings must resolve to null.
 */
function anytour_anex_normalize_prices(
    array $payload,
    array $context,
    ?callable $resolver = null,
    array $sensitiveValues = []
): array {
    $search = anytour_anex_normalizer_context($context);
    $sensitive = anytour_anex_normalizer_sensitive($sensitiveValues);
    if (array_key_exists('error', $payload)) {
        throw new InvalidArgumentException('invalid_anex_prices');
    }
    $data = array_key_exists('SearchTour_PRICES', $payload) ? $payload['SearchTour_PRICES'] : $payload;
    if (!is_array($data) || array_key_exists('error', $data)
        || !isset($data['prices']) || !is_array($data['prices'])
        || ($data['prices'] !== [] && array_keys($data['prices']) !== range(0, count($data['prices']) - 1))) {
        throw new InvalidArgumentException('invalid_anex_prices');
    }
    $result = [
        'schema_version' => 1,
        'provider' => 'anex',
        'supplier_namespace' => 'anex_online',
        'search' => $search,
        // The supplier may have initiated an external search. This adapter
        // returns this page only; it does not poll or expose the search key.
        'external_search_pending' => isset($data['searchKey']) && is_string($data['searchKey'])
            && $data['searchKey'] !== '',
        'offers' => [],
        'rejected_count' => 0,
        'truncated_count' => max(0, count($data['prices']) - 300),
    ];
    foreach (array_slice($data['prices'], 0, 300) as $row) {
        $offer = is_array($row)
            ? anytour_anex_normalizer_offer($row, $search, $resolver, $sensitive)
            : null;
        if ($offer === null) {
            ++$result['rejected_count'];
        } else {
            $result['offers'][] = $offer;
        }
    }
    return $result;
}

/** Validate the explicitly selected search, never infer it from supplier rows. */
function anytour_anex_normalizer_context(array $context): array
{
    $begin = anytour_anex_normalizer_date($context['checkin_begin'] ?? null);
    $end = anytour_anex_normalizer_date($context['checkin_end'] ?? null);
    $from = anytour_anex_normalizer_integer($context['nights_from'] ?? null, 1, 60);
    $till = anytour_anex_normalizer_integer($context['nights_till'] ?? null, 1, 60);
    $adults = anytour_anex_normalizer_integer($context['adults'] ?? null, 1, 9);
    $children = anytour_anex_normalizer_integer($context['children'] ?? null, 0, 8);
    if ($begin === null || $end === null || $begin > $end || $begin->diff($end)->days > 366
        || $from === null || $till === null || $from > $till || $adults === null || $children === null) {
        throw new InvalidArgumentException('invalid_anex_search_context');
    }
    $ages = $context['child_ages'] ?? [];
    if (!is_array($ages) || count($ages) !== $children
        || ($ages !== [] && array_keys($ages) !== range(0, count($ages) - 1))) {
        throw new InvalidArgumentException('invalid_anex_search_context');
    }
    foreach ($ages as &$age) {
        $age = anytour_anex_normalizer_integer($age, 0, 17);
        if ($age === null) {
            throw new InvalidArgumentException('invalid_anex_search_context');
        }
    }
    unset($age);
    $search = [
        'checkin_begin' => $begin->format('Y-m-d'),
        'checkin_end' => $end->format('Y-m-d'),
        'nights_from' => $from,
        'nights_till' => $till,
        'adults' => $adults,
        'children' => $children,
        'child_ages' => $ages,
    ];
    foreach (['departure_id', 'destination_id', 'currency_id'] as $key) {
        if (array_key_exists($key, $context)) {
            $id = anytour_anex_normalizer_id($context[$key]);
            if ($id === null) {
                throw new InvalidArgumentException('invalid_anex_search_context');
            }
            $search[$key] = $id;
        }
    }
    return $search;
}

function anytour_anex_normalizer_offer(array $row, array $search, ?callable $resolver, array $sensitive): ?array
{
    $supplierId = $row['id'] ?? null;
    if (is_int($supplierId) && $supplierId > 0) {
        $supplierId = (string) $supplierId;
    }
    if (!is_string($supplierId) || $supplierId === '0'
        || !preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.:,;\~@+/=|\-]{0,2047}\z~D', $supplierId)
        || strpos($supplierId, '://') !== false || anytour_anex_normalizer_has_secret($supplierId, $sensitive)) {
        return null;
    }
    $hotelId = anytour_anex_normalizer_id($row['hotelKey'] ?? null);
    $hotel = anytour_anex_normalizer_label($row['hotel'] ?? null, $sensitive);
    $checkin = anytour_anex_normalizer_date($row['checkIn'] ?? null);
    $checkout = anytour_anex_normalizer_date($row['checkOut'] ?? null);
    $nights = anytour_anex_normalizer_integer($row['nights'] ?? null, 1, 60);
    $adults = anytour_anex_normalizer_integer($row['adult'] ?? null, 1, 9);
    $children = anytour_anex_normalizer_integer($row['child'] ?? null, 0, 8);
    $grouped = anytour_anex_normalizer_flag($row['grouped'] ?? null);
    $amount = anytour_anex_normalizer_decimal($row['price'] ?? null);
    $currency = $row['currency'] ?? null;
    if ($hotelId === null || $hotel === null || $checkin === null || $checkout === null
        || $nights === null || $adults !== $search['adults'] || $children !== $search['children']
        || $checkin->format('Y-m-d') < $search['checkin_begin']
        || $checkin->format('Y-m-d') > $search['checkin_end'] || $checkin >= $checkout
        || $nights < $search['nights_from'] || $nights > $search['nights_till']
        || anytour_anex_normalizer_integer($row['packetType'] ?? null, 0, 0) !== 0
        // Nonzero infants need a separately verified traveller contract.
        || (array_key_exists('infant', $row)
            && anytour_anex_normalizer_integer($row['infant'], 0, 0) !== 0)
        || $grouped === null || $amount === null || !is_string($currency)
        || !preg_match('/\A[A-Z]{3}\z/D', $currency)) {
        return null;
    }
    $localId = null;
    if ($resolver !== null) {
        // Resolver failures carry no provider/DB error detail across this boundary.
        try {
            $resolved = $resolver('anex_online', $hotelId);
            if (is_int($resolved) && $resolved > 0) {
                $localId = $resolved;
            }
        } catch (Throwable $ignored) {
            $localId = null;
        }
    }
    $kind = $grouped ? 'group_minimum' : 'concrete';
    $freights = isset($row['freights']) && is_array($row['freights']) ? $row['freights'] : [];
    $econom = isset($freights['econom']) && is_array($freights['econom']) ? $freights['econom'] : [];
    return [
        'offer_key' => 'anex_online:' . hash('sha256', $kind . "\0" . $supplierId),
        'supplier_offer_id' => $supplierId,
        'provider' => 'anex',
        'supplier_namespace' => 'anex_online',
        'kind' => $kind,
        'hotel' => [
            'external_id' => $hotelId,
            'local_id' => $localId,
            'mapping_status' => $localId === null ? 'unmapped' : 'resolved',
            'name' => $hotel,
            'star' => anytour_anex_normalizer_label($row['star'] ?? null, $sensitive, 32),
            // Price evidence does not establish geography fields; a verified
            // reference resolver may enrich these separately in the future.
            'country' => null,
            'region' => null,
            'town' => anytour_anex_normalizer_label($row['town'] ?? null, $sensitive),
            'external_town_id' => anytour_anex_normalizer_id($row['townKey'] ?? null),
        ],
        'checkin' => $checkin->format('Y-m-d'),
        'checkout' => $checkout->format('Y-m-d'),
        'nights' => $nights,
        'adults' => $adults,
        'children' => $children,
        'infants' => array_key_exists('infant', $row) ? 0 : null,
        'meal' => anytour_anex_normalizer_label($row['meal'] ?? null, $sensitive, 80),
        'external_meal_id' => anytour_anex_normalizer_id($row['mealKey'] ?? null),
        'room' => anytour_anex_normalizer_label($row['room'] ?? null, $sensitive),
        'external_room_id' => anytour_anex_normalizer_id($row['roomKey'] ?? null),
        'hotel_place' => anytour_anex_normalizer_label($row['htPlace'] ?? null, $sensitive),
        'external_hotel_place_id' => anytour_anex_normalizer_id($row['htPlaceKey'] ?? null),
        'price' => ['amount' => $amount, 'currency' => $currency],
        'converted_price' => anytour_anex_normalizer_converted($row['convertedPrice'] ?? null),
        'availability' => [
            'hotel' => anytour_anex_normalizer_availability($row['hotelAvailability'] ?? null, true),
            // SAMO's observed econom.in denotes outward, econom.out return.
            // These are availability markers only, never flight itineraries.
            'flight_outbound_economy' => anytour_anex_normalizer_availability($econom['in'] ?? null),
            'flight_return_economy' => anytour_anex_normalizer_availability($econom['out'] ?? null),
        ],
        'supplier_booking_flag' => anytour_anex_normalizer_flag($row['bron'] ?? null),
        'final_price_verified' => false,
    ];
}

function anytour_anex_normalizer_integer($value, int $minimum, int $maximum): ?int
{
    if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', $value)) {
        $value = (int) $value;
    }
    return is_int($value) && $value >= $minimum && $value <= $maximum ? $value : null;
}

function anytour_anex_normalizer_id($value): ?string
{
    if (is_int($value) && $value > 0) {
        $value = (string) $value;
    }
    return is_string($value) && preg_match('/\A[1-9][0-9]{0,17}\z/D', $value) ? $value : null;
}

function anytour_anex_normalizer_date($value): ?DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/\A(?:[0-9]{8}|[0-9]{4}-[0-9]{2}-[0-9]{2})\z/D', $value)) {
        return null;
    }
    $format = strlen($value) === 8 ? 'Ymd' : 'Y-m-d';
    $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
        || $date->format($format) !== $value || (int) $date->format('Y') < 2000
        || (int) $date->format('Y') > 2199) {
        return null;
    }
    return $date;
}

/** Keep decimal precision; never use float arithmetic for amounts. */
function anytour_anex_normalizer_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) {
        $value = (string) $value;
    }
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)
        || !preg_match('/[1-9]/', $value)) {
        return null;
    }
    return $value;
}

function anytour_anex_normalizer_converted($value): ?array
{
    if (!is_string($value) || strlen($value) > 80 || !preg_match(
        '/\A((?:[1-9][0-9]{0,2}(?:[ \x{00a0}][0-9]{3})+|0|[1-9][0-9]{0,11})(?:[.,][0-9]{1,2})?)[ \x{00a0}]+([A-Z]{3})\z/uD',
        $value,
        $parts
    )) {
        return null;
    }
    $amount = anytour_anex_normalizer_decimal(str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], $parts[1]));
    return $amount === null ? null : ['amount' => $amount, 'currency' => $parts[2]];
}

function anytour_anex_normalizer_flag($value): ?bool
{
    if ($value === true || $value === 1 || $value === '1') {
        return true;
    }
    if ($value === false || $value === 0 || $value === '0') {
        return false;
    }
    return null;
}

function anytour_anex_normalizer_availability($value, bool $hotel = false): ?string
{
    return is_string($value) && preg_match($hotel ? '/\A[YNFR]{1,8}\z/D' : '/\A[YNFR]\z/D', $value)
        ? $value : null;
}

function anytour_anex_normalizer_sensitive(array $values): array
{
    $result = [];
    foreach (array_slice($values, 0, 32) as $value) {
        if (is_string($value) && $value !== '') {
            $result[] = $value;
            if (trim($value) !== '') {
                $result[] = trim($value);
            }
        }
    }
    return array_unique($result);
}

function anytour_anex_normalizer_has_secret(string $value, array $sensitive): bool
{
    foreach ($sensitive as $secret) {
        if (strpos($value, $secret) !== false || strpos(rawurldecode($value), $secret) !== false) {
            return true;
        }
    }
    return false;
}

function anytour_anex_normalizer_label($value, array $sensitive, int $limit = 180): ?string
{
    if (!is_string($value) || strlen($value) > 8192 || !preg_match('//u', $value)
        || anytour_anex_normalizer_has_secret($value, $sensitive)) {
        return null;
    }
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/[\p{Cc}\p{Cf}]/u', '', $value);
    $value = trim(preg_replace('/\s+/u', ' ', $value));
    if ($value === '' || preg_match('~(?:https?://|www\.|oauth_token\s*[=:])~i', $value)
        || anytour_anex_normalizer_has_secret($value, $sensitive)) {
        return null;
    }
    // PCRE keeps UTF-8 characters intact without requiring mbstring.
    preg_match('/\A.{1,' . $limit . '}/us', $value, $match);
    return $match[0] ?? null;
}
