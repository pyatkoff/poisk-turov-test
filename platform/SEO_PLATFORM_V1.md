# AnyTour SEO Platform v1

## Goal
Control tens of thousands of potential URLs without mass-indexing low-value combinations.

## Ownership
`feature/seo-foundation` owns:
- URL/page registry;
- page types and SEO eligibility rules;
- title/description/H1 generation and manual overrides;
- canonical/indexability;
- redirects and retired URLs;
- sitemap generation;
- schema.org payload rules;
- internal linking rules;
- SEO readiness/quality gates;
- migration compatibility for legacy URLs.

It does not own visual page templates or search internals.

## Core rule
A routable URL and an indexable URL are different states. Generated combinations start non-indexable and become indexable only after passing policy and quality checks.

## Page registry states
- draft
- noindex
- index
- redirect
- gone

## Initial page types
- country
- resort
- hotel
- departure
- departure_country
- departure_resort
- country_month
- resort_month
- holiday_type
- holiday_type_country
- article

## Eligibility
A candidate page may become indexable only when its page-type rule passes. Rules can use:
- active underlying entities;
- real tour/product availability;
- sufficient inventory;
- uniqueness/canonical checks;
- required content blocks;
- internal-link availability;
- demand/business priority signals;
- technical quality score.

No Cartesian mass generation is indexable by default.

## Metadata
Generated metadata uses page-type templates and entity fields. Important URLs can override generated title, description, H1, canonical or schema fields. Resolution rule is `manual override ?? generated value`.

## Internal linking
Links are generated from entity relations and page priority, not manually embedded into PHP templates. Each page type defines allowed related page types and link budgets.

## Sitemap
Sitemap is generated only from registry rows in `index` state. Use sitemap index + type/shard files when volume grows.

## Redirects
Legacy and renamed URLs live in a redirect registry with destination, HTTP status, reason, timestamps and optional retirement rules. Query/attribution preservation must be explicitly tested where relevant.

## SEO readiness
Before `index`, validate at least:
- HTTP 200;
- unique URL;
- title/description/H1;
- canonical resolves correctly;
- no contradictory robots state;
- breadcrumbs;
- required content blocks;
- schema payload;
- internal links;
- no duplicate registry/canonical;
- mobile-safe render;
- live product/tour data when required by page type.

## Rollout
1. Build registry and rules with indexing closed.
2. Use Turkey as reference vertical slice.
3. Validate country -> resort -> hotel -> departure-country -> month/holiday intersections.
4. Open a small controlled cohort for indexing.
5. Measure crawl/index/traffic/conversion before scaling cohorts.
