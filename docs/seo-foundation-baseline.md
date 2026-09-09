# SEO foundation baseline

`seo-foundation-baseline.json` is the machine-readable source inventory for the
next SEO planning cycle. It is deliberately a **source baseline**, not a claim
about live crawl, traffic, rankings or conversion.

## What is currently owned

| Area | Source owner | Read-only validation owner |
| --- | --- | --- |
| Publication and path-selective release | `seo-launch-slice-v1.php`, publication manifest, sitemap candidates | `Validate V2 SEO publication manifest` |
| Canonical and indexability | `seo-config.php`, production identity collector/registry | `Verify anytoour.ru SEO launch` / production identity boundary |
| Sitemap | `robots.txt`, `sitemap.xml`, sitemap candidates | `Verify anytoour.ru SEO launch` |
| Internal links | internal-link groups, page graph and primitives | `Validate V2 SEO structure` |
| Schema | `seo-config.php`, structured-data helpers | structured-data smoke |
| V2 startup performance hygiene | bundle manifest, bundle endpoint and assets helper | `Validate V2 startup bundles` |

The checked-in static sitemap has 104 unique URLs: 3 country, 5 resort and 96
seasonal. It contains neither hotel-tour URLs nor the search route. Generated
Egypt/Maldives resort families are deliberately not counted here: their
materialized scope is validated by the existing live production collector.

## Safety boundary

This slice does not mount routes, change canonical tags, robots policy,
sitemap contents, schema, analytics/Metrika, Tourvisor, pricing or the lead
contract. It only gives future work a reproducible inventory and an ordered
evidence backlog.

The first next action is P1 in the JSON: reconcile the compatibility-route
documentation with the current canonical implementation, using read-only
render evidence and an explicit owner decision before any runtime change.
