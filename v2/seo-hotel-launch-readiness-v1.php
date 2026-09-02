<?php
require_once __DIR__ . '/seo-content-catalog-v1.php';

/**
 * Review-only launch-readiness assessment for hotel-tour pages.
 *
 * This is deliberately stricter than structural publishability and does NOT
 * approve, index, mount, canonicalize or add anything to a sitemap. It only
 * identifies records that are complete enough for a human launch review.
 */
function v2_seo_hotel_launch_readiness(array $catalog, array $snapshotEvidence = [], ?int $nowEpoch = null): array
{
    $registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
    $reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
    $graph = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];
    $nowEpoch = $nowEpoch ?? time();

    $evidenceByHotel = [];
    foreach ($snapshotEvidence as $row) {
        if (!is_array($row)) continue;
        $hotelId = (int)($row['hotel_id'] ?? 0);
        if ($hotelId > 0) $evidenceByHotel[$hotelId] = $row;
    }

    $rows = [];
    foreach ($registry as $path => $entry) {
        if (($entry['type'] ?? '') !== 'hotel_tours') continue;

        $page = is_array($entry['page'] ?? null) ? $entry['page'] : [];
        $report = is_array($reports[$path] ?? null) ? $reports[$path] : [];
        $state = is_array($page['search_state'] ?? null) ? $page['search_state'] : [];
        $countryId = (int)($state['country'] ?? 0);
        $hotelId = (int)($state['hotel'] ?? 0);
        $parent = (string)($graph[$path]['parent'] ?? '');
        $errors = [];
        $score = 0;

        if (($report['status'] ?? '') === 'review') $score += 10; else $errors[] = 'not_review';
        if (($report['publishable'] ?? false) === true) $score += 20; else $errors[] = 'not_structurally_publishable';
        if ($countryId > 0 && $hotelId > 0) $score += 15; else $errors[] = 'invalid_search_identity';
        if ($hotelId > 0 && str_ends_with(rtrim((string)$path, '/'), '-' . $hotelId)) $score += 10; else $errors[] = 'path_hotel_id_mismatch';
        if ($parent !== '' && str_starts_with((string)$path, rtrim($parent, '/') . '/hotel/')) $score += 10; else $errors[] = 'invalid_country_parent';

        $intro = trim((string)($page['intro'] ?? ''));
        $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
        if (mb_strlen($intro, 'UTF-8') >= 80 && count($sections) >= 3) $score += 15; else $errors[] = 'editorial_depth';

        $title = trim((string)($page['title'] ?? ''));
        $h1 = trim((string)($page['h1'] ?? ''));
        $description = trim((string)($page['description'] ?? ''));
        if ($title !== '' && $h1 !== '' && $description !== '' && $title !== $h1 && mb_strlen($description, 'UTF-8') >= 80) {
            $score += 10;
        } else {
            $errors[] = 'metadata_quality';
        }

        $evidence = $evidenceByHotel[$hotelId] ?? null;
        $evidenceFresh = false;
        if (is_array($evidence)) {
            $evidenceCountry = (int)($evidence['country_id'] ?? 0);
            $slug = trim((string)($evidence['hotel_slug'] ?? ''));
            $epoch = (int)($evidence['evidence_epoch'] ?? 0);
            $freshness = (int)($evidence['freshness_seconds'] ?? 0);
            $pathSlug = basename(rtrim((string)$path, '/'));
            $evidenceFresh = $evidenceCountry === $countryId
                && $slug === $pathSlug
                && $epoch > 0
                && $freshness > 0
                && $epoch <= $nowEpoch + 300
                && ($epoch + $freshness) >= $nowEpoch;
        }
        if ($evidenceFresh) $score += 10; else $errors[] = 'fresh_identity_evidence_required';

        $rows[] = [
            'path' => (string)$path,
            'country_id' => $countryId,
            'hotel_id' => $hotelId,
            'score' => $score,
            'ready_for_launch_review' => $score === 100 && $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        $scoreCmp = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        return $scoreCmp !== 0 ? $scoreCmp : strcmp((string)$a['path'], (string)$b['path']);
    });

    return $rows;
}
