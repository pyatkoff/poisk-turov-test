<?php
/** Pure helpers for Tourvisor hotel-description enrichment. */
declare(strict_types=1);

function v2_hotel_detail_object(array $payload): ?array
{
    if ($payload === []) return null;
    if (array_is_list($payload)) {
        foreach ($payload as $row) {
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) return $row;
        }
        return null;
    }
    return (int)($payload['id'] ?? 0) > 0 ? $payload : null;
}

function v2_hotel_detail_text(mixed $value, int $max = 65535): ?string
{
    if (!is_scalar($value) && $value !== null) return null;
    $value = trim((string)$value);
    if ($value === '') return null;
    return mb_substr($value, 0, max(1, $max));
}

function v2_hotel_detail_int(mixed $value): ?int
{
    $n = filter_var($value, FILTER_VALIDATE_INT);
    return $n === false || (int)$n <= 0 ? null : (int)$n;
}

function v2_hotel_detail_float(mixed $value): ?float
{
    if (!is_numeric($value)) return null;
    $n = (float)$value;
    return is_finite($n) ? $n : null;
}

function v2_hotel_detail_https_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 2048) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || trim((string)($parts['host'] ?? '')) === '') return null;
    return $url;
}

function v2_hotel_detail_images(array $hotel): array
{
    $out = [];
    foreach ((array)($hotel['images'] ?? []) as $raw) {
        $url = v2_hotel_detail_https_url($raw);
        if ($url === null) continue;
        $out[$url] = true;
        if (count($out) >= 100) break;
    }
    return array_keys($out);
}

function v2_hotel_detail_json(mixed $value): ?string
{
    if ($value === null || $value === [] || $value === '') return null;
    try {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }
}

function v2_hotel_detail_normalized(array $hotel): array
{
    $common = is_array($hotel['common'] ?? null) ? $hotel['common'] : [];
    $country = is_array($hotel['country'] ?? null) ? $hotel['country'] : [];
    $region = is_array($hotel['region'] ?? null) ? $hotel['region'] : [];
    $subRegion = is_array($hotel['subRegion'] ?? null) ? $hotel['subRegion'] : [];
    $images = v2_hotel_detail_images($hotel);

    return [
        'hotel_id' => v2_hotel_detail_int($hotel['id'] ?? null),
        'name' => v2_hotel_detail_text($hotel['name'] ?? null, 255),
        'country_id' => v2_hotel_detail_int($country['id'] ?? null),
        'country_name' => v2_hotel_detail_text($country['name'] ?? null, 160),
        'region_id' => v2_hotel_detail_int($region['id'] ?? null),
        'region_name' => v2_hotel_detail_text($region['name'] ?? null, 180),
        'subregion_id' => v2_hotel_detail_int($subRegion['id'] ?? null),
        'subregion_name' => v2_hotel_detail_text($subRegion['name'] ?? null, 180),
        'category' => v2_hotel_detail_int($hotel['category'] ?? null),
        'rating' => v2_hotel_detail_float($hotel['rating'] ?? null),
        'hotel_type' => v2_hotel_detail_int($hotel['type'] ?? null),
        'description' => v2_hotel_detail_text($common['description'] ?? null, 200000),
        'address' => v2_hotel_detail_text($common['address'] ?? null, 1000),
        'place' => v2_hotel_detail_text($common['place'] ?? null, 1000),
        'phone' => v2_hotel_detail_text($common['phone'] ?? null, 255),
        'site' => v2_hotel_detail_text($common['site'] ?? null, 1000),
        'build' => v2_hotel_detail_text($common['build'] ?? null, 2000),
        'repair' => v2_hotel_detail_text($common['repair'] ?? null, 2000),
        'square' => v2_hotel_detail_text($common['square'] ?? null, 1000),
        'latitude' => v2_hotel_detail_float($common['latitude'] ?? null),
        'longitude' => v2_hotel_detail_float($common['longitude'] ?? null),
        'primary_image_url' => $images[0] ?? null,
        'images' => $images,
        'images_json' => v2_hotel_detail_json($images),
        'infrastructure_json' => v2_hotel_detail_json($hotel['infrastructure'] ?? null),
        'meals_json' => v2_hotel_detail_json($hotel['meals'] ?? null),
        'services_json' => v2_hotel_detail_json($hotel['services'] ?? null),
        'room_types' => v2_hotel_detail_text($hotel['roomTypes'] ?? null, 100000),
    ];
}
