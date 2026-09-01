<?php
require_once __DIR__ . '/seo-publication-manifest-v1.php';

/**
 * Build sitemap URLs only from approved + publishable publication candidates.
 *
 * This helper never writes sitemap.xml and never changes indexability. Callers must
 * also pass the explicit production launch gate before any URL is emitted.
 */
function v2_seo_sitemap_candidate_urls(array $catalog, bool $launchEnabled): array
{
    if (!$launchEnabled) return [];

    $urls = [];
    foreach (v2_seo_publication_manifest($catalog) as $entry) {
        $path = v2_seo_registry_path($entry['path'] ?? '');
        $urls[] = 'https://anytoour.ru' . $path;
    }

    sort($urls, SORT_STRING);
    return array_values(array_unique($urls));
}

function v2_seo_sitemap_candidate_xml(array $catalog, bool $launchEnabled): string
{
    $urls = v2_seo_sitemap_candidate_urls($catalog, $launchEnabled);
    $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
    foreach ($urls as $url) {
        $lines[] = '  <url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
    }
    $lines[] = '</urlset>';
    return implode("\n", $lines) . "\n";
}
