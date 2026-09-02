<?php
require_once __DIR__ . '/seo-content-catalog-v1.php';

/**
 * Review-only publication manifest for future SEO pages.
 * This manifest never mounts routes, emits canonical tags, generates sitemap entries,
 * adds structured data, or changes indexability. It only summarizes editorial records
 * that already passed the catalog's approved + publishable gate.
 */
function v2_seo_publication_manifest(array $catalog): array
{
    $registry = is_array($catalog['registry'] ?? null) ? $catalog['registry'] : [];
    $reports = is_array($catalog['reports'] ?? null) ? $catalog['reports'] : [];
    $graph = is_array($catalog['graph'] ?? null) ? $catalog['graph'] : [];
    $candidatePaths = v2_seo_content_candidate_paths($catalog);
    $publicationTypes = ['country' => true, 'resort' => true, 'seasonal' => true];

    $manifest = [];
    foreach ($candidatePaths as $path) {
        $path = v2_seo_registry_path($path);
        $registered = $registry[$path] ?? null;
        $report = $reports[$path] ?? null;
        if (!is_array($registered) || !is_array($report)) {
            throw new InvalidArgumentException('Publication candidate is missing registry/report state: '.$path);
        }
        if (($report['status'] ?? '') !== 'approved' || ($report['publishable'] ?? false) !== true) {
            throw new InvalidArgumentException('Publication candidate no longer satisfies approved publishability gate: '.$path);
        }

        $type = strtolower(trim((string)($registered['type'] ?? '')));
        if (!isset($publicationTypes[$type])) {
            throw new InvalidArgumentException('Publication candidate type requires a separate launch decision: '.$type);
        }

        $page = is_array($registered['page'] ?? null) ? $registered['page'] : [];
        $manifest[] = [
            'id' => v2_seo_content_id($report['id'] ?? ''),
            'path' => $path,
            'type' => $type,
            'h1' => trim((string)($page['h1'] ?? '')),
            'title' => trim((string)($page['title'] ?? '')),
            'description' => trim((string)($page['description'] ?? '')),
            'parent' => $graph[$path]['parent'] ?? null,
            'related' => array_values(is_array($graph[$path]['related'] ?? null) ? $graph[$path]['related'] : []),
            'review_state' => 'ready_for_publication_decision',
        ];
    }

    usort($manifest, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
    return $manifest;
}

function v2_seo_publication_manifest_json(array $catalog): string
{
    return json_encode(
        v2_seo_publication_manifest($catalog),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
}
