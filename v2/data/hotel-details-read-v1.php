<?php
/** Read-only local hotel presentation DTO for Search3: scalar and bounded batch. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=1800');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/hotel-presentation-read-v1.php';

function hotel_details_read_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    if ($status !== 200) header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$batch = array_key_exists('hotelIds', $_GET);
try {
    if ($batch && array_key_exists('hotelId', $_GET)) {
        throw new InvalidArgumentException('Choose hotelId or hotelIds, not both');
    }
    $ids = hotel_presentation_read_ids($batch ? $_GET['hotelIds'] : [$_GET['hotelId'] ?? null]);
} catch (InvalidArgumentException $e) {
    hotel_details_read_out(['ok' => false, 'error' => $batch ? $e->getMessage() : 'Invalid hotel id'], 400);
}

try {
    $result = hotel_presentation_read_many(v2_data_db(), $ids);
    if ($batch) {
        hotel_details_read_out(['ok' => true] + $result + ['source' => 'anytour-local-hotel']);
    }
    if ($result['items'] === []) hotel_details_read_out(['ok' => false, 'error' => 'Hotel not found'], 404);
    hotel_details_read_out(['ok' => true, 'item' => $result['items'][0], 'source' => 'anytour-local-hotel']);
} catch (Throwable $e) {
    error_log('hotel-details-read-v1: ' . $e->getMessage());
    hotel_details_read_out(['ok' => false, 'error' => 'Hotel details temporarily unavailable'], 503);
}
