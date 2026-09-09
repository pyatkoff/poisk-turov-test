<?php

/**
 * Cross-family integrity registry for review-only hotel-tour SEO catalogs.
 *
 * This diagnostic layer makes Turkey/Maldives/Egypt comparable without merging
 * their editorial catalogs or changing publication state. It fails closed on
 * candidate leakage, identity/path collisions, wrong country identity or broken
 * parent relationships.
 */
function v2_seo_hotel_family_integrity(array $families): array
{
    $seenKeys = [];
    $seenPaths = [];
    $seenIdentities = [];
    $result = [];

    foreach ($families as $family) {
        if (!is_array($family)) throw new InvalidArgumentException('SEO hotel family integrity requires family arrays');
        $key = strtolower(trim((string)($family['key'] ?? '')));
        $countryId = (int)($family['country_id'] ?? 0);
        $catalog = is_array($family['catalog'] ?? null) ? $family['catalog'] : [];
        if ($key === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) || isset($seenKeys[$key])) {
            throw new InvalidArgumentException('SEO hotel family integrity requires unique stable family keys');
        }
        if ($countryId <= 0) throw new InvalidArgumentException('SEO hotel family integrity requires positive country ID');
        $seenKeys[$key] = true;

        $registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
        $reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
        $graph = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];
        $candidates = is_array($catalog['publication_candidates'] ?? null) ? $catalog['publication_candidates'] : [];
        if ($candidates !== []) {
            throw new InvalidArgumentException('SEO hotel review family leaked publication candidates: ' . $key);
        }

        $hotelCount = 0;
        $parentPaths = [];
        foreach ($registry as $path => $entry) {
            if (($entry['type'] ?? '') !== 'hotel_tours') continue;
            $hotelCount++;
            $path = (string)$path;
            if (isset($seenPaths[$path])) throw new InvalidArgumentException('Duplicate hotel-tour path across families: ' . $path);
            $seenPaths[$path] = $key;

            $report = is_array($reports[$path] ?? null) ? $reports[$path] : [];
            if (($report['status'] ?? '') !== 'review' || ($report['publishable'] ?? false) !== true) {
                throw new InvalidArgumentException('Hotel-tour family contains non-review or structurally blocked page: ' . $path);
            }

            $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
            $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
            $pageCountry = (int)($state['country'] ?? 0);
            $hotelId = (int)($state['hotel'] ?? 0);
            if ($pageCountry !== $countryId || $hotelId <= 0) {
                throw new InvalidArgumentException('Hotel-tour family identity mismatch: ' . $path);
            }
            if (!str_ends_with(rtrim($path, '/'), '-' . $hotelId)) {
                throw new InvalidArgumentException('Hotel-tour family route/hotel mismatch: ' . $path);
            }

            $identity = $countryId . ':' . $hotelId;
            if (isset($seenIdentities[$identity])) {
                throw new InvalidArgumentException('Duplicate hotel identity across families: ' . $identity);
            }
            $seenIdentities[$identity] = $path;

            $parent = trim((string)($graph[$path]['parent'] ?? ''));
            $parentEntry = $parent !== '' && isset($registry[$parent]) ? $registry[$parent] : null;
            if (!is_array($parentEntry) || ($parentEntry['type'] ?? '') !== 'country') {
                throw new InvalidArgumentException('Hotel-tour family parent is not a registered country: ' . $path);
            }
            $parentState = is_array($parentEntry['page']['search_state'] ?? null) ? $parentEntry['page']['search_state'] : [];
            if ((int)($parentState['country'] ?? 0) !== $countryId) {
                throw new InvalidArgumentException('Hotel-tour family parent country mismatch: ' . $path);
            }
            $parentPaths[$parent] = true;
        }

        if ($hotelCount < 1) throw new InvalidArgumentException('SEO hotel family integrity requires at least one hotel page: ' . $key);
        if (count($parentPaths) !== 1) throw new InvalidArgumentException('SEO hotel family must have exactly one country parent: ' . $key);

        $result[] = [
            'key' => $key,
            'country_id' => $countryId,
            'hotel_count' => $hotelCount,
            'country_parent' => array_key_first($parentPaths),
            'publication_candidates' => 0,
            'state' => 'review_noindex_integrity_only',
        ];
    }

    usort($result, static fn(array $a, array $b): int => strcmp((string)$a['key'], (string)$b['key']));
    return [
        'families' => $result,
        'family_count' => count($result),
        'hotel_count' => count($seenIdentities),
        'unique_paths' => count($seenPaths),
        'unique_country_hotel_identities' => count($seenIdentities),
        'publication_candidates' => 0,
        'state' => 'review_noindex_integrity_only',
    ];
}
