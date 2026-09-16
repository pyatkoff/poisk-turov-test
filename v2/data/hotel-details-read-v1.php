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

// Canonical IDs must never silently replace the legacy hotelId contract.
$canonicalKeys = ['legacyHotelIds', 'anytourHotelId', 'anytourHotelIds'];
$canonicalInputs = array_values(array_intersect($canonicalKeys, array_keys($_GET)));
if (array_key_exists('catalog', $_GET) || $canonicalInputs !== []) {
    header('Cache-Control: no-store');
    // Filesystem identity, not Host, forwarded headers or a query feature flag.
    $directory = realpath(__DIR__);
    if (!is_string($directory) || !str_ends_with(str_replace('\\', '/', $directory),
        '/_preview/search3-local-candidate/data')) {
        hotel_details_read_out(['ok' => false, 'error' => 'Canonical catalogue is isolated to local preview'], 403);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        hotel_details_read_out(['ok' => false, 'error' => 'Only GET is allowed'], 405);
    }
    try {
        if (($_GET['catalog'] ?? null) !== 'anytour' || count($canonicalInputs) !== 1
            || array_key_exists('hotelId', $_GET) || array_key_exists('hotelIds', $_GET)) {
            throw new InvalidArgumentException('Choose one explicit canonical ID input');
        }
        require_once __DIR__ . '/anytour-canonical-catalog-v1.php';
        $input = $canonicalInputs[0];
        $values = $input === 'anytourHotelId' ? [$_GET[$input]] : $_GET[$input];
        if (!is_array($values)) throw new InvalidArgumentException('Expected an ID array');
        AnyTourCanonicalCatalog::ids($values);
        $canonicalIds = hotel_presentation_read_ids($values);
    } catch (InvalidArgumentException $e) {
        hotel_details_read_out(['ok' => false, 'error' => 'Invalid canonical hotel IDs'], 400);
    }
    try {
        $catalogue = new AnyTourCanonicalCatalog(v2_data_db());
        if ($input === 'legacyHotelIds') {
            hotel_details_read_out(['ok' => true] + $catalogue->readLegacyProfiles($canonicalIds));
        }
        $result = $catalogue->read($canonicalIds);
        if ($input === 'anytourHotelId') {
            if ($result['items'] === []) hotel_details_read_out(['ok' => false, 'error' => 'Hotel not found'], 404);
            hotel_details_read_out(['ok' => true, 'catalog' => 'anytour',
                'item' => $result['items'][0], 'source' => $result['source']]);
        }
        // The repository sorts its input; the HTTP batch preserves caller order.
        $byId = [];
        foreach ($result['items'] as $item) $byId[$item['id']] = $item;
        $items = $missing = [];
        foreach ($canonicalIds as $id) {
            if (isset($byId[$id])) $items[] = $byId[$id];
            else $missing[] = $id;
        }
        hotel_details_read_out(['ok' => true, 'catalog' => 'anytour', 'source' => $result['source'],
            'items' => $items, 'requestedAnyTourIds' => $canonicalIds, 'missingAnyTourIds' => $missing]);
    } catch (Throwable $e) {
        error_log('hotel-details-read-v1 canonical: ' . $e->getMessage());
        hotel_details_read_out(['ok' => false, 'error' => 'Hotel details temporarily unavailable'], 503);
    }
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
