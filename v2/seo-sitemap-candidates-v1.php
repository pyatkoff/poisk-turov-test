<?php
require_once __DIR__ . '/seo-publication-manifest-v1.php';

/**
 * Build sitemap URLs only from approved + publishable publication candidates.
 *
 * Launch must be explicit and path-selective. A global launch flag with no
 * allowlist intentionally emits zero URLs so future approved pages cannot leak
 * into the sitemap before their own rollout decision.
 */
function v2_seo_sitemap_candidate_urls(array $catalog, bool $launchEnabled, array $allowedPaths = []): array
{
    if (!$launchEnabled || $allowedPaths === []) return [];

    $allowed = [];
    foreach ($allowedPaths as $path) {
        if (!is_string($path) || trim($path) === '') continue;
        $allowed[v2_seo_registry_path($path)] = true;
    }
    if ($allowed === []) return [];

    $urls = [];
    foreach (v2_seo_publication_manifest($catalog) as $entry) {
        $path = v2_seo_registry_path($entry['path'] ?? '');
        if (!isset($allowed[$path])) continue;
        $urls[] = 'https://anytoour.ru' . $path;
    }

    sort($urls, SORT_STRING);
    return array_values(array_unique($urls));
}

function v2_seo_sitemap_candidate_xml(array $catalog, bool $launchEnabled, array $allowedPaths = []): string
{
    $urls = v2_seo_sitemap_candidate_urls($catalog, $launchEnabled, $allowedPaths);
    $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
    foreach ($urls as $url) {
        $lines[] = '  <url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
    }
    $lines[] = '</urlset>';
    return implode("\n", $lines) . "\n";
}
