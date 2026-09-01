<?php
require_once __DIR__ . '/seo-sitemap-candidates-v1.php';

/**
 * First production SEO rollout slice.
 *
 * This is the single allowlist shared by indexation and sitemap generation.
 * Keeping it centralized prevents the two launch gates from drifting apart.
 */
function v2_seo_turkey_launch_paths(): array
{
    return [
        '/country/turkey/',
        '/country/turkey/alanya/',
        '/country/turkey/antalya/',
        '/country/turkey/belek/',
        '/country/turkey/kemer/',
        '/country/turkey/side/',
    ];
}

/**
 * Apply the first SEO rollout slice to site params without mutating source config.
 * The caller still owns the explicit production enable/disable decision.
 */
function v2_seo_turkey_launch_site_params(array $siteParams, bool $launchEnabled): array
{
    $siteParams['SEO_INDEXABLE'] = $launchEnabled;
    $siteParams['SEO_INDEXABLE_PATHS'] = $launchEnabled ? v2_seo_turkey_launch_paths() : [];
    return $siteParams;
}

function v2_seo_turkey_launch_sitemap_urls(array $catalog, bool $launchEnabled): array
{
    return v2_seo_sitemap_candidate_urls($catalog, $launchEnabled, v2_seo_turkey_launch_paths());
}

function v2_seo_turkey_launch_sitemap_xml(array $catalog, bool $launchEnabled): string
{
    return v2_seo_sitemap_candidate_xml($catalog, $launchEnabled, v2_seo_turkey_launch_paths());
}
