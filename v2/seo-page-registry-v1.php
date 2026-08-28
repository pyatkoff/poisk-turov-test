<?php
require_once __DIR__ . '/seo-page-types-v1.php';

/**
 * Curated source boundary for future public SEO landing pages.
 * Registry entries are editorial records, not request/search state.
 * This helper does not publish routes or change indexing policy by itself.
 */
function v2_seo_registry_path($value): string
{
    $path = v2_seo_internal_href($value);
    if ($path === null || str_contains($path, '?') || str_contains($path, '#')) {
        throw new InvalidArgumentException('SEO registry path must be a clean first-party path');
    }
    if ($path !== '/' && !str_ends_with($path, '/')) {
        throw new InvalidArgumentException('SEO registry path must end with a slash');
    }
    return $path;
}

function v2_seo_page_registry(array $entries): array
{
    $registry = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) continue;

        $path = v2_seo_registry_path($entry['path'] ?? '');
        if (isset($registry[$path])) {
            throw new InvalidArgumentException('Duplicate SEO registry path: '.$path);
        }

        $type = strtolower(trim((string)($entry['type'] ?? '')));
        $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
        $page = v2_seo_destination_page($type, $data);

        $registry[$path] = [
            'path' => $path,
            'type' => $page['page_type'],
            'page' => $page,
        ];
    }
    return $registry;
}

function v2_seo_registry_page(array $registry, string $path): ?array
{
    $path = v2_seo_registry_path($path);
    return $registry[$path]['page'] ?? null;
}

function v2_seo_registry_paths(array $registry): array
{
    return array_keys($registry);
}
