<?php
require_once __DIR__ . '/seo-hotel-launch-readiness-v1.php';

/**
 * Summarize review-only hotel launch readiness across named country families.
 *
 * Each family is supplied explicitly as:
 *   ['key' => 'turkey', 'catalog' => [...], 'evidence' => [...]]
 *
 * The summary is diagnostic metadata only. It does not auto-rank hotels across
 * countries, approve records, alter robots/canonical, write sitemap entries or
 * enable any launch flag.
 */
function v2_seo_hotel_family_launch_readiness_summary(array $families, ?int $nowEpoch = null): array
{
    $seenKeys = [];
    $summary = [];

    foreach ($families as $family) {
        if (!is_array($family)) {
            throw new InvalidArgumentException('Hotel launch-readiness family must be an array');
        }

        $key = strtolower(trim((string)($family['key'] ?? '')));
        if ($key === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{1,39}$/', $key)) {
            throw new InvalidArgumentException('Hotel launch-readiness family key is invalid');
        }
        if (isset($seenKeys[$key])) {
            throw new InvalidArgumentException('Duplicate hotel launch-readiness family key: ' . $key);
        }
        $seenKeys[$key] = true;

        $catalog = is_array($family['catalog'] ?? null) ? $family['catalog'] : [];
        $evidence = is_array($family['evidence'] ?? null) ? $family['evidence'] : [];
        $rows = v2_seo_hotel_launch_readiness($catalog, $evidence, $nowEpoch);

        $scores = [];
        $ready = 0;
        $errorCounts = [];
        foreach ($rows as $row) {
            $score = (int)($row['score'] ?? 0);
            $scores[] = $score;
            if (($row['ready_for_launch_review'] ?? false) === true) $ready++;
            foreach ((array)($row['errors'] ?? []) as $error) {
                $error = trim((string)$error);
                if ($error === '') continue;
                $errorCounts[$error] = ($errorCounts[$error] ?? 0) + 1;
            }
        }
        ksort($errorCounts, SORT_STRING);

        $total = count($rows);
        $summary[] = [
            'key' => $key,
            'total' => $total,
            'ready_for_launch_review' => $ready,
            'blocked' => $total - $ready,
            'average_score' => $total > 0 ? round(array_sum($scores) / $total, 1) : 0.0,
            'min_score' => $total > 0 ? min($scores) : 0,
            'max_score' => $total > 0 ? max($scores) : 0,
            'error_counts' => $errorCounts,
            'state' => 'review_only_launch_readiness_summary',
        ];
    }

    usort($summary, static fn(array $a, array $b): int => strcmp((string)$a['key'], (string)$b['key']));

    return $summary;
}
