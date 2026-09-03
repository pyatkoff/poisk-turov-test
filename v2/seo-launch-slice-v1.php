<?php
require_once __DIR__ . '/seo-sitemap-candidates-v1.php';
require_once __DIR__ . '/seo-core-month-matrix-v1.php';
require_once __DIR__ . '/seo-core-resort-launch-state-v1.php';

/** First production SEO rollout slice retained for compatibility and rollback. */
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

/** Second controlled production wave: existing country pages only. */
function v2_seo_second_wave_country_launch_paths(): array
{
    return [
        '/country/egypt/',
        '/country/maldives/',
    ];
}

/** Compatibility helper for the first two seasonal pages launched earlier. */
function v2_seo_seasonal_september_launch_paths(): array
{
    return [
        '/country/turkey/antalya/september/',
        '/country/maldives/september/',
    ];
}

/** Core scalable country/resort month family: 36 country-month + 60 Turkey resort-month. */
function v2_seo_core_month_launch_paths(): array
{
    return array_values(array_map(
        static fn(array $row): string => (string)$row['path'],
        v2_seo_core_month_matrix()['rows']
    ));
}

/** Source-controlled baseline. Dynamic Egypt/Maldives resort routes are added separately. */
function v2_seo_static_controlled_launch_paths(): array
{
    return array_values(array_unique(array_merge(
        v2_seo_turkey_launch_paths(),
        v2_seo_second_wave_country_launch_paths(),
        v2_seo_core_month_launch_paths()
    )));
}

/**
 * Single exact-path production indexation allowlist.
 * Generated Egypt/Maldives resort routes enter only after the production
 * materializer writes a validated launch manifest. hotel_tours stay absent.
 */
function v2_seo_controlled_launch_paths(): array
{
    $paths=array_values(array_unique(array_merge(
        v2_seo_static_controlled_launch_paths(),
        v2_seo_core_resort_launch_paths()
    )));
    sort($paths,SORT_STRING);
    return $paths;
}

function v2_seo_turkey_launch_site_params(array $siteParams, bool $launchEnabled): array
{
    $siteParams['SEO_INDEXABLE'] = $launchEnabled;
    $siteParams['SEO_INDEXABLE_PATHS'] = $launchEnabled ? v2_seo_turkey_launch_paths() : [];
    return $siteParams;
}

/** A global flag can never open arbitrary routes because seo-config requires an exact path match. */
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
