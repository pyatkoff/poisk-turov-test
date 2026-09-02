<?php
require_once __DIR__ . '/seo-content-catalog-v1.php';

/**
 * Build a review-only hotel-tour family around one country editorial record.
 *
 * This helper keeps the family relationship explicit and fail-closed:
 * - every hotel child must belong to the same verified country ID;
 * - every child route must live below the country `/hotel/` namespace;
 * - the country editorial `related` list must match the hotel record set exactly;
 * - all child graph parents are derived from the country path in one place.
 *
 * It does not publish routes, enable indexation or create sitemap entries.
 */
function v2_seo_hotel_family_catalog(array $countryRecord, array $hotelRecords): array
{
    if (($countryRecord['type'] ?? '') !== 'country') {
        throw new InvalidArgumentException('SEO hotel family requires a country parent record');
    }

    $countryPath = v2_seo_registry_path($countryRecord['path'] ?? '');
    $countryState = is_array($countryRecord['data']['search_state'] ?? null)
        ? $countryRecord['data']['search_state']
        : [];
    $countryId = (int)($countryState['country'] ?? 0);
    if ($countryId <= 0) {
        throw new InvalidArgumentException('SEO hotel family requires a verified country ID');
    }

    $hotelPrefix = rtrim($countryPath, '/') . '/hotel/';
    $hotelPaths = [];
    $relations = [];
    foreach ($hotelRecords as $record) {
        if (!is_array($record) || ($record['type'] ?? '') !== 'hotel_tours') {
            throw new InvalidArgumentException('SEO hotel family children must be hotel_tours records');
        }
        if (($record['status'] ?? '') !== 'review') {
            throw new InvalidArgumentException('SEO hotel family children must remain review-only');
        }

        $path = v2_seo_registry_path($record['path'] ?? '');
        if (!str_starts_with($path, $hotelPrefix)) {
            throw new InvalidArgumentException('SEO hotel family child path is outside its country namespace');
        }
        if (isset($hotelPaths[$path])) {
            throw new InvalidArgumentException('Duplicate SEO hotel family child path: ' . $path);
        }

        $state = is_array($record['data']['search_state'] ?? null)
            ? $record['data']['search_state']
            : [];
        if ((int)($state['country'] ?? 0) !== $countryId || (int)($state['hotel'] ?? 0) <= 0) {
            throw new InvalidArgumentException('SEO hotel family child identity does not match the country parent');
        }

        $hotelPaths[$path] = true;
        $relations[$path] = ['parent' => $countryPath];
    }

    $linkedHotelPaths = [];
    foreach (($countryRecord['data']['related'] ?? []) as $link) {
        if (!is_array($link)) continue;
        $href = trim((string)($link['href'] ?? ''));
        if (!str_starts_with($href, $hotelPrefix)) continue;
        $href = v2_seo_registry_path($href);
        if (isset($linkedHotelPaths[$href])) {
            throw new InvalidArgumentException('Duplicate SEO hotel family parent link: ' . $href);
        }
        $linkedHotelPaths[$href] = true;
    }

    $expected = array_keys($hotelPaths);
    $linked = array_keys($linkedHotelPaths);
    sort($expected, SORT_STRING);
    sort($linked, SORT_STRING);
    if ($expected !== $linked) {
        throw new InvalidArgumentException('SEO hotel family parent links do not match hotel records');
    }

    $catalog = v2_seo_content_catalog(array_merge([$countryRecord], $hotelRecords), $relations);
    $registeredHotelPaths = [];
    foreach (($catalog['registry'] ?? []) as $path => $entry) {
        if (($entry['type'] ?? '') === 'hotel_tours') $registeredHotelPaths[] = (string)$path;
    }
    sort($registeredHotelPaths, SORT_STRING);
    if ($registeredHotelPaths !== $expected) {
        throw new InvalidArgumentException('SEO hotel family catalog registry drift');
    }

    return $catalog;
}
