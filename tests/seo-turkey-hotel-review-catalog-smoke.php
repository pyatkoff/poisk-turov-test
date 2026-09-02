<?php
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-hotel-review-catalog-v1.php';

function turkey_hotel_review_fail(string $message): void
{
    fwrite(STDERR, "SEO_TURKEY_HOTEL_REVIEW_FAIL:$message\n");
    exit(1);
}

$manifest = v2_seo_turkey_hotel_manifest();
$expectedHotelCount = count($manifest);
if ($expectedHotelCount <= 0) turkey_hotel_review_fail('empty_manifest');

$manifestPaths = [];
$manifestHotelIds = [];
$manifestFunctions = [];
$editorialFingerprints = [];
foreach ($manifest as $entry) {
    $href = trim((string)($entry['href'] ?? ''));
    $file = trim((string)($entry['file'] ?? ''));
    $function = trim((string)($entry['function'] ?? ''));
    $label = trim((string)($entry['label'] ?? ''));
    if ($href === '' || $file === '' || $function === '' || $label === '') {
        turkey_hotel_review_fail('manifest_entry_incomplete');
    }
    if (isset($manifestPaths[$href])) turkey_hotel_review_fail('duplicate_manifest_href_' . $href);
    if (isset($manifestFunctions[$function])) turkey_hotel_review_fail('duplicate_manifest_function_' . $function);
    $manifestPaths[$href] = true;
    $manifestFunctions[$function] = true;

    $contentFile = __DIR__ . '/../v2/' . $file;
    if (!is_file($contentFile)) turkey_hotel_review_fail('manifest_content_missing_' . $file);
    require_once $contentFile;
    if (!function_exists($function)) turkey_hotel_review_fail('manifest_function_missing_' . $function);
    $record = $function();
    if (($record['status'] ?? '') !== 'review' || ($record['type'] ?? '') !== 'hotel_tours') {
        turkey_hotel_review_fail('record_contract_' . $function);
    }
    if (($record['path'] ?? '') !== $href) turkey_hotel_review_fail('record_href_drift_' . $function);

    if (!preg_match('#^/country/turkey/hotel/([a-z0-9-]+)/$#', $href, $pathMatch)) {
        turkey_hotel_review_fail('invalid_hotel_href_' . $href);
    }
    $slug = $pathMatch[1];
    $state = is_array($record['data']['search_state'] ?? null) ? $record['data']['search_state'] : [];
    $hotelId = (int)($state['hotel'] ?? 0);
    if ((int)($state['country'] ?? 0) !== 4 || $hotelId <= 0 || !str_ends_with($slug, '-' . $hotelId)) {
        turkey_hotel_review_fail('record_identity_' . $slug);
    }
    if (isset($manifestHotelIds[$hotelId])) turkey_hotel_review_fail('duplicate_hotel_id_' . $hotelId);
    $manifestHotelIds[$hotelId] = true;

    $routeFile = __DIR__ . '/../v2' . $href . 'index.php';
    if (!is_file($routeFile)) turkey_hotel_review_fail('route_missing_' . $slug);
    $routeSource = file_get_contents($routeFile);
    if ($routeSource === false || !str_contains($routeSource, 'v2_seo_render_hotel_tour_review(') || !str_contains($routeSource, $function . '()')) {
        turkey_hotel_review_fail('route_contract_' . $slug);
    }

    $hotelName = mb_strtolower(trim((string)($record['data']['name'] ?? '')), 'UTF-8');
    $editorial = [
        'description' => (string)($record['data']['description'] ?? ''),
        'intro' => (string)($record['data']['intro'] ?? ''),
        'sections' => $record['data']['sections'] ?? [],
    ];
    $normalized = mb_strtolower((string)json_encode($editorial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'UTF-8');
    if ($hotelName !== '') $normalized = str_replace($hotelName, '{{hotel}}', $normalized);
    $fingerprint = hash('sha256', preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    if (isset($editorialFingerprints[$fingerprint])) {
        turkey_hotel_review_fail('duplicate_editorial_copy_' . $editorialFingerprints[$fingerprint] . '_' . $slug);
    }
    $editorialFingerprints[$fingerprint] = $slug;
}

$catalog = v2_seo_content_pilot_turkey_hotel_review_catalog();
$registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
$reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
$graph = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];

if (v2_seo_content_candidate_paths($catalog) !== []) {
    turkey_hotel_review_fail('review_family_became_publication_candidate');
}

$hotelPaths = [];
foreach ($registry as $path => $entry) {
    if (($entry['type'] ?? '') !== 'hotel_tours') continue;
    $hotelPaths[] = (string)$path;
    $report = $reports[$path] ?? [];
    if (($report['status'] ?? '') !== 'review') turkey_hotel_review_fail('hotel_not_review_' . $path);
    if (($report['publishable'] ?? false) !== true) turkey_hotel_review_fail('hotel_not_structurally_publishable_' . $path);
    if (($graph[$path]['parent'] ?? null) !== '/country/turkey/') turkey_hotel_review_fail('wrong_parent_' . $path);
    $page = $entry['page'] ?? [];
    $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
    if ((int)($state['country'] ?? 0) !== 4 || (int)($state['hotel'] ?? 0) <= 0) {
        turkey_hotel_review_fail('wrong_search_identity_' . $path);
    }
}

sort($hotelPaths, SORT_STRING);
$expectedPaths = array_keys($manifestPaths);
sort($expectedPaths, SORT_STRING);
if ($hotelPaths !== $expectedPaths) turkey_hotel_review_fail('manifest_registry_path_drift');
if (count($hotelPaths) !== $expectedHotelCount) {
    turkey_hotel_review_fail('manifest_registry_count_mismatch_' . $expectedHotelCount . '_' . count($hotelPaths));
}
if (count($registry) !== $expectedHotelCount + 1) {
    turkey_hotel_review_fail('expected_parent_plus_manifest_hotels');
}

$productionParent = v2_seo_content_pilot_turkey();
if (($productionParent['status'] ?? '') !== 'approved') turkey_hotel_review_fail('production_parent_status_changed');
foreach (($productionParent['data']['related'] ?? []) as $link) {
    $href = (string)($link['href'] ?? '');
    if (str_starts_with($href, '/country/turkey/hotel/')) {
        turkey_hotel_review_fail('production_parent_exposes_review_hotel_link');
    }
}

$reviewParent = $registry['/country/turkey/'] ?? [];
if (($reports['/country/turkey/']['status'] ?? '') !== 'review') turkey_hotel_review_fail('isolated_parent_not_review');
if (($reviewParent['type'] ?? '') !== 'country') turkey_hotel_review_fail('isolated_parent_type');

echo 'SEO_TURKEY_HOTEL_REVIEW_OK hotels=' . $expectedHotelCount . ' candidates=0 parentIsolation=1 country=4 uniqueHotelIds=' . count($manifestHotelIds) . ' uniqueEditorial=' . count($editorialFingerprints) . PHP_EOL;

require __DIR__ . '/seo-egypt-hotel-review-catalog-smoke.php';
