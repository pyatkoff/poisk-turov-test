<?php
require_once __DIR__ . '/seo-page-registry-v1.php';

/**
 * Validate parent/related relationships between already-registered SEO pages.
 * Relations cannot create new URLs; every referenced path must exist in the registry.
 */
function v2_seo_page_graph(array $registry, array $relations): array
{
    $graph = [];
    foreach ($registry as $path => $record) {
        $graph[$path] = ['parent' => null, 'related' => []];
    }

    foreach ($relations as $path => $relation) {
        $path = v2_seo_registry_path($path);
        if (!isset($registry[$path])) throw new InvalidArgumentException('SEO graph source path is not registered: '.$path);
        if (!is_array($relation)) continue;

        $parent = null;
        if (isset($relation['parent']) && trim((string)$relation['parent']) !== '') {
            $parent = v2_seo_registry_path($relation['parent']);
            if ($parent === $path) throw new InvalidArgumentException('SEO page cannot be its own parent');
            if (!isset($registry[$parent])) throw new InvalidArgumentException('SEO graph parent is not registered: '.$parent);
        }

        $related = [];
        $seen = [];
        foreach (($relation['related'] ?? []) as $target) {
            $target = v2_seo_registry_path($target);
            if ($target === $path || isset($seen[$target])) continue;
            if (!isset($registry[$target])) throw new InvalidArgumentException('SEO graph related path is not registered: '.$target);
            $seen[$target] = true;
            $related[] = $target;
        }

        $graph[$path] = ['parent' => $parent, 'related' => $related];
    }

    foreach (array_keys($graph) as $start) {
        $seen = [];
        $cursor = $start;
        while (($parent = $graph[$cursor]['parent'] ?? null) !== null) {
            if (isset($seen[$parent]) || $parent === $start) {
                throw new InvalidArgumentException('SEO graph parent cycle detected');
            }
            $seen[$parent] = true;
            $cursor = $parent;
        }
    }

    return $graph;
}

function v2_seo_graph_link_group(array $registry, array $graph, string $path, string $title = 'Другие направления'): array
{
    $path = v2_seo_registry_path($path);
    if (!isset($registry[$path], $graph[$path])) return [];

    $links = [];
    foreach ($graph[$path]['related'] as $target) {
        $record = $registry[$target] ?? null;
        if (!is_array($record)) continue;
        $label = trim((string)($record['page']['entity_name'] ?? $record['page']['h1'] ?? ''));
        if ($label === '') continue;
        $links[] = ['label' => $label, 'href' => $target];
    }

    if (!$links) return [];
    return ['title' => trim($title) ?: 'Другие направления', 'links' => $links];
}
