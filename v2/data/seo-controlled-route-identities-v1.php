<?php
declare(strict_types=1);

/**
 * Exact identity -> public route bindings for the current controlled SEO cohort.
 * This registry is intentionally small and explicit: DB/catalog slugs are not SEO route authority.
 */
function v2_seo_controlled_route_identities(): array
{
    return [
        'country:country=4'=>'/country/turkey/',
        'country:country=1'=>'/country/egypt/',
        'country:country=8'=>'/country/maldives/',
        'resort:country=4:region=19'=>'/country/turkey/alanya/',
        'resort:country=4:region=20'=>'/country/turkey/antalya/',
        'resort:country=4:region=21'=>'/country/turkey/belek/',
        'resort:country=4:region=22'=>'/country/turkey/kemer/',
        'resort:country=4:region=23'=>'/country/turkey/side/',
        'resort_month:country=4:region=20:period=2026-09'=>'/country/turkey/antalya/september/',
        'country_month:country=8:period=2026-09'=>'/country/maldives/september/',
    ];
}
