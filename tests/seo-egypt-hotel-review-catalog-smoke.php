<?php
require_once __DIR__ . '/../v2/seo-content-pilot-egypt-hotel-review-catalog-v1.php';

function egypt_hotel_review_fail(string $message): void
{
    fwrite(STDERR, "SEO_EGYPT_HOTEL_REVIEW_FAIL:$message\n");
    exit(1);
}

$manifest = v2_seo_egypt_hotel_manifest();
$expectedHotelCount = count($manifest);
if ($expectedHotelCount <= 0) egypt_hotel_review_fail('empty_manifest');

$manifestPaths = [];
$manifestHotelIds = [];
$manifestFunctions = [];
$editorialFingerprints = [];
foreach ($manifest as $entry) {
    $href = trim((string)($entry['href'] ?? ''));
    $file = trim((string)($entry['file'] ?? ''));
    $function = trim((string)($entry['function'] ?? ''));
    $label = trim((string)($entry['label'] ?? ''));
    if ($href === '' || $file === '' || $function === '' || $label === '') egypt_hotel_review_fail('manifest_entry_incomplete');
    if (isset($manifestPaths[$href])) egypt_hotel_review_fail('duplicate_manifest_href_' . $href);
    if (isset($manifestFunctions[$function])) egypt_hotel_review_fail('duplicate_manifest_function_' . $function);
    $manifestPaths[$href] = true;
    $manifestFunctions[$function] = true;

    $contentFile = __DIR__ . '/../v2/' . $file;
    if (!is_file($contentFile)) egypt_hotel_review_fail('manifest_content_missing_' . $file);
    require_once $contentFile;
    if (!function_exists($function)) egypt_hotel_review_fail('manifest_function_missing_' . $function);
    $record = $function();
    if (($record['status'] ?? '') !== 'review' || ($record['type'] ?? '') !== 'hotel_tours') egypt_hotel_review_fail('record_contract_' . $function);
    if (($record['path'] ?? '') !== $href) egypt_hotel_review_fail('record_href_drift_' . $function);

    if (!preg_match('#^/country/egypt/hotel/([a-z0-9-]+)/$#', $href, $pathMatch)) egypt_hotel_review_fail('invalid_hotel_href_' . $href);
    $slug = $pathMatch[1];
    $state = is_array($record['data']['search_state'] ?? null) ? $record['data']['search_state'] : [];
    $hotelId = (int)($state['hotel'] ?? 0);
    if ((int)($state['country'] ?? 0) !== 1 || $hotelId <= 0 || !str_ends_with($slug, '-' . $hotelId)) egypt_hotel_review_fail('record_identity_' . $slug);
    if (isset($manifestHotelIds[$hotelId])) egypt_hotel_review_fail('duplicate_hotel_id_' . $hotelId);
    $manifestHotelIds[$hotelId] = true;

    $routeFile = __DIR__ . '/../v2' . $href . 'index.php';
    if (!is_file($routeFile)) egypt_hotel_review_fail('route_missing_' . $slug);
    $routeSource = file_get_contents($routeFile);
    if ($routeSource === false || !str_contains($routeSource, 'v2_seo_render_hotel_tour_review(') || !str_contains($routeSource, $function . '()')) egypt_hotel_review_fail('route_contract_' . $slug);

    $hotelName = mb_strtolower(trim((string)($record['data']['name'] ?? '')), 'UTF-8');
    $editorial = [
        'description' => (string)($record['data']['description'] ?? ''),
        'intro' => (string)($record['data']['intro'] ?? ''),
        'sections' => $record['data']['sections'] ?? [],
    ];
    $normalized = mb_strtolower((string)json_encode($editorial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'UTF-8');
    if ($hotelName !== '') $normalized = str_replace($hotelName, '{{hotel}}', $normalized);
    $fingerprint = hash('sha256', preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    if (isset($editorialFingerprints[$fingerprint])) egypt_hotel_review_fail('duplicate_editorial_copy_' . $editorialFingerprints[$fingerprint] . '_' . $slug);
    $editorialFingerprints[$fingerprint] = $slug;
}

$catalog = v2_seo_content_pilot_egypt_hotel_review_catalog();
$registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
$reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
$graph = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];
if (v2_seo_content_candidate_paths($catalog) !== []) egypt_hotel_review_fail('review_family_became_publication_candidate');

$hotelPaths = [];
foreach ($registry as $path => $entry) {
    if (($entry['type'] ?? '') !== 'hotel_tours') continue;
    $hotelPaths[] = (string)$path;
    $report = $reports[$path] ?? [];
    if (($report['status'] ?? '') !== 'review') egypt_hotel_review_fail('hotel_not_review_' . $path);
    if (($report['publishable'] ?? false) !== true) egypt_hotel_review_fail('hotel_not_structurally_publishable_' . $path);
    if (($graph[$path]['parent'] ?? null) !== '/country/egypt/') egypt_hotel_review_fail('wrong_parent_' . $path);
    $state = is_array(($entry['page']['search_state'] ?? null)) ? $entry['page']['search_state'] : [];
    if ((int)($state['country'] ?? 0) !== 1 || (int)($state['hotel'] ?? 0) <= 0) egypt_hotel_review_fail('wrong_search_identity_' . $path);
}

sort($hotelPaths, SORT_STRING);
$expectedPaths = array_keys($manifestPaths);
sort($expectedPaths, SORT_STRING);
if ($hotelPaths !== $expectedPaths) egypt_hotel_review_fail('manifest_registry_path_drift');
if (count($hotelPaths) !== $expectedHotelCount) egypt_hotel_review_fail('manifest_registry_count_mismatch_' . $expectedHotelCount . '_' . count($hotelPaths));
if (count($registry) !== $expectedHotelCount + 1) egypt_hotel_review_fail('expected_parent_plus_manifest_hotels');

$productionParent = v2_seo_content_pilot_egypt();
foreach (($productionParent['data']['related'] ?? []) as $link) {
    $href = (string)($link['href'] ?? '');
    if (str_starts_with($href, '/country/egypt/hotel/')) egypt_hotel_review_fail('production_parent_exposes_review_hotel_link');
}
$reviewParent = $registry['/country/egypt/'] ?? [];
if (($reports['/country/egypt/']['status'] ?? '') !== 'review') egypt_hotel_review_fail('isolated_parent_not_review');
if (($reviewParent['type'] ?? '') !== 'country') egypt_hotel_review_fail('isolated_parent_type');

echo 'SEO_EGYPT_HOTEL_REVIEW_OK hotels=' . $expectedHotelCount . ' candidates=0 parentIsolation=1 country=1 uniqueHotelIds=' . count($manifestHotelIds) . ' uniqueEditorial=' . count($editorialFingerprints) . PHP_EOL;
