<?php
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-hotel-review-catalog-v1.php';

function turkey_hotel_review_fail(string $message): void
{
    fwrite(STDERR, "SEO_TURKEY_HOTEL_REVIEW_FAIL:$message\n");
    exit(1);
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
if (count($hotelPaths) !== 6) turkey_hotel_review_fail('expected_6_hotels_got_' . count($hotelPaths));
if (count($registry) !== 7) turkey_hotel_review_fail('expected_parent_plus_6_hotels');

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

echo 'SEO_TURKEY_HOTEL_REVIEW_OK hotels=6 candidates=0 parentIsolation=1 country=4' . PHP_EOL;
