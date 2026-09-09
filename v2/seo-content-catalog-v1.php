<?php
require_once __DIR__ . '/seo-publishability-v1.php';
require_once __DIR__ . '/seo-page-graph-v1.php';

/**
 * Controlled editorial source for future SEO pages.
 * Catalog state never publishes routes, canonicals, sitemap entries or indexing policy.
 */
function v2_seo_content_id($value): string
{
    $id = strtolower(trim((string)$value));
    if ($id === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,79}$/', $id)) {
        throw new InvalidArgumentException('SEO content id must be a stable lowercase editorial identifier');
    }
    return $id;
}

function v2_seo_content_status($value): string
{
    $status = strtolower(trim((string)$value));
    if (!in_array($status, ['draft', 'review', 'approved'], true)) {
        throw new InvalidArgumentException('Unsupported SEO editorial status');
    }
    return $status;
}

function v2_seo_content_catalog(array $records, array $relations = []): array
{
    $ids = [];
    $entries = [];
    $metaByPath = [];

    foreach ($records as $record) {
        if (!is_array($record)) continue;
        $id = v2_seo_content_id($record['id'] ?? '');
        if (isset($ids[$id])) throw new InvalidArgumentException('Duplicate SEO content id: '.$id);
        $ids[$id] = true;

        $status = v2_seo_content_status($record['status'] ?? 'draft');
        $path = v2_seo_registry_path($record['path'] ?? '');
        $type = strtolower(trim((string)($record['type'] ?? '')));
        if ($type === 'hotel_tours' && $status === 'approved') {
            throw new InvalidArgumentException('Hotel-tour records are review-only until a separate indexation launch decision');
        }
        $data = is_array($record['data'] ?? null) ? $record['data'] : [];

        $entries[] = ['path' => $path, 'type' => $type, 'data' => $data];
        $metaByPath[$path] = ['id' => $id, 'status' => $status];
    }

    $registry = v2_seo_page_registry($entries);
    $graph = v2_seo_page_graph($registry, $relations);
    $reports = [];
    $candidates = [];

    foreach ($registry as $path => $registered) {
        $meta = $metaByPath[$path];
        $report = v2_seo_publishability_report($registered);
        $reports[$path] = [
            'id' => $meta['id'],
            'status' => $meta['status'],
            'publishable' => $report['publishable'],
            'errors' => $report['errors'],
        ];
        if ($meta['status'] === 'approved' && $report['publishable']) {
            $candidates[] = $path;
        }
    }

    return [
        'registry' => $registry,
        'graph' => $graph,
        'reports' => $reports,
        'publication_candidates' => $candidates,
    ];
}

function v2_seo_content_candidate_paths(array $catalog): array
{
    return is_array($catalog['publication_candidates'] ?? null)
        ? array_values($catalog['publication_candidates'])
        : [];
}
