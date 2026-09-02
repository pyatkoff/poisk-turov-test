<?php
require_once __DIR__ . '/seo-hotel-launch-readiness-v1.php';

/**
 * Unified review signal for country, resort and hotel-tour SEO families.
 *
 * This returns diagnostics only. It never changes editorial status, publication
 * candidates, routes, robots, canonicals, sitemap output or launch flags.
 */
function v2_seo_page_launch_readiness(array $catalog, array $hotelSnapshotEvidence = [], ?int $nowEpoch = null): array
{
    $registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
    $reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
    $graph = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];
    $rows = [];

    foreach ($registry as $path => $entry) {
        $type = (string)($entry['type'] ?? '');
        if (!in_array($type, ['country', 'resort'], true)) continue;

        $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
        $report = is_array($reports[$path] ?? null) ? $reports[$path] : [];
        $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
        $errors = [];
        $score = 0;

        if (in_array((string)($report['status'] ?? ''), ['review', 'approved'], true)) $score += 10;
        else $errors[] = 'editorial_status';

        if (($report['publishable'] ?? false) === true) $score += 25;
        else $errors[] = 'not_structurally_publishable';

        $countryId = (int)($state['country'] ?? 0);
        if ($type === 'country') {
            if ($countryId > 0) $score += 20; else $errors[] = 'invalid_search_identity';
            $parent = trim((string)($graph[$path]['parent'] ?? ''));
            if ($parent === '') $score += 10; else $errors[] = 'country_must_be_root';
        } else {
            $regionId = (int)($state['region'] ?? 0);
            if ($countryId > 0 && $regionId > 0) $score += 20; else $errors[] = 'invalid_search_identity';
            $parent = trim((string)($graph[$path]['parent'] ?? ''));
            $parentEntry = $parent !== '' && isset($registry[$parent]) ? $registry[$parent] : null;
            if (is_array($parentEntry) && ($parentEntry['type'] ?? '') === 'country') $score += 10;
            else $errors[] = 'invalid_country_parent';
        }

        $title = trim((string)($page['title'] ?? ''));
        $h1 = trim((string)($page['h1'] ?? ''));
        $description = trim((string)($page['description'] ?? ''));
        if ($title !== '' && $h1 !== '' && $title !== $h1 && mb_strlen($description, 'UTF-8') >= 80) $score += 15;
        else $errors[] = 'metadata_quality';

        $intro = trim((string)($page['intro'] ?? ''));
        $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
        if (mb_strlen($intro, 'UTF-8') >= 80 && count($sections) >= 2) $score += 20;
        else $errors[] = 'editorial_depth';

        $rows[] = [
            'path' => (string)$path,
            'type' => $type,
            'country_id' => $countryId,
            'score' => $score,
            'ready_for_launch_review' => $score === 100 && $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    foreach (v2_seo_hotel_launch_readiness($catalog, $hotelSnapshotEvidence, $nowEpoch) as $hotel) {
        $hotel['type'] = 'hotel_tours';
        $rows[] = $hotel;
    }

    usort($rows, static function (array $a, array $b): int {
        $typeCmp = strcmp((string)($a['type'] ?? ''), (string)($b['type'] ?? ''));
        if ($typeCmp !== 0) return $typeCmp;
        return strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''));
    });

    return $rows;
}

function v2_seo_page_launch_readiness_summary(array $rows): array
{
    $summary = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $type = (string)($row['type'] ?? '');
        if (!in_array($type, ['country', 'resort', 'hotel_tours'], true)) continue;
        if (!isset($summary[$type])) {
            $summary[$type] = ['type' => $type, 'total' => 0, 'ready' => 0, 'blocked' => 0, 'error_counts' => []];
        }
        $summary[$type]['total']++;
        if (($row['ready_for_launch_review'] ?? false) === true) $summary[$type]['ready']++;
        else $summary[$type]['blocked']++;
        foreach (($row['errors'] ?? []) as $error) {
            $error = (string)$error;
            if ($error === '') continue;
            $summary[$type]['error_counts'][$error] = ($summary[$type]['error_counts'][$error] ?? 0) + 1;
        }
    }
    foreach ($summary as &$item) ksort($item['error_counts']);
    unset($item);
    ksort($summary);
    return array_values($summary);
}
