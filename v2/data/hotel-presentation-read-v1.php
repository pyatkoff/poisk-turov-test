<?php
/** Local hotel presentation only: no supplier calls, matching, offer or DB writes. */
declare(strict_types=1);
require_once __DIR__ . '/hotel-details-v1.php';

const HOTEL_PRESENTATION_READ_LIMIT = 100;

function hotel_details_read_id(mixed $value): ?int
{
    if (!is_int($value) && !is_string($value)) return null;
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id === false || $id <= 0 ? null : $id;
}

/** Reject an invalid batch in full; never silently truncate or drop invalid IDs. */
function hotel_presentation_read_ids(mixed $values): array
{
    if (!is_array($values) || !array_is_list($values) || $values === []
        || count($values) > HOTEL_PRESENTATION_READ_LIMIT) {
        throw new InvalidArgumentException('Expected 1 to 100 local hotel IDs');
    }
    $ids = [];
    foreach ($values as $value) {
        $id = hotel_details_read_id($value);
        if ($id === null) throw new InvalidArgumentException('Invalid local hotel id');
        $ids[$id] = $id;
    }
    return array_values($ids);
}

function hotel_details_read_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    try {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (JsonException) {
        return [];
    }
}

function hotel_details_read_text(mixed $value): ?string
{
    if (!is_scalar($value) || $value === null) return null;
    $text = trim((string)$value);
    return $text === '' ? null : $text;
}

/** Same DTO for individual cards and batches; no supplier-content fallback. */
function hotel_presentation_read_item(array $row): array
{
    // Reuse the existing pure media validator, not a second URL sanitizer.
    $primary = v2_hotel_detail_https_url($row['primary_image_url'] ?? null);
    $images = v2_hotel_detail_images(['images' => array_merge(
        $primary === null ? [] : [$primary],
        hotel_details_read_json($row['images_json'] ?? null)
    )]);
    return [
        'id' => (int)$row['id'], 'name' => (string)$row['name'],
        'country' => ['id' => (int)$row['country_id'], 'name' => (string)$row['country_name']],
        'region' => $row['region_id'] !== null
            ? ['id' => (int)$row['region_id'], 'name' => (string)($row['region_name'] ?? '')] : null,
        'subRegion' => $row['subregion_id'] !== null
            ? ['id' => (int)$row['subregion_id'], 'name' => (string)($row['subregion_name'] ?? '')] : null,
        'category' => $row['category'] !== null ? (int)$row['category'] : null,
        'rating' => $row['rating'] !== null ? (float)$row['rating'] : null,
        'type' => $row['hotel_type'] !== null ? (int)$row['hotel_type'] : null,
        'description' => hotel_details_read_text($row['description'] ?? null),
        'primaryImage' => $primary, 'images' => $images,
        'infrastructure' => hotel_details_read_json($row['infrastructure_json'] ?? null),
        'meals' => hotel_details_read_json($row['meals_json'] ?? null),
        'services' => hotel_details_read_json($row['services_json'] ?? null),
        'roomTypes' => hotel_details_read_text($row['room_types'] ?? null),
        'address' => hotel_details_read_text($row['address'] ?? null),
        'place' => hotel_details_read_text($row['place'] ?? null),
        'build' => hotel_details_read_text($row['build_info'] ?? null),
        'repair' => hotel_details_read_text($row['repair_info'] ?? null),
        'square' => hotel_details_read_text($row['square_info'] ?? null),
        'coordinates' => $row['latitude'] !== null && $row['longitude'] !== null
            ? ['latitude' => (float)$row['latitude'], 'longitude' => (float)$row['longitude']] : null,
        'detailsAvailable' => ($row['status'] ?? null) === 'success',
        'detailsFetchedAt' => hotel_details_read_text($row['fetched_at'] ?? null),
    ];
}

/** One bounded SELECT against the current local catalogue; request order preserved. */
function hotel_presentation_read_many(PDO $pdo, array $hotelIds): array
{
    $ids = hotel_presentation_read_ids($hotelIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT h.id,h.name,h.country_id,h.country_name,h.region_id,h.region_name,h.subregion_id,h.subregion_name,
                   h.category,h.rating,h.hotel_type,h.latitude,h.longitude,h.primary_image_url,
                   d.status,d.description,d.address,d.place,d.build_info,d.repair_info,d.square_info,
                   d.images_json,d.infrastructure_json,d.meals_json,d.services_json,d.room_types,d.fetched_at
            FROM catalog_hotels h
            LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id AND d.status='success'
            WHERE h.is_active=1 AND h.id IN ($placeholders)
            LIMIT " . count($ids);
    $stmt = $pdo->prepare($sql);
    if ($stmt === false) throw new RuntimeException('Could not prepare local hotel read');
    foreach ($ids as $offset => $id) $stmt->bindValue($offset + 1, $id, PDO::PARAM_INT);
    if (!$stmt->execute()) throw new RuntimeException('Could not read local hotels');
    $byId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int)$row['id']] = hotel_presentation_read_item($row);
    }
    $items = $missing = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) $items[] = $byId[$id];
        else $missing[] = $id;
    }
    return ['items' => $items, 'requestedIds' => $ids, 'missingIds' => $missing];
}
