<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/anex_search3_hotel_content.php';
require_once __DIR__ . '/../scripts/diagnostics/anex_search3_catalog_content_plan.php';

function content_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$plan = json_decode((string)file_get_contents(__DIR__ . '/../scripts/diagnostics/anex_search3_catalog_content_plan.json'), true, 512, JSON_THROW_ON_ERROR);
content_check(anytour_anex_content_plan($plan)['limit'] === 25, 'bounded explicit cohort');
foreach ([['limit' => 26], ['limit' => true], ['batch_key' => 'owner_content_pilot_20260909'],
    ['photos_batch_key' => 'owner_content_photos_pilot_20260909']] as $change) {
    try {
        anytour_anex_content_plan(array_replace($plan, $change));
        throw new RuntimeException('invalid cohort accepted');
    } catch (InvalidArgumentException $expected) {}
}
$rows = [
    ['anex_hotel_id' => 101, 'search_count' => 100, 'mapped_hotel_id' => 101],
    ['anex_hotel_id' => 102, 'search_count' => 9],
    ['anex_hotel_id' => 103, 'search_count' => 8],
    ['anex_hotel_id' => 104, 'search_count' => 7],
    ['anex_hotel_id' => 105, 'search_count' => 6],
    ['anex_hotel_id' => 106, 'search_count' => 1000, 'content_hotel_id' => 106],
    ['anex_hotel_id' => 8121, 'search_count' => 1000],
    ['anex_hotel_id' => 16193, 'search_count' => 1000],
];
content_check(anytour_anex_content_choose($rows, [101, 103, 104, 106, 8121], 25) === [103,104,102,105,101],
    'unresolved then verified then frequent; reservations and pilot never replay');
content_check(anytour_anex_content_choose($rows, [103,104], 2) === [103,104], 'hard batch limit');
content_check(anytour_anex_content_choose([], [], 25) === [], 'empty cohort stays empty');

$details = anytour_anex_hotel_content(['id' => 103, 'name' => 'Own ANEX hotel', 'description' => 'Supplier description',
    'photos' => [['url' => 'https://images.anextour.ru/103a.jpg', 'note' => 'Pool']]], 103);
$empty = anytour_anex_hotel_content(['id' => 103, 'photos' => []], 103);
$merged = anytour_anex_content_merge_photos($details, $empty);
content_check($merged['content'] === $details['content'] && $merged['availability']['photos'] === 'present',
    'empty Photos preserves supplier DETAILS photographs and description');
$photos = anytour_anex_hotel_content(['id' => 103, 'photos' => [
    ['url' => 'https://images.anextour.ru/103a.jpg'], ['url' => 'https://images.anextour.ru/103b.jpg']]], 103);
$merged = anytour_anex_content_merge_photos($details, $photos);
content_check(count($merged['content']['photos']) === 2 && $merged['content']['photos'][0]['note'] === 'Pool'
    && $merged['content']['description'] === 'Supplier description', 'Photos adds and deduplicates without losing DETAILS');
$onlyPhotos = anytour_anex_content_merge_photos(anytour_anex_hotel_content([], 103), $photos);
content_check($onlyPhotos['status'] === 'ok' && $onlyPhotos['content']['id'] === 103
    && count($onlyPhotos['content']['photos']) === 2, 'Photos can enrich an empty DETAILS record');

// Old payload JSON lost .0, while its canonical digest and the review panel retain it.
$coordinatePayload = anytour_anex_hotel_content(['id' => 104, 'name' => 'Integral coordinates',
    'latitude' => 36.0, 'longitude' => 0.0, 'description' => 'Original source'], 104);
$savedRow = ['status' => 'ready', 'content_sha256' => $coordinatePayload['source_sha256'],
    'payload_json' => json_encode($coordinatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
$restored = anytour_anex_content_saved_payload($savedRow, 104);
content_check(is_float($restored['content']['latitude']) && is_float($restored['content']['longitude']),
    'saved integral coordinates restored to their normalized numeric type');
$coordinateMerged = anytour_anex_content_merge_photos($restored, anytour_anex_hotel_content(['id' => 104, 'photos' => []], 104));
$coordinateDigest = hash('sha256', json_encode($coordinateMerged['content'],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
content_check($coordinateDigest === $savedRow['content_sha256'], 'photo merge preserves canonical coordinate digest');
foreach (['hotel_id', 'content_id', 'status', 'content', 'hash'] as $tamper) {
    $bad = $coordinatePayload;
    if ($tamper === 'hotel_id') $bad['hotel_id'] = 105;
    if ($tamper === 'content_id') $bad['content']['id'] = 105;
    if ($tamper === 'status') $bad['status'] = 'empty';
    if ($tamper === 'content') $bad['content']['description'] = 'Changed without provenance';
    if ($tamper === 'hash') $bad['source_sha256'] = str_repeat('0', 64);
    try {
        anytour_anex_content_saved_payload(array_replace($savedRow, ['payload_json' => json_encode($bad)]), 104);
        throw new RuntimeException('mismatched saved identity or digest accepted');
    } catch (InvalidArgumentException $expected) {}
}
$emptyPayload = anytour_anex_hotel_content([], 104);
$emptyRow = ['status' => 'empty', 'content_sha256' => null, 'payload_json' => json_encode($emptyPayload)];
content_check(anytour_anex_content_saved_payload($emptyRow, 104) === $emptyPayload
    && anytour_anex_content_merge_photos($emptyPayload, anytour_anex_hotel_content(['id' => 104, 'photos' => []], 104)) === $emptyPayload,
    'empty source retains its valid empty shape and null digest');
echo "ANEX_CATALOG_CONTENT_SMOKE_OK\n";
