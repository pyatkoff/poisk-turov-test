<?php

/**
 * Build a proposal-only hotel-tour launch slice from an explicit reviewed path list.
 *
 * This helper intentionally does not auto-rank or publish pages. Every proposed path
 * must already have a 100/100 launch-readiness result and must be named explicitly
 * by the caller. The output is review metadata only and is not consumed by robots,
 * sitemap, canonical or route mounting.
 */
function v2_seo_hotel_launch_candidate_proposal(array $readinessRows, array $priorityPaths, int $maxTotal = 15): array
{
    if ($maxTotal < 1 || $maxTotal > 50) {
        throw new InvalidArgumentException('Hotel launch proposal maxTotal must be between 1 and 50');
    }

    $byPath = [];
    foreach ($readinessRows as $row) {
        if (!is_array($row)) continue;
        $path = trim((string)($row['path'] ?? ''));
        if ($path === '') continue;
        if (isset($byPath[$path])) {
            throw new InvalidArgumentException('Duplicate hotel launch-readiness path: ' . $path);
        }
        $byPath[$path] = $row;
    }

    $seen = [];
    $proposal = [];
    foreach ($priorityPaths as $path) {
        $path = trim((string)$path);
        if ($path === '' || isset($seen[$path])) {
            throw new InvalidArgumentException('Hotel launch proposal contains an empty or duplicate path');
        }
        $seen[$path] = true;

        $row = $byPath[$path] ?? null;
        if (!is_array($row)) {
            throw new InvalidArgumentException('Hotel launch proposal path is missing readiness evidence: ' . $path);
        }
        if (($row['ready_for_launch_review'] ?? false) !== true || (int)($row['score'] ?? 0) !== 100) {
            throw new InvalidArgumentException('Hotel launch proposal path is not 100/100 launch-ready: ' . $path);
        }
        if (count($proposal) >= $maxTotal) {
            throw new InvalidArgumentException('Hotel launch proposal exceeds explicit maximum size');
        }

        $proposal[] = [
            'path' => $path,
            'country_id' => (int)($row['country_id'] ?? 0),
            'hotel_id' => (int)($row['hotel_id'] ?? 0),
            'score' => 100,
            'state' => 'proposal_only_requires_launch_approval',
        ];
    }

    return $proposal;
}
