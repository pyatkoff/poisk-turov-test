<?php

/**
 * Compose independently reviewed SEO catalogs into one diagnostics-only registry.
 *
 * The union is intentionally stricter than a generic merge: every source must have
 * registry/report/graph parity, publication candidates must be empty, and no path
 * or search identity may collide. The result is suitable for unified readiness and
 * manifest inspection only; it does not mount routes or change publication state.
 */
function v2_seo_review_catalog_union(array $families): array
{
    if ($families === []) throw new InvalidArgumentException('SEO review catalog union requires families');

    $registry = [];
    $reports = [];
    $graph = [];
    $seenFamilyKeys = [];
    $seenIdentities = [];
    $familySummary = [];

    foreach ($families as $family) {
        if (!is_array($family)) throw new InvalidArgumentException('SEO review catalog union family must be an array');
        $key = strtolower(trim((string)($family['key'] ?? '')));
        $catalog = is_array($family['catalog'] ?? null) ? $family['catalog'] : [];
        if ($key === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{1,39}$/', $key) || isset($seenFamilyKeys[$key])) {
            throw new InvalidArgumentException('SEO review catalog union requires unique stable family keys');
        }
        $seenFamilyKeys[$key] = true;

        $r = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
        $p = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
        $g = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];
        $candidates = is_array($catalog['publication_candidates'] ?? null) ? array_values($catalog['publication_candidates']) : [];
        if ($r === []) throw new InvalidArgumentException('SEO review catalog union family registry is empty: ' . $key);
        if ($candidates !== []) throw new InvalidArgumentException('SEO review catalog union forbids publication candidates: ' . $key);

        $rk = array_keys($r); $pk = array_keys($p); $gk = array_keys($g);
        sort($rk, SORT_STRING); sort($pk, SORT_STRING); sort($gk, SORT_STRING);
        if ($rk !== $pk || $rk !== $gk) throw new InvalidArgumentException('SEO review catalog union parity failure: ' . $key);

        $typeCounts = ['country'=>0, 'resort'=>0, 'hotel_tours'=>0];
        foreach ($r as $path => $entry) {
            $path = trim((string)$path);
            if ($path === '' || isset($registry[$path])) throw new InvalidArgumentException('SEO review catalog union path collision: ' . $path);
            if (!is_array($entry)) throw new InvalidArgumentException('SEO review catalog union registry entry must be an array');
            $type = (string)($entry['type'] ?? '');
            if (!isset($typeCounts[$type])) throw new InvalidArgumentException('SEO review catalog union unsupported type: ' . $type);
            $typeCounts[$type]++;

            $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
            $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
            $countryId = (int)($state['country'] ?? 0);
            $regionId = (int)($state['region'] ?? 0);
            $hotelId = (int)($state['hotel'] ?? 0);
            if ($type === 'country') $identity = 'country:' . $countryId;
            elseif ($type === 'resort') $identity = 'resort:' . $countryId . ':' . $regionId;
            else $identity = 'hotel:' . $countryId . ':' . $hotelId;
            if ($countryId <= 0 || str_ends_with($identity, ':0') || isset($seenIdentities[$identity])) {
                throw new InvalidArgumentException('SEO review catalog union identity collision or invalid identity: ' . $identity);
            }
            $seenIdentities[$identity] = $path;

            $report = is_array($p[$path] ?? null) ? $p[$path] : [];
            if ($type === 'hotel_tours' && ($report['status'] ?? '') !== 'review') {
                throw new InvalidArgumentException('SEO review catalog union hotel_tours must remain review: ' . $path);
            }

            $registry[$path] = $entry;
            $reports[$path] = $report;
            $graph[$path] = is_array($g[$path] ?? null) ? $g[$path] : [];
        }

        $familySummary[] = [
            'key'=>$key,
            'registry_count'=>count($r),
            'type_counts'=>$typeCounts,
            'publication_candidate_count'=>0,
        ];
    }

    ksort($registry, SORT_STRING);
    ksort($reports, SORT_STRING);
    ksort($graph, SORT_STRING);
    usort($familySummary, static fn(array $a,array $b):int => strcmp($a['key'],$b['key']));

    $fingerprintRows = [];
    foreach ($registry as $path => $entry) {
        $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
        $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
        $fingerprintRows[] = [
            'path'=>$path,
            'type'=>(string)($entry['type'] ?? ''),
            'country'=>(int)($state['country'] ?? 0),
            'region'=>(int)($state['region'] ?? 0),
            'hotel'=>(int)($state['hotel'] ?? 0),
            'status'=>(string)($reports[$path]['status'] ?? ''),
            'parent'=>(string)($graph[$path]['parent'] ?? ''),
        ];
    }
    $fingerprint = hash('sha256', json_encode($fingerprintRows, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));

    return [
        'registry'=>$registry,
        'reports'=>$reports,
        'graph'=>$graph,
        'publication_candidates'=>[],
        'review_union'=>[
            'state'=>'review_only_catalog_union',
            'family_count'=>count($familySummary),
            'registry_count'=>count($registry),
            'families'=>$familySummary,
            'registry_sha256'=>$fingerprint,
            'publication_allowed'=>false,
            'hotel_tours_publication_allowed'=>false,
            'hotel_tours_indexation_allowed'=>false,
            'sitemap_allowed'=>false,
            'canonical_launch_allowed'=>false,
            'route_launch_allowed'=>false,
        ],
    ];
}
