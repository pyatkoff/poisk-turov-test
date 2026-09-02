<?php
require_once __DIR__ . '/seo-sitemap-candidates-v1.php';

/**
 * First production SEO rollout slice retained for compatibility and rollback.
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
 * Second controlled production wave: existing country pages only.
 * Resort expansion and every hotel_tours route remain outside this allowlist.
 */
function v2_seo_second_wave_country_launch_paths(): array
{
    return [
        '/country/egypt/',
        '/country/maldives/',
    ];
}

/**
 * Single exact-path production indexation allowlist.
 */
function v2_seo_controlled_launch_paths(): array
{
    return array_values(array_merge(
        v2_seo_turkey_launch_paths(),
        v2_seo_second_wave_country_launch_paths()
    ));
}

/**
 * Apply the original Turkey rollout slice to site params without mutating source config.
 * Kept for compatibility with existing tests/tooling.
 */
function v2_seo_turkey_launch_site_params(array $siteParams, bool $launchEnabled): array
{
    $siteParams['SEO_INDEXABLE'] = $launchEnabled;
    $siteParams['SEO_INDEXABLE_PATHS'] = $launchEnabled ? v2_seo_turkey_launch_paths() : [];
    return $siteParams;
}

/**
 * Apply the current controlled SEO launch. A global flag can never open arbitrary
 * routes because seo-config still requires an exact path match against this list.
 */
function v2_seo_controlled_launch_site_params(array $siteParams, bool $launchEnabled): array
{
    $siteParams['SEO_INDEXABLE'] = $launchEnabled;
    $siteParams['SEO_INDEXABLE_PATHS'] = $launchEnabled ? v2_seo_controlled_launch_paths() : [];
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

function v2_seo_controlled_launch_sitemap_urls(array $catalog, bool $launchEnabled): array
{
    return v2_seo_sitemap_candidate_urls($catalog, $launchEnabled, v2_seo_controlled_launch_paths());
}

function v2_seo_controlled_launch_sitemap_xml(array $catalog, bool $launchEnabled): string
{
    return v2_seo_sitemap_candidate_xml($catalog, $launchEnabled, v2_seo_controlled_launch_paths());
}
